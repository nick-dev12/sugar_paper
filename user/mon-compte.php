<?php
/**
 * Page tableau de bord utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/produit_share.php';
require_once __DIR__ . '/../includes/produit_personnalisation.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: connexion.php');
    exit;
}

require_once __DIR__ . '/../models/model_users.php';
$user = get_user_by_id($_SESSION['user_id']);

if (!$user) {
    session_destroy();
    header('Location: connexion.php');
    exit;
}

require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_livreur_tracking.php';
require_once __DIR__ . '/../models/model_favoris.php';
require_once __DIR__ . '/../models/model_visites.php';

$produits_commandes = get_produits_commandes_by_user($_SESSION['user_id'], 'livree');
$nb_commandes = count_commandes_by_user($_SESSION['user_id']);
$nb_panier = count_panier_items_by_user($_SESSION['user_id']);
$nb_favoris = count_favoris_by_user($_SESSION['user_id']);
$nb_visites = count_visites_by_user($_SESSION['user_id']);

$toutes_commandes = get_commandes_by_user($_SESSION['user_id']);
$dernieres_commandes = array_slice($toutes_commandes, 0, 2);
foreach ($dernieres_commandes as &$cmd_row) {
    $cmd_row['produits'] = get_commande_produits((int) $cmd_row['id']);
    $cmd_row['nb_articles'] = 0;
    foreach ($cmd_row['produits'] as $p) {
        $cmd_row['nb_articles'] += (int) ($p['quantite'] ?? 0);
    }
}
unset($cmd_row);

$display_name = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
if ($display_name === '') {
    $display_name = 'Client';
}
$initials = '';
$name_parts = preg_split('/\s+/', $display_name);
foreach (array_slice($name_parts, 0, 2) as $part) {
    if ($part !== '') {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
if ($initials === '') {
    $initials = 'SP';
}

$statut_labels = [
    'en_attente' => 'En attente',
    'confirmee' => 'Confirmée',
    'prise_en_charge' => 'Prise en charge',
    'en_preparation' => 'En préparation',
    'expediee' => 'Expédiée',
    'livraison_en_cours' => 'Livraison en cours',
    'livree' => 'Livrée',
    'paye' => 'Reçue',
    'annulee' => 'Annulée',
];

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mon-compte.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/platform_share_head.php'; ?>
</head>

<body class="user-page-mon-compte">
    <?php include 'includes/user_nav.php'; ?>

    <div class="mc-page">
        <!-- Hero -->
        <header class="mc-hero">
            <div class="mc-hero__identity">
                <div class="mc-hero__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                <div class="mc-hero__text">
                    <p class="mc-hero__eyebrow">Sugar Paper</p>
                    <h1 class="mc-hero__title">Bonjour, <?php echo htmlspecialchars($display_name); ?></h1>
                    <p class="mc-hero__subtitle">Retrouvez vos commandes, favoris et produits livrés.</p>
                </div>
            </div>
            <div class="mc-hero__actions">
                <a href="/index.php" class="mc-btn mc-btn--primary">
                    <i class="fas fa-store"></i>
                    <span>Continuer mes achats</span>
                </a>
                <a href="mes-commandes.php" class="mc-btn mc-btn--ghost">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Mes commandes</span>
                </a>
            </div>
        </header>

        <div id="notify-help-panel" class="notify-help-panel" hidden aria-live="polite">
            <h4><i class="fas fa-circle-info"></i> Autoriser les notifications manuellement</h4>
            <p>
                Ouvrez le menu → <strong>Notifications</strong>, puis autorisez les alertes pour recevoir
                les mises à jour de commande.
            </p>
            <ol>
                <li>Cliquez sur le <strong>cadenas</strong> (à gauche de l'adresse)</li>
                <li><strong>Notifications</strong> → choisissez <strong>Autoriser</strong></li>
                <li>Cliquez sur le bouton ci-dessous</li>
            </ol>
            <button type="button" id="btn-notify-continue" class="mc-btn mc-btn--primary notify-help-panel__btn">
                <i class="fas fa-check"></i> J'ai autorisé — continuer
            </button>
        </div>

        <!-- Stats -->
        <section class="mc-stats" aria-label="Statistiques du compte">
            <a href="mes-commandes.php" class="mc-stat mc-stat--commandes">
                <span class="mc-stat__icon"><i class="fas fa-shopping-bag"></i></span>
                <span class="mc-stat__value"><?php echo (int) $nb_commandes; ?></span>
                <span class="mc-stat__label">Commandes</span>
            </a>
            <a href="/index.php?open=panier" class="mc-stat mc-stat--panier js-open-cart-modal">
                <span class="mc-stat__icon"><i class="fas fa-shopping-cart"></i></span>
                <span class="mc-stat__value"><?php echo (int) $nb_panier; ?></span>
                <span class="mc-stat__label">Panier</span>
            </a>
            <div class="mc-stat mc-stat--favoris">
                <span class="mc-stat__icon"><i class="fas fa-heart"></i></span>
                <span class="mc-stat__value"><?php echo (int) $nb_favoris; ?></span>
                <span class="mc-stat__label">Favoris</span>
            </div>
            <div class="mc-stat mc-stat--visites">
                <span class="mc-stat__icon"><i class="fas fa-eye"></i></span>
                <span class="mc-stat__value"><?php echo (int) $nb_visites; ?></span>
                <span class="mc-stat__label">Visités</span>
            </div>
        </section>

        <!-- Dernières commandes -->
        <section class="mc-section">
            <div class="mc-section__head">
                <h2><i class="fas fa-clock-rotate-left"></i> Dernières commandes</h2>
                <a href="mes-commandes.php" class="mc-section__link">Tout voir <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (empty($dernieres_commandes)): ?>
                <div class="mc-empty">
                    <i class="fas fa-box-open"></i>
                    <p>Aucune commande pour le moment.</p>
                    <a href="/index.php" class="mc-btn mc-btn--primary">
                        <i class="fas fa-store"></i> Découvrir les produits
                    </a>
                </div>
            <?php else: ?>
                <div class="mc-orders">
                    <?php foreach ($dernieres_commandes as $commande): ?>
                        <?php
                        $statut = (string) ($commande['statut'] ?? 'en_attente');
                        $statut_label = $statut_labels[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
                        $date_cmd = !empty($commande['date_commande'])
                            ? date('d/m/Y', strtotime($commande['date_commande']))
                            : '—';
                        $thumbs = array_slice($commande['produits'] ?? [], 0, 3);
                        ?>
                        <article class="mc-order">
                            <div class="mc-order__top">
                                <div class="mc-order__meta">
                                    <span class="mc-order__numero"><?php echo htmlspecialchars($commande['numero_commande']); ?></span>
                                    <span class="mc-order__date"><i class="far fa-calendar"></i> <?php echo htmlspecialchars($date_cmd); ?></span>
                                </div>
                                <span class="mc-order__statut statut-<?php echo htmlspecialchars($statut); ?>">
                                    <?php echo htmlspecialchars($statut_label); ?>
                                </span>
                            </div>

                            <?php if (!empty($thumbs)): ?>
                                <div class="mc-order__thumbs">
                                    <?php foreach ($thumbs as $thumb): ?>
                                        <img
                                            src="<?php echo htmlspecialchars(commande_ligne_image_url($thumb, 'xs')); ?>"
                                            alt="<?php echo htmlspecialchars($thumb['nom'] ?? 'Produit'); ?>"
                                            loading="lazy"
                                            onerror="this.src='/image/produit1.jpg'">
                                    <?php endforeach; ?>
                                    <?php if (count($commande['produits']) > 3): ?>
                                        <span class="mc-order__more">+<?php echo count($commande['produits']) - 3; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="mc-order__bottom">
                                <div class="mc-order__summary">
                                    <span><?php echo (int) $commande['nb_articles']; ?> article<?php echo ((int) $commande['nb_articles'] > 1) ? 's' : ''; ?></span>
                                    <strong><?php echo number_format((float) ($commande['montant_total'] ?? 0), 0, ',', ' '); ?> FCFA</strong>
                                </div>
                                <div class="mc-order__actions mc-order__actions--inline">
                                    <?php if (livreur_client_peut_suivre_gps($commande)): ?>
                                        <a href="suivi-commande.php?commande_id=<?php echo (int) $commande['id']; ?>"
                                            class="mc-btn mc-btn--sm mc-btn--track">
                                            <i class="fas fa-location-dot"></i> Suivre
                                        </a>
                                    <?php elseif (livreur_client_livraison_en_cours($commande)): ?>
                                        <span class="mc-btn mc-btn--sm mc-btn--pending" aria-disabled="true">
                                            <i class="fas fa-truck"></i> GPS bientôt
                                        </span>
                                    <?php endif; ?>
                                    <a href="commande-categorie.php?commande_id=<?php echo (int) $commande['id']; ?>" class="mc-btn mc-btn--sm mc-btn--ghost">
                                        Détails <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Produits livrés -->
        <section class="mc-section">
            <div class="mc-section__head">
                <h2><i class="fas fa-check-circle"></i> Produits livrés</h2>
                <a href="mes-commandes.php?onglet=recues" class="mc-section__link">Tout voir <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (empty($produits_commandes)): ?>
                <div class="mc-empty mc-empty--compact">
                    <i class="fas fa-truck"></i>
                    <p>Aucun produit livré pour le moment.</p>
                    <a href="mes-commandes.php" class="mc-btn mc-btn--ghost mc-btn--sm">
                        <i class="fas fa-shopping-bag"></i> Voir mes commandes
                    </a>
                </div>
            <?php else: ?>
                <div class="mc-products">
                    <?php foreach (array_slice($produits_commandes, 0, 8) as $produit): ?>
                        <?php
                        $statut_p = $produit['statut'] ?? 'actif';
                        $statut_p_class = 'statut-actif';
                        if ($statut_p === 'inactif') {
                            $statut_p_class = 'statut-inactif';
                        } elseif ($statut_p === 'rupture_stock') {
                            $statut_p_class = 'statut-rupture';
                        }
                        ?>
                        <article class="mc-product">
                            <?php echo produit_share_button_html($produit); ?>
                            <span class="mc-product__badge <?php echo $statut_p_class; ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $statut_p))); ?>
                            </span>
                            <a href="/produit.php?id=<?php echo (int) $produit['id']; ?>" class="mc-product__media">
                                <img
                                    src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'sm')); ?>"
                                    alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                                    loading="lazy"
                                    onerror="this.src='/image/produit1.jpg'">
                            </a>
                            <div class="mc-product__body">
                                <h3 class="mc-product__name">
                                    <a href="/produit.php?id=<?php echo (int) $produit['id']; ?>">
                                        <?php echo htmlspecialchars($produit['nom']); ?>
                                    </a>
                                </h3>
                                <p class="mc-product__cat"><?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie'); ?></p>
                                <p class="mc-product__price">
                                    <?php echo number_format((float) $produit['prix_unitaire'], 0, ',', ' '); ?>
                                    <span>FCFA</span>
                                </p>
                                <p class="mc-product__meta">
                                    Qté <?php echo (int) $produit['quantite']; ?>
                                    · <?php echo htmlspecialchars($produit['numero_commande']); ?>
                                </p>
                                <div class="mc-product__actions">
                                    <a href="/produit.php?id=<?php echo (int) $produit['id']; ?>" class="mc-btn mc-btn--sm mc-btn--primary">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                    <a href="mes-commandes.php" class="mc-btn mc-btn--sm mc-btn--ghost">
                                        <i class="fas fa-list"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'includes/user_footer.php'; ?>
    <?php include __DIR__ . '/../includes/platform_share_footer.php'; ?>
