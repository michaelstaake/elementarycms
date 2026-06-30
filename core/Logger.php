<?php

declare(strict_types=1);

namespace Elementary;

class Logger
{
    public static function log(string $action, int|string|null $userId = null, ?string $details = null): void
    {
        if (!Config::get('installed', false)) {
            return;
        }

        // Cast to int (handles string IDs from PDO on some hosting configs)
        $userId = $userId !== null ? (int) $userId : null;

        try {
            Database::insert('logs', [
                'user_id'    => $userId,
                'action'     => $action,
                'details'    => $details,
                'ip_address' => self::getClientIp(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Silently fail if DB not ready
        }
    }

    public static function purge(): void
    {
        if (!Config::get('installed', false)) {
            return;
        }

        try {
            $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
            Database::delete('logs', 'created_at < ?', [$cutoff]);
        } catch (\Throwable) {
        }
    }

    public static function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
