<?php

require_once(__DIR__ . '/services/gateway/ZaloGatewayResponse.inc.php');
require_once(__DIR__ . '/services/phone/ZaloPhoneNormalizer.inc.php');
require_once(__DIR__ . '/services/security/RateLimiter.inc.php');

/**
 * ZaloApiClient — Gửi tin nhắn Zalo qua API.
 *
 * Hỗ trợ 2 chế độ:
 *   1. sendForEvent() — Gửi theo loại sự kiện, chỉ gửi cho nhóm đã đăng ký (MỚI)
 *   2. send()         — Gửi với thông tin hardcoded (DEPRECATED, giữ cho tương thích ngược)
 */
class ZaloApiClient
{
    public static function normalizePhoneNumber(string $phone): string
    {
        return ZaloPhoneNormalizer::normalize($phone);
    }

    public static function getPhonesForEvent(string $eventType, array $recipientGroups): array
    {
        return ZaloPhoneNormalizer::getPhonesForEvent($eventType, $recipientGroups);
    }

    /**
     * Gửi thông báo Zalo theo loại sự kiện.
     * Chỉ gửi cho các nhóm đã đăng ký nhận sự kiện này.
     *
     * @param string $eventType       Loại sự kiện: SUBMISSION, DECISION, PUBLISH, UNPUBLISH, REVIEW_REMINDER
     * @param string $messageText     Nội dung tin nhắn
     * @param string $botId           Bot ID từ cấu hình plugin
     * @param string $apiKey          API Key từ cấu hình plugin
     * @param array  $recipientGroups Mảng các nhóm nhận thông báo (từ plugin settings)
     * @return string Trạng thái gửi
     */
    public static function sendForEvent(
        string $eventType,
        string $messageText,
        string $botId,
        string $apiKey,
        array $recipientGroups,
        int $contextId = 0
    ): string
    {
        // 1. Kiểm tra cấu hình API
        if (empty($botId) || empty($apiKey)) {
            ZaloNotificationPlugin::writeSecureDebug("sendForEvent [{$eventType}]: Chưa cấu hình Bot ID hoặc API Key.", 'ZaloApiClient');
            return "Thất bại (Chưa cấu hình API)";
        }

        // 2. Lọc nhóm theo event type, gom số điện thoại
        $phoneNumbers = self::getPhonesForEvent($eventType, $recipientGroups);
        $matchedGroups = [];

        $acceptedEvents = ZaloPhoneNormalizer::getAcceptedEvents($eventType);

        foreach ($recipientGroups as $group) {
            $events = isset($group['events']) && is_array($group['events']) ? $group['events'] : [];
            if (empty(array_intersect($acceptedEvents, $events))) {
                continue;
            }

            $matchedGroups[] = $group['name'] ?? 'N/A';
        }

        ZaloNotificationPlugin::writeSecureDebug(
            "sendForEvent [{$eventType}]: groups=" . count($matchedGroups) . ' | recipients=' . count($phoneNumbers),
            'ZaloApiClient'
        );

        // 3. Kiểm tra có số điện thoại nào không
        if (empty($phoneNumbers)) {
            ZaloNotificationPlugin::writeSecureDebug("sendForEvent [{$eventType}]: Không có người nhận, bỏ qua.", 'ZaloApiClient');
            return "Bỏ qua (Không có người nhận cho {$eventType})";
        }

        // 4. Gửi trực tiếp; không phụ thuộc Acron/cron hoặc worker nền.
        return self::sendToPhones($messageText, $botId, $apiKey, $phoneNumbers, $eventType, $contextId);
    }

    /**
     * Gửi tin nhắn đến danh sách số điện thoại cụ thể.
     * Đây là hàm gửi cấp thấp, được dùng bởi cả sendForEvent() và send().
     *
     * @param string $messageText  Nội dung tin nhắn
     * @param string $botId        Bot ID
     * @param string $apiKey       API Key
     * @param array  $phoneNumbers Mảng số điện thoại (đã clean)
     * @return string Trạng thái gửi
     */
    public static function sendToPhones(
        string $messageText,
        string $botId,
        string $apiKey,
        array $phoneNumbers,
        string $eventType = 'DIRECT',
        int $contextId = 0
    ): string
    {
        if (trim($botId) === '' || trim($apiKey) === '') {
            return 'Thất bại (Chưa cấu hình API)';
        }

        $normalizedPhones = [];
        foreach ($phoneNumbers as $phone) {
            $normalized = ZaloPhoneNormalizer::normalize((string) $phone);
            if ($normalized !== '') {
                $normalizedPhones[] = $normalized;
            }
        }
        $normalizedPhones = array_values(array_unique($normalizedPhones));
        if (empty($normalizedPhones)) {
            return 'Thất bại (Thiếu số điện thoại)';
        }

        $contextId = self::resolveOutboxContextId($contextId);

        // Chế độ Queue-First: Đưa thẳng vào hàng đợi Outbox để thao tác chuyển giai đoạn / nộp bài tức thì (không làm chậm hệ thống)
        if (self::isQueueFirstEnabled($contextId) && $contextId > 0 && class_exists('ZaloOutboxRepository')) {
            return self::enqueueDirect($contextId, $eventType, $messageText, $normalizedPhones);
        }

        // Luôn thử gửi trực tiếp trước. Worker gọi sendToPhonesNow() để không tự xếp hàng lặp.
        $directStatus = self::sendToPhonesNow($messageText, $botId, $apiKey, $normalizedPhones);
        if ($directStatus === 'Thành công') {
            return $directStatus;
        }

        // Khi gửi trực tiếp gặp lỗi: đưa vào hàng chờ (outbox queue) để theo dõi và retry
        if ($contextId <= 0 || !class_exists('ZaloOutboxRepository')) {
            ZaloNotificationPlugin::writeSecureDebug(
                "sendToPhones [{$eventType}]: Gửi trực tiếp lỗi nhưng không xác định được context để xếp hàng retry.",
                'ZaloApiClient'
            );
            return $directStatus . '; không thể xếp hàng retry';
        }

        $queueStatus = ZaloOutboxRepository::enqueue(
            $contextId,
            $eventType,
            $messageText,
            $normalizedPhones,
            $directStatus
        );
        if (strpos($queueStatus, 'Đã xếp hàng') === 0) {
            ZaloNotificationPlugin::writeSecureDebug(
                "sendToPhones [{$eventType}]: Gửi trực tiếp lỗi; {$queueStatus} để retry, context={$contextId}.",
                'ZaloApiClient'
            );
            return $queueStatus . ' retry (' . $directStatus . ')';
        }

        return $directStatus . '; ' . $queueStatus;
    }

    /**
     * Kiểm tra xem chế độ Hàng đợi ưu tiên (Queue-First) có đang được bật cho context hay không.
     * Mặc định là true (BẬT) để đảm bảo không làm chậm hay nghẽn tiến trình của người dùng.
     */
    public static function isQueueFirstEnabled(?int $contextId = null): bool
    {
        if ($contextId === null || $contextId <= 0) {
            $contextId = class_exists('ZaloSettingsProvider') ? ZaloSettingsProvider::getCurrentContextId() : 0;
        }
        if ($contextId > 0 && class_exists('\DAORegistry')) {
            try {
                $setting = \DAORegistry::getDAO('PluginSettingsDAO')->getSetting($contextId, 'zalonotificationplugin', 'queueFirstMode');
                if ($setting !== null) {
                    return (bool) $setting;
                }
            } catch (\Throwable $e) {
            }
        }
        return true;
    }

    /**
     * Xác định xem lỗi trả về có thể xếp hàng retry (tạm thời) hay là lỗi vĩnh viễn.
     */
    public static function isRetryableStatus(string $status): bool
    {
        $status = trim($status);
        if ($status === '' || $status === 'Thành công') {
            return false;
        }

        // Lỗi cấu hình hoặc môi trường cục bộ
        if (strpos($status, 'Chưa cấu hình API') !== false
            || strpos($status, 'Thiếu số điện thoại') !== false
            || strpos($status, 'PHP cURL') !== false) {
            return false;
        }

        // Lỗi HTTP 429 (Too Many Requests / Rate limit) là lỗi tạm thời cần xếp hàng retry
        if (preg_match('/Mã lỗi HTTP:\s*429/', $status)) {
            return true;
        }

        // Lỗi HTTP 4xx (400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found...)
        if (preg_match('/Mã lỗi HTTP:\s*4\d\d/', $status)) {
            return false;
        }

        // Gateway từ chối yêu cầu do dữ liệu/quyền không hợp lệ
        if (strpos($status, 'Gateway từ chối') !== false) {
            return false;
        }

        // Thất bại một phần: không retry nguyên danh sách để tránh spam những người đã nhận
        if (strpos($status, 'Thất bại một phần') !== false) {
            return false;
        }

        return true;
    }

    private static function resolveOutboxContextId(int $contextId): int
    {
        if ($contextId > 0) {
            return $contextId;
        }
        if (class_exists('ZaloSettingsProvider')) {
            try {
                $ctx = (int) ZaloSettingsProvider::getCurrentContextId();
                if ($ctx > 0) {
                    return $ctx;
                }
            } catch (\Throwable $e) {
            }
        }
        if (class_exists('\DAORegistry')) {
            try {
                $journalDao = \DAORegistry::getDAO('JournalDAO');
                if ($journalDao) {
                    $journals = $journalDao->getAll(true);
                    $firstJournal = $journals ? $journals->next() : null;
                    if ($firstJournal) {
                        return (int) $firstJournal->getId();
                    }
                }
            } catch (\Throwable $ignored) {
            }
        }
        return 0;
    }

    /**
     * Gửi ngay tới gateway. Được dùng cho mọi thông báo để plugin không phụ thuộc worker.
     */
    public static function sendToPhonesNow(string $messageText, string $botId, string $apiKey, array $phoneNumbers): string
    {
        if (trim($botId) === '' || trim($apiKey) === '') {
            return 'Thất bại (Chưa cấu hình API)';
        }

        if (!function_exists('curl_init')) {
            ZaloNotificationPlugin::writeSecureDebug('sendToPhones: PHP cURL chưa được bật, bỏ qua gửi Zalo.', 'ZaloApiClient');
            return "Thất bại (PHP cURL chưa bật)";
        }

        $url = 'https://sms-service.talab.io.vn/api/gateway/v1.0/bots/' . rawurlencode(trim($botId)) . '/messages/send-batch';

        $recipients = [];
        $seenPhones = [];
        foreach ($phoneNumbers as $phone) {
            $normalized = ZaloPhoneNormalizer::normalize($phone);
            if (!empty($normalized) && !isset($seenPhones[$normalized])) {
                $recipients[] = ["phone" => $normalized];
                $seenPhones[$normalized] = true;
            }
        }

        if (empty($recipients)) {
            ZaloNotificationPlugin::writeSecureDebug('sendToPhones: Mảng recipients rỗng.', 'ZaloApiClient');
            return "Thất bại (Thiếu số điện thoại)";
        }

        $payload = [
            "recipients" => $recipients,
            "content" => [
                "type" => "text",
                "data" => ["text" => $messageText]
            ],
            "mode" => "safe"
        ];

        // Đảm bảo giãn cách tần suất gọi ra gateway tối thiểu 1.0 giây để Zalo không đánh dấu spam
        RateLimiter::throttleEgress(1000);

        $ch = curl_init($url);
        if ($ch === false) {
            ZaloNotificationPlugin::writeSecureDebug('sendToPhones: Không khởi tạo được cURL.', 'ZaloApiClient');
            return "Thất bại (Không khởi tạo được cURL)";
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . trim($apiKey)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $curlError = '';
        if ($response === false) {
            $curlError = curl_error($ch);
        }

        curl_close($ch);

        $gatewayResult = ZaloGatewayResponse::analyze((int) $httpCode, $response, $curlError);

        // Ghi log bảo mật có bổ sung chi tiết lỗi từ gateway nếu có (không ghi payload PII)
        ZaloNotificationPlugin::writeSecureDebug(
            "sendToPhones: HTTP={$httpCode} | recipients=" . count($recipients)
                . " | response_format=" . $gatewayResult['format']
                . " | gateway_signal=" . $gatewayResult['signal']
                . ($curlError !== '' ? " | cURL error=" . ZaloGatewayResponse::sanitizeDiagnostic($curlError) : '')
                . (!empty($gatewayResult['detail']) ? " | gateway_message=" . ZaloGatewayResponse::sanitizeDiagnostic($gatewayResult['detail']) : ''),
            'ZaloApiClient'
        );

        return $gatewayResult['result'];
    }

    /**
     * Đưa trực tiếp thông báo vào hàng đợi outbox (Queue-First), giải phóng tiến trình web ngay lập tức.
     */
    private static function enqueueDirect(
        int $contextId,
        string $eventType,
        string $messageText,
        array $normalizedPhones
    ): string {
        $queueStatus = ZaloOutboxRepository::enqueue(
            $contextId,
            $eventType,
            $messageText,
            $normalizedPhones
        );

        ZaloNotificationPlugin::writeSecureDebug(
            "sendToPhones [{$eventType}]: Queue-First đã xếp hàng vào Outbox (" . count($normalizedPhones) . " người nhận, context={$contextId}).",
            'ZaloApiClient'
        );

        return $queueStatus;
    }

    /**
     * Evaluate both the HTTP status and the gateway's application-level JSON.
     * Kept public so the response contract can be regression-tested without a live gateway.
     */
    public static function evaluateGatewayResponse(int $httpCode, $response, string $curlError = ''): string
    {
        $analysis = ZaloGatewayResponse::analyze($httpCode, $response, $curlError);
        return $analysis['result'];
    }
}
