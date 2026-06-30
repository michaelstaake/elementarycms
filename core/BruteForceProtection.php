<?php

declare(strict_types=1);

namespace Elementary;

class BruteForceProtection
{
    public static function isEnabled(): bool
    {
        return (bool) setting('brute_force_protection_enabled', true);
    }

    public static function getMaxAttempts(): int
    {
        return (int) setting('brute_force_max_attempts', 5);
    }

    public static function getBlockDuration(): int
    {
        return (int) setting('brute_force_block_duration', 3600);
    }

    /**
     * Record a failed login attempt for an IP address.
     */
    public static function recordFailure(string $ip): void
    {
        Database::insert('login_attempts', [
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Check if an IP is currently banned. Returns true if banned.
     */
    public static function isBanned(string $ip): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        self::expireOldAttempts();

        $windowStart = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $row = Database::fetch(
            'SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip_address = ? AND created_at >= ?',
            [$ip, $windowStart]
        );

        return ((int) $row['cnt'] >= self::getMaxAttempts());
    }

    /**
     * Get the remaining ban time in seconds for an IP, or 0 if not banned.
     */
    public static function getBanRemainingSeconds(string $ip): int
    {
        if (!self::isEnabled()) {
            return 0;
        }

        self::expireOldAttempts();

        $windowStart = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $row = Database::fetch(
            'SELECT MAX(created_at) AS last_attempt FROM login_attempts WHERE ip_address = ? AND created_at >= ?',
            [$ip, $windowStart]
        );

        if (!$row || !$row['last_attempt']) {
            return 0;
        }

        $countRow = Database::fetch(
            'SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip_address = ? AND created_at >= ?',
            [$ip, $windowStart]
        );

        if ((int) $countRow['cnt'] < self::getMaxAttempts()) {
            return 0;
        }

        // Ban expires 15 minutes after the last attempt in the window
        $lastAttempt = new DateTime($row['last_attempt']);
        $banExpires = clone $lastAttempt;
        $banExpires->modify('+15 minutes');

        $remaining = $banExpires->getTimestamp() - time();
        return max(0, $remaining);
    }

    /**
     * Get all currently banned IPs with their ban expiry times.
     */
    public static function getBannedIps(): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        self::expireOldAttempts();

        $windowStart = date('Y-m-d H:i:s', strtotime('-15 minutes'));

        $rows = Database::fetchAll(
            'SELECT ip_address, MAX(created_at) AS last_attempt, COUNT(*) AS attempts
             FROM login_attempts
             WHERE ip_address IN (
                 SELECT ip_address FROM login_attempts
                 WHERE created_at >= ?
                 GROUP BY ip_address
                 HAVING COUNT(*) >= ?
             )
             AND created_at >= ?
             GROUP BY ip_address
             ORDER BY last_attempt DESC',
            [$windowStart, self::getMaxAttempts(), $windowStart]
        );

        $result = [];
        foreach ($rows as $row) {
            $lastAttempt = new DateTime($row['last_attempt']);
            $banExpires = clone $lastAttempt;
            $banExpires->modify('+15 minutes');

            $remaining = $banExpires->getTimestamp() - time();

            if ($remaining > 0) {
                $result[] = [
                    'ip_address' => $row['ip_address'],
                    'attempts' => (int) $row['attempts'],
                    'ban_expires' => $banExpires->format('Y-m-d H:i:s'),
                    'remaining_seconds' => $remaining,
                ];
            }
        }

        return $result;
    }

    /**
     * Clear all login attempt records (when disabling protection).
     */
    public static function clearAll(): void
    {
        Database::query('DELETE FROM login_attempts');
    }

    /**
     * Remove expired attempt records (older than 1 hour).
     */
    private static function expireOldAttempts(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-1 hour'));
        Database::delete('login_attempts', 'created_at < ?', [$cutoff]);
    }
}
