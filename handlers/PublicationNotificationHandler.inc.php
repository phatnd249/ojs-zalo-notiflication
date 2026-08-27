<?php

/** Xử lý các sự kiện xuất bản và hủy xuất bản. */
class PublicationNotificationHandler extends StageChangeHandler
{
    // =========================================================================
    // XỬ LÝ XUẤT BẢN CHÍNH THỨC
    // Hook: Publication::publish — $newPublication, $submission
    // =========================================================================
    public static function handlePublish($publication, $submission)
    {
        try {
            if (!$publication || !$submission)
                return;

            $submissionId = (int) $submission->getId();
            $status = (int) $publication->getData('status');

            self::writeDebug("handlePublish START — submissionId:{$submissionId} | status:{$status}");

            // Chỉ thông báo khi bài thực sự PUBLISHED (status = 3)
            if ($status !== 3) {
                self::writeDebug("handlePublish: status={$status} không phải PUBLISHED, bỏ qua.");
                return;
            }

            $uniqueKey = "PUBLISHED_{$submissionId}_" . $publication->getId();
            if (ActivityLogger::isAlreadyLogged($uniqueKey)) {
                self::writeDebug("handlePublish: {$uniqueKey} đã log, bỏ qua.");
                return;
            }

            $title = strip_tags($publication->getLocalizedTitle() ?? "ID #{$submissionId}");
            $abstract = strip_tags($publication->getLocalizedData('abstract') ?? '');
            $author = MessageHelper::getAuthorsString($publication);

            // Lấy thông tin số báo
            $issueString = '';
            $issueId = (int) $publication->getData('issueId');
            if ($issueId) {
                try {
                    $issue = \DAORegistry::getDAO('IssueDAO')->getById($issueId);
                    if ($issue) {
                        $issueString = $issue->getIssueIdentification();
                    }
                } catch (\Throwable $e) {
                    self::writeDebug("LỖI lấy số báo: " . $e->getMessage());
                }
            }

            $datePublished = $publication->getData('datePublished') ?? date('Y-m-d');

            $settings = self::getZaloSettings();
            $botId = $settings['botId'];
            $apiKey = $settings['apiKey'];

            $templates = self::$plugin ? self::$plugin->getMessageTemplates() : [];
            $editorTemplate = $templates['editor']['publish'] ?? '';
            $authorTemplate = $templates['author']['publish'] ?? '';

            $data = array_merge(MessageHelper::getNavigationData($submission), [
                'title' => $title,
                'author' => $author,
                'abstract_if_any' => $abstract ? "📄 Tóm tắt: {$abstract}\n" : '',
                'issueString_if_any' => $issueString ? "📚 Số báo: {$issueString}\n" : '',
                'issueString' => $issueString,
                'datePublished' => $datePublished,
                'timestamp' => date('d/m/Y H:i:s')
            ]);

            // Gửi chung
            if (self::shouldSendTemplate($editorTemplate)) {
                $generalMessage = MessageHelper::buildMessage($editorTemplate, $data);
                self::writeDebug("handlePublish: Chuẩn bị gửi Zalo chung...");
                $zaloStatus = self::sendToEditorAudience(
                    $submission, 'PUBLISH', $generalMessage, $settings,
                    'PUBLISH_ASSIGNED_EDITOR'
                );
                $performedBy = MessageHelper::getCurrentUserName();
                ActivityLogger::logPublish($submissionId, (int) $publication->getId(), $title, $author, $performedBy, $datePublished, $zaloStatus, $issueString);
                self::writeDebug("handlePublish: Gửi xong chung — status: {$zaloStatus}");

            } else {
                $performedBy = MessageHelper::getCurrentUserName();
                ActivityLogger::logPublish($submissionId, (int) $publication->getId(), $title, $author, $performedBy, $datePublished, 'SKIPPED', $issueString);
            }

            // Gửi Tác giả
            if (self::shouldSendTemplate($authorTemplate)) {
                $authorPhones = self::getAuthorPhones($submission);
                if (!empty($authorPhones)) {
                    $authorMsg = MessageHelper::buildMessage($authorTemplate, $data);
                    ZaloApiClient::sendToPhones(
                        $authorMsg, $botId, $apiKey, $authorPhones,
                        'PUBLISH_AUTHOR', self::getSubmissionContextId($submission)
                    );
                }
            }

        } catch (\Throwable $e) {
            self::writeDebug("LỖI handlePublish: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handlePublish', $e->getMessage(), $submissionId ?? 0, $e);
        }
    }

    // =========================================================================
    // XỬ LÝ HỦY XUẤT BẢN
    // Hook: Publication::unpublish — $newPublication, $submission
    // =========================================================================
    public static function handleUnpublish($publication, $submission)
    {
        try {
            if (!$publication || !$submission)
                return;

            $submissionId = (int) $submission->getId();

            self::writeDebug("handleUnpublish START — submissionId:{$submissionId}");

            $uniqueKey = "UNPUBLISHED_{$submissionId}_" . $publication->getId() . "_" . time();

            $title = strip_tags($publication->getLocalizedTitle() ?? "ID #{$submissionId}");
            $author = MessageHelper::getAuthorsString($publication);

            $settings = self::getZaloSettings();
            $botId = $settings['botId'];
            $apiKey = $settings['apiKey'];

            $templates = self::$plugin ? self::$plugin->getMessageTemplates() : [];
            $editorTemplate = $templates['editor']['unpublish'] ?? '';
            $authorTemplate = $templates['author']['unpublish'] ?? '';

            $data = array_merge(MessageHelper::getNavigationData($submission), [
                'title' => $title,
                'author' => $author,
                'timestamp' => date('d/m/Y H:i:s')
            ]);

            // Gửi chung
            if (self::shouldSendTemplate($editorTemplate)) {
                $generalMessage = MessageHelper::buildMessage($editorTemplate, $data);
                self::writeDebug("handleUnpublish: Chuẩn bị gửi Zalo chung...");
                $zaloStatus = self::sendToEditorAudience(
                    $submission, 'UNPUBLISH', $generalMessage, $settings,
                    'UNPUBLISH_ASSIGNED_EDITOR'
                );
                $performedBy = MessageHelper::getCurrentUserName();
                ActivityLogger::logUnpublish($submissionId, (int) $publication->getId(), $title, $author, $performedBy, $zaloStatus);
                self::writeDebug("handleUnpublish: Gửi xong chung — status: {$zaloStatus}");

            } else {
                $performedBy = MessageHelper::getCurrentUserName();
                ActivityLogger::logUnpublish($submissionId, (int) $publication->getId(), $title, $author, $performedBy, 'SKIPPED');
            }

            // Gửi Tác giả
            if (self::shouldSendTemplate($authorTemplate)) {
                $authorPhones = self::getAuthorPhones($submission);
                if (!empty($authorPhones)) {
                    $authorMsg = MessageHelper::buildMessage($authorTemplate, $data);
                    ZaloApiClient::sendToPhones(
                        $authorMsg, $botId, $apiKey, $authorPhones,
                        'UNPUBLISH_AUTHOR', self::getSubmissionContextId($submission)
                    );
                }
            }

        } catch (\Throwable $e) {
            self::writeDebug("LỖI handleUnpublish: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('handleUnpublish', $e->getMessage(), $submissionId ?? 0, $e);
        }
    }

}
