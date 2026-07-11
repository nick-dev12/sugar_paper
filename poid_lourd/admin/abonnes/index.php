<?php
/**
 * Abonnés à la boutique — espace vendeur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

$admin_role = admin_current_role();
if ($admin_role !== 'vendeur') {
    header('Location: ../dashboard.php');
    exit;
}

$vendeur_id = admin_vendeur_filter_id();
if ($vendeur_id === null || $vendeur_id <= 0) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_boutique_abonnements.php';
require_once __DIR__ . '/../../models/model_admin.php';

$vendeur = get_admin_by_id($vendeur_id);
$boutique_nom = trim((string) ($vendeur['boutique_nom'] ?? ''));
if ($boutique_nom === '') {
    $boutique_nom = trim((string) ($vendeur['nom'] ?? 'Ma boutique'));
}

$abonnes = boutique_abonnements_list_by_vendeur($vendeur_id);
$nb_abonnes = count($abonnes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnés — <?php echo htmlspecialchars($boutique_nom, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-abonnes-index.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-abonnes admin-abonnes-page">
    <?php include '../includes/nav.php'; ?>

    <div class="admin-abonnes-shell">
        <header class="admin-abonnes-hero">
            <div class="admin-abonnes-hero__text">
                <h1><i class="fas fa-user-check" aria-hidden="true"></i> Abonnés</h1>
                <p class="admin-abonnes-hero__lead">
                    Clients abonnés à <strong><?php echo htmlspecialchars($boutique_nom, ENT_QUOTES, 'UTF-8'); ?></strong>.
                    Ils reçoivent une notification lorsque vous publiez ou mettez en promotion un produit.
                </p>
            </div>
        </header>

        <div class="admin-abonnes-kpi">
            <div class="admin-abonnes-kpi__card">
                <div class="admin-abonnes-kpi__icon" aria-hidden="true"><i class="fas fa-bell"></i></div>
                <div>
                    <div class="admin-abonnes-kpi__label">Total abonnés</div>
                    <div class="admin-abonnes-kpi__value"><?php echo (int) $nb_abonnes; ?></div>
                </div>
            </div>
        </div>

        <?php if (empty($abonnes)): ?>
        <div class="admin-abonnes-empty">
            <div class="admin-abonnes-empty__icon"><i class="fas fa-users-slash" aria-hidden="true"></i></div>
            <h3>Aucun abonné pour le moment</h3>
            <p>Vos clients peuvent s'abonner à votre boutique depuis une fiche produit pour suivre vos nouveautés.</p>
        </div>
        <?php else: ?>
        <div class="admin-abonnes-grid" role="list">
            <?php foreach ($abonnes as $a):
                $prenom = trim((string) ($a['prenom'] ?? ''));
                $nom = trim((string) ($a['nom'] ?? ''));
                $display = trim($prenom . ' ' . $nom);
                if ($display === '') {
                    $display = 'Client #' . (int) ($a['user_id'] ?? 0);
                }
                $initials = '';
                if ($prenom !== '') {
                    $initials .= mb_strtoupper(mb_substr($prenom, 0, 1));
                }
                if ($nom !== '') {
                    $initials .= mb_strtoupper(mb_substr($nom, 0, 1));
                }
                if ($initials === '') {
                    $initials = 'C';
                }
                $email = trim((string) ($a['email'] ?? ''));
                $tel = trim((string) ($a['telephone'] ?? ''));
                $date_raw = (string) ($a['date_abonnement'] ?? '');
                $date_fmt = $date_raw !== '' ? date('d/m/Y', strtotime($date_raw)) : '';
            ?>
            <article class="admin-abonnes-card" role="listitem">
                <div class="admin-abonnes-card__head">
                    <div class="admin-abonnes-card__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div>
                        <h2 class="admin-abonnes-card__name"><?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <?php if ($date_fmt !== ''): ?>
                        <p class="admin-abonnes-card__date">Abonné depuis le <?php echo htmlspecialchars($date_fmt, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="admin-abonnes-card__details">
                    <?php if ($email !== ''): ?>
                    <div class="admin-abonnes-card__row">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($tel !== ''): ?>
                    <div class="admin-abonnes-card__row">
                        <i class="fas fa-phone" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($tel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
