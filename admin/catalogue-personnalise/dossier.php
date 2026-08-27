<?php
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce();

require_once __DIR__ . '/../../controllers/controller_cp_catalogue.php';
require_once __DIR__ . '/../../includes/asset_version.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';

$dossier_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$dossier = get_cp_dossier_by_id($dossier_id);
if (!$dossier) {
    header('Location: index.php');
    exit;
}

$action_result = process_cp_catalogue_produit_actions($dossier_id);
$produits = get_cp_produits_by_dossier($dossier_id);
$csrf = cp_catalogue_admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dossier['nom']); ?> - Catalogue</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-form.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-catalogue-personnalise.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-cp-catalogue-dossier">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <div class="cp-dossier-header">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dossiers</a>
            <div>
                <h1><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($dossier['nom']); ?></h1>
                <p class="content-header-sub"><?php echo count($produits); ?> produit(s) dans ce dossier</p>
            </div>
        </div>
    </div>

    <?php if (!empty($action_result['message'])): ?>
    <div class="message <?php echo !empty($action_result['success']) ? 'success' : 'error'; ?>">
        <i class="fas fa-<?php echo !empty($action_result['success']) ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($action_result['message']); ?>
    </div>
    <?php endif; ?>

    <section class="content-section cp-catalogue-section">
        <div class="cp-catalogue-toolbar">
            <div class="option-card cp-reveal-card" id="add-produit-card">
                <button type="button" class="btn-reveal-option" id="btn-add-produit">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </button>
                <form method="POST" enctype="multipart/form-data" class="option-reveal-form" id="form-add-produit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="add_produit">
                    <label class="option-reveal-label" for="produit_nom">Nom du produit *</label>
                    <input type="text" id="produit_nom" name="nom" class="options-input" required maxlength="255" placeholder="Ex. : Cake topper prénom 3D">
                    <label class="option-reveal-label" for="produit_image">Image *</label>
                    <input type="file" id="produit_image" name="image" class="options-input file-input-single" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" required>
                    <p class="option-reveal-hint">JPG, PNG, WEBP ou GIF — 1 image par produit.</p>
                    <div class="form-row cp-price-row">
                        <div>
                            <label class="option-reveal-label" for="prix_min">Prix minimum (FCFA) *</label>
                            <input type="number" id="prix_min" name="prix_min" class="options-input" min="0" step="1" required placeholder="5000">
                        </div>
                        <div>
                            <label class="option-reveal-label" for="prix_max">Prix maximum (FCFA) *</label>
                            <input type="number" id="prix_max" name="prix_max" class="options-input" min="0" step="1" required placeholder="15000">
                        </div>
                    </div>
                    <div class="option-reveal-actions">
                        <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Valider</button>
                        <button type="button" class="btn-secondary btn-cancel-reveal"><i class="fas fa-times"></i> Annuler</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($produits)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>Aucun produit</h3>
            <p>Ajoutez des produits à ce dossier pour les afficher sur la page commande personnalisée.</p>
        </div>
        <?php else: ?>
        <div class="cp-produits-admin-grid">
            <?php foreach ($produits as $produit): ?>
            <article class="cp-produit-admin-card">
                <div class="cp-produit-admin-card__media">
                    <img src="<?php echo htmlspecialchars(upload_image_url($produit['image'], 'md')); ?>"
                        alt="<?php echo htmlspecialchars($produit['nom']); ?>" loading="lazy">
                </div>
                <div class="cp-produit-admin-card__body">
                    <h3><?php echo htmlspecialchars($produit['nom']); ?></h3>
                    <p class="cp-produit-admin-card__price">
                        <?php echo number_format((float) $produit['prix_min'], 0, ',', ' '); ?>
                        —
                        <?php echo number_format((float) $produit['prix_max'], 0, ',', ' '); ?> FCFA
                    </p>
                </div>
                <form method="POST" class="cp-produit-admin-card__delete" onsubmit="return confirm('Supprimer ce produit ?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="delete_produit">
                    <input type="hidden" name="produit_id" value="<?php echo (int) $produit['id']; ?>">
                    <button type="submit" class="btn-delete" title="Supprimer"><i class="fas fa-trash"></i></button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <script src="/js/admin-catalogue-personnalise.js<?php echo asset_version_query(); ?>"></script>
    <?php include '../includes/footer.php'; ?>
