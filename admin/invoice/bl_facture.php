<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Facture B2B (bon de livraison) — même rendu que admin/devis/facture.php
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

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';

$bl_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bl_id <= 0 || !bl_tables_available()) {
    header('Location: index.php?tab=facture');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['marquer_facture_payee'])) {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if (!admin_can_bl_retours_b2b()) {
        $_SESSION['flash_facture_error'] = 'Action non autorisée.';
    } elseif ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $_SESSION['flash_facture_error'] = 'Session expirée. Réessayez.';
    } else {
        $r = marquer_bl_facture_payee($bl_id);
        if (!empty($r['ok'])) {
            $_SESSION['success_message'] = 'Facture marquée comme payée.';
        } else {
            $_SESSION['flash_facture_error'] = $r['error'] ?? 'Action impossible.';
        }
    }
    header('Location: bl_facture.php?id=' . $bl_id);
    exit;
}

$bl = get_bl_by_id($bl_id);
if (!$bl) {
    header('Location: index.php?tab=facture');
    exit;
}

$bl_valide = bl_est_statut_verrouille($bl['statut'] ?? 'brouillon');
if ($bl_valide) {
    bl_attribuer_reference_fpl_si_besoin($bl_id);
    $bl = get_bl_by_id($bl_id);
}

$lignes = get_lignes_bl($bl_id);
$total_ht = (float) ($bl['total_ht'] ?? 0);
$remise_globale_pct = (float) ($bl['remise_globale_pct'] ?? 0);

$tva_incl = bl_tva_columns_ok() && !empty($bl['tva_incluse']);
$taux_bl = (bl_tva_columns_ok() && isset($bl['taux_tva_pourcent']) && (float) $bl['taux_tva_pourcent'] > 0)
    ? (float) $bl['taux_tva_pourcent']
    : null;
$decomp = fiscal_decomposer_net_ht($total_ht, $tva_incl, $taux_bl);

$d_bl = strtotime($bl['date_bl'] ?? 'now');
$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$date_facture_aff = date('j', $d_bl) . ' ' . $mois[(int) date('n', $d_bl) - 1] . ' ' . date('Y', $d_bl);

$produits = [];
foreach ($lignes as $l) {
    $ref_fpl = '';
    $pid = (int) ($l['produit_id'] ?? 0);
    if ($pid > 0 && function_exists('produits_has_column') && produits_has_column('identifiant_interne')) {
        $pr = get_produit_by_id($pid);
        if ($pr && !empty($pr['identifiant_interne'])) {
            $ref_fpl = strtoupper(trim((string) $pr['identifiant_interne']));
        }
    }
    $produits[] = [
        'produit_nom' => $l['designation'] ?? '',
        'nom' => $l['designation'] ?? '',
        'prix_unitaire' => (float) ($l['prix_unitaire_ht'] ?? 0),
        'quantite' => $l['quantite'] ?? 0,
        'prix_total' => (float) ($l['total_ligne_ht'] ?? 0),
        'ref_fpl' => $ref_fpl,
        'identifiant_interne' => $ref_fpl,
    ];
}

$bl_facture_payee = bl_est_facture_payee($bl);
$client_b2b_id = (int) ($bl['client_b2b_id'] ?? 0);

$facture = [
    'numero_facture' => $bl['numero_bl'] ?? '',
    'montant_total' => $tva_incl ? $decomp['montant_ttc'] : $total_ht,
    'commande_id' => 0,
    'tva_incluse' => $tva_incl ? 1 : 0,
    'montant_ht' => $decomp['montant_ht'],
    'montant_tva' => $decomp['montant_tva'],
    'taux_tva_pourcent' => $taux_bl ?? fiscal_taux_tva_pourcent(),
    'payee' => $bl_facture_payee ? 1 : 0,
];
$facture_tva_incluse = $tva_incl;
$facture_fiscal_ht = $decomp['montant_ht'];
$facture_fiscal_tva = $decomp['montant_tva'];
$facture_fiscal_taux = $taux_bl ?? fiscal_taux_tva_pourcent();

$adresse_client = trim((string) ($bl['adresse_client'] ?? ''));
if ($adresse_client === '') {
    $adresse_client = trim((string) ($bl['client_adresse'] ?? ''));
}

$commande = [
    'notes' => $bl['notes'] ?? '',
    'frais_livraison' => 0,
    'remise_globale_pct' => $remise_globale_pct,
    'adresse_client' => $adresse_client,
];

$client_nom = trim($bl['raison_sociale'] ?? '');
$client_telephone = $bl['client_telephone'] ?? '';
$adresse_livraison = $adresse_client;

$entreprise_nom = 'Sugar Paper';
$entreprise_rc = 'SN.DKR.2022.A.702';
$entreprise_ninea = '009116684';
$entreprise_adresse = 'Hlm Hann Maristes';
$entreprise_tel1 = '774161212';
$entreprise_tel2 = '773292123';
$entreprise_site = 'https://www.sugar-paper.com';
$entreprise_email = 'sugarpaper26@gmail.com';

$is_public = false;
$whatsapp_url = '';
$facture_back_url = $client_b2b_id > 0
    ? 'bl_voir.php?id=' . $bl_id
    : 'index.php?tab=facture';
$facture_back_label = 'Retour aux détails';

$facture_page_flash_success = '';
if (!empty($_SESSION['success_message'])) {
    $facture_page_flash_success = (string) $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
$facture_page_flash_error = '';
if (!empty($_SESSION['flash_facture_error'])) {
    $facture_page_flash_error = (string) $_SESSION['flash_facture_error'];
    unset($_SESSION['flash_facture_error']);
}

$facture_est_payee = $bl_facture_payee;
$facture_afficher_marquer_payee = admin_can_bl_retours_b2b()
    && bl_col_facture_payee_ok()
    && !$bl_facture_payee;
$facture_csrf_token = (string) ($_SESSION['admin_csrf'] ?? '');
$facture_marquer_payee_confirm = 'Confirmer le paiement ? Cette facture ne sera plus proposée dans les factures mensuelles groupées.';

$facture_document_type_label = 'FACTURE';
$facture_numero_affichage = bl_numero_document_affichage($bl);
$facture_recap_label_total = $tva_incl ? 'TOTAL TTC' : 'TOTAL';

require __DIR__ . '/../../includes/facture_content.php';
