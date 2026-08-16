<?php
$cpu = $state['cpu'] ?? ['freq_mhz' => 1512, 'temp' => 54, 'governor' => 'ondemand'];
$services = $state['services'] ?? [];
$networks = $state['networks'] ?? [];
$wlan = $networks['wlan0'] ?? ['rx_mb' => 5.1, 'tx_mb' => 76.3];
$modem = $networks['enx0c5b8f279a64'] ?? ['rx_mb' => 75.1, 'tx_mb' => 34.7];
?>

<div class="mobile-tab-view" id="viewNetwork">
    <!-- 1. CPU Clock & Thermal Double Donut Card -->
    <div class="m-card">
        <div class="m-card-header">
            <h4>SoC Frequency & Health</h4>
        </div>

        <div class="m-double-donut-row">
            <!-- Donut 1: Frequency -->
            <div class="m-mini-donut-item">
                <div class="m-mini-donut-circle">
                    <svg viewBox="0 0 54 54">
                        <circle cx="27" cy="27" r="21" fill="none" stroke="#e2e8f0" stroke-width="4.5"/>
                        <circle cx="27" cy="27" r="21" fill="none" stroke="#10b981" stroke-width="4.5"
                                stroke-dasharray="131" stroke-dashoffset="20" stroke-linecap="round"/>
                    </svg>
                    <span class="m-mini-donut-pct">100%</span>
                </div>
                <div class="m-mini-donut-text">
                    <strong id="netFreqText"><?= (int)$cpu['freq_mhz'] ?> MHz</strong>
                    <span>Governor: <?= htmlspecialchars($cpu['governor']) ?></span>
                </div>
            </div>

            <!-- Donut 2: Thermals -->
            <div class="m-mini-donut-item">
                <div class="m-mini-donut-circle">
                    <svg viewBox="0 0 54 54">
                        <circle cx="27" cy="27" r="21" fill="none" stroke="#e2e8f0" stroke-width="4.5"/>
                        <circle cx="27" cy="27" r="21" fill="none" stroke="#3b82f6" stroke-width="4.5"
                                stroke-dasharray="131" stroke-dashoffset="45" stroke-linecap="round"/>
                    </svg>
                    <span class="m-mini-donut-pct" id="netTempPct"><?= (int)$cpu['temp'] ?>°</span>
                </div>
                <div class="m-mini-donut-text">
                    <strong id="netTempText"><?= (int)$cpu['temp'] ?>°C</strong>
                    <span>Core Thermal</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Services Status Card -->
    <div class="m-card">
        <div class="m-card-header">
            <h4>Active Core Services</h4>
            <span class="m-badge-pill-soft">Live</span>
        </div>

        <div class="m-services-list">
            <!-- Service 1: Hostapd -->
            <div class="m-service-row">
                <div class="m-service-left">
                    <div class="m-service-badge-num badge-num-amber">AP</div>
                    <div class="m-service-meta">
                        <h6>OcanAP Wi-Fi Hotspot</h6>
                        <p>hostapd.service &bull; 2.4GHz</p>
                    </div>
                </div>
                <span class="m-status-dot-active"></span>
            </div>

            <!-- Service 2: Dnsmasq -->
            <div class="m-service-row">
                <div class="m-service-left">
                    <div class="m-service-badge-num badge-num-green">DHCP</div>
                    <div class="m-service-meta">
                        <h6>DNS / DHCP Gateway</h6>
                        <p>dnsmasq.service &bull; 192.168.1.1</p>
                    </div>
                </div>
                <span class="m-status-dot-active"></span>
            </div>
        </div>
    </div>

    <!-- 3. Neumorphic Capsule Bar Chart (Bandwidth Throughput) -->
    <div class="m-card m-capsule-chart-card">
        <div class="m-card-header">
            <h4>Network Throughput (RX / TX)</h4>
            <span class="m-badge-pill-soft" id="netWanSummary">WAN: <?= (float)$modem['rx_mb'] ?> MB</span>
        </div>

        <!-- 7 Capsule Pill Slots -->
        <div class="m-capsule-bars-grid">
            <div class="m-capsule-col">
                <div class="m-capsule-track">
                    <div class="m-capsule-fill" style="height: 35%;"></div>
                </div>
                <span>00h</span>
            </div>
            <div class="m-capsule-col">
                <div class="m-capsule-track">
                    <div class="m-capsule-fill" style="height: 55%;"></div>
                </div>
                <span>04h</span>
            </div>
            <div class="m-capsule-col">
                <div class="m-capsule-track">
                    <div class="m-capsule-fill" style="height: 25%;"></div>
                </div>
                <span>08h</span>
            </div>
            <div class="m-capsule-col">
                <div class="m-capsule-track">
                    <div class="m-capsule-fill" style="height: 70%;"></div>
                </div>
                <span>12h</span>
            </div>
            <div class="m-capsule-col">
                <div class="m-capsule-track">
                    <div class="m-capsule-fill" style="height: 90%;"></div>
                </div>
                <span>16h</span>
            </div>
            <div class="m-capsule-col">
                <div class="m-capsule-track">
                    <div class="m-capsule-fill" style="height: 60%;"></div>
                </div>
                <span>20h</span>
            </div>
            <div class="m-capsule-col">
                <div class="m-capsule-track">
                    <div class="m-capsule-fill highlight" style="height: 80%;"></div>
                </div>
                <span>Now</span>
            </div>
        </div>
    </div>

    <!-- 4. Active Network Interfaces -->
    <div class="m-card">
        <div class="m-card-header">
            <h4>Network Interfaces</h4>
        </div>

        <div class="m-iface-list">
            <div class="m-iface-item">
                <div class="m-iface-badge">wlan0</div>
                <div class="m-iface-meta">
                    <strong>Wi-Fi Hotspot (OcanAP)</strong>
                    <span>TX: <?= (float)$wlan['tx_mb'] ?> MB &bull; RX: <?= (float)$wlan['rx_mb'] ?> MB</span>
                </div>
                <span class="m-tag-active">UP</span>
            </div>

            <div class="m-iface-item">
                <div class="m-iface-badge">enx0</div>
                <div class="m-iface-meta">
                    <strong>USB WAN Modem</strong>
                    <span>RX: <?= (float)$modem['rx_mb'] ?> MB &bull; Gateway</span>
                </div>
                <span class="m-tag-active">UP</span>
            </div>

            <div class="m-iface-item">
                <div class="m-iface-badge">tailscale0</div>
                <div class="m-iface-meta">
                    <strong>Tailscale VPN Mesh</strong>
                    <span>Mesh Network Active</span>
                </div>
                <span class="m-tag-active">UP</span>
            </div>
        </div>
    </div>
</div>
