<?php
/**
 * Gestion du stock - Catégories et produits
 * Contenu déplacé depuis categories/index.php
 * Utilise la table produits et la colonne stock (plus de table stock_articles)
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

require_once __DIR__ . '/../../models/model_categories.php';
$categories = get_all_categories();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Stock - Catégories - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .btn-history {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 22px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.25s ease;
            border: 2px solid #918a44;
            background: linear-gradient(135deg, #f8f7f2 0%, #fff 100%);
            color: #6b2f20;
            box-shadow: 0 2px 8px rgba(145, 138, 68, 0.15);
        }

        .btn-history:hover {
            background: linear-gradient(135deg, #918a44 0%, #7a7340 100%);
            color: #fff;
            border-color: #918a44;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(145, 138, 68, 0.3);
        }

        .btn-history i {
            font-size: 16px;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-boxes-stacked"></i> Gestion du Stock</h1>
        <div class="header-actions">
            <a href="mouvements.php" class="btn-history">
                <i class="fas fa-history"></i> Historique des mouvements
            </a>
            <a href="../categories/ajouter.php" class="btn-primary">
                <i class="fas fa-plus"></i> Ajouter une catégorie
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
                <a href="../categories/ajouter.php" class="btn-primary">
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
                                <a href="../categories/produits.php?id=<?php echo $categorie['id']; ?>"
                                    class="btn-card btn-view">
                                    <i class="fas fa-box"></i> Voir produits
                                </a>
                                <a href="../categories/modifier.php?id=<?php echo $categorie['id']; ?>"
                                    class="btn-card btn-edit">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <a href="../categories/supprimer.php?id=<?php echo $categorie['id']; ?>"
                                    class="btn-card btn-delete"
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
</body>

</html>