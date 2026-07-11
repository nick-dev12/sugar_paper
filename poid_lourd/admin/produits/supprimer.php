<?php
/**
 * Page de suppression de produit
 * Programmation procédurale uniquement
 */

ob_start();

require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_route_access.php';
require_once __DIR__ . '/../../includes/flash_toast.php';

$stock_list_url = admin_route_build_url('stock/index.php');

// Récupérer l'ID du produit (GET ou POST)
$produit_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

if ($produit_id <= 0) {
    http_redirect_safe($stock_list_url);
}

// Récupérer le produit
require_once __DIR__ . '/../../models/model_produits.php';
$produit = get_produit_by_id($produit_id);

if (!$produit) {
    http_redirect_safe($stock_list_url);
}
admin_vendeur_assert_produit_owned($produit);

// Traiter la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    require_once __DIR__ . '/../../controllers/controller_produits.php';
    $result = process_delete_produit($produit_id);

    if ($result['success']) {
        flash_toast_push('success', $result['message']);
        http_redirect_safe($stock_list_url);
    }

    $error_message = $result['message'];
    flash_toast_queue_page('error', $error_message);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un Produit - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .delete-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }

        .delete-warning {
            background: #fee;
            border-left: 4px solid #c26638;
            color: #6b2f20;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .produit-info {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }

        .produit-info img {
            max-width: 150px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .btn-danger {
            background: #c26638;
            color: #ffffff;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        .btn-danger:hover {
            background: #6b2f20;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #e0e0e0;
            color: #6b2f20;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #d0d0d0;
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header">
        <h1><i class="fas fa-trash"></i> Supprimer un Produit</h1>
        <a href="<?php echo htmlspecialchars($stock_list_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Retour au stock
        </a>
    </div>

    <div class="delete-container">
        <?php if (isset($error_message)): ?>
            <div class="delete-warning">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="delete-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Attention !</strong> Cette action est irréversible. Le produit sera définitivement supprimé.
        </div>

        <div class="produit-info">
            <?php if ($produit['image_principale']): ?>
                <img src="../../upload/<?php echo htmlspecialchars($produit['image_principale']); ?>" 
                     alt="<?php echo htmlspecialchars($produit['nom']); ?>">
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($produit['nom']); ?></h3>
            <p><strong>Prix:</strong> <?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA</p>
            <p><strong>Stock:</strong> <?php echo $produit['stock']; ?> unités</p>
            <p><strong>Catégorie:</strong> <?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie'); ?></p>
        </div>

        <form method="POST" action="supprimer.php?id=<?php echo (int) $produit_id; ?>" onsubmit="return confirm('Êtes-vous absolument sûr de vouloir supprimer ce produit ? Cette action est irréversible.');">
            <input type="hidden" name="confirm_delete" value="1">
            <input type="hidden" name="id" value="<?php echo (int) $produit_id; ?>">
            <button type="submit" class="btn-danger">
                <i class="fas fa-trash"></i> Confirmer la suppression
            </button>
            <a href="<?php echo htmlspecialchars($stock_list_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn-cancel">
                <i class="fas fa-times"></i> Annuler
            </a>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>

