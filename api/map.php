<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_client.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];
$mkConfig = app_mikrotik_config($tenantId);

function mikrotik_normalize_rows_local($result): array
{
    if (!is_array($result) || isset($result['!trap']) || isset($result['!fatal'])) {
        return [];
    }

    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

function get_mikrotik_remote_lookup(array $mkConfig, int $tenantId): array
{
    if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        return app_mikrotik_bridge_remote_lookup($tenantId);
    }

    $api = new MikroTikClient();
    $api->transport = $mkConfig['transport'] ?? 'socket';
    $api->port = $mkConfig['port'] ?? 8728;
    $api->timeout = $mkConfig['timeout'] ?? 1;
    $api->attempts = $mkConfig['attempts'] ?? 1;
    $api->delay = $mkConfig['delay'] ?? 0;
    $api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
    $api->restPort = $mkConfig['rest_port'] ?? 443;
    $api->restPath = $mkConfig['rest_path'] ?? '/rest';
    $api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);

    if (!$api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
        return app_mikrotik_bridge_remote_lookup($tenantId);
    }

    $lookup = [];
    foreach (mikrotik_normalize_rows_local($api->comm('/ppp/active/print')) as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        $address = trim((string) ($item['address'] ?? ''));
        if ($username !== '' && filter_var($address, FILTER_VALIDATE_IP)) {
            $lookup[$username] = $address;
        }
    }
    $api->disconnect();
    return $lookup;
}

$cacheKey = app_cache_key('map_topology', $tenantId);
$cacheTtl = (int) env_value('MAP_CACHE_TTL', 30);
$cached = app_cache_get($cacheKey, $cacheTtl);
if ($cached !== null) {
    echo json_encode($cached);
    exit;
}

try {
    $remoteLookup = get_mikrotik_remote_lookup($mkConfig, $tenantId);

    $stmt = $pdo->prepare("SELECT id, nama, tipe, koordinat FROM lokasi WHERE tenant_id = ? AND koordinat IS NOT NULL AND koordinat != ''");
    $stmt->execute([$tenantId]);
    $lokasiData = $stmt->fetchAll();

    $nodes = [];
    $edges = [];

    foreach ($lokasiData as $lok) {
        $coords = array_map('trim', explode(',', $lok['koordinat']));
        if (count($coords) === 2) {
            $nodes[] = [
                'id' => 'L' . $lok['id'],
                'name' => $lok['nama'],
                'type' => $lok['tipe'],
                'lat' => (float) $coords[0],
                'lng' => (float) $coords[1]
            ];
        }
    }

    $stmtUsers = $pdo->prepare("
        SELECT u.id, u.username, u.nama, u.status, u.remote_ip, u.koordinat AS user_koordinat, l.koordinat AS odp_koordinat
        FROM users u
        LEFT JOIN relasi_jaringan r ON u.id = r.user_id AND u.tenant_id = r.tenant_id
        LEFT JOIN lokasi l ON r.odp_id = l.id AND l.tenant_id = r.tenant_id
        WHERE u.tenant_id = ?
    ");
    $stmtUsers->execute([$tenantId]);
    $usersData = $stmtUsers->fetchAll();

    foreach ($usersData as $u) {
        $rawCoords = $u['user_koordinat'] ?: $u['odp_koordinat'];
        if (!$rawCoords) {
            continue;
        }

        $coords = array_map('trim', explode(',', $rawCoords));
        if (count($coords) === 2) {
            $lat = (float) $coords[0];
            $lng = (float) $coords[1];

            if (!$u['user_koordinat']) {
                $seed = abs(crc32((string) $u['id']));
                $lat += (($seed % 21) - 10) / 10000;
                $lng += (((int) floor($seed / 21) % 21) - 10) / 10000;
            }

            $nodes[] = [
                'id' => 'U' . $u['id'],
                'name' => $u['nama'],
                'username' => $u['username'],
                'type' => 'user',
                'status' => $u['status'],
                'remote_ip' => $remoteLookup[$u['username']] ?? null,
                'lat' => $lat,
                'lng' => $lng
            ];
        }
    }

    $stmtRelasi = $pdo->prepare("SELECT user_id, odp_id, odc_id, server_id FROM relasi_jaringan WHERE tenant_id = ?");
    $stmtRelasi->execute([$tenantId]);
    foreach ($stmtRelasi->fetchAll() as $rel) {
        if ($rel['server_id'] && $rel['odc_id']) {
            $edges[] = ['source' => 'L' . $rel['server_id'], 'target' => 'L' . $rel['odc_id']];
        }
        if ($rel['odc_id'] && $rel['odp_id']) {
            $edges[] = ['source' => 'L' . $rel['odc_id'], 'target' => 'L' . $rel['odp_id']];
        }
        if ($rel['odp_id'] && $rel['user_id']) {
            $edges[] = ['source' => 'L' . $rel['odp_id'], 'target' => 'U' . $rel['user_id']];
        }
    }

    $payload = [
        'success' => true,
        'nodes' => $nodes,
        'edges' => $edges,
        'summary' => [
            'total_nodes' => count($nodes),
            'total_edges' => count($edges)
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
