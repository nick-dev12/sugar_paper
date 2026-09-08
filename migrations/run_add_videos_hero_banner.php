<?php
/**
 * Migration: colonne hero_banner sur videos (bannière accueil)
 * Exécuter: php migrations/run_add_videos_hero_banner.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    if (!$db instanceof PDO) {
        echo "Connexion a la base indisponible depuis le PHP CLI.\n";
        exit(1);
    }

    $table_stmt = $db->query("SHOW TABLES LIKE 'videos'");
    if (!$table_stmt || !$table_stmt->fetchColumn()) {
        echo "La table videos n'existe pas.\n";
        exit(1);
    }

    $column_stmt = $db->query("SHOW COLUMNS FROM videos LIKE 'hero_banner'");
    if ($column_stmt && $column_stmt->fetchColumn()) {
        echo "Colonne hero_banner deja presente.\n";
        exit(0);
    }

    $db->exec("
        ALTER TABLE videos
        ADD COLUMN hero_banner TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '1 = video banniere accueil (une seule a la fois)'
            AFTER statut,
        ADD INDEX idx_hero_banner (hero_banner)
    ");

    echo "Colonne hero_banner ajoutee a videos.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
