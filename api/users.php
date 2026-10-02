<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_client.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];
$mkConfig = app_mikrotik_config($tenantId);

function clear_user_cache(int $tenantId): void {
    app_cache_forget(app_cache_key('dashboard_overview', $tenantId));
    app_cache_forget(app_cache_key('map_topology', $tenantId));
    $trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
    app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));
}

function mikrotik_normalize_rows_local($result): array {
    if (!is_array($result) || isset($result['!trap']) || isset($result['!fatal'])) {
        return [];
    }

    return array_values(array_filter($result, static fn ($item) => is_array($item)));
}

function mikrotik_extract_error_local($result): ?string {
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

function mikrotik_configure_client_local(MikroTikClient $api, array $mkConfig): void {
    $api->transport = $mkConfig['transport'] ?? 'socket';
    $api->port = $mkConfig['port'] ?? 8728;
    $api->timeout = $mkConfig['timeout'] ?? 1;
    $api->attempts = $mkConfig['attempts'] ?? 1;
    $api->delay = $mkConfig['delay'] ?? 0;
    $api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
    $api->restPort = $mkConfig['rest_port'] ?? 443;
    $api->restPath = $mkConfig['rest_path'] ?? '/rest';
    $api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);
}

function mikrotik_delete_pppoe_user_local(array $mkConfig, string $username): array {
    $username = trim($username);
    if ($username === '' || ($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        return ['removed_sessions' => 0, 'removed_secret' => false, 'message' => null];
    }

    $api = new MikroTikClient();
    mikrotik_configure_client_local($api, $mkConfig);
    if (!$api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
        return [
            'removed_sessions' => 0,
            'removed_secret' => false,
            'message' => 'Sinkron hapus PPPoE di MikroTik gagal: ' . ($api->error_str ?: 'koneksi gagal')
        ];
    }

    try {
        $removedSessions = 0;
        foreach (mikrotik_normalize_rows_local($api->comm('/ppp/active/print', ['?name' => $username])) as $session) {
            $sessionId = trim((string) ($session['.id'] ?? ''));
            if ($sessionId === '') {
                continue;
            }

            $error = mikrotik_extract_error_local($api->comm('/ppp/active/remove', ['numbers' => $sessionId]));
            if ($error !== null) {
                throw new RuntimeException($error);
            }
            $removedSessions++;
        }

        $removedSecret = false;
        $secrets = mikrotik_normalize_rows_local($api->comm('/ppp/secret/print', ['?name' => $username]));
        if ($secrets !== [] && !empty($secrets[0]['.id'])) {
            $error = mikrotik_extract_error_local($api->comm('/ppp/secret/remove', ['numbers' => $secrets[0]['.id']]));
            if ($error !== null) {
                throw new RuntimeException($error);
            }
            $removedSecret = true;
        }

        return ['removed_sessions' => $removedSessions, 'removed_secret' => $removedSecret, 'message' => null];
    } catch (Throwable $e) {
        return [
            'removed_sessions' => 0,
            'removed_secret' => false,
            'message' => 'Sinkron hapus PPPoE di MikroTik gagal: ' . $e->getMessage()
        ];
    } finally {
        $api->disconnect();
    }
}

function get_mikrotik_remote_lookup(array $mkConfig, int $tenantId): array {
    if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
        return app_mikrotik_bridge_remote_lookup($tenantId);
    }

    $api = new MikroTikClient();
    mikrotik_configure_client_local($api, $mkConfig);

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

    foreach (mikrotik_normalize_rows_local($api->comm('/ppp/secret/print')) as $item) {
        $username = trim((string) ($item['name'] ?? ''));
        $remoteAddress = trim((string) ($item['remote-address'] ?? ''));
        if ($username !== '' && !isset($lookup[$username]) && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            $lookup[$username] = $remoteAddress;
        }
    }

    $api->disconnect();
    return $lookup;
}

function is_valid_remote_endpoint(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return true;
    }

    if (filter_var($value, FILTER_VALIDATE_IP)) {
        return true;
    }

    if (preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $value)) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    return (bool) preg_match('/^(localhost|[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?)*)(?::\d{1,5})?(?:\/.*)?$/i', $value);
}

function validate_user_payload(array $input, bool $requirePassword = true): array {
    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $nama = trim((string) ($input['nama'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $remoteIp = trim((string) ($input['remote_ip'] ?? ''));
    $koordinat = trim((string) ($input['koordinat'] ?? ''));

    if ($username === '' || $nama === '' || $koordinat === '' || ($requirePassword && $password === '')) {
        return ['success' => false, 'message' => 'Input tidak valid'];
    }

    if (!is_valid_remote_endpoint($remoteIp)) {
        return ['success' => false, 'message' => 'Remote IP / URL tidak valid'];
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
        return ['success' => false, 'message' => 'Nomor telepon tidak valid'];
    }

    $coords = array_map('trim', explode(',', $koordinat));
    if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
        return ['success' => false, 'message' => 'Koordinat user tidak valid'];
    }

    return [
        'success' => true,
        'data' => [
            'username' => $username,
            'password' => $password,
            'nama' => $nama,
            'phone' => $phone,
            'remote_ip' => $remoteIp,
            'koordinat' => $koordinat,
            'pppoe_password' => $password
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_owner_api();
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $userId = (int) ($_POST['id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'User tidak valid']);
            exit;
        }

        try {
            $stmtUser = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmtUser->execute([$userId, $tenantId]);
            $user = $stmtUser->fetch();
            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
                exit;
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM relasi_jaringan WHERE user_id = ? AND tenant_id = ?")->execute([$userId, $tenantId]);
            $pdo->prepare("DELETE FROM billing WHERE user_id = ? AND tenant_id = ?")->execute([$userId, $tenantId]);
            $pdo->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ? LIMIT 1")->execute([$userId, $tenantId]);
            $pdo->commit();

            $mikrotikDelete = mikrotik_delete_pppoe_user_local($mkConfig, (string) $user['username']);

            clear_user_cache($tenantId);
            $message = 'User berhasil dihapus';
            if ($mikrotikDelete['message'] !== null) {
                $message .= '. ' . $mikrotikDelete['message'];
            } elseif ($mikrotikDelete['removed_secret'] || $mikrotikDelete['removed_sessions'] > 0) {
                $message .= ', PPP secret dan active connection MikroTik ikut dibersihkan';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus user']);
        }
        exit;
    }

    if ($action === 'update') {
        $userId = (int) ($_POST['id'] ?? 0);
        $validated = validate_user_payload($_POST, false);
        if ($userId <= 0 || !$validated['success']) {
            http_response_code(422);
            echo json_encode($validated['success'] ? ['success' => false, 'message' => 'User tidak valid'] : $validated);
            exit;
        }

        $payload = $validated['data'];
        try {
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmtUser->execute([$userId, $tenantId]);
            if (!$stmtUser->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
                exit;
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND username = ? AND id != ? LIMIT 1");
            $stmtCheck->execute([$tenantId, $payload['username'], $userId]);
            if ($stmtCheck->fetch()) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Username sudah digunakan pada tenant ini']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE users
                SET username = ?, nama = ?, phone = ?, remote_ip = ?, koordinat = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $payload['username'],
                $payload['nama'],
                $payload['phone'] ?: null,
                $payload['remote_ip'] ?: null,
                $payload['koordinat'],
                $userId,
                $tenantId
            ]);

            if ($payload['pppoe_password'] !== '' && !empty($mkConfig['sync_on_user_create']) && ($mkConfig['host'] ?? '') !== '' && ($mkConfig['user'] ?? '') !== '') {
                $api = new MikroTikClient();
                $api->transport = $mkConfig['transport'] ?? 'socket';
                $api->port = $mkConfig['port'] ?? 8728;
                $api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
                $api->restPort = $mkConfig['rest_port'] ?? 443;
                $api->restPath = $mkConfig['rest_path'] ?? '/rest';
                $api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);
                if ($api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
                    $existingSecret = mikrotik_normalize_rows_local($api->comm('/ppp/secret/print', [
                        '?name' => $payload['username']
                    ]));
                    if ($existingSecret !== [] && !empty($existingSecret[0]['.id'])) {
                        $api->comm('/ppp/secret/set', [
                            '.id' => $existingSecret[0]['.id'],
                            'password' => $payload['pppoe_password']
                        ]);
                    }
                    $api->disconnect();
                }
            }

            clear_user_cache($tenantId);
            echo json_encode(['success' => true, 'message' => 'User berhasil diperbarui']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui user']);
        }
        exit;
    }

    $validated = validate_user_payload($_POST, false);
    if (!$validated['success']) {
        http_response_code(422);
        echo json_encode($validated);
        exit;
    }

    $payload = $validated['data'];
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND username = ?");
        $stmtCheck->execute([$tenantId, $payload['username']]);
        if ($stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username sudah digunakan pada tenant ini']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (tenant_id, username, nama, phone, remote_ip, koordinat, status)
            VALUES (?, ?, ?, ?, ?, ?, 'offline')
        ");
        $stmt->execute([
            $tenantId,
            $payload['username'],
            $payload['nama'],
            $payload['phone'] ?: null,
            $payload['remote_ip'] ?: null,
            $payload['koordinat']
        ]);

        $mikrotikMessage = null;
        if ($payload['pppoe_password'] !== '' && !empty($mkConfig['sync_on_user_create']) && ($mkConfig['host'] ?? '') !== '' && ($mkConfig['user'] ?? '') !== '') {
            $api = new MikroTikClient();
            $api->transport = $mkConfig['transport'] ?? 'socket';
            $api->port = $mkConfig['port'] ?? 8728;
            $api->restScheme = $mkConfig['rest_scheme'] ?? 'https';
            $api->restPort = $mkConfig['rest_port'] ?? 443;
            $api->restPath = $mkConfig['rest_path'] ?? '/rest';
            $api->restAllowInsecure = (bool) ($mkConfig['rest_allow_insecure'] ?? true);
            if ($api->connect($mkConfig['host'], $mkConfig['user'], $mkConfig['pass'])) {
                $api->comm('/ppp/secret/add', [
                    'name' => $payload['username'],
                    'password' => $payload['pppoe_password'],
                    'service' => 'pppoe',
                    'profile' => 'default'
                ]);
                $api->disconnect();
            } else {
                $mikrotikMessage = ' User tersimpan, tapi sinkron MikroTik gagal.';
            }
        }

        clear_user_cache($tenantId);
        echo json_encode([
            'success' => true,
            'message' => 'User berhasil ditambahkan.' . ($mikrotikMessage ?? '')
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan user: ' . $e->getMessage()]);
    }
    exit;
}

try {
    $remoteLookup = get_mikrotik_remote_lookup($mkConfig, $tenantId);
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.username,
            u.nama,
            u.phone,
            u.remote_ip,
            u.koordinat,
            u.status,
            u.expired,
                u.created_at,
                r.server_id,
                r.odc_id,
            r.odp_id
        FROM users u
        LEFT JOIN relasi_jaringan r ON r.user_id = u.id AND r.tenant_id = u.tenant_id
        WHERE u.tenant_id = ?
        ORDER BY u.id DESC
    ");
    $stmt->execute([$tenantId]);
    $users = array_map(static function (array $user) use ($remoteLookup) {
        $username = (string) ($user['username'] ?? '');
        $user['remote_ip_live'] = $remoteLookup[$username] ?? null;
        return $user;
    }, $stmt->fetchAll());

    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
