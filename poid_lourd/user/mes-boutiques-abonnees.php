<?php
/**
 * Boutiques auxquelles le client est abonné
 * Programmation procédurale uniquement
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

require_once __DIR__ . '/../models/model_boutique_abonnements.php';
require_once __DIR__ . '/../includes/marketplace_helpers.php';
require_once __DIR__ . '/../includes/flash_toast.php';

$user_id = (int) $_SESSION['user_id'];
$boutiques = boutique_abonnements_list_by_user($user_id);
$nb_boutiques = count($boutiques);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes boutiques — Mon compte</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-boutiques-abonnees.css<?php echo asset_version_query(); ?>">
</head>
<body class="user-page-boutiques-abo">
    <?php include 'includes/user_nav.php'; ?>

    <div class="uba-page">
        <header class="uba-header">
            <div>
                <p class="uba-header__eyebrow"><i class="fas fa-store" aria-hidden="true"></i> Abonnements</p>
                <h1 class="uba-header__title">Mes boutiques</h1>
            </div>
            <a href="mon-compte.php" class="uba-btn uba-btn--ghost">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tableau de bord
            </a>
        </header>

        <div class="uba-hero">
            <div class="uba-hero__inner">
                <div>
                    <p class="uba-hero__label">Boutiques suivies</p>
                    <div class="uba-hero__count"><?php echo (int) $nb_boutiques; ?> boutique<?php echo $nb_boutiques > 1 ? 's' : ''; ?></div>
                </div>
                <div class="uba-hero__icon" aria-hidden="true">
                    <i class="fas fa-bell"></i>
                </div>
            </div>
        </div>

        <?php if (empty($boutiques)): ?>
        <div class="uba-empty">
            <div class="uba-empty__icon"><i class="fas fa-store-slash" aria-hidden="true"></i></div>
            <h3>Aucune boutique suivie</h3>
            <p>Abonnez-vous à une boutique depuis une fiche produit pour recevoir des notifications sur les nouveautés et promotions.</p>
            <a href="/index.php" class="uba-btn uba-btn--primary">
                <i class="fas fa-shopping-bag" aria-hidden="true"></i> Explorer le catalogue
            </a>
        </div>
        <?php else: ?>
        <div class="uba-grid" role="list">
            <?php foreach ($boutiques as $b):
                $admin_id = (int) ($b['admin_id'] ?? 0);
                $slug = trim((string) ($b['boutique_slug'] ?? ''));
                $nom = trim((string) ($b['boutique_nom'] ?? ''));
                if ($nom === '') {
                    $nom = trim((string) ($b['vendeur_nom'] ?? 'Boutique'));
                }
                $boutique_url = boutique_vitrine_entry_href($slug);
                $logo_rel = trim((string) ($b['boutique_logo'] ?? ''));
                $logo_url = $logo_rel !== '' ? '/upload/' . str_replace('\\', '/', $logo_rel) : '';
                $date_raw = (string) ($b['date_abonnement'] ?? '');
                $date_fmt = $date_raw !== '' ? date('d/m/Y', strtotime($date_raw)) : '';
                $redirect = '/user/mes-boutiques-abonnees.php';
            ?>
            <article class="uba-card" role="listitem">
                <div class="uba-card__head">
                    <div class="uba-card__logo">
                        <?php if ($logo_url !== ''): ?>
                        <img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                            alt=""
                            width="56"
                            height="56"
                            loading="lazy"
                            decoding="async">
                        <?php else: ?>
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <div class="uba-card__meta">
                        <h2 class="uba-card__name"><?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <?php if ($date_fmt !== ''): ?>
                        <p class="uba-card__date">
                            <i class="fas fa-calendar-check" aria-hidden="true"></i>
                            Abonné depuis le <?php echo htmlspecialchars($date_fmt, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="uba-card__body">
                    <a href="<?php echo htmlspecialchars($boutique_url, ENT_QUOTES, 'UTF-8'); ?>" class="uba-btn uba-btn--primary">
                        <i class="fas fa-external-link-alt" aria-hidden="true"></i> Visiter la boutique
                    </a>
                    <form method="post" action="boutique-abonnement-action.php">
                        <input type="hidden" name="admin_id" value="<?php echo $admin_id; ?>">
                        <input type="hidden" name="action" value="unsubscribe">
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="uba-btn uba-btn--outline">
                            <i class="fas fa-bell-slash" aria-hidden="true"></i> Se désabonner
                        </button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/user_footer.php'; ?>
</body>
</html>
