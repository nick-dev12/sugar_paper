<?php
/**
 * Migration complète module Comptes / RH (production-safe, sans FK bloquantes).
 * php migrations/run_migrate_comptes_rh.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();
$migrations_dir = __DIR__;

echo "=== Migration Comptes / RH ===\n\n";

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec('SET FOREIGN_KEY_CHECKS=0');

    $admin_id_type = mig_get_column_type($db, 'admin', 'id');
    if ($admin_id_type === '') {
        $admin_id_type = 'int(11)';
    }

    // Migration legacy utilisateur → gestion_stock (ignorée si ENUM déjà restreint)
    try {
        $db->exec("UPDATE admin SET role = 'gestion_stock' WHERE role = 'utilisateur'");
        echo "+ Migration rôle utilisateur → gestion_stock\n";
    } catch (PDOException $e) {
        echo "— Migration rôle utilisateur (ignoré)\n";
    }

    $fixPk = $migrations_dir . '/run_fix_employes_primary_key.php';
    if (is_file($fixPk)) {
        $code = mig_run_php_script($fixPk);
        if ($code !== 0) {
            throw new RuntimeException('run_fix_employes_primary_key.php a échoué');
        }
    }

    echo "→ Tables RH de base (sans FK inline)…\n";

    mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `employes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(120) NOT NULL,
  `prenom` VARCHAR(120) NOT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `telephone` VARCHAR(50) NULL DEFAULT NULL,
  `poste` VARCHAR(150) NULL DEFAULT NULL,
  `service` VARCHAR(150) NULL DEFAULT NULL,
  `date_embauche` DATE NULL DEFAULT NULL,
  `statut` ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
  `notes` TEXT NULL DEFAULT NULL,
  `statut_familial` VARCHAR(40) NULL DEFAULT NULL COMMENT 'RH — statut familial',
  `type_contrat` VARCHAR(40) NULL DEFAULT NULL COMMENT 'RH — type de contrat',
  `contrat_pdf_chemin` VARCHAR(500) NULL DEFAULT NULL COMMENT 'PDF contrat sous upload/employes_contrats/',
  `admin_id` $admin_id_type NULL DEFAULT NULL COMMENT 'Compte d accès interne lié (optionnel)',
  `qr_chemin` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Chemin relatif PNG sous upload/',
  `qr_payload` VARCHAR(2048) NULL DEFAULT NULL COMMENT 'Donnees encodees dans le QR (audit)',
  `photo_chemin` VARCHAR(400) NULL DEFAULT NULL COMMENT 'Photo RH (chemin sous upload/)',
  `matricule` VARCHAR(12) NULL DEFAULT NULL COMMENT 'Dénormalisé depuis employes_matricules (FPLxxxxxx)',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employes_matricule` (`matricule`),
  KEY `idx_statut` (`statut`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'table employes');

    mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `employes_matricules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `matricule` VARCHAR(12) NOT NULL COMMENT 'Ex. FPL123456 — prefixe + 6 chiffres',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employes_matricules_matricule` (`matricule`),
  UNIQUE KEY `uq_employes_matricules_employe` (`employe_id`),
  KEY `idx_employes_matricules_employe` (`employe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'table employes_matricules');

    // Rôles RH complémentaires (préserve les valeurs ENUM existantes)
    $rh_roles = [
        'gestion_stock', 'commercial', 'commercial_general', 'informaticien', 'developpeur',
        'comptabilite', 'contable', 'rh', 'caissier',
    ];
    $current_roles = mig_enum_values($db, 'admin', 'role');
    if (!empty($current_roles)) {
        mig_expand_enum_column($db, 'admin', 'role', array_merge($current_roles, $rh_roles), 'admin');
        echo "+ Rôles admin RH étendus (ENUM préservé)\n";
    } else {
        echo "— Rôles admin (table admin absente ou colonne role non ENUM)\n";
    }

    $sql_steps = [
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

    foreach ($sql_steps as $file => $label) {
        mig_exec_sql_file($db, $migrations_dir . '/' . $file, $label, true);
    }

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

    echo "\n--- Scripts PHP complémentaires ---\n";
    foreach ($php_steps as $script) {
        $path = $migrations_dir . '/' . $script;
        if (!is_file($path)) {
            echo "! Script absent : $script\n";
            continue;
        }
        echo "\n>> $script\n";
        $code = mig_run_php_script($path);
        if ($code !== 0) {
            throw new RuntimeException("$script a échoué (code $code)");
        }
    }

    $db->exec('SET FOREIGN_KEY_CHECKS=1');

    echo "\n→ Clés étrangères RH (optionnelles)…\n";
    if (mig_table_exists($db, 'admin')) {
        mig_try_add_foreign_key($db, 'employes', 'fk_employes_admin',
            "ALTER TABLE `employes` ADD CONSTRAINT `fk_employes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
    }
    if (mig_table_exists($db, 'employes')) {
        mig_try_add_foreign_key($db, 'employes_matricules', 'fk_employes_matricules_employe',
            "ALTER TABLE `employes_matricules` ADD CONSTRAINT `fk_employes_matricules_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_documents', 'fk_employe_documents_employe',
            "ALTER TABLE `employe_documents` ADD CONSTRAINT `fk_employe_documents_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_autorisations_absence', 'fk_autoris_abs_employe',
            "ALTER TABLE `employe_autorisations_absence` ADD CONSTRAINT `fk_autoris_abs_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_sanctions', 'fk_employe_sanctions_employe',
            "ALTER TABLE `employe_sanctions` ADD CONSTRAINT `fk_employe_sanctions_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_prets', 'fk_employe_prets_employe',
            "ALTER TABLE `employe_prets` ADD CONSTRAINT `fk_employe_prets_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_conges', 'fk_employe_conges_employe',
            "ALTER TABLE `employe_conges` ADD CONSTRAINT `fk_employe_conges_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_bulletins_paie', 'fk_bulletin_employe',
            "ALTER TABLE `employe_bulletins_paie` ADD CONSTRAINT `fk_bulletin_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_prime_transport_retraits', 'fk_emp_transport_employe',
            "ALTER TABLE `employe_prime_transport_retraits` ADD CONSTRAINT `fk_emp_transport_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
    if (mig_table_exists($db, 'employe_absences')) {
        mig_try_add_foreign_key($db, 'employe_absences', 'fk_employe_absences_employe',
            "ALTER TABLE `employe_absences` ADD CONSTRAINT `fk_employe_absences_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_absence_justificatifs', 'fk_justificatif_absence',
            "ALTER TABLE `employe_absence_justificatifs` ADD CONSTRAINT `fk_justificatif_absence` FOREIGN KEY (`absence_id`) REFERENCES `employe_absences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
    if (mig_table_exists($db, 'employe_prets')) {
        mig_try_add_foreign_key($db, 'employe_pret_remboursements', 'fk_pret_remb_pret',
            "ALTER TABLE `employe_pret_remboursements` ADD CONSTRAINT `fk_pret_remb_pret` FOREIGN KEY (`pret_id`) REFERENCES `employe_prets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
    if (mig_table_exists($db, 'admin')) {
        mig_try_add_foreign_key($db, 'employe_absences', 'fk_employe_absences_subject_admin',
            "ALTER TABLE `employe_absences` ADD CONSTRAINT `fk_employe_absences_subject_admin` FOREIGN KEY (`subject_admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_absences', 'fk_employe_absences_admin',
            "ALTER TABLE `employe_absences` ADD CONSTRAINT `fk_employe_absences_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_absence_justificatifs', 'fk_justificatif_admin',
            "ALTER TABLE `employe_absence_justificatifs` ADD CONSTRAINT `fk_justificatif_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_autorisations_absence', 'fk_autoris_abs_admin',
            "ALTER TABLE `employe_autorisations_absence` ADD CONSTRAINT `fk_autoris_abs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_sanctions', 'fk_employe_sanctions_admin',
            "ALTER TABLE `employe_sanctions` ADD CONSTRAINT `fk_employe_sanctions_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_prets', 'fk_employe_prets_admin',
            "ALTER TABLE `employe_prets` ADD CONSTRAINT `fk_employe_prets_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_pret_remboursements', 'fk_pret_remb_admin',
            "ALTER TABLE `employe_pret_remboursements` ADD CONSTRAINT `fk_pret_remb_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_conges', 'fk_employe_conges_admin',
            "ALTER TABLE `employe_conges` ADD CONSTRAINT `fk_employe_conges_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_bulletins_paie', 'fk_bulletin_admin',
            "ALTER TABLE `employe_bulletins_paie` ADD CONSTRAINT `fk_bulletin_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        mig_try_add_foreign_key($db, 'employe_prime_transport_retraits', 'fk_emp_transport_admin',
            "ALTER TABLE `employe_prime_transport_retraits` ADD CONSTRAINT `fk_emp_transport_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
    }

    echo "\n=== Migration Comptes / RH terminée avec succès ===\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
