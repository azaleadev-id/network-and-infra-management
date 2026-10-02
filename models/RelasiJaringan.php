<?php
class RelasiJaringan {
    public $id;
    public $user_id;
    public $odp_id;
    public $odc_id;
    public $server_id;

    public static function all($pdo) {
        $stmt = $pdo->query('SELECT * FROM relasi_jaringan');
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'RelasiJaringan');
    }
    // ...add more methods as needed...
}
