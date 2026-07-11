<?php
/**
 * Page d'accueil du tableau de bord administrateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/includes/require_admin_session.php';



require_once __DIR__ . '/includes/require_access.php';

require_once __DIR__ . '/../models/model_commandes_admin.php';
require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_categories.php';

$__role_dash_ann = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
if ($__role_dash_ann === 'vendeur' && file_exists(__DIR__ . '/../models/model_annonces.php')) {
    require_once __DIR__ . '/../models/model_annonces.php';
    if (function_exists('annonces_table_exists') && annonces_table_exists()) {
        annonce_mark_all_read_vendeur((int) ($_SESSION['admin_id'] ?? 0));
    }
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$add_produit_error_message = '';
$add_produit_post_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['admin_add_produit'])) {
    require_once __DIR__ . '/../controllers/controller_produits.php';
    require_once __DIR__ . '/../includes/flash_toast.php';
    $add_result = process_add_produit();
    if (!empty($add_result['success'])) {
        flash_toast_push('success', $add_result['message']);
        header('Location: dashboard.php');
        exit;
    }
    $add_produit_error_message = $add_result['message'] ?? "Erreur lors de l'ajout.";
    $add_produit_post_error = true;
    flash_toast_push('error', $add_produit_error_message);
}

$__role_dash = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$fap_use_category_hierarchy = categories_hierarchy_enabled() && ($__role_dash === 'vendeur');

$__vendeur_rappel_pending = false;
$__vendeur_admin = false;
if ($__role_dash === 'vendeur') {
    require_once __DIR__ . '/../models/model_vendeur_rappels.php';
    require_once __DIR__ . '/../models/model_admin.php';
    $__vendeur_admin = get_admin_by_id((int) ($_SESSION['admin_id'] ?? 0));
    if ($__vendeur_admin && ($__vendeur_admin['role'] ?? '') === 'vendeur') {
        $__vendeur_rappel_pending = vendeur_rappel_get_pending_for_dashboard((int) $__vendeur_admin['id']);
        if ($__vendeur_rappel_pending === false) {
            $__vendeur_rappel_pending = false;
        }
    }
}

$vcat_prefill_sub = 0;
$vcat_prefill_generale = 0;
$vendeur_genre_ids_prefill = [];
$open_add_modal = isset($_GET['open_add']) && $_GET['open_add'] === '1';
$categorie_id_prefill_modal = isset($_GET['prefill_categorie']) ? (int) $_GET['prefill_categorie'] : 0;
if ($fap_use_category_hierarchy && $categorie_id_prefill_modal > 0) {
    $cp = get_categorie_by_id($categorie_id_prefill_modal);
    if (
        $cp && function_exists('categorie_est_utilisable_par_vendeur')
        && categorie_est_utilisable_par_vendeur((int) $cp['id'], (int) $_SESSION['admin_id'])
    ) {
        $vcat_prefill_sub = (int) $cp['id'];
        if (function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
            $vcat_prefill_generale = (int) ($cp['categorie_generale_id'] ?? 0);
        }
    }
}
$categorie_id_prefill = $categorie_id_prefill_modal;
$enable_firebase_notifications = true;
$firebase_notify_type = 'admin';

$vf_dash = admin_vendeur_filter_id();
require_once __DIR__ . '/includes/vendeur_share_boutique.php';

$prix_neg_recentes = [];
$prix_neg_toutes = [];
$prix_neg_par_produit = [];
$prix_neg_produits_apercu = [];
if ($__role_dash === 'vendeur') {
    $prix_neg_model_path = __DIR__ . '/../models/model_prix_negociations.php';
    if (file_exists($prix_neg_model_path)) {
        require_once $prix_neg_model_path;
        if (function_exists('prix_negociations_table_exists') && prix_negociations_table_exists()) {
            $prix_neg_toutes = prix_negociation_list_by_admin((int) $_SESSION['admin_id'], null, 0);
            if (function_exists('prix_negociation_group_by_produit')) {
                $prix_neg_par_produit = prix_negociation_group_by_produit($prix_neg_toutes);
                $prix_neg_produits_apercu = array_values(array_filter($prix_neg_par_produit, function ($g) {
                    return ((int) ($g['pending_count'] ?? 0)) > 0;
                }));
            }
        }
    }
}

$produits_all = get_all_produits(null, $vf_dash);

require_once __DIR__ . '/../includes/image_optimizer.php';

$dash_promo_images = [];
$dash_promo_produits = get_produits_nouveautes(12);
if (is_array($dash_promo_produits)) {
    foreach ($dash_promo_produits as $dash_promo_p) {
        $dash_promo_img = trim((string) ($dash_promo_p['image_principale'] ?? ''));
        if ($dash_promo_img !== '') {
            $dash_promo_images[] = $dash_promo_img;
        }
        if (count($dash_promo_images) >= 10) {
            break;
        }
    }
}
if (count($dash_promo_images) < 4) {
    foreach (['produit1.jpg', 'produit2.jpg', 'produit3.jpg', 'produit4.jpg', 'produit5.jpg', 'produit6.jpg'] as $dash_promo_fallback) {
        if (!in_array($dash_promo_fallback, $dash_promo_images, true)) {
            $dash_promo_images[] = $dash_promo_fallback;
        }
        if (count($dash_promo_images) >= 6) {
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Administration COLObanes</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-vendeur-share.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/prix-negociation.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-card-uc.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== DASHBOARD VENDEUR v2 ===== */

        .dash-v2-page {
            padding: clamp(16px, 3vw, 32px);
            display: flex;
            flex-direction: column;
            gap: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ---- Header ---- */
        .dash-v2-header {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .dash-v2-header__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            width: 100%;
        }

        .dash-v2-header__left {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
        }

        .dash-v2-header__title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
            min-width: 0;
        }

        .dash-v2-header__notify {
            flex: 0 0 auto;
            margin-left: 0;
            white-space: nowrap;
            padding: 6px 10px;
            font-size: 0.72rem;
            max-width: 46%;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .dash-v2-header__notify-label {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-v2-header__eyebrow {
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--bleu-clair, #4a7ab8);
        }

        .dash-v2-header__title {
            font-size: clamp(1.35rem, 3vw, 1.85rem);
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres);
            line-height: 1.15;
            letter-spacing: -0.025em;
        }

        .dash-v2-header__title .highlight {
            color: var(--couleur-dominante, #3564a6);
            display: inline;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
        }

        .dash-v2-header__title-row .dash-v2-header__title {
            flex: 1 1 auto;
            min-width: 0;
            margin: 0;
        }

        .dash-v2-header__actions {
            display: flex;
            gap: 9px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* ---- Bannière visite COLObanes (alignée mon-compte) ---- */
        .mc-v2-shop-promo {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            height: 100px;
            max-height: 100px;
            background: linear-gradient(125deg, var(--bleu-fonce) 0%, var(--couleur-dominante) 48%, var(--bleu-clair) 100%);
            box-shadow: var(--ombre-douce);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .mc-v2-shop-promo__veil {
            position: absolute;
            inset: 0;
            background: linear-gradient(105deg, rgba(13, 13, 13, 0.72) 0%, rgba(45, 86, 144, 0.55) 42%, rgba(53, 100, 166, 0.25) 100%);
            z-index: 1;
            pointer-events: none;
        }

        .mc-v2-shop-promo__icons {
            position: absolute;
            inset: 0;
            z-index: 0;
            display: flex;
            align-items: center;
            opacity: 0.9;
            mask-image: linear-gradient(90deg, transparent 0%, #000 12%, #000 88%, transparent 100%);
            -webkit-mask-image: linear-gradient(90deg, transparent 0%, #000 12%, #000 88%, transparent 100%);
        }

        .mc-v2-shop-promo__track {
            display: flex;
            align-items: center;
            gap: 18px;
            width: max-content;
            animation: dash-v2-promo-scroll 38s linear infinite;
            padding: 0 12px;
        }

        @keyframes dash-v2-promo-scroll {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        .mc-v2-shop-promo__thumb {
            flex: 0 0 auto;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            object-fit: cover;
            opacity: 0.42;
            filter: blur(1.5px) saturate(1.1);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            border: 2px solid rgba(255, 255, 255, 0.35);
            transform: rotate(-4deg);
        }

        .mc-v2-shop-promo__thumb:nth-child(even) {
            transform: rotate(5deg) scale(0.95);
            opacity: 0.35;
        }

        .mc-v2-shop-promo__content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px 14px;
            height: 100%;
            max-height: 100px;
            box-sizing: border-box;
            padding: 8px 12px;
        }

        .mc-v2-shop-promo__copy {
            flex: 1 1 auto;
            min-width: 0;
            color: var(--texte-clair);
        }

        .mc-v2-shop-promo__eyebrow {
            margin: 0 0 1px;
            font-size: 0.5rem;
            line-height: 1.1;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.78);
        }

        .mc-v2-shop-promo__title {
            margin: 0;
            font-family: var(--font-titres);
            font-size: clamp(0.78rem, 2.2vw, 0.95rem);
            font-weight: 700;
            line-height: 1.2;
            color: #fff;
        }

        .mc-v2-shop-promo__title span {
            color: var(--orange-clair);
        }

        .mc-v2-shop-promo__cta {
            flex: 0 1 auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--orange);
            color: #fff;
            font-weight: 600;
            font-size: clamp(0.6rem, 1.8vw, 0.7rem);
            line-height: 1.2;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 6px 22px rgba(255, 107, 53, 0.45);
            transition: transform 0.25s ease, background 0.25s ease, box-shadow 0.25s ease;
        }

        .mc-v2-shop-promo__cta:hover {
            background: var(--orange-fonce);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(255, 107, 53, 0.5);
            color: #fff;
        }

        .mc-v2-shop-promo__cta i {
            font-size: 0.75rem;
        }

        @media (prefers-reduced-motion: reduce) {
            .mc-v2-shop-promo__track {
                animation: none;
            }
        }

        /* ---- Boutons toolbar ---- */
        .dash-v2-tool-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: 11px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: var(--font-corps);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
        }

        .dash-v2-tool-btn--primary {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
            box-shadow: 0 4px 14px rgba(53, 100, 166, 0.28);
        }

        .dash-v2-tool-btn--primary:hover {
            background: var(--bleu-fonce, #2d5690);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(53, 100, 166, 0.38);
        }

        .dash-v2-tool-btn--outline {
            background: #fff;
            color: var(--couleur-dominante, #3564a6);
            border: 1.5px solid rgba(53, 100, 166, 0.22);
        }

        .dash-v2-tool-btn--outline:hover {
            background: rgba(53, 100, 166, 0.05);
        }

        .dash-v2-tool-btn--ghost {
            background: transparent;
            color: var(--gris-fonce, #4a4a4a);
            border: 1.5px solid rgba(0, 0, 0, 0.1);
        }

        .dash-v2-tool-btn--ghost:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        /* ---- Notification ---- */
        .dash-v2-notif {
            padding: 13px 20px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            gap: 11px;
            font-size: 0.87rem;
            font-weight: 600;
        }

        .dash-v2-notif--success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.25);
            color: #15803d;
        }

        /* ---- Hero carte Vue d'ensemble ---- */
        .dash-v2-hero {
            background: var(--couleur-dominante, #3564a6);
            border-radius: 22px;
            padding: clamp(22px, 3.5vw, 38px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 40px color-mix(in srgb, var(--couleur-dominante, #3564a6) 34%, transparent);
        }

        .dash-v2-hero::before {
            content: '';
            position: absolute;
            top: -70px;
            right: -50px;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        .dash-v2-hero::after {
            content: '';
            position: absolute;
            bottom: -80px;
            right: 80px;
            width: 220px;
            height: 220px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
            pointer-events: none;
        }

        .dash-v2-hero__amount-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            width: 100%;
        }

        .dash-v2-hero__amount-row .dash-v2-hero__amount {
            order: 1;
            flex: 1 1 auto;
            min-width: 0;
        }

        .dash-v2-hero__amount-row .vendeur-hero-cert {
            position: static;
            top: auto;
            right: auto;
            order: 2;
            flex: 0 0 auto;
            margin-left: auto;
        }

        .dash-v2-hero__amount {
            font-size: clamp(1.9rem, 5vw, 3.2rem);
            font-weight: 900;
            color: #fff;
            line-height: 1.05;
            letter-spacing: -0.03em;
            font-family: var(--font-titres);
        }

        .dash-v2-hero__amount sup {
            font-size: 0.38em;
            font-weight: 600;
            opacity: 0.7;
            vertical-align: super;
            margin-right: 2px;
        }

        .dash-v2-hero__amount span.currency {
            font-size: 0.42em;
            font-weight: 600;
            opacity: 0.75;
            margin-left: 6px;
        }

        .dash-v2-hero__meta {
            margin-top: 22px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .dash-v2-hero__pill {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 50px;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            font-size: 0.83rem;
            font-weight: 600;
        }

        .dash-v2-hero__pill i {
            font-size: 0.88rem;
            opacity: 0.85;
        }

        .dash-v2-hero__pill strong {
            font-size: 1.07em;
        }

        .dash-v2-hero__pill--warn {
            background: rgba(255, 193, 7, 0.2);
            border-color: rgba(255, 193, 7, 0.35);
        }

        .dash-v2-hero__pill--share {
            background: rgba(255, 107, 53, 0.22);
            border-color: rgba(255, 107, 53, 0.42);
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        .dash-v2-hero__pill--share:hover {
            background: rgba(255, 107, 53, 0.32);
            transform: translateY(-1px);
        }

        a.dash-v2-hero__pill {
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        a.dash-v2-hero__pill:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
            color: #fff;
        }

        .dash-v2-hero__pill--boutique {
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(255, 255, 255, 0.28);
        }

        .dash-v2-hero__pill--boutique:hover {
            background: rgba(255, 255, 255, 0.28);
        }

        button.dash-v2-hero__pill {
            appearance: none;
            -webkit-appearance: none;
        }

        .dash-v2-hero__voir-tout {
            margin-left: auto;
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.79rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s, gap 0.2s;
        }

        .dash-v2-hero__voir-tout:hover {
            color: #fff;
            gap: 8px;
        }

        /* ---- Stat Cards ---- */
        .dash-v2-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
            gap: 14px;
        }

        .dash-v2-stat {
            background: #fff;
            border-radius: 18px;
            padding: 20px;
            border: 1px solid rgba(53, 100, 166, 0.08);
            box-shadow: 0 2px 14px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .dash-v2-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 26px rgba(53, 100, 166, 0.14);
        }

        .dash-v2-stat__icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .dash-v2-stat--total .dash-v2-stat__icon {
            background: rgba(53, 100, 166, 0.1);
            color: var(--couleur-dominante, #3564a6);
        }

        .dash-v2-stat--attente .dash-v2-stat__icon {
            background: rgba(255, 193, 7, 0.12);
            color: #c8960f;
        }

        .dash-v2-stat--prise .dash-v2-stat__icon {
            background: rgba(255, 107, 53, 0.12);
            color: var(--orange, #FF6B35);
        }

        .dash-v2-stat--livraison .dash-v2-stat__icon {
            background: rgba(34, 197, 94, 0.12);
            color: #16a34a;
        }

        .dash-v2-stat__content {
            display: flex;
            flex-direction: column;
            gap: 1px;
            min-width: 0;
        }

        .dash-v2-stat__label {
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--gris-moyen, #737373);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-v2-stat__value {
            font-size: 1.9rem;
            font-weight: 900;
            color: var(--titres, #0d0d0d);
            line-height: 1.05;
            font-family: var(--font-titres);
        }

        .dash-v2-stat__value-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
            min-width: 0;
        }

        .dash-v2-stat__hint {
            font-size: 0.71rem;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-top: 0;
            width: fit-content;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .dash-v2-stat--attente .dash-v2-stat__hint {
            background: rgba(255, 193, 7, 0.14);
            color: #9a6800;
        }

        /* ---- Alertes ---- */
        .dash-v2-alert {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.09) 0%, rgba(255, 193, 7, 0.04) 100%);
            border: 1px solid rgba(255, 193, 7, 0.28);
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 11px;
            flex-wrap: wrap;
        }

        .dash-v2-alert i {
            color: #c8960f;
            font-size: 1.08rem;
            flex-shrink: 0;
        }

        .dash-v2-alert__text {
            flex: 1;
            font-size: 0.87rem;
            font-weight: 600;
            color: #7a5c00;
            min-width: 0;
        }

        .dash-v2-alert__btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #c8960f;
            color: #fff;
            font-size: 0.79rem;
            font-weight: 700;
            padding: 7px 16px;
            border-radius: 50px;
            text-decoration: none;
            flex-shrink: 0;
            transition: background 0.2s;
        }

        .dash-v2-alert__btn:hover {
            background: #a97c0b;
        }

        /* ---- Layout 2 colonnes ---- */
        .dash-v2-mid {
            display: grid;
            grid-template-columns: 1fr 330px;
            gap: 18px;
        }

        @media (max-width: 920px) {
            .dash-v2-mid {
                grid-template-columns: 1fr;
            }
        }

        /* ---- Card générique ---- */
        .dash-v2-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(53, 100, 166, 0.08);
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .dash-v2-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 17px 22px 13px;
            border-bottom: 1px solid rgba(53, 100, 166, 0.07);
        }

        .dash-v2-card__head h3 {
            font-size: 0.93rem;
            font-weight: 700;
            color: var(--titres);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .dash-v2-card__head h3 i {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            background: rgba(53, 100, 166, 0.1);
            color: var(--couleur-dominante, #3564a6);
        }

        .dash-v2-card__link {
            font-size: 0.77rem;
            font-weight: 700;
            color: var(--couleur-dominante, #3564a6);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: gap 0.18s;
        }

        .dash-v2-card__link:hover {
            gap: 7px;
        }

        /* ---- Lignes commandes ---- */
        .dash-commande-row {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            padding: 12px 22px;
            gap: 10px;
            border-bottom: 1px solid rgba(53, 100, 166, 0.05);
            transition: background 0.14s;
            text-decoration: none;
            color: inherit;
        }

        .dash-commande-row:last-child {
            border-bottom: none;
        }

        .dash-commande-row:hover {
            background: rgba(53, 100, 166, 0.03);
        }

        .dash-commande-row__client {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .dash-commande-row__name {
            font-size: 0.86rem;
            font-weight: 600;
            color: var(--titres);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dash-commande-row__date {
            font-size: 0.72rem;
            color: var(--gris-moyen, #737373);
        }

        .dash-commande-row__amount {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--titres);
            white-space: nowrap;
            text-align: right;
        }

        .dash-commande-row__amount small {
            font-size: 0.69em;
            font-weight: 500;
            color: var(--gris-moyen);
        }

        .dash-commande-row__aside {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            margin-left: auto;
        }

        /* ---- Badges statut ---- */
        .dash-badge {
            font-size: 0.69rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .dash-badge--attente {
            background: rgba(255, 193, 7, 0.14);
            color: #9a6800;
        }

        .dash-badge--prise {
            background: rgba(255, 107, 53, 0.14);
            color: #c04a10;
        }

        .dash-badge--livraison {
            background: rgba(53, 100, 166, 0.12);
            color: var(--bleu-fonce, #2d5690);
        }

        .dash-badge--livree {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
        }

        .dash-badge--paye {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
        }

        .dash-badge--annulee {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
        }

        /* ---- Accès rapides ---- */
        .dash-quick-item {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 22px;
            border-bottom: 1px solid rgba(53, 100, 166, 0.05);
            text-decoration: none;
            color: var(--titres);
            transition: background 0.14s;
            background: transparent;
            border-left: none;
            border-right: none;
            border-top: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-family: var(--font-corps);
        }

        .dash-quick-item:last-child {
            border-bottom: none;
        }

        .dash-quick-item:hover {
            background: rgba(53, 100, 166, 0.04);
        }

        .dash-quick-item__icon {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            flex-shrink: 0;
        }

        .dash-quick-item:nth-child(1) .dash-quick-item__icon {
            background: rgba(53, 100, 166, 0.1);
            color: var(--couleur-dominante);
        }

        .dash-quick-item:nth-child(2) .dash-quick-item__icon {
            background: rgba(34, 197, 94, 0.12);
            color: #16a34a;
        }

        .dash-quick-item:nth-child(3) .dash-quick-item__icon {
            background: rgba(255, 193, 7, 0.14);
            color: #b57d0e;
        }

        .dash-quick-item:nth-child(4) .dash-quick-item__icon {
            background: rgba(255, 107, 53, 0.12);
            color: var(--orange, #FF6B35);
        }

        .dash-quick-item:nth-child(5) .dash-quick-item__icon {
            background: rgba(139, 92, 246, 0.12);
            color: #7c3aed;
        }

        .dash-quick-item__text {
            flex: 1;
        }

        .dash-quick-item__label {
            font-size: 0.87rem;
            font-weight: 600;
            color: var(--titres);
        }

        .dash-quick-item__sub {
            font-size: 0.72rem;
            color: var(--gris-moyen, #737373);
            margin-top: 1px;
        }

        .dash-quick-item__arrow {
            color: var(--gris-clair, #a3a3a3);
            font-size: 0.73rem;
        }

        /* Empty state */
        .dash-v2-empty {
            padding: 48px 24px;
            text-align: center;
            color: var(--gris-moyen, #737373);
        }

        .dash-v2-empty i {
            font-size: 2.5rem;
            opacity: 0.3;
            display: block;
            margin-bottom: 12px;
        }

        .dash-v2-empty p {
            font-size: 0.9rem;
            margin-bottom: 18px;
        }

        /* Modal plein écran */
        .adm-modal-add-produit[hidden] {
            display: none !important;
        }

        .adm-modal-add-produit {
            position: fixed;
            inset: 0;
            z-index: 9990;
            display: flex;
            flex-direction: column;
            background: rgba(13, 13, 13, 0.52);
            backdrop-filter: blur(6px);
        }

        .adm-modal-add-produit-inner {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            width: 100%;
            align-self: stretch;
            background: linear-gradient(165deg, var(--fond-secondaire, #fafafa) 0%, var(--blanc, #fff) 42%, rgba(53, 100, 166, 0.04) 100%);
            border-top: 3px solid var(--couleur-dominante, #3564a6);
            overflow: hidden;
        }

        .adm-modal-add-head {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 22px;
            background: var(--blanc, #fff);
            border-bottom: 1px solid var(--glass-border, rgba(0, 0, 0, 0.08));
        }

        .adm-modal-add-head h2 {
            margin: 0;
            font-size: 1.28rem;
            font-family: var(--font-titres, inherit);
            color: var(--titres, #0d0d0d);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .adm-modal-add-head h2 i {
            color: var(--couleur-dominante, #3564a6);
        }

        .adm-modal-add-head-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .adm-modal-add-close {
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 12px;
            background: rgba(53, 100, 166, 0.1);
            color: var(--couleur-dominante, #3564a6);
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
        }

        .adm-modal-add-close:hover {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
        }

        .adm-modal-add-body {
            flex: 1;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            padding: 22px 24px 40px;
        }

        .adm-modal-add-body .form-add-container {
            max-width: 1280px;
            margin: 0 auto;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dash-v2-page {
                gap: 16px;
            }

            .dash-v2-hero {
                padding: 18px 16px;
                border-radius: 16px;
            }

            .dash-v2-hero__amount {
                font-size: 1.75rem;
            }

            .dash-v2-hero__meta {
                margin-top: 14px;
                gap: 8px;
            }

            .dash-v2-hero__pill {
                padding: 5px 11px;
                font-size: 0.74rem;
            }

            .dash-v2-hero__voir-tout {
                padding: 6px 14px;
                font-size: 0.72rem;
            }

            .dash-v2-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .dash-v2-stat {
                padding: 10px 10px;
                border-radius: 12px;
                gap: 8px;
            }

            .dash-v2-stat__icon {
                width: 32px;
                height: 32px;
                font-size: 0.78rem;
                border-radius: 9px;
            }

            .dash-v2-stat__value {
                font-size: 1.15rem;
            }

            .dash-v2-stat__label {
                font-size: 0.58rem;
                letter-spacing: 0.05em;
            }

            .dash-v2-stat__hint {
                font-size: 0.58rem;
                padding: 1px 6px;
            }

            .dash-v2-stat__value-row {
                gap: 4px;
            }

            .dash-v2-alert {
                padding: 10px 12px;
                gap: 8px;
                border-radius: 12px;
            }

            .dash-v2-alert i {
                font-size: 0.9rem;
            }

            .dash-v2-alert__text {
                font-size: 0.76rem;
            }

            .dash-v2-alert__btn {
                font-size: 0.72rem;
                padding: 5px 12px;
            }

            .dash-v2-mid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .dash-v2-card {
                border-radius: 16px;
            }

            .dash-commande-row {
                flex-wrap: nowrap;
                align-items: center;
                padding: 10px 14px;
                gap: 8px;
            }

            .dash-commande-row__client {
                flex: 1 1 auto;
                min-width: 0;
            }

            .dash-commande-row__name {
                font-size: 0.78rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .dash-commande-row__date {
                font-size: 0.64rem;
                display: block;
                margin-top: 1px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .dash-commande-row__aside {
                width: auto;
                margin-left: auto;
                padding-left: 0;
                flex-shrink: 0;
                justify-content: flex-end;
                gap: 6px;
            }

            .dash-commande-row__amount {
                font-size: 0.74rem;
                text-align: right;
            }

            .dash-badge {
                font-size: 0.6rem;
                padding: 2px 7px;
            }

            .dash-quick-item {
                padding: 12px 16px;
                gap: 10px;
            }

            .dash-quick-item__icon {
                width: 36px;
                height: 36px;
                font-size: 0.82rem;
                border-radius: 10px;
            }

            .dash-quick-item__label {
                font-size: 0.82rem;
            }

            .dash-quick-item__sub {
                font-size: 0.68rem;
            }

            .dash-v2-header__actions .hide-sm {
                display: none;
            }

            .dash-v2-header__row {
                align-items: flex-start;
                gap: 8px;
            }

            .dash-v2-header__left {
                flex: 1 1 100%;
                min-width: 0;
                width: 100%;
            }

            .dash-v2-header__eyebrow {
                font-size: 0.6rem;
                letter-spacing: 0.08em;
            }

            .dash-v2-header__title-row {
                gap: 6px;
            }

            .dash-v2-header__title {
                font-size: clamp(0.82rem, 3.6vw, 1rem);
            }

            .dash-v2-header__notify {
                padding: 5px 8px;
                font-size: 0.64rem;
                max-width: 52%;
            }

            .dash-v2-header__notify i {
                font-size: 0.72rem;
            }

            .mc-v2-shop-promo__content {
                flex-direction: row;
                align-items: center;
                padding: 6px 10px;
                gap: 6px 8px;
            }

            .mc-v2-shop-promo__cta {
                width: auto;
                max-width: 50%;
                justify-content: center;
                padding: 5px 8px;
            }

            .mc-v2-shop-promo__thumb {
                width: 32px;
                height: 32px;
            }
        }

        @media (max-width: 480px) {
            .dash-v2-header__row {
                align-items: stretch;
            }

            .dash-v2-header__title-row {
                flex-wrap: nowrap;
            }

            .dash-v2-header__notify {
                max-width: 54%;
                padding: 4px 7px;
                font-size: 0.6rem;
            }

            .dash-v2-header__title {
                font-size: clamp(0.78rem, 3.2vw, 0.92rem);
            }

            .dash-v2-hero__amount-row {
                flex-wrap: nowrap;
                gap: 10px;
            }

            .dash-v2-hero__amount-row .vendeur-hero-cert .cert-badge-img--sm {
                width: 40px;
                height: auto;
            }
        }

        @media (max-width: 380px) {
            .dash-v2-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 6px;
            }

            .dash-v2-stat {
                padding: 8px;
                gap: 6px;
            }

            .dash-v2-stat__icon {
                width: 28px;
                height: 28px;
                font-size: 0.72rem;
            }

            .dash-v2-stat__value {
                font-size: 1rem;
            }

            .dash-v2-stat__label {
                font-size: 0.52rem;
            }

            .dash-v2-stat__hint {
                font-size: 0.52rem;
                padding: 1px 5px;
            }

            .dash-v2-alert {
                padding: 8px 10px;
            }

            .dash-v2-alert__text {
                font-size: 0.7rem;
            }

            .dash-v2-header__title {
                font-size: 0.74rem;
            }

            .dash-v2-header__eyebrow {
                font-size: 0.55rem;
            }

            .dash-v2-header__notify {
                font-size: 0.56rem;
                padding: 4px 6px;
                max-width: 56%;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="contents-container">
        <div class="dash-v2-page">

            <?php
            // ---- Données ----
            $total_commandes = count_commandes_by_statut(null, $vf_dash);
            $commandes_perso_en_attente = count_commandes_personnalisees_by_statut('en_attente', $vf_dash);
            $en_attente = count_commandes_by_statut('en_attente', $vf_dash);
            $prise_en_charge = count_commandes_by_statut('prise_en_charge', $vf_dash);
            $livraison_en_cours = count_commandes_by_statut('livraison_en_cours', $vf_dash);
            $stats_ventes = get_stats_commandes_vendues_globales($vf_dash);
            $ca_total = $stats_ventes['ca_total'] ?? 0;
            $nb_produits = count($produits_all);
            $toutes_commandes = get_all_commandes(null, $vf_dash);
            $commandes_recentes = array_slice(is_array($toutes_commandes) ? $toutes_commandes : [], 0, 3);
            $dash_boutique_nom = trim((string) ($_SESSION['admin_boutique_nom'] ?? ''));
            if ($dash_boutique_nom === '') {
                $dash_boutique_nom = 'Ma boutique';
            }
            $dash_boutique_nom = htmlspecialchars($dash_boutique_nom, ENT_QUOTES, 'UTF-8');
            ?>

            <?php if (!empty($success_message)): ?>
                <?php /* affiché via flash toast */ ?>
            <?php endif; ?>

            <?php
            if (isset($_SESSION['notification_test_message'])) {
                $test_msg = $_SESSION['notification_test_message'];
                $test_type = $_SESSION['notification_test_type'] ?? 'success';
                unset($_SESSION['notification_test_message'], $_SESSION['notification_test_type']);
                ?>
                <div class="dash-v2-notif dash-v2-notif--success">
                    <i class="fas fa-<?php echo $test_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($test_msg); ?>
                </div>
            <?php } ?>

            <!-- ===== HEADER ===== -->
            <header class="dash-v2-header">
                <div class="dash-v2-header__row">
                    <div class="dash-v2-header__left">
                        <p class="dash-v2-header__eyebrow">Bienvenue</p>
                        <div class="dash-v2-header__title-row">
                            <h1 class="dash-v2-header__title">
                                <span class="highlight"><?php echo $dash_boutique_nom; ?></span>
                            </h1>
                            <button type="button" id="btn-enable-notifications"
                                class="dash-v2-tool-btn dash-v2-tool-btn--outline dash-v2-header__notify"
                                data-notify-type="admin" title="Activer les notifications">
                                <i class="fas fa-bell-slash"></i>
                                <span class="dash-v2-header__notify-label">Activer les notifications</span>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" id="btn-install-pwa" class="dash-v2-tool-btn dash-v2-tool-btn--ghost"
                    style="display:none;" title="Installer l'application">
                    <i class="fas fa-download"></i>
                    <span class="hide-sm">Installer</span>
                </button>
            </header>

            <!-- ===== Bannière visite COLObanes ===== -->
            <section class="mc-v2-shop-promo" aria-labelledby="dash-v2-shop-promo-title">
                <div class="mc-v2-shop-promo__icons" aria-hidden="true">
                    <div class="mc-v2-shop-promo__track">
                        <?php
                        $dash_promo_track = array_merge($dash_promo_images, $dash_promo_images);
                        foreach ($dash_promo_track as $dash_promo_file):
                            $dash_promo_src = str_contains((string) $dash_promo_file, '/')
                                ? upload_image_url((string) $dash_promo_file, 'sm')
                                : '/image/' . rawurlencode((string) $dash_promo_file);
                            ?>
                            <img class="mc-v2-shop-promo__thumb"
                                src="<?php echo htmlspecialchars($dash_promo_src, ENT_QUOTES, 'UTF-8'); ?>" alt=""
                                loading="lazy" decoding="async" width="36" height="36">
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mc-v2-shop-promo__veil" aria-hidden="true"></div>
                <div class="mc-v2-shop-promo__content">
                    <div class="mc-v2-shop-promo__copy">
                        <p class="mc-v2-shop-promo__eyebrow">Globale Marketplace</p>
                        <h2 class="mc-v2-shop-promo__title" id="dash-v2-shop-promo-title">
                            Visitez la marketplace <span>COLObanes</span>
                        </h2>
                    </div>
                    <a href="/index.php" class="mc-v2-shop-promo__cta" title="Visiter COLObanes">
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        Visiter COLObanes
                    </a>
                </div>
            </section>

            <!-- ===== HERO VUE D'ENSEMBLE ===== -->
            <div class="dash-v2-hero">
                <div class="dash-v2-hero__amount-row">
                    <?php require __DIR__ . '/../includes/partials/vendeur_certification_hero_badge.php'; ?>
                    <div class="dash-v2-hero__amount">
                        <?php echo number_format($ca_total, 0, ',', ' '); ?><span class="currency">FCFA</span>
                    </div>
                </div>
                <div class="dash-v2-hero__meta">
                    <?php if (vendeur_share_boutique_is_available()): ?>
                        <?php $dash_boutique_vitrine = vendeur_share_boutique_get_data(); ?>
                        <a href="<?php echo htmlspecialchars($dash_boutique_vitrine['url'], ENT_QUOTES, 'UTF-8'); ?>"
                            class="dash-v2-hero__pill dash-v2-hero__pill--boutique" target="_blank"
                            rel="noopener noreferrer" title="Ouvrir votre vitrine en ligne dans un nouvel onglet">
                            <i class="fas fa-store"></i>
                            <span>Voir ma boutique</span>
                        </a>
                    <?php else: ?>
                        <div class="dash-v2-hero__pill">
                            <i class="fas fa-box"></i>
                            <span>Produits &nbsp;<strong><?php echo $nb_produits; ?></strong></span>
                        </div>
                    <?php endif; ?>
                    <?php if (vendeur_share_boutique_is_available()): ?>
                        <button type="button" class="dash-v2-hero__pill dash-v2-hero__pill--share"
                            id="dashHeroShareBoutique" aria-haspopup="dialog" aria-controls="platformShareModal">
                            <i class="fas fa-share-alt"></i>
                            <span>Partager ma boutique</span>
                        </button>
                    <?php endif; ?>
                    <a href="parametres.php" class="dash-v2-hero__voir-tout">
                        Param&egrave;tres <i class="fas fa-sliders-h"></i>
                    </a>
                </div>
            </div>

            <!-- ===== STAT CARDS ===== -->
            <div class="dash-v2-stats">
                <a href="commandes/index.php" class="dash-v2-stat dash-v2-stat--total">
                    <div class="dash-v2-stat__icon"><i class="fas fa-shopping-bag"></i></div>
                    <div class="dash-v2-stat__content">
                        <span class="dash-v2-stat__label">Total commandes</span>
                        <span class="dash-v2-stat__value"><?php echo $total_commandes; ?></span>
                    </div>
                </a>
                <a href="commandes/index.php?statut=en_attente" class="dash-v2-stat dash-v2-stat--attente">
                    <div class="dash-v2-stat__icon"><i class="fas fa-clock"></i></div>
                    <div class="dash-v2-stat__content">
                        <span class="dash-v2-stat__label">En attente</span>
                        <div class="dash-v2-stat__value-row">
                            <span class="dash-v2-stat__value"><?php echo $en_attente; ?></span>
                            <?php if ($en_attente > 0): ?>
                                <span class="dash-v2-stat__hint">
                                    <i class="fas fa-circle" style="font-size:.4rem;"></i> Action requise
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <a href="commandes/index.php?statut=prise_en_charge" class="dash-v2-stat dash-v2-stat--prise">
                    <div class="dash-v2-stat__icon"><i class="fas fa-box-open"></i></div>
                    <div class="dash-v2-stat__content">
                        <span class="dash-v2-stat__label">Prise en charge</span>
                        <span class="dash-v2-stat__value"><?php echo $prise_en_charge; ?></span>
                    </div>
                </a>
                <a href="commandes/index.php?statut=livraison_en_cours" class="dash-v2-stat dash-v2-stat--livraison">
                    <div class="dash-v2-stat__icon"><i class="fas fa-truck"></i></div>
                    <div class="dash-v2-stat__content">
                        <span class="dash-v2-stat__label">Livraison en cours</span>
                        <span class="dash-v2-stat__value"><?php echo $livraison_en_cours; ?></span>
                    </div>
                </a>
            </div>

            <!-- ===== ALERTES ===== -->
            <?php if ($en_attente > 0): ?>
                <div class="dash-v2-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="dash-v2-alert__text">
                        <strong><?php echo $en_attente; ?></strong>
                        commande<?php echo $en_attente > 1 ? 's' : ''; ?> en attente de prise en charge
                    </span>
                    <a href="commandes/index.php" class="dash-v2-alert__btn">
                        <i class="fas fa-arrow-right"></i> Traiter
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($commandes_perso_en_attente > 0): ?>
                <div class="dash-v2-alert">
                    <i class="fas fa-palette"></i>
                    <span class="dash-v2-alert__text">
                        <strong><?php echo $commandes_perso_en_attente; ?></strong>
                        commande<?php echo $commandes_perso_en_attente > 1 ? 's' : ''; ?>
                        personnalisée<?php echo $commandes_perso_en_attente > 1 ? 's' : ''; ?> en attente
                    </span>
                    <a href="commandes-personnalisees/index.php" class="dash-v2-alert__btn">
                        <i class="fas fa-arrow-right"></i> Voir
                    </a>
                </div>
            <?php endif; ?>

            <!-- ===== COMMANDES RÉCENTES + ACCÈS RAPIDES ===== -->
            <div class="dash-v2-mid">

                <?php if ($__role_dash === 'vendeur'): ?>
                    <div class="dash-v2-card">
                        <div class="dash-v2-card__head">
                            <h3><i class="fas fa-handshake"></i> Produits &agrave; n&eacute;gocier les prix</h3>
                        </div>
                        <?php if (empty($prix_neg_produits_apercu)): ?>
                            <div class="prix-neg-empty">
                                <i class="fas fa-inbox"></i>
                                <p>Aucune offre en attente pour le moment.</p>
                            </div>
                        <?php else: ?>
                            <div class="prix-neg-produit-list">
                                <?php foreach ($prix_neg_produits_apercu as $prix_neg_groupe):
                                    include __DIR__ . '/../includes/partials/prix_negociation_vendor_product.php';
                                endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Commandes récentes -->
                <div class="dash-v2-card">
                    <div class="dash-v2-card__head">
                        <h3><i class="fas fa-list-alt"></i> Commandes récentes</h3>
                        <a href="commandes/index.php" class="dash-v2-card__link">
                            Tout voir <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    <?php if (empty($commandes_recentes)): ?>
                        <div class="dash-v2-empty">
                            <i class="fas fa-inbox"></i>
                            <p>Aucune commande pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($commandes_recentes as $c):
                            $c_statut = $c['statut'] ?? '';
                            $badge_class = 'dash-badge--attente';
                            $badge_label = 'En attente';
                            if ($c_statut === 'prise_en_charge') {
                                $badge_class = 'dash-badge--prise';
                                $badge_label = 'Pris en charge';
                            } elseif ($c_statut === 'livraison_en_cours') {
                                $badge_class = 'dash-badge--livraison';
                                $badge_label = 'En livraison';
                            } elseif ($c_statut === 'livree') {
                                $badge_class = 'dash-badge--livree';
                                $badge_label = 'Livr&eacute;e';
                            } elseif ($c_statut === 'paye') {
                                $badge_class = 'dash-badge--paye';
                                $badge_label = 'Pay&eacute;e';
                            } elseif ($c_statut === 'annulee') {
                                $badge_class = 'dash-badge--annulee';
                                $badge_label = 'Annul&eacute;e';
                            }
                            $client = trim(($c['user_prenom'] ?? '') . ' ' . ($c['user_nom'] ?? ''));
                            if (empty($client))
                                $client = 'Client #' . ((int) ($c['user_id'] ?? 0));
                            $date_fmt = isset($c['date_commande']) ? date('d/m/Y', strtotime($c['date_commande'])) : '&mdash;';
                            ?>
                            <a href="commandes/details.php?id=<?php echo (int) $c['id']; ?>" class="dash-commande-row">
                                <span class="dash-commande-row__client">
                                    <span class="dash-commande-row__name"><?php echo htmlspecialchars($client); ?></span>
                                    <span class="dash-commande-row__date"><?php echo $date_fmt; ?></span>
                                </span>
                                <span class="dash-commande-row__aside">
                                    <span class="dash-commande-row__amount">
                                        <?php echo number_format((float) ($c['montant_total'] ?? 0), 0, ',', ' '); ?>
                                        <small>FCFA</small>
                                    </span>
                                    <span class="dash-badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($__role_dash !== 'vendeur'): ?>
                    <div class="dash-v2-card">
                        <div class="dash-v2-card__head">
                            <h3><i class="fas fa-bolt"></i> Acc&egrave;s rapides</h3>
                        </div>
                        <a href="stock/index.php" class="dash-quick-item">
                            <div class="dash-quick-item__icon"><i class="fas fa-box"></i></div>
                            <div class="dash-quick-item__text">
                                <div class="dash-quick-item__label">Mes produits</div>
                                <div class="dash-quick-item__sub"><?php echo $nb_produits; ?>
                                    produit<?php echo $nb_produits > 1 ? 's' : ''; ?>
                                    publi&eacute;<?php echo $nb_produits > 1 ? 's' : ''; ?></div>
                            </div>
                            <i class="fas fa-chevron-right dash-quick-item__arrow"></i>
                        </a>
                        <a href="ventes/index.php" class="dash-quick-item">
                            <div class="dash-quick-item__icon"><i class="fas fa-chart-bar"></i></div>
                            <div class="dash-quick-item__text">
                                <div class="dash-quick-item__label">Mes revenus</div>
                                <div class="dash-quick-item__sub"><?php echo number_format($ca_total, 0, ',', ' '); ?> FCFA
                                </div>
                            </div>
                            <i class="fas fa-chevron-right dash-quick-item__arrow"></i>
                        </a>
                        <a href="parametres.php" class="dash-quick-item">
                            <div class="dash-quick-item__icon"><i class="fas fa-cog"></i></div>
                            <div class="dash-quick-item__text">
                                <div class="dash-quick-item__label">Param&egrave;tres</div>
                                <div class="dash-quick-item__sub">Infos boutique et contact</div>
                            </div>
                            <i class="fas fa-chevron-right dash-quick-item__arrow"></i>
                        </a>
                    </div>
                <?php endif; ?>

            </div><!-- /.dash-v2-mid -->

        </div><!-- /.dash-v2-page -->
    </div><!-- /.contents-container -->

    <?php
    $add_produit_modal = true;
    $add_produit_form_action = 'dashboard.php';
    $modal_should_show = $add_produit_post_error || $open_add_modal;
    ?>
    <div id="modalAddProduitDash" class="adm-modal-add-produit" <?php echo $modal_should_show ? '' : 'hidden'; ?>
        aria-hidden="<?php echo $modal_should_show ? 'false' : 'true'; ?>">
        <div class="adm-modal-add-produit-inner">
            <div class="adm-modal-add-head">
                <h2><i class="fas fa-plus-circle"></i> Publier un produit</h2>
                <div class="adm-modal-add-head-actions">
                    <button type="button" class="adm-modal-add-close" id="btnCloseAddProduitModalDash" title="Fermer"
                        aria-label="Fermer">&times;</button>
                </div>
            </div>
            <div class="adm-modal-add-body">
                <div class="form-add-container">
                    <?php require __DIR__ . '/produits/inc_form_ajouter_produit.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalAdd = document.getElementById('modalAddProduitDash');
            var btnsOpen = [
                document.getElementById('btnOpenAddProduitModalDash'),
                document.getElementById('btnOpenAddProduitModalSide')
            ];
            var btnCloseDash = document.getElementById('btnCloseAddProduitModalDash');
            var btnCancelModal = document.getElementById('btn-fap-cancel-modal');

            function openAddModalDash() {
                if (!modalAdd) return;
                modalAdd.removeAttribute('hidden');
                modalAdd.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeAddModalDash() {
                if (!modalAdd) return;
                modalAdd.setAttribute('hidden', '');
                modalAdd.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            btnsOpen.forEach(function (btn) {
                if (btn) btn.addEventListener('click', openAddModalDash);
            });

            if (btnCloseDash) btnCloseDash.addEventListener('click', closeAddModalDash);
            if (btnCancelModal) btnCancelModal.addEventListener('click', closeAddModalDash);

            if (modalAdd) modalAdd.addEventListener('click', function (ev) {
                if (ev.target === modalAdd) closeAddModalDash();
            });

            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && modalAdd && !modalAdd.hasAttribute('hidden')) {
                    var vm = document.getElementById('fapVarianteModal');
                    if (vm && !vm.hidden) return;
                    closeAddModalDash();
                }
            });

            if (modalAdd && !modalAdd.hasAttribute('hidden')) {
                document.body.style.overflow = 'hidden';
            }

            try {
                var q = new URLSearchParams(window.location.search);
                if (q.get('open_add') === '1') {
                    openAddModalDash();
                    if (window.history && window.history.replaceState) {
                        var u = new URL(window.location.href);
                        u.searchParams.delete('open_add');
                        u.searchParams.delete('prefill_categorie');
                        window.history.replaceState({}, '', u.pathname + u.search + u.hash);
                    }
                }
            } catch (e) { }

            // PWA install
            var installBtn = document.getElementById('btn-install-pwa');
            var deferredPrompt;

            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                if (installBtn) installBtn.style.display = 'none';
            } else {
                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault();
                    deferredPrompt = e;
                    if (installBtn) installBtn.style.display = 'inline-flex';
                });

                if (installBtn) {
                    installBtn.addEventListener('click', function () {
                        if (!deferredPrompt) {
                            alert("L'installation n'est pas disponible. Essayez depuis Chrome ou Edge en mode HTTPS.");
                            return;
                        }
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then(function (choiceResult) {
                            if (choiceResult.outcome === 'accepted') installBtn.style.display = 'none';
                            deferredPrompt = null;
                        });
                    });
                }
            }
        });
    </script>
    <?php
    if ($__role_dash === 'vendeur' && !empty($prix_neg_par_produit)):
        foreach ($prix_neg_par_produit as $prix_neg_groupe):
            $pn_prod_id = (int) ($prix_neg_groupe['produit_id'] ?? 0);
            if ($pn_prod_id <= 0) {
                continue;
            }
            $pn_modal_id = 'prixNegOffersProd' . $pn_prod_id;
            $pn_prod_nom = (string) ($prix_neg_groupe['produit_nom'] ?? 'Produit');
            $pn_prod_img = trim((string) ($prix_neg_groupe['produit_image'] ?? ''));
            $pn_img_url = $pn_prod_img !== '' ? upload_image_url($pn_prod_img, 'sm') : '';
            ?>
            <div class="prix-neg-fullscreen prix-neg-fullscreen--offers"
                id="<?php echo htmlspecialchars($pn_modal_id, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true" hidden>
                <header class="prix-neg-fullscreen__head">
                    <div class="prix-neg-fullscreen__head-main">
                        <?php if ($pn_img_url !== ''): ?>
                            <img class="prix-neg-fullscreen__head-img"
                                src="<?php echo htmlspecialchars($pn_img_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="48"
                                height="48">
                        <?php endif; ?>
                        <div>
                            <h2><?php echo htmlspecialchars($pn_prod_nom, ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="prix-neg-fullscreen__sub">Offres re&ccedil;ues des clients</p>
                        </div>
                    </div>
                    <button type="button" class="prix-neg-modal__close" data-prix-neg-offers-close aria-label="Fermer">
                        <i class="fas fa-times"></i>
                    </button>
                </header>
                <div class="prix-neg-fullscreen__body">
                    <div class="prix-neg-card__list uc-v2-list">
                        <?php foreach ($prix_neg_groupe['offres'] as $neg):
                            $prix_neg_side = 'vendor';
                            include __DIR__ . '/../includes/partials/prix_negociation_row.php';
                        endforeach; ?>
                    </div>
                </div>
            </div>
            <?php
        endforeach;
        ?>
        <script src="/js/prix-negociation-modal.js<?php echo asset_version_query(); ?>"></script>
    <?php endif; ?>
    <?php
    if (vendeur_share_boutique_is_available()) {
        include dirname(__DIR__) . '/includes/partials/platform_share_modal.php';
        ?>
        <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>" defer></script>
        <?php
        vendeur_share_boutique_render_script([
            'open_button_ids' => ['dashHeroShareBoutique'],
        ]);
    }
    if ($__role_dash === 'vendeur' && !empty($__vendeur_rappel_pending) && is_array($__vendeur_rappel_pending)) {
        include dirname(__DIR__) . '/includes/partials/vendeur_rappel_modal.php';
    }
    ?>
    <?php include 'includes/footer.php'; ?>
</body>

</html>