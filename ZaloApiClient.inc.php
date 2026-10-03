<?php

/**
 * ZaloApiClient — Gửi tin nhắn Zalo qua API.
 *
 * Hỗ trợ 2 chế độ:
 *   1. sendForEvent() — Gửi theo loại sự kiện, chỉ gửi cho nhóm đã đăng ký (MỚI)
 *   2. send()         — Gửi với thông tin hardcoded (DEPRECATED, giữ cho tương thích ngược)
 */
class ZaloApiClient
{
    /**
     * Chuẩn hóa số điện thoại Việt Nam sang định dạng quốc tế (84xxx).
     * Zalo API yêu cầu số điện thoại ở dạng quốc tế để resolve người nhận.
     *
     * VD: 0912345678 → 84912345678
     *     84912345678 → 84912345678 (giữ nguyên)
     */
    private static function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', trim($phone));

        // Ưu tiên xử lý 0084 trước
        if (strpos($clean, '0084') === 0) {
            $clean = substr($clean, 2); // 0084 -> 84
        } elseif (strpos($clean, '0') === 0) {
            $clean = '84' . substr($clean, 1);
        }

        // Chỉ nhận số VN dạng 84 + 9 số sau đó
        if (!preg_match('/^84[0-9]{9}$/', $clean)) {
            return '';
        }

        return $clean;
    }

    public static function normalizePhoneNumber(string $phone): string
    {
        return self::normalizePhone($phone);
    }

    public static function getPhonesForEvent(string $eventType, array $recipientGroups): array
    {
        $phoneNumbers = [];
        $acceptedEvents = self::getAcceptedEvents($eventType);

        foreach ($recipientGroups as $group) {
            $events = isset($group['events']) && is_array($group['events']) ? $group['events'] : [];
            if (empty(array_intersect($acceptedEvents, $events))) {
                continue;
            }

            $phones = isset($group['phones'])
                ? (is_array($group['phones']) ? $group['phones'] : explode(',', $group['phones']))
                : [];

            foreach ($phones as $phone) {
                $clean = self::normalizePhone($phone);
                if (!empty($clean)) {
                    $phoneNumbers[] = $clean;
                }
            }
        }

        return array_values(array_unique($phoneNumbers));
    }

    private static function getAcceptedEvents(string $eventType): array
    {
        $eventAliases = [
            'REVIEW_REQUEST' => ['REVIEW_REQUEST', 'REVIEW_REMINDER'],
            'REVIEW_RESPONSE' => ['REVIEW_RESPONSE', 'REVIEW_REQUEST', 'REVIEW_REMINDER'],
            'REVIEW_COMPLETED' => ['REVIEW_COMPLETED', 'REVIEW_REMINDER'],
        ];

        return $eventAliases[$eventType] ?? [$eventType];
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

        $acceptedEvents = self::getAcceptedEvents($eventType);

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
            $normalized = self::normalizePhone((string) $phone);
            if ($normalized !== '') {
                $normalizedPhones[] = $normalized;
            }
        }
        $normalizedPhones = array_values(array_unique($normalizedPhones));
        if (empty($normalizedPhones)) {
            return 'Thất bại (Thiếu số điện thoại)';
        }

        // Luôn thử gửi trực tiếp trước. Worker gọi sendToPhonesNow() để không tự xếp hàng lặp.
        $directStatus = self::sendToPhonesNow($messageText, $botId, $apiKey, $normalizedPhones);
        if ($directStatus === 'Thành công') {
            return $directStatus;
        }

        // Không xếp hàng retry đối với lỗi cấu hình, lỗi client 4xx, hoặc gateway từ chối vĩnh viễn
        if (!self::isRetryableStatus($directStatus)) {
            return $directStatus;
        }

        $contextId = self::resolveOutboxContextId($contextId);
        if ($contextId <= 0 || !class_exists('ZaloOutboxRepository')) {
            ZaloNotificationPlugin::writeSecureDebug(
                "sendToPhones [{$eventType}]: Gửi trực tiếp lỗi nhưng không xác định được context để retry.",
                'ZaloApiClient'
            );
            return $directStatus . '; không thể xếp hàng retry';
        }

        $queueStatus = ZaloOutboxRepository::enqueue(
            $contextId,
            $eventType,
            $messageText,
            $normalizedPhones
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

        // Lỗi HTTP 4xx (400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found...)
        if (preg_match('/Mã lỗi HTTP:\s*4\d\d/', $status)) {
            return false;
        }

        // Gateway từ chối yêu cầu do dữ liệu/quyền không hợp lệ
        if (strpos($status, 'Gateway từ chối yêu cầu') !== false) {
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
                return (int) ZaloSettingsProvider::getCurrentContextId();
            } catch (\Throwable $e) {
                return 0;
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
            $normalized = self::normalizePhone($phone);
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

        $gatewayResult = self::analyzeGatewayResponse((int) $httpCode, $response, $curlError);

        // Không ghi payload, số điện thoại, API key hoặc nội dung phản hồi vào log.
        ZaloNotificationPlugin::writeSecureDebug(
            "sendToPhones: HTTP={$httpCode} | recipients=" . count($recipients)
                . " | response_format=" . $gatewayResult['format']
                . " | gateway_signal=" . $gatewayResult['signal']
                . ($curlError !== '' ? " | cURL error=" . self::sanitizeDiagnostic($curlError) : ''),
            'ZaloApiClient'
        );

        return $gatewayResult['result'];
    }

    /**
     * Evaluate both the HTTP status and the gateway's application-level JSON.
     * Kept public so the response contract can be regression-tested without a live gateway.
     */
    public static function evaluateGatewayResponse(int $httpCode, $response, string $curlError = ''): string
    {
        $analysis = self::analyzeGatewayResponse($httpCode, $response, $curlError);
        return $analysis['result'];
    }

    private static function analyzeGatewayResponse(int $httpCode, $response, string $curlError): array
    {
        if ($curlError !== '' || $response === false) {
            return [
                'result' => 'Thất bại (Lỗi kết nối gateway)',
                'format' => 'none',
                'signal' => 'transport_error',
            ];
        }

        $body = trim((string) $response);
        $format = $body === '' ? 'empty' : 'text';
        $signals = [
            'success' => false,
            'failure' => false,
            'partial' => false,
        ];

        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $format = 'json';
                self::collectGatewaySignals($decoded, $signals, 0);
            }
        }

        // A transport/protocol failure always wins, even if the body claims success.
        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'result' => "Thất bại (Mã lỗi HTTP: {$httpCode})",
                'format' => $format,
                'signal' => 'http_error',
            ];
        }

        // Gateways sometimes return HTTP 200 while rejecting the business request.
        if ($signals['failure']) {
            return [
                'result' => $signals['partial']
                    ? 'Thất bại một phần (Một số người nhận bị lỗi)'
                    : 'Thất bại (Gateway từ chối yêu cầu)',
                'format' => $format,
                'signal' => $signals['partial'] ? 'partial_failure' : 'application_error',
            ];
        }

        return [
            'result' => 'Thành công',
            'format' => $format,
            'signal' => $signals['success'] ? 'application_success' : 'http_success',
        ];
    }

    private static function collectGatewaySignals(array $value, array &$signals, int $depth): void
    {
        if ($depth > 6) {
            return;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = is_string($key)
                ? strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $key))
                : '';

            if (in_array($normalizedKey, ['success', 'successful', 'ok'], true)) {
                $booleanSignal = self::readBooleanSignal($item);
                if ($booleanSignal === true) {
                    $signals['success'] = true;
                } elseif ($booleanSignal === false) {
                    $signals['failure'] = true;
                }
            }

            if (in_array($normalizedKey, ['error', 'errors', 'errormessage'], true) && self::hasErrorValue($item)) {
                $signals['failure'] = true;
            }

            if (in_array($normalizedKey, ['status', 'state', 'result'], true) && is_string($item)) {
                $status = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', trim($item)));
                if (in_array($status, ['error', 'failed', 'failure', 'unsuccessful', 'rejected', 'invalid', 'unauthorized', 'forbidden'], true)) {
                    $signals['failure'] = true;
                } elseif (in_array($status, ['partialfailure', 'partiallyfailed'], true)) {
                    $signals['failure'] = true;
                    $signals['partial'] = true;
                } elseif (in_array($status, ['success', 'successful', 'ok', 'accepted', 'queued', 'sent', 'completed'], true)) {
                    $signals['success'] = true;
                }
            }

            if (in_array($normalizedKey, ['failedcount', 'failurecount', 'errorcount', 'rejectedcount'], true)
                && is_numeric($item) && (int) $item > 0
            ) {
                $signals['failure'] = true;
                $signals['partial'] = true;
            }

            if ($normalizedKey === 'errorcode' && is_numeric($item) && (int) $item !== 0) {
                $signals['failure'] = true;
            }

            if (in_array($normalizedKey, ['code', 'statuscode'], true) && is_numeric($item)) {
                $code = (int) $item;
                if ($code === 0 || ($code >= 200 && $code < 300)) {
                    $signals['success'] = true;
                } elseif ($code < 0 || $code >= 400) {
                    $signals['failure'] = true;
                }
            }

            if (is_array($item)) {
                self::collectGatewaySignals($item, $signals, $depth + 1);
            }
        }
    }

    private static function hasErrorValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return is_array($value) && !empty($value);
    }

    private static function readBooleanSignal($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', 'yes', 'success', 'ok'], true)) {
                return true;
            }
            if (in_array($normalized, ['false', 'no', 'failed', 'failure', 'error'], true)) {
                return false;
            }
        }
        return null;
    }

    private static function sanitizeDiagnostic(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
        return substr(trim((string) $value), 0, 200);
    }
}
