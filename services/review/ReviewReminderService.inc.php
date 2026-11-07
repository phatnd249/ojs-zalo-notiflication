<?php

class ReviewReminderService extends StageChangeHandler
{
    public static function getDailyReminderKey(int $contextId, int $reviewId): string
    {
        return "REVIEW_REMINDER_DAILY_{$contextId}_{$reviewId}_" . date('Y-m-d');
    }

    public static function wasRemindedToday($reviewAssignment): bool
    {
        if (!$reviewAssignment || !method_exists($reviewAssignment, 'getDateReminded')) {
            return false;
        }
        $dateReminded = $reviewAssignment->getDateReminded();
        $timestamp = $dateReminded ? strtotime((string) $dateReminded) : false;
        return $timestamp !== false && date('Y-m-d', $timestamp) === date('Y-m-d');
    }

    public static function checkOverdueReviewDeadlines($plugin = null, int $contextId = 0): void
    {
        $scanLockHandle = null;
        try {
            if ($plugin) {
                self::setPlugin($plugin);
            }

            // Không bao giờ quét toàn hệ thống khi không xác định được tạp chí.
            if ($contextId <= 0) {
                self::writeDebug('checkOverdueReviewDeadlines: Bỏ qua vì không xác định được context.');
                return;
            }

            // Khóa theo context chỉ ngăn hai worker quét đồng thời; lịch chạy do scheduled task quyết định.
            $scanLock = ZaloNotificationPlugin::getDataDir() . "/deadline_scan_context_{$contextId}.lock";
            $scanLockHandle = @fopen($scanLock, 'c');
            if (!$scanLockHandle || !@flock($scanLockHandle, LOCK_EX | LOCK_NB)) {
                return;
            }

            $settings = self::getZaloSettings($contextId);
            $reminderState = self::loadReminderState($contextId);

            $beforeDays = (int) ($settings['reviewReminderBeforeDays'] ?? 4);
            $queryIntervalDays = max(1, $beforeDays);

            // 1. Quét thông báo Sắp đến hạn và Đến hạn hôm nay
            try {
                $upcomingResult = \DAORegistry::getDAO('UserDAO')->retrieve(
                    'SELECT ra.review_id, ra.submission_id, ra.reviewer_id, ra.date_due, ra.round
                       FROM review_assignments ra
                       JOIN submissions s ON s.submission_id = ra.submission_id
                      WHERE s.context_id = ?
                        AND ra.cancelled = 0
                        AND ra.declined = 0
                        AND ra.date_completed IS NULL
                        AND ra.date_due IS NOT NULL
                        AND DATE(ra.date_due) BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL ' . $queryIntervalDays . ' DAY)
                   ORDER BY ra.date_due ASC
                      LIMIT 50',
                    [$contextId]
                );

                if (is_iterable($upcomingResult)) {
                    foreach ($upcomingResult as $uRow) {
                        $uRow = is_array($uRow) ? $uRow : (array) $uRow;
                        self::processUpcomingRow($uRow, $contextId, $settings);
                    }
                } else {
                    while (is_object($upcomingResult) && property_exists($upcomingResult, 'EOF') && !$upcomingResult->EOF) {
                        self::processUpcomingRow((array) $upcomingResult->fields, $contextId, $settings);
                        $upcomingResult->MoveNext();
                    }
                }
                if (is_object($upcomingResult) && method_exists($upcomingResult, 'Close')) {
                    $upcomingResult->Close();
                }
            } catch (\Throwable $upErr) {
                self::writeDebug("LỖI quét sắp đến hạn context {$contextId}: " . $upErr->getMessage());
            }

            // 2. Quét thông báo Quá hạn (Overdue) theo tần suất và số lần tối đa
            $result = \DAORegistry::getDAO('UserDAO')->retrieve(
                'SELECT ra.review_id, ra.submission_id, ra.reviewer_id, ra.date_due, ra.round
                   FROM review_assignments ra
                   JOIN submissions s ON s.submission_id = ra.submission_id
                  WHERE s.context_id = ?
                    AND ra.cancelled = 0
                    AND ra.declined = 0
                    AND ra.date_completed IS NULL
                    AND ra.date_due IS NOT NULL
                    AND ra.date_due < NOW()
                    AND (ra.date_reminded IS NULL OR DATE(ra.date_reminded) < CURRENT_DATE)
               ORDER BY ra.date_due ASC
                  LIMIT 25',
                [$contextId]
            );

            $maxOverdue = (int) ($settings['reviewReminderMaxOverdueCount'] ?? 3);
            $overdueFreq = (int) ($settings['reviewReminderOverdueFrequency'] ?? 2);

            $overdueRows = [];
            if (is_iterable($result)) {
                foreach ($result as $row) {
                    $overdueRows[] = is_array($row) ? $row : (array) $row;
                }
            } else {
                while (is_object($result) && property_exists($result, 'EOF') && !$result->EOF) {
                    $overdueRows[] = (array) $result->fields;
                    $result->MoveNext();
                }
            }
            if (is_object($result) && method_exists($result, 'Close')) {
                $result->Close();
            }

            $stateModified = false;
            foreach ($overdueRows as $row) {
                $reviewId = (int) ($row['review_id'] ?? 0);
                if ($reviewId <= 0) {
                    continue;
                }

                $rState = $reminderState[$reviewId] ?? [];
                if (($rState['date_due'] ?? '') !== (string) ($row['date_due'] ?? '')) {
                    $rState = ['date_due' => (string) ($row['date_due'] ?? ''), 'overdue_count' => 0];
                }

                $currentCount = (int) ($rState['overdue_count'] ?? 0);
                if ($currentCount >= $maxOverdue) {
                    continue;
                }

                $lastOverdueTime = (int) ($rState['last_overdue_time'] ?? 0);
                if ($lastOverdueTime > 0 && (time() - $lastOverdueTime) < ($overdueFreq * 86400 - 3600)) {
                    continue;
                }

                $nextCount = $currentCount + 1;
                $dueTime = strtotime((string) $row['date_due']);
                $daysOverdue = $dueTime ? max(1, (int) floor((time() - $dueTime) / 86400)) : 1;
                $overdueText = "Quá hạn {$daysOverdue} ngày (Nhắc lần {$nextCount}/{$maxOverdue})";

                self::sendOverdueReviewReminder(
                    $reviewId,
                    (int) ($row['submission_id'] ?? 0),
                    (int) ($row['reviewer_id'] ?? 0),
                    (string) ($row['date_due'] ?? ''),
                    (int) ($row['round'] ?? 1),
                    $contextId,
                    "Quá hạn lần {$nextCount}",
                    $overdueText
                );

                $rState['overdue_count'] = $nextCount;
                $rState['last_overdue_time'] = time();
                $rState['last_overdue_date'] = date('Y-m-d');
                $reminderState[$reviewId] = $rState;
                $stateModified = true;
            }

            if ($stateModified) {
                self::saveReminderState($contextId, $reminderState);
            }
        } catch (\Throwable $e) {
            self::writeDebug("LỖI checkOverdueReviewDeadlines context {$contextId}: " . $e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
            ActivityLogger::logError('checkOverdueReviewDeadlines', $e->getMessage(), 0, $e, $contextId);
        } finally {
            if (is_resource($scanLockHandle)) {
                @flock($scanLockHandle, LOCK_UN);
                @fclose($scanLockHandle);
            }
        }
    }

    private static function processUpcomingRow(array $row, int $contextId, array $settings): void
    {
        $reviewId = (int) ($row['review_id'] ?? 0);
        $dateDue = (string) ($row['date_due'] ?? '');
        $dueTime = strtotime($dateDue);
        if ($dueTime === false || $reviewId <= 0) {
            return;
        }

        $dueDay = strtotime(date('Y-m-d', $dueTime));
        $today = strtotime(date('Y-m-d'));
        $daysDiff = (int) round(($dueDay - $today) / 86400);

        $beforeDays = (int) ($settings['reviewReminderBeforeDays'] ?? 4);

        if ($beforeDays > 0 && $daysDiff === $beforeDays) {
            $upcomingKey = "REMINDER_UPCOMING_{$contextId}_{$reviewId}_" . date('Y-m-d', $dueDay);
            if (!ActivityLogger::isAlreadyLogged($upcomingKey, 86400 * 5, $contextId)) {
                self::sendOverdueReviewReminder(
                    $reviewId,
                    (int) ($row['submission_id'] ?? 0),
                    (int) ($row['reviewer_id'] ?? 0),
                    $dateDue,
                    (int) ($row['round'] ?? 1),
                    $contextId,
                    "Sắp đến hạn (còn {$beforeDays} ngày)",
                    "Còn {$beforeDays} ngày",
                    $upcomingKey
                );
            }
        } elseif ($daysDiff === 0 && !empty($settings['reviewReminderOnDueDate'])) {
            $todayKey = "REMINDER_TODAY_{$contextId}_{$reviewId}_" . date('Y-m-d', $dueDay);
            if (!ActivityLogger::isAlreadyLogged($todayKey, 86400 * 2, $contextId)) {
                self::sendOverdueReviewReminder(
                    $reviewId,
                    (int) ($row['submission_id'] ?? 0),
                    (int) ($row['reviewer_id'] ?? 0),
                    $dateDue,
                    (int) ($row['round'] ?? 1),
                    $contextId,
                    'Đến hạn hôm nay',
                    'Hôm nay là hạn chót',
                    $todayKey
                );
            }
        }
    }

    private static function loadReminderState(int $contextId): array
    {
        $stateFile = ZaloNotificationPlugin::getDataDir() . "/review_reminder_state_{$contextId}.json";
        if (file_exists($stateFile)) {
            $content = @file_get_contents($stateFile);
            $data = $content ? json_decode($content, true) : null;
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    private static function saveReminderState(int $contextId, array $state): void
    {
        $stateFile = ZaloNotificationPlugin::getDataDir() . "/review_reminder_state_{$contextId}.json";
        $cutoff = time() - (90 * 86400);
        foreach ($state as $rId => $item) {
            if (isset($item['last_overdue_time']) && $item['last_overdue_time'] < $cutoff) {
                unset($state[$rId]);
            }
        }
        @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function sendOverdueReviewReminder(
        int $reviewId,
        int $submissionId,
        int $reviewerId,
        string $dateDue,
        int $round,
        int $contextId,
        string $overrideType = '',
        string $overrideDaysText = '',
        string $overrideActionKey = ''
    ): void
    {
        $dailyKey = self::getDailyReminderKey($contextId, $reviewId);
        if ($overrideActionKey !== '') {
            $dailyKey = $overrideActionKey;
        }
        if (ActivityLogger::isAlreadyLogged($dailyKey, 172800, $contextId)) {
            return;
        }

        try {
            $reviewAssignment = \DAORegistry::getDAO('ReviewAssignmentDAO')->getById($reviewId);
            if (!$reviewAssignment || self::wasRemindedToday($reviewAssignment)) {
                return;
            }
            $submission = \DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
            if (!$submission) {
                return;
            }
            if ((int) $submission->getContextId() !== $contextId) {
                self::writeDebug("sendOverdueReviewReminder: Bỏ qua submission #{$submissionId} không thuộc context {$contextId}.");
                return;
            }

            $publication = $submission->getCurrentPublication();
            $title = $publication ? strip_tags($publication->getLocalizedTitle() ?? "ID #{$submissionId}") : "ID #{$submissionId}";
            $author = MessageHelper::getAuthorsString($publication ?: $submission);

            $reviewerName = "Reviewer #{$reviewerId}";
            $reviewerPhone = '';
            $reviewer = \DAORegistry::getDAO('UserDAO')->getById($reviewerId);
            if ($reviewer) {
                $reviewerName = $reviewer->getFullName();
                $reviewerPhone = preg_replace('/[^0-9]/', '', trim($reviewer->getPhone() ?? ''));
            }

            $dueTime = strtotime($dateDue);
            $daysOverdue = $dueTime ? max(0, (int) floor((time() - $dueTime) / 86400)) : 0;
            $daysText = $overrideDaysText !== '' ? $overrideDaysText : ($daysOverdue > 0 ? "Quá hạn {$daysOverdue} ngày" : 'Đến hạn hôm nay');
            $deadline = $dueTime ? date('d/m/Y', $dueTime) : $dateDue;

            $settings = self::getZaloSettings($contextId);
            $templates = self::$plugin ? self::$plugin->getMessageTemplatesForContext($contextId) : [];
            $editorTemplate = $templates['editor']['reminder'] ?? '';
            $reviewerTemplate = $templates['reviewer']['reminder'] ?? '';
            $data = array_merge(MessageHelper::getNavigationData($submission, $reviewId), [
                'title' => $title,
                'author' => $author,
                'reviewerName' => $reviewerName,
                'deadline' => $deadline,
                'daysLeft' => $daysText,
                'round' => $round ?: 1,
                'timestamp' => date('d/m/Y H:i:s')
                ]);

            $sendEditor = (bool) ($settings['reviewReminderSendEditor'] ?? true);
            $sendReviewer = (bool) ($settings['reviewReminderSendReviewer'] ?? true);

            $statuses = [];
            if ($sendEditor && self::shouldSendTemplate($editorTemplate)) {
                $editorMessage = MessageHelper::buildMessage($editorTemplate, $data);
                $statuses[] = 'Editor: ' . self::sendToEditorAudience(
                    $submission, 'REVIEW_REMINDER', $editorMessage, $settings,
                    'REVIEW_REMINDER_ASSIGNED_EDITOR'
                );
            }

            if ($sendReviewer && self::shouldSendTemplate($reviewerTemplate)) {
                if ($reviewerPhone !== '') {
                    $reviewerMessage = MessageHelper::buildMessage($reviewerTemplate, $data);
                    $statuses[] = 'Reviewer: ' . ZaloApiClient::sendToPhones(
                        $reviewerMessage, $settings['botId'], $settings['apiKey'], [$reviewerPhone],
                        'REVIEW_REMINDER', self::getSubmissionContextId($submission)
                    );
                } else {
                    $statuses[] = 'Reviewer: SKIPPED (Chưa có SĐT)';
                }
            }

            $zaloStatus = $statuses ? implode(' | ', $statuses) : 'SKIPPED';
            $isSent = false;
            foreach ($statuses as $st) {
                if (strpos($st, 'Thành công') !== false || strpos($st, 'Đã xếp hàng') !== false) {
                    $isSent = true;
                    break;
                }
            }
            if ($isSent && $reviewAssignment) {
                try {
                    $reviewAssignment->setDateReminded(date('Y-m-d H:i:s'));
                    $reviewAssignment->setReminderWasAutomatic(1);
                    \DAORegistry::getDAO('ReviewAssignmentDAO')->updateObject($reviewAssignment);
                } catch (\Throwable $updateError) {
                    self::writeDebug('sendOverdueReviewReminder: Không cập nhật được date_reminded: ' . $updateError->getMessage());
                }
            }

            $logType = $overrideType !== '' ? $overrideType : 'Quá hạn phản biện';
            ActivityLogger::logReviewReminder($submissionId, $title, $reviewerName, $deadline, -$daysOverdue, $zaloStatus, $logType, $dailyKey, $contextId);
            self::writeDebug("checkOverdueReviewDeadlines context {$contextId} #{$submissionId}/review {$reviewId}: {$zaloStatus}");
        } catch (\Throwable $e) {
            self::writeDebug("LỖI sendOverdueReviewReminder review {$reviewId}: " . $e->getMessage());
            ActivityLogger::logError('sendOverdueReviewReminder', $e->getMessage(), $submissionId, $e, $contextId);
        }
    }
}
