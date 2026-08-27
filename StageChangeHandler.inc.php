<?php

/**
 * Facade tương thích cho các hook OJS.
 * Nghiệp vụ được tách sang các handler theo nhóm sự kiện.
 */
class StageChangeHandler
{
    protected static $plugin = null;

    public static function setPlugin($plugin): void
    {
        self::$plugin = $plugin;
    }

    /**
     * Đọc cấu hình Zalo API từ plugin settings.
     */
    protected static function getZaloSettings(?int $contextId = null): array
    {
        if (!self::$plugin) {
            self::writeDebug("getZaloSettings: Plugin chưa được set, không có cấu hình.");
            return ['botId' => '', 'apiKey' => '', 'recipientGroups' => []];
        }

        try {
            if ($contextId !== null && $contextId > 0) {
                return self::$plugin->getZaloSettingsForContext($contextId);
            }
            return self::$plugin->getZaloSettings();
        } catch (\Throwable $e) {
            self::writeDebug("getZaloSettings LỖI: " . $e->getMessage());
            return ['botId' => '', 'apiKey' => '', 'recipientGroups' => []];
        }
    }

    protected static function getAuthorPhones($submission): array
    {
        return NotificationRecipientResolver::getAuthorPhones($submission);
    }

    protected static function getEditorPhones(int $submissionId): array
    {
        return NotificationRecipientResolver::getEditorPhones($submissionId);
    }

    protected static function excludeEventGroupPhones(string $eventType, array $phones, array $settings): array
    {
        return NotificationRecipientResolver::excludeEventGroupPhones($eventType, $phones, $settings);
    }

    /**
     * Gửi mẫu dành cho biên tập viên tới cả nhóm cấu hình và BTV được gán vào bài.
     * Số đã có trong nhóm cấu hình sẽ bị loại khỏi lượt gửi trực tiếp để tránh nhận hai lần.
     */
    protected static function sendToEditorAudience(
        $submission,
        string $eventType,
        string $message,
        array $settings,
        ?string $assignedEditorEventType = null
    ): string {
        if (!$submission) {
            return 'Thất bại (Không xác định được bài báo)';
        }

        $submissionId = (int) $submission->getId();
        $contextId = self::getSubmissionContextId($submission);
        $groupStatus = ZaloApiClient::sendForEvent(
            $eventType,
            $message,
            (string) ($settings['botId'] ?? ''),
            (string) ($settings['apiKey'] ?? ''),
            isset($settings['recipientGroups']) && is_array($settings['recipientGroups'])
                ? $settings['recipientGroups']
                : [],
            $contextId
        );

        $editorPhones = self::excludeEventGroupPhones(
            $eventType,
            self::getEditorPhones($submissionId),
            $settings
        );
        $editorStatus = empty($editorPhones)
            ? 'Bỏ qua (Không có BTV được gán ngoài nhóm cấu hình)'
            : ZaloApiClient::sendToPhones(
                $message,
                (string) ($settings['botId'] ?? ''),
                (string) ($settings['apiKey'] ?? ''),
                $editorPhones,
                $assignedEditorEventType ?: $eventType . '_ASSIGNED_EDITOR',
                $contextId
            );

        self::writeDebug(
            "sendToEditorAudience [{$eventType}] submission #{$submissionId}: "
            . 'nhóm=' . $groupStatus . ' | BTV được gán=' . $editorStatus
        );
        return 'Nhóm cấu hình: ' . $groupStatus . ' | BTV được gán: ' . $editorStatus;
    }

    protected static function getReviewerPhones(int $submissionId): array
    {
        return NotificationRecipientResolver::getReviewerPhones($submissionId);
    }

    protected static function getSubmissionContextId($submission): int
    {
        if ($submission) {
            if (method_exists($submission, 'getContextId')) {
                $contextId = (int) $submission->getContextId();
                if ($contextId > 0) {
                    return $contextId;
                }
            }
            if (method_exists($submission, 'getData')) {
                $contextId = (int) $submission->getData('contextId');
                if ($contextId > 0) {
                    return $contextId;
                }
            }
        }
        return ZaloSettingsProvider::getCurrentContextId();
    }

    protected static function resultToArray($result): array
    {
        return NotificationRecipientResolver::resultToArray($result);
    }

    protected static function shouldSendTemplate($template): bool
    {
        $template = trim((string) $template);
        if ($template === '') {
            return false;
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($template, 'UTF-8')
            : strtolower($template);
        return strpos($normalized, 'bạn không có thông báo') === false
            && strpos($normalized, 'không có thông báo') === false
            && strpos($normalized, 'khÃ´ng cÃ³ thÃ´ng bÃ¡o') === false;
    }


    public static function handleEditorAssigned($submission, int $editorId, int $userGroupId, int $stageId = 0): void
    {
        EditorialNotificationHandler::handleEditorAssigned($submission, $editorId, $userGroupId, $stageId);
    }

    public static function checkOverdueReviewDeadlines($plugin = null, int $contextId = 0): void
    {
        ReviewNotificationHandler::checkOverdueReviewDeadlines($plugin, $contextId);
    }

    public static function handleReviewerAssigned($submission, int $reviewerId): void
    {
        ReviewNotificationHandler::handleReviewerAssigned($submission, $reviewerId);
    }

    public static function handleReviewDueDatesSet($reviewAssignment, $reviewer = null, $reviewDueDate = null, $responseDueDate = null): void
    {
        ReviewNotificationHandler::handleReviewDueDatesSet($reviewAssignment, $reviewer, $reviewDueDate, $responseDueDate);
    }

    public static function handleReviewerResponse($reviewAssignment, $submission, bool $decline): void
    {
        ReviewNotificationHandler::handleReviewerResponse($reviewAssignment, $submission, $decline);
    }

    public static function handleReviewerResponseFromHook($submission, $email, bool $decline): void
    {
        ReviewNotificationHandler::handleReviewerResponseFromHook($submission, $email, $decline);
    }

    public static function handleReviewAssignmentStatusChanged($submission, $reviewAssignment, string $status): void
    {
        ReviewNotificationHandler::handleReviewAssignmentStatusChanged($submission, $reviewAssignment, $status);
    }

    public static function handleReviewerReviewCompleted($form): void
    {
        ReviewNotificationHandler::handleReviewerReviewCompleted($form);
    }

    public static function handleSubmissionSubmitStep4Form($form): void
    {
        SubmissionNotificationHandler::handleSubmissionSubmitStep4Form($form);
    }

    public static function handleAuthorRevisionUploaded($submissionFile, $request = null): void
    {
        SubmissionNotificationHandler::handleAuthorRevisionUploaded($submissionFile, $request);
    }

    public static function handleSubmission($submission, $oldSubmission = null, string $source = '')
    {
        return SubmissionNotificationHandler::handleSubmission($submission, $oldSubmission, $source);
    }

    public static function handleAuthorEnteredReview($submission, int $previousStageId, int $currentStageId): void
    {
        SubmissionNotificationHandler::handleAuthorEnteredReview($submission, $previousStageId, $currentStageId);
    }

    public static function handlePublish($publication, $submission)
    {
        return PublicationNotificationHandler::handlePublish($publication, $submission);
    }

    public static function handleUnpublish($publication, $submission)
    {
        return PublicationNotificationHandler::handleUnpublish($publication, $submission);
    }

    public static function handleDecision33($submission, $editorDecision)
    {
        return EditorialNotificationHandler::handleDecision33($submission, $editorDecision);
    }

    public static function handleMailEvent($mail, $plugin)
    {
        return EditorialNotificationHandler::handleMailEvent($mail, $plugin);
    }

    protected static function writeDebug(string $message): void
    {
        ZaloNotificationPlugin::writeSecureDebug($message, 'StageChangeHandler');
    }
}
