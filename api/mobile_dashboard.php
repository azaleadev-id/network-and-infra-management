<?php
require_once __DIR__ . '/mobile_bootstrap.php';
require_once __DIR__ . '/../config/mikrotik_client.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

mobile_require_method('GET');

function mobile_dashboard_normalize_rows($result): array
{
    if (!is_array($result) || isset($result['!trap']) || isset($result['!fatal'])) {
        return [];
    }

    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

function mobile_dashboard_extract_error($result): ?string
{
    if (!is_array($result)) {
        return null;
    }

    $messages = [];
    foreach (['!trap', '!fatal'] as $bucket) {
        if (!isset($result[$bucket]) || !is_array($result[$bucket])) {
            continue;
        }

        foreach ($result[$bucket] as $entry) {
            if (is_array($entry) && !empty($entry['message'])) {
                $messages[] = $entry['message'];
            }
        }
    }

    return $messages === [] ? null : implode('; ', array_unique($messages));
}

function mobile_dashboard_first_int(array $item, array $keys, int $default = 0): int
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $item) && $item[$key] !== '' && $item[$key] !== null) {
            return (int) $item[$key];
        }
    }

    return $default;
}

function mobile_dashboard_traffic_snapshot_from_bridge(int $tenantId, string $trafficInterface): ?array
{
    $snapshot = app_mikrotik_bridge_snapshot_get($tenantId);
    if (!is_array($snapshot)) {
        return null;
    }

    $snapshot['tenant_id'] = $tenantId;
    $snapshot['interface_name'] = $trafficInterface;
    $snapshot['meta'] = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
    $snapshot['meta']['tenant_id'] = $tenantId;
    $snapshot['meta']['traffic_interface'] = $trafficInterface;
    $snapshot['meta']['bridge_fallback'] = true;
    return $snapshot;
}

function mobile_dashboard_fetch_traffic_monitoring(array $tenant): array
{
    $tenantId = (int) ($tenant['id'] ?? 0);
    $tenantName = (string) ($tenant['name'] ?? 'Tenant');
    $mkConfig = app_mikrotik_config($tenantId);
    $trafficInterface = (string) app_setting_get('traffic_interface', $mkConfig['interface'] ?? 'ether1', $tenantId);
    $cacheKey = app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId);
    $cacheTtl = (int) env_value('MIKROTIK_CACHE_TTL', 15);
    $liveMode = (string) ($_GET['live'] ?? '0') === '1';

    $basePayload = [
        'tenant_id' => $tenantId,
        'tenant_name' => $tenantName,
        'interface_name' => $trafficInterface,
        'success' => false,
        'traffic' => ['rx' => 0, 'tx' => 0, 'rx_packet_rate' => 0, 'tx_packet_rate' => 0],
        'interface_stats' => [
            'name' => $trafficInterface,
            'tx_byte' => 0,
            'rx_byte' => 0,
            'tx_packet' => 0,
            'rx_packet' => 0,
            'tx_drop' => 0,
            'rx_drop' => 0,
            'tx_error' => 0,
            'rx_error' => 0,
        ],
        'meta' => [
            'tenant_id' => $tenantId,
            'traffic_interface' => $trafficInterface,
            'mobile_api' => true,
            'generated_at' => date(DATE_ATOM),
        ],
    ];

    if (!$liveMode && $cacheTtl > 0) {
        $cached = app_cache_get($cacheKey, $cacheTtl);
        if (is_array($cached)) {
            $cached['tenant_id'] = $tenantId;
            $cached['tenant_name'] = $tenantName;
            $cached['interface_name'] = $trafficInterface;
            $cached['meta'] = is_array($cached['meta'] ?? null) ? $cached['meta'] : [];
            $cached['meta']['tenant_id'] = $tenantId;
            $cached['meta']['traffic_interface'] = $trafficInterface;
            $cached['meta']['mobile_api'] = true;
            return $cached;
        }
    }

    if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        $bridgeSnapshot = mobile_dashboard_traffic_snapshot_from_bridge($tenantId, $trafficInterface);
        if ($bridgeSnapshot !== null) {
            $bridgeSnapshot['tenant_name'] = $tenantName;
            return $bridgeSnapshot;
        }

        $basePayload['message'] = 'Konfigurasi MikroTik tenant ini belum lengkap.';
        return $basePayload;
    }

    $api = new MikroTikClient();
    $api->transport = $mkConfig['transport'] ?? 'socket';
    $api->port = $mkConfig['port'] ?? 8728;
    $api->timeout = 2;
    $api->attempts = 1;
    $api->delay = 0;
    $api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
    $api->restPort = $mkConfig['rest_port'] ?? 443;
    $api->restPath = $mkConfig['rest_path'] ?? '/rest';
    $api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);

    if (!$api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
        $bridgeSnapshot = mobile_dashboard_traffic_snapshot_from_bridge($tenantId, $trafficInterface);
        if ($bridgeSnapshot !== null) {
            $bridgeSnapshot['tenant_name'] = $tenantName;
            return $bridgeSnapshot;
        }

        $basePayload['message'] = 'Koneksi ke MikroTik gagal.';
        if (!empty($api->error_str)) {
            $basePayload['debug'] = [
                'host' => $mkConfig['host'],
                'port' => $mkConfig['port'],
                'timeout' => 2,
                'transport' => $mkConfig['transport'] ?? 'socket',
                'error' => $api->error_str,
            ];
        }
        return $basePayload;
    }

    $trafficRaw = $api->comm('/interface/monitor-traffic', ['interface' => $trafficInterface, 'once' => '']);
    $interfacesRaw = $api->comm('/interface/print', ['stats' => '']);
    $api->disconnect();

    $traffic = mobile_dashboard_normalize_rows($trafficRaw);
    $interfacesStats = mobile_dashboard_normalize_rows($interfacesRaw);

    $payload = $basePayload;
    $payload['success'] = true;
    $payload['meta']['transport'] = $api->activeTransport() ?? ($mkConfig['transport'] ?? 'socket');

    if (isset($traffic[0])) {
        $payload['traffic']['rx'] = (int) ($traffic[0]['rx-bits-per-second'] ?? 0);
        $payload['traffic']['tx'] = (int) ($traffic[0]['tx-bits-per-second'] ?? 0);
        $payload['traffic']['rx_packet_rate'] = mobile_dashboard_first_int(
            $traffic[0],
            ['rx-packets-per-second', 'rx-packet-per-second']
        );
        $payload['traffic']['tx_packet_rate'] = mobile_dashboard_first_int(
            $traffic[0],
            ['tx-packets-per-second', 'tx-packet-per-second']
        );
    }

    foreach ($interfacesStats as $item) {
        if (($item['name'] ?? '') !== $trafficInterface) {
            continue;
        }

        $payload['interface_stats'] = [
            'name' => $item['name'] ?? $trafficInterface,
            'type' => $item['type'] ?? '-',
            'running' => ($item['running'] ?? 'false') === 'true',
            'tx_byte' => mobile_dashboard_first_int($item, ['tx-byte', 'fp-tx-byte', 'driver-tx-byte']),
            'rx_byte' => mobile_dashboard_first_int($item, ['rx-byte', 'fp-rx-byte', 'driver-rx-byte']),
            'tx_packet' => mobile_dashboard_first_int($item, ['tx-packet', 'fp-tx-packet', 'driver-tx-packet']),
            'rx_packet' => mobile_dashboard_first_int($item, ['rx-packet', 'fp-rx-packet', 'driver-rx-packet']),
            'tx_drop' => mobile_dashboard_first_int($item, ['tx-drop', 'tx-queue-drop']),
            'rx_drop' => mobile_dashboard_first_int($item, ['rx-drop']),
            'tx_error' => mobile_dashboard_first_int($item, ['tx-error']),
            'rx_error' => mobile_dashboard_first_int($item, ['rx-error']),
        ];
        break;
    }

    $partialErrors = array_filter([
        mobile_dashboard_extract_error($trafficRaw),
        mobile_dashboard_extract_error($interfacesRaw),
    ]);
    if ($partialErrors !== []) {
        $payload['message'] = 'Sebagian data MikroTik gagal dibaca. Detail: ' . implode('; ', array_unique($partialErrors));
    }

    if (!$liveMode && $cacheTtl > 0) {
        app_cache_put($cacheKey, $payload);
    }

    return $payload;
}

function mobile_dashboard_fetch_remote_lookup(array $mkConfig, int $tenantId): array
{
    if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        return app_mikrotik_bridge_remote_lookup($tenantId);
    }

    $api = new MikroTikClient();
    $api->transport = $mkConfig['transport'] ?? 'socket';
    $api->port = $mkConfig['port'] ?? 8728;
    $api->timeout = 2;
    $api->attempts = 1;
    $api->delay = 0;
    $api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
    $api->restPort = $mkConfig['rest_port'] ?? 443;
    $api->restPath = $mkConfig['rest_path'] ?? '/rest';
    $api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);

    if (!$api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
        return app_mikrotik_bridge_remote_lookup($tenantId);
    }

    $lookup = [];
    foreach (mobile_dashboard_normalize_rows($api->comm('/ppp/active/print')) as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        $address = trim((string) ($item['address'] ?? ''));
        if ($username !== '' && filter_var($address, FILTER_VALIDATE_IP)) {
            $lookup[$username] = $address;
        }
    }
    $api->disconnect();

    return $lookup;
}

function mobile_dashboard_user_runtime_state_from_bridge(int $tenantId): ?array
{
    $snapshot = app_mikrotik_bridge_snapshot_get($tenantId);
    if (!is_array($snapshot)) {
        return null;
    }

    $inventory = is_array($snapshot['inventory'] ?? null) ? $snapshot['inventory'] : [];
    $statusByUsername = [];
    $remoteLookup = [];
    $activeLookup = [];

    foreach (($inventory['active'] ?? []) as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        $address = trim((string) ($item['address'] ?? ''));
        if ($username === '') {
            continue;
        }

        $activeLookup[$username] = true;
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            $remoteLookup[$username] = $address;
        }
    }

    foreach (($inventory['secrets'] ?? []) as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        if ($username === '') {
            continue;
        }

        $status = (string) ($item['status'] ?? 'offline');
        if (isset($activeLookup[$username])) {
            $status = 'online';
        }
        $statusByUsername[$username] = in_array($status, ['online', 'offline', 'disabled'], true)
            ? $status
            : 'offline';

        $remoteAddress = trim((string) ($item['remote_address'] ?? ''));
        if (!isset($remoteLookup[$username]) && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            $remoteLookup[$username] = $remoteAddress;
        }
    }

    foreach (array_keys($activeLookup) as $username) {
        if (!isset($statusByUsername[$username])) {
            $statusByUsername[$username] = 'online';
        }
    }

    return [
        'success' => $statusByUsername !== [] || $remoteLookup !== [],
        'status_by_username' => $statusByUsername,
        'remote_lookup' => $remoteLookup,
        'source' => 'bridge',
    ];
}

function mobile_dashboard_fetch_user_runtime_state(array $tenant): array
{
    $tenantId = (int) ($tenant['id'] ?? 0);
    $mkConfig = app_mikrotik_config($tenantId);
    $cacheKey = app_cache_key('mikrotik_user_runtime_state', $tenantId);
    $cacheTtl = (int) env_value('MIKROTIK_CACHE_TTL', 15);
    $liveMode = (string) ($_GET['live'] ?? '0') === '1';

    if (!$liveMode && $cacheTtl > 0) {
        $cached = app_cache_get($cacheKey, $cacheTtl);
        if (is_array($cached)) {
            return [
                'success' => (bool) ($cached['success'] ?? false),
                'status_by_username' => is_array($cached['status_by_username'] ?? null) ? $cached['status_by_username'] : [],
                'remote_lookup' => is_array($cached['remote_lookup'] ?? null) ? $cached['remote_lookup'] : [],
                'source' => (string) ($cached['source'] ?? 'cache'),
            ];
        }
    }

    if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        $bridgeState = mobile_dashboard_user_runtime_state_from_bridge($tenantId);
        return is_array($bridgeState)
            ? $bridgeState
            : ['success' => false, 'status_by_username' => [], 'remote_lookup' => [], 'source' => 'none'];
    }

    $api = new MikroTikClient();
    $api->transport = $mkConfig['transport'] ?? 'socket';
    $api->port = $mkConfig['port'] ?? 8728;
    $api->timeout = 2;
    $api->attempts = 1;
    $api->delay = 0;
    $api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
    $api->restPort = $mkConfig['rest_port'] ?? 443;
    $api->restPath = $mkConfig['rest_path'] ?? '/rest';
    $api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);

    if (!$api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
        $bridgeState = mobile_dashboard_user_runtime_state_from_bridge($tenantId);
        return is_array($bridgeState)
            ? $bridgeState
            : ['success' => false, 'status_by_username' => [], 'remote_lookup' => [], 'source' => 'none'];
    }

    $activeRaw = $api->comm('/ppp/active/print');
    $secretsRaw = $api->comm('/ppp/secret/print');
    $api->disconnect();

    $activeUsers = mobile_dashboard_normalize_rows($activeRaw);
    $secrets = mobile_dashboard_normalize_rows($secretsRaw);
    $activeLookup = [];
    $remoteLookup = [];
    $statusByUsername = [];

    foreach ($activeUsers as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        $address = trim((string) ($item['address'] ?? ''));
        if ($username === '') {
            continue;
        }

        $activeLookup[$username] = true;
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            $remoteLookup[$username] = $address;
        }
    }

    foreach ($secrets as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        if ($username === '') {
            continue;
        }

        $status = 'offline';
        if (($item['disabled'] ?? 'false') === 'true') {
            $status = 'disabled';
        } elseif (isset($activeLookup[$username])) {
            $status = 'online';
        }

        $statusByUsername[$username] = $status;

        $remoteAddress = trim((string) ($item['remote-address'] ?? ''));
        if (!isset($remoteLookup[$username]) && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            $remoteLookup[$username] = $remoteAddress;
        }
    }

    foreach (array_keys($activeLookup) as $username) {
        if (!isset($statusByUsername[$username])) {
            $statusByUsername[$username] = 'online';
        }
    }

    $state = [
        'success' => $statusByUsername !== [] || $remoteLookup !== [],
        'status_by_username' => $statusByUsername,
        'remote_lookup' => $remoteLookup,
        'source' => 'mikrotik',
    ];

    if (!$liveMode && $cacheTtl > 0) {
        app_cache_put($cacheKey, $state);
    }

    return $state;
}

function mobile_dashboard_fetch_topology_map(array $tenants, array $runtimeStates = []): array
{
    $allNodes = [];
    $allEdges = [];

    foreach ($tenants as $tenant) {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $tenantName = (string) ($tenant['name'] ?? 'Tenant');
        if ($tenantId <= 0) {
            continue;
        }

        $cacheKey = app_cache_key('map_topology', $tenantId);
        $cacheTtl = (int) env_value('MAP_CACHE_TTL', 30);
        $topology = app_cache_get($cacheKey, $cacheTtl);

        if (!is_array($topology) || !isset($topology['nodes'], $topology['edges'])) {
            $mkConfig = app_mikrotik_config($tenantId);
            $tenantRuntimeState = $runtimeStates[$tenantId] ?? ['status_by_username' => [], 'remote_lookup' => []];
            $remoteLookup = is_array($tenantRuntimeState['remote_lookup'] ?? null) && $tenantRuntimeState['remote_lookup'] !== []
                ? $tenantRuntimeState['remote_lookup']
                : mobile_dashboard_fetch_remote_lookup($mkConfig, $tenantId);

            $stmtLokasi = $GLOBALS['pdo']->prepare("
                SELECT id, nama, tipe, koordinat
                FROM lokasi
                WHERE tenant_id = ? AND koordinat IS NOT NULL AND koordinat != ''
            ");
            $stmtLokasi->execute([$tenantId]);
            $lokasiData = $stmtLokasi->fetchAll(PDO::FETCH_ASSOC);

            $nodes = [];
            $edges = [];

            foreach ($lokasiData as $lok) {
                $coords = array_map('trim', explode(',', (string) ($lok['koordinat'] ?? '')));
                if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                    continue;
                }

                $nodes[] = [
                    'id' => 'L' . $lok['id'],
                    'name' => $lok['nama'],
                    'type' => $lok['tipe'],
                    'lat' => (float) $coords[0],
                    'lng' => (float) $coords[1],
                ];
            }

            $stmtUsers = $GLOBALS['pdo']->prepare("
                SELECT u.id, u.username, u.nama, u.status, u.remote_ip, u.koordinat AS user_koordinat, l.koordinat AS odp_koordinat
                FROM users u
                LEFT JOIN relasi_jaringan r ON u.id = r.user_id AND u.tenant_id = r.tenant_id
                LEFT JOIN lokasi l ON r.odp_id = l.id AND l.tenant_id = r.tenant_id
                WHERE u.tenant_id = ?
            ");
            $stmtUsers->execute([$tenantId]);
            $usersData = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

            foreach ($usersData as $user) {
                $rawCoords = $user['user_koordinat'] ?: $user['odp_koordinat'];
                if (!$rawCoords) {
                    continue;
                }

                $coords = array_map('trim', explode(',', (string) $rawCoords));
                if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                    continue;
                }

                $lat = (float) $coords[0];
                $lng = (float) $coords[1];
                if (!$user['user_koordinat']) {
                    $seed = abs(crc32((string) $user['id']));
                    $lat += (($seed % 21) - 10) / 10000;
                    $lng += (((int) floor($seed / 21) % 21) - 10) / 10000;
                }

                $nodes[] = [
                    'id' => 'U' . $user['id'],
                    'name' => $user['nama'],
                    'username' => $user['username'],
                    'type' => 'user',
                    'status' => $tenantRuntimeState['status_by_username'][$user['username']] ?? $user['status'],
                    'remote_ip' => $remoteLookup[$user['username']] ?? $user['remote_ip'] ?? null,
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            }

            $stmtRelasi = $GLOBALS['pdo']->prepare("
                SELECT user_id, odp_id, odc_id, server_id
                FROM relasi_jaringan
                WHERE tenant_id = ?
            ");
            $stmtRelasi->execute([$tenantId]);
            foreach ($stmtRelasi->fetchAll(PDO::FETCH_ASSOC) as $rel) {
                if (!empty($rel['server_id']) && !empty($rel['odc_id'])) {
                    $edges[] = ['source' => 'L' . $rel['server_id'], 'target' => 'L' . $rel['odc_id']];
                }
                if (!empty($rel['odc_id']) && !empty($rel['odp_id'])) {
                    $edges[] = ['source' => 'L' . $rel['odc_id'], 'target' => 'L' . $rel['odp_id']];
                }
                if (!empty($rel['odp_id']) && !empty($rel['user_id'])) {
                    $edges[] = ['source' => 'L' . $rel['odp_id'], 'target' => 'U' . $rel['user_id']];
                }
            }

            $topology = [
                'success' => true,
                'nodes' => $nodes,
                'edges' => $edges,
            ];
            app_cache_put($cacheKey, $topology);
        }

        $tenantRuntimeState = $runtimeStates[$tenantId] ?? ['status_by_username' => [], 'remote_lookup' => []];

        foreach (($topology['nodes'] ?? []) as $node) {
            $username = (string) ($node['username'] ?? '');
            $resolvedStatus = $node['status'] ?? null;
            $resolvedRemoteIp = $node['remote_ip'] ?? null;

            if (($node['type'] ?? '') === 'user' && $username !== '') {
                $resolvedStatus = $tenantRuntimeState['status_by_username'][$username] ?? $resolvedStatus;
                $resolvedRemoteIp = $tenantRuntimeState['remote_lookup'][$username] ?? $resolvedRemoteIp;
            }

            $allNodes[] = [
                'id' => 'T' . $tenantId . '-' . ($node['id'] ?? ''),
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'name' => $node['name'] ?? '-',
                'type' => $node['type'] ?? 'node',
                'status' => $resolvedStatus,
                'remote_ip' => $resolvedRemoteIp,
                'lat' => (float) ($node['lat'] ?? 0),
                'lng' => (float) ($node['lng'] ?? 0),
            ];
        }

        foreach (($topology['edges'] ?? []) as $edge) {
            $allEdges[] = [
                'source' => 'T' . $tenantId . '-' . ($edge['source'] ?? ''),
                'target' => 'T' . $tenantId . '-' . ($edge['target'] ?? ''),
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
            ];
        }
    }

    return [
        'nodes' => $allNodes,
        'edges' => $allEdges,
        'summary' => [
            'total_nodes' => count($allNodes),
            'total_edges' => count($allEdges),
        ],
    ];
}

$account = mobile_require_account();
$tenantIds = mobile_resolve_tenant_ids($account);

if (!$tenantIds) {
    mobile_json_response([
        'success' => false,
        'message' => 'Tenant aktif belum dipilih',
    ], 422);
}

$tenantPlaceholders = implode(', ', array_fill(0, count($tenantIds), '?'));

$tenantStmt = $GLOBALS['pdo']->prepare("
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
    WHERE t.id IN ($tenantPlaceholders)
    ORDER BY t.name ASC, t.id ASC
");
$tenantStmt->execute($tenantIds);
$tenants = $tenantStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$tenants) {
    mobile_json_response([
        'success' => false,
        'message' => 'Tenant tidak ditemukan',
    ], 404);
}

$runtimeStates = [];
foreach ($tenants as $tenant) {
    $runtimeStates[(int) ($tenant['id'] ?? 0)] = mobile_dashboard_fetch_user_runtime_state($tenant);
}

$stmt = $GLOBALS['pdo']->prepare("
    SELECT id, tenant_id, username, nama AS name, status
    FROM users
    WHERE tenant_id IN ($tenantPlaceholders)
    ORDER BY nama ASC, id DESC
");
$stmt->execute($tenantIds);
$users = array_map(static function (array $user) use ($runtimeStates): array {
    $tenantId = (int) ($user['tenant_id'] ?? 0);
    $username = (string) ($user['username'] ?? '');
    $runtimeState = $runtimeStates[$tenantId] ?? ['status_by_username' => []];
    $resolvedStatus = $runtimeState['status_by_username'][$username] ?? ($user['status'] ?? 'offline');

    $user['id'] = (int) $user['id'];
    $user['tenant_id'] = $tenantId;
    $user['status'] = in_array($resolvedStatus, ['online', 'offline', 'disabled'], true)
        ? $resolvedStatus
        : 'offline';
    return $user;
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

$statistik = [
    'online' => 0,
    'offline' => 0,
    'disabled' => 0,
];

foreach ($users as $user) {
    $status = (string) ($user['status'] ?? 'offline');
    if (isset($statistik[$status])) {
        $statistik[$status] += 1;
    }
}

$trafficMonitoring = array_map(
    static fn (array $tenant): array => mobile_dashboard_fetch_traffic_monitoring($tenant),
    $tenants
);
$topologyMap = mobile_dashboard_fetch_topology_map($tenants, $runtimeStates);

$tenantSummary = count($tenants) === 1
    ? $tenants[0]
    : [
        'id' => 0,
        'name' => count($tenants) . ' tenant aktif',
        'slug' => 'multi-tenant',
        'owner_name' => $account['nama'] ?? null,
        'phone' => null,
        'status' => 'active',
        'active_admin_count' => array_sum(array_map(
            static fn (array $tenant): int => (int) ($tenant['active_admin_count'] ?? 0),
            $tenants
        )),
    ];

mobile_json_response([
    'success' => true,
    'statistik' => $statistik,
    'users' => $users,
    'traffic_monitoring' => $trafficMonitoring,
    'topology_map' => $topologyMap,
    'tenant' => [
        'id' => (int) ($tenantSummary['id'] ?? 0),
        'name' => $tenantSummary['name'] ?? 'Tenant',
        'slug' => $tenantSummary['slug'] ?? null,
        'owner_name' => $tenantSummary['owner_name'] ?? null,
        'phone' => $tenantSummary['phone'] ?? null,
        'status' => $tenantSummary['status'] ?? 'active',
        'active_admin_count' => (int) ($tenantSummary['active_admin_count'] ?? 0),
    ],
    'tenants' => array_map(static function (array $tenant): array {
        return [
            'id' => (int) ($tenant['id'] ?? 0),
            'name' => $tenant['name'] ?? 'Tenant',
            'slug' => $tenant['slug'] ?? null,
            'owner_name' => $tenant['owner_name'] ?? null,
            'phone' => $tenant['phone'] ?? null,
            'status' => $tenant['status'] ?? 'active',
            'active_admin_count' => (int) ($tenant['active_admin_count'] ?? 0),
        ];
    }, $tenants),
    'meta' => [
        'generated_at' => date(DATE_ATOM),
        'tenant_id' => (int) $tenantIds[0],
        'tenant_ids' => $tenantIds,
        'mobile_api' => true,
    ],
]);
