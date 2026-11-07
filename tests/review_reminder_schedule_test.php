<?php

function failScheduleTest(string $message): void
{
    fwrite(STDERR, "FAIL [ReviewReminderSchedule]: {$message}\n");
    exit(1);
}

// 1. Kiểm tra ZaloSettingsProvider có chứa các cài đặt lịch nhắc mặc định (mặc định trước hạn 4 ngày)
$providerSource = file_get_contents(dirname(__DIR__) . '/services/ZaloSettingsProvider.inc.php');
if (strpos($providerSource, "'reviewReminderBeforeDays'") === false
    || strpos($providerSource, "reviewReminderBeforeDays") === false
    || strpos($providerSource, "? 4 :") === false
    || strpos($providerSource, "'reviewReminderOnDueDate'") === false
    || strpos($providerSource, "'reviewReminderOverdueFrequency'") === false
    || strpos($providerSource, "'reviewReminderMaxOverdueCount'") === false
    || strpos($providerSource, "'reviewReminderSendReviewer'") === false
    || strpos($providerSource, "'reviewReminderSendEditor'") === false) {
    failScheduleTest('ZaloSettingsProvider chưa hỗ trợ các trường lịch nhắc phản biện hoặc thiếu mặc định 4 ngày.');
}

// 2. Kiểm tra ZaloSettingsController lưu đầy đủ các trường lịch nhắc trong saveApiSettings
$settingsControllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloSettingsController.inc.php');
if (strpos($settingsControllerSource, "\$plugin->updateSetting(\$contextId, 'reviewReminderBeforeDays'") === false
    || strpos($settingsControllerSource, "\$plugin->updateSetting(\$contextId, 'reviewReminderOnDueDate'") === false
    || strpos($settingsControllerSource, "\$plugin->updateSetting(\$contextId, 'reviewReminderOverdueFrequency'") === false
    || strpos($settingsControllerSource, "\$plugin->updateSetting(\$contextId, 'reviewReminderMaxOverdueCount'") === false
    || strpos($settingsControllerSource, "\$plugin->updateSetting(\$contextId, 'reviewReminderSendReviewer'") === false
    || strpos($settingsControllerSource, "\$plugin->updateSetting(\$contextId, 'reviewReminderSendEditor'") === false) {
    failScheduleTest('ZaloSettingsController chưa lưu các cài đặt lịch nhắc phản biện.');
}

// 3. Kiểm tra ZaloSettingsController truyền cài đặt vào apiSettingsForm
$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloSettingsController.inc.php');
if (strpos($controllerSource, "'reviewReminderBeforeDays' => \$reviewReminderBeforeDays") === false
    || strpos($controllerSource, "'reviewReminderOnDueDate' => \$reviewReminderOnDueDate") === false
    || strpos($controllerSource, "'reviewReminderOverdueFrequency' => \$reviewReminderOverdueFrequency") === false
    || strpos($controllerSource, "'reviewReminderMaxOverdueCount' => \$reviewReminderMaxOverdueCount") === false) {
    failScheduleTest('ZaloSettingsController chưa truyền cài đặt lịch nhắc vào template.');
}

// 4. Kiểm tra form giao diện apiSettingsForm.tpl
$apiTplSource = file_get_contents(dirname(__DIR__) . '/templates/apiSettingsForm.tpl');
if (strpos($apiTplSource, 'name="reviewReminderBeforeDays"') === false
    || strpos($apiTplSource, 'name="reviewReminderOnDueDate"') === false
    || strpos($apiTplSource, 'name="reviewReminderOverdueFrequency"') === false
    || strpos($apiTplSource, 'name="reviewReminderMaxOverdueCount"') === false
    || strpos($apiTplSource, 'name="reviewReminderSendReviewer"') === false
    || strpos($apiTplSource, 'name="reviewReminderSendEditor"') === false) {
    failScheduleTest('Giao diện apiSettingsForm.tpl thiếu các thành phần cấu hình lịch nhắc phản biện.');
}

// 5. Kiểm tra ReviewNotificationHandler: Quét trước hạn theo ngày cấu hình (mặc định 4 ngày), quét đúng ngày hẹn, tần suất & giới hạn quá hạn
$handlerSource = file_get_contents(dirname(__DIR__) . '/services/review/ReviewReminderService.inc.php');
if (strpos($handlerSource, '$beforeDays = (int) ($settings[\'reviewReminderBeforeDays\'] ?? 4);') === false
    || strpos($handlerSource, '$daysDiff === $beforeDays') === false
    || strpos($handlerSource, 'REMINDER_UPCOMING_') === false
    || strpos($handlerSource, 'REMINDER_TODAY_') === false
    || strpos($handlerSource, 'processUpcomingRow') === false
    || strpos($handlerSource, 'review_reminder_state_') === false
    || strpos($handlerSource, '$currentCount >= $maxOverdue') === false) {
    failScheduleTest('ReviewNotificationHandler chưa thực thi đầy đủ logic quét trước hạn theo ngày cấu hình.');
}

// 6. Kiểm tra reviewReminderDashboard.tpl hiển thị banner lịch nhắc
$dashboardTplSource = file_get_contents(dirname(__DIR__) . '/templates/reviewReminderDashboard.tpl');
if (strpos($dashboardTplSource, 'zrd-schedule-banner') === false
    || strpos($dashboardTplSource, 'Lịch nhắc tự động:') === false) {
    failScheduleTest('reviewReminderDashboard.tpl thiếu thanh trạng thái tóm tắt lịch nhắc tự động.');
}

echo "Review reminder schedule tests passed.\n";
