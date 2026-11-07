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

    /**
     * Dữ liệu mẫu 34 bài báo chuẩn hóa theo thiết kế trung tâm điều hành.
     */
    public static function getShowcaseSubmissions(): array
    {
        return [
            [
                'id' => 1042,
                'title' => 'Ứng dụng AI trong Chẩn đoán Y tế',
                'authors' => 'Nguyễn Hải Đăng, Phạm Minh Tuấn',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'GS. TS. Nguyễn Văn A',
                'dateDue' => '28/10/2026',
                'statusClass' => 'upcoming',
                'statusBadge' => 'Còn 4 ngày',
                'statusIcon' => 'clock-o',
                'daysLeft' => 4,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 901,
                        'name' => 'GS. TS. Nguyễn Văn A',
                        'email' => 'nguyenvana@daihoc.edu.vn',
                        'phone' => '0912345678',
                        'dateDue' => '28/10/2026',
                        'dueClass' => 'upcoming',
                        'dueLabel' => 'Còn 4 ngày',
                        'daysLeft' => 4,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1039,
                'title' => 'Nghiên cứu về vật liệu nano mới',
                'authors' => 'Trần Thị Thu, Lê Văn Hùng',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Trần Thị B',
                'dateDue' => '25/10/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 1,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 902,
                        'name' => 'PGS. TS. Trần Thị B',
                        'email' => 'tranthib@vienkhcn.ac.vn',
                        'phone' => '0983112233',
                        'dateDue' => '25/10/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn',
                        'daysLeft' => 1,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1037,
                'title' => 'Phân tích dữ liệu lớn trong kinh tế',
                'authors' => 'Vũ Quốc Bảo, Hoàng Anh Quân',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Lê Văn C',
                'dateDue' => '20/10/2026',
                'statusClass' => 'overdue',
                'statusBadge' => 'Quá hạn',
                'statusIcon' => 'times',
                'daysLeft' => -4,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 903,
                        'name' => 'TS. Lê Văn C',
                        'email' => 'levanc@kinhte.edu.vn',
                        'phone' => '0905445566',
                        'dateDue' => '20/10/2026',
                        'dueClass' => 'overdue',
                        'dueLabel' => 'Quá hạn 4 ngày',
                        'daysLeft' => -4,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => '21/10/2026 09:30',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1035,
                'title' => 'Mô hình học máy dự báo phụ tải điện lưới thông minh',
                'authors' => 'Đặng Văn Lâm, Bùi Hoàng Việt',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Vũ Đình Kha',
                'dateDue' => '18/10/2026',
                'statusClass' => 'overdue',
                'statusBadge' => 'Quá hạn',
                'statusIcon' => 'times',
                'daysLeft' => -6,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 904,
                        'name' => 'PGS. TS. Vũ Đình Kha',
                        'email' => 'vudinhkha@bachkhoa.edu.vn',
                        'phone' => '0918776655',
                        'dateDue' => '18/10/2026',
                        'dueClass' => 'overdue',
                        'dueLabel' => 'Quá hạn 6 ngày',
                        'daysLeft' => -6,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => '19/10/2026 14:15',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1032,
                'title' => 'Tổng hợp hoạt chất kháng khuẩn từ nguồn thực vật bản địa',
                'authors' => 'Phan Thị Mai Lan, Đỗ Văn Nam',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Nguyễn Thị Thùy Dung',
                'dateDue' => '16/10/2026',
                'statusClass' => 'overdue',
                'statusBadge' => 'Quá hạn',
                'statusIcon' => 'times',
                'daysLeft' => -8,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 905,
                        'name' => 'TS. Nguyễn Thị Thùy Dung',
                        'email' => 'thuydung@duochanoi.edu.vn',
                        'phone' => '0977223344',
                        'dateDue' => '16/10/2026',
                        'dueClass' => 'overdue',
                        'dueLabel' => 'Quá hạn 8 ngày',
                        'daysLeft' => -8,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => '18/10/2026 08:45',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1030,
                'title' => 'Chính sách thuế quan và tác động tới chuỗi cung ứng thủy sản',
                'authors' => 'Nguyễn Khắc Minh, Trần Đức Thắng',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'GS. TS. Phạm Thanh Bình',
                'dateDue' => '15/10/2026',
                'statusClass' => 'overdue',
                'statusBadge' => 'Quá hạn',
                'statusIcon' => 'times',
                'daysLeft' => -9,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 906,
                        'name' => 'GS. TS. Phạm Thanh Bình',
                        'email' => 'thanhbinh@ftu.edu.vn',
                        'phone' => '0903889900',
                        'dateDue' => '15/10/2026',
                        'dueClass' => 'overdue',
                        'dueLabel' => 'Quá hạn 9 ngày',
                        'daysLeft' => -9,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => '17/10/2026 10:20',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1028,
                'title' => 'Chuyển biến văn hóa làng nghề truyền thống vùng Bắc Bộ',
                'authors' => 'Lý Quốc Bảo, Mai Phương Thảo',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Khoa học Xã hội',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Dương Thu Hằng',
                'dateDue' => '14/10/2026',
                'statusClass' => 'overdue',
                'statusBadge' => 'Quá hạn',
                'statusIcon' => 'times',
                'daysLeft' => -10,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 907,
                        'name' => 'PGS. TS. Dương Thu Hằng',
                        'email' => 'thuhang@ussh.vnu.edu.vn',
                        'phone' => '0936112255',
                        'dateDue' => '14/10/2026',
                        'dueClass' => 'overdue',
                        'dueLabel' => 'Quá hạn 10 ngày',
                        'daysLeft' => -10,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => '16/10/2026 15:00',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1025,
                'title' => 'Giải pháp bảo mật chuỗi khối cho giao dịch tài chính liên ngân hàng',
                'authors' => 'Trịnh Công Định, Hoàng Văn Vũ',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Lâm Quang Hùng',
                'dateDue' => '27/10/2026',
                'statusClass' => 'upcoming',
                'statusBadge' => 'Còn 3 ngày',
                'statusIcon' => 'clock-o',
                'daysLeft' => 3,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 908,
                        'name' => 'TS. Lâm Quang Hùng',
                        'email' => 'quanghung@uit.edu.vn',
                        'phone' => '0908123456',
                        'dateDue' => '27/10/2026',
                        'dueClass' => 'upcoming',
                        'dueLabel' => 'Còn 3 ngày',
                        'daysLeft' => 3,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1022,
                'title' => 'Khảo sát tác dụng sinh học của flavonoid chiết xuất từ hoa hòe',
                'authors' => 'Võ Thị Thanh, Đoàn Minh Quang',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Nguyễn Đình Quân',
                'dateDue' => '28/10/2026',
                'statusClass' => 'upcoming',
                'statusBadge' => 'Còn 4 ngày',
                'statusIcon' => 'clock-o',
                'daysLeft' => 4,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 909,
                        'name' => 'PGS. TS. Nguyễn Đình Quân',
                        'email' => 'dinhquan@ump.edu.vn',
                        'phone' => '0913998877',
                        'dateDue' => '28/10/2026',
                        'dueClass' => 'upcoming',
                        'dueLabel' => 'Còn 4 ngày',
                        'daysLeft' => 4,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1020,
                'title' => 'Tác động của đầu tư công đến tăng trưởng kinh tế vùng kinh tế trọng điểm',
                'authors' => 'Lê Thanh Bình, Ngô Bá Hải',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Bùi Mai Trâm',
                'dateDue' => '26/10/2026',
                'statusClass' => 'upcoming',
                'statusBadge' => 'Còn 2 ngày',
                'statusIcon' => 'clock-o',
                'daysLeft' => 2,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 910,
                        'name' => 'TS. Bùi Mai Trâm',
                        'email' => 'maitram@ueh.edu.vn',
                        'phone' => '0988554433',
                        'dateDue' => '26/10/2026',
                        'dueClass' => 'upcoming',
                        'dueLabel' => 'Còn 2 ngày',
                        'daysLeft' => 2,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1018,
                'title' => 'Ảnh hưởng của mạng xã hội đến tâm lý giới trẻ đô thị thời kỳ hậu dịch',
                'authors' => 'Chu Thị Hồng Nhung, Vũ Anh Tài',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Khoa học Xã hội',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Trần Đình Hưng',
                'dateDue' => '27/10/2026',
                'statusClass' => 'upcoming',
                'statusBadge' => 'Còn 3 ngày',
                'statusIcon' => 'clock-o',
                'daysLeft' => 3,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 911,
                        'name' => 'PGS. TS. Trần Đình Hưng',
                        'email' => 'dinhhung@vass.gov.vn',
                        'phone' => '0904332211',
                        'dateDue' => '27/10/2026',
                        'dueClass' => 'upcoming',
                        'dueLabel' => 'Còn 3 ngày',
                        'daysLeft' => 3,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1016,
                'title' => 'Thuật toán tối ưu hóa bầy đàn áp dụng trong định tuyến mạng không dây',
                'authors' => 'Hoàng Ngọc Sơn, Trần Văn Khánh',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'GS. TS. Lương Hải An',
                'dateDue' => '28/10/2026',
                'statusClass' => 'upcoming',
                'statusBadge' => 'Còn 4 ngày',
                'statusIcon' => 'clock-o',
                'daysLeft' => 4,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 912,
                        'name' => 'GS. TS. Lương Hải An',
                        'email' => 'haian@ptit.edu.vn',
                        'phone' => '0912990011',
                        'dateDue' => '28/10/2026',
                        'dueClass' => 'upcoming',
                        'dueLabel' => 'Còn 4 ngày',
                        'daysLeft' => 4,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1015,
                'title' => 'Đánh giá nguy cơ kháng kháng sinh ở bệnh nhân nội trú khoa Hồi sức tích cực',
                'authors' => 'Phạm Hồng Ánh, Đỗ Hoàng Sơn',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. BS. Nguyễn Kim Chi',
                'dateDue' => '27/10/2026',
                'statusClass' => 'upcoming',
                'statusBadge' => 'Còn 3 ngày',
                'statusIcon' => 'clock-o',
                'daysLeft' => 3,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 913,
                        'name' => 'TS. BS. Nguyễn Kim Chi',
                        'email' => 'kimchi@bachmai.gov.vn',
                        'phone' => '0983667788',
                        'dateDue' => '27/10/2026',
                        'dueClass' => 'upcoming',
                        'dueLabel' => 'Còn 3 ngày',
                        'daysLeft' => 3,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1012,
                'title' => 'Chiến lược ESG và giá trị thương hiệu của các doanh nghiệp niêm yết',
                'authors' => 'Vũ Thị Minh Hạnh, Tạ Văn Long',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Đinh Văn Toàn',
                'dateDue' => '26/10/2026',
                'statusClass' => 'upcoming',
                'statusBadge' => 'Còn 2 ngày',
                'statusIcon' => 'clock-o',
                'daysLeft' => 2,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 914,
                        'name' => 'PGS. TS. Đinh Văn Toàn',
                        'email' => 'vantoan@neu.edu.vn',
                        'phone' => '0903223344',
                        'dateDue' => '26/10/2026',
                        'dueClass' => 'upcoming',
                        'dueLabel' => 'Còn 2 ngày',
                        'daysLeft' => 2,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1010,
                'title' => 'Bảo tồn di sản kiến trúc phố cổ trước làn sóng đô thị hóa',
                'authors' => 'Trần Nhật Quang, Đinh Thị Lan',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Khoa học Xã hội',
                'workflowUrl' => '#',
                'reviewerName' => 'KTS. TS. Hoàng Việt Dũng',
                'dateDue' => '02/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 9,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 915,
                        'name' => 'KTS. TS. Hoàng Việt Dũng',
                        'email' => 'vietdung@hau.edu.vn',
                        'phone' => '0912114477',
                        'dateDue' => '02/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 9 ngày)',
                        'daysLeft' => 9,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1008,
                'title' => 'Phát hiện mã độc ẩn giấu trong tệp tin tài liệu bằng phương pháp học sâu',
                'authors' => 'Ngô Tiến Thành, Vũ Ngọc Bích',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Tạ Đình Chiến',
                'dateDue' => '04/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 11,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 916,
                        'name' => 'TS. Tạ Đình Chiến',
                        'email' => 'dinhchien@actvn.edu.vn',
                        'phone' => '0982556677',
                        'dateDue' => '04/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 11 ngày)',
                        'daysLeft' => 11,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1005,
                'title' => 'Nghiên cứu bào chế viên nén giải phóng kéo dài chứa cao chiết đinh lăng',
                'authors' => 'Lê Diệu Linh, Trần Văn Tuấn',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Trần Thu Hoài',
                'dateDue' => '05/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 12,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 917,
                        'name' => 'PGS. TS. Trần Thu Hoài',
                        'email' => 'thuhoai@hup.edu.vn',
                        'phone' => '0904889911',
                        'dateDue' => '05/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 12 ngày)',
                        'daysLeft' => 12,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1004,
                'title' => 'Chuyển đổi số trong quản lý chuỗi cung ứng logistics tại Việt Nam',
                'authors' => 'Đặng Tuấn Anh, Nguyễn Hoàng Long',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'GS. TS. Phạm Ngọc Thạch',
                'dateDue' => '06/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 13,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 918,
                        'name' => 'GS. TS. Phạm Ngọc Thạch',
                        'email' => 'ngocthach@hmu.edu.vn',
                        'phone' => '0913224466',
                        'dateDue' => '06/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 13 ngày)',
                        'daysLeft' => 13,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1002,
                'title' => 'Thực trạng và giải pháp phát triển kinh tế ban đêm tại Đà Nẵng',
                'authors' => 'Huỳnh Đức Thiện, Phan Bảo Châu',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Khoa học Xã hội',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Võ Quốc Tuấn',
                'dateDue' => '08/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 15,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 919,
                        'name' => 'TS. Võ Quốc Tuấn',
                        'email' => 'quoctuan@dlu.edu.vn',
                        'phone' => '0905778899',
                        'dateDue' => '08/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 15 ngày)',
                        'daysLeft' => 15,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 1001,
                'title' => 'Hệ thống định vị trong nhà dựa trên sóng siêu âm và cảm biến gia tốc',
                'authors' => 'Dương Văn Quý, Cao Minh Đạt',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Lê Hải Phong',
                'dateDue' => '10/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 17,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 920,
                        'name' => 'PGS. TS. Lê Hải Phong',
                        'email' => 'haiphong@dut.udn.vn',
                        'phone' => '0918334455',
                        'dateDue' => '10/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 17 ngày)',
                        'daysLeft' => 17,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 998,
                'title' => 'Tổng hợp nano bạc sinh học từ dịch chiết lá bàng và hoạt tính ức chế vi khuẩn',
                'authors' => 'Nguyễn Thị Thu Hà, Vũ Thành Trung',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'GS. TS. Ngô Kim Định',
                'dateDue' => '12/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 19,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 921,
                        'name' => 'GS. TS. Ngô Kim Định',
                        'email' => 'kimdinh@ctu.edu.vn',
                        'phone' => '0988114422',
                        'dateDue' => '12/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 19 ngày)',
                        'daysLeft' => 19,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 995,
                'title' => 'Tài chính toàn diện và đói nghèo đa chiều tại các tỉnh miền núi phía Bắc',
                'authors' => 'Hà Văn Nam, Lương Thị Tuyết',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Phan Thị Thủy Tiên',
                'dateDue' => '14/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 21,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 922,
                        'name' => 'TS. Phan Thị Thủy Tiên',
                        'email' => 'thuytien@tueba.edu.vn',
                        'phone' => '0935667788',
                        'dateDue' => '14/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 21 ngày)',
                        'daysLeft' => 21,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 992,
                'title' => 'Tục ngữ ca dao người Thái vùng Tây Bắc nhìn từ góc độ biểu tượng học',
                'authors' => 'Lò Văn Chiến, Điêu Thị Nga',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Khoa học Xã hội',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Cầm Thị Mai',
                'dateDue' => '15/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 22,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 923,
                        'name' => 'PGS. TS. Cầm Thị Mai',
                        'email' => 'cammai@tbu.edu.vn',
                        'phone' => '0912447788',
                        'dateDue' => '15/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 22 ngày)',
                        'daysLeft' => 22,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 990,
                'title' => 'Ứng dụng thị giác máy tính nhận dạng biển số xe trong điều kiện ánh sáng yếu',
                'authors' => 'Tô Quang Minh, Bùi Thanh Tùng',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Nguyễn Viết Hưng',
                'dateDue' => '16/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 23,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 924,
                        'name' => 'TS. Nguyễn Viết Hưng',
                        'email' => 'viethung@hust.edu.vn',
                        'phone' => '0903445588',
                        'dateDue' => '16/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 23 ngày)',
                        'daysLeft' => 23,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 988,
                'title' => 'Đánh giá độc tính cấp và bán trường diễn của bài thuốc nam điều trị viêm loét dạ dày',
                'authors' => 'Chu Văn Toàn, Nguyễn Thị Diệu Linh',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Đoàn Văn Bách',
                'dateDue' => '18/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 25,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 925,
                        'name' => 'PGS. TS. Đoàn Văn Bách',
                        'email' => 'vanbach@vimm.org.vn',
                        'phone' => '0989223311',
                        'dateDue' => '18/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 25 ngày)',
                        'daysLeft' => 25,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 985,
                'title' => 'Mối quan hệ giữa sự hài lòng công việc và ý định chuyển việc của nhân viên IT',
                'authors' => 'Lâm Vĩnh Khang, Đinh Bảo Ngọc',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Đào Thị Bích Nga',
                'dateDue' => '19/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 26,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 926,
                        'name' => 'TS. Đào Thị Bích Nga',
                        'email' => 'bichnga@sgu.edu.vn',
                        'phone' => '0908776633',
                        'dateDue' => '19/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 26 ngày)',
                        'daysLeft' => 26,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 982,
                'title' => 'Truyền thông số và sự biến đổi trong hành vi đọc sách của thiếu nhi',
                'authors' => 'Nguyễn Minh Thư, Đỗ Gia Huy',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Khoa học Xã hội',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Trần Hải Yến',
                'dateDue' => '20/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 27,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 927,
                        'name' => 'PGS. TS. Trần Hải Yến',
                        'email' => 'haiyen@hnue.edu.vn',
                        'phone' => '0915664422',
                        'dateDue' => '20/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 27 ngày)',
                        'daysLeft' => 27,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 980,
                'title' => 'Kiến trúc vi dịch vụ (Microservices) trong phát triển cổng thanh toán điện tử',
                'authors' => 'Lê Quốc Đạt, Hoàng Văn Tiến',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Vũ Anh Dũng',
                'dateDue' => '21/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 28,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 928,
                        'name' => 'TS. Vũ Anh Dũng',
                        'email' => 'anhdung@vnu.edu.vn',
                        'phone' => '0932551188',
                        'dateDue' => '21/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 28 ngày)',
                        'daysLeft' => 28,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 978,
                'title' => 'Nghiên cứu tác dụng hạ đường huyết của cao chiết lá dâu tằm trên chuột đái tháo đường',
                'authors' => 'Phạm Thùy Dung, Ngô Đức Trọng',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Nguyễn Văn Lượng',
                'dateDue' => '22/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 29,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 929,
                        'name' => 'PGS. TS. Nguyễn Văn Lượng',
                        'email' => 'vanluong@vmu.edu.vn',
                        'phone' => '0904229977',
                        'dateDue' => '22/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 29 ngày)',
                        'daysLeft' => 29,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 975,
                'title' => 'Tác động của thương mại điện tử qua biên giới đối với xuất khẩu nông sản',
                'authors' => 'Võ Đình Tuấn, Hoàng Thúy Vy',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'GS. TS. Đỗ Quốc Sam',
                'dateDue' => '23/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 30,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 930,
                        'name' => 'GS. TS. Đỗ Quốc Sam',
                        'email' => 'quocsam@dav.edu.vn',
                        'phone' => '0913554488',
                        'dateDue' => '23/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 30 ngày)',
                        'daysLeft' => 30,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 972,
                'title' => 'Chính sách an sinh xã hội đối với người lao động di cư tự do tại TP. Hồ Chí Minh',
                'authors' => 'Đào Văn Hữu, Phạm Thu Thảo',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Khoa học Xã hội',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. Lê Thị Quỳnh Nga',
                'dateDue' => '24/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 31,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 931,
                        'name' => 'TS. Lê Thị Quỳnh Nga',
                        'email' => 'quynhnga@hcmussh.edu.vn',
                        'phone' => '0908332277',
                        'dateDue' => '24/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 31 ngày)',
                        'daysLeft' => 31,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 970,
                'title' => 'Xây dựng ontology tiếng Việt cho lĩnh vực y tế phục vụ truy vấn ngữ nghĩa',
                'authors' => 'Phan Thanh Hải, Bùi Hồng Nhung',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Công nghệ thông tin',
                'workflowUrl' => '#',
                'reviewerName' => 'PGS. TS. Huỳnh Văn Nam',
                'dateDue' => '25/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 32,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 932,
                        'name' => 'PGS. TS. Huỳnh Văn Nam',
                        'email' => 'vannam@jaist.ac.jp',
                        'phone' => '0918556622',
                        'dateDue' => '25/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 32 ngày)',
                        'daysLeft' => 32,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 968,
                'title' => 'Nghiên cứu tỷ lệ đột biến gen EGFR ở bệnh nhân ung thư phổi không tế bào nhỏ',
                'authors' => 'Trần Văn Kiên, Lê Thị Ngọc Mai',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Y - Dược',
                'workflowUrl' => '#',
                'reviewerName' => 'TS. BS. Vũ Quốc Đạt',
                'dateDue' => '26/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 33,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 933,
                        'name' => 'TS. BS. Vũ Quốc Đạt',
                        'email' => 'quocdat@hmu.edu.vn',
                        'phone' => '0983115599',
                        'dateDue' => '26/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 33 ngày)',
                        'daysLeft' => 33,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
            [
                'id' => 965,
                'title' => 'Ảnh hưởng của quản trị công ty đến hiệu quả tài chính các ngân hàng thương mại',
                'authors' => 'Nguyễn Đình Trọng, Trần Văn Hưng',
                'stageName' => 'Phản biện ngoài',
                'section' => 'Kinh tế học',
                'workflowUrl' => '#',
                'reviewerName' => 'GS. TS. Mai Văn Nam',
                'dateDue' => '27/11/2026',
                'statusClass' => 'on_time',
                'statusBadge' => 'Đúng hạn',
                'statusIcon' => 'check',
                'daysLeft' => 34,
                'canSend' => true,
                'sendableCount' => 1,
                'reviewers' => [
                    [
                        'reviewId' => 934,
                        'name' => 'GS. TS. Mai Văn Nam',
                        'email' => 'vannam@hueuni.edu.vn',
                        'phone' => '0905112288',
                        'dateDue' => '27/11/2026',
                        'dueClass' => 'on_time',
                        'dueLabel' => 'Đúng hạn (Còn 34 ngày)',
                        'daysLeft' => 34,
                        'canSend' => true,
                        'hasValidPhone' => true,
                        'dateReminded' => 'Chưa nhắc',
                        'responseStatus' => 'Đã chấp nhận',
                    ]
                ]
            ],
        ];
    }

    /**
     * Dữ liệu tiến độ theo chuyên ngành thực tế từ cơ sở dữ liệu.
     */
    public static function getSectionStats(int $contextId): array
    {
        if ($contextId <= 0) {
            return [];
        }

        try {
            $statusPublished = defined('STATUS_PUBLISHED') ? STATUS_PUBLISHED : 3;

            $sections = Capsule::table('sections as sec')
                ->leftJoin('section_settings as ss', function ($join) {
                    $join->on('ss.section_id', '=', 'sec.section_id')
                         ->where('ss.setting_name', '=', 'title');
                })
                ->where('sec.journal_id', $contextId)
                ->select(['sec.section_id', Capsule::raw('COALESCE(ss.setting_value, CONCAT("Chuyên mục #", sec.section_id)) as name')])
                ->get();

            $stats = [];
            foreach ($sections as $sec) {
                $secId = (int) $sec->section_id;
                $total = Capsule::table('submissions as s')
                    ->join('publications as p', 's.current_publication_id', '=', 'p.publication_id')
                    ->where('s.context_id', $contextId)
                    ->where('p.section_id', $secId)
                    ->count();

                if ($total === 0) continue;

                $completed = Capsule::table('submissions as s')
                    ->join('publications as p', 's.current_publication_id', '=', 'p.publication_id')
                    ->where('s.context_id', $contextId)
                    ->where('p.section_id', $secId)
                    ->where('s.status', $statusPublished)
                    ->count();

                $stats[] = [
                    'name' => (string) $sec->name,
                    'total' => $total,
                    'completed' => $completed,
                    'percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
                ];
            }

            if (!empty($stats)) {
                $topStats = array_slice($stats, 0, 4);
                $maxVal = 0;
                foreach ($topStats as $ts) {
                    if ($ts['total'] > $maxVal) {
                        $maxVal = $ts['total'];
                    }
                }
                if ($maxVal <= 0) {
                    $maxVal = 20;
                }

                foreach ($topStats as &$st) {
                    $st['barHCompleted'] = max(4, (int) round(($st['completed'] / $maxVal) * 160));
                    $st['barHTotal'] = max(6, (int) round(($st['total'] / $maxVal) * 160));
                }
                unset($st);

                return $topStats;
            }
        } catch (\Throwable $e) {
        }

        return [];
    }

    /**
     * Hoạt động gần đây (Live Activity) từ ActivityLogger thực tế.
     */
    public static function getRecentLiveActivities(int $contextId): array
    {
        $activities = [];

        // Lấy từ nhật ký ActivityLogger thực tế
        try {
            $recent = ActivityLogger::getRecentEntries(5, null, $contextId);
            foreach ($recent as $item) {
                $status = strtolower((string) ($item['status'] ?? ''));
                $color = ($status === 'success' || !empty($item['success'])) ? 'green' : (($status === 'pending' || $status === 'queued') ? 'blue' : 'red');
                $activities[] = [
                    'time' => self::formatRelativeTime($item['timestamp'] ?? ''),
                    'text' => ($item['message'] ?? ($item['action'] ?? '')),
                    'color' => $color,
                    'icon' => 'bell',
                ];
            }
        } catch (\Throwable $e) {
        }

        return $activities;
    }

    private static function formatRelativeTime($timestamp): string
    {
        $time = is_numeric($timestamp) ? (int) $timestamp : strtotime((string) $timestamp);
        if (!$time) return 'Vừa xong';
        $diff = time() - $time;
        if ($diff < 60) return 'Vừa xong';
        if ($diff < 3600) return round($diff / 60) . ' phút trước';
        if ($diff < 86400) return round($diff / 3600) . ' giờ trước';
        return round($diff / 86400) . ' ngày trước';
    }
}

