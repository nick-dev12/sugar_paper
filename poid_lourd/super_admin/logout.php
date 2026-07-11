<?php
/**
 * Déconnexion super administrateur
 */
require_once dirname(__DIR__) . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/paths.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: ' . super_admin_href('login.php'));
exit;
