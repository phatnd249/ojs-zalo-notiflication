<?php

class ZaloTemplateDefaults
{
    public static function get(): array
    {
        $templates = [];
        $templates['editor'] = [
            'submission' => "▣ BÀI NỘP MỚI\n\n› Bài: {title}\n› Tác giả: {author}\n{abstract_if_any}› Trạng thái: Đã hoàn tất nộp bài\n\n→ Vui lòng đăng nhập OJS để phân công xử lý.\n⏱ Thời gian: {timestamp}",
            'decision' => "▣ QUYẾT ĐỊNH BIÊN TẬP\n\n› Bài: {title}\n› Tác giả: {author}\n› Giai đoạn: {stageName}\n› Quyết định: {decisionDesc}\n› Biên tập viên: {editorName}\n\n⏱ Thời gian: {timestamp}",
            'initial_decline' => "Bạn không có thông báo cho sự kiện này.",
            'review_started' => "Bạn không có thông báo cho sự kiện này.",
            'publish' => "✓ BÀI BÁO ĐÃ XUẤT BẢN\n\n› Bài: {title}\n› Tác giả: {author}\n{issueString_if_any}› Ngày xuất bản: {datePublished}\n\n⏱ Thời gian: {timestamp}",
            'unpublish' => "! HỦY XUẤT BẢN\n\n› Bài: {title}\n› Tác giả: {author}\n\n→ Vui lòng kiểm tra lại thông tin xuất bản trên OJS.\n⏱ Thời gian: {timestamp}",
            'reminder' => "! NHẮC HẠN PHẢN BIỆN\n\n› Bài: {title}\n› Phản biện viên: {reviewerName}\n› Hạn chót: {deadline}\n› Tình trạng: {daysLeft}\n› Vòng phản biện: {round}\n\n⏱ Thời gian: {timestamp}",
            'review_request' => "▣ ĐÃ MỜI PHẢN BIỆN\n\n› Bài: {title}\n› Tác giả: {author}\n› Phản biện viên: {reviewerName}\n› Hạn nộp đánh giá: {deadline}\n› Hạn phản hồi: {responseDeadline}\n› Vòng phản biện: {round}\n\n⏱ Thời gian: {timestamp}",
            'review_response' => "▣ PHẢN HỒI LỜI MỜI PHẢN BIỆN\n\n› Bài: {title}\n› Tác giả: {author}\n› Phản biện viên: {reviewerName}\n› Trạng thái: {responseStatus}\n› Hạn nộp đánh giá: {deadline}\n\n⏱ Thời gian: {timestamp}",
            'review_completed' => "✓ PHẢN BIỆN ĐÃ NỘP ĐÁNH GIÁ\n\n› Bài: {title}\n› Phản biện viên: {reviewerName}\n› Đề xuất: {recommendationDesc}\n\n→ Vui lòng đăng nhập OJS để xem chi tiết.\n⏱ Thời gian: {timestamp}",
            'author_revision' => "▣ TÁC GIẢ ĐÃ NỘP BẢN CHỈNH SỬA\n\n› Bài: {title}\n› Tác giả: {author}\n› Giai đoạn: {stageName}\n› Vòng phản biện: {round}\n\n→ Vui lòng đăng nhập OJS để kiểm tra bản chỉnh sửa.\n⏱ Thời gian: {timestamp}",
            'editor_assignment' => "▣ PHÂN CÔNG BIÊN TẬP\n\n› Xin chào: {editorName}\n› Bạn được phân công xử lý bài: {title}\n› Tác giả: {author}\n› Vai trò: {editorRole}\n› Giai đoạn: {stageName}\n› Người phân công: {assignedBy}\n\n⏱ Thời gian: {timestamp}",
        ];

        $templates['reviewer'] = [
            'submission' => "Bạn không có thông báo cho sự kiện này.",
            'decision' => "✓ BÀI BÁO ĐÃ CẬP NHẬT TRẠNG THÁI\n\n› Bài: {title}\n\nCảm ơn bạn đã tham gia đánh giá bài báo này.\n⏱ Thời gian: {timestamp}",
            'initial_decline' => "Bạn không có thông báo cho sự kiện này.",
            'review_started' => "Bạn không có thông báo cho sự kiện này.",
            'publish' => "Bạn không có thông báo cho sự kiện này.",
            'unpublish' => "Bạn không có thông báo cho sự kiện này.",
            'reminder' => "! NHẮC HẠN PHẢN BIỆN\n\n› Bài: {title}\n› Hạn chót: {deadline}\n› Tình trạng: {daysLeft}\n› Vòng phản biện: {round}\n\n→ Vui lòng đăng nhập OJS để hoàn tất đánh giá.\n⏱ Thời gian: {timestamp}",
            'review_request' => "▣ LỜI MỜI PHẢN BIỆN\n\n› Bài: {title}\n› Hạn nộp đánh giá: {deadline}\n› Hạn phản hồi: {responseDeadline}\n› Vòng phản biện: {round}\n\n→ Vui lòng đăng nhập OJS để phản hồi lời mời.\n⏱ Thời gian: {timestamp}",
            'review_response' => "Bạn không có thông báo cho sự kiện này.",
            'review_completed' => "Bạn không có thông báo cho sự kiện này.",
            'author_revision' => "Bạn không có thông báo cho sự kiện này.",
            'editor_assignment' => "Bạn không có thông báo cho sự kiện này.",
        ];

        $templates['author'] = [
            'submission' => "✓ NỘP BÀI THÀNH CÔNG\n\n› Bài: {title}\n› Tác giả: {author}\n\nBài viết đã được ghi nhận trên hệ thống. Ban biên tập sẽ sớm phân công xử lý.\n⏱ Thời gian: {timestamp}",
            'decision' => "▣ BÀI BÁO ĐÃ CÓ KẾT QUẢ PHẢN BIỆN\n\n› Bài: {title}\n› Yêu cầu: {decisionDesc}\n\n→ Đăng nhập OJS để xem nội dung và thực hiện yêu cầu: {authorUrl}\n⏱ Thời gian: {timestamp}",
            'initial_decline' => "▣ QUYẾT ĐỊNH BIÊN TẬP BAN ĐẦU\n\n› Bài: {title}\n› Quyết định: {decisionDesc}\n\n→ Đăng nhập OJS để xem thông tin chi tiết: {authorUrl}\n⏱ Thời gian: {timestamp}",
            'review_started' => "▣ BÀI BÁO ĐÃ CHUYỂN SANG PHẢN BIỆN\n\n› Bài: {title}\n› Trạng thái: Đã chuyển sang {stageName}\n\nBan biên tập đã bắt đầu quy trình phản biện bài viết.\n\n→ Theo dõi bài trên OJS: {authorUrl}\n⏱ Thời gian: {timestamp}",
            'publish' => "✓ BÀI BÁO ĐÃ XUẤT BẢN\n\n› Bài: {title}\n{issueString_if_any}Chúc mừng tác giả.\n⏱ Thời gian: {timestamp}",
            'unpublish' => "! BÀI BÁO ĐÃ BỊ HỦY XUẤT BẢN\n\n› Bài: {title}\n\n→ Vui lòng liên hệ Ban biên tập để biết thêm thông tin.\n⏱ Thời gian: {timestamp}",
            'reminder' => "Bạn không có thông báo cho sự kiện này.",
            'review_request' => "Bạn không có thông báo cho sự kiện này.",
            'review_response' => "Bạn không có thông báo cho sự kiện này.",
            'review_completed' => "Bạn không có thông báo cho sự kiện này.",
            'author_revision' => "Bạn không có thông báo cho sự kiện này.",
            'editor_assignment' => "Bạn không có thông báo cho sự kiện này.",
        ];

        return self::ensureNavigationDetails($templates);
    }

    public static function ensureNavigationDetails(array $templates): array
    {
        foreach (['editor', 'reviewer', 'author'] as $role) {
            if (empty($templates[$role]) || !is_array($templates[$role])) {
                continue;
            }

            foreach ($templates[$role] as $event => $template) {
                $template = (string) $template;
                if ($template === '' || strpos($template, 'Bạn không có thông báo') !== false) {
                    continue;
                }

                $template = str_replace('Mã bài:', 'ID bài:', $template);
                $templates[$role][$event] = $template;

                $details = [];
                if (strpos($template, '{submissionId}') === false) {
                    $details[] = '› ID bài: {submissionId}';
                }
                if ($role === 'editor') {
                    if (strpos($template, '{stageId}') === false) {
                        $details[] = '› Mã giai đoạn: {stageId}';
                    }
                    if (strpos($template, '{workflowUrl}') === false) {
                        $details[] = '→ Mở đúng giai đoạn: {workflowUrl}';
                    }
                } elseif ($role === 'reviewer') {
                    if (in_array($event, ['reminder', 'review_request'], true) && strpos($template, '{reviewId}') === false) {
                        $details[] = '› Mã phản biện: {reviewId}';
                    }
                    if (strpos($template, '{reviewerUrl}') === false) {
                        $details[] = '→ Mở nhiệm vụ phản biện: {reviewerUrl}';
                    }
                } else {
                    if ($event === 'decision' && strpos($template, '{decisionDesc}') === false) {
                        $details[] = '› Yêu cầu: {decisionDesc}';
                    }
                    $urlVariable = $event === 'publish' ? '{publicUrl}' : '{authorUrl}';
                    if (strpos($template, $urlVariable) === false) {
                        $details[] = $event === 'publish'
                            ? '→ Xem bài đã xuất bản: {publicUrl}'
                            : '→ Mở bài trên OJS: {authorUrl}';
                    }
                }

                if ($details) {
                    $templates[$role][$event] = rtrim($template) . "\n\n" . implode("\n", $details);
                }
            }
        }

        return $templates;
    }
}
