<?php
/**
 * Page d'accueil du tableau de bord administrateur
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
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
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Barre de navigation verticale -->

    <!-- Contenu principal -->
    <div class="contents-container">
        <div class="content-header">
            <h1><i class="fas fa-chart-line"></i> Tableau de Bord</h1>
            <div class="header-actions">
                <button type="button" id="btn-install-pwa" class="btn-primary btn-secondary-style"
                    title="Installer l'application Sugar Paper sur cet appareil" style="display: none;">
                    <i class="fas fa-download"></i> Installer l'application
                </button>
                <button type="button" id="btn-enable-notifications" class="btn-primary btn-secondary-style"
                    title="Recevoir des notifications push pour les nouvelles commandes">
                    <i class="fas fa-bell"></i> Activer les notifications
                </button>
                <!-- <a href="test-notification.php" class="btn-primary btn-secondary-style" title="Envoyer une notification de test sur cet ordinateur">
                    <i class="fas fa-paper-plane"></i> Test notification
                </a> -->
                <a href="zones-livraison/index.php" class="btn-primary btn-secondary-style">
                    <i class="fas fa-truck"></i> Zones de livraison
                </a>
                <a href="produits/ajouter.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Nouveau Produit
                </a>
            </div>
        </div>

        <?php
        if (isset($_SESSION['notification_test_message'])) {
            $test_msg = $_SESSION['notification_test_message'];
            $test_type = $_SESSION['notification_test_type'] ?? 'success';
            unset($_SESSION['notification_test_message'], $_SESSION['notification_test_type']);
            ?>
            <div class="alert-box message-<?php echo htmlspecialchars($test_type); ?>" style="margin-bottom: 20px;">
                <p><i class="fas fa-<?php echo $test_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($test_msg); ?></p>
            </div>
            <?php
        }
        // Récupérer les statistiques des commandes
        require_once __DIR__ . '/../models/model_commandes_admin.php';
        require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
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
                <h2><i class="fas fa-box"></i> Mes Produits</h2>
            </div>

            <?php
            // Récupérer les produits depuis la base de données
            require_once __DIR__ . '/../models/model_produits.php';
            $produits = get_all_produits();
            ?>

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
                        <div class="produit-card">
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
                                    <?php if ($produit['poids']): ?>
                                        (<?php echo htmlspecialchars($produit['poids']); ?>)
                                    <?php endif; ?>
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

    <script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-messaging-compat.js"></script>
    <?php require_once __DIR__ . '/../includes/firebase_init.php'; ?>
    <script>
        if (window.FIREBASE_CONFIG) {
            firebase.initializeApp(window.FIREBASE_CONFIG);
        }
    </script>
    <script src="/js/firebase-notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('btn-enable-notifications');
            if (btn) {
                btn.addEventListener('click', function () {
                    if (typeof FirebaseNotifications !== 'undefined') {
                        FirebaseNotifications.enable('admin', this);
                    } else {
                        alert(
                            'Erreur: Les scripts de notification ne sont pas chargés. Vérifiez la console (F12).'
                        );
                    }
                });
            }

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