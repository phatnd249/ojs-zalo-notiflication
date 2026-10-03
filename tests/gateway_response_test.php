<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ZaloApiClient.inc.php';

function assertGatewayResult(string $label, string $expected, int $httpCode, $body, string $curlError = ''): void
{
    $actual = ZaloApiClient::evaluateGatewayResponse($httpCode, $body, $curlError);
    if ($actual !== $expected) {
        fwrite(STDERR, "{$label}: expected [{$expected}], got [{$actual}].\n");
        exit(1);
    }
}

assertGatewayResult(
    'Explicit JSON success',
    'Thành công',
    200,
    json_encode(['success' => true, 'data' => ['status' => 'sent']])
);

assertGatewayResult(
    'HTTP 200 with application failure',
    'Thất bại (Gateway từ chối yêu cầu)',
    200,
    json_encode(['success' => false, 'message' => 'Invalid request'])
);

assertGatewayResult(
    'String false is an application failure',
    'Thất bại (Gateway từ chối yêu cầu)',
    200,
    json_encode(['success' => 'false'])
);

assertGatewayResult(
    'Nested gateway error',
    'Thất bại (Gateway từ chối yêu cầu)',
    201,
    json_encode(['data' => ['result' => ['status' => 'rejected']]])
);

assertGatewayResult(
    'Partial batch failure',
    'Thất bại một phần (Một số người nhận bị lỗi)',
    200,
    json_encode(['success' => true, 'data' => ['successCount' => 2, 'failedCount' => 1]])
);

assertGatewayResult(
    'Partial failure status',
    'Thất bại một phần (Một số người nhận bị lỗi)',
    200,
    json_encode(['status' => 'partially_failed'])
);

assertGatewayResult(
    'Accepted response',
    'Thành công',
    202,
    json_encode(['status' => 'accepted'])
);

assertGatewayResult('Empty no-content response', 'Thành công', 204, '');
assertGatewayResult('Legacy text response', 'Thành công', 200, 'OK');

assertGatewayResult(
    'HTTP error overrides successful body',
    'Thất bại (Mã lỗi HTTP: 401)',
    401,
    json_encode(['success' => true])
);

assertGatewayResult(
    'Transport failure',
    'Thất bại (Lỗi kết nối gateway)',
    0,
    false,
    'Connection timed out'
);

assertGatewayResult(
    'Zero error code is not a failure',
    'Thành công',
    200,
    json_encode(['errorCode' => 0, 'data' => []])
);

if (ZaloApiClient::isRetryableStatus('Thành công')) {
    fwrite(STDERR, "Success should not be retryable.\n");
    exit(1);
}
if (ZaloApiClient::isRetryableStatus('Thất bại (Mã lỗi HTTP: 401)')) {
    fwrite(STDERR, "HTTP 401 should not be retryable.\n");
    exit(1);
}
if (ZaloApiClient::isRetryableStatus('Thất bại một phần (Một số người nhận bị lỗi)')) {
    fwrite(STDERR, "Partial failure without individual phone filtering should not be retryable.\n");
    exit(1);
}
if (!ZaloApiClient::isRetryableStatus('Thất bại (Lỗi kết nối gateway)')) {
    fwrite(STDERR, "Transport error should be retryable.\n");
    exit(1);
}
if (!ZaloApiClient::isRetryableStatus('Thất bại (Mã lỗi HTTP: 502)')) {
    fwrite(STDERR, "HTTP 502 should be retryable.\n");
    exit(1);
}

echo "Gateway response tests passed.\n";
