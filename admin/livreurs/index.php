<?php
/**
 * Livraisons — commandes (prise en charge par les livreurs)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce();

require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';
require_once __DIR__ . '/../../models/model_bl.php';

if (!admin_can_livreur_gps()) {
    header('Location: ../dashboard.php');
    exit;
}

$admin_role = normalize_admin_role($_SESSION['admin_role'] ?? 'admin');
$is_livreur = ($admin_role === 'livreur');
$is_admin = admin_can_manage_livreurs();
$admin_session_id = (int) $_SESSION['admin_id'];

$tables_ready = livreur_tracking_tables_ready();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tables_ready && ($is_livreur || $is_admin)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'commencer_livraison') {
        $commande_id = (int) ($_POST['commande_id'] ?? 0);
        $result = livreur_commencer_livraison($commande_id, $admin_session_id, [
            'driver_lat' => $_POST['driver_lat'] ?? '',
            'driver_lng' => $_POST['driver_lng'] ?? '',
            'driver_precision' => $_POST['driver_precision'] ?? '',
            'delivery_lat' => $_POST['delivery_lat'] ?? '',
            'delivery_lng' => $_POST['delivery_lng'] ?? '',
            'adresse_livraison' => $_POST['adresse_livraison'] ?? '',
        ], $is_livreur);
        if (!empty($result['ok'])) {
            header('Location: suivi.php?commande_id=' . (int) ($result['commande_id'] ?? $commande_id) . '&autostart=1');
            exit;
        }
        $error = $result['error'] ?? 'Impossible de démarrer la livraison.';
    } elseif ($action === 'commencer_livraison_facture') {
        $bl_id = (int) ($_POST['bl_id'] ?? 0);
        $result = livreur_commencer_livraison_facture($bl_id, $admin_session_id, [
            'driver_lat' => $_POST['driver_lat'] ?? '',
            'driver_lng' => $_POST['driver_lng'] ?? '',
            'driver_precision' => $_POST['driver_precision'] ?? '',
            'delivery_lat' => $_POST['delivery_lat'] ?? '',
            'delivery_lng' => $_POST['delivery_lng'] ?? '',
            'adresse_livraison' => $_POST['adresse_livraison'] ?? '',
        ], $is_livreur);
        if (!empty($result['ok'])) {
            header('Location: suivi.php?bl_id=' . (int) ($result['bl_id'] ?? $bl_id) . '&autostart=1');
            exit;
        }
        $error = $result['error'] ?? 'Impossible de démarrer la livraison de la facture.';
    }
}

$commandes_liste = $tables_ready
    ? livreur_get_commandes_livraison_list($is_livreur)
    : [];
$bl_tables_ok = bl_tables_available();
$factures_liste = ($tables_ready && $bl_tables_ok)
    ? livreur_get_factures_livraison_list($is_livreur)
    : [];

$tab_param = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
$active_tab = ($tab_param === 'commandes') ? 'commandes' : 'facture';
$tab_commandes_active = $active_tab === 'commandes';
$tab_facture_active = $active_tab === 'facture';

$today_ymd = date('Y-m-d');
$commandes_count = 0;
foreach ($commandes_liste as $cmd_row) {
    if (empty($cmd_row['date_commande'])) {
        continue;
    }
    if ($is_livreur || date('Y-m-d', strtotime($cmd_row['date_commande'])) === $today_ymd) {
        $commandes_count++;
    }
}
$factures_count = 0;
foreach ($factures_liste as $facture_row) {
    $date_source = !empty($facture_row['date_bl']) ? $facture_row['date_bl'] : ($facture_row['date_creation'] ?? '');
    if ($date_source === '') {
        continue;
    }
    if ($is_livreur || date('Y-m-d', strtotime($date_source)) === $today_ymd) {
        $factures_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_livreur ? 'Livraisons du jour' : 'Livreurs GPS'; ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-devis-compta-pages.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-invoice-onglets.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-livreur-suivi.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-livreurs-index">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="content-header content-header--livreurs">
    <h1><i class="fas fa-motorcycle" aria-hidden="true"></i> <?php echo $is_livreur ? 'Livraisons du jour' : 'Livreurs GPS'; ?></h1>
</div>

<?php if (!$tables_ready): ?>
<div class="message error">
    <i class="fas fa-database"></i>
    <span>Module non installé. Exécutez : <code>php migrations/run_add_livreur_tracking.php</code></span>
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="message success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($message); ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="message error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
<?php endif; ?>

<section class="livreur-card livreur-card--wide livreur-card--commandes-jour page-livreur-delivery-section">
    <div class="livreur-delivery-tabs-wrap">
        <div class="admin-devis-bl-tabs livreur-delivery-tabs" role="tablist" aria-label="Factures et commandes à livrer">
            <button type="button"
                class="admin-tab admin-tab--bl livreur-delivery-tab <?php echo $tab_facture_active ? 'is-active' : ''; ?>"
                id="livreur-tab-btn-facture"
                role="tab"
                aria-selected="<?php echo $tab_facture_active ? 'true' : 'false'; ?>"
                aria-controls="livreur-panel-facture"
                data-livreur-tab="facture"
                <?php echo !$bl_tables_ok ? 'disabled title="Module factures B2B indisponible"' : ''; ?>>
                <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                <span class="admin-tab__txt">Factures (<span class="livreur-tab-count" id="livreur-tab-count-facture"><?php echo (int) $factures_count; ?></span>)</span>
            </button>
            <button type="button"
                class="admin-tab admin-tab--devis livreur-delivery-tab <?php echo $tab_commandes_active ? 'is-active' : ''; ?>"
                id="livreur-tab-btn-commandes"
                role="tab"
                aria-selected="<?php echo $tab_commandes_active ? 'true' : 'false'; ?>"
                aria-controls="livreur-panel-commandes"
                data-livreur-tab="commandes">
                <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-shopping-bag"></i></span>
                <span class="admin-tab__txt">Commandes (<span class="livreur-tab-count" id="livreur-tab-count-commandes"><?php echo (int) $commandes_count; ?></span>)</span>
            </button>
        </div>
    </div>

    <?php if ($is_livreur): ?>
    <p class="livreur-section-hint">Prenez une commande ou consultez une facture à livrer<?php echo $is_livreur ? ' aujourd\'hui' : ''; ?>.</p>
    <?php endif; ?>

    <div class="invoice-panel-toolbar livreur-panel-toolbar">
        <div class="invoice-panel-toolbar-main">
            <div class="invoice-panel-search-bar">
                <label class="sr-only" for="livreur-search-input" id="livreur-search-label">Rechercher</label>
                <div class="invoice-panel-search-wrap">
                    <i class="fas fa-search invoice-panel-search-ic" aria-hidden="true"></i>
                    <input type="search"
                        id="livreur-search-input"
                        class="invoice-panel-search-input"
                        placeholder="<?php echo $tab_facture_active ? 'Nom client, téléphone…' : 'Nom client, téléphone, n° commande…'; ?>"
                        autocomplete="off"
                        inputmode="search"
                        data-live-search-input>
                </div>
            </div>
            <?php if ($is_admin): ?>
            <div class="invoice-panel-period-trigger">
                <button type="button" class="btn-secondary invoice-period-toggle" id="livreur-period-toggle" aria-expanded="false" aria-controls="livreur-period-panel" aria-label="Filtrer par période">
                    <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                    <span class="invoice-period-toggle__label">Période</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($is_admin): ?>
        <div class="invoice-period-panel" id="livreur-period-panel" hidden>
            <div class="invoice-period-presets" role="group" aria-label="Périodes rapides livraisons">
                <button type="button" class="invoice-period-preset is-active" data-preset="today">Aujourd'hui</button>
                <button type="button" class="invoice-period-preset" data-preset="week">7 jours</button>
                <button type="button" class="invoice-period-preset" data-preset="month">Ce mois</button>
                <button type="button" class="invoice-period-preset" data-preset="all">Tout</button>
            </div>
            <div class="admin-filters-bar invoice-period-fields">
                <div class="admin-filter-field">
                    <label for="livreur-date-debut">Du</label>
                    <input type="date" id="livreur-date-debut">
                </div>
                <div class="admin-filter-field">
                    <label for="livreur-date-fin">Au</label>
                    <input type="date" id="livreur-date-fin">
                </div>
                <div class="admin-filter-actions">
                    <button type="button" class="btn-primary" id="livreur-period-apply">Appliquer</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($is_admin): ?>
    <p class="invoice-period-summary" id="livreur-period-summary" aria-live="polite"></p>
    <?php endif; ?>

    <p class="livreur-search-summary" id="livreur-search-summary" aria-live="polite"></p>

    <div id="livreur-panel-commandes"
        class="livreur-tab-panel tab-panel-devis-bl <?php echo $tab_commandes_active ? 'is-active' : ''; ?>"
        role="tabpanel"
        aria-labelledby="livreur-tab-btn-commandes"
        <?php echo $tab_commandes_active ? '' : 'hidden'; ?>>
        <?php if (empty($commandes_liste)): ?>
            <p class="livreur-empty">Aucune commande à livrer<?php echo $is_livreur ? ' aujourd\'hui' : ''; ?>.</p>
        <?php else: ?>
        <div class="livreur-table-wrap">
            <table class="livreur-table livreur-table--jour">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="livreur-cmd-list-body">
                <?php foreach ($commandes_liste as $cmd): ?>
                    <?php
                    $cmd_livreur_id = !empty($cmd['livreur_id']) ? (int) $cmd['livreur_id'] : null;
                    $prise_par_moi = $cmd_livreur_id === $admin_session_id;
                    $prise_par_autre = $cmd_livreur_id !== null && !$prise_par_moi;
                    $disponible = $cmd_livreur_id === null;
                    $client_nom = trim((string) ($cmd['user_prenom'] ?? $cmd['client_prenom'] ?? '') . ' ' . (string) ($cmd['user_nom'] ?? $cmd['client_nom'] ?? ''));
                    $client_tel = trim((string) ($cmd['user_telephone'] ?? $cmd['client_telephone'] ?? $cmd['telephone_livraison'] ?? ''));
                    $date_iso = !empty($cmd['date_commande']) ? date('Y-m-d', strtotime($cmd['date_commande'])) : '';
                    $search_blob = htmlspecialchars(livreur_commande_search_blob($cmd), ENT_QUOTES, 'UTF-8');
                    $delivery_lat = livreur_parse_coord($cmd['delivery_latitude'] ?? null);
                    $delivery_lng = livreur_parse_coord($cmd['delivery_longitude'] ?? null);
                    ?>
                    <tr class="livreur-cmd-row" data-search="<?php echo $search_blob; ?>" data-date="<?php echo htmlspecialchars($date_iso, ENT_QUOTES, 'UTF-8'); ?>">
                        <td data-label="Client">
                            <?php if ($client_nom !== ''): ?>
                                <span class="livreur-cmd-client"><i class="fas fa-user" aria-hidden="true"></i> <?php echo htmlspecialchars($client_nom); ?></span>
                            <?php endif; ?>
                            <?php if ($client_tel !== ''): ?>
                                <br><span class="livreur-cmd-tel"><i class="fas fa-phone" aria-hidden="true"></i> <?php echo htmlspecialchars($client_tel); ?></span>
                            <?php endif; ?>
                            <br><small><?php echo htmlspecialchars($cmd['adresse_livraison'] ?? ''); ?></small>
                        </td>
                        <td data-label="Statut">
                            <span class="livreur-badge livreur-badge--statut"><?php echo htmlspecialchars(livreur_statut_label($cmd['statut'] ?? '')); ?></span>
                            <?php if ($prise_par_moi): ?>
                                <br><small class="livreur-cmd-mine">Votre livraison</small>
                            <?php elseif ($prise_par_autre): ?>
                                <br><small class="livreur-cmd-taken">Prise par <?php echo htmlspecialchars(trim(($cmd['livreur_prenom'] ?? '') . ' ' . ($cmd['livreur_nom'] ?? ''))); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="livreur-actions" data-label="Action">
                            <?php if (($is_livreur || $is_admin) && $disponible): ?>
                            <button type="button"
                                class="btn-primary btn-sm livreur-btn-prendre"
                                data-livraison-type="commande"
                                data-commande-id="<?php echo (int) $cmd['id']; ?>"
                                    data-numero="<?php echo htmlspecialchars($cmd['numero_commande'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-adresse="<?php echo htmlspecialchars($cmd['adresse_livraison'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-delivery-lat="<?php echo $delivery_lat !== null ? htmlspecialchars((string) $delivery_lat, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                    data-delivery-lng="<?php echo $delivery_lng !== null ? htmlspecialchars((string) $delivery_lng, ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    <i class="fas fa-hand-pointer" aria-hidden="true"></i>
                                    <span class="livreur-btn-text livreur-btn-text--full">Prendre la commande</span>
                                    <span class="livreur-btn-text livreur-btn-text--short">Prendre</span>
                                </button>
                            <?php elseif (($is_livreur || $is_admin) && $prise_par_moi): ?>
                                <a href="suivi.php?commande_id=<?php echo (int) $cmd['id']; ?>&amp;autostart=1" class="btn-secondary btn-sm livreur-btn-suivi">
                                    <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                                    <span class="livreur-btn-text livreur-btn-text--full">Suivi GPS</span>
                                    <span class="livreur-btn-text livreur-btn-text--short">GPS</span>
                                </a>
                            <?php elseif ($is_livreur && $prise_par_autre): ?>
                                <span class="livreur-badge livreur-badge--off">Indisponible</span>
                            <?php elseif ($is_admin && $prise_par_autre): ?>
                                <a href="suivi.php?commande_id=<?php echo (int) $cmd['id']; ?>" class="btn-link livreur-btn-link"><i class="fas fa-map-location-dot" aria-hidden="true"></i> GPS</a>
                            <?php elseif ($is_admin && $cmd_livreur_id): ?>
                                <a href="suivi.php?commande_id=<?php echo (int) $cmd['id']; ?>" class="btn-link livreur-btn-link"><i class="fas fa-map-location-dot" aria-hidden="true"></i> GPS</a>
                            <?php else: ?>
                                <span class="livreur-badge livreur-badge--actif">Disponible</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="livreur-empty livreur-no-results" id="livreur-no-results-commandes" hidden>
            <i class="fas fa-search"></i> Aucune commande ne correspond à votre recherche ou à la période sélectionnée.
        </p>
        <?php endif; ?>
    </div>

    <div id="livreur-panel-facture"
        class="livreur-tab-panel tab-panel-devis-bl <?php echo $tab_facture_active ? 'is-active' : ''; ?>"
        role="tabpanel"
        aria-labelledby="livreur-tab-btn-facture"
        <?php echo $tab_facture_active ? '' : 'hidden'; ?>>
        <?php if (!$bl_tables_ok): ?>
            <p class="livreur-empty">Module factures B2B indisponible.</p>
        <?php elseif (empty($factures_liste)): ?>
            <p class="livreur-empty">Aucune facture à livrer<?php echo $is_livreur ? ' aujourd\'hui' : ''; ?>.</p>
        <?php else: ?>
        <div class="livreur-table-wrap">
            <table class="livreur-table livreur-table--jour livreur-table--factures">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="livreur-facture-list-body">
                <?php foreach ($factures_liste as $f): ?>
                    <?php
                    $fid = (int) $f['id'];
                    $client_label = trim($f['raison_sociale'] ?? '') ?: '—';
                    $client_tel = trim((string) ($f['client_telephone'] ?? ''));
                    $adresse_facture = livreur_facture_adresse_affichage($f);
                    $date_source = !empty($f['date_bl']) ? $f['date_bl'] : ($f['date_creation'] ?? 'now');
                    $date_iso = date('Y-m-d', strtotime($date_source));
                    $search_blob = htmlspecialchars(livreur_facture_search_blob($f), ENT_QUOTES, 'UTF-8');
                    $bl_livreur_id = !empty($f['livreur_id']) ? (int) $f['livreur_id'] : null;
                    $prise_par_moi = $bl_livreur_id === $admin_session_id;
                    $prise_par_autre = $bl_livreur_id !== null && !$prise_par_moi;
                    $disponible = $bl_livreur_id === null;
                    $delivery_lat = livreur_parse_coord($f['delivery_latitude'] ?? null);
                    $delivery_lng = livreur_parse_coord($f['delivery_longitude'] ?? null);
                    $statut_livraison = livreur_facture_statut_livraison($f);
                    ?>
                    <tr class="livreur-cmd-row livreur-facture-row" data-search="<?php echo $search_blob; ?>" data-date="<?php echo htmlspecialchars($date_iso, ENT_QUOTES, 'UTF-8'); ?>">
                        <td data-label="Client">
                            <?php if ($client_label !== '' && $client_label !== '—'): ?>
                                <span class="livreur-cmd-client"><i class="fas fa-building" aria-hidden="true"></i> <?php echo htmlspecialchars($client_label); ?></span>
                            <?php endif; ?>
                            <?php if ($client_tel !== ''): ?>
                                <br><span class="livreur-cmd-tel"><i class="fas fa-phone" aria-hidden="true"></i> <?php echo htmlspecialchars($client_tel); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Statut">
                            <span class="livreur-badge livreur-badge--statut"><?php echo htmlspecialchars($statut_livraison); ?></span>
                            <?php if ($prise_par_moi): ?>
                                <br><small class="livreur-cmd-mine">Votre livraison</small>
                            <?php elseif ($prise_par_autre): ?>
                                <br><small class="livreur-cmd-taken">Prise par <?php echo htmlspecialchars(trim(($f['livreur_prenom'] ?? '') . ' ' . ($f['livreur_nom'] ?? ''))); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="livreur-actions" data-label="Action">
                            <?php if (($is_livreur || $is_admin) && $disponible): ?>
                                <button type="button"
                                    class="btn-primary btn-sm livreur-btn-prendre"
                                    data-livraison-type="facture"
                                    data-bl-id="<?php echo $fid; ?>"
                                    data-client="<?php echo htmlspecialchars($client_label, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-adresse="<?php echo htmlspecialchars($adresse_facture, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-delivery-lat="<?php echo $delivery_lat !== null ? htmlspecialchars((string) $delivery_lat, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                    data-delivery-lng="<?php echo $delivery_lng !== null ? htmlspecialchars((string) $delivery_lng, ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    <i class="fas fa-hand-pointer" aria-hidden="true"></i>
                                    <span class="livreur-btn-text livreur-btn-text--full">Prendre la facture</span>
                                    <span class="livreur-btn-text livreur-btn-text--short">Prendre</span>
                                </button>
                            <?php elseif (($is_livreur || $is_admin) && $prise_par_moi): ?>
                                <a href="suivi.php?bl_id=<?php echo $fid; ?>&amp;autostart=1" class="btn-secondary btn-sm livreur-btn-suivi">
                                    <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                                    <span class="livreur-btn-text livreur-btn-text--full">Suivi GPS</span>
                                    <span class="livreur-btn-text livreur-btn-text--short">GPS</span>
                                </a>
                            <?php elseif ($is_livreur && $prise_par_autre): ?>
                                <span class="livreur-badge livreur-badge--off">Indisponible</span>
                            <?php elseif ($is_admin && $prise_par_autre): ?>
                                <a href="suivi.php?bl_id=<?php echo $fid; ?>" class="btn-link livreur-btn-link"><i class="fas fa-map-location-dot" aria-hidden="true"></i> GPS</a>
                            <?php else: ?>
                                <span class="livreur-badge livreur-badge--actif">Disponible</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="livreur-empty livreur-no-results" id="livreur-no-results-factures" hidden>
            <i class="fas fa-search"></i> Aucune facture ne correspond à votre recherche ou à la période sélectionnée.
        </p>
        <?php endif; ?>
    </div>
</section>

<?php if ($tables_ready && ($is_livreur || $is_admin)): ?>
<div id="livreur-demarrage-panel" class="livreur-demarrage-panel" hidden aria-hidden="true">
    <div class="livreur-demarrage-panel__backdrop" data-livreur-demarrage-close aria-hidden="true"></div>
    <div class="livreur-demarrage-panel__card" role="dialog" aria-modal="true" aria-labelledby="livreur-demarrage-title">
        <header class="livreur-demarrage-panel__head">
            <h3 id="livreur-demarrage-title"><i class="fas fa-route" aria-hidden="true"></i> Démarrer la livraison</h3>
            <p class="livreur-demarrage-panel__cmd"><span id="livreur-demarrage-label">Commande</span> <strong id="livreur-demarrage-numero"></strong></p>
            <button type="button" class="livreur-demarrage-panel__close" data-livreur-demarrage-close aria-label="Fermer">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>

        <form method="post" id="livreur-demarrage-form" class="livreur-demarrage-form">
            <input type="hidden" name="action" id="livreur-demarrage-action" value="commencer_livraison">
            <input type="hidden" name="commande_id" id="livreur-demarrage-commande-id" value="">
            <input type="hidden" name="bl_id" id="livreur-demarrage-bl-id" value="">
            <input type="hidden" name="driver_lat" id="livreur-driver-lat" value="">
            <input type="hidden" name="driver_lng" id="livreur-driver-lng" value="">
            <input type="hidden" name="driver_precision" id="livreur-driver-precision" value="">
            <input type="hidden" name="delivery_lat" id="livreur-delivery-lat" value="">
            <input type="hidden" name="delivery_lng" id="livreur-delivery-lng" value="">

            <div class="livreur-demarrage-field">
                <label for="livreur-driver-position">Votre position (départ)</label>
                <input type="text" id="livreur-driver-position" readonly placeholder="Capture GPS en cours…">
            </div>

            <div class="livreur-demarrage-field livreur-demarrage-field--address">
                <label for="livreur-demarrage-adresse">Adresse du client (arrivée)</label>
                <div class="livreur-address-autocomplete" id="livreur-address-autocomplete">
                    <textarea name="adresse_livraison" id="livreur-demarrage-adresse" rows="2" required placeholder="Quartier, rue, ville…" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="search" inputmode="search" role="combobox" aria-autocomplete="list" aria-controls="livreur-address-suggest" aria-expanded="false"></textarea>
                    <ul id="livreur-address-suggest" class="livreur-address-suggest" role="listbox" hidden aria-label="Suggestions d'adresse"></ul>
                </div>
            </div>

            <div id="livreur-demarrage-status" class="livreur-demarrage-status" data-state="pending" aria-live="polite"></div>

            <div id="livreur-demarrage-map" class="livreur-demarrage-map" aria-label="Carte départ et arrivée"></div>

            <div class="livreur-demarrage-legend">
                <span><i class="fas fa-motorcycle" aria-hidden="true"></i> Départ</span>
                <span><i class="fas fa-house" aria-hidden="true"></i> Arrivée</span>
                <span><i class="fas fa-route" aria-hidden="true"></i> Itinéraire</span>
            </div>

            <div class="livreur-demarrage-actions">
                <button type="button" class="btn-secondary" data-livreur-demarrage-close>Annuler</button>
                <button type="submit" class="btn-primary livreur-demarrage-submit">
                    <i class="fas fa-play" aria-hidden="true"></i> Commencer la livraison
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
window.LIVREUR_INDEX_UI = {
    enablePeriod: <?php echo $is_admin ? 'true' : 'false'; ?>,
    activeTab: <?php echo json_encode($active_tab, JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="/js/livreur-route-api.js<?php echo asset_version_query(); ?>"></script>
<script src="/js/admin-livreur-demarrage.js<?php echo asset_version_query(); ?>"></script>
<script src="/js/admin-livreurs-index-ui.js<?php echo asset_version_query(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
