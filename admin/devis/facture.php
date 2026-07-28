<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Affichage d'une facture de devis (admin, design identique à facture commande)
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$facture_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($facture_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_factures_devis.php';
require_once __DIR__ . '/../../models/model_devis.php';
require_once __DIR__ . '/../../includes/site_url.php';

$facture = get_facture_devis_by_id($facture_id);
if (!$facture) {
    header('Location: index.php');
    exit;
}

$token = $facture['token'] ?? null;
if (empty($token)) {
    $token = ensure_facture_devis_token($facture_id);
    if ($token) {
        $facture = get_facture_devis_by_id($facture_id);
    }
}

$devis = get_devis_by_id($facture['devis_id']);
$produits = get_produits_by_devis($facture['devis_id']);
$produits = is_array($produits) ? $produits : [];

// Construire une structure compatible avec facture_content (commande-like)
$commande = [
    'user_prenom' => $devis['client_prenom'] ?? '',
    'user_nom' => $devis['client_nom'] ?? '',
    'user_telephone' => $devis['client_telephone'] ?? '',
    'telephone_livraison' => $devis['client_telephone'] ?? '',
    'adresse_livraison' => $devis['adresse_livraison'] ?? '',
    'notes' => $devis['notes'] ?? '—',
    'frais_livraison' => $devis['frais_livraison'] ?? 0,
    'remise_globale_pct' => (float) ($devis['remise_globale_pct'] ?? 0),
    'numero_commande' => $devis['numero_devis'] ?? ''
];

$client_nom = trim(($devis['client_prenom'] ?? '') . ' ' . ($devis['client_nom'] ?? ''));
$client_telephone = $devis['client_telephone'] ?? '';
$adresse_livraison = $devis['adresse_livraison'] ?? '';

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

// Lien public de la facture (page facture devis publique)
$base_url = get_site_base_url();
$facture_url = $base_url . '/facture-devis.php?token=' . ($token ?? '');
$facture_share_url = $facture_url;
$facture_share_title = 'Facture ' . ($facture['numero_facture'] ?? '');
if (!empty($devis['numero_devis']) && empty($commande['numero_commande'])) {
    $commande['numero_commande'] = (string) $devis['numero_devis'];
}
/* Message détaillé généré dans facture_content.php (partage natif + WhatsApp desktop) */
$whatsapp_url = '';

$entreprise_nom = 'Sugar Paper';
$entreprise_rc = 'SN.DKR.2022.A.702';
$entreprise_ninea = '009116684';
$entreprise_adresse = 'Hlm Hann Maristes';
$entreprise_tel1 = '774161212';
$entreprise_tel2 = '773292123';
$entreprise_site = 'https://www.sugar-paper.com';
$entreprise_email = 'sugarpaper26@gmail.com';

$is_public = false;
$facture_back_url = 'details.php?id=' . $devis['id'];
$facture_back_label = 'Retour au devis';
require __DIR__ . '/../../includes/facture_content.php';