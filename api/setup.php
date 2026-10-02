<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

require_once __DIR__ . '/../config/mikrotik_client.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_owner_api();
$tenantId = (int) $account['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan'
    ]);
    exit;
}

$action = trim((string) ($_POST['action'] ?? 'save'));
$host = trim((string) ($_POST['mikrotik_host'] ?? ''));
$user = trim((string) ($_POST['mikrotik_user'] ?? ''));
$pass = (string) ($_POST['mikrotik_pass'] ?? '');
$transport = strtolower(trim((string) ($_POST['mikrotik_transport'] ?? 'socket')));
$backupEnabledRaw = $_POST['mikrotik_backup_enabled'] ?? null;
$backupIntervalRaw = $_POST['mikrotik_backup_interval_days'] ?? null;

if ($host === '' || $user === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Host MikroTik dan username MikroTik wajib diisi'
    ]);
    exit;
}

$currentPassword = (string) app_setting_get('mikrotik_pass', '', $tenantId);
$targetPass = ($pass !== '') ? $pass : $currentPassword;

if ($targetPass === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Password MikroTik wajib diisi'
    ]);
    exit;
}

if ($action === 'test_connection') {
    $parsed = app_parse_mikrotik_host($host);
    $api = new MikroTikClient();
    $api->transport = $transport;
    $api->host = $parsed['host'];
    $api->port = $parsed['socket_port'] ?? 8728;
    $api->restScheme = $parsed['rest_scheme'] ?? 'https';
    $api->restPort = $parsed['rest_port'] ?? 443;
    $api->restPath = $parsed['rest_path'] ?? '/rest';
    $api->timeout = 5;

    if ($api->connect($parsed['host'], $user, $targetPass)) {
        $api->disconnect();
        echo json_encode([
            'success' => true,
            'message' => 'Koneksi ke MikroTik BERHASIL! (' . strtoupper($api->activeTransport() ?: $transport) . ')'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Koneksi GAGAL: ' . ($api->error_str ?: 'Tidak dapat terhubung')
        ]);
    }
    exit;
}

if ($backupIntervalRaw !== null) {
    $backupInterval = (int) $backupIntervalRaw;
    if ($backupInterval < 1 || $backupInterval > 365) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Interval backup harus antara 1 sampai 365 hari'
        ]);
        exit;
    }
}

app_setting_set('mikrotik_host', $host, $tenantId);
app_setting_set('mikrotik_user', $user, $tenantId);
app_setting_set('mikrotik_pass', $pass !== '' ? $pass : $currentPassword, $tenantId);
app_setting_set('mikrotik_transport', $transport, $tenantId);
if ($backupEnabledRaw !== null) {
    $backupEnabled = in_array(strtolower((string) $backupEnabledRaw), ['1', 'true', 'yes', 'on'], true);
    app_setting_set('mikrotik_backup_enabled', $backupEnabled ? 'true' : 'false', $tenantId);
}
if ($backupIntervalRaw !== null) {
    app_setting_set('mikrotik_backup_interval_days', (string) (int) $backupIntervalRaw, $tenantId);
}
$bridgeApiKey = app_mikrotik_bridge_api_key_get($tenantId, true);

$trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));

echo json_encode([
    'success' => true,
    'message' => 'Koneksi MikroTik berhasil disimpan ke tenant_settings',
    'data' => [
        'mikrotik_host' => $host,
        'mikrotik_user' => $user,
        'mikrotik_pass_saved' => true,
        'mikrotik_bridge_api_key' => $bridgeApiKey
    ]
]);
