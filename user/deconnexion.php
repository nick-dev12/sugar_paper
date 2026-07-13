<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Page de déconnexion utilisateur
 * Programmation procédurale uniquement
 */

session_start_persistent();

// Supprimer les tokens FCM du client avant déconnexion
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../models/model_fcm.php';
    delete_fcm_tokens_by_user((int) $_SESSION['user_id']);
}

// Détruire toutes les variables de session
$_SESSION = array();

// Effacer le cookie de session (mêmes attributs que la session persistante)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] !== '' ? $params['path'] : '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool) ($params['secure'] ?? false),
        'httponly' => (bool) ($params['httponly'] ?? true),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

// Finalement, détruire la session
session_destroy();

// Rediriger vers la page d'accueil
header('Location: /index.php');
exit;

?>

