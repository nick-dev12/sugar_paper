<?php
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce();
require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_zones_livraison()) {
    header('Location: ../dashboard.php');
    exit;
}
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
require_once __DIR__ . '/../../models/model_zones_livraison.php';
$zones = get_all_zones_livraison(null);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zones de livraison - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-zones-livraison-index.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-zones-livraison-index">
    <?php include '../includes/nav.php'; ?>
    <div class="content-header">
        <h1><i class="fas fa-truck"></i> Zones de livraison</h1>
        <div class="header-actions">
            <a href="ajouter.php" class="btn-primary"><i class="fas fa-plus"></i> Nouvelle zone</a>
        </div>
    </div>
    <?php if (!empty($success_message)): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <section class="content-section">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="fas fa-map-marker-alt"></i> Lieux et tarifs de livraison (<?php echo count($zones); ?>)</h2>
            </div>
        </div>
        <?php if (empty($zones)): ?>
            <div class="empty-state">
                <i class="fas fa-truck"></i>
                <h3>Aucune zone de livraison</h3>
                <p>Définissez les zones (ville, quartier) et les prix de livraison pour que les clients puissent sélectionner leur adresse lors de la commande.</p>
                <a href="ajouter.php" class="btn-primary"><i class="fas fa-plus"></i> Ajouter la première zone</a>
            </div>
        <?php else: ?>
            <div class="zones-table-wrap">
                <table class="data-table zones-data-table">
                    <colgroup>
                        <col class="zones-col-lieu">
                        <col class="zones-col-prix">
                        <col class="zones-col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Quartier / Zone</th>
                            <th class="zones-col-prix">Prix livraison</th>
                            <th class="zones-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                        <tr>
                            <td data-label="Quartier / Zone">
                                <span class="zones-cell-quartier"><?php echo htmlspecialchars($zone['quartier']); ?></span>
                                <span class="zones-cell-ville"><?php echo htmlspecialchars($zone['ville']); ?></span>
                            </td>
                            <td data-label="Prix livraison" class="zones-col-prix">
                                <span class="zones-cell-prix"><?php echo number_format((float) $zone['prix_livraison'], 0, ',', ' '); ?> FCFA</span>
                            </td>
                            <td data-label="Actions" class="zones-col-actions">
                                <div class="zones-actions">
                                    <a href="modifier.php?id=<?php echo (int) $zone['id']; ?>" class="btn-edit" title="Modifier">
                                        <i class="fas fa-edit" aria-hidden="true"></i><span>Modifier</span>
                                    </a>
                                    <a href="supprimer.php?id=<?php echo (int) $zone['id']; ?>" class="btn-delete" title="Supprimer" onclick="return confirm('Supprimer cette zone ?');">
                                        <i class="fas fa-trash" aria-hidden="true"></i><span>Supprimer</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php include '../includes/footer.php'; ?>
