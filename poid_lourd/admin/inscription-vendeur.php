<?php
require_once __DIR__ . '/../includes/session_admin.php';
/**
 * Inscription vendeur — création boutique (sans session admin requise).
 */
session_start_persistent();
require_once __DIR__ . '/../includes/google_auth_coop.php';
require_once __DIR__ . '/../includes/auth_redirect.php';

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/../controllers/controller_admin.php';

if (isset($_SESSION['admin_id'])) {
    auth_redirect_after_login('/admin/dashboard.php');
}

$result = process_inscription_vendeur();
if (!empty($result['success'])) {
    $_SESSION['inscription_success'] = $result['message'];
    auth_redirect_after_login('/choix-connexion.php');
}

$err = (!empty($result['message']) && empty($result['success'])) ? $result['message'] : '';

require_once __DIR__ . '/../includes/asset_version.php';
require_once __DIR__ . '/../includes/site_url.php';
$url_choix_connexion = get_site_base_url() . '/choix-connexion.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouvrir ma boutique — inscription vendeur</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body class="auth-page auth-page--vendor page-inscription-vendeur">
    <header class="auth-header">
        <a class="logo" href="/index.php">
            <img src="/image/logo_market.png" alt="COLObanes">
        </a>
    </header>

    <div class="auth-layout">
        <main class="auth-main">
            <div class="auth-card">
                <div class="auth-card__inner">
                    <div class="auth-card__head">
                        <h1>Créer ma boutique</h1>
                    </div>

                    <?php if ($err): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $err; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    $social_auth_type = 'vendor';
                    $social_auth_redirect = '/admin/dashboard.php';
                    $social_auth_position = 'top';
                    include __DIR__ . '/../includes/google_auth_button.php';
                    ?>

                    <form method="post" action="" class="auth-inscription-form" id="inscriptionVendeurForm">
                        <div class="form-group">
                            <label for="identite"><i class="fas fa-id-card"></i> Identité (nom affiché) *</label>
                            <div class="input-wrapper">
                                <input type="text" id="identite" name="identite" placeholder="Nom public affiché"
                                    required maxlength="200" autocomplete="name"
                                    value="<?php echo isset($_POST['identite']) ? htmlspecialchars($_POST['identite']) : ''; ?>">
                                <i class="fas fa-user" aria-hidden="true"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="boutique_nom"><i class="fas fa-shop"></i> Nom de la boutique *</label>
                            <div class="input-wrapper">
                                <input type="text" id="boutique_nom" name="boutique_nom" placeholder="Ex. : Épicerie du marché"
                                    required maxlength="255" autocomplete="organization"
                                    value="<?php echo isset($_POST['boutique_nom']) ? htmlspecialchars($_POST['boutique_nom']) : ''; ?>">
                                <i class="fas fa-store" aria-hidden="true"></i>
                            </div>
                        </div>

                        <?php
                        require_once __DIR__ . '/../includes/marketplace_countries.php';
                        require_once __DIR__ . '/../includes/geo_regions.php';
                        require_once __DIR__ . '/../includes/ip_geo_resolver.php';
                        $sel_country = isset($_POST['boutique_country'])
                            ? strtoupper((string) $_POST['boutique_country'])
                            : ip_geo_detect_country_code();
                        if (!marketplace_country_is_valid($sel_country)) {
                            $sel_country = marketplace_country_default_code();
                        }
                        $sel_region = isset($_POST['boutique_region']) ? (string) $_POST['boutique_region'] : '';
                        require_once __DIR__ . '/../includes/boutique_types.php';
                        $sel_boutique_type = isset($_POST['boutique_type_id']) ? (int) $_POST['boutique_type_id'] : 0;
                        $boutique_types_required = boutique_types_inscription_required();
                        ?>
                        <script type="application/json" id="geoRegionsData"><?php echo geo_regions_json_for_js(); ?></script>
                        <div class="form-group">
                            <label for="boutique_country"><i class="fas fa-globe-africa"></i> Pays de la boutique *</label>
                            <div class="input-wrapper">
                                <select id="boutique_country" name="boutique_country" required class="auth-select">
                                    <?php echo marketplace_countries_options_html($sel_country, false); ?>
                                </select>
                                <i class="fas fa-flag" aria-hidden="true"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="boutique_region"><i class="fas fa-map-marker-alt"></i> Région de la boutique *</label>
                            <div class="input-wrapper">
                                <select id="boutique_region" name="boutique_region" required class="auth-select"
                                    data-selected="<?php echo htmlspecialchars($sel_region, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-empty-label="Sélectionnez une région">
                                    <?php echo geo_regions_options_html($sel_country, $sel_region, true, 'Sélectionnez une région'); ?>
                                </select>
                                <i class="fas fa-location-dot" aria-hidden="true"></i>
                            </div>
                        </div>

                        <?php if ($boutique_types_required): ?>
                        <div class="form-group">
                            <label for="boutique_type_id"><i class="fas fa-layer-group"></i> Type de boutique *</label>
                            <div class="input-wrapper">
                                <select id="boutique_type_id" name="boutique_type_id" required class="auth-select">
                                    <?php echo boutique_types_options_html($sel_boutique_type, true, 'Sélectionnez un type de boutique'); ?>
                                </select>
                                <i class="fas fa-tags" aria-hidden="true"></i>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="telephone"><i class="fas fa-phone"></i> Téléphone (connexion) *</label>
                            <div class="input-wrapper input-wrapper--intl-tel">
                                <input type="tel" id="telephone" name="telephone" placeholder="77 123 45 67"
                                    required autocomplete="tel"
                                    value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email <span class="form-optional">(facultatif)</span></label>
                            <div class="input-wrapper">
                                <input type="email" id="email" name="email" placeholder="contact@boutique.com" autocomplete="email"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <i class="fas fa-envelope" aria-hidden="true"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pin"><i class="fas fa-key"></i> Code PIN (6 chiffres) *</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                    placeholder="• • • • • •" required autocomplete="new-password" title="6 chiffres">
                                <button type="button" class="password-toggle" aria-label="Afficher le code PIN"
                                    onclick="togglePin('pin', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pin_confirm"><i class="fas fa-key"></i> Confirmer le code PIN *</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="pin_confirm" name="pin_confirm" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                    placeholder="• • • • • •" required autocomplete="new-password" title="6 chiffres">
                                <button type="button" class="password-toggle" aria-label="Afficher la confirmation du PIN"
                                    onclick="togglePin('pin_confirm', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-store"></i> Créer mon compte vendeur
                        </button>
                        <input type="hidden" name="insc_geo_lat" id="insc_geo_lat" value="">
                        <input type="hidden" name="insc_geo_lng" id="insc_geo_lng" value="">
                        <input type="hidden" name="insc_geo_precision" id="insc_geo_precision" value="">
                        <input type="hidden" name="insc_geo_manual" id="insc_geo_manual" value="">
                    </form>

                    <div class="auth-footer">
                        <p><a href="<?php echo htmlspecialchars($url_choix_connexion, ENT_QUOTES, 'UTF-8'); ?>">Déjà inscrit ? Connexion vendeur</a></p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function togglePin(inputId, button) {
            var input = document.getElementById(inputId);
            var icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
    <?php include __DIR__ . '/../includes/auth_intl_tel_scripts.php'; ?>
    <script>
        (function () {
            function bootIntlTelVendeur() {
                if (typeof window.initAuthIntlTel === 'function') {
                    window.initAuthIntlTel('telephone');
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootIntlTelVendeur);
            } else {
                bootIntlTelVendeur();
            }
        })();
    </script>
    <?php include __DIR__ . '/../includes/google_auth_scripts.php'; ?>
    <script src="/js/geo-country-region.js" defer></script>
    <?php require_once __DIR__ . '/../includes/geo_native_bridge_script.php'; ?>
    <script src="/js/geo-address-format.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-inscription-location.js<?php echo asset_version_query(); ?>" defer></script>
    <?php include __DIR__ . '/../includes/social_floating.php'; ?>
</body>
</html>