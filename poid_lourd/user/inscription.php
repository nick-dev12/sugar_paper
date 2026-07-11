<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Page d'inscription utilisateur
 * Programmation procédurale uniquement
 */

session_start_persistent();
require_once __DIR__ . '/../includes/google_auth_coop.php';
require_once __DIR__ . '/../includes/auth_redirect.php';

if (ob_get_level() === 0) {
    ob_start();
}

$inscription_redirect_get = isset($_GET['redirect']) ? trim((string) $_GET['redirect']) : '';
if ($inscription_redirect_get === '' || !preg_match('/^[a-z0-9_-]+$/i', $inscription_redirect_get)) {
    $inscription_redirect_get = '';
}

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
    $loc = '/choix-connexion.php';
    if ($inscription_redirect_get !== '') {
        $loc .= '?' . http_build_query(['redirect' => $inscription_redirect_get]);
    }
    auth_redirect_after_login($loc);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Inscription - COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="auth-page auth-page--email page-inscription-client">
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
                        <h1>Créer un compte</h1>
                    </div>

                    <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    $social_auth_type = 'client';
                    $social_auth_redirect = '/index.php';
                    $social_auth_position = 'top';
                    include __DIR__ . '/../includes/google_auth_button.php';
                    ?>

                    <form method="POST" action="" id="inscriptionForm" class="auth-inscription-form">
                        <div class="form-group">
                            <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                            <div class="input-wrapper">
                                <input type="text" id="nom" name="nom" placeholder="Votre nom" required autocomplete="family-name"
                                    value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                                <i class="fas fa-user" aria-hidden="true"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email <span class="form-optional">(facultatif)</span></label>
                            <div class="input-wrapper">
                                <input type="email" id="email" name="email" placeholder="votre@email.com" autocomplete="email"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <i class="fas fa-envelope" aria-hidden="true"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="telephone"><i class="fas fa-phone"></i> Téléphone *</label>
                            <div class="input-wrapper input-wrapper--intl-tel">
                                <input type="tel" id="telephone" name="telephone" placeholder="77 123 45 67" required autocomplete="tel"
                                    value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pin"><i class="fas fa-key"></i> Code PIN (6 chiffres) *</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                    placeholder="• • • • • •" required autocomplete="new-password" title="6 chiffres">
                                <button type="button" class="password-toggle" aria-label="Afficher le code PIN"
                                    onclick="togglePassword('pin', this)">
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
                                    onclick="togglePassword('pin_confirm', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-user-plus"></i> S'inscrire
                        </button>
                        <input type="hidden" name="insc_geo_lat" id="insc_geo_lat" value="">
                        <input type="hidden" name="insc_geo_lng" id="insc_geo_lng" value="">
                        <input type="hidden" name="insc_geo_precision" id="insc_geo_precision" value="">
                    </form>

                    <div class="auth-footer">
                        <p>Vous avez déjà un compte ?
                            <a href="/choix-connexion.php<?php
echo $inscription_redirect_get !== '' ? htmlspecialchars('?' . http_build_query(['redirect' => $inscription_redirect_get])) : '';
?>">Se connecter</a></p>
                    </div>
                </div>
            </div>
        </main>
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
    </script>
    <?php include __DIR__ . '/../includes/google_auth_scripts.php'; ?>
    <?php include __DIR__ . '/../includes/auth_intl_tel_scripts.php'; ?>
    <?php require_once __DIR__ . '/../includes/geo_native_bridge_script.php'; ?>
    <script src="/js/geo-address-format.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-inscription-location.js<?php echo asset_version_query(); ?>" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }
        });
    </script>
    <?php include __DIR__ . '/../includes/social_floating.php'; ?>
</body>

</html>
