<?php
class AuditLog {
    public $id;
    public $user_id;
    public $action;
    public $detail;
    public $waktu;

    public static function log($pdo, $user_id, $action, $detail) {
        $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, detail) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $action, $detail]);
    }
    public static function all($pdo) {
        $stmt = $pdo->query('SELECT * FROM audit_log');
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'AuditLog');
    }
}
