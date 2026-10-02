<?php
require_once __DIR__ . '/mobile_bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
    mobile_json_response([
        'success' => false,
        'message' => 'Method not allowed',
    ], 405);
}

$account = mobile_require_account();

function mobile_employee_list(array $account): void
{
    $tenantIds = mobile_resolve_tenant_ids($account);

    if (!$tenantIds) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tenant aktif belum dipilih',
        ], 422);
    }

    $selectColumns = [
        'id',
        'tenant_id',
        'username',
        'nama',
        'role',
        'is_active',
    ];
    if (mobile_has_column('admin_users', 'email')) {
        $selectColumns[] = 'email';
    }
    if (mobile_has_column('admin_users', 'can_scan_payment')) {
        $selectColumns[] = 'can_scan_payment';
    }

    $tenantPlaceholders = implode(', ', array_fill(0, count($tenantIds), '?'));
    $stmt = $GLOBALS['pdo']->prepare(sprintf(
        'SELECT %s FROM admin_users WHERE tenant_id IN (%s) ORDER BY tenant_id ASC, role ASC, nama ASC, id DESC',
        implode(', ', $selectColumns),
        $tenantPlaceholders
    ));
    $stmt->execute($tenantIds);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_json_response([
        'success' => true,
        'data' => array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['tenant_id'] = (int) $item['tenant_id'];
            $item['is_active'] = (int) ($item['is_active'] ?? 0) === 1;
            $item['email'] = isset($item['email']) && $item['email'] !== '' ? (string) $item['email'] : null;
            $item['can_scan_payment'] = (int) ($item['can_scan_payment'] ?? 0) === 1;
            return $item;
        }, $items),
    ]);
}

function mobile_employee_create(array $account): void
{
    $role = strtolower(trim((string) ($account['role'] ?? '')));
    if (!in_array($role, ['owner', 'superadmin', 'superuser', 'admin'], true)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Akun ini tidak diizinkan menambah karyawan.',
        ], 403);
    }

    $tenantId = mobile_resolve_tenant_id($account);
    $nama = trim((string) mobile_request_value('nama', ''));
    $username = trim((string) mobile_request_value('username', ''));
    $email = strtolower(trim((string) mobile_request_value('email', '')));
    $password = (string) mobile_request_value('password', '');
    $employeeRole = strtolower(trim((string) mobile_request_value('role', 'karyawan')));
    $isActive = (int) mobile_request_value('is_active', 1) === 1;
    $canScanPayment = (int) mobile_request_value('can_scan_payment', 0) === 1;

    if ($nama === '' || $username === '' || $email === '' || $password === '') {
        mobile_json_response([
            'success' => false,
            'message' => 'Nama, username, email, dan password wajib diisi.',
        ], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Format email tidak valid.',
        ], 422);
    }

    if (strlen($password) < 6) {
        mobile_json_response([
            'success' => false,
            'message' => 'Password minimal 6 karakter.',
        ], 422);
    }

    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Username hanya boleh berisi huruf, angka, titik, underscore, atau strip.',
        ], 422);
    }

    $allowedRoles = ['admin', 'cashier', 'collector', 'karyawan', 'kasir', 'penagih', 'petugas', 'staff'];
    if (!in_array($employeeRole, $allowedRoles, true)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Role karyawan tidak valid.',
        ], 422);
    }

    try {
        $stmt = $GLOBALS['pdo']->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        if ($stmt->fetchColumn()) {
            mobile_json_response([
                'success' => false,
                'message' => 'Username sudah dipakai akun lain.',
            ], 422);
        }

        if (mobile_has_column('admin_users', 'email')) {
            $stmt = $GLOBALS['pdo']->prepare('SELECT id FROM admin_users WHERE LOWER(email) = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                mobile_json_response([
                    'success' => false,
                    'message' => 'Email sudah dipakai akun lain.',
                ], 422);
            }
        }

        $columns = ['tenant_id', 'username', 'password', 'nama', 'role', 'is_active', 'created_at'];
        $placeholders = ['?', '?', '?', '?', '?', '?', 'NOW()'];
        $values = [
            $tenantId,
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $nama,
            $employeeRole,
            $isActive ? 1 : 0,
        ];

        if (mobile_has_column('admin_users', 'email')) {
            $columns[] = 'email';
            $placeholders[] = '?';
            $values[] = $email;
        }
        if (mobile_has_column('admin_users', 'can_scan_payment')) {
            $columns[] = 'can_scan_payment';
            $placeholders[] = '?';
            $values[] = $canScanPayment ? 1 : 0;
        }
        if (mobile_has_column('admin_users', 'parent_admin_user_id')) {
            $columns[] = 'parent_admin_user_id';
            $placeholders[] = '?';
            $values[] = (int) ($account['id'] ?? 0);
        }
        if (mobile_has_column('admin_users', 'created_by')) {
            $columns[] = 'created_by';
            $placeholders[] = '?';
            $values[] = (int) ($account['id'] ?? 0);
        }
        if (mobile_has_column('admin_users', 'login_provider')) {
            $columns[] = 'login_provider';
            $placeholders[] = '?';
            $values[] = 'manual';
        }

        $sql = sprintf(
            'INSERT INTO admin_users (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $stmt = $GLOBALS['pdo']->prepare($sql);
        $stmt->execute($values);

        $employeeId = (int) $GLOBALS['pdo']->lastInsertId();

        mobile_json_response([
            'success' => true,
            'message' => 'Karyawan baru berhasil ditambahkan.',
            'employee' => [
                'id' => $employeeId,
                'tenant_id' => $tenantId,
                'username' => $username,
                'nama' => $nama,
                'email' => $email,
                'role' => $employeeRole,
                'is_active' => $isActive,
                'can_scan_payment' => $canScanPayment,
            ],
        ]);
    } catch (Throwable $e) {
        $message = env_value('APP_DEBUG', 'false') === 'true'
            ? $e->getMessage()
            : 'Terjadi kesalahan sistem';

        mobile_json_response([
            'success' => false,
            'message' => $message,
        ], 500);
    }
}

function mobile_employee_delete(array $account): void
{
    $role = strtolower(trim((string) ($account['role'] ?? '')));
    if (!in_array($role, ['owner', 'superadmin', 'superuser', 'admin'], true)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Akun ini tidak diizinkan menghapus karyawan.',
        ], 403);
    }

    $employeeId = (int) mobile_request_value('employee_id', mobile_request_value('id', 0));
    if ($employeeId <= 0) {
        mobile_json_response([
            'success' => false,
            'message' => 'ID karyawan tidak valid.',
        ], 422);
    }

    if ($employeeId === (int) ($account['id'] ?? 0)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Akun yang sedang dipakai login tidak bisa dihapus.',
        ], 422);
    }

    $allowedTenantIds = mobile_resolve_tenant_ids($account);
    if (!$allowedTenantIds) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tenant aktif belum dipilih.',
        ], 422);
    }

    $placeholders = implode(', ', array_fill(0, count($allowedTenantIds), '?'));
    $params = array_merge([$employeeId], $allowedTenantIds);
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT id, tenant_id, username, nama, role
        FROM admin_users
        WHERE id = ?
          AND tenant_id IN ($placeholders)
        LIMIT 1
    ");
    $stmt->execute($params);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        mobile_json_response([
            'success' => false,
            'message' => 'Karyawan tidak ditemukan atau tidak berada di tenant yang diizinkan.',
        ], 404);
    }

    $targetRole = strtolower(trim((string) ($employee['role'] ?? '')));
    if (in_array($targetRole, ['owner', 'superadmin', 'superuser'], true)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Akun owner atau superuser tidak bisa dihapus dari endpoint ini.',
        ], 403);
    }

    try {
        $GLOBALS['pdo']->beginTransaction();

        if (mobile_has_column('audit_log', 'tenant_id') && mobile_has_column('audit_log', 'admin_user_id')) {
            $auditStmt = $GLOBALS['pdo']->prepare("
                INSERT INTO audit_log (tenant_id, admin_user_id, action, detail)
                VALUES (?, ?, ?, ?)
            ");
            $auditStmt->execute([
                (int) $employee['tenant_id'],
                (int) ($account['id'] ?? 0),
                'delete_employee',
                sprintf(
                    'Menghapus karyawan %s (@%s) dengan role %s',
                    (string) ($employee['nama'] ?? '-'),
                    (string) ($employee['username'] ?? '-'),
                    (string) ($employee['role'] ?? '-')
                ),
            ]);
        }

        $deleteStmt = $GLOBALS['pdo']->prepare('DELETE FROM admin_users WHERE id = ? LIMIT 1');
        $deleteStmt->execute([$employeeId]);

        if ($deleteStmt->rowCount() < 1) {
            $GLOBALS['pdo']->rollBack();
            mobile_json_response([
                'success' => false,
                'message' => 'Karyawan gagal dihapus.',
            ], 500);
        }

        $GLOBALS['pdo']->commit();
        mobile_forget_tenant_cache((int) $employee['tenant_id']);

        mobile_json_response([
            'success' => true,
            'message' => 'Karyawan berhasil dihapus.',
            'employee' => [
                'id' => (int) $employee['id'],
                'tenant_id' => (int) $employee['tenant_id'],
                'username' => (string) ($employee['username'] ?? ''),
                'nama' => (string) ($employee['nama'] ?? ''),
                'role' => (string) ($employee['role'] ?? ''),
            ],
        ]);
    } catch (Throwable $e) {
        if ($GLOBALS['pdo']->inTransaction()) {
            $GLOBALS['pdo']->rollBack();
        }

        $message = env_value('APP_DEBUG', 'false') === 'true'
            ? $e->getMessage()
            : 'Terjadi kesalahan sistem';

        mobile_json_response([
            'success' => false,
            'message' => $message,
        ], 500);
    }
}

if ($method === 'POST') {
    $action = strtolower(trim((string) mobile_request_value('action', 'create')));
    if (in_array($action, ['delete', 'delete_employee', 'remove'], true)) {
        mobile_employee_delete($account);
    }

    mobile_employee_create($account);
}

mobile_employee_list($account);
