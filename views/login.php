<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/auth.php';
if (app_current_account() !== null) {
    $freshAccount = app_current_account_fresh();
    $accessIssue = app_account_access_issue($freshAccount);
    if ($accessIssue === null) {
        header('Location: ' . app_url(app_login_redirect_path($freshAccount)));
        exit;
    }

    app_session_logout();
}
$appName = app_setting_get('app_name', 'Nikonet');
$sessionStatus = trim((string) ($_GET['session_status'] ?? ''));
$sessionMessage = '';
if ($sessionStatus === 'expired') {
    $sessionMessage = 'Session owner berakhir karena akun tenant sudah expired';
} elseif ($sessionStatus === 'inactive') {
    $sessionMessage = 'Session berakhir karena akun atau tenant sedang nonaktif.';
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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
                lg: "0.5rem",
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

<div class="relative w-full max-w-[400px] bg-white rounded-3xl shadow-2xl border border-outline-variant/40 p-6 sm:p-8 my-4 anim-in-d1">
    <div class="text-center mb-6 anim-in-d2">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-primary flex items-center justify-center mx-auto mb-3 shadow-lg shadow-primary/30">
            <span class="material-symbols-outlined text-white text-[28px] sm:text-[32px]">router</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-on-surface"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-on-surface-variant mt-0.5">Masuk ke akun Anda</p>
    </div>

    <?php if ($sessionMessage !== ''): ?>
    <div class="mb-4 p-3 bg-error-container border border-error-container rounded-xl flex items-start gap-2">
        <span class="material-symbols-outlined text-error text-[18px] mt-0.5 shrink-0">error</span>
        <p class="text-sm text-on-error-container"><?= htmlspecialchars($sessionMessage, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <form id="loginForm" action="#" method="POST" onsubmit="return false" class="space-y-4 anim-in-d3">
        <div>
            <label class="text-sm font-bold text-on-surface-variant block mb-1" for="identifier">Username atau Email</label>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px]">person</span>
                <input type="text" id="identifier" name="username" placeholder="Username atau email" required
                    class="w-full pl-10 pr-3 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
            </div>
        </div>

        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="text-sm font-bold text-on-surface-variant" for="password">Kata Sandi</label>
                <a href="#" id="forgotPasswordLink" class="text-xs text-primary font-semibold hover:underline">Lupa?</a>
            </div>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px]">lock</span>
                <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required
                    class="w-full pl-10 pr-10 py-3 bg-surface-container-low border border-outline-variant rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                <button type="button" id="togglePassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-on-surface-variant p-1">
                    <span class="material-symbols-outlined text-[20px]" id="toggleIcon">visibility</span>
                </button>
            </div>
        </div>

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="rememberMe" name="remember"
                class="w-4 h-4 text-primary bg-surface-container border-outline-variant rounded focus:ring-primary">
            <span class="text-sm text-on-surface-variant">Ingat saya</span>
        </label>

        <button type="submit" class="w-full py-3 bg-primary hover:bg-primary/90 text-on-primary text-sm font-bold tracking-wide rounded-xl shadow-lg shadow-primary/20 transition-all active:scale-[0.98] flex items-center justify-center gap-2">
            Masuk
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>

        <div class="relative flex items-center">
            <div class="flex-grow border-t border-outline-variant/60"></div>
            <span class="flex-shrink mx-3 text-xs text-on-surface-variant/50 font-medium">Atau</span>
            <div class="flex-grow border-t border-outline-variant/60"></div>
        </div>

        <button type="button" id="googleLoginBtn" class="w-full py-3 bg-white border border-outline-variant hover:bg-surface-container-low text-on-surface text-sm font-semibold rounded-xl transition-all flex items-center justify-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 24 24">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Masuk dengan Google
        </button>
    </form>

    <div id="loginMsg" class="mt-3 text-center text-sm"></div>

    <div class="mt-5 pt-5 border-t border-outline-variant/40 text-center space-y-2">
        <p class="text-sm text-on-surface-variant">
            Belum punya akun? <a href="register" class="text-primary font-bold hover:underline">Daftar</a>
        </p>
        <p class="text-xs text-on-surface-variant/60">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const msgDiv = document.getElementById('loginMsg');
    const pwd = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePassword');
    const toggleIcon = document.getElementById('toggleIcon');
    const forgotLink = document.getElementById('forgotPasswordLink');
    const googleBtn = document.getElementById('googleLoginBtn');

    form.onsubmit = async function(e) {
        e.preventDefault();
        msgDiv.innerHTML = 'Memproses login...';
        const fd = new FormData(this);
        try {
            const res = await fetch('api/auth.php', {method: 'POST', body: fd});
            const d = await res.json();
            if (d.success) {
                msgDiv.innerHTML = 'Login berhasil! Mengarahkan...';
                msgDiv.className = 'mt-3 text-center text-sm text-green-600 font-bold';
                setTimeout(() => location.href = d.redirect || '<?= htmlspecialchars(app_url('dashboard'), ENT_QUOTES, 'UTF-8') ?>', 1500);
            } else {
                msgDiv.innerHTML = d.message;
                msgDiv.className = 'mt-3 text-center text-sm text-error font-bold';
            }
        } catch(_) {
            msgDiv.innerHTML = 'Terjadi kesalahan sistem.';
            msgDiv.className = 'mt-3 text-center text-sm text-error font-bold';
        }
    };

    toggleBtn?.addEventListener('click', () => {
        const isPwd = pwd.type === 'password';
        pwd.type = isPwd ? 'text' : 'password';
        toggleIcon.textContent = isPwd ? 'visibility_off' : 'visibility';
    });

    forgotLink?.addEventListener('click', e => { e.preventDefault(); alert('Fitur lupa sandi akan segera tersedia.'); });
    googleBtn?.addEventListener('click', () => alert('Login dengan Google akan segera tersedia.'));
});
</script>
<?= app_render_tenant_expiry_script('login.php') ?>
</body>
</html>

