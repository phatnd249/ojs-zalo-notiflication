<?php

class ZaloGatewayResponse
{
    public static function analyze(int $httpCode, $response, string $curlError): array
    {
        if ($curlError !== '' || $response === false) {
            return [
                'result' => 'Thất bại (Lỗi kết nối gateway)',
                'format' => 'none',
                'signal' => 'transport_error',
                'detail' => $curlError,
            ];
        }

        $body = trim((string) $response);
        $format = $body === '' ? 'empty' : 'text';
        $signals = [
            'success' => false,
            'failure' => false,
            'partial' => false,
        ];
        $detailError = '';

        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $format = 'json';
                self::collectGatewaySignals($decoded, $signals, 0);
                $detailError = self::extractGatewayErrorMessage($decoded);
            }
        }

        // A transport/protocol failure always wins, even if the body claims success.
        if ($httpCode < 200 || $httpCode >= 300) {
            $reason = $detailError !== '' ? $detailError : self::getHttpStatusExplanation($httpCode);
            $suffix = $reason !== '' ? " - {$reason}" : '';
            return [
                'result' => "Thất bại (Mã lỗi HTTP: {$httpCode}{$suffix})",
                'format' => $format,
                'signal' => 'http_error',
                'detail' => $detailError,
            ];
        }

        // Gateways sometimes return HTTP 200 while rejecting the business request.
        if ($signals['failure']) {
            if ($signals['partial']) {
                $msg = $detailError !== '' ? $detailError : 'Một số người nhận bị lỗi';
                $result = "Thất bại một phần ({$msg})";
            } else {
                $msg = $detailError !== '' ? ": {$detailError}" : ' yêu cầu';
                $result = "Thất bại (Gateway từ chối{$msg})";
            }
            return [
                'result' => $result,
                'format' => $format,
                'signal' => $signals['partial'] ? 'partial_failure' : 'application_error',
                'detail' => $detailError,
            ];
        }

        return [
            'result' => 'Thành công',
            'format' => $format,
            'signal' => $signals['success'] ? 'application_success' : 'http_success',
            'detail' => '',
        ];
    }

    /**
     * Trích xuất thông điệp lỗi chi tiết từ phản hồi của Gateway (nếu có).
     */
    private static function extractGatewayErrorMessage(array $decoded): string
    {
        $candidates = ['message', 'msg', 'error_message', 'errorMessage', 'error_description', 'detail', 'reason', 'description'];
        foreach ($candidates as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key]) && trim($decoded[$key]) !== '') {
                return self::sanitizeDiagnostic($decoded[$key]);
            }
        }

        if (isset($decoded['error'])) {
            if (is_string($decoded['error']) && trim($decoded['error']) !== '') {
                return self::sanitizeDiagnostic($decoded['error']);
            }
            if (is_array($decoded['error'])) {
                foreach ($candidates as $key) {
                    if (isset($decoded['error'][$key]) && is_string($decoded['error'][$key]) && trim($decoded['error'][$key]) !== '') {
                        return self::sanitizeDiagnostic($decoded['error'][$key]);
                    }
                }
                $flat = array_filter(array_map('strval', $decoded['error']));
                if (!empty($flat)) {
                    return self::sanitizeDiagnostic(implode('; ', $flat));
                }
            }
        }

        if (isset($decoded['errors']) && is_array($decoded['errors'])) {
            $flat = [];
            foreach ($decoded['errors'] as $k => $v) {
                if (is_string($v) && trim($v) !== '') {
                    $flat[] = is_numeric($k) ? $v : "{$k}: {$v}";
                } elseif (is_array($v)) {
                    $sub = array_filter(array_map('strval', $v));
                    if (!empty($sub)) {
                        $flat[] = is_numeric($k) ? implode(', ', $sub) : "{$k}: " . implode(', ', $sub);
                    }
                }
            }
            if (!empty($flat)) {
                return self::sanitizeDiagnostic(implode('; ', $flat));
            }
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            foreach ($candidates as $key) {
                if (isset($decoded['data'][$key]) && is_string($decoded['data'][$key]) && trim($decoded['data'][$key]) !== '') {
                    return self::sanitizeDiagnostic($decoded['data'][$key]);
                }
            }
        }

        return '';
    }

    /**
     * Mô tả giải thích thân thiện cho người dùng đối với các mã lỗi HTTP phổ biến khi gateway không có body chi tiết.
     */
    private static function getHttpStatusExplanation(int $httpCode): string
    {
        switch ($httpCode) {
            case 400:
                return 'Dữ liệu không hợp lệ hoặc không xác định được người nhận';
            case 401:
                return 'Sai API Key hoặc chưa được xác thực';
            case 403:
                return 'Không có quyền truy cập hoặc Bot ID/API Key không hợp lệ';
            case 404:
                return 'Không tìm thấy endpoint hoặc Bot ID';
            case 429:
                return 'Vượt quá tần suất gửi tin cho phép (Rate limit)';
            case 500:
                return 'Lỗi máy chủ gateway';
            case 502:
            case 503:
            case 504:
                return 'Máy chủ gateway đang quá tải hoặc tạm thời gián đoạn';
            default:
                return '';
        }
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

    public static function sanitizeDiagnostic(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
        return substr(trim((string) $value), 0, 200);
    }
}
