<?php
/**
 * Traitement abonnement / désabonnement boutique
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_boutique_abonnements.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/flash_toast.php';

/**
 * Valide une URL de redirection interne
 *
 * @param string $redirect
 * @return string
 */
function boutique_abonnement_safe_redirect($redirect)
{
    $redirect = trim((string) $redirect);
    if ($redirect === '') {
        return '/index.php';
    }
    if (strpos($redirect, '//') !== false || preg_match('#^https?://#i', $redirect)) {
        return '/index.php';
    }
    if ($redirect[0] !== '/') {
        $redirect = '/' . $redirect;
    }
    return $redirect;
}

/**
 * Traite POST subscribe / unsubscribe
 *
 * @return never
 */
function process_boutique_abonnement_action()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_redirect_safe('/index.php');
    }

    if (empty($_SESSION['user_id'])) {
        flash_toast_push('error', 'Connectez-vous pour vous abonner à une boutique.');
        http_redirect_safe('/choix-connexion.php');
    }

    $user_id = (int) $_SESSION['user_id'];
    $admin_id = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;
    $action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
    $redirect = boutique_abonnement_safe_redirect($_POST['redirect'] ?? '/index.php');

    if ($admin_id <= 0 || !in_array($action, ['subscribe', 'unsubscribe'], true)) {
        flash_toast_push('error', 'Action d\'abonnement invalide.');
        http_redirect_safe($redirect);
    }

    if (!boutique_abonnements_table_exists()) {
        flash_toast_push('error', 'Le système d\'abonnement n\'est pas encore disponible.');
        http_redirect_safe($redirect);
    }

    $vendeur = get_admin_by_id($admin_id);
    if (!$vendeur || ($vendeur['role'] ?? '') !== 'vendeur' || ($vendeur['statut'] ?? '') !== 'actif') {
        flash_toast_push('error', 'Cette boutique n\'est pas disponible.');
        http_redirect_safe($redirect);
    }

    $slug = trim((string) ($vendeur['boutique_slug'] ?? ''));
    if ($slug === '') {
        flash_toast_push('error', 'Cette boutique n\'a pas de vitrine publique.');
        http_redirect_safe($redirect);
    }

    $boutique_nom = trim((string) ($vendeur['boutique_nom'] ?? ''));
    if ($boutique_nom === '') {
        $boutique_nom = trim((string) ($vendeur['nom'] ?? 'Boutique'));
    }

    if ($action === 'subscribe') {
        if (boutique_abonnement_subscribe($user_id, $admin_id)) {
            flash_toast_push('success', 'Vous êtes abonné à la boutique « ' . $boutique_nom . ' ». Vous serez notifié des nouveautés.');
        } else {
            flash_toast_push('error', 'Impossible de vous abonner à cette boutique.');
        }
    } else {
        if (boutique_abonnement_unsubscribe($user_id, $admin_id)) {
            flash_toast_push('success', 'Vous n\'êtes plus abonné à « ' . $boutique_nom . ' ».');
        } else {
            flash_toast_push('error', 'Impossible de vous désabonner.');
        }
    }

    http_redirect_safe($redirect);
}
