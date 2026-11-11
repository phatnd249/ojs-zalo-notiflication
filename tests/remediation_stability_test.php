<?php

/**
 * Kiểm thử độ ổn định và các bản vá tối ưu hóa hệ thống (Remediation & Stability Tests).
 */

function failStabilityTest(string $message): void
{
    fwrite(STDERR, "FAIL [RemediationStabilityTest]: {$message}\n");
    exit(1);
}

// 1. Kiểm tra handleExportLog không dùng PHP_INT_MAX và dùng stream php://output
$fullSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloActivityLogController.inc.php');
$handleExportPos = strpos($fullSource, 'function handleExportLog');
$exportSource = substr($fullSource, $handleExportPos);

if (strpos($exportSource, 'PHP_INT_MAX') !== false) {
    failStabilityTest('handleExportLog vẫn còn nạp toàn bộ log bằng PHP_INT_MAX.');
}
if (strpos($exportSource, 'php://output') === false) {
    failStabilityTest('handleExportLog chưa chuyển sang stream qua php://output.');
}
if (strpos($exportSource, 'ob_end_clean') === false) {
    failStabilityTest('handleExportLog chưa tắt bộ đệm đầu ra để giải phóng RAM.');
}

// 2. Kiểm tra loại bỏ hàm DATEDIFF MySQL-specific trong ZaloReviewReminderController
$reminderSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloReviewReminderController.inc.php');
if (strpos($reminderSource, 'DATEDIFF') !== false) {
    failStabilityTest('ZaloReviewReminderController vẫn còn chứa hàm DATEDIFF của MySQL.');
}
if (strpos($reminderSource, 'strtotime') === false || strpos($reminderSource, '86400') === false) {
    failStabilityTest('Chưa tính toán thời gian phản biện trung bình bằng PHP thuần đa CSDL.');
}

// 3. Kiểm tra khóa tệp chống Race Condition khi xoay log
$debugLoggerSource = file_get_contents(dirname(__DIR__) . '/services/debug/ZaloDebugLogger.inc.php');
if (strpos($debugLoggerSource, 'rotate.lock') === false || strpos($debugLoggerSource, 'LOCK_EX') === false) {
    failStabilityTest('ZaloDebugLogger chưa có cơ chế khóa rotate.lock.');
}

$storageSource = file_get_contents(dirname(__DIR__) . '/services/activity/ActivityLogStorage.inc.php');
if (strpos($storageSource, 'rotate.lock') === false || strpos($storageSource, 'LOCK_EX') === false) {
    failStabilityTest('ActivityLogStorage chưa có cơ chế khóa rotate.lock.');
}

// 4. Kiểm tra ZaloOutboxTask tối ưu Pacing và Deadline
$outboxTaskSource = file_get_contents(dirname(__DIR__) . '/tasks/ZaloOutboxTask.inc.php');
if (strpos($outboxTaskSource, 'claimBatch(20)') === false) {
    failStabilityTest('ZaloOutboxTask chưa nâng batch lên 20 tin.');
}
if (strpos($outboxTaskSource, 'microtime(true) + 45') === false) {
    failStabilityTest('ZaloOutboxTask chưa nâng deadline lên 45 giây.');
}
if (strpos($outboxTaskSource, 'usleep(mt_rand(300000, 500000))') === false) {
    failStabilityTest('ZaloOutboxTask chưa tối ưu pacing nghỉ 0.3s - 0.5s.');
}

// 5. Kiểm tra ActivityLogReader::getDailySummary lọc file theo mtime
$readerSource = file_get_contents(dirname(__DIR__) . '/services/activity/ActivityLogReader.inc.php');
if (strpos($readerSource, 'filemtime($file)') === false) {
    failStabilityTest('ActivityLogReader::getDailySummary chưa lọc file xoay theo filemtime.');
}

// 6. Kiểm tra đóng gói getStalePendingCount trong ZaloOutboxRepository
$repoSource = file_get_contents(dirname(__DIR__) . '/services/ZaloOutboxRepository.inc.php');
if (strpos($repoSource, 'function getStalePendingCount') === false) {
    failStabilityTest('ZaloOutboxRepository thiếu hàm getStalePendingCount.');
}

$outboxCtrlSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloOutboxController.inc.php');
if (strpos($outboxCtrlSource, 'ZaloOutboxRepository::getStalePendingCount') === false) {
    failStabilityTest('ZaloOutboxController chưa gọi ZaloOutboxRepository::getStalePendingCount.');
}

// 7. Kiểm tra PostSaveNotificationDispatcher log lỗi khi flush
$dispatcherSource = file_get_contents(dirname(__DIR__) . '/services/PostSaveNotificationDispatcher.inc.php');
if (strpos($dispatcherSource, "ActivityLogger::logError('DispatcherFlush'") === false) {
    failStabilityTest('PostSaveNotificationDispatcher chưa ghi log lỗi vào ActivityLogger.');
}

// 8. Kiểm tra SubmissionNotificationHandler mặt nạ hóa số điện thoại tác giả
$handlerSource = file_get_contents(dirname(__DIR__) . '/handlers/SubmissionNotificationHandler.inc.php');
if (strpos($handlerSource, 'ZaloNotificationPlugin::maskPhone') === false) {
    failStabilityTest('SubmissionNotificationHandler chưa ẩn số điện thoại tác giả.');
}

// 9. Kiểm tra ZaloNotificationPlugin không chứa mã chết sau downloadReport
$pluginSource = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');
if (preg_match("/case 'downloadReport':\s*ZaloReviewReminderController::handleDownloadReport\(\\\$this, \\\$request\);\s*return/", $pluginSource)) {
    failStabilityTest('Vẫn còn mã chết return sau handleDownloadReport.');
}

echo "Remediation & Stability tests passed.\n";
