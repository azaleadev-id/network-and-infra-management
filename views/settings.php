<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';
$account = app_require_tenant_panel_page();
$appName = app_setting_get('app_name', 'Nikonet');
$mikrotikHost = app_setting_get('mikrotik_host', env_value('MIKROTIK_HOST', ''));
$mikrotikUser = app_setting_get('mikrotik_user', env_value('MIKROTIK_USER', ''));
$mikrotikTransport = app_setting_get('mikrotik_transport', env_value('MIKROTIK_TRANSPORT', 'socket'));
$trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'));
$mikrotikBridgeApiKey = app_mikrotik_bridge_api_key_get((int) $account['tenant_id'], true);
$mikrotikBackupEnabled = app_setting_get('mikrotik_backup_enabled', 'false');
$mikrotikBackupInterval = (int) app_setting_get('mikrotik_backup_interval_days', 7);
$mikrotikBackupTime = trim((string) app_setting_get('mikrotik_backup_time', '02:00'));
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $mikrotikBackupTime)) {
    $mikrotikBackupTime = '02:00';
}
$mikrotikBackupRetention = (int) app_setting_get('mikrotik_backup_retention_days', 30);
if ($mikrotikBackupRetention < 1) {
    $mikrotikBackupRetention = 30;
}
$mikrotikBackupLastAt = trim((string) app_setting_get('mikrotik_backup_last_at', ''));
$mikrotikBackupLastName = trim((string) app_setting_get('mikrotik_backup_last_name', ''));
$mikrotikBackupChecked = in_array(strtolower((string) $mikrotikBackupEnabled), ['1', 'true', 'yes', 'on'], true);
$mikrotikBackupStatus = 'Belum ada backup yang tercatat.';
if ($mikrotikBackupLastAt !== '') {
    $mikrotikBackupStatus = 'Terakhir: ' . $mikrotikBackupLastAt;
    if ($mikrotikBackupLastName !== '') {
        $mikrotikBackupStatus .= ' (' . $mikrotikBackupLastName . ')';
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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

<?php $activePage = 'settings'; require_once __DIR__ . '/partials/dashboard_sidebar.php'; ?>

<!-- Main Content -->
<main class="lg:ml-[270px] min-h-screen transition-all">
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-outline-variant h-16 flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button type="button" class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center hover:bg-surface-container transition-all" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-on-surface-variant">menu</span>
            </button>
            <h2 class="text-lg font-black text-on-surface">Settings</h2>
        </div>
    </header>

    <div class="p-6 max-w-[1600px] mx-auto">
        <?= app_render_tenant_expiry_banner() ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-gradient-to-br from-gray-100 to-gray-50 border-l-4 border-gray-500 rounded-xl p-6 shadow-sm" id="appSettingsSection">
                <h3 class="text-lg font-bold text-on-surface mb-4">Nama Aplikasi</h3>
                <form id="settingsForm">
                    <div class="mb-4">
                        <label for="appNameInput" class="block text-sm font-medium text-on-surface-variant mb-1.5">Nama App</label>
                        <input type="text" id="appNameInput" name="app_name" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>" required>
                        <p class="text-xs text-on-surface-variant mt-1.5">Nama ini akan tampil di login, sidebar, judul halaman, dan hasil print.</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface-variant mb-1.5">Backup MikroTik Otomatis</label>
                        <div class="flex flex-wrap items-end gap-4">
                            <input type="hidden" name="mikrotik_backup_enabled" value="0">
                            <label class="flex items-center gap-2 text-sm text-on-surface">
                                <input type="checkbox" id="mikrotikBackupEnabled" name="mikrotik_backup_enabled" value="1" <?= $mikrotikBackupChecked ? 'checked' : '' ?> class="rounded border-outline-variant text-primary focus:ring-primary">
                                Aktifkan
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                <span class="text-xs text-on-surface-variant">Backup setiap</span>
                                <input type="number" id="mikrotikBackupIntervalInput" name="mikrotik_backup_interval_days" class="w-full px-3 py-2 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= (int) $mikrotikBackupInterval ?>" min="1" max="365" style="max-width:180px;" placeholder="Berapa hari sekali">
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                <span class="text-xs text-on-surface-variant">Jam backup</span>
                                <input type="time" id="mikrotikBackupTimeInput" name="mikrotik_backup_time" class="w-full px-3 py-2 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= htmlspecialchars($mikrotikBackupTime, ENT_QUOTES, 'UTF-8') ?>" style="max-width:160px;" title="Jam backup otomatis">
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                <span class="text-xs text-on-surface-variant">Hapus backup lama</span>
                                <input type="number" id="mikrotikBackupRetentionInput" name="mikrotik_backup_retention_days" class="w-full px-3 py-2 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= (int) $mikrotikBackupRetention ?>" min="1" max="3650" style="max-width:190px;" placeholder="Hapus setelah berapa hari">
                            </label>
                        </div>
                        <p class="text-xs text-on-surface-variant mt-1.5" id="mikrotikBackupLastLabel"><?= htmlspecialchars($mikrotikBackupStatus, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-on-surface-variant mt-1">Backup otomatis diproses via cron server. File backup tersimpan di storage MikroTik.</p>
                    </div>
                    <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Simpan Nama App</button>
                    <div id="settingsMsg" class="mt-2 text-sm font-medium"></div>
                </form>
                <button type="button" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all mt-3" id="runMikrotikBackupBtn">Backup Sekarang</button>
                <div id="mikrotikBackupMsg" class="mt-2 text-sm font-medium"></div>
            </div>

            <div class="bg-gradient-to-br from-gray-100 to-gray-50 border-l-4 border-gray-500 rounded-xl p-6 shadow-sm" id="mikrotikSettingsSection">
                <h3 class="text-lg font-bold text-on-surface mb-4">Info Koneksi MikroTik</h3>
                <form id="mikrotikSettingsForm">
                    <div class="mb-4">
                        <label for="mikrotikHostInput" class="block text-sm font-medium text-on-surface-variant mb-1.5">Host MikroTik</label>
                        <input type="text" id="mikrotikHostInput" name="mikrotik_host" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= htmlspecialchars($mikrotikHost, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: 192.168.88.1" required>
                        <p class="text-xs text-on-surface-variant mt-1.5">Gunakan url remote dari penyedia remote vpn anda.</p>
                    </div>
                    <div class="mb-4">
                        <label for="mikrotikUserInput" class="block text-sm font-medium text-on-surface-variant mb-1.5">Username MikroTik</label>
                        <input type="text" id="mikrotikUserInput" name="mikrotik_user" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= htmlspecialchars($mikrotikUser, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: admin" required>
                    </div>
                    <div class="mb-4">
                        <label for="mikrotikPassInput" class="block text-sm font-medium text-on-surface-variant mb-1.5">Password MikroTik</label>
                        <input type="password" id="mikrotikPassInput" name="mikrotik_pass" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Kosongkan jika tidak ingin mengubah password tersimpan">
                        <p class="text-xs text-on-surface-variant mt-1.5">Password lama tetap dipakai kalau field ini dikosongkan saat update.</p>
                    </div>
                    <div class="mb-4">
                        <label for="mikrotikTransport" class="block text-sm font-medium text-on-surface-variant mb-1.5">Metode Koneksi</label>
                        <select name="mikrotik_transport" id="mikrotikTransport" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="socket" <?= $mikrotikTransport === 'socket' ? 'selected' : '' ?>>Socket API (Port 8728)</option>
                            <option value="rest" <?= $mikrotikTransport === 'rest' ? 'selected' : '' ?>>REST API (HTTPS - Port 443)</option>
                        </select>
                        <p class="text-xs text-on-surface-variant mt-1.5">Pilih REST API jika Anda menggunakan koneksi remote HTTPS.</p>
                    </div>
                    <div class="mb-4">
                        <label for="trafficInterfaceInput" class="block text-sm font-medium text-on-surface-variant mb-1.5">Interface Traffic</label>
                        <input type="text" id="trafficInterfaceInput" name="traffic_interface" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" value="<?= htmlspecialchars($trafficInterface, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: ether1">
                        <p class="text-xs text-on-surface-variant mt-1.5">Dipakai untuk statistik traffic dashboard dan cache monitoring.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <button type="button" class="px-6 py-2.5 border border-outline-variant text-secondary text-sm font-semibold rounded-lg hover:bg-surface-container-high transition-all" id="testMikrotikConnBtn">Cek Koneksi</button>
                        <button type="submit" class="px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Simpan Koneksi</button>
                    </div>
                    <div id="mikrotikSettingsMsg" class="mt-2 text-sm font-medium"></div>
                </form>
                <div class="mt-6 space-y-3">
                    <div class="mb-4">
                        <label for="mikrotikBridgeApiKeyInput" class="block text-sm font-medium text-on-surface-variant mb-1.5">API Key Bridge MikroTik</label>
                        <input type="hidden" id="mikrotikBridgeApiKeyInput" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm" value="<?= htmlspecialchars($mikrotikBridgeApiKey, ENT_QUOTES, 'UTF-8') ?>" readonly>
                        <p class="text-xs text-on-surface-variant mt-1.5">jika belum terkoneksi silahkan generate ulang api key nya.</p>
                    </div>
                    <button type="button" class="w-full px-6 py-2.5 border border-outline-variant text-secondary text-sm font-semibold rounded-lg hover:bg-surface-container-high transition-all" id="regenerateBridgeKeyBtn">Generate Ulang API Key</button>
                    <div id="bridgeKeyMsg" class="text-sm font-medium"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('settingsForm').onsubmit = async function(e) {
    e.preventDefault();
    const form = new FormData(this);
    const msgDiv = document.getElementById('settingsMsg');
    msgDiv.textContent = 'Menyimpan...';
    msgDiv.style.color = 'inherit';

    try {
        const res = await fetch('api/settings.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            const nextAppName = data.data.app_name;
            document.getElementById('appNameLabel').textContent = nextAppName;
            document.title = `Settings - ${nextAppName}`;
            msgDiv.textContent = data.message;
            msgDiv.style.color = 'var(--success)';
        } else {
            msgDiv.textContent = data.message || 'Gagal menyimpan nama app';
            msgDiv.style.color = 'var(--danger)';
        }
    } catch (error) {
        msgDiv.textContent = 'Terjadi kesalahan sistem';
        msgDiv.style.color = 'var(--danger)';
    }

    setTimeout(() => { msgDiv.textContent = ''; }, 3000);
};

document.getElementById('mikrotikSettingsForm').onsubmit = async function(e) {
    e.preventDefault();
    const form = new FormData(this);
    const msgDiv = document.getElementById('mikrotikSettingsMsg');
    msgDiv.textContent = 'Menyimpan koneksi MikroTik...';
    msgDiv.style.color = 'inherit';

    try {
        const setupRes = await fetch('api/setup.php', { method: 'POST', body: form });
        const setupData = await setupRes.json();
        if (!setupData.success) {
            msgDiv.textContent = setupData.message || 'Gagal menyimpan koneksi MikroTik';
            msgDiv.style.color = 'var(--danger)';
            return;
        }

        const interfaceForm = new FormData();
        interfaceForm.append('traffic_interface', document.getElementById('trafficInterfaceInput').value.trim());
        const settingsRes = await fetch('api/settings.php', { method: 'POST', body: interfaceForm });
        const settingsData = await settingsRes.json();
        if (!settingsData.success) {
            msgDiv.textContent = settingsData.message || 'Koneksi tersimpan, tetapi interface traffic gagal diperbarui';
            msgDiv.style.color = 'var(--danger)';
            return;
        }

        document.getElementById('mikrotikPassInput').value = '';
        msgDiv.textContent = 'Koneksi MikroTik berhasil disimpan';
        msgDiv.style.color = 'var(--success)';
    } catch (error) {
        msgDiv.textContent = 'Terjadi kesalahan sistem';
        msgDiv.style.color = 'var(--danger)';
    }

    setTimeout(() => { msgDiv.textContent = ''; }, 3000);
};

document.getElementById('testMikrotikConnBtn').onclick = async function() {
    const formEl = document.getElementById('mikrotikSettingsForm');
    const form = new FormData(formEl);
    form.append('action', 'test_connection');
    
    const msgDiv = document.getElementById('mikrotikSettingsMsg');
    msgDiv.textContent = 'Mencoba koneksi ke MikroTik...';
    msgDiv.style.color = 'inherit';

    try {
        const res = await fetch('api/setup.php', { method: 'POST', body: form });
        const data = await res.json();
        msgDiv.textContent = data.message;
        msgDiv.style.color = data.success ? 'var(--success)' : 'var(--danger)';
    } catch (error) {
        msgDiv.textContent = 'Terjadi kesalahan sistem saat mencoba koneksi';
        msgDiv.style.color = 'var(--danger)';
    }

    setTimeout(() => { msgDiv.textContent = ''; }, 5000);
};

document.getElementById('regenerateBridgeKeyBtn').onclick = async function() {
    const msgDiv = document.getElementById('bridgeKeyMsg');
    msgDiv.textContent = 'Membuat API key baru...';
    msgDiv.style.color = 'inherit';

    try {
        const form = new FormData();
        form.append('action', 'regenerate_mikrotik_bridge_api_key');
        const res = await fetch('api/settings.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            document.getElementById('mikrotikBridgeApiKeyInput').value = data.data.mikrotik_bridge_api_key;
            msgDiv.textContent = data.message;
            msgDiv.style.color = 'var(--success)';
        } else {
            msgDiv.textContent = data.message || 'Gagal membuat API key baru';
            msgDiv.style.color = 'var(--danger)';
        }
    } catch (error) {
        msgDiv.textContent = 'Terjadi kesalahan sistem';
        msgDiv.style.color = 'var(--danger)';
    }

    setTimeout(() => { msgDiv.textContent = ''; }, 4000);
};

document.getElementById('runMikrotikBackupBtn').onclick = async function() {
    const msgDiv = document.getElementById('mikrotikBackupMsg');
    const lastLabel = document.getElementById('mikrotikBackupLastLabel');
    msgDiv.textContent = 'Menjalankan backup MikroTik...';
    msgDiv.style.color = 'inherit';

    try {
        const form = new FormData();
        form.append('action', 'run_mikrotik_backup');
        const res = await fetch('api/settings.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            msgDiv.textContent = data.message || 'Backup berhasil.';
            msgDiv.style.color = 'var(--success)';
            if (lastLabel && data.data?.backup_last_at) {
                const backupName = data.data?.backup_name ? ` (${data.data.backup_name})` : '';
                lastLabel.textContent = `Terakhir: ${data.data.backup_last_at}${backupName}`;
            }
        } else {
            msgDiv.textContent = data.message || 'Backup MikroTik gagal.';
            msgDiv.style.color = 'var(--danger)';
        }
    } catch (error) {
        msgDiv.textContent = 'Terjadi kesalahan sistem';
        msgDiv.style.color = 'var(--danger)';
    }

    setTimeout(() => { msgDiv.textContent = ''; }, 4000);
};

function toggleSidebar() { document.getElementById('appSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }
function closeSidebar() { document.getElementById('appSidebar').classList.add('-translate-x-full'); document.getElementById('sidebarOverlay').classList.add('hidden'); }
document.querySelectorAll('#appSidebar a').forEach(l => l.addEventListener('click', () => { if (window.innerWidth < 768) closeSidebar(); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;

async function logout() { await fetch('api/auth.php?action=logout'); location.href = '<?= htmlspecialchars(app_url('login'), ENT_QUOTES, 'UTF-8') ?>'; }
</script>
<?= app_render_tenant_expiry_script('login.php') ?>
</body>
</html>

