<?php

declare(strict_types=1);

namespace Elementary;

class Auth
{
    public const GROUP_ADMIN = 'admin';
    public const GROUP_STANDARD = 'standard';
    public const GROUP_AUTHOR = 'author';

    /** Default session lifetime: 30 days (in seconds). */
    private const DEFAULT_SESSION_LIFETIME = 2592000;

    public static function getSessionLifetime(): int
    {
        $lifetime = (int) Config::get('session_lifetime', self::DEFAULT_SESSION_LIFETIME);
        return max(3600, $lifetime);
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = self::getSessionLifetime();
            ini_set('session.gc_maxlifetime', (string) $lifetime);
            $siteUrl = Config::get('site_url', '');
            $isHttps = str_starts_with($siteUrl, 'https://');
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $isHttps,
            ]);
            session_start();
        }
    }

    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        if (!self::validateSession()) {
            unset($_SESSION['user_id'], $_SESSION['session_token'], $_SESSION['pending_2fa_user_id']);
            return null;
        }

        return Database::fetch('SELECT * FROM users WHERE id = ? AND status = ?', [$_SESSION['user_id'], 'active']);
    }

    public static function getUserByUsername(string $username): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(string $username, string $password): array
    {
        $user = Database::fetch('SELECT * FROM users WHERE username = ?', [$username]);

        if (!$user || !password_verify($password, $user['password'])) {
            Logger::log('auth_failure', null, 'Failed login for username: ' . $username);
            return ['success' => false, 'error' => 'Invalid username or password.', 'needs_2fa' => false];
        }

        if ($user['status'] !== 'active') {
            Logger::log('auth_failure', (int) $user['id'], 'Inactive account login attempt');
            return ['success' => false, 'error' => 'Your account is not active.', 'needs_2fa' => false];
        }

        $ip = Logger::getClientIp();
        $knownIp = Database::fetch(
            'SELECT * FROM user_ips WHERE user_id = ? AND ip_address = ? AND last_login > ?',
            [$user['id'], $ip, date('Y-m-d H:i:s', strtotime('-30 days'))]
        );

        if (!$knownIp) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Database::delete('user_2fa_codes', 'user_id = ?', [$user['id']]);
            Database::insert('user_2fa_codes', [
                'user_id'    => $user['id'],
                'code'       => password_hash($code, PASSWORD_DEFAULT),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $appName = Config::get('app_name', 'Elementary');
            $mailSent = Mailer::send(
                $user['email'],
                "{$appName} - Verification Code",
                "<p>Your verification code is: <strong>{$code}</strong></p><p>This code expires in 15 minutes.</p>"
            );

            if (!$mailSent) {
                Logger::log('auth_2fa_email_failed', (int) $user['id'], 'Failed to send 2FA email');
                $debug = Config::get('debug_mode', false);
                if ($debug) {
                    // In debug mode, get the actual error by re-attempting with exception
                    try {
                        require_once ELEMENTARY_ROOT . '/vendor/phpmailer/PHPMailer.php';
                        require_once ELEMENTARY_ROOT . '/vendor/phpmailer/SMTP.php';
                        require_once ELEMENTARY_ROOT . '/vendor/phpmailer/Exception.php';
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $smtpHost = Config::get('smtp_host', '');
                        if ($smtpHost) {
                            $mail->isSMTP();
                            $mail->Host       = $smtpHost;
                            $mail->Port       = (int) Config::get('smtp_port', 587);
                            $mail->SMTPAuth   = true;
                            $mail->Username   = Config::get('smtp_user', '');
                            $mail->Password   = Config::get('smtp_pass', '');
                            $mail->SMTPSecure = Config::get('smtp_port', 587) == 465 ? 'ssl' : 'tls';
                        }
                        $fromEmail = Config::get('smtp_from_email', '') ?: 'noreply@' . parse_url(Config::get('site_url', ''), PHP_URL_HOST);
                        $fromName  = Config::get('smtp_from_name', '') ?: $appName;
                        $mail->setFrom($fromEmail, $fromName);
                        $mail->addAddress($user['email']);
                        $mail->isHTML(true);
                        $mail->Subject = "{$appName} - Verification Code";
                        $mail->Body    = "<p>Your verification code is: <strong>{$code}</strong></p><p>This code expires in 15 minutes.</p>";
                        $mail->send();
                    } catch (\Throwable $e) {
                        return ['success' => false, 'error' => 'Mail error: ' . $e->getMessage(), 'needs_2fa' => false];
                    }
                } else {
                    return ['success' => false, 'error' => \__t('2fa_email_failed'), 'needs_2fa' => false];
                }
            }

            $_SESSION['pending_2fa_user_id'] = $user['id'];
            Logger::log('auth_2fa_sent', (int) $user['id'], '2FA code sent to email');
            return ['success' => false, 'error' => null, 'needs_2fa' => true];
        }

        self::completeLogin($user);
        return ['success' => true, 'error' => null, 'needs_2fa' => false];
    }

    public static function verify2fa(string $code): array
    {
        $userId = $_SESSION['pending_2fa_user_id'] ?? null;
        if (!$userId) {
            return ['success' => false, 'error' => 'No pending verification.'];
        }

        $record = Database::fetch(
            'SELECT * FROM user_2fa_codes WHERE user_id = ? AND expires_at > ? ORDER BY id DESC LIMIT 1',
            [$userId, date('Y-m-d H:i:s')]
        );

        if (!$record || !password_verify($code, $record['code'])) {
            Logger::log('auth_2fa_failure', (int) $userId, 'Invalid 2FA code');
            return ['success' => false, 'error' => 'Invalid or expired verification code.'];
        }

        Database::delete('user_2fa_codes', 'user_id = ?', [$userId]);
        unset($_SESSION['pending_2fa_user_id']);

        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        self::completeLogin($user);
        return ['success' => true, 'error' => null];
    }

    private static function completeLogin(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $ip = Logger::getClientIp();

        $existing = Database::fetch('SELECT id FROM user_ips WHERE user_id = ? AND ip_address = ?', [$user['id'], $ip]);
        if ($existing) {
            Database::update('user_ips', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('user_ips', [
                'user_id'    => $user['id'],
                'ip_address' => $ip,
                'last_login' => date('Y-m-d H:i:s'),
            ]);
        }

        Database::update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        self::registerSession((int) $user['id']);
        Logger::log('auth_success', (int) $user['id'], 'User logged in');
        Hooks::doAction('user_login', $user);
    }

    private static function registerSession(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = $token;
        $tokenHash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');

        Database::insert('user_sessions', [
            'user_id'       => $userId,
            'session_token' => $tokenHash,
            'ip_address'    => Logger::getClientIp(),
            'user_agent'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            'last_activity' => $now,
            'created_at'    => $now,
        ]);
    }

    private static function validateSession(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $userId = (int) $_SESSION['user_id'];
        $token = $_SESSION['session_token'] ?? null;

        if (!$token) {
            if (!self::sessionsTableExists()) {
                return true;
            }
            self::registerSession($userId);
            return true;
        }

        if (!self::sessionsTableExists()) {
            return true;
        }

        $session = Database::fetch(
            'SELECT id, last_activity, session_token FROM user_sessions WHERE user_id = ? AND session_token = ?',
            [$userId, hash('sha256', $token)]
        );

        if (!$session) {
            return false;
        }

        $lifetime = self::getSessionLifetime();
        if (strtotime($session['last_activity']) + $lifetime < time()) {
            Database::delete('user_sessions', 'id = ?', [(int) $session['id']]);
            return false;
        }

        self::touchSession((int) $session['id']);
        self::purgeExpiredSessions();
        return true;
    }

    private static function purgeExpiredSessions(): void
    {
        static $purged = false;
        if ($purged || !self::sessionsTableExists()) {
            return;
        }
        $purged = true;

        $cutoff = date('Y-m-d H:i:s', time() - self::getSessionLifetime());
        Database::delete('user_sessions', 'last_activity < ?', [$cutoff]);
    }

    private static function sessionsTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            Database::fetch('SELECT 1 FROM user_sessions LIMIT 1');
            $exists = true;
        } catch (\Throwable) {
            $exists = false;
        }

        return $exists;
    }

    private static function touchSession(int $sessionId): void
    {
        static $touched = false;
        if ($touched) {
            return;
        }

        Database::update('user_sessions', ['last_activity' => date('Y-m-d H:i:s')], 'id = ?', [$sessionId]);
        $touched = true;
    }

    public static function getSessions(int $userId): array
    {
        if (!self::sessionsTableExists()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC',
            [$userId]
        );
    }

    public static function getCurrentSessionToken(): ?string
    {
        return $_SESSION['session_token'] ?? null;
    }

    public static function revokeSession(int $userId, int $sessionId): array
    {
        $session = Database::fetch(
            'SELECT * FROM user_sessions WHERE id = ? AND user_id = ?',
            [$sessionId, $userId]
        );

        if (!$session) {
            return ['success' => false, 'redirect' => false];
        }

        $isCurrent = hash_equals(hash('sha256', (string) ($_SESSION['session_token'] ?? '')), (string) $session['session_token']);
        Database::delete('user_sessions', 'id = ?', [$sessionId]);

        if ($isCurrent) {
            self::logout();
        }

        return ['success' => true, 'redirect' => $isCurrent];
    }

    public static function revokeAllSessions(int $userId): void
    {
        Database::delete('user_sessions', 'user_id = ?', [$userId]);
        unset($_SESSION['session_token']);
        self::logout();
    }

    public static function parseBrowser(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'Unknown browser';
        }

        if (str_contains($userAgent, 'Firefox/')) {
            return 'Firefox';
        }
        if (str_contains($userAgent, 'Edg/')) {
            return 'Edge';
        }
        if (str_contains($userAgent, 'Chrome/')) {
            return 'Chrome';
        }
        if (str_contains($userAgent, 'Safari/') && !str_contains($userAgent, 'Chrome/')) {
            return 'Safari';
        }
        if (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera/')) {
            return 'Opera';
        }

        return 'Unknown browser';
    }

    public static function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $token = $_SESSION['session_token'] ?? null;

        if ($userId && $token && self::sessionsTableExists()) {
            Database::delete('user_sessions', 'user_id = ? AND session_token = ?', [(int) $userId, hash('sha256', $token)]);
        }

        if ($userId) {
            Logger::log('auth_logout', (int) $userId);
        }

        unset($_SESSION['user_id'], $_SESSION['pending_2fa_user_id'], $_SESSION['session_token']);
        session_regenerate_id(true);
    }

    public static function hasGroup(string ...$groups): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        return in_array($user['user_group'], $groups, true);
    }

    public static function canManageUsers(): bool
    {
        return self::hasGroup(self::GROUP_ADMIN);
    }

    public static function canAccessSettings(): bool
    {
        return self::hasGroup(self::GROUP_ADMIN);
    }

    public static function canManagePages(): bool
    {
        return self::hasGroup(self::GROUP_ADMIN, self::GROUP_STANDARD);
    }

    public static function canManagePosts(): bool
    {
        return self::hasGroup(self::GROUP_ADMIN, self::GROUP_STANDARD, self::GROUP_AUTHOR);
    }

    public static function canManageAllPosts(): bool
    {
        return self::hasGroup(self::GROUP_ADMIN, self::GROUP_STANDARD);
    }

    public static function canManageCategories(): bool
    {
        return self::hasGroup(self::GROUP_ADMIN, self::GROUP_STANDARD);
    }

    public static function canManageFiles(): bool
    {
        return self::hasGroup(self::GROUP_ADMIN, self::GROUP_STANDARD);
    }

    public static function isProtectedUser(int $userId): bool
    {
        $user = Database::fetch('SELECT username FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return false;
        }
        $protected = Config::get('protected_users', []);
        return in_array($user['username'], $protected, true);
    }

    public static function requestPasswordReset(string $username): bool
    {
        $user = Database::fetch('SELECT * FROM users WHERE username = ?', [$username]);
        if (!$user) {
            Logger::log('password_reset_request_unknown', null, 'Reset requested for: ' . $username);
            return true;
        }

        $token = bin2hex(random_bytes(32));
        Database::delete('password_resets', 'user_id = ?', [$user['id']]);
        Database::insert('password_resets', [
            'user_id'    => $user['id'],
            'token'      => password_hash($token, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $appName = Config::get('app_name', 'Elementary');
        $resetUrl = admin_url('reset-password?token=' . urlencode($token) . '&user=' . $user['id']);
        Mailer::send(
            $user['email'],
            "{$appName} - Password Reset",
            "<p>Click the link below to reset your password:</p><p><a href=\"{$resetUrl}\">Reset Password</a></p><p>This link expires in 1 hour.</p>"
        );

        Logger::log('password_reset_request', (int) $user['id']);
        return true;
    }

    public static function resetPassword(int $userId, string $token, string $newPassword): array
    {
        $record = Database::fetch(
            'SELECT * FROM password_resets WHERE user_id = ? AND expires_at > ? ORDER BY id DESC LIMIT 1',
            [$userId, date('Y-m-d H:i:s')]
        );

        if (!$record || !password_verify($token, $record['token'])) {
            return ['success' => false, 'error' => 'Invalid or expired reset link.'];
        }

        Database::update('users', [
            'password'   => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$userId]);

        Database::delete('password_resets', 'user_id = ?', [$userId]);
        Logger::log('password_reset_complete', $userId);
        return ['success' => true, 'error' => null];
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect(admin_url('login'));
        }
    }

    public static function getUserTimezone(): string
    {
        $user = self::user();
        if ($user && !empty($user['timezone']) && $user['timezone'] !== 'system') {
            return $user['timezone'];
        }
        return Config::get('time_zone', 'UTC');
    }
}
