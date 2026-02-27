<?php
session_start();

// Inclusion des modèles
require_once __DIR__ . '/models/model_categories.php';
require_once __DIR__ . '/models/model_produits.php';

// Récupérer l'ID de la catégorie depuis l'URL
$categorie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Réinitialiser la variable pour éviter tout problème de cache
unset($categorie);
$categorie = null;

// Récupérer les informations de la catégorie
if ($categorie_id > 0) {
    $categorie = get_categorie_by_id($categorie_id);
}

// Si la catégorie n'existe pas, rediriger vers l'accueil
if (!$categorie || !is_array($categorie) || empty($categorie['nom'])) {
    header('Location: index.php');
    exit;
}

// Forcer la récupération du nom de la catégorie depuis le tableau
$categorie_nom = isset($categorie['nom']) ? $categorie['nom'] : 'Catégorie';

// Récupérer tous les produits de cette catégorie
$produits = get_produits_by_categorie($categorie_id);
if ($produits === false) {
    $produits = [];
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title><?php echo htmlspecialchars($categorie_nom); ?> - Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Almarai&family=Rozha+One&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="/css/owl.carousel.min.css">
    <link rel="stylesheet" href="/css/owl.carousel.css">
    <link rel="stylesheet" href="/css/animate.css">
    <link rel="stylesheet" href="/css/animate.min.css">
    <link rel="stylesheet" href="/css/product-cards.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/a_style.css">
    <style>
    /* Styles personnalisés pour les cartes produits */
    </style>
</head>

<body>

    <?php include('nav_bar.php') ?>

    <section class="section00">
        <section class="produit_vedetes">
            <div class="box1">
                <h1><?php echo htmlspecialchars($categorie_nom); ?></h1>
            </div>

            <?php if (empty($produits)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                <p style="font-size: 16px;">Aucun produit publié pour le moment.</p>
                <a href="index.php"
                    style="display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #918a44; color: white; text-decoration: none; border-radius: 5px; transition: background 0.3s ease;">
                    <i class="fas fa-arrow-left"></i> Retour à l'accueil
                </a>
            </div>
            <?php else: ?>
            <article data-aos="fade-up" data-aos-delay="0" data-aos-duration="1000" data-aos-easing="ease-in-out"
                data-aos-mirror="true" data-aos-once="false" data-aos-anchor-placement="top-bottom"
                class="articles  carousel11">
                <?php foreach ($produits as $produit): ?>
                <?php
                        // Vérifier si promotion disponible
                        $has_promo = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                        $prix_principal = number_format($produit['prix'], 0, ',', ' ');
                        $prix_promo_value = $has_promo ? number_format($produit['prix_promotion'], 0, ',', ' ') : '';
                        $pourcentage_reduction = 0;
                        if ($has_promo) {
                            $pourcentage_reduction = round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100);
                        }
                        ?>
                <div class="carousel">
                    <div class="image-wrapper">
                        <img src="/upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
                            alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                            onerror="this.src='/image/produit1.jpg'">
                    </div>
                    <div class="produit-content">
                        <p id="nom"><?php echo htmlspecialchars($produit['nom']); ?></p>

                        <p class="prix">
                            <?php if ($has_promo): ?>
                            <span class="span2"><?php echo $prix_principal; ?> FCFA</span>
                            <span class="prix-promo"><?php echo $prix_promo_value; ?> FCFA</span>
                            <span class="span3">-<?php echo $pourcentage_reduction; ?>%</span>
                            <?php else: ?>
                            <?php echo $prix_principal; ?><span class="span1"> FCFA</span>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($produit['stock'])): ?>
                        <p class="produit-card-stock-info">
                            <strong>Stock:</strong> <?php echo $produit['stock']; ?>

                        </p>
                        <?php endif; ?>
                    </div>
                    <a href="produit.php?id=<?php echo $produit['id']; ?>">
                        <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                    </a>
                </div>
                <?php endforeach; ?>
            </article>
            <?php endif; ?>
        </section>
    </section>

    <?php include('footer.php') ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="/js/owl.carousel.min.js"></script>
    <script src="/js/owl.carousel.js"></script>
    <script src="/js/owl.animate.js"></script>
    <script src="/js/owl.autoplay.js"></script>

    <script>
    $(document).ready(function() {
        AOS.init();
    });
    </script>

    <script>
    // ..
    AOS.init();
    </script>

</body>

</html>