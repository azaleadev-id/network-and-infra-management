<?php
require_once __DIR__ . '/../../config/db.php';

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

echo "Starting Network Scan...\n";

try {
    $tenants = $pdo->query("SELECT id, name FROM tenants WHERE status = 'active' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tenants as $tenant) {
        $tenantId = (int) $tenant['id'];
        echo "Scanning tenant {$tenant['name']} (#{$tenantId})...\n";

        $stmt = $pdo->prepare("
            SELECT o.id AS odp_id, o.nama AS odp_name, u.status AS user_status
            FROM relasi_jaringan r
            JOIN lokasi o ON r.odp_id = o.id AND o.tenant_id = r.tenant_id
            JOIN users u ON r.user_id = u.id AND u.tenant_id = r.tenant_id
            WHERE r.tenant_id = ? AND o.tipe = 'odp'
        ");
        $stmt->execute([$tenantId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $odpStats = [];
        foreach ($data as $row) {
            $odpId = (int) $row['odp_id'];
            if (!isset($odpStats[$odpId])) {
                $odpStats[$odpId] = ['name' => $row['odp_name'], 'total' => 0, 'offline' => 0];
            }
            $odpStats[$odpId]['total']++;
            if ($row['user_status'] === 'offline') {
                $odpStats[$odpId]['offline']++;
            }
        }

        $odpDown = [];
        foreach ($odpStats as $odpId => $stat) {
            if ($stat['total'] === 0) {
                continue;
            }

            $offlineRatio = $stat['offline'] / $stat['total'];
            if ($offlineRatio == 1.0) {
                $odpDown[] = $odpId;
                createAlert($pdo, $tenantId, 'odp_down', $odpId, "ODP {$stat['name']} DOWN (Semua user offline)");
            } elseif ($offlineRatio >= 0.5) {
                createAlert($pdo, $tenantId, 'warning', $odpId, "WARNING: {$stat['offline']} dari {$stat['total']} user offline pada ODP {$stat['name']}");
            } else {
                resolveAlert($pdo, $tenantId, $odpId);
            }
        }

        $stmt = $pdo->prepare("
            SELECT c.id AS odc_id, c.nama AS odc_name, r.odp_id
            FROM relasi_jaringan r
            JOIN lokasi c ON r.odc_id = c.id AND c.tenant_id = r.tenant_id
            WHERE r.tenant_id = ?
            GROUP BY c.id, c.nama, r.odp_id
        ");
        $stmt->execute([$tenantId]);
        $odcData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $odcStats = [];
        foreach ($odcData as $row) {
            $odcId = (int) $row['odc_id'];
            if (!isset($odcStats[$odcId])) {
                $odcStats[$odcId] = ['name' => $row['odc_name'], 'odp_total' => 0, 'odp_down' => 0];
            }
            $odcStats[$odcId]['odp_total']++;
            if (in_array((int) $row['odp_id'], $odpDown, true)) {
                $odcStats[$odcId]['odp_down']++;
            }
        }

        foreach ($odcStats as $odcId => $stat) {
            if ($stat['odp_total'] > 0 && $stat['odp_down'] === $stat['odp_total']) {
                createAlert($pdo, $tenantId, 'odc_down', $odcId, "ODC {$stat['name']} DOWN (Semua ODP dibawahnya mati)");
            } else {
                resolveAlert($pdo, $tenantId, $odcId);
            }
        }
    }

    echo "Scan Complete.\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}

function createAlert(PDO $pdo, int $tenantId, string $type, int $targetId, string $message): void
{
    $stmt = $pdo->prepare("SELECT id FROM alerts WHERE tenant_id = ? AND target_id = ? AND status = 'active' AND tipe = ?");
    $stmt->execute([$tenantId, $targetId, $type]);
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare("INSERT INTO alerts (tenant_id, tipe, target_id, pesan, status) VALUES (?, ?, ?, ?, 'active')");
        $insert->execute([$tenantId, $type, $targetId, $message]);
        echo "Alert Created: {$message}\n";
    }
}

function resolveAlert(PDO $pdo, int $tenantId, int $targetId): void
{
    $stmt = $pdo->prepare("UPDATE alerts SET status = 'resolved' WHERE tenant_id = ? AND target_id = ? AND status = 'active'");
    $stmt->execute([$tenantId, $targetId]);
}
