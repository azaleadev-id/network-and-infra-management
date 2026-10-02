<?php
require_once __DIR__ . '/db.php';

if (!function_exists('app_timezone')) {
    function app_timezone(): string {
        static $timezone = null;
        if ($timezone === null) {
            $timezone = (string) env_value('APP_TIMEZONE', 'Asia/Jakarta');
        }

        return $timezone;
    }
}

if (!function_exists('app_bootstrap_timezone')) {
    function app_bootstrap_timezone(): void {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        date_default_timezone_set(app_timezone());
        $bootstrapped = true;
    }
}

app_bootstrap_timezone();

if (!function_exists('app_base_url_path')) {
    function app_base_url_path(): string {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        foreach (['/views', '/api', '/config'] as $suffix) {
            if ($dir !== '' && substr($dir, -strlen($suffix)) === $suffix) {
                $dir = substr($dir, 0, -strlen($suffix));
                break;
            }
        }

        if ($dir === '' || $dir === '.') {
            return '/';
        }

        return $dir;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string {
        $base = rtrim(app_base_url_path(), '/');
        $path = ltrim($path, '/');

        if ($path === '') {
            return $base !== '' ? $base : '/';
        }

        return ($base !== '' ? $base : '') . '/' . $path;
    }
}

if (!function_exists('app_session_start')) {
    function app_session_start(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sessionPath = app_storage_path('sessions');
            app_ensure_directory($sessionPath);

            if (session_save_path() !== $sessionPath) {
                session_save_path($sessionPath);
            }

            $httpsEnabled = (
                (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ||
                ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
            );

            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $httpsEnabled,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                session_set_cookie_params(0, '/; samesite=Lax', '', $httpsEnabled, true);
            }

            session_name('NIKONETSESSID');
            session_start();
        }
    }
}

if (!function_exists('app_session_logout')) {
    function app_session_logout(): void {
        app_session_start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'] ?: '/', $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
        }

        session_destroy();
    }
}

if (!function_exists('app_session_file_matches_int')) {
    function app_session_file_matches_int(string $content, string $key, int $value): bool {
        if ($value <= 0) {
            return false;
        }

        $needle = $key . '|i:' . $value . ';';
        if (strpos($content, $needle) !== false) {
            return true;
        }

        $arrayNeedle = 's:' . strlen($key) . ':"' . $key . '";i:' . $value . ';';
        return strpos($content, $arrayNeedle) !== false;
    }
}

if (!function_exists('app_session_destroy_where')) {
    function app_session_destroy_where(callable $matcher): int {
        $sessionPath = app_storage_path('sessions');
        if (!is_dir($sessionPath)) {
            return 0;
        }

        $removed = 0;
        foreach (glob($sessionPath . '/sess_*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $content = file_get_contents($file);
            if (!is_string($content) || !$matcher($content)) {
                continue;
            }

            if (@unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}

if (!function_exists('app_session_destroy_for_tenant')) {
    function app_session_destroy_for_tenant(int $tenantId): int {
        return app_session_destroy_where(static function (string $content) use ($tenantId): bool {
            return app_session_file_matches_int($content, 'tenant_id', $tenantId);
        });
    }
}

if (!function_exists('app_session_destroy_for_account')) {
    function app_session_destroy_for_account(int $accountId): int {
        return app_session_destroy_where(static function (string $content) use ($accountId): bool {
            return app_session_file_matches_int($content, 'admin', $accountId)
                || app_session_file_matches_int($content, 'id', $accountId);
        });
    }
}

if (!function_exists('app_current_account')) {
    function app_current_account(): ?array {
        app_session_start();
        if (!isset($_SESSION['account']) || !is_array($_SESSION['account'])) {
            return null;
        }

        return $_SESSION['account'];
    }
}

if (!function_exists('app_current_account_fresh')) {
    function app_current_account_fresh(): ?array {
        static $cache = null;
        static $loaded = false;

        if ($loaded) {
            return $cache;
        }

        $account = app_current_account();
        if ($account === null) {
            $loaded = true;
            $cache = null;
            return null;
        }

        if (empty($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            $loaded = true;
            $cache = $account;
            return $account;
        }

        $stmt = $GLOBALS['pdo']->prepare("
            SELECT
                a.id,
                a.tenant_id,
                a.username,
                a.nama,
                a.role,
                a.is_active,
                t.name AS tenant_name,
                t.status AS tenant_status,
                t.expired_at AS tenant_expired_at
            FROM admin_users a
            JOIN tenants t ON t.id = a.tenant_id
            WHERE a.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int) ($account['id'] ?? 0)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $loaded = true;
            $cache = null;
            return null;
        }

        $cache = [
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'username' => $row['username'],
            'nama' => $row['nama'],
            'role' => $row['role'],
            'tenant_name' => $row['tenant_name'],
            'tenant_status' => $row['tenant_status'],
            'tenant_expired_at' => $row['tenant_expired_at'],
            'is_active' => (int) $row['is_active'],
            'is_superadmin' => $row['role'] === 'superadmin',
        ];

        $loaded = true;
        return $cache;
    }
}

if (!function_exists('app_account_access_issue')) {
    function app_account_access_issue(?array $account = null): ?string {
        $account = $account ?? app_current_account_fresh();
        if ($account === null) {
            return 'unauthorized';
        }

        if (!(int) ($account['is_active'] ?? 0)) {
            return 'Akun dashboard nonaktif';
        }

        if ((string) ($account['tenant_status'] ?? 'active') !== 'active') {
            return 'Tenant nonaktif';
        }

        $expiredAt = trim((string) ($account['tenant_expired_at'] ?? ''));
        if ($expiredAt !== '') {
            $expiredAtTs = strtotime($expiredAt);
            if ($expiredAtTs !== false && $expiredAtTs <= time()) {
                return 'Akun tenant sudah expired';
            }
        }

        return null;
    }
}

if (!function_exists('app_current_tenant_expired_at')) {
    function app_current_tenant_expired_at(): ?string {
        $account = app_current_account_fresh();
        if ($account === null || !empty($account['is_superadmin'])) {
            return null;
        }

        $expiredAt = trim((string) ($account['tenant_expired_at'] ?? ''));
        return $expiredAt !== '' ? $expiredAt : null;
    }
}

if (!function_exists('app_require_live_account_api')) {
    function app_require_live_account_api(): array {
        $account = app_current_account_fresh();
        $issue = app_account_access_issue($account);
        if ($issue !== null) {
            app_session_logout();
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $issue]);
            exit;
        }

        $_SESSION['account'] = [
            'id' => (int) $account['id'],
            'tenant_id' => (int) $account['tenant_id'],
            'username' => $account['username'],
            'nama' => $account['nama'],
            'role' => $account['role'],
            'tenant_name' => $account['tenant_name'],
            'is_superadmin' => (bool) $account['is_superadmin'],
        ];
        $_SESSION['tenant_id'] = (int) $account['tenant_id'];
        $_SESSION['admin'] = (int) $account['id'];

        return $_SESSION['account'];
    }
}

if (!function_exists('app_require_live_account_page')) {
    function app_require_live_account_page(): array {
        $account = app_current_account_fresh();
        $issue = app_account_access_issue($account);
        if ($issue !== null) {
            app_session_logout();
            $redirect = app_url('login');
            if ($issue === 'Akun tenant sudah expired') {
                $redirect .= '?session_status=expired';
            } elseif ($issue !== 'unauthorized') {
                $redirect .= '?session_status=inactive';
            }
            header('Location: ' . $redirect);
            exit;
        }

        $_SESSION['account'] = [
            'id' => (int) $account['id'],
            'tenant_id' => (int) $account['tenant_id'],
            'username' => $account['username'],
            'nama' => $account['nama'],
            'role' => $account['role'],
            'tenant_name' => $account['tenant_name'],
            'is_superadmin' => (bool) $account['is_superadmin'],
        ];
        $_SESSION['tenant_id'] = (int) $account['tenant_id'];
        $_SESSION['admin'] = (int) $account['id'];

        return $_SESSION['account'];
    }
}

if (!function_exists('app_render_tenant_expiry_script')) {
    function app_render_tenant_expiry_script(?string $loginPath = 'login.php'): string {
        $expiredAt = app_current_tenant_expired_at();
        if ($expiredAt === null) {
            return '';
        }

        $normalizedLoginPath = trim((string) $loginPath);
        if ($normalizedLoginPath === '') {
            $normalizedLoginPath = 'login';
        }
        $normalizedLoginPath = preg_replace('/\.php$/', '', $normalizedLoginPath) ?: 'login';
        $loginUrl = app_url($normalizedLoginPath);

        $expiredAtJson = json_encode(str_replace(' ', 'T', $expiredAt), JSON_UNESCAPED_SLASHES);
        $loginPathJson = json_encode($loginUrl, JSON_UNESCAPED_SLASHES);

        return <<<HTML
<script>
(function() {
    const expiredAt = {$expiredAtJson};
    const loginPath = {$loginPathJson};
    if (!expiredAt) return;

    const expiredTime = new Date(expiredAt);
    if (Number.isNaN(expiredTime.getTime())) return;

    const runLogout = async () => {
        try {
            await fetch('../api/auth.php?action=logout');
        } catch (error) {
        }
        window.location.href = loginPath + '?session_status=expired';
    };

    const delay = expiredTime.getTime() - Date.now();
    if (delay <= 0) {
        runLogout();
        return;
    }

    window.setTimeout(runLogout, delay);
})();
</script>
HTML;
    }
}

if (!function_exists('app_render_tenant_expiry_banner')) {
    function app_render_tenant_expiry_banner(): string {
        $expiredAt = app_current_tenant_expired_at();
        if ($expiredAt === null) {
            return '';
        }

        $expiredAtJson = json_encode(str_replace(' ', 'T', $expiredAt), JSON_UNESCAPED_SLASHES);
        $expiredAtLabel = htmlspecialchars($expiredAt, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<section class="tenant-expiry-banner" data-tenant-expiry-banner data-expired-at="{$expiredAtLabel}">
    <div class="tenant-expiry-banner__content">
        <span class="tenant-expiry-banner__eyebrow">Masa aktif tenant</span>
        <strong class="tenant-expiry-banner__countdown" data-tenant-expiry-countdown>Memuat countdown...</strong>
        <span class="tenant-expiry-banner__meta">Expired pada <span data-tenant-expiry-label>{$expiredAtLabel}</span></span>
    </div>
</section>
<script>
(function() {
    const expiredAt = {$expiredAtJson};
    if (!expiredAt) return;

    const banner = document.querySelector('[data-tenant-expiry-banner]');
    if (!banner) return;

    const countdownEl = banner.querySelector('[data-tenant-expiry-countdown]');
    const labelEl = banner.querySelector('[data-tenant-expiry-label]');
    const targetTime = new Date(expiredAt);
    if (Number.isNaN(targetTime.getTime())) return;

    if (labelEl) {
        const fullFormatter = new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        labelEl.textContent = fullFormatter.format(targetTime);
    }

    const formatRemaining = (diffMs) => {
        if (diffMs <= 0) {
            return 'Waktu expired sudah habis';
        }

        const totalSeconds = Math.floor(diffMs / 1000);
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        const parts = [];

        if (days > 0) parts.push(days + ' hari');
        if (days > 0 || hours > 0) parts.push(hours + ' jam');
        if (days > 0 || hours > 0 || minutes > 0) parts.push(minutes + ' menit');
        parts.push(seconds + ' detik');

        return parts.join(' ');
    };

    const render = () => {
        if (!countdownEl) return;
        const diff = targetTime.getTime() - Date.now();
        countdownEl.textContent = formatRemaining(diff);
        banner.classList.toggle('is-expired', diff <= 0);
    };

    render();
    window.setInterval(render, 1000);
})();
</script>
HTML;
    }
}

if (!function_exists('app_current_tenant_id')) {
    function app_current_tenant_id(): ?int {
        $account = app_current_account();
        if (!$account) {
            return null;
        }

        $tenantId = (int) ($account['tenant_id'] ?? 0);
        return $tenantId > 0 ? $tenantId : null;
    }
}

if (!function_exists('app_is_superadmin')) {
    function app_is_superadmin(): bool {
        $account = app_current_account();
        return (bool) ($account['is_superadmin'] ?? false);
    }
}

if (!function_exists('app_current_role')) {
    function app_current_role(): ?string {
        $account = app_current_account();
        if (!$account) {
            return null;
        }

        $role = trim((string) ($account['role'] ?? ''));
        return $role !== '' ? $role : null;
    }
}

if (!function_exists('app_has_owner_access')) {
    function app_has_owner_access(): bool {
        $role = app_current_role();
        return in_array($role, ['owner', 'admin', 'superadmin'], true);
    }
}

if (!function_exists('app_is_tenant_account')) {
    function app_is_tenant_account(): bool {
        return app_current_account() !== null && !app_is_superadmin();
    }
}

if (!function_exists('app_require_auth_api')) {
    function app_require_auth_api(): array {
        $account = app_require_live_account_api();
        if ($account === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        return $account;
    }
}

if (!function_exists('app_require_auth_page')) {
    function app_require_auth_page(): array {
        $account = app_require_live_account_page();
        if ($account === null) {
            header('Location: ' . app_url('login'));
            exit;
        }

        return $account;
    }
}

if (!function_exists('app_require_owner_api')) {
    function app_require_owner_api(): array {
        $account = app_require_auth_api();
        if (!app_has_owner_access()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Fitur MikroTik khusus owner']);
            exit;
        }

        return $account;
    }
}

if (!function_exists('app_require_owner_page')) {
    function app_require_owner_page(): array {
        $account = app_require_auth_page();
        if (!app_has_owner_access()) {
            header('Location: ' . app_url('dashboard'));
            exit;
        }

        return $account;
    }
}

if (!function_exists('app_require_superadmin_api')) {
    function app_require_superadmin_api(): array {
        $account = app_require_auth_api();
        if (!app_is_superadmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses khusus superadmin']);
            exit;
        }

        return $account;
    }
}

if (!function_exists('app_require_superadmin_page')) {
    function app_require_superadmin_page(): array {
        $account = app_require_auth_page();
        if (!app_is_superadmin()) {
            header('Location: ' . app_url('dashboard'));
            exit;
        }

        return $account;
    }
}

if (!function_exists('app_require_tenant_panel_page')) {
    function app_require_tenant_panel_page(): array {
        $account = app_require_auth_page();
        if (app_is_superadmin()) {
            header('Location: ' . app_url('superadmin'));
            exit;
        }

        return $account;
    }
}

if (!function_exists('app_require_tenant_panel_api')) {
    function app_require_tenant_panel_api(): array {
        $account = app_require_auth_api();
        if (app_is_superadmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Panel tenant tidak tersedia untuk superadmin']);
            exit;
        }

        return $account;
    }
}

if (!function_exists('app_login_redirect_path')) {
    function app_login_redirect_path(?array $account = null): string {
        $account = $account ?? app_current_account();
        if (!$account) {
            return 'login';
        }

        return !empty($account['is_superadmin']) ? 'superadmin' : 'dashboard';
    }
}

if (!function_exists('app_cache_key')) {
    function app_cache_key(string $base, ?int $tenantId = null): string {
        $tenantId = $tenantId ?: app_current_tenant_id();
        if ($tenantId === null) {
            return $base . ':guest';
        }

        return $base . ':tenant:' . $tenantId;
    }
}
