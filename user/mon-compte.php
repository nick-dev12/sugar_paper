<?php
/**
 * Page tableau de bord utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/produit_share.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit;
}

// Récupérer les informations de l'utilisateur
require_once __DIR__ . '/../models/model_users.php';
$user = get_user_by_id($_SESSION['user_id']);

if (!$user) {
    session_destroy();
    header('Location: connexion.php');
    exit;
}

// Récupérer uniquement les produits des commandes livrées
require_once __DIR__ . '/../models/model_commandes.php';
$produits_commandes = get_produits_commandes_by_user($_SESSION['user_id'], 'livree');

// Récupérer les statistiques
require_once __DIR__ . '/../models/model_favoris.php';
require_once __DIR__ . '/../models/model_visites.php';
$nb_commandes = count_commandes_by_user($_SESSION['user_id']);
$nb_panier = count_panier_items_by_user($_SESSION['user_id']);
$nb_favoris = count_favoris_by_user($_SESSION['user_id']);
$nb_visites = count_visites_by_user($_SESSION['user_id']);

$enable_firebase_notifications = true;
$firebase_notify_type = 'user';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Mon Compte - Sugar Paper</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mon-compte.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/platform_share_head.php'; ?>
</head>

<body class="user-page-mon-compte">
    <?php include 'includes/user_nav.php'; ?>

    <!-- Section orientation : continuer les achats -->
    <div class="continue-shopping-banner">
        <div class="continue-shopping-content">
            <div class="continue-shopping-icon">
                <i class="fas fa-shopping-basket"></i>
            </div>
            <div class="continue-shopping-text">
                <h2>Continuer mes achats</h2>
                <p>Découvrez nos produits naturels et complétez votre panier</p>
            </div>
            <a href="/index.php" class="continue-shopping-btn">
                <i class="fas fa-store"></i> Accueil - Voir les produits
            </a>
        </div>
    </div>

    <div class="content-header">
        <h1>
            <i class="fas fa-home"></i> Bienvenue, <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
        </h1>
        <div class="content-header-actions">
            <a href="/index.php" class="btn-voir-produits">
                <i class="fas fa-store"></i> Voir tous les produits
            </a>
        </div>
    </div>

    <div id="notify-help-panel" class="notify-help-panel" hidden aria-live="polite">
        <h4><i class="fas fa-circle-info"></i> Autoriser les notifications manuellement</h4>
        <p style="margin:0 0 10px;font-size:14px;color:#555;">
            Ouvrez le menu → <strong>Notifications</strong>, puis autorisez les alertes pour recevoir
            les mises à jour de commande sur ce navigateur (et dans l’application Sugar Paper).
        </p>
        <ol>
            <li>Cliquez sur le <strong>cadenas</strong> (à gauche de l'adresse)</li>
            <li><strong>Notifications</strong> → choisissez <strong>Autoriser</strong></li>
            <li>Cliquez sur le bouton ci-dessous</li>
        </ol>
        <button type="button" id="btn-notify-continue" class="btn-voir-produits notify-help-panel__btn">
            <i class="fas fa-check"></i> J'ai autorisé — continuer
        </button>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card stat-card--commandes">
            <div class="stat-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="stat-value"><?php echo $nb_commandes; ?></div>
            <div class="stat-label">Commandes</div>
        </div>
        <div class="stat-card stat-card--panier">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-value"><?php echo $nb_panier; ?></div>
            <div class="stat-label">Articles au panier</div>
        </div>
        <div class="stat-card stat-card--favoris">
            <div class="stat-icon">
                <i class="fas fa-heart"></i>
            </div>
            <div class="stat-value"><?php echo $nb_favoris; ?></div>
            <div class="stat-label">Favoris</div>
        </div>
        <div class="stat-card stat-card--visites">
            <div class="stat-icon">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-value"><?php echo $nb_visites; ?></div>
            <div class="stat-label">Produits visités</div>
        </div>
    </div>

    <!-- Section produits commandés -->
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-check-circle"></i> Mes Produits Livrés</h2>
        </div>

        <?php if (empty($produits_commandes)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>Aucun produit livré pour le moment.</p>
                <a href="mes-commandes.php" class="btn-primary">
                    <i class="fas fa-shopping-bag"></i> Voir mes commandes
                </a>
            </div>
        <?php else: ?>
            <div class="produits-grid">
                <?php foreach ($produits_commandes as $produit): ?>
                    <?php
                    $statut_class = 'statut-actif';
                    if ($produit['statut'] == 'inactif') {
                        $statut_class = 'statut-inactif';
                    } elseif ($produit['statut'] == 'rupture_stock') {
                        $statut_class = 'statut-rupture';
                    }
                    $statut_label = ucfirst(str_replace('_', ' ', $produit['statut']));
                    ?>
                    <div class="produit-card">
                        <?php echo produit_share_button_html($produit); ?>
                        <span class="statut-badge <?php echo $statut_class; ?>"><?php echo $statut_label; ?></span>
                        <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'sm')); ?>"
                            alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-card-image"
                            onerror="this.src='/image/produit1.jpg'">
                        <div class="produit-card-body">
                            <h3 class="produit-card-nom"><?php echo htmlspecialchars($produit['nom']); ?></h3>
                            <p class="produit-card-categorie">
                                <?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie'); ?>
                            </p>
                            <p class="produit-card-prix">
                                <?php echo number_format($produit['prix_unitaire'], 0, ',', ' '); ?>
                                <span class="prix-unite">FCFA</span>
                                <?php if ($produit['prix_promotion']): ?>
                                    <span class="prix-promo">
                                        (Promo: <?php echo number_format($produit['prix_promotion'], 0, ',', ' '); ?> FCFA)
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="produit-card-stock produit-card-quantite">
                                <strong>Quantité commandée:</strong> <?php echo $produit['quantite']; ?>
                                <?php if ($produit['poids']): ?>
                                    (<?php echo htmlspecialchars($produit['poids']); ?>)
                                <?php endif; ?>
                            </p>
                            <p class="produit-card-stock produit-card-commande">
                                <strong>Commande:</strong> <?php echo htmlspecialchars($produit['numero_commande']); ?>
                            </p>
                            <div class="produit-card-actions">
                                <a href="/produit.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-view">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                                <a href="mes-commandes.php" class="btn-card btn-favorite">
                                    <i class="fas fa-list"></i> Détails
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include 'includes/user_footer.php'; ?>
    <?php include __DIR__ . '/../includes/platform_share_footer.php'; ?>