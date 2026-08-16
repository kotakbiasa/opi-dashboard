<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';
require_once __DIR__ . '/includes/ServicesManager.php';

if (!Auth::check()) {
    header("Location: index.php");
    exit;
}

$state = SystemMonitor::getFullState();
$board = $state['board'] ?? [];
$srvData = ServicesManager::getServicesList();
$services = $srvData['services'];
$summary = $srvData['summary'];
$currentPage = 'services';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Sistem - Orange Pi Zero 2</title>
    <meta name="description" content="Manajer dan Kontrol Layanan Sistem Linux (systemd) Orange Pi Gateway">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
    <style>
        .service-tactile-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--nm-raised-sm);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            transition: var(--transition-fast);
        }

        .service-tactile-card:hover {
            box-shadow: var(--nm-raised);
            transform: translateY(-2px);
        }

        .service-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 800;
            box-shadow: var(--nm-inset-sm);
            background: var(--bg-card);
        }

        .service-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: var(--radius-sm);
            font-size: 10.5px;
            font-family: monospace;
            background: rgba(2, 132, 199, 0.04);
            border: 1px solid rgba(182, 198, 220, 0.35);
            color: var(--text-muted);
        }

        .service-meta-chip strong {
            color: var(--text-heading);
        }

        .service-btn-action {
            padding: 6px 12px;
            font-size: 11.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: var(--transition-fast);
            border-radius: var(--radius-pill);
        }

        .code-console-area {
            width: 100%;
            height: 480px;
            font-family: 'JetBrains Mono', 'SFMono-Regular', Consolas, Menlo, monospace;
            font-size: 12.5px;
            line-height: 1.55;
            background: #0f172a;
            color: #f8fafc;
            border: 1.5px solid #334155;
            border-radius: var(--radius-md);
            padding: 16px;
            outline: none;
            resize: vertical;
            white-space: pre-wrap;
            word-break: break-all;
            overflow-y: auto;
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
                    <span class="header-hostname-pill">Layanan Sistem (systemd)</span>
                </div>

                <div class="header-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn-new-device" onclick="restartNetworkStack()" title="Muat Ulang Layanan Jaringan (Hostapd & Dnsmasq)" style="padding: 7px 14px; color: #0284c7;">
                        <i class="bi bi-arrow-repeat" style="color: #0284c7;"></i>
                        <span>Restart Stack Wi-Fi</span>
                    </button>

                    <button type="button" class="btn-primary-neumorphic" onclick="reloadServicesList()" title="Segarkan Status Layanan" style="padding: 8px 18px; font-size: 12px;">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Segarkan Data</span>
                    </button>

                    <div class="btn-new-device" style="cursor: default; padding: 7px 14px;" title="Waktu Aktif Sistem">
                        <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
                        <span>Aktif: <?= htmlspecialchars($board['uptime'] ?? '10m') ?></span>
                    </div>
                </div>
            </header>

            <!-- Hero HUD: Services Overview Summary -->
            <div class="cellular-hud-hero">
                <div class="hud-operator-identity">
                    <div class="hud-operator-logo-badge" style="color: #8b5cf6;">
                        <i class="bi bi-grid-fill"></i>
                    </div>
                    <div>
                        <h1 class="hud-carrier-name">Pusat Layanan Sistem <span style="font-size: 15px; font-weight: 700; color: #10b981;">Linux Daemons</span></h1>
                        <div class="hud-tags-row">
                            <span class="hud-tech-pill" style="color: #059669;"><i class="bi bi-check-circle-fill"></i> Aktif: <strong id="hudRunningCount"><?= $summary['running'] ?></strong> Layanan</span>
                            <span class="hud-freq-pill" style="color: #64748b;"><i class="bi bi-dash-circle-fill"></i> Nonaktif: <strong id="hudInactiveCount"><?= $summary['inactive'] ?></strong></span>
                            <span class="hud-plmn-pill" style="color: #ef4444;"><i class="bi bi-x-circle-fill"></i> Gagal: <strong id="hudFailedCount"><?= $summary['failed'] ?></strong></span>
                            <span class="room-spec-pill" style="font-size: 11px; font-weight: 800;">Total Terkelola: <?= $summary['total'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Right Quick Info -->
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div class="hud-signal-capsule" style="padding: 10px 16px;">
                        <span class="hud-signal-dbm" style="color: #10b981; font-size: 13px;">systemd engine</span>
                        <span class="hud-signal-bar-txt" style="font-size: 11px;">Status: Optimal & Siap</span>
                    </div>
                </div>
            </div>

            <!-- Controls: Category Filter, Search & Sorting -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-top: 16px;">
                <!-- Segmented Category Switcher -->
                <div class="nm-segmented-switch">
                    <button type="button" class="nm-seg-btn active" id="btnFilterAll" onclick="setCategoryFilter('all')" style="padding: 6px 14px;">
                        <i class="bi bi-layers-fill"></i>
                        <span>Semua (<?= $summary['total'] ?>)</span>
                    </button>
                    <button type="button" class="nm-seg-btn" id="btnFilterNetwork" onclick="setCategoryFilter('network')" style="padding: 6px 14px;">
                        <i class="bi bi-wifi" style="color: #0284c7;"></i>
                        <span>Jaringan & Wi-Fi</span>
                    </button>
                    <button type="button" class="nm-seg-btn" id="btnFilterSecurity" onclick="setCategoryFilter('security')" style="padding: 6px 14px;">
                        <i class="bi bi-shield-check" style="color: #10b981;"></i>
                        <span>Keamanan & DNS</span>
                    </button>
                    <button type="button" class="nm-seg-btn" id="btnFilterApps" onclick="setCategoryFilter('apps')" style="padding: 6px 14px;">
                        <i class="bi bi-robot" style="color: #38bdf8;"></i>
                        <span>Aplikasi & Bot</span>
                    </button>
                    <button type="button" class="nm-seg-btn" id="btnFilterWeb" onclick="setCategoryFilter('web')" style="padding: 6px 14px;">
                        <i class="bi bi-globe2" style="color: #8b5cf6;"></i>
                        <span>Web & Sistem</span>
                    </button>
                </div>

                <!-- Search & Sort Controls -->
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <input type="text" id="serviceSearchInput" class="btn-new-device" placeholder="Cari nama layanan..." onkeyup="renderServicesGrid()" style="text-align: left; padding: 7px 14px; font-size: 11.5px; width: 170px;">

                    <select id="serviceSortSelect" onchange="renderServicesGrid()" class="btn-new-device" style="padding: 7px 12px; font-size: 11.5px; font-weight: 700; cursor: pointer;">
                        <option value="name">Nama Layanan (A-Z)</option>
                        <option value="status">Status (Aktif Dulu)</option>
                        <option value="memory">Konsumsi RAM</option>
                    </select>
                </div>
            </div>

            <!-- Main Services Grid -->
            <div id="servicesContainerGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 14px; margin-top: 16px;">
                <!-- Rendered dynamically by JS -->
            </div>
        </main>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: SERVICE LOGS JOURNAL VIEWER -->
    <!-- ========================================================================= -->
    <div id="modalServiceLogs" class="reboot-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
        <div class="hud-card-panel" style="max-width: 860px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="file-badge-icon" style="color: #38bdf8; width: 40px; height: 40px; font-size: 18px;">
                        <i class="bi bi-terminal"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 15px; color: var(--text-heading); font-weight: 800;" id="logModalTitle">Log Jurnal Layanan</h3>
                        <span style="font-size: 11px; color: var(--text-muted); font-family: monospace;" id="logModalUnitName">service.service</span>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <select id="logLinesSelect" onchange="refreshServiceLogs()" class="btn-new-device" style="padding: 4px 10px; font-size: 11px; font-weight: 700; cursor: pointer;">
                        <option value="50">50 Baris</option>
                        <option value="100">100 Baris</option>
                        <option value="200">200 Baris</option>
                    </select>

                    <button type="button" class="btn-primary-neumorphic" onclick="refreshServiceLogs()" style="padding: 6px 12px; font-size: 11.5px;" title="Segarkan Log">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Muat Ulang</span>
                    </button>

                    <button type="button" class="btn-round-ctrl" onclick="closeServiceLogsModal()">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Terminal Output Area -->
            <pre id="logContentArea" class="code-console-area">Memuat log jurnal layanan...</pre>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; font-size: 11px; color: var(--text-muted);">
                <span>Sumber: <code>journalctl -u &lt;service&gt; --no-pager</code></span>
                <button type="button" class="btn-new-device" onclick="closeServiceLogsModal()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Services Controller JavaScript -->
    <script>
        let allServices = <?= json_encode($services) ?>;
        let activeCategory = 'all';
        let currentViewingServiceId = '';

        document.addEventListener('DOMContentLoaded', () => {
            renderServicesGrid();
        });

        async function reloadServicesList() {
            showToast('Memperbarui status seluruh layanan...', 'info');
            try {
                const res = await fetch('api.php?action=get_services_status');
                const json = await res.json();
                if (json.success && json.data) {
                    allServices = json.data.services || [];
                    
                    // Update HUD summary counters
                    const s = json.data.summary;
                    if (s) {
                        document.getElementById('hudRunningCount').textContent = s.running;
                        document.getElementById('hudInactiveCount').textContent = s.inactive;
                        document.getElementById('hudFailedCount').textContent = s.failed;
                    }

                    renderServicesGrid();
                    showToast('Status layanan berhasil diperbarui!', 'success');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        function setCategoryFilter(cat) {
            activeCategory = cat;
            document.querySelectorAll('.nm-seg-btn').forEach(b => {
                if (b.id && b.id.startsWith('btnFilter')) b.classList.remove('active');
            });
            const activeBtn = document.getElementById(`btnFilter${cat.charAt(0).toUpperCase() + cat.slice(1)}`);
            if (activeBtn) activeBtn.classList.add('active');
            renderServicesGrid();
        }

        function getFilteredServices() {
            const query = (document.getElementById('serviceSearchInput')?.value || '').toLowerCase().trim();
            let list = [...allServices];

            if (activeCategory !== 'all') {
                list = list.filter(s => s.category === activeCategory);
            }

            if (query !== '') {
                list = list.filter(s => s.name.toLowerCase().includes(query) || s.unit.toLowerCase().includes(query) || s.description.toLowerCase().includes(query));
            }

            const sortBy = document.getElementById('serviceSortSelect')?.value || 'name';
            list.sort((a, b) => {
                if (sortBy === 'name') return a.name.localeCompare(b.name);
                if (sortBy === 'status') {
                    if (a.is_running && !b.is_running) return -1;
                    if (!a.is_running && b.is_running) return 1;
                    return 0;
                }
                if (sortBy === 'memory') {
                    const ma = parseFloat(a.memory) || 0;
                    const mb = parseFloat(b.memory) || 0;
                    return mb - ma;
                }
                return 0;
            });

            return list;
        }

        function renderServicesGrid() {
            const grid = document.getElementById('servicesContainerGrid');
            if (!grid) return;

            const list = getFilteredServices();
            if (list.length === 0) {
                grid.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 48px; font-size: 13px;">
                        <i class="bi bi-search" style="font-size: 32px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                        Tidak ada layanan yang sesuai dengan filter atau kata kunci pencarian.
                    </div>
                `;
                return;
            }

            let html = '';
            list.forEach(s => {
                const isRunning = s.is_running;
                const isFailed = s.is_failed;
                const statusColor = isRunning ? '#10b981' : (isFailed ? '#ef4444' : '#64748b');
                const statusText = isRunning ? 'Berjalan' : (isFailed ? 'Gagal' : 'Mati');
                const bootColor = s.boot_enabled ? '#10b981' : '#64748b';
                const bootText = s.boot_enabled ? 'Boot: Aktif' : 'Boot: Nonaktif';

                html += `
                    <div class="service-tactile-card">
                        <!-- Top Header -->
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="file-badge-icon" style="color: ${s.color}; width: 38px; height: 38px; font-size: 18px;">
                                        <i class="bi ${s.icon}"></i>
                                    </div>
                                    <div>
                                        <strong style="font-size: 13px; color: var(--text-heading); display: block; line-height: 1.3;">${escapeHtml(s.name)}</strong>
                                        <span style="font-size: 10.5px; color: var(--text-muted); font-family: monospace;">${escapeHtml(s.unit)}</span>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                    <span class="service-status-pill" style="color: ${statusColor};">
                                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:${statusColor}; box-shadow:0 0 6px ${statusColor};"></span>
                                        <span>${statusText}</span>
                                    </span>
                                    <span style="font-size: 9.5px; color: ${bootColor}; font-weight: 700;">${bootText}</span>
                                </div>
                            </div>
                            <p style="font-size: 11px; color: var(--text-muted); line-height: 1.4; margin: 0;">${escapeHtml(s.description)}</p>
                        </div>

                        <!-- Meta Chips Info -->
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span class="service-meta-chip">PID: <strong>${s.pid}</strong></span>
                            <span class="service-meta-chip">RAM: <strong>${s.memory}</strong></span>
                            <span class="service-meta-chip">Uptime: <strong>${s.uptime}</strong></span>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px dashed rgba(182, 198, 220, 0.45); padding-top: 12px;">
                            <div style="display: flex; gap: 6px;">
                                ${isRunning ? `
                                    <button type="button" class="btn-new-device service-btn-action" onclick="executeServiceControl('${s.id}', 'stop')" title="Hentikan Layanan" style="color: #ef4444;">
                                        <i class="bi bi-stop-circle-fill"></i>
                                        <span>Stop</span>
                                    </button>
                                ` : `
                                    <button type="button" class="btn-primary-neumorphic service-btn-action" onclick="executeServiceControl('${s.id}', 'start')" title="Jalankan Layanan" style="color: #10b981;">
                                        <i class="bi bi-play-circle-fill"></i>
                                        <span>Start</span>
                                    </button>
                                `}

                                <button type="button" class="btn-new-device service-btn-action" onclick="executeServiceControl('${s.id}', 'restart')" title="Muat Ulang Layanan (Restart)">
                                    <i class="bi bi-arrow-repeat" style="color: #0284c7;"></i>
                                    <span>Restart</span>
                                </button>
                            </div>

                            <div style="display: flex; gap: 6px;">
                                <button type="button" class="btn-new-device service-btn-action" onclick="openServiceLogsModal('${s.id}')" title="Lihat Log Jurnal">
                                    <i class="bi bi-journal-text" style="color: #8b5cf6;"></i>
                                    <span>Log</span>
                                </button>

                                <button type="button" class="btn-new-device service-btn-action" onclick="executeServiceControl('${s.id}', '${s.boot_enabled ? 'disable' : 'enable'}')" title="${s.boot_enabled ? 'Matikan saat Boot' : 'Aktifkan saat Boot'}" style="color: ${s.boot_enabled ? '#f59e0b' : '#64748b'};">
                                    <i class="bi ${s.boot_enabled ? 'bi-toggle-on' : 'bi-toggle-off'}" style="font-size: 13px;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            grid.innerHTML = html;
        }

        async function executeServiceControl(serviceId, action) {
            showToast(`Menjalankan aksi '${action}' pada layanan...`, 'info');
            try {
                const res = await fetch('api.php?action=control_service', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service: serviceId,
                        service_action: action
                    })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    // Update local service list
                    const target = allServices.find(s => s.id === serviceId);
                    if (target) {
                        target.is_running = json.is_running;
                        target.status = json.status;
                        target.boot_enabled = json.boot_enabled;
                    }
                    renderServicesGrid();
                } else {
                    showToast(json.error || 'Aksi gagal dieksekusi', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function restartNetworkStack() {
            if (!confirm('Muat ulang stack jaringan Wi-Fi (Hostapd & Dnsmasq)? Klien yang terhubung mungkin akan terputus sesaat.')) return;
            showToast('Memuat ulang stack Wi-Fi Hotspot...', 'info');
            await executeServiceControl('hostapd', 'restart');
            await executeServiceControl('dnsmasq', 'restart');
        }

        // --- Log Viewer Modal Handlers ---
        async function openServiceLogsModal(serviceId) {
            currentViewingServiceId = serviceId;
            const target = allServices.find(s => s.id === serviceId);
            if (!target) return;

            document.getElementById('logModalTitle').textContent = `Log Jurnal: ${target.name}`;
            document.getElementById('logModalUnitName').textContent = target.unit;
            document.getElementById('modalServiceLogs').style.display = 'flex';

            await refreshServiceLogs();
        }

        function closeServiceLogsModal() {
            document.getElementById('modalServiceLogs').style.display = 'none';
        }

        async function refreshServiceLogs() {
            if (!currentViewingServiceId) return;
            const lines = document.getElementById('logLinesSelect')?.value || 50;
            const output = document.getElementById('logContentArea');
            if (output) output.textContent = 'Memuat log terbaru dari journalctl...';

            try {
                const res = await fetch(`api.php?action=get_service_logs&service=${encodeURIComponent(currentViewingServiceId)}&lines=${lines}`);
                const json = await res.json();
                if (json.success && output) {
                    output.textContent = json.logs;
                    output.scrollTop = output.scrollHeight;
                } else if (output) {
                    output.textContent = json.error || 'Gagal membaca log.';
                }
            } catch (e) {
                if (output) output.textContent = 'Koneksi ke server terputus.';
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
            }, 3000);
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>
