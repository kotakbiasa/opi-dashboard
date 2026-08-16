<?php
$clients = $state['clients'] ?? [];
$services = $state['services'] ?? [];
$hostapdActive = ($services['hostapd']['active'] ?? true);
$adguard = $state['adguard'] ?? [];
$adgProtection = ($adguard['protection_enabled'] ?? true);
?>

<div class="small-devices-stack">
    <!-- 1. Wi-Fi Hotspot AP Engine (OcanAP 2.4GHz) -->
    <div class="device-card-compact" id="hotspotDeviceCard">
        <div class="device-card-compact-header">
            <div class="device-badge-icon blue-icon" title="Pemancar Hotspot Wi-Fi (OcanAP)">
                <i class="bi bi-wifi" style="color: #0284c7; font-size: 17px;"></i>
            </div>

            <!-- AP Status Badge -->
            <span class="room-spec-pill" id="hotspotStatusPill">
                <span class="pulse-green-dot" style="background: <?= $hostapdActive ? '#10b981' : '#cbd5e1' ?>;"></span>
                <span><?= $hostapdActive ? 'Hotspot Aktif' : 'Nonaktif' ?></span>
            </span>
        </div>

        <div class="device-card-compact-body">
            <div class="device-text-group">
                <h4>Hotspot: OcanAP</h4>
                <p><span id="hotspotClientsCount"><?= count($clients) ?></span> Klien &bull; Saluran 6 (WPA2)</p>
            </div>

            <!-- Radio Signal Activity Visualizer -->
            <div class="waveform-bars" id="tvWaveform" style="opacity: <?= $hostapdActive ? '1' : '0.2' ?>;" title="Aktivitas Transmisi Radio Wi-Fi">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>

            <!-- AP Service Status Switch -->
            <label class="nm-switch" title="Nyalakan / Matikan Layanan Hotspot">
                <input type="checkbox" id="hotspotServiceSwitch" <?= $hostapdActive ? 'checked' : '' ?>>
                <div class="switch-slider"></div>
            </label>
        </div>
    </div>

    <!-- 2. AdGuard Home DNS & Security Engine -->
    <div class="device-card-compact" id="adguardDeviceCard">
        <div class="device-card-compact-header">
            <div class="device-badge-icon badge-teal" title="Proteksi DNS & Pemblokir Iklan AdGuard Home">
                <i class="bi bi-shield-fill-check" style="color: #10b981; font-size: 17px;"></i>
            </div>

            <!-- AdGuard Status Badge -->
            <span class="room-spec-pill" id="adgCardPill">
                <span class="pulse-green-dot" style="background: <?= $adgProtection ? '#10b981' : '#cbd5e1' ?>;"></span>
                <span><?= $adgProtection ? 'Proteksi Aktif' : 'Dijeda' ?></span>
            </span>
        </div>

        <div class="device-card-compact-body">
            <div class="device-text-group">
                <h4>Proteksi AdGuard</h4>
                <p><span style="color: #ef4444; font-weight: 700;"><?= number_format($adguard['num_blocked_filtering'] ?? 2743) ?></span> Iklan Diblokir &bull; 0.43 ms</p>
            </div>

            <!-- Direct Link Badge to AdGuard Page -->
            <a href="adguard.php" class="room-spec-pill" style="text-decoration: none; font-size: 10px; font-weight: 700; color: #059669; background: rgba(16, 185, 129, 0.12);" title="Buka Detail AdGuard">
                <span>Detail</span>
                <i class="bi bi-chevron-right" style="font-size: 9px;"></i>
            </a>

            <!-- AdGuard Protection Switch -->
            <label class="nm-switch" title="Aktifkan / Jeda Pemblokiran Iklan">
                <input type="checkbox" id="adgCardSwitch" <?= $adgProtection ? 'checked' : '' ?> onchange="handleToggleAdgFromCard(this.checked)">
                <div class="switch-slider"></div>
            </label>
        </div>
    </div>
</div>
