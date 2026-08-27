<?php

function failReviewerFilterTest(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

class ZaloNotificationPlugin
{
    public static function writeSecureDebug(string $message, string $component = ''): void
    {
    }
}

class FilterSubmission
{
    public function getStageId(): int
    {
        return 3;
    }
}

class FilterReviewRound
{
    public function getId(): int
    {
        return 500;
    }
}

class FilterUser
{
    private $phone;

    public function __construct(string $phone)
    {
        $this->phone = $phone;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }
}

class FilterAssignment
{
    private $reviewerId;
    private $cancelled;
    private $declined;
    private $completed;
    private $stageId;
    private $reviewRoundId;

    public function __construct(
        int $reviewerId,
        bool $cancelled = false,
        bool $declined = false,
        bool $completed = false,
        int $stageId = 3,
        int $reviewRoundId = 500
    ) {
        $this->reviewerId = $reviewerId;
        $this->cancelled = $cancelled;
        $this->declined = $declined;
        $this->completed = $completed;
        $this->stageId = $stageId;
        $this->reviewRoundId = $reviewRoundId;
    }

    public function getReviewerId(): int { return $this->reviewerId; }
    public function getCancelled(): bool { return $this->cancelled; }
    public function getDeclined(): bool { return $this->declined; }
    public function getDateCompleted() { return $this->completed ? '2026-07-20 10:00:00' : null; }
    public function getStageId(): int { return $this->stageId; }
    public function getReviewRoundId(): int { return $this->reviewRoundId; }
}

class DAORegistry
{
    public static function getDAO($name)
    {
        if ($name === 'SubmissionDAO') {
            return new class {
                public function getById($id) { return new FilterSubmission(); }
            };
        }
        if ($name === 'ReviewRoundDAO') {
            return new class {
                public function getLastReviewRoundBySubmissionId($submissionId, $stageId) { return new FilterReviewRound(); }
            };
        }
        if ($name === 'ReviewAssignmentDAO') {
            return new class {
                public function getBySubmissionId($submissionId): array
                {
                    return [
                        new FilterAssignment(1),
                        new FilterAssignment(2, true),
                        new FilterAssignment(3, false, true),
                        new FilterAssignment(4, false, false, true),
                        new FilterAssignment(5, false, false, false, 2),
                        new FilterAssignment(6, false, false, false, 3, 499),
                    ];
                }
            };
        }
        if ($name === 'UserDAO') {
            return new class {
                public function getById($id) { return new FilterUser('090000000' . $id); }
            };
        }
        throw new RuntimeException("Unexpected DAO: {$name}");
    }
}

require_once dirname(__DIR__) . '/services/NotificationRecipientResolver.inc.php';

$phones = NotificationRecipientResolver::getReviewerPhones(7001);
if ($phones !== ['0900000001']) {
    failReviewerFilterTest('Danh sách reviewer còn chứa lượt đã hủy, từ chối, hoàn tất, sai stage hoặc vòng cũ.');
}

echo "Active reviewer recipient filter test passed.\n";
