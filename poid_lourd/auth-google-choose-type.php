<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Choix du type de compte après authentification Google/Apple (nouveau compte).
 */
session_start_persistent();

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/firebase_auth_flow.php';
require_once __DIR__ . '/includes/flash_toast.php';

$pending = firebase_auth_get_pending();

if (!$pending || empty($pending['uid']) || empty($pending['email'])) {
    firebase_auth_redirect_safe('/choix-connexion.php');
}

$provider_label = firebase_auth_pending_provider_label($pending);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = isset($_POST['account_type']) ? trim((string) $_POST['account_type']) : '';
    if ($type !== 'client' && $type !== 'vendor') {
        $errors[] = 'Veuillez choisir un type de compte.';
    } else {
        $_SESSION['firebase_auth_pending']['type'] = $type;
        $_SESSION['google_auth_pending']['type'] = $type;
        firebase_auth_redirect_safe('/auth-google-complete.php?type=' . urlencode($type));
    }
}

$email = (string) $pending['email'];
$name = trim((string) ($pending['name'] ?? ''));
$vq = asset_version_query();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choisir votre type de compte - COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="/css/auth-choix-inscription.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page auth-page--email page-choix-inscription page-google-choose-type">
    <header class="auth-header">
        <a class="logo" href="/index.php">
            <img src="/image/logo_market.png" alt="COLObanes">
        </a>
    </header>

    <div class="auth-layout auth-layout--wide">
        <main class="choix-shell">
            <header class="choix-head">
                <h1>Bienvenue sur COLObanes</h1>
                <p class="choix-lead">
                    Votre compte <?php echo htmlspecialchars($provider_label, ENT_QUOTES, 'UTF-8'); ?>
                    <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php if ($name !== ''): ?>
                        (<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>)
                    <?php endif; ?>
                    n’est pas encore inscrit.
                </p>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="error-message google-choose-type__error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="auth-google-choose-type.php" class="google-choose-type__form" id="googleChooseTypeForm">
                <p class="google-choose-type__prompt">Choisissez le type de compte à créer.</p>
                <div class="choix-grid" role="group" aria-label="Types de compte">
                    <label class="choix-card choix-card--client google-choose-type__option">
                        <input type="radio" name="account_type" value="client" required>
                        <div class="choix-card__header">
                            <div class="choix-card__icon-wrap">
                                <i class="fas fa-bag-shopping" aria-hidden="true"></i>
                                <span class="choix-card__badge"><i class="fas fa-user"></i></span>
                            </div>
                            <div class="choix-card__info">
                                <span class="choix-card__label">Acheteur</span>
                                <h2 class="choix-card__title">Compte Client</h2>
                            </div>
                        </div>
                        <div class="choix-card__footer">
                            <div class="choix-card__cta">
                                <span><i class="fas fa-user-plus"></i> Acheter sur la marketplace</span>
                                <i class="fas fa-arrow-right arrow" aria-hidden="true"></i>
                            </div>
                        </div>
                    </label>

                    <label class="choix-card choix-card--vendor google-choose-type__option">
                        <input type="radio" name="account_type" value="vendor" required>
                        <div class="choix-card__header">
                            <div class="choix-card__icon-wrap">
                                <i class="fas fa-store" aria-hidden="true"></i>
                                <span class="choix-card__badge"><i class="fas fa-briefcase"></i></span>
                            </div>
                            <div class="choix-card__info">
                                <span class="choix-card__label">Marchand</span>
                                <h2 class="choix-card__title">Compte Vendeur</h2>
                            </div>
                        </div>
                        <div class="choix-card__footer">
                            <div class="choix-card__cta">
                                <span><i class="fas fa-store"></i> Ouvrir ma boutique</span>
                                <i class="fas fa-arrow-right arrow" aria-hidden="true"></i>
                            </div>
                        </div>
                    </label>
                </div>

                <button type="submit" class="btn-submit google-choose-type__submit">
                    <i class="fas fa-arrow-right"></i> Continuer
                </button>
            </form>

            <footer class="choix-foot">
                <a href="/choix-connexion.php" class="choix-foot__link">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span>Retour à la connexion</span>
                </a>
            </footer>
        </main>
    </div>

    <script>
        (function () {
            var form = document.getElementById('googleChooseTypeForm');
            if (!form) return;

            var options = form.querySelectorAll('.google-choose-type__option');
            var radios = form.querySelectorAll('input[name="account_type"]');

            function syncSelection() {
                var hasSelection = false;
                options.forEach(function (option) {
                    var radio = option.querySelector('input[type="radio"]');
                    var selected = radio && radio.checked;
                    option.classList.toggle('is-selected', !!selected);
                    if (selected) hasSelection = true;
                });
                form.classList.toggle('is-selection-made', hasSelection);
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', syncSelection);
            });

            options.forEach(function (option) {
                option.addEventListener('click', function () {
                    window.setTimeout(syncSelection, 0);
                });
            });

            syncSelection();
        })();
    </script>
</body>
</html>
