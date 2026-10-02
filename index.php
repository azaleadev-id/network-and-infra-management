<?php
require_once __DIR__ . '/config/auth.php';

$account = app_current_account_fresh();
$accessIssue = app_account_access_issue($account);
if ($account !== null && $accessIssue !== null) {
    app_session_logout();
    $account = null;
}

$appName = app_setting_get('app_name', 'Nikonet');
$isLoggedIn = $account !== null && $accessIssue === null;
$primaryUrl = $isLoggedIn ? app_url(app_login_redirect_path($account)) : app_url('login');
$primaryLabel = $isLoggedIn ? 'Masuk ke Panel' : 'Login';
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> - Solusi Manajemen ISP Modern</title>
<link rel="icon" type="image/png" href="public/images/rtrwnet-logo-circle.png?v=<?= urlencode((string) filemtime(__DIR__ . '/public/images/rtrwnet-logo-circle.png')) ?>">
<link rel="shortcut icon" type="image/png" href="public/images/rtrwnet-logo-circle.png?v=<?= urlencode((string) filemtime(__DIR__ . '/public/images/rtrwnet-logo-circle.png')) ?>">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script id="tailwind-config">
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
.material-symbols-outlined.fill { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
.gradient-overlay::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.6) 35%, rgba(0,0,0,0.2) 70%, transparent 100%),
                linear-gradient(to top, rgba(0,0,0,0.4) 0%, transparent 40%);
}
@keyframes floatIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
.anim-in { animation: floatIn 0.6s ease-out forwards; }
.anim-in-d1 { animation: floatIn 0.6s ease-out 0.15s forwards; opacity: 0; }
.anim-in-d2 { animation: floatIn 0.6s ease-out 0.25s forwards; opacity: 0; }
.anim-in-d3 { animation: floatIn 0.6s ease-out 0.35s forwards; opacity: 0; }
.anim-in-d4 { animation: floatIn 0.6s ease-out 0.45s forwards; opacity: 0; }
.reveal { opacity: 0; transform: translateY(40px); transition: opacity 0.7s ease-out, transform 0.7s ease-out; }
.reveal.visible { opacity: 1; transform: translateY(0); }
</style>
</head>
<body class="bg-surface-container-low text-on-surface">

<!-- Navbar -->
<nav class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur-xl border-b border-gray-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-white text-[20px]">router</span>
                </div>
                <span class="text-lg font-bold text-primary"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= app_url("register") ?>" class="px-5 py-2.5 bg-secondary text-white text-sm font-semibold rounded-lg hover:bg-secondary/80 transition-all">Daftar</a>
                <a href="<?= htmlspecialchars($primaryUrl, ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg shadow-lg hover:bg-primary/90 transition-all"><?= htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="relative min-h-[85vh] flex items-center overflow-hidden pt-16">
    <div class="absolute inset-0 gradient-overlay">
        <img src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1920&q=80" alt="Server & Network Infrastructure" class="w-full h-full object-cover" onerror="this.style.display='none'" loading="lazy">
    </div>

    <div class="relative w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="max-w-3xl mx-auto text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary-fixed/20 backdrop-blur-sm text-white text-sm font-bold rounded-full mb-6 border border-white/10 anim-in">
                <span class="material-symbols-outlined text-[18px]">bolt</span>
                <span>Platform Monitoring ISP Terpercaya</span>
            </div>
            <h1 class="text-4xl md:text-5xl lg:text-7xl font-black text-white leading-[1.05] tracking-tight mb-6 anim-in-d1">
                Kelola Jaringan
                <span class="text-primary-fixed-dim block">ISP-mu Makin Gampang</span>
            </h1>
            <p class="text-lg text-white/80 max-w-2xl mx-auto leading-relaxed mb-8 anim-in-d2">
                Dari manajemen pelanggan, billing otomatis, monitoring trafik real-time, 
                hingga integrasi penuh dengan perangkat <strong class="text-white">MikroTik</strong> — semua dalam satu dashboard.
            </p>
            <div class="flex flex-wrap justify-center gap-4 anim-in-d3">
                <a href="<?= app_url("register") ?>" class="inline-flex items-center gap-3 px-8 py-4 bg-white text-[#0f1119] text-base font-bold rounded-xl shadow-2xl shadow-black/30 hover:shadow-black/40 hover:scale-[1.02] transition-all">
                    <span class="material-symbols-outlined text-[20px]">rocket_launch</span>
                    Mulai Sekarang
                </a>
                <a href="https://wa.me/6282129374933" class="inline-flex items-center gap-3 px-8 py-4 bg-white/10 backdrop-blur-sm text-white border border-white/20 text-base font-bold rounded-xl hover:bg-white/20 transition-all">
                    <span class="material-symbols-outlined text-[20px]">support_agent</span>
                    Hubungi Admin
                </a>
            </div>
            <div class="flex flex-wrap justify-center items-center gap-6 mt-8 text-sm text-white/70 anim-in-d4">
                <div class="flex items-center gap-2"><span class="material-symbols-outlined fill text-primary-fixed-dim text-[18px]">verified</span>Free Trial</div>
                <div class="flex items-center gap-2"><span class="material-symbols-outlined fill text-primary-fixed-dim text-[18px]">verified</span>No Card Required</div>
                <div class="flex items-center gap-2"><span class="material-symbols-outlined fill text-primary-fixed-dim text-[18px]">verified</span>Dukungan 24/7</div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Strip -->
<section class="py-10 bg-primary reveal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div><p class="text-2xl md:text-3xl font-black text-white">99.9%</p><p class="text-xs text-white/70 mt-1 uppercase tracking-wider">System Uptime</p></div>
            <div><p class="text-2xl md:text-3xl font-black text-white">24/7</p><p class="text-xs text-white/70 mt-1 uppercase tracking-wider">Real-time Monitoring</p></div>
            <div><p class="text-2xl md:text-3xl font-black text-white">AES-256</p><p class="text-xs text-white/70 mt-1 uppercase tracking-wider">Data Encryption</p></div>
            <div><p class="text-2xl md:text-3xl font-black text-white">Multi-ISP</p><p class="text-xs text-white/70 mt-1 uppercase tracking-wider">Platform Ready</p></div>
        </div>
    </div>
</section>

<!-- Fitur Unggulan -->
<section class="py-20 md:py-28 bg-white reveal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-primary-fixed text-primary text-sm font-bold rounded-full mb-4 shadow-sm">
                <span class="material-symbols-outlined text-[18px]">star</span>
                <span>FITUR LENGKAP</span>
            </span>
            <h2 class="text-3xl md:text-4xl font-black text-on-surface mb-4">Semua Yang Anda Butuhkan Dalam Satu Platform</h2>
            <p class="text-on-surface-variant leading-relaxed text-lg">Dari manajemen pelanggan hingga monitoring infrastruktur, semua terintegrasi dengan perangkat MikroTik Anda.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="group p-6 bg-white border border-gray-200 rounded-2xl hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-fixed rounded-2xl flex items-center justify-center mb-5 group-hover:bg-primary transition-all"><span class="material-symbols-outlined text-primary text-[28px] group-hover:text-white transition-all">dashboard</span></div>
                <h3 class="text-lg font-bold text-on-surface mb-2">Dashboard Real-time</h3>
                <p class="text-sm text-on-surface-variant leading-relaxed">Pantau trafik jaringan, topology map interaktif, dan ringkasan perangkat MikroTik secara langsung.</p>
            </div>
            <div class="group p-6 bg-white border border-gray-200 rounded-2xl hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-fixed rounded-2xl flex items-center justify-center mb-5 group-hover:bg-primary transition-all"><span class="material-symbols-outlined text-primary text-[28px] group-hover:text-white transition-all">group</span></div>
                <h3 class="text-lg font-bold text-on-surface mb-2">Manajemen Pelanggan</h3>
                <p class="text-sm text-on-surface-variant leading-relaxed">Kelola data pelanggan, relasi topologi, dan aktifitas koneksi dengan sistem yang terorganisir.</p>
            </div>
            <div class="group p-6 bg-white border border-gray-200 rounded-2xl hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-fixed rounded-2xl flex items-center justify-center mb-5 group-hover:bg-primary transition-all"><span class="material-symbols-outlined text-primary text-[28px] group-hover:text-white transition-all">payments</span></div>
                <h3 class="text-lg font-bold text-on-surface mb-2">Billing & Pembayaran</h3>
                <p class="text-sm text-on-surface-variant leading-relaxed">Atur tagihan otomatis, lacak pembayaran, dan cetak struk dengan sistem billing terintegrasi penuh.</p>
            </div>
            <div class="group p-6 bg-white border border-gray-200 rounded-2xl hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-fixed rounded-2xl flex items-center justify-center mb-5 group-hover:bg-primary transition-all"><span class="material-symbols-outlined text-primary text-[28px] group-hover:text-white transition-all">router</span></div>
                <h3 class="text-lg font-bold text-on-surface mb-2">Integrasi MikroTik</h3>
                <p class="text-sm text-on-surface-variant leading-relaxed">Hubungkan dan kelola perangkat MikroTik — PPPoE, Hotspot, Firewall, semuanya dari satu panel kontrol.</p>
            </div>
            <div class="group p-6 bg-white border border-gray-200 rounded-2xl hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-fixed rounded-2xl flex items-center justify-center mb-5 group-hover:bg-primary transition-all"><span class="material-symbols-outlined text-primary text-[28px] group-hover:text-white transition-all">lan</span></div>
                <h3 class="text-lg font-bold text-on-surface mb-2">Peta Infrastruktur</h3>
                <p class="text-sm text-on-surface-variant leading-relaxed">Visualisasikan node ODP, ODC, dan server Anda pada peta interaktif Google Maps dengan mudah.</p>
            </div>
            <div class="group p-6 bg-white border border-gray-200 rounded-2xl hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-fixed rounded-2xl flex items-center justify-center mb-5 group-hover:bg-primary transition-all"><span class="material-symbols-outlined text-primary text-[28px] group-hover:text-white transition-all">wifi</span></div>
                <h3 class="text-lg font-bold text-on-surface mb-2">Hotspot Voucher</h3>
                <p class="text-sm text-on-surface-variant leading-relaxed">Buat dan kelola voucher hotspot dengan berbagai profile, limit waktu, dan cetak struk siap pakai.</p>
            </div>
        </div>
    </div>
</section>

<!-- Teknisi & Jaringan -->
<section class="py-20 bg-surface-container-low reveal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div class="relative">
                <div class="absolute -bottom-6 -left-6 w-full h-full bg-gradient-to-tr from-[#004ac6]/15 to-transparent rounded-4xl"></div>
                <div class="relative rounded-4xl shadow-2xl overflow-hidden w-full h-[500px]">
                    <div class="absolute inset-0 bg-gradient-to-br from-[#004ac6]/10 via-[#dbe1ff]/30 to-[#004ac6]/5"></div>
                    <img src="https://images.unsplash.com/photo-1573164713714-d95e436ab8d6?auto=format&fit=crop&w=800&q=80" alt="Teknisi Jaringan" class="w-full h-full object-cover" onerror="this.style.opacity='0'" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-30"></div>
                </div>
                <div class="absolute -bottom-4 -right-4 bg-white border border-gray-200 rounded-2xl shadow-xl p-5 flex items-center gap-4">
                    <span class="material-symbols-outlined fill text-primary text-[32px]">verified</span>
                    <div><p class="text-sm font-black text-on-surface">Sudah Terpercaya</p><p class="text-xs text-on-surface-variant">By banyak ISP di Indonesia</p></div>
                </div>
            </div>
            <div>
                <span class="inline-flex items-center gap-2 px-4 py-2 bg-primary-fixed text-primary text-sm font-bold rounded-full mb-6 shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">support_agent</span>
                    <span>UNTUK TEKNISI & ISP</span>
                </span>
                <h2 class="text-3xl md:text-4xl font-black text-on-surface mb-4">Dibuat Khusus Untuk <span class="text-primary">Teknisi RT/RW Net</span></h2>
                <p class="text-on-surface-variant leading-relaxed mb-8 text-lg">Platform ini dirancang bareng para teknisi dan owner ISP kecil-menengah. Antarmuka yang simpel, fitur yang pas, tanpa ribet.</p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-gray-200">
                        <span class="material-symbols-outlined fill text-primary text-[20px]">speed</span>
                        <div><p class="text-sm font-bold text-on-surface">Monitoring Cepat</p><p class="text-xs text-on-surface-variant">Update real-time tiap detik</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-gray-200">
                        <span class="material-symbols-outlined fill text-primary text-[20px]">print</span>
                        <div><p class="text-sm font-bold text-on-surface">Cetak Struk</p><p class="text-xs text-on-surface-variant">Support printer thermal 58mm</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-gray-200">
                        <span class="material-symbols-outlined fill text-primary text-[20px]">cloud_sync</span>
                        <div><p class="text-sm font-bold text-on-surface">Backup Otomatis</p><p class="text-xs text-on-surface-variant">Konfigurasi MikroTik aman</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-gray-200">
                        <span class="material-symbols-outlined fill text-primary text-[20px]">devices</span>
                        <div><p class="text-sm font-bold text-on-surface">Mobile Friendly</p><p class="text-xs text-on-surface-variant">Akses dari HP dimana aja</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MikroTik Section -->
<section class="py-20 bg-primary reveal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <span class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 text-white text-sm font-bold rounded-full mb-6 backdrop-blur-sm">
                    <span class="material-symbols-outlined text-[18px]">router</span>
                    <span>MIKROTIK INTEGRATION</span>
                </span>
                <h2 class="text-3xl md:text-4xl font-black text-white mb-4">Full Integration Dengan <span class="text-white/80">RouterOS</span></h2>
                <p class="text-white/70 leading-relaxed mb-8 text-lg">Platform kami mendukung penuh perangkat RouterOS MikroTik via API. Pantau, konfigurasi, dan backup semua dari dashboard.</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex items-start gap-3 p-4 bg-white/5 rounded-xl backdrop-blur-sm border border-white/10">
                        <span class="material-symbols-outlined fill text-white/80 mt-0.5 text-[18px]">check_circle</span>
                        <div><p class="text-sm font-bold text-white">PPPoE Server</p><p class="text-xs text-white/60">Manajemen secret & profile</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-white/5 rounded-xl backdrop-blur-sm border border-white/10">
                        <span class="material-symbols-outlined fill text-white/80 mt-0.5 text-[18px]">check_circle</span>
                        <div><p class="text-sm font-bold text-white">Hotspot Server</p><p class="text-xs text-white/60">Voucher & user management</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-white/5 rounded-xl backdrop-blur-sm border border-white/10">
                        <span class="material-symbols-outlined fill text-white/80 mt-0.5 text-[18px]">check_circle</span>
                        <div><p class="text-sm font-bold text-white">Traffic Monitoring</p><p class="text-xs text-white/60">Real-time bandwidth graph</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-white/5 rounded-xl backdrop-blur-sm border border-white/10">
                        <span class="material-symbols-outlined fill text-white/80 mt-0.5 text-[18px]">check_circle</span>
                        <div><p class="text-sm font-bold text-white">Backup Config</p><p class="text-xs text-white/60">Automatic .rsc backup</p></div>
                    </div>
                </div>
            </div>
            <div class="relative">
                <div class="absolute -top-6 -right-6 w-full h-full bg-white/5 rounded-4xl"></div>
                <div class="relative rounded-4xl shadow-2xl overflow-hidden w-full h-[450px] border border-white/10">
                    <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-white/10"></div>
                    <img src="https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=800&q=80" alt="Network Infrastructure" class="w-full h-full object-cover" onerror="this.style.display='none'" loading="lazy">
                </div>
                <div class="absolute -bottom-4 -left-4 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl shadow-xl p-4 flex items-center gap-3">
                    <span class="material-symbols-outlined fill text-white text-[28px]">settings_ethernet</span>
                    <div><p class="text-sm font-black text-white">MikroTik Ready</p><p class="text-xs text-white/60">CHR, RB, CCR Series</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Banner -->
<section class="py-20 bg-white reveal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative bg-gradient-to-br from-[#004ac6] to-[#2563eb] rounded-4xl overflow-hidden shadow-2xl">
            <div class="absolute inset-0">
                <img src="https://images.unsplash.com/photo-1600880292203-757bb62b4baf?auto=format&fit=crop&w=1400&q=80" alt="Team working" class="w-full h-full object-cover opacity-20 mix-blend-overlay" onerror="this.style.display='none'" loading="lazy">
            </div>
            <div class="relative z-10 text-center px-8 py-16 md:px-20 md:py-24">
                <span class="material-symbols-outlined text-white/80 text-[56px] mb-6">rocket_launch</span>
                <h2 class="text-3xl md:text-5xl font-black text-white mb-4">Siap Kelola Jaringan ISP Anda?</h2>
                <p class="text-lg text-white/70 leading-relaxed max-w-2xl mx-auto mb-10">Daftarkan layanan Anda sekarang dan dapatkan akses penuh ke semua fitur. Tim support kami siap membantu proses setup dan konfigurasi awal.</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="<?= app_url("register") ?>" class="inline-flex items-center gap-3 px-10 py-4 bg-white text-primary text-base font-bold rounded-2xl hover:bg-white/90 shadow-2xl transition-all">
                        <span class="material-symbols-outlined text-[20px]">login</span>
                        Daftar Sekarang
                    </a>
                    <a href="https://wa.me/6282129374933" class="inline-flex items-center gap-3 px-10 py-4 bg-white/10 text-white border-2 border-white/20 text-base font-bold rounded-2xl hover:bg-white/20 transition-all">
                        <span class="material-symbols-outlined text-[20px]">chat</span>
                        Konsultasi Gratis
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="py-10 border-t border-gray-200 bg-surface-container-low">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-white text-[18px]">router</span>
                </div>
                <span class="font-bold text-primary"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <p class="text-sm text-on-surface-variant">&copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
            <div class="flex items-center gap-5">
                <a href="https://wa.me/6282129374933" class="text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-[22px]">chat</span></a>
                <a href="<?= app_url("login") ?>" class="text-sm text-on-surface-variant hover:text-primary font-medium transition-colors">Login</a>
                <a href="<?= app_url("register") ?>" class="text-sm text-on-surface-variant hover:text-primary font-medium transition-colors">Register</a>
            </div>
        </div>
    </div>
</footer>
<script>
const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.15 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>
</body>
</html>
