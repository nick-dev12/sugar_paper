<?php
/**
 * Ajoute les colonnes nécessaires à Firebase/Google Auth.
 *
 * Usage : php migrations/run_migrate_google_auth.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function google_auth_column_exists(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $q->execute(['t' => $table, 'c' => $col]);
    return (int) $q->fetchColumn() > 0;
}

function google_auth_index_exists(PDO $db, string $table, string $idx): bool {
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i
    ");
    $q->execute(['t' => $table, 'i' => $idx]);
    return (int) $q->fetchColumn() > 0;
}

function google_auth_safe_exec(PDO $db, string $sql): bool {
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

function google_auth_migrate_table(PDO $db, string $table, string $index): void {
    echo "Table $table...\n";

    if (!google_auth_column_exists($db, $table, 'firebase_uid')) {
        google_auth_safe_exec($db, "
            ALTER TABLE `$table`
            ADD COLUMN `firebase_uid` VARCHAR(128) NULL DEFAULT NULL AFTER `password`
        ");
        echo "  + colonne firebase_uid\n";
    } else {
        echo "  colonne firebase_uid déjà présente.\n";
    }

    if (!google_auth_column_exists($db, $table, 'auth_provider')) {
        google_auth_safe_exec($db, "
            ALTER TABLE `$table`
            ADD COLUMN `auth_provider` VARCHAR(32) NULL DEFAULT NULL AFTER `firebase_uid`
        ");
        echo "  + colonne auth_provider\n";
    } else {
        echo "  colonne auth_provider déjà présente.\n";
    }

    if (!google_auth_index_exists($db, $table, $index)) {
        google_auth_safe_exec($db, "ALTER TABLE `$table` ADD UNIQUE KEY `$index` (`firebase_uid`)");
        echo "  + index unique $index\n";
    } else {
        echo "  index $index déjà présent.\n";
    }
}

echo "Migration Google Auth...\n";
google_auth_migrate_table($db, 'users', 'idx_users_firebase_uid');
google_auth_migrate_table($db, 'admin', 'idx_admin_firebase_uid');
echo "Terminé.\n";
?>
