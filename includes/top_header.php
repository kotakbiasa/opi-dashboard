<?php
$board = $state['board'] ?? ['hostname' => 'orangepizero2', 'uptime' => '10m'];
?>
<header class="mobile-top-header">
    <button type="button" class="btn-top-icon" id="btnHeaderBack" title="Refresh Page">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>

    <div class="top-title-wrap">
        <h1 id="mobileHeaderTitle">Daily Cockpit</h1>
        <span class="live-dot-pulse" title="Real-Time Active"></span>
    </div>

    <button type="button" class="btn-top-icon" id="btnTopMenuAction" title="Reboot / System Actions">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <circle cx="12" cy="12" r="2"></circle>
            <circle cx="19" cy="12" r="2"></circle>
            <circle cx="5" cy="12" r="2"></circle>
        </svg>
    </button>
</header>
