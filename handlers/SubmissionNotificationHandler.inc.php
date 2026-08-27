<?php

/** Xử lý các sự kiện nộp bài. */
class SubmissionNotificationHandler extends StageChangeHandler
{
    /** Gửi riêng cho BTV được gán khi tác giả tải bản sửa vào một vòng phản biện. */
    public static function handleAuthorRevisionUploaded($submissionFile, $request = null): void
    {
        try {
            if (!$submissionFile || !method_exists($submissionFile, 'getData')) {
                return;
            }

            $fileStage = method_exists($submissionFile, 'getFileStage')
                ? (int) $submissionFile->getFileStage()
                : (int) $submissionFile->getData('fileStage');
            if (!in_array($fileStage, [SUBMISSION_FILE_REVIEW_REVISION, SUBMISSION_FILE_INTERNAL_REVIEW_REVISION], true)) {
                return;
            }

            $submissionId = (int) $submissionFile->getData('submissionId');
            $uploaderId = method_exists($submissionFile, 'getUploaderUserId')
                ? (int) $submissionFile->getUploaderUserId()
                : (int) $submissionFile->getData('uploaderUserId');
            $reviewRoundId = (int) $submissionFile->getData('assocId');
            if ($submissionId <= 0 || $uploaderId <= 0 || $reviewRoundId <= 0) {
                return;
            }

            $isAuthor = false;
            $authorAssignments = self::resultToArray(
                \DAORegistry::getDAO('StageAssignmentDAO')->getBySubmissionAndRoleId($submissionId, ROLE_ID_AUTHOR)
            );
            foreach ($authorAssignments as $assignment) {
                if ($assignment && method_exists($assignment, 'getUserId') && (int) $assignment->getUserId() === $uploaderId) {
                    $isAuthor = true;
                    break;
                }
            }
            if (!$isAuthor) {
                return;
            }

            $submission = \DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
            $reviewRound = \DAORegistry::getDAO('ReviewRoundDAO')->getById($reviewRoundId);
            if (!$submission || !$reviewRound || (int) $reviewRound->getSubmissionId() !== $submissionId) {
                return;
            }

            $contextId = self::getSubmissionContextId($submission);
            // Một lần tải có thể gồm nhiều file; chỉ gửi một tin cho cùng bài/vòng trong 5 phút.
            $uniqueKey = "AUTHOR_REVISION_UPLOADED_{$submissionId}_{$reviewRoundId}";
            if (ActivityLogger::isAlreadyLogged($uniqueKey, 300, $contextId)) {
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication
                ? strip_tags((string) ($publication->getLocalizedTitle() ?? "ID #{$submissionId}"))
                : "ID #{$submissionId}";
            $author = MessageHelper::getAuthorsString($publication ?: $submission);
            $uploader = \DAORegistry::getDAO('UserDAO')->getById($uploaderId);
            $uploaderName = $uploader ? $uploader->getFullName() : $author;
            $stageId = method_exists($reviewRound, 'getStageId')
                ? (int) $reviewRound->getStageId()
                : (int) $submission->getStageId();
            $round = method_exists($reviewRound, 'getRound') ? max(1, (int) $reviewRound->getRound()) : 1;

            $settings = self::getZaloSettings($contextId);
            $templates = self::$plugin ? self::$plugin->getMessageTemplatesForContext($contextId) : [];
            $template = $templates['editor']['author_revision'] ?? '';
            $status = 'SKIPPED (Mẫu thông báo đang tắt)';

            if (self::shouldSendTemplate($template)) {
                $editorPhones = self::getEditorPhones($submissionId);
                if (empty($editorPhones)) {
                    $status = 'SKIPPED (Không có BTV được gán có SĐT hợp lệ)';
                } else {
                    $data = array_merge(MessageHelper::getNavigationData($submission, 0, $stageId), [
                        'title' => $title,
                        'author' => $author,
                        'stageName' => MessageHelper::getStageName($stageId),
                        'round' => $round,
                        'timestamp' => date('d/m/Y H:i:s'),
                    ]);
                    $status = ZaloApiClient::sendToPhones(
                        MessageHelper::buildMessage($template, $data),
                        $settings['botId'],
                        $settings['apiKey'],
                        $editorPhones,
                        'AUTHOR_REVISION_ASSIGNED_EDITOR',
                        $contextId
                    );
                }
            }

            ActivityLogger::log(
                ActivityLogger::TYPE_ZALO_SEND,
                (strpos($status, 'Thành công') !== false || strpos($status, 'Đã xếp hàng') === 0 || strpos($status, 'SKIPPED') === 0)
                    ? ActivityLogger::LEVEL_INFO
                    : ActivityLogger::LEVEL_WARNING,
                $uploaderName,
                $submissionId,
                $title,
                $uniqueKey,
                [
                    'author' => $author,
                    'review_round' => $round,
                    'stage_id' => $stageId,
                    'zalo_status' => $status,
                    'recipients' => 'Biên tập viên được gán vào bài',
                    'action_detail' => "Tác giả {$uploaderName} đã nộp bản chỉnh sửa cho vòng phản biện {$round}.",
                ],
                $contextId
            );
            self::writeDebug("handleAuthorRevisionUploaded #{$submissionId}/round {$reviewRoundId}: {$status}");
        } catch (\Throwable $e) {
            self::writeDebug('LỖI handleAuthorRevisionUploaded: ' . $e->getMessage());
            ActivityLogger::logError(
                'handleAuthorRevisionUploaded',
                $e->getMessage(),
                isset($submissionId) ? $submissionId : 0,
                $e,
                isset($contextId) ? $contextId : null
            );
        }
    }

    /** Gửi cho tác giả sau khi database xác nhận bài đã chuyển từ Nộp bài sang Phản biện. */
    public static function handleAuthorEnteredReview($submission, int $previousStageId, int $currentStageId): void
    {
        try {
            if (!$submission
                || $previousStageId !== WORKFLOW_STAGE_ID_SUBMISSION
                || !in_array($currentStageId, [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW], true)
            ) {
                return;
            }

            $submissionId = (int) $submission->getId();
            $contextId = self::getSubmissionContextId($submission);
            $uniqueKey = "AUTHOR_REVIEW_STARTED_{$submissionId}_{$currentStageId}";
            if (ActivityLogger::isAlreadyLogged($uniqueKey, 315360000, $contextId)) {
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication
                ? strip_tags((string) ($publication->getLocalizedTitle() ?? "ID #{$submissionId}"))
                : "ID #{$submissionId}";
            $author = MessageHelper::getAuthorsString($publication ?: $submission);
            $templates = self::$plugin
                ? self::$plugin->getMessageTemplatesForContext($contextId)
                : [];
            $template = $templates['author']['review_started'] ?? '';
            $status = 'SKIPPED (Mẫu thông báo đang tắt)';

            if (self::shouldSendTemplate($template)) {
                $phones = self::getAuthorPhones($submission);
                if (empty($phones)) {
                    $status = 'SKIPPED (Tác giả chưa có SĐT)';
                } else {
                    $data = array_merge(MessageHelper::getNavigationData($submission, 0, $currentStageId), [
                        'title' => $title,
                        'author' => $author,
                        'stageName' => MessageHelper::getStageName($currentStageId),
                        'timestamp' => date('d/m/Y H:i:s'),
                    ]);
                    $settings = self::getZaloSettings($contextId);
                    $status = ZaloApiClient::sendToPhones(
                        MessageHelper::buildMessage($template, $data),
                        $settings['botId'],
                        $settings['apiKey'],
                        $phones,
                        'AUTHOR_REVIEW_STARTED',
                        $contextId
                    );
                }
            }

            $isGood = $status === 'Thành công' || strpos($status, 'Đã xếp hàng') === 0;
            ActivityLogger::log(
                ActivityLogger::TYPE_ZALO_SEND,
                $isGood ? ActivityLogger::LEVEL_INFO : ActivityLogger::LEVEL_WARNING,
                'System',
                $submissionId,
                $title,
                $uniqueKey,
                [
                    'author' => $author,
                    'from_stage' => $previousStageId,
                    'to_stage' => $currentStageId,
                    'zalo_status' => $status,
                    'action_detail' => 'Thông báo tác giả: bài đã chuyển sang giai đoạn phản biện',
                ],
                $contextId
            );
        } catch (\Throwable $e) {
            self::writeDebug('handleAuthorEnteredReview lỗi: ' . $e->getMessage());
            ActivityLogger::logError(
                'handleAuthorEnteredReview',
                $e->getMessage(),
                isset($submissionId) ? $submissionId : 0,
                $e,
                isset($contextId) ? $contextId : null
            );
        }
    }

    // =========================================================================
    // XỬ LÝ BÀI NỘP MỚI
    // Hook: submissionsubmitstep4form::execute — gọi khi tác giả hoàn tất bước 4
    // =========================================================================
    public static function handleSubmissionSubmitStep4Form($form): void
    {
        try {
            if ($form && method_exists($form, 'submission') && $form->submission) {
                self::handleSubmission($form->submission, null, 'SubmissionSubmitStep4Form');
            } elseif ($form && property_exists($form, 'submission') && $form->submission) {
                self::handleSubmission($form->submission, null, 'SubmissionSubmitStep4Form');
            } elseif ($form && method_exists($form, 'getSubmission')) {
                $sub = $form->getSubmission();
                if ($sub) self::handleSubmission($sub, null, 'SubmissionSubmitStep4Form');
            }
        } catch (\Throwable $e) {
            self::writeDebug("LỖI handleSubmissionSubmitStep4Form: " . $e->getMessage());
            ActivityLogger::logError('handleSubmissionSubmitStep4Form', $e->getMessage(), 0, $e);
        }
    }

    // =========================================================================
    // XỬ LÝ BÀI NỘP MỚI
    // Hook: Submission::add — chỉ fire 1 lần khi nộp bài hoàn tất
    // =========================================================================
    public static function handleSubmission($submission, $oldSubmission = null, string $source = '')
    {
        try {
            if (!$submission)
                return;

            $submissionId = $submission->getId();
            $contextId = self::getSubmissionContextId($submission);
            $submissionProgress = (int) $submission->getData('submissionProgress');
            $dateSubmitted = $submission->getData('dateSubmitted');

            // Đảm bảo bài đã được nộp hoàn chỉnh (không phải bản nháp)
            if ($submissionProgress > 0 || $dateSubmitted === null) {
                self::writeDebug("handleSubmission #{$submissionId}: Bỏ qua — bản nháp chưa hoàn tất. source={$source} progress={$submissionProgress} dateSubmitted=" . ($dateSubmitted ?: 'NULL'));
                return;
            }

            if ($oldSubmission) {
                $oldProgress = (int) $oldSubmission->getData('submissionProgress');
                $oldDateSubmitted = $oldSubmission->getData('dateSubmitted');
                self::writeDebug("handleSubmission #{$submissionId}: Đã nhận bài hoàn tất từ {$source}. oldProgress={$oldProgress} oldDateSubmitted=" . ($oldDateSubmitted ?: 'NULL') . " newProgress={$submissionProgress} newDateSubmitted={$dateSubmitted}");

                if ($oldProgress === 0 && $oldDateSubmitted !== null) {
                    self::writeDebug("handleSubmission #{$submissionId}: Bỏ qua — bài đã được nộp từ trước, đây chỉ là cập nhật sau nộp. source={$source}");
                    return;
                }
            } else {
                self::writeDebug("handleSubmission #{$submissionId}: Đã nhận bài hoàn tất từ {$source}. progress={$submissionProgress} dateSubmitted={$dateSubmitted}");
            }

            $uniqueKey = "SUBMITTED_{$submissionId}";
            if (ActivityLogger::isAlreadyLogged($uniqueKey, 315360000)) {
                self::writeDebug("handleSubmission #{$submissionId}: Đã gửi trước đó, bỏ qua.");
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication ? strip_tags($publication->getLocalizedTitle() ?? 'Không có tiêu đề') : 'Không có tiêu đề';
            $abstract = $publication ? strip_tags($publication->getLocalizedData('abstract') ?? '') : '';
            $author = MessageHelper::getAuthorsString($publication);

            $settings = self::getZaloSettings();
            $botId = $settings['botId'];
            $apiKey = $settings['apiKey'];

            $templates = self::$plugin ? self::$plugin->getMessageTemplates() : [];
            $editorTemplate = $templates['editor']['submission'] ?? '';
            $authorTemplate = $templates['author']['submission'] ?? '';

            $data = array_merge(MessageHelper::getNavigationData($submission), [
                'title' => $title,
                'author' => $author,
                'abstract_if_any' => $abstract ? "📄 Tóm tắt: {$abstract}\n" : '',
                'submissionId' => $submissionId,
                'timestamp' => date('d/m/Y H:i:s')
            ]);

            // Gửi thông báo chung cho Ban biên tập (từ settings)
            if (self::shouldSendTemplate($editorTemplate)) {
                $generalMessage = MessageHelper::buildMessage($editorTemplate, $data);
                $status = self::sendToEditorAudience(
                    $submission, 'SUBMISSION', $generalMessage, $settings,
                    'SUBMISSION_ASSIGNED_EDITOR'
                );
                ActivityLogger::logSubmission($submissionId, $title, $author, $status);
                self::writeDebug("handleSubmission #{$submissionId}: Đã gửi Zalo chung — {$status}");
                
            } else {
                ActivityLogger::logSubmission($submissionId, $title, $author, 'SKIPPED');
            }

            // Gửi riêng cho Tác giả
            if (self::shouldSendTemplate($authorTemplate)) {
                $authorPhones = self::getAuthorPhones($submission);
                if (!empty($authorPhones)) {
                    $authorMsg = MessageHelper::buildMessage($authorTemplate, $data);
                    $authorStatus = ZaloApiClient::sendToPhones($authorMsg, $botId, $apiKey, $authorPhones, 'SUBMISSION_AUTHOR', $contextId);
                    ActivityLogger::logZaloResult($submissionId, $title, $authorStatus, "Gửi tác giả (Nộp bài): " . implode(',', $authorPhones));
                    self::writeDebug("handleSubmission #{$submissionId}: Đã gửi Zalo cho Tác giả (" . implode(',', $authorPhones) . ") - Status: {$authorStatus}");
                }
            }

        } catch (\Throwable $e) {
            self::writeDebug("LỖI handleSubmission: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleSubmission', $e->getMessage(), $submissionId ?? 0, $e);
        }
    }

}
