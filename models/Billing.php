<?php
class Billing {
    public $id;
    public $user_id;
    public $status;
    public $payment_status;
    public $jatuh_tempo;
    public $jumlah;
    public $paid_at;
    public $catatan;
    public $last_update;

    public static function all($pdo) {
        $stmt = $pdo->query('SELECT * FROM billing');
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'Billing');
    }
    // ...add more methods as needed...
}
