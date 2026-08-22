# 🍊 Orange Pi Zero 2 Neumorphic Gateway Dashboard

A lightweight, modern, and tactile web dashboard & captive portal controller designed specifically for **Orange Pi Zero 2 (Allwinner H616)** running Armbian Linux.

![Dashboard Preview](assets/orange-pi-logo.png)

## ✨ Features

- **📊 Modern Neumorphic Cockpit Dashboard (`index.php`)**:
  - Live CPU Frequency, RAM, Storage, and Thermal telemetry gauges.
  - Multi-WAN status: 4G LTE Huawei HiLink Modem (`enx0c5b8f279a64`) & Gigabit Ethernet LAN (`end0`).
  - Active wireless clients counter & real-time ping monitor.
- **📶 Wireless & Hotspot Management (`network.php`)**:
  - SSID configuration, WPA2 passphrase, radio channels, and TxPower control.
  - Connected Wi-Fi station list with signal strength and MAC identification.
- **🌐 4G LTE Modem Cockpit (`modem.php`)**:
  - Huawei HiLink API integration (RSRP, RSRQ, SINR, RSSI signal bars, Cell ID, Band).
  - Data traffic counters and SMS manager.
- **🛡️ AdGuard Home DNS & Security Hub (`adguard.php`)**:
  - Live DNS queries, blocked ads & trackers stats, parental control toggle.
- **🎟️ Captive Portal & Voucher Billing Engine (`portal.php`, `splash.php`)**:
  - Voucher batch generation (Time-based, Quota-based, Expire limits).
  - Member account management & active session monitor with ARP MAC binding.
  - Beautiful, responsive mobile splash login portal for guest Wi-Fi.
- **📂 File Manager & Rclone Cloud Hub (`files.php`)**:
  - Integrated local file explorer with breadcrumb navigation and file operations.
  - Direct support for **Rclone** remotes (Google Drive, OneDrive, S3, WebDAV).
- **⚙️ System Services Manager (`services.php`)**:
  - Manage 12 core Linux daemons (`hostapd`, `dnsmasq`, `AdGuardHome`, `tailscaled`, `ocanap-telegram-bot`, etc.).
  - Real-time `systemctl` controls and `journalctl` log viewer.
- **🔧 Hardware Specifications & Settings (`settings.php`)**:
  - CPU Scaling Governors (`ondemand`, `performance`, `powersave`, `schedutil`, `conservative`).
  - Hardware LED Triggers (`green:power`, `red:status`).
  - Timezone, Hostname, and Admin authentication manager.
  - Comprehensive Hardware Bento Specs (Wi-Fi 4 radio, MicroSD partitions, RAM & ZRAM swap pool, 4-core Cortex-A53 matrix).
  - **Telegram Bot Service Integration**: Live daemon controls, custom event alerts (vouchers, guests, watchdog failover, CPU thermal alerts), and interactive bot commands.

## 🚀 Installation & Setup

1. **Clone repository**:
   ```bash
   git clone https://github.com/<your-username>/<your-repo>.git /var/www/opi-dashboard
   ```
2. **Set permissions**:
   ```bash
   chmod -R 755 /var/www/opi-dashboard
   chown -R www-data:www-data /var/www/opi-dashboard/data
   chmod -R 770 /var/www/opi-dashboard/data
   ```
3. **Block web access to `/data`** (contains credential hashes, vouchers & member data):
   - **Apache**: `data/.htaccess` sudah disertakan (deny all). Pastikan `AllowOverride All` aktif.
   - **Nginx**:
     ```nginx
     location ^~ /data/ { deny all; return 403; }
     ```
   - **Lighttpd**:
     ```lighttpd
     $HTTP["url"] =~ "^/data/" { url.access-deny = ("") }
     ```
   - **PHP built-in server**: gunakan router agar folder `data/` tidak tersaji:
     ```bash
     php -S 0.0.0.0:8000 -t /var/www/opi-dashboard router.php
     ```
4. **Run built-in server or Lighttpd / Nginx**:
   ```bash
   php -S 0.0.0.0:8000 -t /var/www/opi-dashboard
   ```

## 🔒 Security
- First run auto-creates default credentials: username `admin`, password `admin` — **ganti segera lewat halaman Settings** (minimal 8 karakter).
- Remember-me token (`opi_token`) hanya disimpan sebagai hash di `data/auth.json`; cookie tanpa kecocokan hash akan ditolak otomatis.
- Rate limit login: maksimal 5 percobaan per IP, terkunci 60 detik (disimpan server-side di `data/login_attempts.json`).
- CSRF token wajib pada semua aksi POST/DELETE API.
- File Manager dibatasi whitelist direktori: `/root/opi-dashboard`, `/root`, `/etc`, `/var/log`, `/tmp`.
- Jangan pernah ekspos dashboard ini langsung ke internet; akses hanya dari jaringan lokal atau via VPN (mis. Tailscale).

---
Built with ❤️ for **Orange Pi Zero 2 Gateway (OcanAP)**.
