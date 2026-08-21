<?php
$board = $state['board'] ?? ['hostname' => 'orangepizero2', 'uptime' => '10m', 'kernel' => '6.18.44'];
?>

<header class="header-bar">
    <!-- Board Info Capsule (Neumorphic) -->
    <div class="header-brand-capsule">
        <span class="pulse-green-dot"></span>
        <strong style="font-size: 13px; color: var(--text-heading);">Orange Pi Zero 2</strong>
        <span class="header-hostname-pill"><?= htmlspecialchars($board['hostname'] ?? 'orangepizero2') ?></span>
    </div>

    <!-- Header Actions & Board Info -->
    <div class="header-actions">
        <!-- Live Uptime Pill -->
        <div class="btn-new-device" style="cursor: default;" title="Waktu Aktif Sistem">
            <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
            <span id="headerUptimeText">Aktif: <?= htmlspecialchars($board['uptime'] ?? '10m') ?></span>
        </div>

        <!-- Dark Mode Toggle -->
        <button type="button" class="btn-new-device" id="btnDarkModeToggle" onclick="toggleTheme()" title="Toggle Dark Mode">
            <i class="bi bi-moon-stars-fill" id="themeIcon" style="font-size: 15px;"></i>
        </button>
    </div>
</header>
