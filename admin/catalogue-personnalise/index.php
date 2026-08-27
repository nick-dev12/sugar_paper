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

$action_result = process_cp_catalogue_dossier_actions();
$dossiers = get_all_cp_dossiers();
$csrf = cp_catalogue_admin_csrf_token();
$next_position = 1;
if (!empty($dossiers)) {
    $positions = array_map(function ($d) {
        return (int) ($d['position'] ?? 0);
    }, $dossiers);
    $next_position = max($positions) + 1;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration catalogue - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-form.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-catalogue-personnalise.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-cp-catalogue-index">
    <?php include '../includes/nav.php'; ?>
    <div class="content-header">
        <h1><i class="fas fa-folder-tree"></i> Configuration catalogue</h1>
        <p class="content-header-sub">Organisez les dossiers et produits affichés sur la page commande personnalisée.</p>
    </div>

    <?php if (!empty($action_result['message'])): ?>
    <div class="message <?php echo !empty($action_result['success']) ? 'success' : 'error'; ?>">
        <i class="fas fa-<?php echo !empty($action_result['success']) ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($action_result['message']); ?>
    </div>
    <?php endif; ?>

    <section class="content-section cp-catalogue-section">
        <div class="cp-catalogue-toolbar">
            <div class="option-card cp-reveal-card" id="add-dossier-card">
                <button type="button" class="btn-reveal-option" id="btn-add-dossier">
                    <i class="fas fa-plus"></i> Ajouter un dossier
                </button>
                <form method="POST" class="option-reveal-form" id="form-add-dossier">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="add_dossier">
                    <label class="option-reveal-label" for="add_nom">Nom du dossier *</label>
                    <input type="text" id="add_nom" name="nom" class="options-input" required maxlength="255" placeholder="Ex. : Cake toppers">
                    <label class="option-reveal-label" for="add_position">Position</label>
                    <input type="number" id="add_position" name="position" class="options-input" min="0" value="<?php echo (int) $next_position; ?>">
                    <div class="option-reveal-actions">
                        <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Valider</button>
                        <button type="button" class="btn-secondary btn-cancel-reveal"><i class="fas fa-times"></i> Annuler</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($dossiers)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>Aucun dossier</h3>
            <p>Créez votre premier dossier pour organiser les produits du catalogue personnalisé.</p>
        </div>
        <?php else: ?>
        <div class="cp-dossiers-grid">
            <?php foreach ($dossiers as $dossier): ?>
            <article class="cp-dossier-card">
                <a href="dossier.php?id=<?php echo (int) $dossier['id']; ?>" class="cp-dossier-card__link">
                    <span class="cp-dossier-card__icon"><i class="fas fa-folder"></i></span>
                    <h2><?php echo htmlspecialchars($dossier['nom']); ?></h2>
                    <p class="cp-dossier-card__meta">
                        Position <?php echo (int) $dossier['position']; ?>
                        · <?php echo (int) ($dossier['nb_produits'] ?? 0); ?> produit(s)
                    </p>
                </a>
                <div class="cp-dossier-card__actions">
                    <button type="button" class="btn-edit btn-edit-dossier" data-dossier-id="<?php echo (int) $dossier['id']; ?>">
                        <i class="fas fa-edit"></i> Modifier
                    </button>
                    <?php if ((int) ($dossier['nb_produits'] ?? 0) === 0): ?>
                    <form method="POST" class="cp-inline-form" onsubmit="return confirm('Supprimer ce dossier ?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="delete_dossier">
                        <input type="hidden" name="dossier_id" value="<?php echo (int) $dossier['id']; ?>">
                        <button type="submit" class="btn-delete"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <form method="POST" class="option-reveal-form cp-edit-dossier-form" id="edit-dossier-<?php echo (int) $dossier['id']; ?>" hidden>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="update_dossier">
                    <input type="hidden" name="dossier_id" value="<?php echo (int) $dossier['id']; ?>">
                    <label class="option-reveal-label">Nom du dossier *</label>
                    <input type="text" name="nom" class="options-input" required maxlength="255" value="<?php echo htmlspecialchars($dossier['nom']); ?>">
                    <label class="option-reveal-label">Position</label>
                    <input type="number" name="position" class="options-input" min="0" value="<?php echo (int) $dossier['position']; ?>">
                    <div class="option-reveal-actions">
                        <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Valider</button>
                        <button type="button" class="btn-secondary btn-cancel-edit-dossier"><i class="fas fa-times"></i> Annuler</button>
                    </div>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <script src="/js/admin-catalogue-personnalise.js<?php echo asset_version_query(); ?>"></script>
    <?php include '../includes/footer.php'; ?>
