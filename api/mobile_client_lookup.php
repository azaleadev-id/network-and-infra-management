<?php
declare(strict_types=1);

require_once __DIR__ . '/mobile_bootstrap.php';
require_once __DIR__ . '/../config/mikrotik_client.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Lookup-Token');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'POST'], true)) {
    mobile_json_response([
        'success' => false,
        'message' => 'Method not allowed',
    ], 405);
}

function mobile_client_lookup_token(): string
{
    return trim((string) env_value('MOBILE_CLIENT_LOOKUP_TOKEN', ''));
}

function mobile_client_lookup_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = (string) ($_SERVER[$serverKey] ?? '');

    if ($value === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $headerName => $headerValue) {
                if (strtolower((string) $headerName) === strtolower($name)) {
                    $value = (string) $headerValue;
                    break;
                }
            }
        }
    }

    return trim($value);
}

function mobile_client_lookup_require_token(): void
{
    $expected = mobile_client_lookup_token();
    if ($expected === '') {
        return;
    }

    $provided = mobile_client_lookup_header('X-Lookup-Token');
    if ($provided === '' || !hash_equals($expected, $provided)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Lookup token tidak valid.',
        ], 401);
    }
}

function mobile_client_lookup_normalize(string $value): string
{
    return trim($value);
}

function mobile_client_lookup_rows($result): array
{
    if (!is_array($result) || isset($result['!trap']) || isset($result['!fatal'])) {
        return [];
    }

    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

function mobile_client_lookup_first_string(array $item, array $keys): ?string
{
    foreach ($keys as $key) {
        $value = trim((string) ($item[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function mobile_client_lookup_first_int(array $item, array $keys, int $default = 0): int
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $item) && $item[$key] !== '' && $item[$key] !== null) {
            return (int) $item[$key];
        }
    }

    return $default;
}

function mobile_client_lookup_parse_rate_pair(?string $value): array
{
    $value = trim((string) $value);
    if ($value === '' || !str_contains($value, '/')) {
        return ['rx' => 0, 'tx' => 0];
    }

    [$left, $right] = array_pad(explode('/', $value, 2), 2, '0');
    return [
        'rx' => (int) trim($left),
        'tx' => (int) trim($right),
    ];
}

function mobile_client_lookup_effective_payment_status(array $billing): string
{
    $status = trim((string) ($billing['payment_status'] ?? 'belum_bayar')) ?: 'belum_bayar';
    $billAmount = (float) ($billing['jumlah'] ?? 0);
    $amountPaid = (float) ($billing['amount_paid'] ?? 0);
    $remaining = array_key_exists('remaining_amount', $billing)
        ? (float) ($billing['remaining_amount'] ?? max($billAmount - $amountPaid, 0))
        : max($billAmount - $amountPaid, 0);

    if ($billAmount > 0 && ($remaining <= 0.00001 || $amountPaid >= $billAmount - 0.00001)) {
        return 'sudah_bayar';
    }

    return $status;
}

function mobile_client_lookup_normalize_billing(array $billing): array
{
    $billAmount = (float) ($billing['jumlah'] ?? 0);
    $amountPaid = (float) ($billing['amount_paid'] ?? 0);
    $remaining = array_key_exists('remaining_amount', $billing)
        ? (float) ($billing['remaining_amount'] ?? max($billAmount - $amountPaid, 0))
        : max($billAmount - $amountPaid, 0);

    $billing['amount_paid'] = $amountPaid;
    $billing['remaining_amount'] = $remaining <= 0.00001 ? 0 : $remaining;
    $billing['last_payment_amount'] = (float) ($billing['last_payment_amount'] ?? 0);
    $billing['payment_status'] = mobile_client_lookup_effective_payment_status($billing);

    return $billing;
}

function mobile_client_lookup_best_effort_traffic(
    array $mkConfig,
    string $username,
    ?string $activeAddress,
    ?string $remoteAddress
): array {
    $empty = [
        'rx' => 0,
        'tx' => 0,
        'interface_name' => null,
        'source' => null,
    ];

    if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        return $empty;
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
        return $empty;
    }

    try {
        $simpleQueues = mobile_client_lookup_rows($api->comm('/queue/simple/print', ['stats' => '']));
        foreach ($simpleQueues as $queue) {
            $queueName = strtolower(trim((string) ($queue['name'] ?? '')));
            $target = trim((string) ($queue['target'] ?? $queue['targets'] ?? ''));
            $candidateIps = array_filter([
                trim((string) $activeAddress),
                trim((string) $remoteAddress),
            ]);

            $matched = $queueName === strtolower($username);
            if (!$matched && $target !== '') {
                foreach ($candidateIps as $candidateIp) {
                    if ($candidateIp !== '' && str_contains($target, $candidateIp)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                continue;
            }

            $ratePair = mobile_client_lookup_parse_rate_pair(
                mobile_client_lookup_first_string($queue, ['rate', 'current-rate']) ?? ''
            );

            if ($ratePair['rx'] > 0 || $ratePair['tx'] > 0) {
                return [
                    'rx' => $ratePair['rx'],
                    'tx' => $ratePair['tx'],
                    'interface_name' => $queue['name'] ?? null,
                    'source' => 'simple_queue',
                ];
            }
        }

        foreach ([$username, 'pppoe-' . $username, '<pppoe-' . $username . '>'] as $interfaceName) {
            $trafficRows = mobile_client_lookup_rows($api->comm('/interface/monitor-traffic', [
                'interface' => $interfaceName,
                'once' => '',
            ]));

            if ($trafficRows === []) {
                continue;
            }

            $trafficRow = $trafficRows[0];
            $rx = mobile_client_lookup_first_int($trafficRow, ['rx-bits-per-second']);
            $tx = mobile_client_lookup_first_int($trafficRow, ['tx-bits-per-second']);

            if ($rx > 0 || $tx > 0) {
                return [
                    'rx' => $rx,
                    'tx' => $tx,
                    'interface_name' => $interfaceName,
                    'source' => 'pppoe_interface',
                ];
            }
        }
    } catch (Throwable $e) {
        return $empty;
    } finally {
        $api->disconnect();
    }

    return $empty;
}

function mobile_client_lookup_runtime_from_bridge(int $tenantId): array
{
    $snapshot = app_mikrotik_bridge_snapshot_get($tenantId);
    if (!is_array($snapshot)) {
        return [
            'status_by_username' => [],
            'active_by_username' => [],
            'remote_lookup' => [],
            'source' => 'none',
        ];
    }

    $inventory = is_array($snapshot['inventory'] ?? null) ? $snapshot['inventory'] : [];
    $statusByUsername = [];
    $activeByUsername = [];
    $remoteLookup = [];

    foreach (($inventory['active'] ?? []) as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        if ($username === '') {
            continue;
        }

        $statusByUsername[$username] = 'online';
        $activeByUsername[$username] = [
            'name' => $username,
            'address' => $item['address'] ?? null,
            'uptime' => $item['uptime'] ?? null,
            'caller_id' => $item['caller_id'] ?? null,
            'service' => $item['service'] ?? null,
        ];

        $address = trim((string) ($item['address'] ?? ''));
        if ($address !== '' && filter_var($address, FILTER_VALIDATE_IP)) {
            $remoteLookup[$username] = $address;
        }
    }

    foreach (($inventory['secrets'] ?? []) as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        if ($username === '') {
            continue;
        }

        $status = (string) ($item['status'] ?? 'offline');
        $statusByUsername[$username] = in_array($status, ['online', 'offline', 'disabled'], true)
            ? $status
            : ($statusByUsername[$username] ?? 'offline');

        $remoteAddress = trim((string) ($item['remote_address'] ?? ''));
        if (!isset($remoteLookup[$username]) && $remoteAddress !== '' && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            $remoteLookup[$username] = $remoteAddress;
        }
    }

    return [
        'status_by_username' => $statusByUsername,
        'active_by_username' => $activeByUsername,
        'remote_lookup' => $remoteLookup,
        'source' => 'bridge',
    ];
}

function mobile_client_lookup_runtime_state(int $tenantId): array
{
    $mkConfig = app_mikrotik_config($tenantId);
    if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        return mobile_client_lookup_runtime_from_bridge($tenantId);
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
        return mobile_client_lookup_runtime_from_bridge($tenantId);
    }

    $activeRows = mobile_client_lookup_rows($api->comm('/ppp/active/print'));
    $secretRows = mobile_client_lookup_rows($api->comm('/ppp/secret/print'));
    $api->disconnect();

    $statusByUsername = [];
    $activeByUsername = [];
    $remoteLookup = [];

    foreach ($activeRows as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        if ($username === '') {
            continue;
        }

        $statusByUsername[$username] = 'online';
        $activeByUsername[$username] = [
            'name' => $username,
            'address' => $item['address'] ?? null,
            'uptime' => $item['uptime'] ?? null,
            'caller_id' => $item['caller-id'] ?? null,
            'service' => $item['service'] ?? null,
        ];

        $address = trim((string) ($item['address'] ?? ''));
        if ($address !== '' && filter_var($address, FILTER_VALIDATE_IP)) {
            $remoteLookup[$username] = $address;
        }
    }

    foreach ($secretRows as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        if ($username === '') {
            continue;
        }

        $status = 'offline';
        if (($item['disabled'] ?? 'false') === 'true') {
            $status = 'disabled';
        } elseif (isset($activeByUsername[$username])) {
            $status = 'online';
        }

        $statusByUsername[$username] = $status;

        $remoteAddress = trim((string) ($item['remote-address'] ?? ''));
        if (!isset($remoteLookup[$username]) && $remoteAddress !== '' && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            $remoteLookup[$username] = $remoteAddress;
        }
    }

    return [
        'status_by_username' => $statusByUsername,
        'active_by_username' => $activeByUsername,
        'remote_lookup' => $remoteLookup,
        'source' => 'mikrotik',
    ];
}

mobile_client_lookup_require_token();

$lookupUserId = (int) (
    mobile_request_value('user_id', 0) ?:
    mobile_request_value('id', 0) ?:
    mobile_request_value('account_id', 0)
);

$query = trim((string) (
    mobile_request_value('client_name', '') ?:
    mobile_request_value('name', '') ?:
    mobile_request_value('client_username', '') ?:
    mobile_request_value('username', '') ?:
    mobile_request_value('query', '')
));

if ($lookupUserId <= 0 && $query === '') {
    mobile_json_response([
        'success' => false,
        'message' => 'ID atau nama pelanggan wajib diisi.',
    ], 422);
}

$normalizedQuery = mobile_client_lookup_normalize($query);
$lookupMode = 'customer_name_only';
$stmt = null;

if ($lookupUserId > 0) {
    $lookupMode = 'user_id_only';
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT
            u.id,
            u.tenant_id,
            u.username,
            u.nama,
            u.phone,
            u.remote_ip,
            u.koordinat,
            u.status,
            u.expired,
            t.name AS tenant_name,
            t.slug AS tenant_slug,
            t.owner_name,
            t.phone AS tenant_phone,
            t.status AS tenant_status
        FROM users u
        JOIN tenants t ON t.id = u.tenant_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$lookupUserId]);
} else {
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT
            u.id,
            u.tenant_id,
            u.username,
            u.nama,
            u.phone,
            u.remote_ip,
            u.koordinat,
            u.status,
            u.expired,
            t.name AS tenant_name,
            t.slug AS tenant_slug,
            t.owner_name,
            t.phone AS tenant_phone,
            t.status AS tenant_status
        FROM users u
        JOIN tenants t ON t.id = u.tenant_id
        WHERE BINARY u.nama = ?
        LIMIT 1
    ");
    $stmt->execute([$normalizedQuery]);
}

$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    mobile_json_response([
        'success' => false,
        'message' => $lookupUserId > 0
            ? 'Pelanggan dengan ID tersebut tidak ditemukan.'
            : 'Nama pelanggan harus sama persis, termasuk huruf besar dan huruf kecil.',
        'tenant' => null,
        'traffic_monitoring' => [],
        'users' => [],
        'billings' => [],
    ], 404);
}

$tenantId = (int) ($client['tenant_id'] ?? 0);
$mkConfig = app_mikrotik_config($tenantId);
$runtimeState = mobile_client_lookup_runtime_state($tenantId);
$username = (string) ($client['username'] ?? '');
$resolvedStatus = $runtimeState['status_by_username'][$username] ?? ($client['status'] ?? 'offline');
$activeSession = $runtimeState['active_by_username'][$username] ?? null;
$remoteIp = $runtimeState['remote_lookup'][$username] ?? ($client['remote_ip'] ?? null);
$trafficSnapshot = mobile_client_lookup_best_effort_traffic(
    $mkConfig,
    $username,
    $activeSession['address'] ?? null,
    is_string($remoteIp) ? $remoteIp : null
);

$billingStmt = $GLOBALS['pdo']->prepare("
    SELECT
        b.id,
        b.tenant_id,
        b.user_id,
        u.nama,
        u.username,
        b.status,
        b.payment_status,
        b.jatuh_tempo,
        b.jumlah,
        b.paid_at,
        b.amount_paid,
        b.remaining_amount,
        b.last_payment_amount,
        b.last_payment_at,
        b.catatan,
        b.isolir_enabled,
        b.isolir_at,
        b.isolir_status,
        t.name AS tenant_name
    FROM billing b
    JOIN users u ON u.id = b.user_id AND u.tenant_id = b.tenant_id
    JOIN tenants t ON t.id = b.tenant_id
    WHERE b.tenant_id = ? AND b.user_id = ?
    ORDER BY b.jatuh_tempo ASC, b.id DESC
    LIMIT 5
");
$billingStmt->execute([$tenantId, (int) ($client['id'] ?? 0)]);
$billingRows = $billingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$message = $resolvedStatus === 'online'
    ? 'PPPoE active connection ditemukan untuk client ini.'
    : 'Client ditemukan, tetapi saat ini tidak ada sesi PPPoE aktif.';

$trafficMonitoring = [[
    'client_name' => (string) ($client['nama'] ?? $username),
    'client_username' => $username,
    'name' => (string) ($client['nama'] ?? $username),
    'username' => $username,
    'tenant_id' => $tenantId,
    'tenant_name' => (string) ($client['tenant_name'] ?? 'Tenant'),
    'interface_name' => $trafficSnapshot['interface_name'] ?? ($activeSession['caller_id'] ?? null),
    'success' => $resolvedStatus === 'online',
    'message' => $message,
    'traffic' => [
        'rx' => (int) ($trafficSnapshot['rx'] ?? 0),
        'tx' => (int) ($trafficSnapshot['tx'] ?? 0),
    ],
    'session' => [
        'address' => $activeSession['address'] ?? $remoteIp,
        'uptime' => $activeSession['uptime'] ?? null,
        'service' => $activeSession['service'] ?? 'pppoe',
        'caller_id' => $activeSession['caller_id'] ?? null,
        'runtime_source' => $trafficSnapshot['source'] ?? ($runtimeState['source'] ?? 'none'),
    ],
]];

$users = [[
    'id' => (int) ($client['id'] ?? 0),
    'tenant_id' => $tenantId,
    'name' => (string) ($client['nama'] ?? $username),
    'username' => $username,
    'status' => in_array($resolvedStatus, ['online', 'offline', 'disabled'], true)
        ? $resolvedStatus
        : 'offline',
    'remote_ip' => $remoteIp,
]];

$billings = array_map(static function (array $item): array {
    $item = mobile_client_lookup_normalize_billing($item);

    return [
        'id' => (int) ($item['id'] ?? 0),
        'tenant_id' => (int) ($item['tenant_id'] ?? 0),
        'user_id' => (int) ($item['user_id'] ?? 0),
        'tenant_name' => (string) ($item['tenant_name'] ?? 'Tenant'),
        'nama' => (string) ($item['nama'] ?? ''),
        'username' => (string) ($item['username'] ?? ''),
        'status' => (string) ($item['status'] ?? 'aktif'),
        'payment_status' => (string) ($item['payment_status'] ?? 'belum_bayar'),
        'jatuh_tempo' => $item['jatuh_tempo'] ?? null,
        'jumlah' => $item['jumlah'] ?? null,
        'amount_paid' => $item['amount_paid'] ?? null,
        'remaining_amount' => $item['remaining_amount'] ?? null,
        'last_payment_amount' => $item['last_payment_amount'] ?? null,
        'last_payment_at' => $item['last_payment_at'] ?? null,
        'paid_at' => $item['paid_at'] ?? null,
        'catatan' => $item['catatan'] ?? null,
        'isolir_enabled' => (int) ($item['isolir_enabled'] ?? 0),
        'isolir_at' => $item['isolir_at'] ?? null,
        'isolir_status' => $item['isolir_status'] ?? null,
    ];
}, $billingRows);

mobile_json_response([
    'success' => true,
    'message' => 'Lookup client berhasil.',
    'lookup_mode' => $lookupMode,
    'account' => [
        'id' => (int) ($client['id'] ?? 0),
        'tenant_id' => $tenantId,
        'tenant_name' => (string) ($client['tenant_name'] ?? 'Tenant'),
        'tenant_slug' => $client['tenant_slug'] ?? null,
        'username' => $username,
        'nama' => (string) ($client['nama'] ?? $username),
        'role' => 'client',
        'phone' => $client['phone'] ?? null,
        'remote_ip' => $remoteIp,
        'expired' => $client['expired'] ?? null,
    ],
    'tenant' => [
        'id' => $tenantId,
        'name' => $client['tenant_name'] ?? 'Tenant',
        'slug' => $client['tenant_slug'] ?? null,
        'owner_name' => $client['owner_name'] ?? null,
        'phone' => $client['tenant_phone'] ?? null,
        'status' => $client['tenant_status'] ?? 'active',
    ],
    'users' => $users,
    'traffic_monitoring' => $trafficMonitoring,
    'billings' => $billings,
    'meta' => [
        'generated_at' => date(DATE_ATOM),
        'tenant_id' => $tenantId,
        'mobile_api' => true,
        'runtime_source' => $runtimeState['source'] ?? 'none',
        'lookup_user_id' => $lookupUserId > 0 ? $lookupUserId : null,
        'query' => $query,
    ],
]);
