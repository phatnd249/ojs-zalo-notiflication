<?php

/**
 * Tests for Zalo Outbox & Retry Dashboard functionality.
 */

$pluginSource = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');
$outboxRepoSource = file_get_contents(dirname(__DIR__) . '/services/ZaloOutboxRepository.inc.php');
$outboxTemplate = file_get_contents(dirname(__DIR__) . '/templates/outboxDashboard.tpl');
$websiteTabsTemplate = file_get_contents(dirname(__DIR__) . '/templates/websiteSettingsTab.tpl');

// 1. Verify Website Settings tab registration
if (strpos($websiteTabsTemplate, 'id="zaloOutboxRetry"') === false
    || strpos($websiteTabsTemplate, 'label="Hàng đợi & Thử lại"') === false
    || strpos($websiteTabsTemplate, 'url=$zaloOutboxDashboardUrl') === false) {
    fwrite(STDERR, "Website settings tabs must include the Outbox & Retry dashboard tab.\n");
    exit(1);
}

// 2. Verify Plugin Controller verbs and CSRF protection
$requiredVerbs = [
    "case 'outboxDashboardTab':",
    "case 'retryOutboxItem':",
    "case 'retryAllOutbox':",
    "case 'deleteOutboxItem':",
    "case 'clearSentOutbox':",
];

foreach ($requiredVerbs as $verb) {
    if (strpos($pluginSource, $verb) === false) {
        fwrite(STDERR, "Plugin controller is missing outbox manage verb: {$verb}\n");
        exit(1);
    }
}

if (strpos($pluginSource, '$zaloOutboxDashboardUrl') === false) {
    fwrite(STDERR, "Plugin controller must generate and assign zaloOutboxDashboardUrl.\n");
    exit(1);
}

// 3. Verify ZaloOutboxRepository methods
$requiredRepoMethods = [
    'getOutboxEntries',
    'retryItem',
    'retryAllFailed',
    'deleteItem',
    'clearSent',
    'getStats',
];

foreach ($requiredRepoMethods as $method) {
    if (strpos($outboxRepoSource, "function {$method}") === false) {
        fwrite(STDERR, "ZaloOutboxRepository is missing required method: {$method}\n");
        exit(1);
    }
}

// 4. Verify outboxDashboard.tpl template elements
$requiredTemplateElements = [
    'id="zaloOutboxActionForm"',
    'id="zodSearch"',
    'id="zodStatusFilter"',
    'id="zodRetryAllBtn"',
    'id="zodClearSentBtn"',
    'id="zodRefreshBtn"',
    'id="zodPagination"',
    'zod-btn-retry',
    'zod-btn-delete',
];

foreach ($requiredTemplateElements as $element) {
    if (strpos($outboxTemplate, $element) === false) {
        fwrite(STDERR, "outboxDashboard.tpl is missing required UI element: {$element}\n");
        exit(1);
    }
}

echo "Outbox dashboard tests passed.\n";
