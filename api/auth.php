<?php
require_once __DIR__ . '/../config/auth.php';

app_session_start();

if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 42000, '/');
}

header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    app_session_logout();
    echo json_encode(['success' => true, 'message' => 'Logout berhasil']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Username dan password wajib diisi']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.tenant_id,
            a.username,
            a.password,
            a.nama,
            a.role,
            a.is_active,
            t.name AS tenant_name,
            t.status AS tenant_status,
            t.expired_at AS tenant_expired_at
        FROM admin_users a
        JOIN tenants t ON t.id = a.tenant_id
        WHERE a.username = ?
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $account = $stmt->fetch();

    if (
        !$account ||
        !$account['is_active'] ||
        $account['tenant_status'] !== 'active' ||
        (
            !empty($account['tenant_expired_at']) &&
            strtotime((string) $account['tenant_expired_at']) !== false &&
            strtotime((string) $account['tenant_expired_at']) <= time()
        ) ||
        !password_verify($password, $account['password'])
    ) {
        http_response_code(401);
        $message = 'Login gagal';
        if ($account && !empty($account['tenant_expired_at']) && strtotime((string) $account['tenant_expired_at']) !== false && strtotime((string) $account['tenant_expired_at']) <= time()) {
            $message = 'Akun tenant sudah expired';
        }
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['admin'] = (int) $account['id'];
    $_SESSION['tenant_id'] = (int) $account['tenant_id'];
    $_SESSION['account'] = [
        'id' => (int) $account['id'],
        'tenant_id' => (int) $account['tenant_id'],
        'username' => $account['username'],
        'nama' => $account['nama'],
        'role' => $account['role'],
        'tenant_name' => $account['tenant_name'],
        'is_superadmin' => $account['role'] === 'superadmin',
    ];

    $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $account['id']]);

    echo json_encode([
        'success' => true,
        'redirect' => app_url(app_login_redirect_path($_SESSION['account'])),
        'tenant' => [
            'id' => (int) $account['tenant_id'],
            'name' => $account['tenant_name'],
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    $message = 'Terjadi kesalahan sistem.';
    if (env_value('APP_DEBUG', 'false') === 'true') {
        $message = $e->getMessage();
    }

    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
}
