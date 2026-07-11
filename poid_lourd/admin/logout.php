<?php
/**
 * Page de déconnexion administrateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_admin.php';
require_once __DIR__ . '/../includes/auth_redirect.php';
session_start_persistent();

// Supprimer les tokens FCM de l'admin avant déconnexion
if (isset($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../models/model_fcm.php';
    delete_fcm_tokens_by_admin((int) $_SESSION['admin_id']);
}

// Détruire toutes les variables de session
$_SESSION = array();

// Si vous voulez détruire complètement la session, effacez également
// le cookie de session.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

auth_clear_portal_cookie();
auth_revoke_vendeur_marketplace_visit();

auth_redirect_to_site_home();

?>

