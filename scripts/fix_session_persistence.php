<?php
/**
 * Migration : session_start() → session_start_persistent() sur le site principal.
 * Exclut vendor, poid_lourd, Fouta, node_modules.
 * Usage : php scripts/fix_session_persistence.php
 */

$root = dirname(__DIR__);
$excludeDirs = ['vendor', 'poid_lourd', 'Fouta', 'node_modules', '.git', 'scripts'];

/**
 * @return Generator<string>
 */
function iter_php_files(string $dir, array $excludeDirs): Generator
{
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if (in_array($item, $excludeDirs, true)) {
                continue;
            }
            yield from iter_php_files($path, $excludeDirs);
            continue;
        }
        if (substr($item, -4) === '.php') {
            yield $path;
        }
    }
}

function relative_require_to_session_user(string $file, string $root): string
{
    $dir = dirname($file);
    $rel = str_replace('\\', '/', substr($dir, strlen($root)));
    $rel = trim($rel, '/');
    if ($rel === '') {
        return "__DIR__ . '/includes/session_user.php'";
    }
    $depth = substr_count($rel, '/') + 1;
    $up = str_repeat('../', $depth);
    return "__DIR__ . '/{$up}includes/session_user.php'";
}

$updated = 0;
$skipped = 0;

foreach (iter_php_files($root, $excludeDirs) as $file) {
    $content = file_get_contents($file);
    if ($content === false || $content === '') {
        continue;
    }

    // Ignorer le module session lui-même et les scripts de migration
    $norm = str_replace('\\', '/', $file);
    if (strpos($norm, '/includes/session_user.php') !== false) {
        continue;
    }

    if (strpos($content, 'session_start') === false) {
        continue;
    }

    // Déjà entièrement migré (pas de session_start() nu)
    if (strpos($content, 'session_start();') === false
        && !preg_match('/session_status\(\)\s*===\s*PHP_SESSION_NONE.*?session_start\(\)/s', $content)) {
        $skipped++;
        continue;
    }

    $original = $content;
    $requireExpr = relative_require_to_session_user($file, $root);

    if (strpos($content, 'session_user.php') === false) {
        if (preg_match('/^<\?php\r?\n/', $content)) {
            $content = preg_replace(
                '/^<\?php\r?\n/',
                "<?php\nrequire_once {$requireExpr};\n",
                $content,
                1
            );
        } elseif (preg_match('/^<\?php\s+/', $content)) {
            $content = preg_replace(
                '/^<\?php\s+/',
                "<?php\nrequire_once {$requireExpr};\n",
                $content,
                1
            );
        }
    }

    $content = str_replace('session_start();', 'session_start_persistent();', $content);
    $content = preg_replace(
        '/if\s*\(\s*session_status\(\)\s*===\s*PHP_SESSION_NONE\s*\)\s*\{\s*session_start_persistent\(\);\s*\}/',
        'session_start_persistent();',
        $content
    );
    $content = preg_replace(
        '/if\s*\(\s*session_status\(\)\s*===\s*PHP_SESSION_NONE\s*\)\s*\{\s*session_start\(\);\s*\}/',
        'session_start_persistent();',
        $content
    );

    if ($content !== $original) {
        file_put_contents($file, $content);
        $relPath = str_replace('\\', '/', substr($file, strlen($root) + 1));
        echo "Updated: {$relPath}\n";
        $updated++;
    }
}

echo "Done. Updated={$updated} already_ok_or_skipped={$skipped}\n";
