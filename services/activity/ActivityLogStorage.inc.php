<?php

class ActivityLogStorage
{
    private const LOG_FILENAME_PREFIX = 'zalo_notification_activity_context_';
    private const MAX_LOG_SIZE = 5 * 1024 * 1024;
    private const LOG_RETENTION_DAYS = 90;
    private static $retentionChecked = [];
    private static $resolvedLogFiles = [];
    private static $submissionContexts = [];

    /**
     * Xóa toàn bộ file log.
     *
     * @return bool true nếu xóa thành công
     */
    public static function clearLog(?int $contextId = null): bool
    {
        $contextId = self::resolveContextId(0, $contextId);
        $logFile = self::getLogFilePath($contextId);
        $success = true;
        foreach (self::getLogFiles($logFile, true) as $file) {
            if (file_exists($file) && !@unlink($file)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Trả về đường dẫn file log an toàn.
     * Ưu tiên ghi vào thư mục data (files_dir/zalo_notification hoặc tmp),
     * fallback về thư mục plugin nếu cần.
     */
    public static function getLogFilePath(int $contextId): string
    {
        $contextId = max(0, $contextId);
        if (isset(self::$resolvedLogFiles[$contextId])) {
            return self::$resolvedLogFiles[$contextId];
        }

        $filename = self::LOG_FILENAME_PREFIX . $contextId . '.log';

        // Ưu tiên ghi vào thư mục data an toàn
        try {
            $dataDir = ZaloNotificationPlugin::getDataDir();
            $candidate = $dataDir . '/' . $filename;
            if (is_writable($dataDir) && (!file_exists($candidate) || is_writable($candidate))) {
                if (file_exists($candidate)) {
                    @chmod($candidate, 0600);
                }
                self::$resolvedLogFiles[$contextId] = $candidate;
                return self::$resolvedLogFiles[$contextId];
            }
        } catch (\Throwable $e) {
            // getDataDir chưa sẵn sàng
        }

        // Fallback cuối cùng: thư mục plugin
        self::$resolvedLogFiles[$contextId] = dirname(__DIR__, 2) . '/' . $filename;
        if (file_exists(self::$resolvedLogFiles[$contextId])) {
            @chmod(self::$resolvedLogFiles[$contextId], 0600);
        }
        return self::$resolvedLogFiles[$contextId];
    }

    private static function rotateIfNeeded(string $logFile): void
    {
        if (!file_exists($logFile) || filesize($logFile) < self::MAX_LOG_SIZE) {
            return;
        }

        $rotated = self::getRotatedLogFiles($logFile);
        if (!empty($rotated)) {
            krsort($rotated, SORT_NUMERIC);
            foreach ($rotated as $index => $source) {
                @rename($source, $logFile . '.' . ($index + 1));
            }
        }

        @rename($logFile, $logFile . '.1');
    }

    /**
     * Danh sách file log theo thứ tự thời gian. Không giới hạn số file xoay;
     * việc dọn dẹp dựa hoàn toàn vào thời hạn lưu 90 ngày.
     */
    public static function getLogFiles(string $logFile, bool $oldestFirst): array
    {
        $rotated = self::getRotatedLogFiles($logFile);
        if ($oldestFirst) {
            krsort($rotated, SORT_NUMERIC);
        } else {
            ksort($rotated, SORT_NUMERIC);
        }

        if ($oldestFirst) {
            $files = array_values($rotated);
            if (file_exists($logFile)) {
                $files[] = $logFile;
            }
        } else {
            $files = file_exists($logFile) ? [$logFile] : [];
            $files = array_merge($files, array_values($rotated));
        }

        return $files;
    }

    private static function getRotatedLogFiles(string $logFile): array
    {
        $files = [];
        $matches = glob($logFile . '.*');
        if ($matches === false) {
            return $files;
        }

        $prefixLength = strlen($logFile) + 1;
        foreach ($matches as $file) {
            $suffix = substr($file, $prefixLength);
            if ($suffix !== '' && ctype_digit($suffix) && is_file($file)) {
                $files[(int) $suffix] = $file;
            }
        }
        return $files;
    }

    public static function writeEntry(array $entry, int $contextId): void
    {
        $logFile = self::getLogFilePath($contextId);
        self::enforceRetention($logFile, $contextId);
        self::rotateIfNeeded($logFile);
        $entry = ActivityLogger::sanitizeUtf8($entry);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        $written = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        if ($written === false && dirname($logFile) !== dirname(__DIR__, 2)) {
            $fallback = dirname(__DIR__, 2) . '/' . self::LOG_FILENAME_PREFIX . max(0, $contextId) . '.log';
            ActivityLogger::debugWrite("Không ghi được activity log tại {$logFile}, chuyển sang {$fallback}");
            $written = @file_put_contents($fallback, $line, FILE_APPEND | LOCK_EX);
            $logFile = $fallback;
        }

        if ($written === false) {
            ActivityLogger::debugWrite("Không ghi được activity log tại {$logFile}");
        } else {
            @chmod($logFile, 0600);
        }
    }

    /**
     * Xóa bản ghi quá thời hạn lưu. Việc kiểm tra chỉ chạy tối đa một lần/ngày.
     */
    public static function enforceRetention(string $logFile, ?int $contextId = null): void
    {
        if (isset(self::$retentionChecked[$logFile])) {
            return;
        }
        self::$retentionChecked[$logFile] = true;

        $directory = dirname($logFile);
        $marker = $directory . '/.' . basename($logFile) . '.retention_check';
        if (file_exists($marker) && filemtime($marker) !== false && filemtime($marker) > time() - 86400) {
            return;
        }
        @touch($marker);
        @chmod($marker, 0600);

        if ($contextId === null || $contextId <= 0) {
            if (preg_match('/_context_(\d+)\.log$/', basename($logFile), $matches)) {
                $contextId = (int) $matches[1];
            }
        }
        $retentionDays = self::getRetentionDays($contextId);
        $cutoff = time() - ($retentionDays * 86400);
        foreach (self::getLogFiles($logFile, true) as $retentionFile) {
            if (!is_readable($retentionFile) || !is_writable($retentionFile)) {
                continue;
            }

            $retained = '';
            $handle = @fopen($retentionFile, 'r');
            if ($handle) {
                try {
                    while (($line = fgets($handle)) !== false) {
                        $data = json_decode(trim($line), true);
                        if (!is_array($data) || empty($data['time'])) {
                            continue;
                        }
                        $entryTime = strtotime((string) $data['time']);
                        if ($entryTime !== false && $entryTime >= $cutoff) {
                            $retained .= $line;
                        }
                    }
                } finally {
                    fclose($handle);
                }
                if ($retained === '' && $retentionFile !== $logFile) {
                    @unlink($retentionFile);
                } else {
                    @file_put_contents($retentionFile, $retained, LOCK_EX);
                    @chmod($retentionFile, 0600);
                }
            }
        }
    }

    public static function getRetentionDays(?int $contextId = null): int
    {
        $contextId = self::resolveContextId(0, $contextId);
        if ($contextId > 0 && class_exists('\DAORegistry')) {
            try {
                $days = (int) \DAORegistry::getDAO('PluginSettingsDAO')->getSetting($contextId, 'zalonotificationplugin', 'logRetentionDays');
                if ($days > 0) {
                    return $days;
                }
            } catch (\Throwable $e) {
            }
        }
        return self::LOG_RETENTION_DAYS;
    }

    public static function runMaintenance(): void
    {
        $directories = [];
        try {
            $directories[] = ZaloNotificationPlugin::getDataDir();
        } catch (\Throwable $e) {
        }
        $directories[] = dirname(__DIR__, 2);

        foreach (array_unique($directories) as $directory) {
            $files = glob($directory . '/' . self::LOG_FILENAME_PREFIX . '*.log');
            foreach ($files ?: [] as $logFile) {
                self::enforceRetention($logFile);
            }
        }
    }

    public static function isGoodStatus(string $status): bool
    {
        $status = trim($status);
        if ($status === '') {
            return false;
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($status, 'UTF-8')
            : strtolower($status);

        if (strpos($normalized, 'thất bại') !== false
            || strpos($normalized, 'lỗi') !== false
            || strpos($normalized, 'error') !== false
            || strpos($normalized, 'failed') !== false) {
            return false;
        }

        return strpos($normalized, 'thành công') !== false
            || strpos($normalized, 'success') !== false
            || strpos($normalized, 'đã xếp hàng') !== false
            || strpos($normalized, 'queued') !== false
            || strpos($normalized, 'skipped') !== false
            || strpos($normalized, 'log_only') !== false
            || strpos($normalized, 'bỏ qua') !== false;
    }

    /**
     * Xác định context của bản ghi. Context truyền trực tiếp luôn được ưu tiên;
     * nếu không có thì tra submission, cuối cùng mới dùng request hiện tại.
     */
    public static function resolveContextId(int $submissionId = 0, ?int $contextId = null): int
    {
        if ($contextId !== null) {
            return max(0, $contextId);
        }

        if ($submissionId > 0) {
            if (isset(self::$submissionContexts[$submissionId])) {
                return self::$submissionContexts[$submissionId];
            }
            try {
                if (class_exists('DAORegistry')) {
                    $submissionDao = \DAORegistry::getDAO('SubmissionDAO');
                    $submission = $submissionDao ? $submissionDao->getById($submissionId) : null;
                    if ($submission && method_exists($submission, 'getContextId')) {
                        $resolved = (int) $submission->getContextId();
                        self::$submissionContexts[$submissionId] = max(0, $resolved);
                        return self::$submissionContexts[$submissionId];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            if (class_exists('Application')) {
                $request = \Application::get()->getRequest();
                $context = $request ? $request->getContext() : null;
                if ($context) {
                    return max(0, (int) $context->getId());
                }
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }
}
