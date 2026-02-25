<?php
/**
 * Page de modification de produit
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID du produit
$produit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($produit_id <= 0) {
    header('Location: index.php');
    exit;
}

// Récupérer le produit
require_once __DIR__ . '/../../models/model_produits.php';
$produit = get_produit_by_id($produit_id);

if (!$produit) {
    header('Location: index.php');
    exit;
}

// Traiter le formulaire
require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_update_produit($produit_id);

// Si la modification est réussie, rediriger vers la liste
if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}

// Récupérer les catégories
require_once __DIR__ . '/../../models/model_categories.php';
$categories = get_all_categories();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Produit - Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin-dashboard.css">
    <style>
        .form-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: #6b2f20;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #f0e9e9;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #000000;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #918a44;
            box-shadow: 0 0 0 3px rgba(145, 138, 68, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .error-message {
            background: #fee;
            border-left: 4px solid #c26638;
            color: #6b2f20;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .btn-back {
            background: #e0e0e0;
            color: #6b2f20;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #d0d0d0;
        }

        .current-image {
            margin-top: 10px;
            max-width: 200px;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header">
        <h1><i class="fas fa-edit"></i> Modifier un Produit</h1>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

    <div class="form-container">
        <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nom">Nom du produit *</label>
                <input type="text" id="nom" name="nom" required
                       value="<?php echo htmlspecialchars($produit['nom']); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($produit['description']); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="prix">Prix (FCFA) *</label>
                    <input type="number" id="prix" name="prix" step="0.01" min="0" required
                           value="<?php echo $produit['prix']; ?>">
                </div>

                <div class="form-group">
                    <label for="prix_promotion">Prix promotionnel (FCFA)</label>
                    <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0"
                           value="<?php echo $produit['prix_promotion'] ?? ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="stock">Stock *</label>
                    <input type="number" id="stock" name="stock" min="0" required
                           value="<?php echo $produit['stock']; ?>">
                </div>

                <div class="form-group">
                    <label for="categorie_id">Catégorie *</label>
                    <select id="categorie_id" name="categorie_id" required>
                        <option value="">Sélectionner une catégorie</option>
                        <?php if ($categories && count($categories) > 0): ?>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($produit['categorie_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Aucune catégorie disponible</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$categories || count($categories) == 0): ?>
                        <small style="color: #c26638; font-size: 12px; display: block; margin-top: 5px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Aucune catégorie disponible. <a href="../categories/ajouter.php" style="color: #918a44;">Créer une catégorie</a>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="poids">Poids (ex: 500g, 1kg)</label>
                    <input type="text" id="poids" name="poids"
                           value="<?php echo htmlspecialchars($produit['poids'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="unite">Unité</label>
                    <select id="unite" name="unite">
                        <option value="unité" <?php echo ($produit['unite'] == 'unité') ? 'selected' : ''; ?>>Unité</option>
                        <option value="kg" <?php echo ($produit['unite'] == 'kg') ? 'selected' : ''; ?>>Kilogramme</option>
                        <option value="g" <?php echo ($produit['unite'] == 'g') ? 'selected' : ''; ?>>Gramme</option>
                        <option value="L" <?php echo ($produit['unite'] == 'L') ? 'selected' : ''; ?>>Litre</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="image_principale">Image principale</label>
                <?php if ($produit['image_principale']): ?>
                    <div>
                        <img src="../../upload/<?php echo htmlspecialchars($produit['image_principale']); ?>" 
                             alt="Image actuelle" class="current-image">
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">Image actuelle (laisser vide pour conserver)</p>
                    </div>
                <?php endif; ?>
                <input type="file" id="image_principale" name="image_principale" accept="image/*">
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Formats acceptés: JPG, PNG, GIF, WEBP (max 5MB)</small>
            </div>

            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="actif" <?php echo ($produit['statut'] == 'actif') ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo ($produit['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                    <option value="rupture_stock" <?php echo ($produit['statut'] == 'rupture_stock') ? 'selected' : ''; ?>>Rupture de stock</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Enregistrer les modifications
            </button>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>

