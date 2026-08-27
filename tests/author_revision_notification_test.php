<?php

define('ROLE_ID_AUTHOR', 65536);
define('SUBMISSION_FILE_REVIEW_REVISION', 15);
define('SUBMISSION_FILE_INTERNAL_REVIEW_REVISION', 20);

class ZaloNotificationPlugin
{
    public static function writeSecureDebug($message, $source = ''): void {}
}

class ZaloSettingsProvider
{
    public static function getCurrentContextId(): int { return 1; }
}

class ActivityLogger
{
    public const TYPE_ZALO_SEND = 'ZALO_SEND';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public static $actions = [];
    public static $errors = [];

    public static function isAlreadyLogged(string $key, int $window = 0, ?int $contextId = null): bool
    {
        return isset(self::$actions[$key]);
    }

    public static function log($type, $level, $actor, $submissionId, $title, $action, array $extra = [], ?int $contextId = null): void
    {
        self::$actions[$action] = compact('type', 'level', 'actor', 'submissionId', 'title', 'extra', 'contextId');
    }

    public static function logError($source, $message, $submissionId = 0, $exception = null, $contextId = null): void
    {
        self::$errors[] = compact('source', 'message', 'submissionId');
    }
}

class ZaloApiClient
{
    public static $directCalls = [];

    public static function sendToPhones($message, $botId, $apiKey, $phones, $eventType = 'DIRECT', $contextId = 0): string
    {
        self::$directCalls[] = compact('message', 'phones', 'eventType', 'contextId');
        return 'Thành công';
    }
}

class NotificationRecipientResolver
{
    public static function getAuthorPhones($submission): array { return []; }
    public static function getEditorPhones(int $submissionId): array { return ['0866856490']; }
    public static function getReviewerPhones(int $submissionId): array { return []; }
    public static function excludeEventGroupPhones(string $eventType, array $phones, array $settings): array { return $phones; }
    public static function resultToArray($result): array { return is_array($result) ? $result : []; }
}

class MessageHelper
{
    public static function getAuthorsString($source): string { return 'Nguyễn Văn A'; }
    public static function getStageName(int $stageId): string { return 'Phản biện'; }
    public static function getNavigationData($submission, int $reviewId = 0, ?int $stageId = null): array
    {
        return ['submissionId' => $submission->getId(), 'stageId' => $stageId, 'workflowUrl' => 'https://journal.test/workflow/32'];
    }
    public static function buildMessage(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }
        return $template;
    }
}

class FakeRevisionPlugin
{
    public function getZaloSettingsForContext(int $contextId): array
    {
        return ['botId' => 'bot-test', 'apiKey' => 'key-test', 'recipientGroups' => [['phones' => ['0988726204']]]];
    }

    public function getMessageTemplatesForContext(int $contextId): array
    {
        return ['editor' => ['author_revision' => 'Tác giả {author} đã nộp bản sửa bài {title}, vòng {round}. {workflowUrl}']];
    }
}

class FakeAssignment
{
    private $userId;
    public function __construct(int $userId) { $this->userId = $userId; }
    public function getUserId(): int { return $this->userId; }
}

class FakePublication
{
    public function getLocalizedTitle(): string { return 'Bài kiểm thử'; }
}

class FakeSubmission
{
    public function getId(): int { return 32; }
    public function getContextId(): int { return 1; }
    public function getStageId(): int { return 3; }
    public function getCurrentPublication() { return new FakePublication(); }
}

class FakeReviewRound
{
    private $id;
    public function __construct(int $id) { $this->id = $id; }
    public function getSubmissionId(): int { return 32; }
    public function getStageId(): int { return 3; }
    public function getRound(): int { return $this->id === 502 ? 2 : 1; }
}

class FakeUser
{
    public function getFullName(): string { return 'Nguyễn Văn A'; }
}

class FakeSubmissionFile
{
    private $stage;
    private $uploaderId;
    private $roundId;
    public function __construct(int $stage, int $uploaderId, int $roundId = 501)
    {
        $this->stage = $stage;
        $this->uploaderId = $uploaderId;
        $this->roundId = $roundId;
    }
    public function getFileStage(): int { return $this->stage; }
    public function getUploaderUserId(): int { return $this->uploaderId; }
    public function getData($key)
    {
        return ['submissionId' => 32, 'uploaderUserId' => $this->uploaderId, 'assocId' => $this->roundId, 'fileStage' => $this->stage][$key] ?? null;
    }
}

class DAORegistry
{
    public static function getDAO($name)
    {
        if ($name === 'StageAssignmentDAO') {
            return new class {
                public function getBySubmissionAndRoleId($submissionId, $roleId): array { return [new FakeAssignment(10)]; }
            };
        }
        if ($name === 'SubmissionDAO') {
            return new class { public function getById($id) { return new FakeSubmission(); } };
        }
        if ($name === 'ReviewRoundDAO') {
            return new class { public function getById($id) { return new FakeReviewRound((int) $id); } };
        }
        if ($name === 'UserDAO') {
            return new class { public function getById($id) { return new FakeUser(); } };
        }
        throw new RuntimeException('Unexpected DAO: ' . $name);
    }
}

require_once dirname(__DIR__) . '/StageChangeHandler.inc.php';
require_once dirname(__DIR__) . '/handlers/SubmissionNotificationHandler.inc.php';

function assertAuthorRevision($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

StageChangeHandler::setPlugin(new FakeRevisionPlugin());

SubmissionNotificationHandler::handleAuthorRevisionUploaded(new FakeSubmissionFile(1, 10));
assertAuthorRevision(count(ZaloApiClient::$directCalls) === 0, 'File ngoài khu vực bản sửa vẫn tạo tin Zalo.');

SubmissionNotificationHandler::handleAuthorRevisionUploaded(new FakeSubmissionFile(SUBMISSION_FILE_REVIEW_REVISION, 99));
assertAuthorRevision(count(ZaloApiClient::$directCalls) === 0, 'File không do tác giả tải vẫn tạo tin Zalo.');

SubmissionNotificationHandler::handleAuthorRevisionUploaded(new FakeSubmissionFile(SUBMISSION_FILE_REVIEW_REVISION, 10));
assertAuthorRevision(count(ZaloApiClient::$directCalls) === 1, 'Bản sửa của tác giả không tạo tin cho BTV.');
assertAuthorRevision(ZaloApiClient::$directCalls[0]['phones'] === ['0866856490'], 'Tin không chỉ gửi cho BTV được gán.');
assertAuthorRevision(ZaloApiClient::$directCalls[0]['eventType'] === 'AUTHOR_REVISION_ASSIGNED_EDITOR', 'Sai loại sự kiện bản sửa.');
assertAuthorRevision(strpos(ZaloApiClient::$directCalls[0]['message'], 'vòng 1') !== false, 'Tin thiếu vòng phản biện.');

SubmissionNotificationHandler::handleAuthorRevisionUploaded(new FakeSubmissionFile(SUBMISSION_FILE_INTERNAL_REVIEW_REVISION, 10));
assertAuthorRevision(count(ZaloApiClient::$directCalls) === 1, 'Nhiều file cùng bài/vòng không được chống gửi dồn.');

SubmissionNotificationHandler::handleAuthorRevisionUploaded(new FakeSubmissionFile(SUBMISSION_FILE_REVIEW_REVISION, 10, 502));
assertAuthorRevision(count(ZaloApiClient::$directCalls) === 2, 'Bản sửa ở vòng mới bị chặn nhầm.');

echo "Author revision notification test passed.\n";
