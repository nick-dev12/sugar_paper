<?php
/**
 * Migration: colonne slider_carousel sur videos (carrousel accueil)
 * Exécuter: php migrations/run_add_videos_slider_carousel.php
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

    $column_stmt = $db->query("SHOW COLUMNS FROM videos LIKE 'slider_carousel'");
    if ($column_stmt && $column_stmt->fetchColumn()) {
        echo "Colonne slider_carousel deja presente.\n";
        exit(0);
    }

    $db->exec("
        ALTER TABLE videos
        ADD COLUMN slider_carousel TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '1 = afficher dans le carrousel slider accueil'
            AFTER hero_banner,
        ADD INDEX idx_slider_carousel (slider_carousel)
    ");

    echo "Colonne slider_carousel ajoutee a videos.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
