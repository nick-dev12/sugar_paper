<?php
/**
 * Migration : table images carrousel section mise en avant
 * Exécuter: php migrations/run_add_trending_images_table.php
 */

require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    echo "Connexion BDD indisponible.\n";
    exit(1);
}

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS trending_images (
            id INT(11) NOT NULL AUTO_INCREMENT,
            image VARCHAR(255) NOT NULL,
            ordre INT(11) NOT NULL DEFAULT 0,
            date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_trending_images_ordre (ordre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table trending_images OK.\n";

    $count_stmt = $db->query('SELECT COUNT(*) FROM trending_images');
    $count = $count_stmt ? (int) $count_stmt->fetchColumn() : 0;
    if ($count === 0) {
        $config_stmt = $db->query("SELECT image FROM trending_config ORDER BY id DESC LIMIT 1");
        $config = $config_stmt ? $config_stmt->fetch(PDO::FETCH_ASSOC) : false;
        $legacy_image = trim((string) ($config['image'] ?? ''));
        if ($legacy_image !== '' && $legacy_image !== 'speaker.png') {
            $insert = $db->prepare('INSERT INTO trending_images (image, ordre) VALUES (:image, 1)');
            $insert->execute(['image' => $legacy_image]);
            echo "Image legacy importee dans trending_images.\n";
        }
    }

    exit(0);
} catch (PDOException $e) {
    echo 'Erreur migration : ' . $e->getMessage() . "\n";
    exit(1);
}
