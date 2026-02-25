<?php
/**
 * Page de liste des catégories
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Récupérer toutes les catégories
require_once __DIR__ . '/../../models/model_categories.php';
$categories = get_all_categories();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Catégories - Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-tags"></i> Liste des Catégories</h1>
        <div class="header-actions">
            <a href="ajouter.php" class="btn-primary">
                <i class="fas fa-plus"></i> Nouvelle Catégorie
            </a>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
    <div class="message success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <section class="produits-section categories-section">
        <div class="section-title">
            <h2><i class="fas fa-tags"></i> Toutes les Catégories (<?php echo count($categories); ?>)</h2>
        </div>

        <?php if (empty($categories)): ?>
        <div class="empty-state">
            <i class="fas fa-tags"></i>
            <h3>Aucune catégorie</h3>
            <p>Aucune catégorie enregistrée pour le moment.</p>
            <a href="ajouter.php" class="btn-primary">
                <i class="fas fa-plus"></i> Ajouter la première catégorie
            </a>
        </div>
        <?php else: ?>
        <div class="categories-grid">
            <?php foreach ($categories as $categorie): ?>
            <div class="categorie-card">
                <div class="categorie-card-image-wrap">
                    <?php if ($categorie['image']): ?>
                    <img src="/upload/<?php echo htmlspecialchars($categorie['image']); ?>"
                        alt="<?php echo htmlspecialchars($categorie['nom']); ?>" class="categorie-image"
                        onerror="this.src='/image/produit1.jpg'">
                    <?php else: ?>
                    <div class="categorie-image-placeholder">
                        <i class="fas fa-tag"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="categorie-card-body">
                    <h3 class="categorie-nom"><?php echo htmlspecialchars($categorie['nom']); ?></h3>
                    <p class="categorie-description">
                        <?php echo htmlspecialchars($categorie['description'] ?? 'Aucune description'); ?>
                    </p>
                    <div class="categorie-actions">
                        <a href="produits.php?id=<?php echo $categorie['id']; ?>" class="btn-card btn-view">
                            <i class="fas fa-box"></i> Voir produits
                        </a>
                        <a href="modifier.php?id=<?php echo $categorie['id']; ?>" class="btn-card btn-edit">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="supprimer.php?id=<?php echo $categorie['id']; ?>" class="btn-card btn-delete"
                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?');">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>