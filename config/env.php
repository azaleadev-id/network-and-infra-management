<?php

if (!function_exists('env_load')) {
    function env_load($path = null) {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $path = $path ?: dirname(__DIR__) . '/.env';
        if (!is_file($path)) {
            $loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($value !== '' && (
                ($value[0] === '"' && substr($value, -1) === '"') ||
                ($value[0] === "'" && substr($value, -1) === "'")
            )) {
                $value = substr($value, 1, -1);
            }

            if ($name !== '' && getenv($name) === false) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }

        $loaded = true;
    }
}

if (!function_exists('env_value')) {
    function env_value($key, $default = null) {
        env_load();

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('app_storage_path')) {
    function app_storage_path($path = '') {
        $base = dirname(__DIR__) . '/storage';
        if ($path === '') {
            return $base;
        }

        return $base . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}

if (!function_exists('app_ensure_directory')) {
    function app_ensure_directory($path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

if (!function_exists('app_cache_get')) {
    function app_cache_get($key, $ttlSeconds) {
        $file = app_storage_path('cache/' . md5($key) . '.json');
        if (!is_file($file)) {
            return null;
        }

        if ((time() - filemtime($file)) > $ttlSeconds) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('app_cache_put')) {
    function app_cache_put($key, array $payload) {
        $dir = app_storage_path('cache');
        app_ensure_directory($dir);
        $file = $dir . '/' . md5($key) . '.json';
        file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('app_cache_forget')) {
    function app_cache_forget($key) {
        $file = app_storage_path('cache/' . md5($key) . '.json');
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

if (!function_exists('app_settings_path')) {
    function app_settings_path() {
        return app_storage_path('app_settings.json');
    }
}

if (!function_exists('app_file_settings_all')) {
    function app_file_settings_all(): array {
        $defaults = [
            'app_name' => env_value('APP_NAME', 'Nikonet'),
            'traffic_interface' => env_value('MIKROTIK_INTERFACE', 'ether1'),
            'billing_due_day' => 27,
        ];

        $file = app_settings_path();
        if (!is_file($file)) {
            return $defaults;
        }

        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return $defaults;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }
}

if (!function_exists('app_current_tenant_id_from_session')) {
    function app_current_tenant_id_from_session(): ?int {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $tenantId = $_SESSION['tenant_id'] ?? null;
        if ($tenantId === null) {
            return null;
        }

        $tenantId = (int) $tenantId;
        return $tenantId > 0 ? $tenantId : null;
    }
}

if (!function_exists('app_setting_defaults')) {
    function app_setting_defaults(): array {
        return [
            'app_name' => env_value('APP_NAME', 'Nikonet'),
            'traffic_interface' => env_value('MIKROTIK_INTERFACE', 'ether1'),
            'billing_due_day' => 27,
            'mikrotik_host' => env_value('MIKROTIK_HOST', ''),
            'mikrotik_user' => env_value('MIKROTIK_USER', ''),
            'mikrotik_pass' => env_value('MIKROTIK_PASS', ''),
            'mikrotik_backup_enabled' => 'false',
            'mikrotik_backup_interval_days' => '7',
            'mikrotik_backup_time' => '02:00',
            'mikrotik_backup_retention_days' => '30',
            'mikrotik_backup_last_at' => '',
            'mikrotik_backup_last_name' => '',
            'isolir_profile_name' => 'ISOLIR',
            'isolir_profile_rate_limit' => '64k/64k',
            'isolir_profile_comment' => 'Profile isolir otomatis dari aplikasi',
        ];
    }
}

if (!function_exists('app_tenant_settings_all')) {
    function app_tenant_settings_all(?int $tenantId = null): array {
        if (!isset($GLOBALS['app_tenant_settings_cache']) || !is_array($GLOBALS['app_tenant_settings_cache'])) {
            $GLOBALS['app_tenant_settings_cache'] = [];
        }

        $tenantId = $tenantId ?: app_current_tenant_id_from_session();
        if ($tenantId === null) {
            return [];
        }

        if (array_key_exists($tenantId, $GLOBALS['app_tenant_settings_cache'])) {
            return $GLOBALS['app_tenant_settings_cache'][$tenantId];
        }

        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            $GLOBALS['app_tenant_settings_cache'][$tenantId] = [];
            return $GLOBALS['app_tenant_settings_cache'][$tenantId];
        }

        $stmt = $GLOBALS['pdo']->prepare('SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }

        $GLOBALS['app_tenant_settings_cache'][$tenantId] = $settings;
        return $GLOBALS['app_tenant_settings_cache'][$tenantId];
    }
}

if (!function_exists('app_tenant_settings_all_reset')) {
    function app_tenant_settings_all_reset(?int $tenantId = null): void {
        if (!isset($GLOBALS['app_tenant_settings_cache']) || !is_array($GLOBALS['app_tenant_settings_cache'])) {
            $GLOBALS['app_tenant_settings_cache'] = [];
        }

        $tenantId = $tenantId ?: app_current_tenant_id_from_session();
        if ($tenantId === null) {
            $GLOBALS['app_tenant_settings_cache'] = [];
            return;
        }

        unset($GLOBALS['app_tenant_settings_cache'][$tenantId]);
    }
}

if (!function_exists('app_setting_get')) {
    function app_setting_get($key, $default = null, ?int $tenantId = null) {
        $tenantId = $tenantId ?: app_current_tenant_id_from_session();
        $settings = app_setting_defaults();

        if ($tenantId !== null) {
            $tenantSettings = app_tenant_settings_all($tenantId);
            if (array_key_exists($key, $tenantSettings) && $tenantSettings[$key] !== '') {
                return $tenantSettings[$key];
            }
        }

        $fileSettings = app_file_settings_all();
        if (array_key_exists($key, $fileSettings) && $fileSettings[$key] !== '') {
            return $fileSettings[$key];
        }

        return $settings[$key] ?? $default;
    }
}

if (!function_exists('app_setting_set')) {
    function app_setting_set($key, $value, ?int $tenantId = null) {
        $tenantId = $tenantId ?: app_current_tenant_id_from_session();

        if ($tenantId !== null && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $stmt = $GLOBALS['pdo']->prepare("
                INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute([$tenantId, $key, (string) $value]);
            app_tenant_settings_all_reset($tenantId);
            return;
        }

        $settings = app_file_settings_all();
        $settings[$key] = $value;

        $dir = app_storage_path();
        app_ensure_directory($dir);
        file_put_contents(app_settings_path(), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('app_parse_mikrotik_host')) {
    function app_parse_mikrotik_host(string $value): array {
        $raw = trim($value);
        if ($raw === '') {
            return [
                'host' => '',
                'socket_port' => null,
                'rest_scheme' => null,
                'rest_port' => null,
                'rest_path' => null,
            ];
        }

        $normalized = $raw;
        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $normalized)) {
            if (str_contains($normalized, '/') || str_contains($normalized, '?') || str_contains($normalized, '#')) {
                $normalized = 'https://' . ltrim($normalized, '/');
            } else {
                $normalized = 'tcp://' . $normalized;
            }
        }

        $parts = parse_url($normalized);
        if (!is_array($parts)) {
            return [
                'host' => $raw,
                'socket_port' => null,
                'rest_scheme' => null,
                'rest_port' => null,
                'rest_path' => null,
            ];
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $path = trim((string) ($parts['path'] ?? ''));

        if ($host === '' && !empty($parts['path']) && !str_contains((string) $parts['path'], '/')) {
            $host = trim((string) $parts['path']);
            $path = '';
        }

        return [
            'host' => $host !== '' ? $host : $raw,
            'socket_port' => $scheme === 'tcp' ? $port : null,
            'rest_scheme' => in_array($scheme, ['http', 'https'], true) ? $scheme : null,
            'rest_port' => in_array($scheme, ['http', 'https'], true) ? $port : null,
            'rest_path' => in_array($scheme, ['http', 'https'], true) && $path !== '' ? $path : null,
        ];
    }
}

if (!function_exists('app_mikrotik_config')) {
    function app_mikrotik_config(?int $tenantId = null): array {
        $rawHost = (string) app_setting_get('mikrotik_host', env_value('MIKROTIK_HOST', ''), $tenantId);
        $parsedHost = app_parse_mikrotik_host($rawHost);
        $restScheme = strtolower((string) env_value('MIKROTIK_REST_SCHEME', 'https'));
        $restPort = (int) env_value('MIKROTIK_REST_PORT', 443);
        $restPath = (string) env_value('MIKROTIK_REST_PATH', '/rest');

        return [
            'host' => $parsedHost['host'],
            'host_raw' => $rawHost,
            'user' => (string) app_setting_get('mikrotik_user', env_value('MIKROTIK_USER', ''), $tenantId),
            'pass' => (string) app_setting_get('mikrotik_pass', env_value('MIKROTIK_PASS', ''), $tenantId),
            'transport' => strtolower((string) env_value('MIKROTIK_TRANSPORT', 'socket')),
            'port' => $parsedHost['socket_port'] ?? (int) env_value('MIKROTIK_PORT', 8728),
            'timeout' => (int) env_value('MIKROTIK_TIMEOUT', 1),
            'attempts' => (int) env_value('MIKROTIK_ATTEMPTS', 1),
            'delay' => (int) env_value('MIKROTIK_DELAY', 0),
            'rest_scheme' => $parsedHost['rest_scheme'] ?? $restScheme,
            'rest_port' => $parsedHost['rest_port'] ?? $restPort,
            'rest_path' => $parsedHost['rest_path'] ?? $restPath,
            'rest_allow_insecure' => env_value('MIKROTIK_REST_ALLOW_INSECURE', 'true') === 'true',
            'interface' => (string) app_setting_get('traffic_interface', env_value('MIKROTIK_INTERFACE', 'ether1'), $tenantId),
            'sync_on_user_create' => env_value('MIKROTIK_SYNC_ON_USER_CREATE', 'true') === 'true',
        ];
    }
}
