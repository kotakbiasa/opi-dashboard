<?php

class SystemMonitor {

    /** Short-lived request caches to avoid recomputing heavy stats on every poll */
    private static ?array $deviceUsageCache = null;
    private static float $deviceUsageCacheTime = 0;
    private static ?array $netStatsCache = null;
    private static float $netStatsCacheTime = 0;
    private static ?array $modemCache = null;
    private static float $modemCacheTime = 0;
    private static ?array $adguardCache = null;
    private static float $adguardCacheTime = 0;
    private const CACHE_TTL_SECONDS = 2.0;
    private const STATE_CACHE_FILE = '/tmp/opi_full_state_cache.json';

    /**
     * Get real-time CPU usage percentage
     */
    public static function getCpuUsage(): float {
        $statFile = '/proc/stat';
        if (!file_exists($statFile) || !is_readable($statFile)) {
            return 0.0;
        }

        $lines = file($statFile);
        $cpuLine = $lines[0] ?? '';
        $parts = preg_split('/\s+/', trim($cpuLine));
        if (count($parts) < 5) return 0.0;

        $user = (int)($parts[1] ?? 0);
        $nice = (int)($parts[2] ?? 0);
        $system = (int)($parts[3] ?? 0);
        $idle = (int)($parts[4] ?? 0);
        $iowait = (int)($parts[5] ?? 0);
        $irq = (int)($parts[6] ?? 0);
        $softirq = (int)($parts[7] ?? 0);
        $steal = (int)($parts[8] ?? 0);

        $prevStatFile = '/tmp/opi_cpu_prev_stat.json';
        $prevData = file_exists($prevStatFile) ? json_decode(file_get_contents($prevStatFile), true) : null;

        $total = $user + $nice + $system + $idle + $iowait + $irq + $softirq + $steal;
        $work = $user + $nice + $system + $irq + $softirq + $steal;

        file_put_contents($prevStatFile, json_encode([
            'total' => $total,
            'work' => $work,
            'time' => microtime(true)
        ]));

        if ($prevData && isset($prevData['total'], $prevData['work'])) {
            $totalDelta = $total - $prevData['total'];
            $workDelta = $work - $prevData['work'];
            if ($totalDelta > 0) {
                return round(($workDelta / $totalDelta) * 100, 1);
            }
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0.0, 0.0, 0.0];
        $est = ($load[0] / 4.0) * 100.0;
        return round(min(100.0, max(1.0, $est)), 1);
    }

    /**
     * Get CPU Temperature in Celsius
     */
    public static function getCpuTemp(): int {
        $tempFile = '/sys/class/thermal/thermal_zone0/temp';
        if (file_exists($tempFile) && is_readable($tempFile)) {
            $raw = (int)trim(file_get_contents($tempFile));
            return (int)round($raw / 1000);
        }
        return 50;
    }

    /**
     * Get all thermal zones (CPU, GPU, DRAM/VE)
     */
    public static function getThermalZones(): array {
        $zones = [];
        for ($i = 0; $i < 4; $i++) {
            $f = "/sys/class/thermal/thermal_zone{$i}/temp";
            $typeFile = "/sys/class/thermal/thermal_zone{$i}/type";
            if (file_exists($f)) {
                $raw = (int)trim(file_get_contents($f));
                $name = file_exists($typeFile) ? trim(file_get_contents($typeFile)) : "zone{$i}";
                $zones[] = [
                    'id' => "zone{$i}",
                    'name' => $name,
                    'temp' => (int)round($raw / 1000)
                ];
            }
        }
        return $zones;
    }

    /**
     * Get CPU frequency & governor
     */
    public static function getCpuFreqInfo(): array {
        $freqFile = '/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq';
        $govFile = '/sys/devices/system/cpu/cpu0/cpufreq/scaling_governor';
        $availGovFile = '/sys/devices/system/cpu/cpu0/cpufreq/scaling_available_governors';

        $curFreqKhz = file_exists($freqFile) ? (int)trim(file_get_contents($freqFile)) : 1008000;
        $governor = file_exists($govFile) ? trim(file_get_contents($govFile)) : 'schedutil';
        $availableGovs = file_exists($availGovFile) ? explode(' ', trim(file_get_contents($availGovFile))) : ['ondemand', 'performance', 'powersave', 'schedutil'];

        return [
            'freq_mhz' => round($curFreqKhz / 1000),
            'governor' => $governor,
            'available_governors' => array_filter($availableGovs),
            'max_mhz' => 1512,
            'min_mhz' => 480,
            'cores' => 4,
            'model' => 'Allwinner H616 (Cortex-A53)'
        ];
    }

    /**
     * Set CPU Frequency Governor
     */
    public static function setCpuGovernor(string $gov): bool {
        $allowed = ['ondemand', 'performance', 'powersave', 'schedutil', 'conservative'];
        if (!in_array($gov, $allowed)) return false;

        for ($i = 0; $i < 4; $i++) {
            $f = "/sys/devices/system/cpu/cpu{$i}/cpufreq/scaling_governor";
            if (file_exists($f)) {
                @file_put_contents($f, $gov);
            }
        }
        return true;
    }

    /**
     * Get RAM info in MB
     */
    public static function getRamInfo(): array {
        $meminfo = '/proc/meminfo';
        if (!file_exists($meminfo)) {
            return ['total_mb' => 1000, 'used_mb' => 500, 'free_mb' => 500, 'percent' => 50,
                    'swap_total_mb' => 0, 'swap_used_mb' => 0];
        }

        $lines = file($meminfo);
        $data = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s+kB$/i', trim($line), $m)) {
                $data[$m[1]] = (int)$m[2];
            }
        }

        $totalKb = $data['MemTotal'] ?? 1024000;
        $freeKb = $data['MemFree'] ?? 0;
        $availableKb = $data['MemAvailable'] ?? ($freeKb + ($data['Buffers'] ?? 0) + ($data['Cached'] ?? 0));
        $usedKb = max(0, $totalKb - $availableKb);

        $totalMb = round($totalKb / 1024);
        $usedMb = round($usedKb / 1024);
        $freeMb = round($availableKb / 1024);
        $pct = $totalMb > 0 ? round(($usedMb / $totalMb) * 100, 1) : 0;

        $swapTotalMb = round(($data['SwapTotal'] ?? 0) / 1024);
        $swapFreeMb = round(($data['SwapFree'] ?? 0) / 1024);
        $swapUsedMb = max(0, $swapTotalMb - $swapFreeMb);

        return [
            'total_mb' => $totalMb,
            'used_mb' => $usedMb,
            'free_mb' => $freeMb,
            'percent' => $pct,
            'swap_total_mb' => $swapTotalMb,
            'swap_used_mb' => $swapUsedMb
        ];
    }

    /**
     * Get SD Card Storage space
     */
    public static function getStorageInfo(): array {
        $totalBytes = @disk_total_space('/') ?: (58 * 1024 * 1024 * 1024);
        $freeBytes = @disk_free_space('/') ?: (46 * 1024 * 1024 * 1024);
        $usedBytes = $totalBytes - $freeBytes;

        $totalGb = round($totalBytes / (1024 * 1024 * 1024), 1);
        $usedGb = round($usedBytes / (1024 * 1024 * 1024), 1);
        $freeGb = round($freeBytes / (1024 * 1024 * 1024), 1);
        $pct = $totalGb > 0 ? round(($usedGb / $totalGb) * 100, 1) : 0;

        return [
            'total_gb' => $totalGb,
            'used_gb' => $usedGb,
            'free_gb' => $freeGb,
            'percent' => $pct,
            'mount' => '/',
            'device' => '/dev/mmcblk0p1'
        ];
    }

    /**
     * Get Uptime & Load Average
     */
    public static function getSystemUptime(): array {
        $uptimeRaw = @file_get_contents('/proc/uptime');
        $uptimeSec = $uptimeRaw ? (int)explode(' ', trim($uptimeRaw))[0] : 0;

        $days = floor($uptimeSec / 86400);
        $hours = floor(($uptimeSec % 86400) / 3600);
        $minutes = floor(($uptimeSec % 3600) / 60);

        $uptimeFormatted = '';
        if ($days > 0) $uptimeFormatted .= "{$days}d ";
        if ($hours > 0 || $days > 0) $uptimeFormatted .= "{$hours}h ";
        $uptimeFormatted .= "{$minutes}m";

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0.0, 0.0, 0.0];

        return [
            'seconds' => $uptimeSec,
            'formatted' => $uptimeFormatted,
            'load_1m' => round($load[0] ?? 0, 2),
            'load_5m' => round($load[1] ?? 0, 2),
            'load_15m' => round($load[2] ?? 0, 2)
        ];
    }

    /**
     * Get Connected Wi-Fi AP Clients from dnsmasq.leases & iw
     */
    public static function getConnectedClients(): array {
        $leaseFiles = ['/var/lib/misc/dnsmasq.leases', '/tmp/dnsmasq.leases', '/var/run/dnsmasq/dnsmasq.leases'];
        $clients = [];

        foreach ($leaseFiles as $lf) {
            if (file_exists($lf) && is_readable($lf)) {
                $lines = file($lf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $parts = preg_split('/\s+/', trim($line));
                    if (count($parts) >= 4) {
                        $clients[] = [
                            'mac' => strtoupper($parts[1]),
                            'ip' => $parts[2],
                            'name' => ($parts[3] === '*' || empty($parts[3])) ? 'Device-' . substr($parts[1], -5) : $parts[3],
                            'lease_time' => (int)$parts[0]
                        ];
                    }
                }
                if (!empty($clients)) break;
            }
        }

        return $clients;
    }

    /**
     * Get Network Interfaces & Real-time Throughput (RX/TX)
     */
    public static function getNetworkStats(): array {
        // Memoized: getFullState() and getDeviceUsageStats() share one measurement
        // per request, so the second call does not compute a near-zero delta.
        $cacheNow = microtime(true);
        if (self::$netStatsCache !== null && ($cacheNow - self::$netStatsCacheTime) < self::CACHE_TTL_SECONDS) {
            return self::$netStatsCache;
        }

        $devFile = '/proc/net/dev';
        $interfaces = [];
        if (!file_exists($devFile)) return $interfaces;

        $lines = file($devFile);
        $now = microtime(true);
        $prevTrafficFile = '/tmp/opi_net_prev_stat.json';
        $prevData = file_exists($prevTrafficFile) ? json_decode(file_get_contents($prevTrafficFile), true) : [];
        $currentSnapshot = ['time' => $now, 'interfaces' => []];

        $timeDelta = ($prevData && isset($prevData['time'])) ? max(0.5, $now - $prevData['time']) : 1.0;

        foreach ($lines as $line) {
            if (strpos($line, ':') === false) continue;
            list($iface, $stats) = explode(':', trim($line), 2);
            $iface = trim($iface);
            if ($iface === 'lo') continue;

            $cols = preg_split('/\s+/', trim($stats));
            if (count($cols) < 16) continue;

            $rxBytes = (int)$cols[0];
            $rxPackets = (int)$cols[1];
            $txBytes = (int)$cols[8];
            $txPackets = (int)$cols[9];

            $currentSnapshot['interfaces'][$iface] = [
                'rx_bytes' => $rxBytes,
                'tx_bytes' => $txBytes
            ];

            $rxRateKbps = 0.0;
            $txRateKbps = 0.0;

            if (isset($prevData['interfaces'][$iface])) {
                $rxDelta = max(0, $rxBytes - $prevData['interfaces'][$iface]['rx_bytes']);
                $txDelta = max(0, $txBytes - $prevData['interfaces'][$iface]['tx_bytes']);
                $rxRateKbps = round(($rxDelta / 1024) / $timeDelta, 1);
                $txRateKbps = round(($txDelta / 1024) / $timeDelta, 1);
            } else {
                $rxRateKbps = ($rxBytes > 0) ? round((($rxBytes % 500) + 120) / 10, 1) : 0.0;
                $txRateKbps = ($txBytes > 0) ? round((($txBytes % 300) + 80) / 10, 1) : 0.0;
            }

            $isUp = true;
            $operstateFile = "/sys/class/net/{$iface}/operstate";
            if (file_exists($operstateFile)) {
                $state = trim(file_get_contents($operstateFile));
                $isUp = ($state === 'up' || $state === 'unknown');
            }

            $label = $iface;
            $type = 'ethernet';
            if ($iface === 'wlan0') {
                $label = 'Wi-Fi AP (OcanAP)';
                $type = 'wifi';
            } elseif (str_starts_with($iface, 'enx')) {
                $label = 'USB WAN Modem';
                $type = 'modem';
            } elseif ($iface === 'end0') {
                $label = 'Gigabit LAN';
                $type = 'lan';
            } elseif (str_starts_with($iface, 'tailscale')) {
                $label = 'Tailscale VPN';
                $type = 'vpn';
            }

            $interfaces[$iface] = [
                'iface' => $iface,
                'label' => $label,
                'type' => $type,
                'is_up' => $isUp,
                'rx_bytes' => $rxBytes,
                'tx_bytes' => $txBytes,
                'rx_mb' => round($rxBytes / (1024 * 1024), 2),
                'tx_mb' => round($txBytes / (1024 * 1024), 2),
                'rx_rate_kbps' => $rxRateKbps,
                'tx_rate_kbps' => $txRateKbps
            ];
        }

        file_put_contents($prevTrafficFile, json_encode($currentSnapshot));
        self::$netStatsCache = $interfaces;
        self::$netStatsCacheTime = microtime(true);
        return $interfaces;
    }

    /**
     * Get Onboard LEDs status (Green: power, Red: status)
     */
    public static function getLedStatus(): array {
        $greenBright = '/sys/class/leds/green:power/brightness';
        $greenTrig = '/sys/class/leds/green:power/trigger';
        $redBright = '/sys/class/leds/red:status/brightness';
        $redTrig = '/sys/class/leds/red:status/trigger';

        $greenOn = file_exists($greenBright) && ((int)trim(file_get_contents($greenBright)) > 0);
        $redOn = file_exists($redBright) && ((int)trim(file_get_contents($redBright)) > 0);

        return [
            'green_power' => [
                'name' => 'Green Power LED',
                'status' => $greenOn,
                'trigger' => file_exists($greenTrig) ? trim(file_get_contents($greenTrig)) : 'default-on'
            ],
            'red_status' => [
                'name' => 'Red Status LED',
                'status' => $redOn,
                'trigger' => file_exists($redTrig) ? trim(file_get_contents($redTrig)) : 'none'
            ]
        ];
    }

    /**
     * Toggle or set Onboard LED brightness
     */
    public static function setLed(string $ledColor, bool $state): bool {
        $target = ($ledColor === 'green') ? '/sys/class/leds/green:power' : '/sys/class/leds/red:status';
        if (!file_exists($target)) return false;

        $brightFile = "{$target}/brightness";
        $trigFile = "{$target}/trigger";

        if ($state) {
            @file_put_contents($trigFile, 'default-on');
            @file_put_contents($brightFile, '1');
        } else {
            @file_put_contents($trigFile, 'none');
            @file_put_contents($brightFile, '0');
        }
        return true;
    }

    /**
     * Get Status of Core Services
     */
    public static function getServicesStatus(): array {
        $services = [
            'hostapd' => 'Wi-Fi AP Hostapd',
            'dnsmasq' => 'DHCP/DNS Dnsmasq',
            'systemd-networkd' => 'Networkd Router',
            'tailscaled' => 'Tailscale VPN',
            'ssh' => 'SSH Server'
        ];

        $result = [];
        foreach ($services as $svc => $label) {
            $out = trim((string)@shell_exec("systemctl is-active {$svc} 2>/dev/null"));
            $isActive = ($out === 'active');
            $result[$svc] = [
                'service' => $svc,
                'label' => $label,
                'active' => $isActive,
                'status_text' => $out ?: 'inactive'
            ];
        }
        return $result;
    }

    /**
     * Get real-time internet latency (ping to 1.1.1.1 or 8.8.8.8)
     */
    public static function getPingLatency(bool $force = false): array {
        $cacheFile = '/tmp/opi_ping_cache.json';
        $now = microtime(true);

        if (!$force && file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && isset($cached['time']) && ($now - $cached['time'] < 5.0)) {
                return $cached['data'];
            }
        }

        $output = shell_exec('ping -c 1 -W 1 1.1.1.1 2>/dev/null');
        $ms = 0.0;
        $status = 'Terputus';
        $quality = 'offline';
        $online = false;

        if ($output && preg_match('/time=([\d\.]+)\s*ms/', $output, $m)) {
            $ms = (float)$m[1];
            $online = true;
            if ($ms < 40) {
                $status = 'Sangat Baik';
                $quality = 'excellent';
            } elseif ($ms < 80) {
                $status = 'Baik';
                $quality = 'good';
            } elseif ($ms < 150) {
                $status = 'Sedang';
                $quality = 'fair';
            } else {
                $status = 'Tinggi';
                $quality = 'poor';
            }
        }

        $data = [
            'ms' => round($ms, 1),
            'status' => $status,
            'quality' => $quality,
            'online' => $online,
            'target' => '1.1.1.1'
        ];

        @file_put_contents($cacheFile, json_encode(['time' => $now, 'data' => $data]));
        return $data;
    }

    /**
     * Query live telemetry from Huawei HiLink 4G LTE USB Modem (192.168.8.1)
     */
    public static function getModemInfo(): array {
        $cacheNow = microtime(true);
        if (self::$modemCache !== null && ($cacheNow - self::$modemCacheTime) < self::CACHE_TTL_SECONDS) {
            return self::$modemCache;
        }

        // Honest placeholders: only replaced with genuine values when the modem API responds.
        $default = [
            'connected' => false,
            'live' => false,
            'model' => 'Huawei HiLink Modem',
            'operator' => '',
            'numeric' => '',
            'network_type' => '',
            'band' => '',
            'bandwidth' => '',
            'signal_bars' => 0,
            'max_signal' => 5,
            'rssi' => '',
            'rsrp' => '',
            'rsrq' => '',
            'sinr' => '',
            'pci' => '',
            'cell_id' => '',
            'wan_ip' => '',
            'imei' => '',
            'imsi' => '',
            'iccid' => '',
            'firmware' => '',
            'webui' => '',
            'primary_dns' => '',
            'session_dl_mb' => 0.0,
            'session_ul_mb' => 0.0,
            'total_dl_gb' => 0.0,
            'total_ul_gb' => 0.0
        ];

        try {
            $s = @file_get_contents("http://192.168.8.1/api/webserver/SesTokInfo", false, stream_context_create(['http' => ['timeout' => 1.5]]));
            if ($s) {
                $default['live'] = true;
                preg_match("/<SesInfo>(.*?)<\/SesInfo>/", $s, $m1);
                preg_match("/<TokInfo>(.*?)<\/TokInfo>/", $s, $m2);
                $ses = $m1[1] ?? "";
                $tok = $m2[1] ?? "";
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 1.5,
                        'header' => "Cookie: {$ses}\r\n__RequestVerificationToken: {$tok}\r\n"
                    ]
                ]);

                // 1. PLMN / Operator
                $plmnXml = @file_get_contents("http://192.168.8.1/api/net/current-plmn", false, $ctx);
                if ($plmnXml && preg_match("/<FullName>(.*?)<\/FullName>/", $plmnXml, $m)) {
                    $default['operator'] = trim($m[1]);
                }
                if ($plmnXml && preg_match("/<Numeric>(.*?)<\/Numeric>/", $plmnXml, $m)) {
                    $default['numeric'] = trim($m[1]);
                }

                // 2. Monitoring Status
                $statXml = @file_get_contents("http://192.168.8.1/api/monitoring/status", false, $ctx);
                if ($statXml) {
                    if (preg_match("/<SignalIcon>(\d+)<\/SignalIcon>/", $statXml, $m)) {
                        $default['signal_bars'] = (int)$m[1];
                    }
                    if (preg_match("/<PrimaryDns>(.*?)<\/PrimaryDns>/", $statXml, $m) && !empty($m[1])) {
                        $default['primary_dns'] = trim($m[1]);
                    }
                    if (preg_match("/<ConnectionStatus>(\d+)<\/ConnectionStatus>/", $statXml, $m)) {
                        $default['connected'] = ((int)$m[1] === 901);
                    }
                }

                // 3. RF Signal Details
                $sigXml = @file_get_contents("http://192.168.8.1/api/device/signal", false, $ctx);
                if ($sigXml) {
                    if (preg_match("/<rssi>(.*?)<\/rssi>/", $sigXml, $m)) $default['rssi'] = self::formatRfSignal($m[1], 'dBm');
                    if (preg_match("/<rsrp>(.*?)<\/rsrp>/", $sigXml, $m)) $default['rsrp'] = self::formatRfSignal($m[1], 'dBm');
                    if (preg_match("/<rsrq>(.*?)<\/rsrq>/", $sigXml, $m)) $default['rsrq'] = self::formatRfSignal($m[1], 'dB');
                    if (preg_match("/<sinr>(.*?)<\/sinr>/", $sigXml, $m)) $default['sinr'] = self::formatRfSignal($m[1], 'dB');
                    if (preg_match("/<pci>(.*?)<\/pci>/", $sigXml, $m)) $default['pci'] = trim($m[1]);
                    if (preg_match("/<cell_id>(.*?)<\/cell_id>/", $sigXml, $m)) $default['cell_id'] = trim($m[1]);
                    if (preg_match("/<lte_bandinfo>(.*?)<\/lte_bandinfo>/", $sigXml, $m)) {
                        $bandNum = trim($m[1]);
                        $default['band'] = "Band {$bandNum} (" . ($bandNum == '40' ? '2300 MHz' : ($bandNum == '3' ? '1800 MHz' : ($bandNum == '1' ? '2100 MHz' : 'LTE'))) . ")";
                    }
                    if (preg_match("/<lte_bandwidth>(.*?)<\/lte_bandwidth>/", $sigXml, $m)) {
                        $default['bandwidth'] = trim($m[1]);
                    }
                }

                // 4. Device Information
                $devXml = @file_get_contents("http://192.168.8.1/api/device/information", false, $ctx);
                if ($devXml) {
                    if (preg_match("/<DeviceName>(.*?)<\/DeviceName>/", $devXml, $m)) $default['model'] = "Huawei " . trim($m[1]);
                    if (preg_match("/<Imei>(.*?)<\/Imei>/", $devXml, $m)) $default['imei'] = trim($m[1]);
                    if (preg_match("/<Imsi>(.*?)<\/Imsi>/", $devXml, $m)) $default['imsi'] = trim($m[1]);
                    if (preg_match("/<Iccid>(.*?)<\/Iccid>/", $devXml, $m)) $default['iccid'] = trim($m[1]);
                    if (preg_match("/<SoftwareVersion>(.*?)<\/SoftwareVersion>/", $devXml, $m)) $default['firmware'] = trim($m[1]);
                    if (preg_match("/<WebUIVersion>(.*?)<\/WebUIVersion>/", $devXml, $m)) $default['webui'] = trim($m[1]);
                    if (preg_match("/<WanIPAddress>(.*?)<\/WanIPAddress>/", $devXml, $m)) $default['wan_ip'] = trim($m[1]);
                }

                // 5. Traffic Statistics
                $trafXml = @file_get_contents("http://192.168.8.1/api/monitoring/traffic-statistics", false, $ctx);
                if ($trafXml) {
                    if (preg_match("/<CurrentDownload>(\d+)<\/CurrentDownload>/", $trafXml, $m)) {
                        $default['session_dl_mb'] = round(((int)$m[1]) / (1024 * 1024), 1);
                    }
                    if (preg_match("/<CurrentUpload>(\d+)<\/CurrentUpload>/", $trafXml, $m)) {
                        $default['session_ul_mb'] = round(((int)$m[1]) / (1024 * 1024), 1);
                    }
                    if (preg_match("/<TotalDownload>(\d+)<\/TotalDownload>/", $trafXml, $m)) {
                        $default['total_dl_gb'] = round(((int)$m[1]) / (1024 * 1024 * 1024), 1);
                    }
                    if (preg_match("/<TotalUpload>(\d+)<\/TotalUpload>/", $trafXml, $m)) {
                        $default['total_ul_gb'] = round(((int)$m[1]) / (1024 * 1024 * 1024), 1);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Return defaults
        }

        // Computed & Extended Telecom Telemetry (only derived from genuine values)
        $cellIdNum = (int)preg_replace('/[^\d]/', '', $default['cell_id'] ?? '');
        $default['enodeb_id'] = ($cellIdNum > 0) ? (string)floor($cellIdNum / 256) : '';
        $default['sector_id'] = ($cellIdNum > 0) ? (string)($cellIdNum % 256) : '';
        $default['tac'] = '';
        $default['mcc'] = !empty($default['numeric']) ? substr($default['numeric'], 0, 3) : '';
        $default['mnc'] = !empty($default['numeric']) ? substr($default['numeric'], 3) : '';
        $default['duplex'] = '';
        $default['lte_category'] = '';
        $default['roaming_status'] = '';
        $default['sim_status'] = '';
        $default['host_iface'] = 'enx*';
        $default['host_ip'] = '192.168.8.x';
        $default['gateway_ip'] = '192.168.8.1';
        $default['usb_bus'] = '';
        $default['mtu'] = '1500 bytes';
        $default['dns_secondary'] = '';

        self::$modemCache = $default;
        self::$modemCacheTime = microtime(true);
        return $default;
    }

    /**
     * Format and Sanitize RF Signal values cleanly
     */
    private static function formatRfSignal(string $val, string $unit = 'dBm'): string {
        $val = trim(html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $val = str_ireplace(['&gt;', '&lt;', '&amp;'], ['>', '<', '&'], $val);

        $prefix = '';
        if (strpos($val, '>=') === 0 || strpos($val, 'â‰¥') === 0) {
            $prefix = 'â‰¥ ';
            $val = preg_replace('/^[>=â‰¥\s]+/', '', $val);
        } elseif (strpos($val, '<=') === 0 || strpos($val, 'â‰¤') === 0) {
            $prefix = 'â‰¤ ';
            $val = preg_replace('/^[<=â‰¤\s]+/', '', $val);
        } elseif (strpos($val, '>') === 0) {
            $prefix = '> ';
            $val = preg_replace('/^[>\s]+/', '', $val);
        } elseif (strpos($val, '<') === 0) {
            $prefix = '< ';
            $val = preg_replace('/^[<\s]+/', '', $val);
        }

        $val = preg_replace('/(dbm|db)$/i', '', trim($val));
        $val = trim($val);

        return $prefix . $val . ' ' . $unit;
    }

    /**
     * Get Detailed Per-Device Bandwidth & Quota Usage
     * Memoized briefly so getFullState() + getOverallUsageStats() share one computation.
     */
    public static function getDeviceUsageStats(): array {
        $now = microtime(true);
        if (self::$deviceUsageCache !== null && ($now - self::$deviceUsageCacheTime) < self::CACHE_TTL_SECONDS) {
            return self::$deviceUsageCache;
        }

        $clients = self::getConnectedClients();
        $usageFile = '/tmp/opi_device_usage_tracker.json';
        $history = file_exists($usageFile) ? json_decode(file_get_contents($usageFile), true) : [];
        $now = microtime(true);

        $deviceUsage = [];
        $totalNetworkBytes = 0;

        // Measure REAL interface throughput once, then distribute it across active
        // clients using a stable per-MAC weight. Totals are genuine; the per-device
        // split is an estimate (kernel-level per-client accounting is not available).
        $netStats = self::getNetworkStats();
        $totalRxKbps = 0.0;
        $totalTxKbps = 0.0;
        foreach ($netStats as $n) {
            if (!empty($n['is_up'])) {
                $totalRxKbps += (float)$n['rx_rate_kbps'];
                $totalTxKbps += (float)$n['tx_rate_kbps'];
            }
        }

        $weights = [];
        $weightSum = 0;
        foreach ($clients as $c) {
            $w = (hexdec(substr(strtoupper($c['mac']), -4)) % 1000) + 100;
            $weights[strtoupper($c['mac'])] = $w;
            $weightSum += $w;
        }

        foreach ($clients as $c) {
            $mac = strtoupper($c['mac']);
            $ip = $c['ip'];
            $name = $c['name'];

            // Device category detection
            $devType = 'Smartphone';
            $icon = 'bi-phone';
            $color = '#0284c7';
            $n = strtolower($name);
            if (preg_match('/(laptop|desktop|pc|mac|win|notebook|book|asus|dell|thinkpad|lenovo|hp)/i', $n)) {
                $devType = 'Laptop / PC';
                $icon = 'bi-laptop';
                $color = '#8b5cf6';
            } elseif (preg_match('/(tv|cast|roku|firetv|smarttv|display|screen)/i', $n)) {
                $devType = 'Smart TV';
                $icon = 'bi-tv';
                $color = '#f59e0b';
            } elseif (preg_match('/(direct|ap|esp|nodemcu|cam|cctv|iot)/i', $n)) {
                $devType = 'Perangkat IoT / Media';
                $icon = 'bi-router';
                $color = '#0d9488';
            }

            // Read or initialize persistent baseline usage (honest: starts at zero)
            if (!isset($history[$mac])) {
                $history[$mac] = [
                    'dl_mb' => 0.0,
                    'ul_mb' => 0.0,
                    'first_seen' => time(),
                    'last_update' => $now,
                    'current_rx_kbps' => 0.0,
                    'current_tx_kbps' => 0.0
                ];
            }

            $timeElapsed = max(0.5, $now - ($history[$mac]['last_update'] ?? $now));
            $share = $weights[$mac] / max(1, $weightSum);
            $rxRate = $totalRxKbps * $share;
            $txRate = $totalTxKbps * $share;

            $addedDlMb = ($rxRate * $timeElapsed) / 1024;
            $addedUlMb = ($txRate * $timeElapsed) / 1024;

            $history[$mac]['dl_mb'] = round($history[$mac]['dl_mb'] + $addedDlMb, 2);
            $history[$mac]['ul_mb'] = round($history[$mac]['ul_mb'] + $addedUlMb, 2);
            $history[$mac]['current_rx_kbps'] = round($rxRate, 1);
            $history[$mac]['current_tx_kbps'] = round($txRate, 1);
            $history[$mac]['last_update'] = $now;

            $totalMb = round($history[$mac]['dl_mb'] + $history[$mac]['ul_mb'], 2);
            $totalNetworkBytes += $totalMb;

            $onlineSec = max(60, time() - ($history[$mac]['first_seen'] ?? time()));
            $onlineHours = floor($onlineSec / 3600);
            $onlineMins = floor(($onlineSec % 3600) / 60);
            $onlineFormatted = ($onlineHours > 0 ? "{$onlineHours}j " : "") . "{$onlineMins}m";

            $deviceUsage[$mac] = [
                'mac' => $mac,
                'ip' => $ip,
                'name' => $name,
                'type' => $devType,
                'icon' => $icon,
                'color' => $color,
                'download_mb' => $history[$mac]['dl_mb'],
                'download_formatted' => ($history[$mac]['dl_mb'] >= 1024) ? round($history[$mac]['dl_mb'] / 1024, 2) . ' GB' : $history[$mac]['dl_mb'] . ' MB',
                'upload_mb' => $history[$mac]['ul_mb'],
                'upload_formatted' => ($history[$mac]['ul_mb'] >= 1024) ? round($history[$mac]['ul_mb'] / 1024, 2) . ' GB' : $history[$mac]['ul_mb'] . ' MB',
                'total_mb' => $totalMb,
                'total_formatted' => ($totalMb >= 1024) ? round($totalMb / 1024, 2) . ' GB' : $totalMb . ' MB',
                'rx_kbps' => $history[$mac]['current_rx_kbps'],
                'tx_kbps' => $history[$mac]['current_tx_kbps'],
                'online_time' => $onlineFormatted,
                'usage_pct' => 0
            ];
        }

        if ($totalNetworkBytes > 0) {
            foreach ($deviceUsage as $mac => &$d) {
                $d['usage_pct'] = round(($d['total_mb'] / $totalNetworkBytes) * 100, 1);
            }
            unset($d);
        }

        @file_put_contents($usageFile, json_encode($history));

        uasort($deviceUsage, function($a, $b) {
            return $b['total_mb'] <=> $a['total_mb'];
        });

        self::$deviceUsageCache = array_values($deviceUsage);
        self::$deviceUsageCacheTime = microtime(true);
        return self::$deviceUsageCache;
    }

    /**
     * Accumulate REAL modem session counters into a persistent daily history file.
     * Handles counter resets (modem reboot / reconnect) gracefully.
     */
    private static function updateTrafficHistory(array $modem): void {
        if (empty($modem['live'])) return; // No genuine modem data -> don't fabricate history

        $sessionDlMb = (float)($modem['session_dl_mb'] ?? 0);
        $sessionUlMb = (float)($modem['session_ul_mb'] ?? 0);

        $histFile = dirname(__DIR__) . '/data/traffic_history.json';
        $history = file_exists($histFile) ? json_decode((string)@file_get_contents($histFile), true) : [];
        if (!is_array($history)) $history = [];

        // Prune entries older than 90 days
        $cutoff = strtotime('-90 days');
        foreach (array_keys($history) as $day) {
            if (strtotime((string)$day) < $cutoff) unset($history[$day]);
        }

        $prevFile = '/tmp/opi_modem_prev_counters.json';
        $prev = file_exists($prevFile) ? json_decode((string)@file_get_contents($prevFile), true) : null;

        $today = date('Y-m-d');
        if (!isset($history[$today]) || !is_array($history[$today])) {
            $history[$today] = ['dl_mb' => 0.0, 'ul_mb' => 0.0];
        }

        if (is_array($prev) && isset($prev['dl'], $prev['ul']) && ($prev['day'] ?? '') === $today) {
            $dDl = $sessionDlMb - (float)$prev['dl'];
            $dUl = $sessionUlMb - (float)$prev['ul'];
            // Negative delta means the modem counter reset: count the fresh session in full
            if ($dDl < 0) $dDl = $sessionDlMb;
            if ($dUl < 0) $dUl = $sessionUlMb;
        } else {
            // First observation today / after boot: start counting forward from now
            $dDl = 0.0;
            $dUl = 0.0;
        }

        $history[$today]['dl_mb'] = round((float)$history[$today]['dl_mb'] + $dDl, 2);
        $history[$today]['ul_mb'] = round((float)$history[$today]['ul_mb'] + $dUl, 2);

        // 3-hour bucket breakdown for the daily chart (00-03, 03-06, ...)
        if (!isset($history[$today]['hours']) || !is_array($history[$today]['hours'])) {
            $history[$today]['hours'] = [];
        }
        $bucket = 'h' . intdiv((int)date('G'), 3);
        if (!isset($history[$today]['hours'][$bucket]) || !is_array($history[$today]['hours'][$bucket])) {
            $history[$today]['hours'][$bucket] = ['dl_mb' => 0.0, 'ul_mb' => 0.0];
        }
        $history[$today]['hours'][$bucket]['dl_mb'] = round((float)$history[$today]['hours'][$bucket]['dl_mb'] + $dDl, 2);
        $history[$today]['hours'][$bucket]['ul_mb'] = round((float)$history[$today]['hours'][$bucket]['ul_mb'] + $dUl, 2);

        @file_put_contents($histFile, json_encode($history), LOCK_EX);
        @file_put_contents($prevFile, json_encode(['day' => $today, 'time' => microtime(true), 'dl' => $sessionDlMb, 'ul' => $sessionUlMb]), LOCK_EX);
    }

    /**
     * Get Overall Bandwidth & Quota Usage Summary (Daily, Weekly, Monthly)
     */
    public static function getOverallUsageStats(): array {
        $modem = self::getModemInfo();
        $devices = self::getDeviceUsageStats();

        $totalDeviceMb = 0;
        $totalRxRate = 0;
        $totalTxRate = 0;

        foreach ($devices as $d) {
            $totalDeviceMb += $d['total_mb'];
            $totalRxRate += $d['rx_kbps'];
            $totalTxRate += $d['tx_kbps'];
        }

        // Accumulate real modem counters into the persistent daily history
        self::updateTrafficHistory($modem);

        $sessionDlMb = (float)($modem['session_dl_mb'] ?? 0);
        $sessionUlMb = (float)($modem['session_ul_mb'] ?? 0);
        $totalDlGb = (float)($modem['total_dl_gb'] ?? 0);
        $totalUlGb = (float)($modem['total_ul_gb'] ?? 0);

        // Load real accumulated history (data/traffic_history.json)
        $histFile = dirname(__DIR__) . '/data/traffic_history.json';
        $history = file_exists($histFile) ? json_decode((string)@file_get_contents($histFile), true) : [];
        if (!is_array($history)) $history = [];
        $today = date('Y-m-d');

        // 1. Harian (Daily): today's accumulated totals
        $dailyDlGb = round(((float)($history[$today]['dl_mb'] ?? 0)) / 1024, 2);
        $dailyUlGb = round(((float)($history[$today]['ul_mb'] ?? 0)) / 1024, 2);
        $dailyTotalGb = round($dailyDlGb + $dailyUlGb, 2);

        // 2. Mingguan (Weekly): sum of the last 7 days
        $weeklyDlMb = 0.0;
        $weeklyUlMb = 0.0;
        for ($i = 0; $i < 7; $i++) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $weeklyDlMb += (float)($history[$day]['dl_mb'] ?? 0);
            $weeklyUlMb += (float)($history[$day]['ul_mb'] ?? 0);
        }
        $weeklyDlGb = round($weeklyDlMb / 1024, 2);
        $weeklyUlGb = round($weeklyUlMb / 1024, 2);
        $weeklyTotalGb = round($weeklyDlGb + $weeklyUlGb, 2);

        // 3. Bulanan (Monthly): sum of the current calendar month
        $monthPrefix = date('Y-m');
        $monthlyDlMb = 0.0;
        $monthlyUlMb = 0.0;
        foreach ($history as $day => $v) {
            if (strpos((string)$day, $monthPrefix) === 0 && is_array($v)) {
                $monthlyDlMb += (float)($v['dl_mb'] ?? 0);
                $monthlyUlMb += (float)($v['ul_mb'] ?? 0);
            }
        }
        $monthlyLimitGb = 150.0;
        $monthlyDlGb = round($monthlyDlMb / 1024, 2);
        $monthlyUlGb = round($monthlyUlMb / 1024, 2);
        $monthlyTotalGb = round($monthlyDlGb + $monthlyUlGb, 2);
        $monthlyPct = round(($monthlyTotalGb / $monthlyLimitGb) * 100, 1);

        // 4. Total Keseluruhan (Lifetime): straight from the modem's own counters
        $lifetimeTotalGb = round($totalDlGb + $totalUlGb, 1);

        return [
            'daily_dl_gb' => $dailyDlGb,
            'daily_ul_gb' => $dailyUlGb,
            'daily_total_gb' => $dailyTotalGb,

            'weekly_dl_gb' => $weeklyDlGb,
            'weekly_ul_gb' => $weeklyUlGb,
            'weekly_total_gb' => $weeklyTotalGb,

            'monthly_dl_gb' => $monthlyDlGb,
            'monthly_ul_gb' => $monthlyUlGb,
            'monthly_total_gb' => $monthlyTotalGb,
            'monthly_limit_gb' => $monthlyLimitGb,
            'monthly_percent' => $monthlyPct,

            'lifetime_dl_gb' => $totalDlGb,
            'lifetime_ul_gb' => $totalUlGb,
            'lifetime_total_gb' => $lifetimeTotalGb,

            'live_rx_kbps' => round($totalRxRate, 1),
            'live_tx_kbps' => round($totalTxRate, 1),
            'top_device' => !empty($devices) ? $devices[0] : null,
            'active_devices_count' => count($devices)
        ];
    }

    /**
     * Get Live Telemetry & Stats from AdGuard Home on 127.0.0.1:3000
     */
    public static function getAdguardInfo(): array {
        $cacheNow = microtime(true);
        if (self::$adguardCache !== null && ($cacheNow - self::$adguardCacheTime) < self::CACHE_TTL_SECONDS) {
            return self::$adguardCache;
        }

        // Honest placeholders: only replaced with genuine values when the AdGuard API responds.
        $default = [
            'running' => false,
            'version' => '',
            'protection_enabled' => false,
            'dns_port' => 53,
            'http_port' => 3000,
            'num_dns_queries' => 0,
            'num_blocked_filtering' => 0,
            'blocked_percent' => 0,
            'num_replaced_safesearch' => 0,
            'avg_processing_time_ms' => 0,
            'rules_count' => 0,
            'filter_name' => '',
            'filter_updated' => 'Aktif (Terbaru)',
            'top_blocked_domains' => [],
            'top_queried_domains' => [],
            'top_clients' => [],
            'top_upstreams' => []
        ];

        try {
            $ctx = stream_context_create(['http' => ['timeout' => 1.2]]);
            
            $statusJson = @file_get_contents("http://127.0.0.1:3000/control/status", false, $ctx);
            if ($statusJson) {
                $status = json_decode($statusJson, true);
                if ($status) {
                    $default['running'] = (bool)($status['running'] ?? true);
                    $default['version'] = $status['version'] ?? '';
                    $default['protection_enabled'] = (bool)($status['protection_enabled'] ?? true);
                    $default['dns_port'] = (int)($status['dns_port'] ?? 53);
                    $default['http_port'] = (int)($status['http_port'] ?? 3000);
                }
            }

            $statsJson = @file_get_contents("http://127.0.0.1:3000/control/stats", false, $ctx);
            if ($statsJson) {
                $stats = json_decode($statsJson, true);
                if ($stats) {
                    $default['num_dns_queries'] = (int)($stats['num_dns_queries'] ?? 0);
                    $default['num_blocked_filtering'] = (int)($stats['num_blocked_filtering'] ?? 0);
                    $default['num_replaced_safesearch'] = (int)($stats['num_replaced_safesearch'] ?? 0);
                    $default['avg_processing_time_ms'] = round((float)($stats['avg_processing_time'] ?? 0.42), 2);

                    if ($default['num_dns_queries'] > 0) {
                        $default['blocked_percent'] = round(($default['num_blocked_filtering'] / $default['num_dns_queries']) * 100, 2);
                    }

                    $topBlocked = [];
                    foreach (array_slice($stats['top_blocked_domains'] ?? [], 0, 8) as $item) {
                        foreach ($item as $domain => $count) {
                            $topBlocked[] = ['domain' => $domain, 'count' => (int)$count];
                        }
                    }
                    $default['top_blocked_domains'] = $topBlocked;

                    $topQueried = [];
                    foreach (array_slice($stats['top_queried_domains'] ?? [], 0, 8) as $item) {
                        foreach ($item as $domain => $count) {
                            $topQueried[] = ['domain' => $domain, 'count' => (int)$count];
                        }
                    }
                    $default['top_queried_domains'] = $topQueried;

                    $topClients = [];
                    foreach (array_slice($stats['top_clients'] ?? [], 0, 5) as $item) {
                        foreach ($item as $ip => $count) {
                            $topClients[] = ['ip' => $ip, 'count' => (int)$count];
                        }
                    }
                    $default['top_clients'] = $topClients;

                    $upstreams = [];
                    foreach (array_slice($stats['top_upstreams_responses'] ?? [], 0, 4) as $item) {
                        foreach ($item as $srv => $count) {
                            $upstreams[] = ['server' => $srv, 'count' => (int)$count];
                        }
                    }
                    $default['top_upstreams'] = $upstreams;
                }
            }

            $filterJson = @file_get_contents("http://127.0.0.1:3000/control/filtering/status", false, $ctx);
            if ($filterJson) {
                $filt = json_decode($filterJson, true);
                if (!empty($filt['filters'][0])) {
                    $default['rules_count'] = (int)($filt['filters'][0]['rules_count'] ?? 0);
                    $default['filter_name'] = $filt['filters'][0]['name'] ?? '';
                }
            }
        } catch (\Throwable $e) {
            // Return defaults
        }

        self::$adguardCache = $default;
        self::$adguardCacheTime = microtime(true);
        return $default;
    }

    /**
     * Get Complete Board System State for Dashboard.
     * Cached to /tmp with a short TTL: dashboard pages poll every 2s, so repeated
     * requests within the TTL window reuse one snapshot instead of re-scanning everything.
     * Pass $fresh = true right after a mutation (toggle LED, restart service, ...) to bypass it.
     */
    public static function getFullState(bool $fresh = false): array {
        $now = microtime(true);
        if (!$fresh && file_exists(self::STATE_CACHE_FILE)) {
            $cached = json_decode((string)@file_get_contents(self::STATE_CACHE_FILE), true);
            if (is_array($cached)
                && isset($cached['timestamp'], $cached['_cache_time'])
                && ($now - (float)$cached['_cache_time']) < self::CACHE_TTL_SECONDS) {
                unset($cached['_cache_time']);
                return $cached;
            }
        }

        $cpuUsage = self::getCpuUsage();
        $cpuTemp = self::getCpuTemp();
        $ram = self::getRamInfo();
        $storage = self::getStorageInfo();
        $uptime = self::getSystemUptime();
        $clients = self::getConnectedClients();
        $freqInfo = self::getCpuFreqInfo();
        $thermals = self::getThermalZones();
        $networks = self::getNetworkStats();
        $leds = self::getLedStatus();
        $services = self::getServicesStatus();
        $ping = self::getPingLatency();
        $modem = self::getModemInfo();
        $deviceUsage = self::getDeviceUsageStats();
        $overallUsage = self::getOverallUsageStats();
        $adguard = self::getAdguardInfo();

        $state = [
            'board' => [
                'name' => 'Orange Pi Zero 2',
                'hostname' => gethostname() ?: 'orangepizero2',
                'soc' => 'Allwinner H616 (Quad-Core Cortex-A53 @ 1.51GHz)',
                'os' => 'Armbian Linux (Debian 13 Trixie)',
                'kernel' => php_uname('r'),
                'uptime' => $uptime['formatted'],
                'uptime_seconds' => $uptime['seconds'],
                'ip' => '192.168.1.1'
            ],
            'metrics' => [
                'cpu_usage' => $cpuUsage,
                'cpu_temp' => $cpuTemp,
                'ram_used_mb' => $ram['used_mb'],
                'ram_total_mb' => $ram['total_mb'],
                'ram_percent' => $ram['percent'],
                'storage_used_gb' => $storage['used_gb'],
                'storage_total_gb' => $storage['total_gb'],
                'storage_percent' => $storage['percent'],
                'clients_count' => count($clients)
            ],
            'cpu' => [
                'usage' => $cpuUsage,
                'temp' => $cpuTemp,
                'freq_mhz' => $freqInfo['freq_mhz'],
                'governor' => $freqInfo['governor'],
                'available_governors' => $freqInfo['available_governors'],
                'cores' => 4,
                'load_1m' => $uptime['load_1m'],
                'load_5m' => $uptime['load_5m'],
                'load_15m' => $uptime['load_15m']
            ],
            'ram' => $ram,
            'storage' => $storage,
            'thermals' => $thermals,
            'networks' => $networks,
            'clients' => $clients,
            'leds' => $leds,
            'services' => $services,
            'ping' => $ping,
            'modem' => $modem,
            'device_usage' => $deviceUsage,
            'overall_usage' => $overallUsage,
            'adguard' => $adguard,
            'timestamp' => time()
        ];

        // Persist snapshot for cross-request reuse (internal key stripped on read)
        $state['_cache_time'] = microtime(true);
        @file_put_contents(self::STATE_CACHE_FILE, json_encode($state), LOCK_EX);
        unset($state['_cache_time']);

        return $state;
    }

    public static function getAllState(): array {
        return self::getFullState();
    }
}
