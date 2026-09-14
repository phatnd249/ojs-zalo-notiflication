<?php

/** Xử lý phân công biên tập, quyết định và các email nghiệp vụ. */
class EditorialNotificationHandler extends StageChangeHandler
{
    /** Quyết định phản biện chờ email trong cùng request. */
    private static $pendingAuthorDecisionNotifications = [];

    public static function handleEditorAssigned($submission, int $editorId, int $userGroupId, int $stageId = 0): void
    {
        try {
            if (!$submission || !$editorId || !$userGroupId) {
                return;
            }

            $userGroupDao = \DAORegistry::getDAO('UserGroupDAO');
            $userGroup = $userGroupDao->getById($userGroupId);
            if (!$userGroup || !in_array((int) $userGroup->getRoleId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], true)) {
                return;
            }

            $submissionId = (int) $submission->getId();
            $stageId = $stageId ?: (int) $submission->getStageId();
            $assignmentId = 0;
            $stageAssignmentDao = \DAORegistry::getDAO('StageAssignmentDAO');
            $assignments = self::resultToArray($stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $editorId, $stageId));
            foreach ($assignments as $assignment) {
                if ((int) $assignment->getUserGroupId() === $userGroupId) {
                    $assignmentId = max($assignmentId, (int) $assignment->getId());
                }
            }

            $uniqueKey = 'EDITOR_ASSIGNED_' . ($assignmentId ?: "{$submissionId}_{$editorId}_{$userGroupId}_{$stageId}");
            if (ActivityLogger::isAlreadyLogged($uniqueKey)) {
                self::writeDebug("handleEditorAssigned #{$submissionId}: Phân công {$uniqueKey} đã được xử lý, bỏ qua.");
                return;
            }

            $editor = \DAORegistry::getDAO('UserDAO')->getById($editorId);
            if (!$editor) {
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication ? strip_tags($publication->getLocalizedTitle() ?? "ID #{$submissionId}") : "ID #{$submissionId}";
            $author = MessageHelper::getAuthorsString($publication ?: $submission);
            $editorName = $editor->getFullName();
            $editorRole = $userGroup->getLocalizedName();
            $phone = ZaloApiClient::normalizePhoneNumber((string) $editor->getPhone());

            $templates = self::$plugin ? self::$plugin->getMessageTemplates() : [];
            $template = $templates['editor']['editor_assignment'] ?? '';
            $status = 'SKIPPED (Chưa có mẫu thông báo)';

            if (self::shouldSendTemplate($template)) {
                if ($phone === '') {
                    $status = 'SKIPPED (Biên tập viên chưa có SĐT hợp lệ)';
                } else {
                    $data = array_merge(MessageHelper::getNavigationData($submission, 0, $stageId), [
                        'title' => $title,
                        'author' => $author,
                        'editorName' => $editorName,
                        'editorRole' => $editorRole,
                        'assignedBy' => MessageHelper::getCurrentUserName(),
                        'stageName' => MessageHelper::getStageName($stageId),
                        'timestamp' => date('d/m/Y H:i:s'),
                    ]);
                    $message = MessageHelper::buildMessage($template, $data);
                    $settings = self::getZaloSettings();
                    $status = ZaloApiClient::sendToPhones(
                        $message, $settings['botId'], $settings['apiKey'], [$phone],
                        'EDITOR_ASSIGNMENT', self::getSubmissionContextId($submission)
                    );
                }
            }

            ActivityLogger::log(
                ActivityLogger::TYPE_ZALO_SEND,
                strpos($status, 'Thành công') !== false ? ActivityLogger::LEVEL_INFO : ActivityLogger::LEVEL_WARNING,
                $editorName,
                $submissionId,
                $title,
                $uniqueKey,
                [
                    'performed_by' => MessageHelper::getCurrentUserName(),
                    'editor' => $editorName,
                    'editor_role' => $editorRole,
                    'stage_id' => $stageId,
                    'zalo_status' => $status,
                    'action_detail' => "Phân công {$editorName} xử lý bài ở giai đoạn " . MessageHelper::getStageName($stageId),
                ]
            );
            self::writeDebug("handleEditorAssigned #{$submissionId}: editorId={$editorId}, assignmentId={$assignmentId}, status={$status}");
        } catch (\Throwable $e) {
            self::writeDebug('LỖI handleEditorAssigned: ' . $e->getMessage() . ' [' . $e->getFile() . ':' . $e->getLine() . ']');
            ActivityLogger::logError('handleEditorAssigned', $e->getMessage(), isset($submissionId) ? $submissionId : 0, $e);
        }
    }

    // =========================================================================
    // XỬ LÝ QUYẾT ĐỊNH BIÊN TẬP (OJS 3.3)
    // Hook: Decision::add
    // =========================================================================
    public static function handleDecision33($submission, $editorDecision)
    {
        try {
            if (!$submission || !$editorDecision) {
                self::writeDebug("handleDecision33: Nhận được dữ liệu null, bỏ qua.");
                return;
            }

            $submissionId = (int) $submission->getId();
            $decisionType = isset($editorDecision['decision']) ? (int)$editorDecision['decision'] : 0;
            $stageId = isset($editorDecision['stageId'])
                ? (int) $editorDecision['stageId']
                : (int) $submission->getStageId();
            $editorId = isset($editorDecision['editorId']) ? (int)$editorDecision['editorId'] : 0;
            $decisionId = (int) ($editorDecision['editDecisionId'] ?? 0);

            $decisionEmailKeys = [
                SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS => 'EDITOR_DECISION_REVISIONS',
                SUBMISSION_EDITOR_DECISION_RESUBMIT => 'EDITOR_DECISION_RESUBMIT',
                SUBMISSION_EDITOR_DECISION_ACCEPT => 'EDITOR_DECISION_ACCEPT',
                SUBMISSION_EDITOR_DECISION_DECLINE => 'EDITOR_DECISION_DECLINE',
                SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE => 'EDITOR_DECISION_INITIAL_DECLINE',
            ];
            $isInitialDecline = $decisionType === SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE;
            if (
                ($isInitialDecline || in_array($stageId, [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW], true))
                && isset($decisionEmailKeys[$decisionType])
            ) {
                self::$pendingAuthorDecisionNotifications[$submissionId] = [
                    'emailKey' => $decisionEmailKeys[$decisionType],
                    'decisionType' => $decisionType,
                    'stageId' => $stageId,
                    'decisionId' => $decisionId,
                ];
            }

            self::writeDebug("handleDecision33 START — submissionId:{$submissionId} | decisionType:{$decisionType} | stageId:{$stageId} | editorId:{$editorId}");

            $stageName = MessageHelper::getStageName($stageId);
            $decisionDesc = MessageHelper::getDecisionName($decisionType);

            $title = 'Không có tiêu đề';
            $abstract = '';
            $author = 'Không rõ';
            try {
                $publication = $submission->getCurrentPublication();
                if ($publication) {
                    $title = strip_tags($publication->getLocalizedTitle() ?? 'Không có tiêu đề');
                    $abstract = strip_tags($publication->getLocalizedData('abstract') ?? '');
                    $author = MessageHelper::getAuthorsString($publication);
                }
            } catch (\Throwable $e) {
                self::writeDebug("handleDecision33: LỖI lấy title/author: " . $e->getMessage());
            }

            $editorName = 'Không rõ';
            if ($editorId) {
                try {
                    $editor = \DAORegistry::getDAO('UserDAO')->getById($editorId);
                    if ($editor) {
                        $editorName = $editor->getFullName();
                    }
                } catch (\Throwable $e) {
                    self::writeDebug("handleDecision33: LỖI lấy editor: " . $e->getMessage());
                }
            }

            $uniqueKey = "DECISION_{$submissionId}_{$stageId}_{$decisionType}" . ($decisionId > 0 ? "_{$decisionId}" : '');
            if (ActivityLogger::isAlreadyLogged($uniqueKey)) {
                return;
            }

            $settings = self::getZaloSettings();
            $botId = $settings['botId'];
            $apiKey = $settings['apiKey'];

            $templates = self::$plugin ? self::$plugin->getMessageTemplates() : [];
            $editorTemplate = $templates['editor']['decision'] ?? '';
            $reviewerTemplate = $templates['reviewer']['decision'] ?? '';

            $data = array_merge(MessageHelper::getNavigationData($submission, 0, $stageId), [
                'title' => $title,
                'author' => $author,
                'abstract_if_any' => $abstract ? "📄 Tóm tắt: {$abstract}\n" : '',
                'stageName' => $stageName,
                'decisionDesc' => $decisionDesc,
                'editorName' => $editorName,
                'timestamp' => date('d/m/Y H:i:s')
            ]);

            // Gửi Zalo chung
            if (self::shouldSendTemplate($editorTemplate)) {
                $generalMessage = MessageHelper::buildMessage($editorTemplate, $data);
                $status = self::sendToEditorAudience(
                    $submission, 'DECISION', $generalMessage, $settings,
                    'DECISION_ASSIGNED_EDITOR'
                );
                ActivityLogger::logDecision($submissionId, $title, $author, $stageId, $stageName, $decisionType, $decisionDesc, $editorName, $status);
                self::writeDebug("handleDecision33: Gửi xong chung — status: {$status}");

            } else {
                ActivityLogger::logDecision($submissionId, $title, $author, $stageId, $stageName, $decisionType, $decisionDesc, $editorName, 'SKIPPED');
            }

            // Tác giả chỉ nhận thông báo khi OJS thực sự gửi email quyết định.
            // Việc gửi được xử lý trong handleMailEvent() để tôn trọng lựa chọn
            // "Skip Email" và tránh gửi Zalo trùng hai lần.

            // Gửi Phản biện
            if (self::shouldSendTemplate($reviewerTemplate)) {
                $reviewerPhones = self::getReviewerPhones($submissionId);
                if (!empty($reviewerPhones)) {
                    $reviewerMsg = MessageHelper::buildMessage($reviewerTemplate, $data);
                    ZaloApiClient::sendToPhones(
                        $reviewerMsg, $botId, $apiKey, $reviewerPhones,
                        'EDITOR_DECISION_REVIEWER', self::getSubmissionContextId($submission)
                    );
                }
            }

        } catch (\Throwable $e) {
            self::writeDebug("LỖI NGHIÊM TRỌNG handleDecision33: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleDecision', $e->getMessage(), $submissionId ?? 0, $e);
        }
    }

    // =========================================================================
    // XỬ LÝ SỰ KIỆN QUA EMAIL (THAY THẾ CHO ReviewAssignment::edit)
    // Hook: Mail::send
    // =========================================================================
    public static function handleMailEvent($mail, $plugin)
    {
        try {
            if (!$mail || !property_exists($mail, 'emailKey') || empty($mail->emailKey)) return;
            
            $emailKey = $mail->emailKey;
            $submission = property_exists($mail, 'submission') ? $mail->submission : null;
            if (!$submission) return;
            
            $submissionId = (int) $submission->getId();
            
            self::writeDebug("handleMailEvent START — emailKey:{$emailKey} | submissionId:{$submissionId}");
            
            $title = 'Không có tiêu đề';
            $author = 'Không rõ';
            try {
                $publication = $submission->getCurrentPublication();
                if ($publication) {
                    $title = strip_tags($publication->getLocalizedTitle() ?? 'Không có tiêu đề');
                    $author = MessageHelper::getAuthorsString($publication);
                }
            } catch (\Throwable $e) {
                self::writeDebug("handleMailEvent: LỖI lấy title/author: " . $e->getMessage());
            }

            $settings = self::getZaloSettings();
            $botId = $settings['botId'];
            $apiKey = $settings['apiKey'];
            $templates = $plugin->getMessageTemplates();

            // EMAIL KẾT QUẢ PHẢN BIỆN GỬI CHO TÁC GIẢ
            // Không sao chép nội dung email/nhận xét phản biện sang Zalo để bảo
            // đảm ẩn danh. Tin nhắn chỉ dùng mẫu quyết định và liên kết OJS.
            $pendingDecision = self::$pendingAuthorDecisionNotifications[$submissionId] ?? null;
            if (!$pendingDecision || $pendingDecision['emailKey'] !== $emailKey) {
                $pendingDecision = self::resolvePersistedDecisionForEmail($submissionId, $emailKey);
            }
            if ($pendingDecision && $pendingDecision['emailKey'] === $emailKey) {
                unset(self::$pendingAuthorDecisionNotifications[$submissionId]);
                $decisionType = (int) $pendingDecision['decisionType'];
                $stageId = (int) $pendingDecision['stageId'];
                $decisionId = (int) ($pendingDecision['decisionId'] ?? 0);
                $uniqueKey = "DECISION_EMAIL_AUTHOR_{$submissionId}_{$emailKey}" . ($decisionId > 0 ? "_{$decisionId}" : '');
                if (ActivityLogger::isAlreadyLogged($uniqueKey)) {
                    self::writeDebug("handleMailEvent {$emailKey}: Da gui Zalo cho tac gia, bo qua gui trung.");
                    return;
                }

                $authorTemplate = $decisionType === SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE
                    ? ($templates['author']['initial_decline'] ?? '')
                    : ($templates['author']['decision'] ?? '');
                $decisionDesc = MessageHelper::getDecisionName($decisionType);
                $data = array_merge(MessageHelper::getNavigationData($submission, 0, $stageId), [
                    'title' => $title,
                    'author' => $author,
                    'stageName' => MessageHelper::getStageName($stageId),
                    'decisionDesc' => $decisionDesc,
                    'editorName' => '',
                    'timestamp' => date('d/m/Y H:i:s'),
                ]);

                $zaloStatus = 'SKIPPED';
                if (self::shouldSendTemplate($authorTemplate)) {
                    $authorPhones = self::getAuthorPhones($submission);
                    if (!empty($authorPhones)) {
                        $authorMessage = MessageHelper::buildMessage($authorTemplate, $data);
                        $zaloStatus = ZaloApiClient::sendToPhones(
                            $authorMessage, $botId, $apiKey, $authorPhones,
                            'EDITOR_DECISION_AUTHOR', self::getSubmissionContextId($submission)
                        );
                    } else {
                        $zaloStatus = 'SKIPPED (Tác giả chưa có SĐT)';
                    }
                }

                ActivityLogger::log(
                    ActivityLogger::TYPE_DECISION,
                    strpos($zaloStatus, 'Thành công') !== false || strpos($zaloStatus, 'Đã xếp hàng') === 0 || strpos($zaloStatus, 'SKIPPED') === 0
                        ? ActivityLogger::LEVEL_INFO : ActivityLogger::LEVEL_WARNING,
                    'System',
                    $submissionId,
                    $title,
                    $uniqueKey,
                    [
                        'author' => $author,
                        'decision' => $decisionDesc,
                        'email_key' => $emailKey,
                        'zalo_status' => $zaloStatus,
                        'privacy' => 'Không gửi nội dung hoặc danh tính phản biện',
                    ]
                );
                self::writeDebug("handleMailEvent {$emailKey}: Gui thong bao ket qua cho tac gia — {$zaloStatus}");
                return;
            }
            
            // 1. NHẮC NHỞ TỰ ĐỘNG
            if (strpos($emailKey, 'REVIEW_REMIND_AUTO') === 0 || strpos($emailKey, 'REVIEW_REQUEST_REMIND_AUTO') === 0) {
                $contextId = method_exists($submission, 'getContextId') ? (int) $submission->getContextId() : 0;
                $reviewAssignment = ReviewNotificationHandler::resolveReminderAssignmentFromMail($submissionId, $mail);
                $reviewId = $reviewAssignment ? (int) $reviewAssignment->getId() : 0;
                $uniqueKey = $reviewId > 0
                    ? ReviewNotificationHandler::getDailyReminderKey($contextId, $reviewId)
                    : "MAIL_REMINDER_{$contextId}_{$emailKey}_{$submissionId}_" . date('Y-m-d');
                if (($reviewAssignment && ReviewNotificationHandler::wasRemindedToday($reviewAssignment))
                    || ActivityLogger::isAlreadyLogged($uniqueKey, 172800, $contextId)) {
                    self::writeDebug("handleMailEvent REVIEW_REMINDER: Đã nhắc reviewId={$reviewId} trong ngày, bỏ qua Zalo trùng.");
                    return;
                }

                $reviewerName = isset($mail->params['reviewerName']) ? $mail->params['reviewerName'] : 'Không rõ';
                $deadlineDate = isset($mail->params['reviewDueDate']) ? $mail->params['reviewDueDate'] : (isset($mail->params['responseDueDate']) ? $mail->params['responseDueDate'] : 'N/A');

                $editorTemplate = $templates['editor']['reminder'] ?? '';
                $data = array_merge(MessageHelper::getNavigationData($submission), [
                    'title' => $title,
                    'author' => $author,
                    'reviewerName' => $reviewerName,
                    'round' => 1,
                    'deadline' => $deadlineDate,
                    'daysLeft' => 'Đã đến hạn',
                    'timestamp' => date('d/m/Y H:i:s')
                ]);

                if (self::shouldSendTemplate($editorTemplate)) {
                    $message = MessageHelper::buildMessage($editorTemplate, $data);
                    $zaloStatus = self::sendToEditorAudience(
                        $submission, 'REVIEW_REMINDER', $message, $settings,
                        'REVIEW_REMINDER_ASSIGNED_EDITOR'
                    );
                    ActivityLogger::logReviewReminder($submissionId, $title, $reviewerName, $deadlineDate, 0, $zaloStatus, 'Nhắc nhở tự động', $uniqueKey, $contextId);
                    self::writeDebug("handleMailEvent REVIEW_REMINDER: Đã gửi Zalo cho editor — {$zaloStatus}");
                }
            } 
            // 2. LỜI MỜI PHẢN BIỆN MỚI
            elseif (strpos($emailKey, 'REVIEW_REQUEST') === 0) {
                self::writeDebug("handleMailEvent REVIEW_REQUEST: Bo qua Mail::send de tranh gui trung; plugin gui tai EditorAction::setDueDates.");
                return;
            } 
            // 3. HOÀN TẤT ĐÁNH GIÁ (REVIEW_ACK)
            elseif ($emailKey === 'REVIEW_ACK') {
                self::writeDebug("handleMailEvent REVIEW_ACK: Bo qua Mail::send de tranh gui trung; plugin gui tai reviewerreviewstep3form::execute.");
                return;
            }
            // 4. NỘP BÀI THÀNH CÔNG (SUBMISSION_ACK)
            elseif ($emailKey === 'SUBMISSION_ACK') {
                $uniqueKey = "SUBMITTED_{$submissionId}";
                if (ActivityLogger::isAlreadyLogged($uniqueKey, 315360000)) return;

                $abstract = '';
                try {
                    $publication = $submission->getCurrentPublication();
                    if ($publication) {
                        $abstract = strip_tags($publication->getLocalizedData('abstract') ?? '');
                    }
                } catch (\Throwable $e) {
                    self::writeDebug("handleMailEvent SUBMISSION_ACK: LỖI lấy tóm tắt: " . $e->getMessage());
                }

                $data = array_merge(MessageHelper::getNavigationData($submission), [
                    'title' => $title,
                    'author' => $author,
                    'abstract_if_any' => $abstract ? "📄 Tóm tắt: {$abstract}\n" : '',
                    'timestamp' => date('d/m/Y H:i:s')
                ]);

                $editorTemplate = $templates['editor']['submission'] ?? '';
                $authorTemplate = $templates['author']['submission'] ?? '';

                // Gửi cho Ban biên tập
                if (self::shouldSendTemplate($editorTemplate)) {
                    $generalMessage = MessageHelper::buildMessage($editorTemplate, $data);
                    $status = self::sendToEditorAudience(
                        $submission, 'SUBMISSION', $generalMessage, $settings,
                        'SUBMISSION_ACK_ASSIGNED_EDITOR'
                    );
                    ActivityLogger::logSubmission($submissionId, $title, $author, $status);
                }

                // Gửi cho Tác giả
                if (self::shouldSendTemplate($authorTemplate)) {
                    $authorPhones = self::getAuthorPhones($submission);
                    if (!empty($authorPhones)) {
                        $authorMsg = MessageHelper::buildMessage($authorTemplate, $data);
                        ZaloApiClient::sendToPhones(
                            $authorMsg, $botId, $apiKey, $authorPhones,
                            'SUBMISSION_ACK_AUTHOR', self::getSubmissionContextId($submission)
                        );
                        self::writeDebug("handleMailEvent: Đã gửi Zalo SUBMISSION_ACK cho tác giả.");
                    } else {
                        self::writeDebug("handleMailEvent: SUBMISSION_ACK không tìm thấy SĐT tác giả nào.");
                    }
                }
            }

        } catch (\Throwable $e) {
            self::writeDebug("LỖI handleMailEvent: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleMailEvent', $e->getMessage(), isset($submissionId) ? $submissionId : 0, $e);
        }
    }

    private static function resolvePersistedDecisionForEmail(int $submissionId, string $emailKey): ?array
    {
        $decisionByEmail = [
            'EDITOR_DECISION_REVISIONS' => SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS,
            'EDITOR_DECISION_RESUBMIT' => SUBMISSION_EDITOR_DECISION_RESUBMIT,
            'EDITOR_DECISION_ACCEPT' => SUBMISSION_EDITOR_DECISION_ACCEPT,
            'EDITOR_DECISION_DECLINE' => SUBMISSION_EDITOR_DECISION_DECLINE,
            'EDITOR_DECISION_INITIAL_DECLINE' => SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE,
        ];
        if (!isset($decisionByEmail[$emailKey])) {
            return null;
        }

        try {
            $expectedDecision = (int) $decisionByEmail[$emailKey];
            $decisions = \DAORegistry::getDAO('EditDecisionDAO')->getEditorDecisions($submissionId);
            for ($index = count($decisions) - 1; $index >= 0; $index--) {
                $decision = $decisions[$index];
                if ((int) ($decision['decision'] ?? 0) !== $expectedDecision) {
                    continue;
                }
                $stageId = (int) ($decision['stageId'] ?? 0);
                if ($expectedDecision !== SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE
                    && !in_array($stageId, [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW], true)
                ) {
                    continue;
                }
                return [
                    'emailKey' => $emailKey,
                    'decisionType' => $expectedDecision,
                    'stageId' => $stageId,
                    'decisionId' => (int) ($decision['editDecisionId'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            self::writeDebug('resolvePersistedDecisionForEmail lỗi: ' . $e->getMessage());
        }
        return null;
    }

}
