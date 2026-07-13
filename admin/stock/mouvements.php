<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Page historique des mouvements de stock
 * Filtres: catégorie, produit, type
 */

session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../models/model_mouvements_stock.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_produits.php';

$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : null;
$produit_id = isset($_GET['produit_id']) ? (int) $_GET['produit_id'] : null;
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['entree', 'sortie', 'inventaire']) ? $_GET['type'] : null;

$mouvements = get_stock_mouvements(null, $produit_id, $categorie_id, $type_filter, 200);
$categories = get_all_categories();

if ($categorie_id > 0) {
    $produits = get_produits_by_categorie($categorie_id);
} else {
    $produits = get_all_produits();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mouvements de Stock - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-stock-mouvements.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-stock-mouvements">
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
        <div class="mouvements-filters-card">
            <h3><i class="fas fa-filter"></i> Filtres</h3>
            <form method="GET" action="">
                <div class="mouvements-filters">
                    <div class="filter-group filter-group--categorie">
                        <label for="categorie_id"><i class="fas fa-tags"></i> Catégorie</label>
                        <select name="categorie_id" id="categorie_id">
                            <option value="">Toutes</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo $categorie_id === (int) $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nom']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group filter-group--type">
                        <label for="type"><i class="fas fa-exchange-alt"></i> Type</label>
                        <select name="type" id="type">
                            <option value="">Tous</option>
                            <option value="entree" <?php echo $type_filter === 'entree' ? 'selected' : ''; ?>>Entrées</option>
                            <option value="sortie" <?php echo $type_filter === 'sortie' ? 'selected' : ''; ?>>Sorties</option>
                            <option value="inventaire" <?php echo $type_filter === 'inventaire' ? 'selected' : ''; ?>>Inventaires</option>
                        </select>
                    </div>
                    <div class="filter-group filter-group--produit">
                        <label for="produit_id"><i class="fas fa-box"></i> Produit</label>
                        <select name="produit_id" id="produit_id">
                            <option value="">Tous</option>
                            <?php foreach ($produits as $p): ?>
                            <option value="<?php echo (int) $p['id']; ?>" <?php echo $produit_id === (int) $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nom']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                        <a href="mouvements.php" class="btn-reset">
                            <i class="fas fa-rotate-left"></i> Réinitialiser
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="mouvements-section">
            <h2><i class="fas fa-list"></i> Mouvements (<?php echo count($mouvements); ?>)</h2>
            <?php if (empty($mouvements)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>Aucun mouvement enregistré<?php echo ($categorie_id || $produit_id || $type_filter) ? ' pour ces critères.' : '.'; ?></p>
                    <?php if ($categorie_id || $produit_id || $type_filter): ?>
                    <a href="mouvements.php" class="btn-primary">
                        <i class="fas fa-rotate-left"></i> Voir tous les mouvements
                    </a>
                    <?php else: ?>
                    <a href="index.php" class="btn-primary">
                        <i class="fas fa-arrow-left"></i> Retour au stock
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mouvements-table-wrap">
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
                                        <?php echo htmlspecialchars($m['produit_nom'] ?? '-'); ?>
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
                <div class="mouvements-cards">
                    <?php foreach ($mouvements as $m):
                        $badge = 'badge-' . $m['type'];
                        $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                        $ref = !empty($m['reference_numero']) ? htmlspecialchars($m['reference_numero']) : ($m['reference_type'] === 'commande' && $m['reference_id'] ? 'Commande #' . (int) $m['reference_id'] : htmlspecialchars($m['reference_type'] ?? '-'));
                    ?>
                    <div class="mouvement-card">
                        <div class="mouvement-card-header">
                            <span class="mouvement-card-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></span>
                            <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                        </div>
                        <div class="mouvement-card-body">
                            <div class="mouvement-card-row">
                                <span class="label">Article / Produit</span>
                                <span class="value"><?php echo htmlspecialchars($m['produit_nom'] ?? '-'); ?></span>
                            </div>
                            <div class="mouvement-card-row">
                                <span class="label">Quantité</span>
                                <span class="value"><?php echo (int) $m['quantite']; ?></span>
                            </div>
                            <div class="mouvement-card-row">
                                <span class="label">Avant</span>
                                <span class="value"><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></span>
                            </div>
                            <div class="mouvement-card-row">
                                <span class="label">Après</span>
                                <span class="value"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></span>
                            </div>
                            <div class="mouvement-card-row">
                                <span class="label">Référence</span>
                                <span class="value"><?php echo $ref; ?></span>
                            </div>
                        </div>
                        <?php if (!empty($m['notes'])): ?>
                        <div class="mouvement-card-notes"><?php echo htmlspecialchars($m['notes']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
