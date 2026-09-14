<?php

/** Xử lý lời mời, phản hồi, hạn và kết quả phản biện. */
class ReviewNotificationHandler extends StageChangeHandler
{
    public static function getDailyReminderKey(int $contextId, int $reviewId): string
    {
        return "REVIEW_REMINDER_DAILY_{$contextId}_{$reviewId}_" . date('Y-m-d');
    }

    public static function wasRemindedToday($reviewAssignment): bool
    {
        if (!$reviewAssignment || !method_exists($reviewAssignment, 'getDateReminded')) {
            return false;
        }
        $dateReminded = $reviewAssignment->getDateReminded();
        $timestamp = $dateReminded ? strtotime((string) $dateReminded) : false;
        return $timestamp !== false && date('Y-m-d', $timestamp) === date('Y-m-d');
    }

    /** Tìm đúng lượt phản biện từ người nhận của email nhắc tự động OJS. */
    public static function resolveReminderAssignmentFromMail(int $submissionId, $mail)
    {
        try {
            $recipients = $mail && method_exists($mail, 'getRecipients') ? $mail->getRecipients() : [];
            if (!is_array($recipients) || empty($recipients)) {
                return null;
            }

            $reviewerIds = [];
            foreach ($recipients as $recipient) {
                $email = is_array($recipient) ? (string) ($recipient['email'] ?? '') : '';
                if ($email === '') {
                    continue;
                }
                $user = self::getUserByEmail($email);
                if ($user) {
                    $reviewerIds[(int) $user->getId()] = true;
                }
            }
            if (empty($reviewerIds)) {
                return null;
            }

            $selected = null;
            $assignments = \DAORegistry::getDAO('ReviewAssignmentDAO')->getBySubmissionId($submissionId);
            foreach ($assignments as $assignment) {
                if (!isset($reviewerIds[(int) $assignment->getReviewerId()])) {
                    continue;
                }
                if ((method_exists($assignment, 'getCancelled') && $assignment->getCancelled())
                    || (method_exists($assignment, 'getDeclined') && $assignment->getDeclined())
                    || (method_exists($assignment, 'getDateCompleted') && $assignment->getDateCompleted())) {
                    continue;
                }
                if (!$selected || (int) $assignment->getId() > (int) $selected->getId()) {
                    $selected = $assignment;
                }
            }
            return $selected;
        } catch (\Throwable $e) {
            self::writeDebug('resolveReminderAssignmentFromMail lỗi: ' . $e->getMessage());
            return null;
        }
    }

    /** Xếp hàng một lời nhắc thủ công chỉ gửi tới phản biện viên được chọn. */
    public static function sendManualReminder($reviewAssignment, $submission): array
    {
        $reviewerName = 'Phản biện viên';
        try {
            if (!$reviewAssignment || !$submission || $reviewAssignment->getDateCompleted()
                || $reviewAssignment->getCancelled() || $reviewAssignment->getDeclined()) {
                return ['success' => false, 'reviewerName' => $reviewerName, 'message' => 'lượt phản biện không còn hiệu lực'];
            }

            $reviewer = \DAORegistry::getDAO('UserDAO')->getById((int) $reviewAssignment->getReviewerId());
            $reviewerName = $reviewer ? $reviewer->getFullName() : 'Phản biện viên #' . $reviewAssignment->getReviewerId();
            if (!$reviewAssignment->getDateNotified()) {
                return ['success' => false, 'reviewerName' => $reviewerName, 'message' => 'chưa gửi lời mời phản biện'];
            }
            $phone = $reviewer ? ZaloApiClient::normalizePhoneNumber((string) $reviewer->getPhone()) : '';
            if ($phone === '') {
                return ['success' => false, 'reviewerName' => $reviewerName, 'message' => 'thiếu số điện thoại hợp lệ'];
            }

            $templates = self::$plugin ? self::$plugin->getMessageTemplates() : [];
            $template = $templates['reviewer']['reminder'] ?? '';
            if (!self::shouldSendTemplate($template)) {
                return ['success' => false, 'reviewerName' => $reviewerName, 'message' => 'mẫu nhắc phản biện đang tắt'];
            }

            $dateDue = (string) $reviewAssignment->getDateDue();
            $dueTime = $dateDue ? strtotime($dateDue) : false;
            $today = strtotime(date('Y-m-d'));
            $dueDay = $dueTime ? strtotime(date('Y-m-d', $dueTime)) : false;
            $daysLeft = $dueDay === false ? 0 : (int) round(($dueDay - $today) / 86400);
            $daysText = $dueDay === false ? 'Chưa đặt hạn' : ($daysLeft < 0
                ? 'Quá hạn ' . abs($daysLeft) . ' ngày'
                : ($daysLeft === 0 ? 'Hết hạn hôm nay' : 'Còn ' . $daysLeft . ' ngày'));
            $publication = $submission->getCurrentPublication();
            $title = $publication ? strip_tags((string) $publication->getLocalizedTitle()) : 'Bài báo #' . $submission->getId();
            $data = array_merge(MessageHelper::getNavigationData($submission, (int) $reviewAssignment->getId()), [
                'title' => $title,
                'author' => MessageHelper::getAuthorsString($publication ?: $submission),
                'reviewerName' => $reviewerName,
                'deadline' => $dueTime ? date('d/m/Y', $dueTime) : 'Chưa đặt hạn',
                'daysLeft' => $daysText,
                'round' => max(1, (int) $reviewAssignment->getRound()),
                'timestamp' => date('d/m/Y H:i:s'),
            ]);
            $settings = self::getZaloSettings();
            // Thử gửi ngay trong request; nếu gateway lỗi thì tự xếp outbox retry.
            $status = ZaloApiClient::sendToPhones(
                MessageHelper::buildMessage($template, $data),
                $settings['botId'], $settings['apiKey'], [$phone],
                'REVIEW_REMINDER_MANUAL', self::getSubmissionContextId($submission)
            );
            $success = $status === 'Thành công';
            ActivityLogger::logReviewReminder(
                (int) $submission->getId(), $title, $reviewerName,
                $dueTime ? date('d/m/Y', $dueTime) : 'Chưa đặt hạn',
                $daysLeft, $status, 'Thủ công',
                'REVIEW_REMINDER_MANUAL_' . $reviewAssignment->getId() . '_' . date('YmdHis')
            );
            if ($success) {
                try {
                    $reviewAssignment->setDateReminded(date('Y-m-d H:i:s'));
                    $reviewAssignment->setReminderWasAutomatic(0);
                    \DAORegistry::getDAO('ReviewAssignmentDAO')->updateObject($reviewAssignment);
                } catch (\Throwable $updateError) {
                    self::writeDebug('sendManualReminder: Gateway đã nhận tin nhưng không cập nhật được date_reminded: ' . $updateError->getMessage());
                }
            }
            return ['success' => $success, 'reviewerName' => $reviewerName, 'message' => $status];
        } catch (\Throwable $e) {
            ActivityLogger::logError('sendManualReminder', $e->getMessage(), $submission ? (int) $submission->getId() : 0, $e);
            return ['success' => false, 'reviewerName' => $reviewerName, 'message' => 'không thể xếp hàng lời nhắc'];
        }
    }

    public static function checkOverdueReviewDeadlines($plugin = null, int $contextId = 0): void
    {
        $scanLockHandle = null;
        try {
            if ($plugin) {
                self::setPlugin($plugin);
            }

            // Không bao giờ quét toàn hệ thống khi không xác định được tạp chí.
            if ($contextId <= 0) {
                self::writeDebug('checkOverdueReviewDeadlines: Bỏ qua vì không xác định được context.');
                return;
            }

            // Khóa theo context chỉ ngăn hai worker quét đồng thời; lịch chạy do scheduled task quyết định.
            $scanLock = ZaloNotificationPlugin::getDataDir() . "/deadline_scan_context_{$contextId}.lock";
            $scanLockHandle = @fopen($scanLock, 'c');
            if (!$scanLockHandle || !@flock($scanLockHandle, LOCK_EX | LOCK_NB)) {
                return;
            }

            $result = \DAORegistry::getDAO('UserDAO')->retrieve(
                'SELECT ra.review_id, ra.submission_id, ra.reviewer_id, ra.date_due, ra.round
                   FROM review_assignments ra
                   JOIN submissions s ON s.submission_id = ra.submission_id
                  WHERE s.context_id = ?
                    AND ra.cancelled = 0
                    AND ra.declined = 0
                    AND ra.date_completed IS NULL
                    AND ra.date_due IS NOT NULL
                    AND ra.date_due < NOW()
                    AND (ra.date_reminded IS NULL OR DATE(ra.date_reminded) < CURRENT_DATE)
               ORDER BY ra.date_due ASC
                  LIMIT 25',
                [$contextId]
            );

            if (is_iterable($result)) {
                foreach ($result as $row) {
                    $row = is_array($row) ? $row : (array) $row;
                    self::sendOverdueReviewReminder(
                        (int) ($row['review_id'] ?? 0),
                        (int) ($row['submission_id'] ?? 0),
                        (int) ($row['reviewer_id'] ?? 0),
                        (string) ($row['date_due'] ?? ''),
                        (int) ($row['round'] ?? 1),
                        $contextId
                    );
                }
            } else {
                while (is_object($result) && property_exists($result, 'EOF') && !$result->EOF) {
                    self::sendOverdueReviewReminder(
                        (int) ($result->fields['review_id'] ?? 0),
                        (int) ($result->fields['submission_id'] ?? 0),
                        (int) ($result->fields['reviewer_id'] ?? 0),
                        (string) ($result->fields['date_due'] ?? ''),
                        (int) ($result->fields['round'] ?? 1),
                        $contextId
                    );
                    $result->MoveNext();
                }
            }

            if (is_object($result) && method_exists($result, 'Close')) {
                $result->Close();
            }
        } catch (\Throwable $e) {
            self::writeDebug("LỖI checkOverdueReviewDeadlines context {$contextId}: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('checkOverdueReviewDeadlines', $e->getMessage(), 0, $e, $contextId);
        } finally {
            if (is_resource($scanLockHandle)) {
                @flock($scanLockHandle, LOCK_UN);
                @fclose($scanLockHandle);
            }
        }
    }

    private static function sendOverdueReviewReminder(
        int $reviewId,
        int $submissionId,
        int $reviewerId,
        string $dateDue,
        int $round,
        int $contextId
    ): void
    {
        $dailyKey = self::getDailyReminderKey($contextId, $reviewId);
        if (ActivityLogger::isAlreadyLogged($dailyKey, 172800, $contextId)) {
            return;
        }

        try {
            $reviewAssignment = \DAORegistry::getDAO('ReviewAssignmentDAO')->getById($reviewId);
            if (!$reviewAssignment || self::wasRemindedToday($reviewAssignment)) {
                return;
            }
            $submission = \DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
            if (!$submission) {
                return;
            }
            if ((int) $submission->getContextId() !== $contextId) {
                self::writeDebug("sendOverdueReviewReminder: Bỏ qua submission #{$submissionId} không thuộc context {$contextId}.");
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication ? strip_tags($publication->getLocalizedTitle() ?? "ID #{$submissionId}") : "ID #{$submissionId}";
            $author = MessageHelper::getAuthorsString($publication ?: $submission);

            $reviewerName = "Reviewer #{$reviewerId}";
            $reviewerPhone = '';
            $reviewer = \DAORegistry::getDAO('UserDAO')->getById($reviewerId);
            if ($reviewer) {
                $reviewerName = $reviewer->getFullName();
                $reviewerPhone = preg_replace('/[^0-9]/', '', trim($reviewer->getPhone() ?? ''));
            }

            $dueTime = strtotime($dateDue);
            $daysOverdue = $dueTime ? max(0, (int) floor((time() - $dueTime) / 86400)) : 0;
            $daysText = $daysOverdue > 0 ? "Quá hạn {$daysOverdue} ngày" : 'Đến hạn hôm nay';
            $deadline = $dueTime ? date('d/m/Y', $dueTime) : $dateDue;

            $settings = self::getZaloSettings($contextId);
            $templates = self::$plugin ? self::$plugin->getMessageTemplatesForContext($contextId) : [];
            $editorTemplate = $templates['editor']['reminder'] ?? '';
            $reviewerTemplate = $templates['reviewer']['reminder'] ?? '';
            $data = array_merge(MessageHelper::getNavigationData($submission, $reviewId), [
                'title' => $title,
                'author' => $author,
                'reviewerName' => $reviewerName,
                'deadline' => $deadline,
                'daysLeft' => $daysText,
                'round' => $round ?: 1,
                'timestamp' => date('d/m/Y H:i:s')
                ]);

            $statuses = [];
            if (self::shouldSendTemplate($editorTemplate)) {
                $editorMessage = MessageHelper::buildMessage($editorTemplate, $data);
                $statuses[] = 'Editor: ' . self::sendToEditorAudience(
                    $submission, 'REVIEW_REMINDER', $editorMessage, $settings,
                    'REVIEW_REMINDER_ASSIGNED_EDITOR'
                );
            }

            if (self::shouldSendTemplate($reviewerTemplate)) {
                if ($reviewerPhone !== '') {
                    $reviewerMessage = MessageHelper::buildMessage($reviewerTemplate, $data);
                    $statuses[] = 'Reviewer: ' . ZaloApiClient::sendToPhones(
                        $reviewerMessage, $settings['botId'], $settings['apiKey'], [$reviewerPhone],
                        'REVIEW_RESPONSE', self::getSubmissionContextId($submission)
                    );
                } else {
                    $statuses[] = 'Reviewer: SKIPPED (Chưa có SĐT)';
                }
            }

            $zaloStatus = $statuses ? implode(' | ', $statuses) : 'SKIPPED';
            ActivityLogger::logReviewReminder($submissionId, $title, $reviewerName, $deadline, -$daysOverdue, $zaloStatus, 'Quá hạn phản biện', $dailyKey, $contextId);
            self::writeDebug("checkOverdueReviewDeadlines context {$contextId} #{$submissionId}/review {$reviewId}: {$zaloStatus}");
        } catch (\Throwable $e) {
            self::writeDebug("LỖI sendOverdueReviewReminder review {$reviewId}: " . $e->getMessage());
            ActivityLogger::logError('sendOverdueReviewReminder', $e->getMessage(), $submissionId, $e, $contextId);
        }
    }

    // =========================================================================
    // XỬ LÝ PHÂN CÔNG PHẢN BIỆN
    // Hook: EditorAction::addReviewer — gọi khi editor thêm phản biện viên
    // =========================================================================
    public static function handleReviewerAssigned($submission, int $reviewerId): void
    {
        try {
            if (!$submission || !$reviewerId) {
                return;
            }

            $submissionId = (int) $submission->getId();
            self::writeDebug("handleReviewerAssigned #{$submissionId}: Bat duoc addReviewer cho reviewerId={$reviewerId}; cho EditorAction::setDueDates de gui thong bao co han phan bien.");
        } catch (\Throwable $e) {
            self::writeDebug("LỖI handleReviewerAssigned: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleReviewerAssigned', $e->getMessage(), isset($submission) && $submission ? (int) $submission->getId() : 0, $e);
        }
    }

    public static function handleReviewDueDatesSet($reviewAssignment, $reviewer = null, $reviewDueDate = null, $responseDueDate = null): void
    {
        try {
            if (!$reviewAssignment) {
                return;
            }

            $submissionId = (int) $reviewAssignment->getSubmissionId();
            $reviewerId = (int) $reviewAssignment->getReviewerId();
            $reviewId = method_exists($reviewAssignment, 'getId') ? (int) $reviewAssignment->getId() : 0;
            $round = method_exists($reviewAssignment, 'getRound') ? (int) $reviewAssignment->getRound() : 1;

            $dueValue = $reviewDueDate ?: (method_exists($reviewAssignment, 'getDateDue') ? $reviewAssignment->getDateDue() : '');
            $responseValue = $responseDueDate ?: (method_exists($reviewAssignment, 'getDateResponseDue') ? $reviewAssignment->getDateResponseDue() : '');
            $deadline = self::formatReviewDate($dueValue);
            $responseDeadline = self::formatReviewDate($responseValue);

            $uniqueSuffix = $reviewId ?: $reviewerId;
            $editorKey = "REVIEW_REQUEST_EDITOR_{$submissionId}_{$reviewerId}_{$uniqueSuffix}";
            $reviewerKey = "REVIEW_REQUEST_REVIEWER_{$submissionId}_{$reviewerId}_{$uniqueSuffix}";
            if (ActivityLogger::isAlreadyLogged($editorKey) && ActivityLogger::isAlreadyLogged($reviewerKey)) {
                self::writeDebug("handleReviewDueDatesSet #{$submissionId}: Da gui loi moi cho reviewId={$uniqueSuffix}, bo qua.");
                return;
            }

            $submission = \DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
            if (!$submission) {
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication ? strip_tags($publication->getLocalizedTitle() ?? "ID #{$submissionId}") : "ID #{$submissionId}";
            $author = MessageHelper::getAuthorsString($publication ?: $submission);

            if (!$reviewer && $reviewerId) {
                $reviewer = \DAORegistry::getDAO('UserDAO')->getById($reviewerId);
            }

            $reviewerName = $reviewer ? $reviewer->getFullName() : "Reviewer #{$reviewerId}";
            $reviewerPhone = $reviewer ? preg_replace('/[^0-9]/', '', trim($reviewer->getPhone() ?? '')) : '';

            $settings = self::getZaloSettings();
            $templates = self::$plugin ? self::$plugin->getMessageTemplates() : [];
            $editorTemplate = $templates['editor']['review_request'] ?? ($templates['editor']['reminder'] ?? '');
            $reviewerTemplate = $templates['reviewer']['review_request'] ?? '';

            $data = array_merge(MessageHelper::getNavigationData($submission, $reviewId), [
                'title' => $title,
                'author' => $author,
                'reviewerName' => $reviewerName,
                'deadline' => $deadline,
                'responseDeadline' => $responseDeadline,
                'daysLeft' => 'Mới gửi lời mời',
                'round' => $round ?: 1,
                'timestamp' => date('d/m/Y H:i:s')
                ]);

            if (!ActivityLogger::isAlreadyLogged($editorKey)) {
                if (self::shouldSendTemplate($editorTemplate)) {
                    $message = MessageHelper::buildMessage($editorTemplate, $data);
                    $zaloStatus = self::sendToEditorAudience(
                        $submission, 'REVIEW_REQUEST', $message, $settings,
                        'REVIEW_REQUEST_ASSIGNED_EDITOR'
                    );
                    ActivityLogger::logReviewRequest($submissionId, $title, $reviewerName, $deadline, $zaloStatus, $editorKey);
                    self::writeDebug("handleReviewDueDatesSet #{$submissionId}: Gui editor review request reviewId={$uniqueSuffix} -- {$zaloStatus}");
                } else {
                    ActivityLogger::logReviewRequest($submissionId, $title, $reviewerName, $deadline, 'SKIPPED', $editorKey);
                }
            }

            if (!ActivityLogger::isAlreadyLogged($reviewerKey)) {
                if (self::shouldSendTemplate($reviewerTemplate)) {
                    if ($reviewerPhone !== '') {
                        $reviewerMessage = MessageHelper::buildMessage($reviewerTemplate, $data);
                        $reviewerStatus = ZaloApiClient::sendToPhones(
                            $reviewerMessage, $settings['botId'], $settings['apiKey'], [$reviewerPhone],
                            'REVIEW_REQUEST', self::getSubmissionContextId($submission)
                        );
                        ActivityLogger::logReviewRequest($submissionId, $title, $reviewerName, $deadline, $reviewerStatus, $reviewerKey);
                        self::writeDebug("handleReviewDueDatesSet #{$submissionId}: Gui reviewer review request reviewId={$uniqueSuffix} -- {$reviewerStatus}");
                    } else {
                        ActivityLogger::logReviewRequest($submissionId, $title, $reviewerName, $deadline, 'SKIPPED (Phản biện viên chưa có SĐT)', $reviewerKey);
                    }
                } else {
                    ActivityLogger::logReviewRequest($submissionId, $title, $reviewerName, $deadline, 'SKIPPED', $reviewerKey);
                }
            }
        } catch (\Throwable $e) {
            self::writeDebug("LOI handleReviewDueDatesSet: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleReviewDueDatesSet', $e->getMessage(), 0, $e);
        }
    }

    public static function handleReviewerResponse($reviewAssignment, $submission, bool $decline): void
    {
        try {
            if (!$reviewAssignment || !$submission) {
                return;
            }

            $submissionId = (int) $submission->getId();
            $reviewerId = (int) $reviewAssignment->getReviewerId();
            $reviewId = method_exists($reviewAssignment, 'getId') ? (int) $reviewAssignment->getId() : 0;
            $statusCode = $decline ? 'DECLINED' : 'ACCEPTED';
            $uniqueKey = "REVIEW_RESPONSE_{$statusCode}_{$submissionId}_{$reviewerId}_" . ($reviewId ?: '0');
            if (ActivityLogger::isAlreadyLogged($uniqueKey, 86400)) {
                self::writeDebug("handleReviewerResponse #{$submissionId}: Da ghi nhan {$statusCode} cho reviewId={$reviewId}, bo qua.");
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication ? strip_tags($publication->getLocalizedTitle() ?? "ID #{$submissionId}") : "ID #{$submissionId}";
            $author = MessageHelper::getAuthorsString($publication ?: $submission);

            $reviewer = \DAORegistry::getDAO('UserDAO')->getById($reviewerId);
            $reviewerName = $reviewer ? $reviewer->getFullName() : "Reviewer #{$reviewerId}";
            $deadline = self::formatReviewDate(method_exists($reviewAssignment, 'getDateDue') ? $reviewAssignment->getDateDue() : '');
            $responseStatus = $decline ? 'Từ chối lời mời phản biện' : 'Chấp nhận lời mời phản biện';

            $settings = self::getZaloSettings();
            $templates = self::$plugin ? self::$plugin->getMessageTemplates() : [];
            $editorTemplate = $templates['editor']['review_response'] ?? ($templates['editor']['review_request'] ?? '');

            if (self::shouldSendTemplate($editorTemplate)) {
                $message = MessageHelper::buildMessage($editorTemplate, array_merge(
                    MessageHelper::getNavigationData($submission, $reviewId),
                    [
                    'title' => $title,
                    'author' => $author,
                    'reviewerName' => $reviewerName,
                    'responseStatus' => $responseStatus,
                    'deadline' => $deadline,
                    'round' => method_exists($reviewAssignment, 'getRound') ? (int) $reviewAssignment->getRound() : 1,
                        'timestamp' => date('d/m/Y H:i:s')
                    ]
                ));
                $zaloStatus = self::sendToEditorAudience(
                    $submission, 'REVIEW_RESPONSE', $message, $settings,
                    'REVIEW_RESPONSE_ASSIGNED_EDITOR'
                );
                ActivityLogger::logReviewResponse($submissionId, $title, $reviewerName, $responseStatus, $deadline, $zaloStatus, $uniqueKey);
                self::writeDebug("handleReviewerResponse #{$submissionId}: {$statusCode} -- {$zaloStatus}");
            } else {
                ActivityLogger::logReviewResponse($submissionId, $title, $reviewerName, $responseStatus, $deadline, 'SKIPPED', $uniqueKey);
            }
        } catch (\Throwable $e) {
            self::writeDebug("LOI handleReviewerResponse: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleReviewerResponse', $e->getMessage(), 0, $e);
        }
    }

    public static function handleReviewerResponseFromHook($submission, $email, bool $decline): void
    {
        try {
            if (!$submission || !$email) {
                return;
            }

            $submissionId = (int) $submission->getId();
            $reviewerEmail = '';
            $replyTo = method_exists($email, 'getReplyTo') ? $email->getReplyTo() : [];
            if (is_array($replyTo) && !empty($replyTo[0]['email'])) {
                $reviewerEmail = (string) $replyTo[0]['email'];
            }

            $reviewer = null;
            if ($reviewerEmail !== '') {
                $reviewer = self::getUserByEmail($reviewerEmail);
            }

            $reviewerId = $reviewer ? (int) $reviewer->getId() : 0;
            $reviewAssignment = null;
            $reviewAssignmentDao = \DAORegistry::getDAO('ReviewAssignmentDAO');
            $assignments = $reviewAssignmentDao->getBySubmissionId($submissionId);
            $assignmentList = [];
            foreach ($assignments as $assignment) {
                $assignmentList[] = $assignment;
            }

            foreach ($assignmentList as $assignment) {
                if ($reviewerId && (int) $assignment->getReviewerId() !== $reviewerId) {
                    continue;
                }
                if (method_exists($assignment, 'getDateConfirmed') && $assignment->getDateConfirmed()) {
                    continue;
                }
                if (method_exists($assignment, 'getCancelled') && $assignment->getCancelled()) {
                    continue;
                }
                $reviewAssignment = $assignment;
                break;
            }

            if (!$reviewAssignment && $reviewerId) {
                foreach ($assignmentList as $assignment) {
                    if ((int) $assignment->getReviewerId() === $reviewerId) {
                        $reviewAssignment = $assignment;
                        break;
                    }
                }
            }

            if (!$reviewAssignment) {
                $status = $decline ? 'DECLINED' : 'ACCEPTED';
                self::writeDebug("handleReviewerResponseFromHook #{$submissionId}: Không tìm thấy reviewAssignment cho reviewerEmail={$reviewerEmail}, status={$status}");
                ActivityLogger::logError('handleReviewerResponseFromHook', "Không tìm thấy reviewAssignment cho reviewerEmail={$reviewerEmail}, status={$status}", $submissionId);
                return;
            }

            self::handleReviewerResponse($reviewAssignment, $submission, $decline);
        } catch (\Throwable $e) {
            self::writeDebug("LỖI handleReviewerResponseFromHook: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleReviewerResponseFromHook', $e->getMessage(), isset($submission) && $submission ? (int) $submission->getId() : 0, $e);
        }
    }

    private static function getUserByEmail(string $email)
    {
        $userDao = \DAORegistry::getDAO('UserDAO');
        if (method_exists($userDao, 'getUserByEmail')) {
            return $userDao->getUserByEmail($email);
        }
        if (method_exists($userDao, 'getByEmail')) {
            return $userDao->getByEmail($email);
        }

        return null;
    }

    public static function handleReviewAssignmentStatusChanged($submission, $reviewAssignment, string $status): void
    {
        try {
            if (!$submission || !$reviewAssignment) {
                return;
            }

            $submissionId = (int) $submission->getId();
            $reviewerId = (int) $reviewAssignment->getReviewerId();
            $reviewId = method_exists($reviewAssignment, 'getId') ? (int) $reviewAssignment->getId() : 0;
            $uniqueKey = "REVIEW_ASSIGNMENT_" . strtoupper($status) . "_{$submissionId}_{$reviewerId}_" . ($reviewId ?: '0');
            if (ActivityLogger::isAlreadyLogged($uniqueKey, 86400)) {
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication ? strip_tags($publication->getLocalizedTitle() ?? "ID #{$submissionId}") : "ID #{$submissionId}";
            $reviewer = \DAORegistry::getDAO('UserDAO')->getById($reviewerId);
            $reviewerName = $reviewer ? $reviewer->getFullName() : "Reviewer #{$reviewerId}";
            $statusText = $status === 'reinstated' ? 'Khôi phục phân công phản biện' : 'Huỷ/xoá phân công phản biện';
            $deadline = self::formatReviewDate(method_exists($reviewAssignment, 'getDateDue') ? $reviewAssignment->getDateDue() : '');

            ActivityLogger::logReviewResponse($submissionId, $title, $reviewerName, $statusText, $deadline, 'LOG_ONLY', $uniqueKey);
            self::writeDebug("handleReviewAssignmentStatusChanged #{$submissionId}: {$statusText} reviewerId={$reviewerId}");
        } catch (\Throwable $e) {
            self::writeDebug("LỖI handleReviewAssignmentStatusChanged: " . $e->getMessage());
            ActivityLogger::logError('handleReviewAssignmentStatusChanged', $e->getMessage(), 0, $e);
        }
    }

    private static function formatReviewDate($dateValue): string
    {
        if (empty($dateValue)) {
            return 'Chua thiet lap';
        }

        if (is_numeric($dateValue)) {
            $timestamp = (int) $dateValue;
        } else {
            $timestamp = strtotime((string) $dateValue);
        }

        return $timestamp ? date('d/m/Y', $timestamp) : (string) $dateValue;
    }

    public static function handleReviewerReviewCompleted($form): void
    {
        try {
            if (!$form || !method_exists($form, 'getReviewAssignment')) {
                return;
            }

            $reviewAssignment = $form->getReviewAssignment();
            if (!$reviewAssignment) {
                return;
            }

            $submissionId = (int) $reviewAssignment->getSubmissionId();
            $reviewerId = (int) $reviewAssignment->getReviewerId();
            $uniqueKey = "REVIEW_COMPLETED_{$submissionId}_{$reviewerId}_" . (int) $reviewAssignment->getId();
            if (ActivityLogger::isAlreadyLogged($uniqueKey)) {
                self::writeDebug("handleReviewerReviewCompleted #{$submissionId}: Đã gửi trước đó cho reviewId=" . $reviewAssignment->getId() . ", bỏ qua.");
                return;
            }

            $submission = null;
            try {
                $submission = \DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
            } catch (\Throwable $e) {
                self::writeDebug("handleReviewerReviewCompleted: LỖI lấy submission: " . $e->getMessage());
            }

            $title = "ID #{$submissionId}";
            $author = 'Không rõ';
            if ($submission) {
                $publication = $submission->getCurrentPublication();
                if ($publication) {
                    $title = strip_tags($publication->getLocalizedTitle() ?? $title);
                    $author = MessageHelper::getAuthorsString($publication);
                }
            }

            $reviewerName = "Reviewer #{$reviewerId}";
            try {
                $reviewer = \DAORegistry::getDAO('UserDAO')->getById($reviewerId);
                if ($reviewer) {
                    $reviewerName = $reviewer->getFullName();
                }
            } catch (\Throwable $e) {
                self::writeDebug("handleReviewerReviewCompleted: LỖI lấy reviewer: " . $e->getMessage());
            }

            $recommendationDesc = "Đã nộp bản đánh giá";
            if (method_exists($reviewAssignment, 'getRecommendation') && $reviewAssignment->getRecommendation()) {
                $recommendationDesc .= " (mã đề xuất: " . $reviewAssignment->getRecommendation() . ")";
            }

            $contextId = self::getSubmissionContextId($submission);
            $settings = self::getZaloSettings($contextId);
            $templates = self::$plugin ? self::$plugin->getMessageTemplatesForContext($contextId) : [];
            $editorTemplate = $templates['editor']['review_completed'] ?? '';
            if (!self::shouldSendTemplate($editorTemplate)) {
                ActivityLogger::logReviewCompleted($submissionId, $title, $reviewerName, $recommendationDesc, 'SKIPPED', $uniqueKey);
                self::writeDebug("handleReviewerReviewCompleted #{$submissionId}: Không có mẫu thông báo cho editor.");
                return;
            }

            $data = array_merge(MessageHelper::getNavigationData($submission, (int) $reviewAssignment->getId()), [
                'title' => $title,
                'author' => $author,
                'reviewerName' => $reviewerName,
                'recommendationDesc' => $recommendationDesc,
                'timestamp' => date('d/m/Y H:i:s')
                ]);

            $message = MessageHelper::buildMessage($editorTemplate, $data);
            $zaloStatus = self::sendToEditorAudience(
                $submission, 'REVIEW_COMPLETED', $message, $settings,
                'REVIEW_COMPLETED_ASSIGNED_EDITOR'
            );
            ActivityLogger::logReviewCompleted($submissionId, $title, $reviewerName, $recommendationDesc, $zaloStatus, $uniqueKey);
            self::writeDebug("handleReviewerReviewCompleted #{$submissionId}: Đã gửi Zalo cho editor — {$zaloStatus}");
        } catch (\Throwable $e) {
            self::writeDebug("LỖI handleReviewerReviewCompleted: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleReviewerReviewCompleted', $e->getMessage(), 0, $e);
        }
    }

}
