<?php
$clients = $state['clients'] ?? [];
$cpu = $state['cpu'] ?? ['governor' => 'ondemand'];
$leds = $state['leds'] ?? [];
$greenLed = $leds['green_power'] ?? ['status' => true];
$redLed = $leds['red_status'] ?? ['status' => false];
?>

<div class="mobile-tab-view" id="viewClients">
    <!-- 1. Clients Overview Card (Large Number + 3D Neumorphic Sector) -->
    <div class="m-card">
        <div class="m-card-header">
            <h4>Connected Hotspot Clients</h4>
            <span class="m-badge-pill-soft" id="clientsCountBadge"><?= count($clients) ?> Active</span>
        </div>

        <div class="m-clients-hero-body">
            <div class="m-clients-hero-nums">
                <div class="m-hero-big-num">
                    <span id="heroClientsNum"><?= count($clients) ?></span>
                    <p>DHCP Leases</p>
                </div>
                <div class="m-hero-sub-num">
                    <span>100%</span>
                    <p>Signal Health</p>
                </div>
            </div>

            <!-- Neumorphic 3D Embossed Pie Chart Visual -->
            <div class="m-pie-visual-wrap">
                <svg viewBox="0 0 100 100" class="m-pie-svg">
                    <circle cx="50" cy="50" r="40" fill="#eaf0f8" filter="drop-shadow(3px 3px 6px rgba(180,195,215,0.5))"/>
                    <!-- Sector 1: Main Clients -->
                    <circle cx="50" cy="50" r="32" fill="none" stroke="#3b82f6" stroke-width="12"
                            stroke-dasharray="140 200" stroke-linecap="round"/>
                    <!-- Sector 2: Buffer -->
                    <circle cx="50" cy="50" r="32" fill="none" stroke="#10b981" stroke-width="12"
                            stroke-dasharray="40 200" stroke-dashoffset="-145" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- 2. Real Connected Clients List -->
    <div class="m-card">
        <div class="m-card-header">
            <h4>Device Lease Table</h4>
            <span class="m-badge-pill-soft">dnsmasq</span>
        </div>

        <div class="m-clients-list" id="mClientsList">
            <?php if (empty($clients)): ?>
                <div class="m-client-row-empty">No devices currently connected</div>
            <?php else: ?>
                <?php foreach ($clients as $c): ?>
                    <div class="m-client-row">
                        <div class="m-client-icon-circle">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                                <line x1="12" y1="18" x2="12.01" y2="18"></line>
                            </svg>
                        </div>
                        <div class="m-client-meta">
                            <strong><?= htmlspecialchars($c['name']) ?></strong>
                            <span><?= htmlspecialchars($c['ip']) ?> &bull; <?= htmlspecialchars($c['mac']) ?></span>
                        </div>
                        <span class="m-badge-live-green">Online</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Hardware Controls 2x2 Grid -->
    <div class="m-card">
        <div class="m-card-header">
            <h4>Hardware Actions & Power</h4>
        </div>

        <div class="m-controls-2x2-grid">
            <!-- CPU Governor Control -->
            <div class="m-ctrl-box">
                <div class="m-ctrl-box-top">
                    <span class="m-ctrl-icon-amber">⚡</span>
                    <span class="m-ctrl-label">Governor</span>
                </div>
                <div class="m-gov-buttons-row">
                    <button type="button" class="btn-m-gov <?= ($cpu['governor'] === 'ondemand') ? 'active' : '' ?>" data-set-gov="ondemand">OnDemand</button>
                    <button type="button" class="btn-m-gov <?= ($cpu['governor'] === 'performance') ? 'active' : '' ?>" data-set-gov="performance">Turbo</button>
                    <button type="button" class="btn-m-gov <?= ($cpu['governor'] === 'powersave') ? 'active' : '' ?>" data-set-gov="powersave">Eco</button>
                </div>
            </div>

            <!-- Physical LEDs Control -->
            <div class="m-ctrl-box">
                <div class="m-ctrl-box-top">
                    <span class="m-ctrl-icon-green">💡</span>
                    <span class="m-ctrl-label">Green LED</span>
                </div>
                <label class="nm-switch" style="margin-top: 10px;">
                    <input type="checkbox" id="actionsGreenLedSwitch" <?= ($greenLed['status'] ?? true) ? 'checked' : '' ?>>
                    <div class="switch-slider"></div>
                </label>
            </div>

            <!-- Restart Hotspot -->
            <div class="m-ctrl-box">
                <div class="m-ctrl-box-top">
                    <span class="m-ctrl-icon-blue">🔄</span>
                    <span class="m-ctrl-label">Restart AP</span>
                </div>
                <button type="button" class="btn-m-action-full" id="btnActionRestartAP">Reload Hotspot</button>
            </div>

            <!-- Reboot System -->
            <div class="m-ctrl-box">
                <div class="m-ctrl-box-top">
                    <span class="m-ctrl-icon-red">🔌</span>
                    <span class="m-ctrl-label">Reboot</span>
                </div>
                <button type="button" class="btn-m-action-danger" id="btnActionReboot">Reboot Pi</button>
            </div>
        </div>
    </div>
</div>
