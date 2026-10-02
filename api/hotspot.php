<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_client.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];
$mk_config = app_mikrotik_config($tenantId);

function hotspot_normalize_rows($result): array
{
    if (!is_array($result) || isset($result['!trap']) || isset($result['!fatal'])) return [];
    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

function hotspot_extract_error($result): ?string
{
    if (!is_array($result)) return null;
    $messages = [];
    foreach (['!trap', '!fatal'] as $bucket) {
        if (!isset($result[$bucket]) || !is_array($result[$bucket])) continue;
        foreach ($result[$bucket] as $entry) {
            if (is_array($entry) && !empty($entry['message'])) $messages[] = $entry['message'];
        }
    }
    return $messages === [] ? null : implode('; ', array_unique($messages));
}

function hotspot_connect(array $mk_config): MikroTikClient
{
    if (($mk_config['host'] ?? '') === '' || ($mk_config['user'] ?? '') === '') {
        throw new RuntimeException('Konfigurasi MikroTik tenant ini belum lengkap. Lengkapi dari menu settings tenant.');
    }
    $api = new MikroTikClient();
    $api->transport = $mk_config['transport'] ?? 'socket';
    $api->port = $mk_config['port'] ?? 8728;
    $api->timeout = 10; // Moderate timeout like PPPoE but slightly higher for VPN
    $api->attempts = 1;
    $api->delay = 0;
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

function hotspot_clear_cache(): void
{
    global $tenantId;
    $trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
    app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));
    app_cache_forget(app_cache_key('dashboard_overview', $tenantId));
}

function hotspot_random_token(int $length = 6, string $mode = 'capital_numbers'): string
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
    for ($i = 0; $i < $length; $i++) $token .= $alphabet[random_int(0, $max)];
    return $token;
}

function hotspot_to_bytes($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $number = (float) $value;
    if ($number <= 0) return null;
    return (string) (int) round($number * 1024 * 1024);
}

function hotspot_post_names(): array
{
    $raw = $_POST['names'] ?? $_POST['names_json'] ?? '[]';
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $decoded = json_decode((string) $raw, true);
        $items = is_array($decoded) ? $decoded : [];
    }
    $names = [];
    foreach ($items as $item) {
        $name = trim((string) $item);
        if ($name !== '') $names[$name] = true;
    }
    return array_keys($names);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_owner_api();
    $action = trim((string) ($_POST['action'] ?? ''));
    $api = null;
    try {
        $api = hotspot_connect($mk_config);
        if ($action === 'create_vouchers') {
            $inputVal = trim((string) ($_POST['input'] ?? ''));
            $count = (int) ($_POST['count'] ?? 1);
            $passwordMode = 'username';
            $profile = trim((string) ($_POST['profile'] ?? 'default'));
            $server = trim((string) ($_POST['server'] ?? ''));
            $limitTimeMode = trim((string) ($_POST['limit_time_mode'] ?? 'none'));
            $limitHours = $limitTimeMode === 'custom' ? (int) ($_POST['limit_hours'] ?? 0) : 0;
            $limitMinutes = $limitTimeMode === 'custom' ? (int) ($_POST['limit_minutes'] ?? 0) : 0;
            $limitUptime = '';
            if ($limitHours > 0) $limitUptime .= $limitHours . 'h';
            if ($limitMinutes > 0) $limitUptime .= $limitMinutes . 'm';
            $limitMb = trim((string) ($_POST['limit_mb'] ?? ''));
            $quotaDisplay = trim((string) ($_POST['quota_display'] ?? ''));
            $price = trim((string) ($_POST['price'] ?? ''));
            $shopId = trim((string) ($_POST['shop_id'] ?? ''));
            $comment = trim((string) ($_POST['comment'] ?? ''));

            $charMode = trim((string) ($_POST['char_mode'] ?? 'capital_numbers'));
            $charLength = (int) ($_POST['char_length'] ?? 6);
            $userMode = trim((string) ($_POST['user_mode'] ?? 'random'));

            if ($count < 1 || $count > 200) throw new RuntimeException('Jumlah voucher 1-200.');

            $manualNames = [];
            if ($userMode === 'manual') {
                if ($inputVal === '') throw new RuntimeException('Input manual wajib diisi.');
                $manualNames = preg_split("/[\s,]+/", $inputVal, -1, PREG_SPLIT_NO_EMPTY);
                $manualNames = array_unique($manualNames);
                $targetCount = count($manualNames);
            } else {
                $targetCount = $count;
            }

            $created = [];
            for ($i = 0; $i < $targetCount; $i++) {
                if ($userMode === 'manual') $name = $manualNames[$i];
                else if ($userMode === 'prefix') $name = $inputVal . hotspot_random_token($charLength, $charMode);
                else $name = hotspot_random_token($charLength, $charMode);
                
                $idVoucher = (string)random_int(1000, 9999);
                while($idVoucher === $name) { $idVoucher = (string)random_int(1000, 9999); }

            $fullComment = $comment;
            if (stripos($comment, 'TESTER') !== false) $fullComment = "[U:1]" . ($fullComment !== '' ? ' ' . $fullComment : '');
            if ($quotaDisplay !== '') $fullComment = "[Q:$quotaDisplay]" . ($fullComment !== '' ? ' ' . $fullComment : '');
            if ($price !== '') $fullComment = "[P:$price]" . ($fullComment !== '' ? ' ' . $fullComment : '');
            if ($shopId !== '') $fullComment = "[S:$shopId]" . ($fullComment !== '' ? ' ' . $fullComment : '');
            $fullComment = "[I:$idVoucher]" . ($fullComment !== '' ? ' ' . $fullComment : '');

                $password = $name;
                $payload = [ 'name' => $name, 'password' => $password, 'profile' => $profile ?: 'default' ];
                if ($server !== '') $payload['server'] = $server;
                if ($limitUptime !== '') $payload['limit-uptime'] = $limitUptime;
                $bytes = hotspot_to_bytes($limitMb);
                if ($bytes !== null) $payload['limit-bytes-total'] = $bytes;
                if ($fullComment !== '') $payload['comment'] = $fullComment;

                $result = $api->comm('/ip/hotspot/user/add', $payload);
                $error = hotspot_extract_error($result);
                if ($error !== null) { if ($userMode === 'manual') continue; throw new RuntimeException($error); }
                $created[] = [ 
                    'username' => $name, 
                    'password' => $password, 
                    'profile' => $payload['profile'], 
                    'limit_uptime' => $limitUptime !== '' ? $limitUptime : 'Tanpa Limit', 
                    'limit_mb' => $limitMb ?: '-',
                    'quota_display' => $quotaDisplay,
                    'price' => $price,
                    'shop_id' => $shopId,
                    'id_voucher' => $idVoucher,
                    'comment' => $fullComment
                ];
            }
            hotspot_clear_cache();
            echo json_encode(['success' => true, 'message' => count($created) . ' voucher berhasil dibuat.', 'created' => $created]);
            exit;
        }
        if ($action === 'create_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') throw new RuntimeException('Nama profile wajib.');
            $payload = ['name' => $name, 'shared-users' => (string)($_POST['shared_users'] ?? 1)];
            if (!empty($_POST['rate_limit'])) $payload['rate-limit'] = $_POST['rate_limit'];
            if (!empty($_POST['keepalive_timeout'])) $payload['keepalive-timeout'] = $_POST['keepalive_timeout'];
            if (!empty($_POST['idle_timeout'])) $payload['idle-timeout'] = $_POST['idle_timeout'];
            
            $result = $api->comm('/ip/hotspot/user/profile/add', $payload);
            $error = hotspot_extract_error($result);
            if ($error !== null) throw new RuntimeException($error);
            hotspot_clear_cache();
            echo json_encode(['success' => true, 'message' => "Profile {$name} dibuat."]);
            exit;
        }
        if (in_array($action, ['delete_user', 'enable_user', 'disable_user'], true)) {
            $name = trim((string) ($_POST['name'] ?? ''));
            $existing = hotspot_normalize_rows($api->comm('/ip/hotspot/user/print', ['?name' => $name]));
            if ($existing === [] || empty($existing[0]['.id'])) throw new RuntimeException('Voucher tidak ditemukan.');
            $command = match ($action) { 'delete_user' => '/ip/hotspot/user/remove', 'enable_user' => '/ip/hotspot/user/enable', default => '/ip/hotspot/user/disable' };
            $result = $api->comm($command, ['numbers' => $existing[0]['.id']]);
            $error = hotspot_extract_error($result);
            if ($error !== null) throw new RuntimeException($error);
            hotspot_clear_cache();
            echo json_encode(['success' => true, 'message' => "Voucher {$name} diproses."]);
            exit;
        }
        if ($action === 'delete_expired_users') {
            $names = hotspot_post_names();
            if ($names === []) throw new RuntimeException('Tidak ada voucher expired yang dipilih untuk dihapus.');
            $removed = 0;
            foreach ($names as $name) {
                $existing = hotspot_normalize_rows($api->comm('/ip/hotspot/user/print', ['?name' => $name]));
                if ($existing === [] || empty($existing[0]['.id'])) continue;
                $result = $api->comm('/ip/hotspot/user/remove', ['numbers' => $existing[0]['.id']]);
                $error = hotspot_extract_error($result);
                if ($error !== null) throw new RuntimeException($error);
                $removed++;
            }
            hotspot_clear_cache();
            echo json_encode(['success' => true, 'message' => $removed . ' voucher expired berhasil dihapus.']);
            exit;
        }
        if ($action === 'delete_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $existing = hotspot_normalize_rows($api->comm('/ip/hotspot/user/profile/print', ['?name' => $name]));
            if ($existing === [] || empty($existing[0]['.id'])) throw new RuntimeException('Profile tidak ditemukan.');
            $result = $api->comm('/ip/hotspot/user/profile/remove', ['numbers' => $existing[0]['.id']]);
            $error = hotspot_extract_error($result);
            if ($error !== null) throw new RuntimeException($error);
            hotspot_clear_cache();
            echo json_encode(['success' => true, 'message' => "Profile {$name} dihapus."]);
            exit;
        }
        if ($action === 'remove_active') {
            $result = $api->comm('/ip/hotspot/active/remove', ['numbers' => $_POST['session_id'] ?? '']);
            $error = hotspot_extract_error($result);
            if ($error !== null) throw new RuntimeException($error);
            hotspot_clear_cache();
            echo json_encode(['success' => true, 'message' => 'Active hotspot dihapus.']);
            exit;
        }
        http_response_code(422); echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']); exit;
    } catch (Throwable $e) { http_response_code(422); echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; }
    finally { if ($api instanceof MikroTikClient) $api->disconnect(); }
}

try {
    $api = hotspot_connect($mk_config);
    $profiles = hotspot_normalize_rows($api->comm('/ip/hotspot/user/profile/print'));
    $users = hotspot_normalize_rows($api->comm('/ip/hotspot/user/print'));
    $active = hotspot_normalize_rows($api->comm('/ip/hotspot/active/print'));
    $servers = hotspot_normalize_rows($api->comm('/ip/hotspot/print'));
    $api->disconnect();
    usort($profiles, static fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    usort($users, static fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    usort($active, static fn ($a, $b) => strcasecmp((string) ($a['user'] ?? ''), (string) ($b['user'] ?? '')));
    echo json_encode([ 'success' => true,
        'profiles' => array_map(static fn ($item) => [
            'name' => $item['name'] ?? '-', 
            'rate_limit' => $item['rate-limit'] ?? '-', 
            'keepalive_timeout' => $item['keepalive-timeout'] ?? '-',
            'idle_timeout' => $item['idle-timeout'] ?? '-',
            'shared_users' => $item['shared-users'] ?? '-'
        ], array_slice($profiles, 0, 100)),
        'users' => array_map(static fn ($item) => ['name' => $item['name'] ?? '-', 'profile' => $item['profile'] ?? '-', 'server' => $item['server'] ?? '-', 'uptime' => $item['uptime'] ?? '-', 'limit_uptime' => $item['limit-uptime'] ?? '-', 'bytes_in' => $item['bytes-in'] ?? '0', 'bytes_out' => $item['bytes-out'] ?? '0', 'limit_bytes_total' => $item['limit-bytes-total'] ?? '-', 'disabled' => ($item['disabled'] ?? 'false') === 'true', 'comment' => $item['comment'] ?? ''], array_slice($users, 0, 100)),
        'active' => array_map(static fn ($item) => [
            'id' => $item['.id'] ?? '',
            'user' => $item['user'] ?? '-',
            'address' => $item['address'] ?? '-',
            'uptime' => $item['uptime'] ?? '-',
            'mac_address' => $item['mac-address'] ?? '-',
            'server' => $item['server'] ?? '-',
            'bytes_in' => $item['bytes-in'] ?? '0',
            'bytes_out' => $item['bytes-out'] ?? '0'
        ], array_slice($active, 0, 100)),
        'servers' => array_map(static fn ($item) => ['name' => $item['name'] ?? '-'], array_slice($servers, 0, 100))
    ]);
} catch (Throwable $e) { echo json_encode([ 'success' => false, 'message' => $e->getMessage(), 'profiles' => [], 'users' => [], 'active' => [], 'servers' => [] ]); }
