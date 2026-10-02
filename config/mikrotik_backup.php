<?php
require_once __DIR__ . '/mikrotik_isolir.php';

if (!function_exists('mikrotik_backup_bool_value')) {
    function mikrotik_backup_bool_value($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('mikrotik_backup_enabled')) {
    function mikrotik_backup_enabled(int $tenantId): bool
    {
        return mikrotik_backup_bool_value(app_setting_get('mikrotik_backup_enabled', 'false', $tenantId));
    }
}

if (!function_exists('mikrotik_backup_interval_days')) {
    function mikrotik_backup_interval_days(int $tenantId): int
    {
        return (int) app_setting_get('mikrotik_backup_interval_days', 7, $tenantId);
    }
}

if (!function_exists('mikrotik_backup_time')) {
    function mikrotik_backup_time(int $tenantId): string
    {
        $value = trim((string) app_setting_get('mikrotik_backup_time', '02:00', $tenantId));
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '02:00';
    }
}

if (!function_exists('mikrotik_backup_retention_days')) {
    function mikrotik_backup_retention_days(int $tenantId): int
    {
        $value = (int) app_setting_get('mikrotik_backup_retention_days', 30, $tenantId);
        return $value > 0 ? $value : 30;
    }
}

if (!function_exists('mikrotik_backup_last_at')) {
    function mikrotik_backup_last_at(int $tenantId): ?string
    {
        $value = trim((string) app_setting_get('mikrotik_backup_last_at', '', $tenantId));
        return $value === '' ? null : $value;
    }
}

if (!function_exists('mikrotik_backup_should_run')) {
    function mikrotik_backup_should_run(int $tenantId, ?int $nowTs = null): bool
    {
        if (!mikrotik_backup_enabled($tenantId)) {
            return false;
        }

        $interval = mikrotik_backup_interval_days($tenantId);
        if ($interval <= 0) {
            return false;
        }

        $nowTs = $nowTs ?? time();
        $backupTime = mikrotik_backup_time($tenantId);
        [$hour, $minute] = array_map('intval', explode(':', $backupTime));
        $todayRunTs = mktime($hour, $minute, 0, (int) date('n', $nowTs), (int) date('j', $nowTs), (int) date('Y', $nowTs));
        if ($nowTs < $todayRunTs) {
            return false;
        }

        $lastAt = mikrotik_backup_last_at($tenantId);
        if ($lastAt === null) {
            return true;
        }

        $lastTs = strtotime($lastAt);
        if ($lastTs === false) {
            return true;
        }

        $diffDays = (int) floor(($nowTs - $lastTs) / 86400);
        return $diffDays >= $interval;
    }
}

if (!function_exists('mikrotik_backup_file_prefix')) {
    function mikrotik_backup_file_prefix(int $tenantId): string
    {
        $appName = trim((string) app_setting_get('app_name', 'Nikonet', $tenantId));
        $prefix = preg_replace('/[^A-Za-z0-9_-]+/', '-', $appName) ?: '';
        $prefix = trim($prefix, '-_');

        return $prefix !== '' ? $prefix : 'tenant-' . $tenantId;
    }
}

if (!function_exists('mikrotik_backup_cleanup_old_files')) {
    function mikrotik_backup_cleanup_old_files(MikroTikClient $api, int $tenantId, ?int $nowTs = null): int
    {
        $retentionDays = mikrotik_backup_retention_days($tenantId);
        $cutoffTs = ($nowTs ?? time()) - ($retentionDays * 86400);
        $prefix = mikrotik_backup_file_prefix($tenantId) . '-';
        $files = mikrotik_isolir_normalize_rows($api->comm('/file/print', [
            '.proplist' => '.id,name',
        ]));

        $deleted = 0;
        foreach ($files as $file) {
            $fileId = trim((string) ($file['.id'] ?? ''));
            $fileName = trim((string) ($file['name'] ?? ''));
            if ($fileId === '' || !str_starts_with($fileName, $prefix)) {
                continue;
            }

            if (!preg_match('/-(\d{8})-(\d{6})\.backup$/', $fileName, $matches)) {
                continue;
            }

            $fileDate = DateTime::createFromFormat('Ymd His', $matches[1] . ' ' . $matches[2]);
            if (!$fileDate instanceof DateTime || $fileDate->getTimestamp() > $cutoffTs) {
                continue;
            }

            $result = $api->comm('/file/remove', [
                'numbers' => $fileId,
            ]);
            if (mikrotik_isolir_extract_error($result) === null) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

if (!function_exists('mikrotik_backup_run')) {
    function mikrotik_backup_run(MikroTikClient $api, int $tenantId, ?array $mkConfig = null): array
    {
        $name = mikrotik_backup_file_prefix($tenantId) . '-' . date('Ymd-His');
        $routerFileName = $name . '.backup';
        $result = $api->comm('/system/backup/save', [
            'name' => $name,
            'dont-encrypt' => 'yes',
        ]);

        $error = mikrotik_isolir_extract_error($result);
        if ($error !== null) {
            return [
                'success' => false,
                'name' => $name,
                'message' => $error,
            ];
        }

        $lastAt = date(DATE_ATOM);
        app_setting_set('mikrotik_backup_last_at', $lastAt, $tenantId);
        app_setting_set('mikrotik_backup_last_name', $routerFileName, $tenantId);
        $deletedOldBackups = mikrotik_backup_cleanup_old_files($api, $tenantId);
        $message = 'Backup MikroTik berhasil dibuat di storage MikroTik';
        if ($deletedOldBackups > 0) {
            $message .= ". {$deletedOldBackups} backup lama dihapus";
        }

        return [
            'success' => true,
            'name' => $name,
            'file_name' => $routerFileName,
            'last_at' => $lastAt,
            'deleted_old_backups' => $deletedOldBackups,
            'message' => $message,
        ];
    }
}
