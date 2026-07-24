<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Hub Invoice — Devis & factures (bons de livraison B2B)
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_invoice_hub()) {
    header('Location: ../dashboard.php');
    exit;
}

$invoice_archive_mode = !empty($INVOICE_ARCHIVE_MODE);
$invoice_archive_sql_mode = $invoice_archive_mode ? 'archived' : 'active';
if ($invoice_archive_mode && !admin_is_full_admin()) {
    header('Location: index.php');
    exit;
}
$invoice_hub_self = $invoice_archive_mode ? 'archives.php' : 'index.php';
$invoice_hub_qs_base = $invoice_archive_mode ? 'archives.php?' : 'index.php?';


require_once __DIR__ . '/../../models/model_devis.php';
require_once __DIR__ . '/../../models/model_zones_livraison.php';
require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_contacts.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';
$fiscal_tva_pourcent_devis_bl = fiscal_taux_tva_pourcent();

$contacts_recherche = '';
$contacts_redirect_qs = 'tab=contacts';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    if (empty($nom) || empty($telephone)) {
        $_SESSION['contacts_error'] = 'Le nom et le téléphone sont obligatoires.';
    } elseif (create_contact($nom, $prenom, $telephone, $email)) {
        $_SESSION['contacts_success'] = 'Contact ajouté avec succès.';
    } else {
        $_SESSION['contacts_error'] = 'Erreur lors de l\'ajout du contact.';
    }
    header('Location: ' . $invoice_hub_self . '?' . $contacts_redirect_qs);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {
    $id = (int) ($_POST['contact_id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    if ($id <= 0 || empty($nom) || empty($telephone)) {
        $_SESSION['contacts_error'] = 'Données invalides.';
    } elseif (update_contact($id, $nom, $prenom, $telephone, $email)) {
        $_SESSION['contacts_success'] = 'Contact modifié avec succès.';
    } else {
        $_SESSION['contacts_error'] = 'Erreur lors de la modification.';
    }
    header('Location: ' . $invoice_hub_self . '?' . $contacts_redirect_qs);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_contacts'])) {
    $json = $_POST['import_contacts_data'] ?? '';
    $data = json_decode($json, true);
    if (is_array($data) && count($data) > 0) {
        $result = import_contacts_from_array($data);
        $parts = [];
        $parts[] = (int) $result['imported'] . ' importé(s)';
        if ((int) $result['skipped'] > 0) {
            $parts[] = (int) $result['skipped'] . ' déjà existant(s)';
        }
        if ((int) $result['invalid'] > 0) {
            $parts[] = (int) $result['invalid'] . ' ignoré(s)';
        }
        if ((int) $result['imported'] > 0) {
            $_SESSION['contacts_success'] = 'Import terminé : ' . implode(', ', $parts) . '.';
        } elseif ((int) $result['skipped'] > 0) {
            $_SESSION['contacts_success'] = 'Aucun nouveau contact : ' . implode(', ', $parts) . '.';
        } else {
            $_SESSION['contacts_error'] = 'Aucun contact valide à importer.';
        }
    } else {
        $_SESSION['contacts_error'] = 'Aucun contact à importer.';
    }
    header('Location: ' . $invoice_hub_self . '?' . $contacts_redirect_qs);
    exit;
}

$contacts_success = $_SESSION['contacts_success'] ?? '';
$contacts_error = $_SESSION['contacts_error'] ?? '';
unset($_SESSION['contacts_success'], $_SESSION['contacts_error']);
$contacts_list = get_all_contacts($contacts_recherche);

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$zones_livraison = get_all_zones_livraison('actif');

$devis_total_count = admin_can_devis() ? count_all_devis() : 0;
$devis_list = admin_can_devis() ? get_all_devis() : [];

$bl_tables_ok = bl_tables_available();
$facture_total_count = ($bl_tables_ok && admin_can_bl_retours_b2b()) ? count_all_bl_invoices($invoice_archive_sql_mode) : 0;
$facture_list = ($bl_tables_ok && admin_can_bl_retours_b2b())
    ? get_all_bl_with_clients($invoice_archive_sql_mode)
    : [];

if ($bl_tables_ok) {
    $contacts_list = enrich_contacts_with_factures_stats($contacts_list);
}
$contacts_total_count = count($contacts_list);

$bl_erreur = $_SESSION['bl_erreur'] ?? null;
if (isset($_SESSION['bl_erreur'])) {
    unset($_SESSION['bl_erreur']);
}

$devis_erreur = $_SESSION['devis_erreur'] ?? null;
$devis_post = $_SESSION['devis_post'] ?? null;
if (isset($_SESSION['devis_erreur'])) {
    unset($_SESSION['devis_erreur']);
}

$show_modal_devis = isset($_GET['modal']) && $_GET['modal'] === 'devis';
$show_modal_bl = isset($_GET['modal']) && $_GET['modal'] === 'bl';

require_once __DIR__ . '/../../includes/invoice_form_prefill.php';

$bl_post = $_SESSION['bl_post'] ?? null;

$bl_edit_id = 0;
$bl_edit_lignes_json = '[]';
$bl_form_is_edit = false;
$bl_form_action = 'bl_enregistrer.php';
$bl_form_title = 'Nouvelle facture';
$bl_form_submit_label = 'Enregistrer la facture';

$devis_edit_id = 0;
$devis_edit_lignes_json = '[]';
$devis_form_is_edit = false;
$devis_form_action = '../devis/create.php';
$devis_form_title = 'Nouveau devis';
$devis_form_submit_label = 'Créer le devis';

if (isset($_GET['edit']) && (string) ($_GET['modal'] ?? '') === 'bl' && admin_can_bl_retours_b2b()) {
    $bl_edit_id = (int) $_GET['edit'];
    if ($bl_edit_id > 0) {
        if (!empty($bl_post) && is_array($bl_post)) {
            if (!empty($bl_post['bl_id'])) {
                $bl_edit_id = (int) $bl_post['bl_id'];
            }
            $prefill_bl_err = invoice_bl_prefill_for_modal($bl_edit_id);
            if ($prefill_bl_err) {
                $bl_edit_lignes_json = json_encode($prefill_bl_err['lignes'], JSON_UNESCAPED_UNICODE);
                if (empty($bl_post['client_nom'])) {
                    $bl_post = array_merge($prefill_bl_err['post'], $bl_post);
                }
                $bl_form_title = 'Modifier la facture ' . ($prefill_bl_err['numero_bl'] ?? '');
            } else {
                $bl_form_title = 'Modifier la facture';
            }
            $show_modal_bl = true;
            $bl_form_is_edit = true;
            $bl_form_action = 'bl_maj_complet.php';
            $bl_form_submit_label = 'Enregistrer les modifications';
        } else {
            $prefill_bl = invoice_bl_prefill_for_modal($bl_edit_id);
            if ($prefill_bl) {
                $bl_post = $prefill_bl['post'];
                $bl_edit_lignes_json = json_encode($prefill_bl['lignes'], JSON_UNESCAPED_UNICODE);
                $show_modal_bl = true;
                $bl_form_is_edit = true;
                $bl_form_action = 'bl_maj_complet.php';
                $bl_form_title = 'Modifier la facture ' . ($prefill_bl['numero_bl'] ?? '');
                $bl_form_submit_label = 'Enregistrer les modifications';
            }
        }
    }
}

if (isset($_GET['edit']) && (string) ($_GET['modal'] ?? '') === 'devis' && admin_can_devis()) {
    $devis_edit_id = (int) $_GET['edit'];
    if ($devis_edit_id > 0) {
        if (!empty($devis_post) && is_array($devis_post)) {
            if (!empty($devis_post['devis_id'])) {
                $devis_edit_id = (int) $devis_post['devis_id'];
            }
            $prefill_devis_err = invoice_devis_prefill_for_modal($devis_edit_id);
            if ($prefill_devis_err) {
                $devis_edit_lignes_json = json_encode($prefill_devis_err['lignes'], JSON_UNESCAPED_UNICODE);
                $devis_form_title = 'Modifier le devis #' . ($prefill_devis_err['numero_devis'] ?? '');
            } else {
                $devis_form_title = 'Modifier le devis';
            }
            $show_modal_devis = true;
            $devis_form_is_edit = true;
            $devis_form_action = '../devis/update.php';
            $devis_form_submit_label = 'Enregistrer les modifications';
        } else {
            $prefill_devis = invoice_devis_prefill_for_modal($devis_edit_id);
            if ($prefill_devis) {
                $devis_post = $prefill_devis['post'];
                $devis_edit_lignes_json = json_encode($prefill_devis['lignes'], JSON_UNESCAPED_UNICODE);
                $show_modal_devis = true;
                $devis_form_is_edit = true;
                $devis_form_action = '../devis/update.php';
                $devis_form_title = 'Modifier le devis #' . ($prefill_devis['numero_devis'] ?? '');
                $devis_form_submit_label = 'Enregistrer les modifications';
            }
        }
    }
}

if (isset($_SESSION['devis_post'])) {
    unset($_SESSION['devis_post']);
}
if (isset($_SESSION['bl_post'])) {
    unset($_SESSION['bl_post']);
}

$tab_param = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
$tab_allowed = ['devis', 'facture', 'contacts', 'rapports'];
if (!admin_can_devis() && !admin_can_bl_retours_b2b()) {
    $active_tab = in_array($tab_param, ['contacts', 'rapports'], true) ? $tab_param : 'contacts';
} elseif (!admin_can_devis()) {
    $default_tab = 'facture';
    if (in_array($tab_param, ['contacts', 'rapports'], true)) {
        $active_tab = $tab_param;
    } else {
        $active_tab = $tab_param === 'facture' ? 'facture' : $default_tab;
    }
} elseif (!admin_can_bl_retours_b2b()) {
    $active_tab = in_array($tab_param, $tab_allowed, true) ? $tab_param : 'devis';
} else {
    $active_tab = in_array($tab_param, $tab_allowed, true) ? $tab_param : 'facture';
}

$rapport_vue = isset($_GET['vue']) ? (string) $_GET['vue'] : 'paye';
$rapport_vue_allowed = ['paye', 'clients', 'articles'];
if (!in_array($rapport_vue, $rapport_vue_allowed, true)) {
    $rapport_vue = 'paye';
}
$rapport_annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');
if ($rapport_annee < 2000 || $rapport_annee > 2100) {
    $rapport_annee = (int) date('Y');
}
$rapport_annees = $bl_tables_ok ? get_annees_disponibles_rapport_factures() : [(int) date('Y') + 1, (int) date('Y'), (int) date('Y') - 1];
if (!in_array($rapport_annee, $rapport_annees, true)) {
    $rapport_annees[] = $rapport_annee;
    rsort($rapport_annees, SORT_NUMERIC);
}
$rapport_mensuel = $bl_tables_ok ? get_rapport_mensuel_factures_payees($rapport_annee, $invoice_archive_sql_mode) : ['mois' => [], 'total' => ['nb_clients' => 0, 'nb_factures' => 0, 'montant' => 0]];
$rapport_clients = $bl_tables_ok ? get_rapport_clients_factures_payees($rapport_annee, $invoice_archive_sql_mode) : [];
$rapport_articles = $bl_tables_ok ? get_rapport_articles_factures_payees($rapport_annee, $invoice_archive_sql_mode) : [];

/** Texte de recherche pour filtrage client (onglets devis / facture / contacts). */
function invoice_tab_search_blob(...$parts)
{
    $s = implode(' ', array_map('strval', $parts));
    return htmlspecialchars(mb_strtolower($s, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

$bl_modal_err = $bl_erreur;
if ($show_modal_bl && $bl_modal_err) {
    $bl_erreur = null;
}

/** Valeurs re-affichées dans le modal BL (mêmes clés que le devis + date_bl / statut) */
$bp = is_array($bl_post) ? $bl_post : [];
$devis_page_has_alert = isset($_SESSION['success_message']) || !empty($bl_erreur) || !empty($devis_erreur);

$invoice_hub_titles = [
    'facture' => ['label' => $invoice_archive_mode ? 'Factures archivées' : 'Factures', 'icon' => $invoice_archive_mode ? 'fa-box-archive' : 'fa-file-invoice-dollar'],
    'devis' => ['label' => 'Devis', 'icon' => 'fa-file-invoice'],
    'contacts' => ['label' => 'Clients', 'icon' => 'fa-address-book'],
    'rapports' => ['label' => $invoice_archive_mode ? 'Rapports (archives)' : 'Rapports', 'icon' => 'fa-chart-bar'],
];
$invoice_hub_title = $invoice_hub_titles[$active_tab] ?? $invoice_hub_titles['facture'];

$facture_montant_paye = 0.0;
$facture_montant_impaye = 0.0;
$facture_montant_livraison = 0.0;
$facture_lignes_totaux_map = [];
if ($bl_tables_ok && admin_can_bl_retours_b2b()) {
    $facture_bl_ids = array_map(function ($f_row) {
        return (int) ($f_row['id'] ?? 0);
    }, $facture_list);
    $facture_lignes_totaux_map = bl_prefetch_totaux_lignes_par_bl_ids($facture_bl_ids);
    foreach ($facture_list as $f_row) {
        $bl_id_row = (int) ($f_row['id'] ?? 0);
        $lignes_totaux_row = $facture_lignes_totaux_map[$bl_id_row] ?? null;
        $decomp_row = bl_decomposer_montant_facture($f_row, $lignes_totaux_row);
        $facture_montant_livraison += (float) $decomp_row['livraison'];
        if (bl_est_facture_payee($f_row)) {
            $facture_montant_paye += (float) $decomp_row['hors_livraison'];
        } else {
            $facture_montant_impaye += (float) $decomp_row['hors_livraison'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $invoice_archive_mode ? 'Archives Invoice' : 'Invoice'; ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-devis-compta-pages.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-invoice-onglets.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-devis-admin page-invoice-hub">
    <div class="content-header dashboard-hero page-devis-hero" id="invoice-hub-hero">
        <div class="dashboard-hero-text">
            <p class="dashboard-eyebrow">Espace commercial</p>
            <h1>
                <i class="fas <?php echo htmlspecialchars($invoice_hub_title['icon']); ?>" id="invoice-hub-hero-icon" aria-hidden="true"></i>
                <span id="invoice-hub-hero-label"><?php echo htmlspecialchars($invoice_hub_title['label']); ?></span>
            </h1>
        </div>
        <div class="header-actions">
            <?php if (admin_is_full_admin()): ?>
                <?php if ($invoice_archive_mode): ?>
                <a href="index.php?tab=facture" class="btn-secondary">
                    <i class="fas fa-file-invoice-dollar"></i> Invoice active
                </a>
                <?php else: ?>
                <a href="archives.php?tab=facture" class="btn-secondary">
                    <i class="fas fa-box-archive"></i> Archives
                </a>
                <?php endif; ?>
            <?php endif; ?>
            <?php include __DIR__ . '/../includes/btn_retour_site.php'; ?>
        </div>
    </div>

    <?php if ($devis_page_has_alert): ?>
    <div class="page-devis-alerts" role="region" aria-label="Messages">
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success page-devis-message">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($devis_erreur)): ?>
        <div class="message error page-devis-message">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($devis_erreur); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($bl_erreur)): ?>
        <div class="message error page-devis-message">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($bl_erreur); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($devis_page_has_alert): ?>
    </div>
    <?php endif; ?>

    <?php
    $tab_devis_active = $active_tab === 'devis';
    $tab_facture_active = $active_tab === 'facture';
    $tab_contacts_active = $active_tab === 'contacts';
    $tab_rapports_active = $active_tab === 'rapports';
    ?>
    <section class="content-section page-devis-section" aria-label="Devis et factures BL">
        <div class="section-header section-header--tabs page-devis-tabs-wrap">
            <div class="admin-devis-bl-tabs" role="tablist" aria-label="Devis, factures, clients et rapports">
                <?php if (admin_can_bl_retours_b2b()): ?>
                <button type="button" class="admin-tab admin-tab--bl <?php echo $tab_facture_active ? 'is-active' : ''; ?>" id="tab-btn-facture" role="tab" aria-selected="<?php echo $tab_facture_active ? 'true' : 'false'; ?>" aria-controls="panel-facture" data-tab="facture" <?php echo !$bl_tables_ok ? 'disabled title="Migration B2B requise"' : ''; ?>>
                    <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                    <span class="admin-tab__txt">Facture (<?php echo $bl_tables_ok ? (int) $facture_total_count : 0; ?>)</span>
                </button>
                <?php endif; ?>
                <?php if (admin_can_devis()): ?>
                <button type="button" class="admin-tab admin-tab--devis <?php echo $tab_devis_active ? 'is-active' : ''; ?>" id="tab-btn-devis" role="tab" aria-selected="<?php echo $tab_devis_active ? 'true' : 'false'; ?>" aria-controls="panel-devis" data-tab="devis">
                    <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-file-invoice"></i></span>
                    <span class="admin-tab__txt">Devis (<?php echo (int) $devis_total_count; ?>)</span>
                </button>
                <?php endif; ?>
                <button type="button" class="admin-tab admin-tab--contacts <?php echo $tab_contacts_active ? 'is-active' : ''; ?>" id="tab-btn-contacts" role="tab" aria-selected="<?php echo $tab_contacts_active ? 'true' : 'false'; ?>" aria-controls="panel-contacts" data-tab="contacts">
                    <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-address-book"></i></span>
                    <span class="admin-tab__txt">Clients (<?php echo (int) $contacts_total_count; ?>)</span>
                </button>
                <button type="button" class="admin-tab admin-tab--rapports <?php echo $tab_rapports_active ? 'is-active' : ''; ?>" id="tab-btn-rapports" role="tab" aria-selected="<?php echo $tab_rapports_active ? 'true' : 'false'; ?>" aria-controls="panel-rapports" data-tab="rapports" <?php echo !$bl_tables_ok ? 'title="Données limitées sans migration B2B"' : ''; ?>>
                    <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-chart-bar"></i></span>
                    <span class="admin-tab__txt">Rapports</span>
                </button>
            </div>
        </div>

        <?php if (admin_can_devis()): ?>
        <div id="panel-devis" class="tab-panel-devis-bl <?php echo $tab_devis_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="tab-btn-devis" <?php echo $tab_devis_active ? '' : 'hidden'; ?>>
            <div class="admin-devis-bl-panel-actions">
                <button type="button" class="btn-primary invoice-panel-fab" id="btn-nouveau-devis" aria-label="Créer un devis">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <span class="invoice-fab-label">Nouveau devis</span>
                </button>
            </div>
            <?php if (empty($devis_list)): ?>
                <div class="empty-state page-devis-empty">
                    <div class="page-devis-empty__ic" aria-hidden="true"><i class="fas fa-file-invoice"></i></div>
                    <h3>Aucun devis</h3>
                    <p>Cliquez sur « Nouveau devis » pour créer votre premier devis.</p>
                </div>
            <?php else: ?>
                <div class="invoice-panel-toolbar">
                    <div class="invoice-panel-toolbar-main">
                        <div class="invoice-panel-search-bar">
                            <label class="sr-only" for="search-devis">Rechercher un devis</label>
                            <div class="invoice-panel-search-wrap">
                                <i class="fas fa-search invoice-panel-search-ic" aria-hidden="true"></i>
                                <input type="search" id="search-devis" class="invoice-panel-search-input" placeholder="Rechercher client, n° devis…" autocomplete="off" inputmode="search">
                            </div>
                        </div>
                        <div class="invoice-panel-period-trigger">
                            <button type="button" class="btn-secondary invoice-period-toggle" id="devis-period-toggle" aria-expanded="false" aria-controls="devis-period-panel" aria-label="Filtrer les devis par période">
                                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                                <span class="invoice-period-toggle__label">Période</span>
                            </button>
                        </div>
                    </div>
                    <div class="invoice-period-panel" id="devis-period-panel" hidden>
                        <div class="invoice-period-presets" role="group" aria-label="Périodes rapides devis">
                            <button type="button" class="invoice-period-preset is-active" data-preset="today">Aujourd'hui</button>
                            <button type="button" class="invoice-period-preset" data-preset="week">7 jours</button>
                            <button type="button" class="invoice-period-preset" data-preset="month">Ce mois</button>
                            <button type="button" class="invoice-period-preset" data-preset="all">Tout</button>
                        </div>
                        <div class="admin-filters-bar invoice-period-fields">
                            <div class="admin-filter-field">
                                <label for="devis-date-debut">Du</label>
                                <input type="date" id="devis-date-debut">
                            </div>
                            <div class="admin-filter-field">
                                <label for="devis-date-fin">Au</label>
                                <input type="date" id="devis-date-fin">
                            </div>
                            <div class="admin-filter-actions">
                                <button type="button" class="btn-primary" id="devis-period-apply">Appliquer</button>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="invoice-period-summary" id="devis-period-summary" aria-live="polite"></p>
                <div class="invoice-panel-table-wrap" id="devis-table-wrap">
                    <table class="data-table invoice-data-table">
                        <colgroup>
                            <col class="invoice-col-client">
                            <col class="invoice-col-montant">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th class="invoice-col-num">Montant</th>
                            </tr>
                        </thead>
                        <tbody id="devis-list-body">
                            <?php foreach ($devis_list as $d): ?>
                            <?php
                            $did = (int) $d['id'];
                            $devis_href = '../devis/details.php?id=' . $did;
                            $client_label = trim(($d['client_prenom'] ?? '') . ' ' . ($d['client_nom'] ?? '')) ?: '—';
                            $numero_devis = $d['numero_devis'] ?? '—';
                            $date_aff = date('d/m/Y', strtotime($d['date_creation']));
                            $statut_devis = ucfirst($d['statut'] ?? '');
                            $montant_aff = number_format((float) $d['montant_total'], 0, ',', ' ');
                            $search_blob = invoice_tab_search_blob($client_label, $numero_devis, $date_aff, $statut_devis, $montant_aff, 'fcfa');
                            ?>
                            <?php $date_iso = date('Y-m-d', strtotime($d['date_creation'])); ?>
                            <tr class="invoice-list-item invoice-list-item--clickable" data-search="<?php echo $search_blob; ?>" data-date="<?php echo htmlspecialchars($date_iso); ?>" data-href="<?php echo htmlspecialchars($devis_href); ?>" role="link" tabindex="0" aria-label="Voir le devis <?php echo htmlspecialchars($numero_devis); ?>">
                                <td data-label="Client">
                                    <strong class="invoice-cell-primary"><?php echo htmlspecialchars($client_label); ?></strong>
                                    <span class="invoice-cell-sub"><?php echo htmlspecialchars($numero_devis); ?></span>
                                </td>
                                <td data-label="Montant" class="invoice-col-num">
                                    <div class="invoice-montant-cell">
                                        <span class="invoice-cell-primary"><?php echo $montant_aff; ?> FCFA</span>
                                        <span class="invoice-cell-sub"><?php echo htmlspecialchars($date_aff); ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="invoice-list-no-results" id="devis-no-results" hidden><i class="fas fa-search"></i> <span id="devis-no-results-text">Aucun devis ne correspond à votre recherche.</span></p>
                <div class="invoice-list-load-more-wrap" id="devis-load-more-wrap" hidden>
                    <button type="button" class="btn-secondary invoice-list-load-more" id="devis-load-more" hidden>Voir plus</button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (admin_can_bl_retours_b2b()): ?>
        <div id="panel-facture" class="tab-panel-devis-bl <?php echo $tab_facture_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="tab-btn-facture" <?php echo $tab_facture_active ? '' : 'hidden'; ?>>
        <?php if (!$bl_tables_ok): ?>
            <p class="message error page-devis-message page-devis-b2b-migration"><i class="fas fa-database" aria-hidden="true"></i> Tables BL absentes : exécutez <code>php migrations/run_migrate_invoice_bl.php</code>.</p>
        <?php else: ?>
        <?php if (!admin_is_restricted_admin_account()): ?>
        <div class="admin-devis-bl-panel-actions">
            <?php if (!$invoice_archive_mode): ?>
            <button type="button" class="btn-primary invoice-panel-fab invoice-panel-fab--facture" id="btn-nouveau-bl" aria-label="Créer une nouvelle facture">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span class="invoice-fab-label">Nouvelle facture</span>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="invoice-facture-kpis" id="facture-kpis" aria-label="Montants totaux des factures">
            <button type="button" class="invoice-facture-kpi invoice-facture-kpi--paye invoice-facture-kpi--filter" aria-pressed="false" aria-label="Afficher les factures payées">
                <span class="invoice-facture-kpi__icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
                <div class="invoice-facture-kpi__body">
                    <span class="invoice-facture-kpi__label">Factures payées</span>
                    <strong class="invoice-facture-kpi__value" id="facture-kpi-paye"><?php echo number_format($facture_montant_paye, 0, ',', ' '); ?> FCFA</strong>
                </div>
            </button>
            <button type="button" class="invoice-facture-kpi invoice-facture-kpi--impaye invoice-facture-kpi--filter" aria-pressed="false" aria-label="Afficher les factures impayées">
                <span class="invoice-facture-kpi__icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                <div class="invoice-facture-kpi__body">
                    <span class="invoice-facture-kpi__label">Factures impayées</span>
                    <strong class="invoice-facture-kpi__value" id="facture-kpi-impaye"><?php echo number_format($facture_montant_impaye, 0, ',', ' '); ?> FCFA</strong>
                </div>
            </button>
            <div class="invoice-facture-kpi invoice-facture-kpi--livraison">
                <span class="invoice-facture-kpi__icon" aria-hidden="true"><i class="fas fa-truck"></i></span>
                <div class="invoice-facture-kpi__body">
                    <span class="invoice-facture-kpi__label">Livraison</span>
                    <strong class="invoice-facture-kpi__value" id="facture-kpi-livraison"><?php echo number_format($facture_montant_livraison, 0, ',', ' '); ?> FCFA</strong>
                </div>
            </div>
        </div>
        <?php if (empty($facture_list)): ?>
            <div class="bl-empty-state" role="status">
                <div class="bl-empty-state__visual" aria-hidden="true">
                    <span class="bl-empty-state__ring"></span>
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h3 class="bl-empty-state__title"><?php echo $invoice_archive_mode ? 'Aucune facture archivée' : 'Aucune facture'; ?></h3>
                <p class="bl-empty-state__text"><?php echo $invoice_archive_mode ? 'Les factures archivées apparaîtront ici.' : 'Créez une première facture avec « Nouvelle facture ».'; ?></p>
            </div>
        <?php else: ?>
                <div class="invoice-panel-toolbar">
                    <div class="invoice-panel-toolbar-main">
                        <div class="invoice-panel-search-bar">
                            <label class="sr-only" for="search-facture">Rechercher une facture</label>
                            <div class="invoice-panel-search-wrap">
                                <i class="fas fa-search invoice-panel-search-ic" aria-hidden="true"></i>
                                <input type="search" id="search-facture" class="invoice-panel-search-input" placeholder="Rechercher client, n° facture…" autocomplete="off" inputmode="search">
                            </div>
                        </div>
                        <div class="invoice-panel-period-trigger">
                            <button type="button" class="btn-secondary invoice-period-toggle" id="facture-period-toggle" aria-expanded="false" aria-controls="facture-period-panel" aria-label="Filtrer les factures par période">
                                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                                <span class="invoice-period-toggle__label">Période</span>
                            </button>
                        </div>
                    </div>
                    <div class="invoice-period-panel" id="facture-period-panel" hidden>
                        <div class="invoice-period-presets" role="group" aria-label="Périodes rapides factures">
                            <button type="button" class="invoice-period-preset" data-preset="today">Aujourd'hui</button>
                            <button type="button" class="invoice-period-preset" data-preset="week">7 jours</button>
                            <button type="button" class="invoice-period-preset" data-preset="month">Ce mois</button>
                            <button type="button" class="invoice-period-preset is-active" data-preset="all">Tout</button>
                        </div>
                        <div class="admin-filters-bar invoice-period-fields">
                            <div class="admin-filter-field">
                                <label for="facture-date-debut">Du</label>
                                <input type="date" id="facture-date-debut">
                            </div>
                            <div class="admin-filter-field">
                                <label for="facture-date-fin">Au</label>
                                <input type="date" id="facture-date-fin">
                            </div>
                            <div class="admin-filter-actions">
                                <button type="button" class="btn-primary" id="facture-period-apply">Appliquer</button>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="invoice-period-summary" id="facture-period-summary" aria-live="polite"></p>
                <div class="invoice-panel-table-wrap" id="facture-table-wrap">
                    <table class="data-table invoice-data-table">
                        <colgroup>
                            <col class="invoice-col-client">
                            <col class="invoice-col-montant">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th class="invoice-col-num">Montant</th>
                            </tr>
                        </thead>
                        <tbody id="facture-list-body">
                            <?php
                            $facture_group_date = null;
                            $facture_today_iso = date('Y-m-d');
                            $facture_yesterday_iso = date('Y-m-d', strtotime('-1 day'));
                            foreach ($facture_list as $f):
                            ?>
                            <?php
                            $fid = (int) $f['id'];
                            $facture_href = 'bl_voir.php?id=' . $fid;
                            $client_label = trim($f['raison_sociale'] ?? '') ?: '—';
                            $numero_facture = $f['numero_bl'] ?? '—';
                            $date_source = !empty($f['date_bl']) ? $f['date_bl'] : ($f['date_creation'] ?? 'now');
                            $date_iso = date('Y-m-d', strtotime($date_source));
                            $date_aff = date('d/m/Y', strtotime($date_source));
                            if ($date_iso !== $facture_group_date):
                                $facture_group_date = $date_iso;
                                if ($date_iso === $facture_today_iso) {
                                    $facture_group_label = "Aujourd'hui (" . $date_aff . ')';
                                } elseif ($date_iso === $facture_yesterday_iso) {
                                    $facture_group_label = 'Hier (' . $date_aff . ')';
                                } else {
                                    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
                                    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
                                    $ts = strtotime($date_iso);
                                    $facture_group_label = ucfirst($jours[(int) date('w', $ts)]) . ' ' . date('j', $ts) . ' ' . $mois[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
                                }
                            ?>
                            <tr class="invoice-date-group-row" data-date-group="<?php echo htmlspecialchars($date_iso); ?>" aria-hidden="true">
                                <td colspan="2">
                                    <div class="invoice-date-group">
                                        <span class="invoice-date-group__label"><?php echo htmlspecialchars($facture_group_label); ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php
                            $lignes_totaux_f = $facture_lignes_totaux_map[$fid] ?? null;
                            $decomp_montant_f = bl_decomposer_montant_facture($f, $lignes_totaux_f);
                            $montant_aff = (float) $decomp_montant_f['total'];
                            $montant_hors_livraison = (int) round((float) $decomp_montant_f['hors_livraison']);
                            $montant_livraison = (int) round((float) $decomp_montant_f['livraison']);
                            $montant_txt = number_format($montant_aff, 0, ',', ' ');
                            $est_payee = bl_est_facture_payee($f);
                            $statut_facture = $est_payee ? 'Payée' : 'Impayée';
                            $statut_class = $est_payee ? 'paye' : 'impaye';
                            $search_blob = invoice_tab_search_blob($client_label, $numero_facture, $date_aff, $statut_facture, $montant_txt, 'fcfa');
                            ?>
                            <tr class="invoice-list-item invoice-list-item--clickable" data-search="<?php echo $search_blob; ?>" data-date="<?php echo htmlspecialchars($date_iso); ?>" data-href="<?php echo htmlspecialchars($facture_href); ?>" data-montant="<?php echo (int) round($montant_aff); ?>" data-montant-hors-livraison="<?php echo $montant_hors_livraison; ?>" data-montant-livraison="<?php echo $montant_livraison; ?>" data-payee="<?php echo $est_payee ? '1' : '0'; ?>" role="link" tabindex="0" aria-label="Voir la facture <?php echo htmlspecialchars($numero_facture); ?>">
                                <td data-label="Client">
                                    <strong class="invoice-cell-primary"><?php echo htmlspecialchars($client_label); ?></strong>
                                    <span class="invoice-cell-sub"><?php echo htmlspecialchars($numero_facture); ?></span>
                                </td>
                                <td data-label="Montant" class="invoice-col-num">
                                    <div class="invoice-montant-cell">
                                        <span class="invoice-cell-primary"><?php echo $montant_txt; ?> FCFA</span>
                                        <span class="invoice-date-statut-line">
                                            <span class="invoice-cell-sub"><?php echo htmlspecialchars($date_aff); ?></span>
                                            <span class="invoice-row-statut invoice-row-statut--inline commande-statut statut-<?php echo $statut_class; ?>"><?php echo htmlspecialchars($statut_facture); ?></span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="invoice-list-no-results" id="facture-no-results" hidden><i class="fas fa-search"></i> <span id="facture-no-results-text">Aucune facture ne correspond à votre recherche.</span></p>
                <div class="invoice-list-load-more-wrap" id="facture-load-more-wrap" hidden>
                    <button type="button" class="btn-secondary invoice-list-load-more" id="facture-load-more" hidden>Voir plus</button>
                </div>
        <?php endif; ?>
        <?php endif; ?>
        </div>
        <?php endif; ?>

        <div id="panel-contacts" class="tab-panel-devis-bl <?php echo $tab_contacts_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="tab-btn-contacts" <?php echo $tab_contacts_active ? '' : 'hidden'; ?>>
            <?php
            $contacts = $contacts_list;
            include __DIR__ . '/partials/panel_contacts.php';
            ?>
        </div>

        <div id="panel-rapports" class="tab-panel-devis-bl <?php echo $tab_rapports_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="tab-btn-rapports" <?php echo $tab_rapports_active ? '' : 'hidden'; ?>>
            <?php include __DIR__ . '/partials/panel_rapports.php'; ?>
        </div>
    </section>
    </div>

    <?php include __DIR__ . '/modal_devis_inc.php'; ?>

    <?php if ($bl_tables_ok && admin_can_bl_retours_b2b()): ?>
    <!-- Modal BL : mêmes champs et même structure que le modal devis (+ date BL + statut) -->
    <div id="modal-bl" class="modal-commande-manuelle invoice-form-modal <?php echo $show_modal_bl ? 'modal-open' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="modal-bl-title">
        <div class="modal-commande-manuelle-backdrop" id="modal-bl-backdrop"></div>
        <div class="modal-commande-manuelle-content">
            <div class="modal-commande-manuelle-header">
                <h2 id="modal-bl-title"><i class="fas fa-file-invoice-dollar"></i> <?php echo htmlspecialchars($bl_form_title); ?></h2>
                <button type="button" class="modal-commande-manuelle-close" id="modal-bl-close" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-commande-manuelle-body">
                <?php if ($bl_modal_err): ?>
                    <div class="message error modal-commande-erreur">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($bl_modal_err); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($bl_form_action); ?>" id="form-bl">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <?php if ($bl_form_is_edit && $bl_edit_id > 0): ?>
                    <input type="hidden" name="bl_id" value="<?php echo (int) $bl_edit_id; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="user_id" id="user_id_bl" value="<?php echo htmlspecialchars($bp['user_id'] ?? ''); ?>">
                    <div class="form-commande-manuelle-grid">
                        <div class="form-commande-manuelle-col form-col-articles">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-search"></i>
                                    <h3>Rechercher un produit</h3>
                                </div>
                                <div class="form-group search-group">
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-produit-bl" placeholder="Nom, description… — filtre en direct" autocomplete="off" inputmode="search" data-live-search-input>
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-loading-bl" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <div id="search-produit-results-bl" class="search-produit-results" role="listbox" aria-hidden="true"></div>
                                </div>
                            </div>

                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h3>Produits du devis</h3>
                                    <span class="lignes-count" id="lignes-count-bl">0 article(s)</span>
                                </div>
                                <div id="lignes-commande-bl" class="lignes-commande lignes-commande-modal-wrap">
                                    <div class="ligne-commande-head ligne-commande-head-bl ligne-commande-head-invoice" id="lignes-head-bl" hidden>
                                        <span class="lch-head-cell">Produit</span>
                                        <span class="lch-head-cell lch-head-cell--qte">
                                            <span class="lch-head-full">Quantité</span>
                                            <span class="lch-head-short" aria-hidden="true">Qté</span>
                                        </span>
                                        <span class="lch-head-cell">Montant</span>
                                        <span class="lch-head-cell">Total</span>
                                        <span class="lch-head-cell lch-head-actions" aria-hidden="true"></span>
                                    </div>
                                    <div class="lignes-empty" id="lignes-empty-bl">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun produit ajouté. Utilisez la recherche ci-dessus.</p>
                                    </div>
                                </div>
                                <div class="modal-tva-option" role="group" aria-labelledby="modal-tva-bl-title">
                                    <input type="hidden" name="inclure_tva" value="0">
                                    <label class="modal-tva-option__label" for="inclure_tva_bl">
                                        <span class="modal-tva-option__inner">
                                            <span class="modal-tva-option__glow" aria-hidden="true"></span>
                                            <span class="modal-tva-option__leading">
                                                <span class="modal-tva-option__icon" aria-hidden="true"><i class="fas fa-percent"></i></span>
                                                <span class="modal-tva-option__title" id="modal-tva-bl-title">Inclure la TVA</span>
                                            </span>
                                            <span class="modal-tva-option__toggle">
                                                <input type="checkbox" name="inclure_tva" value="1" id="inclure_tva_bl" class="modal-tva-option__checkbox"
                                                    <?php echo (is_array($bp) && !empty($bp['inclure_tva'])) ? 'checked' : ''; ?>>
                                                <span class="modal-tva-option__track" aria-hidden="true"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-commande-manuelle-col form-col-client">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-user"></i>
                                    <h3>Informations client</h3>
                                </div>
                                <div class="form-group search-group" style="position:relative;">
                                    <label for="search-client-bl">Client <span class="required">*</span></label>
                                    <div class="search-input-wrapper">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-client-loading-bl" style="visibility:hidden;"><i class="fas fa-spinner fa-spin"></i></span>
                                        <input type="text" id="search-client-bl" placeholder="Nom ou téléphone (carnet + téléphone)…" autocomplete="off">
                                    </div>
                                    <div id="search-client-results-bl" class="search-produit-results" role="listbox" aria-hidden="true" style="position:absolute; left:0; right:0; top:100%; z-index:100;"></div>
                                    <div id="client-selected-bl" class="client-selected-chip" style="display:none;" aria-hidden="true">
                                        <div class="client-selected-info">
                                            <strong id="client-selected-nom-bl"></strong>
                                            <span id="client-selected-tel-bl"></span>
                                        </div>
                                        <button type="button" id="client-selected-clear-bl" class="client-selected-clear" title="Changer de client" aria-label="Changer de client">&times;</button>
                                    </div>
                                    <input type="hidden" id="client_nom_bl" name="client_nom" value="<?php echo htmlspecialchars($bp['client_nom'] ?? ''); ?>">
                                    <input type="hidden" id="client_telephone_bl" name="client_telephone" value="<?php echo htmlspecialchars($bp['client_telephone'] ?? ''); ?>">
                                    <p class="form-hint">Suggestions : clients enregistrés + contacts du téléphone (dans l’app). Nouveau : « Nom 07… ».</p>
                                </div>
                                <div class="form-group">
                                    <label for="zone_livraison_id_bl"><i class="fas fa-map-marker-alt"></i> Adresse de livraison <span class="optional">(optionnel)</span></label>
                                    <select id="zone_livraison_id_bl" name="zone_livraison_id">
                                        <option value="">— Sélectionnez une adresse —</option>
                                        <?php foreach ($zones_livraison as $z): ?>
                                        <option value="<?php echo (int) $z['id']; ?>"
                                            data-adresse="<?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>"
                                            data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                            <?php echo (isset($bp['zone_livraison_id']) && (string) $bp['zone_livraison_id'] === (string) $z['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                            (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="custom" <?php echo (isset($bp['zone_livraison_id']) && $bp['zone_livraison_id'] === 'custom') ? 'selected' : ''; ?>>— Adresse personnalisée —</option>
                                    </select>
                                    <div id="adresse-custom-wrap-bl" class="adresse-custom-wrap" style="display:none; margin-top:10px;">
                                        <textarea id="adresse_livraison_ta_bl" rows="3" placeholder="Saisissez l'adresse complète"><?php echo htmlspecialchars($bp['adresse_livraison'] ?? ''); ?></textarea>
                                    </div>
                                    <div id="adresse-zone-display-bl" class="adresse-zone-display" style="display:none; margin-top:8px; padding:10px; background:#f5f5f4; border-radius:8px;"></div>
                                    <input type="hidden" name="adresse_livraison" id="adresse_livraison_bl" value="">
                                    <input type="hidden" name="frais_livraison" id="frais_livraison_bl" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="remise_globale_pct_bl"><i class="fas fa-percent"></i> Réduction globale (%)</label>
                                    <input type="number" name="remise_globale_pct" id="remise_globale_pct_bl" min="0" max="100" step="0.01" placeholder="0"
                                        value="<?php echo htmlspecialchars($bp['remise_globale_pct'] ?? '0'); ?>">
                                    <p class="form-hint">Pourcentage appliqué sur le sous-total produits + livraison.</p>
                                </div>
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label for="date_bl">Date de la facture</label>
                                        <input type="date" name="date_bl" id="date_bl" value="<?php echo htmlspecialchars($bp['date_bl'] ?? date('Y-m-d')); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="statut_bl_sel">Statut de la facture</label>
                                        <select name="statut" id="statut_bl_sel">
                                            <?php
                                            $sb = $bp['statut'] ?? 'brouillon';
                                            if (!in_array($sb, ['brouillon', 'valide'], true)) {
                                                $sb = 'brouillon';
                                            }
                                            ?>
                                            <option value="brouillon" <?php echo $sb === 'brouillon' ? 'selected' : ''; ?>>Brouillon</option>
                                            <option value="valide" <?php echo $sb === 'valide' ? 'selected' : ''; ?>>Validé (comptabilité)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="commande-manuelle-recap">
                                    <div class="recap-line">
                                        <span>Sous-total produits (HT)</span>
                                        <span id="recap-sous-total-bl">0 FCFA</span>
                                    </div>
                                    <div class="recap-line">
                                        <span>Frais de livraison (HT)</span>
                                        <span id="recap-frais-bl">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-remise-line" id="recap-remise-line-bl" style="display:none;">
                                        <span>Réduction (<span id="recap-remise-pct-bl">0</span> %)</span>
                                        <span id="recap-remise-montant-bl">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-tva-line-bl" id="recap-tva-line-bl" style="display:none;">
                                        <span>TVA (<span id="recap-tva-pct-bl"><?php echo htmlspecialchars((string) $fiscal_tva_pourcent_devis_bl); ?></span> %)</span>
                                        <span id="recap-tva-montant-bl">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-total">
                                        <span id="recap-total-label-bl">Total</span>
                                        <span id="recap-total-bl">0 FCFA</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-commande-manuelle-actions">
                        <button type="button" class="btn-secondary" id="modal-bl-cancel">Annuler</button>
                        <button type="submit" class="btn-primary btn-submit-commande" name="submit_bl">
                            <i class="fas fa-check"></i> <?php echo htmlspecialchars($bl_form_submit_label); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $admin_invoice_hub_bottom_nav = true;
    $admin_invoice_hub_active_tab = $active_tab;
    include '../includes/footer.php';
    ?>

    <script src="/js/admin-produit-search-ui.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/admin-invoice-list-ui.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/admin-contacts-import.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/admin-client-search-sync.js<?php echo asset_version_query(); ?>"></script>
    <script>
    window.INVOICE_BL_EDIT_LIGNES = <?php echo $bl_edit_lignes_json; ?>;
    window.INVOICE_DEVIS_EDIT_LIGNES = <?php echo $devis_edit_lignes_json; ?>;
    </script>
    <script>
    (function() {
        var FISCAL_TVA_PCT = <?php echo json_encode((float) $fiscal_tva_pourcent_devis_bl); ?>;
        var tabDevis = document.getElementById('tab-btn-devis');
        var tabFacture = document.getElementById('tab-btn-facture');
        var tabContacts = document.getElementById('tab-btn-contacts');
        var tabRapports = document.getElementById('tab-btn-rapports');
        var panelDevis = document.getElementById('panel-devis');
        var panelFacture = document.getElementById('panel-facture');
        var panelContacts = document.getElementById('panel-contacts');
        var panelRapports = document.getElementById('panel-rapports');

        var invoiceHubTitles = {
            facture: { label: 'Factures', icon: 'fa-file-invoice-dollar' },
            devis: { label: 'Devis', icon: 'fa-file-invoice' },
            contacts: { label: 'Clients', icon: 'fa-address-book' },
            rapports: { label: 'Rapports', icon: 'fa-chart-bar' }
        };

        function syncInvoiceHubHero(which) {
            var cfg = invoiceHubTitles[which];
            if (!cfg) {
                return;
            }
            var labelEl = document.getElementById('invoice-hub-hero-label');
            var iconEl = document.getElementById('invoice-hub-hero-icon');
            if (labelEl) {
                labelEl.textContent = cfg.label;
            }
            if (iconEl) {
                iconEl.className = 'fas ' + cfg.icon;
            }
        }

        function showTab(which) {
            if (which === 'facture' && tabFacture && tabFacture.disabled) {
                return;
            }
            var map = [
                ['devis', tabDevis, panelDevis],
                ['facture', tabFacture, panelFacture],
                ['contacts', tabContacts, panelContacts],
                ['rapports', tabRapports, panelRapports],
            ];
            for (var i = 0; i < map.length; i++) {
                var id = map[i][0];
                var btn = map[i][1];
                var panel = map[i][2];
                if (!panel) {
                    continue;
                }
                var on = id === which;
                if (on) {
                    panel.removeAttribute('hidden');
                    panel.classList.add('is-active');
                } else {
                    panel.setAttribute('hidden', 'hidden');
                    panel.classList.remove('is-active');
                }
                if (btn && !btn.disabled) {
                    if (on) {
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-selected', 'true');
                    } else {
                        btn.classList.remove('is-active');
                        btn.setAttribute('aria-selected', 'false');
                    }
                }
            }
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', which);
                if (which !== 'rapports') {
                    url.searchParams.delete('annee');
                    url.searchParams.delete('vue');
                }
                if (which !== 'contacts') {
                    url.searchParams.delete('recherche');
                }
                window.history.replaceState({}, '', url.toString());
            }
            syncInvoiceBottomNav(which);
            syncInvoiceHubHero(which);
        }

        function syncInvoiceBottomNav(which) {
            var nav = document.getElementById('adminBottomNav');
            if (!nav) {
                return;
            }
            var items = nav.querySelectorAll('[data-invoice-tab]');
            for (var j = 0; j < items.length; j++) {
                var el = items[j];
                var tab = el.getAttribute('data-invoice-tab');
                var on = tab === which;
                el.classList.toggle('is-active', on);
                if (on) {
                    el.setAttribute('aria-current', 'page');
                } else {
                    el.removeAttribute('aria-current');
                }
            }
        }

        var bottomInvoiceTabs = document.querySelectorAll('#adminBottomNav [data-invoice-tab]');
        for (var b = 0; b < bottomInvoiceTabs.length; b++) {
            bottomInvoiceTabs[b].addEventListener('click', function () {
                var tab = this.getAttribute('data-invoice-tab');
                if (tab) {
                    showTab(tab);
                }
            });
        }
        syncInvoiceBottomNav(<?php echo json_encode($active_tab); ?>);

        if (tabDevis) tabDevis.addEventListener('click', function() { showTab('devis'); });
        if (tabFacture) tabFacture.addEventListener('click', function() { if (!tabFacture.disabled) showTab('facture'); });
        if (tabContacts) tabContacts.addEventListener('click', function() { showTab('contacts'); });
        if (tabRapports) tabRapports.addEventListener('click', function() { showTab('rapports'); });

        var modalBl = document.getElementById('modal-bl');
        var btnOpenBl = document.getElementById('btn-nouveau-bl');
        var btnCloseBl = document.getElementById('modal-bl-close');
        var btnCancelBl = document.getElementById('modal-bl-cancel');
        var backdropBl = document.getElementById('modal-bl-backdrop');

        function openModalBl() {
            showTab('facture');
            if (modalBl) {
                modalBl.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            }
            if (window.AdminClientSearchSync && AdminClientSearchSync.preloadDeviceContacts) {
                AdminClientSearchSync.preloadDeviceContacts();
            }
        }
        function closeModalBl() {
            if (modalBl) {
                modalBl.classList.remove('modal-open');
                document.body.style.overflow = '';
            }
        }

        if (btnOpenBl) btnOpenBl.addEventListener('click', openModalBl);
        if (btnCloseBl) btnCloseBl.addEventListener('click', closeModalBl);
        if (btnCancelBl) btnCancelBl.addEventListener('click', closeModalBl);
        if (backdropBl) backdropBl.addEventListener('click', closeModalBl);

        /* ——— Même logique que le modal devis (recherche produits, lignes, zone, client, recap) ——— */
        var searchInputBl = document.getElementById('search-produit-bl');
        var searchResultsBl = document.getElementById('search-produit-results-bl');
        var searchLoadingBl = document.getElementById('search-loading-bl');
        var lignesContainerBl = document.getElementById('lignes-commande-bl');
        var lignesEmptyBl = document.getElementById('lignes-empty-bl');
        var lignesCountBl = document.getElementById('lignes-count-bl');
        var ligneIndexBl = 0;
        var ajaxUrlBl = '../devis/ajax_search_produits.php';

        function updateLignesUIBl() {
            var items = lignesContainerBl ? lignesContainerBl.querySelectorAll('.ligne-commande-item') : [];
            var n = items.length;
            if (lignesEmptyBl) lignesEmptyBl.style.display = n === 0 ? 'flex' : 'none';
            if (lignesCountBl) lignesCountBl.textContent = n + ' article(s)';
            var headBl = document.getElementById('lignes-head-bl');
            if (headBl) {
                if (n > 0) {
                    headBl.removeAttribute('hidden');
                } else {
                    headBl.setAttribute('hidden', 'hidden');
                }
            }
        }

        function addLigneBl(produit) {
            var U = window.FoutaAdminProduitSearchUi;
            var idx = ligneIndexBl++;
            var div = document.createElement('div');
            div.className = 'ligne-commande-item ligne-commande-item-bl';
            div.dataset.produitId = produit.id;
            div.innerHTML = U && U.buildLigneCommandeItemHtml
                ? U.buildLigneCommandeItemHtml(produit, idx, 'lignes', { hidePromo: true })
                : '';
            if (lignesEmptyBl) lignesEmptyBl.style.display = 'none';
            div.querySelector('.ligne-remove').addEventListener('click', function() {
                div.remove();
                updateLignesUIBl();
                updateRecapBl();
            });
            lignesContainerBl.appendChild(div);
            if (produit.quantite != null && produit.quantite !== '') {
                var qEl = div.querySelector('.ligne-qte');
                if (qEl) qEl.value = produit.quantite;
            }
            if (produit.prix != null && produit.prix !== '') {
                var pEl = div.querySelector('.ligne-prix');
                if (pEl) pEl.value = produit.prix;
            }
            if (U && U.updateLigneRowTotal) U.updateLigneRowTotal(div);
            updateLignesUIBl();
            updateRecapBl();
        }

        function prefillBlLignesEdit() {
            var rows = window.INVOICE_BL_EDIT_LIGNES;
            if (!rows || !rows.length || !lignesContainerBl) {
                return;
            }
            for (var i = 0; i < rows.length; i++) {
                var l = rows[i];
                addLigneBl({
                    id: l.produit_id,
                    nom: l.nom,
                    prix: l.prix_unitaire,
                    prix_promotion: l.prix_promotion || '',
                    quantite: l.quantite,
                    stock: 9999
                });
            }
        }

        function doSearchBl(q) {
            if (searchLoadingBl) searchLoadingBl.style.visibility = 'visible';
            fetch(ajaxUrlBl + '?q=' + encodeURIComponent(q) + '&limit=25')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var items = data.items || [];
                    searchResultsBl.innerHTML = '';
                    if (items.length === 0) {
                        searchResultsBl.innerHTML = '<div class="search-no-results"><i class="fas fa-box-open"></i> Aucun produit trouvé.</div>';
                    } else {
                        items.forEach(function(p) {
                            var el = document.createElement('div');
                            el.className = 'search-result-item';
                            el.setAttribute('role', 'option');
                            el.setAttribute('tabindex', '0');
                            var U = window.FoutaAdminProduitSearchUi;
                            el.innerHTML = U && U.buildSearchResultHtml ? U.buildSearchResultHtml(p) : (
                                '<span class="sr-nom">' + (p.nom || '') + '</span>' +
                                '<span class="sr-meta">' + (p.categorie_nom || '') + '</span>'
                            );
                            el.addEventListener('mousedown', function(ev) {
                                ev.preventDefault();
                                addLigneBl(p);
                                searchInputBl.value = '';
                                searchResultsBl.innerHTML = '';
                                searchResultsBl.setAttribute('aria-hidden', 'true');
                            });
                            el.addEventListener('keydown', function(ev) {
                                if (ev.key === 'Enter' || ev.key === ' ') {
                                    ev.preventDefault();
                                    addLigneBl(p);
                                    searchInputBl.value = '';
                                    searchResultsBl.innerHTML = '';
                                    searchResultsBl.setAttribute('aria-hidden', 'true');
                                }
                            });
                            searchResultsBl.appendChild(el);
                        });
                    }
                    searchResultsBl.setAttribute('aria-hidden', 'false');
                })
                .catch(function() {
                    searchResultsBl.innerHTML = '<div class="search-no-results"><i class="fas fa-exclamation-triangle"></i> Erreur de recherche.</div>';
                })
                .finally(function() {
                    if (searchLoadingBl) searchLoadingBl.style.visibility = 'hidden';
                });
        }

        var zoneSelectBl = document.getElementById('zone_livraison_id_bl');
        var adresseCustomWrapBl = document.getElementById('adresse-custom-wrap-bl');
        var adresseZoneDisplayBl = document.getElementById('adresse-zone-display-bl');
        var adresseLivraisonBl = document.getElementById('adresse_livraison_bl');
        var adresseTaBl = document.getElementById('adresse_livraison_ta_bl');
        var fraisInputBl = document.getElementById('frais_livraison_bl');
        var recapSousTotalBl = document.getElementById('recap-sous-total-bl');
        var recapFraisBl = document.getElementById('recap-frais-bl');
        var recapTotalBl = document.getElementById('recap-total-bl');
        var recapTotalLabelBl = document.getElementById('recap-total-label-bl');
        var recapTvaLineBl = document.getElementById('recap-tva-line-bl');
        var recapRemiseLineBl = document.getElementById('recap-remise-line-bl');
        var recapRemisePctBl = document.getElementById('recap-remise-pct-bl');
        var recapRemiseMontantBl = document.getElementById('recap-remise-montant-bl');
        var remiseInputBl = document.getElementById('remise_globale_pct_bl');
        var recapTvaMontantBl = document.getElementById('recap-tva-montant-bl');
        var inclureTvaBl = document.getElementById('inclure_tva_bl');
        var formBl = document.getElementById('form-bl');

        function formatNumberBl(n) {
            return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function getSousTotalBl() {
            var U = window.FoutaAdminProduitSearchUi;
            if (U && U.getLignesSousTotal) {
                return U.getLignesSousTotal(lignesContainerBl);
            }
            return 0;
        }

        function getFraisLivraisonBl() {
            if (!zoneSelectBl || zoneSelectBl.value === '' || zoneSelectBl.value === 'custom') return 0;
            var opt = zoneSelectBl.options[zoneSelectBl.selectedIndex];
            return opt && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
        }

        function getRemisePctBl() {
            return remiseInputBl ? Math.min(100, Math.max(0, parseFloat(remiseInputBl.value) || 0)) : 0;
        }

        function updateRecapBl() {
            var sousTotal = getSousTotalBl();
            var frais = getFraisLivraisonBl();
            var brutHt = sousTotal + frais;
            var remisePct = getRemisePctBl();
            var remiseMontant = Math.round(brutHt * remisePct / 100);
            var netHt = Math.round(brutHt - remiseMontant);
            var tvaOn = inclureTvaBl && inclureTvaBl.checked;
            var tvaMontant = 0;
            var totalAff = netHt;
            if (remisePct > 0) {
                if (recapRemiseLineBl) recapRemiseLineBl.style.display = '';
                if (recapRemisePctBl) recapRemisePctBl.textContent = remisePct.toString().replace('.', ',');
                if (recapRemiseMontantBl) recapRemiseMontantBl.textContent = '-' + formatNumberBl(remiseMontant) + ' FCFA';
            } else {
                if (recapRemiseLineBl) recapRemiseLineBl.style.display = 'none';
            }
            if (tvaOn) {
                tvaMontant = Math.round(netHt * (FISCAL_TVA_PCT / 100));
                totalAff = Math.round(netHt + tvaMontant);
                if (recapTvaLineBl) recapTvaLineBl.style.display = '';
                if (recapTvaMontantBl) recapTvaMontantBl.textContent = formatNumberBl(tvaMontant) + ' FCFA';
                if (recapTotalLabelBl) recapTotalLabelBl.textContent = 'Total TTC';
            } else {
                if (recapTvaLineBl) recapTvaLineBl.style.display = 'none';
                if (recapTotalLabelBl) recapTotalLabelBl.textContent = 'Total';
            }
            if (recapSousTotalBl) recapSousTotalBl.textContent = formatNumberBl(sousTotal) + ' FCFA';
            if (recapFraisBl) recapFraisBl.textContent = formatNumberBl(frais) + ' FCFA';
            if (recapTotalBl) recapTotalBl.textContent = formatNumberBl(totalAff) + ' FCFA';
            if (fraisInputBl) fraisInputBl.value = frais;
        }

        function onZoneChangeBl() {
            var val = zoneSelectBl ? zoneSelectBl.value : '';
            if (val === 'custom') {
                if (adresseCustomWrapBl) adresseCustomWrapBl.style.display = 'block';
                if (adresseZoneDisplayBl) adresseZoneDisplayBl.style.display = 'none';
                if (adresseLivraisonBl) adresseLivraisonBl.value = '';
            } else if (val !== '') {
                var opt = zoneSelectBl.options[zoneSelectBl.selectedIndex];
                var adr = opt && opt.dataset.adresse ? opt.dataset.adresse : '';
                if (adresseLivraisonBl) adresseLivraisonBl.value = adr;
                if (adresseCustomWrapBl) adresseCustomWrapBl.style.display = 'none';
                if (adresseZoneDisplayBl) {
                    adresseZoneDisplayBl.textContent = adr;
                    adresseZoneDisplayBl.style.display = 'block';
                }
            } else {
                if (adresseCustomWrapBl) adresseCustomWrapBl.style.display = 'none';
                if (adresseZoneDisplayBl) adresseZoneDisplayBl.style.display = 'none';
                if (adresseLivraisonBl) adresseLivraisonBl.value = '';
            }
            updateRecapBl();
        }

        if (zoneSelectBl) zoneSelectBl.addEventListener('change', onZoneChangeBl);

        if (inclureTvaBl) inclureTvaBl.addEventListener('change', updateRecapBl);
        if (remiseInputBl) remiseInputBl.addEventListener('input', updateRecapBl);

        var UBl = window.FoutaAdminProduitSearchUi;
        if (UBl && UBl.bindLignesLiveRecap) {
            UBl.bindLignesLiveRecap(lignesContainerBl, updateRecapBl);
        }

        if (formBl) {
            formBl.addEventListener('submit', function(ev) {
                var zvb = zoneSelectBl ? zoneSelectBl.value : '';
                if (zvb === 'custom' && adresseTaBl && adresseLivraisonBl) {
                    adresseLivraisonBl.value = adresseTaBl.value.trim();
                } else if (zvb && zvb !== 'custom' && zoneSelectBl && adresseLivraisonBl) {
                    var optB = zoneSelectBl.options[zoneSelectBl.selectedIndex];
                    adresseLivraisonBl.value = optB && optB.dataset.adresse ? optB.dataset.adresse : '';
                }
            });
        }

        var searchTimeoutBl;
        if (searchInputBl && searchResultsBl) {
            searchInputBl.addEventListener('input', function() {
                clearTimeout(searchTimeoutBl);
                var q = searchInputBl.value.trim();
                searchTimeoutBl = setTimeout(function() { doSearchBl(q); }, 120);
            });
            searchInputBl.addEventListener('focus', function() {
                var q = searchInputBl.value.trim();
                if (searchResultsBl.getAttribute('aria-hidden') === 'true' || searchResultsBl.innerHTML === '') {
                    doSearchBl(q);
                }
            });
            searchInputBl.addEventListener('blur', function() {
                setTimeout(function() {
                    if (!searchResultsBl.contains(document.activeElement)) {
                        searchResultsBl.innerHTML = '';
                        searchResultsBl.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchResultsBl.addEventListener('mousedown', function(ev) { ev.preventDefault(); });
        }

        if (window.AdminClientSearchSync) {
            AdminClientSearchSync.init({
                searchInput: document.getElementById('search-client-bl'),
                resultsEl: document.getElementById('search-client-results-bl'),
                loadingEl: document.getElementById('search-client-loading-bl'),
                nomInput: document.getElementById('client_nom_bl'),
                telInput: document.getElementById('client_telephone_bl'),
                userIdInput: document.getElementById('user_id_bl'),
                selectedWrap: document.getElementById('client-selected-bl'),
                selectedNomEl: document.getElementById('client-selected-nom-bl'),
                selectedTelEl: document.getElementById('client-selected-tel-bl'),
                clearBtn: document.getElementById('client-selected-clear-bl'),
                ajaxUrl: '../devis/ajax_search_clients.php'
            });
        }

        /* ——— Modal devis ——— */
        var modalDevis = document.getElementById('modal-devis');
        var btnOpenDevis = document.getElementById('btn-nouveau-devis');
        var btnCloseDevis = document.getElementById('modal-devis-close');
        var btnCancelDevis = document.getElementById('modal-devis-cancel');
        var backdropDevis = document.getElementById('modal-devis-backdrop');
        function openModalDevis() {
            showTab('devis');
            if (modalDevis) { modalDevis.classList.add('modal-open'); document.body.style.overflow = 'hidden'; }
            if (window.AdminClientSearchSync && AdminClientSearchSync.preloadDeviceContacts) {
                AdminClientSearchSync.preloadDeviceContacts();
            }
        }
        function closeModalDevis() {
            if (modalDevis) { modalDevis.classList.remove('modal-open'); document.body.style.overflow = ''; }
        }
        if (btnOpenDevis) btnOpenDevis.addEventListener('click', openModalDevis);
        if (btnCloseDevis) btnCloseDevis.addEventListener('click', closeModalDevis);
        if (btnCancelDevis) btnCancelDevis.addEventListener('click', closeModalDevis);
        if (backdropDevis) backdropDevis.addEventListener('click', closeModalDevis);

        var searchInputDevis = document.getElementById('search-produit');
        var searchResultsDevis = document.getElementById('search-produit-results');
        var searchLoadingDevis = document.getElementById('search-loading');
        var lignesContainerDevis = document.getElementById('lignes-commande');
        var lignesEmptyDevis = document.getElementById('lignes-empty');
        var lignesCountDevis = document.getElementById('lignes-count');
        var ligneIndexDevis = 0;
        var ajaxUrlDevis = '../devis/ajax_search_produits.php';

        function updateLignesUIDevis() {
            var items = lignesContainerDevis ? lignesContainerDevis.querySelectorAll('.ligne-commande-item') : [];
            var n = items.length;
            if (lignesEmptyDevis) lignesEmptyDevis.style.display = n === 0 ? 'flex' : 'none';
            if (lignesCountDevis) lignesCountDevis.textContent = n + ' article(s)';
            var headDevis = document.getElementById('lignes-head-devis');
            if (headDevis) {
                if (n > 0) {
                    headDevis.removeAttribute('hidden');
                } else {
                    headDevis.setAttribute('hidden', 'hidden');
                }
            }
        }
        function addLigneDevis(produit) {
            var U = window.FoutaAdminProduitSearchUi;
            var idx = ligneIndexDevis++;
            var div = document.createElement('div');
            div.className = 'ligne-commande-item ligne-commande-item-devis';
            div.dataset.produitId = produit.id;
            div.innerHTML = U && U.buildLigneCommandeItemHtml
                ? U.buildLigneCommandeItemHtml(produit, idx, 'lignes', { hidePromo: true })
                : '';
            if (lignesEmptyDevis) lignesEmptyDevis.style.display = 'none';
            div.querySelector('.ligne-remove').addEventListener('click', function() {
                div.remove();
                updateLignesUIDevis();
                updateRecapDevis();
            });
            lignesContainerDevis.appendChild(div);
            if (produit.quantite != null && produit.quantite !== '') {
                var qEl = div.querySelector('.ligne-qte');
                if (qEl) qEl.value = produit.quantite;
            }
            if (produit.prix != null && produit.prix !== '') {
                var pEl = div.querySelector('.ligne-prix');
                if (pEl) pEl.value = produit.prix;
            }
            if (U && U.updateLigneRowTotal) U.updateLigneRowTotal(div);
            updateLignesUIDevis();
            updateRecapDevis();
        }

        function prefillDevisLignesEdit() {
            var rows = window.INVOICE_DEVIS_EDIT_LIGNES;
            if (!rows || !rows.length || !lignesContainerDevis) {
                return;
            }
            for (var d = 0; d < rows.length; d++) {
                var l = rows[d];
                addLigneDevis({
                    id: l.produit_id,
                    nom: l.nom,
                    prix: l.prix_unitaire,
                    prix_promotion: l.prix_promotion || '',
                    quantite: l.quantite
                });
            }
        }

        function doSearchDevis(q) {
            if (searchLoadingDevis) searchLoadingDevis.style.visibility = 'visible';
            fetch(ajaxUrlDevis + '?q=' + encodeURIComponent(q) + '&limit=25')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var items = data.items || [];
                    searchResultsDevis.innerHTML = '';
                    items.forEach(function(p) {
                        var el = document.createElement('div');
                        el.className = 'search-result-item';
                        el.innerHTML = '<span class="sr-nom">' + (p.nom || '') + '</span>';
                        el.addEventListener('mousedown', function(ev) { ev.preventDefault(); addLigneDevis(p); searchInputDevis.value = ''; searchResultsDevis.innerHTML = ''; });
                        searchResultsDevis.appendChild(el);
                    });
                    searchResultsDevis.setAttribute('aria-hidden', 'false');
                })
                .finally(function() { if (searchLoadingDevis) searchLoadingDevis.style.visibility = 'hidden'; });
        }
        var zoneSelectDevis = document.getElementById('zone_livraison_id');
        var adresseLivraisonDevis = document.getElementById('adresse_livraison');
        var adresseTaDevis = document.getElementById('adresse_livraison_ta');
        var fraisInputDevis = document.getElementById('frais_livraison');
        var remiseInputDevis = document.getElementById('remise_globale_pct_devis');
        var recapRemiseLineDevis = document.getElementById('recap-remise-line-devis');
        var recapRemisePctDevis = document.getElementById('recap-remise-pct-devis');
        var recapRemiseMontantDevis = document.getElementById('recap-remise-montant-devis');
        function getRemisePctDevis() {
            return remiseInputDevis ? Math.min(100, Math.max(0, parseFloat(remiseInputDevis.value) || 0)) : 0;
        }
        function getSousTotalDevis() {
            var U = window.FoutaAdminProduitSearchUi;
            if (U && U.getLignesSousTotal) {
                return U.getLignesSousTotal(lignesContainerDevis);
            }
            return 0;
        }
        function getFraisDevis() {
            if (!zoneSelectDevis || zoneSelectDevis.value === '' || zoneSelectDevis.value === 'custom') return 0;
            var opt = zoneSelectDevis.options[zoneSelectDevis.selectedIndex];
            return opt && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
        }
        function updateRecapDevis() {
            var sous = getSousTotalDevis();
            var frais = getFraisDevis();
            var brut = sous + frais;
            var remisePct = getRemisePctDevis();
            var remiseMontant = Math.round(brut * remisePct / 100);
            var total = Math.round(brut - remiseMontant);
            var elSt = document.getElementById('recap-sous-total');
            var elFr = document.getElementById('recap-frais');
            var elTot = document.getElementById('recap-total');
            if (elSt) elSt.textContent = Math.round(sous).toLocaleString('fr-FR') + ' FCFA';
            if (elFr) elFr.textContent = Math.round(frais).toLocaleString('fr-FR') + ' FCFA';
            if (remisePct > 0) {
                if (recapRemiseLineDevis) recapRemiseLineDevis.style.display = '';
                if (recapRemisePctDevis) recapRemisePctDevis.textContent = remisePct.toString().replace('.', ',');
                if (recapRemiseMontantDevis) recapRemiseMontantDevis.textContent = '-' + remiseMontant.toLocaleString('fr-FR') + ' FCFA';
            } else if (recapRemiseLineDevis) {
                recapRemiseLineDevis.style.display = 'none';
            }
            if (elTot) elTot.textContent = total.toLocaleString('fr-FR') + ' FCFA';
            if (fraisInputDevis) fraisInputDevis.value = frais;
        }
        if (zoneSelectDevis) zoneSelectDevis.addEventListener('change', function() {
            var val = zoneSelectDevis.value;
            var adresseCustomWrap = document.getElementById('adresse-custom-wrap');
            var adresseZoneDisplay = document.getElementById('adresse-zone-display');
            if (val === 'custom') {
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'block';
                if (adresseZoneDisplay) adresseZoneDisplay.style.display = 'none';
            } else if (val !== '') {
                var opt = zoneSelectDevis.options[zoneSelectDevis.selectedIndex];
                if (adresseLivraisonDevis && opt) adresseLivraisonDevis.value = opt.dataset.adresse || '';
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'none';
                if (adresseZoneDisplay) {
                    adresseZoneDisplay.textContent = opt && opt.dataset.adresse ? opt.dataset.adresse : '';
                    adresseZoneDisplay.style.display = 'block';
                }
            } else {
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'none';
                if (adresseZoneDisplay) adresseZoneDisplay.style.display = 'none';
            }
            updateRecapDevis();
        });
        if (remiseInputDevis) remiseInputDevis.addEventListener('input', updateRecapDevis);
        var UDevis = window.FoutaAdminProduitSearchUi;
        if (UDevis && UDevis.bindLignesLiveRecap) {
            UDevis.bindLignesLiveRecap(lignesContainerDevis, updateRecapDevis);
        }
        var searchTimeoutDevis;
        if (searchInputDevis) {
            searchInputDevis.addEventListener('input', function() {
                clearTimeout(searchTimeoutDevis);
                searchTimeoutDevis = setTimeout(function() { doSearchDevis(searchInputDevis.value.trim()); }, 250);
            });
            searchInputDevis.addEventListener('focus', function() {
                var q = searchInputDevis.value.trim();
                if (searchResultsDevis && (searchResultsDevis.getAttribute('aria-hidden') === 'true' || searchResultsDevis.innerHTML === '')) {
                    doSearchDevis(q);
                }
            });
            searchInputDevis.addEventListener('blur', function() {
                setTimeout(function() {
                    if (searchResultsDevis && !searchResultsDevis.contains(document.activeElement)) {
                        searchResultsDevis.innerHTML = '';
                        searchResultsDevis.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            if (searchResultsDevis) searchResultsDevis.addEventListener('mousedown', function(ev) { ev.preventDefault(); });
        }

        if (window.AdminClientSearchSync) {
            AdminClientSearchSync.init({
                searchInput: document.getElementById('search-client'),
                resultsEl: document.getElementById('search-client-results'),
                loadingEl: document.getElementById('search-client-loading'),
                nomInput: document.getElementById('client_nom'),
                telInput: document.getElementById('client_telephone'),
                userIdInput: document.getElementById('user_id'),
                selectedWrap: document.getElementById('client-selected'),
                selectedNomEl: document.getElementById('client-selected-nom'),
                selectedTelEl: document.getElementById('client-selected-tel'),
                clearBtn: document.getElementById('client-selected-clear'),
                ajaxUrl: '../devis/ajax_search_clients.php'
            });
        }

        var formDevis = document.getElementById('form-devis');
        if (formDevis) {
            formDevis.addEventListener('submit', function(ev) {
                if (zoneSelectDevis && zoneSelectDevis.value === 'custom' && adresseTaDevis && adresseLivraisonDevis) {
                    adresseLivraisonDevis.value = adresseTaDevis.value.trim();
                } else if (zoneSelectDevis && zoneSelectDevis.value && zoneSelectDevis.value !== 'custom') {
                    var opt = zoneSelectDevis.options[zoneSelectDevis.selectedIndex];
                    if (adresseLivraisonDevis && opt) adresseLivraisonDevis.value = opt.dataset.adresse || '';
                }
                if (adresseLivraisonDevis && !adresseLivraisonDevis.value.trim()) {
                    ev.preventDefault();
                    alert('Veuillez sélectionner une adresse de livraison.');
                }
            });
        }
        updateLignesUIDevis();

        if (modalBl && modalBl.classList.contains('modal-open')) {
            document.body.style.overflow = 'hidden';
            showTab('facture');
            prefillBlLignesEdit();
            if (zoneSelectBl && zoneSelectBl.value) {
                onZoneChangeBl();
            }
            updateRecapBl();
        }
        if (modalDevis && modalDevis.classList.contains('modal-open')) {
            document.body.style.overflow = 'hidden';
            showTab('devis');
            prefillDevisLignesEdit();
            if (zoneSelectDevis && zoneSelectDevis.value) {
                zoneSelectDevis.dispatchEvent(new Event('change'));
            }
            updateRecapDevis();
        }
    })();

    (function() {
        var modalAdd = document.getElementById('modal-add-contact-invoice');
        var btnAdd = document.getElementById('btn-add-contact-invoice');
        var btnAddClose = document.getElementById('modal-add-contact-invoice-close');
        var btnAddCancel = document.getElementById('modal-add-contact-invoice-cancel');

        function openAdd() {
            if (modalAdd) modalAdd.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeAdd() {
            if (modalAdd) modalAdd.classList.remove('show');
            document.body.style.overflow = '';
        }
        if (btnAdd) btnAdd.addEventListener('click', openAdd);
        if (btnAddClose) btnAddClose.addEventListener('click', closeAdd);
        if (btnAddCancel) btnAddCancel.addEventListener('click', closeAdd);
        if (modalAdd) modalAdd.addEventListener('click', function(e) { if (e.target === modalAdd) closeAdd(); });

        var modalEdit = document.getElementById('modal-edit-contact-invoice');
        var btnEditClose = document.getElementById('modal-edit-contact-invoice-close');
        var btnEditCancel = document.getElementById('modal-edit-contact-invoice-cancel');
        function openEdit(id, nom, prenom, telephone, email) {
            var elId = document.getElementById('edit_contact_id_invoice');
            var elNom = document.getElementById('edit_nom_invoice');
            var elPrenom = document.getElementById('edit_prenom_invoice');
            var elTel = document.getElementById('edit_telephone_invoice');
            var elEmail = document.getElementById('edit_email_invoice');
            if (elId) elId.value = id;
            if (elNom) elNom.value = nom || '';
            if (elPrenom) elPrenom.value = prenom || '';
            if (elTel) elTel.value = telephone || '';
            if (elEmail) elEmail.value = email || '';
            if (modalEdit) modalEdit.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeEdit() {
            if (modalEdit) modalEdit.classList.remove('show');
            document.body.style.overflow = '';
        }
        document.querySelectorAll('.btn-edit-contact-invoice').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openEdit(btn.dataset.id, btn.dataset.nom, btn.dataset.prenom, btn.dataset.telephone, btn.dataset.email);
            });
        });
        if (btnEditClose) btnEditClose.addEventListener('click', closeEdit);
        if (btnEditCancel) btnEditCancel.addEventListener('click', closeEdit);
        if (modalEdit) modalEdit.addEventListener('click', function(e) { if (e.target === modalEdit) closeEdit(); });
    })();
    </script>
</body>
</html>
