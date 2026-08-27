<?php

class ZaloNotificationPlugin
{
    public static function writeSecureDebug($message, $source = ''): void
    {
    }
}

class ZaloSettingsProvider
{
    public static function getCurrentContextId(): int
    {
        return 1;
    }
}

class ZaloApiClient
{
    public static $groupCalls = [];
    public static $directCalls = [];

    public static function normalizePhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        return strpos($clean, '0') === 0 ? '84' . substr($clean, 1) : $clean;
    }

    public static function getPhonesForEvent(string $eventType, array $groups): array
    {
        return ['84988726204'];
    }

    public static function sendForEvent($eventType, $message, $botId, $apiKey, $groups, $contextId = 0): string
    {
        self::$groupCalls[] = compact('eventType', 'message', 'contextId');
        return 'Thành công';
    }

    public static function sendToPhones($message, $botId, $apiKey, $phones, $eventType = 'DIRECT', $contextId = 0): string
    {
        self::$directCalls[] = compact('message', 'phones', 'eventType', 'contextId');
        return 'Đã xếp hàng retry';
    }
}

class NotificationRecipientResolver
{
    public static $editorPhones = ['0988726204', '0866856490'];

    public static function getEditorPhones(int $submissionId): array
    {
        return self::$editorPhones;
    }

    public static function excludeEventGroupPhones(string $eventType, array $phones, array $settings): array
    {
        $groupMap = ['84988726204' => true];
        return array_values(array_filter($phones, function ($phone) use ($groupMap) {
            $normalized = ZaloApiClient::normalizePhoneNumber((string) $phone);
            return $normalized !== '' && !isset($groupMap[$normalized]);
        }));
    }

    public static function getAuthorPhones($submission): array { return []; }
    public static function getReviewerPhones(int $submissionId): array { return []; }
    public static function resultToArray($result): array { return []; }
}

class FakeEditorAudienceSubmission
{
    public function getId(): int { return 32; }
    public function getContextId(): int { return 1; }
}

require_once dirname(__DIR__) . '/StageChangeHandler.inc.php';

class EditorAudienceTestHandler extends StageChangeHandler
{
    public static function send($submission, array $settings): string
    {
        return self::sendToEditorAudience(
            $submission,
            'REVIEW_COMPLETED',
            'Phản biện viên đã nộp đánh giá',
            $settings,
            'REVIEW_COMPLETED_ASSIGNED_EDITOR'
        );
    }
}

function assertEditorAudience($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$settings = [
    'botId' => 'bot-test',
    'apiKey' => 'key-test',
    'recipientGroups' => [['name' => 'Tổng biên tập', 'phones' => ['0988726204'], 'events' => ['REVIEW_COMPLETED']]],
];

$status = EditorAudienceTestHandler::send(new FakeEditorAudienceSubmission(), $settings);
assertEditorAudience(count(ZaloApiClient::$groupCalls) === 1, 'Không gửi tới nhóm cấu hình.');
assertEditorAudience(count(ZaloApiClient::$directCalls) === 1, 'Không gửi trực tiếp tới BTV được gán.');
assertEditorAudience(
    ZaloApiClient::$directCalls[0]['phones'] === ['0866856490'],
    'Không khử trùng đúng số BTV đã có trong nhóm cấu hình.'
);
assertEditorAudience(
    ZaloApiClient::$directCalls[0]['eventType'] === 'REVIEW_COMPLETED_ASSIGNED_EDITOR',
    'Sai loại sự kiện của lượt gửi tới BTV được gán.'
);
assertEditorAudience(strpos($status, 'Nhóm cấu hình: Thành công') !== false, 'Thiếu kết quả gửi nhóm trong trạng thái.');
assertEditorAudience(strpos($status, 'BTV được gán: Đã xếp hàng retry') !== false, 'Thiếu kết quả gửi BTV trong trạng thái.');

NotificationRecipientResolver::$editorPhones = ['0988726204'];
ZaloApiClient::$directCalls = [];
EditorAudienceTestHandler::send(new FakeEditorAudienceSubmission(), $settings);
assertEditorAudience(count(ZaloApiClient::$directCalls) === 0, 'BTV thuộc nhóm cấu hình vẫn bị gửi trùng.');

echo "Editor audience routing test passed.\n";
