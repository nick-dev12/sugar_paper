<?php
/**
 * Migration one-shot : sessions persistantes + garde auth user_id uniquement.
 * Usage : php scripts/fix_user_sessions.php
 */

$dir = dirname(__DIR__) . '/user';
$files = glob($dir . '/*.php') ?: [];

$guardOld = "!isset(\$_SESSION['user_id']) || !isset(\$_SESSION['user_email'])";
$guardNew = 'empty($_SESSION[\'user_id\']) || (int) $_SESSION[\'user_id\'] <= 0';

$loggedOld = "isset(\$_SESSION['user_id']) && isset(\$_SESSION['user_email'])";
$loggedNew = '!empty($_SESSION[\'user_id\']) && (int) $_SESSION[\'user_id\'] > 0';

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false || $content === '') {
        fwrite(STDERR, "SKIP empty: $file\n");
        continue;
    }

    $original = $content;

    if (strpos($content, 'session_user.php') === false) {
        $content = preg_replace(
            '/^<\?php\r?\n/',
            "<?php\nrequire_once __DIR__ . '/../includes/session_user.php';\n",
            $content,
            1
        );
    }

    $content = str_replace('session_start();', 'session_start_persistent();', $content);
    $content = str_replace($guardOld, $guardNew, $content);
    $content = str_replace($loggedOld, $loggedNew, $content);

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: " . basename($file) . "\n";
    }
}

echo "Done.\n";
