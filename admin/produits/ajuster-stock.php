<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Page d'ajustement du stock d'un produit
 * Affiche: stock total, quantité vendue, stock restant (total - vendu), comptabilité, formulaire d'ajustement, historique
 */

session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/image_optimizer.php';

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($produit_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_ajuster_stock_produit($produit_id);

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: ajuster-stock.php?id=' . $produit_id);
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_commandes.php';
require_once __DIR__ . '/../../models/model_mouvements_stock.php';

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    header('Location: index.php');
    exit;
}

$quantite_vendue = get_quantite_vendue_produit($produit_id);
$stock_actuel = (int) ($produit['stock'] ?? 0);
$nombre_total = $stock_actuel + $quantite_vendue;
$stock_restant = $nombre_total - $quantite_vendue;

$prix_produit = (float) ($produit['prix'] ?? 0);
if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < $prix_produit) {
    $prix_produit = (float) $produit['prix_promotion'];
}
$valeur_stock_actuel = $stock_actuel * $prix_produit;
$valeur_ventes = $quantite_vendue * $prix_produit;

$galerie_images = [];
if (!empty($produit['images'])) {
    $dec = json_decode($produit['images'], true);
    if (is_array($dec)) {
        $galerie_images = $dec;
    }
}
if (empty($galerie_images) && !empty($produit['image_principale'])) {
    $galerie_images = [$produit['image_principale']];
}

$prix_affichage = (float) ($produit['prix'] ?? 0);
$prix_original = null;
if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < $prix_affichage) {
    $prix_original = $prix_affichage;
    $prix_affichage = (float) $produit['prix_promotion'];
}
$pourcentage_reduction = 0;
if ($prix_original) {
    $pourcentage_reduction = round((($prix_original - $prix_affichage) / $prix_original) * 100);
}

$mouvements = get_stock_mouvements(null, $produit_id, null, null, 50);

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuster le stock - <?php echo htmlspecialchars($produit['nom']); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .ajuster-stock-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 900px) {
            .ajuster-stock-layout {
                grid-template-columns: 1fr;
            }
        }

        .ajuster-stock-card {
            background: linear-gradient(135deg, #fff 0%, #fafaf8 100%);
            border: 1px solid #e5e3d8;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        .ajuster-stock-card h2 {
            margin: 0 0 20px 0;
            font-size: 16px;
            color: #6b2f20;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #918a44;
        }

        .ajuster-stock-card h2 i {
            color: #918a44;
        }

        .stock-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .stock-stat-card {
            background: #fff;
            border: 2px solid #e5e3d8;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
        }

        .stock-stat-card:hover {
            border-color: #918a44;
            box-shadow: 0 4px 12px rgba(145, 138, 68, 0.15);
        }

        .stock-stat-card h4 {
            margin: 0 0 8px 0;
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stock-stat-card .value {
            font-size: 26px;
            font-weight: 700;
            color: #918a44;
        }

        .stock-stat-card.stock-total .value {
            color: #6b2f20;
        }

        .stock-stat-card.stock-vendu .value {
            color: #c26638;
        }

        .stock-stat-card.stock-restant .value {
            color: #155724;
        }

        .comptabilite-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .comptabilite-item {
            background: #fff;
            border: 1px solid #e5e3d8;
            border-radius: 10px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .comptabilite-item label {
            font-size: 12px;
            color: #666;
        }

        .comptabilite-item .montant {
            font-size: 20px;
            font-weight: 700;
            color: #6b2f20;
        }

        .comptabilite-item .detail {
            font-size: 12px;
            color: #888;
        }

        .stock-form-block {
            background: linear-gradient(135deg, #fff 0%, #fafaf8 100%);
            border: 1px solid #e5e3d8;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        .stock-form-block h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stock-form-block h3 i {
            color: #918a44;
        }

        .stock-form-block .form-group {
            margin-bottom: 16px;
        }

        .stock-form-block input[type="number"] {
            padding: 12px 16px;
            border: 2px solid #e5e3d8;
            border-radius: 10px;
            font-size: 16px;
            max-width: 200px;
        }

        .stock-form-block input:focus {
            outline: none;
            border-color: #918a44;
        }

        .mouvements-section {
            background: #fff;
            border: 1px solid #e5e3d8;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        .mouvements-section h2 {
            margin: 0;
            padding: 20px 24px;
            font-size: 16px;
            color: #6b2f20;
            background: #f8f7f2;
            border-bottom: 2px solid #e5e3d8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mouvements-produit-table {
            width: 100%;
            border-collapse: collapse;
        }

        .mouvements-produit-table th,
        .mouvements-produit-table td {
            padding: 14px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .mouvements-produit-table th {
            background: #f8f8f8;
            font-weight: 600;
            color: #6b2f20;
            font-size: 12px;
            text-transform: uppercase;
        }

        .mouvements-produit-table tbody tr:hover {
            background: #fafaf8;
        }

        .badge-entree {
            background: #d4edda;
            color: #155724;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-sortie {
            background: #f8d7da;
            color: #721c24;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-inventaire {
            background: #fff3cd;
            color: #856404;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .produit-preview {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f8f7f2;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .produit-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e5e3d8;
        }

        .produit-preview-info h3 {
            margin: 0 0 4px 0;
            font-size: 18px;
            color: #333;
        }

        .produit-preview-info .prix {
            font-size: 14px;
            color: #918a44;
            font-weight: 600;
        }
        /* Responsive: cartes mouvements sur mobile */
        .mouvements-produit-cards { display: none; }
        .mouvement-produit-card {
            background: #fff;
            border: 1px solid #e5e3d8;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .mouvement-produit-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .mouvement-produit-card-date { font-size: 13px; color: #666; font-weight: 600; }
        .mouvement-produit-card-body { display: grid; gap: 8px; }
        .mouvement-produit-card-row { display: flex; justify-content: space-between; font-size: 13px; }
        .mouvement-produit-card-row .label { color: #888; }
        .mouvement-produit-card-row .value { font-weight: 600; color: #333; }
        .mouvement-produit-card-notes { font-size: 12px; color: #666; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #eee; }
        @media (max-width: 768px) {
            .mouvements-produit-table-wrap { display: none !important; }
            .mouvements-produit-cards { display: block; padding: 16px; }
        }
        @media (min-width: 769px) {
            .mouvements-produit-cards { display: none !important; }
        }
    </style>
</head>

<body>

    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-boxes-stacked"></i> Ajuster le stock - <?php echo htmlspecialchars($produit['nom']); ?>
        </h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour aux produits
            </a>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($result['message']); ?>
        </div>
    <?php endif; ?>

    <div class="produit-preview">
        <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'sm')); ?>" alt=""
            onerror="this.src='/image/produit1.jpg'">
        <div class="produit-preview-info">
            <h3><?php echo htmlspecialchars($produit['nom']); ?></h3>
            <span class="prix"><?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA / unité</span>
        </div>
    </div>

    <div class="ajuster-stock-layout">
        <div class="ajuster-stock-card">
            <h2><i class="fas fa-chart-bar"></i> État du stock</h2>
            <div class="stock-stats-grid">
                <div class="stock-stat-card stock-total">
                    <h4>Nombre total</h4>
                    <div class="value"><?php echo $nombre_total; ?></div>
                    <small style="font-size: 11px; color: #888;">Stock initial + entrées</small>
                </div>
                <div class="stock-stat-card stock-vendu">
                    <h4>Quantité vendue</h4>
                    <div class="value"><?php echo $quantite_vendue; ?></div>
                </div>
                <div class="stock-stat-card stock-restant">
                    <h4>Stock restant</h4>
                    <div class="value"><?php echo $stock_restant; ?></div>
                    <small style="font-size: 11px; color: #888;">Total − Vendu</small>
                </div>
            </div>

            <h2 style="margin-top: 24px;"><i class="fas fa-calculator"></i> Comptabilité</h2>
            <div class="comptabilite-grid">
                <div class="comptabilite-item">
                    <label>Valeur du stock actuel</label>
                    <span class="montant"><?php echo number_format($valeur_stock_actuel, 0, ',', ' '); ?> FCFA</span>
                    <span class="detail"><?php echo $stock_actuel; ?> ×
                        <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="comptabilite-item">
                    <label>Chiffre d'affaires (ventes)</label>
                    <span class="montant"><?php echo number_format($valeur_ventes, 0, ',', ' '); ?> FCFA</span>
                    <span class="detail"><?php echo $quantite_vendue; ?> vendu(s) ×
                        <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</span>
                </div>
            </div>
        </div>

        <div>
            <div class="stock-form-block">
                <h3><i class="fas fa-edit"></i> Ajuster le stock</h3>
                <form method="POST" action="?id=<?php echo $produit_id; ?>">
                    <input type="hidden" name="ajuster_stock" value="1">
                    <div class="form-group">
                        <label for="nouveau_stock">Nouvelle quantité de stock</label>
                        <input type="number" id="nouveau_stock" name="nouveau_stock" min="0" required
                            value="<?php echo $stock_actuel; ?>" placeholder="0">
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> Ajuster le stock
                    </button>
                </form>
            </div>
        </div>
    </div>

    <section class="mouvements-section" style="margin-top: 24px;">
        <h2><i class="fas fa-history"></i> Historique des mouvements (<?php echo count($mouvements); ?>)</h2>
        <?php if (empty($mouvements)): ?>
            <p style="padding: 24px; color: #666;">Aucun mouvement enregistré pour ce produit.</p>
        <?php else: ?>
            <div class="mouvements-produit-table-wrap" style="overflow-x: auto;">
                <table class="mouvements-produit-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Quantité</th>
                            <th>Avant</th>
                            <th>Après</th>
                            <th>Référence</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements as $m): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></td>
                                <td>
                                    <?php
                                    $badge = 'badge-' . $m['type'];
                                    $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                                    ?>
                                    <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                                </td>
                                <td><?php echo (int) $m['quantite']; ?></td>
                                <td><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></td>
                                <td><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></td>
                                <td><?php echo htmlspecialchars($m['reference_numero'] ?? ($m['reference_type'] ?? '-')); ?>
                                </td>
                                <td><?php echo htmlspecialchars($m['notes'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mouvements-produit-cards">
                <?php foreach ($mouvements as $m):
                    $badge = 'badge-' . $m['type'];
                    $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                    $ref = htmlspecialchars($m['reference_numero'] ?? ($m['reference_type'] ?? '-'));
                ?>
                <div class="mouvement-produit-card">
                    <div class="mouvement-produit-card-header">
                        <span class="mouvement-produit-card-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></span>
                        <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                    </div>
                    <div class="mouvement-produit-card-body">
                        <div class="mouvement-produit-card-row">
                            <span class="label">Quantité</span>
                            <span class="value"><?php echo (int) $m['quantite']; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Avant</span>
                            <span class="value"><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Après</span>
                            <span class="value"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Référence</span>
                            <span class="value"><?php echo $ref; ?></span>
                        </div>
                    </div>
                    <?php if (!empty($m['notes'])): ?>
                    <div class="mouvement-produit-card-notes"><?php echo htmlspecialchars($m['notes']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>

</html>