<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_client.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];
$mk_config = app_mikrotik_config($tenantId);

function pppoe_normalize_rows($result): array
{
    if (!is_array($result)) {
        return [];
    }

    if (isset($result['!trap']) || isset($result['!fatal'])) {
        return [];
    }

    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

function pppoe_extract_error($result): ?string
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

function pppoe_connect(array $mk_config): MikroTikClient
{
    if (($mk_config['host'] ?? '') === '' || ($mk_config['user'] ?? '') === '') {
        throw new RuntimeException('Konfigurasi MikroTik tenant ini belum lengkap. Lengkapi dari menu settings tenant.');
    }

    $api = new MikroTikClient();
    $api->transport = $mk_config['transport'] ?? 'socket';
    $api->port = $mk_config['port'] ?? 8728;
    $api->timeout = $mk_config['timeout'] ?? 2;
    $api->attempts = $mk_config['attempts'] ?? 1;
    $api->delay = $mk_config['delay'] ?? 0;
    $api->restScheme = $mk_config['rest_scheme'] ?? 'https';
    $api->restPort = $mk_config['rest_port'] ?? 443;
    $api->restPath = $mk_config['rest_path'] ?? '/rest';
    $api->restAllowInsecure = (bool) ($mk_config['rest_allow_insecure'] ?? true);

    if (!$api->connect($mk_config['host'], $mk_config['user'], $mk_config['pass'])) {
        $detail = !empty($api->error_str) ? ' Detail: ' . $api->error_str : '';
        throw new RuntimeException('Koneksi ke MikroTik gagal.' . $detail);
    }

    return $api;
}

function pppoe_clear_cache(): void
{
    global $tenantId;
    $trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
    app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));
    app_cache_forget(app_cache_key('map_topology', $tenantId));
    app_cache_forget(app_cache_key('dashboard_overview', $tenantId));
}

function pppoe_default_services(): array
{
    return ['pppoe'];
}

function pppoe_collect_services(array $secrets, array $active): array
{
    $serviceValues = [];

    foreach (array_merge($secrets, $active) as $item) {
        $service = trim((string) ($item['service'] ?? ''));
        if ($service !== '') {
            $serviceValues[$service] = $service;
        }
    }

    if ($serviceValues === []) {
        return pppoe_default_services();
    }

    $services = array_values($serviceValues);
    usort($services, static function (string $a, string $b): int {
        if ($a === 'pppoe') {
            return -1;
        }
        if ($b === 'pppoe') {
            return 1;
        }

        return strcasecmp($a, $b);
    });

    return $services;
}

function pppoe_remove_active_sessions_by_name(MikroTikClient $api, string $name): int
{
    if ($name === '') {
        return 0;
    }

    $activeSessions = pppoe_normalize_rows($api->comm('/ppp/active/print', [
        '?name' => $name
    ]));

    $removed = 0;
    foreach ($activeSessions as $session) {
        $sessionId = trim((string) ($session['.id'] ?? ''));
        if ($sessionId === '') {
            continue;
        }

        $result = $api->comm('/ppp/active/remove', [
            'numbers' => $sessionId
        ]);
        $error = pppoe_extract_error($result);
        if ($error !== null) {
            throw new RuntimeException($error);
        }

        $removed++;
    }

    return $removed;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_owner_api();
    $action = trim((string) ($_POST['action'] ?? ''));
    $api = null;

    try {
        $api = pppoe_connect($mk_config);

        if ($action === 'create_secret') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $profile = trim((string) ($_POST['profile'] ?? 'default'));
            $service = trim((string) ($_POST['service'] ?? 'pppoe'));

            if ($name === '' || $password === '') {
                throw new RuntimeException('Nama secret dan password wajib diisi.');
            }

            $existing = pppoe_normalize_rows($api->comm('/ppp/secret/print', [
                '?name' => $name
            ]));
            if ($existing !== []) {
                throw new RuntimeException('Secret dengan nama tersebut sudah ada di MikroTik.');
            }

            $payload = [
                'name' => $name,
                'password' => $password,
                'service' => $service !== '' ? $service : 'pppoe',
                'profile' => $profile !== '' ? $profile : 'default'
            ];

            $result = $api->comm('/ppp/secret/add', $payload);
            $error = pppoe_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            pppoe_clear_cache();
            echo json_encode([
                'success' => true,
                'message' => "PPP secret {$name} berhasil ditambahkan."
            ]);
            exit;
        }

        if ($action === 'update_secret') {
            $originalName = trim((string) ($_POST['original_name'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $profile = trim((string) ($_POST['profile'] ?? 'default'));
            $service = trim((string) ($_POST['service'] ?? 'pppoe'));

            if ($originalName === '' || $name === '') {
                throw new RuntimeException('Nama secret tidak valid.');
            }

            $existing = pppoe_normalize_rows($api->comm('/ppp/secret/print', [
                '?name' => $originalName
            ]));
            if ($existing === [] || empty($existing[0]['.id'])) {
                throw new RuntimeException('Secret yang ingin diedit tidak ditemukan di MikroTik.');
            }

            if (strcasecmp($originalName, $name) !== 0) {
                $target = pppoe_normalize_rows($api->comm('/ppp/secret/print', [
                    '?name' => $name
                ]));
                if ($target !== []) {
                    throw new RuntimeException('Nama secret baru sudah dipakai di MikroTik.');
                }
            }

            $payload = [
                '.id' => $existing[0]['.id'],
                'name' => $name,
                'service' => $service !== '' ? $service : 'pppoe',
                'profile' => $profile !== '' ? $profile : 'default'
            ];

            if ($password !== '') {
                $payload['password'] = $password;
            }

            $result = $api->comm('/ppp/secret/set', $payload);
            $error = pppoe_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            pppoe_clear_cache();
            echo json_encode([
                'success' => true,
                'message' => "PPP secret {$originalName} berhasil diupdate menjadi {$name}."
            ]);
            exit;
        }

        if ($action === 'disable_secret') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Nama secret tidak valid.');
            }

            $existing = pppoe_normalize_rows($api->comm('/ppp/secret/print', [
                '?name' => $name
            ]));
            if ($existing === [] || empty($existing[0]['.id'])) {
                throw new RuntimeException('Secret tidak ditemukan di MikroTik.');
            }

            if (($existing[0]['disabled'] ?? 'false') === 'true') {
                throw new RuntimeException('Secret tersebut sudah dalam kondisi disabled.');
            }

            $result = $api->comm('/ppp/secret/disable', [
                'numbers' => $existing[0]['.id']
            ]);
            $error = pppoe_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            $removedActiveCount = pppoe_remove_active_sessions_by_name($api, $name);

            pppoe_clear_cache();
            echo json_encode([
                'success' => true,
                'message' => $removedActiveCount > 0
                    ? "PPP secret {$name} berhasil di-disable dan {$removedActiveCount} active connection diputus."
                    : "PPP secret {$name} berhasil di-disable."
            ]);
            exit;
        }

        if ($action === 'enable_secret') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Nama secret tidak valid.');
            }

            $existing = pppoe_normalize_rows($api->comm('/ppp/secret/print', [
                '?name' => $name
            ]));
            if ($existing === [] || empty($existing[0]['.id'])) {
                throw new RuntimeException('Secret tidak ditemukan di MikroTik.');
            }

            if (($existing[0]['disabled'] ?? 'false') !== 'true') {
                throw new RuntimeException('Secret tersebut sudah dalam kondisi aktif.');
            }

            $result = $api->comm('/ppp/secret/enable', [
                'numbers' => $existing[0]['.id']
            ]);
            $error = pppoe_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            $removedActiveCount = pppoe_remove_active_sessions_by_name($api, $name);

            pppoe_clear_cache();
            echo json_encode([
                'success' => true,
                'message' => $removedActiveCount > 0
                    ? "PPP secret {$name} berhasil di-enable dan {$removedActiveCount} active connection diputus untuk refresh IP."
                    : "PPP secret {$name} berhasil di-enable."
            ]);
            exit;
        }

        if ($action === 'create_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $localAddress = trim((string) ($_POST['local_address'] ?? ''));
            $remoteAddressMode = trim((string) ($_POST['remote_address_mode'] ?? 'manual'));
            $remoteAddress = trim((string) ($_POST['remote_address'] ?? ''));
            $remotePool = trim((string) ($_POST['remote_pool'] ?? ''));
            $rateLimit = trim((string) ($_POST['rate_limit'] ?? ''));
            $comment = trim((string) ($_POST['comment'] ?? ''));
            $onlyOne = trim((string) ($_POST['only_one'] ?? 'false')) === 'true' ? 'yes' : 'no';

            if ($name === '') {
                throw new RuntimeException('Nama profile wajib diisi.');
            }

            $existing = pppoe_normalize_rows($api->comm('/ppp/profile/print', [
                '?name' => $name
            ]));
            if ($existing !== []) {
                throw new RuntimeException('Profile dengan nama tersebut sudah ada di MikroTik.');
            }

            $payload = [
                'name' => $name,
                'only-one' => $onlyOne
            ];

            if ($localAddress !== '') {
                $payload['local-address'] = $localAddress;
            }
            $resolvedRemoteAddress = $remoteAddressMode === 'pool' ? $remotePool : $remoteAddress;
            if ($resolvedRemoteAddress !== '') {
                $payload['remote-address'] = $resolvedRemoteAddress;
            }
            if ($rateLimit !== '') {
                $payload['rate-limit'] = $rateLimit;
            }
            if ($comment !== '') {
                $payload['comment'] = $comment;
            }

            $result = $api->comm('/ppp/profile/add', $payload);
            $error = pppoe_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            pppoe_clear_cache();
            echo json_encode([
                'success' => true,
                'message' => "PPP profile {$name} berhasil ditambahkan."
            ]);
            exit;
        }

        if ($action === 'delete_secret') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Nama secret tidak valid.');
            }

            $existing = pppoe_normalize_rows($api->comm('/ppp/secret/print', [
                '?name' => $name
            ]));
            if ($existing === [] || empty($existing[0]['.id'])) {
                throw new RuntimeException('Secret tidak ditemukan di MikroTik.');
            }

            $result = $api->comm('/ppp/secret/remove', [
                'numbers' => $existing[0]['.id']
            ]);
            $error = pppoe_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            pppoe_clear_cache();
            echo json_encode([
                'success' => true,
                'message' => "PPP secret {$name} berhasil dihapus."
            ]);
            exit;
        }

        if ($action === 'delete_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Nama profile tidak valid.');
            }

            $existing = pppoe_normalize_rows($api->comm('/ppp/profile/print', [
                '?name' => $name
            ]));
            if ($existing === [] || empty($existing[0]['.id'])) {
                throw new RuntimeException('Profile tidak ditemukan di MikroTik.');
            }

            $result = $api->comm('/ppp/profile/remove', [
                'numbers' => $existing[0]['.id']
            ]);
            $error = pppoe_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            pppoe_clear_cache();
            echo json_encode([
                'success' => true,
                'message' => "PPP profile {$name} berhasil dihapus."
            ]);
            exit;
        }

        if ($action === 'remove_active') {
            $sessionId = trim((string) ($_POST['session_id'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($sessionId === '') {
                throw new RuntimeException('Session active connection tidak valid.');
            }

            $activeSession = pppoe_normalize_rows($api->comm('/ppp/active/print', [
                '?.id' => $sessionId
            ]));
            if ($activeSession === []) {
                throw new RuntimeException('Active connection tidak ditemukan.');
            }

            $resolvedName = $name !== '' ? $name : trim((string) ($activeSession[0]['name'] ?? ''));
            if ($resolvedName === '') {
                throw new RuntimeException('Nama secret untuk active connection tidak ditemukan.');
            }

            $secret = pppoe_normalize_rows($api->comm('/ppp/secret/print', [
                '?name' => $resolvedName
            ]));
            if ($secret === [] || empty($secret[0]['.id'])) {
                throw new RuntimeException("Secret {$resolvedName} tidak ditemukan. Disable secret dulu sebelum hapus active connection.");
            }

            if (($secret[0]['disabled'] ?? 'false') !== 'true') {
                throw new RuntimeException("Secret {$resolvedName} harus di-disable dulu sebelum active connection bisa dihapus.");
            }

            $result = $api->comm('/ppp/active/remove', [
                'numbers' => $sessionId
            ]);
            $error = pppoe_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            pppoe_clear_cache();
            echo json_encode([
                'success' => true,
                'message' => 'Active connection' . ($resolvedName !== '' ? " {$resolvedName}" : '') . ' berhasil dihapus.'
            ]);
            exit;
        }

        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Aksi PPPoE tidak dikenali.']);
        exit;
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    } finally {
        if ($api instanceof MikroTikClient) {
            $api->disconnect();
        }
    }
}

try {
    $api = pppoe_connect($mk_config);
    $profiles = pppoe_normalize_rows($api->comm('/ppp/profile/print'));
    $secrets = pppoe_normalize_rows($api->comm('/ppp/secret/print'));
    $active = pppoe_normalize_rows($api->comm('/ppp/active/print'));
    $pools = pppoe_normalize_rows($api->comm('/ip/pool/print'));
    $api->disconnect();

    usort($profiles, static fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    usort($secrets, static fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    usort($active, static fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    usort($pools, static fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

    $secretStatusByName = [];
    foreach ($secrets as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $secretStatusByName[$name] = ($item['disabled'] ?? 'false') === 'true';
    }

    echo json_encode([
        'success' => true,
        'profiles' => array_map(static function (array $item) {
            return [
                'name' => $item['name'] ?? '-',
                'local_address' => $item['local-address'] ?? '-',
                'remote_address' => $item['remote-address'] ?? '-',
                'rate_limit' => $item['rate-limit'] ?? '-',
                'only_one' => $item['only-one'] ?? 'no'
            ];
        }, array_slice($profiles, 0, 50)),
        'secrets' => array_map(static function (array $item) {
            return [
                'name' => $item['name'] ?? '-',
                'profile' => $item['profile'] ?? '-',
                'local_address' => $item['local-address'] ?? '-',
                'remote_address' => $item['remote-address'] ?? '-',
                'service' => $item['service'] ?? '-',
                'disabled' => ($item['disabled'] ?? 'false') === 'true'
            ];
        }, $secrets),
        'active' => array_map(static function (array $item) use ($secretStatusByName) {
            $name = trim((string) ($item['name'] ?? ''));
            return [
                'id' => $item['.id'] ?? '',
                'name' => $item['name'] ?? '-',
                'address' => $item['address'] ?? '-',
                'service' => $item['service'] ?? '-',
                'uptime' => $item['uptime'] ?? '-',
                'caller_id' => $item['caller-id'] ?? '-',
                'secret_disabled' => $name !== '' ? ($secretStatusByName[$name] ?? false) : false
            ];
        }, array_slice($active, 0, 50)),
        'pools' => array_map(static function (array $item) {
            return [
                'name' => $item['name'] ?? '-',
                'ranges' => $item['ranges'] ?? '-',
            ];
        }, array_slice($pools, 0, 100)),
        'services' => pppoe_collect_services($secrets, $active)
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'profiles' => [],
        'secrets' => [],
        'active' => [],
        'pools' => [],
        'services' => pppoe_default_services()
    ]);
}
