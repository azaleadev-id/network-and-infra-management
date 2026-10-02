<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/mikrotik_client.php';

if (!function_exists('mikrotik_isolir_normalize_rows')) {
    function mikrotik_isolir_normalize_rows($result): array
    {
        if (!is_array($result)) {
            return [];
        }

        if (isset($result['!trap']) || isset($result['!fatal'])) {
            return [];
        }

        return array_values(array_filter($result, static fn ($item) => is_array($item)));
    }
}

if (!function_exists('mikrotik_isolir_extract_error')) {
    function mikrotik_isolir_extract_error($result): ?string
    {
        if (!is_array($result)) {
            return null;
        }

        $messages = [];
        foreach (['!trap', '!fatal'] as $bucket) {
            if (!isset($result[$bucket]) || !is_array($result[$bucket])) {
                continue;
            }

            foreach ($result[$bucket] as $entry) {
                if (is_array($entry) && !empty($entry['message'])) {
                    $messages[] = trim((string) $entry['message']);
                }
            }
        }

        return $messages === [] ? null : implode('; ', array_unique($messages));
    }
}

if (!function_exists('mikrotik_isolir_settings')) {
    function mikrotik_isolir_settings(?int $tenantId = null): array
    {
        return [
            'profile_name' => trim((string) app_setting_get('isolir_profile_name', 'ISOLIR', $tenantId)),
            'rate_limit' => trim((string) app_setting_get('isolir_profile_rate_limit', '64k/64k', $tenantId)),
            'comment' => trim((string) app_setting_get('isolir_profile_comment', 'Profile isolir otomatis dari aplikasi', $tenantId)),
        ];
    }
}

if (!function_exists('mikrotik_isolir_connect')) {
    function mikrotik_isolir_connect(?array $mk_config = null): MikroTikClient
    {
        $mk_config = $mk_config ?? require __DIR__ . '/mikrotik.php';

        if (($mk_config['host'] ?? '') === '' || ($mk_config['user'] ?? '') === '') {
            throw new RuntimeException('Konfigurasi koneksi MikroTik belum lengkap.');
        }

        $api = new MikroTikClient();
        $api->transport = $mk_config['transport'] ?? 'socket';
        $api->port = $mk_config['port'] ?? 8728;
        $api->timeout = $mk_config['timeout'] ?? 2;
        $api->attempts = $mk_config['attempts'] ?? 1;
        $api->delay = $mk_config['delay'] ?? 0;
        $api->restScheme = $mk_config['rest_scheme'] ?? 'https';
        $api->restPort = $mk_config['rest_port'] ?? 443;
        $api->restPath = $mk_config['rest_path'] ?? '/rest';
        $api->restAllowInsecure = (bool) ($mk_config['rest_allow_insecure'] ?? true);

        if (!$api->connect($mk_config['host'], $mk_config['user'], $mk_config['pass'])) {
            $detail = !empty($api->error_str) ? ' Detail: ' . $api->error_str : '';
            throw new RuntimeException('Koneksi ke MikroTik gagal.' . $detail);
        }

        return $api;
    }
}

if (!function_exists('mikrotik_isolir_profile_by_name')) {
    function mikrotik_isolir_profile_by_name(MikroTikClient $api, string $profileName): ?array
    {
        $profileName = trim($profileName);
        if ($profileName === '') {
            return null;
        }

        $profiles = mikrotik_isolir_normalize_rows($api->comm('/ppp/profile/print', [
            '?name' => $profileName
        ]));

        return $profiles[0] ?? null;
    }
}

if (!function_exists('mikrotik_isolir_profile_exists')) {
    function mikrotik_isolir_profile_exists(MikroTikClient $api, string $profileName): bool
    {
        return mikrotik_isolir_profile_by_name($api, $profileName) !== null;
    }
}

if (!function_exists('mikrotik_isolir_secret_by_name')) {
    function mikrotik_isolir_secret_by_name(MikroTikClient $api, string $username): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $secrets = mikrotik_isolir_normalize_rows($api->comm('/ppp/secret/print', [
            '?name' => $username
        ]));

        return $secrets[0] ?? null;
    }
}

if (!function_exists('mikrotik_isolir_remove_active_sessions')) {
    function mikrotik_isolir_remove_active_sessions(MikroTikClient $api, string $username): int
    {
        $username = trim($username);
        if ($username === '') {
            return 0;
        }

        $sessions = mikrotik_isolir_normalize_rows($api->comm('/ppp/active/print', [
            '?name' => $username
        ]));

        $removed = 0;
        foreach ($sessions as $session) {
            $sessionId = trim((string) ($session['.id'] ?? ''));
            if ($sessionId === '') {
                continue;
            }

            $result = $api->comm('/ppp/active/remove', [
                'numbers' => $sessionId
            ]);
            $error = mikrotik_isolir_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            $removed++;
        }

        return $removed;
    }
}

if (!function_exists('mikrotik_isolir_ensure_profile')) {
    function mikrotik_isolir_ensure_profile(MikroTikClient $api, array $settings): array
    {
        $profileName = trim((string) ($settings['profile_name'] ?? ''));
        if ($profileName === '') {
            throw new RuntimeException('Nama profile isolir wajib diisi.');
        }

        $existing = mikrotik_isolir_profile_by_name($api, $profileName);
        if ($existing !== null) {
            return [
                'created' => false,
                'profile_name' => $profileName
            ];
        }

        $payload = [
            'name' => $profileName
        ];

        $rateLimit = trim((string) ($settings['rate_limit'] ?? ''));
        $comment = trim((string) ($settings['comment'] ?? ''));

        if ($rateLimit !== '') {
            $payload['rate-limit'] = $rateLimit;
        }
        if ($comment !== '') {
            $payload['comment'] = $comment;
        }

        $result = $api->comm('/ppp/profile/add', $payload);
        $error = mikrotik_isolir_extract_error($result);
        if ($error !== null) {
            throw new RuntimeException($error);
        }

        return [
            'created' => true,
            'profile_name' => $profileName
        ];
    }
}

if (!function_exists('mikrotik_isolir_apply_to_user')) {
    function mikrotik_isolir_apply_to_user(MikroTikClient $api, string $username, array $settings): array
    {
        $profileName = trim((string) ($settings['profile_name'] ?? ''));
        if ($profileName === '') {
            throw new RuntimeException('Profile isolir belum diatur.');
        }

        if (!mikrotik_isolir_profile_exists($api, $profileName)) {
            throw new RuntimeException("Profile isolir {$profileName} belum ada di MikroTik.");
        }

        $secret = mikrotik_isolir_secret_by_name($api, $username);
        if ($secret === null || empty($secret['.id'])) {
            throw new RuntimeException("PPP secret {$username} tidak ditemukan di MikroTik.");
        }

        $currentProfile = trim((string) ($secret['profile'] ?? 'default'));
        $alreadyIsolated = strcasecmp($currentProfile, $profileName) === 0;

        if (!$alreadyIsolated) {
            $result = $api->comm('/ppp/secret/set', [
                '.id' => $secret['.id'],
                'profile' => $profileName
            ]);
            $error = mikrotik_isolir_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }
        }

        $removedActiveCount = mikrotik_isolir_remove_active_sessions($api, $username);

        return [
            'already_isolated' => $alreadyIsolated,
            'previous_profile' => $currentProfile !== '' ? $currentProfile : 'default',
            'isolir_profile' => $profileName,
            'removed_active_count' => $removedActiveCount
        ];
    }
}

if (!function_exists('mikrotik_isolir_restore_user')) {
    function mikrotik_isolir_restore_user(MikroTikClient $api, string $username, ?string $previousProfile): array
    {
        $secret = mikrotik_isolir_secret_by_name($api, $username);
        if ($secret === null || empty($secret['.id'])) {
            throw new RuntimeException("PPP secret {$username} tidak ditemukan di MikroTik.");
        }

        $restoreProfile = trim((string) $previousProfile);
        if ($restoreProfile === '') {
            $restoreProfile = 'default';
        }

        $currentProfile = trim((string) ($secret['profile'] ?? 'default'));
        if (strcasecmp($currentProfile, $restoreProfile) !== 0) {
            $result = $api->comm('/ppp/secret/set', [
                '.id' => $secret['.id'],
                'profile' => $restoreProfile
            ]);
            $error = mikrotik_isolir_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }
        }

        if (($secret['disabled'] ?? 'false') === 'true') {
            $result = $api->comm('/ppp/secret/enable', [
                'numbers' => $secret['.id']
            ]);
            $error = mikrotik_isolir_extract_error($result);
            if ($error !== null) {
                throw new RuntimeException($error);
            }
        }

        $removedActiveCount = mikrotik_isolir_remove_active_sessions($api, $username);

        return [
            'restored_profile' => $restoreProfile,
            'removed_active_count' => $removedActiveCount
        ];
    }
}

if (!function_exists('mikrotik_isolir_restore_retry_config')) {
    function mikrotik_isolir_restore_retry_config(?array $mk_config = null): array
    {
        $mk_config = $mk_config ?? require __DIR__ . '/mikrotik.php';

        return [
            'attempts' => max(1, (int) ($mk_config['restore_attempts'] ?? $mk_config['attempts'] ?? 1)),
            'delay' => max(0, (int) ($mk_config['restore_delay'] ?? $mk_config['delay'] ?? 0)),
        ];
    }
}

if (!function_exists('mikrotik_isolir_restore_user_with_api_retry')) {
    function mikrotik_isolir_restore_user_with_api_retry(MikroTikClient $api, string $username, ?string $previousProfile, ?array $mk_config = null): array
    {
        $retry = mikrotik_isolir_restore_retry_config($mk_config);
        $errors = [];

        for ($attempt = 1; $attempt <= $retry['attempts']; $attempt++) {
            try {
                $result = mikrotik_isolir_restore_user($api, $username, $previousProfile);
                $result['attempt'] = $attempt;
                $result['attempts'] = $retry['attempts'];
                return $result;
            } catch (Throwable $e) {
                $errors[] = trim($e->getMessage()) !== ''
                    ? trim($e->getMessage())
                    : 'Buka isolir gagal tanpa detail.';

                if ($attempt < $retry['attempts'] && $retry['delay'] > 0) {
                    sleep($retry['delay']);
                }
            }
        }

        $message = implode('; ', array_unique(array_filter($errors)));
        if ($retry['attempts'] > 1) {
            $message .= ($message !== '' ? ' ' : '') . "Sudah dicoba {$retry['attempts']} kali.";
        }

        throw new RuntimeException($message !== '' ? $message : 'Buka isolir MikroTik gagal.');
    }
}

if (!function_exists('mikrotik_isolir_restore_user_with_retry')) {
    function mikrotik_isolir_restore_user_with_retry(string $username, ?string $previousProfile, ?array $mk_config = null): array
    {
        $mk_config = $mk_config ?? require __DIR__ . '/mikrotik.php';
        $retry = mikrotik_isolir_restore_retry_config($mk_config);
        $errors = [];

        for ($attempt = 1; $attempt <= $retry['attempts']; $attempt++) {
            $api = null;

            try {
                $api = mikrotik_isolir_connect($mk_config);
                $result = mikrotik_isolir_restore_user($api, $username, $previousProfile);
                $result['attempt'] = $attempt;
                $result['attempts'] = $retry['attempts'];
                return $result;
            } catch (Throwable $e) {
                $errors[] = trim($e->getMessage()) !== ''
                    ? trim($e->getMessage())
                    : 'Buka isolir gagal tanpa detail.';
            } finally {
                if ($api instanceof MikroTikClient) {
                    $api->disconnect();
                }
            }

            if ($attempt < $retry['attempts'] && $retry['delay'] > 0) {
                sleep($retry['delay']);
            }
        }

        $message = implode('; ', array_unique(array_filter($errors)));
        if ($retry['attempts'] > 1) {
            $message .= ($message !== '' ? ' ' : '') . "Sudah dicoba {$retry['attempts']} kali.";
        }

        throw new RuntimeException($message !== '' ? $message : 'Buka isolir MikroTik gagal.');
    }
}

if (!function_exists('mikrotik_isolir_setup_status')) {
    function mikrotik_isolir_setup_status(?int $tenantId = null): array
    {
        $settings = mikrotik_isolir_settings($tenantId);

        try {
            $api = mikrotik_isolir_connect(app_mikrotik_config($tenantId));
            try {
                $exists = mikrotik_isolir_profile_exists($api, $settings['profile_name']);
                return [
                    'connected' => true,
                    'profile_exists' => $exists,
                    'profile_name' => $settings['profile_name'],
                    'rate_limit' => $settings['rate_limit'],
                    'comment' => $settings['comment'],
                    'message' => $exists
                        ? "Profile isolir {$settings['profile_name']} sudah tersedia di MikroTik."
                        : "Profile isolir {$settings['profile_name']} belum ada di MikroTik."
                ];
            } finally {
                $api->disconnect();
            }
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'profile_exists' => false,
                'profile_name' => $settings['profile_name'],
                'rate_limit' => $settings['rate_limit'],
                'comment' => $settings['comment'],
                'message' => $e->getMessage()
            ];
        }
    }
}
