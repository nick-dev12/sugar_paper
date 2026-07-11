<?php
/**
 * Migration : ajout du rôle contable sur admin.role
 */
require_once __DIR__ . '/../conn/conn.php';

$sqlFile = __DIR__ . '/alter_admin_role_contable.sql';
if (!is_readable($sqlFile)) {
    fwrite(STDERR, "Fichier SQL introuvable.\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
try {
    $db->exec($sql);
    echo "Migration contable OK.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
