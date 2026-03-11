<?php
/**
 * Page principale des paramètres - Regroupe toutes les configurations
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Administration</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-cog"></i> Paramètres et Configurations</h2>
            <p class="section-subtitle">
                Configurez les différentes sections de votre site web
            </p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="parametres-grid">
            <!-- Bannière d'Accueil -->
            <div class="parametre-card">
                <div class="parametre-icon">
                    <i class="fas fa-home"></i>
                </div>
                <h3 class="parametre-title">Bannière d'Accueil</h3>
                <p class="parametre-description">
                    Personnalisez la bannière principale de votre page d'accueil : modifiez le titre, le texte
                    d'accroche et l'image de fond pour créer une première impression mémorable.
                </p>
                <a href="parametres/section4.php" class="parametre-link">
                    <i class="fas fa-edit"></i> Modifier la bannière
                </a>
            </div>

            <!-- Section Tendance -->
            <div class="parametre-card">
                <div class="parametre-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="parametre-title">Section Mise en Avant</h3>
                <p class="parametre-description">
                    Configurez la section de mise en avant des produits : définissez le label, le titre promotionnel, le
                    texte du bouton d'action et l'image illustrative.
                </p>
                <a href="parametres/trending.php" class="parametre-link">
                    <i class="fas fa-edit"></i> Modifier la section
                </a>
            </div>

            <!-- Carrousel Principal -->
            <div class="parametre-card">
                <div class="parametre-icon">
                    <i class="fas fa-sliders-h"></i>
                </div>
                <h3 class="parametre-title">Slider Principal</h3>
                <p class="parametre-description">
                    Gérez le slider d'images en haut de la page d'accueil : ajoutez, modifiez ou supprimez les slides
                    avec leurs titres, textes et boutons d'action.
                </p>
                <a href="slider/index.php" class="parametre-link">
                    <i class="fas fa-edit"></i> Gérer le slider
                </a>
            </div>

            <!-- Section Vidéos -->
            <div class="parametre-card">
                <div class="parametre-icon">
                    <i class="fas fa-video"></i>
                </div>
                <h3 class="parametre-title">Section Vidéos</h3>
                <p class="parametre-description">
                    Gérez les vidéos du carrousel "Ils ont découvert ICON" : ajoutez, modifiez ou supprimez des vidéos
                    YouTube, Vimeo ou locales avec leurs images de prévisualisation.
                </p>
                <a href="parametres/videos.php" class="parametre-link">
                    <i class="fas fa-edit"></i> Gérer les vidéos
                </a>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
</body>

</html>