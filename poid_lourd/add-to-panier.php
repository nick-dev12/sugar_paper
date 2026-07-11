<?php
/**
 * Traitement de l'ajout direct au panier depuis les cartes produits
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/controllers/controller_panier.php';
require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/flash_toast.php';
require_once __DIR__ . '/models/model_admin.php';
require_once __DIR__ . '/includes/boutique_slug_redirect.php';

$boutique_slug_redirect = isset($_POST['boutique_slug']) ? trim((string) $_POST['boutique_slug']) : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['produit_id'])) {
    http_redirect_safe('/index.php');
}

$result = process_add_to_panier();

if ($result['success']) {
    flash_toast_push('success', 'Produit ajouté au panier avec succès.');
    if ($boutique_slug_redirect !== '') {
        $adm_bs = resolve_vendeur_by_boutique_slug($boutique_slug_redirect);
        if ($adm_bs) {
            $current_slug = trim((string) ($adm_bs['boutique_slug'] ?? $boutique_slug_redirect));
            http_redirect_safe(boutique_url('panier.php', $current_slug));
        }
    }
    $return_url = isset($_POST['return_url']) && $_POST['return_url'] !== '' ? (string) $_POST['return_url'] : '';
    if ($return_url !== '' && $return_url[0] === '/' && strpos($return_url, '//') === false) {
        $sep = strpos($return_url, '?') !== false ? '&' : '?';
        http_redirect_safe($return_url . $sep . 'added=1');
    }
    http_redirect_safe('/panier.php');
}

flash_toast_push('error', $result['message'] ?? 'Impossible d\'ajouter ce produit au panier.');
$return_url = isset($_POST['return_url']) && $_POST['return_url'] !== '' ? (string) $_POST['return_url'] : '/index.php';
if ($return_url === '' || $return_url[0] !== '/' || strpos($return_url, '//') !== false) {
    $return_url = '/index.php';
}
http_redirect_safe($return_url);
