<?php
/**
 * Permet de détacher un produit supprimé des lignes historiques (commandes, devis, caisse).
 * Usage : php migrations/run_allow_null_produit_id_lignes.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

function mig_drop_fk_if_exists(PDO $db, $table, $constraint)
{
    $check = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $check->execute([(string) $table, (string) $constraint]);
    if ((int) $check->fetchColumn() <= 0) {
        echo "  — FK {$constraint} absente sur {$table}\n";
        return;
    }

    $db->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
    echo "  + FK {$constraint} supprimée sur {$table}\n";
}

echo "=== Migration produit_id nullable sur lignes historiques ===\n";

if (!mig_table_exists($db, 'produits')) {
    echo "Table produits absente, migration ignorée.\n";
    exit(0);
}

$line_tables = [
    'commande_produits' => [
        'label_col' => 'nom_produit',
        'fk' => 'fk_commande_produits_produit',
    ],
    'devis_produits' => [
        'label_col' => 'nom_produit',
        'fk' => 'fk_devis_produits_produit',
    ],
    'caisse_vente_lignes' => [
        'label_col' => 'designation',
        'fk' => 'fk_cvl_produit',
    ],
];

foreach ($line_tables as $table => $meta) {
    if (!mig_table_exists($db, $table)) {
        echo "— Table {$table} absente, ignorée.\n";
        continue;
    }

    $label_col = $meta['label_col'];
    if (!mig_column_exists($db, $table, $label_col)) {
        if ($label_col === 'nom_produit') {
            mig_safe_exec(
                $db,
                "ALTER TABLE `{$table}` ADD COLUMN `nom_produit` VARCHAR(255) NULL DEFAULT NULL AFTER `produit_id`",
                "colonne nom_produit sur {$table}"
            );
        }
    }

    if (mig_column_exists($db, $table, $label_col) && mig_column_exists($db, $table, 'produit_id')) {
        $sql = "
            UPDATE `{$table}` t
            INNER JOIN `produits` p ON p.id = t.produit_id
            SET t.`{$label_col}` = COALESCE(NULLIF(TRIM(t.`{$label_col}`), ''), p.nom)
            WHERE t.produit_id IS NOT NULL
        ";
        try {
            $count = $db->exec($sql);
            echo "  + Libellés complétés sur {$table}" . ($count !== false ? " ({$count} lignes)" : '') . "\n";
        } catch (PDOException $e) {
            echo "  ! Backfill {$table} : " . $e->getMessage() . "\n";
        }
    }

    $col = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'produit_id'")->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper((string) $col['Null']) === 'NO') {
        mig_drop_fk_if_exists($db, $table, $meta['fk']);
        mig_safe_exec(
            $db,
            "ALTER TABLE `{$table}` MODIFY COLUMN `produit_id` INT(11) NULL DEFAULT NULL",
            "produit_id nullable sur {$table}"
        );
        mig_try_add_foreign_key(
            $db,
            $table,
            $meta['fk'],
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$meta['fk']}` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE"
        );
    } else {
        echo "  — produit_id déjà nullable sur {$table}\n";
    }
}

echo "Migration terminée.\n";
