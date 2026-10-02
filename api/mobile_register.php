<?php
require_once __DIR__ . '/mobile_bootstrap.php';

function mobile_tenant_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'tenant';
}

function mobile_tenant_unique_slug(PDO $pdo, string $baseSlug): string
{
    $slug = mobile_tenant_slugify($baseSlug);
    $candidate = $slug;
    $suffix = 2;

    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$candidate]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }

        $candidate = $slug . '-' . $suffix;
        $suffix++;
    }
}

mobile_require_method('POST');

$tenantName = trim((string) mobile_request_value('tenant_name', ''));
$ownerName = trim((string) mobile_request_value('owner_name', ''));
$phone = trim((string) mobile_request_value('phone', ''));
$username = trim((string) mobile_request_value('username', ''));
$password = (string) mobile_request_value('password', '');
$accountName = trim((string) mobile_request_value('account_name', ''));
$authMode = trim((string) mobile_request_value('auth_mode', 'manual'));
$googleEmail = strtolower(trim((string) mobile_request_value('google_email', '')));

if ($username === '' || $password === '') {
    mobile_json_response([
        'success' => false,
        'message' => 'Username dan password wajib diisi',
    ], 422);
}

if (strlen($password) < 6) {
    mobile_json_response([
        'success' => false,
        'message' => 'Password minimal 6 karakter',
    ], 422);
}

if (!in_array($authMode, ['manual', 'google'], true)) {
    $authMode = 'manual';
}

if ($authMode === 'google') {
    if ($googleEmail === '') {
        mobile_json_response([
            'success' => false,
            'message' => 'Akun Google wajib dihubungkan lebih dulu saat register mode Google',
        ], 422);
    }

    if (!filter_var($googleEmail, FILTER_VALIDATE_EMAIL)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Email Google tidak valid',
        ], 422);
    }
}

$accountName = $accountName !== '' ? $accountName : $username;
$tenantName = $tenantName !== '' ? $tenantName : $accountName;
$ownerName = $ownerName !== '' ? $ownerName : $accountName;

try {
    $stmt = $GLOBALS['pdo']->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn()) {
        mobile_json_response([
            'success' => false,
            'message' => 'Username sudah terdaftar',
        ], 422);
    }

    if ($googleEmail !== '' && mobile_has_column('admin_users', 'google_email')) {
        $stmt = $GLOBALS['pdo']->prepare('SELECT id FROM admin_users WHERE LOWER(google_email) = ? LIMIT 1');
        $stmt->execute([$googleEmail]);
        if ($stmt->fetchColumn()) {
            mobile_json_response([
                'success' => false,
                'message' => 'Email Google sudah terhubung ke akun lain',
            ], 422);
        }
    }

    if ($googleEmail !== '' && mobile_has_column('admin_users', 'email')) {
        $stmt = $GLOBALS['pdo']->prepare('SELECT id FROM admin_users WHERE LOWER(email) = ? LIMIT 1');
        $stmt->execute([$googleEmail]);
        if ($stmt->fetchColumn()) {
            mobile_json_response([
                'success' => false,
                'message' => 'Email Google sudah dipakai akun lain',
            ], 422);
        }
    }

    $GLOBALS['pdo']->beginTransaction();

    $slug = mobile_tenant_unique_slug($GLOBALS['pdo'], $tenantName);

    $stmt = $GLOBALS['pdo']->prepare("
        INSERT INTO tenants (name, slug, owner_name, phone, status, created_at)
        VALUES (?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->execute([$tenantName, $slug, $ownerName, $phone]);
    $tenantId = (int) $GLOBALS['pdo']->lastInsertId();

    $columns = ['tenant_id', 'username', 'password', 'nama', 'role', 'is_active', 'created_at'];
    $placeholders = ['?', '?', '?', '?', "'owner'", '1', 'NOW()'];
    $values = [
        $tenantId,
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $accountName,
    ];

    if (mobile_has_column('admin_users', 'email')) {
        $columns[] = 'email';
        $placeholders[] = '?';
        $values[] = $googleEmail !== '' ? $googleEmail : null;
    }
    if (mobile_has_column('admin_users', 'google_email')) {
        $columns[] = 'google_email';
        $placeholders[] = '?';
        $values[] = $googleEmail !== '' ? $googleEmail : null;
    }
    if (mobile_has_column('admin_users', 'login_provider')) {
        $columns[] = 'login_provider';
        $placeholders[] = '?';
        $values[] = $authMode;
    }

    $sql = sprintf(
        'INSERT INTO admin_users (%s) VALUES (%s)',
        implode(', ', $columns),
        implode(', ', $placeholders)
    );
    $stmt = $GLOBALS['pdo']->prepare($sql);
    $stmt->execute($values);

    $ownerAdminUserId = (int) $GLOBALS['pdo']->lastInsertId();
    if (mobile_has_column('tenants', 'owner_admin_user_id')) {
        $stmt = $GLOBALS['pdo']->prepare('UPDATE tenants SET owner_admin_user_id = ? WHERE id = ?');
        $stmt->execute([$ownerAdminUserId, $tenantId]);
    }

    $GLOBALS['pdo']->commit();

    mobile_json_response([
        'success' => true,
        'message' => 'Pendaftaran berhasil. Akun mobile sudah aktif dan bisa langsung login. Data tenant lain bisa dilengkapi nanti dari profil/dashboard.',
        'tenant' => [
            'id' => $tenantId,
            'name' => $tenantName,
            'slug' => $slug,
            'status' => 'active',
        ],
    ]);
} catch (Throwable $e) {
    if ($GLOBALS['pdo']->inTransaction()) {
        $GLOBALS['pdo']->rollBack();
    }

    $message = env_value('APP_DEBUG', 'false') === 'true'
        ? $e->getMessage()
        : 'Terjadi kesalahan sistem';

    mobile_json_response([
        'success' => false,
        'message' => $message,
    ], 500);
}
