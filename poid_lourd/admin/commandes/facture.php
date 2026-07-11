<?php
/**
 * Affichage d'une facture (admin, design COLObanes)
 */
require_once __DIR__ . '/../includes/require_admin_session.php';


require_once __DIR__ . '/../includes/require_access.php';


$facture_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($facture_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_factures.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../includes/site_url.php';

$facture = get_facture_by_id($facture_id);
if (!$facture) {
    header('Location: index.php');
    exit;
}

$token = $facture['token'] ?? null;
if (empty($token)) {
    $token = ensure_facture_token($facture_id);
    if ($token) {
        $facture = get_facture_by_id($facture_id);
    }
}

$commande = get_commande_by_id($facture['commande_id']);
$produits = get_produits_by_commande($facture['commande_id']);
$produits = is_array($produits) ? $produits : [];

$client_nom = trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''));
$client_telephone = $commande['user_telephone'] ?? $commande['telephone_livraison'] ?? '';
$adresse_livraison = $commande['adresse_livraison'] ?? '';

// Normaliser le téléphone pour WhatsApp
$tel_whatsapp = preg_replace('/\D/', '', $client_telephone);
if (strlen($tel_whatsapp) === 9 && in_array(substr($tel_whatsapp, 0, 2), ['70', '76', '77', '78'])) {
    $tel_whatsapp = '221' . $tel_whatsapp;
} elseif (strlen($tel_whatsapp) === 10 && substr($tel_whatsapp, 0, 1) === '0') {
    $tel_whatsapp = '221' . substr($tel_whatsapp, 1);
}

$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$d_facture = strtotime($facture['date_facture']);
$date_facture_aff = date('j', $d_facture) . ' ' . $mois[(int) date('n', $d_facture) - 1] . ' ' . date('Y', $d_facture);

// Lien public de la facture
$base_url = get_site_base_url();
$facture_url = $base_url . '/facture.php?token=' . ($token ?? '');

require_once __DIR__ . '/../../includes/facture_branding.php';
$__facture_branding = facture_resolve_branding_from_commande($commande);
extract($__facture_branding, EXTR_SKIP);

// Message WhatsApp enrichi : produits, adresse livraison, montant total, lien facture
$lignes_produits = [];
foreach ($produits as $p) {
    $nom = $p['produit_nom'] ?? $p['nom'] ?? '';
    $qte = (int) ($p['quantite'] ?? 0);
    $lignes_produits[] = '- ' . $nom . ' x' . $qte;
}
$msg_whatsapp = "Bonjour " . $client_nom . ",\n\n"
    . "Votre facture n°" . $facture['numero_facture'] . " pour la commande #" . ($commande['numero_commande'] ?? '') . " est prête.\n\n"
    . "Produits commandés :\n" . implode("\n", $lignes_produits) . "\n\n"
    . "Adresse de livraison : " . str_replace(["\r", "\n"], ' ', $adresse_livraison) . "\n\n"
    . "Montant total : " . number_format($facture['montant_total'], 0, ',', ' ') . " CFA\n"
    . "Date : " . $date_facture_aff . "\n\n"
    . "Consultez votre facture en ligne :\n" . $facture_url . "\n\n"
    . "Cordialement,\n" . ($entreprise_nom ?? 'COLObanes');
$facture_share_url = $facture_url;
$facture_share_message = $msg_whatsapp;
$facture_share_title = 'Facture ' . ($facture['numero_facture'] ?? '');

$is_public = false;
require __DIR__ . '/../../includes/facture_content.php';