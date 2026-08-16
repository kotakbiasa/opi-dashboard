<?php
$cpu = $state['cpu'] ?? [
    'temp' => 54,
    'freq_mhz' => 1512,
    'governor' => 'ondemand',
    'usage' => 20.0
];
$cpuTemp = (int)($cpu['temp'] ?? 54);
$governor = $cpu['governor'] ?? 'ondemand';
?>

<div class="ac-card">
    <!-- CPU & SoC Thermal Header -->
    <div class="ac-header">
        <div class="ac-title-wrap">
            <div class="ac-icon-soft spinning" id="acFanIcon" title="Pendingin Termal SoC Allwinner H616">
                <i class="bi bi-fan" style="font-size: 18px; color: var(--color-primary);"></i>
            </div>
            <div>
                <h3>Termal & Daya CPU SoC</h3>
            </div>
        </div>

        <!-- High Performance Mode Toggle -->
        <label class="nm-switch" title="Mode Performa Tinggi (Turbo)">
            <span class="switch-label" id="govLabel"><?= htmlspecialchars(strtoupper($governor)) ?></span>
            <input type="checkbox" id="govPowerSwitch" <?= ($governor === 'performance') ? 'checked' : '' ?>>
            <div class="switch-slider"></div>
        </label>
    </div>

    <!-- Thermostat Stage & Circular Thermal Dial -->
    <div class="thermostat-stage">
        <div class="thermostat-dial-outer" id="thermostatDial" title="Suhu Inti SoC Allwinner H616">
            <!-- Radial Ticks SVG Overlay -->
            <svg class="radial-ticks-svg" id="radialTicksSvg" viewBox="0 0 176 176"></svg>

            <!-- Center Blue Temperature Circle -->
            <div class="thermostat-dial-center" id="thermalDialCenter">
                <span class="temp-val-display" id="tempValDisplay"><?= $cpuTemp ?>°C</span>
                <span class="temp-unit-label" id="thermalStatusLabel">
                    <?= ($cpuTemp < 60) ? 'Optimal' : (($cpuTemp < 75) ? 'Hangat' : 'Suhu Tinggi') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Thermostat Horizontal Controls / Governor Selector -->
    <div class="thermostat-controls">
        <!-- Powersave Governor Button (Valid Battery-Charging Icon) -->
        <button type="button" class="btn-round-ctrl <?= ($governor === 'powersave') ? 'active' : '' ?>" id="btnGovPowersave" title="Mode Hemat Daya (Powersave)">
            <i class="bi bi-battery-charging" style="font-size: 16px; color: #10b981;"></i>
        </button>

        <!-- Current Governor Indicator Capsule -->
        <div class="airflow-indicator" id="currentGovernorText" title="Mode Governor CPU Aktif">
            <i class="bi bi-lightning-charge-fill" style="font-size: 13px; color: var(--color-primary);"></i>
            <span id="governorDisplay"><?= htmlspecialchars($governor) ?></span>
        </div>

        <!-- Turbo Preset Button (Performance 1.51GHz) -->
        <button type="button" class="btn-round-ctrl <?= ($governor === 'performance') ? 'active' : '' ?>" id="btnPresetAmber" title="Mode Turbo (Performa 1.51GHz)">
            <i class="bi bi-speedometer2" style="font-size: 15px; color: #f59e0b;"></i>
        </button>

        <!-- Balanced / OnDemand Button -->
        <button type="button" class="btn-round-ctrl <?= ($governor === 'ondemand') ? 'active' : '' ?>" id="btnGovOndemand" title="Mode Seimbang (OnDemand)">
            <i class="bi bi-cpu" style="font-size: 15px; color: #0284c7;"></i>
        </button>
    </div>
</div>
