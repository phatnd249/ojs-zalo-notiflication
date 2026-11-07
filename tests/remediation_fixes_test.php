<?php

/**
 * Kiểm tra các bản sửa lỗi kỹ thuật, bảo mật và nghiệp vụ (Remediation Test).
 */

function failRemediationTest(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$pluginSource = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');
$settingsControllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloSettingsController.inc.php');
$editorialSource = file_get_contents(dirname(__DIR__) . '/handlers/EditorialNotificationHandler.inc.php');
$pubSource = file_get_contents(dirname(__DIR__) . '/handlers/PublicationNotificationHandler.inc.php');
$outboxRepoSource = file_get_contents(dirname(__DIR__) . '/services/ZaloOutboxRepository.inc.php');
$migrationSource = file_get_contents(dirname(__DIR__) . '/migrations/ZaloNotificationMigration.inc.php');
$outboxControllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloOutboxController.inc.php');
$outboxTplSource = file_get_contents(dirname(__DIR__) . '/templates/outboxDashboard.tpl');
$apiFormSource = file_get_contents(dirname(__DIR__) . '/templates/apiSettingsForm.tpl');

// 1. Kiểm tra hook phân công BTV đã được đăng ký trong register()
if (strpos($pluginSource, "HookRegistry::register('addparticipantform::execute'") === false) {
    failRemediationTest('Hook addparticipantform::execute chưa được đăng ký trong register().');
}

// 2. Kiểm tra rò rỉ quyết định sang Reviewer đã được bảo vệ
if (strpos($editorialSource, "\$reviewerData['decisionDesc'] = 'Đã có thông tin cập nhật'") === false
    || strpos($editorialSource, 'DECISION_REVIEWER_') === false) {
    failRemediationTest('Nhánh quyết định cho reviewer chưa được khử trùng hoặc chưa khử rò rỉ decisionDesc.');
}

// 3. Kiểm tra ngữ cảnh tạp chí (contextId) được truyền từ submission
if (strpos($editorialSource, '$contextId = self::getSubmissionContextId($submission);') === false
    || strpos($editorialSource, 'self::getZaloSettings($contextId)') === false
    || strpos($pubSource, 'self::getZaloSettings($contextId)') === false) {
    failRemediationTest('EditorialHandler hoặc PublicationHandler chưa truyền contextId vào getZaloSettings.');
}

// 4. Kiểm tra markSent không xóa mất message_text và recipients_json
$markSentStart = strpos($outboxRepoSource, 'function markSent(');
$markSentEnd = strpos($outboxRepoSource, 'function markFailed(', $markSentStart);
$markSentBody = substr($outboxRepoSource, $markSentStart, $markSentEnd - $markSentStart);
if (strpos($markSentBody, "'message_text' => ''") !== false
    || strpos($markSentBody, "'recipients_json' => '[]'") !== false) {
    failRemediationTest('markSent vẫn xóa message_text hoặc recipients_json khi cập nhật thành công.');
}

// 5. Kiểm tra thu hồi API Key
if (strpos($settingsControllerSource, '$clearApiKey') === false
    || strpos($settingsControllerSource, "\$plugin->updateSetting(\$contextId, 'apiKey', '')") === false
    || strpos($apiFormSource, 'name="clearApiKey"') === false) {
    failRemediationTest('Cơ chế thu hồi/xóa API Key qua UI chưa được triển khai.');
}

// 6. Kiểm tra dedupe unpublish
if (strpos($pubSource, 'UNPUBLISHED_{$submissionId}_" . $publication->getId() . "_" . time()') !== false
    || strpos($pubSource, 'ActivityLogger::isAlreadyLogged($uniqueKey') === false) {
    failRemediationTest('PublicationNotificationHandler::handleUnpublish chưa được sửa dedupe.');
}

// 7. Kiểm tra migration có hàm down()
if (strpos($migrationSource, 'public function down()') === false
    || strpos($migrationSource, 'dropIfExists') === false) {
    failRemediationTest('ZaloNotificationMigration thiếu hàm down().');
}

// 8. Kiểm tra cảnh báo Cron trên Outbox Dashboard
if (strpos($outboxControllerSource, 'hasStalePending') === false
    || strpos($outboxTplSource, '$hasStalePending') === false) {
    failRemediationTest('Outbox Dashboard chưa có cảnh báo tiến trình ngầm (Cron / Acron).');
}

// 9. Kiểm tra validate SĐT lúc lưu
if (strpos($settingsControllerSource, 'ZaloApiClient::normalizePhoneNumber($phone)') === false) {
    failRemediationTest('saveSettings chưa chuẩn hóa/validate SĐT trước khi lưu.');
}

// 10. Controller phải gọi được các method công khai của plugin (tránh fatal do private).
if (strpos($pluginSource, 'public function writeDebug(') === false
    || strpos($pluginSource, 'public function getDefaultTemplates(') === false) {
    failRemediationTest('writeDebug/getDefaultTemplates phải là public để controller gọi được.');
}

// 11. Controller Outbox phải import Capsule trước khi dùng Capsule::table().
$outboxControllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloOutboxController.inc.php');
if (strpos($outboxControllerSource, 'use Illuminate\Database\Capsule\Manager as Capsule;') === false) {
    failRemediationTest('ZaloOutboxController thiếu import Capsule.');
}

// 12. Outbox dashboard phải nạp JS thao tác (retry/delete/filter/pagination).
$outboxJsPath = dirname(__DIR__) . '/js/outboxDashboard.js';
if (!is_file($outboxJsPath)
    || strpos($outboxTplSource, 'js/outboxDashboard.js') === false
    || strpos(file_get_contents($outboxJsPath), "sendOutboxAction('retryOutboxItem'") === false) {
    failRemediationTest('Outbox Dashboard thiếu JS thao tác hoặc chưa được nhúng vào template.');
}

// 13. Dedupe nhánh reviewer phải thực sự ghi key (nếu không isAlreadyLogged luôn false).
$reviewerLogPos = strpos($editorialSource, 'DECISION_REVIEWER_');
if ($reviewerLogPos === false
    || strpos(substr($editorialSource, $reviewerLogPos), '$reviewerUniqueKey') === false) {
    failRemediationTest('Nhánh reviewer chưa ghi log với $reviewerUniqueKey nên dedupe không hoạt động.');
}

echo "Remediation verification tests passed.\n";
