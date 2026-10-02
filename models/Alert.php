<?php
class Alert {
    public $id;
    public $tipe;
    public $target_id;
    public $status;
    public $waktu;
    public $pesan;

    public static function all($pdo) {
        $stmt = $pdo->query('SELECT * FROM alerts');
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'Alert');
    }
    // ...add more methods as needed...
}
