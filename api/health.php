<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo->query('SELECT 1');
    echo json_encode([
        'success' => true,
        'status' => 'ok',
        'time' => date(DATE_ATOM),
        'environment' => env_value('APP_ENV', 'local')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 'degraded',
        'time' => date(DATE_ATOM)
    ]);
}
