<?php
/**
 * Migration : hiérarchie catégories (générales + sous-catégories vendeur).
 * Usage (CLI ou navigateur) : php migrations/run_migrate_categories_hierarchy.php
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

function idx_exists(PDO $db, string $table, string $idx): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i
    ");
    $q->execute(['t' => $table, 'i' => $idx]);
    return (int) $q->fetchColumn() > 0;
}

function fk_exists(PDO $db, string $table, string $name): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :n
    ");
    $q->execute(['t' => $table, 'n' => $name]);
    return (int) $q->fetchColumn() > 0;
}

/** @return true si succès */
function safe_exec(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT SQL : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration catégories hiérarchie…\n";

if (!col_exists($db, 'categories', 'parent_id')) {
    safe_exec($db, "ALTER TABLE `categories` ADD COLUMN `parent_id` INT NULL DEFAULT NULL COMMENT 'Catégorie parente (générale)' AFTER `id`");
    echo "  + parent_id\n";
}
if (!col_exists($db, 'categories', 'admin_id')) {
    safe_exec($db, "ALTER TABLE `categories` ADD COLUMN `admin_id` INT NULL DEFAULT NULL COMMENT 'Vendeur propriétaire (sous-catégorie)' AFTER `parent_id`");
    echo "  + admin_id\n";
}
if (!col_exists($db, 'categories', 'icone')) {
    safe_exec($db, "ALTER TABLE `categories` ADD COLUMN `icone` VARCHAR(80) NULL DEFAULT NULL COMMENT 'Font Awesome' AFTER `image`");
    echo "  + icone\n";
}
if (!col_exists($db, 'categories', 'sort_ordre')) {
    safe_exec($db, "ALTER TABLE `categories` ADD COLUMN `sort_ordre` INT NOT NULL DEFAULT 0 AFTER `icone`");
    echo "  + sort_ordre\n";
}
if (!col_exists($db, 'categories', 'est_plateforme')) {
    safe_exec($db, "ALTER TABLE `categories` ADD COLUMN `est_plateforme` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = rayon officiel (mega-menu, formulaire vendeur)' AFTER `sort_ordre`");
    echo "  + est_plateforme\n";
}

if (!idx_exists($db, 'categories', 'idx_categories_parent')) {
    safe_exec($db, 'ALTER TABLE `categories` ADD INDEX `idx_categories_parent` (`parent_id`)');
}
if (!idx_exists($db, 'categories', 'idx_categories_admin')) {
    safe_exec($db, 'ALTER TABLE `categories` ADD INDEX `idx_categories_admin` (`admin_id`)');
}

if (!fk_exists($db, 'categories', 'fk_categories_parent')) {
    if (safe_exec($db, 'ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE')) {
        echo "  + FK parent\n";
    }
}
if (!fk_exists($db, 'categories', 'fk_categories_admin')) {
    if (safe_exec($db, 'ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`id`) ON DELETE CASCADE ON UPDATE CASCADE')) {
        echo "  + FK admin\n";
    }
}

safe_exec($db, 'UPDATE `categories` SET `parent_id` = NULL WHERE `parent_id` = 0');
safe_exec($db, 'UPDATE `categories` SET `admin_id` = NULL WHERE `admin_id` = 0');
safe_exec($db, 'UPDATE `categories` SET `sort_ordre` = `id` WHERE `sort_ordre` = 0');

/** Rayons plateforme marketplace (UNIQUE sur `nom` → ON DUPLICATE KEY UPDATE) */
$plateforme_rayons = [
    ['nom' => 'Femme', 'description' => 'Mode, accessoires et univers féminin.', 'icone' => 'fa-solid fa-person-dress', 'sort' => 10],
    ['nom' => 'Homme', 'description' => 'Mode, accessoires et univers masculin.', 'icone' => 'fa-solid fa-person', 'sort' => 20],
    ['nom' => 'Bébé & puériculture', 'description' => 'Puériculture, layette, jouets premier âge.', 'icone' => 'fa-solid fa-baby', 'sort' => 30],
    ['nom' => 'Jouets & jeux', 'description' => 'Jouets, jeux de société, jeux vidéo.', 'icone' => 'fa-solid fa-puzzle-piece', 'sort' => 40],
    ['nom' => 'Électroménager', 'description' => 'Gros et petit électroménager.', 'icone' => 'fa-solid fa-plug', 'sort' => 50],
    ['nom' => 'Électronique & high-tech', 'description' => 'TV, audio, photo, multimédia.', 'icone' => 'fa-solid fa-tv', 'sort' => 60],
    ['nom' => 'Informatique & bureautique', 'description' => 'PC, périphériques, fournitures de bureau.', 'icone' => 'fa-solid fa-laptop', 'sort' => 70],
    ['nom' => 'Téléphonie & accessoires', 'description' => 'Smartphones, coques, chargeurs.', 'icone' => 'fa-solid fa-mobile-screen-button', 'sort' => 80],
    ['nom' => 'Maison & jardin', 'description' => 'Décoration, literie, jardinage.', 'icone' => 'fa-solid fa-house-chimney', 'sort' => 90],
    ['nom' => 'Auto & moto', 'description' => 'Entretien, accessoires, équipements.', 'icone' => 'fa-solid fa-car', 'sort' => 100],
    ['nom' => 'Sport & loisirs', 'description' => 'Fitness, outdoor, hobbies.', 'icone' => 'fa-solid fa-person-running', 'sort' => 110],
    ['nom' => 'Beauté & parfums', 'description' => 'Cosmétique, soins, parfumerie.', 'icone' => 'fa-solid fa-spray-can-sparkles', 'sort' => 120],
    ['nom' => 'Alimentation & boissons', 'description' => 'Épicerie, boissons, produits du terroir.', 'icone' => 'fa-solid fa-basket-shopping', 'sort' => 130],
    ['nom' => 'Bricolage & outillage', 'description' => 'Outils, quincaillerie, rénovation.', 'icone' => 'fa-solid fa-screwdriver-wrench', 'sort' => 140],
    ['nom' => 'Animalerie', 'description' => 'Alimentation et accessoires pour animaux.', 'icone' => 'fa-solid fa-paw', 'sort' => 150],
    ['nom' => 'Livres & papeterie', 'description' => 'Livres, fournitures scolaires et bureau.', 'icone' => 'fa-solid fa-book', 'sort' => 160],
    ['nom' => 'Bijoux & montres', 'description' => 'Bijouterie, horlogerie.', 'icone' => 'fa-solid fa-gem', 'sort' => 170],
    ['nom' => 'Bagagerie & maroquinerie', 'description' => 'Sacs, valises, petite maroquinerie.', 'icone' => 'fa-solid fa-suitcase', 'sort' => 180],
    ['nom' => 'Pièces & poids lourds', 'description' => 'Pièces détachées poids lourds et industriel.', 'icone' => 'fa-solid fa-truck-monster', 'sort' => 190],
];

if (col_exists($db, 'categories', 'est_plateforme')) {
    $insPlat = $db->prepare("
        INSERT INTO `categories` (`nom`, `description`, `image`, `date_creation`, `parent_id`, `admin_id`, `icone`, `sort_ordre`, `est_plateforme`)
        VALUES (:nom, :descr, NULL, NOW(), NULL, NULL, :icone, :sort_ordre, 1)
        ON DUPLICATE KEY UPDATE
            `description` = VALUES(`description`),
            `icone` = VALUES(`icone`),
            `sort_ordre` = VALUES(`sort_ordre`),
            `est_plateforme` = 1,
            `parent_id` = NULL,
            `admin_id` = NULL
    ");
    foreach ($plateforme_rayons as $r) {
        try {
            $insPlat->execute([
                'nom' => $r['nom'],
                'descr' => $r['description'],
                'icone' => $r['icone'],
                'sort_ordre' => $r['sort'],
            ]);
        } catch (PDOException $e) {
            echo 'AVERTISSEMENT insert rayon : ' . $e->getMessage() . "\n";
        }
    }
    safe_exec($db, 'UPDATE `categories` SET `est_plateforme` = 0 WHERE `admin_id` IS NOT NULL OR (`parent_id` IS NOT NULL AND `parent_id` > 0)');
    $noms_officiels = array_values(array_unique(array_map(static function (array $row): string {
        return $row['nom'];
    }, $plateforme_rayons)));
    if (!empty($noms_officiels)) {
        $ph = implode(',', array_fill(0, count($noms_officiels), '?'));
        try {
            $dem = $db->prepare("
                UPDATE `categories` SET `est_plateforme` = 0
                WHERE `est_plateforme` = 1
                  AND (`parent_id` IS NULL OR `parent_id` = 0)
                  AND (`admin_id` IS NULL OR `admin_id` = 0)
                  AND `nom` NOT IN ($ph)
            ");
            $dem->execute($noms_officiels);
        } catch (PDOException $e) {
            echo 'AVERTISSEMENT dé-rayonnage anciennes catégories : ' . $e->getMessage() . "\n";
        }
    }
    echo "  + seed rayons plateforme\n";
}

$icons = [
    ['Les Noix', 'fa-solid fa-nut'],
    ['Noix', 'fa-solid fa-nut'],
    ['Les Feuilles', 'fa-solid fa-leaf'],
    ['Feuilles', 'fa-solid fa-leaf'],
    ['Les Fruits', 'fa-solid fa-apple-whole'],
    ['Fruits', 'fa-solid fa-apple-whole'],
    ['Les Huiles', 'fa-solid fa-bottle-droplet'],
    ['Huiles', 'fa-solid fa-bottle-droplet'],
    ['Les Céréales', 'fa-solid fa-wheat-awn'],
    ['Céréales', 'fa-solid fa-wheat-awn'],
    ['Les Racines', 'fa-solid fa-carrot'],
    ['Racines', 'fa-solid fa-carrot'],
    ['Cosmétiques', 'fa-solid fa-pump-soap'],
    ['Les cosmétiques', 'fa-solid fa-pump-soap'],
    ['Pièces', 'fa-solid fa-gears'],
    ['Poids lourds', 'fa-solid fa-truck'],
    ['amande', 'fa-solid fa-seedling'],
    ['Amande', 'fa-solid fa-seedling'],
];
foreach ($icons as $pair) {
    $st = $db->prepare('UPDATE `categories` SET `icone` = :ic WHERE LOWER(TRIM(`nom`)) = LOWER(TRIM(:n)) AND (`icone` IS NULL OR `icone` = \'\')');
    $st->execute(['ic' => $pair[1], 'n' => $pair[0]]);
}
$st = $db->prepare('UPDATE `categories` SET `icone` = :ic WHERE (`icone` IS NULL OR `icone` = \'\') AND `parent_id` IS NULL AND `admin_id` IS NULL');
$st->execute(['ic' => 'fa-solid fa-folder']);

echo "Terminé.\n";
