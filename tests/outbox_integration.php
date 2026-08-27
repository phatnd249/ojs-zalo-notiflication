<?php

$ojsRoot = dirname(__DIR__, 4);
chdir($ojsRoot);
require $ojsRoot . '/tools/bootstrap.inc.php';
require_once dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php';

use Illuminate\Database\Capsule\Manager as Capsule;

function failTest(string $message): void
{
    throw new RuntimeException($message);
}

if (!ZaloOutboxRepository::ensureSchema()) {
    echo "SKIP: Outbox integration test requires an available OJS database.\n";
    exit(0);
}

try {
    $connection = Capsule::connection();
    $connection->beginTransaction();
    try {
        $message = 'OUTBOX_DUPLICATE_TEST_' . getmypid() . '_' . microtime(true);
        $before = Capsule::table('zalo_notification_outbox')->count();
        $first = ZaloOutboxRepository::enqueue(999999, 'REGRESSION_TEST', $message, ['84912345678']);
        $second = ZaloOutboxRepository::enqueue(999999, 'REGRESSION_TEST', $message, ['84912345678']);
        $after = Capsule::table('zalo_notification_outbox')->count();

        if ($after - $before !== 1) {
            failTest('Two identical enqueue calls created ' . ($after - $before) . ' rows.');
        }
        if (strpos($first, 'Đã xếp hàng') === false || strpos($second, 'trùng đã bỏ qua') === false) {
            failTest("Unexpected enqueue statuses: {$first} / {$second}");
        }
    } finally {
        $connection->rollBack();
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Outbox integration test passed.\n";
