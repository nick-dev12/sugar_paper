<?php
/**
 * Page des produits publiés liés à un article en stock
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$stock_article_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($stock_article_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_stock.php';
require_once __DIR__ . '/../../models/model_produits.php';

$article = get_stock_article_by_id($stock_article_id);
if (!$article) {
    header('Location: index.php');
    exit;
}

$produits = get_produits_by_stock_article($stock_article_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - <?php echo htmlspecialchars($article['nom']); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .produits-stock-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .produit-stock-card { background: #fff; border: 1px solid #ececec; border-radius: 12px; overflow: hidden; transition: box-shadow 0.2s; }
        .produit-stock-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .produit-stock-card img { width: 100%; height: 140px; object-fit: cover; background: #f5f5f5; }
        .produit-stock-card-body { padding: 14px; }
        .produit-stock-card-nom { font-size: 15px; font-weight: 600; color: #000; margin: 0 0 6px 0; }
        .produit-stock-card-prix { font-size: 14px; color: var(--couleur-dominante,#E5488A); font-weight: 600; }
        .produit-stock-card-stock { font-size: 12px; color: #666; margin-top: 4px; }
        .btn-voir-produit { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; padding: 8px 14px; font-size: 13px; background: var(--couleur-dominante,#E5488A); color: #fff; border-radius: 8px; text-decoration: none; }
        .btn-voir-produit:hover { opacity: 0.9; color: #fff; }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-box-open"></i> Produits liés à « <?php echo htmlspecialchars($article['nom']); ?> »</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour au stock</a>
        </div>
    </div>

    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-tag"></i> Produits publiés (<?php echo count($produits); ?>)</h2>
        </div>

        <?php if (empty($produits)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Aucun produit publié</h3>
                <p>Aucun produit publié n'est lié à cet article en stock.</p>
                <a href="../produits/ajouter.php?stock_article_id=<?php echo $stock_article_id; ?>" class="btn-primary">
                    <i class="fas fa-plus"></i> Créer un produit à partir de cet article
                </a>
            </div>
        <?php else: ?>
            <div class="produits-stock-grid">
                <?php foreach ($produits as $p): ?>
                    <div class="produit-stock-card">
                        <img src="/upload/<?php echo htmlspecialchars($p['image_principale'] ?? ''); ?>" alt="<?php echo htmlspecialchars($p['nom']); ?>"
                            onerror="this.src='/image/produit1.jpg'">
                        <div class="produit-stock-card-body">
                            <h3 class="produit-stock-card-nom"><?php echo htmlspecialchars($p['nom']); ?></h3>
                            <div class="produit-stock-card-prix"><?php echo number_format($p['prix'], 0, ',', ' '); ?> FCFA</div>
                            <div class="produit-stock-card-stock">Stock: <?php echo (int) ($p['stock'] ?? 0); ?></div>
                            <a href="../produits/modifier.php?id=<?php echo (int) $p['id']; ?>" class="btn-voir-produit">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
