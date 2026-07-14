<?php
/**
 * Colonne photo_profil sur la table admin (comptes d'accès).
 * Usage : php migrations/run_add_admin_photo_profil.php
 */
require_once __DIR__ . '/lib/migration_helpers.php';

$db = mig_connect();

mig_add_column_smart(
    $db,
    'admin',
    'photo_profil',
    "VARCHAR(400) NULL DEFAULT NULL COMMENT 'Chemin relatif sous upload/ (ex. admin_photos/admin_1_xxx.jpg)'",
    ['role', 'statut', 'derniere_connexion', 'auth_provider', 'firebase_uid']
);

$upload_dir = dirname(__DIR__) . '/upload/admin_photos';
if (!is_dir($upload_dir)) {
    if (@mkdir($upload_dir, 0755, true)) {
        echo "Dossier upload/admin_photos créé.\n";
    } else {
        echo "Avertissement : impossible de créer upload/admin_photos (créez-le manuellement).\n";
    }
}

$htaccess = $upload_dir . '/.htaccess';
if (is_dir($upload_dir) && !is_file($htaccess)) {
    @file_put_contents($htaccess, "Options -Indexes\n");
}

echo "Migration admin.photo_profil terminée.\n";
mig_mark_applied($db, 'admin_photo_profil', 'Photo de profil comptes admin');
