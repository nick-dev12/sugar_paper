<?php
/**
 * Page de commande
 * Programmation procédurale uniquement
 */

ob_start();

require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/boutique_slug_redirect.php';
require_once __DIR__ . '/models/model_admin.php';

$commande_boutique_slug = trim((string) ($_GET['boutique'] ?? ''));
$commande_boutique_admin = null;
if ($commande_boutique_slug !== '') {
    $commande_boutique_admin = boutique_resolve_vendeur_web($commande_boutique_slug);
    if (!$commande_boutique_admin) {
        header('Location: /panier.php');
        exit;
    }
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/auth_redirect.php';
    auth_redirect_to_site_home();
}

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/models/model_users.php';
require_once __DIR__ . '/controllers/controller_commandes.php';

// Traitement du formulaire
$message = '';
$message_type = '';
$commande_id = null;
$numero_commande = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_commande') {
    $result = process_create_commande();

    if ($result['success']) {
        $nums = !empty($result['numeros_commandes']) ? $result['numeros_commandes'] : [$result['numero_commande']];
        $redirect_url = '/user/mes-commandes.php?success=1&numeros=' . urlencode(implode(',', $nums));

        $notification_payload = [
            'notifications' => $result['notifications'] ?? [],
            'email_data' => $result['email_data'] ?? null,
            'numeros_commandes' => $nums,
            'numero_commande' => $result['numero_commande'] ?? '',
            'user_id' => (int) $_SESSION['user_id'],
            'user_email' => trim($_SESSION['user_email'] ?? ''),
        ];

        require_once __DIR__ . '/includes/commande_post_create_deferred.php';
        require_once __DIR__ . '/includes/produit_post_create_deferred.php';
        admin_redirect_then(static function () use ($notification_payload) {
            commande_run_deferred_notifications($notification_payload);
        }, $redirect_url);
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// Récupérer les informations de l'utilisateur
$user = get_user_by_id($_SESSION['user_id']);

// Position déjà connue : session (fraîche) en priorité, sinon dernière position BDD
require_once __DIR__ . '/includes/geo_location_service.php';
$geo_saved = geo_session_get_location();
if ($geo_saved === null && $user) {
    $u_lat = geo_parse_coord($user['last_latitude'] ?? null);
    $u_lng = geo_parse_coord($user['last_longitude'] ?? null);
    if (geo_coords_valid($u_lat, $u_lng)) {
        $geo_saved = [
            'lat' => $u_lat,
            'lng' => $u_lng,
            'precision' => geo_parse_precision($user['last_geo_precision'] ?? null),
        ];
    }
}

// Pays marketplace pour filtrer la recherche d'adresse (Sénégal, CI, Gabon…)
$geo_search_country_iso = geo_search_country_nominatim($commande_boutique_admin);
$geo_search_country_label = geo_search_country_label($commande_boutique_admin);

// Récupérer les produits du panier (éventuellement limités à une boutique)
$panier_items = get_panier_by_user($_SESSION['user_id']);
if ($commande_boutique_admin) {
    $panier_items = filter_panier_items_by_vendeur($panier_items, (int) $commande_boutique_admin['id']);
}

// Vérifier que le panier n'est pas vide
if (empty($panier_items)) {
    if ($commande_boutique_admin && $commande_boutique_slug !== '') {
        header('Location: ' . boutique_url('panier.php', $commande_boutique_slug));
    } else {
        header('Location: /panier.php');
    }
    exit;
}

// Calculer le total (sous-ensemble affiché)
$panier_total = panier_items_sous_total($panier_items);
$nombre_total_articles = panier_items_count_quantites($panier_items);

require_once __DIR__ . '/includes/commande_mode_helpers.php';
require_once __DIR__ . '/includes/image_optimizer.php';
$commande_pickup_boutiques = commande_pickup_boutiques_from_panier($panier_items);
$commande_mode_selected = commande_mode_livraison_normalize($_POST['mode_livraison'] ?? 'livraison');

// Contexte badge panier (commande limitée à une boutique)
if ($commande_boutique_admin) {
    $GLOBALS['nav_panier_count_for_vendeur_id'] = (int) $commande_boutique_admin['id'];
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
    <title>Passer la commande - COLObanes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <?php require_once __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
    <style>
        .commande-container {
            max-width: 1200px;
            margin: clamp(20px, 4vw, 40px) auto clamp(60px, 10vw, 100px);
            padding: 0 clamp(12px, 3vw, 20px);
            min-height: calc(100vh - 200px);
        }

        .commande-wrapper {
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(16px, 3vw, 30px);
            margin-top: clamp(16px, 3vw, 30px);
            max-width: 827px;
            margin-left: auto;
            margin-right: auto;
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
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-input);
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
            border: 2px solid var(--border-input);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
            background: var(--blanc);
            cursor: pointer;
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--border-input-focus);
            box-shadow: 0 0 0 3px var(--focus-ring);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-input);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
            background: var(--blanc);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--border-input-focus);
            box-shadow: 0 0 0 3px var(--focus-ring);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group .iti {
            width: 100%;
            display: block;
        }

        .form-group .iti input[type="tel"] {
            width: 100%;
            padding-left: 52px;
        }

        .form-group small {
            display: block;
            color: var(--gris-moyen);
            font-size: 12px;
            margin-top: 5px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-input);
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
            border-top: 2px solid var(--couleur-dominante);
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
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            color: var(--texte-clair);
        }

        .btn-submit-commande:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        .cmd-submit-overlay {
            position: fixed;
            inset: 0;
            z-index: 12050;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(13, 13, 13, 0.45);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        .cmd-submit-overlay.is-visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .cmd-submit-overlay__card {
            width: min(100%, 380px);
            padding: clamp(22px, 5vw, 30px) clamp(18px, 4vw, 26px);
            border-radius: 20px;
            background: var(--blanc, #fff);
            box-shadow: var(--ombre-gourmande, 0 8px 30px rgba(53, 100, 166, 0.15));
            text-align: center;
        }

        .cmd-submit-overlay__icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: var(--bleu-pale, rgba(53, 100, 166, 0.12));
            color: var(--couleur-dominante, #3564a6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .cmd-submit-overlay__icon i {
            animation: cmdSubmitSpin 1.1s linear infinite;
        }

        @keyframes cmdSubmitSpin {
            to { transform: rotate(360deg); }
        }

        .cmd-submit-overlay__title {
            margin: 0 0 6px;
            font-size: clamp(1rem, 3.2vw, 1.12rem);
            font-weight: 700;
            color: var(--titres, #0d0d0d);
        }

        .cmd-submit-overlay__subtitle {
            margin: 0 0 18px;
            font-size: clamp(0.78rem, 2.5vw, 0.88rem);
            color: var(--gris-moyen, #737373);
        }

        .cmd-submit-overlay__track {
            height: 10px;
            border-radius: 999px;
            background: rgba(53, 100, 166, 0.12);
            overflow: hidden;
            margin-bottom: 10px;
        }

        .cmd-submit-overlay__bar {
            height: 100%;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--couleur-dominante, #3564a6), var(--bleu-clair, #4a7ab8));
            transition: width 0.35s ease;
        }

        .cmd-submit-overlay__pct {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--couleur-dominante, #3564a6);
        }

        body.cmd-submit-busy {
            overflow: hidden;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.error {
            background: var(--error-bg);
            color: var(--titres);
            border: 1px solid var(--error-border);
        }

        .panier-item-summary {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-input);
        }

        .panier-item-summary:last-child {
            border-bottom: none;
        }

        .panier-item-img {
            flex-shrink: 0;
        }

        .panier-item-summary img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            display: block;
        }

        .panier-item-summary-content {
            flex: 1;
            min-width: 0;
        }

        .panier-item-summary-title {
            font-size: 14px;
            color: var(--titres);
            margin: 0 0 4px;
            font-weight: 600;
            line-height: 1.35;
        }

        .panier-item-summary-opts {
            font-size: 11px;
            color: var(--gris-moyen);
            margin: 0 0 6px;
        }

        .panier-item-summary-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .panier-item-qty {
            font-size: 12px;
            color: var(--gris-moyen);
            margin: 0;
        }

        .panier-item-summary-price {
            font-size: 14px;
            font-weight: 700;
            color: var(--orange);
            white-space: nowrap;
        }

        .commande-page-title {
            font-size: clamp(1.25rem, 4vw, 1.75rem);
            color: var(--titres);
            margin-bottom: 8px;
            font-family: var(--font-titres);
        }

        .commande-page-subtitle {
            color: var(--gris-moyen);
            margin-bottom: clamp(16px, 3vw, 30px);
            font-size: clamp(0.82rem, 2.5vw, 1rem);
        }

        .summary-livraison {
            color: var(--gris-moyen);
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
            color: var(--orange);
            text-decoration: underline;
        }

        /* Bloc consentement géolocalisation */
        .geo-consent-box {
            background: var(--bleu-pale);
            border: 1px solid var(--border-input);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .btn-geo-capture {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-geo-capture:hover {
            background: var(--couleur-dominante-hover);
        }

        .geo-status {
            margin-top: 12px;
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 6px;
        }

        .geo-status[data-geo-state="pending"] {
            background: var(--blanc-neige);
            color: var(--gris-fonce);
        }

        .geo-status[data-geo-state="ok"] {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--texte-fonce);
        }

        .geo-status[data-geo-state="error"] {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--texte-fonce);
        }

        .geo-map {
            margin-top: 14px;
            height: 280px;
            border-radius: 8px;
            border: 1px solid var(--border-input);
            overflow: hidden;
            z-index: 0;
        }

        .geo-search-wrap {
            position: relative;
            margin-bottom: 14px;
        }

        .geo-search-label {
            display: block;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .geo-search-input-wrap {
            position: relative;
        }

        .geo-search-input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gris-moyen);
            pointer-events: none;
        }

        .geo-search-input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 2px solid var(--border-input);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: var(--blanc);
        }

        .geo-search-input:focus {
            outline: none;
            border-color: var(--border-input-focus);
            box-shadow: 0 0 0 3px var(--focus-ring);
        }

        .geo-search-suggestions {
            display: none;
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 4px);
            z-index: 500;
            background: var(--blanc);
            border: 1px solid var(--border-input);
            border-radius: 8px;
            box-shadow: var(--ombre-douce);
            max-height: 240px;
            overflow-y: auto;
        }

        .geo-search-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            width: 100%;
            padding: 11px 14px;
            border: none;
            border-bottom: 1px solid rgba(53, 100, 166, 0.08);
            background: transparent;
            text-align: left;
            cursor: pointer;
            font-size: 13px;
            color: var(--texte-fonce);
            font-family: inherit;
            line-height: 1.35;
        }

        .geo-search-item:last-child {
            border-bottom: none;
        }

        .geo-search-item:hover {
            background: var(--bleu-pale);
        }

        .geo-search-item i {
            color: var(--couleur-dominante);
            margin-top: 2px;
            flex-shrink: 0;
        }

        .geo-search-empty {
            padding: 12px 14px;
            font-size: 13px;
            color: var(--gris-moyen);
        }

        .cmd-mode-chooser {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 20px;
        }

        .cmd-mode-btn {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 12px;
            border: 2px solid var(--border-input);
            border-radius: 12px;
            background: var(--blanc);
            color: var(--texte-fonce);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            text-align: center;
            min-height: 0;
        }

        .cmd-mode-btn i {
            font-size: 1.5rem;
            color: var(--couleur-dominante);
            flex-shrink: 0;
        }

        .cmd-mode-btn span {
            line-height: 1.2;
        }

        .cmd-mode-btn.is-active {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale);
            box-shadow: var(--ombre-douce);
        }

        .cmd-mode-panel {
            display: none;
        }

        .cmd-mode-panel.is-visible {
            display: block;
        }

        .cmd-pickup-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 8px;
        }

        .cmd-pickup-card {
            border: 1px solid var(--border-input);
            border-radius: 10px;
            padding: 14px 16px;
            background: var(--blanc-neige);
        }

        .cmd-pickup-card__name {
            font-weight: 800;
            color: var(--titres);
            margin: 0 0 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .cmd-pickup-card__name i {
            color: var(--couleur-dominante);
        }

        .cmd-pickup-hint {
            font-size: 0.82rem;
            color: var(--gris-moyen);
            margin: 0 0 16px;
            line-height: 1.45;
        }

        .cmd-pickup-card__maps {
            margin: 10px 0 0;
            font-size: 0.82rem;
        }

        .cmd-pickup-card__maps a {
            color: var(--couleur-dominante);
            font-weight: 700;
            text-decoration: none;
        }

        .commande-summary-items {
            margin-bottom: 20px;
        }

        .cmd-pickup-card__maps a:hover {
            text-decoration: underline;
        }

        /* Responsive — adaptation progressive par taille d'écran */
        @media (max-width: 968px) {

            .commande-summary-section,
            .commande-form-section {
                padding: 20px;
                border-radius: 10px;
            }

            .section-title {
                font-size: 1.15rem;
                margin-bottom: 18px;
                padding-bottom: 12px;
            }
        }

        @media (max-width: 768px) {

            .commande-summary-section,
            .commande-form-section {
                padding: 16px;
            }

            .section-title {
                font-size: 1.05rem;
                margin-bottom: 14px;
                padding-bottom: 10px;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-group label {
                font-size: 0.82rem;
                margin-bottom: 6px;
            }

            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 10px 12px;
                font-size: 0.85rem;
                border-radius: 8px;
            }

            .form-group textarea {
                min-height: 80px;
            }

            .form-group small {
                font-size: 0.72rem;
            }

            .summary-item {
                padding: 8px 0;
            }

            .summary-item-label,
            .summary-item-value {
                font-size: 0.82rem;
            }

            .summary-total {
                margin-top: 12px;
                padding-top: 12px;
            }

            .summary-total .summary-item-label {
                font-size: 0.92rem;
            }

            .summary-total .summary-item-value {
                font-size: 1.05rem;
            }

            .panier-item-summary {
                display: grid;
                grid-template-columns: 56px 1fr;
                column-gap: 10px;
                row-gap: 4px;
                padding: 8px 0;
            }

            .panier-item-summary img {
                width: 56px;
                height: 56px;
                border-radius: 6px;
            }

            .panier-item-img {
                grid-column: 1;
                grid-row: 1;
            }

            .panier-item-summary-content {
                display: contents;
            }

            .panier-item-summary-title {
                grid-column: 2;
                grid-row: 1;
                align-self: center;
                margin: 0;
                font-size: 0.82rem;
            }

            .panier-item-summary-opts {
                grid-column: 1 / -1;
                margin: 0;
                font-size: 0.68rem;
            }

            .panier-item-summary-meta {
                grid-column: 1 / -1;
                gap: 8px;
            }

            .panier-item-qty {
                font-size: 0.72rem;
            }

            .panier-item-summary-price {
                font-size: 0.82rem;
            }

            .btn-submit-commande {
                padding: 12px 14px;
                font-size: 0.92rem;
                margin-top: 14px;
                gap: 8px;
            }

            .commande-link-retour {
                margin-top: 14px;
                font-size: 0.85rem;
            }

            .message {
                padding: 12px 14px;
                font-size: 0.88rem;
                margin-bottom: 14px;
            }

            .cmd-mode-btn {
                padding: 8px 10px;
                font-size: 0.88rem;
                gap: 8px;
                border-radius: 10px;
            }

            .cmd-mode-btn i {
                font-size: 1.2rem;
            }

            .cmd-pickup-card {
                padding: 10px 12px;
                border-radius: 8px;
            }

            .cmd-pickup-card__name {
                font-size: 0.85rem;
                gap: 6px;
                margin-bottom: 4px;
            }

            .cmd-pickup-card__maps {
                font-size: 0.75rem;
                margin-top: 6px;
            }

            .cmd-pickup-hint {
                font-size: 0.75rem;
                margin-bottom: 12px;
            }

            .geo-consent-box {
                padding: 12px;
                margin-bottom: 14px;
            }

            .geo-search-label {
                font-size: 0.82rem;
                margin-bottom: 6px;
            }

            .geo-search-input {
                padding: 10px 12px 10px 36px;
                font-size: 0.85rem;
            }

            .geo-search-input-wrap i {
                left: 12px;
                font-size: 0.85rem;
            }

            .geo-search-item {
                padding: 9px 12px;
                font-size: 0.78rem;
                gap: 8px;
            }

            .geo-map {
                height: 200px;
                margin-top: 10px;
            }

            .btn-geo-capture {
                padding: 8px 12px;
                font-size: 0.82rem;
                gap: 6px;
                width: 100%;
                justify-content: center;
            }

            .geo-status {
                font-size: 0.78rem;
                padding: 8px 10px;
                margin-top: 10px;
            }
        }

        @media (max-width: 600px) {
            .commande-summary-items {
                margin-bottom: 12px;
            }

            .cmd-mode-chooser {
                gap: 6px;
                margin-bottom: 14px;
            }

            .cmd-mode-btn {
                flex-direction: column;
                gap: 4px;
                padding: 8px 6px;
                font-size: 0.78rem;
            }

            .cmd-mode-btn i {
                font-size: 1.1rem;
            }

            .panier-item-summary-meta {
                flex-wrap: wrap;
            }

            .panier-item-summary-price {
                margin-left: auto;
            }
        }

        @media (max-width: 480px) {

            .commande-summary-section,
            .commande-form-section {
                padding: 12px;
                border-radius: 8px;
            }

            .section-title {
                font-size: 0.95rem;
                margin-bottom: 12px;
            }

            .form-group label {
                font-size: 0.78rem;
            }

            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 9px 10px;
                font-size: 0.82rem;
                border-width: 1.5px;
            }

            .form-group textarea {
                min-height: 72px;
            }

            .summary-item-label,
            .summary-item-value {
                font-size: 0.75rem;
            }

            .summary-total .summary-item-label {
                font-size: 0.85rem;
            }

            .summary-total .summary-item-value {
                font-size: 0.95rem;
            }

            .panier-item-summary {
                grid-template-columns: 48px 1fr;
                column-gap: 8px;
                padding: 6px 0;
            }

            .panier-item-summary img {
                width: 48px;
                height: 48px;
            }

            .panier-item-summary-title {
                font-size: 0.76rem;
                line-height: 1.25;
            }

            .panier-item-summary-opts {
                font-size: 0.64rem;
            }

            .panier-item-qty {
                font-size: 0.66rem;
            }

            .panier-item-summary-price {
                font-size: 0.76rem;
            }

            .btn-submit-commande {
                padding: 10px 12px;
                font-size: 0.85rem;
            }

            .commande-link-retour {
                font-size: 0.8rem;
            }

            .cmd-mode-btn {
                font-size: 0.72rem;
                border-radius: 8px;
            }

            .cmd-mode-btn i {
                font-size: 1rem;
            }

            .cmd-pickup-card {
                padding: 8px 10px;
            }

            .cmd-pickup-card__name {
                font-size: 0.8rem;
            }

            .geo-consent-box {
                padding: 10px;
            }

            .geo-search-label {
                font-size: 0.78rem;
            }

            .geo-search-input {
                padding: 8px 10px 8px 34px;
                font-size: 0.8rem;
            }

            .geo-map {
                height: 170px;
            }

            .btn-geo-capture {
                font-size: 0.78rem;
                padding: 8px 10px;
            }

            .geo-status {
                font-size: 0.72rem;
            }
        }

        @media (max-width: 380px) {
            .commande-page-title {
                font-size: 1.1rem;
            }

            .section-title {
                font-size: 0.88rem;
            }

            .cmd-mode-btn span {
                font-size: 0.68rem;
            }

            .summary-total .summary-item {
                flex-wrap: wrap;
                gap: 4px;
            }

            .panier-item-summary {
                grid-template-columns: 44px 1fr;
            }

            .panier-item-summary img {
                width: 44px;
                height: 44px;
            }

            .panier-item-summary-title {
                font-size: 0.72rem;
            }

            .btn-submit-commande {
                font-size: 0.8rem;
            }
        }

        /* Footer - hérite du style global a_style.css */
    </style>
</head>

<body>

    <div class="commande-container">
        <h1 class="commande-page-title">
            <i class="fas fa-shopping-bag"></i> Passer la commande
        </h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="commande-wrapper">
            <!-- Résumé en premier (au-dessus du formulaire) -->
            <div class="commande-summary-section">
                <h2 class="section-title">
                    <i class="fas fa-shopping-cart"></i> Résumé
                </h2>

                <div class="commande-summary-items">
                    <?php foreach ($panier_items as $item): ?>
                        <?php
                        $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                            ? (float) $item['panier_prix_unitaire']
                            : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
                        $prix_total_item = $prix_unitaire * $item['quantite'];
                        $item_img = !empty($item['panier_variante_image']) ? $item['panier_variante_image'] : $item['image_principale'];
                        ?>
                        <div class="panier-item-summary">
                            <div class="panier-item-img">
                                <img src="<?php echo htmlspecialchars(upload_image_url((string) $item_img, 'sm'), ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($item['nom'], ENT_QUOTES, 'UTF-8'); ?>"
                                    onerror="this.src='/image/produit1.jpg'">
                            </div>
                            <div class="panier-item-summary-content">
                                <h4 class="panier-item-summary-title">
                                    <?php echo htmlspecialchars(!empty($item['panier_variante_nom']) ? $item['nom'] . ' - ' . $item['panier_variante_nom'] : $item['nom'], ENT_QUOTES, 'UTF-8'); ?>
                                </h4>
                                <?php
                                $opts = [];
                                if (!empty($item['panier_couleur']))
                                    $opts[] = 'Couleur: ' . htmlspecialchars($item['panier_couleur']);
                                if (!empty($item['panier_poids']))
                                    $opts[] = 'Poids: ' . htmlspecialchars($item['panier_poids']) . (!empty($item['panier_surcout_poids']) && $item['panier_surcout_poids'] > 0 ? ' (+' . number_format($item['panier_surcout_poids'], 0, ',', ' ') . ' FCFA)' : '');
                                if (!empty($item['panier_taille']))
                                    $opts[] = 'Taille: ' . htmlspecialchars($item['panier_taille']) . (!empty($item['panier_surcout_taille']) && $item['panier_surcout_taille'] > 0 ? ' (+' . number_format($item['panier_surcout_taille'], 0, ',', ' ') . ' FCFA)' : '');
                                ?>
                                <?php if (!empty($opts)): ?>
                                    <p class="panier-item-summary-opts"><?php echo implode(' • ', $opts); ?></p>
                                <?php endif; ?>
                                <div class="panier-item-summary-meta">
                                    <p class="panier-item-qty">Quantité: <?php echo (int) $item['quantite']; ?> ×
                                        <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> FCFA</p>
                                    <div class="panier-item-summary-price">
                                        <?php echo number_format($prix_total_item, 0, ',', ' '); ?> FCFA
                                    </div>
                                </div>
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

                <a href="<?php echo $commande_boutique_admin && $commande_boutique_slug !== '' ? htmlspecialchars(boutique_url('panier.php', $commande_boutique_slug), ENT_QUOTES, 'UTF-8') : '/panier.php'; ?>"
                    class="commande-link-retour">
                    <i class="fas fa-arrow-left"></i> Retour au panier
                </a>
            </div>

            <div class="commande-form-section">
                <h2 class="section-title">
                    <i class="fas fa-truck-fast"></i> Mode de réception
                </h2>

                <form method="POST" action="" id="form-commande">
                    <input type="hidden" name="action" value="create_commande">
                    <input type="hidden" name="mode_livraison" id="mode_livraison"
                        value="<?php echo htmlspecialchars($commande_mode_selected, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($commande_boutique_admin && $commande_boutique_slug !== ''): ?>
                        <input type="hidden" name="boutique_slug"
                            value="<?php echo htmlspecialchars($commande_boutique_slug, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>

                    <div class="cmd-mode-chooser" role="group" aria-label="Choisir le mode de réception">
                        <button type="button"
                            class="cmd-mode-btn<?php echo $commande_mode_selected === 'retrait' ? ' is-active' : ''; ?>"
                            data-mode="retrait" id="cmd-mode-btn-retrait">
                            <i class="fas fa-store"></i>
                            <span>Récupérer sur site</span>
                        </button>
                        <button type="button"
                            class="cmd-mode-btn<?php echo $commande_mode_selected === 'livraison' ? ' is-active' : ''; ?>"
                            data-mode="livraison" id="cmd-mode-btn-livraison">
                            <i class="fas fa-motorcycle"></i>
                            <span>Livraison</span>
                        </button>
                    </div>

                    <div id="panel-retrait"
                        class="cmd-mode-panel<?php echo $commande_mode_selected === 'retrait' ? ' is-visible' : ''; ?>">
                        <p class="cmd-pickup-hint">
                            Vous récupérez votre commande directement en boutique. Voici les points de retrait concernés
                            :
                        </p>
                        <div class="cmd-pickup-list">
                            <?php if (empty($commande_pickup_boutiques)): ?>
                                <p class="cmd-pickup-hint">Informations boutique indisponibles.</p>
                            <?php else: ?>
                                <?php foreach ($commande_pickup_boutiques as $boutique_pickup): ?>
                                    <div class="cmd-pickup-card">
                                        <p class="cmd-pickup-card__name">
                                            <i class="fas fa-store" aria-hidden="true"></i>
                                            <?php echo htmlspecialchars($boutique_pickup['nom'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <?php if (!empty($boutique_pickup['maps_url'])): ?>
                                            <p class="cmd-pickup-card__maps">
                                                <a href="<?php echo htmlspecialchars($boutique_pickup['maps_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    target="_blank" rel="noopener noreferrer">
                                                    <i class="fas fa-map"></i> Voir sur Google Maps
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="panel-livraison"
                        class="cmd-mode-panel<?php echo $commande_mode_selected === 'livraison' ? ' is-visible' : ''; ?>">
                        <!-- Position exacte du client (remplie par le navigateur après consentement) -->
                        <input type="hidden" name="geo_lat" id="geo_lat"
                            value="<?php echo $geo_saved ? htmlspecialchars(number_format($geo_saved['lat'], 8, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <input type="hidden" name="geo_lng" id="geo_lng"
                            value="<?php echo $geo_saved ? htmlspecialchars(number_format($geo_saved['lng'], 8, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <input type="hidden" name="geo_precision" id="geo_precision"
                            value="<?php echo $geo_saved && $geo_saved['precision'] !== null ? (int) $geo_saved['precision'] : ''; ?>">
                        <input type="hidden" name="geo_source" id="geo_source"
                            value="<?php echo $geo_saved ? 'gps' : ''; ?>">

                        <div class="geo-consent-box">
                            <div class="geo-search-wrap">
                                <label class="geo-search-label" for="geo_address_search">
                                    <i class="fas fa-magnifying-glass"></i> Rechercher une adresse de livraison
                                </label>
                                <div class="geo-search-input-wrap">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="geo_address_search" class="geo-search-input"
                                        autocomplete="off"
                                        placeholder="Quartier, rue, marché, ville… ou collez une adresse">
                                </div>
                                <div id="geo_search_suggestions" class="geo-search-suggestions" aria-hidden="true">
                                </div>
                            </div>

                            <div id="geo-map" class="geo-map" style="display:none;"></div>

                            <button type="button" id="btn-geo-capture" class="btn-geo-capture">
                                <i class="fas fa-location-crosshairs"></i>
                                <?php echo $geo_saved ? 'Mettre à jour ma position' : 'Utiliser ma position actuelle'; ?>
                            </button>
                            <div id="geo-status" class="geo-status" style="display:none;"></div>
                        </div>

                        <div class="form-group" id="group-adresse-livraison">
                            <label for="adresse_livraison">
                                <i class="fas fa-location-dot"></i> Adresse de livraison <span
                                    style="font-weight:400;color:var(--gris-moyen);">(facultatif — remplie
                                    automatiquement)</span>
                            </label>
                            <textarea id="adresse_livraison" name="adresse_livraison" rows="3"
                                placeholder="Sélectionnez une suggestion ci-dessus ou saisissez l'adresse complète…"><?php echo isset($_POST['adresse_livraison']) ? htmlspecialchars($_POST['adresse_livraison'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                        </div>
                    </div><!-- /#panel-livraison -->

                    <div class="form-group">
                        <label for="telephone_livraison">
                            <i class="fas fa-phone"></i>
                            <span
                                id="tel-label-text"><?php echo $commande_mode_selected === 'retrait' ? 'Téléphone de contact' : 'Téléphone de livraison'; ?></span>
                            *
                        </label>
                        <input type="tel" id="telephone_livraison" name="telephone_livraison" required
                            autocomplete="tel" placeholder="+221 XX XXX XX XX"
                            value="<?php echo isset($_POST['telephone_livraison']) ? htmlspecialchars($_POST['telephone_livraison'], ENT_QUOTES, 'UTF-8') : htmlspecialchars((string) ($user['telephone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <button type="submit" class="btn-submit-commande" id="btn-submit-commande">
                        <i class="fas fa-check-circle"></i> Confirmer la commande
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="cmdSubmitOverlay" class="cmd-submit-overlay" hidden aria-hidden="true" role="alertdialog" aria-live="polite" aria-labelledby="cmdSubmitOverlayTitle" aria-describedby="cmdSubmitOverlayDesc">
        <div class="cmd-submit-overlay__card">
            <div class="cmd-submit-overlay__icon" aria-hidden="true"><i class="fas fa-circle-notch"></i></div>
            <h3 class="cmd-submit-overlay__title" id="cmdSubmitOverlayTitle">Commande en cours d&rsquo;envoi</h3>
            <p class="cmd-submit-overlay__subtitle" id="cmdSubmitOverlayDesc">Veuillez patienter, nous enregistrons votre commande&hellip;</p>
            <div class="cmd-submit-overlay__track" aria-hidden="true">
                <div class="cmd-submit-overlay__bar" id="cmdSubmitOverlayBar"></div>
            </div>
            <div class="cmd-submit-overlay__pct" id="cmdSubmitOverlayPct">0&nbsp;%</div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <?php require_once __DIR__ . '/includes/auth_intl_tel_scripts.php'; ?>
    <?php require_once __DIR__ . '/includes/geo_native_bridge_script.php'; ?>
    <script src="/js/geo-address-format.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-location.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-location-search.js<?php echo asset_version_query(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modeInput = document.getElementById('mode_livraison');
            var panelRetrait = document.getElementById('panel-retrait');
            var panelLivraison = document.getElementById('panel-livraison');
            var telLabel = document.getElementById('tel-label-text');
            var modeBtns = document.querySelectorAll('.cmd-mode-btn');

            function setCommandeMode(mode) {
                mode = mode === 'retrait' ? 'retrait' : 'livraison';
                if (modeInput) modeInput.value = mode;
                modeBtns.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
                });
                if (panelRetrait) panelRetrait.classList.toggle('is-visible', mode === 'retrait');
                if (panelLivraison) panelLivraison.classList.toggle('is-visible', mode === 'livraison');
                if (telLabel) {
                    telLabel.textContent = mode === 'retrait' ? 'Téléphone de contact' : 'Téléphone de livraison';
                }
            }

            modeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setCommandeMode(btn.getAttribute('data-mode'));
                });
            });

            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone_livraison');
            }
            var addrField = document.getElementById('adresse_livraison');
            if (addrField) {
                addrField.addEventListener('input', function () {
                    delete addrField.dataset.geoAutofilled;
                });
            }
            if (window.GeoLocationCapture) {
                window.GeoLocationCapture.init({
                    latInput: 'geo_lat',
                    lngInput: 'geo_lng',
                    precisionInput: 'geo_precision',
                    sourceInput: 'geo_source',
                    statusEl: 'geo-status',
                    button: 'btn-geo-capture',
                    mapContainer: 'geo-map',
                    addressInput: 'adresse_livraison',
                    <?php if ($geo_saved): ?>
                    initial: {
                            lat: <?php echo json_encode((float) $geo_saved['lat']); ?>,
                            lng: <?php echo json_encode((float) $geo_saved['lng']); ?>,
                            precision: <?php echo json_encode($geo_saved['precision'] !== null ? (float) $geo_saved['precision'] : null); ?>
                        },
                    <?php endif; ?>
                auto: true
                });
            }
            if (window.GeoLocationSearch) {
                window.GeoLocationSearch.init({
                    searchInput: 'geo_address_search',
                    suggestionsList: 'geo_search_suggestions',
                    countryCode: <?php echo json_encode($geo_search_country_iso); ?>,
                    countryLabel: <?php echo json_encode($geo_search_country_label); ?>
                });
            }
        });
    </script>
    <script>
        (function () {
            var panierTotal = <?php echo $panier_total; ?>;
            var spanLivraison = document.getElementById('summary-livraison');
            var spanTotal = document.getElementById('summary-total');

            function formatNumber(n) {
                return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            var frais = 0;
            if (spanLivraison) spanLivraison.textContent = formatNumber(Math.round(frais)) + ' FCFA';
            if (spanTotal) spanTotal.textContent = formatNumber(Math.round(panierTotal + frais)) + ' FCFA';
        })();
    </script>
    <script>
        (function () {
            var form = document.getElementById('form-commande');
            var submitBtn = document.getElementById('btn-submit-commande');
            var overlay = document.getElementById('cmdSubmitOverlay');
            var bar = document.getElementById('cmdSubmitOverlayBar');
            var pct = document.getElementById('cmdSubmitOverlayPct');
            var locked = false;
            var progressTimer = null;
            var progressValue = 0;

            function showOverlay() {
                if (!overlay) return;
                overlay.removeAttribute('hidden');
                overlay.classList.add('is-visible');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('cmd-submit-busy');
            }

            function updateProgress(value) {
                progressValue = Math.max(progressValue, Math.min(98, value));
                if (bar) bar.style.width = progressValue + '%';
                if (pct) pct.textContent = Math.round(progressValue) + '\u00a0%';
            }

            function startProgress() {
                progressValue = 0;
                updateProgress(8);
                if (progressTimer) clearInterval(progressTimer);
                progressTimer = setInterval(function () {
                    if (progressValue >= 92) return;
                    var step = progressValue < 40 ? 4 + Math.random() * 5
                        : progressValue < 75 ? 2 + Math.random() * 3
                        : 0.4 + Math.random() * 1.2;
                    updateProgress(progressValue + step);
                }, 420);
            }

            function lockSubmit() {
                locked = true;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('aria-busy', 'true');
                }
                if (form) {
                    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (el) {
                        el.disabled = true;
                    });
                }
                showOverlay();
                startProgress();
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (locked) {
                        e.preventDefault();
                        return;
                    }
                    if (!form.checkValidity()) {
                        return;
                    }
                    lockSubmit();
                });
            }
        })();
    </script>
</body>

</html>