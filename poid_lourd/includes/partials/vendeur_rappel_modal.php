<?php
/**
 * Popup rappel vendeur — dashboard uniquement.
 * Variables : $__vendeur_rappel_pending (array), $__vendeur_admin (array vendeur)
 */
if (empty($__vendeur_rappel_pending) || !is_array($__vendeur_rappel_pending)) {
    return;
}
if (empty($__vendeur_admin) || !is_array($__vendeur_admin)) {
    return;
}

require_once dirname(__DIR__, 2) . '/includes/marketplace_countries.php';
require_once dirname(__DIR__, 2) . '/includes/geo_regions.php';
require_once dirname(__DIR__, 2) . '/includes/boutique_types.php';
require_once dirname(__DIR__, 2) . '/includes/boutique_vendeur_display.php';
require_once dirname(__DIR__, 2) . '/admin/includes/vendeur_boutique_localisation_ui_vars.php';

$vr = $__vendeur_rappel_pending;
$vr_id = (int) ($vr['id'] ?? 0);
$vr_titre = (string) ($vr['titre'] ?? '');
$vr_message = (string) ($vr['message'] ?? '');
$vr_alabel = (string) ($vr['action_label'] ?? 'Continuer');
$vr_atype = (string) ($vr['action_type'] ?? '');

if ($vr_id <= 0 || $vr_atype === '') {
    return;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$vr_csrf = htmlspecialchars((string) ($_SESSION['admin_csrf'] ?? ''), ENT_QUOTES, 'UTF-8');

$vbl_vars = vendeur_boutique_localisation_prepare_ui_vars($__vendeur_admin, [
    'admin_id' => (int) ($__vendeur_admin['id'] ?? 0),
    'allow_reverse_geocode_upgrade' => false,
]);
extract($vbl_vars);

$c1_val = htmlspecialchars(boutique_normalize_hex_color($__vendeur_admin['boutique_couleur_principale'] ?? '') ?: '#3564a6', ENT_QUOTES, 'UTF-8');
$c2_val = htmlspecialchars(boutique_normalize_hex_color($__vendeur_admin['boutique_couleur_accent'] ?? '') ?: '#ff6b35', ENT_QUOTES, 'UTF-8');

$logo_url = '';
$clogo = trim((string) ($__vendeur_admin['boutique_logo'] ?? ''));
if ($clogo !== '') {
    $logo_url = '/upload/' . htmlspecialchars(str_replace('\\', '/', $clogo), ENT_QUOTES, 'UTF-8');
}

$boutique_nom = trim((string) ($_SESSION['admin_boutique_nom'] ?? ''));
if ($boutique_nom === '') {
    $boutique_nom = 'Ma boutique';
}
$mock_logo_word = mb_strtoupper(mb_substr(preg_replace('/\s+/', '', $boutique_nom), 0, 5, 'UTF-8'), 'UTF-8');
if ($mock_logo_word === '') {
    $mock_logo_word = 'SHOP';
}

$sel_type_id = (int) ($__vendeur_admin['boutique_type_id'] ?? 0);

$vr_action_types = vendeur_rappel_action_types();
$vr_type_meta = $vr_action_types[$vr_atype] ?? ['label' => 'Rappel', 'hint' => ''];
$vr_type_label = (string) ($vr_type_meta['label'] ?? 'Rappel');

require_once dirname(__DIR__, 2) . '/includes/asset_version.php';
?>
<link rel="stylesheet" href="/css/vendeur-rappel-modal.css<?php echo asset_version_query(); ?>">

<div class="vrappel-overlay" id="vrappelOverlay" role="dialog" aria-modal="true" aria-labelledby="vrappelTitle">
    <div class="vrappel-modal" id="vrappelModal">
        <header class="vrappel-modal__header">
            <div class="vrappel-modal__header-glow" aria-hidden="true"></div>
            <div class="vrappel-modal__header-inner">
                <span class="vrappel-modal__eyebrow">
                    <i class="fas fa-bell" aria-hidden="true"></i> Rappel boutique
                </span>
                <div class="vrappel-modal__icon" aria-hidden="true">
                    <i class="fas fa-store"></i>
                </div>
                <h2 class="vrappel-modal__title" id="vrappelTitle"><?php echo htmlspecialchars($vr_titre, ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="vrappel-modal__type-pill"><?php echo htmlspecialchars($vr_type_label, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </header>

        <div class="vrappel-modal__content">
            <p class="vrappel-modal__text"><?php echo nl2br(htmlspecialchars($vr_message, ENT_QUOTES, 'UTF-8')); ?></p>

            <div class="vrappel-modal__body">
            <?php if ($vr_atype === 'link_certification'): ?>
                <div class="vrappel-callout">
                    <i class="fas fa-certificate" aria-hidden="true"></i>
                    <span>Passez au niveau supérieur pour gagner la confiance des clients.</span>
                </div>

            <?php elseif ($vr_atype === 'select_boutique_type'): ?>
                <form method="POST" action="/admin/rappel-action.php" class="vrappel-inline-form" id="vrappelActionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $vr_csrf; ?>">
                    <input type="hidden" name="rappel_id" value="<?php echo $vr_id; ?>">
                    <div class="vrappel-field">
                        <label for="vrappel_boutique_type_id">Type de boutique *</label>
                        <select id="vrappel_boutique_type_id" name="boutique_type_id" required>
                            <?php echo boutique_types_options_html($sel_type_id, true, 'Sélectionnez un type'); ?>
                        </select>
                    </div>
                </form>

            <?php elseif ($vr_atype === 'select_region'): ?>
                <form method="POST" action="/admin/rappel-action.php" class="vrappel-inline-form" id="vrappelActionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $vr_csrf; ?>">
                    <input type="hidden" name="rappel_id" value="<?php echo $vr_id; ?>">
                    <script type="application/json" id="vrappelGeoRegionsData"><?php echo geo_regions_json_for_js(); ?></script>
                    <div class="vrappel-field">
                        <label for="vrappel_boutique_country">Pays *</label>
                        <select id="vrappel_boutique_country" name="boutique_country" required>
                            <?php echo marketplace_countries_options_html($vbl_country_val, false); ?>
                        </select>
                    </div>
                    <div class="vrappel-field">
                        <label for="vrappel_boutique_region">Région *</label>
                        <select id="vrappel_boutique_region" name="boutique_region" required
                            data-selected="<?php echo $vbl_region_val; ?>"
                            data-empty-label="Sélectionnez une région">
                            <?php echo geo_regions_options_html($vbl_country_val, $vbl_region_raw, true, 'Sélectionnez une région'); ?>
                        </select>
                    </div>
                </form>

            <?php elseif ($vr_atype === 'capture_geo'): ?>
                <form method="POST" action="/admin/rappel-action.php" class="vrappel-inline-form" id="vrappelGeoForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $vr_csrf; ?>">
                    <input type="hidden" name="rappel_id" value="<?php echo $vr_id; ?>">
                    <input type="hidden" name="geo_lat" id="vrappel_geo_lat" value="">
                    <input type="hidden" name="geo_lng" id="vrappel_geo_lng" value="">
                </form>
                <div id="vrappelGeoStatus" class="vrappel-geo-status" role="status"></div>

            <?php elseif ($vr_atype === 'upload_logo'): ?>
                <form method="POST" action="/admin/rappel-action.php" enctype="multipart/form-data" class="vrappel-inline-form" id="vrappelActionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $vr_csrf; ?>">
                    <input type="hidden" name="rappel_id" value="<?php echo $vr_id; ?>">
                    <div class="vrappel-logo-preview">
                        <div class="vrappel-logo-frame" id="vrappelLogoFrame">
                            <?php if ($logo_url !== ''): ?>
                                <img src="<?php echo $logo_url; ?>" alt="" id="vrappelLogoImg">
                            <?php else: ?>
                                <span class="vrappel-logo-placeholder" id="vrappelLogoPlaceholder"><i class="fas fa-store"></i></span>
                                <img src="" alt="" id="vrappelLogoImg" hidden>
                            <?php endif; ?>
                        </div>
                        <div>
                            <input type="file" class="vrappel-file-input" id="vrappel_logo_file" name="boutique_logo"
                                accept="image/jpeg,image/png,image/gif,image/webp" required>
                            <label for="vrappel_logo_file" class="vrappel-file-label">
                                <i class="fas fa-upload"></i> Choisir une image
                            </label>
                        </div>
                    </div>
                </form>

            <?php elseif ($vr_atype === 'customize_colors'): ?>
                <p class="vrappel-colors-intro vrappel-callout vrappel-callout--soft">
                    <i class="fas fa-palette" aria-hidden="true"></i>
                    Personnalisez les couleurs de votre vitrine pour vous démarquer.
                </p>
                <div class="vrappel-colors-view" id="vrappelColorsView">
                    <div class="vrappel-colors-head">
                        <h3>Couleurs de la boutique</h3>
                        <button type="button" class="vrappel-colors-back" id="vrappelColorsBack">
                            <i class="fas fa-arrow-left"></i> Retour
                        </button>
                    </div>
                    <form method="POST" action="/admin/rappel-action.php" class="vrappel-inline-form" id="vrappelColorsForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $vr_csrf; ?>">
                        <input type="hidden" name="rappel_id" value="<?php echo $vr_id; ?>">
                        <div class="vrappel-colors-pickers">
                            <div class="vrappel-color-row">
                                <span class="vrappel-color-swatch" id="vrappelSwatchMain" style="background:<?php echo $c1_val; ?>;">
                                    <input type="color" id="vrappel_c1" name="couleur_principale" value="<?php echo $c1_val; ?>">
                                </span>
                                <div class="vrappel-color-meta">
                                    <strong>Couleur principale</strong>
                                    <span id="vrappelHexC1"><?php echo $c1_val; ?></span>
                                </div>
                            </div>
                            <div class="vrappel-color-row">
                                <span class="vrappel-color-swatch" id="vrappelSwatchAccent" style="background:<?php echo $c2_val; ?>;">
                                    <input type="color" id="vrappel_c2" name="couleur_accent" value="<?php echo $c2_val; ?>">
                                </span>
                                <div class="vrappel-color-meta">
                                    <strong>Couleur d'accent</strong>
                                    <span id="vrappelHexC2"><?php echo $c2_val; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="vrappel-mock-shop" id="vrappelMockShop" style="--vr-mock-c1:<?php echo $c1_val; ?>;--vr-mock-c2:<?php echo $c2_val; ?>;">
                            <div class="vrappel-mock-shop__stripe"></div>
                            <div class="vrappel-mock-shop__nav">
                                <div class="vrappel-mock-shop__logo" id="vrappelMockLogo">
                                    <?php if ($logo_url !== ''): ?>
                                        <img src="<?php echo $logo_url; ?>" alt="">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($mock_logo_word, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="vrappel-mock-shop__search">Que recherchez-vous ?</div>
                            </div>
                            <div class="vrappel-mock-shop__body">
                                <div class="vrappel-mock-card">
                                    <div class="vrappel-mock-card__img"></div>
                                    <span class="vrappel-mock-card__badge" id="vrappelMockBadge1">Promo</span>
                                    <div class="vrappel-mock-card__price" id="vrappelMockPrice1">12 500 F</div>
                                </div>
                                <div class="vrappel-mock-card">
                                    <div class="vrappel-mock-card__img"></div>
                                    <span class="vrappel-mock-card__badge" id="vrappelMockBadge2">Nouveau</span>
                                    <div class="vrappel-mock-card__price" id="vrappelMockPrice2">8 900 F</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            </div>
        </div>

        <footer class="vrappel-modal__footer">
        <div class="vrappel-modal__actions vrappel-modal__actions--main" id="vrappelMainActions">
            <?php if ($vr_atype === 'link_certification'): ?>
                <a href="/admin/parametres/certification.php" class="vrappel-btn vrappel-btn--primary">
                    <i class="fas fa-certificate"></i> <?php echo htmlspecialchars($vr_alabel, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php elseif ($vr_atype === 'capture_geo'): ?>
                <button type="button" class="vrappel-btn vrappel-btn--primary" id="vrappelBtnGeo">
                    <i class="fas fa-location-crosshairs"></i> <?php echo htmlspecialchars($vr_alabel, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php elseif ($vr_atype === 'customize_colors'): ?>
                <button type="button" class="vrappel-btn vrappel-btn--primary" id="vrappelBtnOpenColors">
                    <i class="fas fa-palette"></i> <?php echo htmlspecialchars($vr_alabel, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php else: ?>
                <button type="submit" form="vrappelActionForm" class="vrappel-btn vrappel-btn--primary">
                    <i class="fas fa-check"></i> <?php echo htmlspecialchars($vr_alabel, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php endif; ?>

            <form method="POST" action="/admin/rappel-dismiss.php" class="vrappel-snooze-form">
                <input type="hidden" name="csrf_token" value="<?php echo $vr_csrf; ?>">
                <input type="hidden" name="rappel_id" value="<?php echo $vr_id; ?>">
                <button type="submit" class="vrappel-btn vrappel-btn--ghost">
                    <i class="fas fa-clock" aria-hidden="true"></i> Plus tard
                </button>
            </form>
        </div>

        <?php if ($vr_atype === 'customize_colors'): ?>
        <div class="vrappel-modal__actions vrappel-modal__actions--colors">
            <button type="submit" form="vrappelColorsForm" class="vrappel-btn vrappel-btn--accent">
                <i class="fas fa-floppy-disk"></i> Enregistrer les couleurs
            </button>
        </div>
        <?php endif; ?>
        </footer>
    </div>
</div>

<script src="/js/geo-native-bridge.js<?php echo asset_version_query(); ?>"></script>
<script src="/js/geo-country-region.js<?php echo asset_version_query(); ?>" defer></script>
<script src="/js/vendeur-rappel-modal.js<?php echo asset_version_query(); ?>" defer></script>
