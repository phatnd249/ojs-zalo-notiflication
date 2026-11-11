<?php

import('lib.pkp.classes.core.JSONMessage');

/**
 * Controller xử lý Activity Log, thống kê hàng ngày và xuất file nhật ký.
 */
class ZaloActivityLogController
{
    /**
     * Danh sách các loại log hợp lệ.
     */
    public static function getValidTypes(): array
    {
        return [
            ActivityLogger::TYPE_SUBMISSION,
            ActivityLogger::TYPE_DECISION,
            ActivityLogger::TYPE_PUBLISH,
            ActivityLogger::TYPE_UNPUBLISH,
            ActivityLogger::TYPE_ZALO_SEND,
            ActivityLogger::TYPE_ERROR,
            ActivityLogger::TYPE_REVIEW_REQUEST,
            ActivityLogger::TYPE_REVIEW_RESPONSE,
            ActivityLogger::TYPE_REVIEW_REMINDER,
            ActivityLogger::TYPE_REVIEW_COMPLETED,
        ];
    }

    /**
     * Xử lý hiển thị tab Activity Log.
     */
    public static function handleActivityLog(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $filterType = $request->getUserVar('filterType');
        $validTypes = self::getValidTypes();
        if ($filterType && !in_array($filterType, $validTypes, true)) {
            $filterType = null;
        }

        $context = $request->getContext();
        $contextId = $context ? (int) $context->getId() : 0;
        $statsDate = (string) ($request->getUserVar('statsDate') ?? date('Y-m-d'));
        $entries = ActivityLogger::getRecentEntries(PHP_INT_MAX, $filterType, $contextId);
        $outboxStats = ZaloOutboxRepository::getStats($contextId);
        $dailySummary = ActivityLogger::getDailySummary($statsDate, $contextId);

        $logRetentionDays = (int) ($plugin->getSetting($contextId, 'logRetentionDays') ?: 90);
        if ($logRetentionDays <= 0) {
            $logRetentionDays = 90;
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'entries' => $entries,
            'filterType' => $filterType,
            'totalEntries' => count($entries),
            'pluginName' => $plugin->getName(),
            'outboxStats' => $outboxStats,
            'dailyStats' => $dailySummary,
            'selectedStatsDate' => $statsDate,
            'logRetentionDays' => $logRetentionDays,
            'statsActionUrl' => $plugin->getManageUrl($request, 'dailySummaryStats'),
        ]);

        return new JSONMessage(true, $templateMgr->fetch($plugin->getTemplateResource('activityLog.tpl')));
    }

    /**
     * Trả về JSON thống kê tóm tắt theo ngày.
     */
    public static function handleDailySummaryStats(ZaloNotificationPlugin $plugin, $request): JSONMessage
    {
        $context = $request->getContext();
        $contextId = $context ? (int) $context->getId() : 0;
        $statsDate = (string) ($request->getUserVar('statsDate') ?? date('Y-m-d'));
        $dailySummary = ActivityLogger::getDailySummary($statsDate, $contextId);
        return new JSONMessage(true, $dailySummary);
    }

    /**
     * Xuất dữ liệu nhật ký ra tệp text/json để tải về.
     */
    public static function handleExportLog(ZaloNotificationPlugin $plugin, $request): void
    {
        $adminRoles = [
            defined('ROLE_ID_SITE_ADMIN') ? ROLE_ID_SITE_ADMIN : 1,
            defined('ROLE_ID_MANAGER') ? ROLE_ID_MANAGER : 16,
        ];
        if (!ZaloNotificationPlugin::isUserAuthorized($request, $adminRoles)) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Quyền truy cập bị từ chối.';
            exit;
        }

        $context = $request->getContext();
        $contextId = $context ? (int) $context->getId() : 0;
        $filterType = $request->getUserVar('filterType');
        $validTypes = self::getValidTypes();
        if ($filterType && !in_array($filterType, $validTypes, true)) {
            $filterType = null;
        }

        $logFileLocation = ActivityLogger::getLogFileLocation($contextId);
        $filename = $filterType
            ? preg_replace('/[^A-Z0-9_]/', '_', $filterType) . '_' . basename($logFileLocation)
            : basename($logFileLocation);

        header('Content-Description: File Transfer');
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Transfer-Encoding: binary');

        // Tắt bộ đệm đầu ra để giải phóng bộ nhớ (RAM < 2MB khi stream)
        while (ob_get_level()) {
            ob_end_clean();
        }

        $logFile = ActivityLogger::getLogFilePath($contextId);
        ActivityLogStorage::enforceRetention($logFile, $contextId);
        $logFiles = ActivityLogger::getLogFiles($logFile, true);

        $maxExportLines = (int) ($request->getUserVar('limit') ?: 10000);
        if ($maxExportLines <= 0) {
            $maxExportLines = 10000;
        }

        $output = fopen('php://output', 'w');
        $exportedCount = 0;

        foreach ($logFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $handle = @fopen($file, 'r');
            if (!$handle) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if ($filterType !== null) {
                    $entry = json_decode($line, true);
                    if (!is_array($entry) || ($entry['type'] ?? '') !== $filterType) {
                        continue;
                    }
                    $entry = ActivityLogger::sanitizeUtf8($entry);
                    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                }

                fwrite($output, $line . PHP_EOL);
                $exportedCount++;
                if ($exportedCount >= $maxExportLines) {
                    fclose($handle);
                    break 2;
                }
            }
            fclose($handle);
        }

        if (is_resource($output)) {
            fclose($output);
        }
        exit;
    }
}
