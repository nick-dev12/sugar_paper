<?php
/**
 * Renomme les img_*.webp orphelins vers le bon produit_*.webp (3 produits sans original).
 */
if (PHP_SAPI !== 'cli') exit(1);

require __DIR__ . '/../conn/conn.php';
require __DIR__ . '/../includes/image_optimizer_db.php';

$fixes = [
    'produits/img_715eb66af433fccc.webp' => 'produits/produit_6a0606bb65e1e8.90365256.webp',
    'produits/img_851cf64149c0cff2.webp' => 'produits/produit_6a174b30c58672.86103719.webp',
    'produits/img_a4e0d91f1aa498ae.webp' => 'produits/produit_6a174bbc373650.13898354.webp',
];

$root = realpath(__DIR__ . '/../upload');
foreach ($fixes as $wrong => $target) {
    $old_jpg = preg_replace('/\.webp$/i', '.jpg', $target);
    $wrong_base = pathinfo($wrong, PATHINFO_FILENAME);
    $target_base = pathinfo($target, PATHINFO_FILENAME);
    $dir = $root . DIRECTORY_SEPARATOR . 'produits';

    foreach (['', '_md', '_sm'] as $suffix) {
        $src = $dir . DIRECTORY_SEPARATOR . $wrong_base . $suffix . '.webp';
        $tgt = $dir . DIRECTORY_SEPARATOR . $target_base . $suffix . '.webp';
        if (!is_file($src)) {
            continue;
        }
        if (is_file($tgt)) {
            @unlink($tgt);
        }
        if (!@rename($src, $tgt)) {
            fwrite(STDERR, "Échec rename {$src}\n");
        } else {
            echo "fichier {$wrong_base}{$suffix}.webp → {$target_base}{$suffix}.webp\n";
        }
    }

    image_db_apply_path_mapping($db, $old_jpg, $target);
    image_db_apply_path_mapping($db, $wrong, $target);
    echo "BDD {$old_jpg} → {$target}\n";
}

echo "Terminé.\n";
