<?php

require_once dirname(__DIR__) . '/services/NotificationRecipientResolver.inc.php';
require_once dirname(__DIR__) . '/services/ZaloSettingsProvider.inc.php';
require_once dirname(__DIR__) . '/StageChangeHandler.inc.php';
require_once dirname(__DIR__) . '/handlers/SubmissionNotificationHandler.inc.php';
require_once dirname(__DIR__) . '/handlers/ReviewNotificationHandler.inc.php';
require_once dirname(__DIR__) . '/handlers/PublicationNotificationHandler.inc.php';
require_once dirname(__DIR__) . '/handlers/EditorialNotificationHandler.inc.php';
require_once dirname(__DIR__) . '/services/ReviewReminderDashboardService.inc.php';

$facadeMethods = [
    'setPlugin',
    'checkOverdueReviewDeadlines',
    'handleSubmission',
    'handleSubmissionSubmitStep4Form',
    'handleAuthorRevisionUploaded',
    'handleDecision33',
    'handlePublish',
    'handleUnpublish',
    'handleMailEvent',
    'handleReviewerAssigned',
    'handleEditorAssigned',
    'handleReviewDueDatesSet',
    'handleReviewerResponseFromHook',
    'handleReviewAssignmentStatusChanged',
    'handleReviewerReviewCompleted',
];

foreach ($facadeMethods as $method) {
    if (!is_callable(['StageChangeHandler', $method])) {
        fwrite(STDERR, "Facade method is not callable: {$method}\n");
        exit(1);
    }
}

$handlerMethods = [
    ['SubmissionNotificationHandler', 'handleSubmission'],
    ['SubmissionNotificationHandler', 'handleAuthorEnteredReview'],
    ['SubmissionNotificationHandler', 'handleAuthorRevisionUploaded'],
    ['ReviewNotificationHandler', 'handleReviewDueDatesSet'],
    ['ReviewNotificationHandler', 'sendManualReminder'],
    ['PublicationNotificationHandler', 'handlePublish'],
    ['EditorialNotificationHandler', 'handleDecision33'],
];

foreach ($handlerMethods as [$class, $method]) {
    if (!is_callable([$class, $method])) {
        fwrite(STDERR, "Handler method is not callable: {$class}::{$method}\n");
        exit(1);
    }
}

$dashboardSource = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');
$dashboardTemplate = file_get_contents(dirname(__DIR__) . '/templates/reviewReminderDashboard.tpl');
$activityLogTemplate = file_get_contents(dirname(__DIR__) . '/templates/activityLog.tpl');
$reviewHandlerSource = file_get_contents(dirname(__DIR__) . '/handlers/ReviewNotificationHandler.inc.php');
$submissionHandlerSource = file_get_contents(dirname(__DIR__) . '/handlers/SubmissionNotificationHandler.inc.php');
$overdueTaskSource = file_get_contents(dirname(__DIR__) . '/tasks/ZaloOverdueReviewTask.inc.php');
$postSaveDispatcherSource = file_get_contents(dirname(__DIR__) . '/services/PostSaveNotificationDispatcher.inc.php');
$activityLoggerSource = file_get_contents(dirname(__DIR__) . '/ActivityLogger.inc.php');
$messageHelperSource = file_get_contents(dirname(__DIR__) . '/MessageHelper.inc.php');
$websiteTabsTemplate = file_get_contents(dirname(__DIR__) . '/templates/websiteSettingsTab.tpl');
$notificationTemplatesSource = file_get_contents(dirname(__DIR__) . '/templates/notificationTemplatesForm.tpl');
if (strpos($dashboardSource, "case 'reviewDashboardTab':") === false
    || strpos($dashboardSource, "case 'sendReviewReminder':") === false
    || strpos($dashboardSource, 'checkCSRF()') === false
    || strpos($dashboardTemplate, 'data-scope="submission"') === false
    || strpos($dashboardTemplate, 'data-scope="reviewer"') === false
    || strpos($dashboardTemplate, 'id="zrdSearch"') === false
    || strpos($dashboardTemplate, 'id="zrdPagination"') === false) {
    fwrite(STDERR, "Review reminder dashboard or its protected send actions are missing.\n");
    exit(1);
}

if (strpos($activityLoggerSource, "'context_id' => \$contextId") === false
    || strpos($activityLoggerSource, "LOG_FILENAME_PREFIX . \$contextId . '.log'") === false
    || strpos($dashboardSource, 'getRecentEntries(PHP_INT_MAX, $filterType, $contextId)') === false
    || strpos($activityLogTemplate, 'id="zaloLogPagination"') === false
    || strpos($activityLogTemplate, 'var logPageSize = 20;') === false
    || strpos($dashboardSource, 'getLogFileLocation($contextId)') === false) {
    fwrite(STDERR, "Activity logs must be stored and read per journal context.\n");
    exit(1);
}

if (strpos($reviewHandlerSource, 'public static function sendManualReminder') === false
    || strpos($reviewHandlerSource, "'REVIEW_REMINDER_MANUAL', self::getSubmissionContextId(\$submission)") === false) {
    fwrite(STDERR, "Manual review reminders must send directly first and retain context for retry.\n");
    exit(1);
}

if (strpos($dashboardSource, 'StageChangeHandler::checkOverdueReviewDeadlines($this, $scanContextId)') !== false
    || strpos($dashboardSource, 'public function getMessageTemplatesForContext(int $contextId)') === false
    || strpos($reviewHandlerSource, 'JOIN submissions s ON s.submission_id = ra.submission_id') === false
    || strpos($reviewHandlerSource, 'WHERE s.context_id = ?') === false
    || strpos($reviewHandlerSource, 'deadline_scan_context_{$contextId}.lock') === false
    || strpos($reviewHandlerSource, 'flock($scanLockHandle, LOCK_EX | LOCK_NB)') === false
    || strpos($reviewHandlerSource, 'getZaloSettings($contextId)') === false
    || strpos($reviewHandlerSource, 'getMessageTemplatesForContext($contextId)') === false
    || strpos($overdueTaskSource, "getDAO('JournalDAO')->getAll(true)") === false
    || strpos($overdueTaskSource, '$this->plugin->getEnabled($contextId)') === false
    || strpos($overdueTaskSource, 'StageChangeHandler::checkOverdueReviewDeadlines($this->plugin, $contextId)') === false) {
    fwrite(STDERR, "Overdue review scanning must run from a scheduled task and remain isolated by journal context.\n");
    exit(1);
}

$activityTabPosition = strpos($websiteTabsTemplate, 'id="zaloActivityLog"');
$reviewTabPosition = strpos($websiteTabsTemplate, 'id="zaloReviewReminder"');
if ($activityTabPosition === false || $reviewTabPosition === false || $reviewTabPosition < $activityTabPosition) {
    fwrite(STDERR, "Review reminder must be a Website Settings tab next to Activity Log.\n");
    exit(1);
}

$apiClientSource = file_get_contents(dirname(__DIR__) . '/ZaloApiClient.inc.php');
$directMethodStart = strpos($apiClientSource, 'public static function sendToPhones(');
$gatewayMethodStart = strpos($apiClientSource, 'public static function sendToPhonesNow(');
$directDelegation = strpos($apiClientSource, '$directStatus = self::sendToPhonesNow(', $directMethodStart);
$retryEnqueue = strpos($apiClientSource, 'ZaloOutboxRepository::enqueue(', $directMethodStart);
$curlCall = strpos($apiClientSource, 'curl_exec(');
if ($directMethodStart === false || $gatewayMethodStart === false || $directDelegation === false
    || $retryEnqueue === false || $curlCall === false
    || !($directMethodStart < $directDelegation && $directDelegation < $retryEnqueue
        && $retryEnqueue < $gatewayMethodStart && $gatewayMethodStart < $curlCall)) {
    fwrite(STDERR, "Delivery boundary is invalid: send directly first, then enqueue failures for retry.\n");
    exit(1);
}

$scheduledTasks = file_get_contents(dirname(__DIR__) . '/scheduledTasks.xml');
if (strpos($scheduledTasks, 'plugins.generic.zaloNotification.tasks.ZaloOutboxTask') === false) {
    fwrite(STDERR, "ZaloOutboxTask is not registered in scheduledTasks.xml.\n");
    exit(1);
}
if (strpos($scheduledTasks, 'plugins.generic.zaloNotification.tasks.ZaloOverdueReviewTask') === false) {
    fwrite(STDERR, "ZaloOverdueReviewTask is not registered in scheduledTasks.xml.\n");
    exit(1);
}

if (strpos($dashboardSource, 'PostSaveNotificationDispatcher::deferDecision(') === false
    || strpos($dashboardSource, 'PostSaveNotificationDispatcher::deferSubmissionEdit(') === false
    || strpos($dashboardSource, 'PostSaveNotificationDispatcher::deferReviewDueDates(') === false
    || strpos($dashboardSource, 'PostSaveNotificationDispatcher::deferReviewerResponse(') === false
    || strpos($postSaveDispatcherSource, "getDAO('EditDecisionDAO')->getEditorDecisions") === false
    || strpos($postSaveDispatcherSource, "getDAO('ReviewAssignmentDAO')->getById") === false
    || strpos($postSaveDispatcherSource, 'register_shutdown_function') === false) {
    fwrite(STDERR, "Pre-save OJS hooks must be verified by the post-save dispatcher before sending.\n");
    exit(1);
}

if (strpos($postSaveDispatcherSource, 'StageChangeHandler::handleAuthorEnteredReview(') === false
    || strpos($dashboardSource, "'review_started'") === false
    || strpos($reviewHandlerSource, 'AUTHOR_REVIEW_STARTED') !== false
    || strpos(file_get_contents(dirname(__DIR__) . '/handlers/SubmissionNotificationHandler.inc.php'), 'AUTHOR_REVIEW_STARTED') === false
    || strpos(file_get_contents(dirname(__DIR__) . '/templates/notificationTemplatesForm.tpl'), 'data-event="review_started"') === false) {
    fwrite(STDERR, "Author review-start notifications must be emitted after the persisted stage transition.\n");
    exit(1);
}

$revisionHandlerStart = strpos($submissionHandlerSource, 'public static function handleAuthorRevisionUploaded(');
$revisionHandlerEnd = strpos($submissionHandlerSource, 'public static function handleAuthorEnteredReview(', $revisionHandlerStart);
$revisionHandler = $revisionHandlerStart !== false && $revisionHandlerEnd !== false
    ? substr($submissionHandlerSource, $revisionHandlerStart, $revisionHandlerEnd - $revisionHandlerStart)
    : '';
if (strpos($dashboardSource, "HookRegistry::register('SubmissionFile::add'") === false
    || strpos($dashboardSource, 'onSubmissionFileAddCallback') === false
    || strpos($dashboardSource, "'author_revision'") === false
    || strpos($notificationTemplatesSource, 'data-event="author_revision"') === false
    || strpos($revisionHandler, 'SUBMISSION_FILE_REVIEW_REVISION') === false
    || strpos($revisionHandler, 'SUBMISSION_FILE_INTERNAL_REVIEW_REVISION') === false
    || strpos($revisionHandler, 'getBySubmissionAndRoleId($submissionId, ROLE_ID_AUTHOR)') === false
    || strpos($revisionHandler, 'getEditorPhones($submissionId)') === false
    || strpos($revisionHandler, 'AUTHOR_REVISION_ASSIGNED_EDITOR') === false
    || strpos($revisionHandler, 'sendToPhones(') === false
    || strpos($revisionHandler, 'sendForEvent(') !== false
    || strpos($revisionHandler, 'sendToEditorAudience(') !== false) {
    fwrite(STDERR, "Author revision uploads must notify assigned editors only after OJS saves a valid review revision.\n");
    exit(1);
}

if (strpos($dashboardSource, "'decision' => \"▣ BÀI BÁO ĐÃ CÓ KẾT QUẢ PHẢN BIỆN\\n\\n› Bài: {title}\\n› Yêu cầu: {decisionDesc}") === false
    || strpos($dashboardSource, "\$event === 'decision' && strpos(\$template, '{decisionDesc}') === false") === false
    || strpos($dashboardSource, "\$details[] = '› Yêu cầu: {decisionDesc}'") === false
    || strpos($messageHelperSource, "SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS=> '✍️ Yêu cầu chỉnh sửa'") === false
    || strpos($messageHelperSource, 'Yêu cầu nộp lại để mở vòng phản biện mới') === false) {
    fwrite(STDERR, "Author decision templates must distinguish revisions from resubmission, including upgraded saved templates.\n");
    exit(1);
}

if (strpos($notificationTemplatesSource, "reviewer: ['decision', 'reminder', 'review_request']") === false
    || strpos($notificationTemplatesSource, "author: ['submission', 'decision', 'initial_decline', 'review_started', 'publish', 'unpublish']") === false
    || strpos($notificationTemplatesSource, 'function updateVisibleEvents()') === false
    || strpos($notificationTemplatesSource, "tab.style.display = visible ? '' : 'none'") === false) {
    fwrite(STDERR, "Template tabs must only show events supported by the selected recipient role.\n");
    exit(1);
}

if (strpos($notificationTemplatesSource, '<details class="zalo-variables-panel"') === false
    || strpos($notificationTemplatesSource, 'const eventVariables = {') === false
    || strpos($notificationTemplatesSource, 'vars = vars.filter(v => allowedVariables.includes(v.id))') === false
    || strpos($notificationTemplatesSource, 'variableCount.textContent = vars.length') === false
    || strpos($notificationTemplatesSource, "review_request: ['{title}', '{author}', '{reviewerName}', '{deadline}', '{responseDeadline}'") === false) {
    fwrite(STDERR, "Template editor must keep variables collapsed and filter them by the selected event.\n");
    exit(1);
}

echo "Architecture smoke test passed.\n";
