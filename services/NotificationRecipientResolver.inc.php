<?php

/** Tìm và khử trùng số điện thoại người nhận từ dữ liệu OJS. */
class NotificationRecipientResolver
{
    public static function getAuthorPhones($submission): array
    {
        $phones = [];
        try {
            $submissionId = (int) $submission->getId();
            $stageAssignmentDao = \DAORegistry::getDAO('StageAssignmentDAO');
            $assignments = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_AUTHOR);
            $assignments = self::resultToArray($assignments);
            $authorFound = count($assignments) > 0;
            foreach ($assignments as $assignment) {
                $user = \DAORegistry::getDAO('UserDAO')->getById($assignment->getUserId());
                if ($user) {
                    $phone = preg_replace('/[^0-9]/', '', trim($user->getPhone() ?? ''));
                    if (!empty($phone)) {
                        $phones[] = $phone;
                    } else {
                        self::writeDebug("getAuthorPhones: Tác giả (User ID {" . $assignment->getUserId() . "}) chưa điền số điện thoại trong hồ sơ.");
                    }
                }
            }
            if (!$authorFound) {
                self::writeDebug("getAuthorPhones: Không tìm thấy tác giả nào được gán vào bài báo #{$submissionId}.");
            }

            $publication = method_exists($submission, 'getCurrentPublication') ? $submission->getCurrentPublication() : null;
            $publicationId = $publication ? (int) $publication->getId() : 0;
            if ($publicationId) {
                $result = \DAORegistry::getDAO('UserDAO')->retrieve(
                    'SELECT DISTINCT u.phone
                       FROM authors a
                       JOIN users u ON LOWER(u.email) = LOWER(a.email)
                      WHERE a.publication_id = ?',
                    [$publicationId]
                );

                if (is_iterable($result)) {
                    foreach ($result as $row) {
                        $row = is_array($row) ? $row : (array) $row;
                        $phone = preg_replace('/[^0-9]/', '', trim($row['phone'] ?? ''));
                        if (!empty($phone)) {
                            $phones[] = $phone;
                        }
                    }
                } else {
                    while (is_object($result) && property_exists($result, 'EOF') && !$result->EOF) {
                        $phone = preg_replace('/[^0-9]/', '', trim($result->fields['phone'] ?? ''));
                        if (!empty($phone)) {
                            $phones[] = $phone;
                        }
                        $result->MoveNext();
                    }
                }

                if (is_object($result) && method_exists($result, 'Close')) {
                    $result->Close();
                }
            }
        } catch (\Throwable $e) {
            self::writeDebug("LỖI getAuthorPhones: " . $e->getMessage());
        }
        return array_unique($phones);
    }

    public static function getEditorPhones(int $submissionId): array
    {
        $phones = [];
        try {
            $stageAssignmentDao = \DAORegistry::getDAO('StageAssignmentDAO');
            $assignments1 = self::resultToArray($stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_MANAGER));
            $assignments2 = self::resultToArray($stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_SUB_EDITOR));
            $assignments = array_merge($assignments1, $assignments2);
            foreach ($assignments as $assignment) {
                $user = \DAORegistry::getDAO('UserDAO')->getById($assignment->getUserId());
                if ($user) {
                    $phone = preg_replace('/[^0-9]/', '', trim($user->getPhone() ?? ''));
                    if (!empty($phone)) $phones[] = $phone;
                }
            }
        } catch (\Throwable $e) {
            self::writeDebug("LỖI getEditorPhones: " . $e->getMessage());
        }
        return array_unique($phones);
    }

    /**
     * Gửi thông báo riêng cho biên tập viên vừa được phân công vào bài.
     */
    public static function excludeEventGroupPhones(string $eventType, array $phones, array $settings): array
    {
        $groupPhones = ZaloApiClient::getPhonesForEvent($eventType, $settings['recipientGroups'] ?? []);
        $groupPhoneMap = array_fill_keys($groupPhones, true);
        $filtered = [];

        foreach ($phones as $phone) {
            $normalized = ZaloApiClient::normalizePhoneNumber((string) $phone);
            if ($normalized === '' || isset($groupPhoneMap[$normalized])) {
                continue;
            }
            $filtered[$normalized] = $phone;
        }

        return array_values($filtered);
    }

    public static function getReviewerPhones(int $submissionId): array
    {
        $phones = [];
        try {
            $submission = \DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
            if (!$submission) {
                return [];
            }

            $stageId = (int) $submission->getStageId();
            $latestReviewRoundId = 0;
            try {
                $reviewRound = \DAORegistry::getDAO('ReviewRoundDAO')
                    ->getLastReviewRoundBySubmissionId($submissionId, $stageId);
                if ($reviewRound) {
                    $latestReviewRoundId = (int) $reviewRound->getId();
                }
            } catch (\Throwable $e) {
                self::writeDebug("getReviewerPhones: Không xác định được vòng phản biện mới nhất của bài #{$submissionId}: " . $e->getMessage());
            }

            $reviewAssignments = \DAORegistry::getDAO('ReviewAssignmentDAO')->getBySubmissionId($submissionId);
            foreach ($reviewAssignments as $assignment) {
                if (!self::isActiveReviewerAssignment($assignment, $stageId, $latestReviewRoundId)) {
                    continue;
                }
                $reviewerId = $assignment->getReviewerId();
                if ($reviewerId) {
                    $user = \DAORegistry::getDAO('UserDAO')->getById($reviewerId);
                    if ($user) {
                        $phone = preg_replace('/[^0-9]/', '', trim($user->getPhone() ?? ''));
                        if (!empty($phone)) {
                            $phones[] = $phone;
                        } else {
                            self::writeDebug("getReviewerPhones: Phản biện viên (User ID {$reviewerId}) chưa điền số điện thoại trong hồ sơ.");
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            self::writeDebug("LỖI getReviewerPhones: " . $e->getMessage());
        }
        return array_unique($phones);
    }

    /** Chỉ nhận reviewer thuộc lượt phản biện đang hoạt động của vòng hiện tại. */
    private static function isActiveReviewerAssignment($assignment, int $stageId, int $latestReviewRoundId): bool
    {
        if (!$assignment) {
            return false;
        }
        if (method_exists($assignment, 'getCancelled') && $assignment->getCancelled()) {
            return false;
        }
        if (method_exists($assignment, 'getDeclined') && $assignment->getDeclined()) {
            return false;
        }
        if (method_exists($assignment, 'getDateCompleted') && $assignment->getDateCompleted()) {
            return false;
        }
        if (method_exists($assignment, 'getStageId') && (int) $assignment->getStageId() !== $stageId) {
            return false;
        }
        if (
            $latestReviewRoundId > 0
            && method_exists($assignment, 'getReviewRoundId')
            && (int) $assignment->getReviewRoundId() !== $latestReviewRoundId
        ) {
            return false;
        }

        return true;
    }

    public static function resultToArray($result): array
    {
        if (!$result) {
            return [];
        }

        if (method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        if (is_iterable($result)) {
            $rows = [];
            foreach ($result as $row) {
                $rows[] = $row;
            }
            return $rows;
        }

        $rows = [];
        while (is_object($result) && property_exists($result, 'EOF') && !$result->EOF) {
            $rows[] = $result->fields;
            $result->MoveNext();
        }
        return $rows;
    }


    private static function writeDebug(string $message): void
    {
        ZaloNotificationPlugin::writeSecureDebug($message, 'RecipientResolver');
    }
}
