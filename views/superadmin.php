<?php
require_once __DIR__ . '/../config/auth.php';
$account = app_require_superadmin_page();
$appName = env_value('APP_NAME', 'Nikonet');
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Superadmin - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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
:root { --success: #10b981; --danger: #ef4444; --text-muted: #737686; }
body { font-family: 'Inter', sans-serif; }
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
.badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: white; display: inline-flex; align-items: center; justify-content: center; }
.badge-blue { background: #004ac6; }
.badge-green { background: #10b981; }
.badge-red { background: #ba1a1a; }
.badge-yellow { background: #f59e0b; }
.badge-gray { background: #94a3b8; }
.muted { color: #737686; }
.hidden { display: none; }
.btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem 0.8rem; font-size: 0.82rem; font-weight: 700; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; border: none; font-family: inherit; text-decoration: none; }
.btn-small { padding: 0.5rem 0.8rem; font-size: 0.82rem; }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #b91c1c; }
.table-actions { display: flex; gap: 0.5rem; }
table th, table td { padding: 0.75rem 1rem; }
</style>
</head>
<body class="bg-surface text-on-surface">

<aside id="appSidebar" class="fixed left-0 top-0 bottom-0 z-40 w-[270px] bg-surface-container-lowest border-r border-outline-variant flex flex-col transition-transform duration-300 -translate-x-full lg:translate-x-0">
    <div class="px-5 pt-6 pb-4 border-b border-outline-variant">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shadow-md shadow-primary/20">
                <span class="material-symbols-outlined text-white text-[22px]">admin_panel_settings</span>
            </div>
            <div>
                <h1 class="text-lg font-black text-primary"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-[11px] text-on-surface-variant">Superadmin Panel</p>
            </div>
        </div>
    </div>
    <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">
        <div class="px-4 py-2 mt-1 mb-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-outline">Panel Superadmin</span>
        </div>
        <a href="#tenantStatsSection" class="flex items-center gap-3 px-4 py-2.5 bg-primary-fixed text-primary rounded-xl font-bold text-sm transition-all">
            <span class="material-symbols-outlined text-[22px]">dashboard</span> Ringkasan
        </a>
        <a href="#tenantFormSection" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">person_add</span> Tambah Akun Customer
        </a>
        <a href="#tenantListSection" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">list_alt</span> Daftar Tenant
        </a>
        <div class="px-4 py-2 mt-3 mb-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-outline">Navigasi</span>
        </div>
        <a href="dashboard" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">dashboard</span> Dashboard
        </a>
        <a href="users" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">group</span> Manajemen User
        </a>
        <a href="payments" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">payments</span> Pembayaran
        </a>
        <a href="pppoe" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">settings_ethernet</span> PPPoE Client
        </a>
        <a href="hotspot" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">wifi</span> Hotspot Voucher
        </a>
        <a href="infra" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">lan</span> Infrastruktur
        </a>
        <a href="settings" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">settings</span> Settings
        </a>
    </nav>
    <div class="p-4 border-t border-outline-variant">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-primary text-on-primary flex items-center justify-center font-black text-sm shadow-sm">
                <?= strtoupper(substr($account['username'] ?? $appName, 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-on-surface truncate"><?= htmlspecialchars($account['username'] ?? 'Superadmin', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-on-surface-variant truncate">Superadmin</p>
            </div>
            <button onclick="logout()" class="w-9 h-9 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-error-container hover:text-error transition-all" title="Logout">
                <span class="material-symbols-outlined text-[20px]">logout</span>
            </button>
        </div>
    </div>
</aside>
<div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-30 hidden" onclick="closeSidebar()"></div>

<!-- Main Content -->
<main class="lg:ml-[270px] min-h-screen transition-all">
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-outline-variant h-16 flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button type="button" class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center hover:bg-surface-container transition-all" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-on-surface-variant">menu</span>
            </button>
            <h1 class="text-lg font-black text-on-surface">Panel Superadmin Layanan</h1>
        </div>
    </header>

    <div class="p-6 max-w-[1600px] mx-auto">
        <!-- Info Note -->
        <div class="mb-6 p-4 bg-gradient-to-r from-cyan-50 to-orange-50 border border-cyan-200 rounded-xl text-sm text-on-surface-variant leading-relaxed">
            Panel ini khusus untuk Anda sebagai penyedia jasa. Di sini Anda cukup mengelola tenant/customer dan akun dashboard mereka, sedangkan monitoring RT/RW Net tetap dipakai masing-masing tenant pada panel mereka sendiri.
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6" id="tenantStatsSection">
            <div class="bg-gradient-to-br from-slate-100 to-slate-50 border-l-4 border-slate-500 rounded-xl shadow-sm p-6 text-center">
                <div class="text-sm text-secondary mb-1 font-semibold">Total Tenant</div>
                <div class="text-3xl font-bold text-on-surface" id="totalTenantsStat">0</div>
                <div class="text-xs text-secondary mt-2">Semua customer yang terdaftar</div>
            </div>
            <div class="bg-gradient-to-br from-slate-100 to-slate-50 border-l-4 border-slate-500 rounded-xl shadow-sm p-6 text-center">
                <div class="text-sm text-secondary mb-1 font-semibold">Tenant Aktif</div>
                <div class="text-3xl font-bold text-on-surface" id="activeTenantsStat">0</div>
                <div class="text-xs text-secondary mt-2">Siap login dan monitoring</div>
            </div>
            <div class="bg-gradient-to-br from-slate-100 to-slate-50 border-l-4 border-slate-500 rounded-xl shadow-sm p-6 text-center">
                <div class="text-sm text-secondary mb-1 font-semibold">Akun Dashboard</div>
                <div class="text-3xl font-bold text-on-surface" id="totalAccountsStat">0</div>
                <div class="text-xs text-secondary mt-2">Jumlah akun customer non-superadmin</div>
            </div>
        </div>

        <!-- Form & List -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Form Card -->
            <div class="bg-gradient-to-br from-slate-100 to-slate-50 rounded-xl shadow-sm p-6" id="tenantFormSection">
                <h3 class="text-base font-bold text-on-surface mb-5 flex items-center justify-between">
                    <span id="tenantFormTitle">Tambah Tenant & Akun Customer</span>
                </h3>
                <form id="tenantForm">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="tenant_id" id="tenantIdField" value="">
                    <input type="hidden" name="account_id" id="accountIdField" value="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Nama Tenant / Brand</label>
                            <input type="text" name="tenant_name" id="tenantNameField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Contoh: RT RW Net Sukamaju" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Slug Tenant</label>
                            <input type="text" name="slug" id="tenantSlugField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Opsional, otomatis jika kosong">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Nama Owner / PIC</label>
                            <input type="text" name="owner_name" id="ownerNameField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Nama penanggung jawab">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Nomor Telepon</label>
                            <input type="text" name="phone" id="tenantPhoneField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Contoh: 081234567890">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Expired Akun Tenant</label>
                            <input type="datetime-local" name="expired_at" id="tenantExpiredAtField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <p class="mt-1 text-xs text-on-surface-variant">Kosongkan jika akun tenant tidak punya batas waktu. Jika diisi, login tenant akan otomatis ditolak setelah tanggal dan jam ini.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Username Login Dashboard</label>
                            <input type="text" name="username" id="tenantUsernameField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Username customer" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Password Dashboard</label>
                            <div class="flex gap-2">
                                <input type="password" name="password" id="tenantPasswordField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Wajib saat tambah, opsional saat edit">
                                <button type="button" class="px-4 py-2.5 bg-secondary text-on-secondary rounded-xl text-sm font-semibold whitespace-nowrap hover:opacity-90 transition-all" id="toggleTenantPasswordButton">Lihat Password</button>
                            </div>
                            <p class="mt-1 text-xs text-on-surface-variant" id="tenantPasswordHint">Saat edit, password lama tidak bisa ditampilkan lagi karena tersimpan aman dalam bentuk hash. Isi field ini hanya jika ingin ganti password.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Konfirmasi Password</label>
                            <input type="password" id="tenantPasswordConfirmField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Ulangi password untuk cek kecocokan">
                            <p class="mt-1 text-xs text-on-surface-variant" id="tenantPasswordConfirmHint">Ulangi password yang sama untuk memastikan tidak ada salah ketik.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Nama Akun Dashboard</label>
                            <input type="text" name="account_name" id="accountNameField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Nama user dashboard" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Role Dashboard</label>
                            <select name="role" id="accountRoleField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" required>
                                <option value="owner">Owner</option>
                                <option value="admin">Admin</option>
                                <option value="operator">Operator</option>
                                <option value="viewer">Viewer</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-semibold text-on-surface-variant mb-1.5">Status Tenant</label>
                            <select name="status" id="tenantStatusField" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" required>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                                <option value="pending">Pending Konfirmasi</option>
                            </select>
                            <p class="mt-1 text-xs text-on-surface-variant">Jika nonaktif, akun tenant akan ikut dinonaktifkan agar tidak bisa login.</p>
                        </div>
                        <div></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-6">
                        <button type="submit" class="w-full px-4 py-3 bg-primary text-on-primary rounded-xl font-bold shadow-sm hover:opacity-90 transition-all text-sm" id="tenantSubmitButton">Simpan Tenant</button>
                        <button type="button" class="w-full px-4 py-3 bg-secondary text-on-secondary rounded-xl font-bold hover:opacity-90 transition-all text-sm hidden" id="cancelTenantEditButton">Batal Edit</button>
                    </div>
                    <div id="tenantFormMsg" style="margin-top:10px; font-size:0.9rem; font-weight:500;"></div>
                </form>
            </div>

            <!-- List Side -->
            <div class="flex flex-col gap-6">
                <div class="bg-gradient-to-br from-slate-100 to-slate-50 rounded-xl shadow-sm p-6">
                    <h3 class="text-base font-bold text-on-surface mb-4">Pencarian Tenant</h3>
                    <input type="text" id="tenantSearch" class="w-full px-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Cari nama tenant, owner, username, role, atau nomor telepon">
                </div>

                <div class="bg-gradient-to-br from-slate-100 to-slate-50 rounded-xl shadow-sm p-6" id="tenantListSection">
                    <h3 class="text-base font-bold text-on-surface mb-4">Daftar Tenant Customer</h3>
                    <div id="tenantList" class="overflow-x-auto">Memuat data tenant...</div>
                </div>
            </div>
        </div>
    </div>
</main>

    <script>
    let tenantRows = [];
    const tenantForm = document.getElementById('tenantForm');
    const cancelTenantEditButton = document.getElementById('cancelTenantEditButton');
    const tenantPasswordField = document.getElementById('tenantPasswordField');
    const tenantPasswordConfirmField = document.getElementById('tenantPasswordConfirmField');
    const toggleTenantPasswordButton = document.getElementById('toggleTenantPasswordButton');
    const tenantPasswordHint = document.getElementById('tenantPasswordHint');
    const tenantPasswordConfirmHint = document.getElementById('tenantPasswordConfirmHint');
    const tenantExpiredAtField = document.getElementById('tenantExpiredAtField');

    function formatDateTimeLocal(value) {
        if (!value) return '';
        return String(value).replace(' ', 'T').slice(0, 16);
    }

    function getExpiryBadge(row) {
        if (!row.expired_at) {
            return '<span class="badge badge-blue">TANPA BATAS</span>';
        }

        const expiredTime = new Date(String(row.expired_at).replace(' ', 'T'));
        if (!Number.isNaN(expiredTime.getTime()) && expiredTime.getTime() <= Date.now()) {
            return '<span class="badge badge-red">EXPIRED</span>';
        }

        return '<span class="badge badge-yellow">AKTIF SAMPAI BATAS</span>';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, '&#039;');
    }

    function setTenantFormMessage(message, tone = 'muted') {
        const target = document.getElementById('tenantFormMsg');
        const toneMap = { muted: 'inherit', success: 'var(--success)', danger: 'var(--danger)' };
        target.textContent = message;
        target.style.color = toneMap[tone] || toneMap.muted;
    }

    function resetTenantForm() {
        tenantForm.reset();
        document.getElementById('tenantFormTitle').textContent = 'Tambah Tenant & Akun Customer';
        document.getElementById('tenantIdField').value = '';
        document.getElementById('accountIdField').value = '';
        document.getElementById('tenantSubmitButton').textContent = 'Simpan Tenant';
        tenantPasswordField.type = 'password';
        tenantPasswordField.placeholder = 'Wajib saat tambah, opsional saat edit';
        tenantPasswordConfirmField.type = 'password';
        tenantPasswordConfirmField.placeholder = 'Ulangi password untuk cek kecocokan';
        toggleTenantPasswordButton.textContent = 'Lihat Password';
        tenantPasswordHint.textContent = 'Saat edit, password lama tidak bisa ditampilkan lagi karena tersimpan aman dalam bentuk hash. Isi field ini hanya jika ingin ganti password.';
        tenantPasswordConfirmHint.textContent = 'Ulangi password yang sama untuk memastikan tidak ada salah ketik.';
        tenantPasswordConfirmHint.style.color = 'var(--text-muted)';
        tenantExpiredAtField.value = '';
        document.getElementById('tenantStatusField').value = 'active';
        document.getElementById('accountRoleField').value = 'owner';
        cancelTenantEditButton.classList.add('hidden');
    }

    function syncTenantPasswordHint() {
        const value = tenantPasswordField.value || '';
        const isEditMode = document.getElementById('tenantIdField').value !== '';

        if (!value) {
            tenantPasswordHint.textContent = isEditMode
                ? 'Password lama tidak bisa dicek ulang dari server karena tersimpan hash. Isi field ini hanya jika ingin reset password tenant.'
                : 'Isi password untuk akun dashboard tenant. Anda bisa klik tombol lihat password untuk cek sebelum simpan.';
            return;
        }

        tenantPasswordHint.textContent = `Password siap disimpan. Panjang saat ini ${value.length} karakter.`;
    }

    function validateTenantPasswords(showMessage = true) {
        const password = tenantPasswordField.value || '';
        const confirmPassword = tenantPasswordConfirmField.value || '';
        const isEditMode = document.getElementById('tenantIdField').value !== '';

        if (!password && !confirmPassword) {
            tenantPasswordConfirmHint.textContent = isEditMode
                ? 'Kosongkan kedua field jika password tenant tidak ingin diubah.'
                : 'Ulangi password yang sama untuk memastikan tidak ada salah ketik.';
            tenantPasswordConfirmHint.style.color = 'var(--text-muted)';
            return true;
        }

        if (!password || !confirmPassword) {
            if (showMessage) {
                tenantPasswordConfirmHint.textContent = 'Password dan konfirmasi password harus diisi bersamaan.';
                tenantPasswordConfirmHint.style.color = 'var(--danger)';
            }
            return false;
        }

        if (password !== confirmPassword) {
            if (showMessage) {
                tenantPasswordConfirmHint.textContent = 'Konfirmasi password belum cocok.';
                tenantPasswordConfirmHint.style.color = 'var(--danger)';
            }
            return false;
        }

        tenantPasswordConfirmHint.textContent = 'Password cocok dan siap disimpan.';
        tenantPasswordConfirmHint.style.color = 'var(--success)';
        return true;
    }

    function setSummary(summary) {
        document.getElementById('totalTenantsStat').textContent = Number(summary?.total_tenants || 0);
        document.getElementById('activeTenantsStat').textContent = Number(summary?.active_tenants || 0);
        document.getElementById('totalAccountsStat').textContent = Number(summary?.total_accounts || 0);
    }

    function renderTenantList(rows) {
        const container = document.getElementById('tenantList');
        if (!rows.length) {
            container.innerHTML = '<p class="muted">Belum ada tenant customer.</p>';
            return;
        }

        let html = '<table><thead><tr><th>Tenant</th><th>Akun Dashboard</th><th>Data Tenant</th><th>Aksi</th></tr></thead><tbody>';
        rows.forEach(row => {
            const statusBadge = row.status === "active" ? "<span class=\"badge badge-green\">AKTIF</span>" : (row.status === "pending" ? "<span class=\"badge badge-yellow\">PENDING</span>" : "<span class=\"badge badge-gray\">NONAKTIF</span>");
            const roleBadgeClass = row.role === 'owner' ? 'badge-blue' : (row.role === 'admin' ? 'badge-green' : 'badge-yellow');
            const roleBadge = row.role ? `<span class="badge ${roleBadgeClass}">${escapeHtml(String(row.role).toUpperCase())}</span>` : '<span class="muted">-</span>';
            const lastLogin = row.last_login_at ? escapeHtml(row.last_login_at) : '<span class="muted">Belum login</span>';
            const expiryBadge = getExpiryBadge(row);
            const expiryText = row.expired_at ? escapeHtml(String(row.expired_at)) : '-';

            html += `<tr>
                <td>
                    <strong>${escapeHtml(row.name)}</strong><br>
                    <span class="muted">Slug: ${escapeHtml(row.slug || '-')}</span><br>
                    ${statusBadge}
                </td>
                <td>
                    <strong>${escapeHtml(row.account_name || '-')}</strong><br>
                    <span class="muted">${escapeHtml(row.username || '-')}</span><br>
                    ${roleBadge}<br>
                    <span class="muted">Login terakhir: ${lastLogin}</span>
                </td>
                <td>
                    <span class="muted">PIC:</span> ${escapeHtml(row.owner_name || '-')}<br>
                    <span class="muted">Telepon:</span> ${escapeHtml(row.phone || '-')}<br>
                    <span class="muted">Expired:</span> ${expiryText}<br>
                    ${expiryBadge}<br>
                    <span class="muted">Akun:</span> ${Number(row.admin_count || 0)}<br>
                    <span class="muted">Pelanggan:</span> ${Number(row.customer_count || 0)}<br>
                    <span class="muted">Infra:</span> ${Number(row.infra_count || 0)}
                </td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="btn btn-small edit-tenant-btn" data-id="${row.id}">Edit</button>
                        <button type="button" class="btn btn-danger btn-small delete-tenant-btn" data-id="${row.id}" data-name="${escapeHtml(row.name)}">Hapus</button>
                    </div>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;

        container.querySelectorAll('.edit-tenant-btn').forEach(button => {
            button.onclick = () => startEditTenant(Number(button.dataset.id || 0));
        });

        container.querySelectorAll('.delete-tenant-btn').forEach(button => {
            button.onclick = () => deleteTenant(Number(button.dataset.id || 0), button.dataset.name || 'tenant');
        });
    }

    async function loadTenants() {
        try {
            const res = await fetch('api/tenants.php');
            const data = await res.json();
            if (!data.success) {
                document.getElementById('tenantList').innerHTML = '<p style="color:var(--danger);">Gagal memuat data tenant.</p>';
                return;
            }

            tenantRows = data.data || [];
            setSummary(data.summary || {});
            renderTenantList(tenantRows);
        } catch (error) {
            document.getElementById('tenantList').innerHTML = '<p style="color:var(--danger);">Gagal memuat data tenant.</p>';
        }
    }

    function startEditTenant(id) {
        const row = tenantRows.find(item => Number(item.id) === Number(id));
        if (!row) return;

        document.getElementById('tenantFormTitle').textContent = `Edit Tenant: ${row.name}`;
        document.getElementById('tenantIdField').value = row.id || '';
        document.getElementById('accountIdField').value = row.account_id || '';
        document.getElementById('tenantNameField').value = row.name || '';
        document.getElementById('tenantSlugField').value = row.slug || '';
        document.getElementById('ownerNameField').value = row.owner_name || '';
        document.getElementById('tenantPhoneField').value = row.phone || '';
        tenantExpiredAtField.value = formatDateTimeLocal(row.expired_at || '');
        document.getElementById('tenantUsernameField').value = row.username || '';
        tenantPasswordField.value = '';
        tenantPasswordField.type = 'password';
        tenantPasswordField.placeholder = 'Kosongkan jika password tidak diubah';
        tenantPasswordConfirmField.value = '';
        tenantPasswordConfirmField.type = 'password';
        tenantPasswordConfirmField.placeholder = 'Ulangi password baru untuk cek kecocokan';
        toggleTenantPasswordButton.textContent = 'Lihat Password';
        document.getElementById('accountNameField').value = row.account_name || '';
        document.getElementById('accountRoleField').value = row.role || 'owner';
        document.getElementById('tenantStatusField').value = row.status || 'active';
        document.getElementById('tenantSubmitButton').textContent = 'Update Tenant';
        cancelTenantEditButton.classList.remove('hidden');
        syncTenantPasswordHint();
        validateTenantPasswords(false);
        tenantForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function deleteTenant(id, name) {
        if (!id) return;
        if (!confirm(`Hapus tenant ${name}? Semua akun dashboard, pelanggan, billing, dan infrastruktur tenant ini juga ikut terhapus.`)) {
            return;
        }

        const form = new FormData();
        form.append('action', 'delete');
        form.append('tenant_id', id);

        try {
            const res = await fetch('api/tenants.php', { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Gagal menghapus tenant');
                return;
            }

            if (Number(document.getElementById('tenantIdField').value) === Number(id)) {
                resetTenantForm();
            }
            await loadTenants();
        } catch (error) {
            alert('Terjadi kesalahan sistem saat menghapus tenant');
        }
    }

    tenantForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!validateTenantPasswords(true)) {
            setTenantFormMessage('Cek ulang password dan konfirmasi password dulu.', 'danger');
            return;
        }

        const submitButton = document.getElementById('tenantSubmitButton');
        setTenantFormMessage('Menyimpan data tenant...', 'muted');
        submitButton.disabled = true;

        try {
            const res = await fetch('api/tenants.php', { method: 'POST', body: new FormData(this) });
            const data = await res.json();
            if (!data.success) {
                setTenantFormMessage(data.message || 'Gagal menyimpan tenant', 'danger');
                return;
            }

            setTenantFormMessage(data.message, 'success');
            resetTenantForm();
            await loadTenants();
        } catch (error) {
            setTenantFormMessage('Terjadi kesalahan sistem saat menyimpan tenant', 'danger');
        } finally {
            submitButton.disabled = false;
        }

        setTimeout(() => { document.getElementById('tenantFormMsg').textContent = ''; }, 3000);
    });

    document.getElementById('tenantSearch').addEventListener('input', function() {
        const keyword = this.value.trim().toLowerCase();
        if (!keyword) {
            renderTenantList(tenantRows);
            return;
        }

        const filtered = tenantRows.filter(row =>
            [row.name, row.owner_name, row.username, row.account_name, row.role, row.phone, row.slug]
                .filter(Boolean)
                .some(value => String(value).toLowerCase().includes(keyword))
        );
        renderTenantList(filtered);
    });

    toggleTenantPasswordButton.addEventListener('click', function() {
        const isHidden = tenantPasswordField.type === 'password';
        tenantPasswordField.type = isHidden ? 'text' : 'password';
        tenantPasswordConfirmField.type = isHidden ? 'text' : 'password';
        this.textContent = isHidden ? 'Sembunyikan' : 'Lihat Password';
        tenantPasswordField.focus();
    });

    tenantPasswordField.addEventListener('input', syncTenantPasswordHint);
    tenantPasswordField.addEventListener('input', () => validateTenantPasswords(false));
    tenantPasswordConfirmField.addEventListener('input', () => validateTenantPasswords(true));
    cancelTenantEditButton.addEventListener('click', resetTenantForm);

    function toggleSidebar() { document.getElementById('appSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }
    function closeSidebar() { document.getElementById('appSidebar').classList.add('-translate-x-full'); document.getElementById('sidebarOverlay').classList.add('hidden'); }
    document.querySelectorAll('#appSidebar a').forEach(l => l.addEventListener('click', () => { if (window.innerWidth < 768) closeSidebar(); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
    window.toggleSidebar = toggleSidebar;
    window.closeSidebar = closeSidebar;

    async function logout() { await fetch('api/auth.php?action=logout'); location.href = '<?= htmlspecialchars(app_url('login'), ENT_QUOTES, 'UTF-8') ?>'; }

    window.onload = async function() {
        resetTenantForm();
        await loadTenants();
    };
    </script>
</body>
</html>

