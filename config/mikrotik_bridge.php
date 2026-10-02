<?php
require_once __DIR__ . '/env.php';

if (!function_exists('app_mikrotik_bridge_enabled')) {
    function app_mikrotik_bridge_enabled(): bool
    {
        return env_value('MIKROTIK_BRIDGE_ENABLED', 'false') === 'true';
    }
}

if (!function_exists('app_mikrotik_bridge_token')) {
    function app_mikrotik_bridge_token(): string
    {
        return (string) env_value('MIKROTIK_BRIDGE_TOKEN', env_value('CRON_TOKEN', ''));
    }
}

if (!function_exists('app_mikrotik_bridge_generate_api_key')) {
    function app_mikrotik_bridge_generate_api_key(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            return hash('sha256', uniqid('', true) . mt_rand() . microtime(true));
        }
    }
}

if (!function_exists('app_mikrotik_bridge_api_key_get')) {
    function app_mikrotik_bridge_api_key_get(int $tenantId, bool $autoCreate = true): string
    {
        if ($tenantId <= 0) {
            return '';
        }

        $key = trim((string) app_setting_get('mikrotik_bridge_api_key', '', $tenantId));
        if ($key !== '' || !$autoCreate) {
            return $key;
        }

        $key = app_mikrotik_bridge_generate_api_key();
        app_setting_set('mikrotik_bridge_api_key', $key, $tenantId);
        return $key;
    }
}

if (!function_exists('app_mikrotik_bridge_api_key_reset')) {
    function app_mikrotik_bridge_api_key_reset(int $tenantId): string
    {
        $key = app_mikrotik_bridge_generate_api_key();
        app_setting_set('mikrotik_bridge_api_key', $key, $tenantId);
        return $key;
    }
}

if (!function_exists('app_mikrotik_bridge_tenant_id_by_api_key')) {
    function app_mikrotik_bridge_tenant_id_by_api_key(string $apiKey): ?int
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '' || !isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            return null;
        }

        $stmt = $GLOBALS['pdo']->prepare("
            SELECT ts.tenant_id
            FROM tenant_settings ts
            JOIN tenants t ON t.id = ts.tenant_id
            WHERE ts.setting_key = 'mikrotik_bridge_api_key'
              AND ts.setting_value = ?
              AND t.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$apiKey]);
        $tenantId = (int) $stmt->fetchColumn();

        return $tenantId > 0 ? $tenantId : null;
    }
}

if (!function_exists('app_mikrotik_bridge_snapshot_key')) {
    function app_mikrotik_bridge_snapshot_key(int $tenantId): string
    {
        return 'mikrotik_bridge_snapshot:tenant:' . $tenantId;
    }
}

if (!function_exists('app_mikrotik_bridge_snapshot_get')) {
    function app_mikrotik_bridge_snapshot_get(int $tenantId, int $maxAgeSeconds = 180): ?array
    {
        $snapshot = app_cache_get(app_mikrotik_bridge_snapshot_key($tenantId), $maxAgeSeconds);
        return is_array($snapshot) ? $snapshot : null;
    }
}

if (!function_exists('app_mikrotik_bridge_snapshot_put')) {
    function app_mikrotik_bridge_snapshot_put(int $tenantId, array $payload): void
    {
        $payload['meta'] = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $payload['meta']['bridge'] = true;
        $payload['meta']['bridge_received_at'] = date(DATE_ATOM);
        app_cache_put(app_mikrotik_bridge_snapshot_key($tenantId), $payload);
    }
}

if (!function_exists('app_mikrotik_bridge_remote_lookup')) {
    function app_mikrotik_bridge_remote_lookup(int $tenantId, int $maxAgeSeconds = 180): array
    {
        $snapshot = app_mikrotik_bridge_snapshot_get($tenantId, $maxAgeSeconds);
        if (!is_array($snapshot)) {
            return [];
        }

        $inventory = is_array($snapshot['inventory'] ?? null) ? $snapshot['inventory'] : [];
        $lookup = [];

        foreach (($inventory['active'] ?? []) as $item) {
            $username = trim((string) ($item['name'] ?? ''));
            $address = trim((string) ($item['address'] ?? ''));
            if ($username !== '' && filter_var($address, FILTER_VALIDATE_IP)) {
                $lookup[$username] = $address;
            }
        }

        foreach (($inventory['secrets'] ?? []) as $item) {
            $username = trim((string) ($item['name'] ?? ''));
            $remoteAddress = trim((string) ($item['remote_address'] ?? ''));
            if ($username !== '' && !isset($lookup[$username]) && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
                $lookup[$username] = $remoteAddress;
            }
        }

        return $lookup;
    }
}

if (!function_exists('app_mikrotik_bridge_resolve_token')) {
    function app_mikrotik_bridge_resolve_token(): string
    {
        $headerToken = $_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        if ($headerToken !== '') {
            return (string) $headerToken;
        }

        return (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    }
}

if (!function_exists('app_mikrotik_bridge_resolve_api_key')) {
    function app_mikrotik_bridge_resolve_api_key(): string
    {
        $headerKey = $_SERVER['HTTP_X_BRIDGE_API_KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($headerKey !== '') {
            return (string) $headerKey;
        }

        return (string) ($_GET['api_key'] ?? $_GET['key'] ?? $_POST['api_key'] ?? $_POST['key'] ?? '');
    }
}
