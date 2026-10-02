<?php
require_once __DIR__ . '/routeros_api.class.php';

class MikroTikClient
{
    public bool $debug = false;
    public bool $connected = false;
    public int $port = 8728;
    public int $timeout = 3;
    public int $attempts = 1;
    public int $delay = 0;
    public ?int $error_no = null;
    public ?string $error_str = null;
    public string $transport = 'socket';
    public string $restScheme = 'https';
    public int $restPort = 443;
    public string $restPath = '/rest';
    public bool $restAllowInsecure = true;

    private ?RouterosAPI $socketClient = null;
    private ?string $host = null;
    private ?string $login = null;
    private ?string $password = null;
    private ?string $activeTransport = null;

    public function connect($ip, $login, $password): bool
    {
        $this->host = (string) $ip;
        $this->login = (string) $login;
        $this->password = (string) $password;
        $this->error_no = null;
        $this->error_str = null;
        $this->connected = false;
        $this->activeTransport = null;

        $transport = strtolower(trim($this->transport));
        if ($transport === '') {
            $transport = 'socket';
        }

        if ($transport === 'rest') {
            return $this->connectRest();
        }

        if ($transport === 'auto') {
            if ($this->connectSocket()) {
                return true;
            }

            return $this->connectRest();
        }

        return $this->connectSocket();
    }

    public function disconnect(): void
    {
        $this->connected = false;
        if ($this->socketClient instanceof RouterosAPI) {
            $this->socketClient->disconnect();
        }
        $this->socketClient = null;
        $this->activeTransport = null;
    }

    public function comm($command, $arr = [])
    {
        if (!$this->connected) {
            return $this->trapResponse('Koneksi MikroTik belum aktif.');
        }

        if ($this->activeTransport === 'rest') {
            return $this->commRest((string) $command, is_array($arr) ? $arr : []);
        }

        if (!($this->socketClient instanceof RouterosAPI)) {
            return $this->trapResponse('Socket MikroTik tidak tersedia.');
        }

        return $this->socketClient->comm((string) $command, is_array($arr) ? $arr : []);
    }

    public function activeTransport(): ?string
    {
        return $this->activeTransport;
    }

    private function connectSocket(): bool
    {
        $client = new RouterosAPI();
        $client->debug = $this->debug;
        $client->port = $this->port;
        $client->timeout = $this->timeout;
        $client->attempts = $this->attempts;
        $client->delay = $this->delay;

        if (!$client->connect((string) $this->host, (string) $this->login, (string) $this->password)) {
            $this->error_no = $client->error_no ?? null;
            $this->error_str = $client->error_str ?? 'Socket connection failed';
            $target = (string) $this->host . ':' . (string) $this->port;
            if ($this->error_str !== '') {
                $this->error_str .= ' (' . $target . ')';
            } else {
                $this->error_str = 'Socket connection failed (' . $target . ')';
            }
            return false;
        }

        $this->socketClient = $client;
        $this->connected = true;
        $this->activeTransport = 'socket';
        return true;
    }

    private function connectRest(): bool
    {
        if (!function_exists('curl_init')) {
            $this->error_str = 'PHP curl extension tidak tersedia untuk REST MikroTik.';
            return false;
        }

        $result = $this->restRequest('POST', 'system/resource/print', new stdClass());
        if (($result['success'] ?? false) !== true) {
            $this->error_no = (int) ($result['status'] ?? 0);
            $this->error_str = (string) ($result['message'] ?? 'REST connection failed');
            return false;
        }

        $this->connected = true;
        $this->activeTransport = 'rest';
        return true;
    }

    private function commRest(string $command, array $params)
    {
        $parts = array_values(array_filter(explode('/', trim($command, '/')), static fn ($part) => $part !== ''));
        if ($parts === []) {
            return $this->trapResponse('Command MikroTik tidak valid.');
        }

        $action = array_pop($parts);
        $menuPath = implode('/', $parts);

        try {
            switch ($action) {
                case 'print':
                    return $this->restPrint($menuPath, $params);
                case 'add':
                    return $this->restAdd($menuPath, $params);
                case 'set':
                    return $this->restSet($menuPath, $params);
                case 'remove':
                    return $this->restRemove($menuPath, $params);
                case 'enable':
                case 'disable':
                    return $this->restPostAction($menuPath . '/' . $action, $params);
                default:
                    return $this->restPostAction($menuPath . '/' . $action, $params);
            }
        } catch (Throwable $e) {
            return $this->trapResponse($e->getMessage());
        }
    }

    private function restPrint(string $menuPath, array $params): array
    {
        $payload = [];
        $query = [];

        foreach ($params as $key => $value) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }

            if ($key[0] === '?' || $key[0] === '~') {
                $query[] = $key . '=' . $value;
                continue;
            }

            $payload[$key] = $value;
        }

        if ($query !== []) {
            $payload['.query'] = $query;
        }

        $result = $this->restRequest('POST', $menuPath . '/print', $payload === [] ? new stdClass() : $payload);
        if (($result['success'] ?? false) !== true) {
            return $this->trapResponse((string) ($result['message'] ?? 'REST print gagal.'));
        }

        return $this->normalizeRestResponse($result['body'] ?? []);
    }

    private function restAdd(string $menuPath, array $params)
    {
        $result = $this->restRequest('PUT', $menuPath, $params);
        if (($result['success'] ?? false) !== true) {
            return $this->trapResponse((string) ($result['message'] ?? 'REST add gagal.'));
        }

        return $this->normalizeRestResponse($result['body'] ?? []);
    }

    private function restSet(string $menuPath, array $params)
    {
        $id = (string) ($params['.id'] ?? $params['numbers'] ?? '');
        if ($id === '') {
            return $this->trapResponse('ID MikroTik untuk update tidak ditemukan.');
        }

        unset($params['.id'], $params['numbers']);
        $result = $this->restRequest('PATCH', $menuPath . '/' . rawurlencode($id), $params);
        if (($result['success'] ?? false) !== true) {
            return $this->trapResponse((string) ($result['message'] ?? 'REST set gagal.'));
        }

        return $this->normalizeRestResponse($result['body'] ?? []);
    }

    private function restRemove(string $menuPath, array $params)
    {
        $id = (string) ($params['numbers'] ?? $params['.id'] ?? '');
        if ($id === '') {
            return $this->trapResponse('ID MikroTik untuk hapus tidak ditemukan.');
        }

        $result = $this->restRequest('DELETE', $menuPath . '/' . rawurlencode($id), null);
        if (($result['success'] ?? false) !== true) {
            return $this->trapResponse((string) ($result['message'] ?? 'REST remove gagal.'));
        }

        return [];
    }

    private function restPostAction(string $path, array $params)
    {
        $result = $this->restRequest('POST', $path, $params === [] ? new stdClass() : $params);
        if (($result['success'] ?? false) !== true) {
            return $this->trapResponse((string) ($result['message'] ?? 'REST action gagal.'));
        }

        return $this->normalizeRestResponse($result['body'] ?? []);
    }

    private function restRequest(string $method, string $path, $payload): array
    {
        $basePath = '/' . trim($this->restPath, '/');
        $relativePath = trim($path, '/');
        $url = sprintf(
            '%s://%s:%d%s%s',
            $this->restScheme,
            $this->host,
            $this->restPort,
            $basePath,
            $relativePath !== '' ? '/' . $relativePath : ''
        );

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'success' => false,
                'status' => 0,
                'message' => 'Gagal inisialisasi cURL MikroTik.'
            ];
        }

        $headers = ['Accept: application/json'];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->login . ':' . $this->password,
            CURLOPT_CONNECTTIMEOUT => max(1, $this->timeout),
            CURLOPT_TIMEOUT => max(3, $this->timeout + 2),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => !$this->restAllowInsecure,
            CURLOPT_SSL_VERIFYHOST => $this->restAllowInsecure ? 0 : 2,
        ]);

        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded === false ? '{}' : $encoded);
        }

        $raw = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($errno !== 0) {
            return [
                'success' => false,
                'status' => $status,
                'message' => $error !== '' ? $error : 'Koneksi REST MikroTik gagal.'
            ];
        }

        $decoded = null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
        }

        if ($status >= 400) {
            $message = 'REST MikroTik gagal.';
            if (is_array($decoded) && !empty($decoded['message'])) {
                $message = (string) $decoded['message'];
                if (!empty($decoded['detail'])) {
                    $message .= ' Detail: ' . $decoded['detail'];
                }
            } elseif (is_string($raw) && trim($raw) !== '') {
                $message = trim($raw);
            }

            return [
                'success' => false,
                'status' => $status,
                'message' => $message
            ];
        }

        return [
            'success' => true,
            'status' => $status,
            'body' => $decoded
        ];
    }

    private function normalizeRestResponse($body)
    {
        if ($body === null || $body === '') {
            return [];
        }

        if (is_array($body)) {
            $isList = array_keys($body) === range(0, count($body) - 1);
            return $isList ? $body : [$body];
        }

        return [];
    }

    private function trapResponse(string $message): array
    {
        return [
            '!trap' => [
                ['message' => $message]
            ]
        ];
    }
}
