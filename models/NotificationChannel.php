<?php
class NotificationChannel {
    public $id;
    public $tipe;
    public $target;
    public $aktif;

    public static function all($pdo) {
        $stmt = $pdo->query('SELECT * FROM notification_channel');
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'NotificationChannel');
    }
    // ...add more methods as needed...
}
