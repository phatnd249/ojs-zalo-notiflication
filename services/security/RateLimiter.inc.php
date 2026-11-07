<?php

/**
 * Lớp kiểm soát tần suất gửi yêu cầu (Rate Limiter / Throttling)
 * Bảo vệ hệ thống khỏi tình trạng gửi tin nhắn liên tục, spam và quá tải gateway.
 */
class RateLimiter
{
    /**
     * Kiểm tra và áp dụng cooldown cho một hành động xác định.
     *
     * @param string $actionKey Định danh hành động (ví dụ: 'retry_all_outbox_context_1_user_2')
     * @param int $minIntervalSeconds Khoảng cách tối thiểu giữa các lần gọi thành công (giây)
     * @param int|null &$waitSeconds Số giây người dùng cần chờ thêm (nếu bị giới hạn)
     * @return bool True nếu yêu cầu ĐƯỢC CHẤP NHẬN, False nếu BỊ CHẶN do quá nhanh
     */
    public static function checkCooldown(string $actionKey, int $minIntervalSeconds, ?int &$waitSeconds = null): bool
    {
        $now = time();
        $safeKey = 'zalo_rl_' . substr(md5($actionKey), 0, 16);
        $lastTime = self::getTimestamp($safeKey);

        $elapsed = $now - $lastTime;
        if ($lastTime > 0 && $elapsed < $minIntervalSeconds) {
            $waitSeconds = $minIntervalSeconds - $elapsed;
            return false;
        }

        self::setTimestamp($safeKey, $now);
        $waitSeconds = 0;
        return true;
    }

    /**
     * Lấy mốc thời gian thực hiện lần cuối từ Session hoặc bộ nhớ tạm.
     */
    private static function getTimestamp(string $key): int
    {
        // 1. Thử qua Session OJS
        try {
            if (class_exists('SessionManager', false)) {
                $sessionManager = \SessionManager::getManager();
                $session = $sessionManager ? $sessionManager->getUserSession() : null;
                if ($session && method_exists($session, 'getSessionVar')) {
                    $val = $session->getSessionVar($key);
                    if ($val !== null && is_numeric($val)) {
                        return (int) $val;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // 2. Thử qua $_SESSION PHP chuẩn
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
            return (int) $_SESSION[$key];
        }

        // 3. Fallback: file tạm trong thư mục dữ liệu an toàn
        try {
            $file = self::getTempFile($key);
            if (file_exists($file)) {
                $content = @file_get_contents($file);
                if ($content !== false && is_numeric(trim($content))) {
                    return (int) trim($content);
                }
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }

    /**
     * Lưu mốc thời gian thực hiện mới nhất.
     */
    private static function setTimestamp(string $key, int $timestamp): void
    {
        // 1. Thử lưu vào Session OJS
        try {
            if (class_exists('SessionManager', false)) {
                $sessionManager = \SessionManager::getManager();
                $session = $sessionManager ? $sessionManager->getUserSession() : null;
                if ($session && method_exists($session, 'setSessionVar')) {
                    $session->setSessionVar($key, $timestamp);
                }
            }
        } catch (\Throwable $e) {
        }

        // 2. Lưu vào $_SESSION PHP chuẩn
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$key] = $timestamp;
        }

        // 3. Ghi vào file tạm để đảm bảo hoạt động ngay cả khi session đóng hoặc request khác luồng
        try {
            $file = self::getTempFile($key);
            @file_put_contents($file, (string) $timestamp, LOCK_EX);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Đường dẫn file cache tạm cho rate limiting.
     */
    private static function getTempFile(string $key): string
    {
        $dir = sys_get_temp_dir() . '/zalo_ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . '/' . $key . '.tmp';
    }

    /**
     * Xóa cache cooldown (dùng cho kiểm thử đơn vị hoặc reset thủ công).
     */
    public static function reset(string $actionKey): void
    {
        $safeKey = 'zalo_rl_' . substr(md5($actionKey), 0, 16);
        try {
            if (class_exists('SessionManager', false)) {
                $sessionManager = \SessionManager::getManager();
                $session = $sessionManager ? $sessionManager->getUserSession() : null;
                if ($session && method_exists($session, 'unsetSessionVar')) {
                    $session->unsetSessionVar($safeKey);
                }
            }
        } catch (\Throwable $e) {
        }

        if (isset($_SESSION[$safeKey])) {
            unset($_SESSION[$safeKey]);
        }

        $file = self::getTempFile($safeKey);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Giới hạn tần suất gửi tin thực tế ra Zalo Gateway (Egress Dispatch Throttling / Pacing).
     * Đảm bảo khoảng cách an toàn giữa 2 lần bắn tin ra gateway tối thiểu là $minIntervalMs (mặc định 1000ms).
     * Tránh việc gửi tin quá dồn dập khiến Zalo / Gateway đánh dấu bot là spam hoặc chặn IP/Bot ID.
     */
    public static function throttleEgress(int $minIntervalMs = 1000): void
    {
        $now = (int) round(microtime(true) * 1000);
        $file = self::getTempFile('egress_last_sent_ms');
        $lastSent = 0;

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false && is_numeric(trim($content))) {
                $lastSent = (int) trim($content);
            }
        }

        $elapsed = $now - $lastSent;
        if ($lastSent > 0 && $elapsed < $minIntervalMs) {
            $sleepUs = ($minIntervalMs - $elapsed) * 1000;
            // Giới hạn thời gian sleep tối đa 3 giây để không treo request
            if ($sleepUs > 0 && $sleepUs <= 3000000) {
                usleep($sleepUs);
            }
        }

        $updatedNow = (int) round(microtime(true) * 1000);
        @file_put_contents($file, (string) $updatedNow, LOCK_EX);
    }
}

