<?php
/**
 * Annonces plateforme — espace client (redesign v2)
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();


if (!function_exists('auth_redirect_to_site_home')) {
    require_once __DIR__ . '/../includes/auth_redirect.php';
}
if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    auth_redirect_to_site_home();
    exit;
}

require_once __DIR__ . '/../models/model_annonces.php';

$user_id = (int) $_SESSION['user_id'];
$focus_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$annonces = annonces_list_for_client($user_id, 80);
$nb_total = count($annonces);
$nb_non_lues = 0;
foreach ($annonces as $a) {
    if (empty($a['est_lue'])) {
        $nb_non_lues++;
    }
}
$nb_lues = max(0, $nb_total - $nb_non_lues);

if ($focus_id > 0) {
    annonce_mark_read_client($focus_id, $user_id);
} else {
    annonce_mark_all_read_client($user_id);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonces — Mon compte</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-annonces.css<?php echo asset_version_query(); ?>">
</head>
<body class="user-page-annonces">
    <?php include 'includes/user_nav.php'; ?>

    <div class="an-v2-page">

        <header class="an-v2-header">
            <div class="an-v2-header__left">
                <p class="an-v2-header__eyebrow"><i class="fas fa-bullhorn" aria-hidden="true"></i> Plateforme COLObanes</p>
                <h1 class="an-v2-header__title">Annonces</h1>
            </div>
            <a href="mon-compte.php" class="an-btn an-btn--ghost">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tableau de bord
            </a>
        </header>

        <div class="an-v2-hero">
            <div class="an-v2-hero__top">
                <div>
                    <p class="an-v2-hero__label">Messages de la plateforme</p>
                    <div class="an-v2-hero__title"><?php echo (int) $nb_total; ?> annonce<?php echo $nb_total > 1 ? 's' : ''; ?></div>
                </div>
                <div class="an-v2-hero__icon" aria-hidden="true">
                    <i class="fas fa-bell"></i>
                </div>
            </div>
            <div class="an-v2-hero__pills">
                <?php if ($nb_non_lues > 0): ?>
                <div class="an-v2-hero__pill an-v2-hero__pill--warn">
                    <i class="fas fa-circle-dot"></i>
                    <span><strong><?php echo (int) $nb_non_lues; ?></strong> non lue<?php echo $nb_non_lues > 1 ? 's' : ''; ?></span>
                </div>
                <?php endif; ?>
                <div class="an-v2-hero__pill an-v2-hero__pill--ok">
                    <i class="fas fa-envelope-open"></i>
                    <span><strong><?php echo (int) $nb_lues; ?></strong> lue<?php echo $nb_lues > 1 ? 's' : ''; ?></span>
                </div>
            </div>
        </div>

        <?php if (empty($annonces)): ?>
        <div class="an-v2-empty">
            <div class="an-v2-empty__icon"><i class="fas fa-inbox" aria-hidden="true"></i></div>
            <h3>Aucune annonce pour le moment</h3>
            <p>Les messages importants de COLObanes appara&icirc;tront ici (promotions, nouveaut&eacute;s, informations).</p>
            <a href="mon-compte.php" class="an-btn an-btn--primary">
                <i class="fas fa-home" aria-hidden="true"></i> Retour au tableau de bord
            </a>
        </div>
        <?php else: ?>
        <div class="an-v2-list" role="list">
            <?php foreach ($annonces as $a):
                $aid = (int) ($a['id'] ?? 0);
                $is_unread = empty($a['est_lue']);
                $is_focus = ($focus_id > 0 && $aid === $focus_id);
                $date_raw = (string) ($a['date_envoi'] ?? '');
                $date_fmt = $date_raw !== '' ? date('d/m/Y', strtotime($date_raw)) : '';
                $heure_fmt = $date_raw !== '' ? date('H:i', strtotime($date_raw)) : '';
            ?>
            <article class="an-v2-card<?php echo ($is_unread || $is_focus) ? ' an-v2-card--unread' : ''; ?><?php echo $is_focus ? ' an-v2-card--focus' : ''; ?>"
                role="listitem" id="annonce-<?php echo $aid; ?>">
                <div class="an-v2-card__accent" aria-hidden="true"></div>
                <div class="an-v2-card__inner">
                    <header class="an-v2-card__head">
                        <div class="an-v2-card__icon" aria-hidden="true">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="an-v2-card__head-text">
                            <h2 class="an-v2-card__title"><?php echo htmlspecialchars((string) ($a['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <?php if ($date_fmt !== ''): ?>
                            <time class="an-v2-card__date" datetime="<?php echo htmlspecialchars($date_raw, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-calendar-day" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($date_fmt, ENT_QUOTES, 'UTF-8'); ?>
                                <span class="an-v2-card__time"><?php echo htmlspecialchars($heure_fmt, ENT_QUOTES, 'UTF-8'); ?></span>
                            </time>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_unread): ?>
                        <span class="an-v2-card__badge">Nouveau</span>
                        <?php endif; ?>
                    </header>
                    <div class="an-v2-card__body">
                        <?php echo nl2br(htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/user_footer.php'; ?>
</body>
</html>
