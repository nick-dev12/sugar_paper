<?php
/**
 * Redirection accueil quand session client absente ou invalide.
 * Usage : php scripts/fix_user_logout_redirect.php
 */

$dir = dirname(__DIR__) . '/user';
$files = glob($dir . '/*.php') ?: [];

$guardBlock = <<<'PHP'
if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: connexion.php');
    exit;
}
PHP;

$guardReplacement = <<<'PHP'
if (!function_exists('auth_user_redirect_if_not_logged_in')) {
    require_once __DIR__ . '/../includes/auth_redirect.php';
}
auth_user_redirect_if_not_logged_in();
PHP;

$destroyBlock = <<<'PHP'
    session_destroy();
    header('Location: connexion.php');
    exit;
PHP;

$destroyReplacement = <<<'PHP'
    session_destroy();
    if (!function_exists('auth_redirect_to_site_home')) {
        require_once __DIR__ . '/../includes/auth_redirect.php';
    }
    auth_redirect_to_site_home();
PHP;

foreach ($files as $file) {
    if (basename($file) === 'deconnexion.php') {
        continue;
    }

    $content = file_get_contents($file);
    if ($content === false || $content === '') {
        continue;
    }

    $original = $content;
    $content = str_replace($guardBlock, $guardReplacement, $content);
    $content = str_replace($destroyBlock, $destroyReplacement, $content);
    $content = str_replace("header('Location: connexion.php');", "auth_redirect_to_site_home();", $content);

    if ($content !== $original) {
        if (strpos($content, 'auth_redirect.php') === false && strpos($content, 'auth_redirect_to_site_home') !== false) {
            $content = preg_replace(
                '/(require_once __DIR__ \. \'\/\.\.\/includes\/session_user\.php\';[\r\n]+session_start_persistent\(\);[\r\n]+)/',
                "$1\nif (!function_exists('auth_redirect_to_site_home')) {\n    require_once __DIR__ . '/../includes/auth_redirect.php';\n}\n",
                $content,
                1
            );
        }
        file_put_contents($file, $content);
        echo 'Updated: ' . basename($file) . "\n";
    }
}

echo "Done.\n";
