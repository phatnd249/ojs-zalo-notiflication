<?php

/**
 * Gom các hook chạy trước khi OJS ghi database và chỉ phát thông báo
 * ở cuối request sau khi tải lại, xác minh trạng thái đã được lưu.
 */
class PostSaveNotificationDispatcher
{
    private static $callbacks = [];
    private static $registered = false;
    private static $flushing = false;
    private static $plugin = null;

    public static function deferSubmissionEdit($plugin, $newSubmission, $oldSubmission): void
    {
        if (!$newSubmission || !$oldSubmission) {
            return;
        }
        $submissionId = (int) $newSubmission->getId();
        $expectedProgress = (int) $newSubmission->getData('submissionProgress');
        $expectedDateSubmitted = (string) $newSubmission->getData('dateSubmitted');
        $key = "submission-edit:{$submissionId}:{$expectedProgress}:{$expectedDateSubmitted}";

        self::defer($plugin, $key, function () use (
            $submissionId,
            $expectedProgress,
            $expectedDateSubmitted,
            $oldSubmission
        ) {
            $freshSubmission = self::getSubmission($submissionId);
            if (!$freshSubmission
                || (int) $freshSubmission->getData('submissionProgress') !== $expectedProgress
                || (string) $freshSubmission->getData('dateSubmitted') !== $expectedDateSubmitted
            ) {
                self::debug("Bỏ qua submission edit #{$submissionId}: thay đổi chưa được OJS lưu.");
                return;
            }
            StageChangeHandler::handleSubmission($freshSubmission, $oldSubmission, 'Submission::edit:post-save');
        });
    }

    public static function deferDecision($plugin, $submission, array $editorDecision): void
    {
        if (!$submission || empty($editorDecision['decision'])) {
            return;
        }

        $submissionId = (int) $submission->getId();
        $decisionType = (int) $editorDecision['decision'];
        $editorId = (int) ($editorDecision['editorId'] ?? 0);
        $previousStageId = method_exists($submission, 'getStageId') ? (int) $submission->getStageId() : 0;
        $baselineId = self::getLatestDecisionId($submissionId);
        $key = "decision:{$submissionId}:{$baselineId}:{$decisionType}:{$editorId}";

        self::defer($plugin, $key, function () use ($submissionId, $decisionType, $editorId, $baselineId, $previousStageId) {
            $decisions = \DAORegistry::getDAO('EditDecisionDAO')->getEditorDecisions($submissionId);
            $persisted = null;
            foreach ($decisions as $decision) {
                $decisionId = (int) ($decision['editDecisionId'] ?? 0);
                if ($decisionId <= $baselineId || (int) ($decision['decision'] ?? 0) !== $decisionType) {
                    continue;
                }
                if ($editorId > 0 && (int) ($decision['editorId'] ?? 0) !== $editorId) {
                    continue;
                }
                if (!$persisted || $decisionId > (int) $persisted['editDecisionId']) {
                    $persisted = $decision;
                }
            }

            $freshSubmission = self::getSubmission($submissionId);
            if (!$persisted || !$freshSubmission) {
                self::debug("Bỏ qua decision #{$submissionId}: không tìm thấy quyết định mới đã lưu.");
                return;
            }
            StageChangeHandler::handleDecision33($freshSubmission, $persisted);
            $currentStageId = method_exists($freshSubmission, 'getStageId')
                ? (int) $freshSubmission->getStageId()
                : 0;
            StageChangeHandler::handleAuthorEnteredReview(
                $freshSubmission,
                $previousStageId,
                $currentStageId
            );
        });
    }

    public static function deferReviewDueDates(
        $plugin,
        $reviewAssignment,
        $reviewDueDate = null,
        $responseDueDate = null
    ): void {
        if (!$reviewAssignment || !method_exists($reviewAssignment, 'getId')) {
            return;
        }
        $reviewId = (int) $reviewAssignment->getId();
        if ($reviewId <= 0) {
            return;
        }
        $expectedDue = (string) $reviewDueDate;
        $expectedResponseDue = (string) $responseDueDate;

        self::defer($plugin, "review-dates:{$reviewId}", function () use ($reviewId, $expectedDue, $expectedResponseDue) {
            $assignment = \DAORegistry::getDAO('ReviewAssignmentDAO')->getById($reviewId);
            if (!$assignment
                || !self::sameDate($expectedDue, (string) $assignment->getDateDue())
                || !self::sameDate($expectedResponseDue, (string) $assignment->getDateResponseDue())
            ) {
                self::debug("Bỏ qua review dates #{$reviewId}: ngày hạn chưa được OJS lưu.");
                return;
            }
            StageChangeHandler::handleReviewDueDatesSet($assignment, null, null, null);
        });
    }

    public static function deferReviewerResponse($plugin, $submission, $email, bool $decline): void
    {
        if (!$submission || !$email) {
            return;
        }
        $submissionId = (int) $submission->getId();
        $reviewId = self::resolvePendingReviewId($submissionId, $email);
        if ($reviewId <= 0) {
            self::debug("Bỏ qua reviewer response #{$submissionId}: không xác định được lượt phản biện.");
            return;
        }

        self::defer($plugin, "review-response:{$reviewId}:" . ($decline ? '1' : '0'), function () use ($submissionId, $reviewId, $decline) {
            $assignment = \DAORegistry::getDAO('ReviewAssignmentDAO')->getById($reviewId);
            $freshSubmission = self::getSubmission($submissionId);
            $dateConfirmed = $assignment && method_exists($assignment, 'getDateConfirmed')
                ? $assignment->getDateConfirmed()
                : null;
            $persistedDecline = $assignment && method_exists($assignment, 'getDeclined')
                ? (bool) $assignment->getDeclined()
                : false;
            if (!$assignment || !$freshSubmission || !$dateConfirmed || $persistedDecline !== $decline) {
                self::debug("Bỏ qua reviewer response #{$reviewId}: phản hồi chưa được OJS lưu.");
                return;
            }
            StageChangeHandler::handleReviewerResponse($assignment, $freshSubmission, $decline);
        });
    }

    /** Mail::send chạy sau khi các nghiệp vụ quyết định đã lưu; tải lại submission trước khi xử lý. */
    public static function deferMail($plugin, $mail): void
    {
        $submission = $mail && property_exists($mail, 'submission') ? $mail->submission : null;
        if (!$submission) {
            return;
        }
        $submissionId = (int) $submission->getId();
        $emailKey = property_exists($mail, 'emailKey') ? (string) $mail->emailKey : '';
        $objectId = function_exists('spl_object_id') ? spl_object_id($mail) : spl_object_hash($mail);

        self::defer($plugin, "mail:{$submissionId}:{$emailKey}:{$objectId}", function () use ($plugin, $mail, $submissionId) {
            $freshSubmission = self::getSubmission($submissionId);
            if (!$freshSubmission) {
                return;
            }
            $mail->submission = $freshSubmission;
            StageChangeHandler::handleMailEvent($mail, $plugin);
        });
    }

    public static function flush(): void
    {
        if (self::$flushing || empty(self::$callbacks)) {
            return;
        }
        self::$flushing = true;
        $callbacks = self::$callbacks;
        self::$callbacks = [];
        if (self::$plugin) {
            StageChangeHandler::setPlugin(self::$plugin);
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                self::debug('Lỗi dispatcher hậu lưu: ' . $e->getMessage());
            }
        }
        self::$flushing = false;
    }

    private static function defer($plugin, string $key, callable $callback): void
    {
        self::$plugin = $plugin ?: self::$plugin;
        self::$callbacks[$key] = $callback;
        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    private static function getLatestDecisionId(int $submissionId): int
    {
        $latest = 0;
        foreach (\DAORegistry::getDAO('EditDecisionDAO')->getEditorDecisions($submissionId) as $decision) {
            $latest = max($latest, (int) ($decision['editDecisionId'] ?? 0));
        }
        return $latest;
    }

    private static function resolvePendingReviewId(int $submissionId, $email): int
    {
        $replyTo = method_exists($email, 'getReplyTo') ? $email->getReplyTo() : [];
        $reviewerEmail = is_array($replyTo) && !empty($replyTo[0]['email'])
            ? (string) $replyTo[0]['email']
            : '';
        if ($reviewerEmail === '') {
            return 0;
        }
        $userDao = \DAORegistry::getDAO('UserDAO');
        $reviewer = method_exists($userDao, 'getUserByEmail')
            ? $userDao->getUserByEmail($reviewerEmail)
            : (method_exists($userDao, 'getByEmail') ? $userDao->getByEmail($reviewerEmail) : null);
        if (!$reviewer) {
            return 0;
        }

        $reviewId = 0;
        foreach (\DAORegistry::getDAO('ReviewAssignmentDAO')->getBySubmissionId($submissionId) as $assignment) {
            if ((int) $assignment->getReviewerId() !== (int) $reviewer->getId()) {
                continue;
            }
            if ((method_exists($assignment, 'getDateConfirmed') && $assignment->getDateConfirmed())
                || (method_exists($assignment, 'getCancelled') && $assignment->getCancelled())) {
                continue;
            }
            $reviewId = max($reviewId, (int) $assignment->getId());
        }
        return $reviewId;
    }

    private static function sameDate(string $expected, string $actual): bool
    {
        if ($expected === '') {
            return $actual !== '';
        }
        $expectedTime = strtotime($expected);
        $actualTime = strtotime($actual);
        return $expectedTime !== false && $actualTime !== false && $expectedTime === $actualTime;
    }

    private static function getSubmission(int $submissionId)
    {
        return \DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
    }

    private static function debug(string $message): void
    {
        if (class_exists('ZaloNotificationPlugin')) {
            ZaloNotificationPlugin::writeSecureDebug($message, 'PostSaveDispatcher');
        }
    }
}
