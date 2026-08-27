<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/** Đọc dữ liệu các lượt phản biện đang mở để hiển thị trên dashboard. */
class ReviewReminderDashboardService
{
    public static function getSubmissions(int $contextId): array
    {
        if ($contextId <= 0) {
            return [];
        }

        $rows = Capsule::table('review_assignments as ra')
            ->join('submissions as s', 's.submission_id', '=', 'ra.submission_id')
            ->where('s.context_id', $contextId)
            ->whereIn('s.stage_id', [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])
            ->whereColumn('ra.stage_id', 's.stage_id')
            ->where('ra.cancelled', 0)
            ->where('ra.declined', 0)
            ->whereNull('ra.date_completed')
            ->orderByRaw('CASE WHEN ra.date_due IS NULL THEN 1 ELSE 0 END')
            ->orderBy('ra.date_due')
            ->orderBy('ra.submission_id')
            ->limit(500)
            ->get([
                'ra.review_id', 'ra.submission_id', 'ra.reviewer_id', 'ra.round',
                'ra.date_assigned', 'ra.date_notified', 'ra.date_confirmed',
                'ra.date_due', 'ra.date_response_due', 'ra.date_reminded', 'ra.stage_id',
            ]);

        $submissionDao = \DAORegistry::getDAO('SubmissionDAO');
        $userDao = \DAORegistry::getDAO('UserDAO');
        $grouped = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $submissionId = (int) $row['submission_id'];
            if (!isset($grouped[$submissionId])) {
                $submission = $submissionDao->getById($submissionId);
                if (!$submission || (int) $submission->getContextId() !== $contextId) {
                    continue;
                }
                $publication = $submission->getCurrentPublication();
                $navigation = MessageHelper::getNavigationData($submission, 0, (int) $submission->getStageId());
                $grouped[$submissionId] = [
                    'id' => $submissionId,
                    'title' => $publication ? strip_tags((string) $publication->getLocalizedTitle()) : "Bài báo #{$submissionId}",
                    'authors' => MessageHelper::getAuthorsString($publication ?: $submission),
                    'dateSubmitted' => self::formatDate($submission->getDateSubmitted()),
                    'stageName' => MessageHelper::getStageName((int) $submission->getStageId()),
                    'workflowUrl' => $navigation['workflowUrl'] ?? '',
                    'reviewers' => [],
                    'sendableCount' => 0,
                ];
            }

            $reviewer = $userDao->getById((int) $row['reviewer_id']);
            $phone = $reviewer ? trim((string) $reviewer->getPhone()) : '';
            $due = self::getDueState((string) ($row['date_due'] ?? ''));
            $wasInvited = !empty($row['date_notified']);
            $canSend = $reviewer && $wasInvited && ZaloApiClient::normalizePhoneNumber($phone) !== '';

            if ($canSend) {
                $grouped[$submissionId]['sendableCount']++;
            }
            $grouped[$submissionId]['reviewers'][] = [
                'reviewId' => (int) $row['review_id'],
                'reviewerId' => (int) $row['reviewer_id'],
                'name' => $reviewer ? $reviewer->getFullName() : 'Tài khoản không còn tồn tại',
                'email' => $reviewer ? (string) $reviewer->getEmail() : '',
                'phone' => $phone,
                'round' => max(1, (int) $row['round']),
                'dateAssigned' => self::formatDate($row['date_assigned'] ?? ''),
                'dateNotified' => self::formatDate($row['date_notified'] ?? ''),
                'dateDue' => self::formatDate($row['date_due'] ?? ''),
                'dateResponseDue' => self::formatDate($row['date_response_due'] ?? ''),
                'dateReminded' => self::formatDateTime($row['date_reminded'] ?? ''),
                'responseStatus' => !$wasInvited ? 'Chưa gửi lời mời' : (!empty($row['date_confirmed']) ? 'Đã chấp nhận' : 'Chờ phản hồi'),
                'dueClass' => $due['class'],
                'dueLabel' => $due['label'],
                'daysLeft' => $due['daysLeft'],
                'canSend' => $canSend,
                'hasValidPhone' => ZaloApiClient::normalizePhoneNumber($phone) !== '',
                'sendDisabledReason' => !$reviewer ? 'Không tìm thấy tài khoản' : (!$wasInvited ? 'Chưa gửi lời mời phản biện' : (ZaloApiClient::normalizePhoneNumber($phone) === '' ? 'Thiếu số điện thoại hợp lệ' : '')),
            ];
        }

        return array_values($grouped);
    }

    public static function getActiveAssignment(int $reviewId, int $contextId)
    {
        if ($reviewId <= 0 || $contextId <= 0) {
            return null;
        }
        $allowed = Capsule::table('review_assignments as ra')
            ->join('submissions as s', 's.submission_id', '=', 'ra.submission_id')
            ->join('users as u', 'u.user_id', '=', 'ra.reviewer_id')
            ->where('ra.review_id', $reviewId)
            ->where('s.context_id', $contextId)
            ->whereIn('s.stage_id', [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])
            ->whereColumn('ra.stage_id', 's.stage_id')
            ->where('ra.cancelled', 0)->where('ra.declined', 0)->whereNull('ra.date_completed')
            ->exists();
        return $allowed ? \DAORegistry::getDAO('ReviewAssignmentDAO')->getById($reviewId) : null;
    }

    public static function getActiveAssignmentsForSubmission(int $submissionId, int $contextId): array
    {
        if ($submissionId <= 0 || $contextId <= 0) {
            return [];
        }
        $ids = Capsule::table('review_assignments as ra')
            ->join('submissions as s', 's.submission_id', '=', 'ra.submission_id')
            ->join('users as u', 'u.user_id', '=', 'ra.reviewer_id')
            ->where('ra.submission_id', $submissionId)->where('s.context_id', $contextId)
            ->whereIn('s.stage_id', [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])
            ->whereColumn('ra.stage_id', 's.stage_id')
            ->where('ra.cancelled', 0)->where('ra.declined', 0)->whereNull('ra.date_completed')
            ->orderBy('ra.review_id')->pluck('ra.review_id');

        $dao = \DAORegistry::getDAO('ReviewAssignmentDAO');
        $assignments = [];
        foreach ($ids as $id) {
            $assignment = $dao->getById((int) $id);
            if ($assignment) {
                $assignments[] = $assignment;
            }
        }
        return $assignments;
    }

    private static function getDueState(string $dateDue): array
    {
        if ($dateDue === '' || strtotime($dateDue) === false) {
            return ['class' => 'none', 'label' => 'Chưa đặt hạn', 'daysLeft' => 0];
        }
        $dueDay = strtotime(date('Y-m-d', strtotime($dateDue)));
        $today = strtotime(date('Y-m-d'));
        $daysLeft = (int) round(($dueDay - $today) / 86400);
        if ($daysLeft < 0) return ['class' => 'overdue', 'label' => 'Quá hạn ' . abs($daysLeft) . ' ngày', 'daysLeft' => $daysLeft];
        if ($daysLeft === 0) return ['class' => 'today', 'label' => 'Hết hạn hôm nay', 'daysLeft' => 0];
        return ['class' => 'upcoming', 'label' => 'Còn ' . $daysLeft . ' ngày', 'daysLeft' => $daysLeft];
    }

    private static function formatDate($value): string
    {
        $timestamp = $value ? strtotime((string) $value) : false;
        return $timestamp ? date('d/m/Y', $timestamp) : '—';
    }

    private static function formatDateTime($value): string
    {
        $timestamp = $value ? strtotime((string) $value) : false;
        return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Chưa nhắc';
    }
}
