<?php
class User {
    public $id;
    public $tenant_id;
    public $username;
    public $nama;
    public $phone;
    public $remote_ip;
    public $koordinat;
    public $status;
    public $expired;
    public $lokasi_id;
    public $paket_id;
    public $created_at;

    public static function findById($pdo, $id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchObject('User');
    }
    public static function findByUsername($pdo, $username) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetchObject('User');
    }
    public static function all($pdo) {
        $stmt = $pdo->query('SELECT * FROM users');
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'User');
    }
    // ...add more methods as needed...
}
