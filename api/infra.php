<?php
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];

function nullify_infra_relations(PDO $pdo, int $tenantId, int $lokasiId): array
{
    $affectedColumns = [];
    foreach (['server_id', 'odc_id', 'odp_id'] as $column) {
        $stmt = $pdo->prepare("UPDATE relasi_jaringan SET {$column} = NULL WHERE tenant_id = ? AND {$column} = ?");
        $stmt->execute([$tenantId, $lokasiId]);
        if ($stmt->rowCount() > 0) {
            $affectedColumns[] = $column;
        }
    }

    $cleanup = $pdo->prepare('DELETE FROM relasi_jaringan WHERE tenant_id = ? AND server_id IS NULL AND odc_id IS NULL AND odp_id IS NULL');
    $cleanup->execute([$tenantId]);

    return [
        'updated_columns' => $affectedColumns,
        'deleted_empty_relations' => $cleanup->rowCount()
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_owner_api();
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Node infra tidak valid']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, nama, tipe FROM lokasi WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$id, $tenantId]);
            $lokasi = $stmt->fetch();

            if (!$lokasi) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Node infra tidak ditemukan']);
                exit;
            }

            $pdo->beginTransaction();
            $relationCleanup = nullify_infra_relations($pdo, $tenantId, $id);
            $stmtDelete = $pdo->prepare("DELETE FROM lokasi WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmtDelete->execute([$id, $tenantId]);
            $pdo->commit();

            app_cache_forget(app_cache_key('infra_locations', $tenantId));
            app_cache_forget(app_cache_key('map_topology', $tenantId));
            app_cache_forget(app_cache_key('dashboard_overview', $tenantId));

            $message = sprintf('%s %s berhasil dihapus', strtoupper($lokasi['tipe']), $lokasi['nama']);
            if (($relationCleanup['updated_columns'] ?? []) !== [] || ($relationCleanup['deleted_empty_relations'] ?? 0) > 0) {
                $message .= '. Relasi topology terkait sudah disesuaikan otomatis';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus node infra']);
        }
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $tipe = trim((string) ($_POST['tipe'] ?? ''));
    $koordinat = trim((string) ($_POST['koordinat'] ?? ''));
    $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
    $validTypes = ['server', 'odc', 'odp'];

    if ($nama === '' || !in_array($tipe, $validTypes, true) || $koordinat === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Input node infra tidak valid']);
        exit;
    }

    $coords = array_map('trim', explode(',', $koordinat));
    if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Koordinat node infra tidak valid']);
        exit;
    }

    try {
        if ($action === 'update') {
            if ($id <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Node infra tidak valid']);
                exit;
            }

            $existingStmt = $pdo->prepare("SELECT id FROM lokasi WHERE id = ? AND tenant_id = ? LIMIT 1");
            $existingStmt->execute([$id, $tenantId]);
            if (!$existingStmt->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Node infra tidak ditemukan']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE lokasi SET nama = ?, tipe = ?, koordinat = ?, keterangan = ? WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$nama, $tipe, $koordinat, $keterangan ?: null, $id, $tenantId]);
            $message = sprintf('%s %s berhasil diperbarui', strtoupper($tipe), $nama);
        } else {
            $stmt = $pdo->prepare("INSERT INTO lokasi (tenant_id, nama, tipe, koordinat, keterangan) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tenantId, $nama, $tipe, $koordinat, $keterangan ?: null]);
            $message = 'Infrastruktur berhasil ditambahkan';
        }

        app_cache_forget(app_cache_key('infra_locations', $tenantId));
        app_cache_forget(app_cache_key('map_topology', $tenantId));
        app_cache_forget(app_cache_key('dashboard_overview', $tenantId));

        echo json_encode(['success' => true, 'message' => $message]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan node infra']);
    }
    exit;
}

$cacheKey = app_cache_key('infra_locations', $tenantId);
$cached = app_cache_get($cacheKey, 120);
if ($cached !== null) {
    echo json_encode($cached);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nama, tipe, koordinat, keterangan FROM lokasi WHERE tenant_id = ? ORDER BY FIELD(tipe, 'server', 'odc', 'odp'), nama");
    $stmt->execute([$tenantId]);
    $lokasi = $stmt->fetchAll();

    $payload = ['success' => true, 'data' => $lokasi];
    app_cache_put($cacheKey, $payload);
    echo json_encode($payload);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
