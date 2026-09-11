<?php
/**
 * Migration : description texte pour la section mise en avant
 * Exécuter: php migrations/run_add_trending_description.php
 */

require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    echo "Connexion BDD indisponible.\n";
    exit(1);
}

try {
    $column_stmt = $db->query("SHOW COLUMNS FROM trending_config LIKE 'description'");
    if ($column_stmt && $column_stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "La colonne description existe déjà.\n";
        exit(0);
    }

    $db->exec("
        ALTER TABLE trending_config
        ADD COLUMN description TEXT NULL DEFAULT NULL
        AFTER titre
    ");

    echo "Colonne description ajoutée à trending_config.\n";
    exit(0);
} catch (PDOException $e) {
    echo "Erreur migration : " . $e->getMessage() . "\n";
    exit(1);
}
