<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';
require_once __DIR__ . '/includes/SettingsManager.php';

if (!Auth::check()) {
    header("Location: index.php");
    exit;
}

$state = SystemMonitor::getFullState();
$board = $state['board'] ?? [];
$settings = SettingsManager::getSettingsState();
$cpu = $settings['cpu'] ?? [];
$leds = $settings['leds'] ?? [];
$sys = $settings['system'] ?? [];
$specs = $settings['specs'] ?? [];
$wifi = $specs['wifi'] ?? [];
$storage = $specs['storage'] ?? [];
$soc = $specs['soc'] ?? [];
$memory = $specs['memory'] ?? [];
$networks = $specs['networks'] ?? [];
$os = $specs['os'] ?? [];
$telegram = $settings['telegram'] ?? [];
$currentPage = 'settings';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem - Orange Pi Zero 2</title>
    <meta name="description" content="Pengaturan Sistem, Daya & Performa CPU, Waktu, Keamanan, dan Manajemen Daya Orange Pi Gateway">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
    <style>
        .settings-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 16px;
        }

        .settings-section-panel {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--nm-raised-sm);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .settings-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding-bottom: 12px;
            border-bottom: 1px dashed rgba(182, 198, 220, 0.45);
        }

        .settings-item-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .settings-label-group strong {
            font-size: 13px;
            color: var(--text-heading);
            display: block;
            margin-bottom: 2px;
        }

        .settings-label-group span {
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .governor-badge-chip {
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            box-shadow: var(--nm-inset-sm);
            background: var(--bg-card);
        }

        /* Enhanced Hardware Specs Styling */
        .specs-bento-hero-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .spec-hero-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--nm-raised-sm);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition-fast);
        }

        .spec-hero-card:hover {
            box-shadow: var(--nm-raised);
            transform: translateY(-2px);
        }

        .spec-hero-card .hero-val {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-heading);
            display: block;
            line-height: 1.25;
        }

        .spec-hero-card .hero-lbl {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 2px;
        }

        .spec-tile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
            gap: 10px;
        }

        .spec-tile-box {
            background: var(--bg-inset);
            box-shadow: var(--nm-inset-sm);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .spec-tile-box .tile-lbl {
            font-size: 10.5px;
            color: var(--text-muted);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .spec-tile-box .tile-val {
            font-size: 12px;
            font-weight: 800;
            color: var(--text-heading);
        }

        .spec-core-matrix-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .spec-core-tile {
            background: var(--bg-inset);
            box-shadow: var(--nm-inset-sm);
            border-radius: var(--radius-sm);
            padding: 10px 6px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
        }

        .spec-core-tile strong {
            font-size: 11px;
            color: var(--text-heading);
        }

        .spec-core-tile span {
            font-size: 9.5px;
            color: #10b981;
            font-weight: 700;
        }

        .spec-port-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--nm-raised-sm);
            border: 1px solid rgba(182, 198, 220, 0.4);
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: var(--transition-fast);
        }

        .spec-port-card:hover {
            box-shadow: var(--nm-raised);
        }
    </style>
</head>
<body class="app-logged-in">

    <!-- Cockpit Wide Layout -->
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
                    <span class="header-hostname-pill">Pengaturan Sistem & Daya</span>
                </div>

                <div class="header-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn-new-device" onclick="switchSettingsTab('power_mgmt')" style="padding: 7px 14px; color: #ef4444;" title="Menu Daya & Reboot">
                        <i class="bi bi-power" style="color: #ef4444;"></i>
                        <span>Menu Daya</span>
                    </button>

                    <button type="button" class="btn-primary-neumorphic" onclick="refreshSettingsState()" style="padding: 8px 18px; font-size: 12px;" title="Segarkan Data Pengaturan">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Segarkan Data</span>
                    </button>

                    <div class="btn-new-device" style="cursor: default; padding: 7px 14px;" title="Waktu Aktif Sistem">
                        <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
                        <span>Aktif: <?= htmlspecialchars($board['uptime'] ?? '10m') ?></span>
                    </div>
                </div>
            </header>

            <!-- Hero HUD: Settings Summary -->
            <div class="cellular-hud-hero">
                <div class="hud-operator-identity">
                    <div class="hud-operator-logo-badge" style="color: #0284c7;">
                        <i class="bi bi-gear-fill"></i>
                    </div>
                    <div>
                        <h1 class="hud-carrier-name">Pengaturan Sistem <span style="font-size: 15px; font-weight: 700; color: #0284c7;">& Konfigurasi Daya</span></h1>
                        <div class="hud-tags-row">
                            <span class="hud-tech-pill" style="color: #059669;"><i class="bi bi-speedometer2"></i> CPU: <strong id="hudGovText"><?= htmlspecialchars($cpu['current_governor'] ?? 'ondemand') ?></strong> (<?= (int)($cpu['cur_freq_mhz'] ?? 1008) ?> MHz)</span>
                            <span class="hud-freq-pill" style="color: #f59e0b;"><i class="bi bi-thermometer-half"></i> Suhu: <strong><?= (float)($cpu['temperature_c'] ?? 45.0) ?>&deg;C</strong></span>
                            <span class="hud-plmn-pill" style="color: #6366f1;"><i class="bi bi-globe"></i> Host: <strong><?= htmlspecialchars($sys['hostname'] ?? 'orangepizero2') ?></strong></span>
                            <span class="room-spec-pill" style="font-size: 11px; font-weight: 800;">TZ: <?= htmlspecialchars($sys['timezone'] ?? 'Asia/Makassar') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Right Quick System Pill -->
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div class="hud-signal-capsule" style="padding: 10px 16px;">
                        <span class="hud-signal-dbm" style="color: #0284c7; font-size: 12.5px;">Allwinner H616 Quad-Core</span>
                        <span class="hud-signal-bar-txt" style="font-size: 10.5px;"><?= htmlspecialchars($sys['os_kernel'] ?? 'Armbian Linux') ?></span>
                    </div>
                </div>
            </div>

            <!-- Segmented Navigation Switcher -->
            <div class="nm-segmented-switch" style="margin-top: 16px; margin-bottom: 16px;">
                <button type="button" class="nm-seg-btn active" id="btnTabSpecs" onclick="switchSettingsTab('specs')">
                    <i class="bi bi-info-circle-fill" style="color: #38bdf8;"></i>
                    <span>Spesifikasi & Info Sistem</span>
                </button>
                <button type="button" class="nm-seg-btn" id="btnTabCpu" onclick="switchSettingsTab('cpu')">
                    <i class="bi bi-cpu-fill" style="color: #0284c7;"></i>
                    <span>Daya & Performa CPU</span>
                </button>
                <button type="button" class="nm-seg-btn" id="btnTabSystem" onclick="switchSettingsTab('system')">
                    <i class="bi bi-hdd-network-fill" style="color: #10b981;"></i>
                    <span>Sistem & Zona Waktu</span>
                </button>
                <button type="button" class="nm-seg-btn" id="btnTabTelegram" onclick="switchSettingsTab('telegram')">
                    <i class="bi bi-telegram" style="color: #0284c7;"></i>
                    <span>Layanan Bot Telegram</span>
                </button>
                <button type="button" class="nm-seg-btn" id="btnTabSecurity" onclick="switchSettingsTab('security')">
                    <i class="bi bi-shield-lock-fill" style="color: #f59e0b;"></i>
                    <span>Keamanan & Sandi Admin</span>
                </button>
                <button type="button" class="nm-seg-btn" id="btnTabBackup" onclick="switchSettingsTab('backup')">
                    <i class="bi bi-archive-fill" style="color: #8b5cf6;"></i>
                    <span>Cadangkan Data</span>
                </button>
                <button type="button" class="nm-seg-btn" id="btnTabPowerMgmt" onclick="switchSettingsTab('power_mgmt')">
                    <i class="bi bi-power" style="color: #ef4444;"></i>
                    <span>Manajemen Daya & Reboot</span>
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 0: SYSTEM HARDWARE & WIRELESS SPECIFICATIONS -->
            <!-- ========================================================================= -->
            <div id="sectionTabSpecs" style="display: flex; flex-direction: column; gap: 16px;">
                <!-- Top 4-Bento Hero Quick Specs -->
                <div class="specs-bento-hero-grid">
                    <div class="spec-hero-card">
                        <div class="file-badge-icon" style="color: #0284c7; width: 44px; height: 44px; font-size: 20px;">
                            <i class="bi bi-wifi"></i>
                        </div>
                        <div>
                            <span class="hero-lbl">Pemancar Wi-Fi AP</span>
                            <span class="hero-val"><?= htmlspecialchars($wifi['ssid'] ?? 'OcanAP') ?></span>
                            <span style="font-size: 11px; color: #059669; font-weight: 700;"><?= htmlspecialchars($wifi['channel'] ?? 'Kanal 6') ?> &bull; 2.4 GHz</span>
                        </div>
                    </div>

                    <div class="spec-hero-card">
                        <div class="file-badge-icon" style="color: #8b5cf6; width: 44px; height: 44px; font-size: 20px;">
                            <i class="bi bi-sd-card-fill"></i>
                        </div>
                        <div>
                            <span class="hero-lbl">Media Penyimpanan</span>
                            <span class="hero-val"><?= htmlspecialchars($storage['total_capacity'] ?? '64 GB') ?></span>
                            <span style="font-size: 11px; color: #8b5cf6; font-weight: 700;">MicroSD UHS-I &bull; ext4</span>
                        </div>
                    </div>

                    <div class="spec-hero-card">
                        <div class="file-badge-icon" style="color: #f59e0b; width: 44px; height: 44px; font-size: 20px;">
                            <i class="bi bi-cpu-fill"></i>
                        </div>
                        <div>
                            <span class="hero-lbl">Prosesor SoC</span>
                            <span class="hero-val">Allwinner H616</span>
                            <span style="font-size: 11px; color: #f59e0b; font-weight: 700;">Quad-Core Cortex-A53</span>
                        </div>
                    </div>

                    <div class="spec-hero-card">
                        <div class="file-badge-icon" style="color: #10b981; width: 44px; height: 44px; font-size: 20px;">
                            <i class="bi bi-memory"></i>
                        </div>
                        <div>
                            <span class="hero-lbl">Memori Sistem</span>
                            <span class="hero-val"><?= $memory['total_mb'] ?? 969 ?> MB DDR3</span>
                            <span style="font-size: 11px; color: #10b981; font-weight: 700;">+ 2.5 GB ZRAM Swap</span>
                        </div>
                    </div>
                </div>

                <!-- Detailed 6-Bento Card Grid -->
                <div class="settings-card-grid">
                    <!-- 1. Wi-Fi Specifications Card -->
                    <div class="settings-section-panel">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="file-badge-icon" style="color: #0284c7; width: 38px; height: 38px; font-size: 18px;">
                                    <i class="bi bi-broadcast-pin"></i>
                                </div>
                                <div>
                                    <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Modul Wi-Fi & Antarmuka Nirkabel</h3>
                                    <span style="font-size: 11px; color: var(--text-muted);">Spesifikasi radio pemancar Access Point OcanAP</span>
                                </div>
                            </div>
                            <span class="service-status-pill" style="color: #10b981;">
                                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#10b981; box-shadow:0 0 6px #10b981;"></span>
                                <span>AP UP (Master)</span>
                            </span>
                        </div>

                        <!-- 4-Tile Spec Grid -->
                        <div class="spec-tile-grid">
                            <div class="spec-tile-box">
                                <span class="tile-lbl"><i class="bi bi-broadcast" style="color: #0284c7;"></i> Kanal Radio</span>
                                <span class="tile-val"><?= htmlspecialchars($wifi['channel'] ?? 'Kanal 6 (2437 MHz)') ?></span>
                            </div>
                            <div class="spec-tile-box">
                                <span class="tile-lbl"><i class="bi bi-lightning-charge-fill" style="color: #f59e0b;"></i> TxPower</span>
                                <span class="tile-val" style="color: #059669;"><?= htmlspecialchars($wifi['tx_power'] ?? '20 dBm (100 mW)') ?></span>
                            </div>
                            <div class="spec-tile-box">
                                <span class="tile-lbl"><i class="bi bi-shield-lock-fill" style="color: #10b981;"></i> Keamanan</span>
                                <span class="tile-val"><?= htmlspecialchars($wifi['encryption'] ?? 'WPA2-PSK (AES)') ?></span>
                            </div>
                            <div class="spec-tile-box">
                                <span class="tile-lbl"><i class="bi bi-wifi" style="color: #6366f1;"></i> Standar Wi-Fi</span>
                                <span class="tile-val"><?= htmlspecialchars($wifi['standard'] ?? '802.11 b/g/n') ?></span>
                            </div>
                        </div>

                        <!-- Hardware Chipset & MAC Footer -->
                        <div style="background: rgba(2, 132, 199, 0.04); border-radius: var(--radius-md); padding: 12px; border: 1px dashed rgba(182, 198, 220, 0.45); display: flex; flex-direction: column; gap: 6px;">
                            <div style="display: flex; justify-content: space-between; font-size: 11.5px;">
                                <span style="color: var(--text-muted);">Chipset Hardware:</span>
                                <strong style="color: var(--text-heading);"><?= htmlspecialchars($wifi['chipset'] ?? 'Allwinner AW859A / XR829') ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 11.5px;">
                                <span style="color: var(--text-muted);">Alamat MAC (wlan0):</span>
                                <code style="color: #0284c7; font-weight: 700;"><?= htmlspecialchars($wifi['mac_address'] ?? '1C:1D:EC:8D:9B:FF') ?></code>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Storage & Partitions Card -->
                    <div class="settings-section-panel">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="file-badge-icon" style="color: #8b5cf6; width: 38px; height: 38px; font-size: 18px;">
                                    <i class="bi bi-sd-card-fill"></i>
                                </div>
                                <div>
                                    <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Media Penyimpanan & Partisi</h3>
                                    <span style="font-size: 11px; color: var(--text-muted);">Alokasi kapasitas MicroSD dan RAM-disk Linux</span>
                                </div>
                            </div>
                            <span class="service-status-pill" style="color: #10b981;">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>S.M.A.R.T OK</span>
                            </span>
                        </div>

                        <!-- Dual Partition Inset Panels -->
                        <?php $rp = $storage['root_partition'] ?? []; ?>
                        <div style="background: var(--bg-inset); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 14px; display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="font-size: 12px; color: var(--text-heading);"><i class="bi bi-hdd-fill" style="color: #8b5cf6;"></i> Partisi Root Sistem (<code>/</code>)</strong>
                                <span style="font-size: 12px; color: #0284c7; font-weight: 800;"><?= $rp['used_gb'] ?? 12 ?> GB / <?= $rp['total_gb'] ?? 58.9 ?> GB (<?= $rp['used_percent'] ?? 20 ?>%)</span>
                            </div>
                            <div style="width: 100%; height: 8px; border-radius: var(--radius-pill); background: rgba(182, 198, 220, 0.35); overflow: hidden; box-shadow: var(--nm-inset-sm);">
                                <div style="width: <?= $rp['used_percent'] ?? 20 ?>%; height: 100%; border-radius: var(--radius-pill); background: linear-gradient(90deg, #8b5cf6, #38bdf8);"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 10.5px; color: var(--text-muted);">
                                <span>Format: <?= htmlspecialchars($rp['filesystem'] ?? 'ext4 Journaling') ?></span>
                                <span>Bebas: <strong style="color: #059669;"><?= $rp['free_gb'] ?? 47 ?> GB</strong></span>
                            </div>
                        </div>

                        <!-- ZRAM Log Panel -->
                        <?php $zlog = $storage['zram_log'] ?? []; ?>
                        <div style="background: var(--bg-inset); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 11.5px; color: var(--text-heading); display: block;"><i class="bi bi-memory" style="color: #10b981;"></i> RAM-Disk Log (/var/log)</strong>
                                <span style="font-size: 10.5px; color: var(--text-muted);">Proteksi siklus tulis MicroSD Card</span>
                            </div>
                            <span class="service-meta-chip" style="color: #10b981;"><strong><?= htmlspecialchars($zlog['size'] ?? '50 MB') ?></strong> (ZRAM1)</span>
                        </div>
                    </div>

                    <!-- 3. SoC, CPU & GPU Specs Card -->
                    <div class="settings-section-panel">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="file-badge-icon" style="color: #f59e0b; width: 38px; height: 38px; font-size: 18px;">
                                    <i class="bi bi-cpu-fill"></i>
                                </div>
                                <div>
                                    <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Prosesor SoC Quad-Core & Grafis</h3>
                                    <span style="font-size: 11px; color: var(--text-muted);">Allwinner H616 64-bit ARMv8 Architecture</span>
                                </div>
                            </div>
                            <span class="service-status-pill" style="color: #f59e0b;">
                                <span>28nm SoC</span>
                            </span>
                        </div>

                        <!-- 4-Core Visual Core Matrix -->
                        <div>
                            <span style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">STATUS 4 INTI CPU (QUAD-CORE CORTEX-A53):</span>
                            <div class="spec-core-matrix-grid">
                                <div class="spec-core-tile">
                                    <i class="bi bi-cpu" style="color: #f59e0b; font-size: 15px;"></i>
                                    <strong>Core #0</strong>
                                    <span>Aktif &bull; 1.5 GHz</span>
                                </div>
                                <div class="spec-core-tile">
                                    <i class="bi bi-cpu" style="color: #f59e0b; font-size: 15px;"></i>
                                    <strong>Core #1</strong>
                                    <span>Aktif &bull; 1.5 GHz</span>
                                </div>
                                <div class="spec-core-tile">
                                    <i class="bi bi-cpu" style="color: #f59e0b; font-size: 15px;"></i>
                                    <strong>Core #2</strong>
                                    <span>Aktif &bull; 1.5 GHz</span>
                                </div>
                                <div class="spec-core-tile">
                                    <i class="bi bi-cpu" style="color: #f59e0b; font-size: 15px;"></i>
                                    <strong>Core #3</strong>
                                    <span>Aktif &bull; 1.5 GHz</span>
                                </div>
                            </div>
                        </div>

                        <!-- GPU & Load Avg Row -->
                        <div style="display: flex; flex-direction: column; gap: 8px; border-top: 1px dashed rgba(182, 198, 220, 0.45); padding-top: 10px;">
                            <div style="display: flex; justify-content: space-between; font-size: 11.5px;">
                                <span style="color: var(--text-muted);">Akselerator Grafis (GPU):</span>
                                <strong style="color: var(--text-heading);"><?= htmlspecialchars($soc['gpu'] ?? 'ARM Mali G31 MP2') ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 11.5px;">
                                <span style="color: var(--text-muted);">Beban Antrean Sistem (Load Avg):</span>
                                <code style="color: #0284c7; font-weight: 700;"><?= htmlspecialchars($soc['load_average'] ?? '0.85, 0.62, 0.48') ?></code>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Memory RAM & ZRAM Specs Card -->
                    <div class="settings-section-panel">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="file-badge-icon" style="color: #10b981; width: 38px; height: 38px; font-size: 18px;">
                                    <i class="bi bi-memory"></i>
                                </div>
                                <div>
                                    <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Sub-Sistem Memori RAM & Swap</h3>
                                    <span style="font-size: 11px; color: var(--text-muted);">Alokasi memori fisik dan ZRAM ultra-cepat</span>
                                </div>
                            </div>
                            <span class="service-status-pill" style="color: #10b981;">
                                <span>32-bit DDR3</span>
                            </span>
                        </div>

                        <!-- Physical RAM Progress -->
                        <?php 
                            $ramTotalMb = (int)($memory['total_mb'] ?? 969);
                            $ramUsedMb = (int)($memory['used_mb'] ?? 796);
                            $ramPct = ($ramTotalMb > 0) ? round(($ramUsedMb / $ramTotalMb) * 100, 1) : 80;
                        ?>
                        <div style="background: var(--bg-inset); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 14px; display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="font-size: 12px; color: var(--text-heading);">RAM Fisik DDR3 (Total: <?= $ramTotalMb ?> MB)</strong>
                                <span style="font-size: 12px; color: #10b981; font-weight: 800;"><?= $ramUsedMb ?> MB (<?= $ramPct ?>%)</span>
                            </div>
                            <div style="width: 100%; height: 8px; border-radius: var(--radius-pill); background: rgba(182, 198, 220, 0.35); overflow: hidden; box-shadow: var(--nm-inset-sm);">
                                <div style="width: <?= $ramPct ?>%; height: 100%; border-radius: var(--radius-pill); background: linear-gradient(90deg, #10b981, #34d399);"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 10.5px; color: var(--text-muted);">
                                <span>Kecepatan Bus: 667 MHz</span>
                                <span>Tersedia: <strong style="color: #10b981;"><?= $memory['available_mb'] ?? 173 ?> MB</strong></span>
                            </div>
                        </div>

                        <!-- ZRAM Swap Progress -->
                        <?php 
                            $swapTotalMb = (int)($memory['swap_total_mb'] ?? 2532);
                            $swapUsedMb = (int)($memory['swap_used_mb'] ?? 445);
                            $swapPct = ($swapTotalMb > 0) ? round(($swapUsedMb / $swapTotalMb) * 100, 1) : 18;
                        ?>
                        <div style="background: var(--bg-inset); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 11.5px; color: var(--text-heading); display: block;">Swap Pool ZRAM (ZSTD Compression)</strong>
                                <span style="font-size: 10.5px; color: var(--text-muted);">Alokasi memori terkompresi: <?= $swapUsedMb ?> MB / <?= $swapTotalMb ?> MB (<?= $swapPct ?>%)</span>
                            </div>
                            <span class="service-meta-chip" style="color: #8b5cf6;"><strong><?= round($swapTotalMb / 1024, 1) ?> GB Pool</strong></span>
                        </div>
                    </div>

                    <!-- 5. Physical & Virtual Network Ports Card -->
                    <div class="settings-section-panel" style="grid-column: 1 / -1;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="file-badge-icon" style="color: #6366f1; width: 38px; height: 38px; font-size: 18px;">
                                    <i class="bi bi-hdd-network-fill"></i>
                                </div>
                                <div>
                                    <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Antarmuka Port Jaringan Fisik & Virtual</h3>
                                    <span style="font-size: 11px; color: var(--text-muted);">Status konektivitas port Ethernet, Modem 4G LTE, dan Virtual Tunnel</span>
                                </div>
                            </div>
                            <span class="room-spec-pill" style="font-size: 11px; font-weight: 800;">3 Port Terdaftar</span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 14px;">
                            <?php foreach ($networks as $net): 
                                $isLinkUp = (strpos(strtolower($net['status']), 'up') !== false || strpos(strtolower($net['status']), 'aktif') !== false);
                                $dotColor = $isLinkUp ? '#10b981' : '#94a3b8';
                            ?>
                                <div class="spec-port-card">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?= $dotColor ?>; box-shadow:0 0 8px <?= $dotColor ?>;"></span>
                                            <strong style="font-size: 13px; color: var(--text-heading);"><?= htmlspecialchars($net['name']) ?></strong>
                                        </div>
                                        <code style="font-size: 11.5px; background: rgba(2, 132, 199, 0.08); padding: 2px 8px; border-radius: var(--radius-pill); color: #0284c7; font-weight: 700;"><?= htmlspecialchars($net['iface']) ?></code>
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.4;"><?= htmlspecialchars($net['type']) ?></span>
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 10.5px; border-top: 1px dashed rgba(182, 198, 220, 0.35); padding-top: 8px; margin-top: 2px;">
                                        <span style="color: var(--text-muted);">MAC: <code><?= htmlspecialchars($net['mac']) ?></code></span>
                                        <strong style="color: <?= $dotColor ?>;"><?= htmlspecialchars($net['status']) ?></strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 6. OS & Board Specifications Card -->
                    <div class="settings-section-panel" style="grid-column: 1 / -1;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="file-badge-icon" style="color: #64748b; width: 38px; height: 38px; font-size: 18px;">
                                <i class="bi bi-motherboard"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Platform Sistem Operasi & Hardware Board</h3>
                                <span style="font-size: 11px; color: var(--text-muted);">Detail arsitektur papan sirkuit Orange Pi dan kernel Linux</span>
                            </div>
                        </div>

                        <div class="spec-tile-grid">
                            <div class="spec-tile-box">
                                <span class="tile-lbl"><i class="bi bi-motherboard" style="color: #64748b;"></i> Model Papan</span>
                                <span class="tile-val"><?= htmlspecialchars($os['board_model'] ?? 'Orange Pi Zero 2') ?></span>
                            </div>
                            <div class="spec-tile-box">
                                <span class="tile-lbl"><i class="bi bi-box-seam" style="color: #0284c7;"></i> Distribusi OS</span>
                                <span class="tile-val"><?= htmlspecialchars($os['distro'] ?? 'Armbian Linux') ?></span>
                            </div>
                            <div class="spec-tile-box">
                                <span class="tile-lbl"><i class="bi bi-terminal" style="color: #10b981;"></i> Kernel Linux</span>
                                <span class="tile-val"><code><?= htmlspecialchars($os['kernel'] ?? 'Linux 6.18.44') ?></code></span>
                            </div>
                            <div class="spec-tile-box">
                                <span class="tile-lbl"><i class="bi bi-lightning-charge-fill" style="color: #f59e0b;"></i> Input Daya</span>
                                <span class="tile-val"><?= htmlspecialchars($os['power_input'] ?? 'USB Type-C 5V') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 1: CPU GOVERNOR & HARDWARE POWER -->
            <!-- ========================================================================= -->
            <div id="sectionTabCpu" class="settings-card-grid" style="display: none;">
                <!-- CPU Scaling Governor Card -->
                <div class="settings-section-panel">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #0284c7; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Profil Skala CPU (Governor)</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Atur mode kinerja dan penghematan daya prosesor</span>
                        </div>
                    </div>

                    <form onsubmit="handleSaveGovernor(event)" style="display: flex; flex-direction: column; gap: 12px;">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">PILIH GOVERNOR CPU:</label>
                            <select id="selectCpuGov" class="btn-new-device" style="width: 100%; padding: 8px 12px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                <?php foreach ($cpu['available_governors'] as $gov): ?>
                                    <option value="<?= htmlspecialchars($gov) ?>" <?= ($gov === ($cpu['current_governor'] ?? 'ondemand')) ? 'selected' : '' ?>>
                                        <?= strtoupper($gov) ?> <?= ($gov === 'ondemand') ? '(Otomatis / Seimbang - Rekomendasi)' : (($gov === 'performance') ? '(Performa Maksimal)' : (($gov === 'powersave') ? '(Hemat Daya)' : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="background: rgba(2, 132, 199, 0.04); border-radius: var(--radius-md); padding: 12px; border: 1px dashed rgba(182, 198, 220, 0.45);">
                            <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px;">
                                <span style="color: var(--text-muted);">Frekuensi Min / Max:</span>
                                <strong><?= (int)($cpu['min_freq_mhz'] ?? 480) ?> MHz &ndash; <?= (int)($cpu['max_freq_mhz'] ?? 1512) ?> MHz</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                                <span style="color: var(--text-muted);">Frekuensi Saat Ini:</span>
                                <strong style="color: #0284c7;" id="textCurFreq"><?= (int)($cpu['cur_freq_mhz'] ?? 1008) ?> MHz</strong>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-primary-neumorphic" id="btnSaveGov" style="padding: 8px 18px; font-size: 12px;">
                                <i class="bi bi-check-lg"></i>
                                <span>Terapkan Governor</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Hardware LED Indicators Card -->
                <div class="settings-section-panel">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #10b981; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-lightbulb-fill"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Indikator Lampu LED Hardware</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Atur pemicu (trigger) lampu LED pada papan board</span>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <!-- Green LED (Power) -->
                        <div class="settings-item-row">
                            <div class="settings-label-group">
                                <strong>ðŸŸ¢ LED Hijau (Power)</strong>
                                <span>Lampu indikator daya utama</span>
                            </div>
                            <select id="selectLedGreen" class="btn-new-device" onchange="handleSaveLed('green', this.value)" style="padding: 6px 10px; font-size: 11.5px; font-weight: 700; cursor: pointer;">
                                <option value="none" <?= ($leds['green_power'] === 'none') ? 'selected' : '' ?>>Mati (None)</option>
                                <option value="default-on" <?= ($leds['green_power'] === 'default-on') ? 'selected' : '' ?>>Menyala Terus (On)</option>
                                <option value="heartbeat" <?= ($leds['green_power'] === 'heartbeat') ? 'selected' : '' ?>>Denyut Jantung (Heartbeat)</option>
                                <option value="activity" <?= ($leds['green_power'] === 'activity') ? 'selected' : '' ?>>Aktivitas CPU (Activity)</option>
                                <option value="mmc0" <?= ($leds['green_power'] === 'mmc0') ? 'selected' : '' ?>>Aktivitas MicroSD (MMC)</option>
                            </select>
                        </div>

                        <!-- Red LED (Status) -->
                        <div class="settings-item-row">
                            <div class="settings-label-group">
                                <strong>ðŸ”´ LED Merah (Status)</strong>
                                <span>Lampu indikator status sistem</span>
                            </div>
                            <select id="selectLedRed" class="btn-new-device" onchange="handleSaveLed('red', this.value)" style="padding: 6px 10px; font-size: 11.5px; font-weight: 700; cursor: pointer;">
                                <option value="heartbeat" <?= ($leds['red_status'] === 'heartbeat') ? 'selected' : '' ?>>Denyut Jantung (Heartbeat)</option>
                                <option value="activity" <?= ($leds['red_status'] === 'activity') ? 'selected' : '' ?>>Aktivitas CPU (Activity)</option>
                                <option value="default-on" <?= ($leds['red_status'] === 'default-on') ? 'selected' : '' ?>>Menyala Terus (On)</option>
                                <option value="none" <?= ($leds['red_status'] === 'none') ? 'selected' : '' ?>>Mati (None)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 2: SYSTEM & TIMEZONE -->
            <!-- ========================================================================= -->
            <div id="sectionTabSystem" class="settings-card-grid" style="display: none;">
                <!-- Hostname Card -->
                <div class="settings-section-panel">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #6366f1; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-hdd-network"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Identitas Hostname Gateway</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Nama host perangkat pada jaringan lokal</span>
                        </div>
                    </div>

                    <form onsubmit="handleSaveHostname(event)" style="display: flex; flex-direction: column; gap: 12px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">NAMA HOSTNAME:</label>
                            <input type="text" id="inputHostname" class="btn-new-device" value="<?= htmlspecialchars($sys['hostname'] ?? 'orangepizero2') ?>" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-primary-neumorphic" style="padding: 8px 18px; font-size: 12px;">
                                <i class="bi bi-floppy-fill"></i>
                                <span>Simpan Hostname</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Timezone Card -->
                <div class="settings-section-panel">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #059669; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Zona Waktu & Tanggal (NTP)</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Sinkronisasi waktu sistem dengan server waktu global</span>
                        </div>
                    </div>

                    <form onsubmit="handleSaveTimezone(event)" style="display: flex; flex-direction: column; gap: 12px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">PILIH ZONA WAKTU:</label>
                            <select id="selectTimezone" class="btn-new-device" style="width: 100%; padding: 8px 12px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                <option value="Asia/Jakarta" <?= ($sys['timezone'] === 'Asia/Jakarta') ? 'selected' : '' ?>>Asia/Jakarta (WIB, UTC+7)</option>
                                <option value="Asia/Makassar" <?= ($sys['timezone'] === 'Asia/Makassar') ? 'selected' : '' ?>>Asia/Makassar (WITA, UTC+8)</option>
                                <option value="Asia/Jayapura" <?= ($sys['timezone'] === 'Asia/Jayapura') ? 'selected' : '' ?>>Asia/Jayapura (WIT, UTC+9)</option>
                                <option value="UTC" <?= ($sys['timezone'] === 'UTC') ? 'selected' : '' ?>>UTC (Universal Coordinated Time)</option>
                            </select>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted);">
                            Waktu Sistem Saat Ini: <strong style="color: var(--text-heading);"><?= $sys['date_time'] ?></strong>
                        </div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-primary-neumorphic" style="padding: 8px 18px; font-size: 12px;">
                                <i class="bi bi-check2-circle"></i>
                                <span>Terapkan Zona Waktu</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 2.5: TELEGRAM SERVICE & BOT NOTIFICATIONS -->
            <!-- ========================================================================= -->
            <div id="sectionTabTelegram" class="settings-card-grid" style="display: none;">
                <!-- 1. Telegram Bot Daemon Status & Credentials -->
                <div class="settings-section-panel">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="file-badge-icon" style="color: #0284c7; width: 38px; height: 38px; font-size: 18px;">
                                <i class="bi bi-telegram"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Layanan Bot Telegram (OcanAP)</h3>
                                <span style="font-size: 11px; color: var(--text-muted);">Kontrol jarak jauh dan notifikasi gateway via Telegram</span>
                            </div>
                        </div>
                        <?php 
                            $tgSvc = $telegram['service'] ?? [];
                            $tgRunning = !empty($tgSvc['is_running']);
                        ?>
                        <span class="service-status-pill" style="color: <?= $tgRunning ? '#10b981' : '#ef4444' ?>;">
                            <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:<?= $tgRunning ? '#10b981' : '#ef4444' ?>; box-shadow:0 0 6px <?= $tgRunning ? '#10b981' : '#ef4444' ?>;"></span>
                            <span><?= $tgRunning ? 'DAEMON AKTIF' : 'INAKTIF' ?></span>
                        </span>
                    </div>

                    <!-- Daemon Process Info -->
                    <div style="background: var(--bg-inset); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 11px; color: var(--text-muted);">
                            PID: <strong style="color: var(--text-heading);"><?= htmlspecialchars($tgSvc['pid'] ?? '-') ?></strong> &bull;
                            Memori: <strong style="color: var(--text-heading);"><?= htmlspecialchars($tgSvc['memory'] ?? '-') ?></strong> &bull;
                            Unit: <code>ocanap-telegram-bot</code>
                        </div>
                        <button type="button" class="btn-action-round" onclick="handleRestartTelegramDaemon()" title="Muat ulang daemon bot" style="width: 28px; height: 28px; font-size: 12px;">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>

                    <!-- Bot Credentials Form -->
                    <form onsubmit="handleSaveTelegram(event)" style="display: flex; flex-direction: column; gap: 12px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">BOT API TOKEN (DARI @BotFather):</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="password" id="inputTgToken" class="btn-new-device" value="<?= htmlspecialchars($telegram['token'] ?? '') ?>" placeholder="Contoh: 1234567890:ABCdefGHIjklMNOpqrs" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 11.5px;" required>
                                <button type="button" class="btn-new-device" onclick="togglePasswordVisibility('inputTgToken')" style="padding: 0 10px;" title="Lihat/Sembunyikan Token">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">ADMIN CHAT ID (ID TELEGRAM PEMILIK):</label>
                            <input type="text" id="inputTgChatId" class="btn-new-device" value="<?= htmlspecialchars($telegram['chat_id'] ?? '') ?>" placeholder="Contoh: 1025855210" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 11.5px;" required>
                            <span style="font-size: 10.5px; color: var(--text-muted); display: block; margin-top: 3px;">Kirim pesan <code>/start</code> ke bot untuk mendapatkan Chat ID Anda.</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                            <button type="button" class="btn-action-round" onclick="handleTestTelegramMessage()" style="padding: 6px 14px; width: auto; height: auto; border-radius: var(--radius-pill); font-size: 11.5px; display: flex; align-items: center; gap: 6px; color: #0284c7;">
                                <i class="bi bi-send-fill"></i>
                                <span>Uji Coba Kirim Pesan</span>
                            </button>
                            <button type="submit" class="btn-primary-neumorphic" id="btnSaveTg" style="padding: 8px 18px; font-size: 12px;">
                                <i class="bi bi-floppy-fill"></i>
                                <span>Simpan Pengaturan Bot</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 2. Automatic Notification Triggers -->
                <div class="settings-section-panel">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #f59e0b; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Notifikasi Otomatis (Event Alerts)</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Pilih kejadian yang akan memicu pengiriman pesan ke Telegram</span>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <label class="settings-item-row" style="cursor: pointer; margin: 0;">
                            <div class="settings-label-group">
                                <strong>ðŸŽŸï¸ Notifikasi Penggunaan Voucher</strong>
                                <span>Kirim pesan saat ada voucher yang baru diaktivasi oleh klien</span>
                            </div>
                            <input type="checkbox" id="checkTgVoucher" <?= !empty($telegram['notify_voucher']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #0284c7; cursor: pointer;">
                        </label>

                        <label class="settings-item-row" style="cursor: pointer; margin: 0;">
                            <div class="settings-label-group">
                                <strong>ðŸ‘¥ Notifikasi Klien Tamu Baru</strong>
                                <span>Kirim pesan saat perangkat baru terhubung ke Hotspot Wi-Fi</span>
                            </div>
                            <input type="checkbox" id="checkTgGuest" <?= !empty($telegram['notify_guest']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #0284c7; cursor: pointer;">
                        </label>

                        <label class="settings-item-row" style="cursor: pointer; margin: 0;">
                            <div class="settings-label-group">
                                <strong>ðŸ”„ Notifikasi Watchdog / Failover</strong>
                                <span>Kirim pesan saat koneksi internet 4G terputus atau kembali online</span>
                            </div>
                            <input type="checkbox" id="checkTgWatchdog" <?= !empty($telegram['notify_watchdog']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #0284c7; cursor: pointer;">
                        </label>

                        <label class="settings-item-row" style="cursor: pointer; margin: 0;">
                            <div class="settings-label-group">
                                <strong>ðŸŒ¡ï¸ Peringatan Termal Suhu Tinggi</strong>
                                <span>Kirim peringatan darurat jika suhu CPU Orange Pi melebihi 70Â°C</span>
                            </div>
                            <input type="checkbox" id="checkTgTemp" <?= !empty($telegram['notify_temp']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #0284c7; cursor: pointer;">
                        </label>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
                        <button type="button" class="btn-primary-neumorphic" onclick="handleSaveTelegram(event)" style="padding: 8px 18px; font-size: 12px;">
                            <i class="bi bi-check-lg"></i>
                            <span>Terapkan Notifikasi</span>
                        </button>
                    </div>
                </div>

                <!-- 3. Interactive Bot Commands Reference -->
                <div class="settings-section-panel" style="grid-column: 1 / -1;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #10b981; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-chat-left-dots-fill"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Daftar Perintah Interaktif Bot Telegram</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Perintah yang dapat dikirimkan langsung ke <b>@OcanAPBot</b> di aplikasi Telegram</span>
                        </div>
                    </div>

                    <div class="spec-tile-grid">
                        <div class="spec-tile-box">
                            <span class="tile-lbl"><code>/start</code></span>
                            <span class="tile-val" style="font-size: 11px; font-weight: 600;">Menu Utama & Dasbor Interaktif</span>
                        </div>
                        <div class="spec-tile-box">
                            <span class="tile-lbl"><code>/status</code></span>
                            <span class="tile-val" style="font-size: 11px; font-weight: 600;">Status CPU, RAM, Suhu & Modem</span>
                        </div>
                        <div class="spec-tile-box">
                            <span class="tile-lbl"><code>/voucher</code></span>
                            <span class="tile-val" style="font-size: 11px; font-weight: 600;">Generate Voucher Baru Instan</span>
                        </div>
                        <div class="spec-tile-box">
                            <span class="tile-lbl"><code>/clients</code></span>
                            <span class="tile-val" style="font-size: 11px; font-weight: 600;">Daftar Klien Hotspot Terhubung</span>
                        </div>
                        <div class="spec-tile-box">
                            <span class="tile-lbl"><code>/speed</code></span>
                            <span class="tile-val" style="font-size: 11px; font-weight: 600;">Uji Latensi Ping & Gateway WAN</span>
                        </div>
                        <div class="spec-tile-box">
                            <span class="tile-lbl"><code>/reboot</code></span>
                            <span class="tile-val" style="font-size: 11px; font-weight: 600;">Mulai Ulang Orange Pi Jarak Jauh</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 3: SECURITY & ADMIN PASSWORD -->
            <!-- ========================================================================= -->
            <div id="sectionTabSecurity" class="settings-card-grid" style="display: none;">
                <div class="settings-section-panel" style="max-width: 580px; margin: 0 auto;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #f59e0b; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Ubah Kata Sandi Admin Dasbor</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Perbarui kata sandi login untuk pengguna Administrator (admin)</span>
                        </div>
                    </div>

                    <form onsubmit="handleChangePassword(event)" style="display: flex; flex-direction: column; gap: 12px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">KATA SANDI BARU:</label>
                            <input type="password" id="inputNewPass" class="btn-new-device" placeholder="Minimal 4 karakter" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>

                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">KONFIRMASI KATA SANDI BARU:</label>
                            <input type="password" id="inputConfirmPass" class="btn-new-device" placeholder="Ulangi kata sandi baru" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 6px;">
                            <button type="submit" class="btn-primary-neumorphic" id="btnSubmitPass" style="padding: 8px 18px; font-size: 12px;">
                                <i class="bi bi-key-fill"></i>
                                <span>Simpan Kata Sandi Baru</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 4: BACKUP & RESTORE -->
            <!-- ========================================================================= -->
            <div id="sectionTabBackup" class="settings-card-grid" style="display: none;">
                <div class="settings-section-panel" style="max-width: 620px; margin: 0 auto;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #8b5cf6; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-archive-fill"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Cadangkan Konfigurasi Dasbor</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Ekspor data voucher, anggota hotspot, dan konfigurasi rclone</span>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <p style="font-size: 12px; color: var(--text-muted); line-height: 1.5; margin: 0;">
                            File cadangan berisi data JSON lengkap dari pengaturan Captive Portal, daftar voucher yang aktif/tersedia, anggota terdaftar, serta konfigurasi cloud rclone.
                        </p>

                        <div style="display: flex; justify-content: flex-start; gap: 10px; margin-top: 6px;">
                            <button type="button" class="btn-primary-neumorphic" onclick="downloadBackupFile()" style="padding: 8px 18px; font-size: 12px;">
                                <i class="bi bi-cloud-arrow-down-fill"></i>
                                <span>Unduh Berkas Cadangan (JSON)</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 5: POWER MANAGEMENT (REBOOT & SHUTDOWN) -->
            <!-- ========================================================================= -->
            <div id="sectionTabPowerMgmt" class="settings-card-grid" style="display: none;">
                <!-- Reboot Card -->
                <div class="settings-section-panel">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #0284c7; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-arrow-clockwise"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Muat Ulang Gateway (Reboot)</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Mulai ulang sistem operasi Linux Orange Pi secara aman</span>
                        </div>
                    </div>

                    <p style="font-size: 12px; color: var(--text-muted); line-height: 1.45; margin: 0;">
                        Proses reboot memerlukan waktu sekitar 30-45 detik hingga seluruh daemon jaringan (*hostapd, dnsmasq, AdGuard*) aktif kembali.
                    </p>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="button" class="btn-primary-neumorphic" onclick="executeSystemReboot()" style="padding: 8px 18px; font-size: 12px; color: #0284c7;">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Mulai Ulang (Reboot)</span>
                        </button>
                    </div>
                </div>

                <!-- Shutdown Card -->
                <div class="settings-section-panel">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="file-badge-icon" style="color: #ef4444; width: 38px; height: 38px; font-size: 18px;">
                            <i class="bi bi-power"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 14.5px; color: var(--text-heading); font-weight: 800;">Matikan Daya Sistem (Shutdown)</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Hentikan seluruh proses sebelum mencabut adaptor daya</span>
                        </div>
                    </div>

                    <p style="font-size: 12px; color: var(--text-muted); line-height: 1.45; margin: 0;">
                        Seluruh proses dan sistem berkas MicroSD akan di-unmount secara aman untuk mencegah kerusakan data pada media penyimpanan.
                    </p>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="button" class="btn-new-device" onclick="executeSystemShutdown()" style="padding: 8px 18px; font-size: 12px; color: #ef4444;">
                            <i class="bi bi-plug"></i>
                            <span>Matikan Daya (Power Off)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scheduled Reboot Section -->
            <div class="room-card" style="margin-top:20px;">
                <div class="room-card-top">
                    <span class="room-card-title">Jadwal Reboot Otomatis</span>
                    <span class="room-spec-pill"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="room-card-body">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <select id="schedHour" style="padding:8px;border-radius:var(--radius-sm);background:var(--bg-inset);color:var(--text-main);">
                            <?php for($i=0;$i<24;$i++) echo "<option value='$i' ".($i===4?'selected':'').">$i</option>"; ?>
                        </select>
                        <span style="font-size:18px;">:</span>
                        <select id="schedMinute" style="padding:8px;border-radius:var(--radius-sm);background:var(--bg-inset);color:var(--text-main);">
                            <?php for($i=0;$i<60;$i+=5) echo "<option value='$i'>$i</option>"; ?>
                        </select>
                        <button type="button" class="btn-primary-neumorphic" onclick="setScheduledReboot()" style="padding:8px 16px;">
                            <i class="bi bi-clock"></i> <span>Set Jadwal</span>
                        </button>
                        <button type="button" class="btn-action-round" onclick="cancelScheduledReboot()" style="padding:8px 16px;">
                            <span>Batal</span>
                        </button>
                    </div>
                    <p id="schedStatus" style="font-size:11px;color:var(--text-muted);margin-top:8px;"></p>
                </div>
            </div>

            <!-- Wake-on-LAN Section -->
            <div class="room-card" style="margin-top:20px;">
                <div class="room-card-top">
                    <span class="room-card-title">Wake-on-LAN</span>
                    <span class="room-spec-pill"><i class="bi bi-pc-display"></i></span>
                </div>
                <div class="room-card-body">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <input type="text" id="wolMac" placeholder="MAC Address (xx:xx:xx:xx:xx:xx)"
                               style="padding:8px;border-radius:var(--radius-sm);background:var(--bg-inset);color:var(--text-main);width:200px;">
                        <button type="button" class="btn-primary-neumorphic" onclick="sendWOL()" style="padding:8px 16px;">
                            <i class="bi bi-power"></i> <span>Wake</span>
                        </button>
                    </div>
                    <p id="wolStatus" style="font-size:11px;color:var(--text-muted);margin-top:8px;"></p>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Settings Controller JavaScript -->
    <script>
        function switchSettingsTab(tab) {
            const tabs = ['specs', 'cpu', 'system', 'telegram', 'security', 'backup', 'power_mgmt'];
            tabs.forEach(t => {
                const btn = document.getElementById(`btnTab${t === 'power_mgmt' ? 'PowerMgmt' : (t.charAt(0).toUpperCase() + t.slice(1))}`);
                const sec = document.getElementById(`sectionTab${t === 'power_mgmt' ? 'PowerMgmt' : (t.charAt(0).toUpperCase() + t.slice(1))}`);
                if (btn) btn.classList.toggle('active', t === tab);
                if (sec) sec.style.display = (t === tab) ? 'grid' : 'none';
            });
        }

        function togglePasswordVisibility(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.type = (el.type === 'password') ? 'text' : 'password';
        }

        async function refreshSettingsState() {
            showToast('Memperbarui data pengaturan...', 'info');
            try {
                const res = await fetch('api.php?action=get_settings_state');
                const json = await res.json();
                if (json.success && json.data) {
                    const c = json.data.cpu;
                    if (c) {
                        document.getElementById('hudGovText').textContent = c.current_governor;
                        document.getElementById('textCurFreq').textContent = `${c.cur_freq_mhz} MHz`;
                        document.getElementById('selectCpuGov').value = c.current_governor;
                    }
                    showToast('Pengaturan berhasil dimuat ulang!', 'success');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function handleSaveGovernor(e) {
            e.preventDefault();
            const gov = document.getElementById('selectCpuGov')?.value;
            const btn = document.getElementById('btnSaveGov');
            if (!gov) return;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Menerapkan...</span>';
            }

            try {
                const res = await fetch('api.php?action=set_cpu_governor', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ governor: gov })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    document.getElementById('hudGovText').textContent = json.current_governor;
                } else {
                    showToast(json.error || 'Gagal mengubah governor', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg"></i><span>Terapkan Governor</span>';
                }
            }
        }

        async function handleSaveLed(led, trigger) {
            showToast(`Mengubah LED ${led}...`, 'info');
            try {
                const res = await fetch('api.php?action=set_led_trigger', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ led: led, trigger: trigger })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                } else {
                    showToast(json.error || 'Gagal mengubah trigger LED', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function handleSaveHostname(e) {
            e.preventDefault();
            const host = document.getElementById('inputHostname')?.value.trim();
            if (!host) return;

            try {
                const res = await fetch('api.php?action=set_hostname', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ hostname: host })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                } else {
                    showToast(json.error || 'Gagal mengatur hostname', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function handleSaveTimezone(e) {
            e.preventDefault();
            const tz = document.getElementById('selectTimezone')?.value;
            if (!tz) return;

            try {
                const res = await fetch('api.php?action=set_timezone', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ timezone: tz })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                } else {
                    showToast(json.error || 'Gagal mengatur zona waktu', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function handleChangePassword(e) {
            e.preventDefault();
            const p1 = document.getElementById('inputNewPass')?.value;
            const p2 = document.getElementById('inputConfirmPass')?.value;

            if (p1 !== p2) {
                showToast('Konfirmasi kata sandi tidak cocok!', 'error');
                return;
            }

            try {
                const res = await fetch('api.php?action=change_admin_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ new_password: p1 })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    document.getElementById('inputNewPass').value = '';
                    document.getElementById('inputConfirmPass').value = '';
                } else {
                    showToast(json.error || 'Gagal mengubah kata sandi', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function handleSaveTelegram(e) {
            if (e && e.preventDefault) e.preventDefault();
            const token = document.getElementById('inputTgToken')?.value || '';
            const chatId = document.getElementById('inputTgChatId')?.value || '';
            const nGuest = document.getElementById('checkTgGuest')?.checked || false;
            const nVoucher = document.getElementById('checkTgVoucher')?.checked || false;
            const nWatchdog = document.getElementById('checkTgWatchdog')?.checked || false;
            const nTemp = document.getElementById('checkTgTemp')?.checked || false;

            const btn = document.getElementById('btnSaveTg');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Menyimpan...</span>';
            }

            try {
                const res = await fetch('api.php?action=save_telegram_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: token,
                        chat_id: chatId,
                        notify_guest: nGuest,
                        notify_voucher: nVoucher,
                        notify_watchdog: nWatchdog,
                        notify_temp: nTemp
                    })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message || 'Pengaturan Bot Telegram berhasil disimpan!', 'success');
                } else {
                    showToast(json.error || 'Gagal menyimpan pengaturan', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-floppy-fill"></i><span>Simpan Pengaturan Bot</span>';
                }
            }
        }

        async function handleTestTelegramMessage() {
            const token = document.getElementById('inputTgToken')?.value || '';
            const chatId = document.getElementById('inputTgChatId')?.value || '';
            if (!token || !chatId) {
                showToast('Isi Bot Token dan Admin Chat ID terlebih dahulu', 'error');
                return;
            }

            showToast('Mengirim pesan uji coba ke Telegram...', 'info');
            try {
                const res = await fetch('api.php?action=send_test_telegram', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: token, chat_id: chatId })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                } else {
                    showToast(json.error || 'Gagal mengirim pesan', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function handleRestartTelegramDaemon() {
            showToast('Memuat ulang daemon bot Telegram...', 'info');
            try {
                const res = await fetch('api.php?action=control_service', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ service: 'ocanap-telegram-bot', service_action: 'restart' })
                });
                const json = await res.json();
                if (json.success) {
                    showToast('Daemon bot Telegram berhasil dimuat ulang!', 'success');
                } else {
                    showToast(json.error || 'Gagal memuat ulang daemon', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function downloadBackupFile() {
            showToast('Menyiapkan file cadangan...', 'info');
            try {
                const res = await fetch('api.php?action=export_backup');
                const json = await res.json();
                if (json.success && json.data) {
                    const blob = new Blob([JSON.stringify(json.data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `opi_backup_${new Date().toISOString().slice(0,10)}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                    showToast('File cadangan berhasil diunduh!', 'success');
                }
            } catch (e) {
                showToast('Gagal mengunduh file cadangan', 'error');
            }
        }

        async function executeSystemReboot() {
            if (!confirm('Apakah Anda yakin ingin memuat ulang (Reboot) sistem Orange Pi Zero 2 sekarang?')) return;
            showToast('Mengirim perintah Reboot...', 'info');
            try {
                await fetch('api.php?action=reboot_system', { method: 'POST' });
            } catch (e) {}
            alert('Perangkat sedang dimuat ulang. Halaman akan menyegarkan otomatis dalam 35 detik.');
            setTimeout(() => window.location.reload(), 35000);
        }

        async function executeSystemShutdown() {
            if (!confirm('Apakah Anda yakin ingin mematikan daya (Shutdown) sistem Orange Pi Zero 2? Perangkat harus dihidupkan ulang manual dengan menyambungkan kembali kabel daya.')) return;
            showToast('Mengirim perintah Shutdown...', 'info');
            try {
                await fetch('api.php?action=shutdown_system', { method: 'POST' });
            } catch (e) {}
            alert('Perangkat sedang dimatikan. Anda dapat mencabut adaptor daya setelah lampu LED mati.');
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
            }, 3000);
        }
    </script>
</body>
</html>


<script>
async function setScheduledReboot() {
    var h = document.getElementById('schedHour').value;
    var m = document.getElementById('schedMinute').value;
    var res = await fetch('api.php?action=set_scheduled_reboot', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?php echo Auth::csrfToken(); ?>&hour=' + h + '&minute=' + m
    });
    var data = await res.json();
    document.getElementById('schedStatus').textContent = data.message || data.error;
    document.getElementById('schedStatus').style.color = data.success ? 'var(--color-green)' : 'var(--color-danger)';
}

async function cancelScheduledReboot() {
    var res = await fetch('api.php?action=cancel_scheduled_reboot');
    var data = await res.json();
    document.getElementById('schedStatus').textContent = data.message || '';
}

// Load current schedule
fetch('api.php?action=get_scheduled_reboot').then(r=>r.json()).then(data=>{
    if(data.success && data.scheduled !== 'none') {
        document.getElementById('schedStatus').textContent = 'Current: ' + data.scheduled;
    }
});
</script>


<script>
async function sendWOL() {
    var mac = document.getElementById('wolMac').value.trim();
    if (!mac) {
        document.getElementById('wolStatus').textContent = 'Masukkan MAC address';
        return;
    }
    var res = await fetch('api.php?action=wake_on_lan', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?php echo Auth::csrfToken(); ?>&mac=' + mac
    });
    var data = await res.json();
    document.getElementById('wolStatus').textContent = data.message || data.error;
    document.getElementById('wolStatus').style.color = data.success ? 'var(--color-green)' : 'var(--color-danger)';
}
</script>
