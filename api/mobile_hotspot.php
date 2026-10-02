<?php
require_once __DIR__ . '/mobile_bootstrap.php';
require_once __DIR__ . '/../config/mikrotik_client.php';

header('Content-Type: application/json; charset=utf-8');

function mobile_hotspot_normalize_rows($result): array
{
    if (!is_array($result) || isset($result['!trap']) || isset($result['!fatal'])) {
        return [];
    }

    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

function mobile_hotspot_extract_error($result): ?string
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

function mobile_hotspot_connect(array $mkConfig): MikroTikClient
{
    if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        throw new RuntimeException('Konfigurasi MikroTik tenant ini belum lengkap. Lengkapi dari settings tenant.');
    }

    $api = new MikroTikClient();
    $api->transport = $mkConfig['transport'] ?? 'socket';
    $api->port = $mkConfig['port'] ?? 8728;
    $api->timeout = 10;
    $api->attempts = 1;
    $api->delay = 0;
    $api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
    $api->restPort = $mkConfig['rest_port'] ?? 443;
    $api->restPath = $mkConfig['rest_path'] ?? '/rest';
    $api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);

    if (!$api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
        $detail = !empty($api->error_str) ? ' Detail: ' . $api->error_str : '';
        throw new RuntimeException('Koneksi ke MikroTik gagal.' . $detail);
    }

    return $api;
}

function mobile_hotspot_clear_cache(int $tenantId): void
{
    $trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
    app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));
    app_cache_forget(app_cache_key('dashboard_overview', $tenantId));
}

function mobile_hotspot_random_token(int $length = 6, string $mode = 'capital_numbers'): string
{
    $small = 'abcdefghijkmnopqrstuvwxyz';
    $capital = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $numbers = '23456789';
    $alphabet = match ($mode) {
        'numbers' => $numbers,
        'small' => $small,
        'capital' => $capital,
        'mixed_letters' => $small . $capital,
        'small_numbers' => $small . $numbers,
        'capital_numbers' => $capital . $numbers,
        'all' => $small . $capital . $numbers,
        default => $capital . $numbers,
    };

    $token = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $token .= $alphabet[random_int(0, $max)];
    }

    return $token;
}

function mobile_hotspot_to_bytes($value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $number = (float) $value;
    if ($number <= 0) {
        return null;
    }

    return (string) (int) round($number * 1024 * 1024);
}

function mobile_hotspot_post_names(): array
{
    $raw = mobile_request_value('names', mobile_request_value('names_json', []));
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $decoded = json_decode((string) $raw, true);
        $items = is_array($decoded) ? $decoded : [];
    }

    $names = [];
    foreach ($items as $item) {
        $name = trim((string) $item);
        if ($name !== '') {
            $names[$name] = true;
        }
    }

    return array_keys($names);
}

function mobile_hotspot_can_manage(array $account): bool
{
    $role = strtolower(trim((string) ($account['role'] ?? '')));
    return in_array($role, ['owner', 'admin', 'superadmin', 'superuser'], true);
}

function mobile_hotspot_tenant_summary(int $tenantId): array
{
    $tenant = mobile_fetch_tenants_by_ids([$tenantId])[0] ?? null;
    if ($tenant === null) {
        return [
            'id' => $tenantId,
            'name' => 'Tenant',
        ];
    }

    return [
        'id' => (int) ($tenant['id'] ?? 0),
        'name' => (string) ($tenant['name'] ?? 'Tenant'),
        'slug' => $tenant['slug'] ?? null,
        'owner_name' => $tenant['owner_name'] ?? null,
        'phone' => $tenant['phone'] ?? null,
        'status' => $tenant['status'] ?? 'active',
    ];
}

$account = mobile_require_account();
$tenantId = mobile_resolve_tenant_id($account);
$mkConfig = app_mikrotik_config($tenantId);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!mobile_hotspot_can_manage($account)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Fitur hotspot hanya untuk owner atau admin.',
        ], 403);
    }

    $action = trim((string) mobile_request_value('action', ''));
    $api = null;

    try {
        $api = mobile_hotspot_connect($mkConfig);

        if ($action === 'create_vouchers') {
            $inputVal = trim((string) mobile_request_value('input', ''));
            $count = (int) mobile_request_value('count', 1);
            $profile = trim((string) mobile_request_value('profile', 'default'));
            $server = trim((string) mobile_request_value('server', ''));
            $limitHours = (int) mobile_request_value('limit_hours', 0);
            $limitMinutes = (int) mobile_request_value('limit_minutes', 0);
            $charMode = trim((string) mobile_request_value('char_mode', 'capital_numbers'));
            $charLength = (int) mobile_request_value('char_length', 6);
            $userMode = trim((string) mobile_request_value('user_mode', 'random'));
            $limitMb = trim((string) mobile_request_value('limit_mb', ''));
            $quotaDisplay = trim((string) mobile_request_value('quota_display', ''));
            $price = trim((string) mobile_request_value('price', ''));
            $shopId = trim((string) mobile_request_value('shop_id', ''));
            $comment = trim((string) mobile_request_value('comment', ''));

            if ($count < 1 || $count > 200) {
                throw new RuntimeException('Jumlah voucher 1-200.');
            }

            $limitUptime = '';
            if ($limitHours > 0) {
                $limitUptime .= $limitHours . 'h';
            }
            if ($limitMinutes > 0) {
                $limitUptime .= $limitMinutes . 'm';
            }

            $manualNames = [];
            if ($userMode === 'manual') {
                if ($inputVal === '') {
                    throw new RuntimeException('Input manual wajib diisi.');
                }
                $manualNames = preg_split("/[\s,]+/", $inputVal, -1, PREG_SPLIT_NO_EMPTY);
                $manualNames = array_values(array_unique($manualNames));
                $targetCount = count($manualNames);
            } else {
                $targetCount = $count;
            }

            $created = [];
            for ($i = 0; $i < $targetCount; $i++) {
                if ($userMode === 'manual') {
                    $name = $manualNames[$i];
                } elseif ($userMode === 'prefix') {
                    $name = $inputVal . mobile_hotspot_random_token($charLength, $charMode);
                } else {
                    $name = mobile_hotspot_random_token($charLength, $charMode);
                }

                $idVoucher = (string) random_int(1000, 9999);
                while ($idVoucher === $name) {
                    $idVoucher = (string) random_int(1000, 9999);
                }

                $fullComment = $comment;
                if ($quotaDisplay !== '') {
                    $fullComment = "[Q:$quotaDisplay]" . ($fullComment !== '' ? ' ' . $fullComment : '');
                }
                if ($price !== '') {
                    $fullComment = "[P:$price]" . ($fullComment !== '' ? ' ' . $fullComment : '');
                }
                if ($shopId !== '') {
                    $fullComment = "[S:$shopId]" . ($fullComment !== '' ? ' ' . $fullComment : '');
                }
                $fullComment = "[I:$idVoucher]" . ($fullComment !== '' ? ' ' . $fullComment : '');

                $payload = [
                    'name' => $name,
                    'password' => $name,
                    'profile' => $profile !== '' ? $profile : 'default',
                ];
                if ($server !== '') {
                    $payload['server'] = $server;
                }
                if ($limitUptime !== '') {
                    $payload['limit-uptime'] = $limitUptime;
                }

                $bytes = mobile_hotspot_to_bytes($limitMb);
                if ($bytes !== null) {
                    $payload['limit-bytes-total'] = $bytes;
                }
                if ($fullComment !== '') {
                    $payload['comment'] = $fullComment;
                }

                $result = $api->comm('/ip/hotspot/user/add', $payload);
                $error = mobile_hotspot_extract_error($result);
                if ($error !== null) {
                    if ($userMode === 'manual') {
                        continue;
                    }
                    throw new RuntimeException($error);
                }

                $created[] = [
                    'username' => $name,
                    'password' => $name,
                    'profile' => $payload['profile'],
                    'server' => $server,
                    'limit_uptime' => $limitUptime !== '' ? $limitUptime : 'Tanpa Limit',
                    'limit_mb' => $limitMb ?: '-',
                    'quota_display' => $quotaDisplay,
                    'price' => $price,
                    'shop_id' => $shopId,
                    'id_voucher' => $idVoucher,
                    'comment' => $fullComment,
                ];
            }

            mobile_hotspot_clear_cache($tenantId);
            mobile_json_response([
                'success' => true,
                'message' => count($created) . ' voucher berhasil dibuat.',
                'tenant' => mobile_hotspot_tenant_summary($tenantId),
                'created' => $created,
            ]);
        }

        if ($action === 'create_profile') {
            $name = trim((string) mobile_request_value('name', ''));
            if ($name === '') {
                throw new RuntimeException('Nama profile wajib.');
            }

            $payload = [
                'name' => $name,
                'shared-users' => (string) mobile_request_value('shared_users', 1),
            ];
            $rateLimit = trim((string) mobile_request_value('rate_limit', ''));
            $keepaliveTimeout = trim((string) mobile_request_value('keepalive_timeout', ''));
            $idleTimeout = trim((string) mobile_request_value('idle_timeout', ''));
            if ($rateLimit !== '') {
                $payload['rate-limit'] = $rateLimit;
            }
            if ($keepaliveTimeout !== '') {
                $payload['keepalive-timeout'] = $keepaliveTimeout;
            }
            if ($idleTimeout !== '') {
                $payload['idle-timeout'] = $idleTimeout;
            }

            $result = $api->comm('/ip/hotspot/user/profile/add', $payload);
            $error = mobile_hotspot_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            mobile_hotspot_clear_cache($tenantId);
            mobile_json_response([
                'success' => true,
                'message' => "Profile {$name} dibuat.",
                'tenant' => mobile_hotspot_tenant_summary($tenantId),
            ]);
        }

        if (in_array($action, ['delete_user', 'enable_user', 'disable_user'], true)) {
            $name = trim((string) mobile_request_value('name', ''));
            $existing = mobile_hotspot_normalize_rows($api->comm('/ip/hotspot/user/print', ['?name' => $name]));
            if ($existing === [] || empty($existing[0]['.id'])) {
                throw new RuntimeException('Voucher tidak ditemukan.');
            }

            $command = match ($action) {
                'delete_user' => '/ip/hotspot/user/remove',
                'enable_user' => '/ip/hotspot/user/enable',
                default => '/ip/hotspot/user/disable',
            };
            $result = $api->comm($command, ['numbers' => $existing[0]['.id']]);
            $error = mobile_hotspot_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            mobile_hotspot_clear_cache($tenantId);
            mobile_json_response([
                'success' => true,
                'message' => "Voucher {$name} diproses.",
                'tenant' => mobile_hotspot_tenant_summary($tenantId),
            ]);
        }

        if ($action === 'remove_active') {
            $sessionId = trim((string) mobile_request_value('session_id', ''));
            $result = $api->comm('/ip/hotspot/active/remove', ['numbers' => $sessionId]);
            $error = mobile_hotspot_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            mobile_hotspot_clear_cache($tenantId);
            mobile_json_response([
                'success' => true,
                'message' => 'Active hotspot dihapus.',
                'tenant' => mobile_hotspot_tenant_summary($tenantId),
            ]);
        }

        mobile_json_response([
            'success' => false,
            'message' => 'Aksi tidak dikenali.',
        ], 422);
    } catch (Throwable $e) {
        mobile_json_response([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    } finally {
        if ($api instanceof MikroTikClient) {
            $api->disconnect();
        }
    }
}

try {
    $api = mobile_hotspot_connect($mkConfig);
    $profiles = mobile_hotspot_normalize_rows($api->comm('/ip/hotspot/user/profile/print'));
    $users = mobile_hotspot_normalize_rows($api->comm('/ip/hotspot/user/print'));
    $active = mobile_hotspot_normalize_rows($api->comm('/ip/hotspot/active/print'));
    $servers = mobile_hotspot_normalize_rows($api->comm('/ip/hotspot/print'));
    $api->disconnect();

    usort($profiles, static fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    usort($users, static fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    usort($active, static fn ($a, $b) => strcasecmp((string) ($a['user'] ?? ''), (string) ($b['user'] ?? '')));

    mobile_json_response([
        'success' => true,
        'tenant' => mobile_hotspot_tenant_summary($tenantId),
        'profiles' => array_map(static fn ($item) => [
            'name' => $item['name'] ?? '-',
            'rate_limit' => $item['rate-limit'] ?? '-',
            'keepalive_timeout' => $item['keepalive-timeout'] ?? '-',
            'idle_timeout' => $item['idle-timeout'] ?? '-',
            'shared_users' => $item['shared-users'] ?? '-',
        ], array_slice($profiles, 0, 100)),
        'users' => array_map(static fn ($item) => [
            'name' => $item['name'] ?? '-',
            'profile' => $item['profile'] ?? '-',
            'server' => $item['server'] ?? '-',
            'uptime' => $item['uptime'] ?? '-',
            'limit_uptime' => $item['limit-uptime'] ?? '-',
            'bytes_in' => $item['bytes-in'] ?? '0',
            'bytes_out' => $item['bytes-out'] ?? '0',
            'limit_bytes_total' => $item['limit-bytes-total'] ?? '-',
            'disabled' => ($item['disabled'] ?? 'false') === 'true',
            'comment' => $item['comment'] ?? '',
        ], array_slice($users, 0, 100)),
        'active' => array_map(static fn ($item) => [
            'id' => $item['.id'] ?? '',
            'user' => $item['user'] ?? '-',
            'address' => $item['address'] ?? '-',
            'uptime' => $item['uptime'] ?? '-',
            'mac_address' => $item['mac-address'] ?? '-',
            'server' => $item['server'] ?? '-',
            'bytes_in' => $item['bytes-in'] ?? '0',
            'bytes_out' => $item['bytes-out'] ?? '0',
        ], array_slice($active, 0, 100)),
        'servers' => array_map(static fn ($item) => [
            'name' => $item['name'] ?? '-',
        ], array_slice($servers, 0, 100)),
    ]);
} catch (Throwable $e) {
    mobile_json_response([
        'success' => false,
        'message' => $e->getMessage(),
        'tenant' => mobile_hotspot_tenant_summary($tenantId),
        'profiles' => [],
        'users' => [],
        'active' => [],
        'servers' => [],
    ], 200);
}
