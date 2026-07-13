<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Traitement du formulaire de création de devis
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../invoice/index.php?tab=devis');
    exit;
}

require_once __DIR__ . '/../../models/model_devis.php';

$client_nom = trim($_POST['client_nom'] ?? '');
$client_prenom = '';
$client_telephone = trim($_POST['client_telephone'] ?? '');
$client_email = '';
$adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$zone_livraison_id = isset($_POST['zone_livraison_id']) && $_POST['zone_livraison_id'] !== '' && $_POST['zone_livraison_id'] !== 'custom' ? (int) $_POST['zone_livraison_id'] : null;
$frais_livraison = (float) ($_POST['frais_livraison'] ?? 0);
$remise_globale_pct = min(100, max(0, (float) str_replace(',', '.', $_POST['remise_globale_pct'] ?? '0')));
$user_id = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int) $_POST['user_id'] : null;

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
                'nom_produit' => isset($l['nom_produit']) ? trim($l['nom_produit']) : null
            ];
        }
    }
}

$erreur = null;
if (empty($client_nom)) $erreur = 'Le nom du client est requis.';
elseif (empty($client_telephone)) $erreur = 'Le téléphone du client est requis.';
elseif (empty($adresse_livraison)) $erreur = "L'adresse de livraison est requise.";
elseif (empty($items)) $erreur = 'Ajoutez au moins un produit au devis.';

if ($erreur) {
    $_SESSION['devis_erreur'] = $erreur;
    $_SESSION['devis_post'] = $_POST;
    header('Location: ../invoice/index.php?tab=devis&modal=devis');
    exit;
}

$result = create_devis($items, $client_nom, $client_prenom, $client_telephone, $adresse_livraison, $client_email ?: null, $notes ?: null, $zone_livraison_id, $frais_livraison, $user_id, $remise_globale_pct);

if ($result && $result['success']) {
    $_SESSION['success_message'] = 'Devis #' . $result['numero_devis'] . ' créé avec succès.';
    header('Location: details.php?id=' . $result['devis_id']);
    exit;
}

$_SESSION['devis_erreur'] = 'Erreur lors de l\'enregistrement du devis.';
$_SESSION['devis_post'] = $_POST;
header('Location: ../invoice/index.php?tab=devis&modal=devis');
