<?php
session_start();

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/models/model_visites.php';
require_once __DIR__ . '/controllers/controller_panier.php';

// Récupérer l'ID du produit depuis l'URL ou POST
$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produit_id'])) {
    $produit_id = (int) $_POST['produit_id'];
}

// Traitement de l'ajout au panier
$message = '';
$message_type = '';

// Vérifier si c'est une redirection après ajout au panier (pattern Post-Redirect-Get)
if (isset($_GET['added']) && ($_GET['added'] === 'success' || $_GET['added'] === '1')) {
    $message = 'Produit ajouté au panier avec succès.';
    $message_type = 'success';
}
if (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $message_type = 'error';
}

// Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_panier') {
    $result = process_add_to_panier();

    // Redirection vers le panier après ajout réussi
    if ($result['success']) {
        header('Location: /panier.php?added=1');
        exit;
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// Récupérer les informations du produit
$produit = $produit_id > 0 ? get_produit_by_id($produit_id) : false;

// Si le produit n'existe pas, rediriger vers l'accueil
if (!$produit || $produit['statut'] != 'actif') {
    header('Location: index.php');
    exit;
}

// Enregistrer la visite si l'utilisateur est connecté
if (isset($_SESSION['user_id']) && $produit_id > 0) {
    add_visite($_SESSION['user_id'], $produit_id);
}

// Calculer le prix à afficher (promotion si disponible)
$prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix_promotion']
    : $produit['prix'];
$prix_original = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix']
    : null;
$pourcentage_reduction = 0;
if ($prix_original) {
    $pourcentage_reduction = round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100);
}

// Récupérer les produits similaires (même catégorie)
$produits_similaires = get_produits_by_categorie($produit['categorie_id']);
// Exclure le produit actuel
$produits_similaires = array_filter($produits_similaires, function ($p) use ($produit_id) {
    return $p['id'] != $produit_id;
});
$produits_similaires = array_slice($produits_similaires, 0, 4); // Limiter à 4 produits

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
    <title><?php echo htmlspecialchars($produit['nom']); ?> - Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito&display=swap" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai&family=Rozha+One&family=Playfair+Display:wght@400;600;700&family=Quicksand:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="/css/owl.carousel.min.css">
    <link rel="stylesheet" href="/css/owl.carousel.css">
    <link rel="stylesheet" href="/css/animate.css">
    <link rel="stylesheet" href="/css/animate.min.css">
    <link rel="stylesheet" href="/css/a_style.css">
    <link rel="stylesheet" href="/css/product-cards.css">
    <style>
        /* Styles pour la page produit - Palette gourmande */
        body {
            background: transparent;
        }

        .produit-detail-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .produit-detail-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .produit-image-section {
            position: relative;
        }

        .produit-gallery-main {
            position: relative;
            margin-bottom: 15px;
        }

        .produit-gallery-thumbs {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }

        .gallery-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(229, 72, 138, 0.4);
            background: rgba(255, 255, 255, 0.95);
            color: var(--couleur-dominante);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .gallery-nav:hover {
            background: var(--couleur-dominante);
            color: #ffffff;
            border-color: var(--couleur-dominante);
        }

        .gallery-thumbs-list {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0;
            flex: 1;
            scroll-behavior: smooth;
        }

        .gallery-thumbs-list::-webkit-scrollbar {
            height: 4px;
        }

        .gallery-thumbs-list::-webkit-scrollbar-thumb {
            background: rgba(229, 72, 138, 0.4);
            border-radius: 4px;
        }

        .gallery-thumb {
            flex-shrink: 0;
            width: 70px;
            height: 70px;
            padding: 0;
            border: 3px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            background: #f8f8f8;
            transition: all 0.3s;
        }

        .gallery-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .gallery-thumb:hover {
            border-color: rgba(229, 72, 138, 0.5);
        }

        .gallery-thumb.active {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 2px rgba(229, 72, 138, 0.3);
        }

        .produit-image-main {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 16px;
            border: 2px solid rgba(229, 72, 138, 0.2);
            background: var(--beige-creme);
            box-shadow: 0 8px 24px rgba(229, 72, 138, 0.1);
        }

        .produit-info-section {
            display: flex;
            flex-direction: column;
        }

        .produit-nom {
            font-size: 24px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 10px;
            line-height: 1.3;
            font-family: var(--font-titres);
        }

        .produit-categorie {
            display: inline-block;
            font-size: 12px;
            color: var(--couleur-dominante);
            background: rgba(229, 72, 138, 0.12);
            padding: 6px 12px;
            border-radius: 20px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .produit-prix-section {
            margin-bottom: 15px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            border-left: 4px solid var(--couleur-dominante);
        }

        .prix-principal {
            font-size: 26px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 5px;
        }

        .prix-original {
            font-size: 18px;
            color: var(--texte-fonce);
            text-decoration: line-through;
            margin-right: 8px;
            opacity: 0.7;
        }

        .prix-promo {
            font-size: 22px;
            color: var(--accent-promo);
            font-weight: 600;
        }

        .promo-badge {
            display: inline-block;
            background: var(--accent-promo);
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 8px;
        }

        .produit-stock-info {
            margin-bottom: 15px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            border: 1px solid rgba(229, 72, 138, 0.15);
        }

        .stock-item {
            font-size: 13px;
            color: var(--texte-fonce);
            margin-bottom: 6px;
        }

        .stock-item strong {
            color: var(--couleur-dominante);
            font-weight: 600;
            min-width: 90px;
            display: inline-block;
        }

        .stock-value {
            color: var(--couleur-dominante);
            font-weight: 700;
        }

        .couleurs-swatches-display {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-left: 4px;
        }

        .couleur-swatch-display {
            display: inline-block;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
            cursor: default;
        }

        .produit-options-section {
            margin-bottom: 20px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            border: 1px solid rgba(229, 72, 138, 0.15);
        }

        .option-group {
            margin-bottom: 14px;
        }

        .option-group:last-child {
            margin-bottom: 0;
        }

        .option-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
        }

        .couleurs-swatches-select {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .couleur-swatch-select {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #f5f5f5;
            border-radius: 20px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }

        .couleur-swatch-select:hover {
            border-color: rgba(229, 72, 138, 0.5);
            background: #fff;
        }

        .couleur-swatch-select:has(input:checked) {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 2px rgba(229, 72, 138, 0.3);
            background: #fff;
        }

        .couleur-swatch-select input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .couleur-swatch-select .swatch-preview {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        }

        .couleur-swatch-select .swatch-text {
            font-size: 13px;
            color: var(--texte-fonce);
        }

        .option-select {
            padding: 10px 14px;
            border: 2px solid rgba(229, 72, 138, 0.3);
            border-radius: 8px;
            font-size: 14px;
            min-width: 140px;
            background: #fff;
            cursor: pointer;
        }

        .option-select:focus {
            outline: none;
            border-color: var(--couleur-dominante);
        }

        .option-value-display {
            font-size: 14px;
            color: var(--texte-fonce);
            font-weight: 500;
        }

        .produit-description {
            margin-bottom: 24px;
            padding: 24px;
            background: #ffffff;
            border-radius: 16px;
            line-height: 1.7;
            color: var(--texte-fonce);
            font-size: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(229, 72, 138, 0.12);
            border-left: 4px solid var(--couleur-dominante);
        }

        .produit-description h3 {
            font-size: 18px;
            color: var(--couleur-dominante);
            margin-bottom: 14px;
            font-weight: 600;
        }

        .produit-description p {
            margin: 0;
            color: #333;
        }

        .quantite-section {
            margin-bottom: 20px;
        }

        .quantite-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
            display: block;
        }

        .quantite-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .quantite-input-wrapper {
            display: flex;
            align-items: center;
            border: 2px solid rgba(229, 72, 138, 0.4);
            border-radius: 12px;
            overflow: hidden;
        }

        .quantite-btn {
            background: var(--couleur-dominante);
            color: #ffffff;
            border: none;
            width: 38px;
            height: 40px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantite-btn:hover {
            background: rgba(229, 72, 138, 0.9);
        }

        .quantite-input {
            width: 60px;
            height: 38px;
            border: none;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--titres);
        }

        .prix-total-section {
            padding: 18px;
            background: rgba(229, 72, 138, 0.85);
            color: #ffffff;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .prix-total-label {
            font-size: 13px;
            margin-bottom: 5px;
            opacity: 0.95;
        }

        .prix-total-value {
            font-size: 24px;
            font-weight: 700;
        }

        .btn-add-panier {
            width: 100%;
            padding: 14px 25px;
            background: var(--couleur-dominante);
            color: #ffffff;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(229, 72, 138, 0.3);
        }

        .btn-add-panier:hover {
            background: rgba(229, 72, 138, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(229, 72, 138, 0.4);
        }

        .btn-add-panier:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .message {
            padding: 15px 20px;
            padding-right: 45px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            position: relative;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message-close {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            color: inherit;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .message-close:hover {
            opacity: 1;
            background-color: rgba(0, 0, 0, 0.1);
        }

        .message.fade-out {
            animation: fadeOut 0.3s ease-out forwards;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-10px);
                max-height: 0;
                margin-bottom: 0;
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        .produits-similaires {
            margin-top: 60px;
        }

        .produits-similaires h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 30px;
            text-align: center;
            font-family: var(--font-titres);
        }

        .produit-connect-cta {
            padding: 24px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(229, 72, 138, 0.2);
        }

        .produit-connect-cta p {
            margin-bottom: 18px;
            color: var(--texte-fonce);
        }

        .btn-connect-produit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: var(--couleur-dominante);
            color: #ffffff;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(229, 72, 138, 0.3);
        }

        .btn-connect-produit:hover {
            background: rgba(229, 72, 138, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(229, 72, 138, 0.4);
            color: #ffffff;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .produit-detail-wrapper {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .produit-image-main {
                height: 300px;
            }

            .produit-nom {
                font-size: 20px;
            }

            .prix-principal {
                font-size: 22px;
            }

            .prix-total-value {
                font-size: 20px;
            }

            .produit-detail-container {
                margin: 15px auto;
                padding: 0 15px;
            }
        }
    </style>
</head>

<body>

    <?php include('nav_bar.php') ?>

    <div class="produit-detail-container">
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>" id="message-alert">
                <span><?php echo htmlspecialchars($message); ?></span>
                <button type="button" class="message-close" onclick="closeMessage()" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <div class="produit-detail-wrapper">
            <!-- Section Image avec galerie -->
            <div class="produit-image-section">
                <?php
                $galerie_images = [];
                if (!empty($produit['images'])) {
                    $dec = json_decode($produit['images'], true);
                    if (is_array($dec))
                        $galerie_images = $dec;
                }
                if (empty($galerie_images) && !empty($produit['image_principale'])) {
                    $galerie_images = [$produit['image_principale']];
                }
                ?>
                <div class="produit-gallery-main">
                    <img src="/upload/<?php echo htmlspecialchars($galerie_images[0] ?? $produit['image_principale']); ?>"
                        alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-image-main"
                        id="produit-image-main" onerror="this.src='/image/produit1.jpg'">
                </div>
                <?php if (count($galerie_images) > 1): ?>
                    <div class="produit-gallery-thumbs">
                        <button type="button" class="gallery-nav gallery-prev" aria-label="Image précédente">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="gallery-thumbs-list">
                            <?php foreach ($galerie_images as $idx => $img_path): ?>
                                <button type="button" class="gallery-thumb <?php echo $idx === 0 ? 'active' : ''; ?>"
                                    data-index="<?php echo $idx; ?>"
                                    data-src="/upload/<?php echo htmlspecialchars($img_path); ?>">
                                    <img src="/upload/<?php echo htmlspecialchars($img_path); ?>"
                                        alt="Vue <?php echo $idx + 1; ?>" onerror="this.src='/image/produit1.jpg'">
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="gallery-nav gallery-next" aria-label="Image suivante">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section Informations -->
            <div class="produit-info-section">
                <h1 class="produit-nom"><?php echo htmlspecialchars($produit['nom']); ?></h1>

                <!-- Prix -->
                <div class="produit-prix-section">
                    <?php if ($prix_original): ?>
                        <div class="prix-principal">
                            <span class="prix-original"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                FCFA</span>
                            <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                            <span class="promo-badge">-<?php echo $pourcentage_reduction; ?>%</span>
                        </div>
                    <?php else: ?>
                        <div class="prix-principal">
                            <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Stock, Poids, Couleurs, Taille -->
                <?php
                $couleurs_options = [];
                $poids_options = [];
                $taille_options = [];
                if (!empty($produit['couleurs'])) {
                    $cr = trim($produit['couleurs']);
                    $dec = json_decode($cr, true);
                    if (is_array($dec)) {
                        $couleurs_options = array_filter($dec, function ($c) {
                            return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
                        });
                    }
                    if (empty($couleurs_options)) {
                        $couleurs_options = array_map('trim', array_filter(explode(',', $cr)));
                    }
                }
                if (!empty($produit['poids'])) {
                    $poids_options = array_map('trim', array_filter(explode(',', $produit['poids'])));
                }
                if (!empty($produit['taille'])) {
                    $taille_options = array_map('trim', array_filter(explode(',', $produit['taille'])));
                }
                $has_selectable_options = !empty($couleurs_options) || !empty($poids_options) || !empty($taille_options);
                ?>


                <!-- Description -->
                <?php if (!empty($produit['description'])): ?>
                    <div class="produit-description">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($produit['description'])); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Sélection de quantité -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" action="" id="add-to-panier-form">
                        <input type="hidden" name="action" value="add_to_panier">
                        <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">

                        <?php if ($has_selectable_options): ?>
                            <div class="produit-options-section">
                                <div class="quantite-label" style="margin-bottom: 10px;"><i class="fas fa-palette"></i>
                                    Choisissez vos options</div>
                                <?php if (!empty($couleurs_options)): ?>
                                    <div class="option-group">
                                        <label class="option-label">Couleur</label>
                                        <?php if (count($couleurs_options) === 1): ?>
                                            <input type="hidden" name="option_couleur"
                                                value="<?php echo htmlspecialchars($couleurs_options[0]); ?>">
                                            <span class="couleurs-swatches-select">
                                                <?php $hex = $couleurs_options[0]; ?>
                                                <span class="couleur-swatch-select is-hex" style="opacity:0.9;">
                                                    <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)): ?>
                                                        <span class="swatch-preview"
                                                            style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                            title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                        <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                    <?php else: ?>
                                                        <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                        <?php else: ?>
                                            <span class="couleurs-swatches-select">
                                                <?php foreach ($couleurs_options as $hex): ?>
                                                    <label
                                                        class="couleur-swatch-select <?php echo preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? 'is-hex' : ''; ?>">
                                                        <input type="radio" name="option_couleur"
                                                            value="<?php echo htmlspecialchars($hex); ?>" class="option-radio-couleur"
                                                            required>
                                                        <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)): ?>
                                                            <span class="swatch-preview"
                                                                style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                                title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                        <?php else: ?>
                                                            <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                        <?php endif; ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($poids_options) && count($poids_options) > 1): ?>
                                    <div class="option-group">
                                        <label class="option-label" for="option-poids">Poids</label>
                                        <select name="option_poids" id="option-poids" class="option-select" required>
                                            <option value="">— Choisir un poids —</option>
                                            <?php foreach ($poids_options as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>">
                                                    <?php echo htmlspecialchars($opt); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php elseif (!empty($poids_options)): ?>
                                    <div class="option-group">
                                        <label class="option-label">Poids</label>
                                        <input type="hidden" name="option_poids"
                                            value="<?php echo htmlspecialchars($poids_options[0]); ?>">
                                        <span class="option-value-display"><?php echo htmlspecialchars($poids_options[0]); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($taille_options) && count($taille_options) > 1): ?>
                                    <div class="option-group">
                                        <label class="option-label" for="option-taille">Taille</label>
                                        <select name="option_taille" id="option-taille" class="option-select" required>
                                            <option value="">— Choisir une taille —</option>
                                            <?php foreach ($taille_options as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>">
                                                    <?php echo htmlspecialchars($opt); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php elseif (!empty($taille_options)): ?>
                                    <div class="option-group">
                                        <label class="option-label">Taille</label>
                                        <input type="hidden" name="option_taille"
                                            value="<?php echo htmlspecialchars($taille_options[0]); ?>">
                                        <span
                                            class="option-value-display"><?php echo htmlspecialchars($taille_options[0]); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="quantite-section">
                            <label class="quantite-label">Quantité:</label>
                            <div class="quantite-controls">
                                <div class="quantite-input-wrapper">
                                    <button type="button" class="quantite-btn" id="decrease-qty">-</button>
                                    <input type="number" name="quantite" id="quantite" class="quantite-input" value="1"
                                        min="1" max="<?php echo $produit['stock']; ?>" required>
                                    <button type="button" class="quantite-btn" id="increase-qty">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- Prix total calculé -->
                        <div class="prix-total-section">
                            <div class="prix-total-label">Prix total:</div>
                            <div class="prix-total-value" id="prix-total">
                                <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                            </div>
                        </div>

                        <button type="submit" class="btn-add-panier" id="btn-add-panier">
                            <i class="fa-solid fa-cart-shopping"></i>
                            Ajouter au panier
                        </button>
                    </form>
                <?php else: ?>
                    <div class="produit-connect-cta">
                        <p>Vous devez être connecté pour ajouter des produits au panier.</p>
                        <a href="/user/connexion.php" class="btn-connect-produit">
                            <i class="fa-solid fa-right-to-bracket"></i> Se connecter
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Produits similaires -->
        <?php if (!empty($produits_similaires)): ?>
            <div class="produits-similaires">
                <h2>Produits similaires</h2>
                <section class="produit_vedetes">
                    <article class="articles carousel11">
                        <?php foreach ($produits_similaires as $similaire): ?>
                            <?php
                            $prix_sim = !empty($similaire['prix_promotion']) && $similaire['prix_promotion'] < $similaire['prix']
                                ? $similaire['prix_promotion']
                                : $similaire['prix'];
                            ?>
                            <div class="carousel">
                                <a href="produit.php?id=<?php echo $similaire['id']; ?>" class="product-card-link">
                                    <div class="image-wrapper">
                                        <img src="/upload/<?php echo htmlspecialchars($similaire['image_principale']); ?>"
                                            alt="<?php echo htmlspecialchars($similaire['nom']); ?>"
                                            onerror="this.src='/image/produit1.jpg'">
                                    </div>
                                    <div class="produit-content">
                                        <p id="nom"><?php echo htmlspecialchars($similaire['nom']); ?></p>
                                        <p class="prix"><?php echo number_format($prix_sim, 0, ',', ' '); ?> <span
                                                class="span1">FCFA</span></p>
                                        <p id="ville"><?php echo htmlspecialchars($similaire['categorie_nom']); ?></p>
                                    </div>
                                </a>
                                <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                                    <input type="hidden" name="produit_id" value="<?php echo $similaire['id']; ?>">
                                    <input type="hidden" name="quantite" value="1">
                                    <input type="hidden" name="return_url"
                                        value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/produit.php'); ?>">
                                    <button type="submit" class="btn-add-cart">
                                        <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </article>
                </section>
            </div>
        <?php endif; ?>
    </div>

    <?php include('footer.php') ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        // Calcul automatique du prix total
        const prixUnitaire = <?php echo $prix_affichage; ?>;
        const quantiteInput = document.getElementById('quantite');
        const prixTotalElement = document.getElementById('prix-total');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');
        const maxStock = <?php echo $produit['stock']; ?>;

        function updatePrixTotal() {
            const quantite = parseInt(quantiteInput.value) || 1;
            const prixTotal = prixUnitaire * quantite;
            prixTotalElement.textContent = prixTotal.toLocaleString('fr-FR') + ' FCFA';

            // Désactiver le bouton si stock insuffisant
            const btnAdd = document.getElementById('btn-add-panier');
            if (quantite > maxStock || quantite <= 0) {
                btnAdd.disabled = true;
            } else {
                btnAdd.disabled = false;
            }
        }

        var galleryThumbs = document.querySelectorAll('.gallery-thumb');
        var galleryMain = document.getElementById('produit-image-main');
        var galleryPrev = document.querySelector('.gallery-prev');
        var galleryNext = document.querySelector('.gallery-next');
        var galleryList = document.querySelector('.gallery-thumbs-list');
        if (galleryThumbs.length > 0 && galleryMain) {
            var currentIdx = 0;

            function setActiveThumb(idx) {
                galleryThumbs.forEach(function (t, i) {
                    t.classList.toggle('active', i === idx);
                });
                currentIdx = idx;
                var src = galleryThumbs[idx].getAttribute('data-src');
                if (src) galleryMain.src = src;
            }
            galleryThumbs.forEach(function (thumb, idx) {
                thumb.addEventListener('click', function () {
                    setActiveThumb(idx);
                });
            });
            if (galleryPrev) galleryPrev.addEventListener('click', function () {
                currentIdx = (currentIdx - 1 + galleryThumbs.length) % galleryThumbs.length;
                setActiveThumb(currentIdx);
                if (galleryList) galleryList.scrollLeft = galleryThumbs[currentIdx].offsetLeft - galleryList
                    .offsetWidth / 2 + 35;
            });
            if (galleryNext) galleryNext.addEventListener('click', function () {
                currentIdx = (currentIdx + 1) % galleryThumbs.length;
                setActiveThumb(currentIdx);
                if (galleryList) galleryList.scrollLeft = galleryThumbs[currentIdx].offsetLeft - galleryList
                    .offsetWidth / 2 + 35;
            });
        }

        if (quantiteInput) {
            quantiteInput.addEventListener('input', updatePrixTotal);
            quantiteInput.addEventListener('change', function () {
                let value = parseInt(this.value) || 1;
                if (value < 1) value = 1;
                if (value > maxStock) value = maxStock;
                this.value = value;
                updatePrixTotal();
            });
        }

        if (decreaseBtn) {
            decreaseBtn.addEventListener('click', function () {
                let value = parseInt(quantiteInput.value) || 1;
                if (value > 1) {
                    value--;
                    quantiteInput.value = value;
                    updatePrixTotal();
                }
            });
        }

        if (increaseBtn) {
            increaseBtn.addEventListener('click', function () {
                let value = parseInt(quantiteInput.value) || 1;
                if (value < maxStock) {
                    value++;
                    quantiteInput.value = value;
                    updatePrixTotal();
                }
            });
        }

        // Initialiser le prix total au chargement
        if (quantiteInput) {
            updatePrixTotal();
        }

        // Gestion du message de succès/erreur
        function closeMessage() {
            const message = document.getElementById('message-alert');
            if (message) {
                message.classList.add('fade-out');
                setTimeout(() => {
                    message.style.display = 'none';
                    // Supprimer le paramètre ?added=success de l'URL
                    if (window.location.search.includes('added=success')) {
                        const url = new URL(window.location);
                        url.searchParams.delete('added');
                        window.history.replaceState({}, '', url);
                    }
                }, 300);
            }
        }

        // Fermer automatiquement après 3 secondes si c'est un message de succès
        document.addEventListener('DOMContentLoaded', function () {
            const message = document.getElementById('message-alert');
            if (message && message.classList.contains('success')) {
                setTimeout(() => {
                    closeMessage();
                }, 3000);
            }
        });
    </script>

</body>

</html>