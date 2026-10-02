<?php
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

function mobile_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function mobile_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Method not allowed',
        ], 405);
    }
}

function mobile_request_value(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }

    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }

    static $jsonBody = null;
    static $jsonLoaded = false;
    if (!$jsonLoaded) {
        $jsonLoaded = true;
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        $jsonBody = is_array($decoded) ? $decoded : [];
    }

    return $jsonBody[$key] ?? $default;
}

function mobile_request_scheme(): string
{
    $https = (string) ($_SERVER['HTTPS'] ?? '');
    $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    if (strtolower($https) === 'on' || $https === '1' || strtolower($forwarded) === 'https') {
        return 'https';
    }

    return 'http';
}

function mobile_request_host(): string
{
    $forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
    if ($forwardedHost !== '') {
        return $forwardedHost;
    }

    $httpHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($httpHost !== '') {
        return $httpHost;
    }

    $serverName = trim((string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    if (!in_array($port, [80, 443], true) && !str_contains($serverName, ':')) {
        return $serverName . ':' . $port;
    }

    return $serverName;
}

function mobile_absolute_url(string $path = ''): string
{
    $basePath = rtrim(app_base_url_path(), '/');
    $normalizedPath = ltrim($path, '/');
    $relative = $normalizedPath === ''
        ? ($basePath !== '' ? $basePath : '/')
        : (($basePath !== '' ? $basePath : '') . '/' . $normalizedPath);

    return mobile_request_scheme() . '://' . mobile_request_host() . $relative;
}

function mobile_avatar_public_url(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $value)) {
        return $value;
    }

    return mobile_absolute_url($value);
}

function mobile_avatar_storage_relative_path(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $storagePrefix = 'storage/profile-photos/';
    if (str_starts_with($value, $storagePrefix)) {
        return $value;
    }

    $basePath = rtrim(app_base_url_path(), '/');
    $prefixedStoragePath = ($basePath !== '' ? $basePath . '/' : '/') . $storagePrefix;

    if (str_starts_with($value, $prefixedStoragePath)) {
        return ltrim(substr($value, strlen($basePath)), '/');
    }

    if (preg_match('~^https?://~i', $value)) {
        $parts = parse_url($value);
        $urlPath = trim((string) ($parts['path'] ?? ''));
        if ($urlPath !== '' && str_starts_with($urlPath, $prefixedStoragePath)) {
            return ltrim(substr($urlPath, strlen($basePath)), '/');
        }
    }

    return null;
}

function mobile_delete_profile_photo_file(?string $value): void
{
    $relativePath = mobile_avatar_storage_relative_path($value);
    if ($relativePath === null) {
        return;
    }

    $prefix = 'storage/profile-photos/';
    $relativeStoragePath = ltrim(substr($relativePath, strlen('storage/')), '/');
    if (!str_starts_with($relativePath, $prefix) || $relativeStoragePath === '' || str_contains($relativeStoragePath, '..')) {
        return;
    }

    $fullPath = app_storage_path($relativeStoragePath);
    $profilePhotoDir = realpath(app_storage_path('profile-photos'));
    $resolvedFullPath = realpath($fullPath);
    if ($profilePhotoDir === false || $resolvedFullPath === false) {
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
        return;
    }

    if (str_starts_with(str_replace('\\', '/', $resolvedFullPath), str_replace('\\', '/', $profilePhotoDir) . '/')) {
        @unlink($resolvedFullPath);
    }
}

function mobile_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function mobile_base64url_decode(string $value): string|false
{
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/'));
}

function mobile_token_secret(): string
{
    $secret = trim((string) env_value('APP_KEY', ''));
    if ($secret !== '') {
        return $secret;
    }

    return hash('sha256', (string) env_value('DB_NAME', 'billing-net-mobile-secret'));
}

function mobile_issue_token(array $account, int $ttlSeconds = 604800): string
{
    $role = (string) ($account['role'] ?? '');
    $payload = [
        'sub' => (int) ($account['id'] ?? 0),
        'tenant_id' => (int) ($account['tenant_id'] ?? 0),
        'username' => (string) ($account['username'] ?? ''),
        'role' => $role,
        'tenant_name' => (string) ($account['tenant_name'] ?? ''),
        'is_superuser' => in_array($role, ['superadmin', 'superuser', 'owner'], true),
        'exp' => time() + $ttlSeconds,
    ];

    $encodedPayload = mobile_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $encodedPayload, mobile_token_secret(), true);

    return $encodedPayload . '.' . mobile_base64url_encode($signature);
}

function mobile_read_token_payload(?string $token): ?array
{
    $token = trim((string) $token);
    if ($token === '' || !str_contains($token, '.')) {
        return null;
    }

    [$encodedPayload, $encodedSignature] = explode('.', $token, 2);
    $expectedSignature = mobile_base64url_encode(
        hash_hmac('sha256', $encodedPayload, mobile_token_secret(), true)
    );

    if (!hash_equals($expectedSignature, $encodedSignature)) {
        return null;
    }

    $decoded = mobile_base64url_decode($encodedPayload);
    if (!is_string($decoded) || $decoded === '') {
        return null;
    }

    $payload = json_decode($decoded, true);
    if (!is_array($payload)) {
        return null;
    }

    $expiresAt = (int) ($payload['exp'] ?? 0);
    if ($expiresAt <= time()) {
        return null;
    }

    return $payload;
}

function mobile_bearer_token(): ?string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    $header = (string) $value;
                    break;
                }
            }
        }
    }

    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    $header = (string) $value;
                    break;
                }
            }
        }
    }

    if ($header !== '' && preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim((string) $matches[1]);
    }

    $token = mobile_request_value('access_token', '');
    return $token !== '' ? (string) $token : null;
}

function mobile_has_column(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $GLOBALS['pdo']->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    return $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
}

function mobile_select_account_columns(): string
{
    $columns = [
        'a.id',
        'a.tenant_id',
        'a.username',
        'a.password',
        'a.nama',
        'a.role',
        'a.is_active',
        't.name AS tenant_name',
        't.slug AS tenant_slug',
        't.owner_name',
        't.phone AS tenant_phone',
        't.status AS tenant_status',
        't.expired_at AS tenant_expired_at',
    ];

    foreach ([
        'email',
        'google_id',
        'google_email',
        'avatar_url',
        'login_provider',
        'can_scan_payment',
        'parent_admin_user_id',
    ] as $column) {
        if (mobile_has_column('admin_users', $column)) {
            $columns[] = "a.{$column}";
        }
    }

    return implode(",\n            ", $columns);
}

function mobile_fetch_account_by_username(string $username): ?array
{
    $sql = "
        SELECT
            " . mobile_select_account_columns() . "
        FROM admin_users a
        JOIN tenants t ON t.id = a.tenant_id
        WHERE a.username = ?
        LIMIT 1
    ";
    $stmt = $GLOBALS['pdo']->prepare($sql);
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function mobile_fetch_account_by_login(string $login): ?array
{
    $login = trim($login);
    if ($login === '') {
        return null;
    }

    $conditions = ['a.username = ?'];
    $values = [$login];

    if (mobile_has_column('admin_users', 'email')) {
        $conditions[] = 'LOWER(a.email) = ?';
        $values[] = strtolower($login);
    }

    $sql = "
        SELECT
            " . mobile_select_account_columns() . "
        FROM admin_users a
        JOIN tenants t ON t.id = a.tenant_id
        WHERE " . implode(' OR ', $conditions) . "
        LIMIT 1
    ";
    $stmt = $GLOBALS['pdo']->prepare($sql);
    $stmt->execute($values);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function mobile_fetch_account_by_id(int $accountId): ?array
{
    $sql = "
        SELECT
            " . mobile_select_account_columns() . "
        FROM admin_users a
        JOIN tenants t ON t.id = a.tenant_id
        WHERE a.id = ?
        LIMIT 1
    ";
    $stmt = $GLOBALS['pdo']->prepare($sql);
    $stmt->execute([$accountId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function mobile_account_access_issue(?array $account): ?string
{
    if ($account === null) {
        return 'Unauthorized';
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

function mobile_account_payload(array $account, ?string $accessToken = null): array
{
    $role = (string) ($account['role'] ?? 'owner');
    $payload = [
        'id' => (int) ($account['id'] ?? 0),
        'tenant_id' => (int) ($account['tenant_id'] ?? 0),
        'tenant_name' => (string) ($account['tenant_name'] ?? 'Tenant'),
        'tenant_slug' => $account['tenant_slug'] ?? null,
        'username' => (string) ($account['username'] ?? ''),
        'nama' => (string) ($account['nama'] ?? ''),
        'role' => $role,
        'is_superuser' => in_array($role, ['superadmin', 'superuser', 'owner'], true),
        'google_linked' => !empty($account['google_id']) || !empty($account['google_email']),
        'login_provider' => $account['login_provider'] ?? 'manual',
        'can_scan_payment' => (int) ($account['can_scan_payment'] ?? 0) === 1,
        'access_token' => $accessToken,
    ];

    if (array_key_exists('email', $account)) {
        $payload['email'] = $account['email'];
    }
    if (array_key_exists('google_email', $account)) {
        $payload['google_email'] = $account['google_email'];
    }
    if (array_key_exists('avatar_url', $account)) {
        $payload['avatar_url'] = mobile_avatar_public_url($account['avatar_url']);
    }
    if (array_key_exists('parent_admin_user_id', $account)) {
        $payload['parent_admin_user_id'] = (int) ($account['parent_admin_user_id'] ?? 0);
    }

    return $payload;
}

function mobile_require_account(): array
{
    $token = mobile_bearer_token();
    $payload = mobile_read_token_payload($token);
    if ($payload === null) {
        mobile_json_response([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401);
    }

    $account = mobile_fetch_account_by_id((int) ($payload['sub'] ?? 0));
    $issue = mobile_account_access_issue($account);
    if ($issue !== null) {
        mobile_json_response([
            'success' => false,
            'message' => $issue,
        ], 401);
    }

    return $account;
}

function mobile_filter_tenant_ids(array $values): array
{
    $tenantIds = [];
    foreach ($values as $value) {
        $tenantId = (int) $value;
        if ($tenantId > 0) {
            $tenantIds[] = $tenantId;
        }
    }

    return array_values(array_unique($tenantIds));
}

function mobile_requested_tenant_ids(): array
{
    $values = [];

    $tenantIds = mobile_request_value('tenant_ids', []);
    if (is_string($tenantIds)) {
        $values = array_merge($values, preg_split('/[\s,]+/', $tenantIds) ?: []);
    } elseif (is_array($tenantIds)) {
        $values = array_merge($values, $tenantIds);
    }

    $singleTenantId = (int) mobile_request_value('tenant_id', 0);
    if ($singleTenantId > 0) {
        $values[] = $singleTenantId;
    }

    return mobile_filter_tenant_ids($values);
}

function mobile_owned_tenant_ids(array $account): array
{
    $baseTenantId = (int) ($account['tenant_id'] ?? 0);
    $role = strtolower((string) ($account['role'] ?? ''));

    if (!in_array($role, ['superadmin', 'superuser', 'owner'], true)) {
        return $baseTenantId > 0 ? [$baseTenantId] : [];
    }

    $tenantIds = [];
    if ($baseTenantId > 0) {
        $tenantIds[] = $baseTenantId;
    }

    if (!mobile_has_column('tenants', 'owner_admin_user_id')) {
        return mobile_filter_tenant_ids($tenantIds);
    }

    $stmt = $GLOBALS['pdo']->prepare('
        SELECT id
        FROM tenants
        WHERE owner_admin_user_id = ?
        ORDER BY id ASC
    ');
    $stmt->execute([(int) ($account['id'] ?? 0)]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tenantId) {
        $tenantIds[] = (int) $tenantId;
    }

    return mobile_filter_tenant_ids($tenantIds);
}

function mobile_parent_owner_account(array $account): ?array
{
    if (!mobile_has_column('admin_users', 'parent_admin_user_id')) {
        return null;
    }

    $parentAdminUserId = (int) ($account['parent_admin_user_id'] ?? 0);
    if ($parentAdminUserId <= 0) {
        return null;
    }

    $parentAccount = mobile_fetch_account_by_id($parentAdminUserId);
    if (!$parentAccount) {
        return null;
    }

    $parentRole = strtolower((string) ($parentAccount['role'] ?? ''));
    if (!in_array($parentRole, ['owner', 'superadmin', 'superuser'], true)) {
        return null;
    }

    return $parentAccount;
}

function mobile_accessible_tenant_ids(array $account): array
{
    $role = strtolower((string) ($account['role'] ?? ''));
    $baseTenantId = (int) ($account['tenant_id'] ?? 0);

    if (in_array($role, ['superadmin', 'superuser', 'owner'], true)) {
        $ownedTenantIds = mobile_owned_tenant_ids($account);
        return $ownedTenantIds ?: ($baseTenantId > 0 ? [$baseTenantId] : []);
    }

    $parentOwner = mobile_parent_owner_account($account);
    if ($parentOwner) {
        $parentTenantIds = mobile_owned_tenant_ids($parentOwner);
        if ($parentTenantIds) {
            if ($baseTenantId > 0 && !in_array($baseTenantId, $parentTenantIds, true)) {
                $parentTenantIds[] = $baseTenantId;
            }
            return mobile_filter_tenant_ids($parentTenantIds);
        }
    }

    return $baseTenantId > 0 ? [$baseTenantId] : [];
}

function mobile_fetch_tenants_by_ids(array $tenantIds): array
{
    $tenantIds = mobile_filter_tenant_ids($tenantIds);
    if (!$tenantIds) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($tenantIds), '?'));
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT id, name, slug, owner_name, phone, status
        FROM tenants
        WHERE id IN ($placeholders)
        ORDER BY name ASC, id ASC
    ");
    $stmt->execute($tenantIds);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mobile_summarize_tenants(array $tenants, array $account): array
{
    if (count($tenants) === 1) {
        return $tenants[0];
    }

    return [
        'id' => 0,
        'name' => count($tenants) . ' tenant aktif',
        'slug' => 'multi-tenant',
        'owner_name' => $account['nama'] ?? null,
        'phone' => null,
        'status' => 'active',
    ];
}

function mobile_resolve_tenant_ids(array $account): array
{
    $requestedTenantIds = mobile_requested_tenant_ids();
    $allowedTenantIds = mobile_accessible_tenant_ids($account);

    if (!$allowedTenantIds) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tenant tidak valid untuk akun ini.',
        ], 403);
    }

    if (!$requestedTenantIds) {
        return $allowedTenantIds;
    }

    $resolvedTenantIds = array_values(array_intersect($requestedTenantIds, $allowedTenantIds));
    if (!$resolvedTenantIds) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tenant yang dipilih tidak diizinkan untuk akun ini.',
        ], 403);
    }

    return $resolvedTenantIds;
}

function mobile_resolve_tenant_id(array $account): int
{
    $tenantIds = mobile_resolve_tenant_ids($account);
    if (!$tenantIds) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tenant aktif belum dipilih.',
        ], 422);
    }

    return (int) $tenantIds[0];
}

function mobile_forget_tenant_cache(int $tenantId): void
{
    if (!function_exists('app_cache_forget') || !function_exists('app_cache_key')) {
        return;
    }

    app_cache_forget(app_cache_key('dashboard_overview', $tenantId));
}
