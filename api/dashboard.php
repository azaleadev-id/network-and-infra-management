<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];

$cacheKey = app_cache_key('dashboard_overview', $tenantId);
$cacheTtl = (int) env_value('DASHBOARD_CACHE_TTL', 10);
$cached = app_cache_get($cacheKey, $cacheTtl);
if ($cached !== null) {
    echo json_encode($cached);
    exit;
}

try {
    $tenantStmt = $pdo->prepare("
        SELECT
            t.id,
            t.name,
            t.slug,
            t.owner_name,
            t.phone,
            t.status,
            (
                SELECT COUNT(*)
                FROM admin_users au
                WHERE au.tenant_id = t.id AND au.is_active = 1
            ) AS active_admin_count
        FROM tenants t
        WHERE t.id = ?
        LIMIT 1
    ");
    $tenantStmt->execute([$tenantId]);
    $tenant = $tenantStmt->fetch() ?: null;

    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM users WHERE tenant_id = ? GROUP BY status");
    $stmt->execute([$tenantId]);
    $stats = $stmt->fetchAll();

    $statistik = [
        'online' => 0,
        'offline' => 0,
        'disabled' => 0
    ];

    foreach ($stats as $stat) {
        if (isset($statistik[$stat['status']])) {
            $statistik[$stat['status']] = (int) $stat['count'];
        }
    }

    $pppoeActive = 0;
    $bridgeSnapshot = app_mikrotik_bridge_snapshot_get($tenantId);
    if (is_array($bridgeSnapshot) && isset($bridgeSnapshot['pppoe_active'])) {
        $pppoeActive = (int) $bridgeSnapshot['pppoe_active'];
    }

    $stmt = $pdo->prepare("SELECT id, username, nama as name, status FROM users WHERE tenant_id = ? ORDER BY nama ASC, id DESC");
    $stmt->execute([$tenantId]);
    $users = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT tipe as title, waktu as time, pesan as message FROM alerts WHERE tenant_id = ? AND status = 'active' ORDER BY waktu DESC LIMIT 5");
    $stmt->execute([$tenantId]);
    $alerts = $stmt->fetchAll();

    $payload = [
        'success' => true,
        'statistik' => $statistik,
        'pppoe_active' => $pppoeActive,
        'users' => $users,
        'odp_status' => [],
        'odc_status' => [],
        'alerts' => $alerts,
        'lokasi' => [],
        'tenant' => [
            'id' => $tenantId,
            'name' => $tenant['name'] ?? ($account['tenant_name'] ?? 'Tenant'),
            'slug' => $tenant['slug'] ?? null,
            'owner_name' => $tenant['owner_name'] ?? null,
            'phone' => $tenant['phone'] ?? null,
            'status' => $tenant['status'] ?? 'active',
            'active_admin_count' => (int) ($tenant['active_admin_count'] ?? 0),
            'current_user_name' => $account['nama'] ?? null,
            'current_user_role' => $account['role'] ?? null,
            'owner_access' => app_has_owner_access(),
        ],
        'meta' => [
            'cached' => false,
            'generated_at' => date(DATE_ATOM),
            'tenant_id' => $tenantId
        ]
    ];

    app_cache_put($cacheKey, $payload);
    echo json_encode($payload);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
