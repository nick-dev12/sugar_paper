<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Page de liste des commandes (Admin) — onglets : à traiter / livrées / annulées
 */

session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_zones_livraison.php';

$commandes_archive_mode = !empty($COMMANDES_ARCHIVE_MODE);
$commandes_archive_sql_mode = $commandes_archive_mode ? 'archived' : 'active';
if ($commandes_archive_mode && !admin_is_full_admin()) {
    header('Location: index.php');
    exit;
}
$commandes_hub_self = $commandes_archive_mode ? 'archives.php' : 'index.php';

$toutes_commandes = get_all_commandes(null, $commandes_archive_sql_mode);
if (!is_array($toutes_commandes)) {
    $toutes_commandes = [];
}
$zones_livraison = get_all_zones_livraison('actif');

$show_modal_commande_manuelle = !$commandes_archive_mode && isset($_GET['modal']) && $_GET['modal'] === 'commande_manuelle';
$commande_manuelle_erreur = $_SESSION['commande_manuelle_erreur'] ?? null;
$commande_manuelle_post = $_SESSION['commande_manuelle_post'] ?? null;
if (isset($_SESSION['commande_manuelle_erreur'])) unset($_SESSION['commande_manuelle_erreur']);
if (isset($_SESSION['commande_manuelle_post'])) unset($_SESSION['commande_manuelle_post']);

$tab_param = isset($_GET['tab']) ? (string) $_GET['tab'] : 'a_traiter';
if (!in_array($tab_param, ['a_traiter', 'livrees', 'annulees'], true)) {
    $tab_param = 'a_traiter';
}
$active_tab = $tab_param;

$commandes_a_traiter = array_values(array_filter($toutes_commandes, function ($commande) {
    return !in_array($commande['statut'] ?? '', ['livree', 'paye', 'annulee'], true);
}));

$commandes_livrees = array_values(array_filter($toutes_commandes, function ($commande) {
    return in_array($commande['statut'] ?? '', ['livree', 'paye'], true);
}));

$jours_precedents = isset($_GET['jours_precedents']) && $_GET['jours_precedents'] === '1';
$commandes_livrees_affichees = $commandes_livrees;
if ($active_tab === 'livrees' && !$jours_precedents && !$commandes_archive_mode) {
    $aujourd_hui = date('Y-m-d');
    $commandes_livrees_affichees = array_values(array_filter($commandes_livrees, function ($c) use ($aujourd_hui) {
        $date_ref = !empty($c['date_livraison']) ? $c['date_livraison'] : ($c['date_commande'] ?? '');
        if ($date_ref === '') {
            return false;
        }
        return date('Y-m-d', strtotime($date_ref)) === $aujourd_hui;
    }));
}

$commandes_annulees = array_values(array_filter($toutes_commandes, function ($commande) {
    return ($commande['statut'] ?? '') === 'annulee';
}));

$count_a_traiter = count($commandes_a_traiter);
$count_livrees = count($commandes_livrees);
$count_annulees = count($commandes_annulees);

$total_commandes = count_commandes_by_statut(null, $commandes_archive_sql_mode);
$en_attente = count_commandes_by_statut('en_attente', $commandes_archive_sql_mode);
$montant_total_a_traiter = array_sum(array_column($commandes_a_traiter, 'montant_total'));
$montant_total_livrees = get_montant_total_commandes('livree', $commandes_archive_sql_mode) + get_montant_total_commandes('paye', $commandes_archive_sql_mode);
$montant_total_annulees = get_montant_total_commandes('annulee', $commandes_archive_sql_mode);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $commandes_archive_mode ? 'Archives commandes' : 'Commandes'; ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-index.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-invoice-onglets.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-commandes-index<?php echo $commandes_archive_mode ? ' page-commandes-archives' : ''; ?>">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1>
            <i class="fas <?php echo $commandes_archive_mode ? 'fa-box-archive' : 'fa-shopping-bag'; ?>"></i>
            <?php echo $commandes_archive_mode ? 'Archives des commandes' : 'Commandes'; ?>
        </h1>
        <div class="header-actions">
            <?php if (admin_is_full_admin()): ?>
                <?php if ($commandes_archive_mode): ?>
                <a href="index.php" class="btn-secondary"><i class="fas fa-shopping-bag"></i> Commandes actives</a>
                <?php else: ?>
                <a href="archives.php" class="btn-secondary"><i class="fas fa-box-archive"></i> Archives</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (($_SESSION['admin_role'] ?? '') === 'admin' && !$commandes_archive_mode): ?>
            <a href="historique-ventes.php" class="btn-primary">
                <i class="fas fa-chart-line"></i> Historique des ventes & Comptabilité
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="message success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="message error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span>
    </div>
    <?php endif; ?>

    <div class="admin-devis-bl-tabs commandes-hub-tabs" role="tablist" aria-label="Statuts des commandes">
        <a href="<?php echo htmlspecialchars($commandes_hub_self); ?>?tab=a_traiter"
            class="admin-tab <?php echo $active_tab === 'a_traiter' ? 'is-active' : ''; ?>"
            role="tab" aria-selected="<?php echo $active_tab === 'a_traiter' ? 'true' : 'false'; ?>">
            <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-inbox"></i></span>
            <span class="admin-tab__txt">Reçues / non traitées (<?php echo (int) $count_a_traiter; ?>)</span>
        </a>
        <a href="<?php echo htmlspecialchars($commandes_hub_self); ?>?tab=livrees<?php echo $jours_precedents ? '&amp;jours_precedents=1' : ''; ?>"
            class="admin-tab <?php echo $active_tab === 'livrees' ? 'is-active' : ''; ?>"
            role="tab" aria-selected="<?php echo $active_tab === 'livrees' ? 'true' : 'false'; ?>">
            <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
            <span class="admin-tab__txt">Livrées (<?php echo (int) $count_livrees; ?>)</span>
        </a>
        <a href="<?php echo htmlspecialchars($commandes_hub_self); ?>?tab=annulees"
            class="admin-tab <?php echo $active_tab === 'annulees' ? 'is-active' : ''; ?>"
            role="tab" aria-selected="<?php echo $active_tab === 'annulees' ? 'true' : 'false'; ?>">
            <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-ban"></i></span>
            <span class="admin-tab__txt">Annulées (<?php echo (int) $count_annulees; ?>)</span>
        </a>
    </div>

    <?php if ($active_tab === 'a_traiter'): ?>
    <div class="commandes-stats commandes-stats--compact">
        <div class="stat-box">
            <h3>Total Commandes</h3>
            <div class="stat-value"><?php echo $total_commandes; ?></div>
        </div>
        <div class="stat-box">
            <h3>En Attente</h3>
            <div class="stat-value"><?php echo $en_attente; ?></div>
        </div>
    </div>
    <div class="comptabilite-box">
        <div class="comptabilite-label"><i class="fas fa-calculator"></i> Montant total des commandes à traiter</div>
        <div class="comptabilite-value"><?php echo number_format($montant_total_a_traiter, 0, ',', ' '); ?> FCFA</div>
    </div>

    <section class="content-section">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="fas fa-list"></i> Commandes à traiter (<?php echo count($commandes_a_traiter); ?>)</h2>
            </div>
            <?php if (!$commandes_archive_mode): ?>
            <div class="form-actions commandes-section-actions">
                <button type="button" class="btn-primary" id="btn-commande-manuelle"
                    aria-label="Ajouter une commande manuellement">
                    <i class="fas fa-plus-circle"></i> Ajouter une commande
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($commandes_a_traiter)): ?>
        <div class="empty-state">
            <i class="fas fa-shopping-bag"></i>
            <h3>Aucune commande à traiter</h3>
            <p><?php echo $commandes_archive_mode ? 'Aucune commande archivée dans cette catégorie.' : 'Toutes les commandes ont été traitées et livrées.'; ?></p>
        </div>
        <?php else: ?>
        <div class="commandes-table-wrap">
            <table class="data-table commandes-data-table">
                <colgroup>
                    <col class="commandes-col-client">
                    <col class="commandes-col-montant">
                    <col class="commandes-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th class="commandes-col-montant">Montant</th>
                        <th class="commandes-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes_a_traiter as $commande): ?>
                    <?php
                    $client_nom = trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? '')) ?: '—';
                    $telephone_aff = trim($commande['telephone_livraison'] ?? '') ?: '—';
                    $date_aff = date('d/m/Y H:i', strtotime($commande['date_commande']));
                    ?>
                    <tr>
                        <td data-label="Client">
                            <span class="commandes-cell-nom"><?php echo htmlspecialchars($client_nom); ?></span>
                            <span class="commandes-cell-tel"><?php echo htmlspecialchars($telephone_aff); ?></span>
                        </td>
                        <td data-label="Montant" class="commandes-col-montant">
                            <span class="commandes-cell-prix"><?php echo number_format((float) $commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                            <span class="commandes-cell-date"><?php echo htmlspecialchars($date_aff); ?></span>
                        </td>
                        <td data-label="Actions" class="commandes-col-actions">
                            <a href="details.php?id=<?php echo (int) $commande['id']; ?>" class="btn-view">
                                <i class="fas fa-eye" aria-hidden="true"></i> Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <?php elseif ($active_tab === 'livrees'): ?>
    <div class="commandes-stats commandes-stats--compact">
        <div class="stat-box">
            <h3>Total Commandes</h3>
            <div class="stat-value"><?php echo $total_commandes; ?></div>
        </div>
        <div class="stat-box">
            <h3>Commandes Livrées</h3>
            <div class="stat-value"><?php echo $count_livrees; ?></div>
        </div>
    </div>
    <div class="comptabilite-box">
        <div class="comptabilite-label"><i class="fas fa-calculator"></i> Montant total des commandes livrées</div>
        <div class="comptabilite-value"><?php echo number_format($montant_total_livrees, 0, ',', ' '); ?> FCFA</div>
    </div>
    <section class="content-section">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="fas fa-check-circle"></i> Commandes livrées (<?php echo count($commandes_livrees_affichees); ?>)</h2>
            </div>
            <?php if (!$commandes_archive_mode): ?>
            <div class="form-actions">
                <?php if ($jours_precedents): ?>
                <a href="<?php echo htmlspecialchars($commandes_hub_self); ?>?tab=livrees" class="btn-link">
                    <i class="fas fa-calendar-day"></i> Uniquement aujourd'hui
                </a>
                <?php else: ?>
                <a href="<?php echo htmlspecialchars($commandes_hub_self); ?>?tab=livrees&amp;jours_precedents=1" class="btn-link">
                    <i class="fas fa-calendar-alt"></i> Inclure les jours précédents
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if (empty($commandes_livrees_affichees)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>Aucune commande livrée</h3>
            <p><?php echo (!$jours_precedents && !$commandes_archive_mode) ? 'Aucune livraison aujourd\'hui.' : 'Aucune commande livrée dans cette liste.'; ?></p>
        </div>
        <?php else: ?>
        <div class="commandes-grid">
            <?php foreach ($commandes_livrees_affichees as $commande): ?>
            <div class="commande-item">
                <div class="commande-header">
                    <div class="commande-info">
                        <h3>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></h3>
                        <p>
                            <strong>Client:</strong>
                            <?php echo htmlspecialchars(trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''))); ?><br>
                            <span class="client-email"><?php echo !empty($commande['user_email']) ? htmlspecialchars($commande['user_email']) : '—'; ?></span>
                        </p>
                        <p class="commande-date">Date: <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></p>
                    </div>
                    <span class="commande-statut statut-<?php echo htmlspecialchars($commande['statut']); ?>">
                        <?php echo $commande['statut'] === 'paye' ? '<i class="fas fa-money-bill-wave"></i> Payée' : '<i class="fas fa-check-circle"></i> Livrée'; ?>
                    </span>
                </div>
                <div class="commande-details">
                    <div class="detail-item">
                        <label>Montant total</label>
                        <div class="value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</div>
                    </div>
                    <div class="detail-item">
                        <label>Adresse</label>
                        <div class="value small"><?php echo htmlspecialchars(substr((string) ($commande['adresse_livraison'] ?? ''), 0, 40)); ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Téléphone</label>
                        <div class="value"><?php echo htmlspecialchars($commande['telephone_livraison'] ?? ''); ?></div>
                    </div>
                </div>
                <a href="details.php?id=<?php echo (int) $commande['id']; ?>" class="btn-view">
                    <i class="fas fa-eye"></i> Voir les détails
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php else: ?>
    <div class="commandes-stats commandes-stats--compact">
        <div class="stat-box">
            <h3>Total Commandes</h3>
            <div class="stat-value"><?php echo $total_commandes; ?></div>
        </div>
        <div class="stat-box">
            <h3>Commandes Annulées</h3>
            <div class="stat-value"><?php echo $count_annulees; ?></div>
        </div>
    </div>
    <div class="comptabilite-box">
        <div class="comptabilite-label"><i class="fas fa-calculator"></i> Montant total des commandes annulées</div>
        <div class="comptabilite-value"><?php echo number_format($montant_total_annulees, 0, ',', ' '); ?> FCFA</div>
    </div>
    <section class="content-section">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="fas fa-ban"></i> Commandes annulées (<?php echo count($commandes_annulees); ?>)</h2>
            </div>
        </div>
        <?php if (empty($commandes_annulees)): ?>
        <div class="empty-state">
            <i class="fas fa-ban"></i>
            <h3>Aucune commande annulée</h3>
        </div>
        <?php else: ?>
        <div class="commandes-grid">
            <?php foreach ($commandes_annulees as $commande): ?>
            <div class="commande-item">
                <div class="commande-header">
                    <div class="commande-info">
                        <h3>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></h3>
                        <p>
                            <strong>Client:</strong>
                            <?php echo htmlspecialchars(trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''))); ?>
                        </p>
                        <p class="commande-date">Date: <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></p>
                    </div>
                    <span class="commande-statut statut-annulee"><i class="fas fa-ban"></i> Annulée</span>
                </div>
                <div class="commande-details">
                    <div class="detail-item">
                        <label>Montant total</label>
                        <div class="value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</div>
                    </div>
                </div>
                <a href="details.php?id=<?php echo (int) $commande['id']; ?>" class="btn-view">
                    <i class="fas fa-eye"></i> Voir les détails
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

<?php if (!$commandes_archive_mode): ?>
    <!-- Modal commande manuelle (plein écran) -->
    <div id="modal-commande-manuelle"
        class="modal-commande-manuelle <?php echo $show_modal_commande_manuelle ? 'modal-open' : ''; ?>" role="dialog"
        aria-modal="true" aria-labelledby="modal-commande-manuelle-title">
        <div class="modal-commande-manuelle-backdrop"></div>
        <div class="modal-commande-manuelle-content">
            <div class="modal-commande-manuelle-header">
                <h2 id="modal-commande-manuelle-title"><i class="fas fa-plus-circle"></i> Nouvelle commande manuelle
                </h2>
                <button type="button" class="modal-commande-manuelle-close" id="modal-commande-manuelle-close"
                    aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-commande-manuelle-body">
                <?php if ($commande_manuelle_erreur): ?>
                <div class="message error modal-commande-erreur">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($commande_manuelle_erreur); ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="create_manuelle.php" id="form-commande-manuelle">
                    <div class="form-commande-manuelle-grid">
                        <div class="form-commande-manuelle-col form-col-articles">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-search"></i>
                                    <h3>Rechercher un produit</h3>
                                </div>
                                <div class="form-group search-group">
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-produit" name="search_produit"
                                            placeholder="Tapez le nom du produit ou de la catégorie..."
                                            autocomplete="off">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-loading" aria-hidden="true"><i
                                                class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <div id="search-produit-results" class="search-produit-results" role="listbox"
                                        aria-hidden="true"></div>
                                </div>
                                <p class="form-hint"><i class="fas fa-info-circle"></i> Tapez au moins 1 caractère ou
                                    laissez vide pour afficher tous les produits en stock.</p>
                            </div>

                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h3>Produits de la commande</h3>
                                    <span class="lignes-count" id="lignes-count">0 produit(s)</span>
                                </div>
                                <div id="lignes-commande" class="lignes-commande">
                                    <div class="lignes-empty" id="lignes-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun produit ajouté. Utilisez la recherche ci-dessus.</p>
                                    </div>
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
                                    <label for="search-client">Rechercher un client</label>
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-client" placeholder="Nom, téléphone ou email..."
                                            autocomplete="off">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-client-loading"
                                            style="visibility:hidden;"><i class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <div id="search-client-results" class="search-produit-results" role="listbox"
                                        aria-hidden="true"
                                        style="position:absolute; left:0; right:0; top:100%; z-index:100;"></div>
                                    <p class="form-hint"><i class="fas fa-info-circle"></i> Recherchez un client
                                        existant ou saisissez manuellement ci-dessous.</p>
                                </div>
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label for="client_nom">Nom <span class="required">*</span></label>
                                        <input type="text" id="client_nom" name="client_nom" required
                                            value="<?php echo htmlspecialchars($commande_manuelle_post['client_nom'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="client_prenom">Prénom <span class="required">*</span></label>
                                        <input type="text" id="client_prenom" name="client_prenom" required
                                            value="<?php echo htmlspecialchars($commande_manuelle_post['client_prenom'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="client_telephone">Téléphone <span class="required">*</span></label>
                                    <input type="tel" id="client_telephone" name="client_telephone" required
                                        placeholder="Ex: 07 12 34 56 78"
                                        value="<?php echo htmlspecialchars($commande_manuelle_post['client_telephone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="client_email">Email <span class="optional">(optionnel)</span></label>
                                    <input type="email" id="client_email" name="client_email"
                                        placeholder="Si vide, aucun email de confirmation envoyé"
                                        value="<?php echo htmlspecialchars($commande_manuelle_post['client_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="zone_livraison_id"><i class="fas fa-map-marker-alt"></i> Adresse de
                                        livraison <span class="required">*</span></label>
                                    <select id="zone_livraison_id" name="zone_livraison_id">
                                        <option value="">— Sélectionnez une adresse —</option>
                                        <?php foreach ($zones_livraison as $z): ?>
                                        <option value="<?php echo (int) $z['id']; ?>"
                                            data-adresse="<?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>"
                                            data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                            <?php echo (isset($commande_manuelle_post['zone_livraison_id']) && (int)$commande_manuelle_post['zone_livraison_id'] === (int)$z['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                            (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="custom"
                                            <?php echo (isset($commande_manuelle_post['zone_livraison_id']) && $commande_manuelle_post['zone_livraison_id'] === 'custom') ? 'selected' : ''; ?>>
                                            — Adresse personnalisée —</option>
                                    </select>
                                    <div id="adresse-custom-wrap" class="adresse-custom-wrap"
                                        style="display:none; margin-top:10px;">
                                        <textarea id="adresse_livraison_ta" rows="3"
                                            placeholder="Saisissez l'adresse complète"><?php echo htmlspecialchars($commande_manuelle_post['adresse_livraison'] ?? ''); ?></textarea>
                                    </div>
                                    <div id="adresse-zone-display" class="adresse-zone-display"
                                        style="display:none; margin-top:8px; padding:10px; background:#f5f5f4; border-radius:8px;">
                                    </div>
                                    <input type="hidden" name="adresse_livraison" id="adresse_livraison" value="">
                                    <input type="hidden" name="frais_livraison" id="frais_livraison" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <textarea id="notes" name="notes" rows="2"
                                        placeholder="Instructions supplémentaires..."><?php echo htmlspecialchars($commande_manuelle_post['notes'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Date de la commande</label>
                                    <div class="value-static"><i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d/m/Y à H:i'); ?></div>
                                </div>
                                <div class="commande-manuelle-recap">
                                    <div class="recap-line">
                                        <span>Sous-total produits</span>
                                        <span id="recap-sous-total">0 FCFA</span>
                                    </div>
                                    <div class="recap-line">
                                        <span>Frais de livraison</span>
                                        <span id="recap-frais">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-total">
                                        <span>Total</span>
                                        <span id="recap-total">0 FCFA</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-commande-manuelle-actions">
                        <button type="button" class="btn-secondary" id="modal-commande-manuelle-cancel">Annuler</button>
                        <button type="submit" class="btn-primary btn-submit-commande" name="submit_commande_manuelle">
                            <i class="fas fa-check"></i> Enregistrer la commande (statut: En attente)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    (function() {
        var modal = document.getElementById('modal-commande-manuelle');
        var btnOpen = document.getElementById('btn-commande-manuelle');
        var btnClose = document.getElementById('modal-commande-manuelle-close');
        var btnCancel = document.getElementById('modal-commande-manuelle-cancel');
        var backdrop = modal ? modal.querySelector('.modal-commande-manuelle-backdrop') : null;
        var searchInput = document.getElementById('search-produit');
        var searchResults = document.getElementById('search-produit-results');
        var searchLoading = document.getElementById('search-loading');
        var lignesContainer = document.getElementById('lignes-commande');
        var lignesEmpty = document.getElementById('lignes-empty');
        var lignesCount = document.getElementById('lignes-count');
        var ligneIndex = 0;
        var ajaxUrl = 'ajax_search_produits.php';

        function openModal() {
            if (modal) modal.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            if (modal) modal.classList.remove('modal-open');
            document.body.style.overflow = '';
        }

        if (btnOpen) btnOpen.addEventListener('click', openModal);
        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnCancel) btnCancel.addEventListener('click', closeModal);
        if (backdrop) backdrop.addEventListener('click', closeModal);

        if (modal && modal.classList.contains('modal-open')) {
            document.body.style.overflow = 'hidden';
        }

        function updateLignesUI() {
            var items = lignesContainer ? lignesContainer.querySelectorAll('.ligne-commande-item') : [];
            var n = items.length;
            if (lignesEmpty) lignesEmpty.style.display = n === 0 ? 'flex' : 'none';
            if (lignesCount) lignesCount.textContent = n + ' produit(s)';
        }

        function addLigne(produit) {
            var prix = parseFloat(produit.prix) || 0;
            var prixPromo = produit.prix_promotion && parseFloat(produit.prix_promotion) > 0 ? parseFloat(produit
                .prix_promotion) : '';
            var nom = (produit.nom || '');
            var idx = ligneIndex++;
            var div = document.createElement('div');
            div.className = 'ligne-commande-item';
            div.dataset.produitId = produit.id;
            div.innerHTML =
                '<input type="hidden" name="lignes[' + idx + '][produit_id]" value="' + produit.id + '">' +
                '<input type="text" name="lignes[' + idx + '][nom_produit]" value="' + (nom.replace(/"/g,
                '&quot;')) +
                '" placeholder="Nom du produit (modifiable)" class="ligne-nom-input" title="Modifier le nom affiché">' +
                '<input type="number" name="lignes[' + idx + '][quantite]" value="1" min="1" max="' + (produit
                    .stock_dispo || produit.stock || 999) + '" class="ligne-qte" title="Quantité">' +
                '<input type="number" name="lignes[' + idx + '][prix_unitaire]" value="' + (prixPromo || prix) +
                '" min="0" step="0.01" class="ligne-prix" title="Prix unitaire (FCFA)">' +
                '<input type="number" name="lignes[' + idx + '][prix_promotion]" value="' + (prixPromo || '') +
                '" min="0" step="0.01" placeholder="Optionnel" class="ligne-prix-promo" title="Prix promo (optionnel)">' +
                '<button type="button" class="ligne-remove" aria-label="Retirer"><i class="fas fa-trash"></i></button>';
            if (lignesEmpty) lignesEmpty.style.display = 'none';
            div.querySelector('.ligne-remove').addEventListener('click', function() {
                div.remove();
                updateLignesUI();
                updateRecap();
            });
            lignesContainer.appendChild(div);
            updateLignesUI();
            updateRecap();
        }

        function doSearch(q) {
            if (searchLoading) searchLoading.style.visibility = 'visible';
            fetch(ajaxUrl + '?q=' + encodeURIComponent(q) + '&limit=25')
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    var items = data.items || [];
                    searchResults.innerHTML = '';
                    if (items.length === 0) {
                        searchResults.innerHTML =
                            '<div class="search-no-results"><i class="fas fa-box-open"></i> Aucun produit en stock trouvé.</div>';
                    } else {
                        items.forEach(function(p) {
                            var el = document.createElement('div');
                            el.className = 'search-result-item';
                            el.setAttribute('role', 'option');
                            el.setAttribute('tabindex', '0');
                            var stock = p.stock_dispo || p.stock || 0;
                            var prix = parseFloat(p.prix) || 0;
                            el.innerHTML = '<span class="sr-nom">' + (p.nom || '') + '</span>' +
                                '<span class="sr-meta">' + (p.categorie_nom || '') + ' &bull; Stock: ' +
                                stock + ' &bull; ' + prix + ' FCFA</span>';
                            el.addEventListener('mousedown', function(ev) {
                                ev.preventDefault();
                                addLigne(p);
                                searchInput.value = '';
                                searchResults.innerHTML = '';
                                searchResults.setAttribute('aria-hidden', 'true');
                            });
                            el.addEventListener('keydown', function(ev) {
                                if (ev.key === 'Enter' || ev.key === ' ') {
                                    ev.preventDefault();
                                    addLigne(p);
                                    searchInput.value = '';
                                    searchResults.innerHTML = '';
                                    searchResults.setAttribute('aria-hidden', 'true');
                                }
                            });
                            searchResults.appendChild(el);
                        });
                    }
                    searchResults.setAttribute('aria-hidden', 'false');
                })
                .catch(function() {
                    searchResults.innerHTML =
                        '<div class="search-no-results"><i class="fas fa-exclamation-triangle"></i> Erreur de recherche. Vérifiez la connexion.</div>';
                })
                .finally(function() {
                    if (searchLoading) searchLoading.style.visibility = 'hidden';
                });
        }

        var zoneSelect = document.getElementById('zone_livraison_id');
        var adresseCustomWrap = document.getElementById('adresse-custom-wrap');
        var adresseZoneDisplay = document.getElementById('adresse-zone-display');
        var adresseLivraison = document.getElementById('adresse_livraison');
        var adresseTa = document.getElementById('adresse_livraison_ta');
        var fraisInput = document.getElementById('frais_livraison');
        var recapSousTotal = document.getElementById('recap-sous-total');
        var recapFrais = document.getElementById('recap-frais');
        var recapTotal = document.getElementById('recap-total');
        var formCommande = document.getElementById('form-commande-manuelle');

        function formatNumber(n) {
            return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function getSousTotal() {
            var total = 0;
            var items = lignesContainer ? lignesContainer.querySelectorAll('.ligne-commande-item') : [];
            items.forEach(function(row) {
                var qte = parseFloat(row.querySelector('.ligne-qte').value) || 0;
                var prix = parseFloat(row.querySelector('.ligne-prix').value) || 0;
                var promo = row.querySelector('.ligne-prix-promo');
                var p = promo && promo.value && parseFloat(promo.value) > 0 ? parseFloat(promo.value) :
                prix;
                total += p * qte;
            });
            return total;
        }

        function getFraisLivraison() {
            if (!zoneSelect || zoneSelect.value === '' || zoneSelect.value === 'custom') return 0;
            var opt = zoneSelect.options[zoneSelect.selectedIndex];
            return opt && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
        }

        function updateRecap() {
            var sousTotal = getSousTotal();
            var frais = getFraisLivraison();
            var total = sousTotal + frais;
            if (recapSousTotal) recapSousTotal.textContent = formatNumber(sousTotal) + ' FCFA';
            if (recapFrais) recapFrais.textContent = formatNumber(frais) + ' FCFA';
            if (recapTotal) recapTotal.textContent = formatNumber(total) + ' FCFA';
            if (fraisInput) fraisInput.value = frais;
        }

        function onZoneChange() {
            var val = zoneSelect ? zoneSelect.value : '';
            if (val === 'custom') {
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'block';
                if (adresseZoneDisplay) adresseZoneDisplay.style.display = 'none';
                if (adresseLivraison) adresseLivraison.value = '';
            } else if (val !== '') {
                var opt = zoneSelect.options[zoneSelect.selectedIndex];
                var adr = opt && opt.dataset.adresse ? opt.dataset.adresse : '';
                if (adresseLivraison) adresseLivraison.value = adr;
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'none';
                if (adresseZoneDisplay) {
                    adresseZoneDisplay.textContent = adr;
                    adresseZoneDisplay.style.display = 'block';
                }
            } else {
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'none';
                if (adresseZoneDisplay) adresseZoneDisplay.style.display = 'none';
                if (adresseLivraison) adresseLivraison.value = '';
            }
            updateRecap();
        }

        if (zoneSelect) {
            zoneSelect.addEventListener('change', onZoneChange);
        }

        function onLignesChange() {
            updateRecap();
        }
        if (lignesContainer) {
            lignesContainer.addEventListener('input', function(ev) {
                if (ev.target.classList.contains('ligne-qte') || ev.target.classList.contains(
                    'ligne-prix') || ev.target.classList.contains('ligne-prix-promo')) {
                    updateRecap();
                }
            });
        }

        if (formCommande) {
            formCommande.addEventListener('submit', function(ev) {
                if (zoneSelect && zoneSelect.value === 'custom' && adresseTa) {
                    if (adresseLivraison) adresseLivraison.value = adresseTa.value.trim();
                } else if (zoneSelect && zoneSelect.value && zoneSelect.value !== 'custom') {
                    onZoneChange();
                }
                if (adresseLivraison && !adresseLivraison.value.trim()) {
                    ev.preventDefault();
                    alert(
                        'Veuillez sélectionner une adresse de livraison ou saisir une adresse personnalisée.');
                    return false;
                }
            });
        }

        var searchTimeout;
        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                var q = searchInput.value.trim();
                searchTimeout = setTimeout(function() {
                    doSearch(q);
                }, 250);
            });
            searchInput.addEventListener('focus', function() {
                var q = searchInput.value.trim();
                if (searchResults.getAttribute('aria-hidden') === 'true' || searchResults.innerHTML ===
                    '') {
                    doSearch(q);
                }
            });
            searchInput.addEventListener('blur', function() {
                setTimeout(function() {
                    if (!searchResults.contains(document.activeElement)) {
                        searchResults.innerHTML = '';
                        searchResults.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchResults.addEventListener('mousedown', function(ev) {
                ev.preventDefault();
            });
        }

        var searchClientInput = document.getElementById('search-client');
        var searchClientResults = document.getElementById('search-client-results');
        var searchClientLoading = document.getElementById('search-client-loading');
        var clientNomInput = document.getElementById('client_nom');
        var clientPrenomInput = document.getElementById('client_prenom');
        var clientTelInput = document.getElementById('client_telephone');
        var clientEmailInput = document.getElementById('client_email');
        var clientSearchTimeout;
        if (searchClientInput && searchClientResults && clientNomInput && clientPrenomInput && clientTelInput) {
            function doClientSearch(q) {
                if (q.length < 1) {
                    searchClientResults.innerHTML = '';
                    searchClientResults.setAttribute('aria-hidden', 'true');
                    return;
                }
                if (searchClientLoading) searchClientLoading.style.visibility = 'visible';
                fetch('ajax_search_clients.php?q=' + encodeURIComponent(q) + '&limit=15')
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        searchClientResults.innerHTML = '';
                        if (data.length === 0) {
                            searchClientResults.innerHTML =
                                '<div class="search-no-results">Aucun client trouvé.</div>';
                        } else {
                            data.forEach(function(c) {
                                var el = document.createElement('div');
                                el.className = 'search-result-item';
                                el.setAttribute('role', 'option');
                                el.innerHTML = '<span class="sr-nom">' + (c.nom_complet || '') +
                                    '</span>' +
                                    '<span class="sr-meta">' + (c.telephone || '') + (c.email ?
                                        ' &bull; ' + c.email : '') + '</span>';
                                el.addEventListener('mousedown', function(ev) {
                                    ev.preventDefault();
                                    clientNomInput.value = c.nom || '';
                                    clientPrenomInput.value = c.prenom || '';
                                    clientTelInput.value = c.telephone || '';
                                    if (clientEmailInput) clientEmailInput.value = c.email ||
                                    '';
                                    searchClientInput.value = '';
                                    searchClientResults.innerHTML = '';
                                    searchClientResults.setAttribute('aria-hidden', 'true');
                                });
                                searchClientResults.appendChild(el);
                            });
                        }
                        searchClientResults.setAttribute('aria-hidden', 'false');
                    })
                    .catch(function() {
                        searchClientResults.innerHTML =
                            '<div class="search-no-results">Erreur de recherche.</div>';
                    })
                    .finally(function() {
                        if (searchClientLoading) searchClientLoading.style.visibility = 'hidden';
                    });
            }
            searchClientInput.addEventListener('input', function() {
                clearTimeout(clientSearchTimeout);
                var q = searchClientInput.value.trim();
                clientSearchTimeout = setTimeout(function() {
                    doClientSearch(q);
                }, 300);
            });
            searchClientInput.addEventListener('focus', function() {
                var q = searchClientInput.value.trim();
                if (q.length >= 1) doClientSearch(q);
            });
            searchClientInput.addEventListener('blur', function() {
                setTimeout(function() {
                    if (!searchClientResults.contains(document.activeElement)) {
                        searchClientResults.innerHTML = '';
                        searchClientResults.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchClientResults.addEventListener('mousedown', function(ev) {
                ev.preventDefault();
            });
        }

        updateLignesUI();
        if (modal && modal.classList.contains('modal-open') && zoneSelect && zoneSelect.value) {
            onZoneChange();
        }
    })();
    </script>

</body>

</html>
<?php endif; ?>
