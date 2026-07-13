<?php
require_once __DIR__ . '/../../includes/session_user.php';
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

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_clients_b2b.php';
if (!bl_tables_available()) {
    header('Location: index.php');
    exit;
}

$client_b2b_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($client_b2b_id <= 0) {
    header('Location: index.php?tab=facture');
    exit;
}

$client = get_client_b2b_by_id($client_b2b_id);
if (!$client) {
    header('Location: index.php?tab=facture');
    exit;
}

$bl_list = get_all_bl_for_client_b2b($client_b2b_id, true);
$raison = $client['raison_sociale'] ?? '';
$contact_nom = trim(($client['nom_contact'] ?? '') . ' ' . ($client['prenom_contact'] ?? ''));

$initials = '?';
if ($raison !== '') {
    $words = preg_split('/\s+/u', $raison, -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) >= 2) {
        $initials = mb_strtoupper(
            mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1),
            'UTF-8'
        );
    } else {
        $initials = mb_strtoupper(mb_substr($raison, 0, min(2, mb_strlen($raison, 'UTF-8')), 'UTF-8'), 'UTF-8');
    }
}

$nb_factures = count($bl_list);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures — <?php echo htmlspecialchars($raison); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-devis-compta-pages.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-devis-admin">
    <div class="content-header dashboard-hero page-devis-hero">
        <div class="dashboard-hero-text">
            <p class="dashboard-eyebrow">Espace commercial</p>
            <h1><i class="fas fa-file-invoice" aria-hidden="true"></i> Factures — <?php echo htmlspecialchars($raison ?: 'Client'); ?></h1>
        </div>
        <div class="header-actions">
            <a href="index.php?tab=facture" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux contacts</a>
        </div>
    </div>

    <section class="content-section page-devis-section">
        <div class="bl-tab-surface">
            <header class="bl-client-banner" aria-labelledby="bl-client-banner-title">
                <div class="bl-client-banner__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                <div class="bl-client-banner__body">
                    <h2 id="bl-client-banner-title" class="bl-client-banner__title"><?php echo htmlspecialchars($raison ?: '—'); ?></h2>
                    <?php if ($contact_nom !== ''): ?>
                        <p class="bl-client-banner__contact">
                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                            <?php echo htmlspecialchars($contact_nom); ?>
                        </p>
                    <?php endif; ?>
                    <ul class="bl-client-banner__meta">
                        <li>
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-phone"></i></span>
                            <span><?php echo htmlspecialchars($client['telephone'] ?? '—'); ?></span>
                        </li>
                        <li>
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                            <span><?php echo !empty($client['email']) ? htmlspecialchars($client['email']) : '—'; ?></span>
                        </li>
                        <?php if (!empty($client['adresse'])): ?>
                        <li class="bl-client-banner__meta--full">
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                            <span><?php echo nl2br(htmlspecialchars($client['adresse'])); ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="bl-client-banner__stat">
                    <span class="bl-client-banner__stat-num"><?php echo (int) $nb_factures; ?></span>
                    <span class="bl-client-banner__stat-label">facture<?php echo $nb_factures > 1 ? 's' : ''; ?></span>
                </div>
            </header>
        </div>

        <?php if (empty($bl_list)): ?>
            <div class="bl-empty-state bl-empty-state--compact" role="status">
                <div class="bl-empty-state__visual" aria-hidden="true">
                    <span class="bl-empty-state__ring"></span>
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h3 class="bl-empty-state__title">Aucune facture</h3>
                <p class="bl-empty-state__text">Ce contact n’a pas encore de facture associée.</p>
                <a href="index.php?tab=facture" class="btn-primary bl-empty-state__btn"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux contacts</a>
            </div>
        <?php else: ?>
            <div class="invoice-panel-table-wrap">
                <table class="data-table invoice-data-table">
                    <thead>
                        <tr>
                            <th>N° facture</th>
                            <th>Date</th>
                            <th class="invoice-col-num">Total HT</th>
                            <th class="invoice-col-num">Remise</th>
                            <th>Statut</th>
                            <th class="invoice-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bl_list as $b): ?>
                            <?php
                            $b = bl_row_apply_statut_bl($b);
                            $bst = $b['statut'] ?? 'brouillon';
                            $bst_label = bl_libelle_statut_court($bst);
                            $bid = (int) $b['id'];
                            $date_aff = !empty($b['date_bl'])
                                ? date('d/m/Y', strtotime($b['date_bl']))
                                : (!empty($b['date_creation']) ? date('d/m/Y H:i', strtotime($b['date_creation'])) : '—');
                            $remise_pct = isset($b['remise_globale_pct']) ? (float) $b['remise_globale_pct'] : 0;
                            ?>
                            <tr>
                                <td data-label="N° facture">
                                    <strong><?php echo htmlspecialchars($b['numero_bl'] ?? '—'); ?></strong>
                                </td>
                                <td data-label="Date"><?php echo htmlspecialchars($date_aff); ?></td>
                                <td data-label="Total HT" class="invoice-col-num">
                                    <?php echo number_format((float) ($b['total_ht'] ?? 0), 0, ',', ' '); ?> FCFA
                                </td>
                                <td data-label="Remise" class="invoice-col-num">
                                    <?php echo $remise_pct > 0 ? number_format($remise_pct, 2, ',', ' ') . ' %' : '—'; ?>
                                </td>
                                <td data-label="Statut">
                                    <span class="commande-statut statut-<?php echo htmlspecialchars($bst); ?>"><?php echo htmlspecialchars($bst_label); ?></span>
                                </td>
                                <td data-label="Actions" class="invoice-col-actions">
                                    <a href="bl_voir.php?id=<?php echo $bid; ?>" class="invoice-table-link">
                                        <i class="fas fa-eye" aria-hidden="true"></i> Voir
                                    </a>
                                    <?php if (!bl_est_statut_verrouille($bst)): ?>
                                    <a href="bl_modifier.php?id=<?php echo $bid; ?>" class="invoice-table-link">
                                        <i class="fas fa-edit" aria-hidden="true"></i> Réajuster
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
