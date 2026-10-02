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
<title>Hotspot Voucher - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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
</style>
</head>
<body class="bg-surface text-on-surface">

<?php $activePage = 'hotspot'; require_once __DIR__ . '/partials/dashboard_sidebar.php'; ?>

<main class="lg:ml-[270px] min-h-screen transition-all">
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-outline-variant h-16 flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center hover:bg-surface-container transition-all" onclick="toggleSidebar()"><span class="material-symbols-outlined text-on-surface-variant">menu</span></button>
            <h2 class="text-lg font-black text-on-surface">Hotspot Voucher</h2>
        </div>
    </header>
    <div class="p-6 max-w-[1600px] mx-auto">
        <?= app_render_tenant_expiry_banner() ?>

        <div class="bg-gradient-to-br from-pink-100 to-pink-50 rounded-xl shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Ringkasan Voucher Hotspot</h3></div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="hotspotSummaryCards">
                    <button type="button" class="p-4 border border-outline-variant rounded-xl hover:shadow-md transition-shadow text-left hotspot-summary-card" id="hotspotSummaryInactive" data-filter="inactive">
                        <p class="text-xs text-on-surface-variant font-medium uppercase tracking-wide">Total Voucher Belum Active</p>
                        <p class="text-2xl font-bold text-on-surface mt-1" id="hotspotSummaryInactiveCount">0</p>
                        <p class="text-xs text-on-surface-variant mt-1">Klik untuk menampilkan voucher yang belum pernah dipakai.</p>
                    </button>
                    <button type="button" class="p-4 border border-outline-variant rounded-xl hover:shadow-md transition-shadow text-left hotspot-summary-card" id="hotspotSummaryActive" data-filter="active">
                        <p class="text-xs text-on-surface-variant font-medium uppercase tracking-wide">Total Voucher Active</p>
                        <p class="text-2xl font-bold text-on-surface mt-1" id="hotspotSummaryActiveCount">0</p>
                        <p class="text-xs text-on-surface-variant mt-1">Klik untuk menampilkan sesi hotspot yang sedang aktif.</p>
                    </button>
                    <button type="button" class="p-4 border border-outline-variant rounded-xl hover:shadow-md transition-shadow text-left hotspot-summary-card" id="hotspotSummaryExpired" data-filter="expired">
                        <p class="text-xs text-on-surface-variant font-medium uppercase tracking-wide">Total Voucher Expired</p>
                        <p class="text-2xl font-bold text-on-surface mt-1" id="hotspotSummaryExpiredCount">0</p>
                        <p class="text-xs text-on-surface-variant mt-1">Klik untuk menampilkan voucher yang limit waktu atau kuotanya sudah habis.</p>
                    </button>
                    <button type="button" class="p-4 border border-outline-variant rounded-xl hover:shadow-md transition-shadow text-left hotspot-summary-card" id="hotspotSummaryUsed" data-filter="used">
                        <p class="text-xs text-on-surface-variant font-medium uppercase tracking-wide">Total Voucher Sudah Dipakai</p>
                        <p class="text-2xl font-bold text-on-surface mt-1" id="hotspotSummaryUsedCount">0</p>
                        <p class="text-xs text-on-surface-variant mt-1">Klik untuk menampilkan voucher yang pernah dipakai tetapi belum expired.</p>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-gradient-to-br from-pink-100 to-pink-50 rounded-xl shadow-sm" id="hotspotVoucherSection">
                <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Generate Voucher Hotspot</h3></div>
                <div class="p-6">
                    <form id="hotspotVoucherForm">
                        <input type="hidden" name="action" value="create_vouchers">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Mode Username</label><select name="user_mode" id="hotspotUserMode" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="random">Auto (Random)</option><option value="prefix">Prefix + Random</option><option value="manual">Input Manual (Tanpa Random)</option></select></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Input (Opsional)</label><input type="text" name="input" id="hotspotPrefixInput" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Prefix atau Nama Manual" disabled></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Karakter Voucher</label><select name="char_mode" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="numbers">Angka Saja</option><option value="capital_numbers">Huruf Besar + Angka</option><option value="capital">Huruf Besar Saja</option><option value="small">Huruf Kecil Saja</option><option value="mixed_letters">Huruf Besar + Kecil</option><option value="small_numbers">Huruf Kecil + Angka</option><option value="all">Campur (Besar, Kecil, Angka)</option></select></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Panjang Kode</label><input type="number" name="char_length" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" min="4" max="20" value="6"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Jumlah Voucher</label><input type="number" name="count" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" min="1" max="200" value="10" required></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Profile</label><select name="profile" id="hotspotVoucherProfileSelect" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="default">default</option></select></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Server Hotspot</label><select name="server" id="hotspotServerSelect" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="">Semua server</option></select></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Password Login</label><input type="hidden" name="password_mode" value="username"><input type="text" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm" value="Sama dengan kode voucher" readonly></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Mode Limit Waktu</label><select name="limit_time_mode" id="hotspotLimitTimeMode" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="none">Tanpa Limit</option><option value="custom">Custom Jam / Menit</option></select></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Limit Waktu (Jam : Menit)</label><div class="flex gap-2"><input type="number" name="limit_hours" id="hotspotLimitHours" class="flex-1 w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" min="0" value="0" placeholder="Jam" disabled><input type="number" name="limit_minutes" id="hotspotLimitMinutes" class="flex-1 w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" min="0" max="59" value="0" placeholder="Menit" disabled></div></div>
                            <div></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Limit Kuota MB</label><input type="number" name="limit_mb" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" min="0" step="1" placeholder="Opsional"></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Keterangan</label><input type="text" name="comment" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Opsional"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Quota Display (Opsional)</label><input type="text" name="quota_display" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 2 GB"></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Harga (Opsional)</label><input type="text" name="price" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: Rp 5.000"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">ID Toko (Opsional)</label><input type="text" name="shop_id" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: T001"></div>
                            <div></div>
                        </div>
                        <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Generate Voucher</button>
                        <div id="hotspotVoucherMsg" class="mt-3 text-sm font-medium"></div>
                    </form>
                </div>
            </div>

            <div class="bg-gradient-to-br from-pink-100 to-pink-50 rounded-xl shadow-sm" id="hotspotProfileSection">
                <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Profile Hotspot</h3></div>
                <div class="p-6">
                    <form id="hotspotProfileForm">
                        <input type="hidden" name="action" value="create_profile">
                        <div class="mb-4"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama Profile</label><input type="text" name="name" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: VOUCHER-1H" required></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Rate Limit</label><input type="text" name="rate_limit" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 5M/5M"></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Shared Users</label><input type="number" name="shared_users" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" min="1" max="999" value="1"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Keepalive Timeout</label><input type="text" name="keepalive_timeout" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="00:02:00" value="00:02:00"><p class="text-xs text-on-surface-variant mt-1">Toleransi saat sinyal putus (HH:MM:SS)</p></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Idle Timeout</label><input type="text" name="idle_timeout" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="none" value="none"><p class="text-xs text-on-surface-variant mt-1">Logout otomatis jika tidak ada trafik</p></div>
                        </div>
                        <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Tambah Profile Hotspot</button>
                        <div id="hotspotProfileMsg" class="mt-3 text-sm font-medium"></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-pink-100 to-pink-50 rounded-xl shadow-sm mb-6 hidden" id="hotspotPrintSection">
            <div class="px-6 py-4 border-b border-outline-variant">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h3 class="text-base font-bold text-on-surface">Voucher Belum Keprint / Reprint</h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="px-4 py-2 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="selectAllVouchersBtn">Pilih Semua</button>
                        <button type="button" class="px-4 py-2 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="printChecklistVouchersBtn">Print List (Checklist)</button>
                        <button type="button" class="px-4 py-2 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="printSelectedVoucherBtn">Print Struk</button>
                        <button type="button" class="px-4 py-2 bg-error text-on-error text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="markVouchersAsPrintedBtn">Sudah di Print</button>
                    </div>
                </div>
            </div>
            <div class="p-6 overflow-x-auto" id="hotspotCreatedList"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-gradient-to-br from-pink-100 to-pink-50 rounded-xl shadow-sm" id="hotspotUserSection">
                <div class="px-6 py-4 border-b border-outline-variant">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-base font-bold text-on-surface" id="hotspotUserSectionTitle">Daftar Voucher Hotspot</h3>
                            <div class="min-w-[200px]">
                                <select id="hotspotVoucherServerFilter" class="w-full px-3 py-2 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                    <option value="">Semua Server Voucher</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 transition-all hidden" id="deleteSelectedBtn">Hapus Terpilih</button>
                            <button type="button" class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 transition-all hidden" id="reprintUnusedSelectedBtn">Reprint Voucher Dipilih</button>
                            <button type="button" class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 transition-all hidden" id="deleteExpiredBtn">Hapus Semua Expired</button>
                            <button type="button" class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all hidden" id="hotspotResetFilterBtn">Tampilkan Semua</button>
                        </div>
                    </div>
                </div>
                <div class="p-6 overflow-x-auto" id="hotspotUserList">Memuat voucher...</div>
                <div id="hotspotUserMsg" class="px-6 pb-4 text-sm font-medium"></div>
            </div>
            <div class="bg-gradient-to-br from-pink-100 to-pink-50 rounded-xl shadow-sm" id="hotspotActiveSection">
                <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface" id="hotspotActiveSectionTitle">Active Hotspot</h3></div>
                <div class="p-6 overflow-x-auto" id="hotspotActiveList">Memuat active hotspot...</div>
                <div id="hotspotActiveMsg" class="px-6 pb-4 text-sm font-medium"></div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-pink-100 to-pink-50 rounded-xl shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Daftar Profile Hotspot</h3></div>
            <div class="p-6 overflow-x-auto" id="hotspotProfileList">Memuat profile...</div>
        </div>

        <div class="bg-gradient-to-br from-pink-100 to-pink-50 rounded-xl shadow-sm mt-6" id="hotspotDistributionSection">
            <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Sebaran Voucher per Toko</h3></div>
            <div class="p-6" id="hotspotDistributionList"></div>
        </div>
    </div>
</main>

<script src="public/js/receipt-print.js?v=<?= urlencode((string) filemtime(__DIR__ . '/../public/js/receipt-print.js')) ?>"></script>
<script>
const hotspotVoucherForm = document.getElementById('hotspotVoucherForm');
const hotspotProfileForm = document.getElementById('hotspotProfileForm');
const hotspotUserMode = document.getElementById('hotspotUserMode');
const hotspotPrefixInput = document.getElementById('hotspotPrefixInput');
const hotspotLimitTimeMode = document.getElementById('hotspotLimitTimeMode');
const hotspotLimitHours = document.getElementById('hotspotLimitHours');
const hotspotLimitMinutes = document.getElementById('hotspotLimitMinutes');
const hotspotResetFilterBtn = document.getElementById('hotspotResetFilterBtn');
const reprintUnusedSelectedBtn = document.getElementById('reprintUnusedSelectedBtn');
const deleteExpiredBtn = document.getElementById('deleteExpiredBtn');
const hotspotVoucherServerFilter = document.getElementById('hotspotVoucherServerFilter');
const hotspotCreatedList = document.getElementById('hotspotCreatedList');
let lastCreatedVouchers = [];
let currentHotspotFilter = 'all';
let currentVoucherServerFilter = '';
const hotspotPrintStorageKey = 'hotspot_unprinted_vouchers_tenant_<?= (int) ($account["tenant_id"] ?? 0) ?>';

function escapeHtml(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
function formatBytes(v) { const b = Number(v || 0); if (!Number.isFinite(b) || b <= 0) return '-'; const u = ['B', 'KB', 'MB', 'GB', 'TB']; let s = b, i = 0; while (s >= 1024 && i < u.length - 1) { s /= 1024; i++; } return `${s.toFixed(s >= 10 || i === 0 ? 0 : 1)} ${u[i]}`; }
function formatGb(v) { const b = Number(v || 0); if (!Number.isFinite(b) || b <= 0) return '0.00 GB'; return `${(b / (1024 ** 3)).toFixed(2)} GB`; }
function parseUptimeSeconds(value) {
    const text = String(value || '').trim().toLowerCase();
    if (!text || text === '-' || text === '0' || text === '0s' || text === '00:00:00') return 0;
    const unitMap = { w: 604800, d: 86400, h: 3600, m: 60, s: 1 };
    let total = 0;
    const matches = [...text.matchAll(/(\d+)([wdhms])/g)];
    if (matches.length) {
        matches.forEach(([, amount, unit]) => {
            total += Number(amount || 0) * (unitMap[unit] || 0);
        });
        return total;
    }
    if (/^\d+:\d+:\d+$/.test(text)) {
        const parts = text.split(':').map(Number);
        return (parts[0] || 0) * 3600 + (parts[1] || 0) * 60 + (parts[2] || 0);
    }
    return 0;
}
function isVoucherUsed(item) {
    const comment = String(item.comment || '').trim();
    if (comment.includes('[U:1]')) return true;
    const totalUsage = Number(item.bytes_in || 0) + Number(item.bytes_out || 0);
    return totalUsage > 0 || parseUptimeSeconds(item.uptime) > 0;
}
function toNumericBytes(value) {
    const bytes = Number(value || 0);
    return Number.isFinite(bytes) && bytes > 0 ? bytes : 0;
}
function isVoucherExpired(item) {
    const totalUsage = Number(item.bytes_in || 0) + Number(item.bytes_out || 0);
    const uptimeSeconds = parseUptimeSeconds(item.uptime);
    const limitBytes = toNumericBytes(item.limit_bytes_total);
    const limitUptimeSeconds = parseUptimeSeconds(item.limit_uptime);
    const quotaExpired = limitBytes > 0 && totalUsage >= limitBytes;
    const timeExpired = limitUptimeSeconds > 0 && uptimeSeconds >= limitUptimeSeconds;
    return quotaExpired || timeExpired;
}
function normalizeVoucherPrintItem(item = {}) {
    const username = String(item.username ?? item.name ?? '').trim();
    const comment = String(item.comment || '').trim();
    let quota_display = String(item.quota_display || '').trim();
    let price = String(item.price || '').trim();
    let id_voucher = String(item.id_voucher || '').trim();
    let shop_id = String(item.shop_id || '').trim();

    if (!quota_display) {
        const mq = comment.match(/\[Q:([^\]]+)\]/);
        if (mq) quota_display = mq[1];
    }
    if (!price) {
        const mp = comment.match(/\[P:([^\]]+)\]/);
        if (mp) price = mp[1];
    }
    if (!id_voucher) {
        const mi = comment.match(/\[I:([^\]]+)\]/);
        if (mi) id_voucher = mi[1];
    }
    if (!shop_id) {
        const ms = comment.match(/\[S:([^\]]+)\]/);
        if (ms) shop_id = ms[1];
    }

    return {
        username,
        password: String(item.password ?? item.username ?? item.name ?? '').trim() || username,
        profile: String(item.profile || '').trim(),
        server: String(item.server || '').trim(),
        uptime: String(item.uptime || '').trim(),
        bytes_in: item.bytes_in,
        bytes_out: item.bytes_out,
        limit_uptime: String(item.limit_uptime || item['limit-uptime'] || '').trim(),
        limit_mb: String(item.limit_mb || '').trim(),
        limit_bytes_total: String(item.limit_bytes_total || item['limit-bytes-total'] || '').trim(),
        comment,
        quota_display,
        price,
        id_voucher,
        shop_id,
        printed: Boolean(item.printed)
    };
}
function loadStoredUnprintedVouchers() {
    try {
        const raw = localStorage.getItem(hotspotPrintStorageKey);
        const parsed = JSON.parse(raw || '[]');
        return Array.isArray(parsed) ? parsed.map(item => normalizeVoucherPrintItem(item)).filter(item => item.username !== '') : [];
    } catch (e) {
        return [];
    }
}
function saveStoredUnprintedVouchers(items = []) {
    try {
        localStorage.setItem(hotspotPrintStorageKey, JSON.stringify(items));
    } catch (e) {}
}
function upsertUnprintedVouchers(items = []) {
    const existing = loadStoredUnprintedVouchers();
    const map = new Map(existing.map(item => [String(item.username || ''), item]));
    (items || []).forEach(item => {
        const normalized = normalizeVoucherPrintItem(item);
        const key = normalized.username;
        if (!key) return;
        map.set(key, { ...normalized, printed: false });
    });
    const nextItems = Array.from(map.values());
    saveStoredUnprintedVouchers(nextItems);
    return nextItems;
}
function updateStoredVoucherPrintedState(usernames = [], printed = true) {
    const lookup = new Set((usernames || []).map(item => String(item || '').trim()).filter(Boolean));
    const nextItems = loadStoredUnprintedVouchers().map(item => lookup.has(String(item.username || '').trim()) ? { ...item, printed } : item);
    saveStoredUnprintedVouchers(nextItems);
    return nextItems;
}
function removeStoredVouchers(usernames = []) {
    const lookup = new Set((usernames || []).map(item => String(item || '').trim()).filter(Boolean));
    const nextItems = loadStoredUnprintedVouchers().filter(item => !lookup.has(String(item.username || '').trim()));
    saveStoredUnprintedVouchers(nextItems);
    return nextItems;
}
function getSelectedUnprintedVouchers() {
    const selectedNames = Array.from(document.querySelectorAll('.voucher-print-checkbox:checked')).map(input => input.value);
    const lookup = new Set(selectedNames);
    return loadStoredUnprintedVouchers().filter(item => lookup.has(String(item.username || '').trim()));
}
function renderStoredVouchers(items = loadStoredUnprintedVouchers()) {
    const section = document.getElementById('hotspotPrintSection');
    lastCreatedVouchers = items || [];
    if (!lastCreatedVouchers.length) {
        section.classList.add('hidden');
        hotspotCreatedList.innerHTML = '';
        return;
    }
    section.classList.remove('hidden');
    hotspotCreatedList.innerHTML = `<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3"><input type="checkbox" id="voucherPrintMasterCheckbox"></th><th class="pb-3">ID</th><th class="pb-3">Toko</th><th class="pb-3">Kode Voucher</th><th class="pb-3">Quota</th><th class="pb-3">Harga</th></tr></thead><tbody>${lastCreatedVouchers.map(i => `<tr class="border-b border-outline-variant/50"><td class="py-3"><input type="checkbox" class="voucher-print-checkbox" value="${escapeHtml(i.username || '')}" checked></td><td class="py-3"><span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">${escapeHtml(i.id_voucher || '-')}</span></td><td class="py-3">${escapeHtml(i.shop_id || '-')}</td><td class="py-3 font-medium">${escapeHtml(i.username || '-')}</td><td class="py-3">${escapeHtml(i.quota_display || '-')}</td><td class="py-3">${escapeHtml(i.price || '-')}</td></tr>`).join('')}</tbody></table>`;
    const masterCheckbox = document.getElementById('voucherPrintMasterCheckbox');
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', () => {
            document.querySelectorAll('.voucher-print-checkbox').forEach(input => { input.checked = masterCheckbox.checked; });
        });
    }
}
function getVoucherGroups(users = [], active = []) {
    const activeUsers = new Set((active || []).map(item => String(item.user || '').trim()).filter(Boolean));
    const inactive = [];
    const activeVouchers = [];
    const expired = [];
    const used = [];
    (users || []).forEach(item => {
        const username = String(item.name || '').trim();
        if (username !== '' && activeUsers.has(username)) {
            activeVouchers.push(item);
            return;
        }
        if (isVoucherExpired(item)) {
            expired.push(item);
            return;
        }
        if (isVoucherUsed(item)) {
            used.push(item);
            return;
        }
        inactive.push(item);
    });
    return { inactive, active: activeVouchers, expired, used };
}
function setHotspotFilter(filter = 'all', shouldScroll = false) {
    currentHotspotFilter = filter;
    if (window.__hotspotData) renderAllHotspotSections(window.__hotspotData);
    if (shouldScroll) {
        const targetId = filter === 'active' ? 'hotspotActiveSection' : 'hotspotUserSection';
        document.getElementById(targetId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
function renderSummaryCards(groups) {
    document.getElementById('hotspotSummaryInactiveCount').textContent = groups.inactive.length;
    document.getElementById('hotspotSummaryActiveCount').textContent = groups.active.length;
    document.getElementById('hotspotSummaryExpiredCount').textContent = groups.expired.length;
    document.getElementById('hotspotSummaryUsedCount').textContent = groups.used.length;
    document.querySelectorAll('.hotspot-summary-card').forEach(card => {
        const isActive = card.dataset.filter === currentHotspotFilter;
        card.classList.toggle('ring-2', isActive);
        card.classList.toggle('ring-primary', isActive);
    });
}
function updateHotspotSectionTitles(groups) {
    const userTitle = document.getElementById('hotspotUserSectionTitle');
    const activeTitle = document.getElementById('hotspotActiveSectionTitle');
    const titleMap = {
        all: 'Daftar Voucher Hotspot',
        inactive: `Voucher Belum Active (${groups.inactive.length})`,
        active: `Voucher Active (${groups.active.length})`,
        expired: `Voucher Expired (${groups.expired.length})`,
        used: `Voucher Sudah Dipakai (${groups.used.length})`
    };
    userTitle.textContent = titleMap[currentHotspotFilter] || titleMap.all;
    activeTitle.textContent = currentHotspotFilter === 'active' ? `Active Hotspot (${groups.active.length})` : 'Active Hotspot';
    hotspotResetFilterBtn.classList.toggle('hidden', currentHotspotFilter === 'all');
    reprintUnusedSelectedBtn.classList.toggle('hidden', !(currentHotspotFilter === 'all' || currentHotspotFilter === 'inactive'));
    deleteExpiredBtn.classList.toggle('hidden', groups.expired.length === 0);
    updateBulkDeleteBtnVisibility();
}

function updateBulkDeleteBtnVisibility() {
    const selectedCount = document.querySelectorAll('.unused-voucher-checkbox:checked').length;
    const btn = document.getElementById('deleteSelectedBtn');
    if (btn) btn.classList.toggle('hidden', selectedCount === 0);
}

const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', async () => {
        const selectedNames = Array.from(document.querySelectorAll('.unused-voucher-checkbox:checked')).map(input => input.value);
        if (!selectedNames.length) return;
        if (!confirm(`Hapus ${selectedNames.length} voucher yang dipilih?`)) return;

        const fd = new FormData();
        fd.append('action', 'delete_expired_users');
        fd.append('names_json', JSON.stringify(selectedNames));
        await submitHotspotAction(fd, 'hotspotUserMsg', `Menghapus ${selectedNames.length} voucher...`);
    });
}
function syncUserMode() { const m = hotspotUserMode.value; const needsInput = m === 'prefix' || m === 'manual'; hotspotPrefixInput.disabled = !needsInput; hotspotPrefixInput.required = needsInput; if (!needsInput) hotspotPrefixInput.value = ''; }
function syncLimitTimeMode() { const custom = hotspotLimitTimeMode.value === 'custom'; hotspotLimitHours.disabled = !custom; hotspotLimitMinutes.disabled = !custom; if (!custom) { hotspotLimitHours.value = '0'; hotspotLimitMinutes.value = '0'; } }
function renderSelects(data) {
    const ps = document.getElementById('hotspotVoucherProfileSelect'); const cp = ps.value || 'default';
    ps.innerHTML = (data.profiles || [{name:'default'}]).map(i => { const v = i.name || 'default'; return `<option value="${escapeHtml(v)}"${v === cp ? ' selected' : ''}>${escapeHtml(v)}</option>`; }).join('');
    const ss = document.getElementById('hotspotServerSelect'); const cs = ss.value || '';
    ss.innerHTML = `<option value="">Semua server</option>` + (data.servers || []).map(i => { const v = i.name || ''; return `<option value="${escapeHtml(v)}"${v === cs ? ' selected' : ''}>${escapeHtml(v)}</option>`; }).join('');
    const serverNames = Array.from(new Set((data.users || []).map(i => String(i.server || '').trim()).filter(v => v && v !== '-'))).sort((a, b) => a.localeCompare(b));
    hotspotVoucherServerFilter.innerHTML = `<option value="">Semua Server Voucher</option>` + serverNames.map(v => `<option value="${escapeHtml(v)}"${v === currentVoucherServerFilter ? ' selected' : ''}>${escapeHtml(v)}</option>`).join('');
}
function renderProfiles(p = []) {
    const l = document.getElementById('hotspotProfileList'); if (!p.length) { l.innerHTML = '<p class="text-sm text-secondary">Belum ada profile hotspot.</p>'; return; }
    l.innerHTML = `<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">Profile</th><th class="pb-3">Rate Limit</th><th class="pb-3">Keepalive</th><th class="pb-3">Idle</th><th class="pb-3">Shared</th><th class="pb-3">Aksi</th></tr></thead><tbody>${p.map(i => `<tr class="border-b border-outline-variant/50"><td class="py-3 font-medium">${escapeHtml(i.name || '-')}</td><td class="py-3">${escapeHtml(i.rate_limit || '-')}</td><td class="py-3">${escapeHtml(i.keepalive_timeout || '-')}</td><td class="py-3">${escapeHtml(i.idle_timeout || '-')}</td><td class="py-3">${escapeHtml(i.shared_users || '-')}</td><td class="py-3"><button class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 delete-hotspot-profile-btn" data-name="${escapeHtml(i.name || '')}">Hapus</button></td></tr>`).join('')}</tbody></table>`;
    l.querySelectorAll('.delete-hotspot-profile-btn').forEach(b => { b.addEventListener('click', async () => { const n = b.dataset.name; if (n && confirm(`Hapus profile ${n}?`)) { const f = new FormData(); f.append('action', 'delete_profile'); f.append('name', n); await submitHotspotAction(f, 'hotspotProfileMsg', `Menghapus ${n}...`); } }); });
}
function renderUsers(u = [], active = []) {
    const l = document.getElementById('hotspotUserList'); if (!u.length) { l.innerHTML = '<p class="text-sm text-secondary">Belum ada voucher hotspot.</p>'; return; }
    const filteredUsers = currentVoucherServerFilter === '' ? u : u.filter(item => String(item.server || '').trim() === currentVoucherServerFilter);
    if (!filteredUsers.length) { l.innerHTML = '<p class="text-sm text-secondary">Tidak ada voucher pada server yang dipilih.</p>'; return; }
    const groups = getVoucherGroups(filteredUsers, active);
    const renderUserTable = (items, emptyMessage, options = {}) => {
        const showCheckboxes = true;
        if (!items.length) return `<p class="text-sm text-secondary">${emptyMessage}</p>`;
        return `<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant">${showCheckboxes ? '<th class="pb-3"><input type="checkbox" class="unused-voucher-master-checkbox"></th>' : ''}<th class="pb-3">ID</th><th class="pb-3">Toko</th><th class="pb-3">Voucher</th><th class="pb-3">Profile</th><th class="pb-3">Uptime</th><th class="pb-3">Limit</th><th class="pb-3">Status</th><th class="pb-3">Aksi</th></tr></thead><tbody>${items.map(i => {
            const info = normalizeVoucherPrintItem(i);
            const used = Number(i.bytes_in || 0) + Number(i.bytes_out || 0);
            const cleanComment = info.comment.replace(/\[[QPSI]:[^\]]+\]/g, '').trim();
            return `<tr class="border-b border-outline-variant/50">
                ${showCheckboxes ? `<td class="py-3"><input type="checkbox" class="unused-voucher-checkbox" value="${escapeHtml(i.name || '')}"></td>` : ''}
                <td class="py-3"><span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">${escapeHtml(info.id_voucher || '-')}</span></td>
                <td class="py-3">${escapeHtml(info.shop_id || '-')}</td>
                <td class="py-3"><strong>${escapeHtml(i.name || '-')}</strong>${cleanComment ? `<br><span class="text-xs text-on-surface-variant">${escapeHtml(cleanComment)}</span>` : ''}</td>
                <td class="py-3">${escapeHtml(i.profile || '-')}</td>
                <td class="py-3">${escapeHtml(i.uptime || '-')} / ${formatBytes(i.limit_bytes_total)}</td>
                <td class="py-3">${i.disabled ? '<span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">DISABLED</span>' : '<span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">ACTIVE</span>'}</td>
                <td class="py-3">${i.disabled ? `<button class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 enable-hotspot-user-btn" data-name="${escapeHtml(i.name || '')}">Enable</button>` : `<button class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90 disable-hotspot-user-btn" data-name="${escapeHtml(i.name || '')}">Disable</button>`} <button class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 delete-hotspot-user-btn" data-name="${escapeHtml(i.name || '')}">Hapus</button></td>
            </tr>`;
        }).join('')}</tbody></table>`;
    };
    if (currentHotspotFilter === 'inactive') {
        l.innerHTML = renderUserTable(groups.inactive, 'Belum ada voucher yang belum active.');
    } else if (currentHotspotFilter === 'active') {
        l.innerHTML = renderUserTable(groups.active, 'Belum ada voucher yang sedang active.');
    } else if (currentHotspotFilter === 'expired') {
        l.innerHTML = renderUserTable(groups.expired, 'Belum ada voucher expired.');
    } else if (currentHotspotFilter === 'used') {
        l.innerHTML = renderUserTable(groups.used, 'Belum ada voucher yang sudah dipakai.');
    } else {
        l.innerHTML = `
            <div class="space-y-4">
                <div>
                    <h4 class="font-semibold text-sm text-on-surface mb-3">Belum Dipakai</h4>
                    ${renderUserTable(groups.inactive, 'Belum ada voucher yang belum dipakai.')}
                </div>
                <div>
                    <h4 class="font-semibold text-sm text-on-surface mb-3">Sedang Active</h4>
                    ${renderUserTable(groups.active, 'Belum ada voucher yang sedang active.')}
                </div>
                <div>
                    <h4 class="font-semibold text-sm text-on-surface mb-3">Expired</h4>
                    ${renderUserTable(groups.expired, 'Belum ada voucher expired.')}
                </div>
                <div>
                    <h4 class="font-semibold text-sm text-on-surface mb-3">Sudah Dipakai</h4>
                    ${renderUserTable(groups.used, 'Belum ada voucher yang sudah dipakai.')}
                </div>
            </div>`;
    }

    l.querySelectorAll('.unused-voucher-master-checkbox').forEach(master => {
        master.addEventListener('change', () => {
            const container = master.closest('table');
            if (container) {
                container.querySelectorAll('.unused-voucher-checkbox').forEach(input => { input.checked = master.checked; });
            }
            updateBulkDeleteBtnVisibility();
        });
    });

    l.querySelectorAll('.unused-voucher-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkDeleteBtnVisibility);
    });
    updateBulkDeleteBtnVisibility();
    l.querySelectorAll('.enable-hotspot-user-btn, .disable-hotspot-user-btn, .delete-hotspot-user-btn').forEach(b => { b.addEventListener('click', async () => { const n = b.dataset.name; const isD = b.classList.contains('delete-hotspot-user-btn'); const a = isD ? 'delete_user' : (b.classList.contains('enable-hotspot-user-btn') ? 'enable_user' : 'disable_user'); if (n && confirm(`${isD ? 'Hapus' : 'Proses'} voucher ${n}?`)) { const f = new FormData(); f.append('action', a); f.append('name', n); await submitHotspotAction(f, 'hotspotUserMsg', `Memproses ${n}...`); } }); });
}
function renderActive(a = []) {
    const l = document.getElementById('hotspotActiveList'); if (!a.length) { l.innerHTML = '<p class="text-sm text-secondary">Belum ada user aktif.</p>'; return; }
    l.innerHTML = `<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">User</th><th class="pb-3">Address</th><th class="pb-3">Uptime</th><th class="pb-3">Pemakaian</th><th class="pb-3">MAC</th><th class="pb-3">Server</th><th class="pb-3">Aksi</th></tr></thead><tbody>${a.map(i => { const used = Number(i.bytes_in || 0) + Number(i.bytes_out || 0); return `<tr class="border-b border-outline-variant/50"><td class="py-3 font-medium">${escapeHtml(i.user || '-')}</td><td class="py-3">${escapeHtml(i.address || '-')}</td><td class="py-3">${escapeHtml(i.uptime || '-')}</td><td class="py-3">${formatGb(used)}<br><span class="text-xs text-on-surface-variant">${formatBytes(used)}</span></td><td class="py-3">${escapeHtml(i.mac_address || '-')}</td><td class="py-3">${escapeHtml(i.server || '-')}</td><td class="py-3"><button class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 remove-hotspot-active-btn" data-id="${escapeHtml(i.id || '')}" data-user="${escapeHtml(i.user || '')}">Hapus Active</button></td></tr>`; }).join('')}</tbody></table>`;
    l.querySelectorAll('.remove-hotspot-active-btn').forEach(b => { b.addEventListener('click', async () => { const sid = b.dataset.id, u = b.dataset.user; if (sid && confirm(`Hapus active ${u}?`)) { const f = new FormData(); f.append('action', 'remove_active'); f.append('session_id', sid); await submitHotspotAction(f, 'hotspotActiveMsg', `Menghapus ${u}...`); } }); });
}
function renderCreatedVouchers(c = []) { renderStoredVouchers(upsertUnprintedVouchers(c || [])); }
function renderDistribution(users = []) {
    const section = document.getElementById('hotspotDistributionSection');
    const list = document.getElementById('hotspotDistributionList');
    const shops = {};
    let hasData = false;

    const activeUsers = new Set((window.__hotspotData?.active || []).map(item => String(item.user || '').trim()).filter(Boolean));

    users.forEach(u => {
        const item = normalizeVoucherPrintItem(u);
        if (item.shop_id) {
            if (!shops[item.shop_id]) shops[item.shop_id] = { items: [], used: 0, unused: 0 };

            const isUsed = isVoucherUsed(u) || activeUsers.has(String(u.name).trim()) || isVoucherExpired(u);
            if (isUsed) shops[item.shop_id].used++;
            else shops[item.shop_id].unused++;

            shops[item.shop_id].items.push(item);
            hasData = true;
        }
    });

    section.classList.remove('hidden');

    if (!hasData) {
        list.innerHTML = '<p class="text-sm text-secondary text-center py-8">Belum ada sebaran voucher ke toko. Buat voucher baru dengan mengisi <strong>ID Toko</strong> untuk melihat sebaran di sini.</p>';
        return;
    }

    let html = '<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">';
    Object.keys(shops).sort().forEach((shopId, index) => {
        const shop = shops[shopId];
        const shopVouchers = shop.items;
        const contentId = `shopContent_${index}`;
        html += `
            <div class="border border-outline-variant rounded-xl overflow-hidden cursor-pointer" onclick="window.toggleShopDetail('${contentId}')">
                <div class="bg-surface-container-low p-4 flex justify-between items-center" id="header_${contentId}">
                    <div class="flex flex-col gap-1">
                        <span class="text-base font-bold text-primary">Toko: ${escapeHtml(shopId)}</span>
                        <div class="flex gap-1">
                            <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">${shop.unused} Belum</span>
                            <span class="px-2 py-0.5 bg-secondary text-white text-xs font-semibold rounded-full">${shop.used} Sudah</span>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="px-3 py-1 bg-green-100 text-green-700 text-sm font-semibold rounded-full">${shopVouchers.length} Vcr</span>
                    </div>
                </div>
                <div id="${contentId}" class="hidden border-t border-outline-variant" onclick="event.stopPropagation()">
                    <div class="p-3 border-b border-outline-variant flex gap-2 justify-end flex-wrap">
                        <button class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90" onclick="printShopVoucherList('${escapeHtml(shopId)}', true)">Print Terpakai</button>
                        <button class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90" onclick="printShopVougerList('${escapeHtml(shopId)}', false)">Print Semua</button>
                        <button class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90" onclick="printShopVouchers('${escapeHtml(shopId)}')">Print Struk</button>
                    </div>
                    <div class="overflow-x-auto max-h-[350px] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white z-10">
                                <tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant">
                                    <th class="py-2 px-3 text-left">ID</th>
                                    <th class="py-2 px-3 text-left">Paket</th>
                                    <th class="py-2 px-3 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${shopVouchers.map(v => {
                                    const statusLabel = (isVoucherUsed(v) || activeUsers.has(String(v.username).trim()) || isVoucherExpired(v)) ? 'SDH' : 'BLM';
                                    const badgeClass = statusLabel === 'SDH' ? 'bg-secondary text-white' : 'bg-gray-100 text-gray-500';
                                    return `
                                        <tr class="border-b border-outline-variant/50">
                                            <td class="py-2 px-3"><span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">${escapeHtml(v.id_voucher || '-')}</span></td>
                                            <td class="py-2 px-3">
                                                <div class="font-medium">${escapeHtml(v.quota_display || '-')}</div>
                                                <div class="text-xs text-on-surface-variant">${escapeHtml(v.price || '-')}</div>
                                            </td>
                                            <td class="py-2 px-3"><span class="px-2 py-0.5 text-xs font-semibold rounded-full ${badgeClass}">${statusLabel}</span></td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
    window.__shopData = shops;
}

window.toggleShopDetail = function(id) {
    const el = document.getElementById(id);
    const header = document.getElementById('header_' + id);
    if (el) {
        const isHidden = el.classList.contains('hidden');
        el.classList.toggle('hidden');
        if (header) {
            header.style.borderBottomColor = isHidden ? '#c3c6d7' : 'transparent';
        }
    }
};

function updateShopDistributionView() {
}

function printShopVouchers(shopId) {
    if (!window.__shopData || !window.__shopData[shopId] || !window.receiptPrinter) return;
    const shop = window.__shopData[shopId];
    const vouchers = shop.items;
    if (confirm(`Print ${vouchers.length} struk voucher untuk toko ${shopId}?`)) {
        window.receiptPrinter.printVouchers(vouchers, { appName: '<?= addslashes($appName) ?>' });
    }
}

function printShopVoucherList(shopId, onlyUsed = true) {
    if (!window.__shopData || !window.__shopData[shopId]) return;
    const shop = window.__shopData[shopId];
    const allVouchers = shop.items;
    const appName = '<?= addslashes($appName) ?>';
    const now = new Date().toLocaleString('id-ID', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' });
    const activeUsers = new Set((window.__hotspotData?.active || []).map(item => String(item.user || '').trim()).filter(Boolean));

    const vouchers = onlyUsed ? allVouchers.filter(v => {
        const isActive = activeUsers.has(String(v.username).trim());
        return isVoucherUsed(v) || isActive || isVoucherExpired(v);
    }) : allVouchers;

    if (onlyUsed && vouchers.length === 0) {
        alert('Belum ada voucher yang terpakai di toko ini.');
        return;
    }

    let totalPrice = 0;
    vouchers.forEach(v => {
        const price = parseInt(String(v.price || '0').replace(/[^0-9]/g, '')) || 0;
        totalPrice += price;
    });

    const formattedTotal = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalPrice);
    const reportTitle = onlyUsed ? 'LAPORAN PENJUALAN VOUCHER' : 'LAPORAN STATUS VOUCHER';

    const paperWidth = window.receiptPrinter ? (window.receiptPrinter.getPaperWidth ? window.receiptPrinter.getPaperWidth() : 58) : 58;

    let html = `<!DOCTYPE html><html><head><title>Status - ${shopId}</title>
        <style>
            @page { size: ${paperWidth}mm auto; margin: 0; }
            body {
                width: ${paperWidth}mm;
                margin: 0;
                padding: 4mm 2mm;
                font-family: "Courier New", monospace;
                font-size: 10px;
                font-weight: bold;
                line-height: 1.2;
            }
            .text-center { text-align: center; }
            .divider { border-top: 1px dashed #000; margin: 2mm 0; }
            table { width: 100%; border-collapse: collapse; }
            th { text-align: left; border-bottom: 1px solid #000; padding: 1mm 0; font-size: 9px; }
            td { padding: 1.5mm 0; vertical-align: middle; border-bottom: 0.5px solid #eee; }
            .status-col { text-align: right; font-size: 9px; white-space: nowrap; }
            @media screen { body { background:#eee; margin: 20px auto; box-shadow: 0 0 5px rgba(0,0,0,0.2); background:#fff; } }
        </style>
    </head><body>
        <div class="text-center" style="font-size:14px; text-transform:uppercase;">${appName}</div>
        <div class="text-center" style="font-size:10px;">${reportTitle}</div>
        <div class="divider"></div>
        <div>TOKO: ${shopId}</div>
        <div style="font-size:9px;">TGL : ${now}</div>
        <div class="divider"></div>
        <table>
            <thead>
                <tr>
                    <th width="22">NO</th>
                    <th width="32">ID</th>
                    <th>PAKET</th>
                    ${!onlyUsed ? '<th class="text-center" width="65">STATUS</th>' : ''}
                </tr>
            </thead>
            <tbody>
                ${vouchers.map((v, i) => {
                    const isActive = activeUsers.has(String(v.username).trim());
                    const used = isVoucherUsed(v) || isActive || isVoucherExpired(v);
                    const statusLabel = used ? 'SDH PAKAI' : 'BLM PAKAI';

                    return `
                        <tr>
                            <td>${i + 1}</td>
                            <td>${escapeHtml(v.id_voucher || '-')}</td>
                            <td>
                                <div>${escapeHtml(v.quota_display || '-')}</div>
                                <div style="font-size:8px; font-weight:normal;">${escapeHtml(v.price || '-')}</div>
                            </td>
                            ${!onlyUsed ? `<td class="status-col">${statusLabel}</td>` : ''}
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
        <div class="divider"></div>
        <div style="display:flex; justify-content:space-between;">
            <span>JUMLAH VOUCHER:</span>
            <span>${vouchers.length}</span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:11px;">
            <span>TOTAL TAGIHAN:</span>
            <span>${formattedTotal}</span>
        </div>
        <div class="text-center" style="margin-top:4mm; font-size:9px;">--- SELESAI ---</div>
        <script>window.onload = function() { window.print(); setTimeout(() => { window.close(); }, 500); };<\/script>
    </body></html>`;

    const win = window.open('', '_blank', `width=${paperWidth * 4},height=600`);
    win.document.write(html);
    win.document.close();
}

function printVoucherChecklist(vouchers = [], title = 'LIST VOUCHER') {
    if (!vouchers.length) return;
    const appName = '<?= addslashes($appName) ?>';
    const now = new Date().toLocaleString('id-ID', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' });
    const paperWidth = window.receiptPrinter ? (window.receiptPrinter.getPaperWidth ? window.receiptPrinter.getPaperWidth() : 58) : 58;

    let html = `<!DOCTYPE html><html><head><title>Checklist</title>
        <style>
            @page { size: ${paperWidth}mm auto; margin: 0; }
            body { width: ${paperWidth}mm; margin: 0; padding: 4mm 2mm; font-family: "Courier New", monospace; font-size: 10px; font-weight: bold; line-height: 1.2; }
            .text-center { text-align: center; }
            .divider { border-top: 1px dashed #000; margin: 2mm 0; }
            table { width: 100%; border-collapse: collapse; }
            th { text-align: left; border-bottom: 1px solid #000; padding: 1mm 0; font-size: 9px; }
            td { padding: 1.5mm 0; vertical-align: middle; border-bottom: 0.5px solid #eee; }
            .check-box { font-size: 14px; text-align: right; width: 30px; }
            @media screen { body { background:#fff; } }
        </style>
    </head><body>
        <div class="text-center" style="font-size:14px; text-transform:uppercase;">${appName}</div>
        <div class="text-center" style="font-size:10px;">${title}</div>
        <div class="divider"></div>
        <div style="font-size:9px;">TGL : ${now}</div>
        <div class="divider"></div>
        <table>
            <thead>
                <tr>
                    <th width="22">NO</th>
                    <th width="32">ID</th>
                    <th>PAKET</th>
                    <th class="text-center" width="35">[ ]</th>
                </tr>
            </thead>
            <tbody>
                ${vouchers.map((v, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${escapeHtml(v.id_voucher || '-')}</td>
                        <td>
                            <div>${escapeHtml(v.quota_display || '-')}</div>
                            <div style="font-size:8px; font-weight:normal;">${escapeHtml(v.price || '-')}</div>
                        </td>
                        <td class="check-box">[ ]</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
        <div class="divider"></div>
        <div style="text-align:right;">TOTAL: ${vouchers.length} VOUCHER</div>
        <script>window.onload = function() { window.print(); setTimeout(() => { window.close(); }, 500); };<\/script>
    </body></html>`;

    const win = window.open('', '_blank', `width=${paperWidth * 4},height=600`);
    win.document.write(html);
    win.document.close();
}

function toggleShopDetail(id) {
}
function renderAllHotspotSections(data) {
    const scopedUsers = currentVoucherServerFilter === '' ? (data.users || []) : (data.users || []).filter(item => String(item.server || '').trim() === currentVoucherServerFilter);
    const groups = getVoucherGroups(scopedUsers, data.active || []);
    renderSummaryCards(groups);
    updateHotspotSectionTitles(groups);
    renderUsers(data.users || [], data.active || []);
    renderActive(data.active || []);
    renderDistribution(data.users || []);
}
async function loadHotspotData() {
    const userList = document.getElementById('hotspotUserList');
    const userMsg = document.getElementById('hotspotUserMsg');
    const activeMsg = document.getElementById('hotspotActiveMsg');
    try {
        console.log('Memulai pemuatan data hotspot...');
        const r = await fetch(`api/hotspot.php?_=${Date.now()}`);
        if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);

        const text = await r.text();
        let d;
        try {
            d = JSON.parse(text);
        } catch (jsonErr) {
            console.error('Respon API bukan JSON valid:', text);
            throw new Error('Format data dari server tidak valid (Bukan JSON).');
        }

        if (!d.success) {
            const errorMsg = d.message || 'Gagal memuat data dari MikroTik.';
            console.error('API Error:', errorMsg);
            if (userMsg) { userMsg.textContent = errorMsg; userMsg.style.color = '#dc2626'; }
            if (activeMsg) { activeMsg.textContent = errorMsg; activeMsg.style.color = '#dc2626'; }
            userList.innerHTML = `<div class="p-4 bg-error-container text-on-error-container rounded-lg text-sm">${escapeHtml(errorMsg)}</div>`;
            return;
        }

        console.log('Data hotspot berhasil diterima, merender...');
        window.__hotspotData = d;
        renderSelects(d);
        renderProfiles(d.profiles);
        renderAllHotspotSections(d);

        if (userMsg) userMsg.textContent = '';
        if (activeMsg) activeMsg.textContent = '';
        console.log('Render selesai.');
    } catch (e) {
        console.error('Error saat loadHotspotData:', e);
        const errorStr = e.message || 'Terjadi kesalahan sistem saat memuat data.';
        if (userMsg) { userMsg.textContent = errorStr; userMsg.style.color = '#dc2626'; }
        userList.innerHTML = `<div class="p-4 bg-error-container text-on-error-container rounded-lg text-sm">${escapeHtml(errorStr)}<br><small>Coba muat ulang halaman atau cek koneksi MikroTik di Settings.</small></div>`;
    }
}
async function submitHotspotAction(fd, mid, lt = 'Menyimpan...') {
    const action = fd.get('action');
    const msg = document.getElementById(mid); msg.textContent = lt; msg.style.color = 'inherit';
    try {
        const r = await fetch('api/hotspot.php', { method: 'POST', body: fd }), d = await r.json();
        if (!d.success) { msg.textContent = d.message; msg.style.color = '#dc2626'; return false; }
        msg.textContent = d.message; msg.style.color = '#16a34a';
        if (Array.isArray(d.created)) renderCreatedVouchers(d.created);
        else if (action !== 'create_vouchers') renderCreatedVouchers([]);
        await loadHotspotData();
        return true;
    } catch (e) { msg.textContent = 'Error sistem'; msg.style.color = '#dc2626'; return false; } finally { setTimeout(() => { msg.textContent = ''; }, 4000); }
}
function toggleSidebar() { document.getElementById('appSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }
function closeSidebar() { document.getElementById('appSidebar').classList.add('-translate-x-full'); document.getElementById('sidebarOverlay').classList.add('hidden'); }
document.querySelectorAll('#appSidebar a').forEach(l => l.addEventListener('click', () => { if (window.innerWidth < 768) closeSidebar(); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

window.onload = () => {
    console.log('Page loaded, starting initialization...');
    try {
        syncUserMode();
        console.log('syncUserMode done');
        syncLimitTimeMode();
        console.log('syncLimitTimeMode done');
        renderStoredVouchers();
        console.log('renderStoredVouchers done');
        loadHotspotData();
        console.log('loadHotspotData called');
    } catch (err) {
        console.error('Initialization failed:', err);
        document.getElementById('hotspotUserList').innerHTML = `<div class="p-4 bg-error-container text-on-error-container rounded-lg text-sm">Inisialisasi gagal: ${err.message}</div>`;
    }
};
hotspotUserMode.addEventListener('change', syncUserMode);
hotspotLimitTimeMode.addEventListener('change', syncLimitTimeMode);
hotspotResetFilterBtn.addEventListener('click', () => setHotspotFilter('all', true));
hotspotVoucherServerFilter.addEventListener('change', () => {
    currentVoucherServerFilter = hotspotVoucherServerFilter.value || '';
    if (window.__hotspotData) renderAllHotspotSections(window.__hotspotData);
});
document.querySelectorAll('.hotspot-summary-card').forEach(card => {
    card.addEventListener('click', () => setHotspotFilter(card.dataset.filter || 'all', true));
});
hotspotVoucherForm.addEventListener('submit', async e => { e.preventDefault(); const s = await submitHotspotAction(new FormData(hotspotVoucherForm), 'hotspotVoucherMsg'); if (s) { hotspotVoucherForm.reset(); syncUserMode(); syncLimitTimeMode(); } });
hotspotProfileForm.addEventListener('submit', async e => { e.preventDefault(); const s = await submitHotspotAction(new FormData(hotspotProfileForm), 'hotspotProfileMsg'); if (s) hotspotProfileForm.reset(); });
document.getElementById('selectAllVouchersBtn').addEventListener('click', () => {
    document.querySelectorAll('.voucher-print-checkbox').forEach(input => { input.checked = true; });
    const masterCheckbox = document.getElementById('voucherPrintMasterCheckbox');
    if (masterCheckbox) masterCheckbox.checked = true;
});
document.getElementById('printSelectedVoucherBtn').addEventListener('click', () => {
    const selectedVouchers = getSelectedUnprintedVouchers();
    if (!selectedVouchers.length || !window.receiptPrinter) return;
    window.receiptPrinter.printVouchers(selectedVouchers, { appName: '<?= addslashes($appName) ?>' });
});
document.getElementById('printChecklistVouchersBtn').addEventListener('click', () => {
    const selectedVouchers = getSelectedUnprintedVouchers();
    if (!selectedVouchers.length) return;
    printVoucherChecklist(selectedVouchers, 'CHECKLIST VOUCHER BARU');
});
document.getElementById('markVouchersAsPrintedBtn').addEventListener('click', () => {
    const selectedVouchers = getSelectedUnprintedVouchers();
    if (!selectedVouchers.length) return;
    if (confirm(`Hapus ${selectedVouchers.length} voucher dari daftar antrian cetak ini? (Voucher tidak akan terhapus dari MikroTik)`)) {
        renderStoredVouchers(removeStoredVouchers(selectedVouchers.map(item => item.username)));
    }
});
reprintUnusedSelectedBtn.addEventListener('click', () => {
    const selectedNames = Array.from(document.querySelectorAll('.unused-voucher-checkbox:checked')).map(input => input.value);
    if (!selectedNames.length || !window.__hotspotData || !window.receiptPrinter) return;
    const voucherLookup = new Set(selectedNames.map(item => String(item || '').trim()).filter(Boolean));
    const selectedVouchers = (window.__hotspotData.users || [])
        .filter(item => voucherLookup.has(String(item.name || '').trim()))
        .map(item => normalizeVoucherPrintItem(item));
    if (!selectedVouchers.length) return;
    renderStoredVouchers(upsertUnprintedVouchers(selectedVouchers));
    window.receiptPrinter.printVouchers(selectedVouchers, { appName: '<?= addslashes($appName) ?>' });
    renderStoredVouchers(removeStoredVouchers(selectedVouchers.map(item => item.username)));
});
deleteExpiredBtn.addEventListener('click', async () => {
    if (!window.__hotspotData) return;
    const scopedUsers = currentVoucherServerFilter === '' ? (window.__hotspotData.users || []) : (window.__hotspotData.users || []).filter(item => String(item.server || '').trim() === currentVoucherServerFilter);
    const groups = getVoucherGroups(scopedUsers, window.__hotspotData.active || []);
    const expiredNames = groups.expired.map(item => String(item.name || '').trim()).filter(Boolean);
    if (!expiredNames.length) return;
    const serverLabel = currentVoucherServerFilter ? ` pada server ${currentVoucherServerFilter}` : '';
    if (!confirm(`Hapus ${expiredNames.length} voucher expired / sudah dipakai${serverLabel}?`)) return;
    const fd = new FormData();
    fd.append('action', 'delete_expired_users');
    fd.append('names_json', JSON.stringify(expiredNames));
    await submitHotspotAction(fd, 'hotspotUserMsg', `Menghapus ${expiredNames.length} voucher expired...`);
});
async function logout() { await fetch('api/auth.php?action=logout'); location.href = '<?= htmlspecialchars(app_url('login'), ENT_QUOTES, 'UTF-8') ?>'; }
</script>
<?= app_render_tenant_expiry_script('login.php') ?>
</body>
</html>

