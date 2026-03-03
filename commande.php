<?php
/**
 * Page de commande
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: /user/connexion.php?redirect=commande');
    exit;
}

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/models/model_users.php';
require_once __DIR__ . '/models/model_zones_livraison.php';
require_once __DIR__ . '/controllers/controller_commandes.php';

$zones_livraison = get_all_zones_livraison('actif');

// Traitement du formulaire
$message = '';
$message_type = '';
$commande_id = null;
$numero_commande = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_commande') {
    $result = process_create_commande();

    if ($result['success']) {
        // Envoi de la réponse immédiatement pour ne pas bloquer l'utilisateur
        ignore_user_abort(true);
        header('Location: /user/mes-commandes.php?success=1&numero=' . urlencode($result['numero_commande']));
        echo ' ';
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
            if (ob_get_level()) {
                ob_end_flush();
            }
        }

        // Envoi notification + email en arrière-plan (après que le client a reçu la redirection)
        if (!empty($result['email_data']) && file_exists(__DIR__ . '/services/send_new_commande_to_admin.php')) {
            require_once __DIR__ . '/services/send_new_commande_to_admin.php';
            $d = $result['email_data'];
            send_new_commande_to_admin(
                $d['numero_commande'],
                $d['montant_total'],
                $d['nombre_articles'],
                $d['telephone_livraison'] ?? '',
                $d['adresse_livraison'] ?? '',
                $d['produits'] ?? []
            );
        }
        exit;
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// Récupérer les informations de l'utilisateur
$user = get_user_by_id($_SESSION['user_id']);

// Récupérer les produits du panier
$panier_items = get_panier_by_user($_SESSION['user_id']);

// Vérifier que le panier n'est pas vide
if (empty($panier_items)) {
    header('Location: /panier.php');
    exit;
}

// S'il n'y a aucune zone de livraison, le formulaire affichera un message et sera désactivé

// Calculer le total
$panier_total = get_panier_total($_SESSION['user_id']);
$nombre_total_articles = 0;
foreach ($panier_items as $item) {
    $nombre_total_articles += $item['quantite'];
}

// Inclusion de la barre de navigation
include 'nav_bar.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Passer la commande - Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <style>
        .commande-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .commande-wrapper {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            margin-top: 30px;
        }

        @media (max-width: 968px) {
            .commande-wrapper {
                grid-template-columns: 1fr;
            }
        }

        .commande-form-section {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--glass-shadow);
        }

        .commande-summary-section {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--glass-shadow);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(229, 72, 138, 0.2);
            font-family: var(--font-titres);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(229, 72, 138, 0.2);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.8);
            cursor: pointer;
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 3px rgba(229, 72, 138, 0.15);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(229, 72, 138, 0.2);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 3px rgba(229, 72, 138, 0.15);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group small {
            display: block;
            color: #737373;
            font-size: 12px;
            margin-top: 5px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(229, 72, 138, 0.15);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-item-label {
            color: var(--texte-fonce);
            font-size: 14px;
        }

        .summary-item-value {
            color: var(--titres);
            font-weight: 600;
            font-size: 14px;
        }

        .summary-total {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--couleur-dominante);
        }

        .summary-total .summary-item-label {
            font-size: 18px;
            font-weight: 700;
            color: var(--titres);
        }

        .summary-total .summary-item-value {
            font-size: 20px;
            color: var(--accent-promo);
        }

        .btn-submit-commande {
            width: 100%;
            padding: 15px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit-commande:hover {
            background: rgba(229, 72, 138, 0.9);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            color: var(--texte-clair);
        }

        .btn-submit-commande:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.error {
            background: rgba(229, 72, 138, 0.1);
            color: var(--titres);
            border: 1px solid rgba(229, 72, 138, 0.3);
        }

        .panier-item-summary {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(229, 72, 138, 0.15);
        }

        .panier-item-summary:last-child {
            border-bottom: none;
        }

        .panier-item-summary img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .panier-item-summary-info {
            flex: 1;
        }

        .panier-item-summary-info h4 {
            font-size: 14px;
            color: var(--titres);
            margin-bottom: 5px;
            font-weight: 600;
        }

        .panier-item-summary-info p {
            font-size: 12px;
            color: #737373;
            margin: 0;
        }

        .panier-item-summary-price {
            font-size: 14px;
            font-weight: 600;
            color: var(--titres);
        }

        .commande-page-title {
            font-size: 28px;
            color: var(--titres);
            margin-bottom: 10px;
            font-family: var(--font-titres);
        }

        .commande-page-subtitle {
            color: #737373;
            margin-bottom: 30px;
        }

        .summary-livraison {
            color: #737373;
        }

        .commande-link-retour {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--couleur-dominante);
            text-decoration: none;
            font-weight: 500;
        }

        .commande-link-retour:hover {
            text-decoration: underline;
        }

        .choix-produits-section {
            margin-top: 25px;
        }

        .choix-produits-section>label {
            margin-bottom: 12px;
            display: block;
        }

        .choix-produits-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .choix-produit-item {
            padding: 14px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 8px;
            border: 1px solid rgba(229, 72, 138, 0.15);
        }

        .choix-produit-nom {
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 10px;
            font-size: 14px;
        }

        .choix-produit-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .choix-option {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 100px;
        }

        .choix-option label {
            font-size: 12px;
            color: #737373;
        }

        .choix-option select {
            padding: 8px 10px;
            border: 2px solid rgba(229, 72, 138, 0.2);
            border-radius: 6px;
            font-size: 13px;
            background: #fff;
        }

        .choix-aucune {
            font-size: 13px;
            color: #737373;
            font-style: italic;
        }

        .choix-option-couleurs .choix-couleurs-swatches {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: stretch;
        }

        .choix-couleur-swatch {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            padding: 10px 12px;
            background: #fff;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
        }

        .choix-couleur-swatch:hover {
            border-color: rgba(229, 72, 138, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .choix-couleur-swatch:has(input:checked) {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 3px rgba(229, 72, 138, 0.25);
            background: #fff;
        }

        .choix-couleur-swatch input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* Pastille de couleur pleine et bien visible */
        .choix-couleur-swatch.is-hex .swatch {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.15);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            display: block;
            margin-bottom: 6px;
        }

        .choix-couleur-swatch:not(.is-hex) .swatch-text {
            font-size: 14px;
            font-weight: 600;
            color: var(--texte-fonce);
            text-align: center;
            padding: 8px 4px;
        }

        .choix-couleur-swatch.choix-couleur-none {
            min-width: 90px;
            border-style: dashed;
        }

        .choix-couleur-swatch.choix-couleur-none .swatch-text {
            font-size: 13px;
            color: #737373;
            font-weight: 500;
        }

        /* Contraste pour couleurs claires (blanc, jaune, etc.) */
        .choix-couleur-swatch.is-hex .swatch {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2), inset 0 0 0 1px rgba(0, 0, 0, 0.08);
        }

        .choix-produit-item {
            padding: 18px;
        }

        .choix-produit-nom {
            font-size: 15px;
            margin-bottom: 14px;
        }

        .choix-option-couleurs {
            margin-bottom: 16px;
        }

        .choix-option-couleurs > label {
            font-size: 13px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 12px;
            display: block;
        }

        /* Styles pour éviter que le footer s'incruste */
        .commande-container {
            margin-bottom: 100px;
            min-height: calc(100vh - 200px);
        }

        /* Footer - hérite du style global a_style.css */
    </style>
</head>

<body>

    <div class="commande-container">
        <h1 class="commande-page-title">
            <i class="fas fa-shopping-bag"></i> Passer la commande
        </h1>
        <p class="commande-page-subtitle">Veuillez remplir les informations de contact</p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="commande-wrapper">
            <!-- Formulaire de commande -->
            <div class="commande-form-section">
                <h2 class="section-title">
                    <i class="fas fa-phone"></i> Informations de livraison
                </h2>

                <form method="POST" action="" id="form-commande">
                    <input type="hidden" name="action" value="create_commande">

                    <?php if (empty($zones_livraison)): ?>
                        <div class="message error">
                            <i class="fas fa-exclamation-triangle"></i> Aucune zone de livraison n'est configurée. Veuillez
                            contacter l'administrateur.
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="zone_livraison_id">
                                <i class="fas fa-map-marker-alt"></i> Zone de livraison *
                            </label>
                            <select id="zone_livraison_id" name="zone_livraison_id" required>
                                <option value="">Sélectionnez votre zone de livraison</option>
                                <?php if (!empty($zones_livraison)): ?>
                                    <?php foreach ($zones_livraison as $zone): ?>
                                        <option value="<?php echo $zone['id']; ?>"
                                            data-prix="<?php echo (float) $zone['prix_livraison']; ?>">
                                            <?php echo htmlspecialchars($zone['ville'] . ' - ' . $zone['quartier']); ?>
                                            (<?php echo number_format($zone['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small>Choisissez la zone correspondant à votre adresse de livraison</small>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="telephone_livraison">
                            <i class="fas fa-phone"></i> Téléphone de livraison *
                        </label>
                        <input type="tel" id="telephone_livraison" name="telephone_livraison" required
                            placeholder="+241 XX XX XX XX"
                            value="<?php echo isset($_POST['telephone_livraison']) ? htmlspecialchars($_POST['telephone_livraison']) : htmlspecialchars($user['telephone'] ?? ''); ?>">
                        <small>Numéro de téléphone pour la livraison</small>
                    </div>

                    <div class="form-group">
                        <label for="notes">
                            <i class="fas fa-sticky-note"></i> Notes (optionnel)
                        </label>
                        <textarea id="notes" name="notes"
                            placeholder="Instructions spéciales pour la livraison (ex: code d'accès, étage, etc.)"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        <small>Ajoutez des instructions spéciales si nécessaire</small>
                    </div>

                    <div class="form-group choix-produits-section">
                        <label><i class="fas fa-palette"></i> Couleur, poids et taille (par produit)</label>
                        <div class="choix-produits-list">
                            <?php foreach ($panier_items as $item): ?>
                                <?php
                                $couleurs_options = [];
                                $poids_options = [];
                                $taille_options = [];
                                if (!empty($item['couleurs'])) {
                                    $cr = trim($item['couleurs']);
                                    $dec = json_decode($cr, true);
                                    if (is_array($dec)) {
                                        $couleurs_options = array_filter($dec, function ($x) {
                                            return is_string($x) && preg_match('/^#[0-9A-Fa-f]{6}$/', $x); });
                                    } else {
                                        $couleurs_options = array_map('trim', array_filter(explode(',', $cr)));
                                    }
                                }
                                if (!empty($item['poids'])) {
                                    $poids_options = array_map('trim', array_filter(explode(',', $item['poids'])));
                                }
                                if (!empty($item['taille'])) {
                                    $taille_options = array_map('trim', array_filter(explode(',', $item['taille'])));
                                }
                                $has_options = !empty($couleurs_options) || !empty($poids_options) || !empty($taille_options);
                                ?>
                                <div class="choix-produit-item" data-panier-id="<?php echo (int) $item['panier_id']; ?>">
                                    <div class="choix-produit-nom"><?php echo htmlspecialchars($item['nom']); ?></div>
                                    <div class="choix-produit-options">
                                        <?php
                                        $pre_couleur = isset($item['panier_couleur']) ? trim($item['panier_couleur']) : '';
                                        $pre_poids = isset($item['panier_poids']) ? trim($item['panier_poids']) : '';
                                        $pre_taille = isset($item['panier_taille']) ? trim($item['panier_taille']) : '';
                                        ?>
                                        <?php if (!empty($couleurs_options)): ?>
                                            <div class="choix-option choix-option-couleurs">
                                                <label>Couleur</label>
                                                <div class="choix-couleurs-swatches">
                                                    <label class="choix-couleur-swatch choix-couleur-none">
                                                        <input type="radio" name="choix[<?php echo (int) $item['panier_id']; ?>][couleur]" value=""
                                                            <?php echo $pre_couleur === '' ? ' checked' : ''; ?>>
                                                        <span class="swatch-text">— Choisir —</span>
                                                    </label>
                                                    <?php foreach ($couleurs_options as $opt): ?>
                                                        <?php $is_hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $opt); ?>
                                                        <label class="choix-couleur-swatch <?php echo $is_hex ? 'is-hex' : ''; ?>">
                                                            <input type="radio" name="choix[<?php echo (int) $item['panier_id']; ?>][couleur]" value="<?php echo htmlspecialchars($opt); ?>"
                                                                <?php echo ($pre_couleur !== '' && $pre_couleur === $opt) ? ' checked' : ''; ?>>
                                                            <?php if ($is_hex): ?>
                                                                <span class="swatch" style="background-color:<?php echo htmlspecialchars($opt); ?>;" title="<?php echo htmlspecialchars($opt); ?>"></span>
                                                            <?php else: ?>
                                                                <span class="swatch-text"><?php echo htmlspecialchars($opt); ?></span>
                                                            <?php endif; ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($poids_options)): ?>
                                            <div class="choix-option">
                                                <label>Poids</label>
                                                <select name="choix[<?php echo (int) $item['panier_id']; ?>][poids]">
                                                    <option value="">— Choisir —</option>
                                                    <?php foreach ($poids_options as $opt): ?>
                                                        <option value="<?php echo htmlspecialchars($opt); ?>"
                                                            <?php echo ($pre_poids !== '' && $pre_poids === $opt) ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($opt); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($taille_options)): ?>
                                            <div class="choix-option">
                                                <label>Taille</label>
                                                <select name="choix[<?php echo (int) $item['panier_id']; ?>][taille]">
                                                    <option value="">— Choisir —</option>
                                                    <?php foreach ($taille_options as $opt): ?>
                                                        <option value="<?php echo htmlspecialchars($opt); ?>"
                                                            <?php echo ($pre_taille !== '' && $pre_taille === $opt) ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($opt); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$has_options): ?>
                                            <span class="choix-aucune">Aucune option disponible</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit-commande" <?php echo empty($zones_livraison) ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-circle"></i> Confirmer la commande
                    </button>
                </form>
            </div>

            <!-- Résumé de la commande -->
            <div class="commande-summary-section">
                <h2 class="section-title">
                    <i class="fas fa-shopping-cart"></i> Résumé
                </h2>

                <div style="margin-bottom: 20px;">
                    <?php foreach ($panier_items as $item): ?>
                        <?php
                        $prix_unitaire = !empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix']
                            ? $item['prix_promotion']
                            : $item['prix'];
                        $prix_total_item = $prix_unitaire * $item['quantite'];
                        ?>
                        <div class="panier-item-summary">
                            <img src="/upload/<?php echo htmlspecialchars($item['image_principale']); ?>"
                                alt="<?php echo htmlspecialchars($item['nom']); ?>"
                                onerror="this.src='/image/produit1.jpg'">
                            <div class="panier-item-summary-info">
                                <h4><?php echo htmlspecialchars($item['nom']); ?></h4>
                                <p>Quantité: <?php echo $item['quantite']; ?> ×
                                    <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> FCFA
                                </p>
                            </div>
                            <div class="panier-item-summary-price">
                                <?php echo number_format($prix_total_item, 0, ',', ' '); ?> FCFA
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-item">
                    <span class="summary-item-label">Nombre d'articles</span>
                    <span class="summary-item-value"><?php echo $nombre_total_articles; ?></span>
                </div>

                <div class="summary-item">
                    <span class="summary-item-label">Nombre de produits</span>
                    <span class="summary-item-value"><?php echo count($panier_items); ?></span>
                </div>

                <div class="summary-item">
                    <span class="summary-item-label">Sous-total</span>
                    <span class="summary-item-value"><?php echo number_format($panier_total, 0, ',', ' '); ?>
                        FCFA</span>
                </div>

                <div class="summary-item">
                    <span class="summary-item-label">Livraison</span>
                    <span class="summary-item-value" id="summary-livraison">0 FCFA</span>
                </div>

                <div class="summary-total">
                    <div class="summary-item">
                        <span class="summary-item-label">Total général</span>
                        <span class="summary-item-value"
                            id="summary-total"><?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA</span>
                    </div>
                </div>

                <a href="/panier.php" class="commande-link-retour">
                    <i class="fas fa-arrow-left"></i> Retour au panier
                </a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        (function () {
            var panierTotal = <?php echo $panier_total; ?>;
            var selectZone = document.getElementById('zone_livraison_id');
            var spanLivraison = document.getElementById('summary-livraison');
            var spanTotal = document.getElementById('summary-total');
            function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
            function updateTotaux() {
                var opt = selectZone.options[selectZone.selectedIndex];
                var frais = opt && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
                var total = panierTotal + frais;
                spanLivraison.textContent = formatNumber(Math.round(frais)) + ' FCFA';
                spanTotal.textContent = formatNumber(Math.round(total)) + ' FCFA';
            }
            if (selectZone) {
                selectZone.addEventListener('change', updateTotaux);
                updateTotaux();
            }
        })();
    </script>
</body>

</html>