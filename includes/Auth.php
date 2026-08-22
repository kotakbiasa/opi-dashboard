<?php

class Auth {
    private static string $configFile = __DIR__ . '/../data/auth.json';
    private static string $attemptsFile = __DIR__ . '/../data/login_attempts.json';
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 60;
    private const REMEMBER_TTL = 86400 * 30;

    /** Read admin config from disk; bootstrap default admin/admin on first run */
    private static function getConfig(): array {
        if (!file_exists(self::$configFile)) {
            self::saveConfig([
                'username' => 'admin',
                'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        $conf = json_decode((string)@file_get_contents(self::$configFile), true);
        return is_array($conf) ? $conf : [];
    }

    /** Persist admin config to disk */
    private static function saveConfig(array $conf): bool {
        @mkdir(dirname(self::$configFile), 0770, true);
        return @file_put_contents(self::$configFile, json_encode($conf, JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }

    /** Load per-IP login attempt counters (server-side, cannot be reset by client) */
    private static function loadAttempts(): array {
        if (!file_exists(self::$attemptsFile)) return [];
        $data = json_decode((string)@file_get_contents(self::$attemptsFile), true);
        return is_array($data) ? $data : [];
    }

    private static function saveAttempts(array $attempts): void {
        @mkdir(dirname(self::$attemptsFile), 0770, true);
        // Prune entries older than the lockout window
        $cutoff = time() - self::LOGIN_LOCKOUT_SECONDS - 60;
        foreach ($attempts as $ip => $info) {
            if (($info['last'] ?? 0) < $cutoff) unset($attempts[$ip]);
        }
        @file_put_contents(self::$attemptsFile, json_encode($attempts), LOCK_EX);
    }

    private static function clientKey(): string {
        return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    /** Generate CSRF token for current session */
    public static function csrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Verify CSRF token from request */
    public static function csrfVerify(?string $token = null): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $expected = $_SESSION['csrf_token'] ?? '';
        if (empty($expected)) return false;
        $provided = $token ?? ($_POST['csrf_token'] ?? '');
        return hash_equals($expected, (string)$provided);
    }

    /** Check if login is rate-limited for this client IP */
    public static function isRateLimited(): bool {
        $attempts = self::loadAttempts();
        $key = self::clientKey();
        $info = $attempts[$key] ?? null;
        if (!$info) return false;

        if (($info['count'] ?? 0) >= self::MAX_LOGIN_ATTEMPTS) {
            if (time() - ($info['last'] ?? 0) < self::LOGIN_LOCKOUT_SECONDS) {
                return true;
            }
            unset($attempts[$key]);
            self::saveAttempts($attempts);
        }
        return false;
    }

    /** Record a failed login attempt for this client IP */
    public static function recordFailedAttempt(): void {
        $attempts = self::loadAttempts();
        $key = self::clientKey();
        $info = $attempts[$key] ?? ['count' => 0, 'last' => 0];
        $info['count']++;
        $info['last'] = time();
        $attempts[$key] = $info;
        self::saveAttempts($attempts);
    }

    /** Reset login attempts (called on successful login) */
    public static function resetLoginAttempts(): void {
        $attempts = self::loadAttempts();
        unset($attempts[self::clientKey()]);
        self::saveAttempts($attempts);
    }

    /**
     * Validate remember-me cookie against the server-side stored hash.
     * The raw token is never stored; only its hash lives in data/auth.json.
     */
    private static function validateRememberToken(?string $rawToken): bool {
        if (empty($rawToken) || !is_string($rawToken)) return false;
        $conf = self::getConfig();
        $storedHash = $conf['remember_token_hash'] ?? '';
        if (empty($storedHash)) return false;
        return hash_equals($storedHash, hash('sha256', $rawToken));
    }

    /** Issue a new remember-me token: set cookie + persist its hash server-side */
    private static function issueRememberToken(): void {
        $raw = bin2hex(random_bytes(32));
        $conf = self::getConfig();
        $conf['remember_token_hash'] = hash('sha256', $raw);
        $conf['remember_created_at'] = date('c');
        if (self::saveConfig($conf)) {
            setcookie('opi_token', $raw, [
                'expires' => time() + self::REMEMBER_TTL,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['opi_user'])) {
            return true;
        }

        if (!empty($_COOKIE['opi_token']) && self::validateRememberToken($_COOKIE['opi_token'])) {
            $_SESSION['opi_user'] = self::getConfig()['username'] ?? 'admin';
            return true;
        }

        // Invalid/expired remember token: force it out of the browser
        if (!empty($_COOKIE['opi_token'])) {
            setcookie('opi_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true]);
        }

        return false;
    }

    public static function login(string $username, string $password, bool $remember = true): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = trim($username);
        $password = (string)$password;

        if ($username === '' || $password === '') {
            return false;
        }

        $conf = self::getConfig();
        if (isset($conf['username'], $conf['password_hash'])
            && hash_equals((string)$conf['username'], $username)
            && password_verify($password, (string)$conf['password_hash'])) {
            $_SESSION['opi_user'] = $username;
            // Prevent session fixation
            session_regenerate_id(true);
            // Reset rate limit
            self::resetLoginAttempts();
            // Regenerate CSRF token on login
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            if ($remember) {
                self::issueRememberToken();
            }
            return true;
        }

        // Record failed attempt for rate limiting
        self::recordFailedAttempt();
        return false;
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Revoke remember token server-side
        $conf = self::getConfig();
        if (isset($conf['remember_token_hash'])) {
            unset($conf['remember_token_hash'], $conf['remember_created_at']);
            self::saveConfig($conf);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        setcookie('opi_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true]);
        session_destroy();
    }

    public static function changePassword(string $newPassword): bool {
        $newPassword = trim($newPassword);
        if (strlen($newPassword) < 8) return false;
        $conf = self::getConfig();
        $conf['username'] = $conf['username'] ?? 'admin';
        $conf['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $conf['updated_at'] = date('Y-m-d H:i:s');
        return self::saveConfig($conf);
    }
}
