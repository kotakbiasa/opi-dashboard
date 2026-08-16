<?php
$ping = $state['ping'] ?? ['ms' => 68.1, 'status' => 'Baik', 'quality' => 'good', 'online' => true];
$cpu = $state['cpu'] ?? ['load_1m' => 0.5, 'load_5m' => 0.4, 'load_15m' => 0.3, 'temp' => 54];
$load1m = $cpu['load_1m'] ?? 0.5;
?>

<div class="sensors-stack">
    <!-- 1. Internet Quality & Latency (Ping) Card -->
    <div class="sensor-card">
        <div class="sensor-card-left">
            <div class="sensor-badge-icon badge-ping-globe" title="Kualitas Sambungan Internet WAN">
                <i class="bi bi-globe2" style="color: #0284c7; font-size: 16px;"></i>
            </div>
            <div class="sensor-meta">
                <span class="sensor-title">Latensi Internet WAN</span>
                <span class="sensor-reading" id="pingStatusText">
                    <span id="pingValueNum"><?= $ping['ms'] ?></span> ms 
                    <span class="unit-text">&bull; <strong id="pingStatusLabel" class="ping-status-<?= $ping['quality'] ?? 'good' ?>"><?= htmlspecialchars($ping['status']) ?></strong></span>
                </span>
            </div>
        </div>

        <!-- Ping Refresh Button & Target Dot -->
        <div style="display: flex; align-items: center; gap: 8px;">
            <div class="ping-pill-indicator" title="Target: Cloudflare (1.1.1.1)">
                <span class="ping-live-dot" id="pingLiveDot"></span>
                <span>1.1.1.1</span>
            </div>
            <button type="button" class="btn-refresh-ping" id="btnTestPing" title="Uji Latensi Ping Sekarang">
                <i class="bi bi-arrow-repeat" style="font-size: 13px;"></i>
            </button>
        </div>
    </div>

    <!-- 2. System Load Average Card (Smooth Animated Live Waveform) -->
    <div class="sensor-card">
        <div class="sensor-card-left">
            <div class="sensor-badge-icon pink-icon" title="Beban Rata-Rata Sistem (Load Average 1m, 5m, 15m)">
                <i class="bi bi-activity" style="color: #ec4899; font-size: 17px;"></i>
            </div>
            <div class="sensor-meta">
                <span class="sensor-title">Beban Sistem (Load)</span>
                <span class="sensor-reading" id="loadAvgReading">
                    <strong id="load1mSensorVal" style="color: var(--text-heading); font-size: 15px; font-weight: 800; font-family: monospace;"><?= $load1m ?></strong> 
                    <span class="unit-text">&bull; 1m (4 Inti)</span>
                </span>
            </div>
        </div>

        <!-- Live Animating Load Sparkline SVG -->
        <div class="sensor-sparkline" style="width: 118px; height: 42px; flex-shrink: 0;" title="Beban Sistem Real-Time (Bergerak Dinamis)">
            <svg viewBox="0 0 100 36" class="sparkline-svg" id="sparklineLoadSvg" style="width: 100%; height: 100%; overflow: visible;">
                <defs>
                    <linearGradient id="sparkGradPink" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#db2777" stop-opacity="0.45"/>
                        <stop offset="100%" stop-color="#db2777" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>
                <path id="sparklineLoadArea" d="M0,22 C22,22 28,6 48,16 C64,22 72,28 86,8 L100,16 L100,36 L0,36 Z" fill="url(#sparkGradPink)" style="transition: d 0.8s ease;"/>
                <path id="sparklineLoadLine" d="M0,22 C22,22 28,6 48,16 C64,22 72,28 86,8 L100,16" fill="none" stroke="#db2777" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" style="transition: d 0.8s ease;"/>
            </svg>
        </div>
    </div>
</div>
