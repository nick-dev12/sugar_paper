<?php
/**
 * Page historique des mouvements de stock
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../models/model_mouvements_stock.php';
require_once __DIR__ . '/../../models/model_categories.php';

$stock_article_id = isset($_GET['article_id']) ? (int) $_GET['article_id'] : null;
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['entree', 'sortie', 'inventaire']) ? $_GET['type'] : null;

$mouvements = get_stock_mouvements($stock_article_id, null, $type_filter, 200);
$categories = get_all_categories();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mouvements de Stock - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .mouvements-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 16px;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 12px;
        }
        .mouvements-filters select { padding: 10px 14px; border-radius: 8px; border: 1px solid #d9d9d9; }
        .mouvements-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .mouvements-table th, .mouvements-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #eee; }
        .mouvements-table th { background: #f8f8f8; font-weight: 600; color: #6b2f20; }
        .badge-entree { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 6px; font-size: 12px; }
        .badge-sortie { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 6px; font-size: 12px; }
        .badge-inventaire { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 6px; font-size: 12px; }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-history"></i> Historique des mouvements de stock</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour au stock
            </a>
        </div>
    </div>

    <section class="produits-section">
        <form method="GET" action="" class="mouvements-filters">
            <select name="type">
                <option value="">Tous les types</option>
                <option value="entree" <?php echo $type_filter === 'entree' ? 'selected' : ''; ?>>Entrées</option>
                <option value="sortie" <?php echo $type_filter === 'sortie' ? 'selected' : ''; ?>>Sorties</option>
                <option value="inventaire" <?php echo $type_filter === 'inventaire' ? 'selected' : ''; ?>>Inventaires</option>
            </select>
            <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
        </form>

        <?php if (empty($mouvements)): ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>Aucun mouvement enregistré.</p>
                <a href="index.php" class="btn-primary">Retour au stock</a>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="mouvements-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Article / Produit</th>
                            <th>Quantité</th>
                            <th>Avant</th>
                            <th>Après</th>
                            <th>Référence</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements as $m): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></td>
                                <td>
                                    <?php
                                    $badge = 'badge-' . $m['type'];
                                    $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                                    ?>
                                    <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($m['article_nom'] ?: ($m['produit_nom'] ?: '-')); ?>
                                </td>
                                <td><?php echo (int) $m['quantite']; ?></td>
                                <td><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></td>
                                <td><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></td>
                                <td>
                                    <?php
                                    if (!empty($m['reference_numero'])) {
                                        echo htmlspecialchars($m['reference_numero']);
                                    } elseif ($m['reference_type'] === 'commande' && $m['reference_id']) {
                                        echo 'Commande #' . (int) $m['reference_id'];
                                    } else {
                                        echo htmlspecialchars($m['reference_type'] ?? '-');
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($m['notes'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
