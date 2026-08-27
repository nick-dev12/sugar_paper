<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Page de demande de commande personnalisée
 * Accessible à tous (connectés ou non) — compte auto comme le checkout invité
 */

session_start_persistent();

require_once __DIR__ . '/controllers/controller_commandes_personnalisees.php';
require_once __DIR__ . '/models/model_cp_catalogue.php';
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

require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Commande personnalisée gâteau - Sugar Paper';
$seo_description = 'Commande personnalisée de décoration pour gâteaux : anniversaire, mariage, cérémonies. Produits décoratifs comestibles et non comestibles à grande échelle.';
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/bottom-nav.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="/css/commande-personnalisee.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-loader-overlay.css<?php echo asset_version_query(); ?>">
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

        <div class="cp-modal-overlay<?php echo $show_order_form ? ' is-visible' : ''; ?>" id="cp-modal-overlay"<?php echo !$show_order_form ? ' hidden' : ''; ?> aria-hidden="<?php echo $show_order_form ? 'false' : 'true'; ?>">
            <button type="button" class="cp-modal-backdrop" id="cp-modal-backdrop" aria-label="Fermer le formulaire"></button>
            <div class="cp-form-wrap cp-form-wrap--modal" id="cp-form-wrap" role="dialog" aria-modal="true" aria-labelledby="cp-form-product-name">
            <form method="POST" action="" class="form-commande-perso form-commande-perso--modal" id="form-commande-perso" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($cp_csrf); ?>">
                <input type="hidden" name="catalogue_produit_id" id="catalogue_produit_id" value="<?php echo $selected_catalogue_id > 0 ? (int) $selected_catalogue_id : ''; ?>">
                <input type="hidden" name="type_produit" id="type_produit" value="<?php echo htmlspecialchars($selected_catalogue_nom); ?>">

                <div class="cp-form-product-head" id="cp-form-product-head">
                    <?php if ($selected_catalogue_image !== ''): ?>
                    <img src="<?php echo htmlspecialchars($selected_catalogue_image); ?>" alt="" class="cp-form-product-head__img" id="cp-form-product-image">
                    <?php else: ?>
                    <img src="" alt="" class="cp-form-product-head__img" id="cp-form-product-image" hidden>
                    <?php endif; ?>
                    <div>
                        <p class="cp-form-product-head__label">Produit sélectionné</p>
                        <p class="cp-form-product-head__name" id="cp-form-product-name"><?php echo htmlspecialchars($selected_catalogue_nom); ?></p>
                        <p class="cp-form-product-head__range" id="cp-form-product-range">
                            <?php if ($selected_prix_max > 0): ?>
                            Fourchette : <?php echo number_format($selected_prix_min, 0, ',', ' '); ?> — <?php echo number_format($selected_prix_max, 0, ',', ' '); ?> FCFA
                            <?php endif; ?>
                        </p>
                    </div>
                    <button type="button" class="cp-form-product-head__close" id="cp-clear-selection" aria-label="Fermer le formulaire">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <?php if (!$user_logged_in): ?>
                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-user-circle" aria-hidden="true"></i> Vos coordonnées</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" required autocomplete="name"
                                value="<?php echo htmlspecialchars($prefill['nom']); ?>" placeholder="Votre nom">
                        </div>
                        <div class="form-group form-group--tel">
                            <label for="telephone">Téléphone *</label>
                            <div class="input-wrapper input-wrapper--intl-tel cp-tel-intl">
                                <input type="tel" id="telephone" name="telephone" required autocomplete="tel"
                                    value="<?php echo htmlspecialchars($prefill['telephone']); ?>"
                                    placeholder="77 123 45 67">
                            </div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-tag" aria-hidden="true"></i> Votre prix</h2>
                    <div class="form-group">
                        <label for="prix_propose">Prix proposé (FCFA) *</label>
                        <input type="number" id="prix_propose" name="prix_propose" required min="0" step="1"
                            value="<?php echo htmlspecialchars($prefill['prix_propose']); ?>"
                            placeholder="Indiquez votre budget">
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-pen-fancy" aria-hidden="true"></i> Personnalisez</h2>
                    <div class="form-group">
                        <label for="description_creation">Description de votre création</label>
                        <textarea id="description_creation" name="description_creation" rows="4" maxlength="2000"
                            placeholder="Décrivez votre idée : thème, texte, prénom, couleurs, date de l'événement..."><?php echo htmlspecialchars($prefill['description_creation']); ?></textarea>
                        <p class="cp-field-help">Précisez ce que vous souhaitez pour votre création sur mesure.</p>
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-microphone" aria-hidden="true"></i> Votre message vocal <span class="cp-optional">(optionnel)</span></h2>
                    <div class="cp-voice-note" id="cp-voice-note">
                        <input type="file" id="note_vocale" name="note_vocale" class="cp-voice-note__input" accept="audio/*,.webm,.ogg,.mp4,.m4a,.mp3" hidden>
                        <div class="cp-voice-note__panel cp-voice-note__panel--idle" id="cp-voice-idle">
                            <button type="button" class="cp-voice-note__mic" id="cp-voice-record-btn" aria-label="Enregistrer un message vocal">
                                <i class="fas fa-microphone" aria-hidden="true"></i>
                            </button>
                            <div class="cp-voice-note__hint">
                                <strong>Appuyez pour enregistrer</strong>
                                <span>Max 2 min</span>
                            </div>
                        </div>
                        <div class="cp-voice-note__panel cp-voice-note__panel--recording" id="cp-voice-recording" hidden>
                            <span class="cp-voice-note__rec-dot" aria-hidden="true"></span>
                            <span class="cp-voice-note__timer" id="cp-voice-timer">0:00</span>
                            <div class="cp-voice-note__wave cp-voice-note__wave--live" id="cp-voice-wave-live" aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span>
                            </div>
                            <button type="button" class="cp-voice-note__stop" id="cp-voice-stop">
                                <i class="fas fa-stop" aria-hidden="true"></i> Arrêter
                            </button>
                        </div>
                        <div class="cp-voice-note__panel cp-voice-note__panel--preview" id="cp-voice-preview" hidden>
                            <button type="button" class="cp-voice-note__play" id="cp-voice-play" aria-label="Écouter le message">
                                <i class="fas fa-play" aria-hidden="true"></i>
                            </button>
                            <div class="cp-voice-note__preview-body">
                                <div class="cp-voice-note__wave cp-voice-note__wave--preview" aria-hidden="true">
                                    <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                                </div>
                                <span class="cp-voice-note__duration" id="cp-voice-duration">0:00</span>
                            </div>
                            <button type="button" class="cp-voice-note__delete" id="cp-voice-delete" aria-label="Supprimer le message">
                                <i class="fas fa-trash-alt" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="cp-voice-note__error" id="cp-voice-error" hidden role="alert"></p>
                    </div>
                </section>

                <section class="cp-form-section">
                    <h2 class="cp-form-section-title"><i class="fas fa-image" aria-hidden="true"></i> Images d’inspiration <span class="cp-optional">(optionnel)</span></h2>
                    <div class="form-group">
                        <div class="upload-reference-box" id="upload-reference-box">
                            <input type="file" id="images_reference" name="images_reference[]" class="upload-reference-input"
                                accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" multiple>
                            <button type="button" class="upload-reference-trigger" id="upload-reference-trigger">
                                <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                                <strong>Ajouter des photos d’inspiration</strong>
                                <span>Glissez-déposez ou cliquez — jusqu’à 6 images</span>
                            </button>
                            <p class="upload-help">
                                <strong>Formats :</strong> JPG, PNG, WEBP, GIF — 5&nbsp;Mo max par image.
                            </p>
                            <p class="upload-counter" id="upload-counter">0 / 6 image(s) sélectionnée(s)</p>
                            <div class="preview-reference-grid" id="preview-reference-grid" aria-live="polite"></div>
                        </div>
                    </div>
                </section>

                <p class="cp-legal">
                    En envoyant, vous acceptez les
                    <a href="/conditions-utilisation.php" target="_blank" rel="noopener">conditions d’utilisation</a>.
                    <?php if (!$user_logged_in): ?>
                    Un compte sera créé avec votre numéro pour suivre la demande.
                    <?php endif; ?>
                </p>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Envoyer ma demande
                </button>
            </form>
            </div>
        </div>
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

    <?php include __DIR__ . '/includes/auth_intl_tel_scripts.php'; ?>
    <script>
        window.cpShopConfig = {
            userLoggedIn: <?php echo $user_logged_in ? 'true' : 'false'; ?>
        };
    </script>
    <script src="/js/commande-personnalisee.js<?php echo asset_version_query(); ?>"></script>
    <?php include('footer.php'); ?>
    <?php include __DIR__ . '/includes/floating_back_button.php'; ?>
</body>

</html>
