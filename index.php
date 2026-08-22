<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self'; img-src 'self' data:; connect-src 'self'");

require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';

// Handle standard login form submission (POST dari login_view.php)
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    if (!Auth::csrfVerify($_POST['csrf_token'] ?? null)) {
        $loginError = 'Sesi tidak valid atau kedaluwarsa. Silakan coba lagi.';
    } elseif (Auth::isRateLimited()) {
        $loginError = 'Terlalu banyak percobaan login. Tunggu 60 detik.';
    } elseif (Auth::login($_POST['username'], $_POST['password'], !empty($_POST['remember']))) {
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Nama pengguna atau kata sandi salah.';
    }
}

$isLoggedIn = Auth::check();
$state = SystemMonitor::getFullState();
$currentPage = 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gateway 4G LTE & Hotspot - Orange Pi Zero 2</title>
    <meta name="description" content="Pusat Kontrol & Telemetri Modem 4G LTE Hotspot Router Orange Pi Zero 2">
    <meta name="theme-color" content="#0284c7">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/orange-pi-logo.png">
    <link rel="stylesheet" href="assets/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
    <script>
    // Load saved theme
    (function(){var t=localStorage.getItem('opi-theme');if(t){document.documentElement.setAttribute('data-theme',t);}})();
    </script>
</head>
<body class="<?= $isLoggedIn ? 'app-logged-in' : 'app-logged-out' ?>">

    <?php if (!$isLoggedIn): ?>
        <!-- Modern Neumorphic Login Form -->
        <?php include __DIR__ . '/includes/login_view.php'; ?>
    <?php else: ?>
        <!-- 3-Column Luxury Neumorphic Dashboard -->
        <div class="app-container">
            <!-- Left Sidebar Dock -->
            <?php include __DIR__ . '/includes/sidebar.php'; ?>

            <!-- Main Control Workspace -->
            <main class="main-content">
                <!-- Header: Board Info, IP, Uptime & Reboot Actions -->
                <?php include __DIR__ . '/includes/header.php'; ?>

                <!-- Top 4 Router & Gateway KPI Metric Cards -->
                <?php include __DIR__ . '/includes/overview.php'; ?>
                <!-- Speed Test Card -->
                <div class="room-card" data-metric-card="speedtest" style="cursor:pointer;" onclick="runSpeedTest()">
                    <div class="room-card-top">
                        <span class="room-card-title">Speed Test</span>
                        <span class="room-spec-pill"><i class="bi bi-speedometer2"></i></span>
                    </div>
                    <div class="room-card-body">
                        <div class="speedtest-results" id="speedtestResults">
                            <p style="color:var(--text-muted);font-size:12px;">Klik untuk test kecepatan</p>
                        </div>
                    </div>
                </div>


                <!-- Middle Grid: CPU Thermal Dial + Hotspot & AdGuard Modules -->
                <div class="middle-grid">
                    <?php include __DIR__ . '/includes/ac_control.php'; ?>
                    <?php include __DIR__ . '/includes/device_cards.php'; ?>
                </div>

                <!-- Bottom Grid: Dual Sparklines Bandwidth + System Sensors -->
                <div class="bottom-grid">
                    <?php include __DIR__ . '/includes/electricity_summary.php'; ?>
                    <?php include __DIR__ . '/includes/sensor_cards.php'; ?>
                </div>
            </main>

            <!-- Right Panel: Gateway Profile & Connected Devices -->
            <?php include __DIR__ . '/includes/right_panel.php'; ?>
        </div>

        <!-- Safe Reboot Confirmation Modal -->
        <?php include __DIR__ . '/includes/modal_reboot.php'; ?>
    <?php endif; ?>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Self-Contained Real-Time Telemetry Engine (Identical Architecture to usage.php) -->
    <script>
        window.__IS_LOGGED_IN__ = <?= json_encode($isLoggedIn) ?>;
        window.__INITIAL_STATE__ = <?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        document.addEventListener('DOMContentLoaded', () => {
            if (!window.__IS_LOGGED_IN__) return;

            // =========================================================================
            // 1. THERMAL DIAL RADIAL TICKS
            // =========================================================================
            const radialTicksSvg = document.getElementById('radialTicksSvg');
            const TOTAL_TICKS = 48;
            const RADIUS = 76;
            const CENTER_X = 88;
            const CENTER_Y = 88;
            const START_ANGLE = 135;
            const SWEEP_ANGLE = 270;

            function initRadialTicks() {
                if (!radialTicksSvg) return;
                radialTicksSvg.innerHTML = '';
                for (let i = 0; i < TOTAL_TICKS; i++) {
                    const angleDeg = START_ANGLE + (i / (TOTAL_TICKS - 1)) * SWEEP_ANGLE;
                    const angleRad = (angleDeg * Math.PI) / 180;
                    const x1 = CENTER_X + (RADIUS - 8) * Math.cos(angleRad);
                    const y1 = CENTER_Y + (RADIUS - 8) * Math.sin(angleRad);
                    const x2 = CENTER_X + RADIUS * Math.cos(angleRad);
                    const y2 = CENTER_Y + RADIUS * Math.sin(angleRad);

                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', x1.toFixed(2));
                    line.setAttribute('y1', y1.toFixed(2));
                    line.setAttribute('x2', x2.toFixed(2));
                    line.setAttribute('y2', y2.toFixed(2));
                    line.setAttribute('class', 'radial-tick');
                    line.setAttribute('stroke', 'rgba(182, 198, 220, 0.45)');
                    line.setAttribute('stroke-width', '2.5');
                    line.setAttribute('stroke-linecap', 'round');
                    radialTicksSvg.appendChild(line);
                }
            }

            function renderThermalDial(temp) {
                const tempValDisplay = document.getElementById('tempValDisplay');
                const thermalStatusLabel = document.getElementById('thermalStatusLabel');
                const acFanIcon = document.getElementById('acFanIcon');
                if (!radialTicksSvg || !tempValDisplay) return;

                const currentTemp = Math.round(temp);
                tempValDisplay.textContent = `${currentTemp}°C`;

                const minTemp = 30;
                const maxTemp = 85;
                const clampedTemp = Math.max(minTemp, Math.min(maxTemp, currentTemp));
                const activeCount = Math.round(((clampedTemp - minTemp) / (maxTemp - minTemp)) * TOTAL_TICKS);

                const ticks = radialTicksSvg.querySelectorAll('.radial-tick');
                ticks.forEach((tick, idx) => {
                    if (idx <= activeCount) {
                        tick.classList.add('active');
                        tick.style.stroke = (currentTemp > 75) ? '#ef4444' : ((currentTemp > 60) ? '#f59e0b' : '#0ea5e9');
                    } else {
                        tick.classList.remove('active');
                        tick.style.stroke = 'rgba(182, 198, 220, 0.45)';
                    }
                });

                if (thermalStatusLabel) {
                    if (currentTemp < 60) {
                        thermalStatusLabel.textContent = 'Optimal';
                        thermalStatusLabel.style.color = '#38bdf8';
                        if (acFanIcon) acFanIcon.style.animationDuration = '4s';
                    } else if (currentTemp < 75) {
                        thermalStatusLabel.textContent = 'Hangat';
                        thermalStatusLabel.style.color = '#fbbf24';
                        if (acFanIcon) acFanIcon.style.animationDuration = '2s';
                    } else {
                        thermalStatusLabel.textContent = 'Suhu Tinggi';
                        thermalStatusLabel.style.color = '#f87171';
                        if (acFanIcon) acFanIcon.style.animationDuration = '0.8s';
                    }
                }
            }

            // =========================================================================
            // 2. OSCILLOSCOPE SPARKLINE WAVEFORMS (Download, Upload & System Load)
            // =========================================================================
            let loadHistory = [0.42, 0.48, 0.52, 0.45, 0.58, 0.50, 0.54];
            let dlHistory = [140, 180, 240, 190, 320, 280, 360, 420];
            let ulHistory = [35, 45, 60, 40, 85, 70, 95, 110];

            function updateLoadSparkline(currentLoad) {
                loadHistory.shift();
                loadHistory.push(parseFloat(currentLoad) || 0.5);

                const svgArea = document.getElementById('sparklineLoadArea');
                const svgLine = document.getElementById('sparklineLoadLine');
                if (!svgArea || !svgLine) return;

                const points = loadHistory.map((v, i) => {
                    const x = Math.round(i * (100 / (loadHistory.length - 1)));
                    const normalized = Math.max(0.1, Math.min(2.5, v));
                    const y = Math.round(32 - (normalized / 2.5) * 26);
                    return { x, y };
                });

                let d = `M${points[0].x},${points[0].y}`;
                for (let i = 0; i < points.length - 1; i++) {
                    const p0 = points[i];
                    const p1 = points[i + 1];
                    const cx = Math.round((p0.x + p1.x) / 2);
                    d += ` C${cx},${p0.y} ${cx},${p1.y} ${p1.x},${p1.y}`;
                }
                svgLine.setAttribute('d', d);
                svgArea.setAttribute('d', `${d} L100,36 L0,36 Z`);
            }

            function updateBandwidthSparklines(dlRateKbps, ulRateKbps) {
                dlHistory.shift();
                dlHistory.push(Math.max(10, parseFloat(dlRateKbps) || 140));

                ulHistory.shift();
                ulHistory.push(Math.max(5, parseFloat(ulRateKbps) || 35));

                // 1. Download Sparkline (Emerald / Cyan Wave)
                const svgDlArea = document.getElementById('sparklineDlArea');
                const svgDlLine = document.getElementById('sparklineDlLine');
                if (svgDlArea && svgDlLine) {
                    const maxDl = Math.max(...dlHistory, 400);
                    const pointsDl = dlHistory.map((v, i) => {
                        const x = Math.round(i * (200 / (dlHistory.length - 1)));
                        const y = Math.round(44 - (v / maxDl) * 36);
                        return { x, y };
                    });

                    let d = `M${pointsDl[0].x},${pointsDl[0].y}`;
                    for (let i = 0; i < pointsDl.length - 1; i++) {
                        const p0 = pointsDl[i];
                        const p1 = pointsDl[i + 1];
                        const cx = Math.round((p0.x + p1.x) / 2);
                        d += ` C${cx},${p0.y} ${cx},${p1.y} ${p1.x},${p1.y}`;
                    }
                    svgDlLine.setAttribute('d', d);
                    svgDlArea.setAttribute('d', `${d} L200,48 L0,48 Z`);
                }

                // 2. Upload Sparkline (Blue / Purple Wave)
                const svgUlArea = document.getElementById('sparklineUlArea');
                const svgUlLine = document.getElementById('sparklineUlLine');
                if (svgUlArea && svgUlLine) {
                    const maxUl = Math.max(...ulHistory, 180);
                    const pointsUl = ulHistory.map((v, i) => {
                        const x = Math.round(i * (200 / (ulHistory.length - 1)));
                        const y = Math.round(44 - (v / maxUl) * 36);
                        return { x, y };
                    });

                    let d = `M${pointsUl[0].x},${pointsUl[0].y}`;
                    for (let i = 0; i < pointsUl.length - 1; i++) {
                        const p0 = pointsUl[i];
                        const p1 = pointsUl[i + 1];
                        const cx = Math.round((p0.x + p1.x) / 2);
                        d += ` C${cx},${p0.y} ${cx},${p1.y} ${p1.x},${p1.y}`;
                    }
                    svgUlLine.setAttribute('d', d);
                    svgUlArea.setAttribute('d', `${d} L200,48 L0,48 Z`);
                }
            }

            // =========================================================================
            // 3. UI STATE SYNCHRONIZATION (Identical to usage.php)
            // =========================================================================
            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function getDeviceMeta(name) {
                const n = (name || '').toLowerCase();
                if (/(phone|redmi|xiaomi|samsung|galaxy|poco|realme|vivo|oppo|huawei|iphone|android|enall|mobile)/i.test(n)) {
                    return { type: 'Smartphone', color: 'blue', svg: '<i class="bi bi-phone" style="font-size: 16px;"></i>' };
                }
                if (/(laptop|desktop|pc|mac|win|thinkpad|notebook|book|asus|acer|lenovo|dell|hp)/i.test(n)) {
                    return { type: 'Laptop / PC', color: 'purple', svg: '<i class="bi bi-laptop" style="font-size: 16px;"></i>' };
                }
                if (/(tv|cast|roku|firetv|smarttv|display|screen)/i.test(n)) {
                    return { type: 'Smart TV', color: 'amber', svg: '<i class="bi bi-tv" style="font-size: 16px;"></i>' };
                }
                return { type: 'Hotspot AP', color: 'teal', svg: '<i class="bi bi-wifi" style="font-size: 16px;"></i>' };
            }

            function updateHomeUI(state) {
                if (!state) return;

                // 1. CPU & Thermals
                try {
                    if (state.cpu) {
                        const cpu = state.cpu;
                        const elCpuVal = document.getElementById('metricCpuVal');
                        const elCpuFreq = document.getElementById('metricCpuFreq');
                        const elGov = document.getElementById('governorDisplay');
                        const elGovLabel = document.getElementById('govLabel');
                        const elGovSwitch = document.getElementById('govPowerSwitch');

                        if (elCpuVal) elCpuVal.textContent = Math.round(cpu.usage);
                        if (elCpuFreq) elCpuFreq.textContent = `4x @ ${cpu.freq_mhz} MHz`;
                        if (elGov) elGov.textContent = cpu.governor;
                        if (elGovLabel) elGovLabel.textContent = cpu.governor.toUpperCase();
                        if (elGovSwitch) elGovSwitch.checked = (cpu.governor === 'performance');

                        renderThermalDial(cpu.temp || 54);

                        const elLoadSens = document.getElementById('load1mSensorVal');
                        if (elLoadSens && cpu.load_1m !== undefined) elLoadSens.textContent = cpu.load_1m;

                        updateLoadSparkline(cpu.load_1m || 0.5);
                    }
                } catch (e) {}

                // 2. RAM & Clients
                try {
                    if (state.ram) {
                        const elRamVal = document.getElementById('metricRamVal');
                        const elRamPct = document.getElementById('metricRamPct');
                        if (elRamVal) elRamVal.textContent = state.ram.used_mb;
                        if (elRamPct) elRamPct.textContent = `${state.ram.percent}% dari ${state.ram.total_mb}MB`;
                    }

                    const clientCount = state.clients ? state.clients.length : 0;
                    const elClients = document.getElementById('metricClientsVal');
                    const elHotspotClients = document.getElementById('hotspotClientsCount');
                    const elBadgeCount = document.getElementById('badgeClientsCount');
                    if (elClients) elClients.textContent = clientCount;
                    if (elHotspotClients) elHotspotClients.textContent = clientCount;
                    if (elBadgeCount) elBadgeCount.textContent = `${clientCount} Aktif`;
                } catch (e) {}

                // 3. Bandwidth Throughput (Identical to usage.php overall_usage)
                try {
                    const overall = state.overall_usage || {};
                    const rxRate = (overall.live_rx_kbps !== undefined) ? overall.live_rx_kbps : 145.2;
                    const txRate = (overall.live_tx_kbps !== undefined) ? overall.live_tx_kbps : 42.8;

                    const dlFormatted = rxRate >= 1024 ? (rxRate / 1024).toFixed(2) + ' MB/s' : rxRate.toFixed(1) + ' KB/s';
                    const ulFormatted = txRate >= 1024 ? (txRate / 1024).toFixed(2) + ' MB/s' : txRate.toFixed(1) + ' KB/s';

                    const elDl = document.getElementById('liveDlSpeedText');
                    const elUl = document.getElementById('liveUlSpeedText');
                    if (elDl) elDl.textContent = dlFormatted;
                    if (elUl) elUl.textContent = ulFormatted;

                    let totalRxMb = 0;
                    let totalTxMb = 0;
                    if (state.networks) {
                        Object.values(state.networks).forEach(iface => {
                            totalRxMb += (iface.rx_mb || 0);
                            totalTxMb += (iface.tx_mb || 0);
                        });
                    }

                    const elTotalDl = document.getElementById('liveTotalDlMb');
                    const elTotalUl = document.getElementById('liveTotalUlMb');
                    const elTotal = document.getElementById('totalTrafficText');
                    if (elTotalDl) elTotalDl.textContent = `${totalRxMb.toFixed(1)} MB`;
                    if (elTotalUl) elTotalUl.textContent = `${totalTxMb.toFixed(1)} MB`;
                    if (elTotal) elTotal.textContent = `${Math.round(totalRxMb + totalTxMb)} MB`;

                    updateBandwidthSparklines(rxRate, txRate);
                } catch (e) {}

                // 4. Ping Latency
                try {
                    if (state.ping) {
                        const elPingVal = document.getElementById('pingValueNum');
                        const elPingStatus = document.getElementById('pingStatusLabel');
                        const elPingDot = document.getElementById('pingLiveDot');
                        if (elPingVal) elPingVal.textContent = state.ping.ms;
                        if (elPingStatus) {
                            elPingStatus.textContent = state.ping.status;
                            elPingStatus.className = `ping-status-${state.ping.quality || 'good'}`;
                        }
                        if (elPingDot) elPingDot.className = `ping-live-dot ${state.ping.online ? '' : 'offline'}`;
                    }
                } catch (e) {}

                // 5. Connected Clients List
                try {
                    const rightClientsList = document.getElementById('rightClientsList');
                    if (rightClientsList && state.clients) {
                        if (state.clients.length === 0) {
                            rightClientsList.innerHTML = '<div class="member-item-empty">Tidak ada perangkat terhubung</div>';
                        } else {
                            rightClientsList.innerHTML = state.clients.map(c => {
                                const meta = getDeviceMeta(c.name);
                                const shortMac = c.mac ? c.mac.slice(-8) : '00:00:00';
                                return `
                                    <div class="member-item">
                                        <div class="member-avatar-wrap avatar-${escapeHtml(meta.color)}" title="${escapeHtml(meta.type)}">
                                            ${meta.svg}
                                        </div>
                                        <div class="member-meta">
                                            <div class="member-name-row">
                                                <h4 class="member-name">${escapeHtml(c.name)}</h4>
                                                <span class="member-mac-tag">${escapeHtml(shortMac)}</span>
                                            </div>
                                            <span class="member-role">${escapeHtml(c.ip)} &bull; ${escapeHtml(meta.type)}</span>
                                        </div>
                                    </div>
                                `;
                            }).join('');
                        }
                    }
                } catch (e) {}
            }

            // =========================================================================
            // 4. REAL-TIME POLLING LOOP (Identical Architecture to usage.php)
            // =========================================================================
            let isPollingActive = true;
            async function pollHomeTelemetry() {
                if (!isPollingActive) return;
                try {
                    const res = await fetch('api.php?action=get_system_stats');
                    const data = await res.json();
                    if (data.success && data.data) {
                        updateHomeUI(data.data);
                    }
                } catch (err) {
                    console.warn('Live poll dilewati:', err.message);
                }
            }

            // Live Polling Switch
            const livePollingSwitch = document.getElementById('livePollingSwitch');
            if (livePollingSwitch) {
                livePollingSwitch.addEventListener('change', (e) => {
                    isPollingActive = e.target.checked;
                    showToast(isPollingActive ? 'Telemetri Langsung Dilanjutkan' : 'Telemetri Langsung Dijeda', 'info');
                });
            }

            // Real-Time Server Clock
            const daysIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const monthsIndo = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            function tickClock() {
                const now = new Date();
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                const ss = String(now.getSeconds()).padStart(2, '0');
                const elH = document.getElementById('clockHours');
                const elM = document.getElementById('clockMinutes');
                const elS = document.getElementById('clockSeconds');
                const elDate = document.getElementById('clockFullDate');
                if (elH) elH.textContent = hh;
                if (elM) elM.textContent = mm;
                if (elS) elS.textContent = ss;
                if (elDate) elDate.textContent = `${daysIndo[now.getDay()]}, ${now.getDate()} ${monthsIndo[now.getMonth()]} ${now.getFullYear()}`;
            }

            // Init Components
            initRadialTicks();
            updateHomeUI(window.__INITIAL_STATE__);
            tickClock();
            setInterval(tickClock, 1000);

            // Start High-Frequency Real-Time Polling Loop every 2000ms (Identical to usage.php)
            setInterval(pollHomeTelemetry, 2000);
        });

        // Toast Helper
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

        // Governor Switcher
        async function setGovernor(gov) {
            try {
                const res = await fetch('api.php?action=set_cpu_governor', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ governor: gov })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Mode Governor CPU diubah ke ${gov.toUpperCase()}`, 'success');
                }
            } catch (err) {
                showToast('Gagal mengubah mode performa CPU', 'info');
            }
        }

        document.getElementById('btnGovPowersave')?.addEventListener('click', () => setGovernor('powersave'));
        document.getElementById('btnPresetAmber')?.addEventListener('click', () => setGovernor('performance'));
        document.getElementById('btnGovOndemand')?.addEventListener('click', () => setGovernor('ondemand'));

        // Ping Test Button
        document.getElementById('btnTestPing')?.addEventListener('click', async () => {
            const btn = document.getElementById('btnTestPing');
            if (btn) btn.classList.add('spinning');
            showToast('Menguji latensi koneksi internet...', 'info');
            try {
                const res = await fetch('api.php?action=test_ping');
                const data = await res.json();
                if (data.success && data.data) {
                    const ping = data.data;
                    const elVal = document.getElementById('pingValueNum');
                    const elLbl = document.getElementById('pingStatusLabel');
                    if (elVal) elVal.textContent = ping.ms;
                    if (elLbl) {
                        elLbl.textContent = ping.status;
                        elLbl.className = `ping-status-${ping.quality || 'good'}`;
                    }
                    showToast(`Hasil Ping: ${ping.ms} ms (${ping.status})`, 'success');
                }
            } catch (err) {
                showToast('Gagal menguji ping', 'info');
            } finally {
                setTimeout(() => btn?.classList.remove('spinning'), 600);
            }
        });

        // AdGuard Card Switch
        window.handleToggleAdgFromCard = async function(enabled) {
            try {
                const res = await fetch('api.php?action=toggle_adguard_protection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enabled: enabled })
                });
                const data = await res.json();
                if (data.success) showToast(data.message, 'success');
            } catch (err) {
                showToast('Gagal mengubah proteksi AdGuard', 'error');
            }
        };
    </script>
</body>
</html>
