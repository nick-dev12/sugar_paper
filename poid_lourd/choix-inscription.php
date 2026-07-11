<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Sélection du type de compte à créer (client, vendeur) — Design Premium
 */
session_start_persistent();

require_once __DIR__ . '/includes/auth_redirect.php';
auth_redirect_vendeur_to_dashboard();

$redirect = isset($_GET['redirect']) ? trim((string) $_GET['redirect']) : '';
$safe_redirect = preg_match('/^[a-z0-9_-]+$/i', $redirect) ? $redirect : '';
$q = $safe_redirect !== '' ? ('?' . http_build_query(['redirect' => $safe_redirect])) : '';

require_once __DIR__ . '/includes/asset_version.php';
$vq = asset_version_query();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte — COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="/css/auth-choix-inscription.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body class="auth-page auth-page--email page-choix-inscription">
    <!-- Particules décoratives -->
    <div class="choix-particles" aria-hidden="true"></div>

    <header class="auth-header">
        <a class="logo" href="/index.php">
            <img src="/image/logo_market.png" alt="COLObanes">
        </a>
    </header>

    <div class="auth-layout auth-layout--wide">
        <main class="choix-shell">
            <header class="choix-head">
                <h1>Rejoignez COLObanes</h1>
                <p class="choix-lead">Choisissez votre profil et commencez votre aventure sur notre marketplace</p>
            </header>

            <div class="choix-grid" role="navigation" aria-label="Types de compte">
                <!-- Carte Client -->
                <a class="choix-card choix-card--client"
                    href="/user/inscription.php<?php echo $safe_redirect !== '' ? htmlspecialchars($q) : ''; ?>">
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
                            <span><i class="fas fa-user-plus"></i> Créer mon compte</span>
                            <i class="fas fa-arrow-right arrow" aria-hidden="true"></i>
                        </div>
                    </div>
                </a>

                <!-- Carte Vendeur -->
                <a class="choix-card choix-card--vendor" href="/admin/inscription-vendeur.php">
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
                </a>
            </div>

            <!-- Connexion existante -->
            <div class="choix-row-secondary">
                <a class="choix-existing"
                    href="/choix-connexion.php<?php echo $safe_redirect !== '' ? htmlspecialchars($q) : ''; ?>">
                    <div class="choix-existing__icon">
                        <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
                    </div>
                    <div class="choix-existing__content">
                        <h3 class="choix-existing__title">Déjà membre ?</h3>
                        <p class="choix-existing__desc">Connectez-vous à votre espace personnel</p>
                    </div>
                    <i class="fas fa-chevron-right choix-existing__arrow" aria-hidden="true"></i>
                </a>
            </div>

            <footer class="choix-foot">
                <a href="/index.php" class="choix-foot__link">
                    <i class="fas fa-house" aria-hidden="true"></i>
                    <span>Retour au site</span>
                </a>
            </footer>
        </main>
    </div>

    <?php include __DIR__ . '/includes/social_floating.php'; ?>
</body>
</html>
