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
                <h3 class="form-add-section-title"><i class="fas fa-ruler"></i> Poids, couleurs et taille (optionnel)</h3>
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="poids">Poids</label>
                        <input type="text" id="poids" name="poids" placeholder="Ex: 500g, 1kg"
                               value="<?php echo isset($_POST['poids']) ? htmlspecialchars($_POST['poids']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="unite">Unité</label>
                        <select id="unite" name="unite">
                            <option value="unité" <?php echo (!isset($_POST['unite']) || $_POST['unite'] == 'unité') ? 'selected' : ''; ?>>Unité</option>
                            <option value="kg" <?php echo (isset($_POST['unite']) && $_POST['unite'] == 'kg') ? 'selected' : ''; ?>>Kilogramme</option>
                            <option value="g" <?php echo (isset($_POST['unite']) && $_POST['unite'] == 'g') ? 'selected' : ''; ?>>Gramme</option>
                            <option value="L" <?php echo (isset($_POST['unite']) && $_POST['unite'] == 'L') ? 'selected' : ''; ?>>Litre</option>
                        </select>
                    </div>
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
                            <input type="hidden" name="couleurs" id="couleurs-hidden" value="<?php echo isset($_POST['couleurs']) ? htmlspecialchars($_POST['couleurs']) : ''; ?>">
                        </div>
                        <small class="form-help">Cliquez sur la pastille pour choisir une couleur, puis sur « Ajouter ». Vous pouvez ajouter plusieurs couleurs.</small>
                    </div>
                    <div class="form-group">
                        <label for="taille">Tailles disponibles</label>
                        <input type="text" id="taille" name="taille" placeholder="Ex: S, M, L ou 21cm, 14.8cm"
                               value="<?php echo isset($_POST['taille']) ? htmlspecialchars($_POST['taille']) : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="form-add-block">
                <h3 class="form-add-section-title"><i class="fas fa-image"></i> Images du produit</h3>
                <div class="form-group">
                    <label>Images <span class="required">*</span> <small style="font-weight: normal; color: #666;">(1ère = principale, les autres pour la galerie)</small></label>
                    <div class="file-input-wrapper file-input-single" onclick="document.getElementById('images_produit').click()">
                        <input type="file" id="images_produit" name="images_produit[]" accept="image/*" multiple class="file-input" style="display: none;">
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

    <style>
        .file-input-single { cursor: pointer; }
        .image-preview-accumulator { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 15px; }
        .image-preview-accumulator .preview-item { position: relative; }
        .image-preview-accumulator .preview-item img { width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 2px solid rgba(229, 72, 138, 0.3); display: block; }
        .image-preview-accumulator .preview-item .preview-badge { position: absolute; top: 4px; left: 4px; background: var(--couleur-dominante, #E5488A); color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; }
        .image-preview-accumulator .preview-item .preview-remove { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border: none; background: rgba(0,0,0,0.6); color: #fff; border-radius: 50%; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; padding: 0; line-height: 1; }
        .image-preview-accumulator .preview-item .preview-remove:hover { background: #c00; }
        .couleurs-picker-block { margin-top: 8px; }
        .couleurs-add-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
        .couleurs-add-row input[type="color"] { width: 50px; height: 40px; padding: 2px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; }
        .btn-add-couleur { padding: 12px 18px; background: #918a44; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-add-couleur:hover { background: #7a7340; }
        .couleurs-swatches { display: flex; flex-wrap: wrap; gap: 10px; padding: 10px 0; }
        .couleur-swatch { display: flex; align-items: center; gap: 6px; padding: 6px 10px; background: #f5f5f5; border-radius: 20px; border: 2px solid #ddd; }
        .couleur-swatch .swatch-preview { width: 24px; height: 24px; border-radius: 50%; border: 2px solid #333; }
        .couleur-swatch .swatch-hex { font-size: 12px; color: #333; }
        .couleur-swatch .swatch-remove { width: 24px; height: 24px; border: none; background: #c00; color: #fff; border-radius: 50%; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; padding: 0; line-height: 1; }
        .couleur-swatch .swatch-remove:hover { background: #a00; }
    </style>
    <script>
        (function() {
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
                    (function(file, idx) {
                        if (!file.type.match('image.*')) return;
                        var pos = accumulatedFiles.length;
                        accumulatedFiles.push(file);
                        var reader = new FileReader();
                        reader.onload = function(e) {
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
                            btn.onclick = function(ev) {
                                ev.preventDefault();
                                ev.stopPropagation();
                                var idx = parseInt(div.dataset.index, 10);
                                accumulatedFiles.splice(idx, 1);
                                div.remove();
                                for (var j = 0; j < container.children.length; j++) {
                                    container.children[j].dataset.index = j;
                                    container.children[j].querySelector('.preview-badge').textContent = j === 0 ? 'Principale' : (j + 1);
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

            input.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    var newFiles = [];
                    for (var i = 0; i < this.files.length; i++) {
                        newFiles.push(this.files[i]);
                    }
                    addPreviews(newFiles);
                }
            });

            document.querySelector('.form-add').addEventListener('submit', function(e) {
                if (accumulatedFiles.length === 0) {
                    e.preventDefault();
                    alert('Veuillez ajouter au moins une image.');
                    return false;
                }
            });
        })();
        (function() {
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
            } catch (e) {}
            function updateHidden() {
                if (hidden) hidden.value = JSON.stringify(couleurs);
            }
            function render() {
                if (!list) return;
                list.innerHTML = '';
                couleurs.forEach(function(hex, i) {
                    var div = document.createElement('div');
                    div.className = 'couleur-swatch';
                    div.innerHTML = '<span class="swatch-preview" style="background:' + hex + '"></span><span class="swatch-hex">' + hex + '</span><button type="button" class="swatch-remove" data-i="' + i + '" title="Retirer">&times;</button>';
                    list.appendChild(div);
                });
                updateHidden();
            }
            if (btnAdd && couleurInput) {
                btnAdd.addEventListener('click', function() {
                    var hex = couleurInput.value;
                    if (hex && couleurs.indexOf(hex) === -1) {
                        couleurs.push(hex);
                        render();
                    }
                });
            }
            if (list) {
                list.addEventListener('click', function(e) {
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
    </script>
    <?php include '../includes/footer.php'; ?>
