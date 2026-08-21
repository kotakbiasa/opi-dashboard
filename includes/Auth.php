<?php

class Auth {
    private static string $configFile = __DIR__ . '/../data/auth.json';
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 60;

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
        return hash_equals($expected, $provided);
    }

    /** Check if login is rate-limited */
    public static function isRateLimited(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lastTime = $_SESSION['last_attempt_time'] ?? 0;
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            if (time() - $lastTime < self::LOGIN_LOCKOUT_SECONDS) {
                return true;
            }
            // Reset after lockout expires
            $_SESSION['login_attempts'] = 0;
        }
        return false;
    }

    /** Record a failed login attempt */
    public static function recordFailedAttempt(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_attempt_time'] = time();
    }

    /** Reset login attempts (called on successful login) */
    public static function resetLoginAttempts(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;
    }

    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['opi_user'])) {
            return true;
        }

        if (!empty($_COOKIE['opi_token'])) {
            $_SESSION['opi_user'] = 'admin';
            return true;
        }

        return false;
    }

    public static function login(string $username, string $password, bool $remember = true): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = trim($username);
        $password = trim($password);

        if (empty($username) || empty($password)) {
            return false;
        }

        // Custom config check
        if (file_exists(self::$configFile)) {
            $conf = json_decode(file_get_contents(self::$configFile), true);
            if (is_array($conf) && isset($conf['username'], $conf['password_hash'])) {
                if ($username === $conf['username'] && password_verify($password, $conf['password_hash'])) {
                    $_SESSION['opi_user'] = $username;
                    // Prevent session fixation
                    session_regenerate_id(true);
                    // Reset rate limit
                    self::resetLoginAttempts();
                    // Regenerate CSRF token on login
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    if ($remember) {
                        setcookie('opi_token', bin2hex(random_bytes(32)), time() + (86400 * 30), '/', '', false, true);
                    }
                    return true;
                }
            }
        }

        // Record failed attempt for rate limiting
        self::recordFailedAttempt();
        return false;
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        setcookie('opi_token', '', time() - 3600, '/');
        session_destroy();
    }

    public static function changePassword(string $newPassword): bool {
        $newPassword = trim($newPassword);
        if (strlen($newPassword) < 4) return false;
        @mkdir(dirname(self::$configFile), 0755, true);
        $data = [
            'username' => 'admin',
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        return @file_put_contents(self::$configFile, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
}
