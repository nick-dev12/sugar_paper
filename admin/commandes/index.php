<?php
/**
 * Page de liste des commandes non traitées (Admin)
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer toutes les commandes
require_once __DIR__ . '/../../models/model_commandes_admin.php';
$toutes_commandes = get_all_commandes();

// Filtrer pour exclure les commandes avec le statut "livree" et "annulee" (commandes non traitées)
$commandes = array_filter($toutes_commandes, function($commande) {
    return $commande['statut'] !== 'livree' && $commande['statut'] !== 'annulee';
});

// Statistiques
$total_commandes = count_commandes_by_statut();
$en_attente = count_commandes_by_statut('en_attente');
$confirmees = count_commandes_by_statut('confirmee');
$livrees = count_commandes_by_statut('livree');
$prise_en_charge = count_commandes_by_statut('prise_en_charge');
$livraison_en_cours = count_commandes_by_statut('livraison_en_cours');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes Non Traitées - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header">
        <h1><i class="fas fa-shopping-bag"></i> Commandes Non Traitées</h1>
    </div>

    <!-- Statistiques -->
    <div class="commandes-stats">
        <div class="stat-box">
            <h3>Total Commandes</h3>
            <div class="stat-value"><?php echo $total_commandes; ?></div>
        </div>
        <div class="stat-box">
            <h3>En Attente</h3>
            <div class="stat-value"><?php echo $en_attente; ?></div>
        </div>
        <div class="stat-box">
            <h3>Prise en charge</h3>
            <div class="stat-value"><?php echo $prise_en_charge; ?></div>
        </div>
        <div class="stat-box">
            <h3>Livraison en cours</h3>
            <div class="stat-value"><?php echo $livraison_en_cours; ?></div>
        </div>
        <div class="stat-box">
            <h3>Livrées</h3>
            <div class="stat-value"><?php echo $livrees; ?></div>
        </div>
    </div>

    <!-- Liste des commandes -->
    <section class="content-section">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="fas fa-list"></i> Commandes à Traiter (<?php echo count($commandes); ?>)</h2>
            </div>
            <div class="form-actions" style="flex-wrap: wrap;">
                <a href="livrees.php" class="btn-link">
                    <i class="fas fa-check-circle"></i> Voir les commandes livrées
                </a>
                <a href="annulees.php" class="btn-link btn-danger">
                    <i class="fas fa-ban"></i> Voir les commandes annulées
                </a>
            </div>
        </div>

        <?php if (empty($commandes)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h3>Aucune commande à traiter</h3>
                <p>Toutes les commandes ont été traitées et livrées.</p>
            </div>
        <?php else: ?>
            <div class="commandes-grid">
                <?php foreach ($commandes as $commande): ?>
                    <div class="commande-item">
                        <div class="commande-header">
                            <div class="commande-info">
                                <h3>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></h3>
                                <p>
                                    <strong>Client:</strong> <?php echo htmlspecialchars($commande['user_prenom'] . ' ' . $commande['user_nom']); ?><br>
                                    <span class="client-email"><?php echo htmlspecialchars($commande['user_email']); ?></span>
                                </p>
                                <p class="commande-date">Date: <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></p>
                            </div>
                            <span class="commande-statut statut-<?php echo $commande['statut']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $commande['statut'])); ?>
                            </span>
                        </div>
                        <div class="commande-details">
                            <div class="detail-item">
                                <label>Montant total</label>
                                <div class="value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="detail-item">
                                <label>Adresse</label>
                                <div class="value small">
                                    <?php echo htmlspecialchars(substr($commande['adresse_livraison'], 0, 30)); ?>...
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Téléphone</label>
                                <div class="value"><?php echo htmlspecialchars($commande['telephone_livraison']); ?></div>
                            </div>
                        </div>
                        
                        <a href="details.php?id=<?php echo $commande['id']; ?>" class="btn-view">
                            <i class="fas fa-eye"></i> Voir les détails
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
