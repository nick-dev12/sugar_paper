<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Page de modification de la configuration de la section trending
 * Programmation procédurale uniquement
 */

session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../models/model_trending.php';
require_once __DIR__ . '/../../includes/home_sections.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';

$trending_slides = get_trending_slides();
$section_options = get_home_section_accueil_options();
$slides_without_image = get_trending_slides_without_image();

$error_message = '';
$result = null;
$open_modal = '';
$edit_slide = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../controllers/controller_trending.php';
    $result = process_update_trending();

    if (isset($result['success']) && $result['success']) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: trending.php');
        exit;
    }

    if (isset($result['success']) && !$result['success']) {
        $error_message = $result['message'];
        $open_modal = (string) ($result['modal'] ?? '');
        if (!empty($result['slide_id'])) {
            $edit_slide = get_trending_slide_by_id((int) $result['slide_id']);
        }
    }
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$edit_slide_id = 0;
$is_new_text = false;

if ($open_modal === '' && isset($_GET['add_text'])) {
    $open_modal = 'text';
    $is_new_text = true;
}
if ($open_modal === '' && isset($_GET['edit_text'])) {
    $open_modal = 'text';
    $edit_slide_id = (int) $_GET['edit_text'];
    $edit_slide = get_trending_slide_by_id($edit_slide_id);
    if (!$edit_slide) {
        $open_modal = '';
        $error_message = 'Slide introuvable';
    }
}
if ($open_modal === '' && isset($_GET['add_images'])) {
    $open_modal = 'images';
    $is_new_text = false;
}
if ($open_modal === '' && isset($_GET['edit_image'])) {
    $open_modal = 'images';
    $edit_slide_id = (int) $_GET['edit_image'];
    $edit_slide = get_trending_slide_by_id($edit_slide_id);
    if (!$edit_slide) {
        $open_modal = '';
        $error_message = 'Slide introuvable';
    }
}

$form_label = isset($_POST['label']) ? (string) $_POST['label'] : (string) ($edit_slide['label'] ?? '');
$form_titre = isset($_POST['titre']) ? (string) $_POST['titre'] : (string) ($edit_slide['titre'] ?? '');
$form_description = isset($_POST['description']) ? (string) $_POST['description'] : (string) ($edit_slide['description'] ?? '');
$form_bouton_texte = isset($_POST['bouton_texte']) ? (string) $_POST['bouton_texte'] : (string) ($edit_slide['bouton_texte'] ?? 'Découvrir');
$form_section_key = isset($_POST['section_key']) ? (string) $_POST['section_key'] : (string) ($edit_slide['section_key'] ?? 'kit_impression');
$form_section_key = trending_normalize_section_key($form_section_key);
$form_slide_id = isset($_POST['slide_id']) ? (int) $_POST['slide_id'] : $edit_slide_id;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Trending - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .trending-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .trending-block {
            margin-top: 28px;
        }
        .trending-block h3 {
            margin: 0 0 14px;
            color: var(--titres);
            font-size: 18px;
        }
        .trending-slides-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .trending-slide-card {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 14px;
            padding: 18px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 160px;
            gap: 18px;
            align-items: start;
        }
        .trending-slide-card dl {
            margin: 0;
        }
        .trending-slide-card dt {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #888;
            margin-top: 10px;
        }
        .trending-slide-card dt:first-child {
            margin-top: 0;
        }
        .trending-slide-card dd {
            margin: 4px 0 0;
            color: var(--titres);
            font-weight: 600;
            word-break: break-word;
        }
        .trending-slide-visual {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .trending-slide-visual img,
        .trending-slide-visual .trending-no-image {
            width: 100%;
            height: 120px;
            border-radius: 10px;
            object-fit: cover;
            display: block;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }
        .trending-slide-visual .trending-no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
            color: #888;
            font-size: 12px;
            text-align: center;
            padding: 8px;
        }
        .trending-slide-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .trending-slide-actions .btn-edit,
        .trending-slide-actions .btn-delete {
            flex: 1;
            justify-content: center;
            min-height: 36px;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 13px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .trending-slide-actions .btn-edit {
            background: var(--couleur-dominante);
            color: #fff;
        }
        .trending-slide-actions .btn-delete {
            background: var(--accent-promo);
            color: #fff;
        }
        .modal-content.trending-modal {
            max-width: 560px;
            padding: 28px 22px;
        }
        .trending-current-image {
            margin-top: 12px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }
        .trending-current-image img {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
            display: block;
        }
        .trending-upload-zone {
            position: relative;
            margin-top: 10px;
        }
        .trending-file-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .trending-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 120px;
            padding: 24px 20px;
            border: 2px dashed rgba(229, 72, 138, 0.35);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.92);
            color: var(--couleur-dominante);
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            text-align: center;
        }
        .trending-upload-label:hover,
        .trending-upload-label.is-dragover {
            border-color: var(--couleur-dominante);
            background: rgba(229, 72, 138, 0.06);
        }
        .trending-upload-label i {
            font-size: 30px;
            opacity: 0.85;
        }
        .trending-upload-label span {
            font-weight: 600;
            font-size: 15px;
        }
        .trending-upload-label small {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
        }
        .trending-selected-summary {
            margin-top: 10px;
            font-size: 13px;
            color: #555;
            font-weight: 600;
        }
        .trending-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .trending-preview-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: #fff;
            aspect-ratio: 1;
        }
        .trending-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .trending-preview-item span {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 4px 6px;
            font-size: 10px;
            color: #fff;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.72));
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .form-help {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #666;
            line-height: 1.45;
        }
        @media (max-width: 640px) {
            .trending-slide-card {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <section class="produits-section">
        <div class="videos-header">
            <div>
                <h2><i class="fas fa-star"></i> Section Mise en Avant</h2>
                <p>Bannière premium sous les services sur la page d'accueil. Chaque slide associe un texte et une image.</p>
            </div>
            <div class="trending-header-actions">
                <a href="trending.php?add_text=1" class="btn-add-video">
                    <i class="fas fa-font"></i> Ajouter le texte
                </a>
                <a href="trending.php?add_images=1" class="btn-add-video<?php echo empty($slides_without_image) ? ' disabled' : ''; ?>"
                   <?php echo empty($slides_without_image) ? 'aria-disabled="true" onclick="return false;"' : ''; ?>>
                    <i class="fas fa-image"></i> Ajouter une image
                </a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message) && $open_modal === ''): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="trending-block">
            <h3><i class="fas fa-layer-group"></i> Slides de la bannière</h3>
            <?php if (!empty($trending_slides)): ?>
            <div class="trending-slides-list">
                <?php foreach ($trending_slides as $slide): ?>
                <?php
                $slide_image = trim((string) ($slide['image'] ?? ''));
                $slide_section = trending_normalize_section_key($slide['section_key'] ?? 'kit_impression');
                $section_label = $section_options[$slide_section] ?? $slide_section;
                ?>
                <article class="trending-slide-card">
                    <div>
                        <dl>
                            <dt>Label</dt>
                            <dd><?php echo htmlspecialchars($slide['label'] ?? ''); ?></dd>
                            <dt>Titre principal</dt>
                            <dd><?php echo htmlspecialchars(str_replace('|', ' · ', (string) ($slide['titre'] ?? ''))); ?></dd>
                            <dt>Description</dt>
                            <dd><?php echo trim((string) ($slide['description'] ?? '')) !== '' ? htmlspecialchars($slide['description']) : '—'; ?></dd>
                            <dt>Bouton</dt>
                            <dd><?php echo htmlspecialchars((string) ($slide['bouton_texte'] ?? 'Découvrir')); ?></dd>
                            <dt>Section cible</dt>
                            <dd><?php echo htmlspecialchars($section_label); ?></dd>
                        </dl>
                        <div class="trending-slide-actions">
                            <a href="trending.php?edit_text=<?php echo (int) $slide['id']; ?>" class="btn-edit">
                                <i class="fas fa-edit"></i> Modifier le texte
                            </a>
                            <?php if ($slide_image !== ''): ?>
                            <a href="trending.php?edit_image=<?php echo (int) $slide['id']; ?>" class="btn-edit">
                                <i class="fas fa-image"></i> Modifier l'image
                            </a>
                            <form method="POST" action="" onsubmit="return confirm('Supprimer l\'image de ce slide ?');">
                                <input type="hidden" name="action" value="delete_image">
                                <input type="hidden" name="delete_spotlight_image_id" value="<?php echo (int) $slide['id']; ?>">
                                <button type="submit" class="btn-delete">
                                    <i class="fas fa-trash"></i> Supprimer l'image
                                </button>
                            </form>
                            <?php else: ?>
                            <a href="trending.php?add_images=1&amp;slide_id=<?php echo (int) $slide['id']; ?>" class="btn-edit">
                                <i class="fas fa-plus"></i> Ajouter une image
                            </a>
                            <?php endif; ?>
                            <form method="POST" action="" onsubmit="return confirm('Supprimer ce slide complet ?');">
                                <input type="hidden" name="action" value="delete_slide">
                                <input type="hidden" name="slide_id" value="<?php echo (int) $slide['id']; ?>">
                                <button type="submit" class="btn-delete">
                                    <i class="fas fa-trash"></i> Supprimer le slide
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="trending-slide-visual">
                        <?php if ($slide_image !== ''): ?>
                        <img src="<?php echo htmlspecialchars(upload_subdir_image_url('trending', $slide_image, 'md')); ?>"
                             alt="Image du slide">
                        <?php else: ?>
                        <div class="trending-no-image">Aucune image liée</div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-layer-group"></i>
                <h3>Aucun slide pour le moment</h3>
                <p>Cliquez sur « Ajouter le texte » pour créer votre premier slide.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="modal-overlay<?php echo $open_modal === 'text' ? ' active' : ''; ?>" id="trendingTextModal">
        <div class="modal-content trending-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-<?php echo ($is_new_text || !$edit_slide) ? 'plus' : 'edit'; ?>"></i>
                    <?php echo ($is_new_text || !$edit_slide) ? 'Ajouter un slide texte' : 'Modifier le texte'; ?>
                </h3>
                <button type="button" class="modal-close" onclick="closeTrendingModal('trendingTextModal')" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <?php if (!empty($error_message) && $open_modal === 'text'): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="form-add">
                <input type="hidden" name="action" value="save_text">
                <?php if ($form_slide_id > 0): ?>
                <input type="hidden" name="slide_id" value="<?php echo (int) $form_slide_id; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="label">
                        <i class="fas fa-tag"></i> Label (petit texte)
                    </label>
                    <input type="text" id="label" name="label"
                           value="<?php echo htmlspecialchars($form_label); ?>"
                           required
                           placeholder="Ex: Nouveauté">
                </div>

                <div class="form-group">
                    <label for="titre">
                        <i class="fas fa-heading"></i> Titre principal (2 lignes avec |)
                    </label>
                    <input type="text" id="titre" name="titre"
                           value="<?php echo htmlspecialchars($form_titre); ?>"
                           required
                           placeholder="Ex: Sugar Paper|Kit impression comestible">
                </div>

                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i> Description
                    </label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Ex: Découvrez l'impression, les cartouches encre comestible et le papier sucre."><?php echo htmlspecialchars($form_description); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="bouton_texte">
                        <i class="fas fa-hand-pointer"></i> Texte du bouton
                    </label>
                    <input type="text" id="bouton_texte" name="bouton_texte"
                           value="<?php echo htmlspecialchars($form_bouton_texte); ?>"
                           placeholder="Ex: Découvrir">
                </div>

                <div class="form-group">
                    <label for="section_key">
                        <i class="fas fa-th-large"></i> Section produits (destination du bouton)
                    </label>
                    <select id="section_key" name="section_key" required>
                        <?php foreach ($section_options as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"<?php echo $form_section_key === $key ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Le bouton « Découvrir » redirigera vers les produits de cette section.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Enregistrer le texte
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeTrendingModal('trendingTextModal')">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php
    $image_modal_slide_id = 0;
    if ($edit_slide && $open_modal === 'images') {
        $image_modal_slide_id = (int) $edit_slide['id'];
    } elseif (isset($_GET['slide_id'])) {
        $image_modal_slide_id = (int) $_GET['slide_id'];
    } elseif (isset($_POST['slide_id'])) {
        $image_modal_slide_id = (int) $_POST['slide_id'];
    } elseif (count($slides_without_image) === 1) {
        $image_modal_slide_id = (int) $slides_without_image[0]['id'];
    }
    $image_edit_slide = $image_modal_slide_id > 0 ? get_trending_slide_by_id($image_modal_slide_id) : null;
    $is_image_replace = $image_edit_slide && trim((string) ($image_edit_slide['image'] ?? '')) !== '';
    ?>

    <div class="modal-overlay<?php echo $open_modal === 'images' ? ' active' : ''; ?>" id="trendingImagesModal">
        <div class="modal-content trending-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-<?php echo $is_image_replace ? 'edit' : 'image'; ?>"></i>
                    <?php echo $is_image_replace ? 'Modifier l’image' : 'Ajouter une image'; ?>
                </h3>
                <button type="button" class="modal-close" onclick="closeTrendingModal('trendingImagesModal')" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <?php if (!empty($error_message) && $open_modal === 'images'): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>

            <?php if (empty($slides_without_image) && !$is_image_replace): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span>Aucun slide texte disponible sans image. Ajoutez d'abord un texte.</span>
            </div>
            <?php else: ?>
            <form method="POST" action="" enctype="multipart/form-data" class="form-add">
                <input type="hidden" name="MAX_FILE_SIZE" value="52428800">
                <input type="hidden" name="action" value="<?php echo $is_image_replace ? 'replace_image' : 'add_image'; ?>">

                <?php if (!$is_image_replace): ?>
                <div class="form-group">
                    <label for="slide_id">
                        <i class="fas fa-link"></i> Slide texte à lier
                    </label>
                    <select id="slide_id" name="slide_id" required>
                        <option value="">— Choisir un slide —</option>
                        <?php foreach ($slides_without_image as $slide_option): ?>
                        <?php
                        $option_label = str_replace('|', ' · ', (string) ($slide_option['titre'] ?? 'Slide'));
                        $selected = $image_modal_slide_id === (int) $slide_option['id'];
                        ?>
                        <option value="<?php echo (int) $slide_option['id']; ?>"<?php echo $selected ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars(($slide_option['label'] ?? '') . ' — ' . $option_label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Une seule image par slide texte.</small>
                </div>
                <?php else: ?>
                <input type="hidden" name="slide_id" value="<?php echo (int) $image_modal_slide_id; ?>">
                <div class="form-group">
                    <label>Slide lié</label>
                    <p class="form-help"><?php echo htmlspecialchars(str_replace('|', ' · ', (string) ($image_edit_slide['titre'] ?? ''))); ?></p>
                </div>
                <div class="form-group">
                    <label>Image actuelle</label>
                    <div class="trending-current-image">
                        <img src="<?php echo htmlspecialchars(upload_subdir_image_url('trending', $image_edit_slide['image'] ?? '', 'md')); ?>" alt="Image actuelle">
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>
                        <i class="fas fa-image"></i> <?php echo $is_image_replace ? 'Nouvelle image' : 'Image du slide'; ?>
                    </label>
                    <div class="trending-upload-zone" id="trendingUploadZone">
                        <input type="file" id="spotlight_image" name="spotlight_image"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                            class="trending-file-input" required>
                        <label for="spotlight_image" class="trending-upload-label" id="trendingUploadLabel">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Cliquez ou glissez une image ici</span>
                            <small>JPG, PNG, GIF, WEBP — max 50 Mo</small>
                        </label>
                    </div>
                    <p class="trending-selected-summary" id="trendingSelectedSummary" hidden></p>
                    <div class="trending-preview-grid" id="trendingImagesPreview"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> <?php echo $is_image_replace ? 'Mettre à jour l’image' : 'Ajouter l’image'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeTrendingModal('trendingImagesModal')">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    function closeTrendingModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
        }
        document.body.style.overflow = 'auto';
        window.location.href = 'trending.php';
    }

    function initTrendingImageUpload() {
        var fileInput = document.getElementById('spotlight_image');
        var previewGrid = document.getElementById('trendingImagesPreview');
        var summary = document.getElementById('trendingSelectedSummary');
        var uploadLabel = document.getElementById('trendingUploadLabel');
        var uploadZone = document.getElementById('trendingUploadZone');
        if (!fileInput || !previewGrid || !uploadLabel) {
            return;
        }

        function clearPreview() {
            previewGrid.innerHTML = '';
            if (summary) {
                summary.hidden = true;
                summary.textContent = '';
            }
        }

        function renderPreview(files) {
            clearPreview();
            if (!files || !files.length) {
                return;
            }

            var validCount = 0;
            Array.prototype.forEach.call(files, function(file) {
                if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                    return;
                }
                validCount++;
                var item = document.createElement('div');
                item.className = 'trending-preview-item';
                var img = document.createElement('img');
                img.alt = file.name;
                img.src = URL.createObjectURL(file);
                var caption = document.createElement('span');
                caption.textContent = file.name;
                item.appendChild(img);
                item.appendChild(caption);
                previewGrid.appendChild(item);
            });

            if (summary && validCount > 0) {
                summary.hidden = false;
                summary.textContent = '1 image sélectionnée — prête à être envoyée';
            }
        }

        fileInput.addEventListener('change', function() {
            renderPreview(fileInput.files);
        });

        if (uploadZone) {
            ['dragenter', 'dragover'].forEach(function(eventName) {
                uploadZone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    uploadLabel.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function(eventName) {
                uploadZone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    uploadLabel.classList.remove('is-dragover');
                });
            });
            uploadZone.addEventListener('drop', function(e) {
                if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) {
                    return;
                }
                if (typeof DataTransfer !== 'undefined') {
                    var dt = new DataTransfer();
                    Array.prototype.forEach.call(e.dataTransfer.files, function(file) {
                        if (file.type && file.type.indexOf('image/') === 0) {
                            dt.items.add(file);
                        }
                    });
                    fileInput.files = dt.files;
                }
                renderPreview(fileInput.files);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($open_modal === 'text' || $open_modal === 'images'): ?>
        document.body.style.overflow = 'hidden';
        <?php endif; ?>

        initTrendingImageUpload();

        ['trendingTextModal', 'trendingImagesModal'].forEach(function(id) {
            var modal = document.getElementById(id);
            if (!modal) {
                return;
            }
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeTrendingModal(id);
                }
            });
        });
    });
    </script>
</body>
</html>
