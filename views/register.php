<?php
require_once __DIR__ . '/../config/env.php';
$appName = app_setting_get('app_name', 'Nikonet');
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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
                primary: "#004ac6",
                "on-primary": "#ffffff",
                "primary-container": "#2563eb",
                secondary: "#505f76",
                "on-secondary": "#ffffff",
                "surface": "#faf8ff",
                "on-surface": "#0f1119",
                "on-surface-variant": "#2e313d",
                "surface-container-low": "#f3f3fe",
                "surface-container": "#ededf9",
                "outline-variant": "#c3c6d7",
                "primary-fixed": "#dbe1ff",
                "error": "#ba1a1a",
                "error-container": "#ffdad6",
                "on-error-container": "#93000a"
            },
            borderRadius: {
                xl: "0.75rem",
                "2xl": "1rem",
                "3xl": "1.5rem"
            },
            fontFamily: {
                sans: ["Inter"]
            }
        }
    }
}
</script>
<style>
* { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; margin: 0; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
@keyframes floatIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
.anim-in { animation: floatIn 0.6s ease-out forwards; }
.anim-in-d1 { animation: floatIn 0.6s ease-out 0.1s forwards; opacity: 0; }
.anim-in-d2 { animation: floatIn 0.6s ease-out 0.2s forwards; opacity: 0; }
.anim-in-d3 { animation: floatIn 0.6s ease-out 0.3s forwards; opacity: 0; }
</style>
</head>
<body class="min-h-dvh flex items-center justify-center p-4">
<div class="fixed inset-0 pointer-events-none">
    <img src="https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1920&q=80" alt="" class="w-full h-full object-cover" onerror="this.style.display='none'" loading="lazy">
</div>

<div class="relative w-full max-w-[420px] bg-white rounded-3xl shadow-2xl border border-outline-variant/40 p-6 sm:p-8 my-4 anim-in-d1">
    <div class="text-center mb-6 anim-in-d2">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-primary flex items-center justify-center mx-auto mb-3 shadow-lg shadow-primary/30">
            <span class="material-symbols-outlined text-white text-[28px] sm:text-[32px]">app_registration</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-on-surface">Pendaftaran</h1>
        <p class="text-sm text-on-surface-variant mt-0.5">Daftarkan tenant ISP Anda</p>
    </div>

    <form id="registerForm" class="space-y-3.5 anim-in-d3">
        <div>
            <label class="text-sm font-bold text-on-surface-variant block mb-1" for="tenant_name">Nama Tenant / Brand</label>
            <input type="text" id="tenant_name" name="tenant_name" placeholder="Contoh: RT RW Net Jaya" required
                class="w-full px-3 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
        </div>
        <div>
            <label class="text-sm font-bold text-on-surface-variant block mb-1" for="owner_name">Nama Owner / PIC</label>
            <input type="text" id="owner_name" name="owner_name" placeholder="Nama Lengkap" required
                class="w-full px-3 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
        </div>
        <div>
            <label class="text-sm font-bold text-on-surface-variant block mb-1" for="phone">Nomor Telepon</label>
            <input type="text" id="phone" name="phone" placeholder="Contoh: 08123456789" required
                class="w-full px-3 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
        </div>
        <div>
            <label class="text-sm font-bold text-on-surface-variant block mb-1" for="username">Username Login</label>
            <input type="text" id="username" name="username" placeholder="Untuk login dashboard" required
                class="w-full px-3 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
        </div>
        <div>
            <label class="text-sm font-bold text-on-surface-variant block mb-1" for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required
                class="w-full px-3 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
        </div>
        <div>
            <label class="text-sm font-bold text-on-surface-variant block mb-1" for="confirm_password">Konfirmasi Password</label>
            <input type="password" id="confirm_password" placeholder="Ulangi password"
                class="w-full px-3 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
        </div>
        <div>
            <label class="text-sm font-bold text-on-surface-variant block mb-1" for="account_name">Nama Akun Dashboard</label>
            <input type="text" id="account_name" name="account_name" placeholder="Nama yang tampil di dashboard" required
                class="w-full px-3 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
        </div>

        <button type="submit" class="w-full py-3 bg-primary hover:bg-primary/90 text-on-primary text-sm font-bold tracking-wide rounded-xl shadow-lg shadow-primary/20 transition-all active:scale-[0.98]">Daftar Sekarang</button>
        <div id="regMsg" class="text-sm font-medium text-center"></div>
    </form>

    <div class="mt-5 pt-5 border-t border-outline-variant/40 text-center space-y-2">
        <p class="text-sm text-on-surface-variant">
            Sudah punya akun? <a href="login" class="text-primary font-bold hover:underline">Login</a>
        </p>
        <p class="text-xs text-on-surface-variant/60">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
</div>

<script>
document.getElementById('registerForm').onsubmit = async function(e) {
    e.preventDefault();
    const msgDiv = document.getElementById('regMsg');
    const pass = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;

    if (pass !== confirm) {
        msgDiv.textContent = 'Password konfirmasi tidak cocok.';
        msgDiv.style.color = '#dc2626';
        return;
    }

    const form = new FormData(this);
    msgDiv.textContent = 'Memproses pendaftaran...';
    msgDiv.style.color = '#2e313d';

    try {
        const res = await fetch('api/register.php', {method:'POST', body:form});
        const data = await res.json();
        if(data.success) {
            msgDiv.textContent = data.message;
            msgDiv.style.color = '#16a34a';
            this.reset();
        } else {
            msgDiv.textContent = data.message;
            msgDiv.style.color = '#dc2626';
        }
    } catch(e) {
        msgDiv.textContent = 'Terjadi kesalahan sistem.';
        msgDiv.style.color = '#dc2626';
    }
};
</script>
</body>
</html>

