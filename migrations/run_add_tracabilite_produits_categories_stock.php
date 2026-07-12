<?php
/**
 * Traçabilité : créateur / dernier modificateur (produits, catégories) et admin sur mouvements de stock.
 * Ajouts uniquement — aucune suppression de données.
 *
 * Usage : php migrations/run_add_tracabilite_produits_categories_stock.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

echo "=== Traçabilité produits / catégories / stock ===\n\n";

try {
    mig_add_column_if_missing($db, 'produits', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL');
    mig_add_column_if_missing($db, 'produits', 'admin_dernier_modificateur_id', 'INT(11) NULL DEFAULT NULL');
    mig_add_column_if_missing($db, 'categories', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL');
    mig_add_column_if_missing($db, 'categories', 'admin_dernier_modificateur_id', 'INT(11) NULL DEFAULT NULL');

    if (mig_table_exists($db, 'stock_mouvements')) {
        mig_add_column_if_missing($db, 'stock_mouvements', 'admin_id', 'INT(11) NULL DEFAULT NULL');
    } else {
        echo "  ! Table stock_mouvements absente — exécutez run_add_stock_mouvements.php\n";
    }

    if (!mig_index_exists($db, 'produits', 'idx_produits_admin_createur')) {
        mig_safe_exec($db, 'CREATE INDEX idx_produits_admin_createur ON produits (admin_createur_id)', 'index produits.admin_createur');
    }
    if (!mig_index_exists($db, 'produits', 'idx_produits_admin_modif')) {
        mig_safe_exec($db, 'CREATE INDEX idx_produits_admin_modif ON produits (admin_dernier_modificateur_id)', 'index produits.admin_modif');
    }
    if (!mig_index_exists($db, 'categories', 'idx_categories_admin_createur')) {
        mig_safe_exec($db, 'CREATE INDEX idx_categories_admin_createur ON categories (admin_createur_id)', 'index categories.admin_createur');
    }
    if (mig_table_exists($db, 'stock_mouvements') && mig_column_exists($db, 'stock_mouvements', 'admin_id')
        && !mig_index_exists($db, 'stock_mouvements', 'idx_stock_mouvements_admin')) {
        mig_safe_exec($db, 'CREATE INDEX idx_stock_mouvements_admin ON stock_mouvements (admin_id)', 'index stock_mouvements.admin');
    }

    $fks = [
        'ALTER TABLE produits ADD CONSTRAINT fk_produits_admin_createur FOREIGN KEY (admin_createur_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE',
        'ALTER TABLE produits ADD CONSTRAINT fk_produits_admin_modif FOREIGN KEY (admin_dernier_modificateur_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE',
        'ALTER TABLE categories ADD CONSTRAINT fk_categories_admin_createur FOREIGN KEY (admin_createur_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE',
        'ALTER TABLE categories ADD CONSTRAINT fk_categories_admin_modif FOREIGN KEY (admin_dernier_modificateur_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE',
    ];
    if (mig_table_exists($db, 'stock_mouvements') && mig_column_exists($db, 'stock_mouvements', 'admin_id')) {
        $fks[] = 'ALTER TABLE stock_mouvements ADD CONSTRAINT fk_stock_mouvements_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE';
    }
    foreach ($fks as $sql) {
        mig_safe_exec($db, $sql, 'clé étrangère traçabilité');
    }

    echo "\n=== Traçabilité produits / catégories / stock terminée ===\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
