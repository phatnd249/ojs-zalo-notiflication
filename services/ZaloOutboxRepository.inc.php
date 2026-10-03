<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/** Hàng đợi bền vững cho các thông báo Zalo. */
class ZaloOutboxRepository
{
    private const TABLE = 'zalo_notification_outbox';
    private const MAX_ATTEMPTS = 5;
    private const STALE_LOCK_MINUTES = 10;
    private const RETENTION_DAYS = 30;

    /** @var bool|null */
    private static $schemaReady = null;

    public static function ensureSchema(): bool
    {
        if (self::$schemaReady === true) {
            return true;
        }

        try {
            if (!Capsule::schema()->hasTable(self::TABLE)) {
                (new ZaloNotificationMigration())->up();
            }
            self::$schemaReady = Capsule::schema()->hasTable(self::TABLE);
        } catch (\Throwable $e) {
            // Một request khác có thể vừa tạo bảng sau lần kiểm tra đầu tiên.
            try {
                self::$schemaReady = Capsule::schema()->hasTable(self::TABLE);
            } catch (\Throwable $ignored) {
                self::$schemaReady = false;
            }
            if (self::$schemaReady !== true && class_exists('ZaloNotificationPlugin')) {
                ZaloNotificationPlugin::writeSecureDebug(
                    'Không thể khởi tạo bảng outbox: ' . $e->getMessage(),
                    'ZaloOutbox'
                );
            }
        }

        return self::$schemaReady === true;
    }

    public static function enqueue(int $contextId, string $eventType, string $messageText, array $phoneNumbers): string
    {
        if (!self::ensureSchema()) {
            return 'Thất bại (Chưa khởi tạo được hàng đợi)';
        }

        $recipients = array_values(array_unique(array_filter(array_map('strval', $phoneNumbers))));
        sort($recipients, SORT_STRING);
        if ($messageText === '' || empty($recipients)) {
            return 'Thất bại (Dữ liệu hàng đợi không hợp lệ)';
        }

        $eventType = preg_replace('/[^A-Z0-9_-]/i', '', $eventType) ?: 'DIRECT';
        // Chặn hook trùng trong cùng cửa sổ 5 phút, nhưng vẫn cho phép nghiệp vụ hợp lệ lặp lại sau đó.
        $dedupeWindow = (string) floor(time() / 300);
        $dedupeKey = hash(
            'sha256',
            $contextId . '|' . $eventType . '|' . $dedupeWindow . '|' . implode(',', $recipients) . '|' . $messageText
        );
        $now = date('Y-m-d H:i:s');

        try {
            Capsule::table(self::TABLE)->insert([
                'context_id' => $contextId,
                'event_type' => substr($eventType, 0, 64),
                'dedupe_key' => $dedupeKey,
                'message_text' => $messageText,
                'recipients_json' => json_encode($recipients, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => $now,
                'locked_at' => null,
                'sent_at' => null,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return 'Đã xếp hàng';
        } catch (\Throwable $e) {
            // Ràng buộc unique là lớp chống trùng cuối cùng khi nhiều hook chạy đồng thời.
            try {
                if (Capsule::table(self::TABLE)->where('dedupe_key', $dedupeKey)->exists()) {
                    return 'Đã xếp hàng (trùng đã bỏ qua)';
                }
            } catch (\Throwable $ignored) {
            }

            ZaloNotificationPlugin::writeSecureDebug('Không thể thêm outbox: ' . $e->getMessage(), 'ZaloOutbox');
            return 'Thất bại (Không thể xếp hàng)';
        }
    }

    /** Nhận độc quyền một lô thông báo sẵn sàng gửi. */
    public static function claimBatch(int $limit = 20): array
    {
        if (!self::ensureSchema()) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $now = date('Y-m-d H:i:s');
        $staleAt = date('Y-m-d H:i:s', time() - self::STALE_LOCK_MINUTES * 60);

        return Capsule::connection()->transaction(function () use ($limit, $now, $staleAt) {
            $rows = Capsule::table(self::TABLE)
                ->where(function ($query) use ($now, $staleAt) {
                    $query->where(function ($ready) use ($now) {
                        $ready->whereIn('status', ['pending', 'retry'])
                            ->where('available_at', '<=', $now);
                    })->orWhere(function ($stale) use ($staleAt) {
                        $stale->where('status', 'processing')
                            ->where('locked_at', '<=', $staleAt);
                    });
                })
                ->orderBy('outbox_id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $claimed = [];
            foreach ($rows as $row) {
                $attempts = (int) $row->attempts + 1;
                Capsule::table(self::TABLE)
                    ->where('outbox_id', $row->outbox_id)
                    ->update([
                        'status' => 'processing',
                        'attempts' => $attempts,
                        'locked_at' => $now,
                        'updated_at' => $now,
                    ]);
                $row->attempts = $attempts;
                $row->status = 'processing';
                $row->locked_at = $now;
                $claimed[] = $row;
            }
            return $claimed;
        });
    }

    public static function markSent(int $outboxId): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table(self::TABLE)->where('outbox_id', $outboxId)->update([
            'status' => 'sent',
            'message_text' => '',
            'recipients_json' => '[]',
            'locked_at' => null,
            'sent_at' => $now,
            'last_error' => null,
            'updated_at' => $now,
        ]);
    }

    public static function markFailed(int $outboxId, int $attempts, string $error, bool $permanent = false): void
    {
        $now = date('Y-m-d H:i:s');
        $error = self::sanitizeError($error);
        if ($permanent || $attempts >= self::MAX_ATTEMPTS) {
            Capsule::table(self::TABLE)->where('outbox_id', $outboxId)->update([
                'status' => 'failed',
                'locked_at' => null,
                'last_error' => $error,
                'updated_at' => $now,
            ]);
            return;
        }

        $delaySeconds = min(3600, 60 * (2 ** max(0, $attempts - 1)));
        Capsule::table(self::TABLE)->where('outbox_id', $outboxId)->update([
            'status' => 'retry',
            'locked_at' => null,
            'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
            'last_error' => $error,
            'updated_at' => $now,
        ]);
    }

    public static function getOutboxEntries(int $contextId, ?string $statusFilter = null, int $limit = 100): array
    {
        if (!self::ensureSchema()) {
            return [];
        }

        $query = Capsule::table(self::TABLE)
            ->where('context_id', $contextId);

        if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
            if ($statusFilter === 'active_queue') {
                $query->whereIn('status', ['pending', 'retry', 'processing']);
            } else {
                $query->where('status', $statusFilter);
            }
        }

        $rows = $query->orderBy('outbox_id', 'desc')
            ->limit(max(1, min(500, $limit)))
            ->get();

        $results = [];
        foreach ($rows as $row) {
            $recipients = json_decode((string) $row->recipients_json, true);
            $results[] = [
                'outbox_id' => (int) $row->outbox_id,
                'context_id' => (int) $row->context_id,
                'event_type' => (string) $row->event_type,
                'dedupe_key' => (string) $row->dedupe_key,
                'message_text' => (string) $row->message_text,
                'recipients' => is_array($recipients) ? $recipients : [],
                'recipient_count' => is_array($recipients) ? count($recipients) : 0,
                'status' => (string) $row->status,
                'attempts' => (int) $row->attempts,
                'max_attempts' => self::MAX_ATTEMPTS,
                'available_at' => (string) $row->available_at,
                'locked_at' => $row->locked_at ? (string) $row->locked_at : null,
                'sent_at' => $row->sent_at ? (string) $row->sent_at : null,
                'last_error' => $row->last_error ? (string) $row->last_error : null,
                'created_at' => (string) $row->created_at,
                'updated_at' => (string) $row->updated_at,
            ];
        }

        return $results;
    }

    public static function retryItem(int $outboxId, int $contextId): bool
    {
        if (!self::ensureSchema()) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $affected = Capsule::table(self::TABLE)
            ->where('outbox_id', $outboxId)
            ->where('context_id', $contextId)
            ->update([
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => $now,
                'locked_at' => null,
                'last_error' => null,
                'updated_at' => $now,
            ]);

        return $affected > 0;
    }

    public static function retryAllFailed(int $contextId): int
    {
        if (!self::ensureSchema()) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        return Capsule::table(self::TABLE)
            ->where('context_id', $contextId)
            ->whereIn('status', ['failed', 'retry'])
            ->update([
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => $now,
                'locked_at' => null,
                'last_error' => null,
                'updated_at' => $now,
            ]);
    }

    public static function deleteItem(int $outboxId, int $contextId): bool
    {
        if (!self::ensureSchema()) {
            return false;
        }

        $deleted = Capsule::table(self::TABLE)
            ->where('outbox_id', $outboxId)
            ->where('context_id', $contextId)
            ->delete();

        return $deleted > 0;
    }

    public static function clearSent(int $contextId): int
    {
        if (!self::ensureSchema()) {
            return 0;
        }

        return Capsule::table(self::TABLE)
            ->where('context_id', $contextId)
            ->where('status', 'sent')
            ->delete();
    }

    public static function cleanup(): int
    {
        if (!self::ensureSchema()) {
            return 0;
        }
        $cutoff = date('Y-m-d H:i:s', time() - self::RETENTION_DAYS * 86400);
        return Capsule::table(self::TABLE)
            ->whereIn('status', ['sent', 'failed'])
            ->where('updated_at', '<', $cutoff)
            ->delete();
    }

    public static function getStats(int $contextId): array
    {
        $stats = ['pending' => 0, 'processing' => 0, 'retry' => 0, 'sent' => 0, 'failed' => 0];
        if (!self::ensureSchema()) {
            return $stats;
        }

        $rows = Capsule::table(self::TABLE)
            ->select('status', Capsule::raw('COUNT(*) AS total'))
            ->where('context_id', $contextId)
            ->groupBy('status')
            ->get();
        foreach ($rows as $row) {
            if (array_key_exists($row->status, $stats)) {
                $stats[$row->status] = (int) $row->total;
            }
        }
        return $stats;
    }

    private static function sanitizeError(string $error): string
    {
        $error = preg_replace('/[\r\n]+/', ' ', $error);
        $error = preg_replace('/\bzlite_[A-Za-z0-9_-]+\b/i', '[REDACTED]', $error);
        $error = preg_replace('/(?<!\d)(?:\+?84|0084|0)\d{9}(?!\d)/', '[PHONE_REDACTED]', $error);
        return substr(trim($error), 0, 500);
    }
}
