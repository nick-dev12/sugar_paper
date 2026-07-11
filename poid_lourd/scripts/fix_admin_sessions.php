<?php
/**
 * Migration sessions persistantes — espace admin / vendeur.
 * Usage : php scripts/fix_admin_sessions.php
 */

$root = dirname(__DIR__);
$files = array_merge(
    glob($root . '/admin/*.php') ?: [],
    glob($root . '/admin/**/*.php') ?: [],
    glob($root . '/super_admin/*.php') ?: []
);

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false || $content === '' || strpos($content, 'session_start') === false) {
        continue;
    }
    if (strpos($content, 'require_admin_session') !== false) {
        continue;
    }

    $original = $content;

    if (strpos($content, 'session_user.php') === false && strpos($content, 'session_admin.php') === false) {
        $content = preg_replace(
            '/^<\?php\r?\n/',
            "<?php\nrequire_once __DIR__ . '/../includes/session_admin.php';\n",
            $content,
            1
        );
        // super_admin paths
        if (strpos($file, DIRECTORY_SEPARATOR . 'super_admin' . DIRECTORY_SEPARATOR) !== false
            && strpos($content, 'session_admin.php') === false) {
            $content = preg_replace(
                '/^<\?php\r?\n/',
                "<?php\nrequire_once dirname(__DIR__) . '/includes/session_user.php';\n",
                $content,
                1
            );
        }
    }

    $content = str_replace('session_start();', 'session_start_persistent();', $content);
    $content = preg_replace(
        '/if \(session_status\(\) === PHP_SESSION_NONE\) \{\s*session_start\(\);\s*\}/',
        'session_start_persistent();',
        $content
    );

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo 'Updated: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file) . "\n";
    }
}

echo "Done.\n";
