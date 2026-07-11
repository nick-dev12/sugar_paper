<?php
/**
 * Traitement d'une demande de certification — Super Admin
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
require_once dirname(__DIR__, 2) . '/includes/senegal_regions.php';

$demande_id = (int) ($_GET['id'] ?? $_POST['demande_id'] ?? 0);
$vue_lecture = !empty($_GET['vue']) && $_GET['vue'] === 'lecture';
$show_reject_form = !empty($_GET['rejeter']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refusee');

$d = vendeur_certification_get_demande_by_id($demande_id);
if (!$d) {
    header('Location: index.php?tab=en_cours');
    exit;
}

$st_dem = (string) ($d['statut'] ?? '');
$peut_traiter = ($st_dem === 'en_attente');
if ($vue_lecture) {
    $peut_traiter = false;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peut_traiter) {
    $action = trim((string) ($_POST['action'] ?? ''));
    $motif = trim((string) ($_POST['motif_refus'] ?? ''));

    if ($action === 'approuvee') {
        if (vendeur_certification_traiter_demande($demande_id, 'approuvee', '', (int) ($_SESSION['super_admin_id'] ?? 0))) {
            $_SESSION['super_admin_flash_ok'] = 'Certification validée avec succès.';
            header('Location: index.php?tab=validees');
            exit;
        }
        $error = 'Impossible de valider cette demande.';
    } elseif ($action === 'refusee') {
        if ($motif === '') {
            $error = 'Indiquez le motif du refus.';
            $show_reject_form = true;
        } elseif (vendeur_certification_traiter_demande($demande_id, 'refusee', $motif, (int) ($_SESSION['super_admin_id'] ?? 0))) {
            $_SESSION['super_admin_flash_ok'] = 'Demande rejetée — le vendeur sera notifié.';
            header('Location: index.php?tab=refusees');
            exit;
        } else {
            $error = 'Impossible de rejeter cette demande.';
        }
    } else {
        $error = 'Action non reconnue.';
    }
}

$tab_retour = match ($st_dem) {
    'approuvee' => 'validees',
    'refusee' => 'refusees',
    default => 'en_cours',
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traiter demande #<?php echo $demande_id; ?> — Super Admin</title>
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
        <a href="index.php?tab=<?php echo urlencode($tab_retour); ?>" class="cert-back">
            <i class="fas fa-arrow-left"></i> Retour aux demandes
        </a>

        <header class="sa-cert-hero sa-cert-hero--compact">
            <p class="sa-cert-hero__eyebrow"><i class="fas fa-clipboard-check"></i> Traitement certification</p>
            <h1 class="sa-cert-hero__title"><?php echo htmlspecialchars((string) ($d['boutique_nom'] ?? 'Boutique')); ?></h1>
            <p class="sa-cert-hero__lead">
                Demande <?php echo htmlspecialchars(vendeur_certification_niveau_label((string) ($d['niveau'] ?? ''))); ?>
                · <?php echo htmlspecialchars(vendeur_certification_statut_label($st_dem)); ?>
            </p>
        </header>

        <?php if ($error !== ''): ?>
            <div class="cert-alert cert-alert--error" role="alert"><i class="fas fa-circle-exclamation"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <?php
        $sa_cert_show_actions = false;
        require __DIR__ . '/includes/demande_card.php';
        ?>

        <?php if ($peut_traiter && !$show_reject_form): ?>
            <div class="sa-cert-traiter-actions">
                <form method="post" action="traiter.php?id=<?php echo $demande_id; ?>" class="sa-cert-traiter-actions__form">
                    <input type="hidden" name="demande_id" value="<?php echo $demande_id; ?>">
                    <button type="submit" name="action" value="approuvee" class="sa-cert-btn sa-cert-btn--ok sa-cert-btn--lg">
                        <i class="fas fa-check"></i> Valider la certification
                    </button>
                </form>
                <a href="traiter.php?id=<?php echo $demande_id; ?>&amp;rejeter=1" class="sa-cert-btn sa-cert-btn--no sa-cert-btn--lg">
                    <i class="fas fa-times"></i> Rejeter la demande
                </a>
            </div>
        <?php elseif ($peut_traiter && $show_reject_form): ?>
            <div class="sa-cert-reject-panel">
                <h2><i class="fas fa-circle-xmark"></i> Motif du refus</h2>
                <p>Expliquez clairement au vendeur pourquoi la demande est refusée. Ce message lui sera affiché.</p>
                <form method="post" action="traiter.php?id=<?php echo $demande_id; ?>" class="sa-cert-reject-form">
                    <input type="hidden" name="demande_id" value="<?php echo $demande_id; ?>">
                    <input type="hidden" name="action" value="refusee">
                    <div class="cert-field">
                        <label for="motif_refus">Motif du refus <span class="req">*</span></label>
                        <textarea id="motif_refus" name="motif_refus" required maxlength="800" placeholder="Détaillez les éléments manquants ou non conformes…"><?php echo htmlspecialchars((string) ($_POST['motif_refus'] ?? '')); ?></textarea>
                    </div>
                    <div class="sa-cert-traiter-actions">
                        <button type="submit" class="sa-cert-btn sa-cert-btn--no sa-cert-btn--lg">
                            <i class="fas fa-paper-plane"></i> Confirmer le refus
                        </button>
                        <a href="traiter.php?id=<?php echo $demande_id; ?>" class="sa-cert-btn sa-cert-btn--ghost">Annuler</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
