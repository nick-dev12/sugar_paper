<?php
/**
 * Garde-fous CLI : empêche TRUNCATE / vidage accidentel en production.
 * Usage dans les scripts : require_once + db_destructive_guard_*()
 */

/**
 * @return string[]
 */
function db_destructive_guard_protected_database_names()
{
    return [
        'jomas_paper',
    ];
}

/**
 * Bases considérées comme dev local (vider autorisé avec --vider-avant uniquement).
 *
 * @return string[]
 */
function db_destructive_guard_dev_database_names()
{
    return [
        'sugar',
        'tresor_afri',
        'tresor',
    ];
}

function db_destructive_guard_require_cli()
{
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "Ce script ne peut être exécuté qu'en ligne de commande (CLI).\n");
        exit(1);
    }
}

/**
 * @param PDO   $db
 * @param array $argv
 */
function db_destructive_guard_require_truncate_confirmation($db, array $argv)
{
    db_destructive_guard_require_cli();

    if (!$db instanceof PDO) {
        fwrite(STDERR, "Connexion BDD indisponible.\n");
        exit(1);
    }

    $dbName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName === '') {
        fwrite(STDERR, "Aucune base sélectionnée.\n");
        exit(1);
    }

    $expectedFlag = '--confirm-destructive=' . $dbName;
    $hasConfirm = in_array($expectedFlag, $argv, true);

    if (in_array($dbName, db_destructive_guard_dev_database_names(), true)) {
        return;
    }

    if (in_array($dbName, db_destructive_guard_protected_database_names(), true) || !$hasConfirm) {
        if (!$hasConfirm) {
            fwrite(STDERR, "Refus : opération destructive sur la base « {$dbName} ».\n");
            if (in_array($dbName, db_destructive_guard_protected_database_names(), true)) {
                fwrite(STDERR, "Cette base est marquée comme production protégée.\n");
            }
            fwrite(STDERR, "Pour confirmer explicitement, relancez avec :\n");
            fwrite(STDERR, "  {$expectedFlag}\n");
            exit(1);
        }
    }
}
