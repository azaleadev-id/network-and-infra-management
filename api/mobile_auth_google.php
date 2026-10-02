<?php
require_once __DIR__ . '/mobile_bootstrap.php';

function mobile_fetch_google_identity(string $idToken): ?array
{
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $response = @file_get_contents($url);
    if ($response === false) {
        return null;
    }

    $payload = json_decode($response, true);
    if (!is_array($payload) || !empty($payload['error_description'])) {
        return null;
    }

    return $payload;
}

function mobile_google_allowed_client_ids(): array
{
    $values = [];

    foreach ([
        env_value('GOOGLE_WEB_CLIENT_ID', ''),
        env_value('GOOGLE_ALLOWED_CLIENT_IDS', ''),
    ] as $rawValue) {
        foreach (preg_split('/[\s,]+/', trim((string) $rawValue)) ?: [] as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $values[] = $item;
            }
        }
    }

    return array_values(array_unique($values));
}

function mobile_google_identity_matches_client(array $googleIdentity, array $allowedClientIds): bool
{
    if ($allowedClientIds === []) {
        return true;
    }

    $audience = trim((string) ($googleIdentity['aud'] ?? ''));
    $authorizedParty = trim((string) ($googleIdentity['azp'] ?? ''));

    foreach ([$audience, $authorizedParty] as $clientId) {
        if ($clientId !== '' && in_array($clientId, $allowedClientIds, true)) {
            return true;
        }
    }

    return false;
}

mobile_require_method('POST');

$idToken = trim((string) mobile_request_value('id_token', ''));
if ($idToken === '') {
    mobile_json_response([
        'success' => false,
        'message' => 'ID token Google wajib dikirim',
    ], 422);
}

$googleIdentity = mobile_fetch_google_identity($idToken);
if ($googleIdentity === null) {
    mobile_json_response([
        'success' => false,
        'message' => 'Token Google tidak valid atau gagal diverifikasi',
    ], 401);
}

$allowedClientIds = mobile_google_allowed_client_ids();
if (!mobile_google_identity_matches_client($googleIdentity, $allowedClientIds)) {
    mobile_json_response([
        'success' => false,
        'message' => 'Client Google tidak cocok',
    ], 401);
}

$email = strtolower(trim((string) ($googleIdentity['email'] ?? '')));
if ($email === '') {
    mobile_json_response([
        'success' => false,
        'message' => 'Email Google tidak ditemukan',
    ], 401);
}

$queryParts = [];
if (mobile_has_column('admin_users', 'google_email')) {
    $queryParts[] = 'LOWER(a.google_email) = ?';
}
if (mobile_has_column('admin_users', 'email')) {
    $queryParts[] = 'LOWER(a.email) = ?';
}

if ($queryParts === []) {
    mobile_json_response([
        'success' => false,
        'message' => 'Schema akun Google belum siap di server',
    ], 500);
}

$values = array_fill(0, count($queryParts), $email);
$sql = "
    SELECT
        " . mobile_select_account_columns() . "
    FROM admin_users a
    JOIN tenants t ON t.id = a.tenant_id
    WHERE (" . implode(' OR ', $queryParts) . ")
    LIMIT 1
";
$stmt = $GLOBALS['pdo']->prepare($sql);
$stmt->execute($values);
$account = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

$issue = mobile_account_access_issue($account);
if ($issue !== null) {
    mobile_json_response([
        'success' => false,
        'message' => $issue,
    ], 401);
}

if ($account === null) {
    mobile_json_response([
        'success' => false,
        'message' => 'Akun Google belum terhubung ke tenant mana pun',
        'google_email' => $email,
        'google_name' => (string) ($googleIdentity['name'] ?? ''),
    ], 404);
}

$updates = [];
$updateValues = [];

if (mobile_has_column('admin_users', 'google_id') && empty($account['google_id']) && !empty($googleIdentity['sub'])) {
    $updates[] = 'google_id = ?';
    $updateValues[] = (string) $googleIdentity['sub'];
}
if (mobile_has_column('admin_users', 'google_email') && empty($account['google_email'])) {
    $updates[] = 'google_email = ?';
    $updateValues[] = $email;
}
if (mobile_has_column('admin_users', 'avatar_url') && empty($account['avatar_url']) && !empty($googleIdentity['picture'])) {
    $updates[] = 'avatar_url = ?';
    $updateValues[] = (string) $googleIdentity['picture'];
}
if (mobile_has_column('admin_users', 'login_provider')) {
    $updates[] = 'login_provider = ?';
    $updateValues[] = 'google';
}

if ($updates !== []) {
    $updateValues[] = (int) $account['id'];
    $stmt = $GLOBALS['pdo']->prepare(
        'UPDATE admin_users SET ' . implode(', ', $updates) . ' WHERE id = ?'
    );
    $stmt->execute($updateValues);
    $account = mobile_fetch_account_by_id((int) $account['id']) ?? $account;
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
    'message' => 'Login Google berhasil',
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
    'google' => [
        'email' => $email,
        'name' => (string) ($googleIdentity['name'] ?? ''),
        'picture' => (string) ($googleIdentity['picture'] ?? ''),
    ],
]);
