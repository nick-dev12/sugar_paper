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
require_once __DIR__ . '/../../models/model_variantes.php';

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
$variantes = get_variantes_by_produit($produit_id);

$couleurs_options = [];
if (!empty($produit['couleurs'])) {
    $cr = trim($produit['couleurs']);
    $dec_couleurs = json_decode($cr, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($dec_couleurs)) {
        $couleurs_options = array_values(array_filter($dec_couleurs, function ($c) {
            return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
        }));
    } else {
        $couleurs_options = array_values(array_filter(array_map('trim', explode(',', $cr))));
    }
}
$poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
$taille_options = parse_options_with_surcharge($produit['taille'] ?? null);
$poids_options = array_values(array_filter($poids_options, function ($o) {
    $v = trim((string) ($o['v'] ?? ''));
    return $v !== '' && $v !== '[]';
}));
$taille_options = array_values(array_filter($taille_options, function ($o) {
    $v = trim((string) ($o['v'] ?? ''));
    return $v !== '' && $v !== '[]';
}));

$statut_labels = [
    'actif' => 'Actif',
    'inactif' => 'Inactif',
    'rupture_stock' => 'Rupture de stock',
];
$statut_label = $statut_labels[$produit['statut'] ?? ''] ?? ($produit['statut'] ?? '—');

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
    <link rel="stylesheet" href="/css/image-lightbox.css<?php echo asset_version_query(); ?>">
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

        .produit-detail-card {
            background: linear-gradient(135deg, #fff 0%, #fafaf8 100%);
            border: 1px solid #e5e3d8;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        .produit-detail-card h2 {
            margin: 0 0 20px 0;
            font-size: 16px;
            color: #6b2f20;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #918a44;
        }

        .produit-detail-layout {
            display: grid;
            grid-template-columns: minmax(220px, 320px) 1fr;
            gap: 24px;
        }

        @media (max-width: 900px) {
            .produit-detail-layout {
                grid-template-columns: 1fr;
            }
        }

        .produit-detail-gallery-main {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #e5e3d8;
            background: #fff;
            margin-bottom: 12px;
        }

        .produit-detail-gallery-main img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .produit-detail-gallery-thumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .produit-detail-gallery-thumb {
            width: 64px;
            height: 64px;
            padding: 0;
            border: 2px solid #e5e3d8;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            cursor: zoom-in;
        }

        .produit-detail-gallery-thumb.is-active {
            border-color: #918a44;
            box-shadow: 0 0 0 2px rgba(145, 138, 68, 0.25);
        }

        .produit-detail-gallery-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .produit-detail-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .produit-detail-meta {
                grid-template-columns: 1fr;
            }
        }

        .produit-detail-meta-item {
            background: #fff;
            border: 1px solid #e5e3d8;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .produit-detail-meta-item label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #888;
            margin-bottom: 4px;
        }

        .produit-detail-meta-item span {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .produit-detail-description {
            background: #fff;
            border: 1px solid #e5e3d8;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
            line-height: 1.6;
            color: #444;
            white-space: pre-wrap;
        }

        .produit-detail-block {
            margin-bottom: 18px;
        }

        .produit-detail-block h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #6b2f20;
        }

        .produit-options-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .produit-option-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid #e5e3d8;
            background: #fff;
            font-size: 13px;
            color: #333;
        }

        .produit-option-chip .surcout {
            color: #c26638;
            font-weight: 600;
            font-size: 12px;
        }

        .produit-color-swatch {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.15);
            display: inline-block;
        }

        .produit-variantes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
        }

        .produit-variante-card {
            background: #fff;
            border: 1px solid #e5e3d8;
            border-radius: 10px;
            overflow: hidden;
        }

        .produit-variante-card img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
            cursor: zoom-in;
        }

        .produit-variante-card-body {
            padding: 10px 12px;
        }

        .produit-variante-card-body strong {
            display: block;
            font-size: 13px;
            color: #333;
            margin-bottom: 4px;
        }

        .produit-variante-card-body span {
            font-size: 12px;
            color: #918a44;
            font-weight: 600;
        }

        .produit-detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .produit-detail-actions a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #918a44;
            color: #918a44;
            background: #fff;
        }

        .produit-detail-actions a:hover {
            background: #918a44;
            color: #fff;
        }

        .produit-detail-empty {
            color: #888;
            font-size: 13px;
            font-style: italic;
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

    <section class="produit-detail-card">
        <h2><i class="fas fa-box-open"></i> Détails du produit</h2>
        <div class="produit-detail-layout">
            <div>
                <?php
                $main_gallery_src = upload_image_url($galerie_images[0] ?? ($produit['image_principale'] ?? ''), 'original');
                ?>
                <div class="produit-detail-gallery-main">
                    <img src="<?php echo htmlspecialchars($main_gallery_src); ?>"
                        alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                        id="ajuster-stock-gallery-main"
                        class="js-sugar-lightbox-trigger"
                        data-lightbox-src="<?php echo htmlspecialchars($main_gallery_src); ?>"
                        data-lightbox-alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                        role="button" tabindex="0" aria-label="Voir l'image en plein écran"
                        onerror="this.src='/image/produit1.jpg'">
                </div>
                <?php if (count($galerie_images) > 1): ?>
                    <div class="produit-detail-gallery-thumbs">
                        <?php foreach ($galerie_images as $idx => $img_path):
                            $full_src = upload_image_url($img_path, 'original');
                            $thumb_src = upload_image_url($img_path, 'sm');
                        ?>
                            <button type="button"
                                class="produit-detail-gallery-thumb <?php echo $idx === 0 ? 'is-active' : ''; ?>"
                                data-index="<?php echo (int) $idx; ?>"
                                data-full-src="<?php echo htmlspecialchars($full_src); ?>"
                                aria-label="Image <?php echo (int) $idx + 1; ?>">
                                <img src="<?php echo htmlspecialchars($thumb_src); ?>" alt=""
                                    onerror="this.src='/image/produit1.jpg'">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="margin:0 0 8px 0;font-size:22px;color:#333;"><?php echo htmlspecialchars($produit['nom']); ?></h3>
                <p style="margin:0 0 16px 0;font-size:16px;font-weight:700;color:#918a44;">
                    <?php if ($prix_original): ?>
                        <span style="text-decoration:line-through;color:#999;font-weight:500;margin-right:8px;">
                            <?php echo number_format($prix_original, 0, ',', ' '); ?> FCFA
                        </span>
                    <?php endif; ?>
                    <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                    <?php if ($prix_original && $pourcentage_reduction > 0): ?>
                        <span style="font-size:12px;color:#c26638;">(-<?php echo (int) $pourcentage_reduction; ?> %)</span>
                    <?php endif; ?>
                </p>

                <div class="produit-detail-meta">
                    <div class="produit-detail-meta-item">
                        <label>Catégorie</label>
                        <span><?php echo htmlspecialchars($produit['categorie_nom'] ?? '—'); ?></span>
                    </div>
                    <div class="produit-detail-meta-item">
                        <label>Statut</label>
                        <span><?php echo htmlspecialchars($statut_label); ?></span>
                    </div>
                    <div class="produit-detail-meta-item">
                        <label>Stock actuel</label>
                        <span><?php echo (int) $stock_actuel; ?></span>
                    </div>
                    <div class="produit-detail-meta-item">
                        <label>Unité</label>
                        <span><?php echo htmlspecialchars(trim($produit['unite'] ?? '') !== '' ? $produit['unite'] : '—'); ?></span>
                    </div>
                    <?php if (!empty($produit['section_accueil'])): ?>
                    <div class="produit-detail-meta-item">
                        <label>Section accueil</label>
                        <span><?php echo htmlspecialchars($produit['section_accueil']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="produit-detail-meta-item">
                        <label>ID produit</label>
                        <span>#<?php echo (int) $produit_id; ?></span>
                    </div>
                </div>

                <?php if (!empty(trim($produit['description'] ?? ''))): ?>
                    <div class="produit-detail-block">
                        <h3>Description</h3>
                        <div class="produit-detail-description"><?php echo nl2br(htmlspecialchars($produit['description'])); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($couleurs_options)): ?>
                    <div class="produit-detail-block">
                        <h3>Couleurs disponibles</h3>
                        <div class="produit-options-list">
                            <?php foreach ($couleurs_options as $couleur): ?>
                                <span class="produit-option-chip">
                                    <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)): ?>
                                        <span class="produit-color-swatch" style="background:<?php echo htmlspecialchars($couleur); ?>;"></span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($couleur); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($poids_options)): ?>
                    <div class="produit-detail-block">
                        <h3>Options poids / format</h3>
                        <div class="produit-options-list">
                            <?php foreach ($poids_options as $opt): ?>
                                <span class="produit-option-chip">
                                    <?php echo htmlspecialchars($opt['v']); ?>
                                    <?php if (!empty($opt['s'])): ?>
                                        <span class="surcout">+<?php echo number_format((float) $opt['s'], 0, ',', ' '); ?> FCFA</span>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($taille_options)): ?>
                    <div class="produit-detail-block">
                        <h3>Options taille</h3>
                        <div class="produit-options-list">
                            <?php foreach ($taille_options as $opt): ?>
                                <span class="produit-option-chip">
                                    <?php echo htmlspecialchars($opt['v']); ?>
                                    <?php if (!empty($opt['s'])): ?>
                                        <span class="surcout">+<?php echo number_format((float) $opt['s'], 0, ',', ' '); ?> FCFA</span>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($variantes)): ?>
                    <div class="produit-detail-block">
                        <h3>Variantes (<?php echo count($variantes); ?>)</h3>
                        <div class="produit-variantes-grid">
                            <?php foreach ($variantes as $variante):
                                $var_img = !empty($variante['image']) ? upload_image_url($variante['image'], 'md') : upload_image_url($produit['image_principale'] ?? '', 'md');
                                $var_full = !empty($variante['image']) ? upload_image_url($variante['image'], 'original') : upload_image_url($produit['image_principale'] ?? '', 'original');
                                $var_prix = (float) ($variante['prix'] ?? 0);
                            ?>
                                <div class="produit-variante-card">
                                    <img src="<?php echo htmlspecialchars($var_img); ?>"
                                        alt="<?php echo htmlspecialchars($variante['nom'] ?? ''); ?>"
                                        class="js-sugar-lightbox-trigger"
                                        data-lightbox-src="<?php echo htmlspecialchars($var_full); ?>"
                                        data-lightbox-alt="<?php echo htmlspecialchars($variante['nom'] ?? ''); ?>"
                                        role="button" tabindex="0"
                                        onerror="this.src='/image/produit1.jpg'">
                                    <div class="produit-variante-card-body">
                                        <strong><?php echo htmlspecialchars($variante['nom'] ?? ''); ?></strong>
                                        <span><?php echo number_format($var_prix, 0, ',', ' '); ?> FCFA</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($couleurs_options) && empty($poids_options) && empty($taille_options) && empty($variantes) && empty(trim($produit['description'] ?? ''))): ?>
                    <p class="produit-detail-empty">Aucune option ou variante configurée pour ce produit.</p>
                <?php endif; ?>

                <div class="produit-detail-actions">
                    <a href="modifier.php?id=<?php echo (int) $produit_id; ?>"><i class="fas fa-edit"></i> Modifier le produit</a>
                    <a href="../../produit.php?id=<?php echo (int) $produit_id; ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Voir sur le site</a>
                </div>
            </div>
        </div>
    </section>

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
    <script src="/js/image-lightbox.js<?php echo asset_version_query(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.SugarImageLightbox) {
                return;
            }
            SugarImageLightbox.bindAll('.js-sugar-lightbox-trigger');

            var mainImg = document.getElementById('ajuster-stock-gallery-main');
            var thumbs = document.querySelectorAll('.produit-detail-gallery-thumb');
            thumbs.forEach(function (thumb) {
                thumb.addEventListener('click', function () {
                    var fullSrc = thumb.getAttribute('data-full-src');
                    if (!fullSrc || !mainImg) {
                        return;
                    }
                    mainImg.src = fullSrc;
                    mainImg.setAttribute('data-lightbox-src', fullSrc);
                    thumbs.forEach(function (t) {
                        t.classList.toggle('is-active', t === thumb);
                    });
                });
                thumb.addEventListener('dblclick', function (event) {
                    event.preventDefault();
                    var fullSrc = thumb.getAttribute('data-full-src');
                    if (fullSrc) {
                        SugarImageLightbox.open(fullSrc, mainImg ? mainImg.getAttribute('alt') : '');
                    }
                });
            });
        });
    </script>
</body>

</html>