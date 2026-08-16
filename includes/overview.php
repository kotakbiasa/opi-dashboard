<?php
$cpu = $state['cpu'] ?? ['usage' => 15.0, 'freq_mhz' => 1512];
$ram = $state['ram'] ?? ['used_mb' => 520, 'total_mb' => 970, 'percent' => 53.6];
$modem = $state['modem'] ?? ['operator' => 'XL', 'band' => 'Band 40', 'signal_bars' => 4, 'rssi' => '-67 dBm'];
$clients = $state['clients'] ?? [];
?>

<section class="overview-section">
    <!-- Overview Header -->
    <div class="overview-header">
        <div class="overview-title-group">
            <h2>Status Gateway & Router 4G</h2>
            <p>Telemetri Hardware & Jaringan Hotspot Real-Time</p>
        </div>

        <!-- Neumorphic Live Polling Switch -->
        <label class="nm-switch" title="Aktifkan / Jeda Pembaruan Real-Time">
            <span class="switch-label">Langsung</span>
            <input type="checkbox" id="livePollingSwitch" checked>
            <div class="switch-slider"></div>
        </label>
    </div>

    <!-- 4 Neumorphic Router & Gateway KPI Metric Cards Grid -->
    <div class="rooms-grid">
        <!-- 1. CPU Usage Card (Teal Badge) -->
        <div class="room-card" data-metric-card="cpu" title="Allwinner H616 Quad-Core SoC Engine">
            <div class="room-card-top">
                <span class="room-card-title">Prosesor CPU</span>
                <span class="room-spec-pill">4 Inti @ 1.51G</span>
            </div>
            <div class="room-card-body">
                <div class="room-icon-badge badge-teal">
                    <i class="bi bi-cpu-fill" style="color: #0d9488;"></i>
                </div>
                <div class="room-stat">
                    <div class="room-stat-val">
                        <span id="metricCpuVal"><?= (int)round($cpu['usage'] ?? 0) ?></span>
                        <span class="stat-unit-inline">%</span>
                    </div>
                    <span class="room-stat-unit" id="metricCpuFreq">4x @ <?= (int)($cpu['freq_mhz'] ?? 1512) ?> MHz</span>
                </div>
            </div>
        </div>

        <!-- 2. RAM Memory Card (Orange Badge) -->
        <div class="room-card" data-metric-card="ram" title="Total 1GB DDR3 RAM System">
            <div class="room-card-top">
                <span class="room-card-title">Memori RAM</span>
                <span class="room-spec-pill">1 GB DDR3</span>
            </div>
            <div class="room-card-body">
                <div class="room-icon-badge badge-orange">
                    <i class="bi bi-memory" style="color: #f97316;"></i>
                </div>
                <div class="room-stat">
                    <div class="room-stat-val">
                        <span id="metricRamVal"><?= (int)($ram['used_mb'] ?? 0) ?></span>
                        <span class="stat-unit-inline">MB</span>
                    </div>
                    <span class="room-stat-unit" id="metricRamPct"><?= (float)($ram['percent'] ?? 0) ?>% dari <?= (int)($ram['total_mb'] ?? 970) ?>MB</span>
                </div>
            </div>
        </div>

        <!-- 3. 4G LTE Cellular Modem WAN Card (Green Badge) -->
        <div class="room-card" data-metric-card="modem" title="Huawei E3372 4G LTE USB Uplink">
            <div class="room-card-top">
                <span class="room-card-title">Uplink 4G LTE</span>
                <span class="room-spec-pill" style="color: #059669; font-weight: 800;"><?= htmlspecialchars($modem['operator'] ?? 'XL') ?> &bull; <?= $modem['signal_bars'] ?? 4 ?>/5</span>
            </div>
            <div class="room-card-body">
                <div class="room-icon-badge" style="color: #059669;">
                    <i class="bi bi-reception-4"></i>
                </div>
                <div class="room-stat">
                    <div class="room-stat-val">
                        <span style="font-size: 19px; font-family: monospace; color: #059669;"><?= htmlspecialchars($modem['rssi'] ?? '-67 dBm') ?></span>
                    </div>
                    <span class="room-stat-unit"><?= htmlspecialchars($modem['band'] ?? 'Band 40 (2300 MHz)') ?></span>
                </div>
            </div>
        </div>

        <!-- 4. Wi-Fi Hotspot AP Clients Card (Blue Badge) -->
        <div class="room-card" data-metric-card="clients" title="Perangkat terhubung ke Hotspot OcanAP">
            <div class="room-card-top">
                <span class="room-card-title">Klien Hotspot</span>
                <span class="room-spec-pill">OcanAP 2.4G</span>
            </div>
            <div class="room-card-body">
                <div class="room-icon-badge badge-blue">
                    <i class="bi bi-wifi" style="color: #0284c7;"></i>
                </div>
                <div class="room-stat">
                    <div class="room-stat-val">
                        <span id="metricClientsVal"><?= count($clients) ?></span>
                        <span class="stat-unit-inline">Perangkat</span>
                    </div>
                    <span class="room-stat-unit">Klien Aktif di Wi-Fi</span>
                </div>
            </div>
        </div>
    </div>
</section>
