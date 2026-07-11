<?php
/**
 * Genres (indépendants des rayons) + liaison produits_genres.
 * Rend produits.categorie_id nullable pour les produits classés uniquement par genres.
 *
 * Usage : php migrations/run_migrate_genres.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function table_exists(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function col_nullable(PDO $db, string $table, string $col): ?bool {
    try {
        $q = $db->prepare("
            SELECT IS_NULLABLE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
        ");
        $q->execute(['t' => $table, 'c' => $col]);
        $r = $q->fetchColumn();
        if ($r === false) {
            return null;
        }
        return strtoupper((string) $r) === 'YES';
    } catch (PDOException $e) {
        return null;
    }
}

function safe_exec(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration genres…\n";

if (!table_exists($db, 'produits')) {
    echo "Table produits absente.\n";
    exit(1);
}

if (!table_exists($db, 'genres')) {
    safe_exec($db, "
        CREATE TABLE `genres` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nom` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `image` VARCHAR(255) NULL DEFAULT NULL,
            `sort_ordre` INT(11) NOT NULL DEFAULT 0,
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_genres_nom` (`nom`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table genres\n";
} else {
    echo "  table genres déjà présente.\n";
}

if (!table_exists($db, 'produits_genres')) {
    safe_exec($db, "
        CREATE TABLE `produits_genres` (
            `produit_id` INT(11) NOT NULL,
            `genre_id` INT(11) NOT NULL,
            PRIMARY KEY (`produit_id`, `genre_id`),
            KEY `idx_pg_genre` (`genre_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table produits_genres\n";
} else {
    echo "  table produits_genres déjà présente.\n";
}

$nullable = col_nullable($db, 'produits', 'categorie_id');
if ($nullable === false) {
    echo "  → categorie_id NOT NULL : tentative passage en NULL…\n";
    try {
        $st = $db->query("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'produits'
              AND COLUMN_NAME = 'categorie_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");
        $fk = $st ? $st->fetchColumn() : false;
        if ($fk) {
            safe_exec($db, 'ALTER TABLE `produits` DROP FOREIGN KEY `' . str_replace('`', '``', (string) $fk) . '`');
        }
    } catch (PDOException $e) {
        echo "  (drop FK) " . $e->getMessage() . "\n";
    }
    if (safe_exec($db, 'ALTER TABLE `produits` MODIFY `categorie_id` INT(11) NULL DEFAULT NULL')) {
        echo "  + produits.categorie_id nullable\n";
    }
    try {
        safe_exec($db, '
            ALTER TABLE `produits`
            ADD CONSTRAINT `fk_produits_categorie`
            FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE
        ');
        echo "  + FK produits.categorie_id (SET NULL)\n";
    } catch (PDOException $e) {
        echo "  (FK optionnelle non recréée — à vérifier manuellement)\n";
    }
} elseif ($nullable === true) {
    echo "  produits.categorie_id déjà nullable.\n";
}

echo "Terminé.\n";
