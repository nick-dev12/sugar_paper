<?php
/**
 * Page d'inscription utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/google_auth_coop.php';

// Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord
if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
    header('Location: mon-compte.php');
    exit;
}

// Traiter le formulaire
require_once __DIR__ . '/../controllers/controller_users.php';
$result = process_user_inscription();

// Si l'inscription est réussie, rediriger vers la page de connexion
if (isset($result['success']) && $result['success']) {
    $_SESSION['inscription_success'] = $result['message'];
    header('Location: connexion.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Inscription - Sugar Paper</title>
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
            max-width: 500px;
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

        .form-group input::placeholder {
            color: rgba(61, 40, 0, 0.5);
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

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body class="auth-page">
    <header class="auth-header">
        <a class="logo" href="/index.php">
            <img src="/image/sugar_paper.jpg" alt="Sugar Paper">
        </a>
    </header>

    <div class="auth-content">
        <div class="container">
            <div class="header">
                <div class="icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>Créer un compte</h1>
                <p>Rejoignez Sugar Paper</p>
            </div>

            <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
                </div>
            <?php endif; ?>

            <?php
            $google_auth_type = 'client';
            $google_auth_redirect = '/index.php';
            $google_auth_position = 'top';
            $google_auth_label = 'Inscription avec Google';
            include __DIR__ . '/../includes/google_auth_button.php';
            ?>

            <form method="POST" action="" id="inscriptionForm">
                <div class="form-group">
                    <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                    <input type="text" id="nom" name="nom" placeholder="Votre nom" required
                        value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email <span class="label-optional">(facultatif)</span></label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="votre@email.com"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="telephone"><i class="fas fa-phone"></i> Téléphone *</label>
                    <div class="input-wrapper input-wrapper--intl-tel">
                        <input type="tel" id="telephone" name="telephone" placeholder="77 123 45 67" required
                            value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="pin"><i class="fas fa-key"></i> Code PIN (6 chiffres) *</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" id="pin" name="pin" class="pin-input" inputmode="numeric"
                            pattern="[0-9]*" maxlength="6" placeholder="••••••" required autocomplete="new-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('pin', this)" aria-label="Afficher le code PIN">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="pin_confirm"><i class="fas fa-key"></i> Confirmer le code PIN *</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" id="pin_confirm" name="pin_confirm" class="pin-input" inputmode="numeric"
                            pattern="[0-9]*" maxlength="6" placeholder="••••••" required autocomplete="new-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('pin_confirm', this)" aria-label="Afficher le code PIN">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-user-plus"></i> S'inscrire
                </button>
            </form>

            <div class="footer-text">
                <p>Vous avez déjà un compte ? <a href="connexion.php">Se connecter</a></p>
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

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.pin-input').forEach(function (input) {
                input.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 6);
                });
            });
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