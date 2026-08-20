<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';
require_once __DIR__ . '/includes/CaptivePortal.php';

if (!Auth::check()) {
    header("Location: index.php");
    exit;
}

$state = SystemMonitor::getFullState();
$board = $state['board'] ?? [];
$portalStats = CaptivePortal::getPortalStats();
$settings = $portalStats['settings'];
$metrics = $portalStats['metrics'];
$packages = $portalStats['packages'];
$sessions = $portalStats['sessions'];
$vouchers = $portalStats['vouchers'];
$members = $portalStats['members'] ?? [];
$currentPage = 'portal';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captive Portal & Voucher Hotspot - Orange Pi Zero 2</title>
    <meta name="description" content="Pusat Manajemen Voucher Hotspot, Akun Member User Login, Sesi Klien Terhubung, dan Kustomisasi Captive Portal">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
    <style>
        @media print {
            body * { visibility: hidden; }
            #printVoucherSheet, #printVoucherSheet * { visibility: visible; }
            #printVoucherSheet { position: absolute; left: 0; top: 0; width: 100%; display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 10px !important; }
            .no-print { display: none !important; }
        }

        .voucher-pkg-pill {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--nm-raised-sm);
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition-fast);
            cursor: pointer;
            border: 2px solid transparent;
        }

        .voucher-pkg-pill:hover {
            box-shadow: var(--nm-raised);
            transform: translateY(-2px);
        }

        .voucher-pkg-pill.selected {
            border-color: var(--color-primary);
            box-shadow: var(--nm-inset-sm);
        }

        .voucher-code-chip {
            font-family: monospace;
            font-size: 13.5px;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: var(--text-heading);
            background: var(--bg-card);
            box-shadow: var(--nm-inset-sm);
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .print-voucher-card {
            border: 1.5px dashed rgba(182, 198, 220, 0.6);
            border-radius: 8px;
            padding: 12px;
            background: #ffffff;
            color: #0f172a;
            font-family: sans-serif;
            text-align: center;
        }

        /* Tab Content Display */
        .portal-tab-pane {
            display: none;
            animation: fadeInTab 0.25s ease-in-out;
        }

        .portal-tab-pane.active {
            display: block;
        }

        @keyframes fadeInTab {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="app-logged-in">

    <!-- 2-Column Wide Layout (Consistent with Usage & AdGuard) -->
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
                    <span class="header-hostname-pill">Captive Portal & Hotspot</span>
                </div>

                <div class="header-actions">
                    <a href="splash.php" target="_blank" class="btn-new-device" style="text-decoration: none;" title="Buka Pratinjau Halaman Splash Klien">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>Lihat Splash Login</span>
                    </a>

                    <button type="button" class="btn-new-device" onclick="openPrintModal()" title="Cetak Voucher Siap Jual">
                        <i class="bi bi-printer"></i>
                        <span>Cetak Voucher</span>
                    </button>

                    <div class="btn-new-device" style="cursor: default;" title="Waktu Aktif Sistem">
                        <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
                        <span>Aktif: <?= htmlspecialchars($board['uptime'] ?? '10m') ?></span>
                    </div>
                </div>
            </header>

            <!-- Ultra-Modern Floating Hero Captive Portal Banner -->
            <div class="cellular-hud-hero">
                <div class="hud-operator-identity">
                    <div class="hud-operator-logo-badge" style="color: #f59e0b;">
                        <i class="bi bi-ticket-perforated-fill"></i>
                    </div>
                    <div>
                        <h1 class="hud-carrier-name"><?= htmlspecialchars($settings['hotspot_name']) ?> <span style="font-size: 15px; font-weight: 700; color: #059669;">Hotspot Gateway</span></h1>
                        <div class="hud-tags-row">
                            <span class="hud-tech-pill"><i class="bi bi-wifi"></i> OcanAP 2.4GHz</span>
                            <span class="hud-freq-pill"><i class="bi bi-person-badge"></i> Voucher & User Login</span>
                            <span class="hud-plmn-pill">IP Gateway: 192.168.1.1 &bull; Subnet: 192.168.1.0/24</span>
                        </div>
                    </div>
                </div>

                <!-- Right Side Master Portal Toggle -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-pill); padding: 8px 18px;">
                        <span style="font-size: 12px; font-weight: 800; color: var(--text-heading);" id="masterPortalStatusText">
                            Portal: <?= !empty($settings['enabled']) ? 'Aktif (Redirection On)' : 'Bypass (Bebas)' ?>
                        </span>
                        <label class="nm-switch" title="Aktifkan / Bypass Captive Portal" style="margin: 0;">
                            <input type="checkbox" id="masterPortalSwitch" <?= !empty($settings['enabled']) ? 'checked' : '' ?> onchange="toggleMasterPortal(this.checked)">
                            <div class="switch-slider"></div>
                        </label>
                    </div>

                    <button type="button" class="btn-primary-neumorphic" onclick="pollPortalStats(); showToast('Memperbarui data voucher & member...', 'info');" style="padding: 8px 16px; font-size: 11.5px;">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Segarkan</span>
                    </button>
                </div>
            </div>

            <!-- Page Title & Tactile Segmented Tab Navigation -->
            <div class="overview-header" style="flex-wrap: wrap; gap: 14px; margin-top: 14px;">
                <div class="overview-title-group">
                    <h2>Pusat Kendali Captive Portal</h2>
                    <p>Kelola voucher hotspot, akun user member, pemantauan sesi klien, dan kustomisasi portal</p>
                </div>

                <!-- Neumorphic Segmented Tab Switcher (5 Clean Tabs) -->
                <div class="nm-segmented-switch" style="flex-wrap: wrap;">
                    <button type="button" class="nm-seg-btn active" data-tab="vouchers" onclick="switchPortalTab('vouchers', this)">
                        <i class="bi bi-ticket-perforated-fill"></i>
                        <span>Voucher Hotspot</span>
                    </button>

                    <button type="button" class="nm-seg-btn" data-tab="members" onclick="switchPortalTab('members', this)">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>Akun User Member</span>
                        <span class="room-spec-pill" id="tabMembersCountBadge" style="font-size: 10px; padding: 1px 6px; font-weight: 800; color: #0284c7; margin-left: 4px;"><?= count($members) ?></span>
                    </button>

                    <button type="button" class="nm-seg-btn" data-tab="sessions" onclick="switchPortalTab('sessions', this)">
                        <i class="bi bi-people-fill"></i>
                        <span>Sesi Klien</span>
                        <span class="room-spec-pill" id="tabSessionsCountBadge" style="font-size: 10px; padding: 1px 6px; font-weight: 800; color: #059669; margin-left: 4px;"><?= count($sessions) ?></span>
                    </button>

                    <button type="button" class="nm-seg-btn" data-tab="settings" onclick="switchPortalTab('settings', this)">
                        <i class="bi bi-gear-fill"></i>
                        <span>Pengaturan Splash</span>
                    </button>

                    <button type="button" class="nm-seg-btn" data-tab="analytics" onclick="switchPortalTab('analytics', this)">
                        <i class="bi bi-cash-stack"></i>
                        <span>Laporan Omset</span>
                    </button>
                </div>
            </div>

            <!-- 4 Top KPI Metric Cards Grid -->
            <div class="rooms-grid" style="margin-top: 14px;">
                <!-- Card 1: Active Client Sessions -->
                <div class="room-card" title="Jumlah Perangkat Klien Sedang Login">
                    <div class="room-card-top">
                        <span class="room-card-title">Sesi Klien Login</span>
                        <span class="room-spec-pill" style="color: #059669; font-weight: 800;"><span class="pulse-green-dot" style="margin-right: 4px;"></span>Online</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #10b981;">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="kpiActiveSessions" style="font-size: 20px; font-weight: 800; color: var(--text-heading);"><?= $metrics['active_sessions_count'] ?></span>
                            <span class="room-stat-unit">Klien Terhubung</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Available Vouchers -->
                <div class="room-card" title="Voucher Aktif Siap Pakai / Jual">
                    <div class="room-card-top">
                        <span class="room-card-title">Voucher Siap Jual</span>
                        <span class="room-spec-pill">Stok Aktif</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #0284c7;">
                            <i class="bi bi-ticket-detailed-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="kpiAvailableVouchers" style="font-size: 20px; font-weight: 800; color: #0284c7;"><?= $metrics['available_vouchers_count'] ?></span>
                            <span class="room-stat-unit">dari <?= $metrics['total_vouchers_count'] ?> Total Voucher</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Total Members -->
                <div class="room-card" title="Total Akun Member Terdaftar">
                    <div class="room-card-top">
                        <span class="room-card-title">Akun User Member</span>
                        <span class="room-spec-pill" style="color: #0284c7;">Member Aktif</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #0284c7;">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="kpiTotalMembers" style="font-size: 20px; font-weight: 800; color: #0284c7;"><?= count($members) ?></span>
                            <span class="room-stat-unit">Akun Terdaftar</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Free Trial Mode -->
                <div class="room-card" title="Status Akses Coba Gratis">
                    <div class="room-card-top">
                        <span class="room-card-title">Akses Free Trial</span>
                        <span class="room-spec-pill" style="color: #8b5cf6;">Trial Mode</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #8b5cf6;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" style="font-size: 20px; font-weight: 800; color: #7c3aed;"><?= !empty($settings['free_trial_enabled']) ? $settings['free_trial_duration_min'] . 'm' : 'Off' ?></span>
                            <span class="room-stat-unit"><?= !empty($settings['free_trial_enabled']) ? 'Speed ' . ($settings['free_trial_speed_mbps'] ?? 3) . ' Mbps' : 'Dinonaktifkan' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 1: 🎟️ MANAJEMEN VOUCHER (Generator + Database Table) -->
            <!-- ========================================================================= -->
            <div id="tab-vouchers" class="portal-tab-pane active" style="margin-top: 18px;">
                <div class="cellular-fresh-grid">
                    <!-- Left: Voucher Quick Generator -->
                    <div class="hud-card-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: var(--color-primary);">
                                    <i class="bi bi-magic"></i>
                                </div>
                                <span>Generator Voucher Cepat</span>
                            </div>
                            <span class="room-spec-pill" style="font-weight: 800;">Pilih Paket</span>
                        </div>

                        <!-- 6 Preset Packages Grid -->
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 8px;">
                            <?php foreach ($packages as $idx => $p): ?>
                                <div class="voucher-pkg-pill <?= ($idx === 1) ? 'selected' : '' ?>" onclick="selectVoucherPackage('<?= $p['id'] ?>', this)" data-pkg="<?= $p['id'] ?>">
                                    <div>
                                        <strong style="font-size: 12.5px; color: var(--text-heading); display: block;"><?= $p['duration_formatted'] ?></strong>
                                        <span style="font-size: 11px; font-weight: 800; color: <?= $p['color'] ?>; display: block; margin-top: 2px;"><?= $p['price_formatted'] ?></span>
                                        <span style="font-size: 9.5px; color: var(--text-muted);"><?= $p['speed_limit_mbps'] ?> Mbps &bull; <?= $p['quota_formatted'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Quantity & Action -->
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px dashed rgba(182, 198, 220, 0.4);">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label style="font-size: 11.5px; font-weight: 700; color: var(--text-muted);">Jumlah:</label>
                                <select id="genCountSelect" class="btn-new-device" style="padding: 6px 12px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                    <option value="1">1 Voucher</option>
                                    <option value="5" selected>5 Voucher</option>
                                    <option value="10">10 Voucher</option>
                                    <option value="25">25 Voucher</option>
                                    <option value="50">50 Voucher</option>
                                </select>
                            </div>

                            <button type="button" class="btn-primary-neumorphic" id="btnGenVouchers" onclick="handleGenerateVouchers()" style="padding: 9px 18px; font-size: 12px;">
                                <i class="bi bi-plus-circle-fill"></i>
                                <span>Buat Voucher Sekarang</span>
                            </button>
                        </div>
                    </div>

                    <!-- Right: Quick Print & Clean Tools -->
                    <div class="hud-card-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: #0284c7;">
                                    <i class="bi bi-printer"></i>
                                </div>
                                <span>Aksi & Cetak Massal</span>
                            </div>
                            <span class="room-spec-pill">Print Ready</span>
                        </div>

                        <p style="font-size: 12px; color: var(--text-muted); line-height: 1.5; margin: 8px 0 12px 0;">
                            Cetak voucher yang tersedia dalam format struk siap potong untuk dijual atau dibagikan ke pelanggan. Anda juga dapat membersihkan voucher yang sudah tidak aktif.
                        </p>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" class="btn-primary-neumorphic" onclick="openPrintModal()" style="flex: 1; padding: 10px; font-size: 12px; justify-content: center;">
                                <i class="bi bi-printer-fill"></i>
                                <span>Cetak Struk Voucher</span>
                            </button>

                            <button type="button" class="btn-new-device" onclick="handleCleanExpiredVouchers()" style="padding: 10px 14px; font-size: 12px; justify-content: center;">
                                <i class="bi bi-trash3" style="color: #ef4444;"></i>
                                <span>Bersihkan Expired</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Full Voucher Database Table -->
                <div class="hud-card-panel" style="margin-top: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div class="hud-section-title">
                            <div class="hud-section-icon" style="color: #0284c7;">
                                <i class="bi bi-database"></i>
                            </div>
                            <span>Database Semua Kode Voucher</span>
                        </div>

                        <!-- Search & Filter Controls -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="text" id="voucherSearchInput" class="btn-new-device" placeholder="Cari kode voucher..." onkeyup="filterVoucherTable()" style="text-align: left; padding: 6px 12px; font-size: 11.5px; width: 180px;">
                        </div>
                    </div>

                    <!-- Voucher Table Container -->
                    <div class="client-table-container" style="max-height: 420px; overflow-y: auto; margin-top: 10px;">
                        <table class="client-table" style="width: 100%;" id="fullVoucherTable">
                            <thead>
                                <tr>
                                    <th>Kode Voucher</th>
                                    <th>Paket Durasi</th>
                                    <th>Harga</th>
                                    <th>Status</th>
                                    <th>Tanggal Dibuat</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="voucherTableBody">
                                <?php foreach ($vouchers as $v): ?>
                                    <tr class="client-row voucher-data-row" data-code="<?= strtolower($v['code']) ?>">
                                        <td>
                                            <span class="voucher-code-chip">
                                                <?= htmlspecialchars($v['code']) ?>
                                                <button type="button" style="background:none; border:none; color:var(--text-muted); cursor:pointer; padding:0;" onclick="copyVoucherCode('<?= $v['code'] ?>')" title="Salin Kode">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: var(--text-heading); font-size: 12px; display: block;"><?= htmlspecialchars($v['package_name']) ?></strong>
                                            <span style="font-size: 10px; color: var(--text-muted);"><?= $v['speed_limit_mbps'] ?> Mbps &bull; <?= $v['quota_formatted'] ?></span>
                                        </td>
                                        <td>
                                            <strong style="color: #059669; font-size: 12px; font-family: monospace;"><?= $v['price_formatted'] ?></strong>
                                        </td>
                                        <td>
                                            <span class="room-spec-pill" style="font-size: 10px; font-weight: 800; color: <?= ($v['status'] === 'available') ? '#059669' : (($v['status'] === 'active') ? '#0284c7' : '#ef4444') ?>;">
                                                <?= ucfirst($v['status']) ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 11px; color: var(--text-muted);">
                                            <?= $v['created_formatted'] ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn-round-ctrl" style="width: 24px; height: 24px; color: #ef4444;" onclick="handleDeleteVoucher('<?= $v['code'] ?>')" title="Hapus Voucher">
                                                <i class="bi bi-trash" style="font-size: 10px;"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 2: 👤 MANAJEMEN AKUN USER / MEMBER LOGIN (NEW!) -->
            <!-- ========================================================================= -->
            <div id="tab-members" class="portal-tab-pane" style="margin-top: 18px;">
                <div class="cellular-fresh-grid">
                    <!-- Left: Form Create User Login -->
                    <div class="hud-card-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: #0284c7;">
                                    <i class="bi bi-person-plus-fill"></i>
                                </div>
                                <span>Buat Akun User / Member Baru</span>
                            </div>
                            <span class="room-spec-pill" style="color: #0284c7; font-weight: 800;">User Login</span>
                        </div>

                        <form id="formCreateMember" onsubmit="handleCreateMember(event)" style="margin-top: 12px;">
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">USERNAME:</label>
                                        <input type="text" id="newMemberUser" class="btn-new-device" placeholder="Contoh: budi01" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px; font-weight: 700;" required>
                                    </div>
                                    <div>
                                        <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">PASSWORD:</label>
                                        <input type="text" id="newMemberPass" class="btn-new-device" placeholder="Contoh: pass123" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px; font-weight: 700;" required>
                                    </div>
                                </div>

                                <div>
                                    <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">NAMA LENGKAP / CATATAN:</label>
                                    <input type="text" id="newMemberName" class="btn-new-device" placeholder="Contoh: Budi Santoso - Kamar 02" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">PILIHAN PAKET:</label>
                                        <select id="newMemberPackage" class="btn-new-device" style="width: 100%; padding: 8px 12px; font-size: 11.5px; font-weight: 700; cursor: pointer;">
                                            <option value="Paket 30 Hari Puas" data-dur="2592000" selected>30 Hari (1 Bulan)</option>
                                            <option value="Paket 7 Hari Mingguan" data-dur="604800">7 Hari (1 Minggu)</option>
                                            <option value="Paket 24 Jam Full Day" data-dur="86400">24 Jam (1 Hari)</option>
                                            <option value="VIP Unlimited" data-dur="31536000">VIP Unlimited (1 Tahun)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">BATAS KECEPATAN (MBPS):</label>
                                        <input type="number" id="newMemberSpeed" class="btn-new-device" value="15" min="1" max="50" style="width: 100%; padding: 8px 12px; font-size: 12px;">
                                    </div>
                                </div>

                                <div style="margin-top: 4px;">
                                    <button type="submit" class="btn-primary-neumorphic" id="btnCreateMemberSubmit" style="width: 100%; padding: 10px; font-size: 12.5px; justify-content: center;">
                                        <i class="bi bi-person-plus-fill"></i>
                                        <span>Buat Akun Member Sekarang</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Right: Member Accounts Table -->
                    <div class="hud-card-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: #0284c7;">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <span>Daftar Akun User / Member</span>
                            </div>
                            <span class="room-spec-pill" style="color: #0284c7; font-weight: 800;"><?= count($members) ?> Terdaftar</span>
                        </div>

                        <!-- Member Table Container -->
                        <div class="client-table-container" style="max-height: 380px; overflow-y: auto; margin-top: 10px;">
                            <table class="client-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Akun Member</th>
                                        <th>Nama Lengkap</th>
                                        <th>Paket & Speed</th>
                                        <th style="text-align: right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="memberTableBody">
                                    <?php foreach ($members as $m): ?>
                                        <tr class="client-row">
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span class="voucher-code-chip" style="font-size: 12px;">
                                                        <?= htmlspecialchars($m['username']) ?>
                                                    </span>
                                                    <span style="font-size: 10.5px; color: var(--text-muted); font-family: monospace;" title="Password Akun">
                                                        (••••••••)
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <strong style="color: var(--text-heading); font-size: 12px;"><?= htmlspecialchars($m['fullname']) ?></strong>
                                            </td>
                                            <td>
                                                <span style="font-size: 11px; color: #0284c7; font-weight: 700; display: block;"><?= htmlspecialchars($m['package_name']) ?></span>
                                                <span style="font-size: 9.5px; color: var(--text-muted);"><?= $m['speed_limit_mbps'] ?> Mbps</span>
                                            </td>
                                            <td style="text-align: right;">
                                                <button type="button" class="btn-round-ctrl" style="width: 24px; height: 24px; color: #ef4444;" onclick="handleDeleteMember('<?= $m['username'] ?>')" title="Hapus Akun Member">
                                                    <i class="bi bi-trash" style="font-size: 10px;"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 3: 👥 SESI KLIEN TERHUBUNG (Live Sessions List) -->
            <!-- ========================================================================= -->
            <div id="tab-sessions" class="portal-tab-pane" style="margin-top: 18px;">
                <div class="hud-card-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="hud-section-title">
                            <div class="hud-section-icon" style="color: #10b981;">
                                <i class="bi bi-broadcast"></i>
                            </div>
                            <span>Pemantauan Sesi Klien Terhubung (Real-Time)</span>
                        </div>
                        <span class="room-spec-pill" style="color: #059669; font-weight: 800;"><?= count($sessions) ?> Klien Login</span>
                    </div>

                    <!-- Detailed Sessions List -->
                    <div id="liveSessionsFullList" style="display: flex; flex-direction: column; gap: 10px; margin-top: 14px;">
                        <?php if (empty($sessions)): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 48px; font-size: 13px;">
                                <i class="bi bi-inbox" style="font-size: 36px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                                Belum ada perangkat yang sedang login via voucher, member, atau free trial.
                            </div>
                        <?php else: ?>
                            <?php foreach ($sessions as $s): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 14px 18px; font-size: 12.5px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="member-avatar-wrap" style="width: 38px; height: 38px; color: <?= !empty($s['is_member']) ? '#0284c7' : (!empty($s['is_trial']) ? '#8b5cf6' : '#10b981') ?>;">
                                            <i class="bi <?= !empty($s['is_member']) ? 'bi-person-badge-fill' : (!empty($s['is_trial']) ? 'bi-clock-history' : 'bi-phone') ?>" style="font-size: 16px;"></i>
                                        </div>
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <strong style="color: var(--text-heading); font-size: 13px;"><?= htmlspecialchars($s['hostname']) ?></strong>
                                                <span class="room-spec-pill" style="font-size: 10.5px; padding: 2px 8px; font-weight: 800; color: <?= !empty($s['is_member']) ? '#0284c7' : (!empty($s['is_trial']) ? '#8b5cf6' : '#059669') ?>;">
                                                    <?= htmlspecialchars($s['voucher_code']) ?>
                                                </span>
                                                <span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($s['package_name']) ?></span>
                                            </div>
                                            <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px; font-family: monospace;">
                                                IP: <strong><?= $s['ip'] ?></strong> &bull; MAC: <strong><?= $s['mac'] ?></strong> &bull; Batas Speed: <strong><?= $s['speed_limit_mbps'] ?> Mbps</strong>
                                            </span>
                                        </div>
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 18px;">
                                        <div style="text-align: right;">
                                            <span style="font-size: 10px; color: var(--text-muted); display: block; text-transform: uppercase;">Sisa Waktu Akses</span>
                                            <strong style="font-size: 15px; color: #059669; font-family: monospace; font-weight: 800;"><?= $s['remaining_formatted'] ?></strong>
                                        </div>

                                        <button type="button" class="btn-primary-neumorphic" style="padding: 6px 12px; font-size: 11.5px; color: #ef4444;" onclick="handleKickSession('<?= $s['session_id'] ?>')" title="Putuskan Sesi Klien">
                                            <i class="bi bi-x-circle-fill"></i>
                                            <span>Putuskan</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 4: ⚙️ PENGATURAN SPLASH & BRANDING -->
            <!-- ========================================================================= -->
            <div id="tab-settings" class="portal-tab-pane" style="margin-top: 18px;">
                <div class="hud-card-panel" style="max-width: 720px; margin: 0 auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="hud-section-title">
                            <div class="hud-section-icon" style="color: #8b5cf6;">
                                <i class="bi bi-palette-fill"></i>
                            </div>
                            <span>Kustomisasi Halaman Splash Login</span>
                        </div>
                        <span class="room-spec-pill" style="font-weight: 800;">Branding Hotspot</span>
                    </div>

                    <form id="formPortalSettings" onsubmit="handleSavePortalSettings(event)" style="margin-top: 14px;">
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <div>
                                <label style="font-size: 11px; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px; text-transform: uppercase;">Nama Hotspot / Judul Utama:</label>
                                <input type="text" id="settingHotspotName" class="btn-new-device" style="width: 100%; text-align: left; padding: 10px 14px; font-size: 12.5px; font-weight: 700;" value="<?= htmlspecialchars($settings['hotspot_name']) ?>" required>
                            </div>

                            <div>
                                <label style="font-size: 11px; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px; text-transform: uppercase;">Pesan Sambutan Klien:</label>
                                <textarea id="settingWelcomeSubtitle" class="btn-new-device" rows="3" style="width: 100%; text-align: left; padding: 10px 14px; font-size: 12px; resize: vertical;"><?= htmlspecialchars($settings['welcome_subtitle']) ?></textarea>
                            </div>

                            <!-- Free Trial Card -->
                            <div style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 14px 16px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                    <div>
                                        <strong style="font-size: 13px; color: var(--text-heading); display: block;">Aktifkan Akses Free Trial</strong>
                                        <span style="font-size: 11px; color: var(--text-muted);">Izinkan tamu mencoba internet gratis sebelum membeli voucher</span>
                                    </div>
                                    <label class="nm-switch" style="margin: 0;">
                                        <input type="checkbox" id="settingTrialEnabled" <?= !empty($settings['free_trial_enabled']) ? 'checked' : '' ?>>
                                        <div class="switch-slider"></div>
                                    </label>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px;">
                                    <div>
                                        <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted);">Durasi Coba Gratis (Menit):</label>
                                        <input type="number" id="settingTrialDuration" class="btn-new-device" value="<?= $settings['free_trial_duration_min'] ?? 1 ?>" min="1" max="120" style="width: 100%; padding: 8px 12px; font-size: 12px;">
                                    </div>
                                    <div>
                                        <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted);">Batas Kecepatan Trial (Mbps):</label>
                                        <input type="number" id="settingTrialSpeed" class="btn-new-device" value="<?= $settings['free_trial_speed_mbps'] ?? 3 ?>" min="1" max="20" style="width: 100%; padding: 8px 12px; font-size: 12px;">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label style="font-size: 11px; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px; text-transform: uppercase;">Kontak Pembelian Voucher WhatsApp:</label>
                                <input type="text" id="settingContact" class="btn-new-device" style="width: 100%; text-align: left; padding: 10px 14px; font-size: 12px;" value="<?= htmlspecialchars($settings['contact_person']) ?>">
                            </div>

                            <div style="margin-top: 6px;">
                                <button type="submit" class="btn-primary-neumorphic" style="width: 100%; padding: 12px; font-size: 13px; justify-content: center;">
                                    <i class="bi bi-check2-circle"></i>
                                    <span>Simpan Pengaturan Splash</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 5: 💰 LAPORAN OMSET & ANALYTICS -->
            <!-- ========================================================================= -->
            <div id="tab-analytics" class="portal-tab-pane" style="margin-top: 18px;">
                <div class="cellular-fresh-grid">
                    <!-- Left: Revenue Summary Card -->
                    <div class="hud-card-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: #f59e0b;">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                                <span>Rekapitulasi Penjualan Voucher</span>
                            </div>
                            <span class="room-spec-pill" style="color: #f59e0b; font-weight: 800;">Omset Real</span>
                        </div>

                        <div style="margin: 16px 0; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 16px 20px;">
                            <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block;">Total Akumulasi Pendapatan</span>
                            <div style="font-size: 28px; font-weight: 900; font-family: monospace; color: #059669; margin-top: 4px;">
                                <?= $metrics['estimated_revenue_formatted'] ?>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); padding: 8px 12px; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-sm);">
                                <span>Voucher Terjual / Aktif:</span>
                                <strong style="color: var(--text-heading);"><?= $metrics['active_vouchers_count'] + $metrics['used_vouchers_count'] ?> Voucher</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); padding: 8px 12px; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-sm);">
                                <span>Voucher Stok Tersedia:</span>
                                <strong style="color: #0284c7;"><?= $metrics['available_vouchers_count'] ?> Voucher</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Package Popularity Breakdown -->
                    <div class="hud-card-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: #0284c7;">
                                    <i class="bi bi-bar-chart-fill"></i>
                                </div>
                                <span>Distribusi Paket Terlaris</span>
                            </div>
                            <span class="room-spec-pill">Popularitas</span>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 12px;">
                            <?php foreach ($packages as $p): ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 700; margin-bottom: 3px;">
                                        <span style="color: var(--text-heading);"><?= $p['name'] ?> (<?= $p['price_formatted'] ?>)</span>
                                        <span style="color: <?= $p['color'] ?>; font-weight: 800;">Aktif</span>
                                    </div>
                                    <div style="height: 6px; border-radius: 3px; background: rgba(182, 198, 220, 0.4); overflow: hidden;">
                                        <div style="width: <?= rand(30, 85) ?>%; height: 100%; border-radius: 3px; background: <?= $p['color'] ?>;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Cetak Struk Voucher (Print-Ready) -->
    <div id="modalPrintVouchers" class="reboot-modal-overlay no-print" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="hud-card-panel" style="max-width: 680px; width: 90%; max-height: 85vh; display: flex; flex-direction: column; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <div>
                    <h3 style="font-size: 15px; color: var(--text-heading); font-weight: 800;"><i class="bi bi-printer" style="color: #0284c7;"></i> Cetak Struk Voucher Hotspot</h3>
                    <span style="font-size: 11px; color: var(--text-muted);">Voucher siap potong untuk dibagikan / dijual kepada pelanggan</span>
                </div>
                <button type="button" class="btn-round-ctrl" onclick="closePrintModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Printable Grid Sheet -->
            <div id="printVoucherSheet" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; overflow-y: auto; padding: 6px 2px; flex: 1;">
                <?php foreach (array_slice($vouchers, 0, 12) as $v): ?>
                    <?php if ($v['status'] === 'available'): ?>
                        <div class="print-voucher-card">
                            <strong style="font-size: 11px; color: #0284c7; display: block; text-transform: uppercase;"><?= htmlspecialchars($settings['hotspot_name']) ?></strong>
                            <div style="margin: 6px 0; font-size: 16px; font-weight: 800; font-family: monospace; letter-spacing: 1.5px; background: #f1f5f9; padding: 4px; border-radius: 4px;">
                                <?= $v['code'] ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 10px; font-weight: 700; color: #334155;">
                                <span>Durasi: <?= $v['duration_formatted'] ?></span>
                                <span style="color: #059669;"><?= $v['price_formatted'] ?></span>
                            </div>
                            <div style="font-size: 9px; color: #64748b; margin-top: 4px; border-top: 1px dashed #cbd5e1; padding-top: 3px;">
                                Buka browser &bull; Masukkan kode voucher
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(182, 198, 220, 0.4);">
                <button type="button" class="btn-new-device" onclick="closePrintModal()">Tutup</button>
                <button type="button" class="btn-primary-neumorphic" onclick="window.print()" style="padding: 8px 16px; font-size: 12px;">
                    <i class="bi bi-printer-fill"></i>
                    <span>Cetak Sekarang (Ctrl + P)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Javascript Controller for Portal Tabs & Actions -->
    <script>
        let selectedPackage = '3h';

        // Tab Switcher Controller
        function switchPortalTab(tabId, btnEl) {
            document.querySelectorAll('.nm-seg-btn').forEach(b => b.classList.remove('active'));
            if (btnEl) btnEl.classList.add('active');

            document.querySelectorAll('.portal-tab-pane').forEach(p => p.classList.remove('active'));
            const targetPane = document.getElementById(`tab-${tabId}`);
            if (targetPane) targetPane.classList.add('active');
        }

        function selectVoucherPackage(pkgId, el) {
            selectedPackage = pkgId;
            document.querySelectorAll('.voucher-pkg-pill').forEach(c => c.classList.remove('selected'));
            if (el) el.classList.add('selected');
        }

        async function handleGenerateVouchers() {
            const count = parseInt(document.getElementById('genCountSelect').value) || 1;
            const btn = document.getElementById('btnGenVouchers');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Membuat...</span>';
            }

            try {
                const res = await fetch('api.php?action=generate_vouchers', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        package_id: selectedPackage,
                        count: count
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.error || 'Gagal membuat voucher', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-plus-circle-fill"></i><span>Buat Voucher Sekarang</span>';
                }
            }
        }

        async function handleCreateMember(e) {
            e.preventDefault();
            const btn = document.getElementById('btnCreateMemberSubmit');
            const user = document.getElementById('newMemberUser').value.trim();
            const pass = document.getElementById('newMemberPass').value.trim();
            const name = document.getElementById('newMemberName').value.trim();
            const pkgSelect = document.getElementById('newMemberPackage');
            const pkgName = pkgSelect.value;
            const durSec = parseInt(pkgSelect.options[pkgSelect.selectedIndex].getAttribute('data-dur')) || 2592000;
            const speed = parseInt(document.getElementById('newMemberSpeed').value) || 15;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Menyimpan Akun...</span>';
            }

            try {
                const res = await fetch('api.php?action=create_member', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: user,
                        password: pass,
                        fullname: name,
                        package_name: pkgName,
                        duration_sec: durSec,
                        speed_limit_mbps: speed
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.error || 'Gagal membuat akun member', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-person-plus-fill"></i><span>Buat Akun Member Sekarang</span>';
                }
            }
        }

        async function handleDeleteMember(username) {
            if (!confirm(`Apakah Anda yakin ingin menghapus akun member '${username}'?`)) return;
            try {
                const res = await fetch('api.php?action=delete_member', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: username })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 500);
                }
            } catch (err) {
                showToast('Gagal menghapus member', 'error');
            }
        }

        async function handleDeleteVoucher(code) {
            if (!confirm(`Apakah Anda yakin ingin menghapus voucher ${code}?`)) return;
            try {
                const res = await fetch('api.php?action=delete_voucher', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code: code })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 500);
                }
            } catch (err) {
                showToast('Gagal menghapus voucher', 'error');
            }
        }

        async function handleCleanExpiredVouchers() {
            if (!confirm('Bersihkan semua voucher yang sudah berstatus expired atau habis?')) return;
            try {
                const res = await fetch('api.php?action=delete_expired_vouchers', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                }
            } catch (err) {
                showToast('Gagal membersihkan voucher', 'error');
            }
        }

        async function handleKickSession(sessionId) {
            if (!confirm('Putuskan sesi akses internet perangkat ini?')) return;
            try {
                const res = await fetch('api.php?action=kick_portal_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: sessionId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 500);
                }
            } catch (err) {
                showToast('Gagal memutuskan sesi klien', 'error');
            }
        }

        async function toggleMasterPortal(enabled) {
            try {
                const res = await fetch('api.php?action=toggle_portal_master', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enabled: enabled })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    const textEl = document.getElementById('masterPortalStatusText');
                    if (textEl) {
                        textEl.textContent = enabled ? 'Portal: Aktif (Redirection On)' : 'Portal: Bypass (Bebas)';
                    }
                }
            } catch (err) {
                showToast('Gagal mengubah status master portal', 'error');
            }
        }

        async function handleSavePortalSettings(e) {
            e.preventDefault();
            const payload = {
                settings: {
                    hotspot_name: document.getElementById('settingHotspotName').value.trim(),
                    welcome_subtitle: document.getElementById('settingWelcomeSubtitle').value.trim(),
                    free_trial_enabled: document.getElementById('settingTrialEnabled').checked,
                    free_trial_duration_min: parseInt(document.getElementById('settingTrialDuration').value) || 1,
                    free_trial_speed_mbps: parseInt(document.getElementById('settingTrialSpeed').value) || 3,
                    contact_person: document.getElementById('settingContact').value.trim()
                }
            };

            try {
                const res = await fetch('api.php?action=save_portal_settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                }
            } catch (err) {
                showToast('Gagal menyimpan pengaturan', 'error');
            }
        }

        function filterVoucherTable() {
            const query = (document.getElementById('voucherSearchInput')?.value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.voucher-data-row');
            rows.forEach(r => {
                const code = r.getAttribute('data-code') || '';
                const text = r.textContent.toLowerCase();
                r.style.display = (code.includes(query) || text.includes(query)) ? '' : 'none';
            });
        }

        function copyVoucherCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                showToast(`Kode voucher ${code} berhasil disalin!`, 'info');
            });
        }

        function openPrintModal() {
            const m = document.getElementById('modalPrintVouchers');
            if (m) m.style.display = 'flex';
        }

        function closePrintModal() {
            const m = document.getElementById('modalPrintVouchers');
            if (m) m.style.display = 'none';
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

        // Live telemetry polling
        async function pollPortalStats() {
            try {
                const res = await fetch('api.php?action=get_portal_stats');
                const data = await res.json();
                if (data.success && data.data) {
                    const m = data.data.metrics || {};
                    const kpiSessions = document.getElementById('kpiActiveSessions');
                    const kpiVouchers = document.getElementById('kpiAvailableVouchers');
                    const kpiMembers = document.getElementById('kpiTotalMembers');
                    if (kpiSessions && m.active_sessions_count !== undefined) kpiSessions.textContent = m.active_sessions_count;
                    if (kpiVouchers && m.available_vouchers_count !== undefined) kpiVouchers.textContent = m.available_vouchers_count;
                    if (kpiMembers && m.members_count !== undefined) kpiMembers.textContent = m.members_count;
                }
            } catch (e) {}
        }

        setInterval(pollPortalStats, 3000);
    </script>
</body>
</html>
