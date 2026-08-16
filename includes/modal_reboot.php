<!-- Safe Reboot Confirmation Modal -->
<div class="modal-overlay" id="rebootModalOverlay" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalRebootTitle">
        <!-- Danger / Warning Icon Circle -->
        <div class="modal-icon-circle">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                <line x1="12" y1="2" x2="12" y2="12"></line>
            </svg>
        </div>

        <h3 id="modalRebootTitle">Mulai Ulang Orange Pi Zero 2?</h3>
        <p>Apakah Anda yakin ingin me-restart board sekarang? Semua koneksi klien aktif dan hotspot akan terputus sementara selama ±30 detik saat boot ulang.</p>

        <!-- Modal Actions -->
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-cancel" id="btnCancelReboot">Batal</button>
            <button type="button" class="btn-modal btn-confirm-reboot" id="btnConfirmReboot">
                <span class="spinner" id="rebootSpinner" style="display: none;"></span>
                <span id="rebootBtnText">Mulai Ulang Sekarang</span>
            </button>
        </div>
    </div>
</div>
