<?php
require_once __DIR__ . '/mobile_bootstrap.php';

if (mobile_request_value('action') === 'logout') {
    mobile_json_response([
        'success' => true,
        'message' => 'Logout berhasil',
    ]);
}

mobile_require_method('POST');

$username = trim((string) mobile_request_value('username', ''));
$password = (string) mobile_request_value('password', '');

if ($username === '' || $password === '') {
    mobile_json_response([
        'success' => false,
        'message' => 'Username/email dan password wajib diisi',
    ], 422);
}

$account = mobile_fetch_account_by_login($username);
$issue = mobile_account_access_issue($account);
if ($issue !== null || !$account || !password_verify($password, (string) $account['password'])) {
    $message = $issue ?? 'Login gagal';
    mobile_json_response([
        'success' => false,
        'message' => $message,
    ], 401);
}

$GLOBALS['pdo']->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([
    (int) $account['id'],
]);

$accessToken = mobile_issue_token($account);
$tenantIds = mobile_accessible_tenant_ids($account);
$tenants = mobile_fetch_tenants_by_ids($tenantIds);
$tenantSummary = mobile_summarize_tenants($tenants, $account);

mobile_json_response([
    'success' => true,
    'message' => 'Login berhasil',
    'access_token' => $accessToken,
    'account' => mobile_account_payload($account, $accessToken),
    'tenant' => [
        'id' => (int) ($tenantSummary['id'] ?? 0),
        'name' => (string) ($tenantSummary['name'] ?? 'Tenant'),
        'slug' => $tenantSummary['slug'] ?? null,
        'owner_name' => $tenantSummary['owner_name'] ?? null,
        'phone' => $tenantSummary['phone'] ?? null,
        'status' => $tenantSummary['status'] ?? 'active',
    ],
    'tenants' => array_map(static function (array $tenant): array {
        return [
            'id' => (int) ($tenant['id'] ?? 0),
            'name' => $tenant['name'] ?? 'Tenant',
            'slug' => $tenant['slug'] ?? null,
            'owner_name' => $tenant['owner_name'] ?? null,
            'phone' => $tenant['phone'] ?? null,
            'status' => $tenant['status'] ?? 'active',
        ];
    }, $tenants),
]);
