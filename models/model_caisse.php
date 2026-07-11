<?php
/**
 * Stub caisse — module caisse non installé sur ce site.
 * Permet au module activité admin de fonctionner sans erreur fatale.
 */
require_once __DIR__ . '/../conn/conn.php';

function caisse_tables_exist()
{
    global $db;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'caisse_ventes'");
        $cache = $stmt && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $cache = false;
    }
    return $cache;
}
