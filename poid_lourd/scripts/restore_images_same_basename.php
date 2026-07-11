<?php
/**
 * Restaure la règle : même nom de fichier, seule l'extension passe en .webp
 * (ex. produits/produit_abc.jpg → produits/produit_abc.webp).
 *
 * À partir d'un log batch « OK ancien.png → dossier/img_xxx.webp » :
 * - renomme img_xxx.webp → ancien_stem.webp (+ variantes _md / _sm)
 * - met à jour la BDD (ancien chemin → chemin .webp)
 *
 * Usage :
 *   php scripts/restore_images_same_basename.php chemin/vers/log.txt
 *   php scripts/restore_images_same_basename.php --dry-run chemin/vers/log.txt
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$dry_run = in_array('--dry-run', $argv, true);
$log_file = null;
foreach ($argv as $i => $arg) {
    if ($i === 0 || $arg === '--dry-run') {
        continue;
    }
    $log_file = $arg;
    break;
}

if ($log_file === null || !is_file($log_file)) {
    fwrite(STDERR, "Usage : php scripts/restore_images_same_basename.php [--dry-run] log.txt\n");
    exit(1);
}

$conn = __DIR__ . '/../conn/conn.php';
if (!is_file($conn)) {
    fwrite(STDERR, "conn/conn.php introuvable.\n");
    exit(1);
}
require_once $conn;
require_once __DIR__ . '/../includes/image_optimizer_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "PDO indisponible.\n");
    exit(1);
}

$upload_root = realpath(__DIR__ . '/../upload');
if ($upload_root === false) {
    fwrite(STDERR, "upload/ introuvable.\n");
    exit(1);
}

/**
 * @return list<array{old:string,new:string,target:string}>
 */
function restore_parse_log($content) {
    $rows = [];
    foreach (preg_split('/\R/', $content) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/OK\s+(\S+)\s+(?:→|->)\s+(\S+)/u', $line, $m)) {
            $json = json_decode($line, true);
            if (is_array($json) && !empty($json['old']) && !empty($json['new'])) {
                $old = (string) $json['old'];
                $new = (string) $json['new'];
            } else {
                continue;
            }
        } else {
            $old = $m[1];
            $new = $m[2];
        }
        $old = trim(str_replace('\\', '/', $old), '/');
        $new = trim(str_replace('\\', '/', $new), '/');
        $dir = dirname($old);
        $stem = pathinfo($old, PATHINFO_FILENAME);
        if ($stem === '') {
            continue;
        }
        $target = ($dir === '.' || $dir === '') ? $stem . '.webp' : $dir . '/' . $stem . '.webp';
        $rows[] = ['old' => $old, 'new' => $new, 'target' => $target];
    }
    return $rows;
}

$content = file_get_contents($log_file);
if ($content === false) {
    fwrite(STDERR, "Lecture impossible.\n");
    exit(1);
}

$mappings = restore_parse_log($content);
if (empty($mappings)) {
    fwrite(STDERR, "Aucune ligne OK ancien → nouveau trouvée.\n");
    exit(1);
}

$renamed = 0;
$db_updates = 0;

foreach ($mappings as $map) {
    $old = $map['old'];
    $wrong_new = $map['new'];
    $target = $map['target'];

    if ($wrong_new === $target) {
        if (!$dry_run) {
            image_db_apply_path_mapping($db, $old, $target);
            image_db_apply_path_mapping($db, $wrong_new, $target);
        }
        $db_updates++;
        echo "BDD seulement : {$old} → {$target}\n";
        continue;
    }

    $wrong_base = pathinfo($wrong_new, PATHINFO_FILENAME);
    $target_base = pathinfo($target, PATHINFO_FILENAME);
    $dir_abs = $upload_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, dirname($target));

    $suffixes = ['' => '', '_md' => '_md', '_sm' => '_sm'];
    foreach ($suffixes as $src_suffix => $tgt_suffix) {
        $src_name = $wrong_base . ($src_suffix === '' ? '' : $src_suffix) . '.webp';
        $tgt_name = $target_base . $tgt_suffix . '.webp';
        $src_abs = $dir_abs . DIRECTORY_SEPARATOR . $src_name;
        $tgt_abs = $dir_abs . DIRECTORY_SEPARATOR . $tgt_name;

        if (!is_file($src_abs)) {
            continue;
        }
        if (is_file($tgt_abs) && realpath($src_abs) !== realpath($tgt_abs)) {
            fwrite(STDERR, "Conflit : {$tgt_abs} existe déjà.\n");
            continue;
        }
        echo ($dry_run ? '[dry-run] ' : '') . "fichier {$src_name} → {$tgt_name}\n";
        if (!$dry_run) {
            if (!@rename($src_abs, $tgt_abs)) {
                fwrite(STDERR, "Échec rename {$src_abs}\n");
            } else {
                $renamed++;
            }
        }
    }

    if (!$dry_run) {
        image_db_apply_path_mapping($db, $old, $target);
        image_db_apply_path_mapping($db, $wrong_new, $target);
    }
    echo "BDD : {$old} → {$target}\n";
    $db_updates++;
}

echo "\nTerminé : {$renamed} fichier(s) renommé(s), {$db_updates} entrée(s) BDD traitée(s).\n";
if ($dry_run) {
    echo "(Dry-run — aucune modification réelle.)\n";
}
