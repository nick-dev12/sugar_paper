<?php
/**
 * Commandes livrées / payées — Admin & Vendeur
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/includes/commandes_v2_helpers.php';

$vf_cmd = admin_vendeur_filter_id();
$toutes_commandes = get_all_commandes(null, $vf_cmd);

$commandes_livrees_all = array_values(array_filter($toutes_commandes ?: [], function ($commande) {
    return in_array($commande['statut'] ?? '', ['livree', 'paye'], true);
}));

$vue = isset($_GET['vue']) ? (string) $_GET['vue'] : 'jour';
if (!in_array($vue, ['jour', 'toutes'], true)) {
    $vue = 'jour';
}

$commandes_livrees = $commandes_livrees_all;
if ($vue === 'jour') {
    $aujourd_hui = date('Y-m-d');
    $commandes_livrees = array_values(array_filter($commandes_livrees_all, function ($c) use ($aujourd_hui) {
        $date_ref = !empty($c['date_livraison']) ? $c['date_livraison'] : ($c['date_commande'] ?? '');
        if ($date_ref === '') {
            return false;
        }
        return date('Y-m-d', strtotime($date_ref)) === $aujourd_hui;
    }));
}

$commandes_livrees = cmd_v2_tri_commandes($commandes_livrees, 'date_desc');

$total_commandes = count_commandes_by_statut(null, $vf_cmd);
$nb_livrees = count_commandes_by_statut('livree', $vf_cmd) + count_commandes_by_statut('paye', $vf_cmd);
$montant_total_livrees = get_montant_total_commandes('livree', $vf_cmd) + get_montant_total_commandes('paye', $vf_cmd);
$montant_affiche = array_sum(array_map(function ($c) {
    return (float) ($c['montant_total'] ?? 0);
}, $commandes_livrees));

$nb_jour = count(array_filter($commandes_livrees_all, function ($c) {
    $date_ref = !empty($c['date_livraison']) ? $c['date_livraison'] : ($c['date_commande'] ?? '');
    return $date_ref !== '' && date('Y-m-d', strtotime($date_ref)) === date('Y-m-d');
}));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes livr&eacute;es &mdash; Administration COLObanes</title>
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
                    <p class="cmd-v2-header__eyebrow"><i class="fas fa-circle-check"></i> Commandes finalis&eacute;es</p>
                    <h1 class="cmd-v2-header__title">Commandes livr&eacute;es</h1>
                </div>
                <div class="cmd-v2-header__actions">
                    <a href="index.php" class="cmd-v2-btn cmd-v2-btn--outline">
                        <i class="fas fa-shopping-bag"></i> &Agrave; traiter
                    </a>
                    <a href="historique-ventes.php" class="cmd-v2-btn cmd-v2-btn--outline">
                        <i class="fas fa-chart-line"></i> Historique
                    </a>
                    <a href="annulees.php" class="cmd-v2-btn cmd-v2-btn--danger">
                        <i class="fas fa-ban"></i> Annul&eacute;es
                    </a>
                </div>
            </header>

            <div class="cmd-v2-hero">
                <div class="cmd-v2-hero__inner">
                    <div>
                        <p class="cmd-v2-hero__label">
                            <?php echo $vue === 'jour' ? 'Montant — Livraisons du jour' : 'Montant — Toutes les livrées'; ?>
                        </p>
                        <div class="cmd-v2-hero__amount">
                            <?php echo number_format($montant_affiche, 0, ',', ' '); ?><span>FCFA</span>
                        </div>
                        <div class="cmd-v2-hero__pills">
                            <div class="cmd-v2-hero__pill cmd-v2-hero__pill--ok">
                                <i class="fas fa-box"></i>
                                <span><strong><?php echo count($commandes_livrees); ?></strong> affich&eacute;e<?php echo count($commandes_livrees) > 1 ? 's' : ''; ?></span>
                            </div>
                            <div class="cmd-v2-hero__pill">
                                <i class="fas fa-layer-group"></i>
                                <span><strong><?php echo $nb_livrees; ?></strong> au total</span>
                            </div>
                        </div>
                    </div>
                    <div class="cmd-v2-hero__right">
                        <a href="historique-ventes.php?periode=jour&amp;filtre_statut=vendues" class="cmd-v2-hero__cta">
                            <i class="fas fa-chart-line"></i> Voir mes gains
                        </a>
                    </div>
                </div>
            </div>

            <div class="cmd-v2-stats">
                <a href="?vue=jour" class="cmd-v2-stat cmd-v2-stat--ok<?php echo $vue === 'jour' ? ' cmd-v2-stat--active' : ''; ?>">
                    <div class="cmd-v2-stat__icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="cmd-v2-stat__content">
                        <span class="cmd-v2-stat__label">Aujourd'hui</span>
                        <span class="cmd-v2-stat__value"><?php echo $nb_jour; ?></span>
                    </div>
                </a>
                <a href="?vue=toutes" class="cmd-v2-stat cmd-v2-stat--total<?php echo $vue === 'toutes' ? ' cmd-v2-stat--active' : ''; ?>">
                    <div class="cmd-v2-stat__icon"><i class="fas fa-check-double"></i></div>
                    <div class="cmd-v2-stat__content">
                        <span class="cmd-v2-stat__label">Toutes livr&eacute;es</span>
                        <span class="cmd-v2-stat__value"><?php echo $nb_livrees; ?></span>
                    </div>
                </a>
                <div class="cmd-v2-stat cmd-v2-stat--prise">
                    <div class="cmd-v2-stat__icon"><i class="fas fa-coins"></i></div>
                    <div class="cmd-v2-stat__content">
                        <span class="cmd-v2-stat__label">CA livr&eacute; total</span>
                        <span class="cmd-v2-stat__value" style="font-size:1.15rem;"><?php echo number_format($montant_total_livrees, 0, ',', ' '); ?></span>
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
                        Livraisons du jour
                        <span class="cmd-v2-tab__count"><?php echo $nb_jour; ?></span>
                    </a>
                    <a href="?vue=toutes" class="cmd-v2-tab<?php echo $vue === 'toutes' ? ' active' : ''; ?>" role="tab">
                        <i class="fas fa-history"></i>
                        Toutes les livr&eacute;es
                        <span class="cmd-v2-tab__count"><?php echo $nb_livrees; ?></span>
                    </a>
                </div>
            </div>

            <div class="cmd-v2-grid">
                <?php if (empty($commandes_livrees)): ?>
                    <div class="cmd-v2-empty">
                        <div class="cmd-v2-empty__icon"><i class="fas fa-box-open"></i></div>
                        <h3>Aucune commande livr&eacute;e</h3>
                        <p>
                            <?php if ($vue === 'jour'): ?>
                                Aucune livraison enregistr&eacute;e aujourd'hui. Consultez «&nbsp;Toutes les livr&eacute;es&nbsp;» ou l'historique des ventes.
                            <?php else: ?>
                                Aucune commande n'a encore &eacute;t&eacute; marqu&eacute;e comme livr&eacute;e ou pay&eacute;e.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($commandes_livrees as $commande): ?>
                        <?php cmd_v2_render_card($commande, ['show_date' => true, 'show_delivery' => true]); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
