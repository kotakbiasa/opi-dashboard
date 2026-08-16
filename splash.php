<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/CaptivePortal.php';

$settings = CaptivePortal::getSettings();
$packages = array_values(CaptivePortal::$standardPackages);

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '192.168.1.100';
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

$clientMac = '00:00:00:00:00:00';
$arpOutput = @shell_exec("ip neigh show {$clientIp} 2>/dev/null");
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
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
                        <i class="bi bi-globe"></i>
                        <span>Buka Internet</span>
                    </a>

                    <button type="button" class="btn-new-device" style="padding: 12px 18px; font-size: 12.5px; color: #ef4444; justify-content: center; cursor: pointer;" onclick="handleClientLogout()" title="Keluar / Putuskan Akses">
                        <i class="bi bi-box-arrow-right"></i>
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
                        <i class="bi bi-ticket-perforated-fill"></i>
                        <span>Kode Voucher</span>
                    </button>
                    <button type="button" class="nm-seg-btn" id="btnTabMember" onclick="switchLoginMode('member')" style="flex: 1; justify-content: center;">
                        <i class="bi bi-person-badge-fill"></i>
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
                                <i class="bi bi-rocket-takeoff-fill"></i>
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
                                <i class="bi bi-box-arrow-in-right"></i>
                                <span>Login Akun Member</span>
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($settings['free_trial_enabled'])): ?>
                    <!-- Free Trial 1 Menit Button -->
                    <div style="text-align: center; margin-top: 14px; padding-top: 12px; border-top: 1px dashed rgba(182, 198, 220, 0.45);">
                        <button type="button" class="btn-new-device" style="width: 100%; padding: 11px; font-size: 12px; justify-content: center; cursor: pointer;" onclick="handleFreeTrialClick()">
                            <i class="bi bi-clock-history" style="color: #8b5cf6; font-size: 14px;"></i>
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
                    <i class="bi bi-whatsapp" style="color: #25d366; font-size: 13px;"></i>
                    <span>Beli Voucher: <strong style="color: var(--text-heading);"><?= htmlspecialchars($settings['contact_person']) ?></strong></span>
                </div>
            <?php endif; ?>
            <div style="font-size: 10px; color: var(--text-muted); opacity: 0.8; margin-top: 4px;">
                Orange Pi Zero 2 Gateway Hotspot &bull; Protected by AdGuard Home DNS
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
