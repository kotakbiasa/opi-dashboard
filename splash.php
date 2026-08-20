<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/CaptivePortal.php';

$settings = CaptivePortal::getSettings();
$packages = array_values(CaptivePortal::$standardPackages);

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

// Check if client currently has an active session
$sessions = CaptivePortal::getActiveSessions();
$currentSession = null;
foreach ($sessions as $s) {
    if ($s['ip'] === $clientIp || strtoupper($s['mac']) === strtoupper($clientMac)) {
        $currentSession = $s;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['hotspot_name']) ?> - Login Hotspot</title>
    <meta name="description" content="Halaman Login Voucher & Member Hotspot 4G LTE Orange Pi Gateway">
    <link rel="stylesheet" href="css/splash.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
    <style>
        body.splash-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--bg-main);
        }

        .splash-card-wrap {
            width: 100%;
            max-width: 460px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--nm-raised);
            border: 1.5px solid rgba(255, 255, 255, 0.9);
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .splash-header-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .splash-logo-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--bg-card);
            box-shadow: var(--nm-raised);
            border: 1.5px solid rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .splash-device-capsule {
            background: var(--bg-card);
            box-shadow: var(--nm-raised-sm);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: var(--radius-pill);
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .voucher-input-hero {
            width: 100%;
            font-family: monospace;
            font-size: 18px;
            font-weight: 800;
            text-align: center;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 13px 16px;
            border-radius: var(--radius-md);
            background: var(--bg-card);
            border: 1.5px solid rgba(255, 255, 255, 0.9);
            box-shadow: var(--nm-inset-sm);
            color: var(--text-heading);
            outline: none;
            transition: var(--transition-fast);
        }

        .voucher-input-hero:focus {
            border-color: var(--color-primary);
            box-shadow: var(--nm-inset-sm), 0 0 12px rgba(255, 122, 0, 0.35);
        }

        .member-input-field {
            width: 100%;
            font-size: 13px;
            font-weight: 600;
            padding: 11px 14px;
            border-radius: var(--radius-md);
            background: var(--bg-card);
            border: 1.5px solid rgba(255, 255, 255, 0.9);
            box-shadow: var(--nm-inset-sm);
            color: var(--text-heading);
            outline: none;
            transition: var(--transition-fast);
        }

        .member-input-field:focus {
            border-color: #0284c7;
            box-shadow: var(--nm-inset-sm), 0 0 10px rgba(2, 132, 199, 0.35);
        }

        .login-pane {
            display: none;
            animation: fadeInLogin 0.2s ease-in-out;
        }

        .login-pane.active {
            display: block;
        }

        @keyframes fadeInLogin {
            from { opacity: 0; transform: translateY(3px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="splash-page">

    <div class="splash-card-wrap">
        <!-- 1. Branding & Header -->
        <div class="splash-header-brand">
            <div class="splash-logo-circle">
                <img src="assets/orange-pi-logo.png" alt="Logo Hotspot" style="width: 46px; height: 46px; object-fit: contain;">
            </div>
            <h1 style="font-size: 21px; font-weight: 800; color: var(--text-heading); margin-bottom: 4px;">
                <?= htmlspecialchars($settings['hotspot_name']) ?>
            </h1>
            <p style="font-size: 12px; color: var(--text-muted); line-height: 1.45; max-width: 360px;">
                <?= htmlspecialchars($settings['welcome_subtitle']) ?>
            </p>
        </div>

        <!-- 2. Client Device Info Capsule -->
        <div class="splash-device-capsule">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span class="pulse-green-dot"></span>
                <span>IP: <strong style="color: var(--text-heading);"><?= htmlspecialchars($clientIp) ?></strong></span>
            </div>
            <div>
                <span>MAC: <strong style="color: var(--text-heading); font-family: monospace;"><?= htmlspecialchars(substr($clientMac, -8)) ?></strong></span>
            </div>
        </div>

        <?php if ($currentSession): ?>
            <!-- 3A. ACTIVE SESSION CONNECTED VIEW -->
            <div style="background: var(--bg-card); box-shadow: var(--nm-raised-sm); border: 1.5px solid rgba(16, 185, 129, 0.4); border-radius: var(--radius-lg); padding: 20px; text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 6px; color: #059669; font-size: 13px; font-weight: 800; margin-bottom: 8px;">
                    <span class="pulse-green-dot"></span>
                    <span>INTERNET SUDAH TERHUBUNG</span>
                </div>

                <div style="margin: 12px 0;">
                    <span style="font-size: 11px; color: var(--text-muted); display: block; text-transform: uppercase;">Sisa Waktu Akses</span>
                    <strong id="sessionCountdown" style="font-size: 34px; font-weight: 900; font-family: monospace; color: #059669;">
                        <?= $currentSession['remaining_formatted'] ?>
                    </strong>
                </div>

                <div style="display: flex; justify-content: space-around; font-size: 11.5px; color: var(--text-muted); border-top: 1px dashed rgba(182, 198, 220, 0.5); padding-top: 10px; margin-top: 10px;">
                    <span>Paket: <strong style="color: var(--text-heading);"><?= $currentSession['package_name'] ?></strong></span>
                    <span>Speed: <strong style="color: var(--text-heading);"><?= $currentSession['speed_limit_mbps'] ?> Mbps</strong></span>
                </div>

                <div style="margin-top: 16px; display: flex; gap: 10px;">
                    <a href="https://www.google.com" target="_blank" class="btn-primary-neumorphic" style="flex: 1; padding: 12px; text-decoration: none; font-size: 13px; justify-content: center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                        <span>Buka Internet</span>
                    </a>

                    <button type="button" class="btn-new-device" style="padding: 12px 18px; font-size: 12.5px; color: #ef4444; justify-content: center; cursor: pointer;" onclick="handleClientLogout()" title="Keluar / Putuskan Akses">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        <span>Logout</span>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- 3B. DUAL LOGIN SYSTEM (VOUCHER & MEMBER ACCOUNT) -->
            <div>
                <!-- Neumorphic Login Mode Switcher -->
                <div class="nm-segmented-switch" style="width: 100%; display: flex; margin-bottom: 14px;">
                    <button type="button" class="nm-seg-btn active" id="btnTabVoucher" onclick="switchLoginMode('voucher')" style="flex: 1; justify-content: center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4V6a2 2 0 0 0-2-2H4zm5 3a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1zm0 4a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1zm0 4a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1z"/></svg>
                        <span>Kode Voucher</span>
                    </button>
                    <button type="button" class="nm-seg-btn" id="btnTabMember" onclick="switchLoginMode('member')" style="flex: 1; justify-content: center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        <span>Akun Member</span>
                    </button>
                </div>

                <!-- PANE 1: VOUCHER CODE LOGIN FORM -->
                <div id="paneVoucher" class="login-pane active">
                    <form id="formVoucherLogin" onsubmit="handleVoucherSubmit(event)">
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div>
                                <label for="inputVoucherCode" style="font-size: 11px; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 6px; text-align: center; text-transform: uppercase; letter-spacing: 0.5px;">
                                    MASUKKAN KODE VOUCHER
                                </label>
                                <input type="text" id="inputVoucherCode" class="voucher-input-hero" placeholder="CONTOH: OCAN-8821" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" required>
                            </div>

                            <button type="submit" class="btn-primary-neumorphic" id="btnSubmitVoucher" style="width: 100%; padding: 12px; font-size: 13.5px; justify-content: center;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M13.13 2.5a.75.75 0 0 0-1.26 0l-4.5 7.5a.75.75 0 0 0 .65 1.13h2.48v6.12a.75.75 0 0 0 1.5 0v-6.12h2.48a.75.75 0 0 0 .65-1.13l-4.5-7.5z"/></svg>
                                <span>Hubungkan Voucher</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- PANE 2: MEMBER USERNAME & PASSWORD LOGIN FORM -->
                <div id="paneMember" class="login-pane">
                    <form id="formMemberLogin" onsubmit="handleMemberSubmit(event)">
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div>
                                <label for="inputMemberUser" style="font-size: 11px; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px; text-transform: uppercase;">
                                    Username Member
                                </label>
                                <input type="text" id="inputMemberUser" class="member-input-field" placeholder="Username akun member..." autocomplete="username" required>
                            </div>

                            <div>
                                <label for="inputMemberPass" style="font-size: 11px; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px; text-transform: uppercase;">
                                    Password
                                </label>
                                <input type="password" id="inputMemberPass" class="member-input-field" placeholder="Password akun member..." autocomplete="current-password" required>
                            </div>

                            <button type="submit" class="btn-primary-neumorphic" id="btnSubmitMember" style="width: 100%; padding: 12px; font-size: 13.5px; justify-content: center; margin-top: 4px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                                <span>Login Akun Member</span>
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($settings['free_trial_enabled'])): ?>
                    <!-- Free Trial Button -->
                    <div style="text-align: center; margin-top: 14px; padding-top: 12px; border-top: 1px dashed rgba(182, 198, 220, 0.45);">
                        <button type="button" class="btn-new-device" style="width: 100%; padding: 11px; font-size: 12px; justify-content: center; cursor: pointer;" onclick="handleFreeTrialClick()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#8b5cf6"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm4.2 14.2L11 13V7h1.5v5.2l4.5 2.7-.8 1.3z"/></svg>
                            <span>Coba Gratis <?= $settings['free_trial_duration_min'] ?? 1 ?> Menit (Free Trial)</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Contact & WhatsApp Purchasing Footer -->
        <div style="text-align: center; font-size: 11.5px; color: var(--text-muted); border-top: 1px dashed rgba(182, 198, 220, 0.45); padding-top: 14px;">
            <?php if (!empty($settings['contact_person'])): ?>
                <div style="margin-bottom: 6px; display: inline-flex; align-items: center; gap: 6px; background: var(--bg-card); box-shadow: var(--nm-raised-sm); border: 1px solid rgba(255, 255, 255, 0.85); border-radius: var(--radius-pill); padding: 4px 12px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="#25d366"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    <span>Beli Voucher: <strong style="color: var(--text-heading);"><?= htmlspecialchars($settings['contact_person']) ?></strong></span>
                </div>
            <?php endif; ?>
            <div style="font-size: 10px; color: var(--text-muted); opacity: 0.8; margin-top: 4px;">
                Orange Pi Zero 2 Gateway Hotspot &bull; Protected by AdGuard Home DNS
            </div>
            <div style="margin-top: 10px;">
                <a href="http://192.168.1.2" style="font-size: 11px; color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: var(--radius-pill); background: rgba(182, 198, 220, 0.2);">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="#0284c7"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    <span>Masuk ke Dasbor Admin (192.168.1.2)</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Splash Page Script -->
    <script>
        function switchLoginMode(mode) {
            document.getElementById('btnTabVoucher')?.classList.toggle('active', mode === 'voucher');
            document.getElementById('btnTabMember')?.classList.toggle('active', mode === 'member');
            document.getElementById('paneVoucher')?.classList.toggle('active', mode === 'voucher');
            document.getElementById('paneMember')?.classList.toggle('active', mode === 'member');
        }

        async function handleVoucherSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('inputVoucherCode');
            const btn = document.getElementById('btnSubmitVoucher');
            if (!input) return;

            const code = input.value.trim().toUpperCase();
            if (!code) return;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Memeriksa Voucher...</span>';
            }

            try {
                const res = await fetch('api.php?action=auth_voucher', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        voucher_code: code,
                        ip: '<?= htmlspecialchars($clientIp) ?>',
                        mac: '<?= htmlspecialchars($clientMac) ?>',
                        hostname: navigator.userAgent.includes('Mobile') ? 'Smartphone Tamu' : 'Laptop Tamu'
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(data.message || 'Kode voucher tidak valid.', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus. Silakan coba lagi.', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-rocket-takeoff-fill"></i><span>Hubungkan Voucher</span>';
                }
            }
        }

        async function handleMemberSubmit(e) {
            e.preventDefault();
            const userEl = document.getElementById('inputMemberUser');
            const passEl = document.getElementById('inputMemberPass');
            const btn = document.getElementById('btnSubmitMember');
            if (!userEl || !passEl) return;

            const username = userEl.value.trim();
            const password = passEl.value.trim();
            if (!username || !password) return;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Memverifikasi Akun...</span>';
            }

            try {
                const res = await fetch('api.php?action=auth_member', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: username,
                        password: password,
                        ip: '<?= htmlspecialchars($clientIp) ?>',
                        mac: '<?= htmlspecialchars($clientMac) ?>',
                        hostname: navigator.userAgent.includes('Mobile') ? `Smartphone Member (${username})` : `Laptop Member (${username})`
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(data.message || 'Username atau password salah.', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i><span>Login Akun Member</span>';
                }
            }
        }

        async function handleFreeTrialClick() {
            if (!confirm('Gunakan akses Free Trial 1 Menit sekarang?')) return;
            try {
                const res = await fetch('api.php?action=auth_trial', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ip: '<?= htmlspecialchars($clientIp) ?>',
                        mac: '<?= htmlspecialchars($clientMac) ?>',
                        hostname: navigator.userAgent.includes('Mobile') ? 'Smartphone Tamu (Trial)' : 'Laptop Tamu (Trial)'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(data.message || 'Gagal mengaktifkan free trial', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            }
        }

        async function handleClientLogout() {
            if (!confirm('Apakah Anda yakin ingin memutuskan akses internet (Logout)?')) return;
            try {
                const res = await fetch('api.php?action=auth_logout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ip: '<?= htmlspecialchars($clientIp) ?>',
                        mac: '<?= htmlspecialchars($clientMac) ?>'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    showToast(data.message || 'Gagal logout', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            }
        }

        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<span>${message}</span>`;
            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = '0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        <?php if ($currentSession && !empty($currentSession['remaining_sec'])): ?>
            // Live Countdown Timer for Active Session
            let remSeconds = <?= (int)$currentSession['remaining_sec'] ?>;
            const countdownEl = document.getElementById('sessionCountdown');

            function tickCountdown() {
                if (remSeconds <= 0) {
                    if (countdownEl) countdownEl.textContent = 'Habis';
                    showToast('Waktu akses internet Anda telah habis! Silakan login kembali.', 'error');
                    setTimeout(() => window.location.reload(), 1500);
                    return;
                }
                remSeconds--;

                const hours = Math.floor(remSeconds / 3600);
                const minutes = Math.floor((remSeconds % 3600) / 60);
                const secs = remSeconds % 60;

                const hh = String(hours).padStart(2, '0');
                const mm = String(minutes).padStart(2, '0');
                const ss = String(secs).padStart(2, '0');

                if (countdownEl) countdownEl.textContent = `${hh}:${mm}:${ss}`;
            }

            setInterval(tickCountdown, 1000);
        <?php endif; ?>
    </script>
</body>
</html>
