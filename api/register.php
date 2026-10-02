<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function tenant_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'tenant';
}

function tenant_unique_slug(PDO $pdo, string $baseSlug): string
{
    $slug = tenant_slugify($baseSlug);
    $candidate = $slug;
    $suffix = 2;

    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$candidate]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }

        $candidate = $slug . '-' . $suffix;
        $suffix++;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$tenantName = trim((string) ($_POST['tenant_name'] ?? ''));
$ownerName = trim((string) ($_POST['owner_name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$accountName = trim((string) ($_POST['account_name'] ?? ''));

if ($tenantName === '' || $username === '' || $accountName === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Username sudah terdaftar']);
        exit;
    }

    $pdo->beginTransaction();

    $slug = tenant_unique_slug($pdo, $tenantName);

    $stmt = $pdo->prepare("
        INSERT INTO tenants (name, slug, owner_name, phone, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$tenantName, $slug, $ownerName, $phone]);
    $tenantId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO admin_users (tenant_id, username, password, nama, role, is_active, created_at)
        VALUES (?, ?, ?, ?, 'owner', 0, NOW())
    ");
    $stmt->execute([
        $tenantId,
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $accountName
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pendaftaran berhasil! Akun anda sedang menunggu konfirmasi dari Superadmin. Silahkan hubungi admin untuk mempercepat proses.'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
}
