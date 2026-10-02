<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody !== false ? $rawBody : '', true);

if (!is_array($payload)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Payload bridge tidak valid.'
    ]);
    exit;
}

$providedApiKey = app_mikrotik_bridge_resolve_api_key();
$tenantId = $providedApiKey !== '' ? (int) (app_mikrotik_bridge_tenant_id_by_api_key($providedApiKey) ?? 0) : 0;

if ($tenantId <= 0) {
    $expectedToken = app_mikrotik_bridge_token();
    $providedToken = app_mikrotik_bridge_resolve_token();

    if ($expectedToken !== '' && hash_equals($expectedToken, $providedToken)) {
        $tenantId = (int) ($payload['tenant_id'] ?? $_GET['tenant_id'] ?? 0);
    }
}

if ($tenantId <= 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'API key bridge tidak valid atau tenant tidak ditemukan.'
    ]);
    exit;
}

$snapshot = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : $payload;
$trafficInterface = (string) (($snapshot['meta']['traffic_interface'] ?? '') ?: app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId));

app_mikrotik_bridge_snapshot_put($tenantId, $snapshot);
app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));
app_cache_forget(app_cache_key('map_topology', $tenantId));
app_cache_forget(app_cache_key('dashboard_overview', $tenantId));

echo json_encode([
    'success' => true,
    'message' => 'Snapshot MikroTik berhasil diterima.',
    'tenant_id' => $tenantId,
    'traffic_interface' => $trafficInterface,
]);
