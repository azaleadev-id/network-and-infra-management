<?php if (!isset($activePage)) $activePage = 'dashboard'; ?>
<aside id="appSidebar" class="fixed left-0 top-0 bottom-0 z-40 w-[270px] bg-surface-container-lowest border-r border-outline-variant flex flex-col transition-transform duration-300 -translate-x-full lg:translate-x-0">
    <div class="px-5 pt-6 pb-4 border-b border-outline-variant">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shadow-md shadow-primary/20">
                <span class="material-symbols-outlined text-white text-[22px]">router</span>
            </div>
            <div>
                <h1 class="text-lg font-black text-primary"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-[11px] text-on-surface-variant">ISP Management</p>
            </div>
        </div>
    </div>
    <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">
        <?php
        $navItems = [
            ['href' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
            ['href' => 'users', 'icon' => 'group', 'label' => 'Manajemen User'],
            ['href' => 'payments', 'icon' => 'payments', 'label' => 'Pembayaran'],
            ['href' => 'pppoe', 'icon' => 'settings_ethernet', 'label' => 'PPPoE Client'],
            ['href' => 'hotspot', 'icon' => 'wifi', 'label' => 'Hotspot Voucher'],
            ['href' => 'infra', 'icon' => 'lan', 'label' => 'Infrastruktur'],
            ['href' => 'settings', 'icon' => 'settings', 'label' => 'Settings'],
        ];
        foreach ($navItems as $item):
            $isActive = $activePage === $item['href'];
            $linkClass = $isActive
                ? 'flex items-center gap-3 px-4 py-2.5 bg-primary-fixed text-primary rounded-xl font-bold text-sm'
                : 'flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium';
        ?>
        <a href="<?= $item['href'] ?>" class="<?= $linkClass ?> transition-all">
            <span class="material-symbols-outlined text-[22px]"><?= $item['icon'] ?></span>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
        <?php if (app_is_superadmin()): ?>
        <a href="superadmin" class="flex items-center gap-3 px-4 py-2.5 text-on-surface-variant hover:bg-surface-container-high rounded-xl text-sm font-medium transition-all">
            <span class="material-symbols-outlined text-[22px]">admin_panel_settings</span> Superadmin
        </a>
        <?php endif; ?>
    </nav>
    <div class="p-4 border-t border-outline-variant">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-primary text-on-primary flex items-center justify-center font-black text-sm shadow-sm">
                <?= strtoupper(substr($account['username'] ?? $appName, 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-on-surface truncate"><?= htmlspecialchars($account['username'] ?? 'Operator', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-on-surface-variant truncate">ISP Operator</p>
            </div>
            <button onclick="logout()" class="w-9 h-9 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-error-container hover:text-error transition-all" title="Logout">
                <span class="material-symbols-outlined text-[20px]">logout</span>
            </button>
        </div>
    </div>
</aside>
<div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-30 hidden" onclick="closeSidebar()"></div>
