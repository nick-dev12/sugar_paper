<?php
/**
 * Cache des colonnes (information_schema) pour migrations optionnelles.
 */
function db_table_has_column($table, $column) {
    global $db;
    static $cache = [];
    if (!$db) {
        return false;
    }
    $k = $table . '.' . $column;
    if (array_key_exists($k, $cache)) {
        return $cache[$k];
    }
    try {
        $st = $db->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
        ");
        $st->execute(['t' => $table, 'c' => $column]);
        $cache[$k] = (int) $st->fetchColumn() > 0;
    } catch (PDOException $e) {
        $cache[$k] = false;
    }
    return $cache[$k];
}
