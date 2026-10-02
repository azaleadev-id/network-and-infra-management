<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';
require_once __DIR__ . '/../config/mikrotik_backup.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_owner_api();
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'regenerate_mikrotik_bridge_api_key') {
        app_cache_forget(app_mikrotik_bridge_snapshot_key($tenantId));
        $apiKey = app_mikrotik_bridge_api_key_reset($tenantId);

        echo json_encode([
            'success' => true,
            'message' => 'API key bridge MikroTik berhasil dibuat ulang',
            'data' => [
                'mikrotik_bridge_api_key' => $apiKey
            ]
        ]);
        exit;
    }

    if ($action === 'run_mikrotik_backup') {
        $mkConfig = app_mikrotik_config($tenantId);
        if (($mkConfig['host'] ?? '') === '' || ($mkConfig['user'] ?? '') === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Konfigurasi MikroTik belum lengkap'
            ]);
            exit;
        }

        try {
            $api = mikrotik_isolir_connect($mkConfig);
            $backupResult = mikrotik_backup_run($api, $tenantId, $mkConfig);
            if (!($backupResult['success'] ?? false)) {
                http_response_code(502);
                echo json_encode([
                    'success' => false,
                    'message' => $backupResult['message'] ?? 'Backup MikroTik gagal'
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => $backupResult['message'] ?? 'Backup MikroTik berhasil dibuat',
                'data' => [
                    'backup_name' => $backupResult['file_name'] ?? $backupResult['name'] ?? '',
                    'backup_last_at' => $backupResult['last_at'] ?? date(DATE_ATOM)
                ]
            ]);
            exit;
        } catch (Throwable $e) {
            http_response_code(502);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        } finally {
            if (isset($api) && $api instanceof MikroTikClient) {
                $api->disconnect();
            }
        }
    }

    $hasAppName = array_key_exists('app_name', $_POST);
    $hasTrafficInterface = array_key_exists('traffic_interface', $_POST);
    $hasBillingDueDay = array_key_exists('billing_due_day', $_POST);
    $hasBackupEnabled = array_key_exists('mikrotik_backup_enabled', $_POST);
    $hasBackupInterval = array_key_exists('mikrotik_backup_interval_days', $_POST);
    $hasBackupTime = array_key_exists('mikrotik_backup_time', $_POST);
    $hasBackupRetention = array_key_exists('mikrotik_backup_retention_days', $_POST);

    $appName = $hasAppName ? trim((string) ($_POST['app_name'] ?? '')) : null;
    $trafficInterface = $hasTrafficInterface ? trim((string) ($_POST['traffic_interface'] ?? '')) : null;
    $billingDueDay = $hasBillingDueDay ? (int) ($_POST['billing_due_day'] ?? 0) : null;
    $backupEnabledRaw = $hasBackupEnabled ? (string) ($_POST['mikrotik_backup_enabled'] ?? '') : null;
    $backupIntervalRaw = $hasBackupInterval ? (string) ($_POST['mikrotik_backup_interval_days'] ?? '') : null;
    $backupTime = $hasBackupTime ? trim((string) ($_POST['mikrotik_backup_time'] ?? '')) : null;
    $backupRetentionRaw = $hasBackupRetention ? (string) ($_POST['mikrotik_backup_retention_days'] ?? '') : null;

    if (!$hasAppName && !$hasTrafficInterface && !$hasBillingDueDay && !$hasBackupEnabled && !$hasBackupInterval && !$hasBackupTime && !$hasBackupRetention) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Tidak ada pengaturan yang dikirim']);
        exit;
    }

    if ($hasAppName && $appName === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Nama aplikasi wajib diisi']);
        exit;
    }

    if ($hasBillingDueDay && ($billingDueDay < 1 || $billingDueDay > 31)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Tanggal jatuh tempo serentak harus antara 1 sampai 31']);
        exit;
    }

    if ($hasBackupInterval) {
        $backupInterval = (int) $backupIntervalRaw;
        if ($backupInterval < 1 || $backupInterval > 365) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Interval backup harus antara 1 sampai 365 hari']);
            exit;
        }
    }

    if ($hasBackupTime && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $backupTime)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Jam backup harus format HH:MM']);
        exit;
    }

    if ($hasBackupRetention) {
        $backupRetention = (int) $backupRetentionRaw;
        if ($backupRetention < 1 || $backupRetention > 3650) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Hapus backup lama harus antara 1 sampai 3650 hari']);
            exit;
        }
    }

    $previousTrafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
    if ($hasAppName) {
        app_setting_set('app_name', $appName, $tenantId);
    }
    if ($hasTrafficInterface && $trafficInterface !== '') {
        app_setting_set('traffic_interface', $trafficInterface, $tenantId);
    }
    if ($hasBillingDueDay) {
        app_setting_set('billing_due_day', (string) $billingDueDay, $tenantId);
    }
    if ($hasBackupEnabled) {
        $backupEnabled = in_array(strtolower((string) $backupEnabledRaw), ['1', 'true', 'yes', 'on'], true);
        app_setting_set('mikrotik_backup_enabled', $backupEnabled ? 'true' : 'false', $tenantId);
    }
    if ($hasBackupInterval) {
        app_setting_set('mikrotik_backup_interval_days', (string) (int) $backupIntervalRaw, $tenantId);
    }
    if ($hasBackupTime) {
        app_setting_set('mikrotik_backup_time', (string) $backupTime, $tenantId);
    }
    if ($hasBackupRetention) {
        app_setting_set('mikrotik_backup_retention_days', (string) (int) $backupRetentionRaw, $tenantId);
    }

    app_cache_forget(app_cache_key('mikrotik_dashboard_' . $previousTrafficInterface, $tenantId));
    app_cache_forget(app_cache_key('mikrotik_dashboard_' . ($trafficInterface !== null && $trafficInterface !== '' ? $trafficInterface : $previousTrafficInterface), $tenantId));

    echo json_encode([
        'success' => true,
        'message' => 'Pengaturan berhasil diperbarui',
        'data' => [
            'app_name' => app_setting_get('app_name', 'Nikonet', $tenantId),
            'traffic_interface' => app_setting_get('traffic_interface', $previousTrafficInterface, $tenantId),
            'billing_due_day' => (int) app_setting_get('billing_due_day', 27, $tenantId),
            'mikrotik_bridge_api_key' => app_mikrotik_bridge_api_key_get($tenantId, true)
        ]
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'app_name' => app_setting_get('app_name', 'Nikonet', $tenantId),
        'traffic_interface' => app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId),
        'billing_due_day' => (int) app_setting_get('billing_due_day', 27, $tenantId),
        'mikrotik_bridge_api_key' => app_mikrotik_bridge_api_key_get($tenantId, true)
    ]
]);
