<?php
/**
 * Prépare un export phpMyAdmin pour import sur une base qui contient déjà les tables.
 *
 * Corrige notamment :
 *   #1050 — table déjà existante (DROP + IF NOT EXISTS)
 *   #6125 — FK sans PRIMARY KEY sur admin (PK injectée + FK en fin de fichier)
 *   #1068 — plusieurs PRIMARY KEY (suppression des ALTER ADD PRIMARY KEY en double)
 *
 * Actions :
 *   - SET FOREIGN_KEY_CHECKS = 0
 *   - DROP TABLE IF EXISTS avant chaque CREATE TABLE
 *   - CREATE TABLE IF NOT EXISTS + PRIMARY KEY (`id`) si manquante
 *   - Contraintes FOREIGN KEY reportées à la fin du script
 *
 * CLI :
 *   php migrations/preparer_import_sql.php "C:\chemin\export.sql"
 *   php migrations/preparer_import_sql.php export.sql sortie.sql tresor_afri
 *
 * Navigateur (WAMP) :
 *   http://localhost/poid_lourd/migrations/preparer_import_sql.php
 */

function preparer_import_sql_ensure_primary_keys($sql)
{
    return preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `([^`]+)`\s*\(([\s\S]*?)\)\s*ENGINE\s*=/i',
        function ($m) {
            $body = $m[2];
            if (preg_match('/\bPRIMARY\s+KEY\b/i', $body)) {
                return $m[0];
            }
            if (!preg_match('/`id`\s+int(?:\(\d+\))?\s+NOT\s+NULL/i', $body)) {
                return $m[0];
            }
            if (!preg_match('/\bAUTO_INCREMENT\b/i', $body)) {
                $body = preg_replace(
                    '/(`id`\s+int(?:\(\d+\))?\s+NOT\s+NULL)/i',
                    '$1 AUTO_INCREMENT',
                    $body,
                    1
                );
            }
            $body = rtrim($body, " \t,\n\r") . ",\n  PRIMARY KEY (`id`)";

            return 'CREATE TABLE IF NOT EXISTS `' . $m[1] . '` (' . $body . ') ENGINE=';
        },
        $sql
    );
}

function preparer_import_sql_strip_redundant_primary_alters($sql)
{
    $tablesWithPkInCreate = [];
    if (preg_match_all(
        '/CREATE TABLE IF NOT EXISTS `([^`]+)`\s*\(([\s\S]*?)\)\s*ENGINE/i',
        $sql,
        $creates,
        PREG_SET_ORDER
    )) {
        foreach ($creates as $create) {
            if (preg_match('/\bPRIMARY\s+KEY\s*\(\s*`?id`?\s*\)/i', $create[2])) {
                $tablesWithPkInCreate[$create[1]] = true;
            }
        }
    }

    return preg_replace_callback(
        '/ALTER\s+TABLE\s+`([^`]+)`\s+([\s\S]*?);/i',
        function ($m) use ($tablesWithPkInCreate) {
            $table = $m[1];
            if (empty($tablesWithPkInCreate[$table])) {
                return $m[0];
            }

            $clause = $m[2];
            if (!preg_match('/\bADD\s+PRIMARY\s+KEY\b/i', $clause)) {
                return $m[0];
            }

            $newClause = preg_replace(
                '/ADD\s+PRIMARY\s+KEY\s*\(\s*`?id`?\s*\)\s*,?\s*\n?/i',
                '',
                $clause,
                1
            );
            $newClause = trim($newClause);

            if ($newClause === '' || !preg_match('/\S/', $newClause)) {
                return '-- Index déjà dans CREATE TABLE `' . $table . '` (PRIMARY KEY non dupliquée)';
            }

            return 'ALTER TABLE `' . $table . '` ' . $newClause . ';';
        },
        $sql
    );
}

function preparer_import_sql_defer_foreign_keys($sql)
{
    $deferred = [];

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `([^`]+)`\s*\(([\s\S]*?)\)\s*ENGINE/i',
        function ($m) use (&$deferred) {
            $table = $m[1];
            $body = $m[2];
            if (!preg_match('/\bCONSTRAINT\b/i', $body) || !preg_match('/\bFOREIGN\s+KEY\b/i', $body)) {
                return $m[0];
            }
            preg_match_all(
                '/,?\s*CONSTRAINT\s+`([^`]+)`\s+(FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+`[^`]+`\s*\([^)]+\)(?:\s+ON\s+DELETE\s+[A-Z\s]+)?(?:\s+ON\s+UPDATE\s+[A-Z\s]+)?)/i',
                $body,
                $matches,
                PREG_SET_ORDER
            );
            foreach ($matches as $match) {
                $deferred[] = 'ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $match[1] . '` ' . trim($match[2]) . ';';
                $body = str_replace($match[0], '', $body);
            }
            $body = preg_replace('/,\s*,/', ',', $body);
            $body = preg_replace('/,\s*\n\s*\)/', "\n)", $body);

            return 'CREATE TABLE IF NOT EXISTS `' . $table . '` (' . $body . ') ENGINE';
        },
        $sql
    );

    $sql = preg_replace_callback(
        '/ALTER\s+TABLE\s+`([^`]+)`\s+([\s\S]*?);/i',
        function ($m) use (&$deferred) {
            $table = $m[1];
            $clause = $m[2];
            if (!preg_match('/\bFOREIGN\s+KEY\b/i', $clause)) {
                return $m[0];
            }

            preg_match_all(
                '/ADD\s+CONSTRAINT\s+`([^`]+)`\s+(FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+`[^`]+`\s*\([^)]+\)(?:\s+ON\s+DELETE\s+[A-Z\s]+)?(?:\s+ON\s+UPDATE\s+[A-Z\s]+)?)\s*,?/i',
                $clause,
                $matches,
                PREG_SET_ORDER
            );

            $remaining = $clause;
            foreach ($matches as $match) {
                $deferred[] = 'ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $match[1] . '` ' . trim(rtrim($match[2], ',')) . ';';
                $remaining = str_replace($match[0], '', $remaining);
            }

            $remaining = trim(preg_replace('/,\s*$/', '', trim($remaining)));
            if ($remaining === '' || !preg_match('/\S/', $remaining)) {
                return '-- [FK reportées en fin de fichier] `' . $table . '`';
            }

            return 'ALTER TABLE `' . $table . '` ' . $remaining . ';';
        },
        $sql
    );

    if ($deferred !== []) {
        $sql .= "\n\n-- =============================================================================\n";
        $sql .= "-- Contraintes FOREIGN KEY (après tables + clés primaires)\n";
        $sql .= "-- Corrige l'erreur #6125 (ex. fk_bl_admin -> admin.id)\n";
        $sql .= "-- =============================================================================\n\n";
        $sql .= implode("\n", $deferred) . "\n";
    }

    return $sql;
}

function preparer_import_sql_contenu($content, $renameDb = '')
{
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    if ($renameDb !== '') {
        $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $renameDb);
        $content = preg_replace(
            '/(--\s*Base de données\s*:\s*`?)[a-zA-Z0-9_]+(`?)/iu',
            '$1' . $safeDb . '$2',
            $content
        );
        $content = preg_replace(
            '/^\s*USE\s+`?[a-zA-Z0-9_]+`?\s*;/mi',
            'USE `' . $safeDb . '`;',
            $content,
            1
        );
    }

    $content = preg_replace(
        '/^\s*CREATE\s+DATABASE\b.*$/mi',
        '-- CREATE DATABASE désactivé (import sur base existante)',
        $content
    );

    $lines = explode("\n", $content);
    $result = [];
    $lineCount = count($lines);

    for ($i = 0; $i < $lineCount; $i++) {
        $line = $lines[$i];

        if (preg_match('/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i', $line, $m)) {
            $table = $m[1];
            $hasDrop = false;
            for ($j = max(0, $i - 5); $j < $i; $j++) {
                if (preg_match('/^\s*DROP\s+TABLE\s+IF\s+EXISTS\s+`' . preg_quote($table, '/') . '`\s*;/i', $lines[$j])) {
                    $hasDrop = true;
                    break;
                }
            }
            if (!$hasDrop) {
                $result[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
            }
            if (!preg_match('/IF\s+NOT\s+EXISTS/i', $line)) {
                $line = preg_replace(
                    '/^\s*CREATE\s+TABLE\s+/i',
                    'CREATE TABLE IF NOT EXISTS ',
                    $line,
                    1
                );
            }
        }

        $result[] = $line;
    }

    $body = implode("\n", $result);
    $body = preparer_import_sql_ensure_primary_keys($body);
    $body = preparer_import_sql_strip_redundant_primary_alters($body);
    $body = preparer_import_sql_defer_foreign_keys($body);

    $header = <<<'HDR'
-- =============================================================================
-- Fichier préparé pour import phpMyAdmin (base déjà existante)
-- Généré par migrations/preparer_import_sql.php
-- =============================================================================
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

HDR;

    $footer = <<<'FTR'

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
-- Fin import préparé

FTR;

    return $header . $body . $footer;
}

function preparer_import_sql_message($text, $isError = false)
{
    if (PHP_SAPI === 'cli') {
        $stream = defined('STDERR') ? STDERR : fopen('php://output', 'w');
        fwrite($stream, $text . "\n");
        if (!defined('STDERR')) {
            fclose($stream);
        }
        return;
    }
    echo '<p class="' . ($isError ? 'err' : 'ok') . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
}

// --- Mode navigateur (formulaire + téléchargement) ---
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier_sql'])) {
        $file = $_FILES['fichier_sql'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            preparer_import_sql_message('Erreur lors de l\'envoi du fichier.', true);
        } elseif ($file['size'] <= 0) {
            preparer_import_sql_message('Fichier vide.', true);
        } else {
            $raw = file_get_contents($file['tmp_name']);
            if ($raw === false) {
                preparer_import_sql_message('Impossible de lire le fichier.', true);
            } else {
                $renameDb = isset($_POST['rename_db']) ? trim((string) $_POST['rename_db']) : '';
                $final = preparer_import_sql_contenu($raw, $renameDb);
                $base = pathinfo($file['name'], PATHINFO_FILENAME);
                $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) . '_import_pret.sql';

                header('Content-Type: application/sql; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $downloadName . '"');
                header('Content-Length: ' . strlen($final));
                echo $final;
                exit;
            }
        }
    }

    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Préparer import SQL</title>
    <style>
        body { font-family: Poppins, sans-serif; max-width: 520px; margin: 2rem auto; padding: 0 1rem; color: #0D0D0D; }
        h1 { font-size: 1.25rem; color: #3564a6; }
        label { display: block; margin-top: 1rem; font-weight: 500; }
        input[type=file], input[type=text] { width: 100%; margin-top: 0.35rem; }
        button { margin-top: 1.25rem; background: #3564a6; color: #fff; border: 0; padding: 0.65rem 1.25rem; border-radius: 6px; cursor: pointer; }
        button:hover { background: #2d5690; }
        .hint { font-size: 0.9rem; color: #737373; margin-top: 0.5rem; }
        .err { color: #c26638; }
        .ok { color: #3564a6; }
    </style>
</head>
<body>
    <h1>Préparer un fichier SQL pour import</h1>
    <p class="hint">Corrige #1050 (table existante) et #6125 (FK / PRIMARY KEY admin) pour les dumps production.</p>
    <form method="post" enctype="multipart/form-data">
        <label>Fichier .sql exporté
            <input type="file" name="fichier_sql" accept=".sql,.txt" required>
        </label>
        <label>Nom de la base cible (optionnel, ex. tresor_afri)
            <input type="text" name="rename_db" placeholder="tresor_afri">
        </label>
        <p class="hint">Attention : le fichier généré contient DROP TABLE — les données existantes seront remplacées à l'import.</p>
        <button type="submit">Générer et télécharger</button>
    </form>
    <p class="hint">En ligne de commande : <code>php migrations/preparer_import_sql.php export.sql</code></p>
</body>
</html>
    <?php
    exit;
}

// --- Mode CLI ---
$input = $argv[1] ?? '';
$output = $argv[2] ?? '';
$renameDb = $argv[3] ?? '';

if ($input === '' || !is_readable($input)) {
    preparer_import_sql_message('Fichier source introuvable.', true);
    preparer_import_sql_message('Exemple : php migrations/preparer_import_sql.php export.sql export_pret.sql', true);
    exit(1);
}

if ($output === '') {
    $info = pathinfo($input);
    $output = $info['dirname'] . DIRECTORY_SEPARATOR . ($info['filename'] ?? 'dump') . '_import_pret.sql';
}

$content = file_get_contents($input);
if ($content === false) {
    preparer_import_sql_message('Impossible de lire le fichier.', true);
    exit(1);
}

$final = preparer_import_sql_contenu($content, $renameDb);

if (file_put_contents($output, $final) === false) {
    preparer_import_sql_message('Impossible d\'écrire : ' . $output, true);
    exit(1);
}

echo "Fichier prêt : $output\n";
echo "Étapes : phpMyAdmin > sélectionner votre base > Importer > choisir ce fichier.\n";
echo "Attention : DROP TABLE supprime les données existantes de chaque table recréée.\n";
