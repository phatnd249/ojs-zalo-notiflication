<?php

class ZaloDebugLogger
{
    private const MAX_SIZE = 1048576;
    private const ROTATED_FILES = 2;

    public static function write(string $message, string $component = 'Plugin'): void
    {
        $debugFile = ZaloNotificationPlugin::getDataDir() . '/debug.txt';
        self::rotateIfNeeded($debugFile);

        $message = preg_replace('/[\r\n]+/', ' ', $message);
        $message = preg_replace('/\bzlite_[A-Za-z0-9_-]+\b/i', '[API_KEY_REDACTED]', $message);
        $message = preg_replace('/((?:api[ _-]?key|x-api-key)\s*[:=]\s*)[^\s|,;]+/i', '$1[REDACTED]', $message);
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[EMAIL_REDACTED]', $message);
        $message = preg_replace_callback(
            '/(?<!\d)(?:\+?84|0084|0)\d{9}(?!\d)/',
            function (array $matches): string {
                $phone = preg_replace('/\D/', '', $matches[0]);
                return substr($phone, 0, 2) . '******' . substr($phone, -3);
            },
            $message
        );

        $component = preg_replace('/[^A-Za-z0-9_-]/', '', $component) ?: 'Plugin';
        @file_put_contents(
            $debugFile,
            '[' . date('Y-m-d H:i:s') . '] [' . $component . '] ' . $message . "\n",
            FILE_APPEND | LOCK_EX
        );
        @chmod($debugFile, 0600);
    }

    private static function rotateIfNeeded(string $debugFile): void
    {
        if (!file_exists($debugFile) || filesize($debugFile) < self::MAX_SIZE) {
            return;
        }

        $oldest = $debugFile . '.' . self::ROTATED_FILES;
        if (file_exists($oldest)) {
            @unlink($oldest);
        }
        for ($i = self::ROTATED_FILES - 1; $i >= 1; $i--) {
            $source = $debugFile . '.' . $i;
            if (file_exists($source)) {
                @rename($source, $debugFile . '.' . ($i + 1));
            }
        }
        @rename($debugFile, $debugFile . '.1');
    }
}
