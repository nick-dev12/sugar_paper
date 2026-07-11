<?php
/**
 * Table categories_generales (rayons plateforme) + colonne categories.categorie_generale_id.
 * Usage : php migrations/run_migrate_categories_generales_table.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function col_exists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function table_exists(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function safe_exec(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT SQL : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration categories_generales…\n";

if (!table_exists($db, 'categories_generales')) {
    safe_exec($db, "
        CREATE TABLE `categories_generales` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nom` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `icone` VARCHAR(80) NULL DEFAULT NULL,
            `sort_ordre` INT(11) NOT NULL DEFAULT 0,
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_cg_nom` (`nom`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table categories_generales\n";
}

$rayons = [
    ['nom' => 'Femme', 'description' => 'Mode, accessoires et univers féminin.', 'icone' => 'fa-solid fa-person-dress', 'sort' => 10],
    ['nom' => 'Homme', 'description' => 'Mode, accessoires et univers masculin.', 'icone' => 'fa-solid fa-person', 'sort' => 20],
    ['nom' => 'Bébé & puériculture', 'description' => 'Puériculture, layette, premier âge.', 'icone' => 'fa-solid fa-baby', 'sort' => 30],
    ['nom' => 'Jouets & jeux', 'description' => 'Jouets, jeux de société, jeux vidéo.', 'icone' => 'fa-solid fa-puzzle-piece', 'sort' => 40],
    ['nom' => 'Électroménager', 'description' => 'Gros et petit électroménager.', 'icone' => 'fa-solid fa-plug', 'sort' => 50],
    ['nom' => 'Électronique & high-tech', 'description' => 'TV, audio, photo, multimédia.', 'icone' => 'fa-solid fa-tv', 'sort' => 60],
    ['nom' => 'Informatique & bureautique', 'description' => 'PC, périphériques, fournitures de bureau.', 'icone' => 'fa-solid fa-laptop', 'sort' => 70],
    ['nom' => 'Téléphonie & accessoires', 'description' => 'Smartphones, accessoires.', 'icone' => 'fa-solid fa-mobile-screen-button', 'sort' => 80],
    ['nom' => 'Maison & jardin', 'description' => 'Décoration, literie, jardinage.', 'icone' => 'fa-solid fa-house-chimney', 'sort' => 90],
    ['nom' => 'Auto & moto', 'description' => 'Entretien, accessoires, équipements.', 'icone' => 'fa-solid fa-car', 'sort' => 100],
    ['nom' => 'Sport & loisirs', 'description' => 'Fitness, outdoor, hobbies.', 'icone' => 'fa-solid fa-person-running', 'sort' => 110],
    ['nom' => 'Beauté & parfums', 'description' => 'Cosmétique, soins, parfumerie.', 'icone' => 'fa-solid fa-spray-can-sparkles', 'sort' => 120],
    ['nom' => 'Alimentation & boissons', 'description' => 'Épicerie, boissons.', 'icone' => 'fa-solid fa-basket-shopping', 'sort' => 130],
    ['nom' => 'Bricolage & outillage', 'description' => 'Outils, quincaillerie.', 'icone' => 'fa-solid fa-screwdriver-wrench', 'sort' => 140],
    ['nom' => 'Animalerie', 'description' => 'Alimentation et accessoires animaux.', 'icone' => 'fa-solid fa-paw', 'sort' => 150],
    ['nom' => 'Livres & papeterie', 'description' => 'Livres, fournitures scolaires.', 'icone' => 'fa-solid fa-book', 'sort' => 160],
    ['nom' => 'Bijoux & montres', 'description' => 'Bijouterie, horlogerie.', 'icone' => 'fa-solid fa-gem', 'sort' => 170],
    ['nom' => 'Bagagerie & maroquinerie', 'description' => 'Sacs, valises, maroquinerie.', 'icone' => 'fa-solid fa-suitcase', 'sort' => 180],
    ['nom' => 'Pièces & poids lourds', 'description' => 'Pièces détachées poids lourds.', 'icone' => 'fa-solid fa-truck-monster', 'sort' => 190],
];

$ins = $db->prepare("
    INSERT INTO `categories_generales` (`nom`, `description`, `icone`, `sort_ordre`, `date_creation`)
    VALUES (:nom, :descr, :icone, :so, NOW())
    ON DUPLICATE KEY UPDATE
        `description` = VALUES(`description`),
        `icone` = VALUES(`icone`),
        `sort_ordre` = VALUES(`sort_ordre`)
");
foreach ($rayons as $r) {
    try {
        $ins->execute([
            'nom' => $r['nom'],
            'descr' => $r['description'],
            'icone' => $r['icone'],
            'so' => $r['sort'],
        ]);
    } catch (PDOException $e) {
        echo 'AVERTISSEMENT insert : ' . $e->getMessage() . "\n";
    }
}
echo "  + seed categories_generales\n";

if (!col_exists($db, 'categories', 'categorie_generale_id')) {
    safe_exec($db, "
        ALTER TABLE `categories` ADD COLUMN `categorie_generale_id` INT(11) NULL DEFAULT NULL
        COMMENT 'FK vers categories_generales (sous-catégorie vendeur)' AFTER `parent_id`
    ");
    echo "  + categories.categorie_generale_id\n";
}

if (col_exists($db, 'categories', 'categorie_generale_id') && col_exists($db, 'categories', 'parent_id')) {
    safe_exec($db, "
        UPDATE `categories` c
        INNER JOIN `categories` p ON c.`parent_id` = p.`id`
        INNER JOIN `categories_generales` cg ON cg.`nom` = p.`nom`
        SET c.`categorie_generale_id` = cg.`id`
        WHERE c.`admin_id` IS NOT NULL
          AND (c.`categorie_generale_id` IS NULL OR c.`categorie_generale_id` = 0)
    ");
    echo "  + rattrapage categorie_generale_id (parent → nom)\n";
}

echo "Terminé.\n";
