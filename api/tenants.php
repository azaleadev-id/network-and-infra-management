<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_bridge.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_superadmin_api();
$currentSuperadminTenantId = (int) ($account['tenant_id'] ?? 0);

function tenant_normalize_expired_at(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function tenant_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'tenant';
}

function tenant_unique_slug(PDO $pdo, string $baseSlug, int $ignoreTenantId = 0): string
{
    $slug = tenant_slugify($baseSlug);
    $candidate = $slug;
    $suffix = 2;

    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$candidate, $ignoreTenantId]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }

        $candidate = $slug . '-' . $suffix;
        $suffix++;
    }
}

function tenant_payload(PDO $pdo, array $input, bool $isUpdate): array
{
    $tenantName = trim((string) ($input['tenant_name'] ?? ''));
    $slugInput = trim((string) ($input['slug'] ?? ''));
    $ownerName = trim((string) ($input['owner_name'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $status = trim((string) ($input['status'] ?? 'active'));
    $expiredAt = tenant_normalize_expired_at($input['expired_at'] ?? null);
    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $accountName = trim((string) ($input['account_name'] ?? ''));
    $role = trim((string) ($input['role'] ?? 'owner'));

    if ($tenantName === '' || $username === '' || $accountName === '') {
        return ['success' => false, 'message' => 'Nama tenant, username login, dan nama akun wajib diisi'];
    }

    if (!$isUpdate && $password === '') {
        return ['success' => false, 'message' => 'Password wajib diisi saat membuat akun customer'];
    }

    if (!in_array($status, ['active', 'inactive', 'pending'], true)) {
        return ['success' => false, 'message' => 'Status tenant tidak valid'];
    }

    if (!in_array($role, ['owner', 'admin', 'operator', 'viewer'], true)) {
        return ['success' => false, 'message' => 'Role akun tenant tidak valid'];
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
        return ['success' => false, 'message' => 'Nomor telepon tenant tidak valid'];
    }

    if (($input['expired_at'] ?? '') !== '' && $expiredAt === null) {
        return ['success' => false, 'message' => 'Format tanggal dan jam expired tenant tidak valid'];
    }

    return [
        'success' => true,
        'data' => [
            'tenant_name' => $tenantName,
            'slug_seed' => $slugInput !== '' ? $slugInput : $tenantName,
            'owner_name' => $ownerName !== '' ? $ownerName : $accountName,
            'phone' => $phone,
            'status' => $status,
            'expired_at' => $expiredAt,
            'username' => $username,
            'password' => $password,
            'account_name' => $accountName,
            'role' => $role,
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'save'));

    if ($action === 'delete') {
        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tenant tidak valid']);
            exit;
        }

        if ($tenantId === $currentSuperadminTenantId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tenant inti yang dipakai akun superadmin tidak boleh dihapus']);
            exit;
        }

        $tenantStmt = $pdo->prepare("SELECT id, name FROM tenants WHERE id = ? LIMIT 1");
        $tenantStmt->execute([$tenantId]);
        $tenant = $tenantStmt->fetch();
        if (!$tenant) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tenant tidak ditemukan']);
            exit;
        }

        $removedSessions = app_session_destroy_for_tenant($tenantId);
        app_cache_forget(app_mikrotik_bridge_snapshot_key($tenantId));
        app_cache_forget(app_cache_key('dashboard_overview', $tenantId));
        app_cache_forget(app_cache_key('map_topology', $tenantId));

        $pdo->prepare('DELETE FROM tenants WHERE id = ? LIMIT 1')->execute([$tenantId]);

        echo json_encode([
            'success' => true,
            'message' => 'Tenant ' . $tenant['name'] . ' beserta akun dashboard, data, dan ' . $removedSessions . ' sesi login aktif berhasil dihapus'
        ]);
        exit;
    }

    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    $accountId = (int) ($_POST['account_id'] ?? 0);
    $validated = tenant_payload($pdo, $_POST, $tenantId > 0);
    if (!$validated['success']) {
        http_response_code(422);
        echo json_encode($validated);
        exit;
    }

    $payload = $validated['data'];

    try {
        $pdo->beginTransaction();

        if ($tenantId > 0) {
            $tenantStmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? LIMIT 1");
            $tenantStmt->execute([$tenantId]);
            if (!$tenantStmt->fetchColumn()) {
                throw new RuntimeException('Tenant tidak ditemukan');
            }

            $accountStmt = $pdo->prepare("SELECT id FROM admin_users WHERE id = ? AND tenant_id = ? AND role != 'superadmin' LIMIT 1");
            $accountStmt->execute([$accountId, $tenantId]);
            if (!$accountStmt->fetchColumn()) {
                throw new RuntimeException('Akun tenant tidak ditemukan');
            }

            $usernameStmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ? LIMIT 1');
            $usernameStmt->execute([$payload['username'], $accountId]);
            if ($usernameStmt->fetchColumn()) {
                throw new RuntimeException('Username login dashboard sudah dipakai akun lain');
            }

            $slug = tenant_unique_slug($pdo, $payload['slug_seed'], $tenantId);

            $pdo->prepare("
                UPDATE tenants
                SET name = ?, slug = ?, owner_name = ?, phone = ?, status = ?, expired_at = ?
                WHERE id = ?
            ")->execute([
                $payload['tenant_name'],
                $slug,
                $payload['owner_name'],
                $payload['phone'] !== '' ? $payload['phone'] : null,
                $payload['status'],
                $payload['expired_at'],
                $tenantId
            ]);

            if ($payload['password'] !== '') {
                $pdo->prepare("
                    UPDATE admin_users
                    SET username = ?, password = ?, nama = ?, role = ?, is_active = ?
                    WHERE id = ? AND tenant_id = ?
                ")->execute([
                    $payload['username'],
                    password_hash($payload['password'], PASSWORD_DEFAULT),
                    $payload['account_name'],
                    $payload['role'],
                    $payload['status'] === 'active' ? 1 : 0,
                    $accountId,
                    $tenantId
                ]);
            } else {
                $pdo->prepare("
                    UPDATE admin_users
                    SET username = ?, nama = ?, role = ?, is_active = ?
                    WHERE id = ? AND tenant_id = ?
                ")->execute([
                    $payload['username'],
                    $payload['account_name'],
                    $payload['role'],
                    $payload['status'] === 'active' ? 1 : 0,
                    $accountId,
                    $tenantId
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Tenant dan akun dashboard berhasil diperbarui']);
            exit;
        }

        $usernameStmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
        $usernameStmt->execute([$payload['username']]);
        if ($usernameStmt->fetchColumn()) {
            throw new RuntimeException('Username login dashboard sudah dipakai akun lain');
        }

        $slug = tenant_unique_slug($pdo, $payload['slug_seed']);

        $pdo->prepare("
            INSERT INTO tenants (name, slug, owner_name, phone, status, expired_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $payload['tenant_name'],
            $slug,
            $payload['owner_name'],
            $payload['phone'] !== '' ? $payload['phone'] : null,
            $payload['status'],
            $payload['expired_at']
        ]);

        $tenantId = (int) $pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO admin_users (tenant_id, username, password, nama, role, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $tenantId,
            $payload['username'],
            password_hash($payload['password'], PASSWORD_DEFAULT),
            $payload['account_name'],
            $payload['role'],
            $payload['status'] === 'active' ? 1 : 0
        ]);

        app_setting_set('app_name', $payload['tenant_name'], $tenantId);
        app_mikrotik_bridge_api_key_get($tenantId, true);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Tenant baru dan akun dashboard berhasil ditambahkan']);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code($e instanceof RuntimeException ? 422 : 500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage() ?: 'Gagal menyimpan tenant'
        ]);
        exit;
    }
}

try {
    $stmt = $pdo->query("
        SELECT
            t.id,
            t.name,
            t.slug,
            t.owner_name,
            t.phone,
            t.status,
            t.expired_at,
            t.created_at,
            (
                SELECT COUNT(*)
                FROM admin_users au_total
                WHERE au_total.tenant_id = t.id AND au_total.role != 'superadmin'
            ) AS admin_count,
            (
                SELECT COUNT(*)
                FROM users u
                WHERE u.tenant_id = t.id
            ) AS customer_count,
            (
                SELECT COUNT(*)
                FROM lokasi l
                WHERE l.tenant_id = t.id
            ) AS infra_count,
            au.id AS account_id,
            au.username,
            au.nama AS account_name,
            au.role,
            au.is_active,
            au.last_login_at
        FROM tenants t
        LEFT JOIN admin_users au ON au.id = (
            SELECT au_pick.id
            FROM admin_users au_pick
            WHERE au_pick.tenant_id = t.id AND au_pick.role != 'superadmin'
            ORDER BY CASE au_pick.role WHEN 'owner' THEN 0 ELSE 1 END, au_pick.id ASC
            LIMIT 1
        )
        WHERE EXISTS (
            SELECT 1
            FROM admin_users au_filter
            WHERE au_filter.tenant_id = t.id AND au_filter.role != 'superadmin'
        )
        ORDER BY t.id DESC
    ");
    $rows = $stmt->fetchAll();

    $summary = [
        'total_tenants' => count($rows),
        'active_tenants' => count(array_filter($rows, static fn (array $row) => ($row['status'] ?? '') === 'active')),
        'total_accounts' => array_sum(array_map(static fn (array $row) => (int) ($row['admin_count'] ?? 0), $rows))
    ];

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'data' => $rows
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memuat data tenant']);
}
