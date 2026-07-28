<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Page d'accueil du tableau de bord administrateur
 * Programmation procédurale uniquement
 */

session_start_persistent();

require_once __DIR__ . '/../includes/admin_route_access.php';
admin_route_enforce();

require_once __DIR__ . '/../includes/admin_permissions.php';

// Vérifier si l'admin est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../models/model_commandes_admin.php';
require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/site_url.php';

$enable_firebase_notifications = true;
$firebase_notify_type = 'admin';

$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$categories = get_all_categories();
$produits = get_all_produits();
$is_utilisateur_dashboard = admin_is_utilisateur();

if (!empty($produits)) {
    $produits = array_values(array_filter($produits, function ($produit) use ($recherche, $categorie_id) {
        if ($categorie_id > 0 && (int) ($produit['categorie_id'] ?? 0) !== $categorie_id) {
            return false;
        }

        if ($recherche === '') {
            return true;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($recherche) : strtolower($recherche);
        $haystacks = [
            $produit['nom'] ?? '',
            $produit['description'] ?? '',
            $produit['categorie_nom'] ?? '',
            $produit['statut'] ?? ''
        ];

        foreach ($haystacks as $value) {
            $value = function_exists('mb_strtolower') ? mb_strtolower((string) $value) : strtolower((string) $value);
            if (strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }));
}

$produits_plus_vendus = [];
if (!empty($produits)) {
    foreach ($produits as $produit) {
        $qte_vendue = get_quantite_vendue_produit((int) ($produit['id'] ?? 0));
        if ($qte_vendue <= 0) {
            continue;
        }
        $produit['quantite_vendue'] = (int) $qte_vendue;
        $produits_plus_vendus[] = $produit;
    }

    usort($produits_plus_vendus, function ($a, $b) {
        $qa = (int) ($a['quantite_vendue'] ?? 0);
        $qb = (int) ($b['quantite_vendue'] ?? 0);
        if ($qa === $qb) {
            return strcmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
        }
        return $qb <=> $qa;
    });
}
$produits = $produits_plus_vendus;
$total_produits_plus_vendus = count($produits);
$produits_par_page = 15;
$page_produits = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$total_pages_produits = $total_produits_plus_vendus > 0
    ? (int) ceil($total_produits_plus_vendus / $produits_par_page)
    : 1;
if ($page_produits > $total_pages_produits) {
    $page_produits = $total_pages_produits;
}
$offset_produits = ($page_produits - 1) * $produits_par_page;
$produits_page = array_slice($produits, $offset_produits, $produits_par_page);
$produits_affichage_debut = $total_produits_plus_vendus > 0 ? $offset_produits + 1 : 0;
$produits_affichage_fin = min($offset_produits + count($produits_page), $total_produits_plus_vendus);

$dashboard_produits_page_url = function ($page) use ($recherche, $categorie_id) {
    $params = [];
    if ($page > 1) {
        $params['page'] = (int) $page;
    }
    if ($recherche !== '') {
        $params['recherche'] = $recherche;
    }
    if ($categorie_id > 0) {
        $params['categorie_id'] = (int) $categorie_id;
    }
    $qs = http_build_query($params);
    return 'dashboard.php' . ($qs !== '' ? '?' . $qs : '');
};

$base_site_url = rtrim(get_site_base_url(), '/');

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Administration Sugar Paper</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-index.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-dashboard-admin">
    <?php include 'includes/nav.php'; ?>

    <!-- Barre de navigation verticale -->

    <!-- Contenu principal -->
    <div class="contents-container">
        <div class="content-header">
            <h1><i class="fas fa-chart-line"></i> Tableau de Bord</h1>
            <div class="header-actions">
                <?php include __DIR__ . '/includes/btn_retour_site.php'; ?>
                <button type="button" id="btn-install-pwa" class="btn-primary btn-secondary-style"
                    title="Installer l'application Sugar Paper sur cet appareil" style="display: none;">
                    <i class="fas fa-download"></i> Installer l'application
                </button>
                <a href="zones-livraison/index.php" class="btn-primary btn-secondary-style">
                    <i class="fas fa-truck"></i> Zones de livraison
                </a>
                <a href="produits/ajouter.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Nouveau Produit
                </a>
            </div>
        </div>

        <?php if ($is_utilisateur_dashboard): ?>
        <section class="dashboard-quick-links" aria-label="Accès rapides">
            <h2 class="dashboard-quick-links__title"><i class="fas fa-th-large" aria-hidden="true"></i> Accès rapides</h2>
            <div class="dashboard-quick-links__grid">
                <a href="invoice/index.php" class="dashboard-quick-link">
                    <span class="dashboard-quick-link__icon" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                    <span class="dashboard-quick-link__label">Invoice</span>
                    <span class="dashboard-quick-link__sub">Factures, devis, clients, rapports</span>
                </a>
                <a href="livreurs/carte.php" class="dashboard-quick-link">
                    <span class="dashboard-quick-link__icon" aria-hidden="true"><i class="fas fa-map-location-dot"></i></span>
                    <span class="dashboard-quick-link__label">Map livreurs</span>
                    <span class="dashboard-quick-link__sub">Suivi GPS en temps réel</span>
                </a>
                <a href="produits/index.php" class="dashboard-quick-link">
                    <span class="dashboard-quick-link__icon" aria-hidden="true"><i class="fas fa-box"></i></span>
                    <span class="dashboard-quick-link__label">Produits</span>
                    <span class="dashboard-quick-link__sub">Catalogue boutique</span>
                </a>
                <a href="stock/index.php" class="dashboard-quick-link">
                    <span class="dashboard-quick-link__icon" aria-hidden="true"><i class="fas fa-boxes-stacked"></i></span>
                    <span class="dashboard-quick-link__label">Stock</span>
                    <span class="dashboard-quick-link__sub">Catégories et quantités</span>
                </a>
                <a href="commandes/index.php" class="dashboard-quick-link">
                    <span class="dashboard-quick-link__icon" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                    <span class="dashboard-quick-link__label">Commandes</span>
                    <span class="dashboard-quick-link__sub">Gestion des commandes</span>
                </a>
                <a href="commandes-personnalisees/index.php" class="dashboard-quick-link">
                    <span class="dashboard-quick-link__icon" aria-hidden="true"><i class="fas fa-palette"></i></span>
                    <span class="dashboard-quick-link__label">Commandes perso</span>
                    <span class="dashboard-quick-link__sub">Sur mesure client</span>
                </a>
                <a href="zones-livraison/index.php" class="dashboard-quick-link">
                    <span class="dashboard-quick-link__icon" aria-hidden="true"><i class="fas fa-truck"></i></span>
                    <span class="dashboard-quick-link__label">Zones livraison</span>
                    <span class="dashboard-quick-link__sub">Tarifs et secteurs</span>
                </a>
            </div>
        </section>
        <?php endif; ?>

        <?php
        if (isset($_SESSION['notification_test_message'])) {
            $test_msg = $_SESSION['notification_test_message'];
            $test_type = $_SESSION['notification_test_type'] ?? 'success';
            unset($_SESSION['notification_test_message'], $_SESSION['notification_test_type']);
            ?>
            <div class="alert-box message-<?php echo htmlspecialchars($test_type); ?>" style="margin-bottom: 20px;">
                <p style="white-space:pre-wrap;margin:0;"><i class="fas fa-<?php echo $test_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($test_msg); ?></p>
            </div>
            <?php
        }
        // Récupérer les statistiques des commandes
        $total_commandes = count_commandes_by_statut();
        $commandes_perso_en_attente = count_commandes_personnalisees_by_statut('en_attente');
        $en_attente = count_commandes_by_statut('en_attente');
        $prise_en_charge = count_commandes_by_statut('prise_en_charge');
        $livraison_en_cours = count_commandes_by_statut('livraison_en_cours');
        ?>

        <!-- Statistiques des commandes -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Commandes</h3>
                <div class="stat-value"><?php echo $total_commandes; ?></div>
            </div>
            <div class="stat-card stat-en-attente">
                <h3>En Attente</h3>
                <div class="stat-value"><?php echo $en_attente; ?></div>
            </div>
            <div class="stat-card stat-prise">
                <h3>Prise en charge</h3>
                <div class="stat-value"><?php echo $prise_en_charge; ?></div>
            </div>
            <div class="stat-card stat-livraison">
                <h3>Livraison en cours</h3>
                <div class="stat-value"><?php echo $livraison_en_cours; ?></div>
            </div>
        </div>

        <!-- Lien rapide vers les commandes -->
        <!-- Section produits -->
        <section class="produits-section">
            <div class="section-title">
                <h2><i class="fas fa-fire"></i> Produits les plus vendus</h2>
                <span class="section-title-count"><?php echo (int) $total_produits_plus_vendus; ?></span>
            </div>

            <form method="GET" action="" class="admin-filters-bar admin-filters-bar--produits">
                <div class="admin-filters-fields-row">
                    <div class="admin-filter-field admin-filter-field--search">
                        <label for="recherche">Recherche</label>
                        <input type="text" id="recherche" name="recherche" placeholder="Nom, description, catégorie..."
                            value="<?php echo htmlspecialchars($recherche); ?>">
                    </div>
                    <div class="admin-filter-field admin-filter-field--categorie">
                        <label for="categorie_id">Catégorie</label>
                        <select id="categorie_id" name="categorie_id">
                            <option value="0">Toutes</option>
                            <?php foreach ($categories as $categorie): ?>
                                <option value="<?php echo (int) $categorie['id']; ?>"
                                    <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categorie['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="dashboard.php" class="btn-filter-reset">
                        <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                    </a>
                </div>
            </form>

            <?php if (empty($produits)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>Aucun produit vendu pour le moment.</p>
                    <a href="produits/ajouter.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter le premier produit
                    </a>
                </div>
            <?php else: ?>
                <!-- Grille de produits -->
                <div class="produits-grid">
                    <?php foreach ($produits_page as $produit): ?>
                        <?php
                        $share_url = $base_site_url . '/produit.php?id=' . (int) $produit['id'];
                        $share_title = (string) ($produit['nom'] ?? 'Produit');
                        $share_price_value = (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] > 0)
                            ? (float) $produit['prix_promotion']
                            : (float) ($produit['prix'] ?? 0);
                        $share_price = number_format($share_price_value, 0, ',', ' ') . ' FCFA';
                        $share_text = 'Découvrez ce produit : ' . $share_title . ' — ' . $share_price . '.';
                        ?>
                        <div class="produit-card produit-card-linkable"
                            data-href="produits/ajuster-stock.php?id=<?php echo (int) $produit['id']; ?>">
                            <button type="button"
                                class="produit-card-share js-platform-share"
                                aria-label="Partager <?php echo htmlspecialchars($share_title, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-modal-title="Partager le produit"
                                data-share-title="<?php echo htmlspecialchars($share_title, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-url="<?php echo htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-text="<?php echo htmlspecialchars($share_text, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-hint="Partagez ce lien pour que vos clients consultent le produit.">
                                <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                            </button>
                            <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'sm')); ?>"
                                alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-card-image"
                                onerror="this.src='/image/produit1.jpg'">
                            <div class="produit-card-body">
                                <h3 class="produit-card-nom"><?php echo htmlspecialchars($produit['nom']); ?></h3>
                                <p class="produit-card-categorie">
                                    <?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie'); ?>
                                </p>
                                <p class="produit-card-prix">
                                    <?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                    <span class="prix-unite">FCFA</span>
                                    <?php if ($produit['prix_promotion']): ?>
                                        <span class="prix-promo">
                                            (Promo: <?php echo number_format($produit['prix_promotion'], 0, ',', ' '); ?> FCFA)
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="produit-card-stock">
                                    Stock: <span class="stock-value"><?php echo $produit['stock']; ?></span>
                                </p>
                                <p class="produit-card-sales">
                                    Vendus: <span class="stock-value"><?php echo (int) ($produit['quantite_vendue'] ?? 0); ?></span>
                                </p>
                                <div class="produit-card-actions">
                                    <a href="produits/modifier.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-edit">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="produits/supprimer.php?id=<?php echo $produit['id']; ?>"
                                        class="btn-card btn-delete"
                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages_produits > 1): ?>
                <nav class="dashboard-produits-pagination" aria-label="Pagination des produits les plus vendus">
                    <?php if ($page_produits > 1): ?>
                    <a href="<?php echo htmlspecialchars($dashboard_produits_page_url($page_produits - 1)); ?>" class="dashboard-produits-pagination__btn">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent
                    </a>
                    <?php endif; ?>
                    <div class="dashboard-produits-pagination__pages" role="group" aria-label="Numéros de page">
                        <?php for ($i = 1; $i <= $total_pages_produits; $i++): ?>
                            <?php if ($i === $page_produits): ?>
                                <span class="dashboard-produits-pagination__page is-active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($dashboard_produits_page_url($i)); ?>" class="dashboard-produits-pagination__page"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <?php if ($page_produits < $total_pages_produits): ?>
                    <a href="<?php echo htmlspecialchars($dashboard_produits_page_url($page_produits + 1)); ?>" class="dashboard-produits-pagination__btn">
                        Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                    <p class="dashboard-produits-pagination__info">
                        Affichage <?php echo (int) $produits_affichage_debut; ?>–<?php echo (int) $produits_affichage_fin; ?>
                        sur <?php echo (int) $total_produits_plus_vendus; ?> produit<?php echo $total_produits_plus_vendus > 1 ? 's' : ''; ?>
                        (page <?php echo (int) $page_produits; ?> / <?php echo (int) $total_pages_produits; ?>)
                    </p>
                </nav>
                <?php elseif ($total_produits_plus_vendus > 0): ?>
                <p class="dashboard-produits-pagination__info dashboard-produits-pagination__info--solo">
                    <?php echo (int) $total_produits_plus_vendus; ?> produit<?php echo $total_produits_plus_vendus > 1 ? 's' : ''; ?> affiché<?php echo $total_produits_plus_vendus > 1 ? 's' : ''; ?>
                </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.produit-card-linkable').forEach(function(card) {
                card.addEventListener('click', function(event) {
                    if (event.target.closest('a, button, input, select, textarea, form')) {
                        return;
                    }
                    var href = card.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
            });

            var installBtn = document.getElementById('btn-install-pwa');
            var deferredPrompt;

            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                if (installBtn) installBtn.style.display = 'none';
            } else {
                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault();
                    deferredPrompt = e;
                    if (installBtn) installBtn.style.display = 'inline-flex';
                });

                if (installBtn) {
                    installBtn.addEventListener('click', function () {
                        if (!deferredPrompt) {
                            alert(
                                'L\'installation n\'est pas disponible. Essayez depuis Chrome ou Edge en mode HTTPS.'
                            );
                            return;
                        }
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then(function (choiceResult) {
                            if (choiceResult.outcome === 'accepted') {
                                installBtn.style.display = 'none';
                            }
                            deferredPrompt = null;
                        });
                    });
                }
            }
        });
    </script>
    <?php include __DIR__ . '/../includes/partials/platform_share_modal.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>" defer></script>
    <?php include 'includes/footer.php'; ?>