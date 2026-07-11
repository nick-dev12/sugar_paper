<?php
/**
 * Mise à jour d'un devis — mêmes champs que create.php (formulaire modal)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../invoice/index.php?tab=devis');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis()) {
    header('Location: ../dashboard.php');
    exit;
}

$devis_id = (int) ($_POST['devis_id'] ?? 0);
$redirect_edit = $devis_id > 0
    ? '../invoice/index.php?tab=devis&modal=devis&edit=' . $devis_id
    : '../invoice/index.php?tab=devis&modal=devis';
$redirect_details = $devis_id > 0 ? 'details.php?id=' . $devis_id : '../invoice/index.php?tab=devis';

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['devis_erreur'] = 'Session expirée. Réessayez.';
    header('Location: ' . $redirect_edit);
    exit;
}

if ($devis_id <= 0) {
    $_SESSION['devis_erreur'] = 'Devis introuvable.';
    header('Location: ../invoice/index.php?tab=devis');
    exit;
}

require_once __DIR__ . '/../../models/model_devis.php';

$client_nom = trim($_POST['client_nom'] ?? '');
$client_prenom = trim($_POST['client_prenom'] ?? '');
$client_telephone = trim($_POST['client_telephone'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$zone_livraison_id = isset($_POST['zone_livraison_id']) && $_POST['zone_livraison_id'] !== '' && $_POST['zone_livraison_id'] !== 'custom'
    ? (int) $_POST['zone_livraison_id'] : null;
$frais_livraison = (float) ($_POST['frais_livraison'] ?? 0);
$remise_globale_pct = min(100, max(0, (float) str_replace(',', '.', $_POST['remise_globale_pct'] ?? '0')));

$items = [];
if (!empty($_POST['lignes']) && is_array($_POST['lignes'])) {
    foreach (array_values($_POST['lignes']) as $l) {
        $produit_id = (int) ($l['produit_id'] ?? 0);
        $quantite = (int) ($l['quantite'] ?? 1);
        $prix_unitaire = (float) str_replace(',', '.', $l['prix_unitaire'] ?? '0');
        $prix_promotion = isset($l['prix_promotion']) && $l['prix_promotion'] !== '' ? (float) str_replace(',', '.', $l['prix_promotion']) : null;
        if ($produit_id > 0 && $quantite > 0 && $prix_unitaire > 0) {
            $items[] = [
                'produit_id' => $produit_id,
                'quantite' => $quantite,
                'prix_unitaire' => $prix_promotion ?? $prix_unitaire,
                'nom_produit' => isset($l['nom_produit']) ? trim($l['nom_produit']) : null,
            ];
        }
    }
}

$erreur = null;
if (empty($client_nom)) {
    $erreur = 'Le nom du client est requis.';
} elseif (empty($client_telephone)) {
    $erreur = 'Le téléphone du client est requis.';
} elseif (empty($adresse_livraison)) {
    $erreur = "L'adresse de livraison est requise.";
} elseif (empty($items)) {
    $erreur = 'Ajoutez au moins un produit au devis.';
}

if ($erreur) {
    $_SESSION['devis_erreur'] = $erreur;
    $_SESSION['devis_post'] = $_POST;
    header('Location: ' . $redirect_edit);
    exit;
}

$infos = [
    'client_nom' => $client_nom,
    'client_prenom' => $client_prenom,
    'client_telephone' => $client_telephone,
    'client_email' => $client_email,
    'adresse_livraison' => $adresse_livraison,
    'zone_livraison_id' => $zone_livraison_id,
    'frais_livraison' => $frais_livraison,
    'notes' => $notes,
    'remise_globale_pct' => $remise_globale_pct,
];

if (update_devis($devis_id, $items, $infos)) {
    $_SESSION['success_message'] = 'Devis mis à jour avec succès.';
    header('Location: ' . $redirect_details);
    exit;
}

$_SESSION['devis_erreur'] = 'Erreur lors de la mise à jour du devis (vérifiez le statut ou les données).';
$_SESSION['devis_post'] = $_POST;
header('Location: ' . $redirect_edit);
exit;
