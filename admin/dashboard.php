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
require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_categories.php';

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
        <?php if ($en_attente > 0 || $prise_en_charge > 0): ?>
            <div class="alert-box">
                <p>
                    <i class="fas fa-exclamation-circle"></i>
                    <?php if ($en_attente > 0): ?>
                        <?php echo $en_attente; ?> commande<?php echo $en_attente > 1 ? 's' : ''; ?> en attente de prise en
                        charge
                    <?php elseif ($prise_en_charge > 0): ?>
                        <?php echo $prise_en_charge; ?> commande<?php echo $prise_en_charge > 1 ? 's' : ''; ?>
                        prise<?php echo $prise_en_charge > 1 ? 's' : ''; ?> en charge,
                        prête<?php echo $prise_en_charge > 1 ? 's' : ''; ?> à être
                        expédiée<?php echo $prise_en_charge > 1 ? 's' : ''; ?>
                    <?php endif; ?>
                </p>
                <a href="commandes/index.php" class="btn-alert">
                    <i class="fas fa-arrow-right"></i> Gérer les commandes
                </a>
            </div>
        <?php endif; ?>

        <?php if ($commandes_perso_en_attente > 0): ?>
            <div class="alert-box" style="margin-top: 15px;">
                <p>
                    <i class="fas fa-palette"></i>
                    <?php echo $commandes_perso_en_attente; ?>
                    commande<?php echo $commandes_perso_en_attente > 1 ? 's' : ''; ?>
                    personnalisée<?php echo $commandes_perso_en_attente > 1 ? 's' : ''; ?> en attente
                </p>
                <a href="commandes-personnalisees/index.php" class="btn-alert">
                    <i class="fas fa-arrow-right"></i> Voir les commandes personnalisées
                </a>
            </div>
        <?php endif; ?>

        <!-- Section produits -->
        <section class="produits-section">
            <div class="section-title">
                <h2><i class="fas fa-box"></i> Mes Produits (<?php echo count($produits); ?>)</h2>
            </div>

            <form method="GET" action="" class="admin-filters-bar admin-filters-bar--produits">
                <div class="admin-filters-fields-row">
                    <div class="admin-filter-field admin-filter-field--search">
                        <label for="recherche">Recherche</label>
                        <input type="text" id="recherche" name="recherche" placeholder="Nom, description, statut..."
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
                    <p>Aucun produit enregistré pour le moment.</p>
                    <a href="produits/ajouter.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter le premier produit
                    </a>
                </div>
            <?php else: ?>
                <!-- Grille de produits -->
                <div class="produits-grid">
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        $statut_class = 'statut-actif';
                        if ($produit['statut'] == 'inactif') {
                            $statut_class = 'statut-inactif';
                        } elseif ($produit['statut'] == 'rupture_stock') {
                            $statut_class = 'statut-rupture';
                        }
                        $statut_label = ucfirst(str_replace('_', ' ', $produit['statut']));
                        ?>
                        <div class="produit-card produit-card-linkable"
                            data-href="produits/ajuster-stock.php?id=<?php echo (int) $produit['id']; ?>">
                            <span class="statut-badge <?php echo $statut_class; ?>"><?php echo $statut_label; ?></span>
                            <img src="/upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
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
    <?php include 'includes/footer.php'; ?>