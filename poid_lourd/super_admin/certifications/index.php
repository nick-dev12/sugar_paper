<?php
/**
 * Gestion des demandes de certification vendeurs — Super Admin
 * Onglets : en cours | validées | refusées
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
require_once dirname(__DIR__, 2) . '/includes/senegal_regions.php';

$tab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : 'en_cours';
$tab_map = [
    'en_cours'  => 'en_attente',
    'validees'  => 'approuvee',
    'refusees'  => 'refusee',
];
if (!isset($tab_map[$tab])) {
    $tab = 'en_cours';
}
$statut_sql = $tab_map[$tab];

$success = '';
$error = '';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

$counts = vendeur_certification_counts_par_statut();
$demandes = vendeur_certification_list_par_statut($statut_sql, 150);

$tab_labels = [
    'en_cours' => 'En cours',
    'validees' => 'Validées',
    'refusees' => 'Non validées',
];
$tab_desc = [
    'en_cours' => 'Demandes envoyées par les vendeurs, en attente de votre validation.',
    'validees' => 'Certifications approuvées — le badge est actif sur la boutique.',
    'refusees' => 'Demandes refusées avec le motif indiqué au vendeur.',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifications vendeurs — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-certification.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-certifications.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-cert-page-wrap">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-cert-page">
        <header class="sa-cert-hero">
            <p class="sa-cert-hero__eyebrow"><i class="fas fa-certificate"></i> Confiance vendeurs</p>
            <h1 class="sa-cert-hero__title">Demandes de certification</h1>
            <p class="sa-cert-hero__lead">
                Chaque demande soumise par un vendeur depuis son espace admin arrive ici pour validation.
                Examinez les informations, photos et documents avant d'approuver ou de rejeter.
            </p>
        </header>

        <?php if ($msg_err !== ''): ?>
            <div class="cert-alert cert-alert--error" role="alert"><i class="fas fa-circle-exclamation"></i><span><?php echo htmlspecialchars($msg_err); ?></span></div>
        <?php endif; ?>
        <?php if ($msg_ok !== ''): ?>
            <div class="cert-alert cert-alert--success" role="status"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($msg_ok); ?></span></div>
        <?php endif; ?>

        <nav class="sa-cert-tabs" aria-label="Filtrer les demandes">
            <a href="?tab=en_cours" class="sa-cert-tab<?php echo $tab === 'en_cours' ? ' is-active' : ''; ?>">
                <i class="fas fa-hourglass-half"></i>
                <?php echo htmlspecialchars($tab_labels['en_cours']); ?>
                <span class="sa-cert-tab__count<?php echo $counts['en_attente'] > 0 ? ' sa-cert-tab__count--urgent' : ''; ?>"><?php echo (int) $counts['en_attente']; ?></span>
            </a>
            <a href="?tab=validees" class="sa-cert-tab<?php echo $tab === 'validees' ? ' is-active' : ''; ?>">
                <i class="fas fa-circle-check"></i>
                <?php echo htmlspecialchars($tab_labels['validees']); ?>
                <span class="sa-cert-tab__count"><?php echo (int) $counts['approuvee']; ?></span>
            </a>
            <a href="?tab=refusees" class="sa-cert-tab<?php echo $tab === 'refusees' ? ' is-active' : ''; ?>">
                <i class="fas fa-circle-xmark"></i>
                <?php echo htmlspecialchars($tab_labels['refusees']); ?>
                <span class="sa-cert-tab__count"><?php echo (int) $counts['refusee']; ?></span>
            </a>
        </nav>

        <p class="sa-cert-hero__lead" style="margin-bottom:18px;">
            <?php echo htmlspecialchars($tab_desc[$tab]); ?>
            <strong><?php echo count($demandes); ?></strong> demande<?php echo count($demandes) > 1 ? 's' : ''; ?> affichée<?php echo count($demandes) > 1 ? 's' : ''; ?>.
        </p>

        <?php if (empty($demandes)): ?>
            <div class="sa-cert-empty">
                <?php if ($tab === 'en_cours'): ?>
                    <i class="fas fa-inbox"></i>
                    <h3>Aucune demande en cours</h3>
                    <p>Les nouvelles demandes des vendeurs apparaîtront ici automatiquement.</p>
                <?php elseif ($tab === 'validees'): ?>
                    <i class="fas fa-award"></i>
                    <h3>Aucune certification validée</h3>
                    <p>Les demandes approuvées seront listées dans cet onglet.</p>
                <?php else: ?>
                    <i class="fas fa-ban"></i>
                    <h3>Aucune demande refusée</h3>
                    <p>Les rejets avec motif apparaîtront ici.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="sa-cert-list sa-cert-list--summary">
                <?php foreach ($demandes as $d):
                    require __DIR__ . '/includes/demande_card_summary.php';
                endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
