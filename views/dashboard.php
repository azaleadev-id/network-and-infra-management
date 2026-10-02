<?php
require_once __DIR__ . '/../config/auth.php';
$account = app_require_tenant_panel_page();
$appName = app_setting_get('app_name', 'Nikonet');
$trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'));
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="icon" type="image/png" href="public/images/rtrwnet-logo-circle.png">
<link rel="shortcut icon" type="image/png" href="public/images/rtrwnet-logo-circle.png">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "primary": "#004ac6",
                "on-primary": "#ffffff",
                "primary-container": "#2563eb",
                "secondary": "#505f76",
                "on-secondary": "#ffffff",
                "secondary-container": "#d0e1fb",
                "error": "#ba1a1a",
                "on-error": "#ffffff",
                "error-container": "#ffdad6",
                "surface": "#faf8ff",
                "on-surface": "#0f1119",
                "on-surface-variant": "#2e313d",
                "surface-container-lowest": "#ffffff",
                "surface-container-low": "#f3f3fe",
                "outline-variant": "#c3c6d7",
                "primary-fixed": "#dbe1ff",
                "primary-fixed-dim": "#b4c5ff"
            },
            borderRadius: {
                DEFAULT: "0.25rem",
                lg: "0.5rem",
                xl: "0.75rem",
                "2xl": "1rem",
                "3xl": "1.5rem",
                "4xl": "2rem"
            },
            fontFamily: { sans: ["Inter"] }
        }
    }
}
</script>
<style>
body { font-family: 'Inter', sans-serif; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
.material-symbols-outlined.fill { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-thumb { background: #c3c6d7; border-radius: 10px; }
</style>
</head>
<body class="bg-surface text-on-surface">

<?php $activePage = 'dashboard'; require_once __DIR__ . '/partials/dashboard_sidebar.php'; ?>

<main class="lg:ml-[270px] min-h-screen transition-all">
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-outline-variant h-16 flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center hover:bg-surface-container transition-all" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-on-surface-variant">menu</span>
            </button>
            <h2 class="text-lg font-black text-on-surface">Dashboard</h2>
        </div>
        <button onclick="toggleRefresh()" class="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-bold shadow-sm hover:opacity-90 transition-all">
            <span class="material-symbols-outlined text-[18px]">refresh</span>
            Refresh
        </button>
    </header>

    <div class="p-4 lg:p-6 max-w-7xl mx-auto space-y-8">
        <?= app_render_tenant_expiry_banner() ?>

        <!-- Greeting -->
        <div>
            <h1 class="text-2xl lg:text-3xl font-black text-on-surface">Selamat Datang, <?= htmlspecialchars($account['username'] ?? 'Operator', ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p class="text-on-surface-variant mt-1">Berikut ringkasan jaringan Anda hari ini.</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4" id="dashboardOverview">
            <div class="relative overflow-hidden bg-gradient-to-br from-blue-100 to-blue-50 border-l-4 border-primary rounded-2xl p-5 shadow-sm hover:shadow-lg transition-all">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-primary/15 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-[22px]">group</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Pelanggan</p>
                        <p class="text-2xl font-black text-on-surface" id="totalUsersStat">0</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <span class="w-2 h-2 rounded-full bg-primary"></span>
                    <span id="onlineUsersStat">0 online</span>
                </div>
            </div>
            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-100 to-emerald-50 border-l-4 border-emerald-500 rounded-2xl p-5 shadow-sm hover:shadow-lg transition-all">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-emerald-200/50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-emerald-700 text-[22px]">payments</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Tagihan Bulan Ini</p>
                        <p class="text-2xl font-black text-on-surface" id="billingTotalStat">0</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span id="billingPaidStat">0 lunas</span>
                </div>
            </div>
            <div class="relative overflow-hidden bg-gradient-to-br from-violet-100 to-violet-50 border-l-4 border-violet-500 rounded-2xl p-5 shadow-sm hover:shadow-lg transition-all">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-violet-200/50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-violet-700 text-[22px]">settings_ethernet</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">PPPoE Aktif</p>
                        <p class="text-2xl font-black text-on-surface" id="activePppoeStat">0</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                    <span>koneksi saat ini</span>
                </div>
            </div>
            <div class="relative overflow-hidden bg-gradient-to-br from-amber-100 to-amber-50 border-l-4 border-amber-500 rounded-2xl p-5 shadow-sm hover:shadow-lg transition-all">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-200/50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-amber-700 text-[22px]">router</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Node Infrastruktur</p>
                        <p class="text-2xl font-black text-on-surface" id="infraTotalStat">0</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>server + odc + odp</span>
                </div>
            </div>
        </div>

        <!-- Traffic Monitoring -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm" id="dashboardTraffic">
            <div class="p-5 border-b border-gray-200 flex items-center justify-between flex-wrap gap-2" style="background: linear-gradient(135deg, #f0f4ff 0%, #faf8ff 100%);">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-blue-600 flex items-center justify-center shadow-sm">
                        <span class="material-symbols-outlined text-white text-[20px]">monitoring</span>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-on-surface">Traffic</h3>
                        <span id="trafficTitleLive" class="text-xs text-on-surface-variant">Rx 0.00 Mb/s | Tx 0.00 Mb/s</span>
                    </div>
                </div>
                <span id="trafficPanelLive" class="px-3 py-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white text-xs font-bold rounded-full shadow-sm">Live</span>
            </div>
            <form id="trafficInterfaceForm" class="px-5 py-3 border-b border-gray-200 bg-surface-container-low/50">
                <div class="flex items-center gap-3 flex-wrap">
                    <label class="text-sm font-medium text-on-surface-variant">Interface</label>
                    <input type="text" id="trafficInterfaceInput" name="traffic_interface" class="px-3 py-2 bg-white border border-gray-200 rounded-xl text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= htmlspecialchars($trafficInterface, ENT_QUOTES, 'UTF-8') ?>" placeholder="ether1">
                    <button type="submit" class="px-4 py-2 bg-primary text-on-primary text-sm font-bold rounded-xl hover:opacity-90 transition-all">Update</button>
                </div>
            </form>
            <div class="p-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="text-center p-3 bg-surface-container-low rounded-xl"><span class="block text-xs text-on-surface-variant">Rx / Tx</span><strong class="text-sm text-on-surface" id="trafficRateDisplay">0.00 / 0.00 Mb/s</strong></div>
                    <div class="text-center p-3 bg-surface-container-low rounded-xl"><span class="block text-xs text-on-surface-variant">Total Bytes</span><strong class="text-sm text-on-surface" id="trafficBytesDisplay">0 B / 0 B</strong></div>
                    <div class="text-center p-3 bg-surface-container-low rounded-xl"><span class="block text-xs text-on-surface-variant">Packets</span><strong class="text-sm text-on-surface" id="trafficPacketsDisplay">0 / 0</strong></div>
                    <div class="text-center p-3 bg-surface-container-low rounded-xl"><span class="block text-xs text-on-surface-variant">Peak</span><strong class="text-sm text-on-surface" id="trafficPeakDisplay">0.00 Mb/s</strong></div>
                </div>
                <div class="bg-surface-container-low rounded-xl p-4">
                    <canvas id="trafficRateChart" height="60" style="height: 60px; width: 100%;"></canvas>
                </div>
                <div class="mt-3 flex items-center justify-between text-xs text-on-surface-variant">
                    <span id="trafficStatus">Menunggu data...</span>
                    <span id="trafficInterfaceMsg"></span>
                </div>
            </div>
        </div>

        <!-- Topologi Jaringan -->
        <div id="topologi" class="hidden">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-black text-on-surface flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                    Topologi Jaringan
                </h3>
                <div class="flex items-center gap-2">
                    <button onclick="window.locateDevice()" class="flex items-center gap-1 px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-bold rounded-xl hover:bg-amber-100 transition-all">
                        <span class="material-symbols-outlined text-[16px]">my_location</span>
                        Lokasi Saya
                    </button>
                    <button onclick="window.toggleMap()" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-red-50 hover:text-red-600 transition-all text-on-surface-variant">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div id="map" class="w-full h-[400px] md:h-[500px]"></div>
                <div class="px-5 py-3 border-t border-gray-200 flex items-center justify-between text-xs text-on-surface-variant flex-wrap gap-2">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#111827"></span> Server</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#f59e0b"></span> ODC</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#8b5cf6"></span> ODP</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#10b981"></span> Online</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#ef4444"></span> Offline</span>
                    </div>
                    <a href="infra" class="text-primary font-semibold hover:underline">Lihat detail &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div>
            <h3 class="text-base font-black text-on-surface mb-4 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-primary inline-block"></span>
                Akses Cepat
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="users" class="group flex items-center gap-3 p-4 bg-gradient-to-br from-blue-100 to-blue-50 border-l-4 border-primary rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="w-11 h-11 rounded-xl bg-primary/15 flex items-center justify-center group-hover:bg-primary transition-all">
                        <span class="material-symbols-outlined text-primary text-[24px] group-hover:text-white transition-all">group</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Pelanggan</p>
                        <p class="text-xs text-on-surface-variant">Manajemen user</p>
                    </div>
                </a>
                <a href="payments" class="group flex items-center gap-3 p-4 bg-gradient-to-br from-emerald-100 to-emerald-50 border-l-4 border-emerald-500 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="w-11 h-11 rounded-xl bg-emerald-200/50 flex items-center justify-center group-hover:bg-emerald-500 transition-all">
                        <span class="material-symbols-outlined text-emerald-700 text-[24px] group-hover:text-white transition-all">payments</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Pembayaran</p>
                        <p class="text-xs text-on-surface-variant">Tagihan & billing</p>
                    </div>
                </a>
                <a href="pppoe" class="group flex items-center gap-3 p-4 bg-gradient-to-br from-violet-100 to-violet-50 border-l-4 border-violet-500 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="w-11 h-11 rounded-xl bg-violet-200/50 flex items-center justify-center group-hover:bg-violet-500 transition-all">
                        <span class="material-symbols-outlined text-violet-700 text-[24px] group-hover:text-white transition-all">settings_ethernet</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">PPPoE</p>
                        <p class="text-xs text-on-surface-variant">MikroTik client</p>
                    </div>
                </a>
                <button onclick="window.toggleMap()" class="group flex items-center gap-3 p-4 bg-gradient-to-br from-amber-100 to-amber-50 border-l-4 border-amber-500 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all text-left cursor-pointer w-full">
                    <div class="w-11 h-11 rounded-xl bg-amber-200/50 flex items-center justify-center group-hover:bg-amber-500 transition-all">
                        <span class="material-symbols-outlined text-amber-700 text-[24px] group-hover:text-white transition-all">map</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Topologi</p>
                        <p class="text-xs text-on-surface-variant">Peta jaringan</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- Feature Shortcuts -->
        <div>
            <h3 class="text-base font-black text-on-surface mb-4 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-violet-500 inline-block"></span>
                Fitur Lainnya
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <button onclick="window.printReceipts()" class="flex items-center gap-3 p-3 bg-gradient-to-br from-cyan-100 to-cyan-50 border-l-4 border-cyan-500 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all text-left cursor-pointer">
                    <div class="w-9 h-9 rounded-lg bg-cyan-200/50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-cyan-700 text-[20px]">receipt_long</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Cetak Tagihan</p>
                        <p class="text-xs text-on-surface-variant">Print billing</p>
                    </div>
                </button>
                <button onclick="window.printNames()" class="flex items-center gap-3 p-3 bg-gradient-to-br from-sky-100 to-sky-50 border-l-4 border-sky-500 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all text-left cursor-pointer">
                    <div class="w-9 h-9 rounded-lg bg-sky-200/50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-sky-700 text-[20px]">badge</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Cetak Nama</p>
                        <p class="text-xs text-on-surface-variant">Print nama pelanggan</p>
                    </div>
                </button>
                <button onclick="window.locateDevice()" class="flex items-center gap-3 p-3 bg-gradient-to-br from-amber-100 to-amber-50 border-l-4 border-amber-500 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all text-left cursor-pointer">
                    <div class="w-9 h-9 rounded-lg bg-amber-200/50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-amber-700 text-[20px]">my_location</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Lokasi Perangkat</p>
                        <p class="text-xs text-on-surface-variant">Deteksi GPS</p>
                    </div>
                </button>
                <a href="infra" class="flex items-center gap-3 p-3 bg-gradient-to-br from-orange-100 to-orange-50 border-l-4 border-orange-500 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all text-left">
                    <div class="w-9 h-9 rounded-lg bg-orange-200/50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-orange-700 text-[20px]">edit</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Edit Peta</p>
                        <p class="text-xs text-on-surface-variant">Tambah/ubah node</p>
                    </div>
                </a>
                <a href="pppoe" class="flex items-center gap-3 p-3 bg-gradient-to-br from-violet-100 to-violet-50 border-l-4 border-violet-500 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all text-left">
                    <div class="w-9 h-9 rounded-lg bg-violet-200/50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-violet-700 text-[20px]">inventory_2</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Inventaris MikroTik</p>
                        <p class="text-xs text-on-surface-variant">PPP secret & aktif</p>
                    </div>
                </a>
                <a href="users" class="flex items-center gap-3 p-3 bg-gradient-to-br from-blue-100 to-blue-50 border-l-4 border-blue-500 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all text-left">
                    <div class="w-9 h-9 rounded-lg bg-blue-200/50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-blue-700 text-[20px]">manage_accounts</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Status Pelanggan</p>
                        <p class="text-xs text-on-surface-variant">Online/offline/disabled</p>
                    </div>
                </a>
                <a href="settings" class="flex items-center gap-3 p-3 bg-gradient-to-br from-gray-200 to-gray-100 border-l-4 border-gray-400 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all text-left">
                    <div class="w-9 h-9 rounded-lg bg-gray-200/50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-gray-700 text-[20px]">settings</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Pengaturan</p>
                        <p class="text-xs text-on-surface-variant">Konfigurasi sistem</p>
                    </div>
                </a>
                <a href="hotspot" class="flex items-center gap-3 p-3 bg-gradient-to-br from-pink-100 to-pink-50 border-l-4 border-pink-500 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all text-left">
                    <div class="w-9 h-9 rounded-lg bg-pink-200/50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-pink-700 text-[20px]">wifi</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Hotspot</p>
                        <p class="text-xs text-on-surface-variant">Manajemen hotspot</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</main>

<script src="public/js/receipt-print.js?v=<?= urlencode((string) filemtime(__DIR__ . '/../public/js/receipt-print.js')) ?>"></script>
<script>
window.GOOGLE_MAPS_CONFIG = <?= json_encode([
    'apiKey' => (string) env_value('GOOGLE_MAPS_API_KEY', ''),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
function toggleSidebar() { document.getElementById('appSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }
function closeSidebar() { document.getElementById('appSidebar').classList.add('-translate-x-full'); document.getElementById('sidebarOverlay').classList.add('hidden'); }
async function logout() { await fetch('api/auth.php?action=logout'); location.href = '<?= htmlspecialchars(app_url('login'), ENT_QUOTES, 'UTF-8') ?>'; }
let refreshInterval = null;
function toggleRefresh() { if (refreshInterval) { clearInterval(refreshInterval); refreshInterval = null; } else location.reload(); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
document.querySelectorAll('#appSidebar a').forEach(l => l.addEventListener('click', () => { if (window.innerWidth < 768) closeSidebar(); }));

// Stats
async function loadStats() {
    try {
        const [dashRes, billRes, infraRes] = await Promise.all([
            fetch('api/dashboard.php'),
            fetch('api/billing.php'),
            fetch('api/infra.php')
        ]);
        const dash = await dashRes.json();
        const bill = await billRes.json();
        const infra = await infraRes.json();
        if (dash.statistik) {
            const s = dash.statistik;
            document.getElementById('totalUsersStat').textContent = (s.online||0)+(s.offline||0)+(s.disabled||0)||0;
            document.getElementById('onlineUsersStat').textContent = (s.online||0)+' online';
        }
        if (dash.pppoe_active !== undefined) document.getElementById('activePppoeStat').textContent = dash.pppoe_active||0;
        if (bill.data) {
            document.getElementById('billingTotalStat').textContent = bill.data.length||0;
            document.getElementById('billingPaidStat').textContent = bill.data.filter(i=>i.payment_status==='sudah_bayar').length+' lunas';
        }
        document.getElementById('infraTotalStat').textContent = infra.data?.length||0;
    } catch(e) {}
}

// Traffic
const trafficState = { rateCanvas: null, rateHistory: [], lastTx: 0, lastRx: 0 };
function toMbps(bits) { return Number(bits||0)/1000000; }
function fmtRate(bits) { const m=toMbps(bits); return `${m<0.01?m.toFixed(5):m<0.1?m.toFixed(4):m.toFixed(2)} Mb/s`; }

function buildRenderedHistory(history, slots) {
    if (!history.length||slots<=0) return [];
    if (history.length>=slots) return history.slice(-slots);
    if (history.length===1) return Array.from({length:slots}, ()=>history[0]);
    return Array.from({length:slots}, (_,i)=>{
        return history[Math.round((i/Math.max(slots-1,1))*(history.length-1))];
    });
}
function resizeCanvasForDisplay(canvas) {
    if (!canvas) return null;
    const ratio = window.devicePixelRatio||1;
    const r = canvas.getBoundingClientRect();
    const w = Math.max(1,Math.round(r.width*ratio)), h = Math.max(1,Math.round(r.height*ratio));
    if (canvas.width!==w||canvas.height!==h) { canvas.width=w; canvas.height=h; }
    const ctx = canvas.getContext('2d');
    ctx.setTransform(1,0,0,1,0,0); ctx.scale(ratio,ratio);
    return {ctx, width:r.width, height:r.height};
}
function drawTraffic() {
    const c = trafficState.rateCanvas;
    const resized = resizeCanvasForDisplay(c);
    if (!resized) return;
    const {ctx, width, height} = resized;
    const padding = {top:4, right:4, bottom:4, left:4};
    const plotPadding = {left:1, right:1};
    const chartWidth = Math.max(1, width - plotPadding.left - plotPadding.right);
    const chartHeight = Math.max(1, height - padding.top - padding.bottom);
    const maxValue = Math.max(1, ...trafficState.rateHistory.map(i=>Math.max(i.rx||0,i.tx||0)));
    ctx.clearRect(0,0,width,height);
    ctx.fillStyle='#ffffff'; ctx.fillRect(0,0,width,height);
    ctx.strokeStyle='#928878'; ctx.lineWidth=1; ctx.strokeRect(0.5,0.5,width-1,height-1);
    ctx.strokeStyle='#d7d1c8'; ctx.lineWidth=1;
    for (let i=1;i<4;i++) { const y=padding.top+((chartHeight/4)*i); ctx.beginPath(); ctx.moveTo(plotPadding.left,y); ctx.lineTo(width-plotPadding.right,y); ctx.stroke(); }
    if (trafficState.rateHistory.length) {
        const barWidth = 1.5;
        const pairWidth = barWidth * 2;
        const renderSlots = Math.max(1, Math.ceil(chartWidth / pairWidth));
        const renderedHistory = buildRenderedHistory(trafficState.rateHistory, renderSlots);
        renderedHistory.forEach((item, index) => {
            const xBase = plotPadding.left + (index * pairWidth);
            const rxHeight = Math.max(1, Math.round(((item.rx||0) / maxValue) * (chartHeight - 2)));
            const txHeight = Math.max(1, Math.round(((item.tx||0) / maxValue) * (chartHeight - 2)));
            ctx.fillStyle='#dc2626'; ctx.fillRect(xBase, height - padding.bottom - rxHeight, barWidth, rxHeight);
            ctx.fillStyle='#7c3aed'; ctx.fillRect(xBase + barWidth, height - padding.bottom - txHeight, barWidth, txHeight);
        });
    }
    // legend
    const last = trafficState.rateHistory.length ? trafficState.rateHistory[trafficState.rateHistory.length-1] : {tx:0,rx:0};
    ctx.font='11px Inter,sans-serif'; ctx.textBaseline='top';
    const legItems = [
        {color:'#7c3aed', label:`Tx: ${(Number(last.tx||0)/1000000).toFixed(2)} Mb/s`},
        {color:'#dc2626', label:`Rx: ${(Number(last.rx||0)/1000000).toFixed(2)} Mb/s`}
    ];
    const box=12, pad=6, lh=22;
    const lw=box+8+Math.max(...legItems.map(i=>ctx.measureText(i.label).width))+pad*2;
    const lh2=lh*legItems.length+pad;
    ctx.fillStyle='rgba(245,245,245,0.92)'; ctx.strokeStyle='#666'; ctx.lineWidth=1;
    ctx.fillRect(4,4,lw,lh2); ctx.strokeRect(4,4,lw,lh2);
    legItems.forEach((item,i)=>{
        const y=4+pad+i*lh;
        ctx.fillStyle=item.color; ctx.fillRect(4+pad,y,box,box);
        ctx.strokeStyle='#222'; ctx.strokeRect(4+pad,y,box,box);
        ctx.fillStyle='#2d251d'; ctx.fillText(item.label,4+pad+box+8,y);
    });
}

async function loadTraffic() {
    try {
        const r = await fetch(`api/mikrotik.php?live=1&_=${Date.now()}`);
        const d = await r.json();
        if (!d.success) return;
        document.getElementById('activePppoeStat').textContent = d.pppoe_active||0;
        const rx = d.traffic?.rx||0, tx = d.traffic?.tx||0;
        trafficState.rateHistory.push({rx,tx});
        if (trafficState.rateHistory.length>32) trafficState.rateHistory.shift();
        document.getElementById('trafficRateDisplay').textContent = `${fmtRate(rx)} / ${fmtRate(tx)}`;
        document.getElementById('trafficBytesDisplay').textContent = `${(Number(d.interface_stats?.rx_byte||0)/1073741824).toFixed(2)} GB / ${(Number(d.interface_stats?.tx_byte||0)/1073741824).toFixed(2)} GB`;
        document.getElementById('trafficPacketsDisplay').textContent = `${d.interface_stats?.rx_packet||0} / ${d.interface_stats?.tx_packet||0}`;
        const peak = Math.max(...trafficState.rateHistory.map(i=>Math.max(i.rx,i.tx)));
        document.getElementById('trafficPeakDisplay').textContent = fmtRate(peak);
        document.getElementById('trafficTitleLive').textContent = `Rx ${fmtRate(rx)} | Tx ${fmtRate(tx)}`;
        document.getElementById('trafficStatus').textContent = d.interface_stats?.running ? 'Link OK' : 'Interface idle';
        if (d.meta?.traffic_interface) document.getElementById('trafficInterfaceMsg').textContent = `Interface: ${d.meta.traffic_interface}`;
        drawTraffic();
    } catch(e) { document.getElementById('trafficStatus').textContent='Tidak dapat mengakses MikroTik'; }
}

document.getElementById('trafficInterfaceForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this); fd.append('traffic_interface', document.getElementById('trafficInterfaceInput').value.trim());
    await fetch('api/settings.php', {method:'POST',body:fd});
    trafficState.rateHistory=[]; loadTraffic();
});



// Print shortcut functions
async function fetchJson(url) {
    const r = await fetch(url, {cache:'no-store'});
    const d = await r.json();
    if (r.status===401) { location.href='login'; throw new Error('Unauthorized'); }
    return d;
}
function sortUsers(users=[]) {
    return (users||[]).slice().sort((a,b)=>(a.username||a.nama||'').localeCompare(b.username||b.nama||'', 'id', {sensitivity:'base'}));
}
window.printReceipts = async function() {
    try {
        const data = await fetchJson(`api/billing.php?_=${Date.now()}`);
        const users = sortUsers(data.users||[]);
        if (!users.length) { alert('Belum ada pelanggan terdaftar.'); return; }
        window.receiptPrinter?.printReceipts(users, data.data||[], {variant:'payments'});
    } catch(e) { alert('Gagal menyiapkan template print.'); }
};
window.printNames = async function() {
    try {
        const data = await fetchJson(`api/billing.php?_=${Date.now()}`);
        const users = sortUsers(data.users||[]);
        if (!users.length) { alert('Belum ada pelanggan terdaftar.'); return; }
        window.receiptPrinter?.printNameTemplate(users, {rowsPerPage:10});
    } catch(e) { alert('Gagal menyiapkan template print nama.'); }
};

// Map
let mapInstance = null, mapMarkers = [], mapInfoWindow = null, deviceMarker = null;
let lastMapSignature = '', hasAppliedMapBounds = false, gmapsPromise = null;
const mapColors = { server:'#111827', odc:'#f59e0b', odp:'#8b5cf6', user_online:'#10b981', user_offline:'#ef4444', user_disabled:'#64748b' };

function loadGMaps() {
    if (window.google?.maps) return Promise.resolve(window.google.maps);
    if (gmapsPromise) return gmapsPromise;
    const key = String(window.GOOGLE_MAPS_CONFIG?.apiKey||'').trim();
    if (!key) return Promise.reject(new Error('API key kosong'));
    gmapsPromise = new Promise((resolve,reject)=>{
        const cb='__gmCb'; window[cb]=()=>{delete window[cb];resolve(window.google.maps);};
        const s=document.createElement('script');
        s.src=`https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly&callback=${cb}`;
        s.async=true; s.onerror=()=>{delete window[cb];reject(new Error('Gagal load Maps'));};
        document.head.appendChild(s);
    });
    return gmapsPromise;
}

function esc(v) { return String(v??'').replace(/[&<>"]/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[m]); }

function nodeIcon(node) {
    const c = node.type==='user'?(mapColors[`user_${node.status||'offline'}`]||mapColors.user_offline):(mapColors[node.type]||mapColors.server);
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><circle cx="10" cy="10" r="7" fill="${c}" stroke="rgba(255,255,255,0.98)" stroke-width="1.6"/></svg>`;
    return { url:`data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`, scaledSize:new google.maps.Size(20,20), anchor:new google.maps.Point(10,10) };
}

function drawMap(data) {
    if (!mapInstance) return;
    const sig = JSON.stringify({nodes:data.nodes, edges:data.edges});
    if (sig===lastMapSignature) return;
    lastMapSignature = sig; hasAppliedMapBounds = false;
    mapMarkers.forEach(m=>m.setMap(null)); mapMarkers = [];
    if (!data.nodes?.length) return;
    const coords = {}, bounds = new google.maps.LatLngBounds();
    data.nodes.forEach(n => { const p={lat:Number(n.lat),lng:Number(n.lng)}; coords[n.id]=p; bounds.extend(p); });
    (data.edges||[]).forEach(e => {
        const s=coords[e.source], t=coords[e.target];
        if (!s||!t) return;
        mapMarkers.push(new google.maps.Polyline({path:[s,t], strokeColor:'#22c55e', strokeOpacity:0.9, strokeWeight:3, map:mapInstance}));
    });
    data.nodes.forEach(n => {
        const p = coords[n.id];
        const marker = new google.maps.Marker({position:p, map:mapInstance, title:String(n.name||''), icon:nodeIcon(n)});
        marker.addListener('click', ()=>{
            if (!mapInfoWindow) return;
            const parts = [`<b>${esc(n.name)}</b>`,`Type: ${esc(String(n.type||'-').toUpperCase())}`,`Status: ${esc(n.status||'-')}`];
            if (n.username) parts.push(`Username: ${esc(n.username)}`);
            if (n.remote_ip) parts.push(`Remote IP: ${esc(n.remote_ip)}`);
            mapInfoWindow.setContent(parts.join('<br>')); mapInfoWindow.open({map:mapInstance, anchor:marker});
        });
        mapMarkers.push(marker);
    });
    setTimeout(()=>{
        if (!mapInstance) return;
        if (data.nodes.length===1) { mapInstance.setCenter(coords[data.nodes[0].id]); mapInstance.setZoom(19); }
        else if (data.nodes.length>1&&!hasAppliedMapBounds) { mapInstance.fitBounds(bounds); hasAppliedMapBounds = true; }
    },100);
}

async function loadMapData() {
    if (document.hidden) return;
    try { const d=await fetchJson('api/map.php'); if (d.success) drawMap(d); } catch(e) {}
}

async function initMap() {
    try {
        const maps = await loadGMaps();
        const el = document.getElementById('map'); if (!el) return;
        mapInstance = new maps.Map(el, {
            center:{lat:-6.2,lng:106.816666}, zoom:window.matchMedia('(max-width:768px)').matches?17:15,
            mapTypeId:maps.MapTypeId.SATELLITE, streetViewControl:false, fullscreenControl:true,
            zoomControl:true, gestureHandling:'greedy', clickableIcons:false
        });
        mapInfoWindow = new maps.InfoWindow();
        await loadMapData(); setInterval(loadMapData, 90000);
    } catch(e) { console.error('Gagal memuat Google Maps',e); }
}

let mapInitialized = false;
window.toggleMap = function() {
    const el = document.getElementById('topologi');
    if (!el) return;
    const isHidden = el.classList.contains('hidden');
    el.classList.toggle('hidden');
    if (isHidden && !mapInitialized) { mapInitialized = true; initMap(); }
    if (isHidden && mapInstance) { google.maps.event?.trigger(mapInstance, 'resize'); }
};

window.locateDevice = function() {
    if (!navigator.geolocation) { alert('Geolocation tidak didukung'); return; }
    navigator.geolocation.getCurrentPosition(
        p => {
            if (!mapInstance) { alert(`${p.coords.latitude}, ${p.coords.longitude}`); return; }
            const ll = {lat:p.coords.latitude, lng:p.coords.longitude};
            if (deviceMarker) deviceMarker.setMap(null);
            deviceMarker = new google.maps.Marker({position:ll, map:mapInstance, title:'Lokasi Saya'});
            if (mapInfoWindow) { mapInfoWindow.setContent('<b>Lokasi Perangkat Anda</b>'); mapInfoWindow.open({map:mapInstance, anchor:deviceMarker}); }
            mapInstance.setCenter(ll); mapInstance.setZoom(19);
        },
        e => alert(e.code===1?'Izin ditolak':e.code===2?'Tidak tersedia':'Timeout'),
        {enableHighAccuracy:true, timeout:15000}
    );
};

window.onload = function() {
    trafficState.rateCanvas = document.getElementById('trafficRateChart');
    drawTraffic();
    loadStats();
    setTimeout(loadTraffic, 100);
    setInterval(loadTraffic, 5000);
    window.addEventListener('resize', () => { drawTraffic(); if (mapInstance) google.maps.event?.trigger(mapInstance, 'resize'); });
};
</script>
<?= app_render_tenant_expiry_script('login.php') ?>
</body>
</html>

