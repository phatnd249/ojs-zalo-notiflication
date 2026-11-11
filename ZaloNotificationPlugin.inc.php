<?php


import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.plugins.HookRegistry');
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.AjaxModal');
import('lib.pkp.classes.core.JSONMessage');

require_once(__DIR__ . '/ActivityLogger.inc.php');
require_once(__DIR__ . '/services/debug/ZaloDebugLogger.inc.php');
require_once(__DIR__ . '/services/templates/ZaloTemplateDefaults.inc.php');
require_once(__DIR__ . '/MessageHelper.inc.php');
require_once(__DIR__ . '/migrations/ZaloNotificationMigration.inc.php');
require_once(__DIR__ . '/services/ZaloSettingsProvider.inc.php');
require_once(__DIR__ . '/services/ZaloOutboxRepository.inc.php');
require_once(__DIR__ . '/services/NotificationRecipientResolver.inc.php');
require_once(__DIR__ . '/services/ReviewReminderDashboardService.inc.php');
require_once(__DIR__ . '/services/PostSaveNotificationDispatcher.inc.php');
require_once(__DIR__ . '/ZaloApiClient.inc.php');
require_once(__DIR__ . '/StageChangeHandler.inc.php');
require_once(__DIR__ . '/services/review/ReviewReminderService.inc.php');
require_once(__DIR__ . '/handlers/SubmissionNotificationHandler.inc.php');
require_once(__DIR__ . '/handlers/ReviewNotificationHandler.inc.php');
require_once(__DIR__ . '/handlers/PublicationNotificationHandler.inc.php');
require_once(__DIR__ . '/handlers/EditorialNotificationHandler.inc.php');
require_once(__DIR__ . '/services/security/RateLimiter.inc.php');
require_once(__DIR__ . '/controllers/ZaloReviewReminderController.inc.php');
require_once(__DIR__ . '/controllers/ZaloOutboxController.inc.php');
require_once(__DIR__ . '/controllers/ZaloActivityLogController.inc.php');
require_once(__DIR__ . '/controllers/ZaloSettingsController.inc.php');


class ZaloNotificationPlugin extends GenericPlugin
{
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
        ZaloDebugLogger::write($message, $component);
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

                // Prototype phiếu đánh giá phản biện đang thử nghiệm, tạm tắt ở bản phát hành
                // HookRegistry::register('TemplateManager::display', [$this, 'registerReviewerAssessmentPrototype']);

                // 6. Thêm Tab Activity Log vào trang Settings > Website để dễ truy cập
                HookRegistry::register('Template::Settings::website', [$this, 'callbackShowWebsiteSettingsTabs']);

                // 6b. Thêm Tab Điều hành & Nhắc Zalo kế bên tab Lưu trữ trên trang Các bài gửi (dashboard/index.tpl)
                HookRegistry::register('TemplateManager::display', [$this, 'callbackExtendSubmissionsDashboard']);

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

    /**
     * Bắt sự kiện hiển thị trang dashboard/index.tpl để gắn thêm tab Điều hành & Nhắc Zalo
     * ngay kế bên tab Lưu trữ trên trang Các bài gửi.
     */
    public function callbackExtendSubmissionsDashboard($hookName, $args)
    {
        $templateMgr = $args[0];
        $template = $args[1];

        if ($template === 'dashboard/index.tpl') {
            $templateMgr->registerFilter('output', [$this, 'filterDashboardSubmissionsTab']);
        }

        return false;
    }

    /**
     * Chèn thẻ <tab id="zaloDashboard" ...> ngay sau thẻ <tab id="archive" ...></tab>.
     */
    public function filterDashboardSubmissionsTab($output, $smarty)
    {
        if (strpos($output, 'id="zaloDashboard"') !== false) {
            return $output;
        }

        $archivePos = strpos($output, 'id="archive"');
        if ($archivePos === false) {
            return $output;
        }

        $closeTabPos = strpos($output, '</tab>', $archivePos);
        if ($closeTabPos === false) {
            return $output;
        }

        $insertPos = $closeTabPos + strlen('</tab>');

        $request = \Application::get()->getRequest();
        $reviewDashboardUrl = $this->getManageUrl($request, 'reviewDashboardTab') . '&isEmbedded=1';
        $reviewDashboardUrlJson = json_encode($reviewDashboardUrl);

        $tabHtml = '
		<tab id="zaloDashboard" label="Điều hành Zalo" :badge="34">
			<div v-pre id="zaloDashboardSubmissionsContainer" class="zrd-submissions-tab-container">
				<style>
					.pkpTabs__buttons { padding-right: 125px !important; }
				</style>
				<script type="text/javascript">
					$(function() {
						if (typeof $.pkp !== "undefined" && typeof $.pkp.controllers !== "undefined" && typeof $.pkp.controllers.UrlInDivHandler !== "undefined") {
							$("#zaloDashboardSubmissionsWrapper").pkpHandler(
								"$.pkp.controllers.UrlInDivHandler",
								{
									sourceUrl: ' . $reviewDashboardUrlJson . ',
									refreshOn: null
								}
							);
						} else {
							$.get(' . $reviewDashboardUrlJson . ', function(res) {
								var html = (res && res.content) ? res.content : res;
								$("#zaloDashboardSubmissionsWrapper").html(html);
							});
						}
					});
				</script>
				<div id="zaloDashboardSubmissionsWrapper">
					<div style="padding: 40px; text-align: center; color: #64748b; font-size: 14px;">
						<span class="pkp_spinner" style="display:inline-block; margin-right:8px;"></span>
						Đang tải dữ liệu Trung tâm Điều hành Phản biện &amp; Zalo Notification...
					</div>
				</div>
			</div>
		</tab>';

        return substr_replace($output, $tabHtml, $insertPos, 0);
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

    public function getManageUrl($request, string $verb): string
    {
        return $this->getPluginManageUrl($request, $verb);
    }

    public function getActivityLogEntries(?string $filterType, int $contextId): array
    {
        return ActivityLogger::getRecentEntries(PHP_INT_MAX, $filterType, $contextId);
    }

    public function getActivityLogFileLocation(int $contextId): string
    {
        return ActivityLogger::getLogFileLocation($contextId);
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
     * Kiểm tra phân quyền người dùng đối với các verb quản lý của plugin (RBAC).
     * @param PKPRequest $request
     * @param array $allowedRoles Danh sách Role ID được phép (ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR,...)
     * @return bool
     */
    public static function isUserAuthorized($request, array $allowedRoles): bool
    {
        if (!$request) {
            return false;
        }

        $user = method_exists($request, 'getUser') ? $request->getUser() : null;
        if (!$user) {
            return false;
        }

        // Quản trị viên hệ thống (Site Admin) luôn có toàn quyền
        if (class_exists('Validation', false) && method_exists('Validation', 'isSiteAdmin') && \Validation::isSiteAdmin()) {
            return true;
        }

        $userId = (int) $user->getId();
        $context = method_exists($request, 'getContext') ? $request->getContext() : null;
        $contextId = $context && method_exists($context, 'getId') ? (int) $context->getId() : CONTEXT_ID_NONE;

        if (class_exists('DAORegistry', false)) {
            $roleDao = \DAORegistry::getDAO('RoleDAO');
            if ($roleDao && method_exists($roleDao, 'userHasRole')) {
                $siteAdminRole = defined('ROLE_ID_SITE_ADMIN') ? ROLE_ID_SITE_ADMIN : 1;
                if ($roleDao->userHasRole(CONTEXT_ID_NONE, $userId, $siteAdminRole)) {
                    return true;
                }
                if ($contextId && $roleDao->userHasRole($contextId, $userId, $allowedRoles)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Xử lý các verb quản lý plugin.
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        $verb = (string) $request->getUserVar('verb');

        $adminRoles = [
            defined('ROLE_ID_SITE_ADMIN') ? ROLE_ID_SITE_ADMIN : 1,
            defined('ROLE_ID_MANAGER') ? ROLE_ID_MANAGER : 16,
        ];
        $editorialRoles = [
            defined('ROLE_ID_SITE_ADMIN') ? ROLE_ID_SITE_ADMIN : 1,
            defined('ROLE_ID_MANAGER') ? ROLE_ID_MANAGER : 16,
            defined('ROLE_ID_SUB_EDITOR') ? ROLE_ID_SUB_EDITOR : 17,
        ];

        $adminVerbs = [
            'settings',
            'apiTab',
            'saveApiSettings',
            'settingsTab',
            'saveSettings',
            'templatesTab',
            'saveTemplates',
            'activityLog',
            'dailySummaryStats',
            'exportLog',
            'outboxDashboardTab',
            'retryOutboxItem',
            'retryAllOutbox',
            'deleteOutboxItem',
            'clearSentOutbox',
        ];

        $editorialVerbs = [
            'reviewDashboardTab',
            'submissionsDashboardTab',
            'sendReviewReminder',
            'downloadReport',
        ];

        if (in_array($verb, $adminVerbs, true)) {
            if (!self::isUserAuthorized($request, $adminRoles)) {
                return new JSONMessage(false, __('common.permissionDenied'));
            }
        } elseif (in_array($verb, $editorialVerbs, true)) {
            if (!self::isUserAuthorized($request, $editorialRoles)) {
                return new JSONMessage(false, __('common.permissionDenied'));
            }
        }

        switch ($verb) {

            // =================================================================
            // SETTINGS TABS
            // =================================================================
            case 'settings':
                return ZaloSettingsController::handleSettingsModal($this, $request);

            // =================================================================
            // API SETTINGS TAB — Cấu hình API Zalo
            // =================================================================
            case 'apiTab':
                return ZaloSettingsController::handleApiTab($this, $request);

            case 'saveApiSettings':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                return ZaloSettingsController::handleSaveApiSettings($this, $request);

            // =================================================================
            // SETTINGS TAB — Cấu hình API và nhóm nhận thông báo
            // =================================================================
            case 'settingsTab':
                return ZaloSettingsController::handleSettingsTab($this, $request);

            case 'saveSettings':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                return ZaloSettingsController::handleSaveSettings($this, $request);

            // =================================================================
            // MESSAGE TEMPLATES TAB
            // =================================================================
            case 'templatesTab':
                return ZaloSettingsController::handleTemplatesTab($this, $request);

            // =================================================================
            // REVIEW REMINDER DASHBOARD
            // =================================================================
            case 'reviewDashboardTab':
                return ZaloReviewReminderController::handleDashboardTab($this, $request);

            case 'submissionsDashboardTab':
                return ZaloReviewReminderController::handleSubmissionsDashboardTab($this, $request);

            case 'sendReviewReminder':
                if (strtoupper((string) $request->getRequestMethod()) !== 'POST' || !$request->checkCSRF()) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                return ZaloReviewReminderController::sendReviewReminder($this, $request);

            case 'downloadReport':
                ZaloReviewReminderController::handleDownloadReport($this, $request);
                exit;

            case 'saveTemplates':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                return ZaloSettingsController::handleSaveTemplates($this, $request);

            // =================================================================
            // ACTIVITY LOG
            // =================================================================
            case 'activityLog':
                return ZaloActivityLogController::handleActivityLog($this, $request);

            case 'dailySummaryStats':
                return ZaloActivityLogController::handleDailySummaryStats($this, $request);

            case 'exportLog':
                ZaloActivityLogController::handleExportLog($this, $request);
                return new JSONMessage(true);

            // =================================================================
            // OUTBOX & RETRY DASHBOARD
            // =================================================================
            case 'outboxDashboardTab':
                return ZaloOutboxController::handleDashboardTab($this, $request);

            case 'retryOutboxItem':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                return ZaloOutboxController::retryItem($this, $request);

            case 'retryAllOutbox':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                return ZaloOutboxController::retryAll($this, $request);

            case 'deleteOutboxItem':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                return ZaloOutboxController::deleteItem($this, $request);

            case 'clearSentOutbox':
                if (!$this->isValidWriteRequest($request)) {
                    return new JSONMessage(false, __('form.csrfInvalid'));
                }
                return ZaloOutboxController::clearSent($this, $request);
        }

        return parent::manage($args, $request);
    }

    public static function getValidActivityLogTypes(): array
    {
        return ZaloActivityLogController::getValidTypes();
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
        return ZaloTemplateDefaults::ensureNavigationDetails(array_replace_recursive($defaultTemplates, $templates));
    }

    public function getDefaultTemplates(): array
    {
        return ZaloTemplateDefaults::get();
    }

    /**
     * Ghi log debug nhanh vào debug.txt
     */
    public function writeDebug(string $message): void
    {
        self::writeSecureDebug($message, 'Plugin');
    }
}
