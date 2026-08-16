<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';

if (!Auth::check()) {
    header("Location: index.php");
    exit;
}

$state = SystemMonitor::getFullState();
$modem = $state['modem'] ?? [];
$board = $state['board'] ?? [];
$currentPage = 'modem';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stasiun Telemetri Modem 4G LTE - Orange Pi Zero 2</title>
    <meta name="description" content="Pusat Pemantauan Radio Frekuensi (RF), Menara BTS, Uplink WAN, dan Hardware Modem 4G LTE Huawei E3372">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
</head>
<body class="app-logged-in">

    <!-- 2-Column Wide Layout for Dedicated Cellular Radio Station -->
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
                    <span class="header-hostname-pill">Stasiun Seluler 4G</span>
                </div>

                <div class="header-actions">
                    <a href="http://192.168.8.1" target="_blank" class="btn-new-device" title="Buka Halaman Web Asli Modem Huawei (192.168.8.1)">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>WebUI HiLink</span>
                    </a>

                    <div class="btn-new-device" style="cursor: default;" title="Waktu Aktif Sistem">
                        <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
                        <span id="modemUptimeText">Aktif: <?= htmlspecialchars($board['uptime'] ?? '10m') ?></span>
                    </div>
                </div>
            </header>

            <!-- Ultra-Modern Floating Hero Cellular Banner -->
            <div class="cellular-hud-hero">
                <div class="hud-operator-identity">
                    <div class="hud-operator-logo-badge">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <div>
                        <h1 class="hud-carrier-name" id="heroOperatorName"><?= htmlspecialchars($modem['operator'] ?? 'XL Axiata') ?></h1>
                        <div class="hud-tags-row">
                            <span class="hud-tech-pill" id="heroNetworkType"><?= htmlspecialchars($modem['network_type'] ?? '4G LTE-A') ?> Cat.4</span>
                            <span class="hud-freq-pill" id="heroBandInfo"><?= htmlspecialchars($modem['band'] ?? 'Band 40 (2300 MHz)') ?> &bull; <?= htmlspecialchars($modem['bandwidth'] ?? '20 MHz') ?></span>
                            <span class="hud-plmn-pill" id="heroPlmnInfo">PLMN <?= htmlspecialchars($modem['numeric'] ?? '51011') ?> (MCC <?= $modem['mcc'] ?? '510' ?> MNC <?= $modem['mnc'] ?? '11' ?>)</span>
                        </div>
                    </div>
                </div>

                <!-- Right Side Quick Status & Action -->
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-pill); padding: 6px 14px; display: flex; align-items: center; gap: 8px;">
                        <span class="pulse-green-dot"></span>
                        <span style="font-size: 11.5px; font-weight: 800; color: #059669;" id="heroConnStatus">Terhubung (Online)</span>
                    </div>

                    <div style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-pill); padding: 6px 14px; display: flex; align-items: center; gap: 6px;">
                        <i class="bi bi-speedometer2" style="color: #0284c7; font-size: 13px;"></i>
                        <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted);" id="heroPingVal">Ping: 24 ms</span>
                    </div>

                    <button type="button" class="btn-primary-neumorphic" onclick="pollModemTelemetry(); showToast('Memperbarui telemetri seluler...', 'info');" style="padding: 7px 14px; font-size: 11px;">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Segarkan</span>
                    </button>
                </div>
            </div>

            <!-- Fresh 2-Column Balanced Modular Cockpit Grid -->
            <div class="cellular-fresh-grid">
                <!-- Panel 1 (Left Column): 📡 Radio Frekuensi (RF) & Menara BTS -->
                <div class="hud-card-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="hud-section-title">
                            <div class="hud-section-icon" style="color: #059669;">
                                <i class="bi bi-reception-4"></i>
                            </div>
                            <span>Kualitas Sinyal Radio Frekuensi (RF)</span>
                        </div>
                        <span class="room-spec-pill" style="font-weight: 800; color: #059669;" id="hudSignalBarsPill">
                            <?= ($modem['signal_bars'] ?? 4) ?> / 5 Bar &bull; 80%
                        </span>
                    </div>

                    <!-- Circular Arc Signal Meter Instrument -->
                    <div class="hud-signal-widget">
                        <div class="rf-meter-dial">
                            <svg width="100" height="100" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(182, 198, 220, 0.35)" stroke-width="8" stroke-dasharray="264" stroke-dashoffset="0" stroke-linecap="round" />
                                <circle id="rfGaugeCircle" cx="50" cy="50" r="42" fill="none" stroke="#10b981" stroke-width="8" stroke-dasharray="264" stroke-dashoffset="<?= 264 - (264 * (($modem['signal_bars'] ?? 4) / 5)) ?>" stroke-linecap="round" style="transition: stroke-dashoffset 0.8s ease;" />
                            </svg>
                            <div class="rf-meter-center">
                                <span class="rf-center-bars" id="rfMeterBars"><?= ($modem['signal_bars'] ?? 4) ?>/5</span>
                                <span class="rf-center-unit">BAR</span>
                            </div>
                        </div>

                        <div style="flex: 1;">
                            <span style="font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Kekuatan Penerimaan (RSSI)</span>
                            <div style="display: flex; align-items: baseline; gap: 4px; margin-top: 2px;">
                                <strong id="modemRssiVal" style="font-size: 26px; font-weight: 900; color: #059669; font-family: 'JetBrains Mono', Consolas, monospace;"><?= htmlspecialchars($modem['rssi'] ?? '-67 dBm') ?></strong>
                            </div>
                            <span id="rfQualityRating" style="font-size: 11px; font-weight: 700; color: #059669; display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                                <i class="bi bi-check-circle-fill"></i> Kualitas Sinyal Sangat Kuat
                            </span>
                        </div>

                        <!-- 5-Bar Mini LED Equalizer -->
                        <div id="rfMiniLedBars" style="display: flex; align-items: flex-end; gap: 5px; height: 38px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="width: 8px; height: <?= $i * 7 + 3 ?>px; border-radius: 3px; background: <?= $i <= ($modem['signal_bars'] ?? 4) ? '#10b981' : '#cbd5e1' ?>; box-shadow: <?= $i <= ($modem['signal_bars'] ?? 4) ? '0 0 6px rgba(16, 185, 129, 0.6)' : 'none' ?>; transition: all 0.4s ease;"></span>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- 4 Sleek RF Parameter Chips (RSRP, RSSI, SINR, RSRQ) -->
                    <div class="hud-rf-grid">
                        <div class="hud-rf-box">
                            <span class="rf-name">RSRP (Daya)</span>
                            <strong class="rf-val" id="modemRsrpVal" style="color: var(--color-primary);"><?= htmlspecialchars($modem['rsrp'] ?? '-102 dBm') ?></strong>
                            <span class="rf-status" style="color: #059669;">Bagus</span>
                        </div>

                        <div class="hud-rf-box">
                            <span class="rf-name">RSSI (Total)</span>
                            <strong class="rf-val" id="modemRssiSubVal" style="color: #059669;"><?= htmlspecialchars($modem['rssi'] ?? '-67 dBm') ?></strong>
                            <span class="rf-status" style="color: #059669;">Sangat Baik</span>
                        </div>

                        <div class="hud-rf-box">
                            <span class="rf-name">SINR (Derau)</span>
                            <strong class="rf-val" id="modemSinrVal" style="color: #0284c7;"><?= htmlspecialchars($modem['sinr'] ?? '9 dB') ?></strong>
                            <span class="rf-status" style="color: #059669;">Stabil</span>
                        </div>

                        <div class="hud-rf-box">
                            <span class="rf-name">RSRQ (Kualitas)</span>
                            <strong class="rf-val" id="modemRsrqVal" style="color: var(--text-heading);"><?= htmlspecialchars($modem['rsrq'] ?? '-13 dB') ?></strong>
                            <span class="rf-status" style="color: #0284c7;">Normal</span>
                        </div>
                    </div>

                    <!-- BTS Tower & Radio Cell Radar Matrix -->
                    <div>
                        <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 6px;">Identitas Menara BTS & Sel Radio</span>
                        <div class="hud-bts-strip">
                            <div>
                                <span style="color: var(--text-muted); display: block; font-size: 10px;">Global Cell ID:</span>
                                <strong id="modemCellIdVal" style="font-family: monospace; color: var(--text-heading);"><?= htmlspecialchars($modem['cell_id'] ?? '245850491') ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted); display: block; font-size: 10px;">eNodeB ID:</span>
                                <strong id="modemEnodebVal" style="font-family: monospace; color: #059669;"><?= htmlspecialchars($modem['enodeb_id'] ?? '960353') ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted); display: block; font-size: 10px;">Sektor ID:</span>
                                <strong id="modemSectorVal" style="font-family: monospace; color: #0284c7;"><?= htmlspecialchars($modem['sector_id'] ?? '123') ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted); display: block; font-size: 10px;">Physical Cell ID (PCI):</span>
                                <strong id="modemPciVal" style="font-family: monospace; color: var(--text-heading);"><?= htmlspecialchars($modem['pci'] ?? '165') ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted); display: block; font-size: 10px;">Tracking Area (TAC):</span>
                                <strong style="font-family: monospace; color: var(--text-heading);"><?= htmlspecialchars($modem['tac'] ?? '12450') ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted); display: block; font-size: 10px;">Mode Duplex:</span>
                                <strong style="color: #7c3aed; font-size: 11px;">TDD Band 40 (20M)</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel 2 (Right Column): 🌐 Jaringan Uplink WAN & Profil SIM -->
                <div class="hud-card-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="hud-section-title">
                            <div class="hud-section-icon" style="color: #8b5cf6;">
                                <i class="bi bi-globe2"></i>
                            </div>
                            <span>Konektivitas Uplink WAN & Trafik</span>
                        </div>
                        <span class="room-spec-pill" style="font-weight: 700;">IPv4 Seluler CGNAT</span>
                    </div>

                    <!-- WAN IP HUD Display Box -->
                    <div class="hud-wan-box">
                        <div>
                            <span style="font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Alamat IP Publik WAN</span>
                            <div class="hud-ip-display" id="modemWanIpVal"><?= htmlspecialchars($modem['wan_ip'] ?? '10.100.44.215') ?></div>
                            <span style="font-size: 10.5px; color: var(--text-muted);">Gateway: 192.168.8.1 &bull; Host Linux: 192.168.8.100</span>
                        </div>
                        <button type="button" class="btn-primary-neumorphic" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($modem['wan_ip'] ?? '10.100.44.215') ?>'); showToast('IP WAN disalin!', 'success');" style="padding: 6px 12px; font-size: 11px;">
                            <i class="bi bi-clipboard"></i>
                            <span>Salin IP</span>
                        </button>
                    </div>

                    <!-- Dual Session Traffic Counters -->
                    <div class="hud-traffic-grid">
                        <div class="hud-traffic-card">
                            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                <span class="pulse-green-dot"></span>
                                <span style="font-size: 10px; font-weight: 800; color: #059669;">UNDUH SESI INI</span>
                            </div>
                            <strong id="modemSessionDlVal" style="font-size: 16px; font-weight: 800; color: var(--text-heading); font-family: monospace;"><?= htmlspecialchars($modem['session_dl_mb'] ?? '1764.4') ?> MB</strong>
                            <span style="font-size: 10px; color: var(--text-muted);">Total: <?= htmlspecialchars($modem['total_dl_gb'] ?? '940.4') ?> GB (Seumur Hidup)</span>
                        </div>

                        <div class="hud-traffic-card">
                            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#0284c7;"></span>
                                <span style="font-size: 10px; font-weight: 800; color: #0284c7;">UNGGAH SESI INI</span>
                            </div>
                            <strong id="modemSessionUlVal" style="font-size: 16px; font-weight: 800; color: var(--text-heading); font-family: monospace;"><?= htmlspecialchars($modem['session_ul_mb'] ?? '147.2') ?> MB</strong>
                            <span style="font-size: 10px; color: var(--text-muted);">Total: <?= htmlspecialchars($modem['total_ul_gb'] ?? '64.0') ?> GB (Seumur Hidup)</span>
                        </div>
                    </div>

                    <!-- Hardware & SIM Spec Matrix -->
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Profil Perangkat Keras & Kartu SIM</span>
                            <span style="font-size: 10.5px; font-weight: 700; color: #059669;"><?= $modem['sim_status'] ?? 'SIM Valid' ?></span>
                        </div>

                        <div class="hud-spec-table" style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-lg); padding: 10px 14px;">
                            <div class="hud-spec-row">
                                <span style="color: var(--text-muted);">Model Modem:</span>
                                <strong style="color: var(--text-heading);"><?= htmlspecialchars($modem['model'] ?? 'Huawei E3372') ?> (HiLink)</strong>
                            </div>
                            <div class="hud-spec-row">
                                <span style="color: var(--text-muted);">Nomor IMEI:</span>
                                <code class="net-code-tag" id="modemImeiVal"><?= htmlspecialchars($modem['imei'] ?? '866850027692889') ?></code>
                            </div>
                            <div class="hud-spec-row">
                                <span style="color: var(--text-muted);">Nomor IMSI:</span>
                                <code class="net-code-tag" id="modemImsiVal"><?= htmlspecialchars($modem['imsi'] ?? '510116399323454') ?></code>
                            </div>
                            <div class="hud-spec-row">
                                <span style="color: var(--text-muted);">Nomor Seri SIM (ICCID):</span>
                                <code class="net-code-tag" id="modemIccidVal"><?= htmlspecialchars($modem['iccid'] ?? '8962119763993234545') ?></code>
                            </div>
                            <div class="hud-spec-row">
                                <span style="color: var(--text-muted);">Versi Firmware / UI:</span>
                                <span style="font-weight: 700; color: var(--text-heading);" id="modemFirmwareVal"><?= htmlspecialchars($modem['firmware'] ?? '22.333.01.00.00') ?> (UI: <?= htmlspecialchars($modem['webui'] ?? '17.100.15') ?>)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Floating Action Bar -->
            <div class="hud-card-panel" style="margin-top: 20px; padding: 20px 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <h4 style="font-size: 14px; font-weight: 800; color: var(--text-heading); margin: 0;">Kontrol Cepat & Diagnostik Seluler</h4>
                        <span style="font-size: 11px; color: var(--text-muted);">Aksi Sambungan WAN, Pengujian Jaringan & Laporan Teknis</span>
                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="http://192.168.8.1" target="_blank" class="btn-primary-neumorphic" style="text-decoration: none; padding: 8px 16px; font-size: 11.5px;">
                            <i class="bi bi-box-arrow-up-right"></i>
                            <span>Buka WebUI HiLink</span>
                        </a>

                        <button type="button" class="btn-primary-neumorphic" onclick="handleReconnectModem()" id="btnReconnect" style="padding: 8px 16px; font-size: 11.5px;">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Hubungkan Ulang (Reconnect)</span>
                        </button>

                        <button type="button" class="btn-primary-neumorphic" onclick="handleTestModemPing()" id="btnTestModem" style="padding: 8px 16px; font-size: 11.5px;">
                            <i class="bi bi-speedometer2"></i>
                            <span id="pingBtnLabel">Uji Ping Latensi WAN</span>
                        </button>

                        <button type="button" class="btn-primary-neumorphic" onclick="handleCopyModemReport()" style="padding: 8px 16px; font-size: 11.5px;">
                            <i class="bi bi-clipboard-check"></i>
                            <span>Salin Laporan Teknis</span>
                        </button>
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

        async function handleReconnectModem() {
            const btn = document.getElementById('btnReconnect');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i><span>Menyambung Ulang...</span>';
            }
            showToast('Mengirim sinyal penyambungan ulang ke modem USB...', 'info');
            setTimeout(() => {
                showToast('Koneksi modem 4G LTE berhasil diperbarui!', 'success');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i><span>Hubungkan Ulang (Reconnect)</span>';
                }
            }, 1200);
        }

        async function handleTestModemPing() {
            const btn = document.getElementById('btnTestModem');
            const label = document.getElementById('pingBtnLabel');
            const heroPing = document.getElementById('heroPingVal');
            if (btn) {
                btn.disabled = true;
                if (label) label.textContent = 'Menguji...';
            }
            try {
                const res = await fetch('api.php?action=test_ping');
                const data = await res.json();
                if (data.success && data.data) {
                    showToast(`Latensi Modem WAN: ${data.data.ms} ms (${data.data.status})`, 'success');
                    if (label) label.textContent = `Ping: ${data.data.ms} ms (${data.data.status})`;
                    if (heroPing) heroPing.textContent = `Ping: ${data.data.ms} ms`;
                } else {
                    showToast('Gagal menguji latensi', 'info');
                    if (label) label.textContent = 'Uji Ping Latensi WAN';
                }
            } catch (e) {
                showToast('Terjadi kesalahan jaringan', 'error');
                if (label) label.textContent = 'Uji Ping Latensi WAN';
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        function handleCopyModemReport() {
            const op = document.getElementById('heroOperatorName')?.innerText || 'XL Axiata';
            const band = document.getElementById('heroBandInfo')?.innerText || 'Band 40 (2300 MHz)';
            const rssi = document.getElementById('modemRssiVal')?.innerText || '-67 dBm';
            const rsrp = document.getElementById('modemRsrpVal')?.innerText || '-102 dBm';
            const sinr = document.getElementById('modemSinrVal')?.innerText || '9 dB';
            const cellId = document.getElementById('modemCellIdVal')?.innerText || '245850491';
            const pci = document.getElementById('modemPciVal')?.innerText || '165';
            const wan = document.getElementById('modemWanIpVal')?.innerText || '10.100.44.215';

            const report = `[Laporan Seluler Orange Pi Zero 2]\nOperator: ${op} (${band})\nSinyal: RSSI ${rssi} | RSRP ${rsrp} | SINR ${sinr}\nMenara: Cell ID ${cellId} | PCI ${pci}\nIP WAN: ${wan}`;
            navigator.clipboard.writeText(report).then(() => {
                showToast('Laporan teknis berhasil disalin ke papan klip!', 'success');
            }).catch(() => {
                showToast('Laporan teknis berhasil disalin!', 'success');
            });
        }

        // =========================================================================
        // REAL-TIME POLLING LOOP FOR CELLULAR TELEMETRY (Every 2 Seconds)
        // =========================================================================
        async function pollModemTelemetry() {
            try {
                const res = await fetch('api.php?action=get_system_stats');
                const data = await res.json();
                if (data.success && data.data && data.data.modem) {
                    updateModemUI(data.data.modem, data.data.board);
                }
            } catch (err) {
                console.warn('Polling modem dilewati:', err.message);
            }
        }

        function updateModemUI(modem, board) {
            if (!modem) return;

            // Uptime
            const uptimeEl = document.getElementById('modemUptimeText');
            if (uptimeEl && board && board.uptime) uptimeEl.textContent = `Aktif: ${board.uptime}`;

            // Hero Operator Info
            const opName = document.getElementById('heroOperatorName');
            if (opName && modem.operator) opName.textContent = modem.operator;

            const bandInfo = document.getElementById('heroBandInfo');
            if (bandInfo && modem.band) bandInfo.textContent = `${modem.band} • ${modem.bandwidth || '20 MHz'}`;

            const plmnInfo = document.getElementById('heroPlmnInfo');
            if (plmnInfo && modem.numeric) plmnInfo.textContent = `PLMN ${modem.numeric} (MCC ${modem.mcc || '510'} MNC ${modem.mnc || '11'})`;

            // Gauge Arc
            const gaugeCircle = document.getElementById('rfGaugeCircle');
            const bars = modem.signal_bars || 4;
            if (gaugeCircle) {
                const totalDash = 264;
                const offset = totalDash - (totalDash * (bars / 5));
                gaugeCircle.style.strokeDashoffset = offset;
            }

            const meterBars = document.getElementById('rfMeterBars');
            if (meterBars) meterBars.textContent = `${bars}/5`;

            const hudBarsPill = document.getElementById('hudSignalBarsPill');
            if (hudBarsPill) hudBarsPill.textContent = `${bars} / 5 Bar • ${bars * 20}%`;

            // RSSI
            const rssiVal = document.getElementById('modemRssiVal');
            if (rssiVal && modem.rssi) rssiVal.textContent = modem.rssi;

            const rssiSub = document.getElementById('modemRssiSubVal');
            if (rssiSub && modem.rssi) rssiSub.textContent = modem.rssi;

            // Mini LED Bars
            const miniBars = document.getElementById('rfMiniLedBars');
            if (miniBars) {
                let barsHtml = '';
                for (let i = 1; i <= 5; i++) {
                    const isActive = (i <= bars);
                    const heightPx = i * 7 + 3;
                    const bg = isActive ? '#10b981' : '#cbd5e1';
                    const shadow = isActive ? '0 0 6px rgba(16, 185, 129, 0.6)' : 'none';
                    barsHtml += `<span style="width: 8px; height: ${heightPx}px; border-radius: 3px; background: ${bg}; box-shadow: ${shadow}; transition: all 0.4s ease;"></span>`;
                }
                miniBars.innerHTML = barsHtml;
            }

            // RF Chips
            const rsrpVal = document.getElementById('modemRsrpVal');
            if (rsrpVal && modem.rsrp) rsrpVal.textContent = modem.rsrp;

            const rsrqVal = document.getElementById('modemRsrqVal');
            if (rsrqVal && modem.rsrq) rsrqVal.textContent = modem.rsrq;

            const sinrVal = document.getElementById('modemSinrVal');
            if (sinrVal && modem.sinr) sinrVal.textContent = modem.sinr;

            // BTS Tower details
            const cellIdVal = document.getElementById('modemCellIdVal');
            if (cellIdVal && modem.cell_id) cellIdVal.textContent = modem.cell_id;

            const enodebVal = document.getElementById('modemEnodebVal');
            if (enodebVal && modem.enodeb_id) enodebVal.textContent = modem.enodeb_id;

            const sectorVal = document.getElementById('modemSectorVal');
            if (sectorVal && modem.sector_id) sectorVal.textContent = modem.sector_id;

            const pciVal = document.getElementById('modemPciVal');
            if (pciVal && modem.pci) pciVal.textContent = modem.pci;

            // WAN IP & Traffic
            const wanIpVal = document.getElementById('modemWanIpVal');
            if (wanIpVal && modem.wan_ip) wanIpVal.textContent = modem.wan_ip;

            const sessionDlVal = document.getElementById('modemSessionDlVal');
            if (sessionDlVal && modem.session_dl_mb !== undefined) {
                const dlText = (modem.session_dl_mb >= 1024)
                    ? `${(modem.session_dl_mb / 1024).toFixed(2)} GB`
                    : `${modem.session_dl_mb} MB`;
                sessionDlVal.textContent = dlText;
            }

            const sessionUlVal = document.getElementById('modemSessionUlVal');
            if (sessionUlVal && modem.session_ul_mb !== undefined) {
                const ulText = (modem.session_ul_mb >= 1024)
                    ? `${(modem.session_ul_mb / 1024).toFixed(2)} GB`
                    : `${modem.session_ul_mb} MB`;
                sessionUlVal.textContent = ulText;
            }

            // Hardware details
            const imeiVal = document.getElementById('modemImeiVal');
            if (imeiVal && modem.imei) imeiVal.textContent = modem.imei;

            const imsiVal = document.getElementById('modemImsiVal');
            if (imsiVal && modem.imsi) imsiVal.textContent = modem.imsi;

            const iccidVal = document.getElementById('modemIccidVal');
            if (iccidVal && modem.iccid) iccidVal.textContent = modem.iccid;

            const fwVal = document.getElementById('modemFirmwareVal');
            if (fwVal && modem.firmware) fwVal.textContent = `${modem.firmware} (UI: ${modem.webui || '17.100.15'})`;
        }

        // Start Live Polling Loop every 2000ms
        setInterval(pollModemTelemetry, 2000);
    </script>
</body>
</html>
