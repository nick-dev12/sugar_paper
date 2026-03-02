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
            border: 2px solid #e8e8e8;
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
        .gallery-preview-edit { display: flex; flex-wrap: wrap; gap: 12px; margin: 15px 0; }
        .gallery-thumb-edit { position: relative; }
        .gallery-thumb-edit img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid rgba(145, 138, 68, 0.3); }
        .gallery-thumb-edit .img-remove-btn { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border: none; background: rgba(0,0,0,0.6); color: #fff; border-radius: 50%; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; padding: 0; line-height: 1; }
        .gallery-thumb-edit .img-remove-btn:hover { background: #c00; }
        .gallery-thumb-edit .img-badge { position: absolute; top: 4px; left: 4px; background: #918a44; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; }
        .image-preview-container { margin-top: 12px; }
        .image-preview-container img { max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid rgba(229, 72, 138, 0.3); }
        .image-preview-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
        .image-preview-grid .preview-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid rgba(229, 72, 138, 0.3); }

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
                        <option value="unité" <?php echo (($produit['unite'] ?? '') == 'unité') ? 'selected' : ''; ?>>Unité</option>
                        <option value="kg" <?php echo (($produit['unite'] ?? '') == 'kg') ? 'selected' : ''; ?>>Kilogramme</option>
                        <option value="g" <?php echo (($produit['unite'] ?? '') == 'g') ? 'selected' : ''; ?>>Gramme</option>
                        <option value="L" <?php echo (($produit['unite'] ?? '') == 'L') ? 'selected' : ''; ?>>Litre</option>
                    </select>
                </div>
            </div>

            <?php
            $couleurs_init = [];
            $couleurs_raw = trim($produit['couleurs'] ?? '');
            if ($couleurs_raw) {
                $dec = json_decode($couleurs_raw, true);
                if (is_array($dec)) {
                    $couleurs_init = array_filter($dec, function($c) {
                        return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
                    });
                }
            }
            ?>
            <div class="form-row">
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
                        <input type="hidden" name="couleurs" id="couleurs-hidden" value="<?php echo htmlspecialchars($couleurs_raw ? (empty($couleurs_init) ? $couleurs_raw : json_encode($couleurs_init)) : ''); ?>">
                    </div>
                    <?php if ($couleurs_raw && empty($couleurs_init)): ?>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Ancien format (texte) : <?php echo htmlspecialchars($couleurs_raw); ?> — remplacez par des couleurs via le sélecteur ci-dessus.</small>
                    <?php else: ?>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Cliquez sur la pastille pour choisir une couleur, puis sur « Ajouter ». Vous pouvez ajouter plusieurs couleurs.</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="taille">Tailles disponibles (optionnel)</label>
                    <input type="text" id="taille" name="taille" placeholder="Ex: S, M, L ou 21cm, 14.8cm"
                           value="<?php echo htmlspecialchars($produit['taille'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-image"></i> Images du produit</label>
                <p style="font-size: 12px; color: #666; margin-bottom: 10px;">Images actuelles — cliquez sur &times; pour supprimer une image. La première est l'image principale.</p>
                <?php 
                $images_produit = [];
                if (!empty($produit['images'])) {
                    $dec = json_decode($produit['images'], true);
                    if (is_array($dec)) $images_produit = $dec;
                }
                if (empty($images_produit) && !empty($produit['image_principale'])) {
                    $images_produit = [$produit['image_principale']];
                }
                ?>
                <div id="gallery-existing" class="gallery-preview-edit">
                    <?php foreach ($images_produit as $idx => $img_path): ?>
                        <div class="gallery-thumb-edit" data-path="<?php echo htmlspecialchars($img_path); ?>">
                            <input type="hidden" name="images_to_keep[]" value="<?php echo htmlspecialchars($img_path); ?>">
                            <span class="img-badge"><?php echo $idx === 0 ? 'Principale' : ($idx + 1); ?></span>
                            <button type="button" class="img-remove-btn" title="Supprimer cette image">&times;</button>
                            <img src="../../upload/<?php echo htmlspecialchars($img_path); ?>" alt="Image <?php echo $idx + 1; ?>" onerror="this.src='/image/produit1.jpg'">
                        </div>
                    <?php endforeach; ?>
                </div>
                <label for="images_supplementaires" style="display: inline-block; margin-top: 10px; cursor: pointer; padding: 10px 16px; background: #f0f0f0; border-radius: 8px;">
                    <i class="fas fa-plus"></i> Ajouter des images à la galerie
                </label>
                <input type="file" id="images_supplementaires" name="images_supplementaires[]" accept="image/*" multiple style="display: none;" onchange="previewMultipleImages(this, 'preview-supplementaires')">
                <div id="preview-supplementaires" class="image-preview-grid"></div>
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Formats: JPG, PNG, GIF, WEBP. Au moins une image doit rester.</small>
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

    <script>
        (function() {
            var galleryExisting = document.getElementById('gallery-existing');
            var inputSupp = document.getElementById('images_supplementaires');
            if (galleryExisting) {
                galleryExisting.addEventListener('click', function(e) {
                    var btn = e.target.closest('.img-remove-btn');
                    if (btn) {
                        e.preventDefault();
                        btn.closest('.gallery-thumb-edit').remove();
                    }
                });
            }
            function previewMultipleImages(input, containerId) {
                var c = document.getElementById(containerId);
                c.innerHTML = '';
                if (input.files) for (var i = 0; i < input.files.length; i++) {
                    (function(f) {
                        var r = new FileReader();
                        r.onload = function(e) {
                            var d = document.createElement('div');
                            d.className = 'preview-item';
                            var img = document.createElement('img');
                            img.src = e.target.result;
                            d.appendChild(img);
                            c.appendChild(d);
                        };
                        r.readAsDataURL(f);
                    })(input.files[i]);
                }
            }
            if (inputSupp) inputSupp.addEventListener('change', function() { previewMultipleImages(this, 'preview-supplementaires'); });
            document.querySelector('form').addEventListener('submit', function(e) {
                var kept = document.querySelectorAll('input[name="images_to_keep[]"]').length;
                var newFiles = inputSupp && inputSupp.files ? inputSupp.files.length : 0;
                if (kept === 0 && newFiles === 0) {
                    e.preventDefault();
                    alert('Au moins une image est obligatoire. Veuillez conserver ou ajouter au moins une image.');
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

