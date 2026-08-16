<?php
$board = $state['board'] ?? ['name' => 'Orange Pi Zero 2', 'soc' => 'Allwinner H616', 'uptime' => '10m'];
$cpu = $state['cpu'] ?? ['usage' => 20, 'temp' => 54, 'freq_mhz' => 1512];
$ram = $state['ram'] ?? ['used_mb' => 530, 'total_mb' => 970, 'percent' => 54.6];
$storage = $state['storage'] ?? ['used_gb' => 11.7, 'total_gb' => 57.9, 'percent' => 20.2];
$clients = $state['clients'] ?? [];
$leds = $state['leds'] ?? [];
$greenLed = $leds['green_power'] ?? ['status' => true];
?>

<div class="mobile-tab-view active" id="viewCockpit">
    <!-- 1. Board Profile Card -->
    <div class="m-profile-card">
        <div class="m-profile-avatar-wrap">
            <img src="assets/avatar.svg" alt="Orange Pi Zero 2" class="m-profile-avatar-img">
        </div>
        <div class="m-profile-meta">
            <h3>Orange Pi Zero 2</h3>
            <p>Allwinner H616 &bull; Armbian</p>
        </div>
        <button type="button" class="btn-m-edit" id="btnQuickReboot">
            <span class="uptime-live-dot"></span>
            <span id="cockpitUptimeText"><?= htmlspecialchars($board['uptime']) ?></span>
        </button>
    </div>

    <!-- 2. 3 Quick Metrics Row -->
    <div class="m-quick-stats-row">
        <!-- CPU Pill -->
        <div class="m-quick-stat-pill">
            <span class="stat-pill-dot dot-red"></span>
            <span class="stat-pill-val" id="cockpitCpuPill"><?= (int)round($cpu['usage']) ?>%</span>
            <span class="stat-pill-label">CPU Load</span>
        </div>

        <!-- RAM Pill -->
        <div class="m-quick-stat-pill">
            <span class="stat-pill-dot dot-green"></span>
            <span class="stat-pill-val" id="cockpitRamPill"><?= (int)round($ram['percent']) ?>%</span>
            <span class="stat-pill-label">RAM Used</span>
        </div>

        <!-- SD Storage Pill -->
        <div class="m-quick-stat-pill">
            <span class="stat-pill-dot dot-blue"></span>
            <span class="stat-pill-val" id="cockpitStoragePill"><?= (int)round($storage['percent']) ?>%</span>
            <span class="stat-pill-label">SD Card</span>
        </div>
    </div>

    <!-- 3. Smart Alerts / SoC Health Card (Area Chart + Thermal Donut) -->
    <div class="m-card m-health-card">
        <div class="m-card-header">
            <h4>SoC Health & Thermals</h4>
            <div class="m-health-sublabels">
                <span>Core: <strong><?= (int)$cpu['freq_mhz'] ?> MHz</strong></span>
                <span>Status: <strong id="cockpitThermalStatusText"><?= ($cpu['temp'] < 60) ? 'Optimal' : 'Warm' ?></strong></span>
            </div>
        </div>

        <div class="m-health-body">
            <!-- Left: Wave / Area Chart -->
            <div class="m-health-chart-wrap">
                <svg viewBox="0 0 140 60" class="m-area-chart-svg">
                    <defs>
                        <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.45"/>
                            <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0,45 Q20,20 40,35 T80,15 T110,30 T140,20 L140,60 L0,60 Z" fill="url(#areaGrad)"/>
                    <path d="M0,45 Q20,20 40,35 T80,15 T110,30 T140,20" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
            </div>

            <!-- Right: Glowing Thermal Donut Ring -->
            <div class="m-thermal-donut-wrap">
                <div class="m-donut-outer" id="cockpitThermalRing">
                    <svg class="m-donut-svg" viewBox="0 0 72 72">
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#e2e8f0" stroke-width="6"/>
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#3b82f6" stroke-width="6"
                                stroke-dasharray="175" stroke-dashoffset="65" stroke-linecap="round" id="cockpitThermalArc"/>
                    </svg>
                    <div class="m-donut-center-text">
                        <span class="m-donut-temp-num" id="cockpitTempNum"><?= (int)$cpu['temp'] ?>°</span>
                        <span class="m-donut-temp-unit">SoC</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Quick Actions / Onboard LED Hardware Switch -->
    <div class="m-card m-action-bar-card">
        <div class="m-action-left">
            <div class="m-action-icon-badge">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18h6"></path>
                    <path d="M10 22h4"></path>
                    <path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"></path>
                </svg>
            </div>
            <div class="m-action-text">
                <h5>Onboard Green LED</h5>
                <p id="cockpitLedLabel">Physical Board Indicator (<?= ($greenLed['status'] ?? true) ? 'ON' : 'OFF' ?>)</p>
            </div>
        </div>

        <label class="nm-switch">
            <input type="checkbox" id="cockpitLedSwitch" <?= ($greenLed['status'] ?? true) ? 'checked' : '' ?>>
            <div class="switch-slider"></div>
        </label>
    </div>

    <!-- 5. 3 Circular Speedometer / Donut Gauges Row -->
    <div class="m-card m-gauges-section-card">
        <div class="m-card-header">
            <h4>Live Core Metrics</h4>
        </div>

        <div class="m-three-gauges-row">
            <!-- Gauge 1: CPU -->
            <div class="m-gauge-item">
                <div class="m-gauge-circle">
                    <svg viewBox="0 0 72 72">
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#e2e8f0" stroke-width="5.5"/>
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#10b981" stroke-width="5.5"
                                stroke-dasharray="175" stroke-dashoffset="95" stroke-linecap="round" id="gaugeCpuArc"/>
                    </svg>
                    <div class="m-gauge-center-num">
                        <span id="gaugeCpuVal"><?= (int)round($cpu['usage']) ?></span>
                    </div>
                </div>
                <span class="m-gauge-title">CPU Load (%)</span>
            </div>

            <!-- Gauge 2: RAM -->
            <div class="m-gauge-item">
                <div class="m-gauge-circle">
                    <svg viewBox="0 0 72 72">
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#e2e8f0" stroke-width="5.5"/>
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#f59e0b" stroke-width="5.5"
                                stroke-dasharray="175" stroke-dashoffset="80" stroke-linecap="round" id="gaugeRamArc"/>
                    </svg>
                    <div class="m-gauge-center-num">
                        <span id="gaugeRamVal"><?= (int)$ram['used_mb'] ?></span>
                    </div>
                </div>
                <span class="m-gauge-title">RAM (MB)</span>
            </div>

            <!-- Gauge 3: Wi-Fi Clients -->
            <div class="m-gauge-item">
                <div class="m-gauge-circle">
                    <svg viewBox="0 0 72 72">
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#e2e8f0" stroke-width="5.5"/>
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#3b82f6" stroke-width="5.5"
                                stroke-dasharray="175" stroke-dashoffset="60" stroke-linecap="round" id="gaugeClientsArc"/>
                    </svg>
                    <div class="m-gauge-center-num">
                        <span id="gaugeClientsVal"><?= count($clients) ?></span>
                    </div>
                </div>
                <span class="m-gauge-title">Clients (AP)</span>
            </div>
        </div>
    </div>
</div>
