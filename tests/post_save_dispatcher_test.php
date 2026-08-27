<?php

class ZaloNotificationPlugin
{
    public static function writeSecureDebug($message, $component = ''): void
    {
    }
}

class FakePostSaveSubmission
{
    private $id;
    private $stageId;
    private $data;

    public function __construct(int $id, int $stageId = 1, array $data = [])
    {
        $this->id = $id;
        $this->stageId = $stageId;
        $this->data = $data;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStageId(): int
    {
        return $this->stageId;
    }

    public function getData($key)
    {
        return $this->data[$key] ?? null;
    }
}

class FakeEditDecisionDAO
{
    public $decisions = [];

    public function getEditorDecisions($submissionId): array
    {
        return $this->decisions;
    }
}

class FakeSubmissionDAO
{
    public $submission;

    public function getById($submissionId)
    {
        return $this->submission && $this->submission->getId() === $submissionId
            ? $this->submission
            : null;
    }
}

class DAORegistry
{
    public static $decisionDao;
    public static $submissionDao;

    public static function getDAO($name)
    {
        if ($name === 'EditDecisionDAO') {
            return self::$decisionDao;
        }
        if ($name === 'SubmissionDAO') {
            return self::$submissionDao;
        }
        throw new RuntimeException('Unexpected DAO: ' . $name);
    }
}

class StageChangeHandler
{
    public static $decisionCalls = [];
    public static $reviewStageCalls = [];
    public static $submissionCalls = [];

    public static function setPlugin($plugin): void
    {
    }

    public static function handleDecision33($submission, $decision): void
    {
        self::$decisionCalls[] = [$submission, $decision];
    }

    public static function handleAuthorEnteredReview($submission, int $previousStageId, int $currentStageId): void
    {
        self::$reviewStageCalls[] = [$submission, $previousStageId, $currentStageId];
    }

    public static function handleSubmission($submission, $oldSubmission = null, string $source = ''): void
    {
        self::$submissionCalls[] = [$submission, $oldSubmission, $source];
    }
}

require_once dirname(__DIR__) . '/services/PostSaveNotificationDispatcher.inc.php';

$submission = new FakePostSaveSubmission(88001);
DAORegistry::$decisionDao = new FakeEditDecisionDAO();
DAORegistry::$submissionDao = new FakeSubmissionDAO();
DAORegistry::$submissionDao->submission = $submission;
$plugin = new stdClass();
$intent = ['decision' => 7, 'editorId' => 42];

// OJS không ghi bản ghi mới: dispatcher không được gửi.
PostSaveNotificationDispatcher::deferDecision($plugin, $submission, $intent);
PostSaveNotificationDispatcher::flush();
if (count(StageChangeHandler::$decisionCalls) !== 0) {
    fwrite(STDERR, "Dispatcher sent a notification for an uncommitted decision.\n");
    exit(1);
}

// OJS ghi thành công sau hook: dispatcher tải lại và chỉ gửi một lần.
PostSaveNotificationDispatcher::deferDecision($plugin, $submission, $intent);
DAORegistry::$submissionDao->submission = new FakePostSaveSubmission(88001, 3);
DAORegistry::$decisionDao->decisions[] = [
    'editDecisionId' => 901,
    'decision' => 7,
    'editorId' => 42,
    'stageId' => 3,
];
PostSaveNotificationDispatcher::flush();
if (count(StageChangeHandler::$decisionCalls) !== 1
    || (int) StageChangeHandler::$decisionCalls[0][1]['editDecisionId'] !== 901
    || count(StageChangeHandler::$reviewStageCalls) !== 1
    || StageChangeHandler::$reviewStageCalls[0][1] !== 1
    || StageChangeHandler::$reviewStageCalls[0][2] !== 3
) {
    fwrite(STDERR, "Dispatcher did not emit the persisted decision and review-stage transition exactly once.\n");
    exit(1);
}

// Submission::edit cũng là hook trước lưu: chỉ chuyển tiếp sau khi dữ liệu khớp database.
$oldDraft = new FakePostSaveSubmission(88002, 1, ['submissionProgress' => 1, 'dateSubmitted' => null]);
$completedIntent = new FakePostSaveSubmission(88002, 1, [
    'submissionProgress' => 0,
    'dateSubmitted' => '2026-07-20 10:00:00',
]);
DAORegistry::$submissionDao->submission = $oldDraft;
PostSaveNotificationDispatcher::deferSubmissionEdit($plugin, $completedIntent, $oldDraft);
PostSaveNotificationDispatcher::flush();
if (count(StageChangeHandler::$submissionCalls) !== 0) {
    fwrite(STDERR, "Dispatcher emitted an unpersisted submission edit.\n");
    exit(1);
}

PostSaveNotificationDispatcher::deferSubmissionEdit($plugin, $completedIntent, $oldDraft);
DAORegistry::$submissionDao->submission = $completedIntent;
PostSaveNotificationDispatcher::flush();
if (count(StageChangeHandler::$submissionCalls) !== 1
    || StageChangeHandler::$submissionCalls[0][2] !== 'Submission::edit:post-save'
) {
    fwrite(STDERR, "Dispatcher did not emit the persisted submission edit.\n");
    exit(1);
}

echo "Post-save dispatcher tests passed.\n";
