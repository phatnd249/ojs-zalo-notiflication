<?php

function failSettingsSecurityTest(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$source = file_get_contents(dirname(__DIR__) . '/ZaloNotificationPlugin.inc.php');
$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/ZaloSettingsController.inc.php');

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
    $controllerMethod = 'handle' . ucfirst($verb);
    $guardPosition = strpos($caseSource, 'if (!$this->isValidWriteRequest($request))');
    $delegatePosition = strpos($caseSource, 'ZaloSettingsController::' . $controllerMethod . '(');
    if ($guardPosition === false || $delegatePosition === false || $guardPosition > $delegatePosition) {
        failSettingsSecurityTest("{$verb} không kiểm tra POST/CSRF trước khi ghi dữ liệu.");
    }

    $methodStart = strpos($controllerSource, 'function ' . $controllerMethod . '(');
    $methodEnd = $methodStart === false ? false : strpos($controllerSource, 'public static function ', $methodStart + 1);
    if ($methodStart === false) {
        failSettingsSecurityTest("Không tìm thấy {$controllerMethod} trong controller.");
    }
    $methodSource = $methodEnd === false
        ? substr($controllerSource, $methodStart)
        : substr($controllerSource, $methodStart, $methodEnd - $methodStart);
    if (strpos($methodSource, '$plugin->updateSetting(') === false) {
        failSettingsSecurityTest("{$controllerMethod} không ghi dữ liệu cài đặt.");
    }
}

echo "Settings POST/CSRF security test passed.\n";
