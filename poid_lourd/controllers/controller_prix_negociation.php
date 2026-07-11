<?php
/**
 * Contrôleur — négociations de prix produit
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_prix_negociations.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_variantes.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/flash_toast.php';

/**
 * @param string $redirect
 * @return string
 */
function prix_negociation_safe_redirect($redirect)
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
 * Calcule le prix de référence serveur (doit correspondre à l'affichage client)
 *
 * @param array<string, mixed> $produit
 * @param array<string, mixed> $post
 * @return array{success:bool, prix?:float, options?:array<string, mixed>, message?:string}
 */
function prix_negociation_compute_reference_from_post($produit, $post)
{
    $produit_id = (int) ($produit['id'] ?? 0);
    $option_variante_id = isset($post['option_variante_id']) ? (int) $post['option_variante_id'] : 0;
    $option_couleur = isset($post['option_couleur']) ? trim((string) $post['option_couleur']) : '';
    $option_poids = isset($post['option_poids']) ? trim((string) $post['option_poids']) : '';
    $option_taille = isset($post['option_taille']) ? trim((string) $post['option_taille']) : '';
    $option_variante_nom = isset($post['option_variante_nom']) ? trim((string) $post['option_variante_nom']) : '';
    $option_variante_image = isset($post['option_variante_image']) ? trim((string) $post['option_variante_image']) : '';

    $poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
    $taille_options = parse_options_with_surcharge($produit['taille'] ?? null);
    $surcout_poids = get_surcharge_for_option($poids_options, $option_poids);
    $surcout_taille = get_surcharge_for_option($taille_options, $option_taille);

    $variante = null;
    if ($option_variante_id > 0) {
        $v = get_variante_by_id($option_variante_id);
        if ($v && (int) $v['produit_id'] === $produit_id) {
            $variante = $v;
        }
    }

    $prix_base = (float) ($produit['prix'] ?? 0);
    if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] > 0
        && (float) $produit['prix_promotion'] < $prix_base) {
        $prix_base = (float) $produit['prix_promotion'];
    }
    if ($variante) {
        $prix_base = !empty($variante['prix_promotion']) && (float) $variante['prix_promotion'] < (float) $variante['prix']
            ? (float) $variante['prix_promotion'] : (float) $variante['prix'];
    }

    $prix_reference = $prix_base + $surcout_poids + $surcout_taille;
    if ($prix_reference <= 0) {
        return ['success' => false, 'message' => 'Prix du produit invalide.'];
    }

    $posted_ref = isset($post['prix_reference']) && is_numeric($post['prix_reference']) ? (float) $post['prix_reference'] : null;
    if ($posted_ref !== null && $posted_ref > 0 && abs($posted_ref - $prix_reference) > max(1, $prix_reference * 0.02)) {
        return ['success' => false, 'message' => 'Le prix affiché a changé. Rechargez la page et réessayez.'];
    }

    $options = [
        'variante_id' => $variante ? (int) $variante['id'] : ($option_variante_id > 0 ? $option_variante_id : 0),
        'couleur' => $option_couleur,
        'poids' => $option_poids,
        'taille' => $option_taille,
        'variante_nom' => $variante ? (string) ($variante['nom'] ?? '') : $option_variante_nom,
        'variante_image' => $variante ? (string) ($variante['image'] ?? '') : $option_variante_image,
        'surcout_poids' => $surcout_poids,
        'surcout_taille' => $surcout_taille,
    ];

    return ['success' => true, 'prix' => $prix_reference, 'options' => $options];
}

/**
 * Client — soumettre une offre
 *
 * @return never
 */
function process_prix_negociation_client_propose()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_redirect_safe('/index.php');
    }

    if (empty($_SESSION['user_id'])) {
        flash_toast_push('error', 'Connectez-vous pour négocier un prix.');
        http_redirect_safe('/choix-connexion.php');
    }

    $redirect = prix_negociation_safe_redirect($_POST['redirect'] ?? '/index.php');
    $produit_id = isset($_POST['produit_id']) ? (int) $_POST['produit_id'] : 0;
    $prix_propose = isset($_POST['prix_propose']) && is_numeric($_POST['prix_propose']) ? (float) $_POST['prix_propose'] : 0;

    if ($produit_id <= 0 || !prix_negociations_table_exists()) {
        flash_toast_push('error', 'Négociation indisponible.');
        http_redirect_safe($redirect);
    }

    $produit = get_produit_by_id($produit_id);
    if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
        flash_toast_push('error', 'Ce produit n\'est pas disponible.');
        http_redirect_safe($redirect);
    }

    $admin_id = (int) ($produit['admin_id'] ?? 0);
    if ($admin_id <= 0 || !produit_prix_negociable($produit)) {
        flash_toast_push('error', 'Ce produit ne peut pas être négocié.');
        http_redirect_safe($redirect);
    }

    $ref = prix_negociation_compute_reference_from_post($produit, $_POST);
    if (empty($ref['success'])) {
        flash_toast_push('error', $ref['message'] ?? 'Données invalides.');
        http_redirect_safe($redirect);
    }

    $result = prix_negociation_submit_offer(
        (int) $_SESSION['user_id'],
        $admin_id,
        $produit_id,
        $ref['options'],
        (float) $ref['prix'],
        $prix_propose
    );

    if (empty($result['success'])) {
        flash_toast_push('error', $result['message'] ?? 'Impossible d\'envoyer votre offre.');
        http_redirect_safe($redirect);
    }

    require_once __DIR__ . '/../services/send_prix_negociation_notification.php';
    prix_negociation_try_notify_vendor((int) $result['id'], 'nouvelle_offre');

    flash_toast_push('success', 'Votre offre a été envoyée au vendeur.');
    http_redirect_safe($redirect);
}

/**
 * Client — commander au prix convenu
 *
 * @return never
 */
function process_prix_negociation_client_commander()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_redirect_safe('/user/mon-compte.php');
    }

    if (empty($_SESSION['user_id'])) {
        flash_toast_push('error', 'Connectez-vous pour continuer.');
        http_redirect_safe('/choix-connexion.php');
    }

    require_once __DIR__ . '/controller_panier.php';
    $negotiation_id = isset($_POST['negotiation_id']) ? (int) $_POST['negotiation_id'] : 0;
    $redirect = prix_negociation_safe_redirect($_POST['redirect'] ?? '/user/mon-compte.php');
    $result = process_add_to_panier_from_negociation($negotiation_id, (int) $_SESSION['user_id']);

    if (!empty($result['success'])) {
        flash_toast_push('success', $result['message'] ?? 'Produit ajouté au panier au prix convenu.');
        http_redirect_safe('/panier.php');
    }

    flash_toast_push('error', $result['message'] ?? 'Impossible d\'ajouter au panier.');
    http_redirect_safe($redirect);
}

/**
 * Vendeur — accepter / rejeter / contre-proposer
 *
 * @return never
 */
function process_prix_negociation_vendor_action()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_redirect_safe('/admin/dashboard.php');
    }

    if (empty($_SESSION['admin_id'])) {
        http_redirect_safe('/choix-connexion.php');
    }

    $admin_id = (int) $_SESSION['admin_id'];
    $action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
    $negotiation_id = isset($_POST['negotiation_id']) ? (int) $_POST['negotiation_id'] : 0;
    $redirect = prix_negociation_safe_redirect($_POST['redirect'] ?? '/admin/dashboard.php');

    if ($negotiation_id <= 0 || !prix_negociations_table_exists()) {
        flash_toast_push('error', 'Action invalide.');
        http_redirect_safe($redirect);
    }

    $row = prix_negociation_get_by_id($negotiation_id);
    if (!$row || (int) ($row['admin_id'] ?? 0) !== $admin_id) {
        flash_toast_push('error', 'Offre introuvable.');
        http_redirect_safe($redirect);
    }

    require_once __DIR__ . '/../services/send_prix_negociation_notification.php';

    if ($action === 'accept') {
        $res = prix_negociation_vendor_accept($negotiation_id, $admin_id);
        if (!empty($res['success'])) {
            prix_negociation_try_notify_client($negotiation_id, 'offre_acceptee');
            flash_toast_push('success', 'Offre acceptée. Le client a été notifié.');
        } else {
            flash_toast_push('error', $res['message'] ?? 'Échec.');
        }
        http_redirect_safe($redirect);
    }

    if ($action === 'reject_counter') {
        $prix_contre = isset($_POST['prix_contre']) && is_numeric($_POST['prix_contre']) ? (float) $_POST['prix_contre'] : 0;
        $res = prix_negociation_vendor_counter($negotiation_id, $admin_id, $prix_contre);
        if (!empty($res['success'])) {
            prix_negociation_try_notify_client($negotiation_id, 'contre_proposee');
            flash_toast_push('success', 'Contre-proposition envoyée au client.');
        } else {
            flash_toast_push('error', $res['message'] ?? 'Échec.');
        }
        http_redirect_safe($redirect);
    }

    if ($action === 'reject_final') {
        $res = prix_negociation_vendor_reject_final($negotiation_id, $admin_id);
        if (!empty($res['success'])) {
            prix_negociation_try_notify_client($negotiation_id, 'offre_refusee');
            flash_toast_push('success', 'Offre refusée. Le client a été notifié.');
        } else {
            flash_toast_push('error', $res['message'] ?? 'Échec.');
        }
        http_redirect_safe($redirect);
    }

    flash_toast_push('error', 'Action inconnue.');
    http_redirect_safe($redirect);
}
