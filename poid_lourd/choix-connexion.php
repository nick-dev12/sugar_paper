<?php
/**
 * Connexion unifiée (client + équipe / admin via email + mot de passe).
 * La création de compte est proposée via choix-inscription.php.
 */
require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/includes/auth_redirect.php';
session_start_persistent();
require_once __DIR__ . '/includes/google_auth_coop.php';

if (ob_get_level() === 0) {
    ob_start();
}

$redirect_after = isset($_POST['redirect']) ? trim($_POST['redirect']) : (isset($_GET['redirect']) ? trim($_GET['redirect']) : '');
if ($redirect_after && $redirect_after[0] !== '/') {
    $redirect_after = '/' . $redirect_after;
}
$redirect_url = (!empty($redirect_after) && strpos($redirect_after, '//') === false) ? $redirect_after : '/index.php';

if (auth_session_is_vendeur()) {
    auth_redirect_after_login(auth_vendeur_dashboard_url());
}

if (auth_user_is_logged_in()) {
    auth_redirect_after_login($redirect_url);
}

require_once __DIR__ . '/controllers/controller_users.php';

$result = ['success' => false, 'message' => '', 'type' => null, 'admin' => null, 'user' => null, 'vendeur_collaborateur' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = process_unified_login();
}

if (isset($result['success']) && $result['success'] && $result['type'] === 'admin' && $result['admin']) {
    session_regenerate_persistent();
    $_SESSION['admin_id'] = $result['admin']['id'];
    $_SESSION['admin_nom'] = $result['admin']['nom'];
    $_SESSION['admin_prenom'] = $result['admin']['prenom'];
    $_SESSION['admin_email'] = $result['admin']['email'] ?? '';
    $_SESSION['admin_statut'] = $result['admin']['statut'];
    $_SESSION['admin_role'] = normalize_admin_role($result['admin']['role'] ?? 'admin');
    $_SESSION['admin_boutique_nom'] = trim((string) ($result['admin']['boutique_nom'] ?? ''));
    $_SESSION['admin_boutique_slug'] = trim((string) ($result['admin']['boutique_slug'] ?? ''));

    if (!empty($result['vendeur_collaborateur']) && is_array($result['vendeur_collaborateur'])) {
        $_SESSION['vendeur_collaborateur_id'] = (int) ($result['vendeur_collaborateur']['id'] ?? 0);
        $_SESSION['vendeur_collaborateur_nom'] = trim((string) ($result['vendeur_collaborateur']['nom'] ?? ''));
    } else {
        unset($_SESSION['vendeur_collaborateur_id'], $_SESSION['vendeur_collaborateur_nom']);
    }

    $login_role = normalize_admin_role($result['admin']['role'] ?? 'admin');
    auth_set_portal_cookie($login_role === 'vendeur' ? 'vendeur' : 'admin');

    auth_redirect_after_login(auth_login_redirect_url_for_admin());
}

if (isset($result['success']) && $result['success'] && $result['type'] === 'user' && $result['user']) {
    session_regenerate_persistent();
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user_nom'] = $result['user']['nom'];
    $_SESSION['user_prenom'] = $result['user']['prenom'];
    $_SESSION['user_email'] = (string) ($result['user']['email'] ?? '');
    $_SESSION['user_telephone'] = $result['user']['telephone'];
    $_SESSION['user_statut'] = $result['user']['statut'];

    auth_set_portal_cookie('client');

    if (file_exists(__DIR__ . '/includes/panier_invite.php')) {
        try {
            require_once __DIR__ . '/includes/panier_invite.php';
            panier_fusionner_invite_apres_connexion((int) $result['user']['id']);
        } catch (Throwable $e) {
            error_log('[choix-connexion] fusion panier invité : ' . $e->getMessage());
        }
    }

    auth_redirect_after_login($redirect_url);
}

$inscription_success = '';
if (isset($_SESSION['inscription_success'])) {
    $inscription_success = $_SESSION['inscription_success'];
    unset($_SESSION['inscription_success']);
}

$active_login_mode = 'email';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_mode']) && (string) $_POST['login_mode'] === 'phone') {
    $active_login_mode = 'phone';
}

$login_locked = false;
$login_remaining_seconds = 0;
$login_show_warning = false;
$login_remaining_attempts = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = login_attempt_extract_identifier_from_post();
    if ($login_identifier !== '') {
        login_attempt_bind_identifier($login_identifier);
        login_attempt_unlock_if_expired();
        $login_locked = login_attempt_is_locked();
        $login_remaining_seconds = login_attempt_remaining_seconds();
        $login_show_warning = login_attempt_show_warning();
        $login_remaining_attempts = login_attempt_remaining_before_lock();
    }
}

require_once __DIR__ . '/includes/site_brand.php';
$show_auth_form = ($_SERVER['REQUEST_METHOD'] === 'POST');
$redirect_qs = !empty($redirect_after) ? '?' . http_build_query(['redirect' => $redirect_after]) : '';
$form_action = 'choix-connexion.php' . $redirect_qs;
$inscription_href = '/choix-inscription.php';
$rq = isset($_GET['redirect']) ? trim((string) $_GET['redirect']) : '';
if (preg_match('/^[a-z0-9_-]+$/i', $rq)) {
    $inscription_href .= '?' . http_build_query(['redirect' => $rq]);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/includes/asset_version.php'; ?>
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Connexion - COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-choix-connexion-hub.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mc-v2-shop-promo.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="auth-page page-choix-connexion auth-hub auth-page--<?php echo $active_login_mode === 'phone' ? 'phone' : 'email'; ?><?php echo $show_auth_form ? ' auth-hub--form' : ' auth-hub--landing'; ?>">

    <div class="auth-hub-shell">
        <?php
        $mc_v2_shop_promo_id = 'auth-shop-promo-title';
        include __DIR__ . '/includes/mc_v2_shop_promo.php';
        ?>

    <div class="auth-hub" id="authHub">
        <main class="auth-hub__main">
            <div class="auth-hub__brand" aria-hidden="<?php echo $show_auth_form ? 'true' : 'false'; ?>">
                <div class="auth-hub__logo-wrap">
                    <div class="auth-hub__waves" aria-hidden="true">
                        <span class="auth-hub__wave"></span>
                        <span class="auth-hub__wave"></span>
                        <span class="auth-hub__wave"></span>
                        <span class="auth-hub__wave"></span>
                    </div>
                    <img class="auth-hub__logo" src="/image/logo_market.png" alt="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <div class="auth-hub__landing" id="authHubLanding"<?php echo $show_auth_form ? ' hidden' : ''; ?>>
                <?php if (!empty($inscription_success)): ?>
                <div class="auth-hub__messages">
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($inscription_success); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="auth-hub__actions">
                    <?php
                    $google_auth_type = 'auto';
                    $google_auth_redirect = $redirect_url;
                    $google_auth_position = 'top';
                    $google_auth_disabled = $login_locked;
                    $social_auth_variant = 'hub';
                    include __DIR__ . '/includes/google_auth_button.php';
                    ?>

                    <div class="auth-hub__sep" aria-hidden="true"><span>Ou</span></div>

                    <button type="button" class="auth-hub-btn" id="btnAuthPhone"<?php echo $login_locked ? ' disabled' : ''; ?>>
                        <i class="fas fa-mobile-screen-button" aria-hidden="true"></i>
                        Continuer avec Téléphone
                    </button>
                    <button type="button" class="auth-hub-btn" id="btnAuthEmail"<?php echo $login_locked ? ' disabled' : ''; ?>>
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        Continuer avec E-mail
                    </button>
                </div>

                <p class="auth-hub__legal">
                    En poursuivant, vous acceptez les
                    <a href="/conditions-utilisation.php" target="_blank" rel="noopener noreferrer">Conditions d'utilisation</a>
                    et la
                    <a href="/politique-confidentialite.php" target="_blank" rel="noopener noreferrer">Politique de confidentialité</a>.
                </p>
            </div>

            <div class="auth-hub__form" id="authHubForm"<?php echo $show_auth_form ? '' : ' hidden'; ?>>
                <button type="button" class="auth-hub__back" id="btnAuthBack">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
                </button>

                <div class="auth-hub__form-head">
                    <h1 id="authFormTitle"><?php echo $active_login_mode === 'phone' ? 'Connexion par téléphone' : 'Connexion par e-mail'; ?></h1>
                    <p>Accédez à votre compte <?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <div class="auth-hub__messages">
                    <?php if (!empty($inscription_success)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($inscription_success); ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($login_locked) && $login_remaining_seconds > 0): ?>
                        <?php include __DIR__ . '/includes/login_rate_lock_banner.php'; ?>
                    <?php else: ?>
                        <?php if (!empty($login_show_warning)): ?>
                            <?php include __DIR__ . '/includes/login_rate_warning_banner.php'; ?>
                        <?php endif; ?>
                        <?php if (isset($result['message']) && $result['message'] !== '' && !$result['success']): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i> <?php echo login_safe_html_message($result['message']); ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="auth-hub__form-card">
                    <div id="panel-email" class="login-panel" role="region" aria-labelledby="authFormTitle"
                        <?php echo $active_login_mode !== 'email' ? 'hidden' : ''; ?>>
                    <form method="POST" action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="login_mode" value="email">
                        <?php if (!empty($redirect_after)): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_after); ?>">
                        <?php endif; ?>
                        <fieldset class="login-fieldset"<?php echo $login_locked ? ' disabled' : ''; ?>>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                            <div class="input-wrapper">
                                <input type="email" id="email" name="email" placeholder="votre@email.com" autocomplete="email"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Mot de passe *</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="password" name="password" placeholder="Votre mot de passe" autocomplete="current-password">
                                <button type="button" class="password-toggle" aria-label="Afficher le mot de passe"
                                    onclick="togglePassword('password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="forgot-password-link">
                                <a href="/mot-de-passe-oublie.php">Mot de passe oublié ?</a>
                            </div>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="accepte_conditions" name="accepte_conditions" value="1" <?php echo (isset($_POST['accepte_conditions']) && $_POST['accepte_conditions'] == '1') ? 'checked' : ''; ?>>
                            <label for="accepte_conditions">
                                J'accepte les <a href="/conditions-utilisation.php" target="_blank" rel="noopener noreferrer">conditions d'utilisation</a>
                            </label>
                        </div>
                        <button type="submit" class="btn-submit"<?php echo $login_locked ? ' disabled' : ''; ?>>
                            <i class="fas fa-sign-in-alt"></i> Se connecter
                        </button>
                        </fieldset>
                    </form>
                    </div>

                    <div id="panel-phone" class="login-panel" role="region" aria-labelledby="authFormTitle"
                        <?php echo $active_login_mode !== 'phone' ? 'hidden' : ''; ?>>
                    <form method="POST" action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="login_mode" value="phone">
                        <?php if (!empty($redirect_after)): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_after); ?>">
                        <?php endif; ?>
                        <fieldset class="login-fieldset"<?php echo $login_locked ? ' disabled' : ''; ?>>
                        <div class="form-group">
                            <label for="telephone"><i class="fas fa-phone"></i> Numéro de téléphone *</label>
                            <div class="input-wrapper input-wrapper--intl-tel">
                                <input type="tel" id="telephone" name="telephone" placeholder="77 123 45 67" autocomplete="tel"
                                    value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="pin"><i class="fas fa-key"></i> Code PIN ou mot de passe *</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="pin" name="pin" placeholder="Pin ou mot de passe" autocomplete="current-password">
                                <button type="button" class="password-toggle" aria-label="Afficher le code"
                                    onclick="togglePassword('pin', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="forgot-password-link">
                                <a href="/mot-de-passe-oublie.php">Mot de passe oublié ?</a>
                            </div>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="accepte_conditions_phone" name="accepte_conditions_phone" value="1" <?php echo (isset($_POST['accepte_conditions_phone']) && $_POST['accepte_conditions_phone'] === '1') ? 'checked' : ''; ?>>
                            <label for="accepte_conditions_phone">
                                J'accepte les <a href="/conditions-utilisation.php" target="_blank" rel="noopener noreferrer">conditions d'utilisation</a>
                            </label>
                        </div>
                        <button type="submit" class="btn-submit"<?php echo $login_locked ? ' disabled' : ''; ?>>
                            <i class="fas fa-sign-in-alt"></i> Se connecter
                        </button>
                        </fieldset>
                    </form>
                    </div>
                </div>

                <p class="auth-hub__signup" style="margin-top:1.25rem;">
                    Pas encore de compte ?
                    <a href="<?php echo htmlspecialchars($inscription_href, ENT_QUOTES, 'UTF-8'); ?>">Créer un compte</a>
                </p>
            </div>
        </main>
    </div>
    </div>

    <script>
        (function () {
            var landing = document.getElementById('authHubLanding');
            var formView = document.getElementById('authHubForm');
            var panelEmail = document.getElementById('panel-email');
            var panelPhone = document.getElementById('panel-phone');
            var formTitle = document.getElementById('authFormTitle');
            var btnPhone = document.getElementById('btnAuthPhone');
            var btnEmail = document.getElementById('btnAuthEmail');
            var btnBack = document.getElementById('btnAuthBack');
            var showFormOnLoad = <?php echo $show_auth_form ? 'true' : 'false'; ?>;

            function showMode(mode) {
                var isEmail = mode === 'email';
                document.body.classList.remove('auth-page--email', 'auth-page--phone');
                document.body.classList.add(isEmail ? 'auth-page--email' : 'auth-page--phone');
                if (panelEmail) panelEmail.hidden = !isEmail;
                if (panelPhone) panelPhone.hidden = isEmail;
                if (formTitle) {
                    formTitle.textContent = isEmail ? 'Connexion par e-mail' : 'Connexion par téléphone';
                }
            }

            function openForm(mode) {
                showMode(mode);
                if (landing) landing.hidden = true;
                if (formView) formView.hidden = false;
                document.body.classList.remove('auth-hub--landing');
                document.body.classList.add('auth-hub--form');
                var firstInput = document.querySelector(
                    mode === 'email' ? '#panel-email input:not([type=hidden])' : '#telephone'
                );
                if (firstInput && typeof firstInput.focus === 'function') {
                    window.setTimeout(function () { firstInput.focus(); }, 120);
                }
            }

            function backToLanding() {
                if (landing) landing.hidden = false;
                if (formView) formView.hidden = true;
                document.body.classList.add('auth-hub--landing');
                document.body.classList.remove('auth-hub--form');
            }

            if (btnPhone) btnPhone.addEventListener('click', function () { openForm('phone'); });
            if (btnEmail) btnEmail.addEventListener('click', function () { openForm('email'); });
            if (btnBack) btnBack.addEventListener('click', backToLanding);

            if (showFormOnLoad) {
                openForm(<?php echo json_encode($active_login_mode); ?>);
            }
        })();

        function togglePassword(inputId, button) {
            var input = document.getElementById(inputId);
            var icon = button.querySelector('i');
            if (!input || !icon) return;
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
    <?php include __DIR__ . '/includes/auth_intl_tel_scripts.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }
        });
    </script>
    <?php include __DIR__ . '/includes/google_auth_scripts.php'; ?>
    <?php include __DIR__ . '/includes/social_floating.php'; ?>
</body>

</html>
