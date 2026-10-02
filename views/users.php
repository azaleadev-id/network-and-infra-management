<?php
require_once __DIR__ . '/../config/auth.php';
$account = app_require_tenant_panel_page();
$appName = app_setting_get('app_name', 'Nikonet');
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="icon" type="image/png" href="public/images/rtrwnet-logo-circle.png?v=<?= urlencode((string) filemtime(__DIR__ . '/../public/images/rtrwnet-logo-circle.png')) ?>">
<link rel="shortcut icon" type="image/png" href="public/images/rtrwnet-logo-circle.png?v=<?= urlencode((string) filemtime(__DIR__ . '/../public/images/rtrwnet-logo-circle.png')) ?>">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script id="tailwind-config">
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "on-primary-fixed-variant": "#003ea8",
                "tertiary": "#943700",
                "primary": "#004ac6",
                "on-background": "#191b23",
                "on-error": "#ffffff",
                "surface-container-highest": "#e1e2ed",
                "error": "#ba1a1a",
                "primary-fixed-dim": "#b4c5ff",
                "outline-variant": "#c3c6d7",
                "surface-bright": "#faf8ff",
                "surface-container-high": "#e7e7f3",
                "outline": "#737686",
                "on-primary-container": "#eeefff",
                "on-error-container": "#93000a",
                "on-surface-variant": "#2e313d",
                "on-secondary-fixed-variant": "#38485d",
                "surface-dim": "#d9d9e5",
                "on-tertiary-fixed": "#360f00",
                "on-tertiary-fixed-variant": "#7d2d00",
                "tertiary-fixed": "#ffdbcd",
                "primary-container": "#2563eb",
                "secondary-container": "#d0e1fb",
                "on-primary-fixed": "#00174b",
                "on-primary": "#ffffff",
                "secondary-fixed-dim": "#b7c8e1",
                "on-secondary-fixed": "#0b1c30",
                "error-container": "#ffdad6",
                "tertiary-container": "#bc4800",
                "surface-variant": "#e1e2ed",
                "on-secondary": "#ffffff",
                "surface-container": "#ededf9",
                "on-surface": "#0f1119",
                "secondary-fixed": "#d3e4fe",
                "tertiary-fixed-dim": "#ffb596",
                "surface-container-lowest": "#ffffff",
                "on-tertiary-container": "#ffede6",
                "on-secondary-container": "#54647a",
                "background": "#faf8ff",
                "primary-fixed": "#dbe1ff",
                "inverse-primary": "#b4c5ff",
                "inverse-on-surface": "#f0f0fb",
                "surface-tint": "#0053db",
                "inverse-surface": "#2e3039",
                "surface-container-low": "#f3f3fe",
                "secondary": "#505f76",
                "on-tertiary": "#ffffff",
                "surface": "#faf8ff"
            },
            borderRadius: {
                DEFAULT: "0.25rem",
                lg: "0.5rem",
                xl: "0.75rem",
                full: "9999px"
            },
            fontFamily: {
                sans: ["Inter"]
            }
        }
    }
}
</script>
<style>
body { font-family: 'Inter', sans-serif; }
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
#detail-panel {
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
#detail-panel.open { transform: translateX(0); }
table th, table td { padding: 0.75rem 1rem; }
</style>
</head>
<body class="bg-surface text-on-surface">

<?php $activePage = 'users'; require_once __DIR__ . '/partials/dashboard_sidebar.php'; ?>

<!-- Main Content -->
<main class="lg:ml-[270px] min-h-screen transition-all">
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-outline-variant h-16 flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button id="sidebarToggle" class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center hover:bg-surface-container transition-all" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-on-surface-variant">menu</span>
            </button>
            <h2 class="text-lg font-black text-on-surface">Manajemen User</h2>
        </div>
    </header>

    <div class="p-6 max-w-[1600px] mx-auto">
        <?= app_render_tenant_expiry_banner() ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-gradient-to-br from-blue-100 to-blue-50 border-l-4 border-primary rounded-xl p-6 shadow-sm text-center">
                <div class="text-xs text-secondary uppercase tracking-wider mb-2">Total User</div>
                <div class="text-2xl font-bold text-on-surface" id="totalUsersStat">0</div>
                <div class="text-xs text-secondary mt-1">Semua pelanggan yang terdaftar</div>
            </div>
            <div class="bg-gradient-to-br from-blue-100 to-blue-50 border-l-4 border-primary rounded-xl p-6 shadow-sm text-center">
                <div class="text-xs text-secondary uppercase tracking-wider mb-2">User Online</div>
                <div class="text-2xl font-bold text-on-surface" id="onlineUsersStat">0</div>
                <div class="text-xs text-secondary mt-1">Pelanggan aktif saat ini</div>
            </div>
            <div class="bg-gradient-to-br from-blue-100 to-blue-50 border-l-4 border-primary rounded-xl p-6 shadow-sm text-center">
                <div class="text-xs text-secondary uppercase tracking-wider mb-2">Remote IP Tersedia</div>
                <div class="text-2xl font-bold text-on-surface" id="remoteUsersStat">0</div>
                <div class="text-xs text-secondary mt-1">Siap diakses langsung dari tabel user</div>
            </div>
        </div>

        <!-- Add/Edit User Form -->
        <div class="bg-gradient-to-br from-blue-100 to-blue-50 rounded-xl shadow-sm mb-6" id="userFormSection">
            <div class="px-6 py-4 border-b border-outline-variant">
                <h3 class="text-base font-bold text-on-surface"><span id="userFormTitle">Tambah User PPPoE</span></h3>
            </div>
            <div class="p-6">
                <form id="addUserForm">
                    <input type="hidden" name="action" id="userFormAction" value="create">
                    <input type="hidden" name="id" id="userIdField" value="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1">Username</label>
                            <input type="text" name="username" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Username untuk login PPPoE" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1">Password PPPoE <span class="text-secondary font-normal" id="passwordHint">(opsional, tidak dipakai untuk login dashboard)</span></label>
                            <input type="password" name="password" id="passwordField" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Opsional untuk sync PPPoE">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama Pelanggan</label>
                            <input type="text" name="nama" id="userNameInput" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Nama Lengkap" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1">Nomor Telepon</label>
                            <input type="text" name="phone" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 081234567890">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1">Remote IP / URL</label>
                            <input type="text" name="remote_ip" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 192.168.10.2 atau https://remote.example.com">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1">Koordinat</label>
                            <input type="text" name="koordinat" id="userKoordinat" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Geser pin ke lokasi rumah pelanggan" readonly required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Titik Rumah di Peta</label>
                        <div class="flex gap-2 mb-2 flex-wrap">
                            <input type="text" id="userMapSearchInput" class="flex-1 min-w-[200px] px-3 py-2 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Cari alamat atau lokasi di Google Maps">
                            <button type="button" class="px-4 py-2 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all" onclick="useCurrentUserLocation()">Gunakan Lokasi Perangkat</button>
                        </div>
                        <div id="userPickerMap" class="h-[400px] rounded-lg border border-outline-variant"></div>
                        <div class="text-xs text-secondary mt-1" id="userLocationHelperText">Klik di map atau drag marker untuk posisi rumah pelanggan.</div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="userSubmitButton">Simpan User</button>
                        <button type="button" class="px-6 py-2.5 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all hidden" id="cancelEditButton">Batal Edit</button>
                    </div>
                    <div id="addMsg" class="mt-3 text-sm font-medium"></div>
                </form>
            </div>
        </div>

        <!-- Active Connections -->
        <div class="bg-gradient-to-br from-blue-100 to-blue-50 rounded-xl shadow-sm mb-6" id="activeConnectionsSection">
            <div class="px-6 py-4 border-b border-outline-variant">
                <h3 class="text-base font-bold text-on-surface">Semua Active PPPoE Connection</h3>
            </div>
            <div class="p-6 overflow-x-auto" id="activeConnectionsList">Memuat data koneksi aktif...</div>
        </div>

        <!-- Relations + User List -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-6" id="relationSection">
                <div class="bg-gradient-to-br from-blue-100 to-blue-50 rounded-xl shadow-sm">
                    <div class="px-6 py-4 border-b border-outline-variant">
                        <h3 class="text-base font-bold text-on-surface">Relasi Topologi User</h3>
                    </div>
                    <div class="p-6">
                        <form id="relationForm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">User</label>
                                    <select id="relationUser" name="user_id" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required><option value="">Pilih user...</option></select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Server</label>
                                    <select id="relationServer" name="server_id" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required><option value="">Pilih server...</option></select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">ODC</label>
                                    <select id="relationOdc" name="odc_id" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required><option value="">Pilih ODC...</option></select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">ODP</label>
                                    <select id="relationOdp" name="odp_id" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required><option value="">Pilih ODP...</option></select>
                                </div>
                            </div>
                            <button type="submit" class="px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Simpan Relasi</button>
                            <div id="relationMsg" class="mt-3 text-sm font-medium"></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gradient-to-br from-blue-100 to-blue-50 rounded-xl shadow-sm">
                    <div class="px-6 py-4 border-b border-outline-variant">
                        <h3 class="text-base font-bold text-on-surface">Pencarian User</h3>
                    </div>
                    <div class="p-6">
                        <input type="text" id="userSearch" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Cari nama, username, telepon, atau remote IP">
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-100 to-blue-50 rounded-xl shadow-sm" id="usersListSection">
                    <div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between">
                        <h3 class="text-base font-bold text-on-surface">Daftar Pengguna PPPoE</h3>
                        <div class="flex items-center gap-2">
                            <button type="button" class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all" id="printCustomerNamesPdfButton">PDF Nama</button>
                            <button type="button" class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 transition-all" id="printUserNamesButton">Print Nama</button>
                        </div>
                    </div>
                    <div class="p-6 overflow-x-auto" id="usersList">Memuat data...</div>
                </div>

                <div class="bg-gradient-to-br from-blue-100 to-blue-50 rounded-xl shadow-sm">
                    <div class="px-6 py-4 border-b border-outline-variant">
                        <h3 class="text-base font-bold text-on-surface">Daftar Relasi Topologi</h3>
                    </div>
                    <div class="p-6 overflow-x-auto" id="relationList">Memuat data...</div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="public/js/receipt-print.js?v=<?= urlencode((string) filemtime(__DIR__ . '/../public/js/receipt-print.js')) ?>"></script>
<script>
if (window.receiptPrinter && typeof window.receiptPrinter.configure === 'function') {
    window.receiptPrinter.configure({
        appName: <?= json_encode($appName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        billingDueDay: <?= (int) app_setting_get('billing_due_day', 27) ?>
    });
}

window.GOOGLE_MAPS_CONFIG = <?= json_encode([
    'apiKey' => (string) env_value('GOOGLE_MAPS_API_KEY', ''),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
let userMap, userMarker, userInfoWindow, userPlaceAutocomplete, googleMapsApiPromise = null;
let topologyPreviewLayers = [], allUsers = [], visibleUsers = [], activeConnections = [];
let relationOptions = { users: [], servers: [], odcs: [], odps: [] };
const defaultCenter = { lat: -6.200000, lng: 106.816666 };
const isMobile = window.matchMedia('(max-width: 768px)').matches;
const userForm = document.getElementById('addUserForm');
const cancelEditButton = document.getElementById('cancelEditButton');
const userLocationHelperText = document.getElementById('userLocationHelperText');

function loadGoogleMapsApi() {
    if (window.google && window.google.maps) return Promise.resolve(window.google.maps);
    if (googleMapsApiPromise) return googleMapsApiPromise;
    const apiKey = String(window.GOOGLE_MAPS_CONFIG?.apiKey || '').trim();
    if (!apiKey) return Promise.reject(new Error('Google Maps API key belum dikonfigurasi.'));
    googleMapsApiPromise = new Promise((resolve, reject) => {
        const callbackName = '__initUsersGoogleMaps';
        const existingScript = document.querySelector('script[data-google-maps-loader="users"]');
        if (existingScript) { window[callbackName] = () => resolve(window.google.maps); existingScript.addEventListener('error', () => reject(new Error('Gagal memuat Google Maps API.')), { once: true }); return; }
        window[callbackName] = () => resolve(window.google.maps);
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places&v=weekly&callback=${callbackName}`;
        script.async = true; script.defer = true; script.dataset.googleMapsLoader = 'users';
        script.onerror = () => reject(new Error('Gagal memuat Google Maps API.'));
        document.head.appendChild(script);
    });
    return googleMapsApiPromise;
}

function syncCoords(latlng) {
    document.getElementById('userKoordinat').value = `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
}

function setUserLocationMessage(message, tone = 'muted') {
    const colors = { muted: 'var(--text-muted)', success: 'var(--success)', danger: 'var(--danger)' };
    userLocationHelperText.textContent = message;
    userLocationHelperText.style.color = colors[tone] || colors.muted;
}

function setFormMessage(message, tone = 'muted') {
    const msgDiv = document.getElementById('addMsg');
    const colors = { muted: 'inherit', success: 'var(--success)', danger: 'var(--danger)' };
    msgDiv.textContent = message;
    msgDiv.style.color = colors[tone] || colors.muted;
}

function moveUserMarker(latlng, zoom = (isMobile ? 14 : 15)) {
    if (!userMarker || !userMap) return;
    userMarker.setPosition(latlng);
    syncCoords(latlng);
    userMap.setCenter(latlng);
    userMap.setZoom(zoom);
}

function initUserMapSearch(maps) {
    const input = document.getElementById('userMapSearchInput');
    if (!input || !maps.places) return;
    userPlaceAutocomplete = new maps.places.Autocomplete(input, { fields: ['formatted_address', 'geometry', 'name'] });
    userPlaceAutocomplete.addListener('place_changed', () => {
        const place = userPlaceAutocomplete.getPlace();
        if (!place?.geometry?.location) { setUserLocationMessage('Lokasi tidak ditemukan. Coba kata kunci lain.', 'danger'); return; }
        const latlng = { lat: place.geometry.location.lat(), lng: place.geometry.location.lng() };
        moveUserMarker(latlng, isMobile ? 15 : 16);
        input.value = place.formatted_address || place.name || input.value;
        setUserLocationMessage('Lokasi pelanggan diperbarui dari pencarian Google Maps.', 'success');
    });
}

function scrollToUserForm() { userForm.scrollIntoView({ behavior: 'smooth', block: 'start' }); }

function escapeHtml(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function buildRemoteHref(value) {
    const remoteValue = String(value || '').trim();
    if (!remoteValue) return '';
    if (/^[a-z][a-z0-9+.-]*:\/\//i.test(remoteValue)) return remoteValue;
    return `http://${remoteValue}`;
}

function buildRemoteButton(value) {
    const href = buildRemoteHref(value);
    if (!href) return '<span class="text-secondary text-xs">-</span>';
    return `<a class="inline-flex items-center px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 transition-all" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">Remote</a>`;
}

function clearTopologyPreview() {
    topologyPreviewLayers.forEach(layer => { if (layer && typeof layer.setMap === 'function') layer.setMap(null); });
    topologyPreviewLayers = [];
}

function getPreviewNodeColor(node) {
    if (node.type === 'server') return '#111827';
    if (node.type === 'odc') return '#f59e0b';
    if (node.type === 'odp') return '#8b5cf6';
    if (node.type === 'user' && node.status === 'online') return '#10b981';
    if (node.type === 'user' && node.status === 'disabled') return '#64748b';
    return '#ef4444';
}

function buildPreviewPopup(node) {
    const parts = [`<b>${escapeHtml(node.name || '-')}</b>`, `Type: ${escapeHtml(String(node.type || '-').toUpperCase())}`];
    if (node.username) parts.push(`Username: ${escapeHtml(node.username)}`);
    if (node.status) parts.push(`Status: ${escapeHtml(String(node.status).toUpperCase())}`);
    if (node.remote_ip) { parts.push(`Remote IP: ${escapeHtml(node.remote_ip)}`); parts.push(`<a href="${escapeHtml(buildRemoteHref(node.remote_ip))}" target="_blank" rel="noopener noreferrer" class="px-3 py-1 bg-primary text-white text-xs rounded-lg inline-block mt-2">Remote</a>`); }
    return parts.join('<br>');
}

function renderTopologyPreview(data) {
    if (!userMap || !(window.google && window.google.maps)) return;
    clearTopologyPreview();
    if (!Array.isArray(data?.nodes) || data.nodes.length === 0) return;
    const nodeCoords = {};
    data.nodes.forEach(node => { nodeCoords[node.id] = { lat: Number(node.lat), lng: Number(node.lng) }; });
    (data.edges || []).forEach(edge => {
        const source = nodeCoords[edge.source], target = nodeCoords[edge.target];
        if (!source || !target) return;
        topologyPreviewLayers.push(new google.maps.Polyline({ path: [source, target], strokeColor: '#0f172a', strokeWeight: 5, strokeOpacity: 0.6, map: userMap }));
        topologyPreviewLayers.push(new google.maps.Polyline({ path: [source, target], strokeColor: '#f8fafc', strokeWeight: 2.5, strokeOpacity: 0.95, icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, scale: 3 }, offset: '0', repeat: '14px' }], map: userMap }));
    });
    data.nodes.forEach(node => {
        const marker = new google.maps.Marker({
            position: { lat: Number(node.lat), lng: Number(node.lng) },
            map: userMap,
            icon: { path: google.maps.SymbolPath.CIRCLE, fillColor: getPreviewNodeColor(node), fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2, scale: 7 },
            title: String(node.name || '')
        });
        marker.addListener('click', () => {
            if (!userInfoWindow) userInfoWindow = new google.maps.InfoWindow();
            userInfoWindow.setContent(buildPreviewPopup(node));
            userInfoWindow.open({ map: userMap, anchor: marker });
        });
        topologyPreviewLayers.push(marker);
    });
}

async function loadUserTopologyPreview() {
    try { const res = await fetch(`api/map.php?_=${Date.now()}`); const data = await res.json(); if (data.success) renderTopologyPreview(data); } catch (error) {}
}

function renderSelectOptions(id, items, labelBuilder) {
    const select = document.getElementById(id);
    const currentValue = select.value;
    const placeholder = select.querySelector('option') ? select.querySelector('option').outerHTML : '<option value="">Pilih data...</option>';
    select.innerHTML = placeholder;
    items.forEach(item => { const o = document.createElement('option'); o.value = item.id; o.textContent = labelBuilder(item); select.appendChild(o); });
    if ([...select.options].some(o => o.value === currentValue)) select.value = currentValue;
}

function applyRelationOptions() {
    renderSelectOptions('relationUser', relationOptions.users || [], item => `${item.nama} (${item.username})`);
    renderSelectOptions('relationServer', relationOptions.servers || [], item => item.nama);
    renderSelectOptions('relationOdc', relationOptions.odcs || [], item => item.nama);
    renderSelectOptions('relationOdp', relationOptions.odps || [], item => item.nama);
}

function renderRelations(relations) {
    const list = document.getElementById('relationList');
    if (!relations.length) { list.innerHTML = '<p class="text-sm text-secondary">Belum ada relasi topologi.</p>'; return; }
    let html = '<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">User</th><th class="pb-3">Server</th><th class="pb-3">ODC</th><th class="pb-3">ODP</th><th class="pb-3">Status</th></tr></thead><tbody>';
    relations.forEach(item => {
        const isComplete = Number(item.is_complete || 0) === 1;
        const badge = isComplete ? '<span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Lengkap</span>' : '<span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">Perlu dicek</span>';
        html += `<tr class="border-b border-outline-variant/50"><td class="py-3"><strong>${item.user_name}</strong><br><span class="text-xs text-secondary">${item.username}</span></td><td class="py-3">${item.server_name || '-'}</td><td class="py-3">${item.odc_name || '-'}</td><td class="py-3">${item.odp_name || '-'}</td><td class="py-3">${badge}</td></tr>`;
    });
    html += '</tbody></table>';
    list.innerHTML = html;
}

async function loadRelations() {
    try {
        const res = await fetch('api/relations.php');
        const data = await res.json();
        if (!data.success) { document.getElementById('relationList').innerHTML = '<p class="text-sm text-error">Gagal memuat relasi.</p>'; return; }
        relationOptions = data.options || relationOptions;
        applyRelationOptions();
        renderRelations(data.data || []);
    } catch (error) { document.getElementById('relationList').innerHTML = '<p class="text-sm text-error">Gagal memuat relasi.</p>'; }
}

async function initUserMap() {
    const maps = await loadGoogleMapsApi();
    userMap = new maps.Map(document.getElementById('userPickerMap'), {
        center: defaultCenter, zoom: isMobile ? 13 : 14, mapTypeId: maps.MapTypeId.SATELLITE,
        fullscreenControl: true, streetViewControl: false, mapTypeControl: true, zoomControl: true
    });
    userMarker = new maps.Marker({ position: defaultCenter, map: userMap, draggable: true, title: 'Lokasi pelanggan' });
    syncCoords(defaultCenter);
    userMarker.addListener('drag', () => { const p = userMarker.getPosition(); if (p) syncCoords({ lat: p.lat(), lng: p.lng() }); });
    userMarker.addListener('dragend', () => { const p = userMarker.getPosition(); if (p) { syncCoords({ lat: p.lat(), lng: p.lng() }); setUserLocationMessage('Lokasi pelanggan diperbarui dari marker yang digeser.', 'muted'); } });
    userMap.addListener('click', (event) => { if (!event.latLng) return; const latlng = { lat: event.latLng.lat(), lng: event.latLng.lng() }; userMarker.setPosition(latlng); syncCoords(latlng); setUserLocationMessage('Lokasi pelanggan diperbarui dari titik yang dipilih di peta.', 'muted'); });
    initUserMapSearch(maps);
}

function useCurrentUserLocation() {
    if (!navigator.geolocation) { setUserLocationMessage('Browser ini belum mendukung geolocation.', 'danger'); return; }
    setUserLocationMessage('Mengambil lokasi perangkat sekarang...', 'muted');
    navigator.geolocation.getCurrentPosition(
        position => { moveUserMarker({ lat: position.coords.latitude, lng: position.coords.longitude }, isMobile ? 15 : 16); setUserLocationMessage('Lokasi perangkat berhasil dipakai untuk titik pelanggan.', 'success'); },
        error => { let m = 'Lokasi perangkat tidak bisa diambil.'; if (error.code === 1) m = 'Izin lokasi ditolak.'; if (error.code === 2) m = 'Lokasi perangkat tidak tersedia.'; if (error.code === 3) m = 'Pengambilan lokasi timeout.'; setUserLocationMessage(m, 'danger'); },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

function renderUsers(data) {
    visibleUsers = Array.isArray(data) ? data.slice() : [];
    const list = document.getElementById('usersList');
    if (!data.length) { list.innerHTML = '<p class="text-sm text-secondary">Tidak ada pengguna yang ditemukan.</p>'; return; }
    let html = '<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">Username</th><th class="pb-3">Pelanggan</th><th class="pb-3">Telepon</th><th class="pb-3">Remote IP</th><th class="pb-3">Status</th><th class="pb-3">Aksi</th></tr></thead><tbody>';
    data.forEach(u => {
        let badgeClass = 'bg-red-100 text-red-700';
        if (u.status === 'online') badgeClass = 'bg-green-100 text-green-700';
        if (u.status === 'disabled') badgeClass = 'bg-gray-100 text-gray-500';
        const statusBadge = `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${badgeClass}">${u.status.toUpperCase()}</span>`;
        const remoteValue = u.remote_ip_live || u.remote_ip || '';
        const remoteButton = buildRemoteButton(remoteValue);
        html += `<tr class="border-b border-outline-variant/50"><td class="py-3 font-medium">${u.username}</td><td class="py-3">${u.nama}</td><td class="py-3">${u.phone || '-'}</td><td class="py-3">${remoteValue ? escapeHtml(remoteValue) : '-'}</td><td class="py-3">${statusBadge}</td><td class="py-3"><div class="flex gap-1"><button class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90" onclick="startEditUser(${u.id})">Edit</button>${remoteButton}<button class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 remove-user-btn" data-id="${u.id}" data-label="${u.nama || u.username}">Remove</button></div></td></tr>`;
    });
    html += '</tbody></table>';
    list.innerHTML = html;
    list.querySelectorAll('.remove-user-btn').forEach(button => {
        button.onclick = () => removeUser(Number(button.dataset.id || 0), button.dataset.label || 'user');
    });
}

function updateUserStats(users) {
    document.getElementById('totalUsersStat').textContent = users.length;
    document.getElementById('onlineUsersStat').textContent = users.filter(user => user.status === 'online').length;
    document.getElementById('remoteUsersStat').textContent = users.filter(user => !!(user.remote_ip_live || user.remote_ip)).length;
}

async function loadUsers() {
    try {
        const res = await fetch('api/users.php');
        const data = await res.json();
        if (data.success && data.data) {
            allUsers = data.data;
            updateUserStats(allUsers);
            renderUsers(allUsers);
            relationOptions.users = allUsers.map(user => ({ id: user.id, username: user.username, nama: user.nama }));
            applyRelationOptions();
            renderActiveConnections(activeConnections);
            loadUserTopologyPreview();
        } else { document.getElementById('usersList').innerHTML = '<p class="text-sm text-error">Gagal memuat daftar pengguna.</p>'; }
    } catch (e) { document.getElementById('usersList').innerHTML = '<p class="text-sm text-error">Gagal memuat daftar pengguna.</p>'; }
}

function applyActiveConnectionToForm(connectionName, address = '') {
    const existingUser = allUsers.find(user => String(user.username || '').toLowerCase() === String(connectionName || '').toLowerCase());
    if (existingUser) { startEditUser(existingUser.id); userForm.remote_ip.value = address && address !== '-' ? address : (existingUser.remote_ip || ''); setFormMessage(`Mode edit dibuka untuk user ${existingUser.username} dari active connection.`, 'success'); }
    else { resetUserForm(); userForm.username.value = connectionName || ''; userForm.nama.value = connectionName || ''; userForm.remote_ip.value = address && address !== '-' ? address : ''; document.getElementById('userFormTitle').textContent = 'Tambah User dari Active Connection'; setFormMessage(`Form diisi dari active connection ${connectionName}. Lengkapi data lalu simpan user.`, 'success'); }
    scrollToUserForm();
}

function renderActiveConnections(items) {
    const list = document.getElementById('activeConnectionsList');
    if (!Array.isArray(items) || items.length === 0) { list.innerHTML = '<p class="text-sm text-secondary">Belum ada koneksi PPPoE aktif.</p>'; return; }
    let html = '<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">User</th><th class="pb-3">Address</th><th class="pb-3">Service</th><th class="pb-3">Uptime</th><th class="pb-3">Caller ID</th><th class="pb-3">Aksi</th></tr></thead><tbody>';
    items.forEach(item => {
        const remoteAction = item.address && item.address !== '-' ? buildRemoteButton(item.address) : '<span class="text-xs text-secondary">-</span>';
        const existingUser = allUsers.find(user => String(user.username || '').toLowerCase() === String(item.name || '').toLowerCase());
        const syncLabel = existingUser ? 'Edit User' : 'Pakai ke Form';
        html += `<tr class="border-b border-outline-variant/50"><td class="py-3 font-medium">${item.name || '-'}</td><td class="py-3">${item.address || '-'}</td><td class="py-3">${item.service || '-'}</td><td class="py-3">${item.uptime || '-'}</td><td class="py-3">${item.caller_id || '-'}</td><td class="py-3"><div class="flex gap-1">${remoteAction}<button class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 use-active-connection-btn" data-name="${item.name || ''}" data-address="${item.address || ''}">${syncLabel}</button></div></td></tr>`;
    });
    html += '</tbody></table>';
    list.innerHTML = html;
    list.querySelectorAll('.use-active-connection-btn').forEach(button => {
        button.onclick = () => applyActiveConnectionToForm(button.dataset.name || '', button.dataset.address || '');
    });
}

async function loadActiveConnections() {
    try {
        const res = await fetch(`api/mikrotik.php?live=1&full=1&_=${Date.now()}`);
        const data = await res.json();
        if (!data.success) { document.getElementById('activeConnectionsList').innerHTML = `<p class="text-sm text-error">${data.message || 'Gagal memuat koneksi aktif.'}</p>`; return; }
        activeConnections = (data.inventory?.active || []).slice().sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'id', { sensitivity: 'base' }));
        renderActiveConnections(activeConnections);
    } catch (error) { document.getElementById('activeConnectionsList').innerHTML = '<p class="text-sm text-error">Gagal memuat koneksi aktif.</p>'; }
}

document.getElementById('userSearch').addEventListener('input', function() {
    const keyword = this.value.trim().toLowerCase();
    if (!keyword) { renderUsers(allUsers); return; }
    renderUsers(allUsers.filter(user => [user.username, user.nama, user.phone, user.remote_ip_live, user.remote_ip].filter(Boolean).some(value => String(value).toLowerCase().includes(keyword))));
});

function resetUserForm() {
    userForm.reset();
    document.getElementById('userFormTitle').textContent = 'Tambah User PPPoE';
    document.getElementById('userFormAction').value = 'create';
    document.getElementById('userIdField').value = '';
    document.getElementById('userSubmitButton').textContent = 'Simpan User';
    document.getElementById('passwordField').required = false;
    document.getElementById('passwordHint').textContent = '(opsional, tidak dipakai untuk login dashboard)';
    cancelEditButton.classList.add('hidden');
    moveUserMarker(defaultCenter, isMobile ? 13 : 14);
    setUserLocationMessage('Klik di map atau drag marker untuk posisi rumah pelanggan saat tambah user.', 'muted');
}

window.startEditUser = function(id) {
    const user = allUsers.find(item => Number(item.id) === Number(id));
    if (!user) return;
    document.getElementById('userFormTitle').textContent = `Edit User: ${user.nama}`;
    document.getElementById('userFormAction').value = 'update';
    document.getElementById('userIdField').value = user.id;
    document.getElementById('userSubmitButton').textContent = 'Update User';
    document.getElementById('passwordField').required = false;
    document.getElementById('passwordHint').textContent = '(isi hanya jika mau update password PPPoE)';
    cancelEditButton.classList.remove('hidden');
    setUserLocationMessage('Mode edit aktif. Titik map user ini bisa diubah dengan klik peta atau drag marker.', 'success');
    userForm.username.value = user.username || '';
    userForm.password.value = '';
    userForm.nama.value = user.nama || '';
    userForm.phone.value = user.phone || '';
    userForm.remote_ip.value = user.remote_ip || '';
    userForm.koordinat.value = user.koordinat || `${defaultCenter.lat.toFixed(6)}, ${defaultCenter.lng.toFixed(6)}`;
    document.getElementById('relationUser').value = String(user.id || '');
    document.getElementById('relationServer').value = String(user.server_id || '');
    document.getElementById('relationOdc').value = String(user.odc_id || '');
    document.getElementById('relationOdp').value = String(user.odp_id || '');
    const coords = (userForm.koordinat.value || '').split(',').map(part => Number(part.trim()));
    if (coords.length === 2 && Number.isFinite(coords[0]) && Number.isFinite(coords[1])) moveUserMarker({ lat: coords[0], lng: coords[1] });
    scrollToUserForm();
};

window.removeUser = async function(id, label) {
    if (!confirm(`Hapus user ${label}? Data relasi dan billing user ini juga akan ikut dihapus.`)) return;
    const form = new FormData();
    form.append('action', 'delete'); form.append('id', id);
    try {
        const res = await fetch('api/users.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) { alert(data.message || 'Gagal menghapus user'); return; }
        await Promise.all([loadUsers(), loadRelations()]);
        if (Number(document.getElementById('userIdField').value) === Number(id)) resetUserForm();
    } catch (error) { alert('Terjadi kesalahan sistem'); }
};

cancelEditButton.addEventListener('click', resetUserForm);

document.getElementById('addUserForm').onsubmit = async function(e) {
    e.preventDefault();
    const form = new FormData(this);
    const submitButton = this.querySelector('button[type="submit"]');
    setFormMessage('Menyimpan...', 'muted');
    submitButton.disabled = true;
    try {
        const res = await fetch('api/users.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) { setFormMessage(data.message, 'success'); resetUserForm(); await Promise.all([loadUsers(), loadRelations(), loadUserTopologyPreview()]); }
        else { setFormMessage(data.message || 'Gagal menyimpan data', 'danger'); }
    } catch (err) { setFormMessage('Terjadi kesalahan sistem', 'danger'); }
    finally { submitButton.disabled = false; }
    setTimeout(() => { document.getElementById('addMsg').textContent = ''; }, 3000);
};

document.getElementById('relationForm').onsubmit = async function(e) {
    e.preventDefault();
    const form = new FormData(this);
    const msgDiv = document.getElementById('relationMsg');
    msgDiv.textContent = 'Menyimpan relasi...'; msgDiv.style.color = 'inherit';
    try {
        const res = await fetch('api/relations.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) { msgDiv.textContent = data.message; msgDiv.style.color = 'var(--success)'; await Promise.all([loadRelations(), loadUsers(), loadUserTopologyPreview()]); }
        else { msgDiv.textContent = data.message || 'Gagal menyimpan relasi'; msgDiv.style.color = 'var(--danger)'; }
    } catch (err) { msgDiv.textContent = 'Terjadi kesalahan sistem'; msgDiv.style.color = 'var(--danger)'; }
    setTimeout(() => { msgDiv.textContent = ''; }, 3000);
};

async function logout() {
    await fetch('api/auth.php?action=logout');
    location.href = '<?= htmlspecialchars(app_url('login'), ENT_QUOTES, 'UTF-8') ?>';
}

function toggleSidebar() { document.getElementById('appSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }
function closeSidebar() { document.getElementById('appSidebar').classList.add('-translate-x-full'); document.getElementById('sidebarOverlay').classList.add('hidden'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
document.querySelectorAll('#appSidebar a').forEach(l => l.addEventListener('click', () => { if (window.innerWidth < 768) closeSidebar(); }));

function printUserNames() {
    if (!window.receiptPrinter || typeof window.receiptPrinter.printNameTemplate !== 'function') { alert('Fitur print belum siap.'); return; }
    window.receiptPrinter.printNameTemplate(visibleUsers, { rowsPerPage: 10 });
}

function printCustomerNamesPdf() {
    const names = (visibleUsers || [])
        .map(user => String(user?.nama || '').trim())
        .filter(Boolean)
        .sort((left, right) => left.localeCompare(right, 'id', { sensitivity: 'base' }));

    if (!names.length) {
        alert('Tidak ada nama pelanggan untuk dicetak.');
        return;
    }

    const printedAt = new Date().toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
    const printAppName = <?= json_encode($appName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const rows = names.map((name, index) => `<div class="name-item"><span class="number">${index + 1}.</span> ${escapeHtml(name)}</div>`).join('');
    const printWindow = window.open('', '_blank', 'width=900,height=700');

    if (!printWindow) {
        alert('Popup print diblokir browser.');
        return;
    }

    printWindow.document.open();
    printWindow.document.write(`<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Nama Pelanggan</title>
<style>
@page { size: A4 portrait; margin: 10mm; }
* { box-sizing: border-box; }
body {
    margin: 0;
    color: #111827;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
}
.header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-end;
    padding-bottom: 6px;
    border-bottom: 1px solid #d1d5db;
    margin-bottom: 8px;
}
h1 {
    margin: 0;
    font-size: 16px;
    line-height: 1.2;
}
.meta {
    color: #4b5563;
    font-size: 10px;
    text-align: right;
}
.names-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 4px 8px;
}
.name-item {
    min-height: 19px;
    padding: 3px 5px;
    border-bottom: 1px solid #e5e7eb;
    line-height: 1.2;
    break-inside: avoid;
    overflow-wrap: anywhere;
}
.number {
    font-weight: 700;
}
@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Daftar Nama Pelanggan</h1>
        </div>
        <div class="meta">
            <div>${escapeHtml(printAppName)}</div>
            <div>${escapeHtml(printedAt)}</div>
            <div>${names.length} pelanggan</div>
        </div>
    </div>
    <div class="names-grid">${rows}</div>
<script>
window.onload = function() {
    window.focus();
    window.print();
};
<\/script>
</body>
</html>`);
    printWindow.document.close();
}

window.useCurrentUserLocation = useCurrentUserLocation;
document.getElementById('printCustomerNamesPdfButton').addEventListener('click', printCustomerNamesPdf);
document.getElementById('printUserNamesButton').addEventListener('click', printUserNames);

window.onload = async function() {
    try {
        await initUserMap();
        resetUserForm();
        loadUsers();
        loadRelations();
        setTimeout(loadActiveConnections, 150);
        setTimeout(loadUserTopologyPreview, 300);
        setInterval(loadActiveConnections, isMobile ? 12000 : 8000);
    } catch (error) {
        setUserLocationMessage('Google Maps gagal dimuat. Periksa API key atau koneksi internet.', 'danger');
        document.getElementById('usersList').innerHTML = '<p class="text-sm text-error">Google Maps gagal dimuat.</p>';
    }
};
</script>
<?= app_render_tenant_expiry_script('login.php') ?>
</body>
</html>

