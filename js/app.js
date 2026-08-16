/**
 * OPI-DASHBOARD: Neumorphic Real-Time Telemetry & Control Center
 * Full Javascript Controller for Orange Pi Zero 2 (Allwinner H616 • Armbian)
 */

document.addEventListener('DOMContentLoaded', () => {
    // =========================================================================
    // 1. THERMAL DIAL & RADIAL TICKS
    // =========================================================================
    const radialTicksSvg = document.getElementById('radialTicksSvg');
    const tempValDisplay = document.getElementById('tempValDisplay');
    const thermalStatusLabel = document.getElementById('thermalStatusLabel');
    const acFanIcon = document.getElementById('acFanIcon');

    const TOTAL_TICKS = 48;
    const RADIUS = 76;
    const CENTER_X = 88;
    const CENTER_Y = 88;
    const START_ANGLE = 135; // degrees (Bottom left)
    const SWEEP_ANGLE = 270; // degrees (Sweep to bottom right)

    function initRadialTicks() {
        if (!radialTicksSvg) return;
        radialTicksSvg.innerHTML = '';

        for (let i = 0; i < TOTAL_TICKS; i++) {
            const angleDeg = START_ANGLE + (i / (TOTAL_TICKS - 1)) * SWEEP_ANGLE;
            const angleRad = (angleDeg * Math.PI) / 180;

            const x1 = CENTER_X + (RADIUS - 8) * Math.cos(angleRad);
            const y1 = CENTER_Y + (RADIUS - 8) * Math.sin(angleRad);
            const x2 = CENTER_X + RADIUS * Math.cos(angleRad);
            const y2 = CENTER_Y + RADIUS * Math.sin(angleRad);

            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', x1.toFixed(2));
            line.setAttribute('y1', y1.toFixed(2));
            line.setAttribute('x2', x2.toFixed(2));
            line.setAttribute('y2', y2.toFixed(2));
            line.setAttribute('class', 'radial-tick');
            line.setAttribute('stroke', 'rgba(182, 198, 220, 0.45)');
            line.setAttribute('stroke-width', '2.5');
            line.setAttribute('stroke-linecap', 'round');
            line.setAttribute('data-tick-index', i);
            radialTicksSvg.appendChild(line);
        }
    }

    function renderThermalDial(temp) {
        if (!radialTicksSvg || !tempValDisplay) return;
        const currentTemp = Math.round(temp);
        tempValDisplay.textContent = `${currentTemp}°C`;

        // Temperature range mapping: 30°C to 85°C
        const minTemp = 30;
        const maxTemp = 85;
        const clampedTemp = Math.max(minTemp, Math.min(maxTemp, currentTemp));
        const activeCount = Math.round(((clampedTemp - minTemp) / (maxTemp - minTemp)) * TOTAL_TICKS);

        const ticks = radialTicksSvg.querySelectorAll('.radial-tick');
        ticks.forEach((tick, idx) => {
            if (idx <= activeCount) {
                tick.classList.add('active');
                if (currentTemp > 75) {
                    tick.style.stroke = '#ef4444'; // Red for high temp
                } else if (currentTemp > 60) {
                    tick.style.stroke = '#f59e0b'; // Amber for warm temp
                } else {
                    tick.style.stroke = '#0ea5e9'; // Blue for optimal
                }
            } else {
                tick.classList.remove('active');
                tick.style.stroke = 'rgba(182, 198, 220, 0.45)';
            }
        });

        // Status Label & Fan Animation Speed
        if (thermalStatusLabel) {
            if (currentTemp < 60) {
                thermalStatusLabel.textContent = 'Optimal';
                thermalStatusLabel.style.color = '#38bdf8';
                if (acFanIcon) acFanIcon.style.animationDuration = '4s';
            } else if (currentTemp < 75) {
                thermalStatusLabel.textContent = 'Hangat';
                thermalStatusLabel.style.color = '#fbbf24';
                if (acFanIcon) acFanIcon.style.animationDuration = '2s';
            } else {
                thermalStatusLabel.textContent = 'Suhu Tinggi';
                thermalStatusLabel.style.color = '#f87171';
                if (acFanIcon) acFanIcon.style.animationDuration = '0.8s';
            }
        }
    }

    // =========================================================================
    // 2. AUTHENTICATION & LOGIN FORM HANDLER
    // =========================================================================
    const formLogin = document.getElementById('formLogin');
    const loginErrorMsg = document.getElementById('loginErrorMsg');
    const btnLoginSubmit = document.getElementById('btnLoginSubmit');

    if (formLogin) {
        formLogin.addEventListener('submit', async (e) => {
            e.preventDefault();
            const usernameInput = document.getElementById('loginUsername');
            const passwordInput = document.getElementById('loginPassword');

            if (!usernameInput || !passwordInput) return;
            const username = usernameInput.value.trim();
            const password = passwordInput.value;

            if (loginErrorMsg) {
                loginErrorMsg.style.display = 'none';
                loginErrorMsg.textContent = '';
            }
            if (btnLoginSubmit) {
                btnLoginSubmit.disabled = true;
                btnLoginSubmit.innerHTML = '<span>Memeriksa...</span>';
            }

            try {
                const res = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const data = await res.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    if (loginErrorMsg) {
                        loginErrorMsg.textContent = data.message || 'Nama pengguna atau kata sandi salah.';
                        loginErrorMsg.style.display = 'block';
                    }
                    if (btnLoginSubmit) {
                        btnLoginSubmit.disabled = false;
                        btnLoginSubmit.innerHTML = '<span>Masuk</span>';
                    }
                }
            } catch (err) {
                if (loginErrorMsg) {
                    loginErrorMsg.textContent = 'Koneksi ke server terputus. Silakan coba lagi.';
                    loginErrorMsg.style.display = 'block';
                }
                if (btnLoginSubmit) {
                    btnLoginSubmit.disabled = false;
                    btnLoginSubmit.innerHTML = '<span>Masuk</span>';
                }
            }
        });
    }

    // =========================================================================
    // 3. LIVE STATE POLLING & REAL-TIME UI UPDATES
    // =========================================================================
    let state = window.__INITIAL_STATE__ || window.INITIAL_STATE || null;
    let isPolling = true;
    let pollInterval = null;

    // Header & Metric Elements
    const headerUptimeText = document.getElementById('headerUptimeText');
    const metricCpuVal = document.getElementById('metricCpuVal');
    const metricCpuFreq = document.getElementById('metricCpuFreq');
    const metricRamVal = document.getElementById('metricRamVal');
    const metricRamPct = document.getElementById('metricRamPct');
    const metricStorageVal = document.getElementById('metricStorageVal');
    const metricStoragePct = document.getElementById('metricStoragePct');
    const metricClientsVal = document.getElementById('metricClientsVal');

    // Controls
    const governorDisplay = document.getElementById('governorDisplay');
    const govLabel = document.getElementById('govLabel');
    const govPowerSwitch = document.getElementById('govPowerSwitch');
    const greenLedSwitch = document.getElementById('greenLedSwitch');
    const greenLedStatusText = document.getElementById('greenLedStatusText');
    const hotspotClientsCount = document.getElementById('hotspotClientsCount');
    const hotspotServiceSwitch = document.getElementById('hotspotServiceSwitch');
    const tvWaveform = document.getElementById('tvWaveform');

    // Summary & Sensors
    const totalTrafficText = document.getElementById('totalTrafficText');
    const liveBandwidthBar = document.getElementById('liveBandwidthBar');
    const loadAvgReading = document.getElementById('loadAvgReading');
    const socCoreReading = document.getElementById('socCoreReading');

    let loadHistory = [0.42, 0.48, 0.52, 0.45, 0.58, 0.50, 0.54];
    let dlHistory = [120, 180, 240, 160, 320, 280, 350, 420];
    let ulHistory = [35, 45, 60, 40, 85, 70, 95, 110];

    function updateLoadSparkline(currentLoad) {
        loadHistory.shift();
        const val = parseFloat(currentLoad) || 0.5;
        loadHistory.push(val);

        const svgArea = document.getElementById('sparklineLoadArea');
        const svgLine = document.getElementById('sparklineLoadLine');
        if (!svgArea || !svgLine) return;

        const points = loadHistory.map((v, i) => {
            const x = Math.round(i * (100 / (loadHistory.length - 1)));
            const normalized = Math.max(0.1, Math.min(2.5, v));
            const y = Math.round(32 - (normalized / 2.5) * 26);
            return { x, y };
        });

        let d = `M${points[0].x},${points[0].y}`;
        for (let i = 0; i < points.length - 1; i++) {
            const p0 = points[i];
            const p1 = points[i + 1];
            const cx = Math.round((p0.x + p1.x) / 2);
            d += ` C${cx},${p0.y} ${cx},${p1.y} ${p1.x},${p1.y}`;
        }

        svgLine.setAttribute('d', d);
        const dArea = `${d} L100,36 L0,36 Z`;
        svgArea.setAttribute('d', dArea);
    }

    function updateBandwidthSparklines(dlRateKbps, ulRateKbps) {
        dlHistory.shift();
        dlHistory.push(Math.max(10, parseFloat(dlRateKbps) || 120));

        ulHistory.shift();
        ulHistory.push(Math.max(5, parseFloat(ulRateKbps) || 35));

        // 1. Download Sparkline (Emerald / Cyan Wave)
        const svgDlArea = document.getElementById('sparklineDlArea');
        const svgDlLine = document.getElementById('sparklineDlLine');
        if (svgDlArea && svgDlLine) {
            const maxDl = Math.max(...dlHistory, 400);
            const pointsDl = dlHistory.map((v, i) => {
                const x = Math.round(i * (200 / (dlHistory.length - 1)));
                const y = Math.round(44 - (v / maxDl) * 36);
                return { x, y };
            });

            let d = `M${pointsDl[0].x},${pointsDl[0].y}`;
            for (let i = 0; i < pointsDl.length - 1; i++) {
                const p0 = pointsDl[i];
                const p1 = pointsDl[i + 1];
                const cx = Math.round((p0.x + p1.x) / 2);
                d += ` C${cx},${p0.y} ${cx},${p1.y} ${p1.x},${p1.y}`;
            }
            svgDlLine.setAttribute('d', d);
            svgDlArea.setAttribute('d', `${d} L200,48 L0,48 Z`);
        }

        // 2. Upload Sparkline (Blue / Purple Wave)
        const svgUlArea = document.getElementById('sparklineUlArea');
        const svgUlLine = document.getElementById('sparklineUlLine');
        if (svgUlArea && svgUlLine) {
            const maxUl = Math.max(...ulHistory, 180);
            const pointsUl = ulHistory.map((v, i) => {
                const x = Math.round(i * (200 / (ulHistory.length - 1)));
                const y = Math.round(44 - (v / maxUl) * 36);
                return { x, y };
            });

            let d = `M${pointsUl[0].x},${pointsUl[0].y}`;
            for (let i = 0; i < pointsUl.length - 1; i++) {
                const p0 = pointsUl[i];
                const p1 = pointsUl[i + 1];
                const cx = Math.round((p0.x + p1.x) / 2);
                d += ` C${cx},${p0.y} ${cx},${p1.y} ${p1.x},${p1.y}`;
            }
            svgUlLine.setAttribute('d', d);
            svgUlArea.setAttribute('d', `${d} L200,48 L0,48 Z`);
        }
    }

    function updateUI(freshState) {
        if (!freshState) return;
        state = freshState;

        // 1. Over View Metrics
        if (freshState.cpu) {
            if (metricCpuVal) metricCpuVal.textContent = Math.round(freshState.cpu.usage);
            if (metricCpuFreq) metricCpuFreq.textContent = `4x @ ${freshState.cpu.freq_mhz} MHz`;
            if (governorDisplay) governorDisplay.textContent = freshState.cpu.governor;
            if (govLabel) govLabel.textContent = freshState.cpu.governor.toUpperCase();
            if (govPowerSwitch) govPowerSwitch.checked = (freshState.cpu.governor === 'performance');

            const btnPowersave = document.getElementById('btnGovPowersave');
            const btnPerformance = document.getElementById('btnPresetAmber');
            const btnOndemand = document.getElementById('btnGovOndemand');
            if (btnPowersave) btnPowersave.classList.toggle('active', freshState.cpu.governor === 'powersave');
            if (btnPerformance) btnPerformance.classList.toggle('active', freshState.cpu.governor === 'performance');
            if (btnOndemand) btnOndemand.classList.toggle('active', freshState.cpu.governor === 'ondemand');

            renderThermalDial(freshState.cpu.temp || 50);

            const loadSens = document.getElementById('load1mSensorVal');
            if (loadSens && freshState.cpu.load_1m !== undefined) {
                loadSens.textContent = freshState.cpu.load_1m;
            }

            const load1mVal = document.getElementById('load1mVal');
            if (load1mVal) load1mVal.textContent = freshState.cpu.load_1m;

            updateLoadSparkline(freshState.cpu.load_1m || 0.5);
        }

        if (freshState.ram) {
            if (metricRamVal) metricRamVal.textContent = freshState.ram.used_mb;
            if (metricRamPct) metricRamPct.textContent = `${freshState.ram.percent}% dari ${freshState.ram.total_mb}MB`;
        }

        if (freshState.storage) {
            if (metricStorageVal) metricStorageVal.textContent = freshState.storage.used_gb;
            if (metricStoragePct) metricStoragePct.textContent = `${freshState.storage.percent}% dari ${freshState.storage.total_gb}GB`;
        }

        const clientCount = freshState.clients ? freshState.clients.length : 0;
        if (metricClientsVal) metricClientsVal.textContent = clientCount;
        if (hotspotClientsCount) hotspotClientsCount.textContent = clientCount;
        if (rightClientsNum) rightClientsNum.textContent = clientCount;

        // 2. Internet Latency & Ping
        if (freshState.ping) {
            const pingValueNum = document.getElementById('pingValueNum');
            const pingStatusLabel = document.getElementById('pingStatusLabel');
            const pingLiveDot = document.getElementById('pingLiveDot');
            if (pingValueNum) pingValueNum.textContent = freshState.ping.ms;
            if (pingStatusLabel) {
                pingStatusLabel.textContent = freshState.ping.status;
                pingStatusLabel.className = `ping-status-${freshState.ping.quality || 'good'}`;
            }
            if (pingLiveDot) {
                pingLiveDot.className = `ping-live-dot ${freshState.ping.online ? '' : 'offline'}`;
            }
        }

        // 3. Hotspot AP Status
        if (freshState.services && freshState.services.hostapd) {
            const isApActive = freshState.services.hostapd.active;
            if (hotspotServiceSwitch) hotspotServiceSwitch.checked = isApActive;
            if (tvWaveform) tvWaveform.style.opacity = isApActive ? '1' : '0.2';
        }

        // 3b. AdGuard Card Status
        if (freshState.adguard) {
            const adgSwitch = document.getElementById('adgCardSwitch');
            const adgPill = document.getElementById('adgCardPill');
            if (adgSwitch && freshState.adguard.protection_enabled !== undefined) {
                adgSwitch.checked = freshState.adguard.protection_enabled;
            }
            if (adgPill && freshState.adguard.protection_enabled !== undefined) {
                const isProt = freshState.adguard.protection_enabled;
                adgPill.innerHTML = `<span class="pulse-green-dot" style="background: ${isProt ? '#10b981' : '#cbd5e1'};"></span><span>${isProt ? 'Proteksi Aktif' : 'Dijeda'}</span>`;
            }
        }

        // 3c. Load average in sensor card
        if (freshState.cpu && freshState.cpu.load_1m !== undefined) {
            const loadSens = document.getElementById('load1mSensorVal');
            if (loadSens) loadSens.textContent = freshState.cpu.load_1m;
        }

        // 4. Bandwidth & Traffic (Identical Source to usage.php + Dual Sparklines)
        const overall = freshState.overall_usage || {};
        const rxRate = (overall.live_rx_kbps !== undefined) ? overall.live_rx_kbps : 0;
        const txRate = (overall.live_tx_kbps !== undefined) ? overall.live_tx_kbps : 0;

        const liveDlSpeedFormatted = rxRate >= 1024 ? (rxRate / 1024).toFixed(2) + ' MB/s' : rxRate.toFixed(1) + ' KB/s';
        const liveUlSpeedFormatted = txRate >= 1024 ? (txRate / 1024).toFixed(2) + ' MB/s' : txRate.toFixed(1) + ' KB/s';

        const liveDlSpeedText = document.getElementById('liveDlSpeedText');
        const liveUlSpeedText = document.getElementById('liveUlSpeedText');
        if (liveDlSpeedText) liveDlSpeedText.textContent = liveDlSpeedFormatted;
        if (liveUlSpeedText) liveUlSpeedText.textContent = liveUlSpeedFormatted;

        // Total traffic volume
        let totalRxMb = 0;
        let totalTxMb = 0;
        if (freshState.networks) {
            Object.values(freshState.networks).forEach(iface => {
                totalRxMb += (iface.rx_mb || 0);
                totalTxMb += (iface.tx_mb || 0);
            });
        }

        const liveTotalDlMb = document.getElementById('liveTotalDlMb');
        const liveTotalUlMb = document.getElementById('liveTotalUlMb');
        if (liveTotalDlMb) liveTotalDlMb.textContent = `${totalRxMb.toFixed(1)} MB`;
        if (liveTotalUlMb) liveTotalUlMb.textContent = `${totalTxMb.toFixed(1)} MB`;

        if (totalTrafficText) {
            totalTrafficText.textContent = `${Math.round(totalRxMb + totalTxMb)} MB`;
        }

        // Animate the Dual Oscilloscope Sparklines dynamically
        updateBandwidthSparklines(rxRate, txRate);

        // 5. Right Panel Clients List
        if (rightClientsList && freshState.clients) {
            const badgeCount = document.getElementById('badgeClientsCount');
            if (badgeCount) badgeCount.textContent = `${freshState.clients.length} Aktif`;

            if (freshState.clients.length === 0) {
                rightClientsList.innerHTML = '<div class="member-item-empty">Tidak ada perangkat terhubung</div>';
            } else {
                rightClientsList.innerHTML = freshState.clients.map(c => {
                    const meta = getDeviceMeta(c.name);
                    const shortMac = c.mac ? c.mac.slice(-8) : '00:00:00';
                    return `
                        <div class="member-item">
                            <div class="member-avatar-wrap avatar-${meta.color}" title="${meta.type}">
                                ${meta.svg}
                            </div>
                            <div class="member-meta">
                                <div class="member-name-row">
                                    <h4 class="member-name">${c.name}</h4>
                                    <span class="member-mac-tag">${shortMac}</span>
                                </div>
                                <span class="member-role">${c.ip} &bull; ${meta.type}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }
    }

    function getDeviceMeta(name) {
        const n = (name || '').toLowerCase();
        if (/(phone|redmi|xiaomi|samsung|galaxy|poco|realme|vivo|oppo|huawei|iphone|android|enall|mobile)/i.test(n)) {
            return {
                type: 'Smartphone',
                color: 'blue',
                svg: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="2" width="14" height="20" rx="2.5" ry="2.5"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>`
            };
        }
        if (/(laptop|desktop|pc|mac|win|thinkpad|notebook|book|asus|acer|lenovo|dell|hp)/i.test(n)) {
            return {
                type: 'Laptop / PC',
                color: 'purple',
                svg: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>`
            };
        }
        if (/(tv|cast|roku|firetv|smarttv|display|screen)/i.test(n)) {
            return {
                type: 'Smart TV',
                color: 'amber',
                svg: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="7" width="20" height="15" rx="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg>`
            };
        }
        return {
            type: 'Hotspot AP',
            color: 'teal',
            svg: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>`
        };
    }

    async function pollState() {
        if (!isPolling) return;
        try {
            const res = await fetch(`api.php?action=get_system_stats&_t=${Date.now()}`);
            if (res.status === 401) {
                window.location.reload();
                return;
            }
            const data = await res.json();
            if (data.success && data.data) {
                updateUI(data.data);
            }
        } catch (err) {
            console.warn('Live poll dilewati:', err.message);
        }
    }

    // Toggle Polling
    const livePollingSwitch = document.getElementById('livePollingSwitch');
    if (livePollingSwitch) {
        livePollingSwitch.addEventListener('change', (e) => {
            isPolling = e.target.checked;
            showToast(isPolling ? 'Telemetri Langsung Dilanjutkan' : 'Telemetri Langsung Dijeda', 'info');
        });
    }

    // Start 2.0s Real-Time Polling Loop (Identical to usage.php)
    pollInterval = setInterval(pollState, 2000);

    // =========================================================================
    // 4. HARDWARE & NETWORK CONTROL ACTIONS
    // =========================================================================
    // Test Ping Latency Button
    const btnTestPing = document.getElementById('btnTestPing');
    if (btnTestPing) {
        btnTestPing.addEventListener('click', async () => {
            btnTestPing.classList.add('spinning');
            showToast('Menguji latensi koneksi internet...', 'info');
            try {
                const res = await fetch('api.php?action=test_ping');
                const data = await res.json();
                if (data.success && data.data) {
                    const ping = data.data;
                    const pingValueNum = document.getElementById('pingValueNum');
                    const pingStatusLabel = document.getElementById('pingStatusLabel');
                    const pingLiveDot = document.getElementById('pingLiveDot');
                    if (pingValueNum) pingValueNum.textContent = ping.ms;
                    if (pingStatusLabel) {
                        pingStatusLabel.textContent = ping.status;
                        pingStatusLabel.className = `ping-status-${ping.quality || 'good'}`;
                    }
                    if (pingLiveDot) {
                        pingLiveDot.className = `ping-live-dot ${ping.online ? '' : 'offline'}`;
                    }
                    showToast(`Hasil Ping: ${ping.ms} ms (${ping.status})`, 'success');
                }
            } catch (err) {
                showToast('Gagal menguji ping', 'info');
            } finally {
                setTimeout(() => btnTestPing.classList.remove('spinning'), 600);
            }
        });
    }

    // Governor Switcher
    async function setGovernor(gov) {
        try {
            const res = await fetch('api.php?action=set_cpu_governor', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ governor: gov })
            });
            const data = await res.json();
            if (data.success) {
                showToast(`Mode Governor CPU diubah ke ${gov.toUpperCase()}`, 'success');
                if (data.data) updateUI(data.data);
            }
        } catch (err) {
            showToast('Gagal mengubah mode performa CPU', 'info');
        }
    }

    document.getElementById('btnGovPowersave')?.addEventListener('click', () => setGovernor('powersave'));
    document.getElementById('btnPresetAmber')?.addEventListener('click', () => setGovernor('performance'));
    document.getElementById('btnGovOndemand')?.addEventListener('click', () => setGovernor('ondemand'));

    if (govPowerSwitch) {
        govPowerSwitch.addEventListener('change', (e) => {
            setGovernor(e.target.checked ? 'performance' : 'ondemand');
        });
    }

    // Restart Hotspot
    document.getElementById('btnRestartHotspot')?.addEventListener('click', async () => {
        showToast('Memulai ulang layanan hotspot...', 'info');
        try {
            const res = await fetch('api.php?action=restart_service', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ service: 'hostapd' })
            });
            const data = await res.json();
            if (data.success) showToast('Layanan hotspot berhasil dimulai ulang', 'success');
        } catch (e) {
            showToast('Gagal memulai ulang hotspot', 'info');
        }
    });

    // =========================================================================
    // 5. REBOOT SYSTEM MODAL
    // =========================================================================
    const modalReboot = document.getElementById('rebootModalOverlay');
    const btnHeaderReboot = document.getElementById('btnHeaderReboot');
    const btnProfileActions = document.getElementById('btnProfileActions');
    const btnCancelReboot = document.getElementById('btnCancelReboot');
    const btnConfirmReboot = document.getElementById('btnConfirmReboot');

    function openRebootModal() { if (modalReboot) modalReboot.classList.add('open'); }
    function closeRebootModal() { if (modalReboot) modalReboot.classList.remove('open'); }

    if (btnHeaderReboot) btnHeaderReboot.addEventListener('click', openRebootModal);
    if (btnProfileActions) btnProfileActions.addEventListener('click', openRebootModal);
    if (btnCancelReboot) btnCancelReboot.addEventListener('click', closeRebootModal);

    if (btnConfirmReboot) {
        btnConfirmReboot.addEventListener('click', async () => {
            btnConfirmReboot.disabled = true;
            btnConfirmReboot.textContent = 'Memulai Ulang...';
            showToast('Orange Pi Zero 2 sedang memulai ulang...', 'info');
            try {
                await fetch('api.php?action=reboot_system', { method: 'POST' });
            } catch (e) {}
            closeRebootModal();
            setTimeout(() => {
                document.body.innerHTML = `
                    <div style="text-align:center; padding:80px 20px; font-family:sans-serif; color:#64748b;">
                        <h2>🍊 Orange Pi Zero 2 Sedang Memulai Ulang...</h2>
                        <p style="margin-top:12px;">Mohon tunggu sekitar ~30 detik lalu refresh halaman ini.</p>
                    </div>
                `;
            }, 1000);
        });
    }

    // Sidebar Logout
    document.getElementById('btnSidebarLogout')?.addEventListener('click', async () => {
        try {
            await fetch('api.php?action=logout', { method: 'POST' });
            window.location.reload();
        } catch (e) {
            window.location.reload();
        }
    });

    // 2x2 Network Interfaces Click Switcher
    document.querySelectorAll('.room-nav-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.room-nav-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const ifaceLabel = btn.querySelector('span')?.textContent || 'Antarmuka';
            showToast(`Antarmuka Dipilih: ${ifaceLabel}`, 'info');
        });
    });

    // =========================================================================
    // 6. TOAST HELPER
    // =========================================================================
    const toastContainer = document.getElementById('toastContainer');
    function showToast(message, type = 'info') {
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const iconSvg = type === 'success'
            ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>`
            : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;

        toast.innerHTML = `${iconSvg}<span>${message}</span>`;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = '0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // =========================================================================
    // 7. REAL-TIME DIGITAL SERVER CLOCK TICKER (1 SECOND)
    // =========================================================================
    const clockHours = document.getElementById('clockHours');
    const clockMinutes = document.getElementById('clockMinutes');
    const clockSeconds = document.getElementById('clockSeconds');
    const clockFullDate = document.getElementById('clockFullDate');

    const daysIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const monthsIndo = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    function tickDigitalClock() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const ss = String(now.getSeconds()).padStart(2, '0');

        if (clockHours) clockHours.textContent = hh;
        if (clockMinutes) clockMinutes.textContent = mm;
        if (clockSeconds) clockSeconds.textContent = ss;

        if (clockFullDate) {
            const dayName = daysIndo[now.getDay()];
            const monthName = monthsIndo[now.getMonth()];
            clockFullDate.textContent = `${dayName}, ${now.getDate()} ${monthName} ${now.getFullYear()}`;
        }
    }

    tickDigitalClock();
    setInterval(tickDigitalClock, 1000);

    window.handleToggleAdgFromCard = async function(enabled) {
        try {
            const res = await fetch('api.php?action=toggle_adguard_protection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: enabled })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
            }
        } catch (err) {
            showToast('Gagal mengubah proteksi AdGuard', 'error');
        }
    };

    // Init Dial & Initial Render
    initRadialTicks();
    updateUI(state);
    pollState();
});
