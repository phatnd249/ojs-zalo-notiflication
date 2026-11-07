<?php

class ActivityLogReader
{
    /**
     * Thống kê hoạt động và số lượng gửi tin Zalo theo ngày (hôm nay hoặc các ngày trước).
     */
    public static function getDailySummary(?string $selectedDate = null, ?int $contextId = null): array
    {
        $contextId = ActivityLogger::resolveContextId(0, $contextId);
        $date = $selectedDate ? substr(trim($selectedDate), 0, 10) : date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $stats = [
            'date' => $date,
            'is_today' => ($date === date('Y-m-d')),
            'sent' => 0,
            'delivered' => 0,
            'failed' => 0,
            'retrying' => 0,
            'events' => [
                'submission' => 0,
                'decision' => 0,
                'publish' => 0,
                'unpublish' => 0,
                'review_request' => 0,
                'review_response' => 0,
                'review_reminder' => 0,
                'review_completed' => 0,
            ],
            'recent_activity' => [],
            'reviewer_reminders' => [
                'overdue' => 0,
                'due_in_3_days' => 0,
            ]
        ];

        if (class_exists('ZaloOutboxRepository')) {
            try {
                $outboxStats = ZaloOutboxRepository::getStats($contextId);
                $stats['retrying'] = (int) ($outboxStats['retry'] ?? 0) + (int) ($outboxStats['pending'] ?? 0) + (int) ($outboxStats['processing'] ?? 0);
            } catch (\Throwable $e) {
            }
        }

        if (class_exists('ReviewReminderDashboardService')) {
            try {
                $submissions = ReviewReminderDashboardService::getSubmissions($contextId);
                foreach ($submissions as $sub) {
                    if (!isset($sub['reviewers']) || !is_array($sub['reviewers'])) continue;
                    foreach ($sub['reviewers'] as $rev) {
                        if (($rev['dueClass'] ?? '') === 'overdue') {
                            $stats['reviewer_reminders']['overdue']++;
                        } elseif (isset($rev['daysLeft']) && $rev['daysLeft'] >= 0 && $rev['daysLeft'] <= 3) {
                            $stats['reviewer_reminders']['due_in_3_days']++;
                        }
                    }
                }
            } catch (\Throwable $ignored) {
            }
        }

        $logFile = ActivityLogger::getLogFilePath($contextId);
        ActivityLogStorage::enforceRetention($logFile, $contextId);
        $logFiles = ActivityLogger::getLogFiles($logFile, true);
        if (empty($logFiles)) {
            return $stats;
        }

        foreach ($logFiles as $file) {
            $handle = @fopen($file, 'r');
            if (!$handle) continue;

            try {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $data = json_decode($line, true);
                    if (!is_array($data) || empty($data['time'])) continue;

                    $entryDate = substr($data['time'], 0, 10);
                    if ($entryDate !== $date) continue;

                    $data = ActivityLogger::sanitizeUtf8($data);
                    $type = (string) ($data['type'] ?? '');
                    $level = (string) ($data['level'] ?? '');
                    $extra = isset($data['extra']) && is_array($data['extra']) ? $data['extra'] : [];
                    $zaloStatus = (string) ($extra['zalo_status'] ?? ($extra['status'] ?? ''));

                    // Count events
                    if ($type === ActivityLogger::TYPE_SUBMISSION) $stats['events']['submission']++;
                    elseif ($type === ActivityLogger::TYPE_DECISION) $stats['events']['decision']++;
                    elseif ($type === ActivityLogger::TYPE_PUBLISH) $stats['events']['publish']++;
                    elseif ($type === ActivityLogger::TYPE_UNPUBLISH) $stats['events']['unpublish']++;
                    elseif ($type === ActivityLogger::TYPE_REVIEW_REQUEST) $stats['events']['review_request']++;
                    elseif ($type === ActivityLogger::TYPE_REVIEW_RESPONSE) $stats['events']['review_response']++;
                    elseif ($type === ActivityLogger::TYPE_REVIEW_REMINDER) $stats['events']['review_reminder']++;
                    elseif ($type === ActivityLogger::TYPE_REVIEW_COMPLETED) $stats['events']['review_completed']++;

                    // Count deliveries
                    if ($zaloStatus !== '' || $type === ActivityLogger::TYPE_ZALO_SEND) {
                        $stats['sent']++;
                        if (ActivityLogger::isGoodStatus($zaloStatus)) {
                            $stats['delivered']++;
                        } else {
                            $stats['failed']++;
                        }
                    } elseif ($type === ActivityLogger::TYPE_ERROR || $level === ActivityLogger::LEVEL_ERROR) {
                        $stats['failed']++;
                    }

                    // Activity item
                    $icon = '✓';
                    $statusClass = 'success';
                    if ($type === ActivityLogger::TYPE_ERROR || $level === ActivityLogger::LEVEL_ERROR || (strpos($zaloStatus, 'Thất bại') !== false)) {
                        $icon = '✕';
                        $statusClass = 'danger';
                    } elseif (strpos($zaloStatus, 'retry') !== false || strpos($zaloStatus, 'Chờ') !== false) {
                        $icon = '↻';
                        $statusClass = 'warning';
                    }

                    $label = !empty($data['title']) ? (mb_substr((string) $data['title'], 0, 45) . (mb_strlen((string) $data['title']) > 45 ? '...' : '')) : ($data['action'] ?? $type);
                    $stats['recent_activity'][] = [
                        'time' => substr((string) $data['time'], 11, 5),
                        'icon' => $icon,
                        'class' => $statusClass,
                        'type' => $type,
                        'label' => $label,
                        'actor' => $data['actor'] ?? 'System',
                        'status' => $zaloStatus,
                    ];
                }
            } finally {
                fclose($handle);
            }
        }

        $stats['recent_activity'] = array_slice(array_reverse($stats['recent_activity']), 0, 6);
        return $stats;
    }

    /**
     * Đọc N dòng log gần nhất, trả về mảng các entry đã parse.
     * Kết quả sắp xếp mới nhất trước.
     *
     * @param int $limit Số dòng tối đa cần lấy (mặc định 100)
     * @param string|null $filterType Lọc theo type (null = tất cả)
     * @return array Mảng các entry (associative array)
     */
    public static function getRecentEntries(int $limit = 100, ?string $filterType = null, ?int $contextId = null): array
    {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return [];
        }

        $contextId = ActivityLogger::resolveContextId(0, $contextId);
        $logFile = ActivityLogger::getLogFilePath($contextId);
        ActivityLogStorage::enforceRetention($logFile, $contextId);
        $logFiles = ActivityLogger::getLogFiles($logFile, false);
        if (empty($logFiles)) {
            return [];
        }

        // Chỉ đọc phần đuôi của từng file, mới nhất trước.
        $entries = [];
        foreach ($logFiles as $file) {
            $lines = self::readTailLines($file, ActivityLogger::READ_TAIL_BYTES);
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $data = json_decode($lines[$i], true);
                if (!is_array($data)) {
                    continue;
                }

                $data = ActivityLogger::sanitizeUtf8($data);
                if ($filterType !== null && (!isset($data['type']) || $data['type'] !== $filterType)) {
                    continue;
                }

                $entries[] = $data;
                if (count($entries) >= $limit) {
                    break 2;
                }
            }
        }

        return $entries;
    }

    /**
     * Kiểm tra xem hành động đã được ghi log TRONG VÒNG $windowSeconds giây gần đây chưa.
     * Mặc định: 300 giây (5 phút) — cho phép lặp lại sau 5 phút, nhưng chặn double-click.
     */
    public static function isAlreadyLogged(string $actionKey, int $windowSeconds = 300, ?int $contextId = null): bool
    {
        $contextId = ActivityLogger::resolveContextId(0, $contextId);
        $logFile = ActivityLogger::getLogFilePath($contextId);
        ActivityLogStorage::enforceRetention($logFile, $contextId);
        $logFiles = ActivityLogger::getLogFiles($logFile, false);
        if (empty($logFiles)) return false;

        $cutoff = time() - $windowSeconds;
        foreach ($logFiles as $file) {
            $lines = self::readTailLines($file, ActivityLogger::READ_TAIL_BYTES);
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $data = json_decode($lines[$i], true);
                if (!$data) continue;

                if (isset($data['time'])) {
                    $entryTime = strtotime((string) $data['time']);
                    if ($entryTime !== false && $entryTime < $cutoff) {
                        break;
                    }
                }

                if (isset($data['action']) && $data['action'] === $actionKey && isset($data['time'])) {
                    $entryTime = strtotime((string) $data['time']);
                    if ($entryTime !== false && $entryTime >= $cutoff) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function readTailLines(string $file, int $maxBytes): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $size = filesize($file);
        if ($size === false || $size <= 0) {
            return [];
        }

        $handle = @fopen($file, 'rb');
        if (!$handle) {
            return [];
        }

        try {
            $start = max(0, $size - $maxBytes);
            if ($start > 0) {
                fseek($handle, $start);
                fgets($handle);
            }

            $lines = [];
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
            return $lines;
        } finally {
            fclose($handle);
        }
    }
}
