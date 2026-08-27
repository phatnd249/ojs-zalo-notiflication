<?php

function failSettingsSecurityTest(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$source = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');

if (strpos($source, "strtoupper((string) \$request->getRequestMethod()) === 'POST'") === false
    || strpos($source, '$request->checkCSRF()') === false) {
    failSettingsSecurityTest('Bộ kiểm tra POST/CSRF dùng chung bị thiếu.');
}

$writeCases = [
    'saveApiSettings' => 'settingsTab',
    'saveSettings' => 'templatesTab',
    'saveTemplates' => 'activityLog',
];

foreach ($writeCases as $verb => $nextVerb) {
    $start = strpos($source, "case '{$verb}':");
    $end = strpos($source, "case '{$nextVerb}':", $start + 1);
    if ($start === false || $end === false || $end <= $start) {
        failSettingsSecurityTest("Không tìm thấy phạm vi xử lý {$verb}.");
    }

    $caseSource = substr($source, $start, $end - $start);
    $guardPosition = strpos($caseSource, 'if (!$this->isValidWriteRequest($request))');
    $mutationPosition = strpos($caseSource, '$this->updateSetting(');
    if ($guardPosition === false || $mutationPosition === false || $guardPosition > $mutationPosition) {
        failSettingsSecurityTest("{$verb} không kiểm tra POST/CSRF trước khi ghi dữ liệu.");
    }
}

echo "Settings POST/CSRF security test passed.\n";
