<?php


import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.AjaxModal');
import('lib.pkp.classes.core.JSONMessage');

require_once(__DIR__ . '/ActivityLogger.inc.php');
require_once(__DIR__ . '/MessageHelper.inc.php');
require_once(__DIR__ . '/migrations/ZaloNotificationMigration.inc.php');
require_once(__DIR__ . '/services/ZaloSettingsProvider.inc.php');
require_once(__DIR__ . '/services/ZaloOutboxRepository.inc.php');
require_once(__DIR__ . '/services/NotificationRecipientResolver.inc.php');
require_once(__DIR__ . '/services/ReviewReminderDashboardService.inc.php');
require_once(__DIR__ . '/services/PostSaveNotificationDispatcher.inc.php');
require_once(__DIR__ . '/ZaloApiClient.inc.php');
require_once(__DIR__ . '/StageChangeHandler.inc.php');
require_once(__DIR__ . '/handlers/SubmissionNotificationHandler.inc.php');
require_once(__DIR__ . '/handlers/ReviewNotificationHandler.inc.php');
require_once(__DIR__ . '/handlers/PublicationNotificationHandler.inc.php');
require_once(__DIR__ . '/handlers/EditorialNotificationHandler.inc.php');

class ZaloNotificationPlugin extends GenericPlugin
{
    private const DEBUG_LOG_MAX_SIZE = 1048576; // 1 MB
    private const DEBUG_LOG_ROTATED_FILES = 2;

    /**
     * Trả về thư mục ghi file an toàn cho plugin (log, debug, lock...).
     * Ưu tiên: files_dir/zalo_notification > sys_get_temp_dir > __DIR__.
     * Thư mục được giới hạn quyền và chặn truy cập trực tiếp từ web server.
     * Tương thích cả Windows (XAMPP) và Linux server.
     */
    public static function getDataDir(): string
    {
        static $dataDir = null;
        if ($dataDir !== null) {
            return $dataDir;
        }

        // 1. Ưu tiên files_dir của OJS (thư mục upload, luôn ghi được trên mọi server)
        try {
            if (class_exists('Config')) {
                $filesDir = \Config::getVar('files', 'files_dir');
                if ($filesDir && is_dir($filesDir) && is_writable($filesDir)) {
                    $candidate = rtrim($filesDir, '/\\') . '/zalo_notification';
                    if (!is_dir($candidate)) {
                        @mkdir($candidate, 0750, true);
                    }
                    if (is_dir($candidate) && is_writable($candidate)) {
                        self::protectDataDir($candidate);
                        $dataDir = $candidate;
                        return $dataDir;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Config chưa sẵn sàng — bỏ qua
        }

        // 2. Fallback: sys_get_temp_dir (luôn ghi được trên mọi OS)
        $tmpDir = sys_get_temp_dir() . '/zalo_notification';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0750, true);
        }
        if (is_dir($tmpDir) && is_writable($tmpDir)) {
            self::protectDataDir($tmpDir);
            $dataDir = $tmpDir;
            return $dataDir;
        }

        // 3. Fallback cuối cùng: thư mục plugin (chỉ hoạt động trên XAMPP/dev)
        self::protectDataDir(__DIR__);
        $dataDir = __DIR__;
        return $dataDir;
    }

    /**
     * Giới hạn quyền đọc thư mục dữ liệu và chặn tải log qua HTTP.
     */
    private static function protectDataDir(string $directory): void
    {
        // Không đổi quyền thư mục mã plugin khi phải dùng fallback cuối cùng.
        // Việc đổi quyền tại đây có thể làm Apache/PHP mất quyền nạp plugin.
        if (realpath($directory) !== realpath(__DIR__)) {
            @chmod($directory, 0750);
        }

        $htaccess = $directory . '/.htaccess';
        if (!file_exists($htaccess) && is_writable($directory)) {
            @file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
                LOCK_EX
            );
            @chmod($htaccess, 0640);
        }

        $indexFile = $directory . '/index.html';
        if (!file_exists($indexFile) && is_writable($directory)) {
            @file_put_contents($indexFile, '', LOCK_EX);
            @chmod($indexFile, 0640);
        }
    }

    /**
     * Ghi debug có xoay file và che dữ liệu nhạy cảm.
     */
    public static function writeSecureDebug(string $message, string $component = 'Plugin'): void
    {
        $debugFile = self::getDataDir() . '/debug.txt';
        self::rotateDebugLogIfNeeded($debugFile);

        $message = preg_replace('/[\r\n]+/', ' ', $message);
        $message = preg_replace('/\bzlite_[A-Za-z0-9_-]+\b/i', '[API_KEY_REDACTED]', $message);
        $message = preg_replace('/((?:api[ _-]?key|x-api-key)\s*[:=]\s*)[^\s|,;]+/i', '$1[REDACTED]', $message);
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[EMAIL_REDACTED]', $message);
        $message = preg_replace_callback(
            '/(?<!\d)(?:\+?84|0084|0)\d{9}(?!\d)/',
            function (array $matches): string {
                $phone = preg_replace('/\D/', '', $matches[0]);
                return substr($phone, 0, 2) . '******' . substr($phone, -3);
            },
            $message
        );

        $component = preg_replace('/[^A-Za-z0-9_-]/', '', $component) ?: 'Plugin';
        @file_put_contents(
            $debugFile,
            '[' . date('Y-m-d H:i:s') . '] [' . $component . '] ' . $message . "\n",
            FILE_APPEND | LOCK_EX
        );
        @chmod($debugFile, 0600);
    }

    private static function rotateDebugLogIfNeeded(string $debugFile): void
    {
        if (!file_exists($debugFile) || filesize($debugFile) < self::DEBUG_LOG_MAX_SIZE) {
            return;
        }

        $oldest = $debugFile . '.' . self::DEBUG_LOG_ROTATED_FILES;
        if (file_exists($oldest)) {
            @unlink($oldest);
        }
        for ($i = self::DEBUG_LOG_ROTATED_FILES - 1; $i >= 1; $i--) {
            $source = $debugFile . '.' . $i;
            if (file_exists($source)) {
                @rename($source, $debugFile . '.' . ($i + 1));
            }
        }
        @rename($debugFile, $debugFile . '.1');
    }


    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            HookRegistry::register('AcronPlugin::parseCronTab', [$this, 'callbackParseCronTab']);

            if ($this->getEnabled($mainContextId)) {
                // Hỗ trợ cả cài mới và nâng cấp thủ công: phép tạo bảng này là idempotent.
                ZaloOutboxRepository::ensureSchema();

                // Hook này được gọi tại Repository::add() và khi tạo draft
                HookRegistry::register('Submission::add', [$this, 'onSubmissionAddCallback']);
                HookRegistry::register('Submission::edit', [$this, 'onSubmissionEditCallback']);
                // SubmissionFile::add chạy sau khi OJS đã lưu file; handler chỉ nhận bản sửa do tác giả tải lên.
                HookRegistry::register('SubmissionFile::add', [$this, 'onSubmissionFileAddCallback']);
                
                // Hook này được gọi khi tác giả hoàn tất bước 4 (Finish Submission)
                HookRegistry::register('submissionsubmitstep4form::execute', [$this, 'onSubmissionSubmitStep4Callback']);

                // 2. Luồng theo dõi mọi quyết định của Biên tập viên (OJS 3.3).
                // Hook được gọi trong EditorAction::recordDecision trước khi OJS lưu quyết định.
                HookRegistry::register('EditorAction::recordDecision', [$this, 'onDecisionAddCallback33']);

                // 3. Luồng theo dõi bài báo được XUẤT BẢN chính thức
                // Hook này được gọi tại Publication\Repository::publish() sau khi status chuyển sang PUBLISHED
                HookRegistry::register('Publication::publish', [$this, 'onPublicationPublishCallback']);

                // 4. Luồng theo dõi bài báo bị HỦY XUẤT BẢN
                // Hook này được gọi tại Publication\Repository::unpublish()
                HookRegistry::register('Publication::unpublish', [$this, 'onPublicationUnpublishCallback']);

                // 5. Bắt các sự kiện liên quan đến Review (Gửi yêu cầu, Nhắc nhở) qua email hook
                // Hook này được gọi mỗi khi hệ thống chuẩn bị gửi email (Mail::send)
                HookRegistry::register('Mail::send', [$this, 'onMailSendCallback']);
                HookRegistry::register('EditorAction::addReviewer', [$this, 'onAddReviewerCallback']);
                HookRegistry::register('EditorAction::setDueDates', [$this, 'onSetDueDatesCallback']);
                HookRegistry::register('ReviewerAction::confirmReview', [$this, 'onReviewerConfirmReviewCallback']);
                HookRegistry::register('EditorAction::clearReview', [$this, 'onClearReviewCallback']);
                HookRegistry::register('EditorAction::reinstateReview', [$this, 'onReinstateReviewCallback']);
                HookRegistry::register('reviewerreviewstep3form::execute', [$this, 'onReviewerReviewCompletedCallback']);
                HookRegistry::register('pkpreviewerreviewstep3form::execute', [$this, 'onReviewerReviewCompletedCallback']);
                HookRegistry::register('addparticipantform::execute', [$this, 'onAddEditorParticipantCallback']);
                HookRegistry::register('TemplateManager::display', [$this, 'registerReviewerAssessmentPrototype']);

                // 6. Thêm Tab Activity Log vào trang Settings > Website để dễ truy cập
                HookRegistry::register('Template::Settings::website', [$this, 'callbackShowWebsiteSettingsTabs']);

                // 7. Truyền plugin instance vào StageChangeHandler để handler đọc được settings
                StageChangeHandler::setPlugin($this);

                // Quét quá hạn được thực hiện bởi ZaloOverdueReviewTask, không chạy trong request.
            }
            return true;
        }
        return false;
    }

    /**
     * Bắt sự kiện bài báo được nộp lần đầu tiên.
     * Hook 'Submission::add' chỉ fire một lần duy nhất khi tác giả hoàn tất nộp bài.
     */
    public function onSubmissionAddCallback($hookName, $args)
    {
        $submission = isset($args[0]) ? $args[0] : null;
        StageChangeHandler::handleSubmission($submission, null, 'Submission::add');
        return false;
    }

    /**
     * Bắt sự kiện bài báo được cập nhật (khi hoàn tất submit, dateSubmitted sẽ được set).
     */
    public function onSubmissionEditCallback($hookName, $args)
    {
        $newSubmission = isset($args[0]) ? $args[0] : null;
        $oldSubmission = isset($args[1]) ? $args[1] : null;
        if ($newSubmission && $oldSubmission) {
            PostSaveNotificationDispatcher::deferSubmissionEdit($this, $newSubmission, $oldSubmission);
        }
        return false;
    }

    /**
     * Bắt sự kiện tác giả hoàn tất bước 4 (Finish Submission).
     * Đây là hook chính xác nhất để biết bài nộp đã xong trên OJS 3.3.
     */
    public function onSubmissionSubmitStep4Callback($hookName, $args)
    {
        $form = isset($args[0]) ? $args[0] : null;
        if ($form) {
            StageChangeHandler::handleSubmissionSubmitStep4Form($form);
        }
        return false;
    }

    /**
     * Bắt sự kiện Biên tập viên ra quyết định.
     * $args[0] là Submission; $args[1] là mảng dữ liệu quyết định.
     */
    public function onDecisionAddCallback33($hookName, $args)
    {
        $submission = isset($args[0]) ? $args[0] : null;
        $editorDecision = isset($args[1]) ? $args[1] : null;
        if ($submission && $editorDecision) {
            PostSaveNotificationDispatcher::deferDecision($this, $submission, $editorDecision);
        }
        return false;
    }

    /**
     * Bắt sự kiện bài báo được XUẤT BẢN chính thức.
     * Hook 'Publication::publish' — args: [&$newPublication, $publication, $submission]
     * Chỉ gửi thông báo khi status thực sự là STATUS_PUBLISHED (không phải STATUS_SCHEDULED).
     */
    public function onPublicationPublishCallback($hookName, $args)
    {
        $newPublication = isset($args[0]) ? $args[0] : null;
        $submission = isset($args[2]) ? $args[2] : null;
        if ($newPublication && $submission) {
            StageChangeHandler::handlePublish($newPublication, $submission);
        }
        return false;
    }

    /**
     * Bắt sự kiện bài báo bị HỦY XUẤT BẢN.
     * Hook 'Publication::unpublish' — args: [&$newPublication, $publication, $submission, $context]
     */
    public function onPublicationUnpublishCallback($hookName, $args)
    {
        $newPublication = isset($args[0]) ? $args[0] : null;
        $submission = isset($args[2]) ? $args[2] : null;
        if ($newPublication && $submission) {
            StageChangeHandler::handleUnpublish($newPublication, $submission);
        }
        return false;
    }

    /**
     * Bắt sự kiện gửi email của hệ thống.
     * Dùng để thay thế hook ReviewAssignment::edit không tồn tại trong OJS 3.3.
     */
    public function onMailSendCallback($hookName, $args)
    {
        try {
            $mail = isset($args[0]) ? $args[0] : null;
            if ($mail && is_a($mail, 'SubmissionMailTemplate')) {
                PostSaveNotificationDispatcher::deferMail($this, $mail);
            }
        } catch (\Throwable $e) {
            $this->writeDebug("LỖI onMailSendCallback: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
        }

        return false;
    }

    /**
     * Bắt sự kiện editor phân công phản biện cho bài báo.
     */
    public function onAddReviewerCallback($hookName, $args)
    {
        try {
            $submission = isset($args[0]) ? $args[0] : null;
            $reviewerId = isset($args[1]) ? (int) $args[1] : 0;
            if ($submission && $reviewerId) {
                StageChangeHandler::handleReviewerAssigned($submission, $reviewerId);
            }
        } catch (\Throwable $e) {
            $this->writeDebug("LỖI onAddReviewerCallback: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
        }

        return false;
    }

    /** Bắt bản sửa phản biện đã được OJS lưu thành công. */
    public function onSubmissionFileAddCallback($hookName, $args)
    {
        try {
            $submissionFile = isset($args[0]) ? $args[0] : null;
            $request = isset($args[1]) ? $args[1] : null;
            if ($submissionFile) {
                StageChangeHandler::handleAuthorRevisionUploaded($submissionFile, $request);
            }
        } catch (\Throwable $e) {
            $this->writeDebug('LỖI onSubmissionFileAddCallback: ' . $e->getMessage());
        }
        return false;
    }

    /** @copydoc Plugin::getInstallMigration() */
    public function getInstallMigration()
    {
        return new ZaloNotificationMigration();
    }

    /** Đăng ký worker outbox với plugin Acron của OJS. */
    public function callbackParseCronTab($hookName, $args)
    {
        $taskFilesPath =& $args[0];
        $taskFile = $this->getPluginPath() . DIRECTORY_SEPARATOR . 'scheduledTasks.xml';
        if (!in_array($taskFile, $taskFilesPath, true)) {
            $taskFilesPath[] = $taskFile;
        }
        return false;
    }

    /**
     * Gửi Zalo khi một biên tập viên mới được phân công vào bài.
     * Hook chạy sau khi AddParticipantForm đã tạo stage assignment.
     */
    public function onAddEditorParticipantCallback($hookName, $args)
    {
        try {
            $form = isset($args[0]) ? $args[0] : null;
            if (!$form || !method_exists($form, 'getSubmission')) {
                return false;
            }

            // Đây là thao tác sửa quyền của phân công hiện có, không phải phân công mới.
            if (property_exists($form, '_assignmentId') && !empty($form->_assignmentId)) {
                return false;
            }

            $submission = $form->getSubmission();
            $userId = (int) $form->getData('userId');
            $userGroupId = (int) $form->getData('userGroupId');
            $stageId = method_exists($form, 'getStageId') ? (int) $form->getStageId() : 0;
            if ($submission && $userId && $userGroupId) {
                StageChangeHandler::handleEditorAssigned($submission, $userId, $userGroupId, $stageId);
            }
        } catch (\Throwable $e) {
            $this->writeDebug('LỖI onAddEditorParticipantCallback: ' . $e->getMessage() . ' [' . $e->getFile() . ':' . $e->getLine() . ']');
        }

        return false;
    }

    /**
     * Nạp bản thử nghiệm nút mở bảng đánh giá ở bước 3 của phản biện viên.
     * Script tự bỏ qua tất cả trang không chứa biểu mẫu reviewStep3Form.
     */
    public function registerReviewerAssessmentPrototype($hookName, $args)
    {
        $templateMgr = isset($args[0]) ? $args[0] : null;
        if (!$templateMgr) {
            return false;
        }

        $request = \Application::get()->getRequest();
        $baseUrl = rtrim($request->getBaseUrl(), '/');
        $assetBase = $baseUrl . '/' . $this->getPluginPath();
        $templateMgr->addJavaScript(
            'zaloReviewerAssessmentPrototype',
            $assetBase . '/js/reviewerAssessmentPrototype.js',
            ['contexts' => 'backend']
        );
        $templateMgr->addStyleSheet(
            'zaloReviewerAssessmentPrototype',
            $assetBase . '/styles/reviewerAssessmentPrototype.css',
            ['contexts' => 'backend']
        );

        return false;
    }

    public function onSetDueDatesCallback($hookName, $args)
    {
        try {
            $reviewAssignment = isset($args[0]) ? $args[0] : null;
            $reviewer = isset($args[1]) ? $args[1] : null;
            $reviewDueDate = isset($args[2]) ? $args[2] : null;
            $responseDueDate = isset($args[3]) ? $args[3] : null;
            if ($reviewAssignment) {
                PostSaveNotificationDispatcher::deferReviewDueDates(
                    $this, $reviewAssignment, $reviewDueDate, $responseDueDate
                );
            }
        } catch (\Throwable $e) {
            $this->writeDebug("LOI onSetDueDatesCallback: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
        }

        return false;
    }

    public function onReviewerConfirmReviewCallback($hookName, $args)
    {
        try {
            $request = isset($args[0]) ? $args[0] : null;
            $submission = isset($args[1]) ? $args[1] : null;
            $email = isset($args[2]) ? $args[2] : null;
            $decline = isset($args[3]) ? (bool) $args[3] : false;
            if ($submission && $email) {
                PostSaveNotificationDispatcher::deferReviewerResponse($this, $submission, $email, $decline);
            }
        } catch (\Throwable $e) {
            $this->writeDebug("LOI onReviewerConfirmReviewCallback: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
        }

        return false;
    }

    public function onClearReviewCallback($hookName, $args)
    {
        try {
            $submission = isset($args[0]) ? $args[0] : null;
            $reviewAssignment = isset($args[1]) ? $args[1] : null;
            if ($submission && $reviewAssignment) {
                StageChangeHandler::handleReviewAssignmentStatusChanged($submission, $reviewAssignment, 'cancelled');
            }
        } catch (\Throwable $e) {
            $this->writeDebug("LOI onClearReviewCallback: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
        }

        return false;
    }

    public function onReinstateReviewCallback($hookName, $args)
    {
        try {
            $submission = isset($args[0]) ? $args[0] : null;
            $reviewAssignment = isset($args[1]) ? $args[1] : null;
            if ($submission && $reviewAssignment) {
                StageChangeHandler::handleReviewAssignmentStatusChanged($submission, $reviewAssignment, 'reinstated');
            }
        } catch (\Throwable $e) {
            $this->writeDebug("LOI onReinstateReviewCallback: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
        }

        return false;
    }

    /**
     * Bắt sự kiện phản biện viên hoàn tất nộp đánh giá.
     */
    public function onReviewerReviewCompletedCallback($hookName, $args)
    {
        try {
            $form = isset($args[0]) ? $args[0] : null;
            if ($form) {
                $this->writeDebug("onReviewerReviewCompletedCallback: Bat duoc su kien reviewerreviewstep3form::execute.");
                StageChangeHandler::handleReviewerReviewCompleted($form);
            }
        } catch (\Throwable $e) {
            $this->writeDebug("LỖI onReviewerReviewCompletedCallback: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
        }

        return false;
    }
    /**
     * Thêm các tab Activity Log và Nhắc phản biện vào Settings > Website.
     */
    public function callbackShowWebsiteSettingsTabs($hookName, $args)
    {
        $templateMgr = $args[1];
        $output = &$args[2];

        $request = \Registry::get('request');
        $dispatcher = $request->getDispatcher();
        $zaloLogUrl = $dispatcher->url(
            $request,
            ROUTE_COMPONENT,
            null,
            'grid.settings.plugins.SettingsPluginGridHandler',
            'manage',
            null,
            ['category' => 'generic', 'plugin' => $this->getName(), 'verb' => 'activityLog']
        );
        $zaloReviewDashboardUrl = $dispatcher->url(
            $request,
            ROUTE_COMPONENT,
            null,
            'grid.settings.plugins.SettingsPluginGridHandler',
            'manage',
            null,
            ['category' => 'generic', 'plugin' => $this->getName(), 'verb' => 'reviewDashboardTab']
        );
        $zaloOutboxDashboardUrl = $dispatcher->url(
            $request,
            ROUTE_COMPONENT,
            null,
            'grid.settings.plugins.SettingsPluginGridHandler',
            'manage',
            null,
            ['category' => 'generic', 'plugin' => $this->getName(), 'verb' => 'outboxDashboardTab']
        );

        $zaloApiSettingsUrl = $this->getPluginManageUrl($request, 'apiTab');
        $zaloGroupSettingsUrl = $this->getPluginManageUrl($request, 'settingsTab');
        $zaloTemplatesUrl = $this->getPluginManageUrl($request, 'templatesTab');

        $templateMgr->assign([
            'zaloApiSettingsUrl' => $zaloApiSettingsUrl,
            'zaloGroupSettingsUrl' => $zaloGroupSettingsUrl,
            'zaloTemplatesUrl' => $zaloTemplatesUrl,
            'zaloLogUrl' => $zaloLogUrl,
            'zaloReviewDashboardUrl' => $zaloReviewDashboardUrl,
            'zaloOutboxDashboardUrl' => $zaloOutboxDashboardUrl,
        ]);
        $output .= $templateMgr->fetch($this->getTemplateResource('websiteSettingsTab.tpl'));

        return false;
    }

    private function getPluginManageUrl($request, string $verb): string
    {
        $dispatcher = $request->getDispatcher();
        return $dispatcher->url(
            $request,
            ROUTE_COMPONENT,
            null,
            'grid.settings.plugins.SettingsPluginGridHandler',
            'manage',
            null,
            [
                'category' => 'generic',
                'plugin' => $this->getName(),
                'verb' => $verb,
            ]
        );
    }

    /** Chỉ cho phép các thao tác thay đổi dữ liệu qua POST có CSRF hợp lệ. */
    private function isValidWriteRequest($request): bool
    {
        return strtoupper((string) $request->getRequestMethod()) === 'POST'
            && $request->checkCSRF();
    }

    // =========================================================================
    // SETTINGS TAB — Activity Log
    // =========================================================================

    /**
     * Đăng ký nút "Activity Log" trong danh sách plugin.
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb)
    {
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $this->getPluginManageUrl($request, 'settings'),
                        'Cài đặt API & Nhóm — ' . $this->getDisplayName()
                    ),
                    'Settings',
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
    }

    /**
     * Xử lý các verb quản lý plugin.
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {

            // =================================================================
            // SETTINGS TABS
            // =================================================================
            case 'settings':
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'apiTabUrl' => $this->getPluginManageUrl($request, 'apiTab'),
                    'settingsTabUrl' => $this->getPluginManageUrl($request, 'settingsTab'),
                    'templatesTabUrl' => $this->getPluginManageUrl($request, 'templatesTab'),
                ]);

                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('settingsTabs.tpl')));

            // =================================================================
            // API SETTINGS TAB — Cấu hình API Zalo
            // =================================================================
            case 'apiTab':
                $contextId = 0;
                $context = $request->getContext();
                if ($context)
                    $contextId = $context->getId();

                $saveUrl = $this->getPluginManageUrl($request, 'saveApiSettings');

                $botId = $this->getSetting($contextId, 'botId');
                $savedApiKey = trim((string) ($this->getSetting($contextId, 'apiKey') ?? ''));

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'botId' => $botId,
                    'apiKeyConfigured' => $savedApiKey !== '',
                    'saveUrl' => $saveUrl,
                ]);

                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('apiSettingsForm.tpl')));

            case 'saveApiSettings':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                $contextId = 0;
                $context = $request->getContext();
                if ($context)
                    $contextId = $context->getId();

                $botId = trim($request->getUserVar('botId') ?? '');
                $apiKey = trim($request->getUserVar('apiKey') ?? '');

                $this->updateSetting($contextId, 'botId', $botId);
                // Không gửi khóa hiện có về trình duyệt. Để trống khi lưu nghĩa
                // là giữ nguyên API key đã cấu hình trước đó.
                if ($apiKey !== '') {
                    $this->updateSetting($contextId, 'apiKey', $apiKey);
                }

                $this->writeDebug('saveApiSettings: Đã lưu API Settings.');

                return new JSONMessage(true);

            // =================================================================
            // SETTINGS TAB — Cấu hình API và nhóm nhận thông báo
            // =================================================================
            case 'settingsTab':
                $contextId = 0;
                $context = $request->getContext();
                if ($context)
                    $contextId = $context->getId();

                $saveUrl = $this->getPluginManageUrl($request, 'saveSettings');

                $groupsJson = $this->getSetting($contextId, 'recipientGroups');
                $groups = $groupsJson ? json_decode($groupsJson, true) : [];
                if (!is_array($groups))
                    $groups = [];

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'pluginName' => $this->getName(),
                    'recipientGroupsJson' => json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                    'saveUrl' => $saveUrl,
                ]);

                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('settingsForm.tpl')));

            case 'saveSettings':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                $contextId = 0;
                $context = $request->getContext();
                if ($context)
                    $contextId = $context->getId();

                $groupsJsonRaw = $request->getUserVar('recipientGroupsJson') ?? '[]';

                $groups = json_decode($groupsJsonRaw, true);
                if (!is_array($groups))
                    $groups = [];

                $allowedEvents = [
                    'SUBMISSION',
                    'DECISION',
                    'PUBLISH',
                    'UNPUBLISH',
                    'REVIEW_REQUEST',
                    'REVIEW_RESPONSE',
                    'REVIEW_REMINDER',
                    'REVIEW_COMPLETED',
                ];

                // Clean up — chỉ lưu nhóm có tên hoặc số điện thoại
                $cleanGroups = [];
                foreach ($groups as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    $name = trim($group['name'] ?? '');

                    $rawPhones = [];
                    if (isset($group['phones'])) {
                        $rawPhones = is_array($group['phones'])
                            ? $group['phones']
                            : preg_split('/[,\r\n;]+/', (string) $group['phones']);
                    }
                    $phones = array_values(array_unique(array_filter(array_map('trim', $rawPhones), function ($phone) {
                        return $phone !== '';
                    })));

                    $rawEvents = isset($group['events']) && is_array($group['events'])
                        ? $group['events']
                        : [];
                    $events = array_values(array_unique(array_filter($rawEvents, function ($event) use ($allowedEvents) {
                        return in_array($event, $allowedEvents, true);
                    })));

                    if (!empty($name) || !empty($phones)) {
                        $cleanGroups[] = [
                            'name' => $name,
                            'phones' => $phones,
                            'events' => $events,
                        ];
                    }
                }

                $this->updateSetting($contextId, 'recipientGroups', json_encode($cleanGroups, JSON_UNESCAPED_UNICODE));

                $this->writeDebug("saveSettings: Đã lưu groups=" . count($cleanGroups));

                return new JSONMessage(true);

            // =================================================================
            // MESSAGE TEMPLATES TAB
            // =================================================================
            case 'templatesTab':
                $contextId = 0;
                $context = $request->getContext();
                if ($context)
                    $contextId = $context->getId();

                $saveUrl = $this->getPluginManageUrl($request, 'saveTemplates');

                $templates = $this->getMessageTemplates();
                $defaultTemplates = $this->getDefaultTemplates();
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'pluginName' => $this->getName(),
                    'templatesJson' => json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                    'defaultTemplatesJson' => json_encode($defaultTemplates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                    'saveUrl' => $saveUrl,
                ]);

                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('notificationTemplatesForm.tpl')));

            // =================================================================
            // REVIEW REMINDER DASHBOARD
            // =================================================================
            case 'reviewDashboardTab':
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                $submissions = ReviewReminderDashboardService::getSubmissions($contextId);
                $reviewerCount = 0;
                $overdueCount = 0;
                foreach ($submissions as $submission) {
                    $reviewerCount += count($submission['reviewers']);
                    foreach ($submission['reviewers'] as $reviewer) {
                        if ($reviewer['dueClass'] === 'overdue') {
                            $overdueCount++;
                        }
                    }
                }
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'submissions' => $submissions,
                    'submissionCount' => count($submissions),
                    'reviewerCount' => $reviewerCount,
                    'overdueCount' => $overdueCount,
                    'sendReminderUrl' => $this->getPluginManageUrl($request, 'sendReviewReminder'),
                ]);
                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('reviewReminderDashboard.tpl')));

            case 'sendReviewReminder':
                if (strtoupper((string) $request->getRequestMethod()) !== 'POST' || !$request->checkCSRF()) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                $scope = (string) $request->getUserVar('scope');
                $assignments = [];
                if ($scope === 'reviewer') {
                    $assignment = ReviewReminderDashboardService::getActiveAssignment((int) $request->getUserVar('reviewId'), $contextId);
                    if ($assignment) {
                        $assignments[] = $assignment;
                    }
                } elseif ($scope === 'submission') {
                    $assignments = ReviewReminderDashboardService::getActiveAssignmentsForSubmission(
                        (int) $request->getUserVar('submissionId'), $contextId
                    );
                }
                if (empty($assignments)) {
                    return new JSONMessage(false, 'Không tìm thấy lượt phản biện đang mở trong tạp chí này.');
                }

                $sent = 0;
                $skipped = [];
                foreach ($assignments as $assignment) {
                    $submission = \DAORegistry::getDAO('SubmissionDAO')->getById((int) $assignment->getSubmissionId());
                    $result = ReviewNotificationHandler::sendManualReminder($assignment, $submission);
                    if (!empty($result['success'])) {
                        $sent++;
                    } else {
                        $skipped[] = $result['reviewerName'] . ': ' . $result['message'];
                    }
                }
                if ($sent === 0) {
                    return new JSONMessage(false, implode('; ', $skipped));
                }
                $message = "Gateway đã chấp nhận tin nhắc cho {$sent} phản biện viên.";
                if ($skipped) {
                    $message .= ' Bỏ qua: ' . implode('; ', $skipped);
                }
                return new JSONMessage(true, $message);

            case 'saveTemplates':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                $contextId = 0;
                $context = $request->getContext();
                if ($context)
                    $contextId = $context->getId();

                $templatesJsonRaw = $request->getUserVar('templatesJson') ?? '{}';
                $templates = json_decode($templatesJsonRaw, true);
                if (!is_array($templates))
                    $templates = [];

                $allowedRoles = ['editor', 'reviewer', 'author'];
                $allowedEvents = ['submission', 'decision', 'initial_decline', 'review_started', 'publish', 'unpublish', 'reminder', 'review_request', 'review_response', 'review_completed', 'author_revision', 'editor_assignment'];
                $cleanTemplates = [];

                foreach ($allowedRoles as $role) {
                    $cleanTemplates[$role] = [];
                    $roleTemplates = isset($templates[$role]) && is_array($templates[$role]) ? $templates[$role] : [];
                    foreach ($allowedEvents as $event) {
                        if (isset($roleTemplates[$event])) {
                            $cleanTemplates[$role][$event] = (string) $roleTemplates[$event];
                        }
                    }
                }

                // Remove JSON_UNESCAPED_UNICODE to escape 4-byte emojis as \uXXXX\uXXXX surrogate pairs.
                // This prevents OJS MySQL (utf8) from truncating or replacing emojis with ????
                $this->updateSetting($contextId, 'messageTemplates', json_encode($cleanTemplates));
                $this->writeDebug("saveTemplates: Đã lưu mẫu thông báo mới");

                return new JSONMessage(true);

            // =================================================================
            // ACTIVITY LOG
            // =================================================================
            case 'activityLog':
                $filterType = $request->getUserVar('filterType');
                $validTypes = self::getValidActivityLogTypes();
                if ($filterType && !in_array($filterType, $validTypes, true)) {
                    $filterType = null;
                }

                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                $statsDate = (string) ($request->getUserVar('statsDate') ?? date('Y-m-d'));
                $entries = ActivityLogger::getRecentEntries(PHP_INT_MAX, $filterType, $contextId);
                $outboxStats = ZaloOutboxRepository::getStats($contextId);
                $dailySummary = ActivityLogger::getDailySummary($statsDate, $contextId);

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'entries' => $entries,
                    'filterType' => $filterType,
                    'totalEntries' => count($entries),
                    'pluginName' => $this->getName(),
                    'outboxStats' => $outboxStats,
                    'dailyStats' => $dailySummary,
                    'selectedStatsDate' => $statsDate,
                    'statsActionUrl' => $this->getPluginManageUrl($request, 'dailySummaryStats'),
                ]);

                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('activityLog.tpl')));

            case 'dailySummaryStats':
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                $statsDate = (string) ($request->getUserVar('statsDate') ?? date('Y-m-d'));
                $dailySummary = ActivityLogger::getDailySummary($statsDate, $contextId);
                return new JSONMessage(true, $dailySummary);

            case 'exportLog':
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                $filterType = $request->getUserVar('filterType');
                $validTypes = self::getValidActivityLogTypes();
                if ($filterType && !in_array($filterType, $validTypes, true)) {
                    $filterType = null;
                }

                $logFileLocation = ActivityLogger::getLogFileLocation($contextId);
                $entries = ActivityLogger::getRecentEntries(PHP_INT_MAX, $filterType, $contextId);
                $content = '';
                foreach (array_reverse($entries) as $entry) {
                    $content .= json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
                }
                $filename = $filterType
                    ? preg_replace('/[^A-Z0-9_]/', '_', $filterType) . '_' . basename($logFileLocation)
                    : basename($logFileLocation);

                header('Content-Description: File Transfer');
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . strlen($content));
                echo $content;
                exit;

            // =================================================================
            // OUTBOX & RETRY DASHBOARD
            // =================================================================
            case 'outboxDashboardTab':
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                $statusFilter = (string) ($request->getUserVar('statusFilter') ?? 'all');
                
                $entries = ZaloOutboxRepository::getOutboxEntries($contextId, $statusFilter, 100);
                $stats = ZaloOutboxRepository::getStats($contextId);
                
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'entries' => $entries,
                    'stats' => $stats,
                    'totalItems' => count($entries),
                    'currentFilter' => $statusFilter,
                    'outboxActionUrl' => $this->getPluginManageUrl($request, 'outboxAction'),
                ]);
                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('outboxDashboard.tpl')));

            case 'retryOutboxItem':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                $outboxId = (int) $request->getUserVar('outboxId');
                if ($outboxId <= 0) {
                    return new JSONMessage(false, 'ID tin nhắn không hợp lệ.');
                }
                
                $success = ZaloOutboxRepository::retryItem($outboxId, $contextId);
                if ($success) {
                    $this->writeDebug("retryOutboxItem: Đã đưa tin #{$outboxId} về trạng thái chờ gửi");
                    return new JSONMessage(true, "Tin nhắn #{$outboxId} đã được đưa về trạng thái Chờ gửi.");
                }
                return new JSONMessage(false, "Không thể cập nhật tin nhắn #{$outboxId}.");

            case 'retryAllOutbox':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                
                $count = ZaloOutboxRepository::retryAllFailed($contextId);
                $this->writeDebug("retryAllOutbox: Đã đưa {$count} tin lỗi về trạng thái chờ gửi");
                return new JSONMessage(true, "Đã đưa {$count} tin nhắn lỗi về trạng thái Chờ gửi.");

            case 'deleteOutboxItem':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                $outboxId = (int) $request->getUserVar('outboxId');
                if ($outboxId <= 0) {
                    return new JSONMessage(false, 'ID tin nhắn không hợp lệ.');
                }
                
                $success = ZaloOutboxRepository::deleteItem($outboxId, $contextId);
                if ($success) {
                    $this->writeDebug("deleteOutboxItem: Đã xóa tin #{$outboxId}");
                    return new JSONMessage(true, "Đã xóa tin nhắn #{$outboxId} khỏi hàng đợi.");
                }
                return new JSONMessage(false, "Không thể xóa tin nhắn #{$outboxId}.");

            case 'clearSentOutbox':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                $context = $request->getContext();
                $contextId = $context ? (int) $context->getId() : 0;
                
                $count = ZaloOutboxRepository::clearSent($contextId);
                $this->writeDebug("clearSentOutbox: Đã dọn dẹp {$count} tin đã gửi");
                return new JSONMessage(true, "Đã dọn dẹp {$count} tin nhắn đã gửi thành công.");
        }

        return parent::manage($args, $request);
    }

    private static function getValidActivityLogTypes(): array
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

    // =========================================================================
    // PLUGIN INFO
    // =========================================================================

    public function getDisplayName()
    {
        return 'Zalo Notification Plugin';
    }

    public function getDescription()
    {
        return 'Theo dõi trạng thái bài báo trên OJS 3.3 và gửi thông báo tức thì về Zalo khi có bài nộp mới, quyết định biên tập, xuất bản, hoặc nhắc nhở phản biện tự động.';
    }

    // =========================================================================
    // HELPER — Đọc cấu hình Zalo API
    // =========================================================================

    /**
     * Lấy cấu hình Zalo API cho context hiện tại.
     * Trả về mảng ['botId', 'apiKey', 'recipientGroups'].
     * Được sử dụng bởi cả Plugin và StageChangeHandler.
     */
    public function getZaloSettings(): array
    {
        return $this->getZaloSettingsForContext(ZaloSettingsProvider::getCurrentContextId());
    }

    public function getZaloSettingsForContext(int $contextId): array
    {
        return ZaloSettingsProvider::getForContext($contextId);
    }

    /**
     * Lấy danh sách mẫu thông báo từ cấu hình, hoặc fallback sang mặc định
     */
    public function getMessageTemplates(): array
    {
        $contextId = 0;
        try {
            $request = \Application::get()->getRequest();
            $context = $request->getContext();
            if ($context)
                $contextId = $context->getId();
        } catch (\Throwable $e) {
        }

        return $this->getMessageTemplatesForContext($contextId);
    }

    /**
     * Lấy mẫu thông báo của một context cụ thể.
     * Dùng cho các tác vụ không thể dựa vào context của request hiện tại.
     */
    public function getMessageTemplatesForContext(int $contextId): array
    {
        $templatesJson = $this->getSetting($contextId, 'messageTemplates');
        $templates = $templatesJson ? json_decode($templatesJson, true) : [];
        $defaultTemplates = $this->getDefaultTemplates();
        if (!is_array($templates) || empty($templates)) {
            return $defaultTemplates;
        }
        return $this->ensureNavigationDetails(array_replace_recursive($defaultTemplates, $templates));
    }

    private function getDefaultTemplates(): array
    {
        $templates = [];
        $templates['editor'] = [
            'submission' => "▣ BÀI NỘP MỚI\n\n› Bài: {title}\n› Tác giả: {author}\n{abstract_if_any}› Trạng thái: Đã hoàn tất nộp bài\n\n→ Vui lòng đăng nhập OJS để phân công xử lý.\n⏱ Thời gian: {timestamp}",
            'decision' => "▣ QUYẾT ĐỊNH BIÊN TẬP\n\n› Bài: {title}\n› Tác giả: {author}\n› Giai đoạn: {stageName}\n› Quyết định: {decisionDesc}\n› Biên tập viên: {editorName}\n\n⏱ Thời gian: {timestamp}",
            'initial_decline' => "Bạn không có thông báo cho sự kiện này.",
            'review_started' => "Bạn không có thông báo cho sự kiện này.",
            'publish' => "✓ BÀI BÁO ĐÃ XUẤT BẢN\n\n› Bài: {title}\n› Tác giả: {author}\n{issueString_if_any}› Ngày xuất bản: {datePublished}\n\n⏱ Thời gian: {timestamp}",
            'unpublish' => "! HỦY XUẤT BẢN\n\n› Bài: {title}\n› Tác giả: {author}\n\n→ Vui lòng kiểm tra lại thông tin xuất bản trên OJS.\n⏱ Thời gian: {timestamp}",
            'reminder' => "! NHẮC HẠN PHẢN BIỆN\n\n› Bài: {title}\n› Phản biện viên: {reviewerName}\n› Hạn chót: {deadline}\n› Tình trạng: {daysLeft}\n› Vòng phản biện: {round}\n\n⏱ Thời gian: {timestamp}",
            'review_request' => "▣ ĐÃ MỜI PHẢN BIỆN\n\n› Bài: {title}\n› Tác giả: {author}\n› Phản biện viên: {reviewerName}\n› Hạn nộp đánh giá: {deadline}\n› Hạn phản hồi: {responseDeadline}\n› Vòng phản biện: {round}\n\n⏱ Thời gian: {timestamp}",
            'review_response' => "▣ PHẢN HỒI LỜI MỜI PHẢN BIỆN\n\n› Bài: {title}\n› Tác giả: {author}\n› Phản biện viên: {reviewerName}\n› Trạng thái: {responseStatus}\n› Hạn nộp đánh giá: {deadline}\n\n⏱ Thời gian: {timestamp}",
            'review_completed' => "✓ PHẢN BIỆN ĐÃ NỘP ĐÁNH GIÁ\n\n› Bài: {title}\n› Phản biện viên: {reviewerName}\n› Đề xuất: {recommendationDesc}\n\n→ Vui lòng đăng nhập OJS để xem chi tiết.\n⏱ Thời gian: {timestamp}",
            'author_revision' => "▣ TÁC GIẢ ĐÃ NỘP BẢN CHỈNH SỬA\n\n› Bài: {title}\n› Tác giả: {author}\n› Giai đoạn: {stageName}\n› Vòng phản biện: {round}\n\n→ Vui lòng đăng nhập OJS để kiểm tra bản chỉnh sửa.\n⏱ Thời gian: {timestamp}",
            'editor_assignment' => "▣ PHÂN CÔNG BIÊN TẬP\n\n› Xin chào: {editorName}\n› Bạn được phân công xử lý bài: {title}\n› Tác giả: {author}\n› Vai trò: {editorRole}\n› Giai đoạn: {stageName}\n› Người phân công: {assignedBy}\n\n⏱ Thời gian: {timestamp}",
        ];

        $templates['reviewer'] = [
            'submission' => "Bạn không có thông báo cho sự kiện này.",
            'decision' => "▣ CẬP NHẬT BÀI PHẢN BIỆN\n\n› Bài: {title}\n› Quyết định: {decisionDesc}\n\nCảm ơn bạn đã tham gia đánh giá.\n⏱ Thời gian: {timestamp}",
            'initial_decline' => "Bạn không có thông báo cho sự kiện này.",
            'review_started' => "Bạn không có thông báo cho sự kiện này.",
            'publish' => "Bạn không có thông báo cho sự kiện này.",
            'unpublish' => "Bạn không có thông báo cho sự kiện này.",
            'reminder' => "! NHẮC HẠN PHẢN BIỆN\n\n› Bài: {title}\n› Hạn chót: {deadline}\n› Tình trạng: {daysLeft}\n› Vòng phản biện: {round}\n\n→ Vui lòng đăng nhập OJS để hoàn tất đánh giá.\n⏱ Thời gian: {timestamp}",
            'review_request' => "▣ LỜI MỜI PHẢN BIỆN\n\n› Bài: {title}\n› Hạn nộp đánh giá: {deadline}\n› Hạn phản hồi: {responseDeadline}\n› Vòng phản biện: {round}\n\n→ Vui lòng đăng nhập OJS để phản hồi lời mời.\n⏱ Thời gian: {timestamp}",
            'review_response' => "Bạn không có thông báo cho sự kiện này.",
            'review_completed' => "Bạn không có thông báo cho sự kiện này.",
            'author_revision' => "Bạn không có thông báo cho sự kiện này.",
            'editor_assignment' => "Bạn không có thông báo cho sự kiện này.",
        ];

        $templates['author'] = [
            'submission' => "✓ NỘP BÀI THÀNH CÔNG\n\n› Bài: {title}\n› Tác giả: {author}\n\nBài viết đã được ghi nhận trên hệ thống. Ban biên tập sẽ sớm phân công xử lý.\n⏱ Thời gian: {timestamp}",
            'decision' => "▣ BÀI BÁO ĐÃ CÓ KẾT QUẢ PHẢN BIỆN\n\n› Bài: {title}\n› Yêu cầu: {decisionDesc}\n\n→ Đăng nhập OJS để xem nội dung và thực hiện yêu cầu: {authorUrl}\n⏱ Thời gian: {timestamp}",
            'initial_decline' => "▣ QUYẾT ĐỊNH BIÊN TẬP BAN ĐẦU\n\n› Bài: {title}\n› Quyết định: {decisionDesc}\n\n→ Đăng nhập OJS để xem thông tin chi tiết: {authorUrl}\n⏱ Thời gian: {timestamp}",
            'review_started' => "▣ BÀI BÁO ĐÃ CHUYỂN SANG PHẢN BIỆN\n\n› Bài: {title}\n› Trạng thái: Đã chuyển sang {stageName}\n\nBan biên tập đã bắt đầu quy trình phản biện bài viết.\n\n→ Theo dõi bài trên OJS: {authorUrl}\n⏱ Thời gian: {timestamp}",
            'publish' => "✓ BÀI BÁO ĐÃ XUẤT BẢN\n\n› Bài: {title}\n{issueString_if_any}Chúc mừng tác giả.\n⏱ Thời gian: {timestamp}",
            'unpublish' => "! BÀI BÁO ĐÃ BỊ HỦY XUẤT BẢN\n\n› Bài: {title}\n\n→ Vui lòng liên hệ Ban biên tập để biết thêm thông tin.\n⏱ Thời gian: {timestamp}",
            'reminder' => "Bạn không có thông báo cho sự kiện này.",
            'review_request' => "Bạn không có thông báo cho sự kiện này.",
            'review_response' => "Bạn không có thông báo cho sự kiện này.",
            'review_completed' => "Bạn không có thông báo cho sự kiện này.",
            'author_revision' => "Bạn không có thông báo cho sự kiện này.",
            'editor_assignment' => "Bạn không có thông báo cho sự kiện này.",
        ];

        return $this->ensureNavigationDetails($templates);
    }

    /**
     * Ensure upgraded installations also receive IDs and clickable deep links,
     * including when their templates were saved before these variables existed.
     */
    private function ensureNavigationDetails(array $templates): array
    {
        foreach (['editor', 'reviewer', 'author'] as $role) {
            if (empty($templates[$role]) || !is_array($templates[$role])) {
                continue;
            }

            foreach ($templates[$role] as $event => $template) {
                $template = (string) $template;
                if ($template === '' || strpos($template, 'Bạn không có thông báo') !== false) {
                    continue;
                }

                // Chuẩn hóa nhãn cũ trong các mẫu đã được lưu trước khi nâng cấp.
                $template = str_replace('Mã bài:', 'ID bài:', $template);
                $templates[$role][$event] = $template;

                $details = [];
                if (strpos($template, '{submissionId}') === false) {
                    $details[] = '› ID bài: {submissionId}';
                }
                if ($role === 'editor') {
                    if (strpos($template, '{stageId}') === false) {
                        $details[] = '› Mã giai đoạn: {stageId}';
                    }
                    if (strpos($template, '{workflowUrl}') === false) {
                        $details[] = '→ Mở đúng giai đoạn: {workflowUrl}';
                    }
                } elseif ($role === 'reviewer') {
                    if (in_array($event, ['reminder', 'review_request'], true) && strpos($template, '{reviewId}') === false) {
                        $details[] = '› Mã phản biện: {reviewId}';
                    }
                    if (strpos($template, '{reviewerUrl}') === false) {
                        $details[] = '→ Mở nhiệm vụ phản biện: {reviewerUrl}';
                    }
                } else {
                    if ($event === 'decision' && strpos($template, '{decisionDesc}') === false) {
                        $details[] = '› Yêu cầu: {decisionDesc}';
                    }
                    $urlVariable = $event === 'publish' ? '{publicUrl}' : '{authorUrl}';
                    if (strpos($template, $urlVariable) === false) {
                        $details[] = $event === 'publish'
                            ? '→ Xem bài đã xuất bản: {publicUrl}'
                            : '→ Mở bài trên OJS: {authorUrl}';
                    }
                }

                if ($details) {
                    $templates[$role][$event] = rtrim($template) . "\n\n" . implode("\n", $details);
                }
            }
        }

        return $templates;
    }

    /**
     * Ghi log debug nhanh vào debug.txt
     */
    private function writeDebug(string $message): void
    {
        self::writeSecureDebug($message, 'Plugin');
    }
}
