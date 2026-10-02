<?php
require_once __DIR__ . '/BaseModel.php';
class Lokasi extends BaseModel {
    protected static $table = 'lokasi';
    public $id;
    public $nama;
    public $tipe;
    public $parent_id;
    public $koordinat;
    public $keterangan;

    public static function findByType($pdo, $tipe) {
        $stmt = $pdo->prepare('SELECT * FROM lokasi WHERE tipe = ?');
        $stmt->execute([$tipe]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'Lokasi');
    }
    public static function findChildren($pdo, $parent_id) {
        $stmt = $pdo->prepare('SELECT * FROM lokasi WHERE parent_id = ?');
        $stmt->execute([$parent_id]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'Lokasi');
    }
    public static function search($pdo, $keyword) {
        $stmt = $pdo->prepare("SELECT * FROM lokasi WHERE nama LIKE ? OR keterangan LIKE ?");
        $kw = "%$keyword%";
        $stmt->execute([$kw, $kw]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, 'Lokasi');
    }
}
