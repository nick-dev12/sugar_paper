<?php
/**
 * Annonces plateforme — espace vendeur
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/includes/require_access.php';
require_once dirname(__DIR__) . '/models/model_annonces.php';

$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '');
if ($role !== 'vendeur') {
    header('Location: dashboard.php');
    exit;
}

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$focus_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($focus_id > 0) {
    annonce_mark_read_vendeur($focus_id, $admin_id);
} else {
    annonce_mark_all_read_vendeur($admin_id);
}

$annonces = annonces_list_for_vendeur($admin_id, 80);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonces — Administration</title>
    <?php require_once dirname(__DIR__) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-annonces.css<?php echo asset_version_query(); ?>">
</head>
<body class="admin-page-annonces">
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="pa-page">
        <header class="pa-header">
            <p class="pa-header__eyebrow"><i class="fas fa-bullhorn" aria-hidden="true"></i> Plateforme</p>
            <h1 class="pa-header__title">Annonces</h1>
        </header>

        <?php if (empty($annonces)): ?>
        <div class="pa-empty">
            <div><i class="fas fa-inbox" aria-hidden="true"></i></div>
            <p>Aucune annonce pour le moment.</p>
        </div>
        <?php else: ?>
        <div class="pa-list" role="list">
            <?php foreach ($annonces as $a):
                $aid = (int) ($a['id'] ?? 0);
                $is_unread = empty($a['est_lue']);
                $is_focus = ($focus_id > 0 && $aid === $focus_id);
            ?>
            <article class="pa-card<?php echo ($is_unread || $is_focus) ? ' pa-card--unread' : ''; ?>" role="listitem" id="annonce-<?php echo $aid; ?>">
                <div class="pa-card__top">
                    <h2 class="pa-card__title"><?php echo htmlspecialchars((string) ($a['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <time class="pa-card__date" datetime="<?php echo htmlspecialchars((string) ($a['date_envoi'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo !empty($a['date_envoi']) ? date('d/m/Y H:i', strtotime((string) $a['date_envoi'])) : ''; ?>
                    </time>
                </div>
                <p class="pa-card__body"><?php echo nl2br(htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                <?php if ($is_unread): ?>
                <span class="pa-card__badge-new">Nouveau</span>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
