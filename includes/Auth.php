<?php

class Auth {
    private static string $configFile = __DIR__ . '/../data/auth.json';

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
                    if ($remember) {
                        setcookie('opi_token', bin2hex(random_bytes(32)), time() + (86400 * 30), '/', '', false, true);
                    }
                    return true;
                }
            }
        }

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
