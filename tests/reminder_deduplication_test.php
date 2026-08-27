<?php

function failReminderDeduplicationTest(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

class StageChangeHandler
{
}

class ReminderAssignment
{
    private $dateReminded;

    public function __construct($dateReminded)
    {
        $this->dateReminded = $dateReminded;
    }

    public function getDateReminded()
    {
        return $this->dateReminded;
    }
}

require_once dirname(__DIR__) . '/handlers/ReviewNotificationHandler.inc.php';

$contextId = 77;
$reviewId = 88;
$expectedKey = "REVIEW_REMINDER_DAILY_{$contextId}_{$reviewId}_" . date('Y-m-d');
if (ReviewNotificationHandler::getDailyReminderKey($contextId, $reviewId) !== $expectedKey) {
    failReminderDeduplicationTest('Khóa nhắc hàng ngày không ổn định theo context và review ID.');
}
if (!ReviewNotificationHandler::wasRemindedToday(new ReminderAssignment(date('Y-m-d H:i:s')))) {
    failReminderDeduplicationTest('Không nhận diện được lượt đã nhắc trong ngày.');
}
if (ReviewNotificationHandler::wasRemindedToday(new ReminderAssignment(date('Y-m-d H:i:s', time() - 172800)))) {
    failReminderDeduplicationTest('Lượt nhắc ngày cũ bị nhận diện nhầm là hôm nay.');
}

$reviewSource = file_get_contents(dirname(__DIR__) . '/handlers/ReviewNotificationHandler.inc.php');
$editorialSource = file_get_contents(dirname(__DIR__) . '/handlers/EditorialNotificationHandler.inc.php');
if (strpos($reviewSource, 'AND (ra.date_reminded IS NULL OR DATE(ra.date_reminded) < CURRENT_DATE)') === false
    || strpos($reviewSource, '$dailyKey = self::getDailyReminderKey($contextId, $reviewId)') === false
    || strpos($editorialSource, 'ReviewNotificationHandler::getDailyReminderKey($contextId, $reviewId)') === false
    || strpos($editorialSource, 'ReviewNotificationHandler::wasRemindedToday($reviewAssignment)') === false) {
    failReminderDeduplicationTest('Hook email và quét quá hạn chưa dùng chung cơ chế chống trùng.');
}

echo "Review reminder deduplication test passed.\n";
