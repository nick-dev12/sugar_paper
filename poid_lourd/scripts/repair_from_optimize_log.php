<?php
/**
 * Répare les chemins BDD à partir des lignes « OK ancien → nouveau » du batch optimize.
 *
 * Usage :
 *   php scripts/repair_from_optimize_log.php chemin/vers/log.txt
 *   php scripts/repair_from_optimize_log.php   (lit scripts/optimize_image_mapping.jsonl si présent)
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
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

$mappings = [];

if (isset($argv[1]) && is_file($argv[1])) {
    $content = file_get_contents($argv[1]);
    if ($content === false) {
        fwrite(STDERR, "Lecture impossible : {$argv[1]}\n");
        exit(1);
    }
    foreach (preg_split('/\R/', $content) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/OK\s+(\S+)\s+→\s+(\S+)/u', $line, $m)
            || preg_match('/OK\s+(\S+)\s+->\s+(\S+)/', $line, $m)) {
            $mappings[] = ['old' => $m[1], 'new' => $m[2]];
            continue;
        }
        $json = json_decode($line, true);
        if (is_array($json) && !empty($json['old']) && !empty($json['new'])) {
            $mappings[] = ['old' => (string) $json['old'], 'new' => (string) $json['new']];
        }
    }
} elseif (is_file(__DIR__ . '/optimize_image_mapping.jsonl')) {
    foreach (file(__DIR__ . '/optimize_image_mapping.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $json = json_decode($line, true);
        if (is_array($json) && !empty($json['old']) && !empty($json['new'])) {
            $mappings[] = ['old' => (string) $json['old'], 'new' => (string) $json['new']];
        }
    }
} else {
    fwrite(STDERR, "Aucun fichier log fourni.\n");
    fwrite(STDERR, "Usage : php scripts/repair_from_optimize_log.php terminals/5.txt\n");
    exit(1);
}

if (empty($mappings)) {
    fwrite(STDERR, "Aucune correspondance old→new trouvée dans le fichier.\n");
    exit(1);
}

$applied = 0;
foreach ($mappings as $map) {
    image_db_apply_path_mapping($db, $map['old'], $map['new']);
    echo $map['old'] . ' → ' . $map['new'] . "\n";
    $applied++;
}

echo "\n{$applied} correspondance(s) appliquée(s).\n";
$sync = image_db_sync_all_image_paths($db);
echo 'Sync complémentaire : ' . (int) $sync['updated'] . " mise(s) à jour.\n";
