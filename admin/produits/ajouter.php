<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Page d'ajout de produit
 * Formulaire direct - stock gÃ©rÃ© via la colonne produits.stock (plus de lien stock_articles)
 */

session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_add_produit();

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    $categorie_id = isset($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : 0;
    if ($categorie_id > 0) {
        header('Location: ../categories/produits.php?id=' . $categorie_id);
    } else {
        header('Location: ../stock/index.php');
    }
    exit;
}

require_once __DIR__ . '/../../models/model_categories.php';
$categories = get_all_categories();
$categorie_id_prefill = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-form.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header content-header-form">
        <h1><i class="fas fa-plus"></i> Ajouter un produit</h1>
        <div class="header-actions">
            <?php if ($categorie_id_prefill > 0): ?>
            <a href="../categories/produits.php?id=<?php echo $categorie_id_prefill; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour aux produits
            </a>
            <?php else: ?>
            <a href="../stock/index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour au stock
            </a>
            <?php endif; ?>
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
        
            <form method="POST" action="" enctype="multipart/form-data" class="form-add form-add--v2">
            <div class="form-add-block">
                <h3 class="form-add-section-title">
                    <span class="form-step-num">1</span>
                    Informations gÃ©nÃ©rales
                </h3>
                <div class="form-group">
                    <label for="nom">Nom du produit <span class="required">*</span></label>
                    <input type="text" id="nom" name="nom" required placeholder="Ex: Miel naturel pur"
                            value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" required placeholder="DÃ©crivez votre produit, ses usages, son origineâ€¦"
                            rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="form-group-row form-group-row-2">
                <div class="form-group">
                    <label for="categorie_id">CatÃ©gorie <span class="required">*</span></label>
                    <select id="categorie_id" name="categorie_id" required>
                        <option value="">SÃ©lectionner une catÃ©gorie</option>
                        <?php if ($categories && count($categories) > 0): ?>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ((isset($_POST['categorie_id']) && $_POST['categorie_id'] == $c['id']) || ($categorie_id_prefill > 0 && $c['id'] == $categorie_id_prefill)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Aucune catÃ©gorie disponible</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$categories || count($categories) == 0): ?>
                        <small class="form-help form-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Aucune catÃ©gorie disponible. <a href="../categories/ajouter.php" class="link-accent">CrÃ©er une catÃ©gorie</a>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="statut">VisibilitÃ©</label>
                    <select id="statut" name="statut">
                            <option value="actif" <?php echo (!isset($_POST['statut']) || $_POST['statut'] == 'actif') ? 'selected' : ''; ?>>
                                Actif â€” visible en boutique</option>
                            <option value="inactif" <?php echo (isset($_POST['statut']) && $_POST['statut'] == 'inactif') ? 'selected' : ''; ?>>
                                Inactif â€” masquÃ©</option>
                    </select>
                </div>
                </div>

                <div class="form-group">
                    <label for="section_accueil">Section page d'accueil</label>
                    <select id="section_accueil" name="section_accueil">
                        <option value="">Aucune — n'apparaît pas sur l'accueil</option>
                        <?php foreach (get_produit_section_accueil_labels() as $section_key => $section_label): ?>
                        <option value="<?php echo htmlspecialchars($section_key); ?>" <?php echo ((isset($_POST['section_accueil']) && $_POST['section_accueil'] === $section_key) ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($section_label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Choisissez la section de la page d'accueil où afficher ce produit.</small>
                </div>
            </div>

            <div class="form-add-block">
                    <h3 class="form-add-section-title">
                        <span class="form-step-num">2</span>
                        Prix et stock
                    </h3>
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="prix">Prix (FCFA) <span class="required">*</span></label>
                        <input type="number" id="prix" name="prix" step="0.01" min="0" required placeholder="0"
                               value="<?php echo isset($_POST['prix']) ? htmlspecialchars($_POST['prix']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="prix_promotion">Prix promotionnel</label>
                            <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0"
                                placeholder="Optionnel"
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
                    <h3 class="form-add-section-title">
                        <span class="form-step-num">3</span>
                        Options du produit
                        <span class="form-step-optional">optionnel</span>
                    </h3>
                    <p class="form-help form-help-lead">Cliquez sur un bouton pour ouvrir le formulaire. Validez pour enregistrer lâ€™option. Un montant (+ FCFA) peut sâ€™ajouter au prix de base pour un poids ou une taille.</p>

                    <div class="options-cards-grid">
                        <article class="option-card" data-option="poids">
                            <header class="option-card-header">
                                <span class="option-card-icon" aria-hidden="true"><i class="fas fa-weight-hanging"></i></span>
                                <div class="option-card-heading">
                                    <h4>Poids</h4>
                                    <p>Formats proposÃ©s au client</p>
                                </div>
                                <span class="option-card-count" id="poids-count" hidden>0</span>
                            </header>
                            <div id="poids-list" class="options-tags-list options-tags-with-surcharge"></div>
                            <p class="option-empty-hint">Aucun poids ajoutÃ©</p>
                            <button type="button" class="btn-reveal-option" data-target="poids">
                                <i class="fas fa-plus"></i> Ajouter un poids
                            </button>
                            <div class="option-reveal-form" id="poids-form" hidden>
                                <label class="option-reveal-label" for="poids-input">LibellÃ© du poids</label>
                                <input type="text" id="poids-input" placeholder="Ex : 500g, 1kg" class="options-input" autocomplete="off">
                                <label class="option-reveal-label" for="poids-surcharge">Montant en plus (FCFA)</label>
                                <input type="number" id="poids-surcharge" placeholder="0" min="0" step="1" class="options-surcharge" title="Montant Ã  ajouter au prix">
                                <p class="option-reveal-hint">Laissez 0 sâ€™il nâ€™y a pas de surcoÃ»t (ex. 1kg + 300).</p>
                                <div class="option-reveal-actions">
                                    <button type="button" class="btn-validate-option" id="btn-add-poids">
                                        <i class="fas fa-check"></i> Valider
                                    </button>
                                    <button type="button" class="btn-cancel-option">Annuler</button>
                                </div>
                            </div>
                                <input type="hidden" name="poids" id="poids-hidden"
                               value="<?php echo isset($_POST['poids']) ? htmlspecialchars($_POST['poids']) : ''; ?>">
                        </article>

                        <article class="option-card" data-option="couleurs">
                            <header class="option-card-header">
                                <span class="option-card-icon option-card-icon-color" aria-hidden="true"><i class="fas fa-palette"></i></span>
                                <div class="option-card-heading">
                                    <h4>Couleurs</h4>
                                    <p>Teintes au choix</p>
                    </div>
                                <span class="option-card-count" id="couleurs-count" hidden>0</span>
                            </header>
                            <div id="couleurs-list" class="couleurs-swatches"></div>
                            <p class="option-empty-hint">Aucune couleur ajoutÃ©e</p>
                            <button type="button" class="btn-reveal-option" data-target="couleurs">
                                <i class="fas fa-plus"></i> Ajouter une couleur
                            </button>
                            <div class="option-reveal-form" id="couleurs-form" hidden>
                                <label class="option-reveal-label" for="couleur-input">Choisir la couleur</label>
                            <div class="couleurs-add-row">
                                <input type="color" id="couleur-input" value="#E5488A" title="Choisir une couleur">
                                    <span class="couleur-hex-preview" id="couleur-hex-preview">#E5488A</span>
                                </div>
                                <p class="option-reveal-hint">Cliquez sur la pastille, puis validez pour lâ€™ajouter.</p>
                                <div class="option-reveal-actions">
                                    <button type="button" class="btn-validate-option" id="btn-add-couleur">
                                        <i class="fas fa-check"></i> Valider
                                </button>
                                    <button type="button" class="btn-cancel-option">Annuler</button>
                                </div>
                            </div>
                                <input type="hidden" name="couleurs" id="couleurs-hidden"
                                    value="<?php echo isset($_POST['couleurs']) ? htmlspecialchars($_POST['couleurs']) : ''; ?>">
                        </article>

                        <article class="option-card" data-option="taille">
                            <header class="option-card-header">
                                <span class="option-card-icon option-card-icon-size" aria-hidden="true"><i class="fas fa-ruler-combined"></i></span>
                                <div class="option-card-heading">
                                    <h4>Tailles</h4>
                                    <p>Pointures ou coupes</p>
                                </div>
                                <span class="option-card-count" id="taille-count" hidden>0</span>
                            </header>
                            <div id="taille-list" class="options-tags-list options-tags-with-surcharge"></div>
                            <p class="option-empty-hint">Aucune taille ajoutÃ©e</p>
                            <button type="button" class="btn-reveal-option" data-target="taille">
                                <i class="fas fa-plus"></i> Ajouter une taille
                            </button>
                            <div class="option-reveal-form" id="taille-form" hidden>
                                <label class="option-reveal-label" for="taille-input">LibellÃ© de la taille</label>
                                <input type="text" id="taille-input" placeholder="Ex : S, M, L, 38" class="options-input" autocomplete="off">
                                <label class="option-reveal-label" for="taille-surcharge">Montant en plus (FCFA)</label>
                                <input type="number" id="taille-surcharge" placeholder="0" min="0" step="1" class="options-surcharge" title="Montant Ã  ajouter au prix">
                                <p class="option-reveal-hint">Laissez 0 sâ€™il nâ€™y a pas de surcoÃ»t (ex. L + 200).</p>
                                <div class="option-reveal-actions">
                                    <button type="button" class="btn-validate-option" id="btn-add-taille">
                                        <i class="fas fa-check"></i> Valider
                                    </button>
                                    <button type="button" class="btn-cancel-option">Annuler</button>
                                </div>
                            </div>
                            <input type="hidden" name="taille" id="taille-hidden"
                               value="<?php echo isset($_POST['taille']) ? htmlspecialchars($_POST['taille']) : ''; ?>">
                        </article>
                </div>
            </div>

                <div class="form-add-block">
                    <h3 class="form-add-section-title">
                        <span class="form-step-num">4</span>
                        Images du produit
                    </h3>
                <div class="form-group">
                        <label>Photos <span class="required">*</span></label>
                        <p class="form-help">La premiÃ¨re image devient la photo principale. Les suivantes alimentent la galerie.</p>
                        <div class="file-input-wrapper file-input-single"
                            onclick="document.getElementById('images_produit').click()">
                            <input type="file" id="images_produit" name="images_produit[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" multiple required
                                class="file-input" style="display: none;">
                        <label class="file-input-label" style="cursor: pointer; margin: 0;">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Ajouter des images</span>
                            <small>JPG, PNG, GIF, WEBP â€” une ou plusieurs Ã  la fois</small>
                        </label>
                    </div>
                    <div id="preview-images" class="image-preview-accumulator"></div>
                </div>
            </div>

                <div class="form-add-block form-add-block-variantes">
                    <h3 class="form-add-section-title">
                        <span class="form-step-num">5</span>
                        Variantes
                        <span class="form-step-optional">optionnel</span>
                    </h3>
                    <p class="form-help form-help-lead">Formats distincts (nom, prix, image). Les options couleur, poids et taille sâ€™appliquent aussi aux variantes.</p>
                    <div id="variantes-container" class="variantes-container">
                        <div class="variante-item" data-index="0">
                            <div class="variante-row">
                                <input type="hidden" name="variantes_id[]" value="">
                                <input type="text" name="variantes_nom[]" placeholder="Nom (ex: Format familial)"
                                    class="variante-nom">
                                <input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01"
                                    class="variante-prix">
                                <input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0"
                                    step="0.01" class="variante-prix-promo">
                                <div class="variante-image-wrap">
                                    <div class="variante-image-area">
                                        <input type="file" name="variantes_image[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
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
                        <i class="fas fa-plus"></i> Ajouter le produit
                </button>
                <?php if ($categorie_id_prefill > 0): ?>
                <a href="../categories/produits.php?id=<?php echo $categorie_id_prefill; ?>" class="btn-cancel">Annuler</a>
                <?php else: ?>
                <a href="../stock/index.php" class="btn-cancel">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    </section>

    <script src="/js/admin-produits-form.js<?php echo asset_version_query(); ?>"></script>
    <?php include '../includes/footer.php'; ?>
