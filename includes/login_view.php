<div class="login-wrapper">
    <div class="login-card">
        <!-- Top Circular Logo -->
        <div class="login-logo-circle">
            <img src="assets/orange-pi-logo.png" alt="Orange Pi Zero 2" width="68" height="68">
        </div>

        <!-- Title & Subtitle -->
        <div class="login-header-text">
            <h2>Orange Pi Zero 2</h2>
            <p>Pusat Kontrol & Telemetri Sistem</p>
        </div>

        <!-- Login Form (Supports both AJAX and standard POST) -->
        <form id="formLogin" class="login-form" method="POST" action="index.php">
            <!-- Username Input (Neumorphic Inset) -->
            <div class="login-input-group">
                <span class="input-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </span>
                <input type="text" name="username" id="loginUsername" placeholder="Nama pengguna" autocomplete="username" value="admin" required>
            </div>

            <!-- Password Input (Neumorphic Inset) -->
            <div class="login-input-group">
                <span class="input-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </span>
                <input type="password" name="password" id="loginPassword" placeholder="Kata sandi" autocomplete="current-password" value="admin" required>
            </div>

            <!-- Error message container -->
            <?php if (!empty($loginError)): ?>
                <div class="login-error-text"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <div id="loginErrorMsg" class="login-error-text" style="display: none;"></div>

            <!-- Submit Button -->
            <button type="submit" class="btn-login-cyan" id="btnLoginSubmit">
                <span>Masuk</span>
            </button>

            <!-- Bottom Subtitle / Info -->
            <div class="login-footer-info">
                <span>Bawaan: <strong>admin</strong> / <strong>admin</strong></span>
            </div>
        </form>
    </div>
</div>
