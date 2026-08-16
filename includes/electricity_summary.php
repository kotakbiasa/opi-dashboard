<?php
$networks = $state['networks'] ?? [];
$wlan = $networks['wlan0'] ?? ['rx_mb' => 0, 'tx_mb' => 0];
$modem = $networks['enx0c5b8f279a64'] ?? ['rx_mb' => 0, 'tx_mb' => 0];
$totalRxMb = round(($wlan['rx_mb'] ?? 0) + ($modem['rx_mb'] ?? 0), 1);
$totalTxMb = round(($wlan['tx_mb'] ?? 0) + ($modem['tx_mb'] ?? 0), 1);
$overall = $state['overall_usage'] ?? [];
$liveRx = $overall['live_rx_kbps'] ?? 145.2;
$liveTx = $overall['live_tx_kbps'] ?? 42.8;

$dlFormatted = ($liveRx >= 1024) ? round($liveRx / 1024, 2) . ' MB/s' : round($liveRx, 1) . ' KB/s';
$ulFormatted = ($liveTx >= 1024) ? round($liveTx / 1024, 2) . ' MB/s' : round($liveTx, 1) . ' KB/s';
?>

<div class="summary-card" style="padding: 16px 20px;">
    <!-- Bandwidth Telemetry Header -->
    <div class="summary-header" style="margin-bottom: 12px;">
        <div class="summary-title-group">
            <h3>Throughput Bandwidth Real-Time</h3>
            <p>Statistik Gelombang Lalu Lintas Unduh & Unggah (Modem 4G & Wi-Fi)</p>
        </div>

        <div class="summary-traffic-pills">
            <!-- Live Total Pill -->
            <div class="traffic-pill" style="box-shadow: var(--nm-inset-sm); padding: 4px 12px; gap: 6px;" title="Total Volume Data Sesi Ini">
                <i class="bi bi-database" style="font-size: 12px; color: var(--color-primary);"></i>
                <span>Total: <strong id="totalTrafficText"><?= round($totalRxMb + $totalTxMb) ?> MB</strong></span>
            </div>
        </div>
    </div>

    <!-- Dual Live Waveforms Grid (Download & Upload Oscilloscope Cards) -->
    <div class="bandwidth-waveforms-grid">
        <!-- 1. Download Channel (Emerald / Cyan Wave) -->
        <div class="bw-waveform-card">
            <div class="bw-waveform-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="bw-channel-icon badge-dl">
                        <i class="bi bi-arrow-down-circle-fill" style="font-size: 18px; color: #10b981;"></i>
                    </div>
                    <div>
                        <span class="bw-channel-label">Kecepatan Unduh</span>
                        <div class="bw-channel-stat">
                            <strong id="liveDlSpeedText" class="text-dl" style="font-size: 16px; font-weight: 800; font-family: monospace; color: #059669;"><?= $dlFormatted ?></strong>
                        </div>
                    </div>
                </div>
                <div class="bw-meta-tag" title="Total Volume Unduh">
                    <span class="pulse-green-dot"></span>
                    <span id="liveTotalDlMb"><?= $totalRxMb ?> MB</span>
                </div>
            </div>

            <!-- Download Sparkline SVG Curve -->
            <div class="bw-sparkline-wrap" title="Gelombang Unduh Real-Time">
                <svg viewBox="0 0 200 48" class="sparkline-svg" id="sparklineDlSvg" style="width: 100%; height: 48px; overflow: visible;">
                    <defs>
                        <linearGradient id="sparkGradDl" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.45"/>
                            <stop offset="100%" stop-color="#06b6d4" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <path id="sparklineDlArea" d="M0,32 C30,32 45,12 80,24 C115,36 140,8 170,20 L200,14 L200,48 L0,48 Z" fill="url(#sparkGradDl)" style="transition: d 0.6s ease;"/>
                    <path id="sparklineDlLine" d="M0,32 C30,32 45,12 80,24 C115,36 140,8 170,20 L200,14" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="transition: d 0.6s ease;"/>
                </svg>
            </div>
        </div>

        <!-- 2. Upload Channel (Blue / Purple Wave) -->
        <div class="bw-waveform-card">
            <div class="bw-waveform-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="bw-channel-icon badge-ul">
                        <i class="bi bi-arrow-up-circle-fill" style="font-size: 18px; color: #0284c7;"></i>
                    </div>
                    <div>
                        <span class="bw-channel-label">Kecepatan Unggah</span>
                        <div class="bw-channel-stat">
                            <strong id="liveUlSpeedText" class="text-ul" style="font-size: 16px; font-weight: 800; font-family: monospace; color: #0284c7;"><?= $ulFormatted ?></strong>
                        </div>
                    </div>
                </div>
                <div class="bw-meta-tag" title="Total Volume Unggah">
                    <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#0284c7;"></span>
                    <span id="liveTotalUlMb"><?= $totalTxMb ?> MB</span>
                </div>
            </div>

            <!-- Upload Sparkline SVG Curve -->
            <div class="bw-sparkline-wrap" title="Gelombang Unggah Real-Time">
                <svg viewBox="0 0 200 48" class="sparkline-svg" id="sparklineUlSvg" style="width: 100%; height: 48px; overflow: visible;">
                    <defs>
                        <linearGradient id="sparkGradUl" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#0284c7" stop-opacity="0.45"/>
                            <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <path id="sparklineUlArea" d="M0,36 C35,36 50,18 90,28 C130,38 150,14 180,24 L200,18 L200,48 L0,48 Z" fill="url(#sparkGradUl)" style="transition: d 0.6s ease;"/>
                    <path id="sparklineUlLine" d="M0,36 C35,36 50,18 90,28 C130,38 150,14 180,24 L200,18" fill="none" stroke="#0284c7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="transition: d 0.6s ease;"/>
                </svg>
            </div>
        </div>
    </div>
</div>
