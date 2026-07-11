<?php
/**
 * Tables annonces plateforme (envoi client / vendeur + lectures).
 *
 * Usage : php migrations/run_add_platform_annonces.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function table_exists_pa(PDO $db, string $table): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function safe_exec_pa(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration platform_annonces…\n";

if (!table_exists_pa($db, 'platform_annonces')) {
    safe_exec_pa($db, "
        CREATE TABLE `platform_annonces` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `titre` VARCHAR(200) NOT NULL,
          `message` TEXT NOT NULL,
          `audience` ENUM('client','vendeur') NOT NULL,
          `lien_url` VARCHAR(500) NULL DEFAULT NULL,
          `super_admin_id` INT(11) NOT NULL,
          `nb_destinataires_cibles` INT(11) NOT NULL DEFAULT 0,
          `nb_push_envoyes` INT(11) NOT NULL DEFAULT 0,
          `nb_push_echecs` INT(11) NOT NULL DEFAULT 0,
          `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_audience_date` (`audience`, `date_envoi`),
          KEY `idx_super_admin` (`super_admin_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table platform_annonces\n";
} else {
    echo "  table platform_annonces déjà présente.\n";
}

if (!table_exists_pa($db, 'platform_annonce_lectures')) {
    safe_exec_pa($db, "
        CREATE TABLE `platform_annonce_lectures` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `annonce_id` INT(11) NOT NULL,
          `user_id` INT(11) NULL DEFAULT NULL,
          `admin_id` INT(11) NULL DEFAULT NULL,
          `date_lecture` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_annonce_user` (`annonce_id`, `user_id`),
          UNIQUE KEY `uk_annonce_admin` (`annonce_id`, `admin_id`),
          KEY `idx_annonce` (`annonce_id`),
          CONSTRAINT `fk_lecture_annonce` FOREIGN KEY (`annonce_id`)
            REFERENCES `platform_annonces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table platform_annonce_lectures\n";
} else {
    echo "  table platform_annonce_lectures déjà présente.\n";
}

echo "Terminé.\n";
