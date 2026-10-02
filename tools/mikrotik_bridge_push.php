<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mikrotik_client.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

function tool_option(string $name, ?string $default = null): ?string
{
    global $argv;
    foreach (array_slice($argv, 1) as $arg) {
        if (strpos($arg, '--' . $name . '=') === 0) {
            return substr($arg, strlen($name) + 3);
        }
    }

    return $default;
}

function tool_normalize_rows($result): array
{
    if (!is_array($result) || isset($result['!trap']) || isset($result['!fatal'])) {
        return [];
    }

    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

$siteUrl = rtrim((string) tool_option('site-url', ''), '/');
$tenantId = (int) tool_option('tenant-id', '0');
$apiKey = (string) tool_option('api-key', '');
$legacyToken = (string) tool_option('token', '');
$overrideHost = trim((string) tool_option('host', ''));
$overrideUser = trim((string) tool_option('user', ''));
$overridePass = (string) tool_option('pass', '');
$overrideTransport = trim((string) tool_option('transport', ''));
$overridePort = trim((string) tool_option('port', ''));
$overrideInterface = trim((string) tool_option('interface', ''));

if ($siteUrl === '' || $tenantId <= 0) {
    fwrite(STDERR, "Usage: php tools/mikrotik_bridge_push.php --site-url=https://example.com --tenant-id=2 [--api-key=TENANT_KEY] [--host=HOST] [--user=USER] [--pass=PASS]\n");
    exit(1);
}

if ($apiKey === '' && $legacyToken === '') {
    $apiKey = app_mikrotik_bridge_api_key_get($tenantId, true);
}

if ($apiKey === '' && $legacyToken === '') {
    fwrite(STDERR, "API key bridge belum tersedia untuk tenant {$tenantId}.\n");
    exit(1);
}

$cfg = app_mikrotik_config($tenantId);
$parsedOverrideHost = $overrideHost !== '' ? app_parse_mikrotik_host($overrideHost) : null;
if (is_array($parsedOverrideHost)) {
    if (($parsedOverrideHost['host'] ?? '') !== '') {
        $cfg['host'] = $parsedOverrideHost['host'];
        $cfg['host_raw'] = $overrideHost;
    }
    if (($parsedOverrideHost['socket_port'] ?? null) !== null) {
        $cfg['port'] = (int) $parsedOverrideHost['socket_port'];
    }
    if (($parsedOverrideHost['rest_scheme'] ?? null) !== null) {
        $cfg['rest_scheme'] = (string) $parsedOverrideHost['rest_scheme'];
    }
    if (($parsedOverrideHost['rest_port'] ?? null) !== null) {
        $cfg['rest_port'] = (int) $parsedOverrideHost['rest_port'];
    }
    if (($parsedOverrideHost['rest_path'] ?? null) !== null) {
        $cfg['rest_path'] = (string) $parsedOverrideHost['rest_path'];
    }
}
if ($overrideUser !== '') {
    $cfg['user'] = $overrideUser;
}
if ($overridePass !== '') {
    $cfg['pass'] = $overridePass;
}
if ($overrideTransport !== '') {
    $cfg['transport'] = strtolower($overrideTransport);
}
if ($overridePort !== '') {
    $cfg['port'] = (int) $overridePort;
}
if ($overrideInterface !== '') {
    $cfg['interface'] = $overrideInterface;
}

if (($cfg['host'] ?? '') === '' || ($cfg['user'] ?? '') === '') {
    fwrite(STDERR, "Konfigurasi MikroTik lokal belum lengkap.\n");
    exit(1);
}

$api = new MikroTikClient();
$api->transport = $cfg['transport'] ?? 'socket';
$api->port = $cfg['port'] ?? 8728;
$api->timeout = $cfg['timeout'] ?? 2;
$api->attempts = $cfg['attempts'] ?? 1;
$api->delay = $cfg['delay'] ?? 0;
$api->restScheme = $cfg['rest_scheme'] ?? 'https';
$api->restPort = $cfg['rest_port'] ?? 443;
$api->restPath = $cfg['rest_path'] ?? '/rest';
$api->restAllowInsecure = (bool) ($cfg['rest_allow_insecure'] ?? true);

if (!$api->connect($cfg['host'], $cfg['user'], $cfg['pass'])) {
    fwrite(STDERR, 'Koneksi lokal ke MikroTik gagal: ' . ($api->error_str ?? 'unknown') . "\n");
    exit(1);
}

$trafficInterface = (string) ($cfg['interface'] ?? 'ether1');
$activeRaw = $api->comm('/ppp/active/print');
$secretsRaw = $api->comm('/ppp/secret/print');
$profilesRaw = $api->comm('/ppp/profile/print');
$interfacesRaw = $api->comm('/interface/print');
$trafficRaw = $api->comm('/interface/monitor-traffic', ['interface' => $trafficInterface, 'once' => '']);
$api->disconnect();

$activeUsers = tool_normalize_rows($activeRaw);
$secrets = tool_normalize_rows($secretsRaw);
$profiles = tool_normalize_rows($profilesRaw);
$interfaces = tool_normalize_rows($interfacesRaw);
$traffic = tool_normalize_rows($trafficRaw);

$activeLookup = array_fill_keys(array_filter(array_column($activeUsers, 'name')), true);
$users = [];
foreach ($secrets as $secret) {
    $status = 'offline';
    if (($secret['disabled'] ?? 'false') === 'true') {
        $status = 'disabled';
    } elseif (isset($activeLookup[$secret['name'] ?? ''])) {
        $status = 'online';
    }

    $users[] = [
        'name' => $secret['name'] ?? '',
        'status' => $status,
    ];
}

$interfaceStats = null;
foreach ($interfaces as $item) {
    if (($item['name'] ?? '') !== $trafficInterface) {
        continue;
    }

    $interfaceStats = [
        'name' => $item['name'] ?? $trafficInterface,
        'type' => $item['type'] ?? '-',
        'running' => ($item['running'] ?? 'false') === 'true',
        'tx_byte' => (int) ($item['tx-byte'] ?? 0),
        'rx_byte' => (int) ($item['rx-byte'] ?? 0),
        'tx_packet' => (int) ($item['tx-packet'] ?? 0),
        'rx_packet' => (int) ($item['rx-packet'] ?? 0),
        'tx_drop' => (int) ($item['tx-drop'] ?? 0),
        'rx_drop' => (int) ($item['rx-drop'] ?? 0),
        'tx_error' => (int) ($item['tx-error'] ?? 0),
        'rx_error' => (int) ($item['rx-error'] ?? 0),
    ];
    break;
}

$snapshot = [
    'success' => true,
    'traffic' => [
        'rx' => (int) (($traffic[0]['rx-bits-per-second'] ?? 0)),
        'tx' => (int) (($traffic[0]['tx-bits-per-second'] ?? 0)),
    ],
    'users' => $users,
    'pppoe_active' => count($activeUsers),
    'secrets_count' => count($secrets),
    'profiles_count' => count($profiles),
    'interfaces_count' => count($interfaces),
    'inventory' => [
        'secrets' => array_slice(array_map(static function ($item) use ($activeLookup) {
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
                'status' => $status,
            ];
        }, $secrets), 0, 100),
        'active' => array_slice(array_map(static function ($item) {
            return [
                'name' => $item['name'] ?? '-',
                'address' => $item['address'] ?? '-',
                'service' => $item['service'] ?? '-',
                'uptime' => $item['uptime'] ?? '-',
                'caller_id' => $item['caller-id'] ?? '-',
            ];
        }, $activeUsers), 0, 100),
        'profiles' => array_slice(array_map(static function ($item) {
            return [
                'name' => $item['name'] ?? '-',
                'local_address' => $item['local-address'] ?? '-',
                'remote_address' => $item['remote-address'] ?? '-',
            ];
        }, $profiles), 0, 100),
        'interfaces' => array_slice(array_map(static function ($item) {
            return [
                'name' => $item['name'] ?? '-',
                'type' => $item['type'] ?? '-',
                'running' => ($item['running'] ?? 'false') === 'true' ? 'yes' : 'no',
            ];
        }, $interfaces), 0, 100),
    ],
    'interface_stats' => $interfaceStats,
    'meta' => [
        'cached' => false,
        'generated_at' => date(DATE_ATOM),
        'traffic_interface' => $trafficInterface,
        'tenant_id' => $tenantId,
        'transport' => 'bridge-local',
    ],
];

$endpoint = $siteUrl . '/api/mikrotik_bridge_push.php';
if ($legacyToken !== '') {
    $endpoint .= '?tenant_id=' . $tenantId;
}
$body = json_encode([
    'tenant_id' => $tenantId,
    'snapshot' => $snapshot,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$headers = [
    'Content-Type: application/json',
];

if ($apiKey !== '') {
    $headers[] = 'X-Bridge-Api-Key: ' . $apiKey;
} else {
    $headers[] = 'X-Bridge-Token: ' . $legacyToken;
}

$curl = curl_init($endpoint);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($response === false || $status >= 400) {
    fwrite(STDERR, "Push snapshot gagal. HTTP {$status}. {$error}\n");
    if (is_string($response) && $response !== '') {
        fwrite(STDERR, $response . "\n");
    }
    exit(1);
}

echo "Snapshot MikroTik berhasil dikirim ke {$endpoint}\n";
