<?php

function failActivityContextTest(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

class ZaloNotificationPlugin
{
    public static function getDataDir(): string
    {
        $directory = sys_get_temp_dir() . '/zalo_activity_context_test_' . getmypid();
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        return $directory;
    }

    public static function writeSecureDebug(string $message, string $component = ''): void
    {
    }
}

require_once dirname(__DIR__) . '/ActivityLogger.inc.php';

$contextA = 91001;
$contextB = 91002;
$action = 'CONTEXT_ISOLATION_TEST';

ActivityLogger::log(
    ActivityLogger::TYPE_ZALO_SEND,
    ActivityLogger::LEVEL_INFO,
    'Test A',
    0,
    'Bài của tạp chí A',
    $action,
    ['marker' => 'journal-a'],
    $contextA
);
ActivityLogger::log(
    ActivityLogger::TYPE_ZALO_SEND,
    ActivityLogger::LEVEL_INFO,
    'Test A',
    0,
    'Chỉ có ở tạp chí A',
    'ONLY_IN_A',
    [],
    $contextA
);
ActivityLogger::log(
    ActivityLogger::TYPE_ZALO_SEND,
    ActivityLogger::LEVEL_INFO,
    'Test B',
    0,
    'Bài của tạp chí B',
    $action,
    ['marker' => 'journal-b'],
    $contextB
);

$pathA = ActivityLogger::getLogFileLocation($contextA);
$pathB = ActivityLogger::getLogFileLocation($contextB);
if (!rename($pathA, $pathA . '.1')) {
    failActivityContextTest('Không thể tạo file log xoay để kiểm thử.');
}
ActivityLogger::log(
    ActivityLogger::TYPE_ZALO_SEND,
    ActivityLogger::LEVEL_INFO,
    'Test A',
    0,
    'Log hiện tại của tạp chí A',
    'CURRENT_IN_A',
    [],
    $contextA
);
$entriesA = ActivityLogger::getRecentEntries(10, null, $contextA);
$entriesB = ActivityLogger::getRecentEntries(10, null, $contextB);

if ($pathA === $pathB || basename($pathA) !== "zalo_notification_activity_context_{$contextA}.log") {
    failActivityContextTest('Hai context không được ánh xạ sang file log riêng.');
}
if (count($entriesA) !== 3 || ($entriesA[0]['action'] ?? '') !== 'CURRENT_IN_A'
    || ($entriesA[1]['action'] ?? '') !== 'ONLY_IN_A'
    || ($entriesA[2]['extra']['marker'] ?? '') !== 'journal-a') {
    failActivityContextTest('Context A đọc được dữ liệu sai hoặc dữ liệu của context khác.');
}
if (count($entriesB) !== 1 || ($entriesB[0]['context_id'] ?? null) !== $contextB
    || ($entriesB[0]['extra']['marker'] ?? '') !== 'journal-b') {
    failActivityContextTest('Context B đọc được dữ liệu sai hoặc dữ liệu của context khác.');
}
if (!ActivityLogger::isAlreadyLogged($action, 300, $contextA)
    || !ActivityLogger::isAlreadyLogged($action, 300, $contextB)
    || ActivityLogger::isAlreadyLogged('ONLY_IN_A', 300, $contextB)) {
    failActivityContextTest('Kiểm tra chống trùng không được cô lập theo context.');
}

$directory = dirname($pathA);
foreach ([$pathA, $pathB] as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
    $marker = $directory . '/.' . basename($file) . '.retention_check';
    if (file_exists($marker)) {
        unlink($marker);
    }
    if (file_exists($file . '.1')) {
        unlink($file . '.1');
    }
}
if (is_dir($directory)) {
    rmdir($directory);
}

echo "Activity log context isolation test passed.\n";
