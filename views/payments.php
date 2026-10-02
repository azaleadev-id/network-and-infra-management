<?php
require_once __DIR__ . '/../config/auth.php';
$account = app_require_tenant_panel_page();
$appName = app_setting_get('app_name', 'Nikonet');
$billingDueDay = (int) app_setting_get('billing_due_day', 27);
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembayaran - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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
</style>
</head>
<body class="bg-surface text-on-surface">

<?php $activePage = 'payments'; require_once __DIR__ . '/partials/dashboard_sidebar.php'; ?>

<!-- Main Content -->
<main class="lg:ml-[270px] min-h-screen transition-all">
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-outline-variant h-16 flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button id="sidebarToggle" class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center hover:bg-surface-container transition-all" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-on-surface-variant">menu</span>
            </button>
            <h2 class="text-lg font-black text-on-surface">Manajemen Pembayaran</h2>
        </div>
    </header>

    <div class="p-6 max-w-[1600px] mx-auto">
        <?= app_render_tenant_expiry_banner() ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 border-l-4 border-emerald-500 rounded-xl p-6 shadow-sm text-center">
                <div class="text-sm text-on-surface-variant font-medium">Total Tagihan</div>
                <div class="text-3xl font-bold text-on-surface mt-2" id="billingTotalStat">0</div>
                <div class="text-xs text-on-surface-variant mt-1">Semua tagihan pelanggan</div>
            </div>
            <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 border-l-4 border-emerald-500 rounded-xl p-6 shadow-sm text-center">
                <div class="text-sm text-on-surface-variant font-medium">Sudah Bayar</div>
                <div class="text-3xl font-bold text-green-600 mt-2" id="billingPaidStat">0</div>
                <div class="text-xs text-on-surface-variant mt-1">Tagihan yang sudah lunas</div>
            </div>
            <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 border-l-4 border-emerald-500 rounded-xl p-6 shadow-sm text-center">
                <div class="text-sm text-on-surface-variant font-medium">Belum Bayar</div>
                <div class="text-3xl font-bold text-red-600 mt-2" id="billingUnpaidStat">0</div>
                <div class="text-xs text-on-surface-variant mt-1">Perlu ditagih / follow up</div>
            </div>
            <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 border-l-4 border-emerald-500 rounded-xl p-6 shadow-sm text-center">
                <div class="text-sm text-on-surface-variant font-medium">Sedang Isolir</div>
                <div class="text-3xl font-bold text-orange-600 mt-2" id="billingIsolirStat">0</div>
                <div class="text-xs text-on-surface-variant mt-1">Tagihan yang sudah terisolir</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.5fr] gap-6">
            <div class="space-y-6">
                <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 rounded-xl shadow-sm" id="isolirSetupSection">
                    <h3 class="px-6 py-4 border-b border-outline-variant text-lg font-bold text-on-surface">Setup Isolir MikroTik</h3>
                    <div class="p-6">
                        <form id="isolirSetupForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Nama Profile Isolir</label>
                                <input type="text" name="profile_name" id="isolirProfileName" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: ISOLIR" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Rate Limit Isolir</label>
                                <input type="text" name="rate_limit" id="isolirRateLimit" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 64k/64k">
                                <p class="text-xs text-on-surface-variant mt-1">Dipakai saat aplikasi membuat profile isolir otomatis di MikroTik.</p>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Comment Profile</label>
                                <input type="text" name="comment" id="isolirComment" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Opsional">
                            </div>
                            <div id="isolirSetupStatus" class="text-sm text-on-surface-variant bg-surface-container-low p-3 rounded-lg mb-4">Memeriksa setup isolir...</div>
                            <div class="flex gap-3">
                                <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Simpan Setup</button>
                                <button type="button" class="w-full px-6 py-2.5 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="createIsolirProfileButton">Simpan + Buat Profile</button>
                            </div>
                            <div id="isolirSetupMsg" class="mt-2.5 text-sm font-medium"></div>
                        </form>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 rounded-xl shadow-sm" id="billingDueDaySection">
                    <h3 class="px-6 py-4 border-b border-outline-variant text-lg font-bold text-on-surface">Jatuh Tempo Serentak</h3>
                    <div class="p-6">
                        <form id="billingDueDayForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Tanggal Default Jatuh Tempo</label>
                                <input type="number" min="1" max="31" name="billing_due_day" id="billingDueDayInput" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= $billingDueDay ?>" required>
                                <p class="text-xs text-on-surface-variant mt-1">Dipakai untuk nota pembayaran dan sebagai tanggal default saat menambah tagihan baru.</p>
                            </div>
                            <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Simpan Tanggal Default</button>
                            <div id="billingDueDayMsg" class="mt-2.5 text-sm font-medium"></div>
                        </form>

                        <hr class="my-5 border-t border-outline-variant">

                        <form id="billingMassDueDateForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Terapkan ke Semua Tagihan Belum Bayar</label>
                                <input type="date" name="target_date" id="billingMassDueDate" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Jam Timer Isolir Massal</label>
                                <input type="time" name="target_time" id="billingMassDueTime" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                <p class="text-xs text-on-surface-variant mt-1">Opsional. Jika diisi, jam timer isolir untuk tagihan yang aktif isolir akan ikut diseragamkan.</p>
                            </div>
                            <button type="submit" class="w-full px-6 py-2.5 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Terapkan Jatuh Tempo Serentak</button>
                            <div id="billingMassDueDateMsg" class="mt-2.5 text-sm font-medium"></div>
                        </form>

                        <hr class="my-5 border-t border-outline-variant">

                        <form id="billingMarkAllUnpaidForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Belum Bayar Semua</label>
                                <input type="date" name="target_date" id="billingMarkAllUnpaidDate" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Jam Timer Isolir</label>
                                <input type="time" name="target_time" id="billingMarkAllUnpaidTime" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                <p class="text-xs text-on-surface-variant mt-1">Opsional. Ubah semua tagihan menjadi belum bayar dan samakan jatuh tempo beserta jam timer isolir. Jika ada yang sedang terisolir, aplikasi akan mencoba memulihkannya terlebih dulu.</p>
                            </div>
                            <button type="submit" class="w-full px-6 py-2.5 bg-error text-on-error text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Belum Bayar Semua</button>
                            <div id="billingMarkAllUnpaidMsg" class="mt-2.5 text-sm font-medium"></div>
                        </form>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 rounded-xl shadow-sm" id="billingFormSection">
                    <h3 class="px-6 py-4 border-b border-outline-variant text-lg font-bold text-on-surface"><span id="billingFormTitle">Tambah Tagihan</span></h3>
                    <div class="p-6">
                        <form id="billingForm">
                            <input type="hidden" name="id" id="billingId" value="">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="isolir_enabled" value="0">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Pelanggan</label>
                                <select name="user_id" id="billingUser" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                                    <option value="">Pilih user...</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Status Layanan</label>
                                <select name="status" id="billingStatus" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="pending">Pending</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Status Pembayaran</label>
                                <select name="payment_status" id="paymentStatus" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                                    <option value="belum_bayar">Belum Bayar</option>
                                    <option value="sudah_bayar">Sudah Bayar</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Jatuh Tempo</label>
                                <input type="date" name="jatuh_tempo" id="billingDueDate" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Nominal</label>
                                <input type="number" step="0.01" min="0" name="jumlah" id="billingAmount" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 150000" required>
                            </div>
                            <div class="mb-4">
                                <label class="flex items-center gap-2 text-sm font-medium text-on-surface">
                                    <input type="checkbox" name="isolir_enabled" id="billingIsolirEnabled" value="1" class="rounded border-outline-variant text-primary focus:ring-primary/20">
                                    <span>Aktifkan timer isolir</span>
                                </label>
                                <p class="text-xs text-on-surface-variant mt-1">Jika aktif, pelanggan akan dipindah ke profile isolir pada tanggal jatuh tempo, sesuai jam yang kamu isi.</p>
                            </div>
                            <div class="mb-4 hidden" id="billingIsolirAtGroup">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Jam Isolir</label>
                                <input type="time" name="isolir_time" id="billingIsolirAt" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                <p class="text-xs text-on-surface-variant mt-1">Tanggal timer akan selalu mengikuti `jatuh_tempo` tagihan ini.</p>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-on-surface mb-1.5">Catatan</label>
                                <textarea name="catatan" id="billingNote" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" rows="3" placeholder="Opsional"></textarea>
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="billingSubmitButton">Simpan Tagihan</button>
                                <button type="button" class="w-full px-6 py-2.5 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all hidden" id="cancelBillingEdit">Batal Edit</button>
                            </div>
                            <div id="billingMsg" class="mt-2.5 text-sm font-medium"></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 rounded-xl shadow-sm" id="billingFilterSection">
                    <h3 class="px-6 py-4 border-b border-outline-variant text-lg font-bold text-on-surface flex items-center justify-between">
                        <span>Filter Tagihan</span>
                    </h3>
                    <div class="p-6 space-y-3">
                        <div class="flex gap-2">
                            <button type="button" class="px-4 py-2 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="printReceiptsButton">Print Nota Pembayaran</button>
                        </div>
                        <input type="text" id="billingSearch" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Cari nama, username, telepon, status pembayaran, atau status isolir">
                    </div>
                </div>

                <div class="bg-gradient-to-br from-emerald-100 to-emerald-50 rounded-xl shadow-sm" id="billingListSection">
                    <h3 class="px-6 py-4 border-b border-outline-variant text-lg font-bold text-on-surface">Daftar Pembayaran</h3>
                    <div class="p-6">
                        <div id="billingList" class="overflow-x-auto">Memuat data...</div>
                    </div>
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
        billingDueDay: <?= (int) app_setting_get('billing_due_day', 27) ?>,
        tenantId: <?= (int) ($account['tenant_id'] ?? 0) ?>,
        tenantName: <?= json_encode($appName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    });
}
</script>
<script>
let allBillings = [];
let allUsers = [];
let isolirSetup = null;
let billingDueDay = <?= $billingDueDay ?>;
let autoIsolirTimer = null;
const billingForm = document.getElementById('billingForm');
const cancelBillingEdit = document.getElementById('cancelBillingEdit');
const billingIsolirEnabled = document.getElementById('billingIsolirEnabled');
const billingIsolirAtGroup = document.getElementById('billingIsolirAtGroup');
const paymentStatusInput = document.getElementById('paymentStatus');

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(parsed);
}

function sanitizeDueDay(value) {
    const numeric = Number(value || 27);
    if (!Number.isFinite(numeric)) {
        return 27;
    }

    return Math.min(31, Math.max(1, Math.round(numeric)));
}

function getClampedDayForMonth(year, monthIndex, day) {
    const lastDay = new Date(year, monthIndex + 1, 0).getDate();
    return Math.min(lastDay, sanitizeDueDay(day));
}

function getDefaultDueDateValue() {
    const dueDay = sanitizeDueDay(billingDueDay);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    let year = now.getFullYear();
    let month = now.getMonth();
    let candidate = new Date(year, month, getClampedDayForMonth(year, month, dueDay));

    if (candidate < today) {
        month += 1;
        candidate = new Date(year, month, getClampedDayForMonth(year, month, dueDay));
    }

    const finalYear = candidate.getFullYear();
    const finalMonth = String(candidate.getMonth() + 1).padStart(2, '0');
    const finalDay = String(candidate.getDate()).padStart(2, '0');
    return `${finalYear}-${finalMonth}-${finalDay}`;
}

function refreshMassDueDateDefault() {
    const defaultDate = getDefaultDueDateValue();
    ['billingMassDueDate', 'billingMarkAllUnpaidDate'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.value = defaultDate;
        }
    });
}

function getIsolirBadge(item) {
    const status = String(item.isolir_status || 'nonaktif');
    if (!item.isolir_enabled) {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">NONAKTIF</span>';
    }
    if (status === 'menunggu') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">MENUNGGU</span>';
    }
    if (status === 'terisolir') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">TERISOLIR</span>';
    }
    if (status === 'dipulihkan') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">DIPULIHKAN</span>';
    }
    if (status === 'gagal') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">GAGAL</span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">NONAKTIF</span>';
}

function getPaymentStatusBadge(item) {
    if (String(item.isolir_status || '') === 'terisolir') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">TERISOLIR</span>';
    }

    return item.payment_status === 'sudah_bayar'
        ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">SUDAH BAYAR</span>'
        : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">BELUM BAYAR</span>';
}

function syncBillingIsolirControls() {
    const isPaid = paymentStatusInput.value === 'sudah_bayar';
    if (isPaid) {
        billingIsolirEnabled.checked = false;
        billingIsolirEnabled.disabled = true;
        document.getElementById('billingIsolirAt').value = '';
        billingIsolirAtGroup.classList.add('hidden');
        return;
    }

    billingIsolirEnabled.disabled = false;
    billingIsolirAtGroup.classList.toggle('hidden', !billingIsolirEnabled.checked);
}

function setBillingStats(data) {
    document.getElementById('billingTotalStat').textContent = data.length;
    document.getElementById('billingPaidStat').textContent = data.filter(item => item.payment_status === 'sudah_bayar').length;
    document.getElementById('billingUnpaidStat').textContent = data.filter(item => item.payment_status !== 'sudah_bayar').length;
    document.getElementById('billingIsolirStat').textContent = data.filter(item => item.isolir_status === 'terisolir').length;
}

function populateUserOptions(users) {
    const select = document.getElementById('billingUser');
    const currentValue = select.value;
    select.innerHTML = '<option value="">Pilih user...</option>';
    users.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = `${user.nama} (${user.username})`;
        select.appendChild(option);
    });
    if ([...select.options].some(option => option.value === currentValue)) {
        select.value = currentValue;
    }
}

function renderIsolirSetupStatus(setup) {
    isolirSetup = setup || null;
    const statusBox = document.getElementById('isolirSetupStatus');
    const badge = setup?.profile_exists
        ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">READY</span>'
        : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">BELUM SIAP</span>';
    const connectionInfo = setup?.connected
        ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">TERHUBUNG</span>'
        : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">KONEKSI GAGAL</span>';

    statusBox.innerHTML = `
        <div class="flex flex-wrap gap-2 mb-3">
            ${connectionInfo}
            ${badge}
        </div>
        <div><strong>Profile:</strong> ${escapeHtml(setup?.profile_name || '-')}</div>
        <div class="mt-1"><strong>Status:</strong> ${escapeHtml(setup?.message || 'Belum ada data setup isolir.')}</div>
    `;

    document.getElementById('isolirProfileName').value = setup?.profile_name || 'ISOLIR';
    document.getElementById('isolirRateLimit').value = setup?.rate_limit || '';
    document.getElementById('isolirComment').value = setup?.comment || '';
}

async function loadIsolirSetup() {
    try {
        const res = await fetch('api/isolir.php');
        const data = await res.json();
        if (!data.success) {
            document.getElementById('isolirSetupStatus').textContent = data.message || 'Gagal memuat setup isolir.';
            return;
        }

        renderIsolirSetupStatus(data.setup || null);
    } catch (error) {
        document.getElementById('isolirSetupStatus').textContent = 'Gagal memuat setup isolir.';
    }
}

function renderBillingTable(data) {
    const list = document.getElementById('billingList');
    if (!data.length) {
        list.innerHTML = '<p class="text-on-surface-variant text-sm">Belum ada data tagihan.</p>';
        return;
    }

    const rows = data.map(item => {
        const paymentBadge = getPaymentStatusBadge(item);

        const serviceBadgeClass = item.status === 'aktif' ? 'bg-blue-100 text-blue-800' : (item.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
        const isolirTimerLabel = item.isolir_enabled
            ? `${formatDateTime(item.isolir_at)}${item.isolir_error ? `<br><span class="text-on-surface-variant text-sm">${escapeHtml(item.isolir_error)}</span>` : ''}`
            : '<span class="text-on-surface-variant text-sm">Tidak aktif</span>';

        const canApplyIsolir = item.payment_status !== 'sudah_bayar' && item.isolir_enabled && item.isolir_status !== 'terisolir';

        return `
            <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors">
                <td class="px-4 py-3"><strong>${escapeHtml(item.nama)}</strong><br><span class="text-on-surface-variant text-sm">${escapeHtml(item.username)}</span></td>
                <td class="px-4 py-3">${formatCurrency(item.jumlah)}</td>
                <td class="px-4 py-3">${item.jatuh_tempo || '-'}</td>
                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${serviceBadgeClass}">${escapeHtml(String(item.status).toUpperCase())}</span></td>
                <td class="px-4 py-3">${paymentBadge}</td>
                <td class="px-4 py-3">${isolirTimerLabel}</td>
                <td class="px-4 py-3">${getIsolirBadge(item)}</td>
                <td class="px-4 py-3">${item.paid_at ? formatDateTime(item.paid_at) : '-'}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-2 flex-wrap">
                        <button type="button" class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 transition-all" onclick="editBilling(${item.id})">Edit</button>
                        ${item.payment_status === 'sudah_bayar' ? '' : `<button type="button" class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all" onclick="markPaid(${item.id})">Sudah Bayar</button>`}
                        ${canApplyIsolir ? `<button type="button" class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all" onclick="applyIsolir(${item.id})">Isolir Sekarang</button>` : ''}
                        <button type="button" class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 transition-all" onclick="deleteBilling(${item.id})">Hapus</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    list.innerHTML = `<table class="w-full text-sm"><thead><tr class="border-b border-outline-variant text-left"><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">User</th><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Nominal</th><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Jatuh Tempo</th><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status Layanan</th><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status Bayar</th><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Timer Isolir</th><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status Isolir</th><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Tanggal Bayar</th><th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Aksi</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function resetBillingForm() {
    billingForm.reset();
    document.getElementById('billingId').value = '';
    document.getElementById('billingFormTitle').textContent = 'Tambah Tagihan';
    document.getElementById('billingSubmitButton').textContent = 'Simpan Tagihan';
    cancelBillingEdit.classList.add('hidden');
    billingIsolirEnabled.checked = false;
    document.getElementById('billingDueDate').value = getDefaultDueDateValue();
    syncBillingIsolirControls();
}

window.editBilling = function(id) {
    const item = allBillings.find(row => Number(row.id) === Number(id));
    if (!item) return;

    document.getElementById('billingFormTitle').textContent = `Edit Tagihan: ${item.nama}`;
    document.getElementById('billingSubmitButton').textContent = 'Update Tagihan';
    document.getElementById('billingId').value = item.id;
    document.getElementById('billingUser').value = item.user_id;
    document.getElementById('billingStatus').value = item.status;
    document.getElementById('paymentStatus').value = item.payment_status;
    document.getElementById('billingDueDate').value = item.jatuh_tempo;
    document.getElementById('billingAmount').value = item.jumlah;
    document.getElementById('billingNote').value = item.catatan || '';
    billingIsolirEnabled.checked = Number(item.isolir_enabled || 0) === 1;
    document.getElementById('billingIsolirAt').value = item.isolir_time_input || '';
    cancelBillingEdit.classList.remove('hidden');
    syncBillingIsolirControls();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.markPaid = async function(id) {
    const form = new FormData();
    form.append('action', 'mark_paid');
    form.append('id', id);
    const res = await fetch('api/billing.php', { method: 'POST', body: form });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Gagal memperbarui pembayaran');
        return;
    }
    await loadBillings();
    alert(data.message);
};

window.applyIsolir = async function(id) {
    const form = new FormData();
    form.append('action', 'apply_isolir');
    form.append('id', id);

    const res = await fetch('api/billing.php', { method: 'POST', body: form });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Gagal menjalankan isolir');
        return;
    }

    await loadBillings();
    alert(data.message);
};

window.deleteBilling = async function(id) {
    if (!confirm('Hapus tagihan ini?')) return;
    const form = new FormData();
    form.append('action', 'delete');
    form.append('id', id);
    const res = await fetch('api/billing.php', { method: 'POST', body: form });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Gagal menghapus tagihan');
        return;
    }
    await loadBillings();
    if (Number(document.getElementById('billingId').value) === Number(id)) {
        resetBillingForm();
    }
};

async function saveBillingDueDay(e) {
    e.preventDefault();
    const msg = document.getElementById('billingDueDayMsg');
    msg.textContent = 'Menyimpan tanggal default...';
    msg.style.color = 'inherit';

    const form = new FormData();
    form.append('billing_due_day', document.getElementById('billingDueDayInput').value);

    try {
        const res = await fetch('api/settings.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) {
            msg.textContent = data.message || 'Gagal menyimpan tanggal jatuh tempo default';
            msg.style.color = '#dc2626';
            return;
        }

        billingDueDay = sanitizeDueDay(data.data?.billing_due_day || document.getElementById('billingDueDayInput').value);
        document.getElementById('billingDueDayInput').value = billingDueDay;
        if (window.receiptPrinter && typeof window.receiptPrinter.configure === 'function') {
            window.receiptPrinter.configure({ billingDueDay });
        }
        refreshMassDueDateDefault();
        if (!document.getElementById('billingId').value) {
            document.getElementById('billingDueDate').value = getDefaultDueDateValue();
        }
        msg.textContent = 'Tanggal jatuh tempo default berhasil disimpan.';
        msg.style.color = '#16a34a';
    } catch (error) {
        msg.textContent = 'Terjadi kesalahan sistem saat menyimpan tanggal default';
        msg.style.color = '#dc2626';
    }

    setTimeout(() => { msg.textContent = ''; }, 3000);
}

async function applyMassDueDate(e) {
    e.preventDefault();
    const msg = document.getElementById('billingMassDueDateMsg');
    msg.textContent = 'Menerapkan jatuh tempo serentak...';
    msg.style.color = 'inherit';

    const form = new FormData();
    form.append('action', 'apply_due_date');
    form.append('target_date', document.getElementById('billingMassDueDate').value);
    form.append('target_time', document.getElementById('billingMassDueTime').value);

    try {
        const res = await fetch('api/billing.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) {
            msg.textContent = data.message || 'Gagal menerapkan jatuh tempo serentak';
            msg.style.color = '#dc2626';
            return;
        }

        msg.textContent = data.message;
        msg.style.color = '#16a34a';
        await loadBillings();
    } catch (error) {
        msg.textContent = 'Terjadi kesalahan sistem saat menerapkan jatuh tempo serentak';
        msg.style.color = '#dc2626';
    }

    setTimeout(() => { msg.textContent = ''; }, 3000);
}

async function markAllUnpaid(e) {
    e.preventDefault();
    if (!confirm('Ubah semua tagihan menjadi belum bayar dan samakan tanggal jatuh temponya?')) {
        return;
    }

    const msg = document.getElementById('billingMarkAllUnpaidMsg');
    msg.textContent = 'Mengubah semua tagihan menjadi belum bayar...';
    msg.style.color = 'inherit';

    const form = new FormData();
    form.append('action', 'mark_all_unpaid');
    form.append('target_date', document.getElementById('billingMarkAllUnpaidDate').value);
    form.append('target_time', document.getElementById('billingMarkAllUnpaidTime').value);

    try {
        const res = await fetch('api/billing.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) {
            msg.textContent = data.message || 'Gagal menjalankan fitur belum bayar semua';
            msg.style.color = '#dc2626';
            return;
        }

        msg.textContent = data.message;
        msg.style.color = data.restore_failed_count > 0 ? '#ca8a04' : '#16a34a';
        await loadBillings();
    } catch (error) {
        msg.textContent = 'Terjadi kesalahan sistem saat menjalankan belum bayar semua';
        msg.style.color = '#dc2626';
    }

    setTimeout(() => { msg.textContent = ''; }, 4000);
}

async function loadBillings() {
    try {
        const res = await fetch('api/billing.php');
        const data = await res.json();
        if (!data.success) {
            document.getElementById('billingList').innerHTML = '<p style="color:#dc2626;">Gagal memuat tagihan.</p>';
            return;
        }

        allBillings = data.data || [];
        allUsers = data.users || [];
        populateUserOptions(data.users || []);
        setBillingStats(allBillings);
        renderBillingTable(allBillings);

        if ((data.auto_isolir?.processed || 0) > 0) {
            console.info('Timer isolir otomatis diproses:', data.auto_isolir);
        }
    } catch (error) {
        document.getElementById('billingList').innerHTML = '<p style="color:#dc2626;">Gagal memuat tagihan.</p>';
    }
}

async function autoRunDueIsolirSilently() {
    try {
        const form = new FormData();
        form.append('action', 'run_due_isolir');

        const res = await fetch('api/billing.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) {
            return;
        }

        if ((data.summary?.processed || 0) > 0) {
            await loadBillings();
        }
    } catch (error) {
    }
}

document.getElementById('billingSearch').addEventListener('input', function() {
    const keyword = this.value.trim().toLowerCase();
    if (!keyword) {
        renderBillingTable(allBillings);
        return;
    }

    const filtered = allBillings.filter(item =>
        [
            item.nama,
            item.username,
            item.phone,
            item.status,
            item.payment_status,
            item.isolir_status,
            item.isolir_error
        ]
            .filter(Boolean)
            .some(value => String(value).toLowerCase().includes(keyword))
    );
    renderBillingTable(filtered);
});

billingForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const msg = document.getElementById('billingMsg');
    msg.textContent = 'Menyimpan...';
    msg.style.color = 'inherit';

    try {
        const res = await fetch('api/billing.php', { method: 'POST', body: new FormData(this) });
        const data = await res.json();
        if (!data.success) {
            msg.textContent = data.message || 'Gagal menyimpan tagihan';
            msg.style.color = '#dc2626';
            return;
        }

        msg.textContent = data.message;
        msg.style.color = '#16a34a';
        resetBillingForm();
        await loadBillings();
    } catch (error) {
        msg.textContent = 'Terjadi kesalahan sistem';
        msg.style.color = '#dc2626';
    }

    setTimeout(() => { msg.textContent = ''; }, 3000);
});

document.getElementById('isolirSetupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    await saveIsolirSetup('save');
});

async function saveIsolirSetup(action) {
    const msg = document.getElementById('isolirSetupMsg');
    msg.textContent = action === 'save_and_create' ? 'Menyimpan setup dan membuat profile...' : 'Menyimpan setup isolir...';
    msg.style.color = 'inherit';

    const form = new FormData(document.getElementById('isolirSetupForm'));
    form.append('action', action);

    try {
        const res = await fetch('api/isolir.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) {
            msg.textContent = data.message || 'Gagal menyimpan setup isolir';
            msg.style.color = '#dc2626';
            if (data.setup) {
                renderIsolirSetupStatus(data.setup);
            }
            return;
        }

        msg.textContent = data.message;
        msg.style.color = '#16a34a';
        if (data.setup) {
            renderIsolirSetupStatus(data.setup);
        } else {
            await loadIsolirSetup();
        }
    } catch (error) {
        msg.textContent = 'Terjadi kesalahan sistem saat menyimpan setup isolir';
        msg.style.color = '#dc2626';
    }

    setTimeout(() => { msg.textContent = ''; }, 3000);
}

cancelBillingEdit.addEventListener('click', resetBillingForm);
billingIsolirEnabled.addEventListener('change', syncBillingIsolirControls);
paymentStatusInput.addEventListener('change', syncBillingIsolirControls);
document.getElementById('createIsolirProfileButton').addEventListener('click', () => saveIsolirSetup('save_and_create'));
document.getElementById('printReceiptsButton').addEventListener('click', async () => {
    if (!window.receiptPrinter || typeof window.receiptPrinter.printReceipts !== 'function') {
        alert('Fitur print nota belum siap. Muat ulang halaman lalu coba lagi.');
        return;
    }

    try {
        const res = await fetch(`api/billing.php?_=${Date.now()}`, { cache: 'no-store' });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Gagal memuat data tagihan terbaru.');
            return;
        }

        const latestBillings = data.data || [];
        const latestUsers = data.users || [];
        window.receiptPrinter.printReceipts(latestUsers, latestBillings, { variant: 'payments' });
    } catch (error) {
        alert('Gagal memuat data tagihan terbaru.');
    }
});
document.getElementById('billingDueDayForm').addEventListener('submit', saveBillingDueDay);
document.getElementById('billingMassDueDateForm').addEventListener('submit', applyMassDueDate);
document.getElementById('billingMarkAllUnpaidForm').addEventListener('submit', markAllUnpaid);

const compactSidebarMedia = window.matchMedia('(max-width: 1024px)');

function syncSidebarState() {
    const isOpen = document.getElementById('appSidebar').classList.contains('!left-0');
    document.querySelectorAll('.sidebar-toggle').forEach(button => {
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    if (!compactSidebarMedia.matches && isOpen) {
        document.getElementById('appSidebar').classList.remove('!left-0');
        document.getElementById('sidebarOverlay').classList.add('hidden');
        document.querySelectorAll('.sidebar-toggle').forEach(button => {
            button.setAttribute('aria-expanded', 'false');
        });
    }
}

function toggleSidebar() { document.getElementById('appSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }
function closeSidebar() { document.getElementById('appSidebar').classList.add('-translate-x-full'); document.getElementById('sidebarOverlay').classList.add('hidden'); }
document.querySelectorAll('#appSidebar a').forEach(l => l.addEventListener('click', () => { if (window.innerWidth < 768) closeSidebar(); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;

async function logout() {
    if (autoIsolirTimer) { clearInterval(autoIsolirTimer); autoIsolirTimer = null; }
    await fetch('api/auth.php?action=logout');
    location.href = '<?= htmlspecialchars(app_url('login'), ENT_QUOTES, 'UTF-8') ?>';
}

window.onload = async function() {
    resetBillingForm();
    refreshMassDueDateDefault();
    loadBillings();
    window.setTimeout(loadIsolirSetup, 200);
    autoIsolirTimer = window.setInterval(autoRunDueIsolirSilently, 15000);
};
</script>
<?= app_render_tenant_expiry_script('login.php') ?>
</body>
</html>

