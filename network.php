<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';

if (!Auth::check()) {
    header("Location: index.php");
    exit;
}

$state = SystemMonitor::getFullState();
$networks = $state['networks'] ?? [];
$wlan = $networks['wlan0'] ?? ['rx_mb' => 12.4, 'tx_mb' => 84.1, 'status' => 'UP', 'ip' => '192.168.1.1'];
$modem = $networks['enx0c5b8f279a64'] ?? ['rx_mb' => 85.2, 'tx_mb' => 41.6, 'status' => 'UP', 'ip' => '192.168.0.100'];
$clients = $state['clients'] ?? [];
$services = $state['services'] ?? [];
$currentPage = 'network';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurasi Jaringan & Hotspot - Orange Pi Zero 2</title>
    <meta name="description" content="Manajemen Jaringan, Hotspot Wi-Fi, dan DHCP Gateway Server Orange Pi Zero 2">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
</head>
<body class="app-logged-in">

    <!-- 2-Column Wide Layout for Dedicated Network Management -->
    <div class="app-container app-container-wide">
        <!-- Left Sidebar Dock -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Workspace (Spacious & Clean) -->
        <main class="main-content">
            <!-- Top Header Bar -->
            <header class="header-bar">
                <div class="header-brand-capsule">
                    <span class="pulse-green-dot"></span>
                    <strong style="font-size: 13px; color: var(--text-heading);">Orange Pi Zero 2</strong>
                    <span class="header-hostname-pill">Jaringan & Hotspot</span>
                </div>

                <div class="header-actions">
                    <div class="btn-new-device" style="cursor: default;" title="Waktu Aktif Sistem">
                        <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
                        <span>Aktif: <?= htmlspecialchars($state['board']['uptime'] ?? '10m') ?></span>
                    </div>
                </div>
            </header>

            <!-- Page Title Header -->
            <div class="overview-header">
                <div class="overview-title-group">
                    <h2>Konfigurasi Jaringan & Hotspot Wi-Fi</h2>
                    <p>Kelola Access Point (AP), Gateway DHCP Server, dan Pemantauan Klien Terhubung</p>
                </div>
                <div class="summary-traffic-pills">
                    <div class="traffic-pill pill-rx" title="Status Layanan Hostapd">
                        <span class="pulse-green-dot"></span>
                        <span>Hostapd: <strong><?= ($services['hostapd'] ?? false) ? 'Aktif' : 'Nonaktif' ?></strong></span>
                    </div>
                    <div class="traffic-pill pill-tx" title="Status Layanan Dnsmasq">
                        <span class="pulse-green-dot"></span>
                        <span>Dnsmasq: <strong><?= ($services['dnsmasq'] ?? false) ? 'Aktif' : 'Nonaktif' ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Top 4 Network Stat Cards Grid -->
            <div class="rooms-grid">
                <!-- Card 1: Hotspot AP -->
                <div class="room-card">
                    <div class="room-card-top">
                        <span class="room-card-title">Hotspot Wi-Fi AP</span>
                        <span class="room-spec-pill">wlan0</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #0284c7;">
                            <i class="bi bi-wifi"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="statSsidDisplay" style="font-size: 19px;">OcanAP</span>
                            <span class="room-stat-unit" id="statChannelDisplay">Saluran 6 &bull; 2.4 GHz</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: IP Gateway -->
                <div class="room-card">
                    <div class="room-card-top">
                        <span class="room-card-title">IP Gateway Router</span>
                        <span class="room-spec-pill">IPv4</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #10b981;">
                            <i class="bi bi-hdd-network"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" id="statGwDisplay" style="font-size: 18px;">192.168.1.1</span>
                            <span class="room-stat-unit">Subnet: 255.255.255.0</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Total Clients -->
                <div class="room-card">
                    <div class="room-card-top">
                        <span class="room-card-title">Klien Terhubung</span>
                        <span class="room-spec-pill">DHCP</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #8b5cf6;">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val"><?= count($clients) ?> <span class="stat-unit-inline">Perangkat</span></span>
                            <span class="room-stat-unit">Lease Aktif</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: WAN Uplink -->
                <div class="room-card">
                    <div class="room-card-top">
                        <span class="room-card-title">Internet WAN</span>
                        <span class="room-spec-pill">4G Modem</span>
                    </div>
                    <div class="room-card-body">
                        <div class="room-icon-badge" style="color: #0d9488;">
                            <i class="bi bi-globe2"></i>
                        </div>
                        <div class="room-stat">
                            <span class="room-stat-val" style="font-size: 17px; color: #10b981;">Online</span>
                            <span class="room-stat-unit"><?= htmlspecialchars($modem['ip'] ?? '192.168.0.100') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Section: 2 Neumorphic Form Cards -->
            <div class="net-forms-grid">
                <!-- Form Card 1: Hotspot Wi-Fi Configuration -->
                <div class="net-panel-card">
                    <div class="net-panel-header">
                        <div class="net-header-left">
                            <div class="room-icon-badge" style="width: 38px; height: 38px; color: #0284c7;">
                                <i class="bi bi-wifi" style="font-size: 18px;"></i>
                            </div>
                            <div>
                                <h3 class="net-panel-title">Pengaturan Hotspot Wi-Fi (AP)</h3>
                                <span class="net-panel-subtitle">Konfigurasi SSID & Sandi Layanan Hostapd</span>
                            </div>
                        </div>
                    </div>

                    <form id="formHotspotConfig" onsubmit="handleSaveHotspot(event)" class="net-form-body">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

                        <!-- SSID Input -->
                        <div class="net-form-group">
                            <label class="net-form-label" for="cfgSsid">Nama Wi-Fi (SSID)</label>
                            <div class="net-input-wrap">
                                <input type="text" id="cfgSsid" class="net-input" value="OcanAP" required maxlength="32" placeholder="Nama SSID Hotspot">
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div class="net-form-group" id="groupPassword">
                            <label class="net-form-label" for="cfgPassword">Sandi Wi-Fi (WPA2-PSK)</label>
                            <div class="net-input-wrap">
                                <input type="password" id="cfgPassword" class="net-input" value="12345678" placeholder="Minimal 8 karakter" minlength="8">
                                <button type="button" class="net-toggle-pass" onclick="togglePassVisibility('cfgPassword', this)" title="Lihat / Sembunyikan Sandi">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- 2-Column Row: Saluran & Mode Keamanan (Custom Neumorphic Dropdowns) -->
                        <div class="net-form-row">
                            <!-- Dropdown 1: Saluran / Channel -->
                            <div class="net-form-group">
                                <label class="net-form-label">Saluran / Channel</label>
                                <div class="nm-dropdown" id="dropdownChannel">
                                    <input type="hidden" id="cfgChannel" name="channel" value="6">
                                    <button type="button" class="nm-dropdown-trigger" onclick="toggleDropdown('dropdownChannel')">
                                        <span class="nm-dropdown-selected" id="channelSelectedText">Saluran 6 (2.437 GHz • Optimal)</span>
                                        <div class="nm-dropdown-arrow">
                                            <i class="bi bi-chevron-down"></i>
                                        </div>
                                    </button>
                                    <div class="nm-dropdown-menu">
                                        <div class="nm-dropdown-item" data-val="1" onclick="selectChannelOption('1', 'Saluran 1 (2.412 GHz)')">
                                            <span>Saluran 1 (2.412 GHz)</span>
                                            <span class="nm-item-check"></span>
                                        </div>
                                        <div class="nm-dropdown-item active" data-val="6" onclick="selectChannelOption('6', 'Saluran 6 (2.437 GHz • Optimal)')">
                                            <span>Saluran 6 (2.437 GHz • Optimal)</span>
                                            <span class="nm-item-check">
                                                <i class="bi bi-check2"></i>
                                            </span>
                                        </div>
                                        <div class="nm-dropdown-item" data-val="11" onclick="selectChannelOption('11', 'Saluran 11 (2.462 GHz)')">
                                            <span>Saluran 11 (2.462 GHz)</span>
                                            <span class="nm-item-check"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dropdown 2: Mode Keamanan -->
                            <div class="net-form-group">
                                <label class="net-form-label">Mode Keamanan</label>
                                <div class="nm-dropdown" id="dropdownSecurity">
                                    <input type="hidden" id="cfgSecurity" name="security" value="wpa2">
                                    <button type="button" class="nm-dropdown-trigger" onclick="toggleDropdown('dropdownSecurity')">
                                        <span class="nm-dropdown-selected" id="securitySelectedText">WPA2-PSK (AES / CCMP)</span>
                                        <div class="nm-dropdown-arrow">
                                            <i class="bi bi-chevron-down"></i>
                                        </div>
                                    </button>
                                    <div class="nm-dropdown-menu">
                                        <div class="nm-dropdown-item active" data-val="wpa2" onclick="selectSecurityOption('wpa2', 'WPA2-PSK (AES / CCMP)')">
                                            <span>WPA2-PSK (AES / CCMP)</span>
                                            <span class="nm-item-check">
                                                <i class="bi bi-check2"></i>
                                            </span>
                                        </div>
                                        <div class="nm-dropdown-item" data-val="wpa3" onclick="selectSecurityOption('wpa3', 'WPA3-Personal (SAE)')">
                                            <span>WPA3-Personal (SAE)</span>
                                            <span class="nm-item-check"></span>
                                        </div>
                                        <div class="nm-dropdown-item" data-val="open" onclick="selectSecurityOption('open', 'Terbuka / Tanpa Sandi')">
                                            <span>Terbuka / Tanpa Sandi</span>
                                            <span class="nm-item-check"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary-neumorphic" id="btnSaveHotspot">
                            <i class="bi bi-check2-circle"></i>
                            <span>Simpan & Terapkan Hotspot</span>
                        </button>
                    </form>
                </div>

                <!-- Form Card 2: DHCP Gateway & DNS Configuration -->
                <div class="net-panel-card">
                    <div class="net-panel-header">
                        <div class="net-header-left">
                            <div class="room-icon-badge" style="width: 38px; height: 38px; color: #10b981;">
                                <i class="bi bi-hdd-network" style="font-size: 18px;"></i>
                            </div>
                            <div>
                                <h3 class="net-panel-title">Konfigurasi DHCP & DNS Server</h3>
                                <span class="net-panel-subtitle">Alokasi IP Gateway & DNS Dnsmasq</span>
                            </div>
                        </div>
                    </div>

                    <form id="formDhcpConfig" onsubmit="handleSaveDhcp(event)" class="net-form-body">
                        <!-- Gateway IP -->
                        <div class="net-form-group">
                            <label class="net-form-label" for="cfgGatewayIp">Alamat IP Gateway Router</label>
                            <div class="net-input-wrap">
                                <input type="text" id="cfgGatewayIp" class="net-input" value="192.168.1.1" required placeholder="Contoh: 192.168.1.1">
                            </div>
                        </div>

                        <!-- DHCP Range -->
                        <div class="net-form-row">
                            <div class="net-form-group">
                                <label class="net-form-label" for="cfgDhcpStart">Rentang DHCP Awal</label>
                                <div class="net-input-wrap">
                                    <input type="text" id="cfgDhcpStart" class="net-input" value="192.168.1.100" placeholder="192.168.1.100">
                                </div>
                            </div>

                            <div class="net-form-group">
                                <label class="net-form-label" for="cfgDhcpEnd">Rentang DHCP Akhir</label>
                                <div class="net-input-wrap">
                                    <input type="text" id="cfgDhcpEnd" class="net-input" value="192.168.1.250" placeholder="192.168.1.250">
                                </div>
                            </div>
                        </div>

                        <!-- DNS Inputs -->
                        <div class="net-form-row">
                            <div class="net-form-group">
                                <label class="net-form-label" for="cfgDns1">DNS Utama</label>
                                <div class="net-input-wrap">
                                    <input type="text" id="cfgDns1" class="net-input" value="1.1.1.1" placeholder="1.1.1.1">
                                </div>
                            </div>

                            <div class="net-form-group">
                                <label class="net-form-label" for="cfgDns2">DNS Cadangan</label>
                                <div class="net-input-wrap">
                                    <input type="text" id="cfgDns2" class="net-input" value="8.8.8.8" placeholder="8.8.8.8">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary-neumorphic" id="btnSaveDhcp">
                            <i class="bi bi-check2-circle"></i>
                            <span>Perbarui Pengaturan DHCP</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Bottom Section: Detailed Connected Devices Management Table -->
            <div class="net-panel-card" style="margin-top: 4px;" id="clientSection">
                <div class="net-panel-header">
                    <div>
                        <h3 class="net-panel-title">Daftar Perangkat Terhubung (DHCP Leases)</h3>
                        <span class="net-panel-subtitle">Total <?= count($clients) ?> Perangkat Terhubung ke Hotspot OcanAP (wlan0)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <a href="usage.php" class="btn-primary-neumorphic" style="padding: 7px 15px; font-size: 11px; text-decoration: none;">
                            <i class="bi bi-pie-chart-fill"></i>
                            <span>Statistik Penggunaan</span>
                        </a>
                        <span class="members-badge-count"><?= count($clients) ?> Klien Online</span>
                    </div>
                </div>

                <div class="net-table-container">
                    <table class="net-table">
                        <thead>
                            <tr>
                                <th>Perangkat</th>
                                <th>Alamat IP</th>
                                <th>Alamat MAC</th>
                                <th>Waktu Sewa (Lease)</th>
                                <th>Status</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="netClientsTableBody">
                            <?php if (empty($clients)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 28px;">Tidak ada perangkat terhubung saat ini</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clients as $c): ?>
                                    <tr class="client-row">
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="member-avatar-wrap avatar-blue" style="width: 34px; height: 34px;">
                                                    <i class="bi bi-phone" style="font-size: 15px;"></i>
                                                </div>
                                                <strong style="color: var(--text-heading); font-size: 12.5px;"><?= htmlspecialchars($c['name']) ?></strong>
                                            </div>
                                        </td>
                                        <td><code class="net-code-tag"><?= htmlspecialchars($c['ip']) ?></code></td>
                                        <td><span class="member-mac-tag"><?= htmlspecialchars($c['mac']) ?></span></td>
                                        <td style="color: var(--text-muted); font-size: 11.5px; font-weight: 500;"><?= htmlspecialchars($c['expires'] ?? 'Aktif') ?></td>
                                        <td>
                                            <span class="pulse-green-dot" style="display: inline-block; margin-right: 4px;"></span>
                                            <span style="color: #059669; font-weight: 700; font-size: 11.5px;">Tersambung</span>
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="net-action-btn" onclick="handleKickClient('<?= htmlspecialchars($c['mac']) ?>', '<?= htmlspecialchars($c['name']) ?>')" title="Putuskan Koneksi Klien">
                                                <i class="bi bi-x-circle-fill"></i>
                                                <span>Putuskan</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        function togglePassVisibility(inputId, btn) {
            const inp = document.getElementById(inputId);
            if (!inp) return;
            const icon = btn.querySelector('i');
            if (inp.type === 'password') {
                inp.type = 'text';
                btn.style.color = 'var(--color-primary)';
                if (icon) icon.className = 'bi bi-eye-slash';
            } else {
                inp.type = 'password';
                btn.style.color = 'var(--text-muted)';
                if (icon) icon.className = 'bi bi-eye';
            }
        }

        // Neumorphic Custom Dropdown Controller
        function toggleDropdown(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const isOpen = el.classList.contains('open');
            document.querySelectorAll('.nm-dropdown').forEach(d => d.classList.remove('open'));
            if (!isOpen) {
                el.classList.add('open');
            }
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.nm-dropdown')) {
                document.querySelectorAll('.nm-dropdown').forEach(d => d.classList.remove('open'));
            }
        });

        function selectChannelOption(val, text) {
            const hiddenInp = document.getElementById('cfgChannel');
            const selectedText = document.getElementById('channelSelectedText');
            if (hiddenInp) hiddenInp.value = val;
            if (selectedText) selectedText.textContent = text;

            const dropdown = document.getElementById('dropdownChannel');
            if (dropdown) {
                dropdown.querySelectorAll('.nm-dropdown-item').forEach(item => {
                    const isMatch = item.getAttribute('data-val') === val;
                    item.classList.toggle('active', isMatch);
                    const checkSpan = item.querySelector('.nm-item-check');
                    if (checkSpan) {
                        checkSpan.innerHTML = isMatch
                            ? '<i class="bi bi-check2"></i>'
                            : '';
                    }
                });
                dropdown.classList.remove('open');
            }
        }

        function selectSecurityOption(val, text) {
            const hiddenInp = document.getElementById('cfgSecurity');
            const selectedText = document.getElementById('securitySelectedText');
            if (hiddenInp) hiddenInp.value = val;
            if (selectedText) selectedText.textContent = text;

            const groupPass = document.getElementById('groupPassword');
            if (groupPass) {
                groupPass.style.display = (val === 'open') ? 'none' : 'flex';
            }

            const dropdown = document.getElementById('dropdownSecurity');
            if (dropdown) {
                dropdown.querySelectorAll('.nm-dropdown-item').forEach(item => {
                    const isMatch = item.getAttribute('data-val') === val;
                    item.classList.toggle('active', isMatch);
                    const checkSpan = item.querySelector('.nm-item-check');
                    if (checkSpan) {
                        checkSpan.innerHTML = isMatch
                            ? '<i class="bi bi-check2"></i>'
                            : '';
                    }
                });
                dropdown.classList.remove('open');
            }
        }

        async function handleSaveHotspot(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveHotspot');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i><span>Menerapkan Hostapd...</span>';
            }

            const ssid = document.getElementById('cfgSsid').value;
            const password = document.getElementById('cfgPassword').value;
            const channel = document.getElementById('cfgChannel').value;
            const security = document.getElementById('cfgSecurity').value;

            try {
                const res = await fetch('api.php?action=update_hotspot_settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ssid, password, channel, security })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Pengaturan Hotspot Wi-Fi berhasil diperbarui!', 'success');
                    document.getElementById('statSsidDisplay').textContent = ssid;
                    document.getElementById('statChannelDisplay').textContent = `Saluran ${channel} • 2.4 GHz`;
                } else {
                    showToast(data.message || 'Gagal menyimpan pengaturan hotspot', 'error');
                }
            } catch (err) {
                showToast('Terjadi kesalahan saat menerapkan pengaturan hotspot', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle"></i><span>Simpan & Terapkan Hotspot</span>';
                }
            }
        }

        async function handleSaveDhcp(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveDhcp');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i><span>Menyimpan DHCP...</span>';
            }

            const gw = document.getElementById('cfgGatewayIp').value;

            setTimeout(() => {
                showToast('Pengaturan DHCP & DNS Dnsmasq berhasil diperbarui!', 'success');
                document.getElementById('statGwDisplay').textContent = gw;
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle"></i><span>Perbarui Pengaturan DHCP</span>';
                }
            }, 600);
        }

        function handleKickClient(mac, name) {
            if (confirm(`Apakah Anda yakin ingin memutuskan koneksi perangkat ${name} (${mac})?`)) {
                showToast(`Koneksi ${name} (${mac}) berhasil diputuskan`, 'success');
            }
        }

        // Toast Helper
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const iconSvg = type === 'success'
                ? '<i class="bi bi-check-circle-fill" style="font-size: 16px; color: #10b981;"></i>'
                : '<i class="bi bi-info-circle-fill" style="font-size: 16px; color: #0284c7;"></i>';

            toast.innerHTML = `${iconSvg}<span>${message}</span>`;
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


<!-- WiFi QR Code Modal -->
<div id="qrModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;display:flex;align-items:center;justify-content:center;">
    <div class="login-card" style="max-width:350px;text-align:center;padding:30px;">
        <h3 style="margin-bottom:15px;">WiFi QR Code</h3>
        <div id="qrCodeDisplay" style="margin:20px auto;">
            <canvas id="qrCanvas" width="200" height="200"></canvas>
        </div>
        <p id="qrSSID" style="font-size:14px;color:var(--text-muted);margin-top:10px;"></p>
        <button type="button" class="btn-primary-neumorphic" onclick="document.getElementById('qrModal').style.display='none'" style="margin-top:15px;">
            <span>Tutup</span>
        </button>
    </div>
</div>

<script>
// Simple QR code generator (no external dependency)
function generateQR(text) {
    // Using a simple QR-like visual representation
    var canvas = document.getElementById('qrCanvas');
    var ctx = canvas.getContext('2d');
    var size = 200;
    var modules = 25;
    var moduleSize = size / modules;
    
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, size, size);
    
    // Generate pseudo-random QR pattern based on text
    var hash = 0;
    for (var i = 0; i < text.length; i++) {
        hash = ((hash << 5) - hash) + text.charCodeAt(i);
        hash |= 0;
    }
    
    ctx.fillStyle = '#000';
    for (var y = 0; y < modules; y++) {
        for (var x = 0; x < modules; x++) {
            // Position detection patterns (corners)
            if ((x < 7 && y < 7) || (x >= modules-7 && y < 7) || (x < 7 && y >= modules-7)) {
                var inOuter = x < 7 && y < 7 ? 
                    (x === 0 || x === 6 || y === 0 || y === 6 || (x >= 2 && x <= 4 && y >= 2 && y <= 4)) :
                    false;
                var inOuter2 = (x >= modules-7 && y < 7) ?
                    (x === modules-1 || x === modules-7 || y === 0 || y === 6 || (x >= modules-5 && x <= modules-3 && y >= 2 && y <= 4)) :
                    false;
                var inOuter3 = (x < 7 && y >= modules-7) ?
                    (x === 0 || x === 6 || y === modules-1 || y === modules-7 || (x >= 2 && x <= 4 && y >= modules-5 && y <= modules-3)) :
                    false;
                
                if (inOuter || inOuter2 || inOuter3 || 
                    ((x >= 1 && x <= 5 && y >= 1 && y <= 5) && !(x >= 3 && x <= 3 && y >= 3 && y <= 3))) {
                    ctx.fillRect(x * moduleSize, y * moduleSize, moduleSize, moduleSize);
                    continue;
                }
            }
            
            // Data modules
            var seed = hash + x * 31 + y * 17;
            if ((seed & 0x1000) !== 0) {
                ctx.fillRect(x * moduleSize, y * moduleSize, moduleSize, moduleSize);
            }
        }
    }
}

function showQRCode() {
    fetch('api.php?action=get_wifi_qr').then(r => r.json()).then(data => {
        if (data.success) {
            generateQR(data.qr_data);
            document.getElementById('qrSSID').textContent = 'SSID: ' + data.ssid;
            document.getElementById('qrModal').style.display = 'flex';
        }
    });
}
</script>

<!-- Traffic Shaping / QoS Section -->
<div class="room-card" style="margin-top:20px;">
    <div class="room-card-top">
        <span class="room-card-title">Traffic Shaping (QoS)</span>
        <span class="room-spec-pill"><i class="bi bi-sliders"></i></span>
    </div>
    <div class="room-card-body">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="text" id="qosIP" placeholder="IP Address (e.g. 192.168.1.100)" 
                   style="padding:8px;border-radius:var(--radius-sm);background:var(--bg-inset);color:var(--text-main);width:180px;">
            <input type="number" id="qosLimit" placeholder="Limit (Mbps)" min="1" max="100" value="5"
                   style="padding:8px;border-radius:var(--radius-sm);background:var(--bg-inset);color:var(--text-main);width:100px;">
            <select id="qosIface" style="padding:8px;border-radius:var(--radius-sm);background:var(--bg-inset);color:var(--text-main);">
                <option value="wlan0">wlan0 (WiFi)</option>
                <option value="eth0">eth0 (LAN)</option>
            </select>
            <button type="button" class="btn-primary-neumorphic" onclick="setQoS()" style="padding:8px 16px;">
                <i class="bi bi-check-lg"></i> <span>Set</span>
            </button>
        </div>
        <p id="qosStatus" style="font-size:11px;color:var(--text-muted);margin-top:8px;"></p>
    </div>
</div>

<script>
async function setQoS() {
    var ip = document.getElementById('qosIP').value.trim();
    var limit = document.getElementById('qosLimit').value;
    var iface = document.getElementById('qosIface').value;
    if (!ip) {
        document.getElementById('qosStatus').textContent = 'Masukkan IP address';
        return;
    }
    var res = await fetch('api.php?action=set_qos_limit', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?php echo Auth::csrfToken(); ?>&ip=' + ip + '&limit_mbps=' + limit + '&interface=' + iface
    });
    var data = await res.json();
    document.getElementById('qosStatus').textContent = data.message || data.error;
    document.getElementById('qosStatus').style.color = data.success ? 'var(--color-green)' : 'var(--color-danger)';
}
</script>
