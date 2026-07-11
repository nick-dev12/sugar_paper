<?php
/**
 * Hub Paramètres — Super administrateur
 */
require_once __DIR__ . '/../includes/require_login.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-param-hub-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell">
        <header class="sa-param-hero" aria-labelledby="sa-param-title">
            <div class="sa-param-hero__grid">
                <div>
                    <nav class="sa-param-breadcrumb" aria-label="Fil d’Ariane">
                        <ol>
                            <li><a href="../dashboard.php">Tableau de bord</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li aria-current="page">Paramètres</li>
                        </ol>
                    </nav>
                    <p class="sa-param-hero__eyebrow">
                        <i class="fas fa-sliders" aria-hidden="true"></i> Configuration plateforme
                    </p>
                    <h1 class="sa-param-hero__title" id="sa-param-title">
                        Paramètres
                        <span class="sa-param-hero__badge">Hub</span>
                    </h1>
                </div>
                <div class="sa-param-hero__stamp" aria-hidden="true">
                    <div class="sa-param-hero__stamp-box">
                        <i class="fas fa-gears"></i>
                    </div>
                </div>
            </div>
        </header>

        <section class="sa-param-section" aria-labelledby="sa-param-section-title">
            <h2 class="sa-param-section__title" id="sa-param-section-title">
                <i class="fas fa-bolt" aria-hidden="true"></i>
                Accès aux sections
            </h2>
        </section>

        <div class="sa-param-cards" role="navigation" aria-label="Sections paramètres">
            <a class="sa-param-card" href="categories-catalogue.php">
                <div class="sa-param-card__top">
                    <span class="sa-param-card__icon" aria-hidden="true"><i class="fas fa-sitemap"></i></span>
                    <span class="sa-param-card__tag">Catalogue</span>
                </div>
                <h3 class="sa-param-card__title">Rayons (menu)</h3>
                <div class="sa-param-card__footer">
                    <span>Ouvrir l’espace <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </div>
            </a>

            <a class="sa-param-card" href="genres-catalogue.php">
                <div class="sa-param-card__top">
                    <span class="sa-param-card__icon" aria-hidden="true"><i class="fas fa-tags"></i></span>
                    <span class="sa-param-card__tag">Catalogue</span>
                </div>
                <h3 class="sa-param-card__title">Genres produits</h3>
                <div class="sa-param-card__footer">
                    <span>Ouvrir l’espace <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </div>
            </a>

            <a class="sa-param-card" href="sous-categories-catalogue.php">
                <div class="sa-param-card__top">
                    <span class="sa-param-card__icon" aria-hidden="true"><i class="fas fa-sitemap"></i></span>
                    <span class="sa-param-card__tag">Catalogue</span>
                </div>
                <h3 class="sa-param-card__title">Sous-catégories</h3>
                <div class="sa-param-card__footer">
                    <span>Ouvrir l’espace <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </div>
            </a>

            <a class="sa-param-card" href="boutique-types.php">
                <div class="sa-param-card__top">
                    <span class="sa-param-card__icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                    <span class="sa-param-card__tag">Vendeurs</span>
                </div>
                <h3 class="sa-param-card__title">Types de boutique</h3>
                <div class="sa-param-card__footer">
                    <span>Ouvrir l'espace <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </div>
            </a>

            <a class="sa-param-card" href="rappels-vendeur.php">
                <div class="sa-param-card__top">
                    <span class="sa-param-card__icon" aria-hidden="true"><i class="fas fa-bell"></i></span>
                    <span class="sa-param-card__tag">Vendeurs</span>
                </div>
                <h3 class="sa-param-card__title">Rappels vendeur</h3>
                <div class="sa-param-card__footer">
                    <span>Ouvrir l'espace <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </div>
            </a>

            <a class="sa-param-card" href="app-mobile.php">
                <div class="sa-param-card__top">
                    <span class="sa-param-card__icon" aria-hidden="true"><i class="fas fa-mobile-screen"></i></span>
                    <span class="sa-param-card__tag">Mobile</span>
                </div>
                <h3 class="sa-param-card__title">Mise à jour app</h3>
                <div class="sa-param-card__footer">
                    <span>Ouvrir l'espace <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </div>
            </a>

            <a class="sa-param-card sa-param-card--accent-orange" href="hero-affiches.php">
                <div class="sa-param-card__top">
                    <span class="sa-param-card__icon" aria-hidden="true"><i class="fas fa-panorama"></i></span>
                    <span class="sa-param-card__tag">Accueil</span>
                </div>
                <h3 class="sa-param-card__title">Hero &amp; bannières</h3>
                <div class="sa-param-card__footer">
                    <span>Ouvrir l’espace <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </div>
            </a>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>
