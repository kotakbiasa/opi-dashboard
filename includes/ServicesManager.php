<?php
/**
 * OPI-DASHBOARD: System Services Manager Engine
 * Queries and Controls Linux systemd daemons (Hostapd, Dnsmasq, AdGuard, Tailscale, etc.)
 */

class ServicesManager {
    /**
     * Managed Gateway Services Definition
     */
    private static $managedServices = [
        'hostapd' => [
            'unit' => 'hostapd.service',
            'name' => 'Hostapd Access Point',
            'category' => 'network',
            'icon' => 'bi-wifi',
            'color' => '#0284c7',
            'description' => 'Daemon pemancar Wi-Fi Access Point Hotspot (OcanAP 2.4GHz)'
        ],
        'dnsmasq' => [
            'unit' => 'dnsmasq.service',
            'name' => 'Dnsmasq DHCP & DNS',
            'category' => 'network',
            'icon' => 'bi-router',
            'color' => '#f59e0b',
            'description' => 'Server alokasi IP DHCP lokal dan resolver DNS gateway'
        ],
        'AdGuardHome' => [
            'unit' => 'AdGuardHome.service',
            'name' => 'AdGuard Home DNS',
            'category' => 'security',
            'icon' => 'bi-shield-check',
            'color' => '#10b981',
            'description' => 'Proteksi DNS pemblokir iklan, pelacak, dan situs berbahaya'
        ],
        'tailscaled' => [
            'unit' => 'tailscaled.service',
            'name' => 'Tailscale Mesh VPN',
            'category' => 'network',
            'icon' => 'bi-link-45deg',
            'color' => '#6366f1',
            'description' => 'Jaringan VPN aman untuk akses remote jarak jauh tanpa port forwarding'
        ],
        'ocanap-telegram-bot' => [
            'unit' => 'ocanap-telegram-bot.service',
            'name' => 'Telegram Bot Gateway',
            'category' => 'apps',
            'icon' => 'bi-telegram',
            'color' => '#38bdf8',
            'description' => 'Daemon notifikasi dan kontrol dasbor interaktif via Telegram'
        ],
        'raspap-failover' => [
            'unit' => 'raspap-failover.service',
            'name' => 'Auto-Failover Monitor',
            'category' => 'network',
            'icon' => 'bi-arrow-left-right',
            'color' => '#ec4899',
            'description' => 'Monitor otomatis pergantian jalur internet WAN/Modem/Wi-Fi'
        ],
        'vnstat' => [
            'unit' => 'vnstat.service',
            'name' => 'vnStat Traffic Monitor',
            'category' => 'apps',
            'icon' => 'bi-graph-up-arrow',
            'color' => '#8b5cf6',
            'description' => 'Pencatat dan perekam histori konsumsi kuota bandwidth jaringan'
        ],
        'ssh' => [
            'unit' => 'ssh.service',
            'name' => 'OpenSSH Server',
            'category' => 'security',
            'icon' => 'bi-terminal-fill',
            'color' => '#64748b',
            'description' => 'Akses remote terminal konsol aman ke Linux Orange Pi (Port 22)'
        ],
        'cron' => [
            'unit' => 'cron.service',
            'name' => 'Cron Task Scheduler',
            'category' => 'apps',
            'icon' => 'bi-clock-history',
            'color' => '#ea580c',
            'description' => 'Penjadwal tugas dan eksekusi skrip otomatis latar belakang Linux'
        ],
        'php8.4-fpm' => [
            'unit' => 'php8.4-fpm.service',
            'name' => 'PHP 8.4 FastCGI Manager',
            'category' => 'web',
            'icon' => 'bi-filetype-php',
            'color' => '#8b5cf6',
            'description' => 'Mesin pemroses skrip backend Dasbor PHP Orange Pi'
        ],
        'lighttpd' => [
            'unit' => 'lighttpd.service',
            'name' => 'Lighttpd Web Server',
            'category' => 'web',
            'icon' => 'bi-hdd-network-fill',
            'color' => '#0284c7',
            'description' => 'Server web HTTP ringan untuk portal dan dasbor gateway'
        ],
        'bluetooth' => [
            'unit' => 'bluetooth.service',
            'name' => 'Bluetooth Daemon',
            'category' => 'system',
            'icon' => 'bi-bluetooth',
            'color' => '#3b82f6',
            'description' => 'Layanan konektivitas nirkabel Bluetooth perangkat'
        ]
    ];

    /**
     * Get All Managed Services Status & Metadata
     */
    public static function getServicesList(): array {
        $services = [];
        $runningCount = 0;
        $inactiveCount = 0;
        $failedCount = 0;

        foreach (self::$managedServices as $key => $meta) {
            $unit = $meta['unit'];

            // 1. Is-Active status
            $activeStatus = trim(@shell_exec("systemctl is-active " . escapeshellarg($unit) . " 2>/dev/null") ?: 'unknown');
            $isRunning = ($activeStatus === 'active');
            $isFailed = ($activeStatus === 'failed');

            if ($isRunning) $runningCount++;
            elseif ($isFailed) $failedCount++;
            else $inactiveCount++;

            // 2. Is-Enabled status on Boot
            $enabledStatus = trim(@shell_exec("systemctl is-enabled " . escapeshellarg($unit) . " 2>/dev/null") ?: 'unknown');
            $isBootEnabled = ($enabledStatus === 'enabled');

            // 3. Process Properties (PID, Memory, Uptime)
            $propsRaw = @shell_exec("systemctl show " . escapeshellarg($unit) . " --property=MainPID,ActiveEnterTimestamp,MemoryCurrent 2>/dev/null");
            $props = [];
            if ($propsRaw) {
                foreach (explode("\n", trim($propsRaw)) as $line) {
                    if (strpos($line, '=') !== false) {
                        list($k, $v) = explode('=', $line, 2);
                        $props[trim($k)] = trim($v);
                    }
                }
            }

            $pid = (int)($props['MainPID'] ?? 0);
            $memBytes = (int)($props['MemoryCurrent'] ?? 0);
            $memFormatted = ($memBytes > 0 && $memBytes < 18446744073709551615) ? round($memBytes / (1024 * 1024), 1) . ' MB' : '-';
            
            $since = $props['ActiveEnterTimestamp'] ?? '';
            $uptimeFormatted = '-';
            if ($isRunning && !empty($since)) {
                $sinceTime = strtotime($since);
                if ($sinceTime > 0) {
                    $diff = max(0, time() - $sinceTime);
                    if ($diff < 3600) {
                        $uptimeFormatted = round($diff / 60) . ' mnt';
                    } elseif ($diff < 86400) {
                        $uptimeFormatted = round($diff / 3600, 1) . ' jam';
                    } else {
                        $uptimeFormatted = round($diff / 86400, 1) . ' hari';
                    }
                }
            }

            $services[] = [
                'id' => $key,
                'unit' => $unit,
                'name' => $meta['name'],
                'category' => $meta['category'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
                'description' => $meta['description'],
                'status' => $activeStatus,
                'is_running' => $isRunning,
                'is_failed' => $isFailed,
                'boot_enabled' => $isBootEnabled,
                'boot_status' => $enabledStatus,
                'pid' => ($pid > 0) ? $pid : '-',
                'memory' => $memFormatted,
                'uptime' => $uptimeFormatted
            ];
        }

        return [
            'services' => $services,
            'summary' => [
                'total' => count($services),
                'running' => $runningCount,
                'inactive' => $inactiveCount,
                'failed' => $failedCount
            ]
        ];
    }

    /**
     * Safely Control a Linux Service
     */
    public static function controlService(string $serviceKey, string $action): array {
        if (!isset(self::$managedServices[$serviceKey])) {
            return ['success' => false, 'error' => "Layanan '{$serviceKey}' tidak terdaftar atau tidak diizinkan."];
        }

        $allowedActions = ['start', 'stop', 'restart', 'enable', 'disable'];
        $action = strtolower(trim($action));
        if (!in_array($action, $allowedActions)) {
            return ['success' => false, 'error' => "Aksi '{$action}' tidak valid."];
        }

        $unit = self::$managedServices[$serviceKey]['unit'];
        $name = self::$managedServices[$serviceKey]['name'];
        $escapedUnit = escapeshellarg($unit);

        $cmd = "systemctl {$action} {$escapedUnit} 2>&1";
        $out = @shell_exec($cmd);

        // Re-check status
        $newActive = trim(@shell_exec("systemctl is-active {$escapedUnit} 2>/dev/null") ?: 'unknown');
        $newEnabled = trim(@shell_exec("systemctl is-enabled {$escapedUnit} 2>/dev/null") ?: 'unknown');

        $actionLabels = [
            'start' => 'dijalankan',
            'stop' => 'dihentikan',
            'restart' => 'dimuat ulang',
            'enable' => 'diaktifkan saat boot',
            'disable' => 'dinonaktifkan dari boot'
        ];
        $actText = $actionLabels[$action] ?? $action;

        return [
            'success' => true,
            'message' => "Layanan {$name} berhasil {$actText}!",
            'unit' => $unit,
            'action' => $action,
            'is_running' => ($newActive === 'active'),
            'status' => $newActive,
            'boot_enabled' => ($newEnabled === 'enabled'),
            'output' => trim($out ?: '')
        ];
    }

    /**
     * Get Diagnostic Logs (journalctl) for a Service
     */
    public static function getServiceLogs(string $serviceKey, int $lines = 50): array {
        if (!isset(self::$managedServices[$serviceKey])) {
            return ['success' => false, 'error' => "Layanan '{$serviceKey}' tidak valid."];
        }

        $unit = self::$managedServices[$serviceKey]['unit'];
        $name = self::$managedServices[$serviceKey]['name'];
        $lines = max(10, min(200, $lines));
        $escapedUnit = escapeshellarg($unit);

        $cmd = "journalctl -u {$escapedUnit} -n {$lines} --no-pager 2>&1";
        $logs = @shell_exec($cmd);

        return [
            'success' => true,
            'service_name' => $name,
            'unit' => $unit,
            'lines_count' => $lines,
            'logs' => $logs ?: 'Tidak ada catatan log ditemukan untuk layanan ini.'
        ];
    }
}
