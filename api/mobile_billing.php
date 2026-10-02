<?php
require_once __DIR__ . '/mobile_bootstrap.php';
require_once __DIR__ . '/../config/mikrotik_isolir.php';

function mobile_billing_estimated_service_days(float $billAmount, float $paymentAmount): int
{
    if ($billAmount <= 0 || $paymentAmount <= 0) {
        return 0;
    }

    $days = (int) floor(($paymentAmount / $billAmount) * 30);
    return max(1, min(30, $days));
}

function mobile_billing_cycle_days(array $billing): int
{
    $cycleDays = (int) ($billing['billing_cycle_days'] ?? 30);
    return $cycleDays > 0 ? $cycleDays : 30;
}

function mobile_billing_advance_due_date(array $billing, int $daysAdded): ?string
{
    if ($daysAdded <= 0) {
        return isset($billing['jatuh_tempo']) ? trim((string) $billing['jatuh_tempo']) ?: null : null;
    }

    $today = new DateTimeImmutable(date('Y-m-d'));
    $candidates = [$today];

    foreach (['service_active_until', 'jatuh_tempo'] as $field) {
        $raw = trim((string) ($billing[$field] ?? ''));
        if ($raw === '') {
            continue;
        }

        try {
            $candidates[] = new DateTimeImmutable(substr($raw, 0, 10));
        } catch (Throwable $e) {
        }
    }

    usort($candidates, static function (DateTimeImmutable $left, DateTimeImmutable $right): int {
        return $left <=> $right;
    });

    $baseDate = $candidates[count($candidates) - 1] ?? $today;
    return $baseDate->modify('+' . $daysAdded . ' days')->format('Y-m-d');
}

function mobile_billing_build_isolir_datetime(?string $dueDate, ?string $sourceDateTime): ?string
{
    $dueDate = trim((string) $dueDate);
    if ($dueDate === '') {
        return null;
    }

    $timePart = '00:00:00';
    $sourceDateTime = trim((string) $sourceDateTime);
    if ($sourceDateTime !== '') {
        try {
            $timePart = (new DateTimeImmutable($sourceDateTime))->format('H:i:s');
        } catch (Throwable $e) {
        }
    }

    try {
        return (new DateTimeImmutable($dueDate . ' ' . $timePart))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function mobile_billing_effective_payment_status(array $billing): string
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

function mobile_billing_normalize_row(array $billing): array
{
    if (array_key_exists('remaining_amount', $billing)) {
        $billAmount = (float) ($billing['jumlah'] ?? 0);
        $amountPaid = (float) ($billing['amount_paid'] ?? 0);
        $remaining = (float) ($billing['remaining_amount'] ?? max($billAmount - $amountPaid, 0));
        $billing['remaining_amount'] = $remaining <= 0.00001 ? 0 : $remaining;
    }

    return $billing;
}

function mobile_billing_clear_cache(int $tenantId): void
{
    mobile_forget_tenant_cache($tenantId);

    if (function_exists('app_cache_forget') && function_exists('app_cache_key') && function_exists('app_setting_get') && function_exists('env_value')) {
        $trafficInterface = app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId);
        app_cache_forget(app_cache_key('mikrotik_dashboard_' . $trafficInterface, $tenantId));
    }
}

function mobile_billing_restore_isolir(int $tenantId, array $billing): ?string
{
    if (($billing['isolir_status'] ?? 'nonaktif') !== 'terisolir') {
        $stmt = $GLOBALS['pdo']->prepare("
            UPDATE billing
            SET isolir_status = 'nonaktif', isolir_error = NULL, last_update = CURRENT_TIMESTAMP
            WHERE tenant_id = ? AND id = ?
        ");
        $stmt->execute([$tenantId, (int) $billing['id']]);
        return null;
    }

    $result = mikrotik_isolir_restore_user_with_retry(
        (string) ($billing['username'] ?? ''),
        $billing['isolir_profile_before'] ?? null,
        app_mikrotik_config($tenantId)
    );

    $stmt = $GLOBALS['pdo']->prepare("
        UPDATE billing
        SET
            isolir_status = 'dipulihkan',
            isolir_action_at = ?,
            isolir_error = NULL,
            last_update = CURRENT_TIMESTAMP
        WHERE tenant_id = ? AND id = ?
    ");
    $stmt->execute([date('Y-m-d H:i:s'), $tenantId, (int) $billing['id']]);

    return $result['restored_profile'] ?? null;
}

function mobile_billing_schedule_isolir(int $tenantId, int $billingId, bool $isolirEnabled, ?string $nextDueDate, ?string $currentIsolirAt): ?string
{
    if (!$isolirEnabled) {
        $stmt = $GLOBALS['pdo']->prepare("
            UPDATE billing
            SET
                isolir_status = 'nonaktif',
                isolir_at = NULL,
                isolir_action_at = NULL,
                isolir_error = NULL,
                last_update = CURRENT_TIMESTAMP
            WHERE tenant_id = ? AND id = ?
        ");
        $stmt->execute([$tenantId, $billingId]);
        return null;
    }

    $nextIsolirAt = mobile_billing_build_isolir_datetime($nextDueDate, $currentIsolirAt);
    $stmt = $GLOBALS['pdo']->prepare("
        UPDATE billing
        SET
            isolir_status = ?,
            isolir_at = ?,
            isolir_action_at = NULL,
            isolir_error = NULL,
            last_update = CURRENT_TIMESTAMP
        WHERE tenant_id = ? AND id = ?
    ");
    $stmt->execute([
        $nextIsolirAt !== null ? 'menunggu' : 'nonaktif',
        $nextIsolirAt,
        $tenantId,
        $billingId,
    ]);

    return $nextIsolirAt;
}

$account = mobile_require_account();
$tenantIds = mobile_resolve_tenant_ids($account);

if (!$tenantIds) {
    mobile_json_response([
        'success' => false,
        'message' => 'Tenant aktif belum dipilih',
    ], 422);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) mobile_request_value('action', ''));

    if (!in_array($action, ['mark_paid', 'record_payment'], true)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Action tidak didukung di mobile',
        ], 422);
    }

    $billingId = (int) mobile_request_value('id', 0);
    if ($billingId <= 0) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tagihan tidak valid',
        ], 422);
    }

    $requestedTenantId = (int) mobile_request_value('tenant_id', 0);
    $tenantId = $requestedTenantId > 0 ? $requestedTenantId : (int) $tenantIds[0];
    if (!in_array($tenantId, $tenantIds, true)) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tagihan ini tidak termasuk tenant yang sedang aktif.',
        ], 403);
    }

    $stmt = $GLOBALS['pdo']->prepare("
        SELECT
            b.id,
            b.payment_status
        FROM billing b
        WHERE b.tenant_id = ? AND b.id = ?
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $billingId]);
    $billing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$billing) {
        mobile_json_response([
            'success' => false,
            'message' => 'Tagihan tidak ditemukan',
        ], 404);
    }

    $detailStmt = $GLOBALS['pdo']->prepare("
        SELECT
            b.id,
            b.payment_status,
            b.paid_at,
            b.jumlah,
            b.jatuh_tempo,
            b.billing_cycle_days,
            b.service_active_until,
            b.service_days_added_total,
            b.last_days_added,
            b.amount_paid,
            b.remaining_amount,
            b.payment_count,
            b.isolir_enabled,
            b.isolir_at,
            b.isolir_status,
            b.isolir_profile_before,
            u.username
        FROM billing b
        JOIN users u ON u.id = b.user_id AND u.tenant_id = b.tenant_id
        WHERE b.tenant_id = ? AND b.id = ?
        LIMIT 1
    ");
    $detailStmt->execute([$tenantId, $billingId]);
    $billingDetail = mobile_billing_normalize_row($detailStmt->fetch(PDO::FETCH_ASSOC) ?: $billing);

    $paidVia = mobile_has_column('billing', 'paid_via')
        ? trim((string) mobile_request_value('paid_via', 'scan_qr'))
        : null;
    if ($paidVia !== null && !in_array($paidVia, ['manual', 'scan_qr', 'google', 'system'], true)) {
        $paidVia = 'scan_qr';
    }

    if ($action === 'record_payment') {
        $paymentAmount = (float) mobile_request_value('payment_amount', 0);
        if ($paymentAmount <= 0) {
            mobile_json_response([
                'success' => false,
                'message' => 'Nominal bayar wajib lebih besar dari 0',
            ], 422);
        }

        $billAmount = (float) ($billingDetail['jumlah'] ?? 0);
        $currentPaid = mobile_has_column('billing', 'amount_paid')
            ? (float) ($billingDetail['amount_paid'] ?? 0)
            : 0.0;
        $currentRemaining = mobile_has_column('billing', 'remaining_amount')
            ? (float) ($billingDetail['remaining_amount'] ?? max($billAmount - $currentPaid, 0))
            : max($billAmount - $currentPaid, 0);

        if ($billAmount <= 0) {
            mobile_json_response([
                'success' => false,
                'message' => 'Nominal tagihan tidak valid untuk diproses.',
            ], 422);
        }

        if ($currentRemaining <= 0.00001 || (string) ($billingDetail['payment_status'] ?? '') === 'sudah_bayar') {
            if ((string) ($billingDetail['payment_status'] ?? '') !== 'sudah_bayar') {
                $repairFields = ['payment_status = ?', 'last_update = CURRENT_TIMESTAMP'];
                $repairValues = ['sudah_bayar'];
                if (mobile_has_column('billing', 'remaining_amount')) {
                    $repairFields[] = 'remaining_amount = ?';
                    $repairValues[] = 0;
                }
                if (mobile_has_column('billing', 'paid_at')) {
                    $repairFields[] = 'paid_at = COALESCE(paid_at, NOW())';
                }
                $repairValues[] = $tenantId;
                $repairValues[] = $billingId;
                $repairSql = 'UPDATE billing SET ' . implode(', ', $repairFields) . ' WHERE tenant_id = ? AND id = ?';
                $repairStmt = $GLOBALS['pdo']->prepare($repairSql);
                $repairStmt->execute($repairValues);
            }
            mobile_json_response([
                'success' => false,
                'message' => 'Tagihan ini sudah lunas.',
            ], 422);
        }

        if ($paymentAmount > $currentRemaining + 0.00001) {
            mobile_json_response([
                'success' => false,
                'message' => 'Nominal bayar melebihi sisa tagihan.',
            ], 422);
        }

        $newPaid = $currentPaid + $paymentAmount;
        $remaining = max($billAmount - $newPaid, 0);
        $isSettled = $remaining <= 0.00001;
        $serviceDaysEstimate = mobile_billing_estimated_service_days($billAmount, $paymentAmount);
        $nextDueDate = mobile_billing_advance_due_date($billingDetail, $serviceDaysEstimate);
        $isolirEnabled = (int) ($billingDetail['isolir_enabled'] ?? 0) === 1;

        $fields = ['last_update = CURRENT_TIMESTAMP'];
        $values = [];

        if (mobile_has_column('billing', 'amount_paid')) {
            $fields[] = 'amount_paid = ?';
            $values[] = $newPaid;
        }
        if (mobile_has_column('billing', 'remaining_amount')) {
            $fields[] = 'remaining_amount = ?';
            $values[] = $remaining;
        }
        if (mobile_has_column('billing', 'last_payment_amount')) {
            $fields[] = 'last_payment_amount = ?';
            $values[] = $paymentAmount;
        }
        if (mobile_has_column('billing', 'last_payment_at')) {
            $fields[] = 'last_payment_at = NOW()';
        }
        if (mobile_has_column('billing', 'payment_count')) {
            $fields[] = 'payment_count = ?';
            $values[] = ((int) ($billingDetail['payment_count'] ?? 0)) + 1;
        }
        if (mobile_has_column('billing', 'last_days_added')) {
            $fields[] = 'last_days_added = ?';
            $values[] = $serviceDaysEstimate;
        }
        if (mobile_has_column('billing', 'service_days_added_total')) {
            $fields[] = 'service_days_added_total = ?';
            $values[] = ((int) ($billingDetail['service_days_added_total'] ?? 0)) + $serviceDaysEstimate;
        }
        if ($nextDueDate !== null) {
            $fields[] = 'jatuh_tempo = ?';
            $values[] = $nextDueDate;
        }
        if (mobile_has_column('billing', 'service_active_until')) {
            $fields[] = 'service_active_until = ?';
            $values[] = $nextDueDate;
        }
        if (mobile_has_column('billing', 'last_payment_note')) {
            $fields[] = 'last_payment_note = ?';
            $values[] = trim((string) mobile_request_value('note', 'Pembayaran dari aplikasi mobile')) ?: null;
        }
        if ($paidVia !== null) {
            $fields[] = 'paid_via = ?';
            $values[] = $paidVia;
        }
        if (mobile_has_column('billing', 'scanned_at')) {
            $fields[] = 'scanned_at = NOW()';
        }
        if (mobile_has_column('billing', 'scanned_by_admin_user_id')) {
            $fields[] = 'scanned_by_admin_user_id = ?';
            $values[] = (int) $account['id'];
        }

        $fields[] = 'payment_status = ?';
        $values[] = $isSettled ? 'sudah_bayar' : 'belum_bayar';

        if (mobile_has_column('billing', 'paid_at')) {
            if ($isSettled) {
                $fields[] = 'paid_at = NOW()';
            } else {
                $fields[] = 'paid_at = NULL';
            }
        }

        $values[] = $tenantId;
        $values[] = $billingId;

        $sql = 'UPDATE billing SET ' . implode(', ', $fields) . ' WHERE tenant_id = ? AND id = ?';
        $stmt = $GLOBALS['pdo']->prepare($sql);
        $stmt->execute($values);

        $restoredProfile = null;
        $restoreWarning = null;
        $nextIsolirAt = null;
        if (!$isSettled) {
            if ((string) ($billingDetail['isolir_status'] ?? '') === 'terisolir') {
                try {
                    $restoredProfile = mobile_billing_restore_isolir($tenantId, $billingDetail);
                } catch (Throwable $e) {
                    $restoreWarning = $e->getMessage();
                }
            }

            $nextIsolirAt = mobile_billing_schedule_isolir(
                $tenantId,
                $billingId,
                $isolirEnabled,
                $nextDueDate,
                $billingDetail['isolir_at'] ?? null
            );
        } elseif ($isSettled && (string) ($billingDetail['isolir_status'] ?? '') === 'terisolir') {
            try {
                $restoredProfile = mobile_billing_restore_isolir($tenantId, $billingDetail);
            } catch (Throwable $e) {
                $restoreWarning = $e->getMessage();
            }
        }

        if ($restoreWarning !== null && (string) ($billingDetail['isolir_status'] ?? '') === 'terisolir') {
            $fallbackStatus = $isSettled
                ? 'gagal'
                : ($isolirEnabled && $nextIsolirAt !== null ? 'menunggu' : 'nonaktif');
            $fallbackActionAt = $fallbackStatus === 'gagal' ? date('Y-m-d H:i:s') : null;
            $fallbackIsolirAt = $isSettled ? ($billingDetail['isolir_at'] ?? null) : $nextIsolirAt;

            $errorStmt = $GLOBALS['pdo']->prepare("
                UPDATE billing
                SET
                    isolir_status = ?,
                    isolir_at = ?,
                    isolir_action_at = ?,
                    isolir_error = ?,
                    last_update = CURRENT_TIMESTAMP
                WHERE tenant_id = ? AND id = ?
            ");
            $errorStmt->execute([
                $fallbackStatus,
                $fallbackIsolirAt,
                $fallbackActionAt,
                substr($restoreWarning, 0, 255),
                $tenantId,
                $billingId,
            ]);
        }

        mobile_billing_clear_cache($tenantId);

        $message = $isSettled
            ? 'Pembayaran masuk dan tagihan sekarang lunas.'
            : 'Pembayaran masuk. Sisa tagihan masih ada.';
        if ($restoredProfile !== null) {
            $message .= " Koneksi dikembalikan ke profile {$restoredProfile}.";
        } elseif ($restoreWarning !== null) {
            $message .= ' Namun buka isolir MikroTik gagal: ' . $restoreWarning;
        }

        mobile_json_response([
            'success' => true,
            'message' => $message,
            'payment' => [
                'amount_paid' => $newPaid,
                'remaining_amount' => $remaining,
                'payment_status' => $isSettled ? 'sudah_bayar' : 'belum_bayar',
                'service_days_estimate' => $serviceDaysEstimate,
                'full_service_days' => mobile_billing_cycle_days($billingDetail),
                'service_ratio' => $paymentAmount / $billAmount,
                'next_due_date' => $nextDueDate,
                'service_active_until' => $nextDueDate,
                'next_isolir_at' => $nextIsolirAt,
                'restored_profile' => $restoredProfile,
                'restore_warning' => $restoreWarning,
            ],
        ]);
    }

    $alreadyPaid = (string) ($billing['payment_status'] ?? '') === 'sudah_bayar';
    $fields = ['payment_status = ?', 'last_update = CURRENT_TIMESTAMP'];
    $values = ['sudah_bayar'];

    if ($alreadyPaid && !empty($billingDetail['paid_at'])) {
        $fields[] = 'paid_at = ?';
        $values[] = $billingDetail['paid_at'];
    } else {
        $fields[] = 'paid_at = NOW()';
    }

    if (mobile_has_column('billing', 'amount_paid')) {
        $fields[] = 'amount_paid = ?';
        $values[] = (float) ($billingDetail['jumlah'] ?? 0);
    }
    if (mobile_has_column('billing', 'remaining_amount')) {
        $fields[] = 'remaining_amount = ?';
        $values[] = 0;
    }
    if (mobile_has_column('billing', 'last_payment_amount')) {
        $fields[] = 'last_payment_amount = ?';
        $values[] = (float) ($billingDetail['jumlah'] ?? 0);
    }
    if (mobile_has_column('billing', 'last_payment_at')) {
        $fields[] = 'last_payment_at = NOW()';
    }
    if (mobile_has_column('billing', 'payment_count')) {
        $fields[] = 'payment_count = ?';
        $values[] = $alreadyPaid
            ? max(1, (int) ($billingDetail['payment_count'] ?? 0))
            : max(1, ((int) ($billingDetail['payment_count'] ?? 0)) + 1);
    }

    if ($paidVia !== null) {
        $fields[] = 'paid_via = ?';
        $values[] = $paidVia;
    }
    if (mobile_has_column('billing', 'scanned_at')) {
        $fields[] = 'scanned_at = NOW()';
    }
    if (mobile_has_column('billing', 'scanned_by_admin_user_id')) {
        $fields[] = 'scanned_by_admin_user_id = ?';
        $values[] = (int) $account['id'];
    }

    $values[] = $tenantId;
    $values[] = $billingId;

    $sql = 'UPDATE billing SET ' . implode(', ', $fields) . ' WHERE tenant_id = ? AND id = ?';
    $stmt = $GLOBALS['pdo']->prepare($sql);
    $stmt->execute($values);

    $restoredProfile = null;
    $restoreWarning = null;
    if ((string) ($billingDetail['isolir_status'] ?? '') === 'terisolir') {
        try {
            $restoredProfile = mobile_billing_restore_isolir($tenantId, $billingDetail);
        } catch (Throwable $e) {
            $restoreWarning = $e->getMessage();
            $errorStmt = $GLOBALS['pdo']->prepare("
                UPDATE billing
                SET
                    isolir_status = 'gagal',
                    isolir_action_at = ?,
                    isolir_error = ?,
                    last_update = CURRENT_TIMESTAMP
                WHERE tenant_id = ? AND id = ?
            ");
            $errorStmt->execute([
                date('Y-m-d H:i:s'),
                substr($restoreWarning, 0, 255),
                $tenantId,
                $billingId,
            ]);
        }
    }

    mobile_billing_clear_cache($tenantId);

    $message = $alreadyPaid
        ? 'Tagihan ini memang sudah lunas.'
        : 'Tagihan berhasil ditandai sudah bayar.';
    if ($restoredProfile !== null) {
        $message .= " Koneksi dikembalikan ke profile {$restoredProfile}.";
    } elseif ($restoreWarning !== null) {
        $message .= ' Namun buka isolir MikroTik gagal: ' . $restoreWarning;
    }

    mobile_json_response([
        'success' => true,
        'message' => $message,
        'restored_profile' => $restoredProfile,
        'restore_warning' => $restoreWarning,
    ]);
}

mobile_require_method('GET');

$autoIsolirSummary = [
    'processed' => 0,
    'success' => 0,
    'failed' => 0,
    'messages' => [],
];

foreach ($tenantIds as $autoTenantId) {
    try {
        $rows = $GLOBALS['pdo']->prepare("
            SELECT
                b.id,
                u.username,
                b.isolir_profile_before
            FROM billing b
            JOIN users u ON u.id = b.user_id AND u.tenant_id = b.tenant_id
            WHERE b.tenant_id = ?
              AND b.payment_status != 'sudah_bayar'
              AND b.isolir_enabled = 1
              AND b.isolir_at IS NOT NULL
              AND b.isolir_status IN ('menunggu', 'gagal')
              AND b.isolir_at <= NOW()
            ORDER BY b.isolir_at ASC, b.id ASC
        ");
        $rows->execute([(int) $autoTenantId]);
        $dueRows = $rows->fetchAll(PDO::FETCH_ASSOC);
        if ($dueRows === []) {
            continue;
        }

        $settings = mikrotik_isolir_settings((int) $autoTenantId);
        $api = mikrotik_isolir_connect(app_mikrotik_config((int) $autoTenantId));

        try {
            foreach ($dueRows as $dueBilling) {
                $autoIsolirSummary['processed']++;

                try {
                    $result = mikrotik_isolir_apply_to_user($api, (string) ($dueBilling['username'] ?? ''), $settings);
                    $stmt = $GLOBALS['pdo']->prepare("
                        UPDATE billing
                        SET
                            isolir_status = 'terisolir',
                            isolir_profile_before = ?,
                            isolir_action_at = NOW(),
                            isolir_error = NULL,
                            last_update = CURRENT_TIMESTAMP
                        WHERE tenant_id = ? AND id = ?
                    ");
                    $stmt->execute([
                        ($dueBilling['isolir_profile_before'] ?? '') ?: ($result['previous_profile'] ?? 'default'),
                        (int) $autoTenantId,
                        (int) ($dueBilling['id'] ?? 0),
                    ]);
                    $autoIsolirSummary['success']++;
                    $autoIsolirSummary['messages'][] = !empty($result['already_isolated'])
                        ? ($dueBilling['username'] . ' sudah berada di profile isolir.')
                        : ($dueBilling['username'] . ' berhasil diisolir.');
                } catch (Throwable $e) {
                    $stmt = $GLOBALS['pdo']->prepare("
                        UPDATE billing
                        SET
                            isolir_status = 'gagal',
                            isolir_action_at = NOW(),
                            isolir_error = ?,
                            last_update = CURRENT_TIMESTAMP
                        WHERE tenant_id = ? AND id = ?
                    ");
                    $stmt->execute([
                        substr($e->getMessage(), 0, 255),
                        (int) $autoTenantId,
                        (int) ($dueBilling['id'] ?? 0),
                    ]);
                    $autoIsolirSummary['failed']++;
                    $autoIsolirSummary['messages'][] = ($dueBilling['username'] ?? 'User') . ' gagal diisolir: ' . $e->getMessage();
                }
            }
        } finally {
            $api->disconnect();
        }

        mobile_billing_clear_cache((int) $autoTenantId);
    } catch (Throwable $e) {
        $autoIsolirSummary['messages'][] = $e->getMessage();
    }
}

$tenantPlaceholders = implode(', ', array_fill(0, count($tenantIds), '?'));

$stmt = $GLOBALS['pdo']->prepare("
    SELECT
        b.id,
        b.tenant_id,
        b.user_id,
        u.nama,
        u.username,
        u.phone,
        b.status,
        b.payment_status,
        b.jatuh_tempo,
        b.jumlah,
        b.paid_at,
        b.catatan,
        b.isolir_enabled,
        b.isolir_at,
        b.isolir_status,
        b.amount_paid,
        b.remaining_amount,
        b.last_payment_amount,
        b.last_payment_at,
        b.invoice_code,
        b.qr_token,
        t.name AS tenant_name
    FROM billing b
    JOIN users u ON u.id = b.user_id AND u.tenant_id = b.tenant_id
    JOIN tenants t ON t.id = b.tenant_id
    WHERE b.tenant_id IN ($tenantPlaceholders)
    ORDER BY b.jatuh_tempo ASC, t.name ASC, b.id DESC
");
$stmt->execute($tenantIds);
$billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tenantStmt = $GLOBALS['pdo']->prepare("
    SELECT id, name, slug, owner_name, phone, status
    FROM tenants
    WHERE id IN ($tenantPlaceholders)
    ORDER BY name ASC, id ASC
");
$tenantStmt->execute($tenantIds);
$tenants = $tenantStmt->fetchAll(PDO::FETCH_ASSOC);

$tenantSummary = count($tenants) === 1
    ? $tenants[0]
    : [
        'id' => 0,
        'name' => count($tenants) . ' tenant aktif',
        'slug' => 'multi-tenant',
        'owner_name' => $account['nama'] ?? null,
        'phone' => null,
        'status' => 'active',
    ];

mobile_json_response([
    'success' => true,
    'data' => array_map(static function (array $item): array {
        $item = mobile_billing_normalize_row($item);
        $item['tenant_id'] = (int) $item['tenant_id'];
        $item['user_id'] = (int) $item['user_id'];
        $item['isolir_enabled'] = (int) ($item['isolir_enabled'] ?? 0);
        return $item;
    }, $billings),
    'tenant' => [
        'id' => (int) ($tenantSummary['id'] ?? 0),
        'name' => $tenantSummary['name'] ?? null,
        'slug' => $tenantSummary['slug'] ?? null,
        'owner_name' => $tenantSummary['owner_name'] ?? null,
        'phone' => $tenantSummary['phone'] ?? null,
        'status' => $tenantSummary['status'] ?? 'active',
    ],
    'tenants' => array_map(static function (array $tenant): array {
        return [
            'id' => (int) ($tenant['id'] ?? 0),
            'name' => $tenant['name'] ?? 'Tenant',
            'slug' => $tenant['slug'] ?? null,
            'owner_name' => $tenant['owner_name'] ?? null,
            'phone' => $tenant['phone'] ?? null,
            'status' => $tenant['status'] ?? 'active',
        ];
    }, $tenants),
    'auto_isolir' => $autoIsolirSummary,
]);
