<?php
/**
 * Page des commandes livrées
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit;
}

// Récupérer uniquement les commandes avec le statut "livree"
require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
$commandes = get_commandes_by_user($_SESSION['user_id']);

// Filtrer pour ne garder que les commandes avec le statut "livree"
$commandes_livrees = array_filter($commandes, function($commande) {
    return $commande['statut'] === 'livree';
});

// Commandes personnalisées terminées
$commandes_perso_terminees = get_commandes_personnalisees_by_user($_SESSION['user_id'], 'terminee');
$statuts_labels = get_statuts_commande_personnalisee();

$nb_commandes_livrees = count($commandes_livrees);
$nb_commandes_perso_terminees = count($commandes_perso_terminees);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Commandes Livrées - Sugar Paper</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-produits-livres.css<?php echo asset_version_query(); ?>">
</head>
<body class="user-page-produits-livres">
    <?php include 'includes/user_nav.php'; ?>

    <div class="continue-shopping-banner">
        <div class="continue-shopping-content">
            <div class="continue-shopping-icon">
                <i class="fas fa-shopping-basket" aria-hidden="true"></i>
            </div>
            <div class="continue-shopping-text">
                <h2>Commander à nouveau</h2>
                <p>Retrouvez vos produits reçus ou passez une nouvelle commande dans notre catalogue.</p>
            </div>
            <div class="continue-shopping-actions">
                <a href="/produits.php" class="continue-shopping-btn">
                    <i class="fas fa-store" aria-hidden="true"></i> Voir les produits
                </a>
                <a href="mes-commandes.php" class="continue-shopping-btn continue-shopping-btn--secondary">
                    <i class="fas fa-shopping-bag" aria-hidden="true"></i> Mes commandes actives
                </a>
            </div>
        </div>
    </div>

    <div class="content-header">
        <h1><i class="fas fa-check-circle"></i> Commandes Livrées</h1>
        <p class="content-header-desc">Toutes les commandes que vous avez reçues</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-card--livrees">
            <div class="stat-icon"><i class="fas fa-box" aria-hidden="true"></i></div>
            <div class="stat-value"><?php echo $nb_commandes_livrees; ?></div>
            <div class="stat-label">Commandes reçues</div>
        </div>
        <div class="stat-card stat-card--perso">
            <div class="stat-icon"><i class="fas fa-palette" aria-hidden="true"></i></div>
            <div class="stat-value"><?php echo $nb_commandes_perso_terminees; ?></div>
            <div class="stat-label">Demandes personnalisées reçues</div>
        </div>
    </div>

    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-box"></i> Mes Commandes Reçues (<?php echo $nb_commandes_livrees; ?>)</h2>
        </div>

        <?php if (empty($commandes_livrees)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Aucune commande livrée</h3>
                <p>Vous n'avez pas encore reçu de commandes. Vos commandes livrées apparaîtront ici une fois que vous aurez confirmé la réception de vos colis.</p>
                <a href="mes-commandes.php" class="btn-primary">
                    <i class="fas fa-shopping-bag"></i> Voir mes commandes
                </a>
            </div>
        <?php else: ?>
            <div class="commandes-grid">
                <?php foreach ($commandes_livrees as $commande): ?>
                    <div class="commande-item commande-item-livree">
                        <div class="commande-header">
                            <div class="commande-info">
                                <h3>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></h3>
                                <p>Date: <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></p>
                            </div>
                            <span class="commande-statut statut-livree" style="align-self: flex-start;">
                                <i class="fas fa-check-circle"></i> Reçu
                            </span>
                        </div>
                        <div class="commande-details">
                            <div class="detail-item">
                                <label>Montant total</label>
                                <div class="value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="detail-item">
                                <label>Adresse</label>
                                <div class="value value--address">
                                    <?php echo htmlspecialchars(substr($commande['adresse_livraison'], 0, 30)); ?>...
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Téléphone</label>
                                <div class="value"><?php echo htmlspecialchars($commande['telephone_livraison']); ?></div>
                            </div>
                            <?php if ($commande['date_livraison']): ?>
                                <div class="detail-item">
                                    <label>Date livraison</label>
                                    <div class="value"><?php echo date('d/m/Y', strtotime($commande['date_livraison'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="commande-actions">
                            <a href="commande-categorie.php?commande_id=<?php echo $commande['id']; ?>" 
                               class="btn-view-categories btn-view-commande">
                                <i class="fas fa-eye"></i> Voir les produits reçus
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Section Commandes personnalisées terminées -->
        <div class="section-title section-title--spaced">
            <h2><i class="fas fa-palette"></i> Commandes personnalisées reçues (<?php echo $nb_commandes_perso_terminees; ?>)</h2>
        </div>

        <?php if (empty($commandes_perso_terminees)): ?>
            <div class="empty-state empty-state-compact">
                <i class="fas fa-palette"></i>
                <p>Aucune commande personnalisée reçue.</p>
                <a href="/commande-personnalisee.php" class="btn-primary">
                    <i class="fas fa-palette"></i> Faire une demande personnalisée
                </a>
            </div>
        <?php else: ?>
            <div class="commandes-grid">
                <?php foreach ($commandes_perso_terminees as $cp): ?>
                    <div class="commande-item commande-item-perso">
                        <div class="commande-header">
                            <div class="commande-info">
                                <h3>Demande #<?php echo $cp['id']; ?></h3>
                                <p>Date: <?php echo date('d/m/Y à H:i', strtotime($cp['date_creation'])); ?></p>
                            </div>
                            <span class="commande-statut statut-terminee" style="align-self: flex-start;">
                                <i class="fas fa-check-circle"></i> Terminée
                            </span>
                        </div>
                        <div class="commande-details">
                            <div class="detail-item">
                                <label>Description</label>
                                <div class="value value--description">
                                    <?php echo nl2br(htmlspecialchars(substr($cp['description'], 0, 120))); ?><?php echo strlen($cp['description']) > 120 ? '...' : ''; ?>
                                </div>
                            </div>
                            <?php if ($cp['type_produit']): ?>
                            <div class="detail-item">
                                <label>Type</label>
                                <div class="value"><?php echo htmlspecialchars($cp['type_produit']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="commande-actions">
                            <a href="commande-personnalisee-details.php?id=<?php echo $cp['id']; ?>" class="btn-view-categories btn-view-commande">
                                <i class="fas fa-eye"></i> Voir les détails
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include 'includes/user_footer.php'; ?>

</body>
</html>
