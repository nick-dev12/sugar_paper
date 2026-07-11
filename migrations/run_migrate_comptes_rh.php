<?php
/**
 * Migration complète module Comptes / RH (employés, absences, bulletins de paie)
 * php migrations/run_migrate_comptes_rh.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

function migrate_exec_sql_file(PDO $db, $path, $label) {
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

function migrate_try_exec(PDO $db, $sql, $label) {
    try {
        $db->exec($sql);
        echo "+ $label\n";
    } catch (PDOException $e) {
        $m = strtolower($e->getMessage());
        if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false
            || strpos($m, 'déjà') !== false || strpos($m, 'exists') !== false) {
            echo "— $label (déjà présent)\n";
            return;
        }
        throw $e;
    }
}

$migrations_dir = __DIR__;

$sql_steps = [
    'alter_admin_role_commercial_general_informaticien.sql' => 'Rôles admin étendus',
    'alter_admin_role_developpeur.sql' => 'Rôle développeur',
    'add_employes.sql' => 'Tables employes + matricules',
    'add_employes_rh_famille_contrat.sql' => 'Colonnes RH famille/contrat',
    'add_employes_qrcode.sql' => 'QR code employés',
    'add_employes_photo_chemin.sql' => 'Photo employés',
    'create_employes_matricules.sql' => 'Table employes_matricules',
    'add_employes_matricule_colonne.sql' => 'Colonne matricule dénormalisée',
    'create_employes_absences_tables.sql' => 'Tables absences',
    'create_employe_documents_table.sql' => 'Documents employés',
    'create_employe_autorisations_absence_table.sql' => 'Autorisations absence',
    'create_employe_sanctions_table.sql' => 'Sanctions employés',
    'create_employe_prets_table.sql' => 'Prêts employés',
    'create_employe_pret_remboursements_table.sql' => 'Remboursements prêts',
    'create_bulletin_paie_tables.sql' => 'Paramètres bulletin paie',
    'alter_employes_salaire_embauche.sql' => 'Salaire embauche employés',
    'add_employe_conges_and_param.sql' => 'Congés employés',
    'add_employe_irpp_bp_forfait_trimf.sql' => 'IRPP / TRIMF employés',
    'add_bulletin_paie_prime_transport.sql' => 'Prime transport bulletin',
    'alter_bulletin_paie_jours_presence_defaut.sql' => 'Jours présence défaut',
    'add_employes_montant_trimf_mensuel.sql' => 'TRIMF mensuel employés',
];

$php_steps = [
    'run_create_bulletin_paie.php',
    'run_alter_bulletin_absences_taux_penalites.php',
    'run_add_employe_conges_and_param.php',
    'run_alter_employes_salaire_embauche.php',
    'run_add_employe_irpp_bp_forfait_trimf.php',
    'run_add_bulletin_paie_prime_transport.php',
    'run_alter_bulletin_jours_presence_defaut.php',
    'run_add_employes_montant_trimf_mensuel.php',
    'run_backfill_employes_matricules.php',
];

echo "=== Migration Comptes / RH ===\n\n";

try {
    $db->exec('SET NAMES utf8mb4');

    // Migration legacy utilisateur → gestion_stock
    try {
        $db->exec("UPDATE admin SET role = 'gestion_stock' WHERE role = 'utilisateur'");
        echo "+ Migration rôle utilisateur → gestion_stock\n";
    } catch (PDOException $e) {
        echo "— Migration rôle utilisateur (ignoré)\n";
    }

    // Réparer employes sans PK si migration partielle antérieure
    $fixPk = $migrations_dir . '/run_fix_employes_primary_key.php';
    if (is_file($fixPk)) {
        passthru('php ' . escapeshellarg($fixPk), $code);
        if ($code !== 0) {
            exit(1);
        }
    }

    foreach ($sql_steps as $file => $label) {
        migrate_exec_sql_file($db, $migrations_dir . '/' . $file, $label);
    }

    echo "\n--- Scripts PHP complémentaires ---\n";
    foreach ($php_steps as $script) {
        $path = $migrations_dir . '/' . $script;
        if (!is_file($path)) {
            echo "! Script absent : $script\n";
            continue;
        }
        echo "\n>> $script\n";
        passthru('php ' . escapeshellarg($path), $code);
        if ($code !== 0) {
            fwrite(STDERR, "Erreur dans $script (code $code)\n");
            exit(1);
        }
    }

    echo "\n=== Migration Comptes / RH terminée avec succès ===\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
