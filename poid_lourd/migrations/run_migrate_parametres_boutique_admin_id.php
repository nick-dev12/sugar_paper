<?php
/**
 * Ajoute admin_id aux tables de paramètres pour scoper par vendeur / boutique.
 * Usage : php migrations/run_migrate_parametres_boutique_admin_id.php
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

function add_col(PDO $db, string $table, string $sqlAfter): void {
    if (!col_exists($db, $table, 'admin_id')) {
        try {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Vendeur (admin.id)' $sqlAfter");
            echo "OK : $table.admin_id\n";
        } catch (PDOException $e) {
            echo "ERREUR $table : " . $e->getMessage() . "\n";
        }
    } else {
        echo "Déjà : $table.admin_id\n";
    }
}

echo "Migration paramètres boutique (admin_id)…\n";

add_col($db, 'section4_config', 'AFTER `id`');
try {
    if (col_exists($db, 'section4_config', 'admin_id')) {
        $db->exec("ALTER TABLE `section4_config` ADD KEY `idx_section4_admin` (`admin_id`)");
    }
} catch (PDOException $e) { /* index existe peut-être */ }

add_col($db, 'trending_config', 'AFTER `id`');
try {
    if (col_exists($db, 'trending_config', 'admin_id')) {
        $db->exec("ALTER TABLE `trending_config` ADD KEY `idx_trending_admin` (`admin_id`)");
    }
} catch (PDOException $e) { }

add_col($db, 'videos', 'AFTER `id`');
try {
    if (col_exists($db, 'videos', 'admin_id')) {
        $db->exec("ALTER TABLE `videos` ADD KEY `idx_videos_admin` (`admin_id`)");
    }
} catch (PDOException $e) { }

add_col($db, 'logos', 'AFTER `id`');
try {
    if (col_exists($db, 'logos', 'admin_id')) {
        $db->exec("ALTER TABLE `logos` ADD KEY `idx_logos_admin` (`admin_id`)");
    }
} catch (PDOException $e) { }

add_col($db, 'zones_livraison', 'AFTER `id`');
try {
    if (col_exists($db, 'zones_livraison', 'admin_id')) {
        $db->exec("ALTER TABLE `zones_livraison` ADD KEY `idx_zones_admin` (`admin_id`)");
    }
} catch (PDOException $e) { }

echo "Terminé.\n";
exit(0);
