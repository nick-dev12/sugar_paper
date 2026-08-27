<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Page de modification de produit
 * Programmation procédurale uniquement
 */

session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$produit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($produit_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_variantes.php';
$produit = get_produit_by_id($produit_id);
$variantes = $produit ? get_variantes_by_produit($produit_id) : [];

if (!$produit) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_update_produit($produit_id);

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_categories.php';
$categories = get_all_categories();

function admin_produit_option_json($raw) {
    $raw = $raw ?? '';
    if ($raw === '[]' || $raw === '') {
        return '';
    }
    $dec = json_decode($raw, true);
    if (is_array($dec)) {
        $dec = array_filter($dec, function ($x) {
            $v = is_array($x) ? ($x['v'] ?? '') : $x;
            return $v !== '' && $v !== '[]';
        });
        return !empty($dec) ? json_encode(array_values($dec)) : '';
    }
    return $raw;
}

$poids_val = admin_produit_option_json($produit['poids'] ?? '');
$taille_val = admin_produit_option_json($produit['taille'] ?? '');

$couleurs_init = [];
$couleurs_raw = trim($produit['couleurs'] ?? '');
if ($couleurs_raw) {
    $dec = json_decode($couleurs_raw, true);
    if (is_array($dec)) {
        $couleurs_init = array_filter($dec, function ($c) {
            return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
        });
    }
}
$couleurs_hidden_val = ($couleurs_raw && $couleurs_raw !== '[]')
    ? (empty($couleurs_init) ? $couleurs_raw : json_encode(array_values($couleurs_init)))
    : '';

$images_produit = [];
if (!empty($produit['images'])) {
    $dec = json_decode($produit['images'], true);
    if (is_array($dec)) {
        $images_produit = $dec;
    }
}
if (empty($images_produit) && !empty($produit['image_principale'])) {
    $images_produit = [$produit['image_principale']];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un produit - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-form.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header content-header-form">
        <h1><i class="fas fa-edit"></i> Modifier un produit</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour aux produits
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

            <form method="POST" action="" enctype="multipart/form-data" class="form-add form-add--v2" id="form-modifier-produit">
                <div class="form-add-block">
                    <h3 class="form-add-section-title">
                        <span class="form-step-num">1</span>
                        Informations générales
                    </h3>
                    <div class="form-group">
                        <label for="nom">Nom du produit <span class="required">*</span></label>
                        <input type="text" id="nom" name="nom" required placeholder="Ex: Miel naturel pur"
                            value="<?php echo htmlspecialchars($produit['nom']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" required placeholder="Décrivez votre produit, ses usages, son origine…"
                            rows="4"><?php echo htmlspecialchars($produit['description']); ?></textarea>
                    </div>
                    <div class="form-group-row form-group-row-2">
                        <div class="form-group">
                            <label for="categorie_id">Catégorie <span class="required">*</span></label>
                            <select id="categorie_id" name="categorie_id" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php if ($categories && count($categories) > 0): ?>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo ((int) $produit['categorie_id'] === (int) $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['nom']); ?>
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
                        <div class="form-group">
                            <label for="statut">Visibilité</label>
                            <select id="statut" name="statut">
                                <option value="actif" <?php echo ($produit['statut'] == 'actif') ? 'selected' : ''; ?>>Actif — visible en boutique</option>
                                <option value="inactif" <?php echo ($produit['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif — masqué</option>
                                <option value="rupture_stock" <?php echo ($produit['statut'] == 'rupture_stock') ? 'selected' : ''; ?>>Rupture de stock</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="section_accueil">Section page d'accueil</label>
                        <select id="section_accueil" name="section_accueil">
                            <option value="">Aucune — n'apparaît pas sur l'accueil</option>
                            <?php
                            $current_section = $produit['section_accueil'] ?? '';
                            foreach (get_produit_section_accueil_labels() as $section_key => $section_label):
                            ?>
                            <option value="<?php echo htmlspecialchars($section_key); ?>" <?php echo ($current_section === $section_key) ? 'selected' : ''; ?>>
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
                                value="<?php echo htmlspecialchars($produit['prix']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="prix_promotion">Prix promotionnel</label>
                            <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0" placeholder="Optionnel"
                                value="<?php echo htmlspecialchars($produit['prix_promotion'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock <span class="required">*</span></label>
                            <input type="number" id="stock" name="stock" min="0" required placeholder="0"
                                value="<?php echo htmlspecialchars($produit['stock']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-add-block">
                    <h3 class="form-add-section-title">
                        <span class="form-step-num">3</span>
                        Options du produit
                        <span class="form-step-optional">optionnel</span>
                    </h3>
                    <p class="form-help form-help-lead">Cliquez sur un bouton pour ouvrir le formulaire. Validez pour enregistrer l’option. Un montant (+ FCFA) peut s’ajouter au prix de base pour un poids ou une taille.</p>

                    <div class="options-cards-grid">
                        <article class="option-card" data-option="poids">
                            <header class="option-card-header">
                                <span class="option-card-icon" aria-hidden="true"><i class="fas fa-weight-hanging"></i></span>
                                <div class="option-card-heading">
                                    <h4>Poids</h4>
                                    <p>Formats proposés au client</p>
                                </div>
                                <span class="option-card-count" id="poids-count" hidden>0</span>
                            </header>
                            <div id="poids-list" class="options-tags-list options-tags-with-surcharge"></div>
                            <p class="option-empty-hint">Aucun poids ajouté</p>
                            <button type="button" class="btn-reveal-option" data-target="poids">
                                <i class="fas fa-plus"></i> Ajouter un poids
                            </button>
                            <div class="option-reveal-form" id="poids-form" hidden>
                                <label class="option-reveal-label" for="poids-input">Libellé du poids</label>
                                <input type="text" id="poids-input" placeholder="Ex : 500g, 1kg" class="options-input" autocomplete="off">
                                <label class="option-reveal-label" for="poids-surcharge">Montant en plus (FCFA)</label>
                                <input type="number" id="poids-surcharge" placeholder="0" min="0" step="1" class="options-surcharge" title="Montant à ajouter au prix">
                                <p class="option-reveal-hint">Laissez 0 s’il n’y a pas de surcoût (ex. 1kg + 300).</p>
                                <div class="option-reveal-actions">
                                    <button type="button" class="btn-validate-option" id="btn-add-poids">
                                        <i class="fas fa-check"></i> Valider
                                    </button>
                                    <button type="button" class="btn-cancel-option">Annuler</button>
                                </div>
                            </div>
                            <input type="hidden" name="poids" id="poids-hidden" value="<?php echo htmlspecialchars($poids_val); ?>">
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
                            <p class="option-empty-hint">Aucune couleur ajoutée</p>
                            <button type="button" class="btn-reveal-option" data-target="couleurs">
                                <i class="fas fa-plus"></i> Ajouter une couleur
                            </button>
                            <div class="option-reveal-form" id="couleurs-form" hidden>
                                <label class="option-reveal-label" for="couleur-input">Choisir la couleur</label>
                                <div class="couleurs-add-row">
                                    <input type="color" id="couleur-input" value="#E5488A" title="Choisir une couleur">
                                    <span class="couleur-hex-preview" id="couleur-hex-preview">#E5488A</span>
                                </div>
                                <p class="option-reveal-hint">Cliquez sur la pastille, puis validez pour l’ajouter.</p>
                                <?php if ($couleurs_raw && empty($couleurs_init)): ?>
                                <p class="option-reveal-hint">Ancien format détecté : <?php echo htmlspecialchars($couleurs_raw); ?> — remplacez via le sélecteur.</p>
                                <?php endif; ?>
                                <div class="option-reveal-actions">
                                    <button type="button" class="btn-validate-option" id="btn-add-couleur">
                                        <i class="fas fa-check"></i> Valider
                                    </button>
                                    <button type="button" class="btn-cancel-option">Annuler</button>
                                </div>
                            </div>
                            <input type="hidden" name="couleurs" id="couleurs-hidden" value="<?php echo htmlspecialchars($couleurs_hidden_val); ?>">
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
                            <p class="option-empty-hint">Aucune taille ajoutée</p>
                            <button type="button" class="btn-reveal-option" data-target="taille">
                                <i class="fas fa-plus"></i> Ajouter une taille
                            </button>
                            <div class="option-reveal-form" id="taille-form" hidden>
                                <label class="option-reveal-label" for="taille-input">Libellé de la taille</label>
                                <input type="text" id="taille-input" placeholder="Ex : S, M, L, 38" class="options-input" autocomplete="off">
                                <label class="option-reveal-label" for="taille-surcharge">Montant en plus (FCFA)</label>
                                <input type="number" id="taille-surcharge" placeholder="0" min="0" step="1" class="options-surcharge" title="Montant à ajouter au prix">
                                <p class="option-reveal-hint">Laissez 0 s’il n’y a pas de surcoût (ex. L + 200).</p>
                                <div class="option-reveal-actions">
                                    <button type="button" class="btn-validate-option" id="btn-add-taille">
                                        <i class="fas fa-check"></i> Valider
                                    </button>
                                    <button type="button" class="btn-cancel-option">Annuler</button>
                                </div>
                            </div>
                            <input type="hidden" name="taille" id="taille-hidden" value="<?php echo htmlspecialchars($taille_val); ?>">
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
                        <p class="form-help">La première image est la photo principale. Cliquez sur × pour retirer une image existante.</p>
                        <div id="gallery-existing" class="gallery-preview-edit">
                            <?php foreach ($images_produit as $idx => $img_path): ?>
                                <div class="gallery-thumb-edit" data-path="<?php echo htmlspecialchars($img_path); ?>">
                                    <input type="hidden" name="images_to_keep[]" value="<?php echo htmlspecialchars($img_path); ?>">
                                    <span class="img-badge"><?php echo $idx === 0 ? 'Principale' : ($idx + 1); ?></span>
                                    <button type="button" class="img-remove-btn" title="Supprimer cette image">&times;</button>
                                    <img src="../../upload/<?php echo htmlspecialchars($img_path); ?>"
                                        alt="Image <?php echo $idx + 1; ?>" onerror="this.src='/image/produit1.jpg'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <label for="images_supplementaires" class="btn-add-gallery">
                            <i class="fas fa-plus"></i> Ajouter des images à la galerie
                        </label>
                        <input type="file" id="images_supplementaires" name="images_supplementaires[]"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" multiple style="display: none;">
                        <div id="preview-supplementaires" class="image-preview-grid"></div>
                        <p class="form-help">Formats : JPG, PNG, GIF, WEBP. Au moins une image doit rester.</p>
                    </div>
                </div>

                <div class="form-add-block form-add-block-variantes">
                    <h3 class="form-add-section-title">
                        <span class="form-step-num">5</span>
                        Variantes
                        <span class="form-step-optional">optionnel</span>
                    </h3>
                    <p class="form-help form-help-lead">Formats distincts (nom, prix, image). Les options couleur, poids et taille s’appliquent aussi aux variantes.</p>
                    <div id="variantes-container" class="variantes-container">
                        <?php if (!empty($variantes)): ?>
                            <?php foreach ($variantes as $idx => $var): ?>
                                <div class="variante-item" data-index="<?php echo $idx; ?>">
                                    <div class="variante-row">
                                        <input type="hidden" name="variantes_id[]" value="<?php echo (int) $var['id']; ?>">
                                        <input type="text" name="variantes_nom[]" placeholder="Nom (ex: Format familial)" class="variante-nom"
                                            value="<?php echo htmlspecialchars($var['nom']); ?>">
                                        <input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01" class="variante-prix"
                                            value="<?php echo htmlspecialchars($var['prix']); ?>">
                                        <input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0" step="0.01" class="variante-prix-promo"
                                            value="<?php echo $var['prix_promotion'] ? htmlspecialchars($var['prix_promotion']) : ''; ?>">
                                        <div class="variante-image-wrap">
                                            <div class="variante-image-area">
                                                <input type="file" name="variantes_image[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="variante-image-input">
                                                <span class="variante-image-label" <?php echo $var['image'] ? 'style="display: none;"' : ''; ?>>
                                                    <i class="fas fa-image"></i> <?php echo $var['image'] ? 'Changer' : 'Image'; ?>
                                                </span>
                                                <img class="variante-preview-img"
                                                    src="<?php echo $var['image'] ? '../../upload/' . htmlspecialchars($var['image']) : ''; ?>"
                                                    alt="" <?php echo $var['image'] ? '' : 'style="display: none;"'; ?>>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-remove-variante" title="Supprimer">&times;</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="variante-item" data-index="0">
                                <div class="variante-row">
                                    <input type="hidden" name="variantes_id[]" value="">
                                    <input type="text" name="variantes_nom[]" placeholder="Nom (ex: Format familial)" class="variante-nom">
                                    <input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01" class="variante-prix">
                                    <input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0" step="0.01" class="variante-prix-promo">
                                    <div class="variante-image-wrap">
                                        <div class="variante-image-area">
                                            <input type="file" name="variantes_image[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="variante-image-input">
                                            <span class="variante-image-label"><i class="fas fa-image"></i> Image</span>
                                            <img class="variante-preview-img" src="" alt="" style="display: none;">
                                        </div>
                                    </div>
                                    <button type="button" class="btn-remove-variante" title="Supprimer">&times;</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="btn-add-variante" class="btn-add-variante">
                        <i class="fas fa-plus"></i> Ajouter une variante
                    </button>
                </div>

                <div class="form-add-actions">
                    <button type="submit" class="btn-primary btn-submit-large">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <a href="index.php" class="btn-cancel">Annuler</a>
                </div>
            </form>
        </div>
    </section>

    <script src="/js/admin-produits-form.js<?php echo asset_version_query(); ?>"></script>
    <script>
        (function () {
            var galleryExisting = document.getElementById('gallery-existing');
            var inputSupp = document.getElementById('images_supplementaires');
            var labelSupp = document.querySelector('label[for="images_supplementaires"]');

            if (labelSupp && inputSupp) {
                labelSupp.addEventListener('click', function (e) {
                    e.preventDefault();
                    inputSupp.click();
                });
            }

            if (galleryExisting) {
                galleryExisting.addEventListener('click', function (e) {
                    var btn = e.target.closest('.img-remove-btn');
                    if (btn) {
                        e.preventDefault();
                        btn.closest('.gallery-thumb-edit').remove();
                    }
                });
            }

            function previewMultipleImages(input) {
                var c = document.getElementById('preview-supplementaires');
                if (!c) return;
                c.innerHTML = '';
                if (!input.files) return;
                for (var i = 0; i < input.files.length; i++) {
                    (function (f) {
                        var r = new FileReader();
                        r.onload = function (e) {
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

            if (inputSupp) {
                inputSupp.addEventListener('change', function () {
                    previewMultipleImages(this);
                });
            }

            var form = document.getElementById('form-modifier-produit');
            if (form) {
                form.addEventListener('submit', function (e) {
                    var kept = document.querySelectorAll('input[name="images_to_keep[]"]').length;
                    var newFiles = inputSupp && inputSupp.files ? inputSupp.files.length : 0;
                    if (kept === 0 && newFiles === 0) {
                        e.preventDefault();
                        alert('Au moins une image est obligatoire. Veuillez conserver ou ajouter au moins une image.');
                        return false;
                    }
                });
            }
        })();
    </script>
    <?php include '../includes/footer.php'; ?>
</body>

</html>
