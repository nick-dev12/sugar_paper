<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/controllers/controller_panier.php';
require_once __DIR__ . '/includes/panier_invite.php';
require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/flash_toast.php';
require_once __DIR__ . '/includes/image_optimizer.php';

// Traitement des actions du panier
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                $result = process_update_panier();
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                break;
            case 'delete':
                $result = process_delete_from_panier();
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                break;
        }
    }
}

if ($message !== '') {
    flash_toast_queue_page($message_type !== '' ? $message_type : 'info', $message);
}

$panier_utilisateur_connecte = panier_utilisateur_est_connecte();

// Récupérer les produits du panier (BDD ou session invité)
$panier_items = panier_get_items_courant();
$panier_groups = group_panier_items_by_vendeur($panier_items);

// Calculer le total et le nombre total d'articles
$panier_total = panier_get_total_courant();
$nombre_total_articles = 0;
foreach ($panier_items as $item) {
    $nombre_total_articles += $item['quantite'];
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Mon Panier - COLObanes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <style>
        /* Styles panier - Palette COLObanes */
        .panier-container {
            max-width: 1200px;
            margin: clamp(20px, 4vw, 40px) auto;
            padding: 0 clamp(12px, 3vw, 20px);
        }

        .panier-title {
            font-size: clamp(1.35rem, 4vw, 2rem);
            font-weight: 700;
            color: var(--titres);
            margin-bottom: clamp(16px, 3vw, 30px);
            text-align: center;
            font-family: var(--font-titres);
        }

        .panier-page .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .panier-page .message.success {
            background: var(--success-bg);
            color: var(--titres);
            border: 1px solid var(--bleu);
        }

        .panier-page .message.error {
            background: var(--error-bg);
            color: var(--titres);
            border: 1px solid var(--error-border);
        }

        .panier-empty {
            text-align: center;
            padding: 60px 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
        }

        .panier-empty i {
            font-size: 64px;
            color: var(--couleur-dominante);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .panier-empty p {
            font-size: 18px;
            color: var(--texte-fonce);
            margin-bottom: 30px;
        }

        .panier-empty .btn-continuer {
            display: inline-block;
            padding: 12px 24px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .panier-empty .btn-continuer:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            color: var(--texte-clair);
        }

        .btn-commander-login {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 20px;
            background: var(--accent-promo);
            color: var(--texte-clair);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 12px;
            transition: background 0.2s, transform 0.2s;
        }

        .btn-commander-login:hover {
            background: var(--orange-fonce);
            color: var(--texte-clair);
            transform: translateY(-2px);
        }

        .panier-invite-notice {
            font-size: 13px;
            color: var(--gris-moyen);
            line-height: 1.45;
            margin: 0 0 14px;
            padding: 10px 12px;
            background: var(--bleu-pale);
            border-radius: 8px;
            border: 1px solid var(--border-input);
        }

        .panier-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        .panier-items {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .panier-vendeur-block {
            margin-bottom: 8px;
        }

        .panier-vendeur-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--couleur-dominante);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--glass-border);
        }

        .panier-vendeur-header a {
            color: #c26638;
            text-decoration: none;
        }

        .panier-vendeur-header a:hover {
            text-decoration: underline;
        }

        .panier-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            transition: all 0.3s;
        }

        .panier-item:hover {
            box-shadow: var(--ombre-gourmande);
        }

        .panier-item-img {
            flex: 0 0 120px;
            width: 120px;
            height: 120px;
            align-self: flex-start;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
            background: var(--blanc-casse, #fafafa);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .panier-item-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
        }

        .panier-item-info {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .panier-item-nom {
            font-size: 18px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
        }

        .panier-item-categorie {
            font-size: 13px;
            color: var(--couleur-dominante);
            margin-bottom: 10px;
        }

        .panier-item-prix {
            font-size: 20px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 15px;
        }

        .panier-item-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .quantite-controls {
            display: flex;
            align-items: center;
            border: 2px solid var(--couleur-dominante);
            border-radius: 8px;
            overflow: hidden;
        }

        .quantite-btn {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            width: 35px;
            height: 35px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantite-btn:hover {
            background: var(--couleur-dominante-hover);
        }

        .quantite-input {
            width: 60px;
            height: 35px;
            border: none;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--texte-fonce);
        }

        .panier-item-total {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-promo);
            margin-left: auto;
        }

        .btn-delete {
            background: var(--boutons-secondaires);
            color: var(--texte-clair);
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-delete:hover {
            background: var(--boutons-secondaires-hover);
        }

        .panier-summary {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
            box-shadow: var(--glass-shadow);
        }

        .summary-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-input);
            font-family: var(--font-titres);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 16px;
            color: var(--texte-fonce);
        }

        .summary-row.total {
            font-size: 24px;
            font-weight: 700;
            color: var(--titres);
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-input);
        }

        .btn-commander {
            width: 100%;
            padding: 15px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-commander:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            color: var(--texte-clair);
        }

        .btn-commander:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        .panier-summary .link-continuer {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: var(--couleur-dominante);
            text-decoration: none;
            font-weight: 500;
        }

        .panier-summary .link-continuer:hover {
            color: var(--orange);
            text-decoration: underline;
        }

        .panier-page .btn-update {
            padding: 8px 15px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .panier-page .badge-promo {
            font-size: 12px;
            background: var(--accent-promo);
            color: var(--texte-clair);
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }

        .panier-prix-label {
            font-size: 16px;
            color: var(--texte-fonce);
        }

        .panier-prix-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--titres);
        }

        .panier-prix-barré {
            font-size: 14px;
            color: var(--gris-moyen);
            text-decoration: line-through;
            margin-left: 10px;
        }

        .panier-update-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panier-quantite-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--texte-fonce);
        }

        .panier-total-detail {
            font-size: 14px;
            color: var(--gris-moyen);
            margin-bottom: 4px;
        }

        .panier-total-montant {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-promo);
        }

        .panier-stock-info {
            font-size: 12px;
            color: var(--gris-moyen);
            margin-top: 10px;
        }

        .panier-item-options {
            font-size: 13px;
            color: var(--couleur-dominante);
            margin-bottom: 8px;
        }

        .panier-item-options .opt-swatch {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.2);
            vertical-align: middle;
        }

        .summary-value {
            font-weight: 600;
            color: var(--titres);
        }

        .summary-value-alt {
            font-weight: 600;
            color: var(--couleur-dominante);
        }

        .summary-subtotal {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border-input);
        }

        .summary-livraison {
            color: var(--gris-moyen);
        }

        .summary-total-value {
            color: var(--titres);
        }

        .panier-delete-form {
            display: inline;
        }

        /* Responsive — adaptation progressive par taille d'écran */
        @media (max-width: 968px) {
            .panier-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .panier-summary {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .panier-empty {
                padding: 40px 16px;
                border-radius: 10px;
            }

            .panier-empty i {
                font-size: 48px;
                margin-bottom: 14px;
            }

            .panier-empty p {
                font-size: 0.95rem;
                margin-bottom: 20px;
            }

            .panier-empty .btn-continuer {
                padding: 10px 18px;
                font-size: 0.88rem;
            }

            .panier-items {
                gap: 12px;
            }

            .panier-vendeur-header {
                font-size: 0.95rem;
                margin-bottom: 8px;
                padding-bottom: 6px;
            }

            .panier-item {
                flex-direction: row;
                align-items: flex-start;
                gap: 12px;
                padding: 12px;
                border-radius: 10px;
            }

            .panier-item-img {
                flex: 0 0 84px;
                width: 84px;
                height: 84px;
                border-radius: 8px;
            }

            .panier-item-nom {
                font-size: 0.92rem;
                margin-bottom: 4px;
                line-height: 1.3;
            }

            .panier-item-categorie {
                font-size: 0.72rem;
                margin-bottom: 6px;
            }

            .panier-item-options {
                font-size: 0.72rem;
                margin-bottom: 6px;
            }

            .panier-item-prix {
                font-size: 0.88rem;
                margin-bottom: 10px;
            }

            .panier-prix-label {
                font-size: 0.78rem;
            }

            .panier-prix-value {
                font-size: 0.92rem;
            }

            .panier-prix-barré {
                font-size: 0.72rem;
                margin-left: 6px;
            }

            .panier-page .badge-promo {
                font-size: 0.62rem;
                padding: 1px 5px;
            }

            .panier-item-controls {
                gap: 8px;
            }

            .panier-update-form {
                gap: 6px;
            }

            .panier-quantite-label {
                font-size: 0.72rem;
            }

            .quantite-btn {
                width: 30px;
                height: 30px;
                font-size: 0.85rem;
            }

            .quantite-input {
                width: 44px;
                height: 30px;
                font-size: 0.85rem;
            }

            .btn-delete {
                padding: 6px 10px;
                font-size: 0.72rem;
                gap: 4px;
            }

            .panier-item-total {
                margin-left: 0;
            }

            .panier-total-detail {
                font-size: 0.72rem;
            }

            .panier-total-montant {
                font-size: 0.88rem;
            }

            .panier-summary {
                padding: 16px;
                border-radius: 10px;
            }

            .summary-title {
                font-size: 1.05rem;
                margin-bottom: 14px;
                padding-bottom: 10px;
            }

            .summary-row {
                margin-bottom: 10px;
                font-size: 0.85rem;
            }

            .summary-row.total {
                font-size: 1.05rem;
                margin-top: 14px;
                padding-top: 14px;
            }

            .btn-commander,
            .btn-commander-login {
                padding: 12px 14px;
                font-size: 0.92rem;
                margin-top: 14px;
                border-radius: 8px;
            }

            .panier-invite-notice {
                font-size: 0.75rem;
                padding: 8px 10px;
                margin-bottom: 10px;
            }

            .panier-summary .link-continuer {
                margin-top: 10px;
                font-size: 0.82rem;
            }
        }

        @media (max-width: 600px) {
            .panier-item {
                gap: 10px;
                padding: 10px;
            }

            .panier-item-img {
                flex: 0 0 72px;
                width: 72px;
                height: 72px;
            }

            .panier-item-nom {
                font-size: 0.84rem;
            }

            .panier-item-controls {
                display: grid;
                grid-template-columns: 1fr auto;
                grid-template-rows: auto auto;
                align-items: center;
                gap: 8px 6px;
                width: 100%;
            }

            .panier-update-form {
                grid-column: 1;
                grid-row: 1;
                flex-wrap: wrap;
            }

            .panier-item-controls .panier-delete-form {
                grid-column: 2;
                grid-row: 1;
                justify-self: end;
            }

            .panier-item-total {
                grid-column: 1 / -1;
                grid-row: 2;
                text-align: right;
                margin-top: 2px;
            }

            .panier-total-montant {
                font-size: 0.82rem;
            }

            .summary-row {
                font-size: 0.8rem;
            }

            .summary-row.total {
                font-size: 0.98rem;
            }
        }

        @media (max-width: 480px) {
            .panier-container {
                padding: 0 10px;
            }

            .panier-title {
                margin-bottom: 14px;
            }

            .panier-vendeur-header {
                font-size: 0.88rem;
            }

            .panier-item {
                gap: 8px;
                padding: 8px;
                border-radius: 8px;
            }

            .panier-item-img {
                flex: 0 0 64px;
                width: 64px;
                height: 64px;
                border-radius: 6px;
            }

            .panier-item-nom {
                font-size: 0.78rem;
            }

            .panier-item-categorie,
            .panier-item-options {
                font-size: 0.66rem;
            }

            .panier-item-prix {
                font-size: 0.78rem;
                margin-bottom: 8px;
            }

            .panier-prix-label {
                display: block;
                font-size: 0.68rem;
                margin-bottom: 2px;
            }

            .panier-prix-value {
                font-size: 0.82rem;
            }

            .panier-quantite-label {
                font-size: 0.66rem;
            }

            .quantite-controls {
                border-width: 1.5px;
                border-radius: 6px;
            }

            .quantite-btn {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }

            .quantite-input {
                width: 38px;
                height: 28px;
                font-size: 0.8rem;
            }

            .btn-delete {
                padding: 5px 8px;
                font-size: 0.66rem;
                border-radius: 5px;
            }

            .btn-delete i {
                font-size: 0.72rem;
            }

            .panier-total-detail {
                font-size: 0.66rem;
            }

            .panier-total-montant {
                font-size: 0.78rem;
            }

            .panier-summary {
                padding: 12px;
            }

            .summary-title {
                font-size: 0.95rem;
            }

            .summary-row {
                font-size: 0.75rem;
                margin-bottom: 8px;
            }

            .summary-row.total {
                font-size: 0.9rem;
            }

            .btn-commander,
            .btn-commander-login {
                padding: 10px 12px;
                font-size: 0.84rem;
            }

            .panier-invite-notice {
                font-size: 0.7rem;
                line-height: 1.4;
            }

            .panier-empty {
                padding: 32px 12px;
            }

            .panier-empty i {
                font-size: 40px;
            }

            .panier-empty p {
                font-size: 0.88rem;
            }
        }

        @media (max-width: 380px) {
            .panier-item-img {
                flex: 0 0 56px;
                width: 56px;
                height: 56px;
            }

            .panier-item-nom {
                font-size: 0.74rem;
            }

            .panier-item-controls {
                gap: 6px 4px;
            }

            .quantite-btn {
                width: 26px;
                height: 26px;
            }

            .quantite-input {
                width: 34px;
                height: 26px;
                font-size: 0.75rem;
            }

            .btn-delete span,
            .btn-delete {
                font-size: 0;
            }

            .btn-delete i {
                font-size: 0.8rem;
                margin: 0;
            }

            .summary-row.total {
                flex-wrap: wrap;
                gap: 4px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body class="panier-page">

    <?php include('nav_bar.php') ?>

    <div class="panier-container">
        <h1 class="panier-title">Mon Panier</h1>

        <?php if (empty($panier_items)): ?>
            <div class="panier-empty">
                <i class="fas fa-shopping-cart"></i>
                <p>Votre panier est vide</p>
                <a href="/index.php" class="btn-continuer">
                    Continuer mes achats
                </a>
            </div>
        <?php else: ?>
            <div class="panier-content">
                <!-- Liste des produits -->
                <div class="panier-items">
                    <?php foreach ($panier_groups as $g): ?>
                    <div class="panier-vendeur-block">
                        <?php
                        $show_vendor = (count($panier_groups) > 1 || !empty($g['slug']));
                        ?>
                        <?php if ($show_vendor): ?>
                            <div class="panier-vendeur-header">
                                <?php if (!empty($g['slug'])): ?>
                                    <a href="<?php echo htmlspecialchars(boutique_url('index.php', $g['slug'])); ?>"><?php echo htmlspecialchars($g['label']); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($g['label']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php foreach ($g['items'] as $item): ?>
                        <?php
                        // Prix unitaire : variante/surcoûts ou produit de base
                        $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                            ? (float) $item['panier_prix_unitaire']
                            : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
                        $prix_total_item = $prix_unitaire * $item['quantite'];
                        $item_img = !empty($item['panier_variante_image']) ? $item['panier_variante_image'] : ($item['image_principale'] ?? '');
                        $item_nom = !empty($item['panier_variante_nom'])
                            ? $item['nom'] . ' → ' . $item['panier_variante_nom']
                            : ($item['nom'] ?? '');
                        $item_categorie = (string) ($item['categorie_nom'] ?? '');
                        ?>
                        <div class="panier-item" data-item-id="<?php echo $item['panier_id']; ?>">
                            <div class="panier-item-img">
                                <img src="<?php echo htmlspecialchars(upload_image_url((string) $item_img, 'sm'), ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars((string) $item_nom, ENT_QUOTES, 'UTF-8'); ?>" class="panier-item-image"
                                    onerror="this.src='/image/produit1.jpg'">
                            </div>

                            <div class="panier-item-info">
                                <h3 class="panier-item-nom"><?php echo htmlspecialchars((string) $item_nom, ENT_QUOTES, 'UTF-8'); ?></h3>
                                <?php if ($item_categorie !== ''): ?>
                                <p class="panier-item-categorie"><?php echo htmlspecialchars($item_categorie, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($item['panier_couleur']) || !empty($item['panier_poids']) || !empty($item['panier_taille'])): ?>
                                    <p class="panier-item-options">
                                        <?php
                                        $opts = [];
                                        if (!empty(trim($item['panier_couleur'] ?? ''))) {
                                            $hex = trim($item['panier_couleur']);
                                            $opts[] = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)
                                                ? '<span class="opt-swatch" style="background:' . htmlspecialchars($hex) . '"></span> ' . htmlspecialchars($hex)
                                                : 'Couleur: ' . htmlspecialchars($hex);
                                        }
                                        if (!empty(trim($item['panier_poids'] ?? '')))
                                            $opts[] = 'Poids: ' . htmlspecialchars($item['panier_poids']);
                                        if (!empty(trim($item['panier_taille'] ?? '')))
                                            $opts[] = 'Taille: ' . htmlspecialchars($item['panier_taille']);
                                        echo implode(' • ', $opts);
                                        ?>
                                    </p>
                                <?php endif; ?>
                                <p class="panier-item-prix">
                                    <span class="panier-prix-label">Prix unitaire:</span>
                                    <span class="panier-prix-value">
                                        <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> FCFA
                                    </span>
                                    <?php if (empty($item['panier_prix_unitaire']) && !empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix']): ?>
                                        <span class="panier-prix-barré">
                                            <?php echo number_format($item['prix'], 0, ',', ' '); ?> FCFA
                                        </span>
                                        <span class="badge-promo">PROMO</span>
                                    <?php endif; ?>
                                </p>

                                <div class="panier-item-controls">
                                    <form method="POST" action="" class="update-form panier-update-form">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="panier_id" value="<?php echo $item['panier_id']; ?>">

                                        <label class="panier-quantite-label">Quantité:</label>
                                        <div class="quantite-controls">
                                            <button type="button" class="quantite-btn decrease-btn">-</button>
                                            <input type="number" name="quantite" class="quantite-input"
                                                value="<?php echo $item['quantite']; ?>" min="1" required>
                                            <button type="button" class="quantite-btn increase-btn">+</button>
                                        </div>
                                    </form>

                                    <form method="POST" action="" class="panier-delete-form"
                                        onsubmit="return confirm('Êtes-vous sûr de vouloir retirer ce produit du panier ?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="panier_id" value="<?php echo $item['panier_id']; ?>">
                                        <button type="submit" class="btn-delete">
                                            <i class="fas fa-trash"></i> Retirer
                                        </button>
                                    </form>

                                    <div class="panier-item-total">
                                        <div class="panier-total-detail">
                                            <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> ×
                                            <?php echo $item['quantite']; ?>
                                        </div>
                                        <div class="panier-total-montant">
                                            Total: <?php echo number_format($prix_total_item, 0, ',', ' '); ?> FCFA
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Résumé du panier -->
                <div class="panier-summary">
                    <h2 class="summary-title">Résumé du panier</h2>

                    <div class="summary-row">
                        <span>Nombre d'articles:</span>
                        <span id="nombre-articles" class="summary-value">
                            <?php echo $nombre_total_articles; ?>
                            article<?php echo $nombre_total_articles > 1 ? 's' : ''; ?>
                        </span>
                    </div>

                    <div class="summary-row">
                        <span>Nombre de produits:</span>
                        <span class="summary-value-alt">
                            <?php echo count($panier_items); ?> produit<?php echo count($panier_items) > 1 ? 's' : ''; ?>
                        </span>
                    </div>

                    <div class="summary-row summary-subtotal">
                        <span>Sous-total:</span>
                        <span id="subtotal" class="summary-value">
                            <?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA
                        </span>
                    </div>

                    <div class="summary-row">
                        <span>Livraison:</span>
                        <span class="summary-livraison">À calculer</span>
                    </div>

                    <div class="summary-row total">
                        <span>Total général:</span>
                        <span id="total" class="summary-total-value">
                            <?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA
                        </span>
                    </div>

                    <?php if ($panier_utilisateur_connecte): ?>
                    <a href="/commande.php" class="btn-commander">
                        <i class="fas fa-shopping-bag"></i> Passer la commande
                    </a>
                    <?php else: ?>
                    <p class="panier-invite-notice">Connectez-vous pour finaliser votre commande. Votre panier est enregistré sur cet appareil.</p>
                    <a href="/choix-connexion.php?redirect=<?php echo rawurlencode('/commande.php'); ?>" class="btn-commander-login">
                        <i class="fas fa-user"></i> Se connecter pour passer la commande
                    </a>
                    <?php endif; ?>

                    <a href="/index.php" class="link-continuer">
                        Continuer mes achats
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include('footer.php') ?>


    <script>
        function submitPanierQuantiteForm(form) {
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }

        document.querySelectorAll('.increase-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const form = this.closest('.panier-update-form');
                const input = this.parentElement.querySelector('.quantite-input');
                let value = parseInt(input.value, 10) || 1;
                value++;
                input.value = value;
                submitPanierQuantiteForm(form);
            });
        });

        document.querySelectorAll('.decrease-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const form = this.closest('.panier-update-form');
                const input = this.parentElement.querySelector('.quantite-input');
                let value = parseInt(input.value, 10) || 1;
                if (value > 1) {
                    value--;
                    input.value = value;
                    submitPanierQuantiteForm(form);
                }
            });
        });

        document.querySelectorAll('.panier-update-form .quantite-input').forEach(input => {
            input.addEventListener('change', function () {
                let value = parseInt(this.value, 10) || 1;
                if (value < 1) value = 1;
                this.value = value;
                submitPanierQuantiteForm(this.closest('.panier-update-form'));
            });
        });
    </script>

</body>

</html>