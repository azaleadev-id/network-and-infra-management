<?php
require_once __DIR__ . '/mobile_bootstrap.php';

mobile_require_method('POST');

$currentAccount = mobile_require_account();
$currentRole = (string) ($currentAccount['role'] ?? '');
if (!in_array($currentRole, ['superadmin', 'superuser', 'owner'], true)) {
    mobile_json_response([
        'success' => false,
        'message' => 'Akses link tenant hanya untuk akun owner multi-tenant',
    ], 403);
}

$username = trim((string) mobile_request_value('username', ''));
$password = (string) mobile_request_value('password', '');

if ($username === '' || $password === '') {
    mobile_json_response([
        'success' => false,
        'message' => 'Username dan password tenant wajib diisi',
    ], 422);
}

$account = mobile_fetch_account_by_username($username);
$issue = mobile_account_access_issue($account);
if ($issue !== null || !$account || !password_verify($password, (string) $account['password'])) {
    mobile_json_response([
        'success' => false,
        'message' => $issue ?? 'Username/password tenant tidak valid',
    ], 401);
}

$tenantId = (int) ($account['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    mobile_json_response([
        'success' => false,
        'message' => 'Tenant target tidak valid',
    ], 422);
}

if (mobile_has_column('tenants', 'owner_admin_user_id')) {
    $tenantStmt = $GLOBALS['pdo']->prepare('SELECT id, owner_admin_user_id FROM tenants WHERE id = ? LIMIT 1');
    $tenantStmt->execute([$tenantId]);
    $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$tenantRow) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tenant tidak ditemukan',
        ], 404);
    }

    $currentOwnerId = (int) ($currentAccount['id'] ?? 0);
    $linkedOwnerId = (int) ($tenantRow['owner_admin_user_id'] ?? 0);

    if ($linkedOwnerId > 0 && $linkedOwnerId !== $currentOwnerId) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tenant ini sudah terhubung ke owner lain',
        ], 409);
    }

    if ($linkedOwnerId !== $currentOwnerId) {
        $GLOBALS['pdo']->prepare('UPDATE tenants SET owner_admin_user_id = ? WHERE id = ?')
            ->execute([$currentOwnerId, $tenantId]);
    }
}

mobile_json_response([
    'success' => true,
    'tenant' => [
        'id' => (int) $account['tenant_id'],
        'name' => (string) ($account['tenant_name'] ?? 'Tenant'),
        'slug' => $account['tenant_slug'] ?? null,
        'owner_name' => $account['owner_name'] ?? null,
        'phone' => $account['tenant_phone'] ?? null,
        'status' => $account['tenant_status'] ?? 'active',
    ],
    'account' => mobile_account_payload($account),
]);
