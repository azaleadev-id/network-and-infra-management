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
<title>PPPoE Client - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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

<?php $activePage = 'pppoe'; require_once __DIR__ . '/partials/dashboard_sidebar.php'; ?>

<main class="lg:ml-[270px] min-h-screen transition-all">
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-outline-variant h-16 flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center hover:bg-surface-container transition-all" onclick="toggleSidebar()"><span class="material-symbols-outlined text-on-surface-variant">menu</span></button>
            <h2 class="text-lg font-black text-on-surface">PPPoE Client MikroTik</h2>
        </div>
    </header>
    <div class="p-6 max-w-[1600px] mx-auto">
        <?= app_render_tenant_expiry_banner() ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-gradient-to-br from-violet-100 to-violet-50 rounded-xl shadow-sm" id="pppoeSecretSection">
                <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">PPPoE Secret MikroTik</h3></div>
                <div class="p-6">
                    <form id="pppoeSecretForm">
                        <input type="hidden" name="action" id="pppoeSecretAction" value="create_secret">
                        <input type="hidden" name="original_name" id="pppoeSecretOriginalName" value="">
                        <div class="mb-4"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama Secret / Username</label><input type="text" name="name" id="pppoeSecretNameField" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: pelanggan-baru" required></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label id="pppoeSecretPasswordLabel" class="block text-sm font-semibold text-on-surface-variant mb-1">Password</label><input type="password" name="password" id="pppoeSecretPasswordField" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Password PPPoE" required></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Profile</label><select name="profile" id="pppoeSecretProfileSelect" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="default">default</option></select></div>
                        </div>
                        <div class="mb-4"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Service</label><select name="service" id="pppoeSecretServiceSelect" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="pppoe">pppoe</option></select></div>
                        <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all" id="pppoeSecretSubmitButton">Tambah PPP Secret</button>
                        <button type="button" class="w-full px-6 py-2.5 bg-secondary text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all hidden mt-2" id="cancelPppoeSecretEditButton">Batal Edit</button>
                        <div id="pppoeSecretMsg" class="mt-3 text-sm font-medium"></div>
                    </form>
                </div>
            </div>

            <div class="bg-gradient-to-br from-violet-100 to-violet-50 rounded-xl shadow-sm" id="pppoeProfileSection">
                <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">PPPoE Profile MikroTik</h3></div>
                <div class="p-6">
                    <form id="pppoeProfileForm">
                        <input type="hidden" name="action" value="create_profile">
                        <div class="mb-4"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama Profile</label><input type="text" name="name" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: PON3_20MB" required></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Local Address</label><select name="local_address_mode" id="pppoeProfileLocalMode" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="manual">Manual</option><option value="router">IP Address MikroTik</option></select></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Remote Address</label><select name="remote_address_mode" id="pppoeProfileRemoteMode" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="manual">Manual</option><option value="pool">IP Pool</option></select></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div id="pppoeProfileLocalManualWrap"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Local Address Manual</label><input type="text" name="local_address" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Opsional"></div>
                            <div class="hidden" id="pppoeProfileLocalPoolWrap"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Local Address dari IP MikroTik</label><select name="local_address_list" id="pppoeProfileLocalAddressSelect" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="">Pilih Local Address...</option></select></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div id="pppoeProfileRemoteManualWrap"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Remote Address Manual</label><input type="text" name="remote_address" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Opsional"></div>
                            <div class="hidden" id="pppoeProfileRemotePoolWrap"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Remote Address dari IP Pool</label><select name="remote_pool" id="pppoeProfileRemotePoolSelect" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"><option value="">Pilih IP Pool...</option></select></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Rate Limit</label><input type="text" name="rate_limit" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm" placeholder="Contoh: 20M/20M"></div>
                            <div><label class="block text-sm font-semibold text-on-surface-variant mb-1">Only One Session</label><select name="only_one" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm"><option value="false">Tidak</option><option value="true">Ya</option></select></div>
                        </div>
                        <div class="mb-4"><label class="block text-sm font-semibold text-on-surface-variant mb-1">Keterangan</label><input type="text" name="comment" class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm" placeholder="Opsional"></div>
                        <button type="submit" class="w-full px-6 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Tambah PPP Profile</button>
                        <div id="pppoeProfileMsg" class="mt-3 text-sm font-medium"></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-gradient-to-br from-violet-100 to-violet-50 rounded-xl shadow-sm">
                <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Daftar Secret MikroTik</h3></div>
                <div class="p-6 overflow-x-auto" id="pppoeSecretList">Memuat secret...</div>
            </div>
            <div class="bg-gradient-to-br from-violet-100 to-violet-50 rounded-xl shadow-sm">
                <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Active Connection MikroTik</h3></div>
                <div class="p-6 overflow-x-auto" id="pppoeActiveList">Memuat active connection...</div>
                <div id="pppoeActiveMsg" class="px-6 pb-4 text-sm font-medium"></div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-violet-100 to-violet-50 rounded-xl shadow-sm" id="pppoeActiveSection">
            <div class="px-6 py-4 border-b border-outline-variant"><h3 class="text-base font-bold text-on-surface">Daftar Profile MikroTik</h3></div>
            <div class="p-6 overflow-x-auto" id="pppoeProfileList">Memuat profile...</div>
        </div>
    </div>
</main>

<script>
const pppoeSecretForm = document.getElementById('pppoeSecretForm');
const pppoeProfileForm = document.getElementById('pppoeProfileForm');
const pppoeSecretActionField = document.getElementById('pppoeSecretAction');
const pppoeSecretOriginalNameField = document.getElementById('pppoeSecretOriginalName');
const pppoeSecretNameField = document.getElementById('pppoeSecretNameField');
const pppoeSecretPasswordField = document.getElementById('pppoeSecretPasswordField');
const pppoeSecretPasswordLabel = document.getElementById('pppoeSecretPasswordLabel');
const pppoeSecretProfileSelect = document.getElementById('pppoeSecretProfileSelect');
const pppoeSecretServiceSelect = document.getElementById('pppoeSecretServiceSelect');
const pppoeSecretSubmitButton = document.getElementById('pppoeSecretSubmitButton');
const cancelPppoeSecretEditButton = document.getElementById('cancelPppoeSecretEditButton');
let pppoeSecretsCache = [];

function escapeHtml(v) { return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

function renderPppoePools(pools = []) {
    const selects = [document.getElementById('pppoeProfileRemotePoolSelect')];
    selects.forEach(select => { if (!select) return; const cv = select.value || ''; select.innerHTML = `<option value="">Pilih IP Pool...</option>${pools.map(p => { const v = p.name || ''; const s = v === cv ? ' selected' : ''; const l = p.ranges && p.ranges !== '-' ? `${v} (${p.ranges})` : v; return `<option value="${escapeHtml(v)}"${s}>${escapeHtml(l)}</option>`; }).join('')}`; });
}

function renderPppoeLocalAddresses(addresses = []) {
    const select = document.getElementById('pppoeProfileLocalAddressSelect'); if (!select) return; const cv = select.value || '';
    select.innerHTML = `<option value="">Pilih Local Address...</option>${addresses.map(a => { const v = a.address || ''; const s = v === cv ? ' selected' : ''; const p = [v]; if (a.interface) p.push(a.interface); if (a.network) p.push(`network ${a.network}`); return `<option value="${escapeHtml(v)}"${s}>${escapeHtml(p.join(' | '))}</option>`; }).join('')}`;
}

function renderPppoeServices(services = []) {
    const select = document.getElementById('pppoeSecretServiceSelect'); if (!select) return; const cv = select.value || 'pppoe';
    select.innerHTML = ((services || []).length ? services : ['pppoe']).map(s => { const v = String(s || '').trim() || 'pppoe'; return `<option value="${escapeHtml(v)}"${v === cv ? ' selected' : ''}>${escapeHtml(v)}</option>`; }).join('');
}

function syncRemoteAddressMode(p) {
    const rm = document.getElementById(`${p}RemoteMode`), mw = document.getElementById(`${p}RemoteManualWrap`), pw = document.getElementById(`${p}RemotePoolWrap`), mi = mw?.querySelector('input[name="remote_address"]'), ps = pw?.querySelector('select[name="remote_pool"]'), u = rm?.value === 'pool';
    if (mw) mw.classList.toggle('hidden', u); if (pw) pw.classList.toggle('hidden', !u); if (mi) mi.disabled = u; if (ps) ps.disabled = !u;
}

function syncLocalAddressMode(p) {
    const lm = document.getElementById(`${p}LocalMode`), mw = document.getElementById(`${p}LocalManualWrap`), pw = document.getElementById(`${p}LocalPoolWrap`), mi = mw?.querySelector('input[name="local_address"]'), as = pw?.querySelector('select[name="local_address_list"]'), u = lm?.value === 'router';
    if (mw) mw.classList.toggle('hidden', u); if (pw) pw.classList.toggle('hidden', !u); if (mi) mi.disabled = u; if (as) as.disabled = !u;
}

function renderPppoeProfiles(profiles = []) {
    const select = pppoeSecretProfileSelect, list = document.getElementById('pppoeProfileList'), cv = select.value || 'default';
    select.innerHTML = (profiles.length ? profiles : [{name:'default'}]).map(p => { const v = p.name || 'default'; return `<option value="${escapeHtml(v)}"${v === cv ? ' selected' : ''}>${escapeHtml(v)}</option>`; }).join('');
    if (!select.value) select.value = 'default';
    if (!profiles.length) { list.innerHTML = '<p class="text-sm text-secondary">Belum ada profile dari MikroTik.</p>'; return; }
    list.innerHTML = `<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">Profile</th><th class="pb-3">Local</th><th class="pb-3">Remote</th><th class="pb-3">Rate Limit</th><th class="pb-3">Aksi</th></tr></thead><tbody>${profiles.map(item => `<tr class="border-b border-outline-variant/50"><td class="py-3 font-medium">${escapeHtml(item.name || '-')}</td><td class="py-3">${escapeHtml(item.local_address || '-')}</td><td class="py-3">${escapeHtml(item.remote_address || '-')}</td><td class="py-3">${escapeHtml(item.rate_limit || '-')}</td><td class="py-3"><button class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 delete-pppoe-profile-btn" data-name="${escapeHtml(item.name || '')}">Hapus</button></td></tr>`).join('')}</tbody></table>`;
    list.querySelectorAll('.delete-pppoe-profile-btn').forEach(b => { b.addEventListener('click', async () => { const n = b.dataset.name || ''; if (!n || !confirm(`Hapus PPP profile ${n} dari MikroTik?`)) return; const f = new FormData(); f.append('action','delete_profile'); f.append('name',n); await submitPppoeAction(f,'pppoeProfileMsg',`Menghapus profile ${n}...`); }); });
}

function resetPppoeSecretForm() { pppoeSecretForm.reset(); pppoeSecretActionField.value='create_secret'; pppoeSecretOriginalNameField.value=''; pppoeSecretPasswordField.required=true; pppoeSecretPasswordField.placeholder='Password PPPoE'; pppoeSecretPasswordLabel.textContent='Password'; pppoeSecretSubmitButton.textContent='Tambah PPP Secret'; cancelPppoeSecretEditButton.classList.add('hidden'); if ([...pppoeSecretProfileSelect.options].some(o=>o.value==='default')) pppoeSecretProfileSelect.value='default'; if ([...pppoeSecretServiceSelect.options].some(o=>o.value==='pppoe')) pppoeSecretServiceSelect.value='pppoe'; }

function startPppoeSecretEdit(secret) { if (!secret?.name) return; pppoeSecretActionField.value='update_secret'; pppoeSecretOriginalNameField.value=secret.name; pppoeSecretNameField.value=secret.name; pppoeSecretPasswordField.value=''; pppoeSecretPasswordField.required=false; pppoeSecretPasswordField.placeholder='Kosongkan jika password tidak diubah'; pppoeSecretPasswordLabel.textContent='Password Baru'; pppoeSecretProfileSelect.value=secret.profile||'default'; pppoeSecretServiceSelect.value=secret.service||'pppoe'; pppoeSecretSubmitButton.textContent='Simpan Perubahan Secret'; cancelPppoeSecretEditButton.classList.remove('hidden'); document.getElementById('pppoeSecretMsg').textContent=`Mode edit aktif untuk secret ${secret.name}.`; document.getElementById('pppoeSecretMsg').style.color='#16a34a'; pppoeSecretNameField.focus(); }

function renderPppoeSecrets(secrets = []) {
    pppoeSecretsCache = Array.isArray(secrets) ? secrets.slice() : [];
    const list = document.getElementById('pppoeSecretList');
    if (!secrets.length) { list.innerHTML = '<p class="text-sm text-secondary">Belum ada secret dari MikroTik.</p>'; return; }
    list.innerHTML = `<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">Secret</th><th class="pb-3">Profile</th><th class="pb-3">Service</th><th class="pb-3">Local Addr</th><th class="pb-3">Remote Addr</th><th class="pb-3">Status</th><th class="pb-3">Aksi</th></tr></thead><tbody>${secrets.map(item => `<tr class="border-b border-outline-variant/50"><td class="py-3 font-medium">${escapeHtml(item.name||'-')}</td><td class="py-3">${escapeHtml(item.profile||'-')}</td><td class="py-3">${escapeHtml(item.service||'-')}</td><td class="py-3">${escapeHtml(item.local_address||'-')}</td><td class="py-3">${escapeHtml(item.remote_address||'-')}</td><td class="py-3">${item.disabled?'<span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">DISABLED</span>':'<span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">ACTIVE</span>'}</td><td class="py-3"><div class="flex gap-1"><button class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 edit-pppoe-secret-btn" data-name="${escapeHtml(item.name||'')}">Edit</button>${item.disabled?`<button class="px-3 py-1.5 bg-primary text-on-primary text-xs font-semibold rounded-lg hover:opacity-90 enable-pppoe-secret-btn" data-name="${escapeHtml(item.name||'')}">Enable</button>`:`<button class="px-3 py-1.5 bg-secondary text-white text-xs font-semibold rounded-lg hover:opacity-90 disable-pppoe-secret-btn" data-name="${escapeHtml(item.name||'')}">Disable</button>`}<button class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 delete-pppoe-secret-btn" data-name="${escapeHtml(item.name||'')}">Hapus</button></div></td></tr>`).join('')}</tbody></table>`;
    list.querySelectorAll('.edit-pppoe-secret-btn').forEach(b=>{b.addEventListener('click',()=>{const n=b.dataset.name||'';const s=pppoeSecretsCache.find(i=>(i.name||'')===n);startPppoeSecretEdit(s);location.hash='pppoeSecretSection';});});
    list.querySelectorAll('.disable-pppoe-secret-btn').forEach(b=>{b.addEventListener('click',async()=>{const n=b.dataset.name||'';if(!n||!confirm(`Disable PPP secret ${n}?`))return;const f=new FormData();f.append('action','disable_secret');f.append('name',n);await submitPppoeAction(f,'pppoeSecretMsg',`Disable secret ${n}...`);});});
    list.querySelectorAll('.enable-pppoe-secret-btn').forEach(b=>{b.addEventListener('click',async()=>{const n=b.dataset.name||'';if(!n||!confirm(`Enable PPP secret ${n}?`))return;const f=new FormData();f.append('action','enable_secret');f.append('name',n);await submitPppoeAction(f,'pppoeSecretMsg',`Enable secret ${n}...`);});});
    list.querySelectorAll('.delete-pppoe-secret-btn').forEach(b=>{b.addEventListener('click',async()=>{const n=b.dataset.name||'';if(!n||!confirm(`Hapus PPP secret ${n}?`))return;const f=new FormData();f.append('action','delete_secret');f.append('name',n);await submitPppoeAction(f,'pppoeSecretMsg',`Menghapus secret ${n}...`);});});
}

function renderPppoeActive(active = []) {
    const list = document.getElementById('pppoeActiveList');
    if (!active.length) { list.innerHTML = '<p class="text-sm text-secondary">Belum ada active connection di MikroTik.</p>'; return; }
    list.innerHTML = `<table class="w-full text-left text-sm"><thead><tr class="text-xs text-secondary uppercase tracking-wider border-b border-outline-variant"><th class="pb-3">User</th><th class="pb-3">Address</th><th class="pb-3">Service</th><th class="pb-3">Uptime</th><th class="pb-3">Caller ID</th><th class="pb-3">Status Secret</th><th class="pb-3">Aksi</th></tr></thead><tbody>${active.map(item => `<tr class="border-b border-outline-variant/50"><td class="py-3 font-medium">${escapeHtml(item.name||'-')}</td><td class="py-3">${escapeHtml(item.address||'-')}</td><td class="py-3">${escapeHtml(item.service||'-')}</td><td class="py-3">${escapeHtml(item.uptime||'-')}</td><td class="py-3">${escapeHtml(item.caller_id||'-')}</td><td class="py-3">${item.secret_disabled?'<span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">DISABLED</span>':'<span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">ACTIVE</span>'}</td><td class="py-3"><button class="px-3 py-1.5 bg-error text-on-error text-xs font-semibold rounded-lg hover:opacity-90 disconnect-pppoe-active-btn" data-id="${escapeHtml(item.id||'')}" data-name="${escapeHtml(item.name||'')}" ${item.secret_disabled?'':'disabled'}>Delete Active</button></td></tr>`).join('')}</tbody></table>`;
    list.querySelectorAll('.disconnect-pppoe-active-btn').forEach(b=>{b.addEventListener('click',async()=>{const sid=b.dataset.id||'',n=b.dataset.name||'';if(!sid||!confirm(`Hapus active connection ${n||'ini'}?`))return;const f=new FormData();f.append('action','remove_active');f.append('session_id',sid);f.append('name',n);await submitPppoeAction(f,'pppoeActiveMsg',`Menghapus active connection ${n||''}...`);});});
}

async function loadPppoeData() {
    try { const r=await fetch(`api/pppoe.php?_=${Date.now()}`); const d=await r.json(); if(!d.success) { document.getElementById('pppoeSecretList').innerHTML=`<p class="text-sm text-error">${escapeHtml(d.message||'Gagal')}</p>`; document.getElementById('pppoeProfileList').innerHTML=`<p class="text-sm text-error">${escapeHtml(d.message||'Gagal')}</p>`; document.getElementById('pppoeActiveList').innerHTML=`<p class="text-sm text-error">${escapeHtml(d.message||'Gagal')}</p>`; return; }
    renderPppoeSecrets(d.secrets||[]); renderPppoeProfiles(d.profiles||[]); renderPppoeActive(d.active||[]); renderPppoePools(d.pools||[]); renderPppoeLocalAddresses(d.addresses||[]); renderPppoeServices(d.services||[]); syncLocalAddressMode('pppoeProfile'); syncRemoteAddressMode('pppoeProfile');
    } catch(e) { document.getElementById('pppoeSecretList').innerHTML='<p class="text-sm text-error">Gagal memuat PPPoE secret.</p>'; document.getElementById('pppoeProfileList').innerHTML='<p class="text-sm text-error">Gagal memuat PPPoE profile.</p>'; document.getElementById('pppoeActiveList').innerHTML='<p class="text-sm text-error">Gagal memuat active connection.</p>'; }
}

async function submitPppoeAction(formData, msgId, loadingText='Menyimpan ke MikroTik...') {
    const md=document.getElementById(msgId); md.textContent=loadingText; md.style.color='inherit';
    try { const r=await fetch('api/pppoe.php',{method:'POST',body:formData}); const d=await r.json(); if(!d.success) { md.textContent=d.message||'Gagal'; md.style.color='#dc2626'; return false; } md.textContent=d.message; md.style.color='#16a34a'; await loadPppoeData(); return true; }
    catch(e) { md.textContent='Terjadi kesalahan sistem'; md.style.color='#dc2626'; return false; }
    finally { setTimeout(()=>{md.textContent='';},3000); }
}

async function submitPppoeForm(formElement, msgId) { const s=await submitPppoeAction(new FormData(formElement),msgId); if(s) { if(formElement===pppoeSecretForm) resetPppoeSecretForm(); else { formElement.reset(); syncLocalAddressMode('pppoeProfile'); syncRemoteAddressMode('pppoeProfile'); } } return s; }

async function logout() { await fetch('api/auth.php?action=logout'); location.href='<?= htmlspecialchars(app_url('login'), ENT_QUOTES, 'UTF-8') ?>'; }
function toggleSidebar() { document.getElementById('appSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }
function closeSidebar() { document.getElementById('appSidebar').classList.add('-translate-x-full'); document.getElementById('sidebarOverlay').classList.add('hidden'); }
document.querySelectorAll('#appSidebar a').forEach(l=>{l.addEventListener('click',()=>{if(window.innerWidth<768) closeSidebar();});});
document.addEventListener('keydown',e=>{if(e.key==='Escape') closeSidebar();});
pppoeSecretForm.addEventListener('submit',async function(e){e.preventDefault();await submitPppoeForm(pppoeSecretForm,'pppoeSecretMsg');});
pppoeProfileForm.addEventListener('submit',async function(e){e.preventDefault();await submitPppoeForm(pppoeProfileForm,'pppoeProfileMsg');});
document.getElementById('pppoeProfileLocalMode').addEventListener('change',()=>syncLocalAddressMode('pppoeProfile'));
document.getElementById('pppoeProfileRemoteMode').addEventListener('change',()=>syncRemoteAddressMode('pppoeProfile'));
cancelPppoeSecretEditButton.addEventListener('click',()=>{resetPppoeSecretForm();document.getElementById('pppoeSecretMsg').textContent='';});
window.onload=async function(){resetPppoeSecretForm();syncLocalAddressMode('pppoeProfile');syncRemoteAddressMode('pppoeProfile');loadPppoeData();};
</script>
<?= app_render_tenant_expiry_script('login.php') ?>
</body>
</html>

