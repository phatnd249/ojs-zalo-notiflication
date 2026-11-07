<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');

/** Gửi các thông báo đã được hook OJS đưa vào outbox. */
class ZaloOutboxTask extends ScheduledTask
{
    /** @var ZaloNotificationPlugin|null */
    private $plugin;

    public function __construct($args = [])
    {
        PluginRegistry::loadCategory('generic', true);
        $this->plugin = PluginRegistry::getPlugin('generic', 'zalonotificationplugin');
        parent::__construct($args);
    }

    public function getName()
    {
        return 'Zalo notification outbox';
    }

    protected function executeActions()
    {
        if (!$this->plugin || !ZaloOutboxRepository::ensureSchema()) {
            $this->addExecutionLogEntry('Zalo plugin hoặc bảng outbox chưa sẵn sàng.', SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
            return false;
        }

        $rows = ZaloOutboxRepository::claimBatch(10);
        $sent = 0;
        $failed = 0;
        $released = 0;
        $deadline = microtime(true) + 20;

        foreach ($rows as $index => $row) {
            if (microtime(true) >= $deadline) {
                foreach (array_slice($rows, $index) as $pendingRow) {
                    ZaloOutboxRepository::releaseClaim((int) $pendingRow->outbox_id);
                    $released++;
                }
                break;
            }

            $outboxId = (int) $row->outbox_id;
            $contextId = (int) $row->context_id;
            $attempts = (int) $row->attempts;
            $eventType = (string) $row->event_type;

            try {
                if (!$this->plugin->getEnabled($contextId)) {
                    ZaloOutboxRepository::markFailed($outboxId, 5, 'Plugin đã bị tắt trước khi gửi');
                    $failed++;
                    continue;
                }

                $settings = ZaloSettingsProvider::getForContext($contextId);
                $recipients = json_decode((string) $row->recipients_json, true);
                if (!is_array($recipients) || empty($recipients)) {
                    ZaloOutboxRepository::markFailed($outboxId, 5, 'Danh sách người nhận không hợp lệ');
                    $failed++;
                    continue;
                }

                $status = ZaloApiClient::sendToPhonesNow(
                    (string) $row->message_text,
                    (string) $settings['botId'],
                    (string) $settings['apiKey'],
                    $recipients
                );

                if ($status === 'Thành công') {
                    ZaloOutboxRepository::markSent($outboxId);
                    $sent++;
                } else {
                    $isRetryable = ZaloApiClient::isRetryableStatus($status);
                    ZaloOutboxRepository::markFailed($outboxId, $attempts, $status, !$isRetryable);
                    $failed++;
                }

                ActivityLogger::logZaloResult(
                    0,
                    $eventType,
                    $status,
                    'Outbox #' . $outboxId . ', lần thử ' . $attempts,
                    $contextId
                );

                // Khi gặp HTTP 429 hoặc lỗi rate limit từ gateway/Zalo -> Tạm dừng đợt gửi hiện tại ngay lập tức
                if (preg_match('/Mã lỗi HTTP:\s*429/', $status) || stripos($status, 'rate limit') !== false || stripos($status, 'too many requests') !== false) {
                    ZaloNotificationPlugin::writeSecureDebug(
                        "ZaloOutboxTask: Phát hiện Rate Limit từ Zalo/Gateway ({$status}). Tạm dừng đợt gửi worker outbox.",
                        'ZaloOutboxTask'
                    );
                    foreach (array_slice($rows, $index + 1) as $pendingRow) {
                        ZaloOutboxRepository::releaseClaim((int) $pendingRow->outbox_id);
                        $released++;
                    }
                    break;
                }

                // Giãn cách an toàn (pacing) giữa các tin gửi liên tiếp: 1.0s - 1.5s
                if ($index < count($rows) - 1) {
                    usleep(mt_rand(1000000, 1500000));
                }
            } catch (\Throwable $e) {
                ZaloOutboxRepository::markFailed($outboxId, $attempts, 'Lỗi worker: ' . $e->getMessage());
                ZaloNotificationPlugin::writeSecureDebug(
                    'Outbox #' . $outboxId . ' lỗi worker: ' . $e->getMessage(),
                    'ZaloOutboxTask'
                );
                $failed++;
            }
        }

        $deleted = ZaloOutboxRepository::cleanup();
        ActivityLogger::runMaintenance();
        $this->addExecutionLogEntry(
            'Đã nhận ' . count($rows) . ' bản ghi; gửi thành công ' . $sent
                . '; chưa gửi ' . $failed . '; trả lại ' . $released . '; dọn ' . $deleted . ' bản ghi cũ.'
        );
        return true;
    }
}
