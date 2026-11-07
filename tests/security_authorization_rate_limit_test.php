<?php

/**
 * Kiểm thử bảo mật: Phân quyền (RBAC), Giới hạn tần suất (Rate Limiting) và Che giấu PII.
 */

function failSecurityTest(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

if (!function_exists('import')) {
    function import(string $class): void {}
}

// 1. Kiểm tra mã nguồn ZaloNotificationPlugin.inc.php về RBAC
$pluginSource = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');
if (strpos($pluginSource, 'public static function isUserAuthorized($request, array $allowedRoles): bool') === false) {
    failSecurityTest('ZaloNotificationPlugin thiếu hàm kiểm tra phân quyền tường minh isUserAuthorized().');
}

if (strpos($pluginSource, 'self::isUserAuthorized($request, $adminRoles)') === false
    || strpos($pluginSource, 'self::isUserAuthorized($request, $editorialRoles)') === false) {
    failSecurityTest('manage() chưa kiểm tra phân quyền tường minh theo vai trò (RBAC) trước khi thực thi verb.');
}

// 2. Kiểm tra Export Log & Download Report có kiểm tra quyền tường minh
$activityControllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloActivityLogController.inc.php');
if (strpos($activityControllerSource, 'ZaloNotificationPlugin::isUserAuthorized($request, $adminRoles)') === false) {
    failSecurityTest('handleExportLog thiếu kiểm tra quyền tường minh.');
}

$reviewControllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloReviewReminderController.inc.php');
if (strpos($reviewControllerSource, 'ZaloNotificationPlugin::isUserAuthorized($request, $editorialRoles)') === false) {
    failSecurityTest('handleDownloadReport thiếu kiểm tra quyền tường minh.');
}

// 3. Kiểm tra Giới hạn tần suất (Rate Limiter)
require_once dirname(__DIR__) . '/services/security/RateLimiter.inc.php';

$testAction = 'unit_test_action_' . uniqid();
RateLimiter::reset($testAction);

$wait1 = 0;
if (!RateLimiter::checkCooldown($testAction, 5, $wait1)) {
    failSecurityTest('Lần gọi đầu tiên của RateLimiter phải thành công.');
}

$wait2 = 0;
if (RateLimiter::checkCooldown($testAction, 5, $wait2)) {
    failSecurityTest('Lần gọi thứ hai ngay lập tức của RateLimiter phải bị chặn.');
}
if ($wait2 <= 0 || $wait2 > 5) {
    failSecurityTest("Số giây chờ không chính xác: {$wait2}");
}

RateLimiter::reset($testAction);

// 4. Kiểm tra Rate Limiting trong Controller
$outboxControllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloOutboxController.inc.php');
if (strpos($outboxControllerSource, 'RateLimiter::checkCooldown') === false) {
    failSecurityTest('retryAll trong ZaloOutboxController thiếu RateLimiter.');
}

if (strpos($reviewControllerSource, 'RateLimiter::checkCooldown') === false) {
    failSecurityTest('sendReviewReminder trong ZaloReviewReminderController thiếu RateLimiter.');
}

// 5. Kiểm tra Khử trùng PII (ActivityLogger & ZaloOutboxController)
require_once dirname(__DIR__) . '/ActivityLogger.inc.php';

$testRawText = 'Nhắc nhở GS. Nguyen Van A qua SĐT 0912345678 và email test.user@gmail.com hoàn thành bài';
$sanitized = ActivityLogger::sanitizePii($testRawText);
if (strpos($sanitized, '0912345678') !== false) {
    failSecurityTest('ActivityLogger::sanitizePii chưa che số điện thoại.');
}
if (strpos($sanitized, 'test.user@gmail.com') !== false) {
    failSecurityTest('ActivityLogger::sanitizePii chưa che địa chỉ email.');
}

require_once dirname(__DIR__) . '/controllers/ZaloOutboxController.inc.php';
$phoneMasked = ZaloOutboxController::maskPhone('84987654321');
if (strpos($phoneMasked, '****') === false || strpos($phoneMasked, '987654') !== false) {
    failSecurityTest('ZaloOutboxController::maskPhone che số không đúng.');
}

// 6. Kiểm tra giới hạn tần suất bắn tin ra Zalo Gateway (Egress Dispatch Throttling & Pacing)
$apiClientSource = file_get_contents(dirname(__DIR__) . '/ZaloApiClient.inc.php');
if (strpos($apiClientSource, 'RateLimiter::throttleEgress(') === false) {
    failSecurityTest('ZaloApiClient::sendToPhonesNow() thiếu throttleEgress để khống chế tốc độ gửi tin ra Zalo.');
}

require_once dirname(__DIR__) . '/ZaloApiClient.inc.php';
if (!ZaloApiClient::isRetryableStatus('Thất bại (Mã lỗi HTTP: 429 - Too Many Requests)')) {
    failSecurityTest('HTTP 429 (Too Many Requests / Rate limit) phải được xem là lỗi tạm thời cần retry.');
}

$taskSource = file_get_contents(dirname(__DIR__) . '/tasks/ZaloOutboxTask.inc.php');
if (strpos($taskSource, '429') === false || strpos($taskSource, 'usleep(') === false) {
    failSecurityTest('ZaloOutboxTask thiếu pacing giãn cách gửi tin hoặc cơ chế ngắt mạch khi gặp HTTP 429.');
}

echo "Security RBAC, Rate Limiting & PII Masking test passed.\n";
