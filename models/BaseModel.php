<?php
class BaseModel {
    protected static $table;
    public static function find($pdo, $id) {
        $stmt = $pdo->prepare('SELECT * FROM ' . static::$table . ' WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchObject(get_called_class());
    }
    public static function all($pdo) {
        $stmt = $pdo->query('SELECT * FROM ' . static::$table);
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }
    public function save($pdo) {
        $props = get_object_vars($this);
        unset($props['id']);
        $columns = array_keys($props);
        $values = array_values($props);
        if (isset($this->id)) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $columns));
            $stmt = $pdo->prepare('UPDATE ' . static::$table . ' SET ' . $set . ' WHERE id = ?');
            $stmt->execute([...$values, $this->id]);
        } else {
            $cols = implode(',', $columns);
            $qs = implode(',', array_fill(0, count($columns), '?'));
            $stmt = $pdo->prepare('INSERT INTO ' . static::$table . " ($cols) VALUES ($qs)");
            $stmt->execute($values);
            $this->id = $pdo->lastInsertId();
        }
    }
    public function delete($pdo) {
        if (isset($this->id)) {
            $stmt = $pdo->prepare('DELETE FROM ' . static::$table . ' WHERE id = ?');
            $stmt->execute([$this->id]);
        }
    }
}
