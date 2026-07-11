<?php
/**
 * Migration module Invoice — bons de livraison B2B
 * php migrations/run_migrate_invoice_bl.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

function invoice_migrate_exec_sql_file(PDO $db, $path, $label) {
    if (!is_file($path)) {
        echo "! Fichier absent : $path\n";
        return;
    }
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        echo "! Fichier vide : $path\n";
        return;
    }
    $parts = preg_split('/;\s*\n/', $sql);
    $applied = 0;
    $skipped = 0;
    foreach ($parts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || strpos($stmt, '--') === 0) {
            continue;
        }
        try {
            $db->exec($stmt);
            $applied++;
        } catch (PDOException $e) {
            $m = strtolower($e->getMessage());
            if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false
                || strpos($m, 'déjà') !== false || strpos($m, 'exists') !== false) {
                $skipped++;
                continue;
            }
            throw $e;
        }
    }
    if ($applied > 0) {
        echo "+ $label ($applied instruction(s))\n";
    } elseif ($skipped > 0) {
        echo "— $label (déjà appliqué)\n";
    } else {
        echo "— $label (aucune instruction)\n";
    }
}

$migrations_dir = __DIR__;

echo "=== Migration Invoice / BL ===\n\n";

try {
    $db->exec('SET NAMES utf8mb4');

    invoice_migrate_exec_sql_file($db, $migrations_dir . '/migration_invoice_bl_core.sql', 'Tables B2B / BL');
    invoice_migrate_exec_sql_file($db, $migrations_dir . '/add_devis_bl_adresse_client.sql', 'Adresse client devis/BL');
    invoice_migrate_exec_sql_file($db, $migrations_dir . '/add_devis_bl_factures_tva.sql', 'Colonnes TVA devis/BL');

    echo "\n→ Types client BL + plafonds contacts…\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($migrations_dir . '/run_add_types_client_bl.php'), $code1);
    if ($code1 !== 0) {
        throw new RuntimeException('run_add_types_client_bl.php a échoué');
    }

    echo "\n→ Adresse / plafond contacts…\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($migrations_dir . '/run_add_contacts_adresse_plafond_bl.php'), $code2);
    if ($code2 !== 0) {
        throw new RuntimeException('run_add_contacts_adresse_plafond_bl.php a échoué');
    }

    echo "\n→ Paiement facture BL…\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($migrations_dir . '/run_add_bl_facture_paiement.php'), $code3);
    if ($code3 !== 0) {
        throw new RuntimeException('run_add_bl_facture_paiement.php a échoué');
    }

    invoice_migrate_exec_sql_file($db, $migrations_dir . '/bl_statut_unify_valide.sql', 'Statuts BL unifiés');

    echo "\nMigration Invoice / BL terminée avec succès.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erreur : " . $e->getMessage() . "\n");
    exit(1);
}
