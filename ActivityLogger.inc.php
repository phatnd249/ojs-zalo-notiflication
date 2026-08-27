<?php


/**
 * ActivityLogger cho Zalo Notification Plugin
 *
 * Ghi lại hoạt động của plugin, bao gồm thông tin người thực hiện (tác giả, editor...).
 */
class ActivityLogger
{
    private const LOG_FILENAME_PREFIX = 'zalo_notification_activity_context_';
    private const MAX_LOG_SIZE = 5 * 1024 * 1024;     // 5 MB
    private const LOG_RETENTION_DAYS = 90;
    private static $retentionChecked = [];
    private static $resolvedLogFiles = [];
    private static $submissionContexts = [];

    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';

    public const TYPE_SUBMISSION = 'SUBMISSION';
    public const TYPE_DECISION = 'DECISION';
    public const TYPE_PUBLISH = 'PUBLISH';
    public const TYPE_UNPUBLISH = 'UNPUBLISH';
    public const TYPE_ZALO_SEND = 'ZALO_SEND';
    public const TYPE_ERROR = 'ERROR';
    public const TYPE_REVIEW_REMINDER = 'REVIEW_REMINDER';
    public const TYPE_REVIEW_COMPLETED = 'REVIEW_COMPLETED';
    public const TYPE_REVIEW_REQUEST = 'REVIEW_REQUEST';
    public const TYPE_REVIEW_RESPONSE = 'REVIEW_RESPONSE';

    /**
     * Ghi log chung
     */
    public static function log(
        string $type,
        string $level,
        string $actor,           // Vai trò: Author, Editor, System...
        int $submissionId,
        string $title,
        string $action,
        array $extra = [],
        ?int $contextId = null
    ): void {
        $contextId = self::resolveContextId($submissionId, $contextId);
        $entry = [
            'time' => date('Y-m-d H:i:s'),
            'context_id' => $contextId,
            'type' => $type,
            'level' => $level,
            'actor' => $actor,
            'submission_id' => $submissionId,
            'title' => $title,
            'action' => $action,
        ];

        if (!empty($extra)) {
            $entry['extra'] = $extra;
        }

        self::writeEntry($entry, $contextId);
    }

    // ====================== Shortcut Methods ======================

    public static function logSubmission(int $submissionId, string $title, string $authorName, string $zaloStatus): void
    {
        self::log(
            self::TYPE_SUBMISSION,
            self::LEVEL_INFO,
            'Author',
            $submissionId,
            $title,
            "SUBMITTED_{$submissionId}",
            [
                'performed_by' => $authorName,
                'zalo_status' => $zaloStatus
            ]
        );
    }



    public static function logDecision(
        int $submissionId,
        string $title,
        string $authorName,
        int $stageId,
        string $stageName,
        int $decisionType,
        string $decisionDesc,
        string $editorName,
        string $zaloStatus
    ): void {
        self::log(
            self::TYPE_DECISION,
            self::LEVEL_INFO,
            'Editor',
            $submissionId,
            $title,
            "DECISION_{$submissionId}_{$stageId}_{$decisionType}",
            [
                'performed_by' => $editorName,
                'author' => $authorName,
                'stage_id' => $stageId,
                'stage_name' => $stageName,
                'decision' => $decisionDesc,
                'zalo_status' => $zaloStatus
            ]
        );
    }

    public static function logPublish(
        int $submissionId,
        int $publicationId,
        string $title,
        string $authorName,
        string $performedBy,
        string $datePublished,
        string $zaloStatus,
        string $issueString = ''
    ): void {
        $extra = [
            'performed_by' => $performedBy,
            'author' => $authorName,
            'date_published' => $datePublished,
            'zalo_status' => $zaloStatus
        ];
        if ($issueString) {
            $extra['issue'] = $issueString;
        }

        self::log(
            self::TYPE_PUBLISH,
            self::LEVEL_INFO,
            'System',
            $submissionId,
            $title,
            "PUBLISHED_{$submissionId}_{$publicationId}",
            $extra
        );
    }
    public static function logUnpublish(
        int $submissionId,
        int $publicationId,
        string $title,
        string $authorName,
        string $performedBy,
        string $zaloStatus
    ): void {
        self::log(
            self::TYPE_UNPUBLISH,
            self::LEVEL_WARNING,
            'System/Editor',
            $submissionId,
            $title,
            "UNPUBLISHED_{$submissionId}_{$publicationId}_" . time(),
            [
                'performed_by' => $performedBy,
                'author' => $authorName,
                'zalo_status' => $zaloStatus
            ]
        );
    }

    public static function logReviewReminder(
        int $submissionId,
        string $title,
        string $reviewerName,
        string $deadline,
        int $daysLeft,
        string $zaloStatus,
        string $reminderType,
        ?string $actionKey = null,
        ?int $contextId = null
    ): void {
        if ($daysLeft < 0) {
            $timeText = 'quá hạn ' . abs($daysLeft) . ' ngày';
        } elseif ($daysLeft === 0) {
            $timeText = 'đến hạn hôm nay';
        } else {
            $timeText = "còn {$daysLeft} ngày";
        }
        $action = "Nhắc nhở phản biện ({$reminderType}) cho: {$reviewerName} (hạn chót: {$deadline}, {$timeText}). Zalo Status: {$zaloStatus}";
        self::log(
            self::TYPE_REVIEW_REMINDER,
            self::isGoodStatus($zaloStatus) ? self::LEVEL_INFO : self::LEVEL_WARNING,
            'System',
            $submissionId,
            $title,
            $actionKey ?: $action,
            [
                'reviewerName' => $reviewerName,
                'deadline' => $deadline,
                'daysLeft' => $daysLeft,
                'zalo_status' => $zaloStatus,
                'reminder_type' => $reminderType,
                'action_detail' => $action
            ],
            $contextId
        );
    }

    public static function logReviewCompleted(
        int $submissionId,
        string $title,
        string $reviewerName,
        string $recommendation,
        string $zaloStatus,
        ?string $actionKey = null
    ): void {
        $actionDetail = "Phản biện viên {$reviewerName} đã nộp đánh giá. Đề xuất: {$recommendation}. Zalo Status: {$zaloStatus}";
        self::log(
            self::TYPE_REVIEW_COMPLETED,
            self::isGoodStatus($zaloStatus) ? self::LEVEL_INFO : self::LEVEL_WARNING,
            $reviewerName,
            $submissionId,
            $title,
            $actionKey ?: $actionDetail,
            [
                'reviewerName' => $reviewerName,
                'recommendation' => $recommendation,
                'zalo_status' => $zaloStatus,
                'action_detail' => $actionDetail
            ]
        );
    }

    public static function logReviewRequest(
        int $submissionId,
        string $title,
        string $reviewerName,
        string $deadline,
        string $zaloStatus,
        ?string $actionKey = null
    ): void {
        $actionDetail = "OJS gửi lời mời phản biện bài báo cho: {$reviewerName} (hạn nộp: {$deadline}). Zalo Status: {$zaloStatus}";
        self::log(
            self::TYPE_REVIEW_REQUEST,
            self::isGoodStatus($zaloStatus) ? self::LEVEL_INFO : self::LEVEL_WARNING,
            'System',
            $submissionId,
            $title,
            $actionKey ?: $actionDetail,
            [
                'reviewerName' => $reviewerName,
                'deadline' => $deadline,
                'zalo_status' => $zaloStatus,
                'action_detail' => $actionDetail
            ]
        );
    }

    public static function logReviewResponse(
        int $submissionId,
        string $title,
        string $reviewerName,
        string $responseStatus,
        string $deadline,
        string $zaloStatus,
        ?string $actionKey = null
    ): void {
        $actionDetail = "Phản biện viên {$reviewerName}: {$responseStatus}. Hạn nộp: {$deadline}. Zalo Status: {$zaloStatus}";
        self::log(
            self::TYPE_REVIEW_RESPONSE,
            self::isGoodStatus($zaloStatus) ? self::LEVEL_INFO : self::LEVEL_WARNING,
            $reviewerName,
            $submissionId,
            $title,
            $actionKey ?: $actionDetail,
            [
                'reviewerName' => $reviewerName,
                'response_status' => $responseStatus,
                'deadline' => $deadline,
                'zalo_status' => $zaloStatus,
                'action_detail' => $actionDetail
            ]
        );
    }

    public static function logZaloResult(
        int $submissionId,
        string $title,
        string $status,
        string $detail = '',
        ?int $contextId = null
    ): void
    {
        $level = self::isGoodStatus($status) ? self::LEVEL_INFO : self::LEVEL_WARNING;

        self::log(
            self::TYPE_ZALO_SEND,
            $level,
            'System',
            $submissionId,
            $title,
            'ZALO_SEND',
            [
                'status' => $status,
                'detail' => $detail
            ],
            $contextId
        );
    }

    public static function logError(
        string $source,
        string $message,
        int $submissionId = 0,
        ?\Throwable $exception = null,
        ?int $contextId = null
    ): void
    {
        $extra = [
            'source' => $source,
            'message' => $message
        ];

        if ($exception) {
            $extra['file'] = $exception->getFile() . ':' . $exception->getLine();
            $extra['trace'] = self::formatTrace($exception);
        }

        self::log(
            self::TYPE_ERROR,
            self::LEVEL_ERROR,
            'System',
            $submissionId,
            '',
            'ERROR',
            $extra,
            $contextId
        );
    }

    // ====================== Đọc Log (cho tab Activity Log) ======================

    /**
     * Đọc N dòng log gần nhất, trả về mảng các entry đã parse.
     * Kết quả sắp xếp mới nhất trước.
     *
     * @param  int    $limit  Số dòng tối đa cần lấy (mặc định 100)
     * @param  string|null $filterType  Lọc theo type (null = tất cả)
     * @return array  Mảng các entry (associative array)
     */
    public static function getRecentEntries(int $limit = 100, ?string $filterType = null, ?int $contextId = null): array
    {
        $contextId = self::resolveContextId(0, $contextId);
        $logFile = self::getLogFilePath($contextId);
        self::enforceRetention($logFile);
        $logFiles = self::getLogFiles($logFile, true);
        if (empty($logFiles)) {
            return [];
        }

        // Đọc cả file hiện tại và các file đã xoay, từ cũ đến mới.
        $entries = [];
        foreach ($logFiles as $file) {
            $handle = @fopen($file, 'r');
            if (!$handle) {
                continue;
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    $data = json_decode($line, true);
                    if (!is_array($data)) {
                        continue;
                    }

                    $data = self::repairMojibake($data);
                    if ($filterType !== null && (!isset($data['type']) || $data['type'] !== $filterType)) {
                        continue;
                    }

                    $entries[] = $data;
                }
            } finally {
                fclose($handle);
            }
        }

        // Đảo ngược để mới nhất trước, rồi giới hạn số lượng
        $entries = array_reverse($entries);
        return array_slice($entries, 0, $limit);
    }

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
     * Trả về đường dẫn file log hiện tại (public cho debug/tab).
     */
    public static function getLogFileLocation(?int $contextId = null): string
    {
        $contextId = self::resolveContextId(0, $contextId);
        return self::getLogFilePath($contextId);
    }

    // ====================== Deduplication & Write ======================

    /**
     * Kiểm tra xem hành động đã được ghi log TRONG VÒNG $windowSeconds giây gần đây chưa.
     * Mặc định: 300 giây (5 phút) — cho phép lặp lại sau 5 phút, nhưng chặn double-click.
     */
    public static function isAlreadyLogged(string $actionKey, int $windowSeconds = 300, ?int $contextId = null): bool
    {
        $contextId = self::resolveContextId(0, $contextId);
        $logFile = self::getLogFilePath($contextId);
        self::enforceRetention($logFile);
        $logFiles = self::getLogFiles($logFile, false);
        if (empty($logFiles)) return false;

        $cutoff = time() - $windowSeconds;
        foreach ($logFiles as $file) {
            $handle = @fopen($file, 'r');
            if (!$handle) continue;

            try {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $data = json_decode($line, true);
                    if (!$data || !isset($data['action'])) continue;

                    if ($data['action'] === $actionKey && isset($data['time'])) {
                        $entryTime = strtotime($data['time']);
                        if ($entryTime !== false && $entryTime >= $cutoff) {
                            return true;
                        }
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        return false;
    }

    /**
     * Trả về đường dẫn file log an toàn.
     * Ưu tiên ghi vào thư mục data (files_dir/zalo_notification hoặc tmp),
     * fallback về thư mục plugin nếu cần.
     */
    private static function getLogFilePath(int $contextId): string
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
        self::$resolvedLogFiles[$contextId] = __DIR__ . '/' . $filename;
        if (file_exists(self::$resolvedLogFiles[$contextId])) {
            @chmod(self::$resolvedLogFiles[$contextId], 0600);
        }
        return self::$resolvedLogFiles[$contextId];
    }

    /**
     * Xóa bản ghi quá thời hạn lưu. Việc kiểm tra chỉ chạy tối đa một lần/ngày.
     */
    private static function enforceRetention(string $logFile): void
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

        $cutoff = time() - (self::LOG_RETENTION_DAYS * 86400);
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

    private static function isGoodStatus(string $status): bool
    {
        $status = trim($status);
        if ($status === '') {
            return false;
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($status, 'UTF-8')
            : strtolower($status);

        return strpos($normalized, 'thành công') !== false
            || strpos($normalized, 'success') !== false
            || strpos($normalized, 'đã xếp hàng') !== false
            || strpos($normalized, 'queued') !== false
            || strpos($normalized, 'skipped') !== false
            || strpos($normalized, 'log_only') !== false
            || strpos($normalized, 'bỏ qua') !== false;
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
    private static function getLogFiles(string $logFile, bool $oldestFirst): array
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

    private static function writeEntry(array $entry, int $contextId): void
    {
        $logFile = self::getLogFilePath($contextId);
        self::enforceRetention($logFile);
        self::rotateIfNeeded($logFile);
        $entry = self::repairMojibake($entry);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        $written = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        if ($written === false && dirname($logFile) !== __DIR__) {
            $fallback = __DIR__ . '/' . self::LOG_FILENAME_PREFIX . max(0, $contextId) . '.log';
            self::debugWrite("Không ghi được activity log tại {$logFile}, chuyển sang {$fallback}");
            $written = @file_put_contents($fallback, $line, FILE_APPEND | LOCK_EX);
            $logFile = $fallback;
        }

        if ($written === false) {
            self::debugWrite("Không ghi được activity log tại {$logFile}");
        } else {
            @chmod($logFile, 0600);
        }
    }

    /**
     * Xác định context của bản ghi. Context truyền trực tiếp luôn được ưu tiên;
     * nếu không có thì tra submission, cuối cùng mới dùng request hiện tại.
     */
    private static function resolveContextId(int $submissionId = 0, ?int $contextId = null): int
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

    private static function formatTrace(\Throwable $e): string
    {
        $lines = explode("\n", $e->getTraceAsString());
        return implode(' | ', array_slice($lines, 0, 6));
    }

    private static function repairMojibake($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::repairMojibake($item);
            }
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        if (!self::hasMojibakeMarker($value)) {
            return $value;
        }

        $fixed = $value;
        for ($i = 0; $i < 3; $i++) {
            if (!function_exists('iconv')) {
                break;
            }
            $converted = @iconv('UTF-8', 'Windows-1252//IGNORE', $fixed);
            if ($converted === false || $converted === '' || $converted === $fixed) {
                break;
            }
            $fixed = $converted;
            if (!self::hasMojibakeMarker($fixed)) {
                break;
            }
        }

        return $fixed;
    }

    private static function hasMojibakeMarker(string $value): bool
    {
        foreach (["\xC3\x83", "\xC3\x82", "\xC3\x84"] as $marker) {
            if (strpos($value, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function debugWrite(string $message): void
    {
        ZaloNotificationPlugin::writeSecureDebug($message, 'ActivityLogger');
    }
}
