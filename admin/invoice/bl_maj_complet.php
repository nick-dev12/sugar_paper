<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Mise à jour BL — mêmes champs / validation que bl_enregistrer.php (formulaire modal)
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_bl_retours_b2b()) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=facture');
    exit;
}

$bl_id = (int) ($_POST['bl_id'] ?? 0);
$redirect_edit = $bl_id > 0
    ? 'index.php?tab=facture&modal=bl&edit=' . $bl_id
    : 'index.php?modal=bl&tab=facture';
$redirect_voir = $bl_id > 0 ? 'bl_voir.php?id=' . $bl_id : 'index.php?tab=facture';

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['bl_erreur'] = 'Session expirée. Réessayez.';
    header('Location: ' . $redirect_edit);
    exit;
}

if ($bl_id <= 0) {
    $_SESSION['bl_erreur'] = 'Facture introuvable.';
    header('Location: index.php?tab=facture');
    exit;
}

$client_nom = trim($_POST['client_nom'] ?? '');
$client_prenom = '';
$client_telephone = trim($_POST['client_telephone'] ?? '');
$client_email = '';
$adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
$adresse_client = $adresse_livraison;
$notes = trim($_POST['notes'] ?? '');
$frais_livraison = (float) ($_POST['frais_livraison'] ?? 0);
$remise_globale_pct = min(100, max(0, (float) str_replace(',', '.', $_POST['remise_globale_pct'] ?? '0')));

$date_bl = trim($_POST['date_bl'] ?? '');
if ($date_bl === '') {
    $date_bl = date('Y-m-d');
}
$statut = $_POST['statut'] ?? 'brouillon';
if (!in_array($statut, ['brouillon', 'valide'], true)) {
    $statut = 'brouillon';
}

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
if ($client_nom === '') {
    $erreur = 'Le nom du client est requis.';
} elseif ($client_telephone === '') {
    $erreur = 'Le téléphone du client est requis.';
} elseif (empty($items)) {
    $erreur = 'Ajoutez au moins un produit.';
}

if ($erreur) {
    $_SESSION['bl_erreur'] = $erreur;
    $_SESSION['bl_post'] = $_POST;
    header('Location: ' . $redirect_edit);
    exit;
}

require_once __DIR__ . '/../../models/model_contacts.php';
require_once __DIR__ . '/../../models/model_clients_b2b.php';
require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_parametres_types_client.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';

if (!bl_tables_available()) {
    $_SESSION['bl_erreur'] = 'Tables BL non installées.';
    $_SESSION['bl_post'] = $_POST;
    header('Location: ' . $redirect_edit);
    exit;
}

$bl_exist = get_bl_by_id($bl_id);
if (!$bl_exist) {
    $_SESSION['bl_erreur'] = 'Facture introuvable.';
    header('Location: index.php?tab=facture');
    exit;
}

ensure_contact_from_bl(
    $client_nom,
    $client_prenom,
    $client_telephone,
    $client_email !== '' ? $client_email : null
);

$contact_row = get_contact_by_telephone($client_telephone);
$type_depuis_contact = $contact_row ? contacts_normalize_type_bl($contact_row['type_client_bl'] ?? 'standard') : 'standard';
$plafond_contact_ht = $contact_row ? (float) ($contact_row['plafond_bl_cumul_ht'] ?? 0) : 0.0;

$client = find_client_b2b_by_telephone($client_telephone);
if (!$client) {
    $rs = trim($client_prenom . ' ' . $client_nom);
    $cid = create_client_b2b([
        'raison_sociale' => $rs !== '' ? $rs : 'Client BL',
        'nom_contact' => $client_nom,
        'prenom_contact' => $client_prenom,
        'email' => $client_email !== '' ? $client_email : null,
        'telephone' => $client_telephone,
        'adresse' => $adresse_livraison,
        'notes' => 'Mis à jour depuis formulaire BL',
        'statut' => 'actif',
        'type_client_bl' => $type_depuis_contact,
        'admin_createur_id' => (int) ($_SESSION['admin_id'] ?? 0),
    ]);
    if (!$cid) {
        $_SESSION['bl_erreur'] = 'Impossible de mettre à jour la fiche client B2B.';
        $_SESSION['bl_post'] = $_POST;
        header('Location: ' . $redirect_edit);
        exit;
    }
    $client = get_client_b2b_by_id($cid);
} else {
    sync_client_b2b_type_bl_depuis_contact($client_telephone);
    $client = find_client_b2b_by_telephone($client_telephone) ?: $client;
    $rs = trim($client_prenom . ' ' . $client_nom);
    update_client_b2b_fiche((int) $client['id'], [
        'raison_sociale' => $rs !== '' ? $rs : (string) ($client['raison_sociale'] ?? 'Client BL'),
        'nom_contact' => $client_nom,
        'prenom_contact' => $client_prenom,
        'telephone' => $client_telephone,
        'adresse' => $adresse_livraison,
        'email' => $client_email,
    ]);
    $client = get_client_b2b_by_id((int) $client['id']) ?: $client;
}

$lignes = [];
foreach ($items as $it) {
    $designation = trim($it['nom_produit'] ?? '');
    if ($designation === '') {
        $designation = 'Produit';
    }
    $lignes[] = [
        'produit_id' => (int) $it['produit_id'],
        'designation' => $designation,
        'quantite' => (float) $it['quantite'],
        'prix_unitaire_ht' => (float) $it['prix_unitaire'],
    ];
}

if ($frais_livraison > 0) {
    $lignes[] = [
        'produit_id' => null,
        'designation' => 'Frais de livraison',
        'quantite' => 1,
        'prix_unitaire_ht' => $frais_livraison,
    ];
}

$total_bl_ht = bl_totaux_ht_lignes_manuel($lignes);
$total_bl_ht = fiscal_apply_remise_globale($total_bl_ht, $remise_globale_pct);
$verif_plafond = pct_verifier_bl_montant_autorise((int) $client['id'], $plafond_contact_ht, $total_bl_ht);
if (empty($verif_plafond['ok'])) {
    $_SESSION['bl_erreur'] = $verif_plafond['message'] ?? 'Plafond BL dépassé.';
    $_SESSION['bl_post'] = $_POST;
    header('Location: ' . $redirect_edit);
    exit;
}

$tva_incl = isset($_POST['inclure_tva']) && (string) $_POST['inclure_tva'] === '1';

$res = update_bl_complet(
    $bl_id,
    (int) $client['id'],
    $date_bl,
    $notes !== '' ? $notes : null,
    $lignes,
    $statut,
    $tva_incl,
    $adresse_client !== '' ? $adresse_client : null,
    $remise_globale_pct
);

if (!empty($res['success'])) {
    ensure_bl_facture_token($bl_id);
    $_SESSION['success_message'] = 'Facture ' . ($bl_exist['numero_bl'] ?? '') . ' mise à jour. La page publique affiche déjà les nouvelles données.';
    header('Location: ' . $redirect_voir);
    exit;
}

$_SESSION['bl_erreur'] = $res['message'] ?? 'Erreur lors de la mise à jour.';
$_SESSION['bl_post'] = $_POST;
header('Location: ' . $redirect_edit);
exit;
