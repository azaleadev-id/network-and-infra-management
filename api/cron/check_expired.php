<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mikrotik_backup.php';

function cron_request_authorized(): bool
{
    if (php_sapi_name() === 'cli') {
        return true;
    }

    $expectedToken = (string) env_value('CRON_TOKEN', '');
    $providedToken = (string) ($_GET['token'] ?? '');

    return $expectedToken !== '' && hash_equals($expectedToken, $providedToken);
}

if (!cron_request_authorized()) {
    http_response_code(403);
    die('Access Denied');
}

echo "Mulai Mengecek Billing Expired...\n";

try {
    $today = date('Y-m-d');
    $tenantRows = $pdo->query("SELECT id, name FROM tenants WHERE status = 'active' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tenantRows as $tenant) {
        $tenantId = (int) $tenant['id'];
        echo "Tenant {$tenant['name']} (#{$tenantId})\n";

        $stmt = $pdo->prepare("
            SELECT b.id, b.user_id, u.username
            FROM billing b
            JOIN users u ON b.user_id = u.id AND u.tenant_id = b.tenant_id
            WHERE b.tenant_id = ?
              AND b.status = 'aktif'
              AND b.jatuh_tempo <= ?
              AND (b.isolir_enabled = 0 OR b.isolir_at IS NULL)
        ");
        $stmt->execute([$tenantId, $today]);
        $expiredUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mkConfig = app_mikrotik_config($tenantId);
        $api = null;
        if (($mkConfig['host'] ?? '') !== '' && ($mkConfig['user'] ?? '') !== '') {
            try {
                $api = mikrotik_isolir_connect($mkConfig);
            } catch (Throwable $e) {
                echo "- MikroTik tenant ini gagal terkoneksi: {$e->getMessage()}\n";
            }
        }

        foreach ($expiredUsers as $user) {
            echo "- Expired: {$user['username']}\n";

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE billing SET status = 'expired', last_update = CURRENT_TIMESTAMP WHERE tenant_id = ? AND id = ?")
                ->execute([$tenantId, $user['id']]);
            $pdo->prepare("UPDATE users SET status = 'disabled' WHERE tenant_id = ? AND id = ?")
                ->execute([$tenantId, $user['user_id']]);
            $pdo->commit();

            if ($api instanceof MikroTikClient) {
                $secret = mikrotik_isolir_secret_by_name($api, (string) $user['username']);
                if ($secret !== null && !empty($secret['.id'])) {
                    $api->comm('/ppp/secret/disable', ['numbers' => $secret['.id']]);
                    mikrotik_isolir_remove_active_sessions($api, (string) $user['username']);
                }
            }
        }

        $isolirStmt = $pdo->prepare("
            SELECT b.id, b.isolir_profile_before, u.username
            FROM billing b
            JOIN users u ON b.user_id = u.id AND u.tenant_id = b.tenant_id
            WHERE b.tenant_id = ?
              AND b.payment_status != 'sudah_bayar'
              AND b.isolir_enabled = 1
              AND b.isolir_at IS NOT NULL
              AND b.isolir_status IN ('menunggu', 'gagal')
              AND b.isolir_at <= NOW()
            ORDER BY b.isolir_at ASC, b.id ASC
        ");
        $isolirStmt->execute([$tenantId]);
        $isolirRows = $isolirStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($api instanceof MikroTikClient) {
            $settings = mikrotik_isolir_settings($tenantId);
            foreach ($isolirRows as $billing) {
                try {
                    $result = mikrotik_isolir_apply_to_user($api, (string) $billing['username'], $settings);
                    $pdo->prepare("
                        UPDATE billing
                        SET isolir_status = 'terisolir',
                            isolir_profile_before = ?,
                            isolir_action_at = NOW(),
                            isolir_error = NULL,
                            last_update = CURRENT_TIMESTAMP
                        WHERE tenant_id = ? AND id = ?
                    ")->execute([
                        $billing['isolir_profile_before'] ?: ($result['previous_profile'] ?? 'default'),
                        $tenantId,
                        $billing['id']
                    ]);
                    echo "- Timer isolir sukses: {$billing['username']}\n";
                } catch (Throwable $e) {
                    $pdo->prepare("
                        UPDATE billing
                        SET isolir_status = 'gagal',
                            isolir_action_at = NOW(),
                            isolir_error = ?,
                            last_update = CURRENT_TIMESTAMP
                        WHERE tenant_id = ? AND id = ?
                    ")->execute([substr($e->getMessage(), 0, 255), $tenantId, $billing['id']]);
                    echo "- Timer isolir gagal {$billing['username']}: {$e->getMessage()}\n";
                }
            }
        }

        if ($api instanceof MikroTikClient) {
            $api->disconnect();
        }

        if (!mikrotik_backup_should_run($tenantId)) {
            continue;
        }

        try {
            if (!($api instanceof MikroTikClient)) {
                $api = mikrotik_isolir_connect($mkConfig);
            }

            $backupResult = mikrotik_backup_run($api, $tenantId, $mkConfig);
            if (!($backupResult['success'] ?? false)) {
                echo "- Backup gagal: " . ($backupResult['message'] ?? 'tidak diketahui') . "\n";
            } else {
                echo "- Backup sukses: " . ($backupResult['file_name'] ?? $backupResult['name'] ?? '-') . "\n";
            }
        } catch (Throwable $e) {
            echo "- Backup gagal: {$e->getMessage()}\n";
        } finally {
            if ($api instanceof MikroTikClient) {
                $api->disconnect();
            }
        }
    }

    echo "Proses Selesai.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'Error: ' . $e->getMessage() . "\n";
}
