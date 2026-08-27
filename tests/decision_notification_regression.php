<?php

// Các hằng số tối thiểu của OJS dùng trong handler quyết định.
define('SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS', 1);
define('SUBMISSION_EDITOR_DECISION_RESUBMIT', 2);
define('SUBMISSION_EDITOR_DECISION_ACCEPT', 3);
define('SUBMISSION_EDITOR_DECISION_DECLINE', 4);
define('SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE', 5);
define('WORKFLOW_STAGE_ID_SUBMISSION', 1);
define('WORKFLOW_STAGE_ID_INTERNAL_REVIEW', 2);
define('WORKFLOW_STAGE_ID_EXTERNAL_REVIEW', 3);
define('WORKFLOW_STAGE_ID_EDITING', 4);
define('ROLE_ID_MANAGER', 16);
define('ROLE_ID_SUB_EDITOR', 17);

function assertTrue($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

class ZaloNotificationPlugin
{
    public static function writeSecureDebug(string $message, string $component = ''): void
    {
    }
}

class ActivityLogger
{
    const TYPE_DECISION = 'DECISION';
    const TYPE_ZALO_SEND = 'ZALO_SEND';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';

    public static $actions = [];
    public static $errors = [];

    public static function isAlreadyLogged(string $action, int $window = 300): bool
    {
        return isset(self::$actions[$action]);
    }

    public static function logDecision($submissionId, $title, $author, $stageId, $stageName, $decisionType, $decisionDesc, $editorName, $status): void
    {
        self::$actions["DECISION_{$submissionId}_{$stageId}_{$decisionType}"] = true;
    }

    public static function log($type, $level, $actor, $submissionId, $title, $action, array $extra = []): void
    {
        self::$actions[$action] = $extra;
    }

    public static function logError(string $source, string $message, int $submissionId = 0, ?Throwable $exception = null): void
    {
        self::$errors[] = compact('source', 'message', 'submissionId');
    }
}

class ZaloApiClient
{
    public static $directMessages = [];
    public static $eventMessages = [];
    public static $throwOnDirect = false;

    public static function normalizePhoneNumber(string $phone): string
    {
        return $phone;
    }

    public static function sendForEvent($eventType, $message, $botId, $apiKey, $groups, $contextId = 0): string
    {
        self::$eventMessages[] = compact('eventType', 'message');
        return 'Đã xếp hàng';
    }

    public static function sendToPhones($message, $botId, $apiKey, $phones, $eventType = 'DIRECT', $contextId = 0): string
    {
        if (self::$throwOnDirect) {
            throw new RuntimeException('Gateway unavailable');
        }
        self::$directMessages[] = compact('message', 'phones', 'eventType');
        return 'Đã xếp hàng';
    }
}

class MessageHelper
{
    public static function getAuthorsString($publication): string
    {
        return 'Tác giả kiểm thử';
    }

    public static function getNavigationData($submission, int $reviewId = 0, ?int $stageId = null): array
    {
        return [
            'submissionId' => (string) $submission->getId(),
            'stageId' => (string) ($stageId ?: $submission->getStageId()),
            'reviewId' => '',
            'publicationId' => '9001',
            'workflowUrl' => 'https://journal.test/journal-a/workflow/index/' . $submission->getId(),
            'authorUrl' => 'https://journal.test/journal-a/authorDashboard/submission/' . $submission->getId(),
            'reviewerUrl' => '',
            'publicUrl' => '',
        ];
    }

    public static function buildMessage(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }
        return trim($template);
    }

    public static function getStageName(int $stageId): string
    {
        return $stageId === 3 ? 'Phản biện' : 'Biên tập';
    }

    public static function getDecisionName(int $decisionType): string
    {
        return 'Yêu cầu chỉnh sửa';
    }

    public static function getCurrentUserName(): string
    {
        return 'Biên tập viên';
    }
}

class NotificationRecipientResolver
{
    public static function getAuthorPhones($submission): array
    {
        return ['84912345678'];
    }

    public static function getEditorPhones(int $submissionId): array
    {
        return [];
    }

    public static function getReviewerPhones(int $submissionId): array
    {
        return [];
    }

    public static function excludeEventGroupPhones(string $eventType, array $phones, array $settings): array
    {
        return $phones;
    }

    public static function resultToArray($result): array
    {
        return [];
    }
}

class DAORegistry
{
    public static function getDAO($name)
    {
        return new class {
            public function getById($id)
            {
                return null;
            }
        };
    }
}

class FakePublication
{
    public function getLocalizedTitle(): string
    {
        return 'Bài kiểm thử';
    }

    public function getLocalizedData($key): string
    {
        return $key === 'abstract' ? 'Tóm tắt công khai' : '';
    }
}

class FakeSubmission
{
    private $id;
    private $stageId;

    public function __construct(int $id, int $stageId)
    {
        $this->id = $id;
        $this->stageId = $stageId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStageId(): int
    {
        return $this->stageId;
    }

    public function getContextId(): int
    {
        return 1;
    }

    public function getCurrentPublication(): FakePublication
    {
        return new FakePublication();
    }
}

class FakeMail
{
    public $emailKey;
    public $submission;
    public $params;

    public function __construct(string $emailKey, FakeSubmission $submission, array $params = [])
    {
        $this->emailKey = $emailKey;
        $this->submission = $submission;
        $this->params = $params;
    }
}

class FakePlugin
{
    public function getZaloSettings(): array
    {
        return ['botId' => 'bot-test', 'apiKey' => 'key-test', 'recipientGroups' => []];
    }

    public function getMessageTemplates(): array
    {
        return [
            'editor' => ['decision' => ''],
            'reviewer' => ['decision' => ''],
            'author' => [
                'decision' => "BÀI BÁO ĐÃ CÓ KẾT QUẢ PHẢN BIỆN\n› Bài: {title}\n→ Xem tại: {authorUrl}",
                'initial_decline' => "QUYẾT ĐỊNH BIÊN TẬP BAN ĐẦU\n› Bài: {title}\n› Quyết định: {decisionDesc}\n→ Xem tại: {authorUrl}",
            ],
        ];
    }
}

require_once dirname(__DIR__) . '/StageChangeHandler.inc.php';
require_once dirname(__DIR__) . '/handlers/EditorialNotificationHandler.inc.php';

$plugin = new FakePlugin();
StageChangeHandler::setPlugin($plugin);

// 1. Skip Email: quyết định được ghi nhận nhưng Mail::send không xảy ra.
$skipSubmission = new FakeSubmission(71001, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
EditorialNotificationHandler::handleDecision33($skipSubmission, ['decision' => SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS]);
assertTrue(count(ZaloApiClient::$directMessages) === 0, 'Skip Email vẫn tạo thông báo Zalo cho tác giả.');

// 2. Tin tác giả không được lấy tên/nội dung phản biện từ email.
$privacySubmission = new FakeSubmission(71002, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
EditorialNotificationHandler::handleDecision33($privacySubmission, ['decision' => SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS]);
$mail = new FakeMail('EDITOR_DECISION_REVISIONS', $privacySubmission, [
    'reviewerName' => 'PHẢN BIỆN VIÊN BÍ MẬT',
    'comments' => 'NỘI DUNG PHẢN BIỆN TUYỆT MẬT',
]);
EditorialNotificationHandler::handleMailEvent($mail, $plugin);
$privacyMessage = ZaloApiClient::$directMessages[count(ZaloApiClient::$directMessages) - 1]['message'];
assertTrue(strpos($privacyMessage, 'PHẢN BIỆN VIÊN BÍ MẬT') === false, 'Tin tác giả làm lộ tên phản biện viên.');
assertTrue(strpos($privacyMessage, 'NỘI DUNG PHẢN BIỆN TUYỆT MẬT') === false, 'Tin tác giả làm lộ nội dung phản biện.');

// 3. Hai hook quyết định/email trùng nhau chỉ tạo một lần gửi cho tác giả.
$beforeDuplicate = count(ZaloApiClient::$directMessages);
$duplicateSubmission = new FakeSubmission(71003, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
$decision = ['decision' => SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS];
EditorialNotificationHandler::handleDecision33($duplicateSubmission, $decision);
EditorialNotificationHandler::handleDecision33($duplicateSubmission, $decision);
$duplicateMail = new FakeMail('EDITOR_DECISION_REVISIONS', $duplicateSubmission);
EditorialNotificationHandler::handleMailEvent($duplicateMail, $plugin);
EditorialNotificationHandler::handleMailEvent($duplicateMail, $plugin);
assertTrue(count(ZaloApiClient::$directMessages) - $beforeDuplicate === 1, 'Hai hook trùng tạo nhiều hơn một thông báo tác giả.');

// 4. Lỗi từ client Zalo không được thoát ra làm hỏng thao tác OJS.
$errorSubmission = new FakeSubmission(71004, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
EditorialNotificationHandler::handleDecision33($errorSubmission, $decision);
ZaloApiClient::$throwOnDirect = true;
$didThrow = false;
try {
    EditorialNotificationHandler::handleMailEvent(new FakeMail('EDITOR_DECISION_REVISIONS', $errorSubmission), $plugin);
} catch (Throwable $e) {
    $didThrow = true;
}
ZaloApiClient::$throwOnDirect = false;
assertTrue(!$didThrow, 'Lỗi Zalo truyền ra ngoài handler và có thể làm hỏng thao tác OJS.');
assertTrue(count(ActivityLogger::$errors) > 0, 'Lỗi Zalo không được ghi nhận.');

// 5. Từ chối ban đầu chỉ gửi Zalo cho tác giả khi email OJS thực sự được gửi.
$beforeInitialDecline = count(ZaloApiClient::$directMessages);
$initialDeclineSubmission = new FakeSubmission(71006, WORKFLOW_STAGE_ID_SUBMISSION);
EditorialNotificationHandler::handleDecision33($initialDeclineSubmission, [
    'decision' => SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE,
]);
assertTrue(
    count(ZaloApiClient::$directMessages) === $beforeInitialDecline,
    'Initial decline gửi Zalo trước khi OJS gửi email quyết định.'
);
EditorialNotificationHandler::handleMailEvent(
    new FakeMail('EDITOR_DECISION_INITIAL_DECLINE', $initialDeclineSubmission),
    $plugin
);
assertTrue(
    count(ZaloApiClient::$directMessages) === $beforeInitialDecline + 1,
    'Initial decline không gửi thông báo Zalo cho tác giả sau email OJS.'
);
$initialDeclineMessage = ZaloApiClient::$directMessages[count(ZaloApiClient::$directMessages) - 1]['message'];
assertTrue(
    strpos($initialDeclineMessage, 'QUYẾT ĐỊNH BIÊN TẬP BAN ĐẦU') !== false,
    'Initial decline dùng nhầm mẫu kết quả phản biện.'
);

// 6. Quyết định ngoài stage phản biện không được tạo tin “kết quả phản biện”.
$beforeOutsideReview = count(ZaloApiClient::$directMessages);
$editingSubmission = new FakeSubmission(71005, WORKFLOW_STAGE_ID_EDITING);
EditorialNotificationHandler::handleDecision33($editingSubmission, $decision);
EditorialNotificationHandler::handleMailEvent(new FakeMail('EDITOR_DECISION_REVISIONS', $editingSubmission), $plugin);
assertTrue(count(ZaloApiClient::$directMessages) === $beforeOutsideReview, 'Quyết định ngoài stage phản biện vẫn gửi kết quả phản biện.');

echo "Decision notification regression tests passed.\n";
