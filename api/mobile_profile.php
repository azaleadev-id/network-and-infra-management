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

if (!mobile_has_column('admin_users', 'avatar_url')) {
    mobile_json_response([
        'success' => false,
        'message' => 'Kolom avatar_url belum tersedia di database.',
    ], 500);
}

function mobile_profile_response(array $account, string $message = 'Profil berhasil dimuat'): void
{
    mobile_json_response([
        'success' => true,
        'message' => $message,
        'account' => mobile_account_payload($account),
    ]);
}

function mobile_profile_store_photo(array $account): void
{
    if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
        mobile_json_response([
            'success' => false,
            'message' => 'File foto profil wajib dipilih.',
        ], 422);
    }

    $file = $_FILES['photo'];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $message = match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran foto terlalu besar.',
            UPLOAD_ERR_NO_FILE => 'File foto profil belum dipilih.',
            default => 'Upload foto profil gagal diproses.',
        };
        mobile_json_response([
            'success' => false,
            'message' => $message,
        ], 422);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Upload foto profil tidak valid.',
        ], 422);
    }

    $maxBytes = 2 * 1024 * 1024;
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        mobile_json_response([
            'success' => false,
            'message' => 'Ukuran foto maksimal 2 MB.',
        ], 422);
    }

    $mimeType = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mimeType = finfo_file($finfo, $tmpName) ?: null;
            finfo_close($finfo);
        }
    }

    $allowedExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $extension = $mimeType && isset($allowedExtensions[$mimeType])
        ? $allowedExtensions[$mimeType]
        : null;
    if ($extension === null) {
        mobile_json_response([
            'success' => false,
            'message' => 'Format foto harus JPG, PNG, atau WEBP.',
        ], 422);
    }

    $directory = app_storage_path('profile-photos');
    app_ensure_directory($directory);

    $fileName = sprintf(
        'user-%d-%d-%s.%s',
        (int) ($account['id'] ?? 0),
        time(),
        bin2hex(random_bytes(5)),
        $extension
    );
    $relativePath = 'storage/profile-photos/' . $fileName;
    $targetPath = app_storage_path('profile-photos/' . $fileName);

    if (!move_uploaded_file($tmpName, $targetPath)) {
        mobile_json_response([
            'success' => false,
            'message' => 'File foto gagal disimpan ke server.',
        ], 500);
    }

    $oldAvatar = $account['avatar_url'] ?? null;

    try {
        $stmt = $GLOBALS['pdo']->prepare('UPDATE admin_users SET avatar_url = ? WHERE id = ?');
        $stmt->execute([$relativePath, (int) $account['id']]);
    } catch (Throwable $e) {
        @unlink($targetPath);
        $message = env_value('APP_DEBUG', 'false') === 'true'
            ? $e->getMessage()
            : 'Tidak bisa menyimpan foto profil ke database.';

        mobile_json_response([
            'success' => false,
            'message' => $message,
        ], 500);
    }

    mobile_delete_profile_photo_file($oldAvatar);
    $freshAccount = mobile_fetch_account_by_id((int) $account['id']) ?? $account;
    mobile_profile_response($freshAccount, 'Foto profil berhasil diperbarui.');
}

function mobile_profile_delete_photo(array $account): void
{
    $currentAvatar = trim((string) ($account['avatar_url'] ?? ''));
    if ($currentAvatar === '') {
        mobile_json_response([
            'success' => false,
            'message' => 'Foto profil belum tersedia.',
        ], 422);
    }

    $stmt = $GLOBALS['pdo']->prepare('UPDATE admin_users SET avatar_url = NULL WHERE id = ?');
    $stmt->execute([(int) $account['id']]);
    mobile_delete_profile_photo_file($currentAvatar);

    $freshAccount = mobile_fetch_account_by_id((int) $account['id']) ?? $account;
    $freshAccount['avatar_url'] = null;
    mobile_profile_response($freshAccount, 'Foto profil berhasil dihapus.');
}

if ($method === 'GET') {
    mobile_profile_response($account);
}

$action = strtolower(trim((string) mobile_request_value('action', 'upload')));
if ($action === 'delete') {
    mobile_profile_delete_photo($account);
}

mobile_profile_store_photo($account);
