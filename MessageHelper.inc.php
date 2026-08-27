<?php

/**
 * MessageHelper — Các hàm hỗ trợ dùng chung cho plugin Zalo Notification.
 * Tương thích OJS 3.3
 */
class MessageHelper
{
    /**
     * Thay thế các placeholder {key} trong template bằng giá trị tương ứng.
     */
    public static function buildMessage(string $template, array $data): string
    {
        $message = $template;
        $optionalNavigationKeys = [
            'submissionId', 'stageId', 'reviewId', 'publicationId',
            'workflowUrl', 'authorUrl', 'reviewerUrl', 'publicUrl',
        ];
        foreach ($data as $key => $value) {
            if (in_array($key, $optionalNavigationKeys, true) && trim((string) $value) === '') {
                $pattern = '/^.*\{' . preg_quote((string) $key, '/') . '\}.*(?:\R|$)/mu';
                $cleaned = preg_replace($pattern, '', $message);
                if ($cleaned !== null) {
                    $message = $cleaned;
                }
                continue;
            }
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        return trim($message);
    }

    /**
     * Build IDs and deep links used by notification templates.
     *
     * The links intentionally rely on OJS authentication and authorization.
     * Reviewer access keys must never be embedded in Zalo messages.
     */
    public static function getNavigationData($submission, int $reviewId = 0, ?int $stageId = null): array
    {
        $data = [
            'submissionId' => '',
            'stageId' => '',
            'reviewId' => $reviewId > 0 ? (string) $reviewId : '',
            'publicationId' => '',
            'workflowUrl' => '',
            'authorUrl' => '',
            'reviewerUrl' => '',
            'publicUrl' => '',
        ];

        if (!$submission || !method_exists($submission, 'getId')) {
            return $data;
        }

        try {
            $submissionId = (int) $submission->getId();
            $stageId = $stageId ?: (method_exists($submission, 'getStageId') ? (int) $submission->getStageId() : 0);
            $publication = method_exists($submission, 'getCurrentPublication') ? $submission->getCurrentPublication() : null;

            $data['submissionId'] = (string) $submissionId;
            $data['stageId'] = $stageId > 0 ? (string) $stageId : '';
            if ($publication && method_exists($publication, 'getId')) {
                $data['publicationId'] = (string) ((int) $publication->getId());
            }

            $application = \Application::get();
            $request = $application->getRequest();
            $dispatcher = $application->getDispatcher();
            if (!$request || !$dispatcher) {
                return $data;
            }

            $contextPath = null;
            $contextId = method_exists($submission, 'getContextId')
                ? (int) $submission->getContextId()
                : (int) $submission->getData('contextId');
            $context = $request->getContext();
            if (!$context || (int) $context->getId() !== $contextId) {
                $contextDao = \DAORegistry::getDAO('JournalDAO');
                $context = $contextDao ? $contextDao->getById($contextId) : null;
            }
            if ($context && method_exists($context, 'getPath')) {
                $contextPath = $context->getPath();
            }

            if ($stageId > 0) {
                $data['workflowUrl'] = $dispatcher->url(
                    $request,
                    ROUTE_PAGE,
                    $contextPath,
                    'workflow',
                    'index',
                    [$submissionId, $stageId]
                );
            } else {
                $data['workflowUrl'] = $dispatcher->url(
                    $request,
                    ROUTE_PAGE,
                    $contextPath,
                    'workflow',
                    'access',
                    $submissionId
                );
            }

            $data['authorUrl'] = $dispatcher->url(
                $request,
                ROUTE_PAGE,
                $contextPath,
                'authorDashboard',
                'submission',
                $submissionId
            );
            $data['reviewerUrl'] = $dispatcher->url(
                $request,
                ROUTE_PAGE,
                $contextPath,
                'reviewer',
                'submission',
                null,
                ['submissionId' => $submissionId]
            );

            if (method_exists($submission, 'getBestId')) {
                $bestId = $submission->getBestId();
                if ($bestId) {
                    $data['publicUrl'] = $dispatcher->url(
                        $request,
                        ROUTE_PAGE,
                        $contextPath,
                        'article',
                        'view',
                        $bestId
                    );
                }
            }
        } catch (\Throwable $e) {
            self::writeDebug('getNavigationData lỗi: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Trả về tên Stage dạng tiếng Việt theo stageId
     */
    public static function getStageName(int $stageId): string
    {
        $map = [
            1 => 'Submission (Nộp bài)',
            2 => 'Internal Review (Phản biện nội bộ)',
            3 => 'Phản biện',
            4 => 'Copyediting (Biên tập)',
            5 => 'Production (Xuất bản)',
        ];
        return $map[$stageId] ?? "Stage #{$stageId}";
    }

    /**
     * Trả về mô tả quyết định dạng tiếng Việt theo decision constant của OJS 3.3.
     */
    public static function getDecisionName(int $decisionType): string
    {
        $map = [
            SUBMISSION_EDITOR_DECISION_ACCEPT           => '✅ Chấp nhận bài (Accept)',
            SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS=> '✍️ Yêu cầu chỉnh sửa',
            SUBMISSION_EDITOR_DECISION_RESUBMIT         => '🔁 Yêu cầu nộp lại để mở vòng phản biện mới',
            SUBMISSION_EDITOR_DECISION_DECLINE          => '❌ Từ chối bài (Decline)',
            SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION=> '🚀 Chuyển sang Xuất bản (Send to Production)',
            SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW  => '🔄 Chuyển sang Phản biện',
            SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE  => '❌ Từ chối ngay từ đầu (Initial Decline)',
            SUBMISSION_EDITOR_RECOMMEND_ACCEPT => '👍 Phản biện đề xuất: Chấp nhận',
            SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS => '📝 Phản biện đề xuất: Yêu cầu sửa',
            SUBMISSION_EDITOR_RECOMMEND_RESUBMIT => '📨 Phản biện đề xuất: Nộp lại',
            SUBMISSION_EDITOR_RECOMMEND_DECLINE => '👎 Phản biện đề xuất: Từ chối',
            SUBMISSION_EDITOR_DECISION_NEW_ROUND => '🔄 Mở vòng Phản biện mới',
            SUBMISSION_EDITOR_DECISION_REVERT_DECLINE   => '↩️ Hủy quyết định Từ chối',
        ];
        return $map[$decisionType] ?? "Quyết định (mã: {$decisionType})";
    }

    /**
     * Lấy tên đầy đủ các tác giả từ Submission hoặc Publication (OJS 3.3)
     */
    public static function getAuthorsString($submissionOrPublication): string
    {
        if (!$submissionOrPublication) {
            return 'Không rõ';
        }

        try {
            if (method_exists($submissionOrPublication, 'getAuthorString')) {
                $authorString = $submissionOrPublication->getAuthorString([]);
                $authorString = self::cleanDisplayName($authorString);
                if ($authorString !== '') {
                    return $authorString;
                }
            }
            if (method_exists($submissionOrPublication, 'getShortAuthorString')) {
                $shortAuthor = $submissionOrPublication->getShortAuthorString();
                $shortAuthor = self::cleanDisplayName($shortAuthor);
                if ($shortAuthor !== '') {
                    return $shortAuthor;
                }
            }
        } catch (\Throwable $e) {
            self::writeDebug("getAuthorsString lỗi: " . $e->getMessage());
        }

        $publication = $submissionOrPublication;
        if (method_exists($submissionOrPublication, 'getCurrentPublication')) {
            try {
                $publication = $submissionOrPublication->getCurrentPublication();
            } catch (\Throwable $e) {
                self::writeDebug("getAuthorsString getCurrentPublication lỗi: " . $e->getMessage());
            }
        }

        $publicationId = 0;
        if ($publication && method_exists($publication, 'getId')) {
            $publicationId = (int) $publication->getId();
        }

        if ($publicationId > 0) {
            $authors = self::getAuthorsFromPublicationId($publicationId);
            if ($authors !== '') {
                return $authors;
            }
        }

        return 'Không rõ';
    }

    private static function getAuthorsFromPublicationId(int $publicationId): string
    {
        $authors = [];

        try {
            $result = \DAORegistry::getDAO('UserDAO')->retrieve(
                "SELECT a.author_id,
                        a.email,
                        MAX(CASE WHEN s.setting_name = 'preferredPublicName' THEN s.setting_value END) AS preferred_name,
                        MAX(CASE WHEN s.setting_name = 'givenName' THEN s.setting_value END) AS given_name,
                        MAX(CASE WHEN s.setting_name = 'familyName' THEN s.setting_value END) AS family_name
                   FROM authors a
              LEFT JOIN author_settings s ON s.author_id = a.author_id
                  WHERE a.publication_id = ?
               GROUP BY a.author_id, a.email, a.seq
               ORDER BY a.seq ASC, a.author_id ASC",
                [$publicationId]
            );

            if (is_iterable($result)) {
                foreach ($result as $row) {
                    $row = is_array($row) ? $row : (array) $row;
                    $name = self::buildAuthorNameFromRow($row);
                    if ($name !== '') {
                        $authors[] = $name;
                    }
                }
            } else {
                while (is_object($result) && property_exists($result, 'EOF') && !$result->EOF) {
                    $row = is_array($result->fields) ? $result->fields : (array) $result->fields;
                    $name = self::buildAuthorNameFromRow($row);
                    if ($name !== '') {
                        $authors[] = $name;
                    }

                    $result->MoveNext();
                }
            }

            if (is_object($result) && method_exists($result, 'Close')) {
                $result->Close();
            }
        } catch (\Throwable $e) {
            self::writeDebug("getAuthorsFromPublicationId lỗi: " . $e->getMessage());
        }

        $authors = array_values(array_unique(array_filter($authors)));
        return implode(', ', $authors);
    }

    private static function buildAuthorNameFromRow(array $row): string
    {
        $preferredName = self::cleanDisplayName($row['preferred_name'] ?? '');
        $givenName = self::cleanDisplayName($row['given_name'] ?? '');
        $familyName = self::cleanDisplayName($row['family_name'] ?? '');
        $email = self::cleanDisplayName($row['email'] ?? '');

        $name = $preferredName;
        if ($name === '') {
            $name = trim($givenName . ' ' . $familyName);
        }
        if ($name === '') {
            $name = $email;
        }

        return $name;
    }

    private static function cleanDisplayName($name): string
    {
        $name = trim(strip_tags((string) $name));
        $cleaned = preg_replace('/\s+/u', ' ', $name);
        if ($cleaned === null) {
            $cleaned = preg_replace('/\s+/', ' ', $name);
        }
        $name = $cleaned ?? $name;
        return trim($name);
    }

    /**
     * Lấy tên user đang đăng nhập (editor/admin thực hiện hành động).
     * Trả về 'System' nếu không xác định được.
     */
    public static function getCurrentUserName(): string
    {
        try {
            $request = \Application::get()->getRequest();
            if ($request) {
                $user = $request->getUser();
                if ($user) {
                    return $user->getFullName();
                }
            }
        } catch (\Throwable $e) {
            // Bỏ qua lỗi
        }
        return 'System';
    }

    /**
     * Ghi log debug nhanh vào debug.txt
     */
    private static function writeDebug(string $message): void
    {
        ZaloNotificationPlugin::writeSecureDebug($message, 'MessageHelper');
    }
}
