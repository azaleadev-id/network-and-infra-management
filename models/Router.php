<?php
class Router {
    public $id;
    public $nama;
    public $ip;
    public $user;
    public $pass;
    public $port;
    public $lokasi_id;

    public static function all($pdo) {
        $stmt = $pdo->query('SELECT * FROM router');
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'Router');
    }
    // ...add more methods as needed...
}
