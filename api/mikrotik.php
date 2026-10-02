<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_client.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];
$mkConfig = app_mikrotik_config($tenantId);

$trafficInterface = app_setting_get('traffic_interface', $mkConfig['interface'] ?? 'ether1', $tenantId);
$cacheKey = app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId);
$cacheTtl = (int) env_value('MIKROTIK_CACHE_TTL', 15);
$liveMode = isset($_GET['live']) && $_GET['live'] === '1';
$fullInventory = isset($_GET['full']) && $_GET['full'] === '1';

if (!$liveMode && $cacheTtl > 0) {
    $cached = app_cache_get($cacheKey, $cacheTtl);
    if ($cached !== null) {
        echo json_encode($cached);
        exit;
    }
}

function mikrotik_normalize_rows($result): array
{
    if (!is_array($result) || isset($result['!trap']) || isset($result['!fatal'])) {
        return [];
    }

    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

function mikrotik_extract_error($result): ?string
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

function mikrotik_first_int(array $item, array $keys, int $default = 0): int
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $item) && $item[$key] !== '' && $item[$key] !== null) {
            return (int) $item[$key];
        }
    }

    return $default;
}

$response = [
    'success' => false,
    'traffic' => ['rx' => 0, 'tx' => 0],
    'users' => [],
    'pppoe_active' => 0,
    'secrets_count' => 0,
    'profiles_count' => 0,
    'interfaces_count' => 0,
    'inventory' => ['secrets' => [], 'active' => [], 'profiles' => [], 'interfaces' => []],
    'interface_stats' => null,
    'meta' => [
        'cached' => false,
        'generated_at' => date(DATE_ATOM),
        'traffic_interface' => $trafficInterface,
        'tenant_id' => $tenantId,
        'transport' => $mkConfig['transport'] ?? 'socket'
    ]
];

if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
    $bridgeSnapshot = app_mikrotik_bridge_snapshot_get($tenantId);
    if ($bridgeSnapshot !== null) {
        $bridgeSnapshot['meta'] = is_array($bridgeSnapshot['meta'] ?? null) ? $bridgeSnapshot['meta'] : [];
        $bridgeSnapshot['meta']['tenant_id'] = $tenantId;
        $bridgeSnapshot['meta']['traffic_interface'] = $trafficInterface;
        echo json_encode($bridgeSnapshot);
        exit;
    }

    $response['message'] = 'Konfigurasi MikroTik tenant ini belum lengkap.';
    echo json_encode($response);
    exit;
}

$api = new MikroTikClient();
$api->transport = $mkConfig['transport'] ?? 'socket';
$api->port = $mkConfig['port'] ?? 8728;
$api->timeout = $mkConfig['timeout'] ?? 2;
$api->attempts = $mkConfig['attempts'] ?? 1;
$api->delay = $mkConfig['delay'] ?? 0;
$api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
$api->restPort = $mkConfig['rest_port'] ?? 443;
$api->restPath = $mkConfig['rest_path'] ?? '/rest';
$api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);


$debugInfo = [];
if (!$api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
    $bridgeSnapshot = app_mikrotik_bridge_snapshot_get($tenantId);
    if ($bridgeSnapshot !== null) {
        $bridgeSnapshot['meta'] = is_array($bridgeSnapshot['meta'] ?? null) ? $bridgeSnapshot['meta'] : [];
        $bridgeSnapshot['meta']['tenant_id'] = $tenantId;
        $bridgeSnapshot['meta']['traffic_interface'] = $trafficInterface;
        $bridgeSnapshot['meta']['transport'] = $bridgeSnapshot['meta']['transport'] ?? 'bridge';
        $bridgeSnapshot['meta']['bridge_fallback'] = true;
        echo json_encode($bridgeSnapshot);
        exit;
    }

    $response['message'] = 'Koneksi ke MikroTik gagal.';
    $debugInfo['host'] = $mkConfig['host'];
    $debugInfo['user'] = $mkConfig['user'];
    $debugInfo['port'] = $mkConfig['port'];
    $debugInfo['timeout'] = $mkConfig['timeout'];
    $debugInfo['attempts'] = $mkConfig['attempts'];
    $debugInfo['delay'] = $mkConfig['delay'];
    $debugInfo['dns_resolve'] = gethostbyname($mkConfig['host']);
    $debugInfo['error_no'] = $api->error_no ?? null;
    $debugInfo['error_str'] = $api->error_str ?? null;
    $response['debug'] = $debugInfo;
    echo json_encode($response);
    exit;
}

$response['success'] = true;
$response['meta']['transport'] = $api->activeTransport() ?? ($mkConfig['transport'] ?? 'socket');
$activeRaw = [];
$secretsRaw = [];
$profilesRaw = [];
$interfacesRaw = [];
$interfacesStatsRaw = [];

$activeRaw = $api->comm('/ppp/active/print');
$activeUsers = mikrotik_normalize_rows($activeRaw);
$response['pppoe_active'] = count($activeUsers);

if (!$liveMode || $fullInventory) {
    $activeLookup = array_fill_keys(array_filter(array_column($activeUsers, 'name')), true);
    $response['inventory']['active'] = $fullInventory ? array_map(static function ($item) {
        return [
            'name' => $item['name'] ?? '-',
            'address' => $item['address'] ?? '-',
            'service' => $item['service'] ?? '-',
            'uptime' => $item['uptime'] ?? '-',
            'caller_id' => $item['caller-id'] ?? '-'
        ];
    }, $activeUsers) : [];

    $secretsRaw = $api->comm('/ppp/secret/print');
    $secrets = mikrotik_normalize_rows($secretsRaw);
    $response['secrets_count'] = count($secrets);
    $response['inventory']['secrets'] = array_slice(array_map(static function ($item) use ($activeLookup) {
        $status = 'offline';
        if (($item['disabled'] ?? 'false') === 'true') {
            $status = 'disabled';
        } elseif (isset($activeLookup[$item['name'] ?? ''])) {
            $status = 'online';
        }

        return [
            'name' => $item['name'] ?? '-',
            'profile' => $item['profile'] ?? '-',
            'remote_address' => $item['remote-address'] ?? '-',
            'status' => $status
        ];
    }, $secrets), 0, 8);

    foreach ($secrets as $secret) {
        $status = 'offline';
        if (($secret['disabled'] ?? 'false') === 'true') {
            $status = 'disabled';
        } elseif (isset($activeLookup[$secret['name'] ?? ''])) {
            $status = 'online';
        }

        $response['users'][] = [
            'name' => $secret['name'] ?? '',
            'status' => $status
        ];
    }

    $stmtUpdate = $pdo->prepare("UPDATE users SET status = ? WHERE tenant_id = ? AND username = ?");
    foreach ($response['users'] as $user) {
        if ($user['name'] === '') {
            continue;
        }
        $stmtUpdate->execute([$user['status'], $tenantId, $user['name']]);
    }
}

$trafficRaw = $api->comm('/interface/monitor-traffic', ['interface' => $trafficInterface, 'once' => '']);
$traffic = mikrotik_normalize_rows($trafficRaw);
if (!$liveMode || $fullInventory) {
    $profilesRaw = $api->comm('/ppp/profile/print');
}
$profiles = mikrotik_normalize_rows($profilesRaw);
$interfacesRaw = $api->comm('/interface/print');
$interfaces = mikrotik_normalize_rows($interfacesRaw);
$interfacesStatsRaw = $api->comm('/interface/print', ['stats' => '']);
$interfacesStats = mikrotik_normalize_rows($interfacesStatsRaw);

if (!$liveMode || $fullInventory) {
    $response['profiles_count'] = count($profiles);
    $response['interfaces_count'] = count($interfaces);
    $response['inventory']['profiles'] = array_slice(array_map(static function ($item) {
        return [
            'name' => $item['name'] ?? '-',
            'local_address' => $item['local-address'] ?? '-',
            'remote_address' => $item['remote-address'] ?? '-'
        ];
    }, $profiles), 0, 8);
    $response['inventory']['interfaces'] = array_slice(array_map(static function ($item) {
        return [
            'name' => $item['name'] ?? '-',
            'type' => $item['type'] ?? '-',
            'running' => ($item['running'] ?? 'false') === 'true' ? 'yes' : 'no'
        ];
    }, $interfaces), 0, 8);
}

foreach ([$interfaces, $interfacesStats] as $interfaceRows) {
    foreach ($interfaceRows as $item) {
        $ifaceName = $item['name'] ?? '';
        if ($ifaceName !== $trafficInterface && !str_starts_with($ifaceName, $trafficInterface . '_') && !str_starts_with($trafficInterface, $ifaceName . '_')) {
            continue;
        }

        $response['interface_stats'] = [
            'name' => $item['name'] ?? $trafficInterface,
            'type' => $item['type'] ?? '-',
            'running' => ($item['running'] ?? 'false') === 'true',
            'tx_byte' => mikrotik_first_int($item, ['tx-byte', 'fp-tx-byte', 'driver-tx-byte']),
            'rx_byte' => mikrotik_first_int($item, ['rx-byte', 'fp-rx-byte', 'driver-rx-byte']),
            'tx_packet' => mikrotik_first_int($item, ['tx-packet', 'fp-tx-packet', 'driver-tx-packet']),
            'rx_packet' => mikrotik_first_int($item, ['rx-packet', 'fp-rx-packet', 'driver-rx-packet']),
            'tx_drop' => mikrotik_first_int($item, ['tx-drop', 'tx-queue-drop']),
            'rx_drop' => mikrotik_first_int($item, ['rx-drop']),
            'tx_error' => mikrotik_first_int($item, ['tx-error']),
            'rx_error' => mikrotik_first_int($item, ['rx-error']),
        ];
        break 2;
    }
}

if ($response['interface_stats'] === null || ($response['interface_stats']['rx_byte'] === 0 && $response['interface_stats']['tx_byte'] === 0)) {
    $bridgeSnapshot = app_mikrotik_bridge_snapshot_get($tenantId);
    if (is_array($bridgeSnapshot) && isset($bridgeSnapshot['interface_stats'])) {
        $response['interface_stats'] = $bridgeSnapshot['interface_stats'];
    }
}

if (isset($traffic[0])) {
    $response['traffic']['rx'] = (int) ($traffic[0]['rx-bits-per-second'] ?? 0);
    $response['traffic']['tx'] = (int) ($traffic[0]['tx-bits-per-second'] ?? 0);
    $response['traffic']['rx_packet_rate'] = mikrotik_first_int($traffic[0], ['rx-packets-per-second', 'rx-packet-per-second']);
    $response['traffic']['tx_packet_rate'] = mikrotik_first_int($traffic[0], ['tx-packets-per-second', 'tx-packet-per-second']);
}

$partialErrors = array_filter([
    mikrotik_extract_error($activeRaw),
    mikrotik_extract_error($secretsRaw),
    mikrotik_extract_error($profilesRaw),
    mikrotik_extract_error($interfacesRaw),
    mikrotik_extract_error($interfacesStatsRaw),
    mikrotik_extract_error($trafficRaw),
]);
if ($partialErrors !== []) {
    $response['message'] = 'Sebagian data MikroTik gagal dibaca. Detail: ' . implode('; ', array_unique($partialErrors));
}

$api->disconnect();

if (!$liveMode && $cacheTtl > 0) {
    app_cache_put($cacheKey, $response);
}

echo json_encode($response);

