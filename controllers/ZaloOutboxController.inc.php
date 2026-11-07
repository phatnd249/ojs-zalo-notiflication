<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.core.JSONMessage');

/**
 * Controller xử lý Dashboard Hàng đợi & Thử lại tin nhắn (Outbox).
 */
class ZaloOutboxController
{
    public static function handleDashboardTab(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $context = $request->getContext();
        $contextId = $context ? (int) $context->getId() : 0;
        $statusFilter = (string) ($request->getUserVar('statusFilter') ?? 'all');

        $entries = ZaloOutboxRepository::getOutboxEntries($contextId, $statusFilter, 100);
        foreach ($entries as &$item) {
            $item['parsed_msg'] = self::parseMessage($item['message_text'] ?? null);
            $maskedRecipients = [];
            if (!empty($item['recipients']) && is_array($item['recipients'])) {
                foreach ($item['recipients'] as $phone) {
                    $maskedRecipients[] = self::maskPhone((string) $phone);
                }
            }
            $item['masked_recipients'] = $maskedRecipients;
        }
        unset($item);

        $stats = ZaloOutboxRepository::getStats($contextId);
        $outboxRetentionDays = (int) ($plugin->getSetting($contextId, 'outboxRetentionDays') ?: 30);
        if ($outboxRetentionDays <= 0) {
            $outboxRetentionDays = 30;
        }

        $stalePendingCount = 0;
        try {
            $staleCutoff = date('Y-m-d H:i:s', time() - 900);
            $stalePendingCount = Capsule::table('zalo_notification_outbox')
                ->where('context_id', $contextId)
                ->where('status', 'pending')
                ->where('available_at', '<', $staleCutoff)
                ->count();
        } catch (\Throwable $ignored) {
        }
        $hasStalePending = ($stalePendingCount > 0);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'entries' => $entries,
            'stats' => $stats,
            'totalItems' => count($entries),
            'currentFilter' => $statusFilter,
            'outboxRetentionDays' => $outboxRetentionDays,
            'outboxActionUrl' => $plugin->getManageUrl($request, 'outboxAction'),
            'hasStalePending' => $hasStalePending,
            'stalePendingCount' => $stalePendingCount,
        ]);
        return new JSONMessage(true, $templateMgr->fetch($plugin->getTemplateResource('outboxDashboard.tpl')));
    }

    public static function retryItem(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $context = $request->getContext();
        $contextId = $context ? (int) $context->getId() : 0;
        $outboxId = (int) $request->getUserVar('outboxId');
        if ($outboxId <= 0) {
            return new JSONMessage(false, 'ID tin nhắn không hợp lệ.');
        }

        $success = ZaloOutboxRepository::retryItem($outboxId, $contextId);
        if ($success) {
            $plugin->writeDebug("retryOutboxItem: Đã đưa tin #{$outboxId} về trạng thái chờ gửi");
            return new JSONMessage(true, "Tin nhắn #{$outboxId} đã được đưa về trạng thái Chờ gửi.");
        }
        return new JSONMessage(false, "Không thể cập nhật tin nhắn #{$outboxId}.");
    }

    public static function retryAll(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $context = $request->getContext();
        $contextId = $context ? (int) $context->getId() : 0;

        $user = $request ? $request->getUser() : null;
        $userId = $user ? (int) $user->getId() : 0;
        $waitSeconds = 0;
        if (!RateLimiter::checkCooldown("retry_all_outbox_{$contextId}_{$userId}", 10, $waitSeconds)) {
            return new JSONMessage(false, "Thao tác quá nhanh. Vui lòng chờ {$waitSeconds} giây trước khi gửi lại toàn bộ hàng đợi.");
        }

        $count = ZaloOutboxRepository::retryAllFailed($contextId);
        $plugin->writeDebug("retryAllOutbox: Đã đưa {$count} tin lỗi về trạng thái chờ gửi");
        return new JSONMessage(true, "Đã đưa {$count} tin nhắn lỗi về trạng thái Chờ gửi.");
    }

    public static function deleteItem(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $context = $request->getContext();
        $contextId = $context ? (int) $context->getId() : 0;
        $outboxId = (int) $request->getUserVar('outboxId');
        if ($outboxId <= 0) {
            return new JSONMessage(false, 'ID tin nhắn không hợp lệ.');
        }

        $success = ZaloOutboxRepository::deleteItem($outboxId, $contextId);
        if ($success) {
            $plugin->writeDebug("deleteOutboxItem: Đã xóa tin #{$outboxId}");
            return new JSONMessage(true, "Đã xóa tin nhắn #{$outboxId} khỏi hàng đợi.");
        }
        return new JSONMessage(false, "Không thể xóa tin nhắn #{$outboxId}.");
    }

    public static function clearSent(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $context = $request->getContext();
        $contextId = $context ? (int) $context->getId() : 0;

        $count = ZaloOutboxRepository::clearSent($contextId);
        $plugin->writeDebug("clearSentOutbox: Đã dọn dẹp {$count} tin đã gửi");
        return new JSONMessage(true, "Đã dọn dẹp {$count} tin nhắn đã gửi thành công.");
    }

    /**
     * Che bớt các số giữa của số điện thoại để bảo vệ quyền riêng tư (PII).
     */
    public static function maskPhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($clean);
        if ($len < 7) {
            return $clean;
        }
        return substr($clean, 0, 4) . '****' . substr($clean, -3);
    }

    /**
     * Phân tích và định dạng nội dung tin nhắn outbox thành cấu trúc tiêu đề,
     * các dòng thông tin chính và dòng chi tiết mở rộng kèm liên kết có thể bấm được.
     */
    public static function parseMessage(?string $raw): array
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return [
                'title' => '',
                'main_lines' => [],
                'more_lines' => [],
                'has_more' => false,
                'more_count' => 0,
            ];
        }

        $rawLines = preg_split('/\r\n|\r|\n/', $text);
        $cleanLines = [];
        foreach ($rawLines as $l) {
            $tl = trim($l);
            if ($tl !== '') {
                $cleanLines[] = $tl;
            }
        }

        $title = !empty($cleanLines) ? array_shift($cleanLines) : '';

        $formatLine = function (string $line): string {
            // Che số điện thoại xuất hiện trong nội dung tin nhắn
            $maskedLine = preg_replace('/(\b(?:0|\+?84)[1-9]\d{1,2})\d{4}(\d{2,3}\b)/', '$1****$2', $line);
            $escaped = htmlspecialchars($maskedLine, ENT_QUOTES, 'UTF-8');
            return (string) preg_replace(
                '/(https?:\/\/[^\s<]+)/',
                '<a href="$1" target="_blank" rel="noopener noreferrer" class="zod-link">$1</a>',
                $escaped
            );
        };

        $formattedLines = array_map($formatLine, $cleanLines);
        $main = array_slice($formattedLines, 0, 4);
        $more = array_slice($formattedLines, 4);

        return [
            'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            'main_lines' => $main,
            'more_lines' => $more,
            'has_more' => !empty($more),
            'more_count' => count($more),
        ];
    }
}
