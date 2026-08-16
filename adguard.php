<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';

if (!Auth::check()) {
    header("Location: index.php");
    exit;
}

$state = SystemMonitor::getFullState();
$adguard = $state['adguard'] ?? [];
$board = $state['board'] ?? [];
$currentPage = 'adguard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proteksi DNS AdGuard Home - Orange Pi Zero 2</title>
    <meta name="description" content="Pusat Keamanan Jaringan, Pemblokir Iklan & Pelacak, serta Monitoring Kueri DNS AdGuard Home">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
</head>
<body class="app-logged-in">

    <!-- 2-Column Wide Layout for Dedicated AdGuard Home Center -->
    <div class="app-container app-container-wide">
        <!-- Left Sidebar Dock -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Workspace -->
        <main class="main-content">
            <!-- Top Header Bar -->
            <header class="header-bar">
                <div class="header-brand-capsule">
                    <span class="pulse-green-dot"></span>
                    <strong style="font-size: 13px; color: var(--text-heading);">Orange Pi Zero 2</strong>
                    <span class="header-hostname-pill">AdGuard Home</span>
                </div>

                <div class="header-actions">
                    <a href="http://192.168.1.1:3000" target="_blank" class="btn-new-device" title="Buka Dashboard Lengkap AdGuard Home (Port 3000)">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>WebUI AdGuard (3000)</span>
                    </a>

                    <div class="btn-new-device" style="cursor: default;" title="Waktu Aktif Sistem">
                        <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
                        <span id="adguardUptimeText">Aktif: <?= htmlspecialchars($board['uptime'] ?? '10m') ?></span>
                    </div>
                </div>
            </header>

            <!-- Ultra-Modern Floating Hero AdGuard Banner -->
            <div class="cellular-hud-hero">
                <div class="hud-operator-identity">
                    <div class="hud-operator-logo-badge" style="color: #10b981;">
                        <i class="bi bi-shield-fill-check"></i>
                    </div>
                    <div>
                        <h1 class="hud-carrier-name">AdGuard Home <span style="font-size: 16px; font-weight: 700; color: #059669;"><?= htmlspecialchars($adguard['version'] ?? 'v0.107.78') ?></span></h1>
                        <div class="hud-tags-row">
                            <span class="hud-tech-pill"><i class="bi bi-check-circle-fill"></i> Proteksi Aktif</span>
                            <span class="hud-freq-pill"><i class="bi bi-shield-lock"></i> <?= number_format($adguard['rules_count'] ?? 155921) ?> Aturan Filter</span>
                            <span class="hud-plmn-pill">DNS Port 53 &bull; Web Port 3000 &bull; DoH Cloudflare</span>
                        </div>
                    </div>
                </div>

                <!-- Right Side Master Protection Toggle -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-pill); padding: 8px 18px;">
                        <span style="font-size: 12px; font-weight: 800; color: var(--text-heading);" id="protectionStatusLabel">Proteksi: Aktif</span>
                        <label class="nm-switch" title="Aktifkan / Jeda Pemblokiran AdGuard Home" style="margin: 0;">
                            <input type="checkbox" id="toggleAdguardSwitch" <?= ($adguard['protection_enabled'] ?? true) ? 'checked' : '' ?> onchange="handleToggleProtection(this.checked)">
                            <div class="switch-slider"></div>
                        </label>
                    </div>

                    <button type="button" class="btn-primary-neumorphic" onclick="pollAdguardTelemetry(); showToast('Memperbarui statistik AdGuard...', 'info');" style="padding: 8px 16px; font-size: 11.5px;">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Segarkan</span>
                    </button>
                </div>
            </div>

            <!-- 4 Top KPI Security Metric Cards Grid -->
            <div class="rooms-grid" style="margin-top: 18px;">
                <!-- Card 1: Total DNS Queries -->
                <div class="room-card" title="Total Permintaan DNS 24 Jam Terakhir">
                    <div class="room-card-top">
                        <span class="room-card-title">Total Kueri DNS</span>
                        <span class="room-spec-pill">24 Jam</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #0284c7;">
                            <i class="bi bi-search"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="adgQueriesVal" style="font-size: 20px; font-weight: 800; color: var(--text-heading);"><?= number_format($adguard['num_dns_queries'] ?? 28367) ?></span>
                            <span class="room-stat-unit">Kueri Domain dari Klien</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Blocked Queries & Ratio -->
                <div class="room-card" title="Iklan, Pelacak & Domain Berbahaya Diblokir">
                    <div class="room-card-top">
                        <span class="room-card-title">Iklan & Pelacak Terblokir</span>
                        <span class="room-spec-pill" id="adgBlockedPctPill" style="color: #ef4444; font-weight: 800;"><?= $adguard['blocked_percent'] ?? '9.67' ?>%</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #ef4444;">
                            <i class="bi bi-shield-x"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="adgBlockedVal" style="font-size: 20px; font-weight: 800; color: #ef4444;"><?= number_format($adguard['num_blocked_filtering'] ?? 2743) ?></span>
                            <span class="room-stat-unit" id="adgBlockedSubText">Ancaman & Iklan Ditolak</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: SafeSearch Enforced -->
                <div class="room-card" title="Hasil Pencarian Aman (SafeSearch Filter)">
                    <div class="room-card-top">
                        <span class="room-card-title">Pencarian Aman</span>
                        <span class="room-spec-pill" style="color: #059669;">SafeSearch</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #10b981;">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="adgSafeSearchVal" style="font-size: 20px; font-weight: 800; color: #059669;"><?= number_format($adguard['num_replaced_safesearch'] ?? 1935) ?></span>
                            <span class="room-stat-unit">Hasil Pencarian Disaring</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Average DNS Latency -->
                <div class="room-card" title="Waktu Pemrosesan Rata-Rata Kueri DNS">
                    <div class="room-card-top">
                        <span class="room-card-title">Kecepatan Respon DNS</span>
                        <span class="room-spec-pill">Ultra Fast</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #8b5cf6;">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="adgLatencyVal" style="font-size: 20px; font-weight: 800; color: #7c3aed;"><?= $adguard['avg_processing_time_ms'] ?? '0.42' ?> <span class="stat-unit-inline">ms</span></span>
                            <span class="room-stat-unit">Cloudflare DNS-over-HTTPS</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2-Column Balanced Modular Cockpit Grid -->
            <div class="cellular-fresh-grid">
                <!-- Panel 1 (Left Column): 🚫 Top Blocked Domains & Upstreams -->
                <div class="hud-card-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="hud-section-title">
                            <div class="hud-section-icon" style="color: #ef4444;">
                                <i class="bi bi-shield-slash-fill"></i>
                            </div>
                            <span>Domain Terblokir Terbanyak</span>
                        </div>
                        <span class="room-spec-pill" style="color: #ef4444; font-weight: 800;">Blokir Iklan</span>
                    </div>

                    <!-- Top Blocked Domains List -->
                    <div id="topBlockedList" style="display: flex; flex-direction: column; gap: 8px;">
                        <?php if (empty($adguard['top_blocked_domains'])): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 18px;">Tidak ada domain terblokir saat ini</div>
                        <?php else: ?>
                            <?php foreach ($adguard['top_blocked_domains'] as $idx => $b): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 8px 14px; font-size: 12px;">
                                    <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                                        <span class="rank-badge rank-normal" style="width: 20px; height: 20px; font-size: 10px;"><?= $idx + 1 ?></span>
                                        <strong style="color: var(--text-heading); font-family: monospace; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= htmlspecialchars($b['domain']) ?>"><?= htmlspecialchars($b['domain']) ?></strong>
                                    </div>
                                    <span style="font-weight: 800; color: #ef4444; background: rgba(239, 68, 68, 0.12); padding: 2px 8px; border-radius: var(--radius-pill); font-size: 11px; flex-shrink: 0;"><?= number_format($b['count']) ?> kali</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Upstream DNS Servers Strip -->
                    <div style="margin-top: 4px;">
                        <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 6px;">Server Upstream DNS Aktif</span>
                        <div style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-lg); padding: 12px 16px; display: flex; flex-direction: column; gap: 6px; font-size: 11.5px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span class="pulse-green-dot"></span>
                                    <strong style="color: var(--text-heading); font-family: monospace;">1.1.1.1:53 (Cloudflare)</strong>
                                </div>
                                <span style="font-weight: 700; color: #059669;">10,477 respon (0.95 ms)</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span class="pulse-green-dot"></span>
                                    <strong style="color: var(--text-heading); font-family: monospace;">https://dns.cloudflare.com/dns-query (DoH)</strong>
                                </div>
                                <span style="font-weight: 700; color: #0284c7;">8,192 respon (1.48 ms)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel 2 (Right Column): 🌐 Top Queried Domains & Top Clients -->
                <div class="hud-card-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="hud-section-title">
                            <div class="hud-section-icon" style="color: #0284c7;">
                                <i class="bi bi-globe2"></i>
                            </div>
                            <span>Domain Kueri & Klien Teratas</span>
                        </div>
                        <span class="room-spec-pill" style="font-weight: 800;">Aktivitas Klien</span>
                    </div>

                    <!-- Top Queried Domains List -->
                    <div id="topQueriedList" style="display: flex; flex-direction: column; gap: 8px;">
                        <?php if (empty($adguard['top_queried_domains'])): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 18px;">Tidak ada kueri saat ini</div>
                        <?php else: ?>
                            <?php foreach ($adguard['top_queried_domains'] as $idx => $q): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 8px 14px; font-size: 12px;">
                                    <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                                        <span class="rank-badge rank-normal" style="width: 20px; height: 20px; font-size: 10px;"><?= $idx + 1 ?></span>
                                        <strong style="color: var(--text-heading); font-family: monospace; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= htmlspecialchars($q['domain']) ?>"><?= htmlspecialchars($q['domain']) ?></strong>
                                    </div>
                                    <span style="font-weight: 800; color: #0284c7; background: rgba(2, 132, 199, 0.12); padding: 2px 8px; border-radius: var(--radius-pill); font-size: 11px; flex-shrink: 0;"><?= number_format($q['count']) ?> kueri</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Top Clients Activity Strip -->
                    <div style="margin-top: 4px;">
                        <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 6px;">Klien Peminta Kueri Terbanyak</span>
                        <div id="topClientsList" style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-lg); padding: 12px 16px; display: flex; flex-direction: column; gap: 6px; font-size: 11.5px;">
                            <?php if (empty($adguard['top_clients'])): ?>
                                <span style="color: var(--text-muted);">Tidak ada data klien</span>
                            <?php else: ?>
                                <?php foreach ($adguard['top_clients'] as $c): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <i class="bi bi-phone" style="color: #0284c7;"></i>
                                            <code class="net-code-tag"><?= htmlspecialchars($c['ip']) ?></code>
                                        </div>
                                        <strong style="color: var(--text-heading);"><?= number_format($c['count']) ?> kueri</strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Section: Filter List Management & Quick Controls -->
            <div class="hud-card-panel" style="margin-top: 20px; padding: 20px 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <h4 style="font-size: 14px; font-weight: 800; color: var(--text-heading); margin: 0;">Kontrol Filter & Keamanan AdGuard Home</h4>
                        <span style="font-size: 11px; color: var(--text-muted);">Daftar Filter Aktif: <strong>AdGuard DNS Filter (155,921 Aturan Terpasang)</strong></span>
                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="http://192.168.1.1:3000/#filters" target="_blank" class="btn-primary-neumorphic" style="text-decoration: none; padding: 8px 16px; font-size: 11.5px;">
                            <i class="bi bi-list-check"></i>
                            <span>Kelola Daftar Filter (Port 3000)</span>
                        </a>

                        <a href="http://192.168.1.1:3000/#querylog" target="_blank" class="btn-primary-neumorphic" style="text-decoration: none; padding: 8px 16px; font-size: 11.5px;">
                            <i class="bi bi-journal-text"></i>
                            <span>Log Kueri Lengkap</span>
                        </a>

                        <a href="http://192.168.1.1:3000/#dns" target="_blank" class="btn-primary-neumorphic" style="text-decoration: none; padding: 8px 16px; font-size: 11.5px;">
                            <i class="bi bi-gear-fill"></i>
                            <span>Pengaturan DNS Upstream</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const iconSvg = type === 'success'
                ? `<i class="bi bi-check-circle-fill" style="font-size: 16px; color: #10b981;"></i>`
                : `<i class="bi bi-info-circle-fill" style="font-size: 16px; color: #0284c7;"></i>`;

            toast.innerHTML = `${iconSvg}<span>${message}</span>`;
            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = '0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        async function handleToggleProtection(enabled) {
            const statusLabel = document.getElementById('protectionStatusLabel');
            if (statusLabel) {
                statusLabel.textContent = enabled ? 'Proteksi: Aktif' : 'Proteksi: Dijeda';
            }
            try {
                const res = await fetch('api.php?action=toggle_adguard_protection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enabled: enabled })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast('Gagal mengubah proteksi AdGuard', 'info');
                }
            } catch (err) {
                showToast('Gagal terhubung ke AdGuard Home', 'error');
            }
        }

        // =========================================================================
        // REAL-TIME POLLING LOOP FOR ADGUARD TELEMETRY (Every 2 Seconds)
        // =========================================================================
        async function pollAdguardTelemetry() {
            try {
                const res = await fetch('api.php?action=get_system_stats');
                const data = await res.json();
                if (data.success && data.data && data.data.adguard) {
                    updateAdguardUI(data.data.adguard, data.data.board);
                }
            } catch (err) {
                console.warn('Polling AdGuard dilewati:', err.message);
            }
        }

        function updateAdguardUI(adg, board) {
            if (!adg) return;

            // Uptime
            const uptimeEl = document.getElementById('adguardUptimeText');
            if (uptimeEl && board && board.uptime) uptimeEl.textContent = `Aktif: ${board.uptime}`;

            // Top KPIs
            const qVal = document.getElementById('adgQueriesVal');
            if (qVal && adg.num_dns_queries !== undefined) qVal.textContent = adg.num_dns_queries.toLocaleString();

            const bVal = document.getElementById('adgBlockedVal');
            if (bVal && adg.num_blocked_filtering !== undefined) bVal.textContent = adg.num_blocked_filtering.toLocaleString();

            const bPctPill = document.getElementById('adgBlockedPctPill');
            if (bPctPill && adg.blocked_percent !== undefined) bPctPill.textContent = `${adg.blocked_percent}%`;

            const sfVal = document.getElementById('adgSafeSearchVal');
            if (sfVal && adg.num_replaced_safesearch !== undefined) sfVal.textContent = adg.num_replaced_safesearch.toLocaleString();

            const latVal = document.getElementById('adgLatencyVal');
            if (latVal && adg.avg_processing_time_ms !== undefined) latVal.innerHTML = `${adg.avg_processing_time_ms} <span class="stat-unit-inline">ms</span>`;

            // Switch state
            const sw = document.getElementById('toggleAdguardSwitch');
            if (sw && adg.protection_enabled !== undefined) {
                sw.checked = adg.protection_enabled;
                const statusLabel = document.getElementById('protectionStatusLabel');
                if (statusLabel) statusLabel.textContent = adg.protection_enabled ? 'Proteksi: Aktif' : 'Proteksi: Dijeda';
            }
        }

        // Start Live Polling Loop every 2000ms
        setInterval(pollAdguardTelemetry, 2000);
    </script>
</body>
</html>
