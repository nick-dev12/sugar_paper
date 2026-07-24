<?php
/**
 * Page de connexion utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/google_auth_coop.php';

// Redirection après connexion (page demandée ou index)
$redirect_after = isset($_POST['redirect']) ? trim($_POST['redirect']) : (isset($_GET['redirect']) ? trim($_GET['redirect']) : '');
if ($redirect_after && $redirect_after[0] !== '/') {
    $redirect_after = '/' . $redirect_after;
}
$redirect_url = (!empty($redirect_after) && strpos($redirect_after, '//') === false) ? $redirect_after : '/index.php';

// Si l'admin est déjà connecté, rediriger vers l'espace admin
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_email'])) {
    header('Location: /admin/dashboard.php');
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
    $_SESSION['admin_email'] = $result['admin']['email'];
    $_SESSION['admin_statut'] = $result['admin']['statut'];
    $_SESSION['admin_role'] = $result['admin']['role'] ?? 'admin';

    // Redirection vers l'espace admin. Si l'admin utilise "retour", connexion.php le redirigera à nouveau.
    header('Location: /admin/dashboard.php');
    exit;
}

// Connexion utilisateur : session + redirection
if (isset($result['success']) && $result['success'] && $result['type'] === 'user' && $result['user']) {
    session_regenerate_persistent();
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user_nom'] = $result['user']['nom'];
    $_SESSION['user_prenom'] = $result['user']['prenom'];
    $_SESSION['user_email'] = $result['user']['email'];
    $_SESSION['user_telephone'] = $result['user']['telephone'];
    $_SESSION['user_statut'] = $result['user']['statut'];
    $_SESSION['fcm_resync_user'] = 1;

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

$active_login_mode = (isset($_POST['login_mode']) && (string) $_POST['login_mode'] === 'email') ? 'email' : 'phone';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Connexion - Sugar Paper</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-social.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-pages.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-corps);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        /* Fond dégradé flouté harmonieux - même que le site */
        body::before {
            content: "";
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse 80% 50% at 30% 20%, rgba(229, 72, 138, 0.4) 0%, transparent 50%),
                radial-gradient(ellipse 60% 40% at 70% 10%, rgba(244, 211, 94, 0.35) 0%, transparent 45%),
                radial-gradient(ellipse 70% 50% at 50% 80%, rgba(32, 197, 199, 0.3) 0%, transparent 50%),
                radial-gradient(ellipse 50% 60% at 10% 70%, rgba(255, 255, 255, 0.95) 0%, transparent 45%),
                radial-gradient(ellipse 60% 50% at 80% 60%, rgba(247, 127, 0, 0.25) 0%, transparent 45%),
                linear-gradient(135deg, #ffffff 0%, rgba(229, 72, 138, 0.15) 50%, rgba(32, 197, 199, 0.1) 100%);
            filter: blur(60px);
            pointer-events: none;
            z-index: -1;
        }

        .auth-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 12px 30px;
            background: #ffffff;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            z-index: 100;
        }

        .auth-header .logo {
            display: inline-block;
        }

        .auth-header .logo img {
            height: 55px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
        }

        .auth-header .logo:hover {
            opacity: 0.9;
        }

        .auth-content {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding-top: 80px;
        }

        .container {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--couleur-dominante);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header .icon {
            width: 70px;
            height: 70px;
            background: var(--couleur-dominante);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--texte-clair);
            font-size: 30px;
        }

        .header h1 {
            color: var(--titres);
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
            font-family: var(--font-titres);
        }

        .header p {
            color: var(--texte-fonce);
            font-size: 14px;
            opacity: 0.85;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: var(--titres);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(229, 72, 138, 0.2);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            color: var(--texte-fonce);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 3px rgba(229, 72, 138, 0.15);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--couleur-dominante);
            font-size: 16px;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--couleur-dominante);
            font-size: 16px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--titres);
        }

        .input-wrapper.password-wrapper {
            position: relative;
        }

        .input-wrapper.password-wrapper input {
            padding-right: 45px;
        }

        .input-wrapper.password-wrapper .password-toggle {
            right: 15px;
        }

        .error-message {
            background: rgba(229, 72, 138, 0.1);
            border-left: 4px solid var(--couleur-dominante);
            color: var(--titres);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .success-message {
            background: rgba(32, 197, 199, 0.12);
            border-left: 4px solid var(--turquoise);
            color: var(--titres);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: var(--ombre-douce);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            background: rgba(229, 72, 138, 0.9);
        }

        .footer-text {
            text-align: center;
            margin-top: 25px;
            color: var(--texte-fonce);
            font-size: 14px;
            opacity: 0.85;
        }

        .footer-text a {
            color: var(--couleur-dominante);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
            margin-top: 3px;
            cursor: pointer;
            accent-color: var(--couleur-dominante);
        }

        .checkbox-group label {
            font-weight: normal;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.5;
            color: var(--texte-fonce);
        }

        .checkbox-group label a {
            color: var(--couleur-dominante);
            text-decoration: underline;
        }

        .checkbox-group label a:hover {
            color: var(--titres);
        }

        .forgot-password-link {
            margin-top: 8px;
            text-align: right;
        }

        .forgot-password-link a {
            color: var(--couleur-dominante);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .forgot-password-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body class="auth-page auth-page--<?php echo $active_login_mode === 'phone' ? 'phone' : 'email'; ?>">
    <header class="auth-header">
        <a class="logo" href="/index.php">
            <img src="/image/sugar_paper.jpg" alt="Sugar Paper">
        </a>
    </header>

    <div class="auth-content">
        <div class="container">
            <div class="header">
                <div class="icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <h1>Connexion</h1>
                <p>Accédez à votre compte</p>
            </div>

            <?php if (!empty($inscription_success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($inscription_success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
                </div>
            <?php endif; ?>

            <?php
            $google_auth_type = 'auto';
            $google_auth_redirect = $redirect_url;
            $google_auth_position = 'top';
            $google_auth_label = 'Connexion avec Google';
            include __DIR__ . '/../includes/google_auth_button.php';
            ?>

            <div class="login-mode-tabs" role="tablist" aria-label="Mode de connexion">
                <button type="button" role="tab" id="tab-phone" aria-controls="panel-phone"
                    aria-selected="<?php echo $active_login_mode === 'phone' ? 'true' : 'false'; ?>"
                    tabindex="<?php echo $active_login_mode === 'phone' ? '0' : '-1'; ?>">
                    <i class="fas fa-phone" aria-hidden="true"></i>
                    <span class="tab-label-long">Téléphone</span>
                </button>
                <button type="button" role="tab" id="tab-email" aria-controls="panel-email"
                    aria-selected="<?php echo $active_login_mode === 'email' ? 'true' : 'false'; ?>"
                    tabindex="<?php echo $active_login_mode === 'email' ? '0' : '-1'; ?>">
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    <span class="tab-label-long">Email</span>
                </button>
            </div>

            <div id="panel-phone" class="login-panel" role="tabpanel" aria-labelledby="tab-phone"
                <?php echo $active_login_mode !== 'phone' ? 'hidden' : ''; ?>>
            <form method="POST" action="" id="loginFormPhone">
                <input type="hidden" name="login_mode" value="phone">
                <?php if (!empty($redirect_after)): ?>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_after); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="telephone"><i class="fas fa-phone"></i> Téléphone *</label>
                    <div class="input-wrapper input-wrapper--intl-tel">
                        <input type="tel" id="telephone" name="telephone" placeholder="77 123 45 67" autocomplete="tel"
                            value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="pin"><i class="fas fa-lock"></i> Mot de passe *</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" id="pin" name="pin" placeholder="Votre mot de passe ou code PIN"
                            autocomplete="current-password"
                            value="<?php echo isset($_POST['pin']) ? htmlspecialchars($_POST['pin']) : ''; ?>">
                        <button type="button" class="password-toggle" onclick="togglePassword('pin', this)" aria-label="Afficher le mot de passe">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="accepte_conditions_phone" name="accepte_conditions_phone" value="1" required
                        <?php echo (isset($_POST['accepte_conditions_phone']) && $_POST['accepte_conditions_phone'] === '1') ? 'checked' : ''; ?>>
                    <label for="accepte_conditions_phone">
                        J'accepte les <a href="/conditions-utilisation.php" target="_blank">conditions d'utilisation</a> *
                    </label>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>
            </div>

            <div id="panel-email" class="login-panel" role="tabpanel" aria-labelledby="tab-email"
                <?php echo $active_login_mode !== 'email' ? 'hidden' : ''; ?>>
            <form method="POST" action="" id="loginFormEmail">
                <input type="hidden" name="login_mode" value="email">
                <?php if (!empty($redirect_after)): ?>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_after); ?>">
                <?php endif; ?>
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
                        <input type="password" id="password" name="password" placeholder="Votre mot de passe"
                            autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="forgot-password-link">
                        <a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="accepte_conditions" name="accepte_conditions" value="1" required>
                    <label for="accepte_conditions">
                        J'accepte les <a href="/conditions-utilisation.php" target="_blank">conditions d'utilisation</a> *
                    </label>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>
            </div>

            <div class="footer-text">
                <p>Vous n'avez pas de compte ? <a href="inscription.php">Créer un compte</a></p>
            </div>
        </div>
    </div>

    <script>
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

        (function () {
            var tabEmail = document.getElementById('tab-email');
            var tabPhone = document.getElementById('tab-phone');
            var panelEmail = document.getElementById('panel-email');
            var panelPhone = document.getElementById('panel-phone');
            if (!tabEmail || !tabPhone || !panelEmail || !panelPhone) return;

            function showMode(mode) {
                var isPhone = mode === 'phone';
                var root = document.querySelector('.auth-page');
                if (root) {
                    root.classList.remove('auth-page--email', 'auth-page--phone');
                    root.classList.add(isPhone ? 'auth-page--phone' : 'auth-page--email');
                }
                panelPhone.hidden = !isPhone;
                panelEmail.hidden = isPhone;
                tabPhone.setAttribute('aria-selected', isPhone ? 'true' : 'false');
                tabEmail.setAttribute('aria-selected', isPhone ? 'false' : 'true');
                tabPhone.tabIndex = isPhone ? 0 : -1;
                tabEmail.tabIndex = isPhone ? -1 : 0;
            }

            tabPhone.addEventListener('click', function () { showMode('phone'); });
            tabEmail.addEventListener('click', function () { showMode('email'); });
        })();

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }
        });
    </script>
    <?php include __DIR__ . '/../includes/auth_intl_tel_scripts.php'; ?>
    <?php include __DIR__ . '/../includes/google_auth_scripts.php'; ?>
    <?php include __DIR__ . '/../includes/social_floating.php'; ?>
</body>

</html>