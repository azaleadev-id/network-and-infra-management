<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mikrotik_isolir.php';

header('Content-Type: application/json; charset=utf-8');

$account = app_require_auth_api();
$tenantId = (int) $account['tenant_id'];

function clear_billing_cache(int $tenantId): void
{
    app_cache_forget(app_cache_key('dashboard_overview', $tenantId));
    $trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
    app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));
}

function billing_has_column(string $column): bool
{
    static $cache = [];

    $column = trim($column);
    if ($column === '') {
        return false;
    }

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    try {
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'billing'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$column]);
        $cache[$column] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$column] = false;
    }

    return $cache[$column];
}

function billing_normalize_datetime_input(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    try {
        return (new DateTime($value))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function billing_format_datetime_for_input(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTime($value))->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

function billing_format_time_for_input(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTime($value))->format('H:i');
    } catch (Throwable $e) {
        return '';
    }
}

function billing_build_isolir_datetime(string $jatuhTempo, ?string $isolirTime): ?string
{
    $jatuhTempo = trim($jatuhTempo);
    $isolirTime = trim((string) $isolirTime);

    if ($jatuhTempo === '' || $isolirTime === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d H:i', $jatuhTempo . ' ' . $isolirTime);
    if (!($date instanceof DateTime)) {
        return null;
    }

    return $date->format('Y-m-d H:i:s');
}

function billing_effective_payment_status(array $billing): string
{
    $status = trim((string) ($billing['payment_status'] ?? 'belum_bayar')) ?: 'belum_bayar';
    $billAmount = (float) ($billing['jumlah'] ?? 0);
    $amountPaid = (float) ($billing['amount_paid'] ?? 0);
    $remaining = array_key_exists('remaining_amount', $billing)
        ? (float) ($billing['remaining_amount'] ?? max($billAmount - $amountPaid, 0))
        : max($billAmount - $amountPaid, 0);

    if ($billAmount > 0 && ($remaining <= 0.00001 || $amountPaid >= $billAmount - 0.00001)) {
        return 'sudah_bayar';
    }

    return $status;
}

function billing_normalize_row(array $billing): array
{
    $billAmount = (float) ($billing['jumlah'] ?? 0);
    $amountPaid = (float) ($billing['amount_paid'] ?? 0);
    $remaining = array_key_exists('remaining_amount', $billing)
        ? (float) ($billing['remaining_amount'] ?? max($billAmount - $amountPaid, 0))
        : max($billAmount - $amountPaid, 0);

    $billing['amount_paid'] = $amountPaid;
    $billing['remaining_amount'] = $remaining <= 0.00001 ? 0 : $remaining;
    $billing['payment_status'] = billing_effective_payment_status($billing);

    return $billing;
}

function billing_fetch_row(PDO $pdo, int $tenantId, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            b.*,
            u.username,
            u.nama
        FROM billing b
        JOIN users u ON u.id = b.user_id AND u.tenant_id = b.tenant_id
        WHERE b.tenant_id = ? AND b.id = ?
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? billing_normalize_row($row) : null;
}

function billing_update_isolir_state(PDO $pdo, int $tenantId, int $billingId, array $payload): void
{
    $allowed = [
        'isolir_status',
        'isolir_profile_before',
        'isolir_action_at',
        'isolir_error',
        'payment_status',
        'paid_at',
        'status',
        'isolir_enabled',
        'isolir_at'
    ];

    $fields = [];
    $values = [];
    foreach ($payload as $column => $value) {
        if (!in_array($column, $allowed, true)) {
            continue;
        }

        $fields[] = "{$column} = ?";
        $values[] = $value;
    }

    if ($fields === []) {
        return;
    }

    $values[] = $tenantId;
    $values[] = $billingId;
    $sql = "UPDATE billing SET " . implode(', ', $fields) . ", last_update = CURRENT_TIMESTAMP WHERE tenant_id = ? AND id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function billing_apply_isolir_to_row(PDO $pdo, int $tenantId, array $billing, MikroTikClient $api, array $settings): array
{
    $result = mikrotik_isolir_apply_to_user($api, (string) $billing['username'], $settings);

    billing_update_isolir_state($pdo, $tenantId, (int) $billing['id'], [
        'isolir_status' => 'terisolir',
        'isolir_profile_before' => $billing['isolir_profile_before'] ?: $result['previous_profile'],
        'isolir_action_at' => date('Y-m-d H:i:s'),
        'isolir_error' => null
    ]);

    return $result;
}

function billing_restore_isolir_for_row_with_api(PDO $pdo, int $tenantId, array $billing, MikroTikClient $api): ?string
{
    if (($billing['isolir_status'] ?? 'nonaktif') !== 'terisolir') {
        billing_update_isolir_state($pdo, $tenantId, (int) $billing['id'], [
            'isolir_status' => 'nonaktif',
            'isolir_error' => null
        ]);
        return null;
    }

    $result = mikrotik_isolir_restore_user_with_api_retry(
        $api,
        (string) $billing['username'],
        $billing['isolir_profile_before'] ?? null,
        app_mikrotik_config($tenantId)
    );

    billing_update_isolir_state($pdo, $tenantId, (int) $billing['id'], [
        'isolir_status' => 'dipulihkan',
        'isolir_action_at' => date('Y-m-d H:i:s'),
        'isolir_error' => null
    ]);

    return $result['restored_profile'] ?? null;
}

function billing_restore_isolir_for_row(PDO $pdo, int $tenantId, array $billing): ?string
{
    if (($billing['isolir_status'] ?? 'nonaktif') !== 'terisolir') {
        billing_update_isolir_state($pdo, $tenantId, (int) $billing['id'], [
            'isolir_status' => 'nonaktif',
            'isolir_error' => null
        ]);
        return null;
    }

    $result = mikrotik_isolir_restore_user_with_retry(
        (string) $billing['username'],
        $billing['isolir_profile_before'] ?? null,
        app_mikrotik_config($tenantId)
    );

    billing_update_isolir_state($pdo, $tenantId, (int) $billing['id'], [
        'isolir_status' => 'dipulihkan',
        'isolir_action_at' => date('Y-m-d H:i:s'),
        'isolir_error' => null
    ]);

    return $result['restored_profile'] ?? null;
}

function billing_mark_paid(PDO $pdo, int $tenantId, int $billingId): array
{
    $billing = billing_fetch_row($pdo, $tenantId, $billingId);
    if ($billing === null) {
        throw new RuntimeException('Tagihan tidak ditemukan');
    }

    $alreadyPaid = ($billing['payment_status'] ?? '') === 'sudah_bayar';
    $paidAt = $alreadyPaid && !empty($billing['paid_at'])
        ? (string) $billing['paid_at']
        : date('Y-m-d H:i:s');
    $billAmount = (float) ($billing['jumlah'] ?? 0);
    $currentPaymentCount = (int) ($billing['payment_count'] ?? 0);
    $nextPaymentCount = $alreadyPaid
        ? max(1, $currentPaymentCount)
        : max(1, $currentPaymentCount + 1);

    $stmt = $pdo->prepare("
        UPDATE billing
        SET
            payment_status = 'sudah_bayar',
            paid_at = ?,
            amount_paid = ?,
            remaining_amount = 0,
            last_payment_amount = ?,
            last_payment_at = ?,
            payment_count = ?,
            last_update = CURRENT_TIMESTAMP
        WHERE tenant_id = ? AND id = ?
    ");
    $stmt->execute([
        $paidAt,
        $billAmount,
        $billAmount,
        $paidAt,
        $nextPaymentCount,
        $tenantId,
        $billingId,
    ]);

    $message = $alreadyPaid
        ? 'Tagihan ini memang sudah lunas.'
        : 'Tagihan berhasil ditandai sudah bayar.';

    if (($billing['isolir_status'] ?? '') !== 'terisolir') {
        clear_billing_cache($tenantId);
        return [
            'message' => $message,
            'restored_profile' => null,
        ];
    }

    try {
        $restoredProfile = billing_restore_isolir_for_row($pdo, $tenantId, $billing);
        clear_billing_cache($tenantId);

        return [
            'message' => $restoredProfile !== null
                ? $message . " Koneksi dikembalikan ke profile {$restoredProfile}."
                : $message,
            'restored_profile' => $restoredProfile,
        ];
    } catch (Throwable $e) {
        billing_update_isolir_state($pdo, $tenantId, $billingId, [
            'isolir_status' => 'gagal',
            'isolir_action_at' => date('Y-m-d H:i:s'),
            'isolir_error' => substr($e->getMessage(), 0, 255),
        ]);
        clear_billing_cache($tenantId);

        return [
            'message' => $message . ' Namun buka isolir MikroTik gagal: ' . $e->getMessage(),
            'restored_profile' => null,
        ];
    }
}

function billing_due_isolir_rows(PDO $pdo, int $tenantId, ?int $billingId = null): array
{
    $params = [$tenantId];
    $sql = "
        SELECT
            b.*,
            u.username,
            u.nama
        FROM billing b
        JOIN users u ON u.id = b.user_id AND u.tenant_id = b.tenant_id
        WHERE b.tenant_id = ?
          AND b.payment_status != 'sudah_bayar'
          AND b.isolir_enabled = 1
          AND b.isolir_at IS NOT NULL
    ";

    if ($billingId !== null) {
        $sql .= " AND b.id = ? ";
        $params[] = $billingId;
    } else {
        $sql .= " AND b.isolir_at <= NOW() AND b.isolir_status IN ('menunggu', 'gagal') ";
    }

    $sql .= " ORDER BY b.isolir_at ASC, b.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function billing_run_due_isolir(PDO $pdo, int $tenantId, ?int $billingId = null): array
{
    $rows = billing_due_isolir_rows($pdo, $tenantId, $billingId);
    if ($rows === []) {
        return [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'messages' => []
        ];
    }

    $settings = mikrotik_isolir_settings($tenantId);
    $api = mikrotik_isolir_connect(app_mikrotik_config($tenantId));

    $processed = 0;
    $success = 0;
    $failed = 0;
    $messages = [];

    try {
        foreach ($rows as $billing) {
            $processed++;
            try {
                $result = billing_apply_isolir_to_row($pdo, $tenantId, $billing, $api, $settings);
                $success++;
                $messages[] = $result['already_isolated']
                    ? "{$billing['username']} sudah berada di profile isolir."
                    : "{$billing['username']} berhasil diisolir.";
            } catch (Throwable $e) {
                $failed++;
                billing_update_isolir_state($pdo, $tenantId, (int) $billing['id'], [
                    'isolir_status' => 'gagal',
                    'isolir_action_at' => date('Y-m-d H:i:s'),
                    'isolir_error' => substr($e->getMessage(), 0, 255)
                ]);
                $messages[] = "{$billing['username']} gagal diisolir: {$e->getMessage()}";
            }
        }
    } finally {
        $api->disconnect();
    }

    clear_billing_cache($tenantId);

    return [
        'processed' => $processed,
        'success' => $success,
        'failed' => $failed,
        'messages' => $messages
    ];
}

function billing_try_auto_run_due_isolir(PDO $pdo, int $tenantId): array
{
    try {
        return billing_run_due_isolir($pdo, $tenantId);
    } catch (Throwable $e) {
        return [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'messages' => [$e->getMessage()]
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_owner_api();
    $action = trim((string) ($_POST['action'] ?? 'save'));

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tagihan tidak valid']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM billing WHERE tenant_id = ? AND id = ? LIMIT 1");
        $stmt->execute([$tenantId, $id]);
        clear_billing_cache($tenantId);
        echo json_encode(['success' => true, 'message' => 'Tagihan berhasil dihapus']);
        exit;
    }

    if ($action === 'run_due_isolir') {
        try {
            $summary = billing_run_due_isolir($pdo, $tenantId);
            echo json_encode([
                'success' => true,
                'message' => $summary['processed'] > 0
                    ? "Timer isolir diproses. Berhasil: {$summary['success']}, gagal: {$summary['failed']}."
                    : 'Belum ada timer isolir yang jatuh tempo.',
                'summary' => $summary
            ]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'apply_due_date') {
        $targetDate = trim((string) ($_POST['target_date'] ?? ''));
        $targetTime = trim((string) ($_POST['target_time'] ?? ''));
        if ($targetDate === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tanggal jatuh tempo serentak wajib diisi']);
            exit;
        }

        $date = DateTime::createFromFormat('Y-m-d', $targetDate);
        if (!($date instanceof DateTime) || $date->format('Y-m-d') !== $targetDate) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Format tanggal jatuh tempo serentak tidak valid']);
            exit;
        }

        if ($targetTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $targetTime)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Format jam jatuh tempo serentak tidak valid']);
            exit;
        }

        $targetIsolirAt = $targetTime !== '' ? billing_build_isolir_datetime($targetDate, $targetTime) : null;
        $hasServiceActiveUntil = billing_has_column('service_active_until');

        if ($targetIsolirAt !== null) {
            $sql = "
                UPDATE billing b
                JOIN users u ON u.id = b.user_id
                SET
                    b.jatuh_tempo = ?," .
                    ($hasServiceActiveUntil ? "
                    b.service_active_until = ?," : "") . "
                    b.isolir_at = CASE
                        WHEN b.isolir_enabled = 1 THEN ?
                        ELSE b.isolir_at
                    END,
                    b.last_update = CURRENT_TIMESTAMP
                WHERE b.tenant_id = ? AND u.tenant_id = b.tenant_id AND b.payment_status != 'sudah_bayar'
            ";
            $stmt = $pdo->prepare($sql);
            $params = [$targetDate];
            if ($hasServiceActiveUntil) {
                $params[] = $targetDate;
            }
            $params[] = $targetIsolirAt;
            $params[] = $tenantId;
            $stmt->execute($params);
        } else {
            $sql = "
                UPDATE billing b
                JOIN users u ON u.id = b.user_id
                SET
                    b.jatuh_tempo = ?," .
                    ($hasServiceActiveUntil ? "
                    b.service_active_until = ?," : "") . "
                    b.isolir_at = CASE
                        WHEN b.isolir_enabled = 1 AND b.isolir_at IS NOT NULL THEN TIMESTAMP(?, TIME(b.isolir_at))
                        ELSE b.isolir_at
                    END,
                    b.last_update = CURRENT_TIMESTAMP
                WHERE b.tenant_id = ? AND u.tenant_id = b.tenant_id AND b.payment_status != 'sudah_bayar'
            ";
            $stmt = $pdo->prepare($sql);
            $params = [$targetDate];
            if ($hasServiceActiveUntil) {
                $params[] = $targetDate;
            }
            $params[] = $targetDate;
            $params[] = $tenantId;
            $stmt->execute($params);
        }
        $affected = $stmt->rowCount();

        clear_billing_cache($tenantId);
        echo json_encode([
            'success' => true,
            'message' => $affected > 0
                ? "{$affected} tagihan belum bayar berhasil diseragamkan ke jatuh tempo {$targetDate}" . ($targetTime !== '' ? " {$targetTime}" : '') . "."
                : 'Tidak ada tagihan belum bayar yang perlu diperbarui.',
            'affected_rows' => $affected
        ]);
        exit;
    }

    if ($action === 'mark_all_unpaid') {
        $targetDate = trim((string) ($_POST['target_date'] ?? ''));
        $targetTime = trim((string) ($_POST['target_time'] ?? ''));
        if ($targetDate === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tanggal jatuh tempo untuk fitur belum bayar semua wajib diisi']);
            exit;
        }

        $date = DateTime::createFromFormat('Y-m-d', $targetDate);
        if (!($date instanceof DateTime) || $date->format('Y-m-d') !== $targetDate) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Format tanggal jatuh tempo tidak valid']);
            exit;
        }

        if ($targetTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $targetTime)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Format jam jatuh tempo tidak valid']);
            exit;
        }

        $isolatedRowsStmt = $pdo->prepare("
            SELECT
                b.id,
                b.user_id,
                b.isolir_status,
                b.isolir_profile_before,
                u.username,
                u.nama
            FROM billing b
            JOIN users u ON u.id = b.user_id AND u.tenant_id = b.tenant_id
            WHERE b.tenant_id = ? AND b.isolir_status = 'terisolir'
        ");
        $isolatedRowsStmt->execute([$tenantId]);
        $isolatedRows = $isolatedRowsStmt->fetchAll(PDO::FETCH_ASSOC);

        $restoreSuccess = 0;
        $restoreFailed = 0;
        $restoreErrors = [];

        if ($isolatedRows !== []) {
            try {
                $api = mikrotik_isolir_connect(app_mikrotik_config($tenantId));
                try {
                    foreach ($isolatedRows as $billing) {
                        try {
                            billing_restore_isolir_for_row_with_api($pdo, $tenantId, $billing, $api);
                            $restoreSuccess++;
                        } catch (Throwable $e) {
                            $restoreFailed++;
                            billing_update_isolir_state($pdo, $tenantId, (int) $billing['id'], [
                                'isolir_status' => 'gagal',
                                'isolir_action_at' => date('Y-m-d H:i:s'),
                                'isolir_error' => substr($e->getMessage(), 0, 255)
                            ]);
                            $restoreErrors[] = "{$billing['username']}: {$e->getMessage()}";
                        }
                    }
                } finally {
                    $api->disconnect();
                }
            } catch (Throwable $e) {
                $restoreFailed = count($isolatedRows);
                foreach ($isolatedRows as $billing) {
                    billing_update_isolir_state($pdo, $tenantId, (int) $billing['id'], [
                        'isolir_status' => 'gagal',
                        'isolir_action_at' => date('Y-m-d H:i:s'),
                        'isolir_error' => substr($e->getMessage(), 0, 255)
                    ]);
                }
                $restoreErrors[] = $e->getMessage();
            }
        }

        $targetIsolirAt = $targetTime !== '' ? billing_build_isolir_datetime($targetDate, $targetTime) : null;
        $hasServiceActiveUntil = billing_has_column('service_active_until');
        $hasLastDaysAdded = billing_has_column('last_days_added');
        $hasServiceDaysAddedTotal = billing_has_column('service_days_added_total');

        if ($targetIsolirAt !== null) {
            $sql = "
                UPDATE billing b
                JOIN users u ON u.id = b.user_id
                SET
                    b.payment_status = 'belum_bayar',
                    b.paid_at = NULL,
                    b.amount_paid = 0,
                    b.remaining_amount = COALESCE(b.jumlah, 0),
                    b.last_payment_amount = 0,
                    b.last_payment_at = NULL,
                    b.payment_count = 0," .
                    ($hasLastDaysAdded ? "
                    b.last_days_added = 0," : "") .
                    ($hasServiceDaysAddedTotal ? "
                    b.service_days_added_total = 0," : "") . "
                    b.jatuh_tempo = ?," .
                    ($hasServiceActiveUntil ? "
                    b.service_active_until = ?," : "") . "
                    b.status = CASE WHEN b.status = 'expired' THEN 'aktif' ELSE b.status END,
                    b.isolir_status = CASE
                        WHEN b.isolir_enabled = 1 THEN 'menunggu'
                        ELSE 'nonaktif'
                    END,
                    b.isolir_at = CASE
                        WHEN b.isolir_enabled = 1 THEN ?
                        ELSE b.isolir_at
                    END,
                    b.isolir_action_at = NULL,
                    b.isolir_error = NULL,
                    b.last_update = CURRENT_TIMESTAMP
                WHERE b.tenant_id = ? AND u.tenant_id = b.tenant_id
            ";
            $stmt = $pdo->prepare($sql);
            $params = [$targetDate];
            if ($hasServiceActiveUntil) {
                $params[] = $targetDate;
            }
            $params[] = $targetIsolirAt;
            $params[] = $tenantId;
            $stmt->execute($params);
        } else {
            $sql = "
                UPDATE billing b
                JOIN users u ON u.id = b.user_id
                SET
                    b.payment_status = 'belum_bayar',
                    b.paid_at = NULL,
                    b.amount_paid = 0,
                    b.remaining_amount = COALESCE(b.jumlah, 0),
                    b.last_payment_amount = 0,
                    b.last_payment_at = NULL,
                    b.payment_count = 0," .
                    ($hasLastDaysAdded ? "
                    b.last_days_added = 0," : "") .
                    ($hasServiceDaysAddedTotal ? "
                    b.service_days_added_total = 0," : "") . "
                    b.jatuh_tempo = ?," .
                    ($hasServiceActiveUntil ? "
                    b.service_active_until = ?," : "") . "
                    b.status = CASE WHEN b.status = 'expired' THEN 'aktif' ELSE b.status END,
                    b.isolir_status = CASE
                        WHEN b.isolir_enabled = 1 AND b.isolir_at IS NOT NULL THEN 'menunggu'
                        ELSE 'nonaktif'
                    END,
                    b.isolir_at = CASE
                        WHEN b.isolir_enabled = 1 AND b.isolir_at IS NOT NULL THEN TIMESTAMP(?, TIME(b.isolir_at))
                        ELSE b.isolir_at
                    END,
                    b.isolir_action_at = NULL,
                    b.isolir_error = NULL,
                    b.last_update = CURRENT_TIMESTAMP
                WHERE b.tenant_id = ? AND u.tenant_id = b.tenant_id
            ";
            $stmt = $pdo->prepare($sql);
            $params = [$targetDate];
            if ($hasServiceActiveUntil) {
                $params[] = $targetDate;
            }
            $params[] = $targetDate;
            $params[] = $tenantId;
            $stmt->execute($params);
        }
        $affected = $stmt->rowCount();

        clear_billing_cache($tenantId);

        $message = $affected > 0
            ? "{$affected} tagihan berhasil diubah menjadi belum bayar dengan jatuh tempo {$targetDate}" . ($targetTime !== '' ? " {$targetTime}" : '') . "."
            : 'Tidak ada tagihan yang diperbarui.';

        if ($restoreSuccess > 0) {
            $message .= " {$restoreSuccess} koneksi yang sebelumnya terisolir berhasil dipulihkan.";
        }
        if ($restoreFailed > 0) {
            $message .= " {$restoreFailed} koneksi gagal dipulihkan.";
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'affected_rows' => $affected,
            'restored_count' => $restoreSuccess,
            'restore_failed_count' => $restoreFailed,
            'restore_errors' => $restoreErrors
        ]);
        exit;
    }

    if ($action === 'apply_isolir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tagihan tidak valid']);
            exit;
        }

        try {
            $summary = billing_run_due_isolir($pdo, $tenantId, $id);
            $message = $summary['processed'] > 0
                ? ($summary['messages'][0] ?? 'Isolir berhasil diproses.')
                : 'Tagihan ini belum siap untuk diisolir.';

            echo json_encode([
                'success' => true,
                'message' => $message,
                'summary' => $summary
            ]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'mark_paid') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tagihan tidak valid']);
            exit;
        }

        try {
            $result = billing_mark_paid($pdo, $tenantId, $id);
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'restored_profile' => $result['restored_profile'],
            ]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? 'aktif'));
    $paymentStatus = trim((string) ($_POST['payment_status'] ?? 'belum_bayar'));
    $jatuhTempo = trim((string) ($_POST['jatuh_tempo'] ?? ''));
    $jumlah = trim((string) ($_POST['jumlah'] ?? ''));
    $catatan = trim((string) ($_POST['catatan'] ?? ''));
    $isolirEnabled = (int) ($_POST['isolir_enabled'] ?? 0) === 1;
    $isolirTime = trim((string) ($_POST['isolir_time'] ?? ''));
    $isolirAt = $isolirEnabled ? billing_build_isolir_datetime($jatuhTempo, $isolirTime) : null;

    if ($isolirEnabled && $isolirTime === '' && isset($_POST['isolir_at'])) {
        $isolirAt = billing_normalize_datetime_input($_POST['isolir_at'] ?? null);
        $isolirTime = billing_format_time_for_input($isolirAt);
    }

    if ($userId <= 0 || $jatuhTempo === '' || $jumlah === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Data pembayaran wajib lengkap']);
        exit;
    }

    if (!in_array($status, ['aktif', 'pending', 'expired'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Status layanan tidak valid']);
        exit;
    }

    if (!in_array($paymentStatus, ['belum_bayar', 'sudah_bayar'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Status pembayaran tidak valid']);
        exit;
    }

    if (!is_numeric($jumlah)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Nominal tagihan tidak valid']);
        exit;
    }

    if ($isolirEnabled && $paymentStatus !== 'sudah_bayar' && ($isolirTime === '' || $isolirAt === null)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Jam isolir wajib diisi dan akan mengikuti tanggal jatuh tempo']);
        exit;
    }

    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND id = ? LIMIT 1");
    $stmtUser->execute([$tenantId, $userId]);
    if (!$stmtUser->fetchColumn()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'User tidak valid']);
        exit;
    }

    $paidAt = $paymentStatus === 'sudah_bayar' ? date('Y-m-d H:i:s') : null;
    $numericAmount = (float) $jumlah;
    $manualAmountPaid = $paymentStatus === 'sudah_bayar' ? $numericAmount : 0.0;
    $manualRemainingAmount = $paymentStatus === 'sudah_bayar' ? 0.0 : $numericAmount;
    $manualLastPaymentAmount = $paymentStatus === 'sudah_bayar' ? $numericAmount : 0.0;
    $manualLastPaymentAt = $paymentStatus === 'sudah_bayar' ? $paidAt : null;
    $serviceActiveUntil = $jatuhTempo;
    $isolirStatus = $isolirEnabled && $paymentStatus !== 'sudah_bayar' ? 'menunggu' : 'nonaktif';
    $isolirProfileBefore = null;
    $isolirActionAt = null;
    $isolirError = null;
    $restoredMessage = null;

    if ($id > 0) {
        $current = billing_fetch_row($pdo, $tenantId, $id);
        if ($current === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tagihan tidak ditemukan']);
            exit;
        }

        if (($current['isolir_status'] ?? '') === 'terisolir' && $paymentStatus !== 'sudah_bayar') {
            $isolirStatus = 'terisolir';
            $isolirProfileBefore = $current['isolir_profile_before'];
            $isolirActionAt = $current['isolir_action_at'];
            $isolirError = $current['isolir_error'];
        }

        $currentPaymentCount = (int) ($current['payment_count'] ?? 0);
        $manualPaymentCount = $paymentStatus === 'sudah_bayar'
            ? max(1, $currentPaymentCount)
            : 0;

        $sql = "
            UPDATE billing
            SET user_id = ?, status = ?, payment_status = ?, jatuh_tempo = ?, jumlah = ?, paid_at = ?, catatan = ?,
                amount_paid = ?, remaining_amount = ?, last_payment_amount = ?, last_payment_at = ?, payment_count = ?,
                " . (billing_has_column('service_active_until') ? "service_active_until = ?, " : "") . "
                isolir_enabled = ?, isolir_at = ?, isolir_status = ?, isolir_profile_before = ?, isolir_action_at = ?, isolir_error = ?,
                last_update = CURRENT_TIMESTAMP
            WHERE tenant_id = ? AND id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $params = [
            $userId,
            $status,
            $paymentStatus,
            $jatuhTempo,
            $jumlah,
            $paidAt,
            $catatan ?: null,
            $manualAmountPaid,
            $manualRemainingAmount,
            $manualLastPaymentAmount,
            $manualLastPaymentAt,
            $manualPaymentCount,
        ];
        if (billing_has_column('service_active_until')) {
            $params[] = $serviceActiveUntil;
        }
        $params = array_merge($params, [
            $isolirEnabled ? 1 : 0,
            $isolirAt,
            $isolirStatus,
            $isolirProfileBefore,
            $isolirActionAt,
            $isolirError,
            $tenantId,
            $id
        ]);
        $stmt->execute($params);
        $message = 'Tagihan berhasil diperbarui';

        if ($paymentStatus === 'sudah_bayar' && ($current['isolir_status'] ?? '') === 'terisolir') {
            try {
                $restoredProfile = billing_restore_isolir_for_row($pdo, $tenantId, $current);
                if ($restoredProfile !== null) {
                    $restoredMessage = " Koneksi dikembalikan ke profile {$restoredProfile}.";
                }
            } catch (Throwable $e) {
                billing_update_isolir_state($pdo, $tenantId, $id, [
                    'isolir_status' => 'gagal',
                    'isolir_action_at' => date('Y-m-d H:i:s'),
                    'isolir_error' => substr($e->getMessage(), 0, 255)
                ]);
                $restoredMessage = ' Namun buka isolir MikroTik gagal: ' . $e->getMessage();
            }
        }
    } else {
        $manualPaymentCount = $paymentStatus === 'sudah_bayar' ? 1 : 0;
        $sql = "
            INSERT INTO billing (
                tenant_id, user_id, status, payment_status, jatuh_tempo, jumlah, paid_at, catatan,
                amount_paid, remaining_amount, last_payment_amount, last_payment_at, payment_count,
                " . (billing_has_column('service_active_until') ? "service_active_until, " : "") . "
                isolir_enabled, isolir_at, isolir_status, isolir_profile_before, isolir_action_at, isolir_error
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?," . (billing_has_column('service_active_until') ? " ?,": "") . " ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($sql);
        $params = [
            $tenantId,
            $userId,
            $status,
            $paymentStatus,
            $jatuhTempo,
            $jumlah,
            $paidAt,
            $catatan ?: null,
            $manualAmountPaid,
            $manualRemainingAmount,
            $manualLastPaymentAmount,
            $manualLastPaymentAt,
            $manualPaymentCount,
        ];
        if (billing_has_column('service_active_until')) {
            $params[] = $serviceActiveUntil;
        }
        $params = array_merge($params, [
            $isolirEnabled ? 1 : 0,
            $isolirAt,
            $isolirStatus,
            $isolirProfileBefore,
            $isolirActionAt,
            $isolirError
        ]);
        $stmt->execute($params);
        $message = 'Tagihan berhasil ditambahkan';
    }

    clear_billing_cache($tenantId);
    echo json_encode([
        'success' => true,
        'message' => $message . ($restoredMessage ?? '')
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $autoIsolirSummary = billing_try_auto_run_due_isolir($pdo, $tenantId);

    $stmtBillings = $pdo->prepare("
        SELECT
            b.id,
            b.user_id,
            u.nama,
            u.username,
            u.phone,
            b.status,
            b.payment_status,
            b.jatuh_tempo,
            b.jumlah,
            b.amount_paid,
            b.remaining_amount,
            b.paid_at,
            b.catatan,
            b.isolir_enabled,
            b.isolir_at,
            b.isolir_status,
            b.isolir_profile_before,
            b.isolir_action_at,
            b.isolir_error,
            b.last_update
        FROM billing b
        JOIN users u ON u.id = b.user_id AND u.tenant_id = b.tenant_id
        WHERE b.tenant_id = ?
        ORDER BY b.jatuh_tempo ASC, b.id DESC
    ");
    $stmtBillings->execute([$tenantId]);
    $billings = $stmtBillings->fetchAll();

    $stmtUsers = $pdo->prepare("
        SELECT id, nama, username
        FROM users
        WHERE tenant_id = ?
        ORDER BY nama ASC
    ");
    $stmtUsers->execute([$tenantId]);
    $users = $stmtUsers->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => array_map(static function (array $item) {
            $item = billing_normalize_row($item);
            $item['isolir_enabled'] = (int) ($item['isolir_enabled'] ?? 0);
            $item['isolir_at_input'] = billing_format_datetime_for_input($item['isolir_at'] ?? null);
            $item['isolir_time_input'] = billing_format_time_for_input($item['isolir_at'] ?? null);
            return $item;
        }, $billings),
        'users' => $users,
        'auto_isolir' => $autoIsolirSummary
    ]);
}
