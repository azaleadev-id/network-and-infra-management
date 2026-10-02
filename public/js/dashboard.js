let map;
let mapMarkers = [];
let deviceLocationMarker = null;
let mapInfoWindow = null;
let lastMapSignature = '';
let hasAppliedMapBounds = false;
let trafficPeak = 0;
let selectedUserStatus = 'online';
let googleMapsApiPromise = null;
let GoogleMapLabelOverlay = null;
const isMobile = window.matchMedia('(max-width: 768px)').matches;
const maxDataPoints = isMobile ? 12 : 36;
const maxTrafficPoints = isMobile ? 16 : 32;
const trafficRefreshInterval = 5000;
const summaryRefreshInterval = 60000;
const mapRefreshInterval = isMobile ? 120000 : 90000;
const trafficColors = {
    rx: '#dc2626',
    tx: '#7c3aed',
    text: '#2d251d',
    border: '#928878',
    grid: '#d7d1c8'
};
const trafficState = {
    rateCanvas: null,
    packetCanvas: null,
    rateHistory: [],
    packetHistory: [],
    lastCounterStats: null
};

const dashboardState = {
    dashboardLoading: false,
    mikrotikLoading: false,
    mikrotikSummaryLoading: false,
    mapLoading: false,
    lastPppoeActive: 0,
    trafficTimer: null,
    summaryTimer: null,
    mapTimer: null
};
const mapDefaultCenter = { lat: -6.2, lng: 106.816666 };
const mapZoomSettings = {
    preferredZoom: isMobile ? 17 : 18,
    focusZoom: 19,
    fitBoundsMaxZoom: 18
};

function loadGoogleMapsApi() {
    if (window.google && window.google.maps) {
        return Promise.resolve(window.google.maps);
    }

    if (googleMapsApiPromise) {
        return googleMapsApiPromise;
    }

    const apiKey = String(window.GOOGLE_MAPS_CONFIG?.apiKey || '').trim();
    if (apiKey === '') {
        return Promise.reject(new Error('Google Maps API key belum dikonfigurasi.'));
    }

    googleMapsApiPromise = new Promise((resolve, reject) => {
        const callbackName = '__initDashboardGoogleMaps';
        const existingScript = document.querySelector('script[data-google-maps-loader="dashboard"]');

        window[callbackName] = () => {
            delete window[callbackName];
            resolve(window.google.maps);
        };

        if (existingScript) {
            existingScript.addEventListener('error', () => reject(new Error('Gagal memuat Google Maps API.')), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&v=weekly&callback=${callbackName}`;
        script.async = true;
        script.defer = true;
        script.dataset.googleMapsLoader = 'dashboard';
        script.onerror = () => {
            delete window[callbackName];
            reject(new Error('Gagal memuat Google Maps API.'));
        };
        document.head.appendChild(script);
    });

    return googleMapsApiPromise;
}

function getMapLabelOverlayClass() {
    if (GoogleMapLabelOverlay || !(window.google && window.google.maps)) {
        return GoogleMapLabelOverlay;
    }

    GoogleMapLabelOverlay = class extends google.maps.OverlayView {
        constructor(position, text) {
            super();
            this.position = position;
            this.text = text;
            this.element = null;
        }

        onAdd() {
            const div = document.createElement('div');
            div.className = 'map-node-label';
            div.textContent = this.text;
            this.element = div;
            this.getPanes().overlayMouseTarget.appendChild(div);
        }

        draw() {
            if (!this.element) return;
            const projection = this.getProjection();
            if (!projection) return;

            const point = projection.fromLatLngToDivPixel(this.position);
            if (!point) return;

            this.element.style.left = `${point.x}px`;
            this.element.style.top = `${point.y}px`;
        }

        onRemove() {
            if (this.element && this.element.parentNode) {
                this.element.parentNode.removeChild(this.element);
            }
            this.element = null;
        }
    };

    return GoogleMapLabelOverlay;
}

function clearDashboardTimer(timerKey) {
    if (dashboardState[timerKey]) {
        window.clearTimeout(dashboardState[timerKey]);
        dashboardState[timerKey] = null;
    }
}

function scheduleDashboardTask(timerKey, callback, delay) {
    clearDashboardTimer(timerKey);
    dashboardState[timerKey] = window.setTimeout(async () => {
        dashboardState[timerKey] = null;
        await callback();
        scheduleDashboardTask(timerKey, callback, delay);
    }, delay);
}

function startDashboardPolling() {
    if (document.hidden) return;

    scheduleDashboardTask('trafficTimer', loadMikrotikStats, trafficRefreshInterval);
    scheduleDashboardTask('summaryTimer', async () => {
        await loadDashboardSummary();
        await loadMikrotikSummary();
    }, summaryRefreshInterval);
    scheduleDashboardTask('mapTimer', loadMapData, mapRefreshInterval);
}

function stopDashboardPolling() {
    clearDashboardTimer('trafficTimer');
    clearDashboardTimer('summaryTimer');
    clearDashboardTimer('mapTimer');
}

async function saveTrafficInterface(event) {
    event.preventDefault();
    const input = document.getElementById('trafficInterfaceInput');
    const message = document.getElementById('trafficInterfaceMsg');
    const formData = new FormData();
    formData.append('app_name', document.title.replace(/^Dashboard - /, '') || 'Nikonet');
    formData.append('traffic_interface', input.value.trim());

    message.textContent = 'Menyimpan interface...';
    message.style.color = 'var(--text-muted)';

    try {
        const response = await fetch('api/settings.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            message.textContent = `Interface aktif: ${data.data.traffic_interface}`;
            message.style.color = 'var(--success)';
            trafficState.rateHistory = [];
            trafficState.packetHistory = [];
            trafficState.lastCounterStats = null;
            trafficPeak = 0;
            drawTrafficCharts();
            await loadMikrotikStats();
            await loadMikrotikSummary();
        } else {
            message.textContent = data.message || 'Gagal menyimpan interface';
            message.style.color = 'var(--danger)';
        }
    } catch (error) {
        message.textContent = 'Terjadi kesalahan sistem';
        message.style.color = 'var(--danger)';
    }
}

function initTrafficPanel() {
    trafficState.rateCanvas = document.getElementById('trafficRateChart');
    trafficState.packetCanvas = document.getElementById('trafficPacketChart');
    drawTrafficCharts();
}

async function initMap() {
    const maps = await loadGoogleMapsApi();

    map = new maps.Map(document.getElementById('map'), {
        center: mapDefaultCenter,
        zoom: mapZoomSettings.preferredZoom,
        renderingType: maps.RenderingType ? maps.RenderingType.VECTOR : undefined,
        mapTypeId: maps.MapTypeId.SATELLITE,
        streetViewControl: false,
        mapTypeControl: true,
        fullscreenControl: true,
        rotateControl: true,
        headingInteractionEnabled: true,
        tiltInteractionEnabled: true,
        zoomControl: true,
        zoomControlOptions: {
            position: maps.ControlPosition.RIGHT_TOP
        },
        gestureHandling: 'greedy',
        clickableIcons: false,
        tilt: 0
    });

    mapInfoWindow = new maps.InfoWindow();
}

function normalizeMapHeading(value) {
    const normalized = Number(value || 0) % 360;
    return normalized < 0 ? normalized + 360 : normalized;
}

function rotateTopologyMap(delta = 30) {
    if (!map || !(window.google && window.google.maps) || typeof map.getHeading !== 'function') {
        setDeviceLocationStatus('Rotasi belum tersedia di browser ini.', 'danger');
        return;
    }

    const currentHeading = Number(map.getHeading() || 0);
    map.setHeading(normalizeMapHeading(currentHeading + Number(delta || 0)));
    setDeviceLocationStatus(`Arah peta diputar ${delta > 0 ? 'ke kanan' : 'ke kiri'}.`, 'success');
}

function resetTopologyMapBearing() {
    if (!map || !(window.google && window.google.maps) || typeof map.setHeading !== 'function') {
        return;
    }

    map.setHeading(0);
    if (typeof map.setTilt === 'function') {
        map.setTilt(0);
    }
    setDeviceLocationStatus('Arah peta dikembalikan ke utara.', 'success');
}

function setDeviceLocationStatus(message, tone = 'muted') {
    const element = document.getElementById('deviceLocationStatus');
    if (!element) return;

    const toneMap = {
        muted: 'var(--text-muted)',
        success: 'var(--success)',
        danger: 'var(--danger)'
    };

    element.textContent = message;
    element.style.color = toneMap[tone] || toneMap.muted;
}

function focusCurrentDeviceLocation() {
    if (!navigator.geolocation) {
        setDeviceLocationStatus('Browser ini belum mendukung geolocation.', 'danger');
        return;
    }

    setDeviceLocationStatus('Mengambil lokasi perangkat sekarang...', 'muted');
    navigator.geolocation.getCurrentPosition(
        position => {
            if (!map) {
                return;
            }

            const latlng = {
                lat: Number(position.coords.latitude),
                lng: Number(position.coords.longitude)
            };
            if (deviceLocationMarker) {
                deviceLocationMarker.setMap(null);
            }

            deviceLocationMarker = new google.maps.Marker({
                position: latlng,
                map,
                title: 'Lokasi perangkat sekarang'
            });

            if (mapInfoWindow) {
                mapInfoWindow.setContent('<b>Lokasi perangkat sekarang</b>');
                mapInfoWindow.open({
                    map,
                    anchor: deviceLocationMarker
                });
            }

            map.setCenter(latlng);
            map.setZoom(mapZoomSettings.focusZoom);
            setDeviceLocationStatus('Posisi perangkat berhasil ditemukan dan ditampilkan di peta.', 'success');
        },
        error => {
            let message = 'Lokasi perangkat tidak bisa diambil.';
            if (error.code === 1) message = 'Izin lokasi ditolak. Izinkan akses lokasi di browser.';
            if (error.code === 2) message = 'Lokasi perangkat tidak tersedia.';
            if (error.code === 3) message = 'Pengambilan lokasi perangkat timeout.';
            setDeviceLocationStatus(message, 'danger');
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}

function toMbps(bits) {
    return Number(bits || 0) / 1000000;
}

function formatRate(bits, decimals = 2) {
    const mbps = toMbps(bits);
    const precision = mbps < 0.01 ? 5 : (mbps < 0.1 ? 4 : decimals);
    return `${mbps.toFixed(precision)} Mb/s`;
}

function formatBitsPerSecond(bits) {
    const value = Number(bits || 0);
    if (value >= 1000000) return `${(value / 1000000).toFixed(value >= 10000000 ? 1 : 2)} Mbps`;
    if (value >= 1000) return `${(value / 1000).toFixed(value >= 10000 ? 1 : 2)} kbps`;
    return `${Math.round(value)} bps`;
}

function computePacketRate(current = null, liveRate = null) {
    if (liveRate && (Number(liveRate.tx || 0) > 0 || Number(liveRate.rx || 0) > 0)) {
        if (current) {
            trafficState.lastCounterStats = {
                tx_packet: Number(current.tx_packet || 0),
                rx_packet: Number(current.rx_packet || 0),
                time: Date.now()
            };
        }

        return {
            tx: Math.max(0, Number(liveRate.tx || 0)),
            rx: Math.max(0, Number(liveRate.rx || 0))
        };
    }

    if (!current) {
        trafficState.lastCounterStats = null;
        return { tx: 0, rx: 0 };
    }

    const previous = trafficState.lastCounterStats;
    const now = Date.now();
    let rate = { tx: 0, rx: 0 };

    if (previous && now > previous.time) {
        const seconds = Math.max((now - previous.time) / 1000, 0.001);
        rate = {
            tx: Math.max(0, (Number(current.tx_packet || 0) - Number(previous.tx_packet || 0)) / seconds),
            rx: Math.max(0, (Number(current.rx_packet || 0) - Number(previous.rx_packet || 0)) / seconds)
        };
    }

    trafficState.lastCounterStats = {
        tx_packet: Number(current.tx_packet || 0),
        rx_packet: Number(current.rx_packet || 0),
        time: now
    };

    return rate;
}

function resizeCanvasForDisplay(canvas) {
    if (!canvas) return null;
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const width = Math.max(1, Math.round(rect.width * ratio));
    const height = Math.max(1, Math.round(rect.height * ratio));

    if (canvas.width !== width || canvas.height !== height) {
        canvas.width = width;
        canvas.height = height;
    }

    const ctx = canvas.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(ratio, ratio);
    return { ctx, width: rect.width, height: rect.height };
}

function appendTrafficHistory(target, value) {
    target.push(typeof value === 'object' && value !== null ? value : Number(value || 0));
    while (target.length > maxTrafficPoints) {
        target.shift();
    }
}

function getLastTrafficPoint(history) {
    return history.length ? history[history.length - 1] : null;
}

function buildRenderedTrafficHistory(history, slots) {
    if (!Array.isArray(history) || history.length === 0 || slots <= 0) {
        return [];
    }

    if (history.length >= slots) {
        return history.slice(-slots);
    }

    if (history.length === 1) {
        return Array.from({ length: slots }, () => history[0]);
    }

    return Array.from({ length: slots }, (_, index) => {
        const sourceIndex = Math.round((index / Math.max(slots - 1, 1)) * (history.length - 1));
        return history[sourceIndex];
    });
}

function drawTrafficLegend(ctx, items) {
    const boxSize = 12;
    const padding = 8;
    const lineHeight = 24;
    const maxText = Math.max(...items.map(item => ctx.measureText(item.label).width));
    const legendWidth = boxSize + 8 + maxText + (padding * 2);
    const legendHeight = (lineHeight * items.length) + padding;
    const x = 6;
    const y = 6;

    ctx.fillStyle = 'rgba(245, 245, 245, 0.92)';
    ctx.strokeStyle = '#666666';
    ctx.lineWidth = 1;
    ctx.fillRect(x, y, legendWidth, legendHeight);
    ctx.strokeRect(x, y, legendWidth, legendHeight);

    items.forEach((item, index) => {
        const rowY = y + padding + (index * lineHeight);
        ctx.fillStyle = item.color;
        ctx.fillRect(x + padding, rowY, boxSize, boxSize);
        ctx.strokeStyle = '#222222';
        ctx.strokeRect(x + padding, rowY, boxSize, boxSize);
        ctx.fillStyle = trafficColors.text;
        ctx.fillText(item.label, x + padding + boxSize + 8, rowY + 1);
    });
}

function drawTrafficChart(canvas, history, legendItems) {
    const resized = resizeCanvasForDisplay(canvas);
    if (!resized) return;

    const { ctx, width, height } = resized;
    const padding = { top: 4, right: 4, bottom: 4, left: 4 };
    const plotPadding = { left: 1, right: 1 };
    const chartWidth = Math.max(1, width - plotPadding.left - plotPadding.right);
    const chartHeight = Math.max(1, height - padding.top - padding.bottom);
    const maxValue = Math.max(1, ...history.map(item => Math.max(item.rx || 0, item.tx || 0)));
    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);

    ctx.strokeStyle = trafficColors.border;
    ctx.lineWidth = 1;
    ctx.strokeRect(0.5, 0.5, width - 1, height - 1);

    ctx.strokeStyle = trafficColors.grid;
    ctx.lineWidth = 1;
    for (let i = 1; i < 4; i += 1) {
        const y = padding.top + ((chartHeight / 4) * i);
        ctx.beginPath();
        ctx.moveTo(plotPadding.left, y);
        ctx.lineTo(width - plotPadding.right, y);
        ctx.stroke();
    }

    // Bar tetap 1.5px dan area chart dipenuhi dengan data yang dipadatkan/direntangkan.
    const totalBar = history.length;
    if (totalBar > 0) {
        const barWidth = 1.5;
        const pairWidth = barWidth * 2;
        const renderSlots = Math.max(1, Math.ceil(chartWidth / pairWidth));
        const renderedHistory = buildRenderedTrafficHistory(history, renderSlots);
        const totalWidth = renderedHistory.length * pairWidth;
        const startX = plotPadding.left;

        renderedHistory.forEach((item, index) => {
            const xBase = startX + (index * pairWidth);
            const rxHeight = Math.max(1, Math.round(((item.rx || 0) / maxValue) * (chartHeight - 2)));
            const txHeight = Math.max(1, Math.round(((item.tx || 0) / maxValue) * (chartHeight - 2)));

            ctx.fillStyle = trafficColors.rx;
            ctx.fillRect(xBase, height - padding.bottom - rxHeight, barWidth, rxHeight);
            ctx.fillStyle = trafficColors.tx;
            ctx.fillRect(xBase + barWidth, height - padding.bottom - txHeight, barWidth, txHeight);
        });
    }

    ctx.font = '12px Segoe UI';
    ctx.textBaseline = 'top';
    drawTrafficLegend(ctx, legendItems);
}

function drawTrafficCharts() {
    const lastRate = getLastTrafficPoint(trafficState.rateHistory);
    const lastPacket = getLastTrafficPoint(trafficState.packetHistory);

    drawTrafficChart(trafficState.rateCanvas, trafficState.rateHistory, [
        { color: trafficColors.tx, label: `Tx: ${formatBitsPerSecond(lastRate?.tx || 0)}` },
        { color: trafficColors.rx, label: `Rx: ${formatBitsPerSecond(lastRate?.rx || 0)}` }
    ]);

    drawTrafficChart(trafficState.packetCanvas, trafficState.packetHistory, [
        { color: trafficColors.tx, label: `Tx Packet: ${Math.round(lastPacket?.tx || 0)} p/s` },
        { color: trafficColors.rx, label: `Rx Packet: ${Math.round(lastPacket?.rx || 0)} p/s` }
    ]);
}

function formatBytes(bytes, decimals = 1) {
    if (!+bytes) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(k)), sizes.length - 1);
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
}

function formatBytesPerSecond(bits) {
    return `${formatBytes(Number(bits || 0) / 8, 2)}/s`;
}

function formatInteger(value) {
    return new Intl.NumberFormat('id-ID').format(Number(value || 0));
}

function sortPrintableUsers(users = []) {
    return (users || []).slice().sort((a, b) => {
        const left = String(a?.username || a?.nama || '').trim();
        const right = String(b?.username || b?.nama || '').trim();
        return left.localeCompare(right, 'id', { sensitivity: 'base' });
    });
}

async function printReceiptsOverview() {
    const button = document.getElementById('printReceiptsOverviewButton');
    if (button) {
        button.disabled = true;
    }

    try {
        const data = await fetchJson(`api/billing.php?_=${Date.now()}`);
        const users = sortPrintableUsers(data.users || []);
        const billings = data.data || [];

        if (!users.length) {
            alert('Belum ada pelanggan terdaftar untuk dicetak.');
            return;
        }

        window.receiptPrinter.printReceipts(users, billings, { variant: 'payments' });
    } catch (error) {
        alert('Gagal menyiapkan template print.');
    } finally {
        if (button) {
            button.disabled = false;
        }
    }
}

async function printNamesOverview() {
    const button = document.getElementById('printNamesOverviewButton');
    if (button) {
        button.disabled = true;
    }

    try {
        const data = await fetchJson(`api/billing.php?_=${Date.now()}`);
        const users = sortPrintableUsers(data.users || []);

        if (!users.length) {
            alert('Belum ada pelanggan terdaftar untuk dicetak.');
            return;
        }

        window.receiptPrinter.printNameTemplate(users, { rowsPerPage: 10 });
    } catch (error) {
        alert('Gagal menyiapkan template print nama.');
    } finally {
        if (button) {
            button.disabled = false;
        }
    }
}

function updateTrafficQuickStats(rx, tx, stats = null) {
    const container = document.getElementById('trafficQuickStats');
    const subtitle = document.getElementById('trafficPanelSubtitle');
    const liveBadge = document.getElementById('trafficPanelLive');
    const titleLive = document.getElementById('trafficTitleLive');
    if (!container) return;

    trafficPeak = Math.max(trafficPeak, rx, tx);
    if (subtitle && stats?.name) {
        subtitle.textContent = `${stats.name}${stats.type ? ` - ${stats.type}` : ''}`;
    }
    if (liveBadge) {
        liveBadge.textContent = stats?.running ? 'Running' : 'Idle';
        liveBadge.style.color = stats?.running ? '#86efac' : '#fda4af';
        liveBadge.style.background = stats?.running ? 'rgba(34, 197, 94, 0.18)' : 'rgba(244, 63, 94, 0.12)';
        liveBadge.style.borderColor = stats?.running ? 'rgba(34, 197, 94, 0.35)' : 'rgba(244, 63, 94, 0.28)';
    }
    if (titleLive) {
        titleLive.textContent = `Rx ${formatRate(rx)} | Tx ${formatRate(tx)}`;
    }

    const currentPacketRate = getLastTrafficPoint(trafficState.packetHistory) || { tx: 0, rx: 0 };

    container.innerHTML = `
        <div class="traffic-classic-row">
            <span>Live Tx/Rx Rate</span>
            <strong>${formatRate(tx)} / ${formatRate(rx)}</strong>
        </div>
        <div class="traffic-classic-row">
            <span>Live Tx/Rx Byte</span>
            <strong>${formatBytesPerSecond(tx)} / ${formatBytesPerSecond(rx)}</strong>
        </div>
        <div class="traffic-classic-row">
            <span>Live Tx/Rx Packet</span>
            <strong>${Math.round(currentPacketRate.tx)} p/s / ${Math.round(currentPacketRate.rx)} p/s</strong>
        </div>
        <div class="traffic-classic-row">
            <span>Peak Rate</span>
            <strong>${formatRate(trafficPeak)}</strong>
        </div>
    `;
}

function updateChart(rx, tx, stats = null, livePacketRate = null) {
    appendTrafficHistory(trafficState.rateHistory, {
        rx: Number(rx || 0),
        tx: Number(tx || 0)
    });
    appendTrafficHistory(trafficState.packetHistory, computePacketRate(stats, livePacketRate));
    drawTrafficCharts();
    updateTrafficQuickStats(rx, tx, stats);
}

const mapColors = {
    server: '#111827',
    odc: '#f59e0b',
    odp: '#8b5cf6',
    user_online: '#10b981',
    user_offline: '#ef4444',
    user_disabled: '#64748b'
};

function getNodeColor(node) {
    if (node.type === 'user') {
        return mapColors[`user_${node.status || 'offline'}`] || mapColors.user_offline;
    }

    return mapColors[node.type] || mapColors.server;
}

function buildNodeMarkerIcon(node) {
    const fill = getNodeColor(node);
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
            <circle cx="10" cy="10" r="7" fill="${fill}" stroke="rgba(255,255,255,0.98)" stroke-width="1.6" />
        </svg>
    `.trim();

    return {
        url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
        scaledSize: new google.maps.Size(20, 20),
        anchor: new google.maps.Point(10, 10)
    };
}

function buildMapPopupContent(node) {
    const popupParts = [
        `<b>${escapeHtml(node.name)}</b>`,
        `Type: ${escapeHtml(String(node.type || '-').toUpperCase())}`,
        `Status: ${escapeHtml(node.status || '-')}`
    ];

    if (node.username) {
        popupParts.push(`Username: ${escapeHtml(node.username)}`);
    }

    if (node.remote_ip) {
        const remoteIp = escapeHtml(node.remote_ip);
        popupParts.push(`Remote IP: ${remoteIp}`);
        popupParts.push(`<a href="http://${remoteIp}" target="_blank" rel="noopener noreferrer" class="btn" style="margin-top:8px; display:inline-block; padding:0.45rem 0.85rem; font-size:0.8rem;">Remote</a>`);
    }

    return popupParts.join('<br>');
}

function drawMap(data) {
    if (!map) return;

    const signature = JSON.stringify({
        nodes: data.nodes,
        edges: data.edges
    });

    if (signature === lastMapSignature) {
        return;
    }
    lastMapSignature = signature;
    hasAppliedMapBounds = false;

    mapMarkers.forEach(layer => layer.setMap(null));
    mapMarkers = [];

    if (!Array.isArray(data.nodes) || data.nodes.length === 0) {
        lastMapSignature = signature;
        hasAppliedMapBounds = false;
        return;
    }

    const nodeCoords = {};
    const bounds = new google.maps.LatLngBounds();
    data.nodes.forEach(node => {
        const position = {
            lat: Number(node.lat),
            lng: Number(node.lng)
        };
        nodeCoords[node.id] = position;
        bounds.extend(position);
    });

    data.edges.forEach(edge => {
        const source = nodeCoords[edge.source];
        const target = nodeCoords[edge.target];
        if (!source || !target) return;

        const line = new google.maps.Polyline({
            path: [source, target],
            geodesic: false,
            strokeColor: '#22c55e',
            strokeOpacity: 0.9,
            strokeWeight: 3,
            map
        });

        mapMarkers.push(line);
    });

    data.nodes.forEach(node => {
        const position = nodeCoords[node.id];
        const marker = new google.maps.Marker({
            position,
            map,
            title: String(node.name || ''),
            icon: buildNodeMarkerIcon(node)
        });

        marker.addListener('click', () => {
            if (!mapInfoWindow) return;
            mapInfoWindow.setContent(buildMapPopupContent(node));
            mapInfoWindow.open({
                map,
                anchor: marker
            });
        });

        if (node.type === 'user' && !isMobile) {
            const LabelOverlay = getMapLabelOverlayClass();
            if (LabelOverlay) {
                const labelOverlay = new LabelOverlay(new google.maps.LatLng(position.lat, position.lng), String(node.name || ''));
                labelOverlay.setMap(map);
                mapMarkers.push(labelOverlay);
            }
        }

        mapMarkers.push(marker);
    });

    setTimeout(() => {
        if (map) {
            if (data.nodes.length === 1) {
                map.setCenter(nodeCoords[data.nodes[0].id]);
                map.setZoom(mapZoomSettings.focusZoom);
            } else if (data.nodes.length > 1 && !hasAppliedMapBounds) {
                map.fitBounds(bounds);
                hasAppliedMapBounds = true;
                google.maps.event.addListenerOnce(map, 'bounds_changed', () => {
                    if (map.getZoom() > mapZoomSettings.fitBoundsMaxZoom) {
                        map.setZoom(mapZoomSettings.fitBoundsMaxZoom);
                    }
                });
            }
        }
    }, 100);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderStatusUsers(users = [], selectedStatus = 'online') {
    const panel = document.getElementById('statusUsersPanel');
    if (!panel) return;

    const normalizedStatus = String(selectedStatus || '').toLowerCase();
    const filteredUsers = (users || []).filter(user => String(user.status || '').toLowerCase() === normalizedStatus);
    const badgeClass = normalizedStatus === 'online'
        ? 'badge-green'
        : (normalizedStatus === 'disabled' ? 'badge-gray' : 'badge-red');

    if (!filteredUsers.length) {
        panel.innerHTML = `
            <div class="status-user-empty">
                Belum ada user dengan status <span class="badge ${badgeClass}">${escapeHtml(normalizedStatus)}</span>.
            </div>
        `;
        return;
    }

    panel.innerHTML = `
        <div class="status-user-list">
            ${filteredUsers.map(user => `
                <div class="status-user-row">
                    <div>
                        <strong>${escapeHtml(user.name || '-')}</strong>
                        <span>${escapeHtml(user.username || '-')}</span>
                    </div>
                    <span class="badge ${badgeClass}">${escapeHtml(normalizedStatus)}</span>
                </div>
            `).join('')}
        </div>
    `;
}

function clearStatusUsersPanel() {
    const panel = document.getElementById('statusUsersPanel');
    if (!panel) return;
    panel.innerHTML = '';
}

function bindStatusUsersDropdown(statistik = {}, users = []) {
    const dropdown = document.getElementById('statusUsersDropdown');
    const select = document.getElementById('statusUsersSelect');
    if (!dropdown || !select) return;

    dropdown.ontoggle = () => {
        if (dropdown.open) {
            renderStatusUsers(users, select.value || selectedUserStatus);
        } else {
            clearStatusUsersPanel();
        }
    };

    if (dropdown.open) {
        renderStatusUsers(users, select.value || selectedUserStatus);
    }
}

function syncStatusUsersSelect(statistik = {}, users = []) {
    const select = document.getElementById('statusUsersSelect');
    if (!select) return;

    const availableStatuses = Object.keys(statistik || {});
    if (!availableStatuses.length) {
        select.innerHTML = '<option value="online">ONLINE</option>';
        select.value = 'online';
        selectedUserStatus = 'online';
        clearStatusUsersPanel();
        bindStatusUsersDropdown(statistik, users);
        return;
    }

    if (!availableStatuses.includes(selectedUserStatus)) {
        selectedUserStatus = availableStatuses[0] || 'online';
    }

    select.innerHTML = availableStatuses.map(status => `
        <option value="${escapeHtml(status)}"${selectedUserStatus === status ? ' selected' : ''}>
            ${escapeHtml(String(status).toUpperCase())}
        </option>
    `).join('');

    select.value = selectedUserStatus;
    select.onchange = () => {
        selectedUserStatus = select.value || 'online';
        const dropdown = document.getElementById('statusUsersDropdown');
        if (dropdown?.open) {
            renderStatusUsers(users, selectedUserStatus);
        }
    };

    clearStatusUsersPanel();
    bindStatusUsersDropdown(statistik, users);
}

function renderStatistik(element, statistik, users = []) {
    const rows = Object.entries(statistik).map(([key, value]) => {
        let color = '#3b82f6';
        if (key === 'online') color = '#10b981';
        if (key === 'offline') color = '#ef4444';
        if (key === 'disabled') color = '#6b7280';

        return `
            <div class="stat-item">
                <span>${key.toUpperCase()}</span>
                <span class="badge" style="background-color:${color}">${value}</span>
            </div>
        `;
    }).join('');

    element.innerHTML = `<div class="stat-list">${rows}</div>`;
    syncStatusUsersSelect(statistik, users);
}

function renderAlerts(element, alerts) {
    if (!alerts || alerts.length === 0) {
        element.innerHTML = '<p style="color:#10b981; font-weight:500;">&#10003; Semua sistem normal.</p>';
        return;
    }

    element.innerHTML = alerts.map(alert => `
        <div style="padding:10px; margin-bottom:10px; border-left:4px solid #ef4444; background-color:#fee2e2; border-radius:4px;">
            <strong style="color:#b91c1c;">${alert.title || 'WARNING'}</strong><br>
            <span style="font-size:0.9rem; color:#7f1d1d;">${alert.message}</span><br>
            <small style="color:#991b1b;">${alert.time}</small>
        </div>
    `).join('');
}

function renderMikrotikInventory(data) {
    const summary = document.getElementById('mikrotikSummary');
    const secretsBox = document.getElementById('mikrotikSecrets');
    const activeBox = document.getElementById('mikrotikActive');
    const profilesBox = document.getElementById('mikrotikProfiles');
    if (!summary || !secretsBox || !activeBox || !profilesBox) return;

    summary.innerHTML = `
        <div class="infra-preview-card">
            <div class="infra-preview-type">Secrets</div>
            <div class="infra-preview-name">${data.secrets_count ?? 0}</div>
            <div class="infra-preview-meta">Total PPP secret di MikroTik</div>
        </div>
        <div class="infra-preview-card">
            <div class="infra-preview-type">Active PPPoE</div>
            <div class="infra-preview-name">${data.pppoe_active ?? 0}</div>
            <div class="infra-preview-meta">Koneksi aktif saat ini</div>
        </div>
        <div class="infra-preview-card">
            <div class="infra-preview-type">Profiles</div>
            <div class="infra-preview-name">${data.profiles_count ?? 0}</div>
            <div class="infra-preview-meta">Profile PPP tersedia</div>
        </div>
    `;

    const secrets = (data.inventory?.secrets || []).map(item => `
        <tr>
            <td>${item.name}</td>
            <td>${item.profile}</td>
            <td>${item.remote_address}</td>
            <td>${item.status}</td>
        </tr>
    `).join('');
    secretsBox.innerHTML = `<table><tr><th>Secret</th><th>Profile</th><th>Remote Addr</th><th>Status</th></tr>${secrets || '<tr><td colspan="4">Tidak ada data</td></tr>'}</table>`;

    const active = (data.inventory?.active || []).map(item => `
        <tr>
            <td>${item.name}</td>
            <td>${item.address}</td>
            <td>${item.service}</td>
            <td>${item.uptime}</td>
        </tr>
    `).join('');
    activeBox.innerHTML = `<table><tr><th>User</th><th>Address</th><th>Service</th><th>Uptime</th></tr>${active || '<tr><td colspan="4">Tidak ada koneksi aktif</td></tr>'}</table>`;

    const profiles = (data.inventory?.profiles || []).map(item => `
        <tr>
            <td>${item.name}</td>
            <td>${item.local_address}</td>
            <td>${item.remote_address}</td>
        </tr>
    `).join('');
    profilesBox.innerHTML = `<table><tr><th>Profile</th><th>Local Addr</th><th>Remote Addr</th></tr>${profiles || '<tr><td colspan="3">Tidak ada profile</td></tr>'}</table>`;
}

async function fetchJson(url) {
    const response = await fetch(url, { cache: 'no-store' });
    const data = await response.json();

    if (response.status === 401) {
        location.href = window.APP_LOGIN_URL || 'login';
        throw new Error('Unauthorized');
    }

    return data;
}

async function loadDashboardSummary() {
    if (document.hidden) return;
    if (dashboardState.dashboardLoading) return;
    dashboardState.dashboardLoading = true;

    try {
        const data = await fetchJson('api/dashboard.php');
        if (data.success !== false && data.statistik) {
            const availableStatuses = Object.keys(data.statistik || {});
            if (!availableStatuses.includes(selectedUserStatus)) {
                selectedUserStatus = availableStatuses[0] || 'online';
            }
            renderStatistik(document.getElementById('statistik'), data.statistik, data.users || []);
        }
        if (data.alerts) {
            renderAlerts(document.getElementById('alerts'), data.alerts);
        }
    } catch (error) {
        console.error('Gagal memuat ringkasan dashboard:', error);
    } finally {
        dashboardState.dashboardLoading = false;
    }
}

async function loadMikrotikStats() {
    if (document.hidden) return;
    if (dashboardState.mikrotikLoading) return;
    dashboardState.mikrotikLoading = true;
    // Update status awal agar tidak menunggu data terus
    const statusEl = document.getElementById('trafficStatus');
    if (statusEl) statusEl.textContent = 'Status: mengambil data...';

    try {
        const data = await fetchJson(`api/mikrotik.php?live=1&_=${Date.now()}`);
        if (data.success) {
            updateChart(data.traffic.rx, data.traffic.tx, data.interface_stats, {
                tx: Number(data.traffic?.tx_packet_rate || 0),
                rx: Number(data.traffic?.rx_packet_rate || 0)
            });
            const iface = data.meta?.traffic_interface ? ` | Interface: ${data.meta.traffic_interface}` : '';
            const runningLabel = data.interface_stats?.running ? 'link ok' : 'interface idle';
            if (statusEl) statusEl.textContent = `${runningLabel} | RX ${formatRate(data.traffic.rx)} | TX ${formatRate(data.traffic.tx)} | PPPoE ${dashboardState.lastPppoeActive}${iface}`;
            const input = document.getElementById('trafficInterfaceInput');
            const msg = document.getElementById('trafficInterfaceMsg');
            if (input && data.meta?.traffic_interface) {
                input.value = data.meta.traffic_interface;
            }
            if (msg && data.meta?.traffic_interface) {
                msg.textContent = `Interface aktif: ${data.meta.traffic_interface}`;
                msg.style.color = 'var(--text-muted)';
            }
        } else {
            if (statusEl) statusEl.textContent = `Status: ${data.message || 'Tidak tersedia'}`;
        }
    } catch (error) {
        if (statusEl) statusEl.textContent = 'Status: API MikroTik Tidak Dapat Diakses';
    } finally {
        dashboardState.mikrotikLoading = false;
    }
}

async function loadMikrotikSummary() {
    if (document.hidden) return;
    if (dashboardState.mikrotikSummaryLoading) return;
    dashboardState.mikrotikSummaryLoading = true;

    try {
        const data = await fetchJson(`api/mikrotik.php?full=1&_=${Date.now()}`);
        if (data.success) {
            dashboardState.lastPppoeActive = Number(data.pppoe_active || 0);
            renderMikrotikInventory(data);
        }
    } catch (error) {
        console.error('Gagal memuat ringkasan MikroTik:', error);
    } finally {
        dashboardState.mikrotikSummaryLoading = false;
    }
}

async function loadMapData() {
    if (document.hidden) return;
    if (dashboardState.mapLoading) return;
    dashboardState.mapLoading = true;

    try {
        const data = await fetchJson('api/map.php');
        if (data.success) {
            drawMap(data);
        }
    } catch (error) {
        console.error('Gagal memuat map:', error);
    } finally {
        dashboardState.mapLoading = false;
    }
}

window.onload = async function() {
    initTrafficPanel();
    try {
        await initMap();
    } catch (error) {
        console.error('Gagal inisialisasi Google Maps:', error);
        setDeviceLocationStatus('Google Maps gagal dimuat. Periksa API key atau koneksi internet.', 'danger');
    }
    const printReceiptsButton = document.getElementById('printReceiptsOverviewButton');
    if (printReceiptsButton) {
        printReceiptsButton.addEventListener('click', printReceiptsOverview);
    }
    const printNamesButton = document.getElementById('printNamesOverviewButton');
    if (printNamesButton) {
        printNamesButton.addEventListener('click', printNamesOverview);
    }
    const locateButton = document.getElementById('locateDeviceButton');
    if (locateButton) {
        locateButton.addEventListener('click', focusCurrentDeviceLocation);
    }
    window.addEventListener('resize', () => {
        drawTrafficCharts();
        if (map && window.google && window.google.maps) {
            const center = map.getCenter();
            google.maps.event.trigger(map, 'resize');
            if (center) {
                map.setCenter(center);
            }
        }
    });
    const trafficForm = document.getElementById('trafficInterfaceForm');
    if (trafficForm) {
        trafficForm.addEventListener('submit', saveTrafficInterface);
    }

    loadDashboardSummary();
    window.setTimeout(loadMikrotikStats, 100);
    window.setTimeout(loadMikrotikSummary, 200);
    setTimeout(loadMapData, isMobile ? 350 : 150);
    startDashboardPolling();

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopDashboardPolling();
            return;
        }

        loadDashboardSummary();
        loadMikrotikStats();
        loadMikrotikSummary();
        loadMapData();
        startDashboardPolling();
    });
};

window.focusCurrentDeviceLocation = focusCurrentDeviceLocation;
