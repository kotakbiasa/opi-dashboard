<?php
$curPage = $currentPage ?? 'home';
?>
<aside class="sidebar-dock">
    <div class="dock-group top-dock">
        <!-- 1. Home (Dasbor Utama) -->
        <a href="index.php" class="dock-btn <?= ($curPage === 'home') ? 'active' : '' ?>" data-tooltip="Dasbor Utama" title="Dasbor Utama">
            <i class="bi bi-house-door-fill"></i>
        </a>

        <!-- 2. Network & Hotspot AP -->
        <a href="network.php" class="dock-btn <?= ($curPage === 'network') ? 'active' : '' ?>" data-tooltip="Konfigurasi Hotspot & Jaringan" title="Konfigurasi Hotspot & Jaringan">
            <i class="bi bi-wifi"></i>
        </a>

        <!-- 3. Modem 4G LTE Telemetry -->
        <a href="modem.php" class="dock-btn <?= ($curPage === 'modem') ? 'active' : '' ?>" data-tooltip="Status Modem 4G LTE" title="Status Modem 4G LTE">
            <i class="bi bi-sim-fill"></i>
        </a>

        <!-- 4. Data & Quota Usage (Per Device) -->
        <a href="usage.php" class="dock-btn <?= ($curPage === 'usage') ? 'active' : '' ?>" data-tooltip="Penggunaan Data & Kuota Klien" title="Penggunaan Data & Kuota Klien">
            <i class="bi bi-pie-chart-fill"></i>
        </a>

        <!-- 5. AdGuard Home DNS Protection -->
        <a href="adguard.php" class="dock-btn <?= ($curPage === 'adguard') ? 'active' : '' ?>" data-tooltip="Proteksi DNS AdGuard Home" title="Proteksi DNS AdGuard Home">
            <i class="bi bi-shield-check"></i>
        </a>

        <!-- 6. Captive Portal & Voucher Management -->
        <a href="portal.php" class="dock-btn <?= ($curPage === 'portal') ? 'active' : '' ?>" data-tooltip="Captive Portal & Voucher" title="Captive Portal & Voucher">
            <i class="bi bi-ticket-perforated-fill"></i>
        </a>

        <!-- 7. File Manager (Manajer Berkas) -->
        <a href="files.php" class="dock-btn <?= ($curPage === 'files') ? 'active' : '' ?>" data-tooltip="Manajer Berkas (File Explorer)" title="Manajer Berkas">
            <i class="bi bi-folder2-open"></i>
        </a>

        <!-- 8. System Services (Layanan Sistem) -->
        <a href="services.php" class="dock-btn <?= ($curPage === 'services') ? 'active' : '' ?>" data-tooltip="Layanan Sistem (Systemd Services)" title="Layanan Sistem">
            <i class="bi bi-grid-fill"></i>
        </a>

        <!-- 9. Settings (Pengaturan Sistem & Daya) -->
        <a href="settings.php" class="dock-btn <?= ($curPage === 'settings') ? 'active' : '' ?>" data-tooltip="Pengaturan Sistem & Daya" title="Pengaturan Sistem">
            <i class="bi bi-gear-fill"></i>
        </a>
    </div>

    <div class="dock-group bottom-dock">
        <!-- Sign Out (Keluar Sesi) -->
        <button type="button" class="dock-btn logout-btn" onclick="openGlobalLogoutModal()" data-tooltip="Keluar Sesi" title="Keluar Sesi">
            <i class="bi bi-box-arrow-right"></i>
        </button>
    </div>
</aside>

<!-- ========================================================================= -->
<!-- MODAL: GLOBAL LOGOUT CONFIRMATION DIALOG -->
<!-- ========================================================================= -->
<div id="modalGlobalLogout" class="reboot-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(6px);">
    <div class="hud-card-panel" style="max-width: 440px; width: 90%; padding: 26px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 14px; animation: modalPop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);">
        <div class="file-badge-icon" style="color: #ef4444; width: 54px; height: 54px; font-size: 26px; box-shadow: 0 0 20px rgba(239, 68, 68, 0.25);">
            <i class="bi bi-box-arrow-right"></i>
        </div>
        
        <div>
            <h3 style="font-size: 16.5px; color: var(--text-heading); font-weight: 800; margin-bottom: 4px;">Konfirmasi Keluar Sesi</h3>
            <p style="font-size: 12px; color: var(--text-muted); line-height: 1.45; margin: 0;">
                Apakah Anda yakin ingin mengakhiri sesi administrator Dasbor Orange Pi Zero 2?
            </p>
        </div>

        <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: rgba(2, 132, 199, 0.06); border-radius: var(--radius-pill); font-size: 11px; font-weight: 700; color: #0284c7; box-shadow: var(--nm-inset-sm);">
            <i class="bi bi-person-badge"></i>
            <span>Pengguna: Administrator (admin)</span>
        </div>

        <div style="display: flex; gap: 10px; width: 100%; justify-content: center; margin-top: 6px;">
            <button type="button" class="btn-new-device" onclick="closeGlobalLogoutModal()" style="flex: 1; padding: 9px 16px; font-size: 12px;">
                Batal
            </button>
            <button type="button" class="btn-primary-neumorphic" id="btnConfirmGlobalLogout" onclick="executeGlobalLogout()" style="flex: 1; padding: 9px 16px; font-size: 12px; color: #ef4444; border-color: rgba(239, 68, 68, 0.4);">
                <i class="bi bi-door-open-fill"></i>
                <span>Ya, Keluar</span>
            </button>
        </div>
    </div>
</div>

<script>
function openGlobalLogoutModal() {
    const modal = document.getElementById('modalGlobalLogout');
    if (modal) modal.style.display = 'flex';
}

function closeGlobalLogoutModal() {
    const modal = document.getElementById('modalGlobalLogout');
    if (modal) modal.style.display = 'none';
}

async function executeGlobalLogout() {
    const btn = document.getElementById('btnConfirmGlobalLogout');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>Mengeluarkan...</span>';
    }
    try {
        await fetch('api.php?action=logout', { method: 'POST' });
    } catch (e) {}
    window.location.href = 'index.php';
}
</script>
