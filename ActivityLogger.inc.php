<?php

require_once(__DIR__ . '/services/activity/ActivityLogStorage.inc.php');
require_once(__DIR__ . '/services/activity/ActivityLogReader.inc.php');

/**
 * ActivityLogger cho Zalo Notification Plugin
 *
 * Ghi lại hoạt động của plugin, bao gồm thông tin người thực hiện (tác giả, editor...).
 */
class ActivityLogger
{
    public const READ_TAIL_BYTES = 2 * 1024 * 1024;

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
            'actor' => self::sanitizePii($actor),
            'submission_id' => $submissionId,
            'title' => self::sanitizePii($title),
            'action' => self::sanitizePii($action),
        ];

        if (!empty($extra)) {
            $entry['extra'] = self::sanitizePii($extra);
        }

        ActivityLogStorage::writeEntry($entry, $contextId);
    }

    /**
     * Khử trùng và che giấu các trường dữ liệu định danh cá nhân nhạy cảm (PII: SĐT, Email)
     * trước khi ghi vào tệp nhật ký trên đĩa.
     */
    public static function sanitizePii($data)
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $val) {
                $cleaned[$key] = self::sanitizePii($val);
            }
            return $cleaned;
        }

        if (!is_string($data) || $data === '') {
            return $data;
        }

        // Che số điện thoại Việt Nam dạng 10-11 số (09x, 03x, 07x, 08x, 84x...)
        $data = preg_replace('/(\b(?:0|\+?84)[1-9]\d{1,2})\d{4}(\d{2,3}\b)/', '$1****$2', $data);

        // Che email
        $data = preg_replace_callback('/([a-zA-Z0-9_.+-])[a-zA-Z0-9_.+-]*@([a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+)/', function ($m) {
            return $m[1] . '***@' . $m[2];
        }, $data);

        return $data;
    }

    // ====================== Shortcut Methods ======================

    public static function logSubmission(int $submissionId, string $title, string $authorName, string $zaloStatus): void
    {
        self::log(
            self::TYPE_SUBMISSION,
            self::isGoodStatus($zaloStatus) ? self::LEVEL_INFO : self::LEVEL_WARNING,
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
            self::isGoodStatus($zaloStatus) ? self::LEVEL_INFO : self::LEVEL_WARNING,
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
            self::isGoodStatus($zaloStatus) ? self::LEVEL_INFO : self::LEVEL_WARNING,
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
            "UNPUBLISHED_{$submissionId}_{$publicationId}",
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

    public static function getDailySummary(?string $selectedDate = null, ?int $contextId = null): array
    {
        return ActivityLogReader::getDailySummary($selectedDate, $contextId);
    }

    public static function getRecentEntries(int $limit = 100, ?string $filterType = null, ?int $contextId = null): array
    {
        return ActivityLogReader::getRecentEntries($limit, $filterType, $contextId);
    }

    public static function clearLog(?int $contextId = null): bool
    {
        return ActivityLogStorage::clearLog($contextId);
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

    public static function isAlreadyLogged(string $actionKey, int $windowSeconds = 300, ?int $contextId = null): bool
    {
        return ActivityLogReader::isAlreadyLogged($actionKey, $windowSeconds, $contextId);
    }

    public static function getLogFilePath(int $contextId): string
    {
        return ActivityLogStorage::getLogFilePath($contextId);
    }

    public static function getRetentionDays(?int $contextId = null): int
    {
        return ActivityLogStorage::getRetentionDays($contextId);
    }

    public static function runMaintenance(): void
    {
        ActivityLogStorage::runMaintenance();
    }

    public static function resolveContextId(int $submissionId = 0, ?int $contextId = null): int
    {
        return ActivityLogStorage::resolveContextId($submissionId, $contextId);
    }

    public static function getLogFiles(string $logFile, bool $oldestFirst): array
    {
        return ActivityLogStorage::getLogFiles($logFile, $oldestFirst);
    }

    public static function isGoodStatus(string $status): bool
    {
        return ActivityLogStorage::isGoodStatus($status);
    }

    private static function formatTrace(\Throwable $e): string
    {
        $lines = explode("\n", $e->getTraceAsString());
        return implode(' | ', array_slice($lines, 0, 6));
    }

    public static function sanitizeUtf8($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::sanitizeUtf8($item);
            }
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            return function_exists('mb_convert_encoding')
                ? mb_convert_encoding($value, 'UTF-8', 'UTF-8')
                : $value;
        }

        return $value;
    }

    public static function debugWrite(string $message): void
    {
        ZaloNotificationPlugin::writeSecureDebug($message, 'ActivityLogger');
    }
}
