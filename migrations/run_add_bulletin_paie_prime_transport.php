<?php
/**
 * Ajout prime transport paramètres + table des retraits transport par employé.
 * php migrations/run_add_bulletin_paie_prime_transport.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec('SET FOREIGN_KEY_CHECKS=0');

    $admin_id_type = mig_get_column_type($db, 'admin', 'id');
    if ($admin_id_type === '') {
        $admin_id_type = 'int(11)';
    }

    mig_add_column_if_missing(
        $db,
        'bulletin_paie_parametres',
        'prime_transport_mensuelle',
        "DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Montant mensuel de référence de la prime de transport'"
    );

    mig_safe_exec($db, "
CREATE TABLE IF NOT EXISTS `employe_prime_transport_retraits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `mois_paie` CHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
  `nb_jours` SMALLINT UNSIGNED NOT NULL,
  `montant_deduit` DECIMAL(12,2) NOT NULL,
  `commentaire` VARCHAR(500) NULL DEFAULT NULL,
  `admin_id` $admin_id_type NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emp_transport_employe_mois` (`employe_id`, `mois_paie`),
  KEY `idx_emp_transport_date` (`date_creation`),
  KEY `idx_emp_transport_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'table employe_prime_transport_retraits');

    $db->exec('SET FOREIGN_KEY_CHECKS=1');

    $db->exec("
        UPDATE bulletin_paie_parametres
        SET prime_transport_mensuelle = 0
        WHERE id = 1 AND (prime_transport_mensuelle IS NULL)
    ");

    echo "Migration prime transport bulletin terminée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
