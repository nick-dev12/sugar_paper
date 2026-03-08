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

// Récupérer les catégories et articles en stock
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_stock.php';
$categories = get_all_categories();

$stock_article_id = isset($_GET['stock_article_id']) ? (int) $_GET['stock_article_id'] : 0;
if (!$stock_article_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stock_article_id']) && (int)$_POST['stock_article_id'] > 0) {
    $stock_article_id = (int) $_POST['stock_article_id'];
}
$no_stock = isset($_GET['no_stock']) && $_GET['no_stock'] === '1';
$article = null;
if ($stock_article_id > 0) {
    $article = get_stock_article_by_id($stock_article_id);
    if (!$article) {
        $stock_article_id = 0;
    }
}

// Afficher la sélection stock uniquement si pas d'article choisi et pas "no_stock"
$show_stock_selection = ($stock_article_id <= 0 && !$no_stock);
$recherche_stock = trim($_GET['recherche_stock'] ?? '');
$categorie_stock = isset($_GET['categorie_stock']) ? (int) $_GET['categorie_stock'] : 0;
$articles_stock = $show_stock_selection ? search_stock_articles($recherche_stock, $categorie_stock > 0 ? $categorie_stock : null, 50) : get_all_stock_articles();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publier un produit - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header content-header-form">
        <h1><i class="fas fa-upload"></i> Publier un produit</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <section class="form-add-section">
    <div class="form-add-container">
            <?php if ($show_stock_selection): ?>
                <div class="form-add-block" style="margin-bottom: 30px;">
                    <h3 class="form-add-section-title"><i class="fas fa-boxes-stacked"></i> Choisir un article en stock</h3>
                    <p class="form-help" style="margin-bottom: 16px;">Sélectionnez un article en stock pour publier un produit. Les informations (nom, image, catégorie, stock) proviendront de l'article.</p>
                    <form method="GET" action="" class="stock-search-bar">
                        <div class="stock-search-input-wrap">
                            <i class="fas fa-search stock-search-icon"></i>
                            <input type="text" id="recherche_stock" name="recherche_stock" placeholder="Rechercher un article par nom..."
                                value="<?php echo htmlspecialchars($recherche_stock); ?>" class="stock-search-input">
                        </div>
                        <div class="stock-search-select-wrap">
                            <select id="categorie_stock" name="categorie_stock" class="stock-search-select">
                                <option value="0">Toutes les catégories</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo (int) $c['id']; ?>" <?php echo $categorie_stock === (int) $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary stock-search-btn">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </form>
                    <?php if (empty($articles_stock)): ?>
                        <p class="form-help" style="margin-bottom: 12px;">Aucun article en stock. <a href="../stock/index.php" class="link-accent">Ajouter des articles au stock</a> ou créez un produit sans lien.</p>
                    <?php else: ?>
                        <div class="stock-select-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px;">
                            <?php foreach ($articles_stock as $a): ?>
                                <div class="stock-select-card" style="border: 1px solid #ececec; border-radius: 10px; overflow: hidden; background: #fff;">
                                    <img src="/upload/<?php echo htmlspecialchars($a['image_principale'] ?? ''); ?>" alt="" style="width: 100%; height: 120px; object-fit: cover;" onerror="this.src='/image/produit1.jpg'">
                                    <div style="padding: 12px;">
                                        <strong style="font-size: 14px;"><?php echo htmlspecialchars($a['nom']); ?></strong>
                                        <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;"><?php echo htmlspecialchars($a['categorie_nom'] ?? ''); ?> · Stock: <?php echo (int) $a['quantite']; ?></p>
                                        <a href="ajouter.php?stock_article_id=<?php echo (int) $a['id']; ?>" class="btn-primary" style="display: block; text-align: center; margin-top: 10px; padding: 8px; font-size: 13px;">
                                            <i class="fas fa-upload"></i> Publier un produit
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p style="margin-top: 20px;">
                        <a href="ajouter.php?no_stock=1" class="link-accent"><i class="fas fa-edit"></i> Créer un produit sans lien au stock</a>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!$show_stock_selection): ?>
        <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $result['message']; ?></span>
            </div>
        <?php endif; ?>
        
            <form method="POST" action="<?php echo $stock_article_id > 0 ? '?stock_article_id=' . $stock_article_id : ''; ?>" enctype="multipart/form-data" class="form-add">
                <div class="form-add-block" style="margin-bottom: 24px;">
                    <h3 class="form-add-section-title"><i class="fas fa-boxes-stacked"></i> Lier à un article en stock</h3>
                    <?php if ($article): ?>
                        <input type="hidden" name="stock_article_id" value="<?php echo $stock_article_id; ?>">
                        <p class="form-help" style="margin: 0; padding: 12px 16px; background: #f0f7f0; border-radius: 8px; border-left: 4px solid #918a44;">
                            <i class="fas fa-link"></i> Publication selon l'article : <strong><?php echo htmlspecialchars($article['nom']); ?></strong>
                            <a href="ajouter.php" style="margin-left: 12px; font-size: 13px; color: #918a44;">Changer d'article</a>
                        </p>
                    <?php else: ?>
                    <div class="form-group">
                        <select id="stock_article_id" name="stock_article_id">
                            <option value="">Aucun (stock géré manuellement)</option>
                            <?php foreach ($articles_stock as $a): ?>
                                <option value="<?php echo (int) $a['id']; ?>">
                                    <?php echo htmlspecialchars($a['nom']); ?> (<?php echo (int) $a['quantite']; ?> en stock)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-help">Si lié, le stock proviendra de l'article et sera décrémenté automatiquement à chaque vente. <a href="../stock/index.php" class="link-accent">Gérer le stock</a></small>
                    </div>
                    <?php endif; ?>
                </div>

            <div class="form-add-block">
                <h3 class="form-add-section-title"><i class="fas fa-info-circle"></i> Informations générales</h3>
                <div class="form-group">
                    <label for="nom">Nom du produit <span class="required">*</span></label>
                    <input type="text" id="nom" name="nom" required placeholder="Ex: Miel naturel pur"
                            value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ($article ? htmlspecialchars($article['nom']) : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" required placeholder="Décrivez votre produit..."
                            rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                    <?php if ($article): ?>
                        <input type="hidden" name="categorie_id" value="<?php echo (int) $article['categorie_id']; ?>">
                        <input type="hidden" name="stock" value="<?php echo (int) $article['quantite']; ?>">
                        <p class="form-help" style="margin: 0; padding: 10px 14px; background: #f0f7f0; border-radius: 8px; border-left: 4px solid #918a44;">
                            <i class="fas fa-info-circle"></i> Catégorie et stock proviennent de l'article : <strong><?php echo htmlspecialchars($article['categorie_nom'] ?? ''); ?></strong> · Stock : <?php echo (int) $article['quantite']; ?>
                        </p>
                    <?php else: ?>
                <div class="form-group">
                    <label for="categorie_id">Catégorie <span class="required">*</span></label>
                    <select id="categorie_id" name="categorie_id" required>
                        <option value="">Sélectionner une catégorie</option>
                        <?php if ($categories && count($categories) > 0): ?>
                            <?php foreach ($categories as $categorie): ?>
                                    <option value="<?php echo $categorie['id']; ?>" <?php echo (isset($_POST['categorie_id']) && $_POST['categorie_id'] == $categorie['id']) ? 'selected' : ''; ?>>
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
                    <?php endif; ?>
            </div>

            <div class="form-add-block">
                    <h3 class="form-add-section-title"><i class="fas fa-tag"></i> Prix<?php if (!$article): ?> et stock<?php endif; ?></h3>
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="prix">Prix (FCFA) <span class="required">*</span></label>
                        <input type="number" id="prix" name="prix" step="0.01" min="0" required placeholder="0"
                               value="<?php echo isset($_POST['prix']) ? htmlspecialchars($_POST['prix']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="prix_promotion">Prix promotionnel (FCFA)</label>
                            <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0"
                                placeholder="Optionnel"
                               value="<?php echo isset($_POST['prix_promotion']) ? htmlspecialchars($_POST['prix_promotion']) : ''; ?>">
                    </div>

                        <?php if (!$article): ?>
                    <div class="form-group">
                        <label for="stock">Stock <span class="required">*</span></label>
                        <input type="number" id="stock" name="stock" min="0" required placeholder="0"
                               value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0'; ?>">
                        </div>
                        <?php endif; ?>
                </div>
            </div>

            <div class="form-add-block">
                    <h3 class="form-add-section-title"><i class="fas fa-ruler"></i> Poids, couleurs et tailles
                        (optionnel)</h3>
                    <p class="form-help" style="margin-bottom: 15px;">Pour chaque poids ou taille, vous pouvez ajouter
                        un montant optionnel (+ FCFA) qui s'additionne au prix de base lorsque le client choisit cette
                        option.</p>
                <div class="form-group-row">
                    <div class="form-group">
                            <label>Poids disponibles</label>
                            <div class="options-add-block options-with-surcharge">
                                <div class="options-add-row">
                                    <input type="text" id="poids-input" placeholder="Ex: 500g, 1kg"
                                        class="options-input">
                                    <input type="number" id="poids-surcharge" placeholder="+ FCFA" min="0" step="1"
                                        class="options-surcharge" title="Montant à ajouter au prix">
                                    <button type="button" class="btn-add-option" id="btn-add-poids">
                                        <i class="fas fa-plus"></i> Ajouter
                                    </button>
                                </div>
                                <div id="poids-list" class="options-tags-list options-tags-with-surcharge"></div>
                                <input type="hidden" name="poids" id="poids-hidden"
                               value="<?php echo isset($_POST['poids']) ? htmlspecialchars($_POST['poids']) : ''; ?>">
                    </div>
                            <small class="form-help">Poids + montant optionnel (ex: 1kg + 300). Laissez vide pour
                                0.</small>
                        </div>
                        <!-- <div class="form-group">
                            <label for="unite">Unité par défaut</label>
                        <select id="unite" name="unite">
                                <option value="unité" <?php echo (!isset($_POST['unite']) || $_POST['unite'] == 'unité') ? 'selected' : ''; ?>>
                                    Unité</option>
                                <option value="kg" <?php echo (isset($_POST['unite']) && $_POST['unite'] == 'kg') ? 'selected' : ''; ?>>
                                    Kilogramme</option>
                                <option value="g" <?php echo (isset($_POST['unite']) && $_POST['unite'] == 'g') ? 'selected' : ''; ?>>
                                    Gramme</option>
                                <option value="L" <?php echo (isset($_POST['unite']) && $_POST['unite'] == 'L') ? 'selected' : ''; ?>>
                                    Litre</option>
                        </select>
                        </div> -->
                </div>
                <div class="form-group-row">
                    <div class="form-group">
                        <label>Couleurs disponibles (optionnel)</label>
                        <div class="couleurs-picker-block">
                            <div class="couleurs-add-row">
                                <input type="color" id="couleur-input" value="#E5488A" title="Choisir une couleur">
                                <button type="button" class="btn-add-couleur" id="btn-add-couleur">
                                    <i class="fas fa-plus"></i> Ajouter cette couleur
                                </button>
                            </div>
                            <div id="couleurs-list" class="couleurs-swatches"></div>
                                <input type="hidden" name="couleurs" id="couleurs-hidden"
                                    value="<?php echo isset($_POST['couleurs']) ? htmlspecialchars($_POST['couleurs']) : ''; ?>">
                            </div>
                            <small class="form-help">Cliquez sur la pastille pour choisir une couleur, puis sur «
                                Ajouter ». Vous pouvez ajouter plusieurs couleurs.</small>
                        </div>

                </div>
            </div>

            <div class="form-add-block">
                <h3 class="form-add-section-title"><i class="fas fa-image"></i> Images du produit</h3>
                    <?php if ($article && !empty($article['image_principale'])): ?>
                        <p class="form-help" style="margin-bottom: 12px;">
                            <i class="fas fa-check-circle" style="color: #918a44;"></i> Image de l'article en stock utilisée par défaut. Vous pouvez ajouter d'autres images pour la galerie.
                        </p>
                    <?php endif; ?>
                <div class="form-group">
                        <label>Images <?php if (!$article): ?><span class="required">*</span><?php endif; ?> <small
                                style="font-weight: normal; color: #666;">(1ère = principale, les autres pour la
                                galerie)</small></label>
                        <div class="file-input-wrapper file-input-single"
                            onclick="document.getElementById('images_produit').click()">
                            <input type="file" id="images_produit" name="images_produit[]" accept="image/*" multiple
                                <?php if (!$article): ?>required<?php endif; ?>
                                class="file-input" style="display: none;">
                        <label class="file-input-label" style="cursor: pointer; margin: 0;">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Cliquer pour ajouter des images</span>
                            <small>Une ou plusieurs à la fois — JPG, PNG, GIF, WEBP</small>
                        </label>
                    </div>
                    <div id="preview-images" class="image-preview-accumulator"></div>
                </div>

                <div class="form-group">
                    <label for="statut">Statut du produit</label>
                    <select id="statut" name="statut">
                            <option value="actif" <?php echo (!isset($_POST['statut']) || $_POST['statut'] == 'actif') ? 'selected' : ''; ?>>
                                Actif (visible en boutique)</option>
                            <option value="inactif" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'inactif') ? 'selected' : ''; ?>>
                                Inactif (masqué)</option>
                    </select>
                </div>
            </div>

                <div class="form-add-block form-add-block-variantes">
                    <h3 class="form-add-section-title"><i class="fas fa-layer-group"></i> Variantes du produit
                        (optionnel)</h3>
                    <p class="form-help" style="margin-bottom: 15px;">Ajoutez des variantes avec un nom, un prix et une
                        image différents du produit de base. Les options couleur, poids et taille s'appliquent aussi aux
                        variantes.</p>
                    <div id="variantes-container" class="variantes-container">
                        <div class="variante-item" data-index="0">
                            <div class="variante-row">
                                <input type="text" name="variantes_nom[]" placeholder="Nom (ex: Format familial)"
                                    class="variante-nom">
                                <input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01"
                                    class="variante-prix">
                                <input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0"
                                    step="0.01" class="variante-prix-promo">
                                <div class="variante-image-wrap">
                                    <div class="variante-image-area">
                                        <input type="file" name="variantes_image[]" accept="image/*"
                                            class="variante-image-input">
                                        <span class="variante-image-label"><i class="fas fa-image"></i> Image</span>
                                        <img class="variante-preview-img" src="" alt="" style="display: none;">
                                    </div>
                                </div>
                                <button type="button" class="btn-remove-variante" title="Supprimer">&times;</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="btn-add-variante" class="btn-add-variante"><i class="fas fa-plus"></i>
                        Ajouter une variante</button>
                </div>

            <div class="form-add-actions">
                <button type="submit" class="btn-primary btn-submit-large">
                        <i class="fas fa-upload"></i> Publier le produit
                </button>
                <a href="index.php" class="btn-cancel">Annuler</a>
            </div>
        </form>
            <?php endif; ?>
    </div>
    </section>

    <style>
        .stock-search-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: stretch;
            padding: 20px;
            background: linear-gradient(135deg, #f8f7f2 0%, #f0efe8 100%);
            border: 1px solid #e5e3d8;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .stock-search-input-wrap {
            flex: 1 1 280px;
            position: relative;
        }
        .stock-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #918a44;
            font-size: 16px;
        }
        .stock-search-input {
            width: 100%;
            padding: 14px 14px 14px 44px;
            border: 2px solid #e5e3d8;
            border-radius: 10px;
            font-size: 15px;
            background: #fff;
            transition: border-color 0.2s;
        }
        .stock-search-input:focus {
            outline: none;
            border-color: #918a44;
            box-shadow: 0 0 0 3px rgba(145, 138, 68, 0.15);
        }
        .stock-search-select-wrap {
            flex: 0 1 220px;
        }
        .stock-search-select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e3d8;
            border-radius: 10px;
            font-size: 15px;
            background: #fff;
            color: #333;
        }
        .stock-search-select:focus {
            outline: none;
            border-color: #918a44;
        }
        .stock-search-btn {
            padding: 14px 24px;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .file-input-single {
            cursor: pointer;
        }

        .image-preview-accumulator {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }

        .image-preview-accumulator .preview-item {
            position: relative;
        }

        .image-preview-accumulator .preview-item img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid rgba(229, 72, 138, 0.3);
            display: block;
        }

        .image-preview-accumulator .preview-item .preview-badge {
            position: absolute;
            top: 4px;
            left: 4px;
            background: var(--couleur-dominante, #E5488A);
            color: #fff;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .image-preview-accumulator .preview-item .preview-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border: none;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            line-height: 1;
        }

        .image-preview-accumulator .preview-item .preview-remove:hover {
            background: #c00;
        }

        .couleurs-picker-block {
            margin-top: 8px;
        }

        .couleurs-add-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .couleurs-add-row input[type="color"] {
            width: 50px;
            height: 40px;
            padding: 2px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-add-couleur {
            padding: 12px 18px;
            background: #918a44;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add-couleur:hover {
            background: #7a7340;
        }

        .couleurs-swatches {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px 0;
        }

        .couleur-swatch {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #f5f5f5;
            border-radius: 20px;
            border: 2px solid #ddd;
        }

        .couleur-swatch .swatch-preview {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #333;
        }

        .couleur-swatch .swatch-hex {
            font-size: 12px;
            color: #333;
        }

        .couleur-swatch .swatch-remove {
            width: 24px;
            height: 24px;
            border: none;
            background: #c00;
            color: #fff;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            line-height: 1;
        }

        .couleur-swatch .swatch-remove:hover {
            background: #a00;
        }

        .options-add-block {
            margin-top: 8px;
        }

        .options-add-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .options-input {
            flex: 1;
            min-width: 150px;
            padding: 10px 14px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .options-input:focus {
            outline: none;
            border-color: #918a44;
        }

        .btn-add-option {
            padding: 10px 16px;
            background: #918a44;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add-option:hover {
            background: #7a7340;
        }

        .options-tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px 0;
        }

        .option-tag {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f5f5f5;
            border-radius: 20px;
            border: 2px solid #ddd;
            font-size: 13px;
        }

        .option-tag .tag-remove {
            width: 22px;
            height: 22px;
            border: none;
            background: #c00;
            color: #fff;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            line-height: 1;
        }

        .option-tag .tag-remove:hover {
            background: #a00;
        }

        .options-surcharge {
            width: 90px;
            padding: 8px 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
        }

        .option-tag .tag-surcharge {
            font-size: 11px;
            color: #666;
            margin-left: 4px;
        }

        .form-add-block-variantes {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid #eee;
        }

        .variantes-container {
            margin-bottom: 15px;
        }

        .variante-item {
            margin-bottom: 12px;
            padding: 16px;
            background: #f9f9f9;
            border-radius: 10px;
            border: 1px solid #e8e8e8;
        }

        .variante-row {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .variante-nom {
            flex: 1;
            min-width: 180px;
            padding: 10px 14px;
            border: 2px solid #ddd;
            border-radius: 8px;
        }

        .variante-prix,
        .variante-prix-promo {
            width: 110px;
            padding: 10px 14px;
            border: 2px solid #ddd;
            border-radius: 8px;
        }

        .variante-image-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .variante-image-area {
            position: relative;
            min-width: 100px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            overflow: hidden;
        }

        .variante-image-area:hover {
            border-color: #918a44;
            background: #fafaf8;
        }

        .variante-image-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 1;
        }

        .variante-image-label {
            padding: 8px 14px;
            color: #918a44;
            font-size: 13px;
        }

        .variante-preview-img {
            max-width: 90px;
            max-height: 70px;
            object-fit: cover;
            border-radius: 6px;
            margin: 4px;
        }

        .btn-remove-variante {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border: none;
            background: #c26638;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }

        .btn-remove-variante:hover {
            background: #a55a30;
        }

        .btn-add-variante {
            padding: 12px 20px;
            background: #918a44;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add-variante:hover {
            background: #7a7340;
        }
    </style>
    <script>
        (function () {
            var input = document.getElementById('images_produit');
            var container = document.getElementById('preview-images');
            var accumulatedFiles = [];

            function updateInputFiles() {
                var dt = new DataTransfer();
                for (var i = 0; i < accumulatedFiles.length; i++) {
                    dt.items.add(accumulatedFiles[i]);
                }
                input.files = dt.files;
            }

            function addPreviews(newFiles) {
                for (var i = 0; i < newFiles.length; i++) {
                    (function (file, idx) {
                        if (!file.type.match('image.*')) return;
                        var pos = accumulatedFiles.length;
                        accumulatedFiles.push(file);
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            var div = document.createElement('div');
                            div.className = 'preview-item';
                            div.dataset.index = pos;
                            var badge = document.createElement('span');
                            badge.className = 'preview-badge';
                            badge.textContent = pos === 0 ? 'Principale' : (pos + 1);
                            var img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Aperçu ' + (pos + 1);
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'preview-remove';
                            btn.innerHTML = '&times;';
                            btn.title = 'Retirer';
                            btn.onclick = function (ev) {
                                ev.preventDefault();
                                ev.stopPropagation();
                                var idx = parseInt(div.dataset.index, 10);
                                accumulatedFiles.splice(idx, 1);
                                div.remove();
                                for (var j = 0; j < container.children.length; j++) {
                                    container.children[j].dataset.index = j;
                                    container.children[j].querySelector('.preview-badge').textContent =
                                        j === 0 ? 'Principale' : (j + 1);
                                }
                                updateInputFiles();
                            };
                            div.appendChild(badge);
                            div.appendChild(img);
                            div.appendChild(btn);
                            container.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    })(newFiles[i], i);
                }
                updateInputFiles();
            }

            input.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    var newFiles = [];
                    for (var i = 0; i < this.files.length; i++) {
                        newFiles.push(this.files[i]);
                    }
                    addPreviews(newFiles);
                }
            });

            document.querySelector('.form-add').addEventListener('submit', function (e) {
                if (accumulatedFiles.length === 0) {
                    e.preventDefault();
                    alert('Veuillez ajouter au moins une image.');
                    return false;
                }
            });
        })();
        (function () {
            var couleurInput = document.getElementById('couleur-input');
            var btnAdd = document.getElementById('btn-add-couleur');
            var list = document.getElementById('couleurs-list');
            var hidden = document.getElementById('couleurs-hidden');
            var couleurs = [];
            try {
                if (hidden && hidden.value) {
                    var parsed = JSON.parse(hidden.value);
                    if (Array.isArray(parsed)) couleurs = parsed;
                }
            } catch (e) { }

            function updateHidden() {
                if (hidden) hidden.value = JSON.stringify(couleurs);
            }

            function render() {
                if (!list) return;
                list.innerHTML = '';
                couleurs.forEach(function (hex, i) {
                    var div = document.createElement('div');
                    div.className = 'couleur-swatch';
                    div.innerHTML = '<span class="swatch-preview" style="background:' + hex +
                        '"></span><span class="swatch-hex">' + hex +
                        '</span><button type="button" class="swatch-remove" data-i="' + i +
                        '" title="Retirer">&times;</button>';
                    list.appendChild(div);
                });
                updateHidden();
            }
            if (btnAdd && couleurInput) {
                btnAdd.addEventListener('click', function () {
                    var hex = couleurInput.value;
                    if (hex && couleurs.indexOf(hex) === -1) {
                        couleurs.push(hex);
                        render();
                    }
                });
            }
            if (list) {
                list.addEventListener('click', function (e) {
                    var btn = e.target.closest('.swatch-remove');
                    if (btn) {
                        var i = parseInt(btn.dataset.i, 10);
                        couleurs.splice(i, 1);
                        render();
                    }
                });
            }
            render();
        })();
        (function () {
            function initOptionsWithSurcharge(idInput, idSurcharge, idList, idHidden, btnId) {
                var input = document.getElementById(idInput);
                var surchargeInput = document.getElementById(idSurcharge);
                var list = document.getElementById(idList);
                var hidden = document.getElementById(idHidden);
                var btn = document.getElementById(btnId);
                var values = [];
                try {
                    if (hidden && hidden.value && hidden.value !== '[]') {
                        var parsed = JSON.parse(hidden.value);
                        if (Array.isArray(parsed)) values = parsed;
                        else values = (hidden.value.split(',').map(function (s) {
                            return {
                                v: s.trim(),
                                s: 0
                            };
                        })).filter(function (x) {
                            return x.v && x.v !== '[]';
                        });
                    }
                } catch (e) {
                    if (hidden && hidden.value && hidden.value !== '[]') {
                        values = hidden.value.split(',').map(function (s) {
                            return {
                                v: s.trim(),
                                s: 0
                            };
                        }).filter(function (x) {
                            return x.v && x.v !== '[]';
                        });
                    }
                }
                values = values.filter(function (item) {
                    var v = typeof item === 'object' ? item.v : item;
                    return v && v !== '[]' && String(v).trim() !== '';
                });

                function updateHidden() {
                    if (hidden) hidden.value = JSON.stringify(values);
                }

                function render() {
                    if (!list) return;
                    list.innerHTML = '';
                    values.forEach(function (item, i) {
                        var v = typeof item === 'object' ? item.v : item;
                        var s = typeof item === 'object' ? (item.s || 0) : 0;
                        var surc = s > 0 ? ' <span class="tag-surcharge">+' + s + ' FCFA</span>' : '';
                        var div = document.createElement('div');
                        div.className = 'option-tag';
                        div.innerHTML = '<span>' + (v.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + surc +
                            '</span><button type="button" class="tag-remove" data-i="' + i +
                            '" title="Retirer">&times;</button>';
                        list.appendChild(div);
                    });
                    updateHidden();
                }
                if (btn && input) {
                    btn.addEventListener('click', function () {
                        var val = (input.value || '').trim();
                        var surc = surchargeInput ? (parseInt(surchargeInput.value, 10) || 0) : 0;
                        if (val) {
                            var exists = values.some(function (x) {
                                return (typeof x === 'object' ? x.v : x) === val;
                            });
                            if (!exists) {
                                values.push({
                                    v: val,
                                    s: surc
                                });
                                input.value = '';
                                if (surchargeInput) surchargeInput.value = '';
                                render();
                            }
                        }
                    });
                    input.addEventListener('keypress', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            btn.click();
                        }
                    });
                }
                if (list) {
                    list.addEventListener('click', function (e) {
                        var b = e.target.closest('.tag-remove');
                        if (b) {
                            values.splice(parseInt(b.dataset.i, 10), 1);
                            render();
                        }
                    });
                }
                render();
            }
            initOptionsWithSurcharge('poids-input', 'poids-surcharge', 'poids-list', 'poids-hidden', 'btn-add-poids');
            initOptionsWithSurcharge('taille-input', 'taille-surcharge', 'taille-list', 'taille-hidden',
                'btn-add-taille');
        })();
        (function () {
            var container = document.getElementById('variantes-container');
            var btnAdd = document.getElementById('btn-add-variante');
            var idx = 1;

            function getVarianteRowHtml() {
                return '<div class="variante-row">' +
                    '<input type="text" name="variantes_nom[]" placeholder="Nom (ex: Format familial)" class="variante-nom">' +
                    '<input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01" class="variante-prix">' +
                    '<input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0" step="0.01" class="variante-prix-promo">' +
                    '<div class="variante-image-wrap">' +
                    '<div class="variante-image-area">' +
                    '<input type="file" name="variantes_image[]" accept="image/*" class="variante-image-input">' +
                    '<span class="variante-image-label"><i class="fas fa-image"></i> Image</span>' +
                    '<img class="variante-preview-img" src="" alt="" style="display: none;">' +
                    '</div></div>' +
                    '<button type="button" class="btn-remove-variante" title="Supprimer">&times;</button></div>';
            }

            function previewVarianteImage(input) {
                var wrap = input.closest('.variante-image-wrap');
                if (!wrap) return;
                var img = wrap.querySelector('.variante-preview-img');
                var label = wrap.querySelector('.variante-image-label');
                if (!img || !label) return;
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                        label.style.display = 'none';
                    };
                    reader.readAsDataURL(input.files[0]);
                } else {
                    img.src = '';
                    img.style.display = 'none';
                    label.style.display = '';
                }
            }
            if (container) {
                container.addEventListener('change', function (e) {
                    if (e.target.classList.contains('variante-image-input')) {
                        previewVarianteImage(e.target);
                    }
                });
            }
            if (btnAdd && container) {
                btnAdd.addEventListener('click', function () {
                    var div = document.createElement('div');
                    div.className = 'variante-item';
                    div.dataset.index = idx++;
                    div.innerHTML = getVarianteRowHtml();
                    container.appendChild(div);
                    div.querySelector('.btn-remove-variante').addEventListener('click', function () {
                        div.remove();
                    });
                });
                container.addEventListener('click', function (e) {
                    var b = e.target.closest('.btn-remove-variante');
                    if (b && container.children.length > 1) b.closest('.variante-item').remove();
                });
            }
        })();
    </script>
    <?php include '../includes/footer.php'; ?>