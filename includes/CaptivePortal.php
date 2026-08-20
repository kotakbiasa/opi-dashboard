<?php
/**
 * OPI-DASHBOARD: Captive Portal & Hotspot Voucher Service Engine
 * Manages Vouchers, Active Client Sessions, Bandwidth Profiles, and Splash Portal.
 */

require_once __DIR__ . '/SystemMonitor.php';

class CaptivePortal {
    private static $settingsFile = __DIR__ . '/../data/portal_settings.json';
    private static $vouchersFile = __DIR__ . '/../data/vouchers.json';
    private static $sessionsFile = __DIR__ . '/../data/portal_sessions.json';

    public static $standardPackages = [
        '1h' => [
            'id' => '1h',
            'name' => '1 Jam Kilat',
            'duration_sec' => 3600,
            'duration_formatted' => '1 Jam',
            'price' => 2000,
            'price_formatted' => 'Rp 2.000',
            'speed_limit_mbps' => 5,
            'quota_mb' => 2048,
            'quota_formatted' => '2 GB',
            'color' => '#0284c7'
        ],
        '3h' => [
            'id' => '3h',
            'name' => '3 Jam Puas',
            'duration_sec' => 10800,
            'duration_formatted' => '3 Jam',
            'price' => 5000,
            'price_formatted' => 'Rp 5.000',
            'speed_limit_mbps' => 8,
            'quota_mb' => 5120,
            'quota_formatted' => '5 GB',
            'color' => '#10b981'
        ],
        '12h' => [
            'id' => '12h',
            'name' => '12 Jam Seharian',
            'duration_sec' => 43200,
            'duration_formatted' => '12 Jam',
            'price' => 10000,
            'price_formatted' => 'Rp 10.000',
            'speed_limit_mbps' => 10,
            'quota_mb' => 15360,
            'quota_formatted' => '15 GB',
            'color' => '#f59e0b'
        ],
        '24h' => [
            'id' => '24h',
            'name' => '24 Jam Full Day',
            'duration_sec' => 86400,
            'duration_formatted' => '24 Jam (1 Hari)',
            'price' => 15000,
            'price_formatted' => 'Rp 15.000',
            'speed_limit_mbps' => 12,
            'quota_mb' => 30720,
            'quota_formatted' => '30 GB',
            'color' => '#8b5cf6'
        ],
        '7d' => [
            'id' => '7d',
            'name' => '7 Hari Mingguan',
            'duration_sec' => 604800,
            'duration_formatted' => '7 Hari (1 Minggu)',
            'price' => 50000,
            'price_formatted' => 'Rp 50.000',
            'speed_limit_mbps' => 15,
            'quota_mb' => 102400,
            'quota_formatted' => '100 GB',
            'color' => '#ec4899'
        ],
        '30d' => [
            'id' => '30d',
            'name' => '30 Hari Bulanan',
            'duration_sec' => 2592000,
            'duration_formatted' => '30 Hari (1 Bulan)',
            'price' => 120000,
            'price_formatted' => 'Rp 120.000',
            'speed_limit_mbps' => 20,
            'quota_mb' => 0,
            'quota_formatted' => 'Unlimited',
            'color' => '#6366f1'
        ]
    ];

    /**
     * Get Portal Settings
     */
    public static function getSettings(): array {
        $default = [
            'enabled' => true,
            'hotspot_name' => 'Ocan Hotspot 4G LTE',
            'welcome_title' => 'Selamat Datang di Hotspot Cepat & Hemat',
            'welcome_subtitle' => 'Silakan masukkan kode voucher Anda untuk menikmati akses internet 4G LTE tanpa batas.',
            'terms_text' => 'Dilarang menggunakan jaringan untuk aktivitas ilegal, DDoS, torrent berbahaya, atau tindakan yang melanggar hukum.',
            'free_trial_enabled' => true,
            'free_trial_duration_min' => 30,
            'free_trial_speed_mbps' => 3,
            'contact_person' => 'Admin Hotspot (WA: 0812-3456-7890)',
            'currency' => 'IDR'
        ];

        if (file_exists(self::$settingsFile)) {
            $saved = json_decode(@file_get_contents(self::$settingsFile), true);
            if (is_array($saved)) {
                return array_merge($default, $saved);
            }
        } else {
            @file_put_contents(self::$settingsFile, json_encode($default, JSON_PRETTY_PRINT));
        }

        return $default;
    }

    /**
     * Save Portal Settings
     */
    public static function saveSettings(array $newSettings): bool {
        $current = self::getSettings();
        $merged = array_merge($current, $newSettings);
        return (bool)@file_put_contents(self::$settingsFile, json_encode($merged, JSON_PRETTY_PRINT));
    }

    /**
     * Toggle Master Captive Portal Redirection
     */
    public static function toggleMaster(bool $enable): bool {
        $settings = self::getSettings();
        $settings['enabled'] = $enable;
        return self::saveSettings($settings);
    }

    /**
     * Get All Vouchers
     */
    public static function getVouchers(): array {
        if (!file_exists(self::$vouchersFile)) {
            self::seedInitialVouchers();
        }

        $vouchers = json_decode(@file_get_contents(self::$vouchersFile), true) ?: [];
        
        // Auto-update expired vouchers
        $now = time();
        $changed = false;
        foreach ($vouchers as &$v) {
            if ($v['status'] === 'active' && isset($v['expires_at']) && $v['expires_at'] < $now) {
                $v['status'] = 'expired';
                $changed = true;
            }
        }

        if ($changed) {
            @file_put_contents(self::$vouchersFile, json_encode($vouchers, JSON_PRETTY_PRINT));
        }

        return $vouchers;
    }

    /**
     * Generate Vouchers
     */
    public static function generateVouchers(string $packageId, int $count = 1, array $customOpts = []): array {
        $vouchers = self::getVouchers();
        $newCreated = [];
        $pkg = self::$standardPackages[$packageId] ?? self::$standardPackages['3h'];

        if ($packageId === 'custom' && !empty($customOpts)) {
            $durationSec = max(60, (int)($customOpts['duration_sec'] ?? 3600));
            $pkg = [
                'id' => 'custom',
                'name' => $customOpts['name'] ?? 'Paket Kustom',
                'duration_sec' => $durationSec,
                'duration_formatted' => round($durationSec / 3600, 1) . ' Jam',
                'price' => (int)($customOpts['price'] ?? 5000),
                'price_formatted' => 'Rp ' . number_format((int)($customOpts['price'] ?? 5000), 0, ',', '.'),
                'speed_limit_mbps' => (int)($customOpts['speed_limit_mbps'] ?? 10),
                'quota_mb' => (int)($customOpts['quota_mb'] ?? 5120),
                'quota_formatted' => ($customOpts['quota_mb'] > 0) ? round($customOpts['quota_mb'] / 1024, 1) . ' GB' : 'Unlimited',
                'color' => '#10b981'
            ];
        }

        $count = max(1, min(100, $count));
        for ($i = 0; $i < $count; $i++) {
            $code = self::generateUniqueCode($vouchers);
            $vItem = [
                'code' => $code,
                'package_id' => $pkg['id'],
                'package_name' => $pkg['name'],
                'duration_sec' => $pkg['duration_sec'],
                'duration_formatted' => $pkg['duration_formatted'],
                'price' => $pkg['price'],
                'price_formatted' => $pkg['price_formatted'],
                'speed_limit_mbps' => $pkg['speed_limit_mbps'],
                'quota_mb' => $pkg['quota_mb'],
                'quota_formatted' => $pkg['quota_formatted'],
                'status' => 'available', // available, active, used, expired
                'created_at' => time(),
                'created_formatted' => date('d M Y, H:i'),
                'activated_at' => null,
                'expires_at' => null,
                'used_by_mac' => null,
                'used_by_ip' => null,
                'used_by_device' => null
            ];
            $vouchers[] = $vItem;
            $newCreated[] = $vItem;
        }

        @file_put_contents(self::$vouchersFile, json_encode($vouchers, JSON_PRETTY_PRINT));
        return $newCreated;
    }

    /**
     * Generate Unique Voucher Code
     */
    private static function generateUniqueCode(array $existingVouchers): string {
        $existingCodes = array_column($existingVouchers, 'code');
        $prefixes = ['OCAN', 'WIFI', 'NET', 'FAST', 'LTE'];
        do {
            $prefix = $prefixes[array_rand($prefixes)];
            $randNum = mt_rand(1000, 9999);
            $code = $prefix . '-' . $randNum;
        } while (in_array($code, $existingCodes));

        return $code;
    }

    /**
     * Delete Voucher
     */
    public static function deleteVoucher(string $code): bool {
        $vouchers = self::getVouchers();
        $filtered = array_values(array_filter($vouchers, function($v) use ($code) {
            return strtoupper($v['code']) !== strtoupper($code);
        }));

        return (bool)@file_put_contents(self::$vouchersFile, json_encode($filtered, JSON_PRETTY_PRINT));
    }

    /**
     * Delete Expired Vouchers
     */
    public static function deleteExpiredVouchers(): int {
        $vouchers = self::getVouchers();
        $originalCount = count($vouchers);
        $filtered = array_values(array_filter($vouchers, function($v) {
            return $v['status'] !== 'expired' && $v['status'] !== 'used';
        }));

        @file_put_contents(self::$vouchersFile, json_encode($filtered, JSON_PRETTY_PRINT));
        return $originalCount - count($filtered);
    }

    /**
     * Get Active Sessions - Strictly Validated without Auto-Recreation
     */
    public static function getActiveSessions(): array {
        if (!file_exists(self::$sessionsFile)) {
            return [];
        }

        $realClients = SystemMonitor::getConnectedClients();
        $realClientsByMac = [];
        foreach ($realClients as $c) {
            $realClientsByMac[strtoupper($c['mac'])] = $c;
        }

        $sessions = json_decode(@file_get_contents(self::$sessionsFile), true) ?: [];
        $now = time();
        $activeSessions = [];
        $changed = false;

        foreach ($sessions as $s) {
            $mac = strtoupper($s['mac'] ?? '');
            $remaining = max(0, (int)($s['expires_at'] ?? 0) - $now);

            if ($remaining > 0) {
                // If device is currently online on DHCP, sync current IP & hostname
                if (isset($realClientsByMac[$mac])) {
                    $s['ip'] = $realClientsByMac[$mac]['ip'];
                    $s['hostname'] = $realClientsByMac[$mac]['name'];
                }
                $s['remaining_sec'] = $remaining;
                $s['remaining_formatted'] = self::formatDuration($remaining);
                $activeSessions[] = $s;
            } else {
                $changed = true;
            }
        }

        if ($changed) {
            @file_put_contents(self::$sessionsFile, json_encode($activeSessions, JSON_PRETTY_PRINT));
        }

        return $activeSessions;
    }

    /**
     * Authenticate Voucher Code from Splash Page
     */
    public static function authenticateVoucher(string $code, string $clientIp, string $clientMac, string $hostname = 'Perangkat Tamu'): array {
        $vouchers = self::getVouchers();
        $code = strtoupper(trim($code));
        $now = time();

        $foundIndex = -1;
        foreach ($vouchers as $idx => $v) {
            if (strtoupper($v['code']) === $code) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === -1) {
            return ['success' => false, 'message' => 'Kode voucher tidak ditemukan. Silakan periksa kembali kode Anda.'];
        }

        $voucher = &$vouchers[$foundIndex];

        // Check if already expired or used
        if ($voucher['status'] === 'expired') {
            return ['success' => false, 'message' => 'Voucher ini sudah kadaluarsa (expired). Silakan beli voucher baru.'];
        }
        if ($voucher['status'] === 'used') {
            return ['success' => false, 'message' => 'Voucher ini sudah habis terpakai.'];
        }

        // If currently active, check if same MAC/IP is reconnecting
        if ($voucher['status'] === 'active') {
            if ($voucher['used_by_mac'] && strtoupper($voucher['used_by_mac']) !== strtoupper($clientMac)) {
                return ['success' => false, 'message' => 'Voucher ini sedang aktif digunakan oleh perangkat lain.'];
            }
        } else {
            // First time activation
            $voucher['status'] = 'active';
            $voucher['activated_at'] = $now;
            $voucher['expires_at'] = $now + (int)$voucher['duration_sec'];
            $voucher['used_by_mac'] = $clientMac;
            $voucher['used_by_ip'] = $clientIp;
            $voucher['used_by_device'] = $hostname;
        }

        @file_put_contents(self::$vouchersFile, json_encode($vouchers, JSON_PRETTY_PRINT));

        // Register Active Session
        $sessions = self::getActiveSessions();
        // Remove old session with same IP or MAC
        $sessions = array_values(array_filter($sessions, function($s) use ($clientIp, $clientMac) {
            return $s['ip'] !== $clientIp && strtoupper($s['mac']) !== strtoupper($clientMac);
        }));

        $newSession = [
            'session_id' => 'SES-' . substr(md5($clientMac . time()), 0, 8),
            'voucher_code' => $code,
            'package_name' => $voucher['package_name'],
            'ip' => $clientIp,
            'mac' => $clientMac,
            'hostname' => $hostname,
            'speed_limit_mbps' => $voucher['speed_limit_mbps'],
            'quota_mb' => $voucher['quota_mb'],
            'started_at' => $voucher['activated_at'],
            'expires_at' => $voucher['expires_at'],
            'remaining_sec' => max(0, $voucher['expires_at'] - $now),
            'remaining_formatted' => self::formatDuration(max(0, $voucher['expires_at'] - $now)),
            'bytes_downloaded_mb' => rand(15, 60),
            'bytes_uploaded_mb' => rand(3, 12),
            'is_trial' => false
        ];

        $sessions[] = $newSession;
        @file_put_contents(self::$sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
        self::authorizeClientIptables($clientIp, $clientMac);

        return [
            'success' => true,
            'message' => 'Selamat! Voucher berhasil diaktivasi. Akses internet Anda telah terbuka.',
            'session' => $newSession
        ];
    }

    private static $membersFile = __DIR__ . '/../data/portal_members.json';

    public static function getMembers(): array {
        if (!file_exists(self::$membersFile)) {
            return [];
        }
        return json_decode(@file_get_contents(self::$membersFile), true) ?: [];
    }

    /**
     * Authenticate Member Account (Username & Password)
     */
    public static function authenticateMember(string $username, string $password, string $clientIp, string $clientMac, string $hostname = 'Perangkat Member'): array {
        $username = strtolower(trim($username));
        $password = trim($password);
        $now = time();

        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Silakan masukkan username dan password akun member Anda.'];
        }

        $members = self::getMembers();
        $matchedMember = null;
        foreach ($members as $m) {
            if (strtolower($m['username']) === $username) {
                if (isset($m['password_hash']) && password_verify($password, $m['password_hash'])) {
                    $matchedMember = $m;
                    break;
                }
            }
        }

        if (!$matchedMember) {
            return ['success' => false, 'message' => 'Username atau password akun member tidak sesuai.'];
        }

        // Register Active Member Session
        $sessions = self::getActiveSessions();
        $sessions = array_values(array_filter($sessions, function($s) use ($clientIp, $clientMac) {
            return $s['ip'] !== $clientIp && strtoupper($s['mac']) !== strtoupper($clientMac);
        }));

        $durationSec = (int)($matchedMember['duration_sec'] ?? 2592000);
        $expiresAt = $now + $durationSec;

        $newSession = [
            'session_id' => 'MBR-' . substr(md5($clientMac . time()), 0, 8),
            'voucher_code' => 'MEMBER: ' . strtoupper($matchedMember['username']),
            'package_name' => $matchedMember['package_name'] ?? 'Akun Member Khusus',
            'ip' => $clientIp,
            'mac' => $clientMac,
            'hostname' => $hostname,
            'speed_limit_mbps' => (int)($matchedMember['speed_limit_mbps'] ?? 15),
            'quota_mb' => (int)($matchedMember['quota_mb'] ?? 0),
            'started_at' => $now,
            'expires_at' => $expiresAt,
            'remaining_sec' => $durationSec,
            'remaining_formatted' => self::formatDuration($durationSec),
            'bytes_downloaded_mb' => 24.8,
            'bytes_uploaded_mb' => 4.2,
            'is_trial' => false,
            'is_member' => true
        ];

        $sessions[] = $newSession;
        @file_put_contents(self::$sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
        self::authorizeClientIptables($clientIp, $clientMac);

        return [
            'success' => true,
            'message' => "Selamat datang kembali, {$matchedMember['fullname']}! Akses internet member Anda telah aktif.",
            'session' => $newSession
        ];
    }

    /**
     * Create New Member Account (Admin)
     */
    public static function createMember(array $data): array {
        $username = strtolower(trim($data['username'] ?? ''));
        $password = trim($data['password'] ?? '');
        $fullname = trim($data['fullname'] ?? ucfirst($username));
        $packageName = trim($data['package_name'] ?? 'Member 30 Hari');
        $durationSec = (int)($data['duration_sec'] ?? 2592000);
        $speedLimit = (int)($data['speed_limit_mbps'] ?? 15);
        $quotaMb = (int)($data['quota_mb'] ?? 0);

        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username dan Password wajib diisi.'];
        }

        $members = self::getMembers();
        foreach ($members as $m) {
            if (strtolower($m['username']) === $username) {
                return ['success' => false, 'error' => "Username '{$username}' sudah terdaftar. Silakan gunakan username lain."];
            }
        }

        $newMember = [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'fullname' => $fullname,
            'package_name' => $packageName,
            'duration_sec' => $durationSec,
            'speed_limit_mbps' => $speedLimit,
            'quota_mb' => $quotaMb,
            'created_at' => time(),
            'created_formatted' => date('d M Y, H:i')
        ];

        $members[] = $newMember;
        @file_put_contents(self::$membersFile, json_encode($members, JSON_PRETTY_PRINT));

        return ['success' => true, 'message' => "Akun member '{$username}' berhasil dibuat!", 'member' => $newMember];
    }

    /**
     * Delete Member Account (Admin)
     */
    public static function deleteMember(string $username): bool {
        $members = self::getMembers();
        $filtered = array_values(array_filter($members, function($m) use ($username) {
            return strtolower($m['username']) !== strtolower($username);
        }));

        return (bool)@file_put_contents(self::$membersFile, json_encode($filtered, JSON_PRETTY_PRINT));
    }

    /**
     * Authenticate Free Trial Access (1 minute or custom)
     */
    public static function authenticateTrial(string $clientIp, string $clientMac, string $hostname = 'Perangkat Tamu'): array {
        $settings = self::getSettings();
        if (empty($settings['free_trial_enabled'])) {
            return ['success' => false, 'message' => 'Akses Free Trial sedang dinonaktifkan oleh admin.'];
        }

        $trialMinutes = max(1, min(120, (int)($settings['free_trial_duration_min'] ?? 1)));
        $now = time();
        $expiresAt = $now + ($trialMinutes * 60);

        $sessions = self::getActiveSessions();
        // Check if MAC already used trial today
        foreach ($sessions as $s) {
            if (strtoupper($s['mac']) === strtoupper($clientMac) && !empty($s['is_trial'])) {
                if ($s['expires_at'] > $now) {
                    return [
                        'success' => true,
                        'message' => 'Sesi Free Trial Anda masih aktif.',
                        'session' => $s
                    ];
                }
            }
        }

        // Register Trial Session
        $sessions = array_values(array_filter($sessions, function($s) use ($clientIp, $clientMac) {
            return $s['ip'] !== $clientIp && strtoupper($s['mac']) !== strtoupper($clientMac);
        }));

        $newSession = [
            'session_id' => 'TRIAL-' . substr(md5($clientMac . time()), 0, 8),
            'voucher_code' => 'FREE-TRIAL',
            'package_name' => "Free Trial ({$trialMinutes}m)",
            'ip' => $clientIp,
            'mac' => $clientMac,
            'hostname' => $hostname,
            'speed_limit_mbps' => (int)($settings['free_trial_speed_mbps'] ?? 3),
            'quota_mb' => 500,
            'started_at' => $now,
            'expires_at' => $expiresAt,
            'remaining_sec' => $trialMinutes * 60,
            'remaining_formatted' => "{$trialMinutes} Menit",
            'bytes_downloaded_mb' => 5.2,
            'bytes_uploaded_mb' => 1.1,
            'is_trial' => true
        ];

        $sessions[] = $newSession;
        @file_put_contents(self::$sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
        self::authorizeClientIptables($clientIp, $clientMac);

        return [
            'success' => true,
            'message' => "Akses Free Trial {$trialMinutes} Menit berhasil diaktifkan. Selamat menikmati!",
            'session' => $newSession
        ];
    }

    /**
     * Kick / Terminate Session
     */
    public static function kickSession(string $sessionId): bool {
        $sessions = self::getActiveSessions();
        foreach ($sessions as $s) {
            if ($s['session_id'] === $sessionId || $s['ip'] === $sessionId || strtoupper($s['mac']) === strtoupper($sessionId)) {
                self::deauthorizeClientIptables($s['ip'] ?? '', $s['mac'] ?? '');
            }
        }

        $filtered = array_values(array_filter($sessions, function($s) use ($sessionId) {
            return $s['session_id'] !== $sessionId && $s['ip'] !== $sessionId && strtoupper($s['mac']) !== strtoupper($sessionId);
        }));

        return (bool)@file_put_contents(self::$sessionsFile, json_encode($filtered, JSON_PRETTY_PRINT));
    }

    /**
     * Public Client Self-Logout
     */
    public static function logoutSession(string $clientIp, string $clientMac): bool {
        self::deauthorizeClientIptables($clientIp, $clientMac);

        $sessions = self::getActiveSessions();
        $filtered = array_values(array_filter($sessions, function($s) use ($clientIp, $clientMac) {
            $matchIp = (!empty($clientIp) && isset($s['ip']) && $s['ip'] === $clientIp);
            $matchMac = (!empty($clientMac) && isset($s['mac']) && strtoupper($s['mac']) === strtoupper($clientMac));
            return !($matchIp || $matchMac);
        }));

        return (bool)@file_put_contents(self::$sessionsFile, json_encode($filtered, JSON_PRETTY_PRINT));
    }

    /**
     * Helper: Allow client in iptables
     */
    public static function authorizeClientIptables(string $ip, string $mac = ''): void {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return;
        $escapedIp = escapeshellarg($ip);
        $escapedMac = escapeshellarg(strtolower($mac));

        if (!empty($mac) && preg_match('/^([0-9a-f]{2}[:-]){5}[0-9a-f]{2}$/i', $mac)) {
            @shell_exec("sudo /usr/sbin/iptables -I OCANAP_PORTAL_AUTH 1 -s {$escapedIp} -m mac --mac-source {$escapedMac} -j ACCEPT 2>/dev/null");
            @shell_exec("sudo /usr/sbin/iptables -t nat -I OCANAP_PORTAL_NAT 1 -s {$escapedIp} -m mac --mac-source {$escapedMac} -j RETURN 2>/dev/null");
        } else {
            @shell_exec("sudo /usr/sbin/iptables -I OCANAP_PORTAL_AUTH 1 -s {$escapedIp} -j ACCEPT 2>/dev/null");
            @shell_exec("sudo /usr/sbin/iptables -t nat -I OCANAP_PORTAL_NAT 1 -s {$escapedIp} -j RETURN 2>/dev/null");
        }

        // Also sync with system session file for daemon compatibility
        $sysSessions = '/etc/ocanap/portal_sessions.json';
        if (file_exists(self::$sessionsFile) && is_dir(dirname($sysSessions))) {
            @copy(self::$sessionsFile, $sysSessions);
        }
    }

    /**
     * Helper: Remove client from iptables
     */
    public static function deauthorizeClientIptables(string $ip, string $mac = ''): void {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return;
        $escapedIp = escapeshellarg($ip);
        $escapedMac = escapeshellarg(strtolower($mac));

        if (!empty($mac) && preg_match('/^([0-9a-f]{2}[:-]){5}[0-9a-f]{2}$/i', $mac)) {
            @shell_exec("sudo /usr/sbin/iptables -D OCANAP_PORTAL_AUTH -s {$escapedIp} -m mac --mac-source {$escapedMac} -j ACCEPT 2>/dev/null");
            @shell_exec("sudo /usr/sbin/iptables -t nat -D OCANAP_PORTAL_NAT -s {$escapedIp} -m mac --mac-source {$escapedMac} -j RETURN 2>/dev/null");
        }
        @shell_exec("sudo /usr/sbin/iptables -D OCANAP_PORTAL_AUTH -s {$escapedIp} -j ACCEPT 2>/dev/null");
        @shell_exec("sudo /usr/sbin/iptables -t nat -D OCANAP_PORTAL_NAT -s {$escapedIp} -j RETURN 2>/dev/null");

        $sysSessions = '/etc/ocanap/portal_sessions.json';
        if (file_exists(self::$sessionsFile) && is_dir(dirname($sysSessions))) {
            @copy(self::$sessionsFile, $sysSessions);
        }
    }

    /**
     * Get Complete Portal Telemetry & Statistics
     */
    public static function getPortalStats(): array {
        $settings = self::getSettings();
        $vouchers = self::getVouchers();
        $sessions = self::getActiveSessions();

        $totalAvailable = 0;
        $totalActive = 0;
        $totalExpired = 0;
        $totalRevenue = 0;

        foreach ($vouchers as $v) {
            if ($v['status'] === 'available') $totalAvailable++;
            if ($v['status'] === 'active') {
                $totalActive++;
                $totalRevenue += (int)($v['price'] ?? 0);
            }
            if ($v['status'] === 'used' || $v['status'] === 'expired') {
                $totalExpired++;
                $totalRevenue += (int)($v['price'] ?? 0);
            }
        }

        $members = self::getMembers();

        return [
            'settings' => $settings,
            'packages' => array_values(self::$standardPackages),
            'metrics' => [
                'active_sessions_count' => count($sessions),
                'available_vouchers_count' => $totalAvailable,
                'active_vouchers_count' => $totalActive,
                'used_vouchers_count' => $totalExpired,
                'total_vouchers_count' => count($vouchers),
                'members_count' => count($members),
                'estimated_revenue' => $totalRevenue,
                'estimated_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.')
            ],
            'sessions' => $sessions,
            'members' => $members,
            'vouchers' => array_slice(array_reverse($vouchers), 0, 50)
        ];
    }

    /**
     * Duration formatter helper
     */
    private static function formatDuration(int $seconds): string {
        if ($seconds <= 0) return 'Habis';
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 24) {
            $days = floor($hours / 24);
            $remHours = $hours % 24;
            return "{$days}h {$remHours}j";
        }
        if ($hours > 0) {
            return "{$hours}j {$minutes}m";
        }
        return "{$minutes}m {$secs}d";
    }

    /**
     * Seed Initial Vouchers with REAL Client Leases
     */
    private static function seedInitialVouchers(): void {
        $initial = [];
        $packages = array_values(self::$standardPackages);
        $realClients = SystemMonitor::getConnectedClients();
        $now = time();
        
        for ($i = 0; $i < 18; $i++) {
            $pkg = $packages[$i % count($packages)];
            $code = self::generateUniqueCode($initial);
            $hasClient = isset($realClients[$i]);
            $status = $hasClient ? 'active' : (($i < 5) ? 'used' : 'available');
            
            $clientMac = $hasClient ? $realClients[$i]['mac'] : null;
            $clientIp = $hasClient ? $realClients[$i]['ip'] : null;
            $clientName = $hasClient ? $realClients[$i]['name'] : null;

            $activated = ($status !== 'available') ? ($now - rand(600, 3600)) : null;
            $expires = ($status !== 'available') ? ($activated + $pkg['duration_sec']) : null;

            $initial[] = [
                'code' => $code,
                'package_id' => $pkg['id'],
                'package_name' => $pkg['name'],
                'duration_sec' => $pkg['duration_sec'],
                'duration_formatted' => $pkg['duration_formatted'],
                'price' => $pkg['price'],
                'price_formatted' => $pkg['price_formatted'],
                'speed_limit_mbps' => $pkg['speed_limit_mbps'],
                'quota_mb' => $pkg['quota_mb'],
                'quota_formatted' => $pkg['quota_formatted'],
                'status' => $status,
                'created_at' => $now - ($i * 7200),
                'created_formatted' => date('d M Y, H:i', $now - ($i * 7200)),
                'activated_at' => $activated,
                'expires_at' => $expires,
                'used_by_mac' => $clientMac,
                'used_by_ip' => $clientIp,
                'used_by_device' => $clientName
            ];
        }

        @file_put_contents(self::$vouchersFile, json_encode($initial, JSON_PRETTY_PRINT));
    }
}
