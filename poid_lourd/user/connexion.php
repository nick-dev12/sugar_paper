<?php
/**
 * Page de connexion utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
require_once __DIR__ . '/../includes/auth_redirect.php';
session_start_persistent();
require_once __DIR__ . '/../includes/google_auth_coop.php';

// Redirection après connexion (page demandée ou index)
$redirect_after = isset($_POST['redirect']) ? trim($_POST['redirect']) : (isset($_GET['redirect']) ? trim($_GET['redirect']) : '');
if ($redirect_after && $redirect_after[0] !== '/') {
    $redirect_after = '/' . $redirect_after;
}
$redirect_url = (!empty($redirect_after) && strpos($redirect_after, '//') === false) ? $redirect_after : '/index.php';

// Vendeur déjà connecté → tableau de bord boutique
if (auth_session_is_vendeur()) {
    header('Location: ' . auth_vendeur_dashboard_url());
    exit;
}

// Si l'utilisateur est déjà connecté, rediriger
if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
    header('Location: ' . $redirect_url);
    exit;
}

// Traiter le formulaire de connexion (admin + user)
require_once __DIR__ . '/../controllers/controller_users.php';
$result = process_unified_login();

// Connexion admin : session + redirection vers l'espace admin
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

    header('Location: ' . auth_login_redirect_url_for_admin());
    exit;
}

// Connexion utilisateur : session + redirection
if (isset($result['success']) && $result['success'] && $result['type'] === 'user' && $result['user']) {
    session_regenerate_persistent();
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user_nom'] = $result['user']['nom'];
    $_SESSION['user_prenom'] = $result['user']['prenom'];
    $_SESSION['user_email'] = (string) ($result['user']['email'] ?? '');
    $_SESSION['user_telephone'] = $result['user']['telephone'];
    $_SESSION['user_statut'] = $result['user']['statut'];

    auth_set_portal_cookie('client');

    if (file_exists(__DIR__ . '/../includes/panier_invite.php')) {
        require_once __DIR__ . '/../includes/panier_invite.php';
        panier_fusionner_invite_apres_connexion((int) $result['user']['id']);
    }

    header('Location: ' . $redirect_url);
    exit;
}

// Afficher le message de succès d'inscription si présent
$inscription_success = '';
if (isset($_SESSION['inscription_success'])) {
    $inscription_success = $_SESSION['inscription_success'];
    unset($_SESSION['inscription_success']);
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

$active_login_mode = (isset($_POST['login_mode']) && (string) $_POST['login_mode'] === 'phone') ? 'phone' : 'email';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Connexion - COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="auth-page auth-page--<?php echo $active_login_mode === 'phone' ? 'phone' : 'email'; ?>">
    <header class="auth-header">
        <a class="logo" href="/index.php">
            <img src="/image/logo_market.png" alt="COLObanes">
        </a>
    </header>

    <div class="auth-layout">
        <!-- <aside class="auth-hero" aria-label="Marché en ligne">
            <span class="auth-hero__kicker"><i class="fas fa-store" aria-hidden="true"></i> E‑commerce</span>
            <h2 class="auth-hero__title">Votre espace marché : achetez et suivez vos commandes</h2>
            <p class="auth-hero__lead">Le même compte pour le catalogue, le panier et le suivi. Les vendeurs et équipes se connectent aussi depuis cette page : email et mot de passe, ou téléphone et code.</p>
            <div class="auth-hero__grid">
                <div class="auth-hero-card">
                    <div class="auth-hero-card__ic" aria-hidden="true"><i class="fas fa-bag-shopping"></i></div>
                    <span>Achats en ligne</span>
                </div>
                <div class="auth-hero-card">
                    <div class="auth-hero-card__ic" aria-hidden="true"><i class="fas fa-receipt"></i></div>
                    <span>Suivi commande</span>
                </div>
                <div class="auth-hero-card">
                    <div class="auth-hero-card__ic" aria-hidden="true"><i class="fas fa-seedling"></i></div>
                    <span>Produits naturels</span>
                </div>
            </div>
        </aside> -->

        <main class="auth-main">
        <div class="auth-card">
            <div class="auth-card__inner">
            <div class="auth-card__head">
                <div class="auth-card__icon" aria-hidden="true"><i class="fas fa-right-to-bracket"></i></div>
                <h1>Connexion</h1>
                <!-- <p>Compte client ou accès équipe : <strong>email</strong> ou <strong>téléphone</strong>
                    (PIN / mot de passe). L’affichage reflète le mode choisi.</p> -->
            </div>

            <?php if (!empty($inscription_success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($inscription_success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($login_locked) && $login_remaining_seconds > 0): ?>
                <?php include __DIR__ . '/../includes/login_rate_lock_banner.php'; ?>
            <?php else: ?>
                <?php if (!empty($login_show_warning)): ?>
                    <?php include __DIR__ . '/../includes/login_rate_warning_banner.php'; ?>
                <?php endif; ?>
                <?php if (isset($result['message']) && $result['message'] !== '' && !$result['success']): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo login_safe_html_message($result['message']); ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            $google_auth_type = 'auto';
            $google_auth_redirect = $redirect_url;
            $google_auth_position = 'top';
            $google_auth_disabled = $login_locked;
            include __DIR__ . '/../includes/google_auth_button.php';
            ?>

            <div class="login-mode-tabs" role="tablist" aria-label="Mode de connexion">
                <button type="button" role="tab" id="tab-email" aria-controls="panel-email"
                    aria-selected="<?php echo $active_login_mode === 'email' ? 'true' : 'false'; ?>"
                    tabindex="<?php echo $active_login_mode === 'email' ? '0' : '-1'; ?>"
                    <?php echo $login_locked ? 'disabled' : ''; ?>>
                    <i class="fas fa-envelope" aria-hidden="true"></i> Email
                </button>
                <button type="button" role="tab" id="tab-phone" aria-controls="panel-phone"
                    aria-selected="<?php echo $active_login_mode === 'phone' ? 'true' : 'false'; ?>"
                    tabindex="<?php echo $active_login_mode === 'phone' ? '0' : '-1'; ?>"
                    <?php echo $login_locked ? 'disabled' : ''; ?>>
                    <i class="fas fa-phone" aria-hidden="true"></i> Téléphone
                </button>
            </div>

            <div id="panel-email" class="login-panel" role="tabpanel" aria-labelledby="tab-email"
                <?php echo $active_login_mode !== 'email' ? 'hidden' : ''; ?>>
            <form method="POST" action="">
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
                    <input type="checkbox" id="accepte_conditions" name="accepte_conditions" value="1" <?php echo (isset($_POST['accepte_conditions']) && $_POST['accepte_conditions'] === '1') ? 'checked' : ''; ?>>
                    <label for="accepte_conditions">
                        J'accepte les <a href="/conditions-utilisation.php" target="_blank">conditions d'utilisation</a>
                        (obligatoire pour les comptes clients)
                    </label>
                </div>

                <button type="submit" class="btn-submit"<?php echo $login_locked ? ' disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
                </fieldset>
            </form>
            </div>

            <div id="panel-phone" class="login-panel" role="tabpanel" aria-labelledby="tab-phone"
                <?php echo $active_login_mode !== 'phone' ? 'hidden' : ''; ?>>
            <form method="POST" action="">
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
                        <input type="password" id="pin" name="pin" placeholder="Même secret que pour votre compte" autocomplete="current-password">
                        <button type="button" class="password-toggle" aria-label="Afficher le code"
                            onclick="togglePassword('pin', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="accepte_conditions_phone" name="accepte_conditions_phone" value="1" <?php echo (isset($_POST['accepte_conditions_phone']) && $_POST['accepte_conditions_phone'] === '1') ? 'checked' : ''; ?>>
                    <label for="accepte_conditions_phone">
                        J'accepte les <a href="/conditions-utilisation.php" target="_blank">conditions d'utilisation</a>
                        (obligatoire pour les comptes clients)
                    </label>
                </div>
                <button type="submit" class="btn-submit"<?php echo $login_locked ? ' disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
                </fieldset>
            </form>
            </div>

            <div class="auth-footer">
                <p>Vous n'avez pas de compte ? <a href="/choix-inscription.php<?php
$rget = isset($_GET['redirect']) ? trim((string) $_GET['redirect']) : '';
$rsafe = preg_match('/^[a-z0-9_-]+$/i', $rget) ? $rget : '';
echo $rsafe !== '' ? htmlspecialchars('?' . http_build_query(['redirect' => $rsafe])) : '';
?>">Créer un compte</a></p>
            </div>
            </div>
        </div>
        </main>
    </div>

    <script>
        (function () {
            var tabEmail = document.getElementById('tab-email');
            var tabPhone = document.getElementById('tab-phone');
            var panelEmail = document.getElementById('panel-email');
            var panelPhone = document.getElementById('panel-phone');
            if (!tabEmail || !tabPhone || !panelEmail || !panelPhone) return;

            function showMode(mode) {
                var isEmail = mode === 'email';
                var root = document.querySelector('.auth-page');
                if (root) {
                    root.classList.remove('auth-page--email', 'auth-page--phone');
                    root.classList.add(isEmail ? 'auth-page--email' : 'auth-page--phone');
                }
                panelEmail.hidden = !isEmail;
                panelPhone.hidden = isEmail;
                tabEmail.setAttribute('aria-selected', isEmail ? 'true' : 'false');
                tabPhone.setAttribute('aria-selected', isEmail ? 'false' : 'true');
                tabEmail.tabIndex = isEmail ? 0 : -1;
                tabPhone.tabIndex = isEmail ? -1 : 0;
            }

            tabEmail.addEventListener('click', function () { showMode('email'); });
            tabPhone.addEventListener('click', function () { showMode('phone'); });
        })();

        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

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
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }
        });
    </script>
    <?php include __DIR__ . '/../includes/google_auth_scripts.php'; ?>
    <?php include __DIR__ . '/../includes/social_floating.php'; ?>
</body>

</html>