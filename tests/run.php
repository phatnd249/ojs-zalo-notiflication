<?php

$tests = [
    'architecture_smoke.php',
    'decision_notification_regression.php',
    'navigation_link_test.php',
    'activity_log_context_test.php',
    'settings_request_security_test.php',
    'reviewer_recipient_filter_test.php',
    'editor_audience_routing_test.php',
    'author_revision_notification_test.php',
    'reminder_deduplication_test.php',
    'gateway_response_test.php',
    'post_save_dispatcher_test.php',
    'outbox_dashboard_test.php',
    'outbox_integration.php',
    'activity_log_ui_test.php',
];

foreach ($tests as $test) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . $test);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Test suite failed at {$test}.\n");
        exit($exitCode);
    }
}

echo "All Zalo notification tests passed.\n";
