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
require_once __DIR__ . '/../../includes/image_optimizer.php';
$config = get_trending_config();
$spotlight_images = get_trending_spotlight_images();
$has_saved_text = is_array($config) && (int) ($config['id'] ?? 0) > 0;

$error_message = '';
$result = null;
$open_modal = '';
$edit_image = null;

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
        if (!empty($result['image_id'])) {
            $edit_image = get_trending_spotlight_image_by_id((int) $result['image_id']);
        }
    }
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($open_modal === '' && isset($_GET['add_text'])) {
    $open_modal = 'text';
}
if ($open_modal === '' && isset($_GET['edit_text'])) {
    $open_modal = 'text';
}
if ($open_modal === '' && isset($_GET['add_images'])) {
    $open_modal = 'images';
}
if ($open_modal === '' && isset($_GET['edit_image'])) {
    $open_modal = 'images';
    $edit_image = get_trending_spotlight_image_by_id((int) $_GET['edit_image']);
    if (!$edit_image) {
        $open_modal = '';
        $error_message = 'Image introuvable';
    }
}

$form_label = isset($_POST['label']) ? (string) $_POST['label'] : (string) ($config['label'] ?? '');
$form_titre = isset($_POST['titre']) ? (string) $_POST['titre'] : (string) ($config['titre'] ?? '');
$form_description = isset($_POST['description']) ? (string) $_POST['description'] : (string) ($config['description'] ?? '');
$form_bouton_texte = isset($_POST['bouton_texte']) ? (string) $_POST['bouton_texte'] : (string) ($config['bouton_texte'] ?? 'Découvrir');
$form_bouton_lien = isset($_POST['bouton_lien']) ? (string) $_POST['bouton_lien'] : (string) ($config['bouton_lien'] ?? 'section-produits.php?section=kit_impression');
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
        .trending-text-card {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 14px;
            padding: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: space-between;
            align-items: flex-start;
        }
        .trending-text-card dl {
            margin: 0;
            flex: 1 1 240px;
        }
        .trending-text-card dt {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #888;
            margin-top: 10px;
        }
        .trending-text-card dt:first-child {
            margin-top: 0;
        }
        .trending-text-card dd {
            margin: 4px 0 0;
            color: var(--titres);
            font-weight: 600;
            word-break: break-word;
        }
        .trending-text-actions,
        .trending-image-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .trending-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 14px;
        }
        .trending-image-card {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 14px;
            overflow: hidden;
        }
        .trending-image-card img {
            width: 100%;
            height: 130px;
            object-fit: cover;
            display: block;
        }
        .trending-image-card .trending-image-actions {
            padding: 10px;
        }
        .trending-image-card form {
            flex: 1;
            margin: 0;
        }
        .trending-text-card .btn-edit,
        .trending-image-card .btn-edit,
        .trending-image-card .btn-delete {
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
        .trending-text-card .btn-edit,
        .trending-image-card .btn-edit {
            background: var(--couleur-dominante);
            color: #fff;
        }
        .trending-image-card .btn-delete {
            width: 100%;
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
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <section class="produits-section">
        <div class="videos-header">
            <div>
                <h2><i class="fas fa-star"></i> Section Mise en Avant</h2>
                <p>Bannière premium sous les services sur la page d'accueil.</p>
            </div>
            <div class="trending-header-actions">
                <a href="trending.php?add_text=1" class="btn-add-video">
                    <i class="fas fa-font"></i> Ajouter le texte
                </a>
                <a href="trending.php?add_images=1" class="btn-add-video">
                    <i class="fas fa-images"></i> Ajouter des images
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
            <h3><i class="fas fa-align-left"></i> Texte enregistré</h3>
            <?php if ($has_saved_text): ?>
            <div class="trending-text-card">
                <dl>
                    <dt>Label</dt>
                    <dd><?php echo htmlspecialchars($config['label'] ?? ''); ?></dd>
                    <dt>Titre principal</dt>
                    <dd><?php echo htmlspecialchars(str_replace('|', ' · ', (string) ($config['titre'] ?? ''))); ?></dd>
                    <dt>Description</dt>
                    <dd><?php echo trim((string) ($config['description'] ?? '')) !== '' ? htmlspecialchars($config['description']) : '—'; ?></dd>
                    <dt>Bouton</dt>
                    <dd><?php echo htmlspecialchars((string) ($config['bouton_texte'] ?? 'Découvrir')); ?></dd>
                </dl>
                <div class="trending-text-actions">
                    <a href="trending.php?edit_text=1" class="btn-edit">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-font"></i>
                <h3>Aucun texte enregistré</h3>
                <p>Cliquez sur « Ajouter le texte » pour configurer la bannière.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="trending-block">
            <h3><i class="fas fa-images"></i> Images du carrousel</h3>
            <?php if (!empty($spotlight_images)): ?>
            <div class="trending-images-grid">
                <?php foreach ($spotlight_images as $spotlight_image): ?>
                <div class="trending-image-card">
                    <img src="<?php echo htmlspecialchars(upload_subdir_image_url('trending', $spotlight_image['image'] ?? '', 'md')); ?>"
                         alt="Image carrousel">
                    <div class="trending-image-actions">
                        <a href="trending.php?edit_image=<?php echo (int) $spotlight_image['id']; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <form method="POST" action="" onsubmit="return confirm('Supprimer cette image ?');">
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="delete_spotlight_image_id" value="<?php echo (int) $spotlight_image['id']; ?>">
                            <button type="submit" class="btn-delete">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <h3>Aucune image pour le moment</h3>
                <p>Cliquez sur « Ajouter des images » pour le carrousel visuel.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="modal-overlay<?php echo $open_modal === 'text' ? ' active' : ''; ?>" id="trendingTextModal">
        <div class="modal-content trending-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-<?php echo $has_saved_text ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $has_saved_text ? 'Modifier le texte' : 'Ajouter le texte'; ?>
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
                    <label for="bouton_lien">
                        <i class="fas fa-link"></i> Lien du bouton
                    </label>
                    <input type="text" id="bouton_lien" name="bouton_lien"
                           value="<?php echo htmlspecialchars($form_bouton_lien); ?>"
                           placeholder="Ex: section-produits.php?section=kit_impression">
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

    <div class="modal-overlay<?php echo $open_modal === 'images' ? ' active' : ''; ?>" id="trendingImagesModal">
        <div class="modal-content trending-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-<?php echo $edit_image ? 'edit' : 'images'; ?>"></i>
                    <?php echo $edit_image ? 'Modifier l’image' : 'Ajouter des images'; ?>
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

            <form method="POST" action="" enctype="multipart/form-data" class="form-add">
                <input type="hidden" name="MAX_FILE_SIZE" value="52428800">
                <?php if ($edit_image): ?>
                <input type="hidden" name="action" value="replace_image">
                <input type="hidden" name="image_id" value="<?php echo (int) $edit_image['id']; ?>">
                <div class="form-group">
                    <label>Image actuelle</label>
                    <div class="trending-current-image">
                        <img src="<?php echo htmlspecialchars(upload_subdir_image_url('trending', $edit_image['image'] ?? '', 'md')); ?>" alt="Image actuelle">
                    </div>
                </div>
                <div class="form-group">
                    <label for="spotlight_image">
                        <i class="fas fa-image"></i> Nouvelle image
                    </label>
                    <div class="file-input-wrapper">
                        <label for="spotlight_image" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Remplacer l’image</span>
                        </label>
                        <input type="file" id="spotlight_image" name="spotlight_image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="file-input" required>
                    </div>
                </div>
                <?php else: ?>
                <input type="hidden" name="action" value="add_images">
                <div class="form-group">
                    <label for="spotlight_images">
                        <i class="fas fa-images"></i> Images du carrousel
                    </label>
                    <small class="form-help">Ajoutez une ou plusieurs images. Si plus d'une image est présente, un carrousel s'affiche sur l'accueil.</small>
                    <div class="file-input-wrapper">
                        <label for="spotlight_images" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choisir des images</span>
                        </label>
                        <input type="file" id="spotlight_images" name="spotlight_images[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="file-input" multiple required>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> <?php echo $edit_image ? 'Mettre à jour l’image' : 'Ajouter les images'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeTrendingModal('trendingImagesModal')">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
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

    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($open_modal === 'text' || $open_modal === 'images'): ?>
        document.body.style.overflow = 'hidden';
        <?php endif; ?>

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
