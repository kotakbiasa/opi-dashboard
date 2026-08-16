<?php
$board = $state['board'] ?? [
    'name' => 'Orange Pi Zero 2',
    'hostname' => 'orangepizero2',
    'soc' => 'Allwinner H616',
    'kernel' => '6.18.44-current-sunxi64'
];
$clients = $state['clients'] ?? [];
$networks = $state['networks'] ?? [];

function getDeviceTypeMeta(string $name): array {
    $n = strtolower($name);
    if (preg_match('/(phone|redmi|xiaomi|samsung|galaxy|poco|realme|vivo|oppo|huawei|iphone|android|enall|mobile)/i', $n)) {
        return [
            'type' => 'Smartphone',
            'color' => 'blue',
            'svg' => '<i class="bi bi-phone" style="font-size: 16px;"></i>'
        ];
    }
    if (preg_match('/(laptop|desktop|pc|mac|win|thinkpad|notebook|book|asus|acer|lenovo|dell|hp)/i', $n)) {
        return [
            'type' => 'Laptop / PC',
            'color' => 'purple',
            'svg' => '<i class="bi bi-laptop" style="font-size: 16px;"></i>'
        ];
    }
    if (preg_match('/(tv|cast|roku|firetv|smarttv|display|screen)/i', $n)) {
        return [
            'type' => 'Smart TV',
            'color' => 'amber',
            'svg' => '<i class="bi bi-tv" style="font-size: 16px;"></i>'
        ];
    }
    return [
        'type' => 'Hotspot AP',
        'color' => 'teal',
        'svg' => '<i class="bi bi-wifi" style="font-size: 16px;"></i>'
    ];
}
?>

<aside class="right-panel">
    <!-- 1. Gateway & Board Profile Card -->
    <div class="user-profile-card">
        <div class="profile-avatar-wrap" title="Orange Pi Zero 2 (Allwinner H616 Gateway Router)">
            <img src="assets/orange-pi-logo.png" alt="Orange Pi Zero 2" class="profile-avatar-img">
        </div>
        <div class="profile-meta">
            <h3 class="profile-title">Orange Pi Zero 2</h3>
            <div class="profile-sub-row">
                <span class="profile-soc-name">Allwinner H616</span>
                <span class="profile-sep-dot">&bull;</span>
                <span class="profile-os-name">Gateway Router</span>
            </div>
        </div>
        <button type="button" class="profile-status-pill" id="btnProfileActions" title="Gateway Aktif &bull; Klik untuk Aksi / Mulai Ulang">
            <span class="pulse-green-dot"></span>
            <span>ONLINE</span>
        </button>
    </div>

    <!-- 2. Real-Time Neumorphic Digital Segment Box Clock & Server Date -->
    <?php
    $daysIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $monthsIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $dayName = $daysIndo[(int)date('w')];
    $monthName = $monthsIndo[(int)date('n')];
    $formattedDateIndo = $dayName . ', ' . date('j') . ' ' . $monthName . ' ' . date('Y');
    ?>
    <div class="clock-segment-card" title="Jam & Tanggal Server Gateway (WITA)">
        <div class="clock-segments-wrap">
            <!-- Hours Box -->
            <div class="clock-digit-box">
                <span class="clock-digit-val" id="clockHours"><?= date('H') ?></span>
                <span class="clock-digit-sub">JAM</span>
            </div>

            <span class="clock-box-colon">:</span>

            <!-- Minutes Box -->
            <div class="clock-digit-box">
                <span class="clock-digit-val" id="clockMinutes"><?= date('i') ?></span>
                <span class="clock-digit-sub">MENIT</span>
            </div>

            <span class="clock-box-colon">:</span>

            <!-- Seconds Box -->
            <div class="clock-digit-box clock-box-sec">
                <span class="clock-digit-val text-primary" id="clockSeconds"><?= date('s') ?></span>
                <span class="clock-digit-sub">DETIK</span>
            </div>

            <!-- Timezone Badge -->
            <div class="clock-box-tz" title="Waktu Indonesia Tengah (Asia/Makassar)">
                <span>WITA</span>
            </div>
        </div>

        <!-- Date & Gateway IP Row -->
        <div class="clock-date-row" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <i class="bi bi-calendar3" style="font-size: 11px;"></i>
                <span id="clockFullDate"><?= $formattedDateIndo ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 4px; color: #059669; font-weight: 700; font-family: monospace; font-size: 11px;">
                <span class="pulse-green-dot"></span>
                <span>192.168.1.1</span>
            </div>
        </div>
    </div>

    <!-- 3. 2x2 Gateway Network Interfaces Grid -->
    <div class="rooms-nav-2x2">
        <!-- Interface 1: wlan0 (Wi-Fi AP) -->
        <button class="room-nav-btn active" data-iface="wlan0" title="Hotspot Wi-Fi AP (OcanAP)">
            <i class="bi bi-wifi" style="font-size: 18px; color: var(--color-primary);"></i>
            <span>Wi-Fi AP</span>
        </button>

        <!-- Interface 2: USB 4G Modem WAN (enx...) -->
        <button class="room-nav-btn" data-iface="modem" title="Modem 4G USB WAN">
            <i class="bi bi-sim-fill" style="font-size: 18px; color: #059669;"></i>
            <span>Modem 4G</span>
        </button>

        <!-- Interface 3: Gigabit LAN (end0) -->
        <button class="room-nav-btn" data-iface="end0" title="Kabel Ethernet LAN (Gigabit)">
            <i class="bi bi-ethernet" style="font-size: 18px; color: #f59e0b;"></i>
            <span>LAN Kabel</span>
        </button>

        <!-- Interface 4: Tailscale VPN -->
        <button class="room-nav-btn" data-iface="tailscale" title="Jaringan VPN Mesh Tailscale">
            <i class="bi bi-shield-shaded" style="font-size: 18px; color: #8b5cf6;"></i>
            <span>VPN Tailscale</span>
        </button>
    </div>

    <!-- 4. Connected Devices Section (Wi-Fi Clients List) -->
    <div class="members-section">
        <div class="members-header">
            <h3>Perangkat Klien Terhubung</h3>
            <span class="members-badge-count" id="badgeClientsCount"><?= count($clients) ?> Aktif</span>
        </div>

        <div class="members-list" id="rightClientsList">
            <?php if (empty($clients)): ?>
                <div class="member-item-empty">Tidak ada perangkat terhubung</div>
            <?php else: ?>
                <?php foreach ($clients as $client): ?>
                    <?php 
                    $meta = getDeviceTypeMeta($client['name']);
                    $shortMac = substr($client['mac'], -8);
                    ?>
                    <div class="member-item">
                        <div class="member-avatar-wrap avatar-<?= $meta['color'] ?>" title="<?= $meta['type'] ?>">
                            <?= $meta['svg'] ?>
                        </div>
                        <div class="member-meta">
                            <div class="member-name-row">
                                <h4 class="member-name"><?= htmlspecialchars($client['name']) ?></h4>
                                <span class="member-mac-tag"><?= $shortMac ?></span>
                            </div>
                            <span class="member-role"><?= htmlspecialchars($client['ip']) ?> &bull; <?= $meta['type'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</aside>
