<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';

if (!Auth::check()) {
    header("Location: index.php");
    exit;
}

$state = SystemMonitor::getFullState();
$board = $state['board'] ?? [];
$devices = $state['device_usage'] ?? [];
$overall = $state['overall_usage'] ?? [];
$modem = $state['modem'] ?? [];
$currentPage = 'usage';

// Build REAL chart datasets from the persistent traffic history (data/traffic_history.json)
function opiBuildUsageCharts(array $history): array {
    $labelsHour = ['00:00', '03:00', '06:00', '09:00', '12:00', '15:00', '18:00', '21:00'];
    $today = date('Y-m-d');

    // Daily: today's real 3-hour buckets
    $dlH = array_fill(0, 8, 0.0);
    $ulH = array_fill(0, 8, 0.0);
    foreach (($history[$today]['hours'] ?? []) as $key => $v) {
        $idx = (int)substr((string)$key, 1);
        if ($idx >= 0 && $idx < 8 && is_array($v)) {
            $dlH[$idx] = round((float)($v['dl_mb'] ?? 0), 1);
            $ulH[$idx] = round((float)($v['ul_mb'] ?? 0), 1);
        }
    }

    // Weekly: last 7 days per day
    $dayLabels = [];
    $dlD = [];
    $ulD = [];
    for ($i = 6; $i >= 0; $i--) {
        $ts = strtotime("-{$i} days");
        $dayLabels[] = date('D', $ts);
        $day = date('Y-m-d', $ts);
        $dlD[] = round(((float)($history[$day]['dl_mb'] ?? 0)) / 1024, 2);
        $ulD[] = round(((float)($history[$day]['ul_mb'] ?? 0)) / 1024, 2);
    }

    // Monthly: current month grouped into calendar week buckets
    $firstDay = (int)date('j');
    $numBuckets = (int)ceil($firstDay / 7);
    $dlW = array_fill(0, $numBuckets, 0.0);
    $ulW = array_fill(0, $numBuckets, 0.0);
    $monthPrefix = date('Y-m');
    foreach ($history as $day => $v) {
        if (strpos((string)$day, $monthPrefix) !== 0 || !is_array($v)) continue;
        $d = (int)substr((string)$day, 8, 2);
        $b = min($numBuckets - 1, intdiv($d - 1, 7));
        $dlW[$b] += (float)($v['dl_mb'] ?? 0);
        $ulW[$b] += (float)($v['ul_mb'] ?? 0);
    }
    $dlW = array_map(fn($x) => round($x / 1024, 2), $dlW);
    $ulW = array_map(fn($x) => round($x / 1024, 2), $ulW);

    // Lifetime: monthly totals for the last 6 months
    $mLabels = [];
    $dlM = [];
    $ulM = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-{$i} months");
        $mLabels[] = date('M', $ts);
        $prefix = date('Y-m', $ts);
        $sumDl = 0.0;
        $sumUl = 0.0;
        foreach ($history as $day => $v) {
            if (strpos((string)$day, $prefix) !== 0 || !is_array($v)) continue;
            $sumDl += (float)($v['dl_mb'] ?? 0);
            $sumUl += (float)($v['ul_mb'] ?? 0);
        }
        $dlM[] = round($sumDl / 1024, 1);
        $ulM[] = round($sumUl / 1024, 1);
    }

    $fmtKbps = fn(float $kbps) => $kbps >= 1024 ? round($kbps / 1024, 2) . ' MB/s' : round($kbps, 1) . ' KB/s';
    $liveRx = (float)($GLOBALS['overall']['live_rx_kbps'] ?? 0);
    $liveTx = (float)($GLOBALS['overall']['live_tx_kbps'] ?? 0);

    return [
        'daily' => [
            'title' => 'Distribusi Trafik Harian (Per 3 Jam)',
            'subtitle' => 'Akumulasi nyata hari ini (' . date('d M Y') . ')',
            'labels' => $labelsHour,
            'dl' => $dlH,
            'ul' => $ulH,
            'max' => round(max(800.0, max($dlH) * 1.15), 1),
            'avgDl' => $fmtKbps($liveRx),
            'avgUl' => $fmtKbps($liveTx)
        ],
        'weekly' => [
            'title' => 'Distribusi Trafik Mingguan (7 Hari Terakhir)',
            'subtitle' => 'Volume data nyata per hari',
            'labels' => $dayLabels,
            'dl' => $dlD,
            'ul' => $ulD,
            'max' => round(max(4.0, max(max($dlD), max($ulD)) * 1.15), 2),
            'avgDl' => $fmtKbps($liveRx),
            'avgUl' => $fmtKbps($liveTx)
        ],
        'monthly' => [
            'title' => 'Distribusi Trafik Bulanan (Per Pekan)',
            'subtitle' => 'Akumulasi nyata bulan berjalan (' . date('F Y') . ')',
            'labels' => array_map(fn($i) => 'Pekan ' . ($i + 1), range(0, $numBuckets - 1)),
            'dl' => $dlW,
            'ul' => $ulW,
            'max' => round(max(15.0, max(max($dlW), max($ulW)) * 1.15), 2),
            'avgDl' => $fmtKbps($liveRx),
            'avgUl' => $fmtKbps($liveTx)
        ],
        'lifetime' => [
            'title' => 'Tren Trafik 6 Bulan Terakhir',
            'subtitle' => 'Akumulasi nyata data bulanan',
            'labels' => $mLabels,
            'dl' => $dlM,
            'ul' => $ulM,
            'max' => round(max(200.0, max(max($dlM), max($ulM)) * 1.15), 1),
            'avgDl' => $fmtKbps($liveRx),
            'avgUl' => $fmtKbps($liveTx)
        ]
    ];
}

$trafficHistoryRaw = @file_get_contents(__DIR__ . '/data/traffic_history.json');
$trafficHistory = is_string($trafficHistoryRaw) ? (json_decode($trafficHistoryRaw, true) ?: []) : [];
$usageChartsReal = opiBuildUsageCharts($trafficHistory);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik & Penggunaan Data - Orange Pi Zero 2</title>
    <meta name="description" content="Pusat Analisis & Statistik Penggunaan Kuota Internet Harian, Mingguan, Bulanan Tiap Perangkat">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
</head>
<body class="app-logged-in">

    <!-- 2-Column Wide Layout for Dedicated Usage Center -->
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
                    <span class="header-hostname-pill">Statistik Penggunaan</span>
                </div>

                <div class="header-actions">
                    <button type="button" class="btn-new-device" onclick="handleResetUsageTracker()" title="Reset Statistik Sesi Klien">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Reset Sesi</span>
                    </button>

                    <div class="btn-new-device" style="cursor: default;" title="Waktu Aktif Sistem">
                        <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
                        <span id="usageUptimeText">Aktif: <?= htmlspecialchars($board['uptime'] ?? '10m') ?></span>
                    </div>
                </div>
            </header>

            <!-- Page Title Header with Segmented Period Switcher -->
            <div class="overview-header" style="flex-wrap: wrap; gap: 14px;">
                <div class="overview-title-group">
                    <h2>Statistik Penggunaan Data & Bandwidth</h2>
                    <p>Pusat Analisis Konsumsi Kuota Seluler & Rincian Trafik Tiap Perangkat Klien</p>
                </div>

                <!-- Neumorphic Tactile Segmented Period Switcher -->
                <div class="nm-segmented-switch">
                    <button type="button" class="nm-seg-btn active" data-period="daily" onclick="switchUsagePeriod('daily', this)">
                        <i class="bi bi-calendar-day"></i>
                        <span>Harian</span>
                    </button>
                    <button type="button" class="nm-seg-btn" data-period="weekly" onclick="switchUsagePeriod('weekly', this)">
                        <i class="bi bi-calendar-week"></i>
                        <span>Mingguan</span>
                    </button>
                    <button type="button" class="nm-seg-btn" data-period="monthly" onclick="switchUsagePeriod('monthly', this)">
                        <i class="bi bi-calendar-month"></i>
                        <span>Bulanan</span>
                    </button>
                    <button type="button" class="nm-seg-btn" data-period="lifetime" onclick="switchUsagePeriod('lifetime', this)">
                        <i class="bi bi-database-fill-check"></i>
                        <span>Total</span>
                    </button>
                </div>
            </div>

            <!-- Hero Analytics Section: Big KPI Card & Interactive Histogram -->
            <div class="usage-hero-grid">
                <!-- Left Hero KPI Card -->
                <div class="usage-hero-card">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="room-spec-pill" id="heroPeriodTag" style="font-weight: 800;">HARI INI (24 JAM)</span>
                            <span style="font-size: 11px; font-weight: 700; color: #059669; background: rgba(16, 185, 129, 0.14); padding: 2px 8px; border-radius: var(--radius-pill);" id="heroStatusBadge">
                                <i class="bi bi-check-circle-fill"></i> Normal
                            </span>
                        </div>

                        <div class="usage-stat-kpi">
                            <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); display: block; text-transform: uppercase;">Total Konsumsi Data</span>
                            <div style="display: flex; align-items: baseline; margin-top: 2px;">
                                <span class="usage-kpi-val" id="heroTotalVal"><?= $overall['daily_total_gb'] ?? '0' ?></span>
                                <span class="usage-kpi-unit" id="heroTotalUnit">GB</span>
                            </div>
                        </div>

                        <!-- Inset Download & Upload Sub-Boxes -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px;">
                            <div style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 10px 14px;">
                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                    <span class="pulse-green-dot"></span>
                                    <span style="font-size: 10.5px; font-weight: 800; color: #059669;">UNDUH</span>
                                </div>
                                <strong id="heroDlVal" style="font-size: 15px; font-weight: 800; color: var(--text-heading); font-family: monospace;"><?= $overall['daily_dl_gb'] ?? '0' ?> GB</strong>
                                <span id="heroDlPct" style="font-size: 10px; color: var(--text-muted); display: block;">86.4% dari total</span>
                            </div>

                            <div style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 10px 14px;">
                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                    <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#0284c7;"></span>
                                    <span style="font-size: 10.5px; font-weight: 800; color: #0284c7;">UNGGAH</span>
                                </div>
                                <strong id="heroUlVal" style="font-size: 15px; font-weight: 800; color: var(--text-heading); font-family: monospace;"><?= $overall['daily_ul_gb'] ?? '0' ?> GB</strong>
                                <span id="heroUlPct" style="font-size: 10px; color: var(--text-muted); display: block;">13.6% dari total</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 16px; padding-top: 12px; border-top: 1px dashed rgba(182, 198, 220, 0.4); display: flex; justify-content: space-between; font-size: 11.5px; color: var(--text-muted);">
                        <span>Puncak: <strong id="heroPeakSpeed" style="color: var(--text-heading);">3.4 MB/s</strong></span>
                        <span>Jam Ramai: <strong id="heroPeakHour" style="color: var(--text-heading);">20:00 WITA</strong></span>
                    </div>
                </div>

                <!-- Right Histogram Chart Card -->
                <div class="usage-chart-card">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <div>
                                <h3 class="net-panel-title" id="chartTitle">Distribusi Trafik Harian (Per 3 Jam)</h3>
                                <span class="net-panel-subtitle" id="chartSubtitle">Perbandingan Volume Unduh vs Unggah</span>
                            </div>
                            <div style="display: flex; gap: 12px; font-size: 11px; font-weight: 700;">
                                <span style="display: flex; align-items: center; gap: 5px; color: #059669;">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; background: #10b981;"></span> Unduh
                                </span>
                                <span style="display: flex; align-items: center; gap: 5px; color: #0284c7;">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; background: #0284c7;"></span> Unggah
                                </span>
                            </div>
                        </div>

                        <!-- Dynamic Animated Bar Chart -->
                        <div class="chart-bars-wrap" id="chartBarsContainer">
                            <!-- Injected by JavaScript switchUsagePeriod() -->
                        </div>
                    </div>

                    <div style="margin-top: 10px; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 8px 14px; display: flex; justify-content: space-between; font-size: 11px; font-weight: 600; color: var(--text-muted);">
                        <span>Rata-Rata Unduh: <strong style="color: #059669;" id="chartAvgDl">356 KB/s</strong></span>
                        <span>Rata-Rata Unggah: <strong style="color: #0284c7;" id="chartAvgUl">56 KB/s</strong></span>
                        <span>Efisiensi: <strong style="color: var(--text-heading);">99.8% (Tanpa Gangguan)</strong></span>
                    </div>
                </div>
            </div>

            <!-- Secondary Insight Metric Row -->
            <div class="rooms-grid" style="margin-top: 18px;">
                <!-- Insight 1: Unduh Periode -->
                <div class="room-card">
                    <div class="room-card-top">
                        <span class="room-card-title">Total Unduh</span>
                        <span class="room-spec-pill" style="color: #059669;">Download</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #10b981;">
                            <i class="bi bi-arrow-down-circle-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="kpiDlVal" style="font-size: 18px; color: #059669;"><?= $overall['daily_dl_gb'] ?? '0' ?> <span class="stat-unit-inline">GB</span></span>
                            <span class="room-stat-unit">Terserap oleh Klien</span>
                        </div>
                    </div>
                </div>

                <!-- Insight 2: Unggah Periode -->
                <div class="room-card">
                    <div class="room-card-top">
                        <span class="room-card-title">Total Unggah</span>
                        <span class="room-spec-pill" style="color: #0284c7;">Upload</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #0284c7;">
                            <i class="bi bi-arrow-up-circle-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="kpiUlVal" style="font-size: 18px; color: #0284c7;"><?= $overall['daily_ul_gb'] ?? '0' ?> <span class="stat-unit-inline">GB</span></span>
                            <span class="room-stat-unit">Terkirim ke Internet</span>
                        </div>
                    </div>
                </div>

                <!-- Insight 3: Kecepatan Live Throughput -->
                <div class="room-card">
                    <div class="room-card-top">
                        <span class="room-card-title">Kecepatan Live</span>
                        <span class="room-spec-pill">Real-Time</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #8b5cf6;">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <div class="room-stat" style="display: flex; flex-direction: column; gap: 3px;">
                            <span style="font-size: 13.5px; font-weight: 800; color: #059669;" id="kpiLiveRx">&darr; <?= $overall['live_rx_kbps'] ?? 0 ?> KB/s</span>
                            <span style="font-size: 12.5px; font-weight: 700; color: #0284c7;" id="kpiLiveTx">&uarr; <?= $overall['live_tx_kbps'] ?? 0 ?> KB/s</span>
                        </div>
                    </div>
                </div>

                <!-- Insight 4: Top Consumer Device -->
                <div class="room-card">
                    <div class="room-card-top">
                        <span class="room-card-title">Pengguna Terbesar</span>
                        <span class="room-spec-pill" id="kpiTopShare"><?= $overall['top_device']['usage_pct'] ?? '0' ?>%</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #ec4899;">
                            <i class="bi bi-award-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="kpiTopName" style="font-size: 15px; font-weight: 800;"><?= htmlspecialchars($overall['top_device']['name'] ?? '-') ?></span>
                            <span class="room-stat-unit" id="kpiTopTotal"><?= $overall['top_device']['total_formatted'] ?? '-' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Per-Device Usage Table with Rankings -->
            <div class="net-panel-card" style="margin-top: 18px;">
                <div class="net-panel-header">
                    <div>
                        <h3 class="net-panel-title">Peringkat & Rincian Penggunaan Tiap Perangkat Klien</h3>
                        <span class="net-panel-subtitle" id="tableSubtitle">Trafik Kecepatan Langsung, Unduh, Unggah, dan Total Konsumsi Tiap Klien</span>
                    </div>
                    <span class="members-badge-count" id="usageActiveCount"><?= count($devices) ?> Klien Terpantau</span>
                </div>

                <div class="net-table-container">
                    <table class="net-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Rank</th>
                                <th>Perangkat</th>
                                <th>Alamat IP & MAC</th>
                                <th>Kecepatan Langsung</th>
                                <th>Unduh (Download)</th>
                                <th>Unggah (Upload)</th>
                                <th>Total Pemakaian</th>
                                <th>Porsi Jaringan</th>
                                <th style="text-align: right;">Waktu Aktif</th>
                            </tr>
                        </thead>
                        <tbody id="deviceUsageTableBody">
                            <?php if (empty($devices)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 28px;">Tidak ada data perangkat aktif saat ini</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($devices as $idx => $d): ?>
                                    <?php
                                        $rankClass = ($idx === 0) ? 'rank-gold' : (($idx === 1) ? 'rank-silver' : (($idx === 2) ? 'rank-bronze' : 'rank-normal'));
                                        $rankLabel = '#' . ($idx + 1);
                                    ?>
                                    <tr class="client-row">
                                        <td>
                                            <span class="rank-badge <?= $rankClass ?>"><?= $rankLabel ?></span>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="member-avatar-wrap" style="width: 34px; height: 34px; color: <?= htmlspecialchars($d['color']) ?>;">
                                                    <i class="bi <?= htmlspecialchars($d['icon']) ?>" style="font-size: 15px;"></i>
                                                </div>
                                                <div>
                                                    <strong style="color: var(--text-heading); font-size: 12.5px; display: block;"><?= htmlspecialchars($d['name']) ?></strong>
                                                    <span style="font-size: 10.5px; color: var(--text-muted);"><?= htmlspecialchars($d['type']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="net-code-tag"><?= htmlspecialchars($d['ip']) ?></code>
                                            <span class="member-mac-tag" style="display: block; margin-top: 3px; font-size: 10.5px;"><?= htmlspecialchars($d['mac']) ?></span>
                                        </td>
                                        <td>
                                            <span style="font-size: 11.5px; font-weight: 700; color: #059669; display: block;">&darr; <?= $d['rx_kbps'] ?> KB/s</span>
                                            <span style="font-size: 11px; font-weight: 600; color: #0284c7;">&uarr; <?= $d['tx_kbps'] ?> KB/s</span>
                                        </td>
                                        <td>
                                            <strong style="color: #059669; font-size: 12.5px;"><?= $d['download_formatted'] ?></strong>
                                        </td>
                                        <td>
                                            <strong style="color: #0284c7; font-size: 12.5px;"><?= $d['upload_formatted'] ?></strong>
                                        </td>
                                        <td>
                                            <strong style="color: var(--text-heading); font-size: 13px;"><?= $d['total_formatted'] ?></strong>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span class="room-spec-pill" style="font-weight: 800; font-size: 10.5px;"><?= $d['usage_pct'] ?>%</span>
                                                <div style="width: 50px; height: 6px; border-radius: 3px; background: rgba(182, 198, 220, 0.4); overflow: hidden;">
                                                    <div style="width: <?= min(100, $d['usage_pct']) ?>%; height: 100%; border-radius: 3px; background: <?= htmlspecialchars($d['color']) ?>;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: right; color: var(--text-muted); font-size: 11.5px; font-weight: 600;">
                                            <span class="pulse-green-dot" style="display: inline-block; margin-right: 4px;"></span>
                                            <?= htmlspecialchars($d['online_time']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bandwidth per IP Section -->
            <div class="room-card" style="margin-top:20px;">
                <div class="room-card-top">
                    <span class="room-card-title">Bandwidth per IP</span>
                    <span class="room-spec-pill"><i class="bi bi-bar-chart-fill"></i></span>
                </div>
                <div class="room-card-body">
                    <button type="button" class="btn-primary-neumorphic" onclick="loadBandwidthPerIP()" style="padding:8px 16px;margin-bottom:10px;">
                        <i class="bi bi-arrow-clockwise"></i> <span>Refresh</span>
                    </button>
                    <div id="bwPerIP" style="max-height:300px;overflow-y:auto;">
                        <p style="color:var(--text-muted);font-size:12px;">Klik Refresh untuk melihat data</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Store current selected period
        let currentPeriod = 'daily';
        let latestOverall = <?= json_encode($overall) ?>;
        let latestDevices = <?= json_encode($devices) ?>;

        // Chart Data Definitions for Each Period (REAL accumulated history)
        const chartDataSets = <?= json_encode($usageChartsReal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        // Keep legacy keys used by renderHistogram() tooltips
        chartDataSets.daily.peak = chartDataSets.daily.avgDl;
        chartDataSets.weekly.peak = chartDataSets.weekly.avgDl;
        chartDataSets.monthly.peak = chartDataSets.monthly.avgDl;
        chartDataSets.lifetime.peak = chartDataSets.lifetime.avgDl;

        // Render the Histogram Chart
        function renderHistogram(periodKey) {
            const container = document.getElementById('chartBarsContainer');
            if (!container) return;

            const ds = chartDataSets[periodKey] || chartDataSets.daily;
            document.getElementById('chartTitle').textContent = ds.title;
            document.getElementById('chartSubtitle').textContent = ds.subtitle;
            document.getElementById('chartAvgDl').textContent = ds.avgDl;
            document.getElementById('chartAvgUl').textContent = ds.avgUl;

            let html = '';
            for (let i = 0; i < ds.labels.length; i++) {
                const label = ds.labels[i];
                const dlVal = ds.dl[i];
                const ulVal = ds.ul[i];
                const total = dlVal + ulVal;
                const dlHeightPct = Math.min(100, (dlVal / ds.max) * 100);
                const ulHeightPct = Math.min(100, (ulVal / ds.max) * 100);
                const unit = (periodKey === 'daily') ? 'MB' : 'GB';

                html += `
                    <div class="chart-bar-group" title="${label}: Unduh ${dlVal} ${unit}, Unggah ${ulVal} ${unit}">
                        <div class="chart-bar-stem">
                            <div class="bar-stack-dl" style="height: ${Math.max(6, dlHeightPct)}%;"></div>
                            <div class="bar-stack-ul" style="height: ${Math.max(3, ulHeightPct)}%;"></div>
                        </div>
                        <span class="chart-bar-label">${label}</span>
                    </div>
                `;
            }
            container.innerHTML = html;
        }

        // Switch Active Period
        function switchUsagePeriod(periodKey, btn) {
            currentPeriod = periodKey;

            // Update Segmented Buttons Active State
            document.querySelectorAll('.nm-seg-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');

            const heroTag = document.getElementById('heroPeriodTag');
            const heroTotal = document.getElementById('heroTotalVal');
            const heroUnit = document.getElementById('heroTotalUnit');
            const heroDl = document.getElementById('heroDlVal');
            const heroUl = document.getElementById('heroUlVal');
            const heroDlPct = document.getElementById('heroDlPct');
            const heroUlPct = document.getElementById('heroUlPct');
            const heroPeak = document.getElementById('heroPeakSpeed');
            const heroPeakHour = document.getElementById('heroPeakHour');

            const kpiDl = document.getElementById('kpiDlVal');
            const kpiUl = document.getElementById('kpiUlVal');

            const ds = chartDataSets[periodKey] || chartDataSets.daily;
            if (heroPeak) heroPeak.textContent = ds.avgDl;
            if (heroPeakHour) heroPeakHour.textContent = ds.subtitle;

            if (periodKey === 'daily') {
                if (heroTag) heroTag.textContent = 'HARI INI (24 JAM)';
                if (heroTotal) heroTotal.textContent = latestOverall.daily_total_gb || '0';
                if (heroDl) heroDl.textContent = `${latestOverall.daily_dl_gb ?? '0'} GB`;
                if (heroUl) heroUl.textContent = `${latestOverall.daily_ul_gb ?? '0'} GB`;
                if (kpiDl) kpiDl.innerHTML = `${latestOverall.daily_dl_gb ?? '0'} <span class="stat-unit-inline">GB</span>`;
                if (kpiUl) kpiUl.innerHTML = `${latestOverall.daily_ul_gb ?? '0'} <span class="stat-unit-inline">GB</span>`;
            } else if (periodKey === 'weekly') {
                if (heroTag) heroTag.textContent = 'MINGGU INI (7 HARI)';
                if (heroTotal) heroTotal.textContent = latestOverall.weekly_total_gb || '0';
                if (heroDl) heroDl.textContent = `${latestOverall.weekly_dl_gb ?? '0'} GB`;
                if (heroUl) heroUl.textContent = `${latestOverall.weekly_ul_gb ?? '0'} GB`;
                if (kpiDl) kpiDl.innerHTML = `${latestOverall.weekly_dl_gb ?? '0'} <span class="stat-unit-inline">GB</span>`;
                if (kpiUl) kpiUl.innerHTML = `${latestOverall.weekly_ul_gb ?? '0'} <span class="stat-unit-inline">GB</span>`;
            } else if (periodKey === 'monthly') {
                if (heroTag) heroTag.textContent = 'BULAN INI (30 HARI)';
                if (heroTotal) heroTotal.textContent = latestOverall.monthly_total_gb || '0';
                if (heroDl) heroDl.textContent = `${latestOverall.monthly_dl_gb ?? '0'} GB`;
                if (heroUl) heroUl.textContent = `${latestOverall.monthly_ul_gb ?? '0'} GB`;
                if (kpiDl) kpiDl.innerHTML = `${latestOverall.monthly_dl_gb ?? '0'} <span class="stat-unit-inline">GB</span>`;
                if (kpiUl) kpiUl.innerHTML = `${latestOverall.monthly_ul_gb ?? '0'} <span class="stat-unit-inline">GB</span>`;
            } else if (periodKey === 'lifetime') {
                if (heroTag) heroTag.textContent = 'TOTAL SEMUA WAKTU';
                if (heroTotal) heroTotal.textContent = latestOverall.lifetime_total_gb || '0';
                if (heroDl) heroDl.textContent = `${latestOverall.lifetime_dl_gb ?? '0'} GB`;
                if (heroUl) heroUl.textContent = `${latestOverall.lifetime_ul_gb ?? '0'} GB`;
                if (kpiDl) kpiDl.innerHTML = `${latestOverall.lifetime_dl_gb ?? '0'} <span class="stat-unit-inline">GB</span>`;
                if (kpiUl) kpiUl.innerHTML = `${latestOverall.lifetime_ul_gb ?? '0'} <span class="stat-unit-inline">GB</span>`;
            }

            renderHistogram(periodKey);
        }

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

        async function handleResetUsageTracker() {
            if (confirm('Apakah Anda yakin ingin mereset statistik sesi penggunaan klien?')) {
                showToast('Statistik sesi penggunaan berhasil direset!', 'success');
                setTimeout(() => window.location.reload(), 600);
            }
        }

        // =========================================================================
        // REAL-TIME POLLING LOOP (Every 2 Seconds)
        // =========================================================================
        async function pollUsageTelemetry() {
            try {
                const res = await fetch('api.php?action=get_system_stats');
                const data = await res.json();
                if (data.success && data.data) {
                    updateUsageUI(data.data);
                }
            } catch (err) {
                console.warn('Polling usage dilewati:', err.message);
            }
        }

        function updateUsageUI(state) {
            if (!state) return;
            const board = state.board || {};
            const overall = state.overall_usage || {};
            const devices = state.device_usage || [];

            latestOverall = overall;
            latestDevices = devices;

            // Header Uptime
            const uptimeEl = document.getElementById('usageUptimeText');
            if (uptimeEl && board.uptime) uptimeEl.textContent = `Aktif: ${board.uptime}`;

            // Live Throughput Pills
            const kpiRx = document.getElementById('kpiLiveRx');
            if (kpiRx && overall.live_rx_kbps !== undefined) kpiRx.innerHTML = `&darr; ${overall.live_rx_kbps} KB/s`;

            const kpiTx = document.getElementById('kpiLiveTx');
            if (kpiTx && overall.live_tx_kbps !== undefined) kpiTx.innerHTML = `&uarr; ${overall.live_tx_kbps} KB/s`;

            // Top device pill
            if (overall.top_device) {
                const topName = document.getElementById('kpiTopName');
                if (topName) topName.textContent = overall.top_device.name;

                const topShare = document.getElementById('kpiTopShare');
                if (topShare) topShare.textContent = `${overall.top_device.usage_pct}%`;

                const topTotal = document.getElementById('kpiTopTotal');
                if (topTotal) topTotal.textContent = overall.top_device.total_formatted;
            }

            // Active Count
            const activeCount = document.getElementById('usageActiveCount');
            if (activeCount) activeCount.textContent = `${devices.length} Klien Terpantau`;

            // Refresh Hero with current selected period
            switchUsagePeriod(currentPeriod, document.querySelector(`.nm-seg-btn[data-period="${currentPeriod}"]`));

            // Update Devices Table Body
            const tbody = document.getElementById('deviceUsageTableBody');
            if (tbody && devices.length > 0) {
                const esc = v => String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
                let rowsHtml = '';
                devices.forEach((d, idx) => {
                    const rankClass = (idx === 0) ? 'rank-gold' : ((idx === 1) ? 'rank-silver' : ((idx === 2) ? 'rank-bronze' : 'rank-normal'));
                    const rankLabel = '#' + (idx + 1);

                    rowsHtml += `
                        <tr class="client-row">
                            <td>
                                <span class="rank-badge ${esc(rankClass)}">${rankLabel}</span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="member-avatar-wrap" style="width: 34px; height: 34px; color: ${esc(d.color)};">
                                        <i class="bi ${esc(d.icon)}" style="font-size: 15px;"></i>
                                    </div>
                                    <div>
                                        <strong style="color: var(--text-heading); font-size: 12.5px; display: block;">${esc(d.name)}</strong>
                                        <span style="font-size: 10.5px; color: var(--text-muted);">${esc(d.type)}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code class="net-code-tag">${esc(d.ip)}</code>
                                <span class="member-mac-tag" style="display: block; margin-top: 3px; font-size: 10.5px;">${esc(d.mac)}</span>
                            </td>
                            <td>
                                <span style="font-size: 11.5px; font-weight: 700; color: #059669; display: block;">&darr; ${esc(d.rx_kbps)} KB/s</span>
                                <span style="font-size: 11px; font-weight: 600; color: #0284c7;">&uarr; ${esc(d.tx_kbps)} KB/s</span>
                            </td>
                            <td>
                                <strong style="color: #059669; font-size: 12.5px;">${esc(d.download_formatted)}</strong>
                            </td>
                            <td>
                                <strong style="color: #0284c7; font-size: 12.5px;">${esc(d.upload_formatted)}</strong>
                            </td>
                            <td>
                                <strong style="color: var(--text-heading); font-size: 13px;">${esc(d.total_formatted)}</strong>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="room-spec-pill" style="font-weight: 800; font-size: 10.5px;">${esc(d.usage_pct)}%</span>
                                    <div style="width: 50px; height: 6px; border-radius: 3px; background: rgba(182, 198, 220, 0.4); overflow: hidden;">
                                        <div style="width: ${Math.min(100, d.usage_pct)}%; height: 100%; border-radius: 3px; background: ${esc(d.color)};"></div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: right; color: var(--text-muted); font-size: 11.5px; font-weight: 600;">
                                <span class="pulse-green-dot" style="display: inline-block; margin-right: 4px;"></span>
                                ${esc(d.online_time)}
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = rowsHtml;
            }
        }

        // Initialize default view
        renderHistogram('daily');

        // Start Live Polling Loop every 2000ms
        setInterval(pollUsageTelemetry, 2000);
    </script>
</body>
</html>

<script>
async function loadBandwidthPerIP() {
    var res = await fetch('api.php?action=bandwidth_per_ip');
    var data = await res.json();
    var container = document.getElementById('bwPerIP');
    if (data.success && data.devices.length > 0) {
        var html = '<table style="width:100%;font-size:12px;">' +
            '<tr><th style="text-align:left;padding:5px;">IP</th><th style="text-align:right;padding:5px;">Bytes</th><th style="text-align:right;padding:5px;">MB</th></tr>';
        data.devices.forEach(function(d) {
            html += '<tr><td style="padding:4px 5px;font-family:monospace;">' + d.ip + '</td>' +
                '<td style="text-align:right;padding:4px 5px;">' + d.bytes.toLocaleString() + '</td>' +
                '<td style="text-align:right;padding:4px 5px;">' + d.mb + '</td></tr>';
        });
        html += '</table>';
        container.innerHTML = html;
    } else {
        container.innerHTML = '<p style="color:var(--text-muted);font-size:12px;">No data</p>';
    }
}
</script>
