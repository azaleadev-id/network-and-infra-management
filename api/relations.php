<?php
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];

function lokasi_exists_with_type(PDO $pdo, int $tenantId, int $id, string $type): bool {
    $stmt = $pdo->prepare('SELECT id FROM lokasi WHERE tenant_id = ? AND id = ? AND tipe = ? LIMIT 1');
    $stmt->execute([$tenantId, $id, $type]);
    return (bool) $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_owner_api();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $serverId = (int) ($_POST['server_id'] ?? 0);
    $odcId = (int) ($_POST['odc_id'] ?? 0);
    $odpId = (int) ($_POST['odp_id'] ?? 0);

    if ($userId <= 0 || $serverId <= 0 || $odcId <= 0 || $odpId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Semua field relasi wajib dipilih']);
        exit;
    }

    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND id = ? LIMIT 1");
    $stmtUser->execute([$tenantId, $userId]);
    if (!$stmtUser->fetchColumn()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'User tidak valid']);
        exit;
    }

    if (
        !lokasi_exists_with_type($pdo, $tenantId, $serverId, 'server') ||
        !lokasi_exists_with_type($pdo, $tenantId, $odcId, 'odc') ||
        !lokasi_exists_with_type($pdo, $tenantId, $odpId, 'odp')
    ) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Node relasi tidak valid']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtCheck = $pdo->prepare('SELECT id FROM relasi_jaringan WHERE tenant_id = ? AND user_id = ? LIMIT 1');
        $stmtCheck->execute([$tenantId, $userId]);
        $existingId = $stmtCheck->fetchColumn();

        if ($existingId) {
            $stmt = $pdo->prepare('UPDATE relasi_jaringan SET server_id = ?, odc_id = ?, odp_id = ? WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$serverId, $odcId, $odpId, $existingId, $tenantId]);
            $message = 'Relasi berhasil diperbarui';
        } else {
            $stmt = $pdo->prepare('INSERT INTO relasi_jaringan (tenant_id, user_id, server_id, odc_id, odp_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$tenantId, $userId, $serverId, $odcId, $odpId]);
            $message = 'Relasi berhasil ditambahkan';
        }

        $pdo->commit();
        app_cache_forget(app_cache_key('map_topology', $tenantId));
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan relasi']);
    }
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.user_id,
            u.username,
            u.nama AS user_name,
            r.server_id,
            s.nama AS server_name,
            r.odc_id,
            c.nama AS odc_name,
            r.odp_id,
            o.nama AS odp_name,
            CASE
                WHEN r.server_id IS NOT NULL AND r.odc_id IS NOT NULL AND r.odp_id IS NOT NULL THEN 1
                ELSE 0
            END AS is_complete
        FROM relasi_jaringan r
        JOIN users u ON u.id = r.user_id AND u.tenant_id = r.tenant_id
        LEFT JOIN lokasi s ON s.id = r.server_id AND s.tenant_id = r.tenant_id
        LEFT JOIN lokasi c ON c.id = r.odc_id AND c.tenant_id = r.tenant_id
        LEFT JOIN lokasi o ON o.id = r.odp_id AND o.tenant_id = r.tenant_id
        WHERE r.tenant_id = ?
        ORDER BY r.id DESC
    ");
    $stmt->execute([$tenantId]);
    $relations = $stmt->fetchAll();

    $usersStmt = $pdo->prepare("SELECT id, username, nama FROM users WHERE tenant_id = ? ORDER BY nama ASC");
    $usersStmt->execute([$tenantId]);
    $serversStmt = $pdo->prepare("SELECT id, nama FROM lokasi WHERE tenant_id = ? AND tipe = 'server' ORDER BY nama ASC");
    $serversStmt->execute([$tenantId]);
    $odcsStmt = $pdo->prepare("SELECT id, nama FROM lokasi WHERE tenant_id = ? AND tipe = 'odc' ORDER BY nama ASC");
    $odcsStmt->execute([$tenantId]);
    $odpsStmt = $pdo->prepare("SELECT id, nama FROM lokasi WHERE tenant_id = ? AND tipe = 'odp' ORDER BY nama ASC");
    $odpsStmt->execute([$tenantId]);

    echo json_encode([
        'success' => true,
        'data' => $relations,
        'options' => [
            'users' => $usersStmt->fetchAll(),
            'servers' => $serversStmt->fetchAll(),
            'odcs' => $odcsStmt->fetchAll(),
            'odps' => $odpsStmt->fetchAll(),
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
