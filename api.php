<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';
require_once __DIR__ . '/includes/CaptivePortal.php';
require_once __DIR__ . '/includes/FileManager.php';
require_once __DIR__ . '/includes/ServicesManager.php';
require_once __DIR__ . '/includes/SettingsManager.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$rawInput = file_get_contents('php://input');
$postData = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $postData = $decoded;
    }
}
$postData = array_merge($_POST, $postData);

if (empty($action) && isset($postData['action'])) {
    $action = $postData['action'];
}

// Client helper for IP & MAC detection
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '192.168.1.100';
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}
// Validate IP address to prevent command injection
if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
    $clientIp = '192.168.1.100';
}

$clientMac = '00:00:00:00:00:00';
$escapedIp = escapeshellarg($clientIp);
$arpOutput = @shell_exec("ip neigh show {$escapedIp} 2>/dev/null");
if ($arpOutput && preg_match('/lladdr\s+([0-9a-f:]{17})/i', $arpOutput, $m)) {
    $clientMac = strtoupper($m[1]);
} else {
    $clientMac = 'E4:5F:01:' . substr(md5($clientIp), 0, 2) . ':' . substr(md5($clientIp), 2, 2) . ':' . substr(md5($clientIp), 4, 2);
}

// =========================================================================
// PUBLIC ACTIONS (No Admin Auth Required: Login, Logout, Captive Portal Splash)
// =========================================================================
if ($action === 'login') {
    $username = trim($postData['username'] ?? '');
    $password = trim($postData['password'] ?? '');
    $remember = !empty($postData['remember']);

    if (Auth::login($username, $password, $remember)) {
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => 'index.php'
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid username or password'
        ]);
    }
    exit;
}

if ($action === 'logout') {
    Auth::logout();
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully',
        'redirect' => 'index.php'
    ]);
    exit;
}

// Public Splash Info
if ($action === 'get_splash_info') {
    $settings = CaptivePortal::getSettings();
    $packages = array_values(CaptivePortal::$standardPackages);
    $sessions = CaptivePortal::getActiveSessions();

    $mySession = null;
    foreach ($sessions as $s) {
        if ($s['ip'] === $clientIp || strtoupper($s['mac']) === strtoupper($clientMac)) {
            $mySession = $s;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'client' => [
            'ip' => $clientIp,
            'mac' => $clientMac,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ],
        'settings' => $settings,
        'packages' => $packages,
        'session' => $mySession
    ]);
    exit;
}

// Public Voucher Authentication (Client Splash Page)
if ($action === 'auth_voucher') {
    $code = trim($postData['voucher_code'] ?? $postData['code'] ?? '');
    $hostname = trim($postData['hostname'] ?? 'Smartphone');
    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Silakan masukkan kode voucher Anda.']);
        exit;
    }

    $res = CaptivePortal::authenticateVoucher($code, $clientIp, $clientMac, $hostname);
    if ($res['success']) {
        echo json_encode($res);
    } else {
        http_response_code(400);
        echo json_encode($res);
    }
    exit;
}

// Public Free Trial Authentication (Client Splash Page)
if ($action === 'auth_trial') {
    $hostname = trim($postData['hostname'] ?? 'Smartphone');
    $res = CaptivePortal::authenticateTrial($clientIp, $clientMac, $hostname);
    if ($res['success']) {
        echo json_encode($res);
    } else {
        http_response_code(400);
        echo json_encode($res);
    }
    exit;
}

// Public Member Account Authentication (Client Splash Page)
if ($action === 'auth_member') {
    $username = trim($postData['username'] ?? '');
    $password = trim($postData['password'] ?? '');
    $hostname = trim($postData['hostname'] ?? 'Smartphone Member');
    $targetIp = trim($postData['ip'] ?? $clientIp);
    $targetMac = trim($postData['mac'] ?? $clientMac);

    $res = CaptivePortal::authenticateMember($username, $password, $targetIp, $targetMac, $hostname);
    if ($res['success']) {
        echo json_encode($res);
    } else {
        http_response_code(400);
        echo json_encode($res);
    }
    exit;
}

// Public Client Self-Logout (Splash Page)
if ($action === 'auth_logout') {
    $targetIp = trim($postData['ip'] ?? $clientIp);
    $targetMac = trim($postData['mac'] ?? $clientMac);
    $res = CaptivePortal::logoutSession($targetIp, $targetMac);
    echo json_encode([
        'success' => true,
        'message' => 'Anda telah berhasil logout dari jaringan hotspot.'
    ]);
    exit;
}

// =========================================================================
// PROTECTED ADMIN ACTIONS: Require Login
// =========================================================================
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Please login.'
    ]);
    exit;
}

try {
    switch ($action) {
        // --- Core System & Hardware Telemetry ---
        case 'get_system_stats':
        case 'get_state':
            $state = SystemMonitor::getFullState();
            echo json_encode([
                'success' => true,
                'data' => $state
            ]);
            break;

        case 'test_ping':
            $ping = SystemMonitor::getPingLatency(true);
            echo json_encode([
                'success' => true,
                'data' => $ping,
                'message' => "Ping: {$ping['ms']} ms ({$ping['status']})"
            ]);
            break;

        case 'toggle_led':
            $led = $postData['led'] ?? 'green';
            $state = isset($postData['status']) ? (bool)$postData['status'] : true;
            $ok = SystemMonitor::setLed($led, $state);
            $freshState = SystemMonitor::getFullState();
            echo json_encode([
                'success' => $ok,
                'message' => ucfirst($led) . " LED set to " . ($state ? 'ON' : 'OFF'),
                'leds' => $freshState['leds'],
                'data' => $freshState
            ]);
            break;

        case 'set_cpu_governor':
            $gov = $postData['governor'] ?? 'ondemand';
            $ok = SystemMonitor::setCpuGovernor($gov);
            $freshState = SystemMonitor::getFullState();
            echo json_encode([
                'success' => $ok,
                'message' => "CPU Governor changed to {$gov}",
                'cpu' => $freshState['cpu'],
                'data' => $freshState
            ]);
            break;

        case 'restart_service':
            $svc = $postData['service'] ?? 'hostapd';
            $allowed = ['hostapd', 'dnsmasq', 'tailscaled', 'systemd-networkd'];
            if (!in_array($svc, $allowed)) {
                throw new Exception("Service not allowed for restart");
            }
            @shell_exec("systemctl restart {$svc} 2>/dev/null");
            $freshState = SystemMonitor::getFullState();
            echo json_encode([
                'success' => true,
                'message' => "Service {$svc} restarted successfully",
                'services' => $freshState['services'],
                'data' => $freshState
            ]);
            break;

        case 'update_hotspot_settings':
            $ssid = trim($postData['ssid'] ?? 'OcanAP');
            $pass = trim($postData['password'] ?? '');
            $channel = (int)($postData['channel'] ?? 6);

            if (strlen($ssid) < 1 || strlen($ssid) > 32) {
                throw new Exception("Nama SSID harus antara 1 sampai 32 karakter.");
            }
            if (!empty($pass) && strlen($pass) < 8) {
                throw new Exception("Sandi Wi-Fi minimal 8 karakter.");
            }

            $hostapdConfPath = '/etc/hostapd/hostapd.conf';
            if (is_writable($hostapdConfPath)) {
                $conf = @file_get_contents($hostapdConfPath);
                if ($conf !== false) {
                    $conf = preg_replace('/^ssid=.*/m', "ssid={$ssid}", $conf);
                    $conf = preg_replace('/^channel=.*/m', "channel={$channel}", $conf);
                    if (!empty($pass)) {
                        $conf = preg_replace('/^wpa_passphrase=.*/m', "wpa_passphrase={$pass}", $conf);
                    }
                    @file_put_contents($hostapdConfPath, $conf);
                    @shell_exec("systemctl restart hostapd 2>/dev/null");
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Pengaturan Hotspot berhasil diperbarui: SSID '{$ssid}' pada Saluran {$channel}!",
                'data' => [
                    'ssid' => $ssid,
                    'channel' => $channel
                ]
            ]);
            break;

        case 'reboot_system':
            echo json_encode([
                'success' => true,
                'message' => "System reboot sequence initiated..."
            ]);
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            @shell_exec("sync && (sleep 1 && reboot) > /dev/null 2>&1 &");
            break;

        case 'toggle_adguard_protection':
            $enabled = isset($postData['enabled']) ? (bool)$postData['enabled'] : true;
            $payload = json_encode(['enabled' => $enabled]);
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 1.5
                ]
            ];
            @file_get_contents('http://127.0.0.1:3000/control/protection', false, stream_context_create($opts));
            $freshState = SystemMonitor::getFullState();
            echo json_encode([
                'success' => true,
                'message' => "Proteksi AdGuard Home " . ($enabled ? "diaktifkan" : "dijeda sementara"),
                'adguard' => $freshState['adguard'],
                'data' => $freshState
            ]);
            break;

        case 'get_adguard_stats':
            $adg = SystemMonitor::getAdguardInfo();
            echo json_encode([
                'success' => true,
                'data' => $adg
            ]);
            break;

        // --- Captive Portal & Voucher Management Endpoints ---
        case 'get_portal_stats':
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'generate_vouchers':
            $packageId = trim($postData['package_id'] ?? '3h');
            $count = max(1, min(100, (int)($postData['count'] ?? 1)));
            $customOpts = $postData['custom_opts'] ?? [];

            $created = CaptivePortal::generateVouchers($packageId, $count, $customOpts);
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => true,
                'message' => "Berhasil membuat {$count} voucher baru (" . ($created[0]['package_name'] ?? '') . ")!",
                'vouchers_created' => $created,
                'data' => $stats
            ]);
            break;

        case 'delete_voucher':
            $code = trim($postData['code'] ?? '');
            if (empty($code)) throw new Exception("Kode voucher tidak boleh kosong.");
            $ok = CaptivePortal::deleteVoucher($code);
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => $ok,
                'message' => "Voucher {$code} berhasil dihapus.",
                'data' => $stats
            ]);
            break;

        case 'delete_expired_vouchers':
            $deletedCount = CaptivePortal::deleteExpiredVouchers();
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => true,
                'message' => "Berhasil membersihkan {$deletedCount} voucher kadaluarsa.",
                'deleted_count' => $deletedCount,
                'data' => $stats
            ]);
            break;

        case 'kick_portal_session':
            $sessionId = trim($postData['session_id'] ?? '');
            if (empty($sessionId)) throw new Exception("ID Sesi tidak boleh kosong.");
            $ok = CaptivePortal::kickSession($sessionId);
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => $ok,
                'message' => "Sesi klien {$sessionId} berhasil diputuskan.",
                'data' => $stats
            ]);
            break;

        case 'create_member':
            $res = CaptivePortal::createMember($postData);
            if (!$res['success']) {
                throw new Exception($res['error'] ?? 'Gagal membuat akun member.');
            }
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => true,
                'message' => $res['message'],
                'member' => $res['member'],
                'data' => $stats
            ]);
            break;

        case 'delete_member':
            $username = trim($postData['username'] ?? '');
            if (empty($username)) throw new Exception("Username tidak boleh kosong.");
            $ok = CaptivePortal::deleteMember($username);
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => $ok,
                'message' => "Akun member '{$username}' berhasil dihapus.",
                'data' => $stats
            ]);
            break;

        case 'toggle_portal_master':
            $enabled = isset($postData['enabled']) ? (bool)$postData['enabled'] : true;
            $ok = CaptivePortal::toggleMaster($enabled);
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => $ok,
                'message' => "Captive Portal " . ($enabled ? "diaktifkan (Redirection On)" : "dinonaktifkan (Bypass Mode)"),
                'enabled' => $enabled,
                'data' => $stats
            ]);
            break;

        case 'save_portal_settings':
            $newSettings = $postData['settings'] ?? [];
            $ok = CaptivePortal::saveSettings($newSettings);
            $stats = CaptivePortal::getPortalStats();
            echo json_encode([
                'success' => $ok,
                'message' => "Pengaturan Captive Portal berhasil disimpan!",
                'data' => $stats
            ]);
            break;

        // --- File Manager Endpoints ---
        case 'list_files':
            $targetPath = trim($_GET['path'] ?? $postData['path'] ?? '/root/opi-dashboard');
            $list = FileManager::listDirectory($targetPath);
            $disk = FileManager::getDiskStats();
            echo json_encode([
                'success' => true,
                'data' => $list,
                'disk' => $disk
            ]);
            break;

        case 'read_file':
            $targetPath = trim($_GET['path'] ?? $postData['path'] ?? '');
            $res = FileManager::readFile($targetPath);
            if (!$res['success']) {
                throw new Exception($res['error']);
            }
            echo json_encode($res);
            break;

        case 'save_file':
            $targetPath = trim($postData['path'] ?? '');
            $content = $postData['content'] ?? '';
            $res = FileManager::saveFile($targetPath, $content);
            if (!$res['success']) {
                throw new Exception($res['error']);
            }
            echo json_encode($res);
            break;

        case 'create_file':
            $dirPath = trim($postData['dir'] ?? '/root/opi-dashboard');
            $fileName = trim($postData['filename'] ?? '');
            $content = $postData['content'] ?? '';
            $res = FileManager::createFile($dirPath, $fileName, $content);
            if (!$res['success']) {
                throw new Exception($res['error']);
            }
            echo json_encode($res);
            break;

        case 'create_folder':
            $dirPath = trim($postData['dir'] ?? '/root/opi-dashboard');
            $folderName = trim($postData['folder_name'] ?? '');
            $res = FileManager::createFolder($dirPath, $folderName);
            if (!$res['success']) {
                throw new Exception($res['error']);
            }
            echo json_encode($res);
            break;

        case 'rename_item':
            $oldPath = trim($postData['old_path'] ?? '');
            $newName = trim($postData['new_name'] ?? '');
            $res = FileManager::renameItem($oldPath, $newName);
            if (!$res['success']) {
                throw new Exception($res['error']);
            }
            echo json_encode($res);
            break;

        case 'delete_item':
            $targetPath = trim($postData['path'] ?? '');
            $res = FileManager::deleteItem($targetPath);
            if (!$res['success']) {
                throw new Exception($res['error']);
            }
            echo json_encode($res);
            break;

        case 'upload_file':
            $dirPath = trim($_POST['dir'] ?? '/root/opi-dashboard');
            $file = $_FILES['file'] ?? [];
            $res = FileManager::uploadFile($dirPath, $file);
            if (!$res['success']) {
                throw new Exception($res['error']);
            }
            echo json_encode($res);
            break;

        // --- Rclone Cloud Endpoints ---
        case 'get_rclone_status':
            $status = FileManager::getRcloneStatus();
            echo json_encode(['success' => true, 'data' => $status]);
            break;

        case 'list_rclone_files':
            $remotePath = trim($_GET['remote_path'] ?? $postData['remote_path'] ?? '');
            if (empty($remotePath)) throw new Exception("Jalur remote cloud tidak boleh kosong.");
            $list = FileManager::listRclonePath($remotePath);
            echo json_encode(['success' => true, 'data' => $list]);
            break;

        case 'sync_to_rclone':
            $local = trim($postData['local_path'] ?? '');
            $remote = trim($postData['remote_path'] ?? '');
            if (empty($local) || empty($remote)) throw new Exception("Jalur lokal dan remote wajib diisi.");
            $res = FileManager::syncLocalToCloud($local, $remote);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal sinkronisasi ke cloud.');
            echo json_encode($res);
            break;

        case 'download_from_rclone':
            $remote = trim($postData['remote_path'] ?? '');
            $localDir = trim($postData['local_dir'] ?? '/root/opi-dashboard');
            if (empty($remote)) throw new Exception("Jalur remote cloud wajib diisi.");
            $res = FileManager::downloadCloudToLocal($remote, $localDir);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal mengunduh dari cloud.');
            echo json_encode($res);
            break;

        case 'get_rclone_config':
            $cfg = FileManager::getRcloneConfigContent();
            echo json_encode(['success' => true, 'config' => $cfg]);
            break;

        case 'save_rclone_config':
            $cfg = $postData['config'] ?? '';
            $ok = FileManager::saveRcloneConfigContent($cfg);
            if (!$ok) throw new Exception("Gagal menyimpan rclone.conf");
            $status = FileManager::getRcloneStatus();
            echo json_encode([
                'success' => true,
                'message' => 'Konfigurasi rclone.conf berhasil disimpan!',
                'data' => $status
            ]);
            break;

        case 'create_rclone_remote':
            $name = trim($postData['name'] ?? '');
            $type = trim($postData['type'] ?? '');
            $options = $postData['options'] ?? [];
            if (empty($name) || empty($type)) throw new Exception("Nama remote dan tipe cloud wajib diisi.");
            $res = FileManager::createRcloneRemote($name, $type, $options);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal membuat remote.');
            $status = FileManager::getRcloneStatus();
            echo json_encode([
                'success' => true,
                'message' => $res['message'],
                'data' => $status
            ]);
            break;

        case 'delete_rclone_remote':
            $name = trim($postData['name'] ?? '');
            if (empty($name)) throw new Exception("Nama remote wajib diisi.");
            $res = FileManager::deleteRcloneRemote($name);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal menghapus remote.');
            $status = FileManager::getRcloneStatus();
            echo json_encode([
                'success' => true,
                'message' => $res['message'],
                'data' => $status
            ]);
            break;

        case 'test_rclone_remote':
            $name = trim($postData['name'] ?? $_GET['name'] ?? '');
            if (empty($name)) throw new Exception("Nama remote wajib diisi.");
            $res = FileManager::testRcloneRemote($name);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal terhubung ke remote.');
            echo json_encode($res);
            break;

        // --- System Services Endpoints ---
        case 'get_services_status':
            $data = ServicesManager::getServicesList();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'control_service':
            $service = trim($postData['service'] ?? '');
            $srvAction = trim($postData['service_action'] ?? '');
            if (empty($service) || empty($srvAction)) throw new Exception("Parameter layanan dan aksi wajib diisi.");
            $res = ServicesManager::controlService($service, $srvAction);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal mengubah status layanan.');
            echo json_encode($res);
            break;

        case 'get_service_logs':
            $service = trim($_GET['service'] ?? $postData['service'] ?? '');
            $lines = (int)($_GET['lines'] ?? $postData['lines'] ?? 50);
            if (empty($service)) throw new Exception("Nama layanan wajib ditentukan.");
            $res = ServicesManager::getServiceLogs($service, $lines);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal membaca log layanan.');
            echo json_encode($res);
            break;

        // --- System Settings Endpoints ---
        case 'get_settings_state':
            $data = SettingsManager::getSettingsState();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'set_cpu_governor':
            $gov = trim($postData['governor'] ?? '');
            if (empty($gov)) throw new Exception("Governor CPU tidak boleh kosong.");
            $res = SettingsManager::setCpuGovernor($gov);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal mengubah governor.');
            echo json_encode($res);
            break;

        case 'set_led_trigger':
            $led = trim($postData['led'] ?? 'green');
            $trig = trim($postData['trigger'] ?? 'none');
            $res = SettingsManager::setLedTrigger($led, $trig);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal mengubah trigger LED.');
            echo json_encode($res);
            break;

        case 'set_timezone':
            $tz = trim($postData['timezone'] ?? '');
            if (empty($tz)) throw new Exception("Zona waktu wajib diisi.");
            $res = SettingsManager::setTimezone($tz);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal mengatur zona waktu.');
            echo json_encode($res);
            break;

        case 'set_hostname':
            $host = trim($postData['hostname'] ?? '');
            if (empty($host)) throw new Exception("Nama hostname wajib diisi.");
            $res = SettingsManager::setHostname($host);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal mengatur hostname.');
            echo json_encode($res);
            break;

        case 'change_admin_password':
            $pass = trim($postData['new_password'] ?? '');
            if (strlen($pass) < 4) throw new Exception("Kata sandi baru minimal 4 karakter.");
            $ok = Auth::changePassword($pass);
            if (!$ok) throw new Exception("Gagal menyimpan kata sandi baru.");
            echo json_encode(['success' => true, 'message' => 'Kata sandi admin berhasil diperbarui!']);
            break;

        case 'reboot_system':
            $res = SettingsManager::rebootSystem();
            echo json_encode($res);
            break;

        case 'shutdown_system':
            $res = SettingsManager::shutdownSystem();
            echo json_encode($res);
            break;

        case 'save_telegram_config':
            $res = SettingsManager::saveTelegramConfig($postData);
            echo json_encode($res);
            break;

        case 'send_test_telegram':
            $token = trim($postData['token'] ?? '');
            $chatId = trim($postData['chat_id'] ?? '');
            $res = SettingsManager::sendTestTelegramMessage($token, $chatId);
            if (!$res['success']) throw new Exception($res['error'] ?? 'Gagal mengirim pesan uji coba.');
            echo json_encode($res);
            break;

        default:
            $state = SystemMonitor::getFullState();
            echo json_encode(['success' => true, 'data' => $state]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
