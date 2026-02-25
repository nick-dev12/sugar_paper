<?php
/**
 * Page d'ajout de produit
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Traiter le formulaire
require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_add_produit();

// Si l'ajout est réussi, rediriger vers la liste
if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}

// Récupérer les catégories pour le formulaire
require_once __DIR__ . '/../../models/model_categories.php';
$categories = get_all_categories();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Produit - Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css">
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header content-header-form">
        <h1><i class="fas fa-plus-circle"></i> Ajouter un Produit</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <section class="form-add-section">
    <div class="form-add-container">
        <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $result['message']; ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data" class="form-add">
            <div class="form-add-block">
                <h3 class="form-add-section-title"><i class="fas fa-info-circle"></i> Informations générales</h3>
                <div class="form-group">
                    <label for="nom">Nom du produit <span class="required">*</span></label>
                    <input type="text" id="nom" name="nom" required placeholder="Ex: Miel naturel pur"
                           value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <textarea id="description" name="description" required placeholder="Décrivez votre produit..." rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="categorie_id">Catégorie <span class="required">*</span></label>
                    <select id="categorie_id" name="categorie_id" required>
                        <option value="">Sélectionner une catégorie</option>
                        <?php if ($categories && count($categories) > 0): ?>
                            <?php foreach ($categories as $categorie): ?>
                                <option value="<?php echo $categorie['id']; ?>" 
                                    <?php echo (isset($_POST['categorie_id']) && $_POST['categorie_id'] == $categorie['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categorie['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Aucune catégorie disponible</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$categories || count($categories) == 0): ?>
                        <small class="form-help form-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Aucune catégorie disponible. <a href="../categories/ajouter.php" class="link-accent">Créer une catégorie</a>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-add-block">
                <h3 class="form-add-section-title"><i class="fas fa-tag"></i> Prix et stock</h3>
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="prix">Prix (FCFA) <span class="required">*</span></label>
                        <input type="number" id="prix" name="prix" step="0.01" min="0" required placeholder="0"
                               value="<?php echo isset($_POST['prix']) ? htmlspecialchars($_POST['prix']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="prix_promotion">Prix promotionnel (FCFA)</label>
                        <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0" placeholder="Optionnel"
                               value="<?php echo isset($_POST['prix_promotion']) ? htmlspecialchars($_POST['prix_promotion']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="stock">Stock <span class="required">*</span></label>
                        <input type="number" id="stock" name="stock" min="0" required placeholder="0"
                               value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0'; ?>">
                    </div>
                </div>
            </div>

            <div class="form-add-block">
                <h3 class="form-add-section-title"><i class="fas fa-image"></i> Image et statut</h3>
                <div class="form-group">
                    <label for="image_principale">Image principale <span class="required">*</span></label>
                    <div class="file-input-wrapper">
                        <input type="file" id="image_principale" name="image_principale" accept="image/*" required class="file-input">
                        <label for="image_principale" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choisir une image</span>
                            <small>JPG, PNG, GIF, WEBP — max 5 Mo</small>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="statut">Statut du produit</label>
                    <select id="statut" name="statut">
                        <option value="actif" <?php echo (!isset($_POST['statut']) || $_POST['statut'] == 'actif') ? 'selected' : ''; ?>>Actif (visible en boutique)</option>
                        <option value="inactif" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif (masqué)</option>
                    </select>
                </div>
            </div>

            <div class="form-add-actions">
                <button type="submit" class="btn-primary btn-submit-large">
                    <i class="fas fa-save"></i> Enregistrer le produit
                </button>
                <a href="index.php" class="btn-cancel">Annuler</a>
            </div>
        </form>
    </div>
    </section>

    <?php include '../includes/footer.php'; ?>
