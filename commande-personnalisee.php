<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Page de demande de commande personnalisée
 * Accessible à tous (connectés ou non) — compte auto comme le checkout invité
 */

session_start_persistent();

require_once __DIR__ . '/controllers/controller_commandes_personnalisees.php';
require_once __DIR__ . '/includes/cake_topper_cp.php';
require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/guest_client.php';

$result = process_commande_personnalisee();
$catalogue_produits = cp_catalogue_tables_available() ? get_cp_catalogue_flat_products('actif') : [];
$catalogue_grouped = cp_catalogue_tables_available() ? get_cp_catalogue_grouped('actif') : [];
$total_catalogue_produits = count($catalogue_produits);

if ($result['success']) {
    require_once __DIR__ . '/services/notifications_order_dispatch.php';

    if (!empty($result['notify_data'])) {
        notifications_dispatch_after_commande_personnalisee($result['notify_data']);
    }

    $_SESSION['commande_perso_success'] = $result['message'];
    header('Location: /user/mes-commandes.php?onglet=en_cours&commande_perso=1');
    exit;
}

if (empty($_SESSION['cp_form_csrf'])) {
    $_SESSION['cp_form_csrf'] = bin2hex(random_bytes(32));
}
$cp_csrf = (string) $_SESSION['cp_form_csrf'];
$user_logged_in = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;

$prefill = [
    'nom' => $_SESSION['user_nom'] ?? '',
    'telephone' => $_SESSION['user_telephone'] ?? '',
    'prix_propose' => '',
    'type_produit' => '',
    'catalogue_produit_id' => '',
    'boutique_produit_id' => '',
    'description_creation' => '',
];

if (!$user_logged_in) {
    $gc = guest_client_get();
    if ($gc) {
        if ($prefill['nom'] === '') {
            $prefill['nom'] = $gc['nom'] ?? '';
        }
        if ($prefill['telephone'] === '') {
            $prefill['telephone'] = $gc['telephone'] ?? '';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefill['nom'] = $_POST['nom'] ?? $prefill['nom'];
    $prefill['telephone'] = $_POST['telephone'] ?? $prefill['telephone'];
    $prefill['prix_propose'] = $_POST['prix_propose'] ?? '';
    $prefill['type_produit'] = $_POST['type_produit'] ?? '';
    $prefill['catalogue_produit_id'] = $_POST['catalogue_produit_id'] ?? '';
    $prefill['boutique_produit_id'] = $_POST['boutique_produit_id'] ?? '';
    $prefill['description_creation'] = $_POST['description_creation'] ?? '';
}

$selected_catalogue_id = (int) ($prefill['catalogue_produit_id'] ?? 0);
$selected_catalogue_nom = trim($prefill['type_produit'] ?? '');
$selected_catalogue_image = '';
$selected_prix_min = 0;
$selected_prix_max = 0;
if ($selected_catalogue_id > 0) {
    $selected_produit = get_cp_produit_by_id($selected_catalogue_id, false);
    if ($selected_produit) {
        $selected_catalogue_nom = trim($selected_produit['nom']);
        $selected_catalogue_image = upload_image_url($selected_produit['image'], 'md');
        $selected_prix_min = (float) $selected_produit['prix_min'];
        $selected_prix_max = (float) $selected_produit['prix_max'];
    } else {
        $selected_catalogue_id = 0;
        $selected_catalogue_nom = '';
    }
}

$show_order_form = $selected_catalogue_id > 0;

$cp_modal = [
    'csrf' => $cp_csrf,
    'user_logged_in' => $user_logged_in,
    'prefill' => $prefill,
    'form_action' => '',
    'show_order_form' => $show_order_form,
    'selected_catalogue_id' => $selected_catalogue_id,
    'selected_catalogue_nom' => $selected_catalogue_nom,
    'selected_catalogue_image' => $selected_catalogue_image,
    'selected_prix_min' => $selected_prix_min,
    'selected_prix_max' => $selected_prix_max,
    'selected_boutique_id' => (int) ($prefill['boutique_produit_id'] ?? 0),
];

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/seo_config.php';
$base = get_site_base_url();
$cp_seo = get_seo_commande_perso_meta();
$seo_title = $cp_seo['title'];
$seo_description = $cp_seo['description'];
$seo_keywords = $cp_seo['keywords'];
$seo_canonical = $base . '/commande-personnalisee.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/bottom-nav.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="/css/commande-personnalisee.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-loader-overlay.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/produit-personnalisation.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-cp">
    <?php include 'nav_bar.php'; ?>

    <div class="page-commande-perso">
        <header class="cp-shop-header">
            <h1>Commande personnalisée</h1>
            <p>Sélectionnez un produit dans le catalogue pour passer votre commande.</p>
        </header>

        <?php if (!empty($result['message']) && !$result['success']): ?>
        <div class="error-message" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo $result['message']; ?></span>
        </div>
        <?php endif; ?>

        <section class="cp-shop-layout cp-shop-layout--full" aria-label="Catalogue produits personnalisés">
            <div class="cp-shop-main">
                <div class="cp-shop-toolbar">
                    <form class="cp-search-form cp-search-form--toolbar" action="#" onsubmit="return false;">
                        <label class="screen-reader-text" for="cp-search">Rechercher un produit</label>
                        <input type="search" id="cp-search" class="cp-shop-search" placeholder="Rechercher un produit…" autocomplete="off">
                        <button type="button" class="cp-search-submit" aria-label="Rechercher">
                            <i class="fas fa-search" aria-hidden="true"></i>
                        </button>
                    </form>
                    <p class="cp-shop-results" id="cp-results-text">
                        Affichage de <strong id="cp-visible-count"><?php echo (int) $total_catalogue_produits; ?></strong>
                        sur <?php echo (int) $total_catalogue_produits; ?> produit(s)
                    </p>
                    <div class="cp-shop-sort-wrap">
                        <label class="screen-reader-text" for="cp-sort">Tri</label>
                        <select id="cp-sort" class="cp-shop-sort" aria-label="Tri des produits">
                            <option value="default">Tri par défaut</option>
                            <option value="name-asc">Nom A-Z</option>
                            <option value="price-asc">Prix croissant</option>
                            <option value="price-desc">Prix décroissant</option>
                        </select>
                    </div>
                </div>

                <?php if (empty($catalogue_produits)): ?>
                <div class="cp-shop-panel">
                    <div class="cp-shop-empty cp-shop-empty--catalog" id="cp-shop-empty">
                        <i class="fas fa-box-open" aria-hidden="true"></i>
                        <h3>Catalogue en préparation</h3>
                        <p>Les produits seront bientôt disponibles.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="cp-shop-panels" id="cp-shop-panels">
                    <?php foreach ($catalogue_grouped as $dossier_cat): ?>
                    <div class="cp-shop-panel cp-dossier-section" data-folder-id="<?php echo (int) $dossier_cat['id']; ?>">
                        <header class="cp-dossier-title">
                            <span class="cp-dossier-title__accent" aria-hidden="true"></span>
                            <span class="cp-dossier-title__icon"><i class="fas fa-folder-open"></i></span>
                            <div class="cp-dossier-title__text">
                                <h2><?php echo htmlspecialchars($dossier_cat['nom']); ?></h2>
                                <p><?php echo count($dossier_cat['produits']); ?> produit(s)</p>
                            </div>
                        </header>
                        <div class="cp-product-grid">
                            <?php foreach ($dossier_cat['produits'] as $produit_cat): ?>
                            <button type="button"
                                class="cp-product-card<?php echo ($selected_catalogue_id === (int) $produit_cat['id']) ? ' is-selected' : ''; ?>"
                                data-product-id="<?php echo (int) $produit_cat['id']; ?>"
                                data-folder-id="<?php echo (int) $dossier_cat['id']; ?>"
                                data-name="<?php echo htmlspecialchars($produit_cat['nom'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-price-min="<?php echo (float) $produit_cat['prix_min']; ?>"
                                data-price-max="<?php echo (float) $produit_cat['prix_max']; ?>"
                                data-image="<?php echo htmlspecialchars(upload_image_url($produit_cat['image'], 'md'), ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="cp-product-card__media">
                                    <img src="<?php echo htmlspecialchars(upload_image_url($produit_cat['image'], 'md')); ?>"
                                        alt="<?php echo htmlspecialchars($produit_cat['nom']); ?>" loading="lazy">
                                </span>
                                <span class="cp-product-card__body">
                                    <strong class="cp-product-card__title"><?php echo htmlspecialchars($produit_cat['nom']); ?></strong>
                                    <span class="cp-product-card__price">
                                        <?php echo number_format((float) $produit_cat['prix_min'], 0, ',', ' '); ?>
                                        — <?php echo number_format((float) $produit_cat['prix_max'], 0, ',', ' '); ?> FCFA
                                    </span>
                                </span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="cp-shop-empty cp-shop-empty--filter" id="cp-shop-empty" hidden>Aucun produit ne correspond à votre recherche.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php include __DIR__ . '/includes/partials/cp_commande_modal.php'; ?>

    </div>

    <div id="commande-loader-overlay" class="commande-loader-overlay" hidden aria-hidden="true" role="alertdialog" aria-modal="true" aria-labelledby="commande-loader-title" aria-describedby="commande-loader-text">
        <div class="commande-loader-card">
            <div class="commande-loader-spinner" aria-hidden="true">
                <span class="commande-loader-spinner__ring commande-loader-spinner__ring--outer"></span>
                <span class="commande-loader-spinner__ring commande-loader-spinner__ring--inner"></span>
                <span class="commande-loader-spinner__icon"><i class="fas fa-palette"></i></span>
            </div>
            <h2 class="commande-loader-title" id="commande-loader-title">Demande en cours</h2>
            <p class="commande-loader-text" id="commande-loader-text">Votre demande personnalisée est en train d’être enregistrée.<br>Merci de patienter quelques instants…</p>
            <div class="commande-loader-dots" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>

    <?php if (!$user_logged_in): ?>
    <?php include __DIR__ . '/includes/auth_intl_tel_scripts.php'; ?>
    <?php endif; ?>
    <script>
        window.cpShopConfig = {
            userLoggedIn: <?php echo $user_logged_in ? 'true' : 'false'; ?>
        };
    </script>
    <script src="/js/cp-form-modal.js<?php echo asset_version_query(); ?>"></script>
    <?php include('footer.php'); ?>
    <?php include __DIR__ . '/includes/floating_back_button.php'; ?>
</body>

</html>
