<?php
/**
 * Commandes annulées — Admin & Vendeur
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/includes/commandes_v2_helpers.php';

$vf_cmd = admin_vendeur_filter_id();
$toutes_commandes = get_all_commandes(null, $vf_cmd);

$commandes_annulees_all = array_values(array_filter($toutes_commandes ?: [], function ($commande) {
    return ($commande['statut'] ?? '') === 'annulee';
}));

$vue = isset($_GET['vue']) ? (string) $_GET['vue'] : 'jour';
if (!in_array($vue, ['jour', 'toutes'], true)) {
    $vue = 'jour';
}

$commandes_annulees = $commandes_annulees_all;
if ($vue === 'jour') {
    $aujourd_hui = date('Y-m-d');
    $commandes_annulees = array_values(array_filter($commandes_annulees_all, function ($c) use ($aujourd_hui) {
        $date_ref = $c['date_commande'] ?? '';
        if ($date_ref === '') {
            return false;
        }
        return date('Y-m-d', strtotime($date_ref)) === $aujourd_hui;
    }));
}

$commandes_annulees = cmd_v2_tri_commandes($commandes_annulees, 'date_desc');

$total_commandes = count_commandes_by_statut(null, $vf_cmd);
$nb_annulees = count_commandes_by_statut('annulee', $vf_cmd);
$montant_total_annulees = get_montant_total_commandes('annulee', $vf_cmd);
$montant_affiche = array_sum(array_map(function ($c) {
    return (float) ($c['montant_total'] ?? 0);
}, $commandes_annulees));

$nb_jour = count(array_filter($commandes_annulees_all, function ($c) {
    $date_ref = $c['date_commande'] ?? '';
    return $date_ref !== '' && date('Y-m-d', strtotime($date_ref)) === date('Y-m-d');
}));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes annul&eacute;es &mdash; Administration COLObanes</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-v2.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container">
        <div class="cmd-v2-page">

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="cmd-v2-notif" role="status">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
                </div>
            <?php endif; ?>

            <header class="cmd-v2-header">
                <div class="cmd-v2-header__left">
                    <p class="cmd-v2-header__eyebrow"><i class="fas fa-ban"></i> Commandes annul&eacute;es</p>
                    <h1 class="cmd-v2-header__title">Annulations</h1>
                </div>
                <div class="cmd-v2-header__actions">
                    <a href="index.php" class="cmd-v2-btn cmd-v2-btn--outline">
                        <i class="fas fa-shopping-bag"></i> &Agrave; traiter
                    </a>
                    <a href="livrees.php" class="cmd-v2-btn cmd-v2-btn--outline">
                        <i class="fas fa-circle-check"></i> Livr&eacute;es
                    </a>
                    <a href="historique-ventes.php?filtre_statut=annulees" class="cmd-v2-btn cmd-v2-btn--danger">
                        <i class="fas fa-chart-line"></i> Historique
                    </a>
                </div>
            </header>

            <div class="cmd-v2-hero">
                <div class="cmd-v2-hero__inner">
                    <div>
                        <p class="cmd-v2-hero__label">
                            <?php echo $vue === 'jour' ? 'Montant — Annulations du jour' : 'Montant — Toutes les annulées'; ?>
                        </p>
                        <div class="cmd-v2-hero__amount">
                            <?php echo number_format($montant_affiche, 0, ',', ' '); ?><span>FCFA</span>
                        </div>
                        <div class="cmd-v2-hero__pills">
                            <div class="cmd-v2-hero__pill cmd-v2-hero__pill--danger">
                                <i class="fas fa-ban"></i>
                                <span><strong><?php echo count($commandes_annulees); ?></strong> affich&eacute;e<?php echo count($commandes_annulees) > 1 ? 's' : ''; ?></span>
                            </div>
                            <div class="cmd-v2-hero__pill">
                                <i class="fas fa-layer-group"></i>
                                <span><strong><?php echo $nb_annulees; ?></strong> au total</span>
                            </div>
                        </div>
                    </div>
                    <div class="cmd-v2-hero__right">
                        <a href="historique-ventes.php?filtre_statut=annulees" class="cmd-v2-hero__cta">
                            <i class="fas fa-chart-line"></i> Voir l'historique
                        </a>
                    </div>
                </div>
            </div>

            <div class="cmd-v2-stats">
                <a href="?vue=jour" class="cmd-v2-stat cmd-v2-stat--annulee<?php echo $vue === 'jour' ? ' cmd-v2-stat--active' : ''; ?>">
                    <div class="cmd-v2-stat__icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="cmd-v2-stat__content">
                        <span class="cmd-v2-stat__label">Aujourd'hui</span>
                        <span class="cmd-v2-stat__value"><?php echo $nb_jour; ?></span>
                    </div>
                </a>
                <a href="?vue=toutes" class="cmd-v2-stat cmd-v2-stat--total<?php echo $vue === 'toutes' ? ' cmd-v2-stat--active' : ''; ?>">
                    <div class="cmd-v2-stat__icon"><i class="fas fa-ban"></i></div>
                    <div class="cmd-v2-stat__content">
                        <span class="cmd-v2-stat__label">Toutes annul&eacute;es</span>
                        <span class="cmd-v2-stat__value"><?php echo $nb_annulees; ?></span>
                    </div>
                </a>
                <div class="cmd-v2-stat cmd-v2-stat--prise">
                    <div class="cmd-v2-stat__icon"><i class="fas fa-coins"></i></div>
                    <div class="cmd-v2-stat__content">
                        <span class="cmd-v2-stat__label">Montant annul&eacute; total</span>
                        <span class="cmd-v2-stat__value" style="font-size:1.15rem;"><?php echo number_format($montant_total_annulees, 0, ',', ' '); ?></span>
                    </div>
                </div>
                <div class="cmd-v2-stat cmd-v2-stat--livraison">
                    <div class="cmd-v2-stat__icon"><i class="fas fa-shopping-bag"></i></div>
                    <div class="cmd-v2-stat__content">
                        <span class="cmd-v2-stat__label">Toutes commandes</span>
                        <span class="cmd-v2-stat__value"><?php echo $total_commandes; ?></span>
                    </div>
                </div>
            </div>

            <div class="cmd-v2-tabs-row">
                <div class="cmd-v2-tabs" role="tablist">
                    <a href="?vue=jour" class="cmd-v2-tab<?php echo $vue === 'jour' ? ' active' : ''; ?>" role="tab">
                        <i class="fas fa-calendar-day"></i>
                        Annulations du jour
                        <span class="cmd-v2-tab__count"><?php echo $nb_jour; ?></span>
                    </a>
                    <a href="?vue=toutes" class="cmd-v2-tab<?php echo $vue === 'toutes' ? ' active' : ''; ?>" role="tab">
                        <i class="fas fa-history"></i>
                        Toutes les annul&eacute;es
                        <span class="cmd-v2-tab__count"><?php echo $nb_annulees; ?></span>
                    </a>
                </div>
            </div>

            <div class="cmd-v2-grid">
                <?php if (empty($commandes_annulees)): ?>
                    <div class="cmd-v2-empty">
                        <div class="cmd-v2-empty__icon"><i class="fas fa-ban"></i></div>
                        <h3>Aucune commande annul&eacute;e</h3>
                        <p>
                            <?php if ($vue === 'jour'): ?>
                                Aucune annulation enregistr&eacute;e aujourd'hui. Consultez &laquo;&nbsp;Toutes les annul&eacute;es&nbsp;&raquo; ou l'historique des ventes.
                            <?php else: ?>
                                Aucune commande n'a &eacute;t&eacute; annul&eacute;e pour le moment.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($commandes_annulees as $commande): ?>
                        <?php cmd_v2_render_card($commande); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
