<?php
require_once __DIR__ . '/env.php';

$host = env_value('DB_HOST', 'localhost');
$db = env_value('DB_NAME', 'rt_rw_net');
$user = env_value('DB_USER', 'root');
$pass = env_value('DB_PASS', '');
$charset = env_value('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    $message = env_value('APP_DEBUG', 'false') === 'true' ? $e->getMessage() : 'Database connection failed';
    throw new PDOException($message, (int)$e->getCode());
}
