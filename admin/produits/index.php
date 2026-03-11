<?php
/**
 * Page de liste des produits
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

// Récupérer tous les produits
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
$produits = get_all_produits();
$categories = get_all_categories();
$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;

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
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Produits - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .admin-filters-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
            margin-bottom: 20px;
            padding: 16px;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 12px;
        }

        .admin-filter-field {
            flex: 1 1 220px;
        }

        .admin-filter-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #6b2f20;
        }

        .admin-filter-field input,
        .admin-filter-field select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #d9d9d9;
            border-radius: 10px;
            background: #fff;
        }

        .admin-filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-filter-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 10px;
            border: 1px solid #d9d9d9;
            color: #6b2f20;
            background: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .produit-card-linkable {
            cursor: pointer;
        }

        .produit-card-linkable:hover .produit-card-nom {
            color: #c26638;
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-box"></i> Liste des Produits</h1>
        <div class="header-actions">
            <a href="ajouter.php" class="btn-primary">
                <i class="fas fa-upload"></i> Publier un produit
            </a>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-box"></i> Tous les Produits (<?php echo count($produits); ?>)</h2>
        </div>

        <form method="GET" action="" class="admin-filters-bar">
            <div class="admin-filter-field">
                <label for="recherche">Recherche</label>
                <input type="text" id="recherche" name="recherche" placeholder="Nom, description, statut..."
                    value="<?php echo htmlspecialchars($recherche); ?>">
            </div>
            <div class="admin-filter-field">
                <label for="categorie_id">Catégorie</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ($categories as $categorie): ?>
                        <option value="<?php echo (int) $categorie['id']; ?>"
                            <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categorie['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Filtrer
                </button>
                <a href="index.php" class="btn-filter-reset">
                    <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                </a>
            </div>
        </form>

        <?php if (empty($produits)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>Aucun produit enregistré pour le moment.</p>
                <a href="ajouter.php" class="btn-primary">
                    <i class="fas fa-upload"></i> Publier le premier produit
                </a>
            </div>
        <?php else: ?>
            <div class="produits-grid">
                <?php foreach ($produits as $produit): ?>
                    <div class="produit-card produit-card-linkable"
                        data-href="modifier.php?id=<?php echo (int) $produit['id']; ?>">
                        <?php
                        $statut_class = 'statut-actif';
                        if ($produit['statut'] == 'inactif') {
                            $statut_class = 'statut-inactif';
                        } elseif ($produit['statut'] == 'rupture_stock') {
                            $statut_class = 'statut-rupture';
                        }
                        $statut_label = ucfirst(str_replace('_', ' ', $produit['statut']));
                        ?>
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
                                <a href="ajuster-stock.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-stock" title="Ajuster le stock">
                                    <i class="fas fa-boxes-stacked"></i> Stock
                                </a>
                                <a href="modifier.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-edit">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <a href="supprimer.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-delete"
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

    <?php include '../includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>