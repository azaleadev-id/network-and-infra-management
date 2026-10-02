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
<title>Infrastruktur - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
#pickerMap { height: 480px; width: 100%; border-radius: 0.75rem; border: 1px solid #c3c6d7; margin-bottom: 1rem; z-index: 1; }
</style>
</head>
<body class="bg-surface text-on-surface">

<?php $activePage = 'infra'; require_once __DIR__ . '/partials/dashboard_sidebar.php'; ?>

<main class="lg:ml-[270px] min-h-screen transition-all">
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-outline-variant h-16 flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center hover:bg-surface-container transition-all" onclick="toggleSidebar()"><span class="material-symbols-outlined text-on-surface-variant">menu</span></button>
            <h2 class="text-lg font-black text-on-surface">Manajemen Infrastruktur</h2>
        </div>
    </header>
    <div class="p-6 max-w-[1600px] mx-auto">
        <?= app_render_tenant_expiry_banner() ?>

        <div class="bg-gradient-to-br from-amber-100 to-amber-50 rounded-xl shadow-sm mb-6" id="infraFormSection">
            <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface"><span id="infraFormTitle">Tambah Infrastruktur</span></h3></div>
            <div class="p-6">
                <form id="addInfraForm" style="box-shadow:none; padding:0; margin:0; max-width:none;">
                    <input type="hidden" name="action" id="infraFormAction" value="create">
                    <input type="hidden" name="id" id="infraIdField" value="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Tipe Node</label><select name="tipe" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required><option value="">Pilih Tipe...</option><option value="server">Server</option><option value="odc">ODC</option><option value="odp">ODP</option></select></div>
                        <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama / Label</label><input type="text" name="nama" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: ODP-JKT-01" required></div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Pilih Lokasi di Peta</label>
                        <div class="flex flex-wrap gap-3 mb-3">
                            <input type="text" id="infraMapSearchInput" class="flex-1 min-w-[200px] px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Cari alamat atau lokasi di Google Maps">
                            <button type="button" class="px-4 py-2.5 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="useCurrentLocationButton" onclick="useCurrentLocation()">Gunakan Lokasi Perangkat Sekarang</button>
                        </div>
                        <div id="pickerMap"></div>
                        <input type="text" name="koordinat" id="inputKoordinat" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Geser pin pada peta" readonly required>
                        <p class="text-xs text-on-surface-variant mt-1" id="locationHelperText">Marker biru adalah node baru. Marker berwarna lain adalah semua infra yang sudah ada.</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Keterangan (Opsional)</label>
                        <textarea name="keterangan" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" rows="3" placeholder="Deskripsi atau alamat lengkap"></textarea>
                    </div>
                    <div class="flex flex-col gap-2">
                        <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="infraSubmitButton">Simpan Node Baru</button>
                        <button type="button" class="w-full px-6 py-2.5 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all hidden" id="cancelInfraEditButton">Batal Edit</button>
                    </div>
                    <div id="addMsg" class="mt-3 text-sm font-medium"></div>
                </form>
            </div>
        </div>

        <div class="bg-gradient-to-br from-amber-100 to-amber-50 rounded-xl shadow-sm" id="infraListSection">
            <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Daftar Infrastruktur Jaringan</h3></div>
            <div class="p-6 overflow-x-auto" id="infraList">Memuat data...</div>
        </div>
    </div>
</main>

<script>
window.GOOGLE_MAPS_CONFIG = <?= json_encode([
    'apiKey' => (string) env_value('GOOGLE_MAPS_API_KEY', ''),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
let map, marker;
let googleMapsApiPromise = null;
let infraInfoWindow;
let infraPlaceAutocomplete;
let existingInfraLayers = [];
let infraItems = [];
const defaultCenter = { lat: -6.200000, lng: 106.816666 };
const infraColors = { server: '#111827', odc: '#f59e0b', odp: '#10b981' };
const locationHelperText = document.getElementById('locationHelperText');
const infraForm = document.getElementById('addInfraForm');
const cancelInfraEditButton = document.getElementById('cancelInfraEditButton');

function loadGoogleMapsApi() {
    if (window.google && window.google.maps) {
        return Promise.resolve(window.google.maps);
    }

    if (googleMapsApiPromise) {
        return googleMapsApiPromise;
    }

    const apiKey = String(window.GOOGLE_MAPS_CONFIG?.apiKey || '').trim();
    if (!apiKey) {
        return Promise.reject(new Error('Google Maps API key belum dikonfigurasi.'));
    }

    googleMapsApiPromise = new Promise((resolve, reject) => {
        const callbackName = '__initInfraGoogleMaps';
        const existingScript = document.querySelector('script[data-google-maps-loader="infra"]');
        if (existingScript) {
            window[callbackName] = () => resolve(window.google.maps);
            existingScript.addEventListener('error', () => reject(new Error('Gagal memuat Google Maps API.')), { once: true });
            return;
        }

        window[callbackName] = () => resolve(window.google.maps);
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places&v=weekly&callback=${callbackName}`;
        script.async = true;
        script.defer = true;
        script.dataset.googleMapsLoader = 'infra';
        script.onerror = () => reject(new Error('Gagal memuat Google Maps API.'));
        document.head.appendChild(script);
    });

    return googleMapsApiPromise;
}

function setCoordinateInput(latlng) {
    document.getElementById('inputKoordinat').value = `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
}

function moveInfraMarker(latlng, zoom = 16) {
    if (!marker || !map) return;
    marker.setPosition(latlng);
    setCoordinateInput(latlng);
    map.setCenter(latlng);
    map.setZoom(zoom);
}

function initInfraMapSearch(maps) {
    const input = document.getElementById('infraMapSearchInput');
    if (!input || !maps.places) {
        return;
    }

    infraPlaceAutocomplete = new maps.places.Autocomplete(input, {
        fields: ['formatted_address', 'geometry', 'name'],
    });

    infraPlaceAutocomplete.addListener('place_changed', () => {
        const place = infraPlaceAutocomplete.getPlace();
        if (!place?.geometry?.location) {
            setLocationMessage('Lokasi tidak ditemukan. Coba kata kunci lain.', 'danger');
            return;
        }

        const latlng = {
            lat: place.geometry.location.lat(),
            lng: place.geometry.location.lng()
        };
        moveInfraMarker(latlng, 16);
        input.value = place.formatted_address || place.name || input.value;
        setLocationMessage('Posisi node diperbarui dari pencarian Google Maps.', 'success');
    });
}

function setLocationMessage(message, tone = 'muted') {
    locationHelperText.textContent = message;
    if (tone === 'success') locationHelperText.style.color = '#16a34a';
    else if (tone === 'danger') locationHelperText.style.color = '#dc2626';
    else locationHelperText.style.color = '#434655';
}

function clearExistingInfraPreview() {
    existingInfraLayers.forEach(layer => {
        if (layer && typeof layer.setMap === 'function') {
            layer.setMap(null);
        }
    });
    existingInfraLayers = [];
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function resetInfraForm() {
    infraForm.reset();
    document.getElementById('infraFormTitle').textContent = 'Tambah Infrastruktur';
    document.getElementById('infraFormAction').value = 'create';
    document.getElementById('infraIdField').value = '';
    document.getElementById('infraSubmitButton').textContent = 'Simpan Node Baru';
    cancelInfraEditButton.classList.add('hidden');
    moveInfraMarker(defaultCenter, 12);
    setLocationMessage('Marker biru adalah node baru. Marker berwarna lain adalah semua infra yang sudah ada.', 'muted');
}

function startEditInfra(id) {
    const item = infraItems.find(entry => Number(entry.id) === Number(id));
    if (!item) {
        return;
    }

    document.getElementById('infraFormTitle').textContent = `Edit Lokasi Infra: ${item.nama}`;
    document.getElementById('infraFormAction').value = 'update';
    document.getElementById('infraIdField').value = item.id;
    document.getElementById('infraSubmitButton').textContent = 'Update Lokasi Infra';
    cancelInfraEditButton.classList.remove('hidden');

    infraForm.tipe.value = item.tipe || '';
    infraForm.nama.value = item.nama || '';
    infraForm.keterangan.value = item.keterangan || '';
    infraForm.koordinat.value = item.koordinat || `${defaultCenter.lat.toFixed(6)}, ${defaultCenter.lng.toFixed(6)}`;

    const coords = (infraForm.koordinat.value || '').split(',').map(part => Number(part.trim()));
    if (coords.length === 2 && Number.isFinite(coords[0]) && Number.isFinite(coords[1])) {
        moveInfraMarker({ lat: coords[0], lng: coords[1] }, 17);
    }

    setLocationMessage('Mode edit aktif. Geser marker atau klik peta untuk memperbarui lokasi infra ini.', 'success');
    infraForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function drawExistingInfraPreview(items) {
    clearExistingInfraPreview();
    items.forEach(item => {
        const coords = (item.koordinat || '').split(',').map(part => part.trim());
        if (coords.length !== 2 || Number.isNaN(Number(coords[0])) || Number.isNaN(Number(coords[1]))) {
            return;
        }

        const color = infraColors[item.tipe] || '#64748b';
        const layer = new google.maps.Marker({
            position: { lat: Number(coords[0]), lng: Number(coords[1]) },
            map,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: color,
                fillOpacity: 0.9,
                strokeColor: '#ffffff',
                strokeWeight: 2,
                scale: 6
            },
            title: String(item.nama || '')
        });
        layer.addListener('click', () => {
            if (!infraInfoWindow) {
                infraInfoWindow = new google.maps.InfoWindow();
            }
            infraInfoWindow.setContent(`<b>${escapeHtml(item.nama)}</b><br>Tipe: ${escapeHtml(String(item.tipe || '').toUpperCase())}<br>${escapeHtml(item.keterangan || 'Tanpa keterangan')}`);
            infraInfoWindow.open({ map, anchor: layer });
        });
        existingInfraLayers.push(layer);
    });
}

async function initMap() {
    const maps = await loadGoogleMapsApi();
    map = new maps.Map(document.getElementById('pickerMap'), {
        center: defaultCenter,
        zoom: 12,
        mapTypeId: maps.MapTypeId.SATELLITE,
        fullscreenControl: true,
        streetViewControl: false,
        mapTypeControl: true,
        zoomControl: true
    });

    marker = new maps.Marker({
        position: defaultCenter,
        map,
        draggable: true,
        title: 'Lokasi infra'
    });

    marker.addListener('dragend', () => {
        const position = marker.getPosition();
        if (!position) return;
        setCoordinateInput({ lat: position.lat(), lng: position.lng() });
        setLocationMessage('Posisi node diperbarui dari marker yang digeser.', 'muted');
    });

    map.addListener('click', (event) => {
        if (!event.latLng) return;
        const latlng = { lat: event.latLng.lat(), lng: event.latLng.lng() };
        marker.setPosition(latlng);
        setCoordinateInput(latlng);
        setLocationMessage('Posisi node diperbarui dari titik yang dipilih di peta.', 'muted');
    });

    setCoordinateInput(defaultCenter);
    initInfraMapSearch(maps);
}

function useCurrentLocation() {
    if (!navigator.geolocation) {
        setLocationMessage('Browser ini belum mendukung geolocation.', 'danger');
        return;
    }

    setLocationMessage('Mengambil lokasi perangkat sekarang...', 'muted');
    navigator.geolocation.getCurrentPosition(
        position => {
            const latlng = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            moveInfraMarker(latlng);
            setLocationMessage('Lokasi perangkat berhasil dipakai untuk node baru.', 'success');
        },
        error => {
            let message = 'Lokasi perangkat tidak bisa diambil.';
            if (error.code === 1) message = 'Izin lokasi ditolak. Izinkan akses lokasi di browser.';
            if (error.code === 2) message = 'Lokasi perangkat tidak tersedia.';
            if (error.code === 3) message = 'Pengambilan lokasi perangkat timeout.';
            setLocationMessage(message, 'danger');
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}

async function loadInfra() {
    try {
        const res = await fetch('api/infra.php');
        const data = await res.json();
        const list = document.getElementById('infraList');
        if (data.success && data.data && data.data.length > 0) {
            infraItems = data.data;
            drawExistingInfraPreview(data.data);
            let html = '<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">ID</th><th class="pb-3">Tipe</th><th class="pb-3">Detail Node</th><th class="pb-3">Koordinat</th><th class="pb-3">Aksi</th></tr></thead><tbody>';
            data.data.forEach(item => {
                const typeLabel = String(item.tipe || '').toUpperCase();
                let badgeClass = 'bg-gray-100 text-gray-500';
                if (item.tipe === 'server') badgeClass = 'bg-blue-100 text-blue-700';
                if (item.tipe === 'odc') badgeClass = 'bg-yellow-100 text-yellow-700';
                if (item.tipe === 'odp') badgeClass = 'bg-green-100 text-green-700';

                const typeBadge = `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${badgeClass}">${typeLabel}</span>`;

                html += `<tr class="border-b border-outline-variant/50">
                    <td class="py-3">#${item.id}</td>
                    <td class="py-3">${typeBadge}</td>
                    <td class="py-3"><strong>${item.nama}</strong><br><span class="text-xs text-on-surface-variant">${item.keterangan || '-'}</span></td>
                    <td class="py-3"><span class="font-mono text-xs">${item.koordinat || '-'}</span></td>
                    <td class="py-3">
                        <div class="flex gap-1">
                            <button type="button" class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 edit-infra-btn" data-id="${item.id}">Edit Lokasi</button>
                            <button type="button" class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90 focus-infra-btn" data-lat="${Number((item.koordinat || '').split(',')[0] || NaN)}" data-lng="${Number((item.koordinat || '').split(',')[1] || NaN)}">Lihat</button>
                            <button type="button" class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 remove-infra-btn" data-id="${item.id}" data-label="${escapeHtml(item.nama)}" data-type="${item.tipe}">Hapus</button>
                        </div>
                    </td>
                </tr>`;
            });
            html += '</tbody></table>';
            list.innerHTML = html;

            list.querySelectorAll('.edit-infra-btn').forEach(button => {
                button.onclick = () => startEditInfra(Number(button.dataset.id || 0));
            });

            list.querySelectorAll('.focus-infra-btn').forEach(button => {
                button.onclick = () => {
                    const lat = Number(button.dataset.lat);
                    const lng = Number(button.dataset.lng);
                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                        return;
                    }

                    const latlng = { lat, lng };
                    moveInfraMarker(latlng, 17);
                    setLocationMessage('Marker diarahkan ke node infra yang dipilih.', 'muted');
                };
            });

            list.querySelectorAll('.remove-infra-btn').forEach(button => {
                button.onclick = () => removeInfra(
                    Number(button.dataset.id || 0),
                    button.dataset.label || 'node',
                    (button.dataset.type || 'infra').toUpperCase()
                );
            });
        } else {
            infraItems = [];
            clearExistingInfraPreview();
            list.innerHTML = '<p class="text-sm text-secondary">Belum ada data infrastruktur.</p>';
        }
    } catch (e) {
        document.getElementById('infraList').innerHTML = '<p class="text-sm text-error">Gagal memuat data infrastruktur.</p>';
    }
}

document.getElementById('addInfraForm').onsubmit = async function(e) {
    e.preventDefault();
    const form = new FormData(this);
    const msgDiv = document.getElementById('addMsg');
    msgDiv.textContent = 'Menyimpan...';
    msgDiv.style.color = 'inherit';

    try {
        const res = await fetch('api/infra.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            msgDiv.textContent = data.message;
            msgDiv.style.color = '#16a34a';
            resetInfraForm();
            await loadInfra();
        } else {
            msgDiv.textContent = data.message || 'Gagal menyimpan data';
            msgDiv.style.color = '#dc2626';
        }
    } catch (err) {
        msgDiv.textContent = 'Terjadi kesalahan sistem';
        msgDiv.style.color = '#dc2626';
    }
    setTimeout(() => { msgDiv.textContent = ''; }, 3000);
};

async function removeInfra(id, label, typeLabel) {
    if (!id) {
        return;
    }

    if (!confirm(`Hapus ${typeLabel} ${label}? Relasi topology yang terhubung akan ikut menjadi kosong.`)) {
        return;
    }

    const form = new FormData();
    form.append('action', 'delete');
    form.append('id', id);

    const msgDiv = document.getElementById('addMsg');
    msgDiv.textContent = 'Menghapus node infra...';
    msgDiv.style.color = 'inherit';

    try {
        const res = await fetch('api/infra.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            msgDiv.textContent = data.message;
            msgDiv.style.color = '#16a34a';
            if (Number(document.getElementById('infraIdField').value) === Number(id)) {
                resetInfraForm();
            }
            await loadInfra();
        } else {
            msgDiv.textContent = data.message || 'Gagal menghapus node infra';
            msgDiv.style.color = '#dc2626';
        }
    } catch (error) {
        msgDiv.textContent = 'Terjadi kesalahan sistem';
        msgDiv.style.color = '#dc2626';
    }

    setTimeout(() => { msgDiv.textContent = ''; }, 3000);
}

function toggleSidebar() { document.getElementById('appSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }
function closeSidebar() { document.getElementById('appSidebar').classList.add('-translate-x-full'); document.getElementById('sidebarOverlay').classList.add('hidden'); }
document.querySelectorAll('#appSidebar a').forEach(l => l.addEventListener('click', () => { if (window.innerWidth < 768) closeSidebar(); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

window.useCurrentLocation = useCurrentLocation;
window.startEditInfra = startEditInfra;
cancelInfraEditButton.addEventListener('click', resetInfraForm);

window.onload = async function() {
    try {
        await initMap();
        loadInfra();
    } catch (error) {
        setLocationMessage('Google Maps gagal dimuat. Periksa API key atau koneksi internet.', 'danger');
        document.getElementById('infraList').innerHTML = '<p class="text-sm text-error">Google Maps gagal dimuat.</p>';
    }
};

async function logout() { await fetch('api/auth.php?action=logout'); location.href = '<?= htmlspecialchars(app_url('login'), ENT_QUOTES, 'UTF-8') ?>'; }
</script>
<?= app_render_tenant_expiry_script('login.php') ?>
</body>
</html>

