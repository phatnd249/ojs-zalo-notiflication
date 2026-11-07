<?php

function failQueueFirstTest(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

require_once dirname(__DIR__) . '/ZaloApiClient.inc.php';

// 1. Kiểm tra method isQueueFirstEnabled mặc định trả về true
if (!method_exists('ZaloApiClient', 'isQueueFirstEnabled')) {
    failQueueFirstTest('ZaloApiClient thiếu method isQueueFirstEnabled().');
}

if (ZaloApiClient::isQueueFirstEnabled(0) !== true) {
    failQueueFirstTest('ZaloApiClient::isQueueFirstEnabled(0) phải trả về true mặc định.');
}

// 2. Kiểm tra mã nguồn ZaloApiClient.inc.php có nhánh xử lý Queue-First
$apiClientSource = file_get_contents(dirname(__DIR__) . '/ZaloApiClient.inc.php');
if (strpos($apiClientSource, 'isQueueFirstEnabled') === false) {
    failQueueFirstTest('ZaloApiClient::sendToPhones() chưa kiểm tra isQueueFirstEnabled().');
}
if (strpos($apiClientSource, 'enqueueDirect') === false) {
    failQueueFirstTest('ZaloApiClient thiếu phương thức enqueueDirect().');
}

// 3. Kiểm tra controller có lưu cấu hình queueFirstMode
$pluginSource = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');
$settingsControllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloSettingsController.inc.php');
if (strpos($settingsControllerSource, "\$plugin->updateSetting(\$contextId, 'queueFirstMode', \$queueFirstMode)") === false
    || strpos($pluginSource, 'ZaloSettingsController::handleSaveApiSettings(') === false) {
    failQueueFirstTest('ZaloNotificationPlugin chưa lưu cài đặt queueFirstMode trong saveApiSettings.');
}

// 4. Kiểm tra apiSettingsForm.tpl có checkbox queueFirstMode
$tplSource = file_get_contents(dirname(__DIR__) . '/templates/apiSettingsForm.tpl');
if (strpos($tplSource, 'name="queueFirstMode"') === false
    || strpos($tplSource, 'id="zaloDispatchModeSection"') === false) {
    failQueueFirstTest('Template apiSettingsForm.tpl chưa có trường cấu hình queueFirstMode.');
}

echo "Queue-First dispatch architecture test passed.\n";
