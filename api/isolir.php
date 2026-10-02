<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_isolir.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_owner_api();
$tenantId = (int) $account['tenant_id'];

function isolir_clear_cache(int $tenantId): void
{
    $trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
    app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));
    app_cache_forget(app_cache_key('dashboard_overview', $tenantId));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'save'));
    $profileName = trim((string) ($_POST['profile_name'] ?? ''));
    $rateLimit = trim((string) ($_POST['rate_limit'] ?? ''));
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if ($profileName === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Nama profile isolir wajib diisi']);
        exit;
    }

    app_setting_set('isolir_profile_name', $profileName, $tenantId);
    app_setting_set('isolir_profile_rate_limit', $rateLimit, $tenantId);
    app_setting_set('isolir_profile_comment', $comment, $tenantId);

    $message = 'Setup isolir berhasil disimpan.';

    if ($action === 'save_and_create') {
        try {
            $api = mikrotik_isolir_connect(app_mikrotik_config($tenantId));
            try {
                $result = mikrotik_isolir_ensure_profile($api, mikrotik_isolir_settings($tenantId));
                $message = $result['created']
                    ? "Profile isolir {$profileName} berhasil dibuat di MikroTik."
                    : "Setup isolir disimpan. Profile {$profileName} sudah ada di MikroTik.";
            } finally {
                $api->disconnect();
            }
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'setup' => mikrotik_isolir_setup_status($tenantId)
            ]);
            exit;
        }
    }

    isolir_clear_cache($tenantId);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'setup' => mikrotik_isolir_setup_status($tenantId)
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'setup' => mikrotik_isolir_setup_status($tenantId)
]);
