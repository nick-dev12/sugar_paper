<?php
/**
 * Page tableau de bord utilisateur — Mon Compte
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();


if (!function_exists('auth_redirect_to_site_home')) {
    require_once __DIR__ . '/../includes/auth_redirect.php';
}
if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    auth_redirect_to_site_home();
    exit;
}

require_once __DIR__ . '/../models/model_users.php';
$user = get_user_by_id($_SESSION['user_id']);

if (!$user) {
    session_destroy();
    auth_redirect_to_site_home();
    exit;
}

require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_visites.php';
require_once __DIR__ . '/../models/model_produits.php';

if (file_exists(__DIR__ . '/../models/model_annonces.php')) {
    require_once __DIR__ . '/../models/model_annonces.php';
    if (function_exists('annonces_table_exists') && annonces_table_exists()) {
        annonce_mark_all_read_client((int) $_SESSION['user_id']);
    }
}

$mc_promo_images = [];
$mc_promo_produits = get_produits_nouveautes(12);
if (is_array($mc_promo_produits)) {
    foreach ($mc_promo_produits as $mc_p) {
        $mc_img = trim((string) ($mc_p['image_principale'] ?? ''));
        if ($mc_img !== '') {
            $mc_promo_images[] = $mc_img;
        }
        if (count($mc_promo_images) >= 10) {
            break;
        }
    }
}
if (count($mc_promo_images) < 4) {
    foreach (['produit1.jpg', 'produit2.jpg', 'produit3.jpg', 'produit4.jpg', 'produit5.jpg', 'produit6.jpg'] as $mc_fallback) {
        if (!in_array($mc_fallback, $mc_promo_images, true)) {
            $mc_promo_images[] = $mc_fallback;
        }
        if (count($mc_promo_images) >= 6) {
            break;
        }
    }
}

$nb_commandes       = count_commandes_by_user($_SESSION['user_id']);
$nb_panier          = count_panier_items_by_user($_SESSION['user_id']);
$nb_visites         = count_visites_by_user($_SESSION['user_id']);

$toutes_commandes_raw = get_commandes_by_user($_SESSION['user_id']);
$toutes_commandes = is_array($toutes_commandes_raw) ? $toutes_commandes_raw : [];
$statuts_termines = ['livree', 'paye', 'annulee'];

// Commandes récentes en cours de traitement (max. 2)
$commandes_en_cours = array_values(array_filter($toutes_commandes, function ($cmd) use ($statuts_termines) {
    return !in_array($cmd['statut'] ?? '', $statuts_termines, true);
}));
$commandes_recentes = array_slice($commandes_en_cours, 0, 2);

/** Contexte cartes commande sur le tableau de bord mon-compte (sans footer, clic → suivi). */
$mc_dashboard_cmd_ctx = [
    'show_footer' => false,
    'link_to_tracking' => true,
];

// Produits livrés — 3 dernières commandes reçues (livree + paye)
$commandes_recues_recentes = array_slice(
    array_values(array_filter($toutes_commandes, function ($c) {
        return in_array($c['statut'] ?? '', ['livree', 'paye'], true);
    })),
    0,
    3
);
$nb_commandes_recues = count(array_values(array_filter($toutes_commandes, function ($c) {
    return in_array($c['statut'] ?? '', ['livree', 'paye'], true);
})));

// Commandes actives (non livrées/annulées)
$nb_actives = count_commandes_actives_by_user($_SESSION['user_id']);

$prix_neg_recentes = [];
$prix_neg_toutes = [];
$prix_neg_model_path = __DIR__ . '/../models/model_prix_negociations.php';
if (file_exists($prix_neg_model_path)) {
    require_once $prix_neg_model_path;
    if (function_exists('prix_negociations_table_exists') && prix_negociations_table_exists()) {
        $prix_neg_toutes = prix_negociation_list_by_user((int) $_SESSION['user_id'], null, 0);
        $prix_neg_actives = array_values(array_filter($prix_neg_toutes, function ($n) {
            return ($n['statut'] ?? '') !== 'commandee';
        }));
        $prix_neg_recentes = array_slice($prix_neg_actives, 0, 3);
    }
}

$avatar_initial = '?';
$nom_trim    = trim((string) ($user['nom']    ?? ''));
$prenom_trim = trim((string) ($user['prenom'] ?? ''));
if ($prenom_trim !== '') {
    $avatar_initial = mb_strtoupper(mb_substr($prenom_trim, 0, 1, 'UTF-8'), 'UTF-8');
} elseif ($nom_trim !== '') {
    $avatar_initial = mb_strtoupper(mb_substr($nom_trim, 0, 1, 'UTF-8'), 'UTF-8');
}
$user_name_display  = trim($prenom_trim . ' ' . $nom_trim);
$user_email_display = trim((string) ($user['email'] ?? ''));

$enable_firebase_notifications = true;
$firebase_notify_type          = 'user';
$skip_pwa_service_worker       = true;

// Helper statut commande
function mc_statut_label($s) {
    $map = [
        'en_attente'        => 'En attente',
        'prise_en_charge'   => 'Confirm&eacute;e',
        'livraison_en_cours' => 'En livraison',
        'livree'            => 'Livr&eacute;e',
        'paye'              => 'Pay&eacute;e',
        'annulee'           => 'Annul&eacute;e',
    ];
    return $map[$s] ?? ucfirst(str_replace('_', ' ', $s));
}

function mc_statut_css($s) {
    $map = [
        'en_attente'        => 'mc-badge--wait',
        'prise_en_charge'   => 'mc-badge--confirm',
        'livraison_en_cours' => 'mc-badge--delivery',
        'livree'            => 'mc-badge--done',
        'paye'              => 'mc-badge--done',
        'annulee'           => 'mc-badge--cancel',
    ];
    return $map[$s] ?? 'mc-badge--wait';
}

function mc_statut_icon($s) {
    $map = [
        'en_attente'        => 'fa-clock',
        'prise_en_charge'   => 'fa-box-open',
        'livraison_en_cours' => 'fa-truck',
        'livree'            => 'fa-circle-check',
        'paye'              => 'fa-circle-check',
        'annulee'           => 'fa-ban',
    ];
    return $map[$s] ?? 'fa-clock';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Mon compte &mdash; <?php echo htmlspecialchars($user_name_display); ?></title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mon-compte.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-card-uc.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/prix-negociation.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== MON COMPTE v2 ===== */

        .mc-v2-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 36px) clamp(14px, 4vw, 28px) 80px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Bannière courses COLObanes */
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
            animation: mc-v2-promo-scroll 38s linear infinite;
            padding: 0 12px;
        }

        @keyframes mc-v2-promo-scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
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

        /* ---- Hero ---- */
        .mc-v2-hero {
            background: linear-gradient(135deg, var(--couleur-dominante, #3564a6) 0%, #1e3f7a 60%, #0f2550 100%);
            border-radius: 22px;
            padding: clamp(22px, 4vw, 38px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(53,100,166,0.28);
        }

        .mc-v2-hero::before {
            content: '';
            position: absolute;
            top: -80px; right: -50px;
            width: 280px; height: 280px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        .mc-v2-hero::after {
            content: '';
            position: absolute;
            bottom: -90px; right: 80px;
            width: 220px; height: 220px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }

        .mc-v2-hero__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .mc-v2-hero__left {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .mc-v2-avatar {
            width: 68px; height: 68px;
            border-radius: 18px;
            background: rgba(255,255,255,0.18);
            border: 2px solid rgba(255,255,255,0.3);
            color: #fff;
            font-size: 1.7rem;
            font-weight: 900;
            display: flex; align-items: center; justify-content: center;
            font-family: var(--font-titres);
            flex-shrink: 0;
        }

        .mc-v2-hero__info { display: flex; flex-direction: column; gap: 3px; }

        .mc-v2-hero__eyebrow {
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255,255,255,0.6);
        }

        .mc-v2-hero__name {
            font-size: clamp(1.2rem, 3vw, 1.65rem);
            font-weight: 800;
            color: #fff;
            font-family: var(--font-titres);
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .mc-v2-hero__email {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.65);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .mc-v2-hero__pill {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50px;
            padding: 7px 16px;
            display: flex;
            align-items: center;
            gap: 7px;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .mc-v2-hero__pill i { opacity: 0.8; font-size: 0.82rem; }
        .mc-v2-hero__pill strong { font-size: 1.06em; }
        .mc-v2-hero__pill--active { background: rgba(255,193,7,0.2); border-color: rgba(255,193,7,0.3); }
        .mc-v2-hero__pill--done   { background: rgba(34,197,94,0.18); border-color: rgba(34,197,94,0.28); }

        .mc-v2-hero__actions {
            display: contents;
        }

        .mc-v2-hero__cta-grid {
            display: flex;
            flex-direction: column;
            gap: 9px;
            align-items: flex-end;
        }

        .mc-v2-hero__pills {
            display: contents;
        }

        .mc-v2-hero__btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-family: var(--font-corps);
            transition: background 0.2s;
            white-space: nowrap;
        }

        .mc-v2-hero__btn--ghost {
            background: rgba(255,255,255,0.12);
            border: 1.5px solid rgba(255,255,255,0.22);
            color: #fff;
        }

        .mc-v2-hero__btn--ghost:hover { background: rgba(255,255,255,0.2); }

        /* ---- Stat cards ---- */
        .mc-v2-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 13px;
        }

        .mc-v2-stat {
            background: #fff;
            border-radius: 17px;
            padding: 18px 16px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .mc-v2-stat:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(53,100,166,0.14); }

        .mc-v2-stat__icon {
            width: 46px; height: 46px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .mc-v2-stat--orders  .mc-v2-stat__icon { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }
        .mc-v2-stat--cart    .mc-v2-stat__icon { background: rgba(255,107,53,0.12); color: var(--orange, #FF6B35); }
        .mc-v2-stat--fav     .mc-v2-stat__icon { background: rgba(239,68,68,0.1); color: #e03131; }
        .mc-v2-stat--visits  .mc-v2-stat__icon { background: rgba(139,92,246,0.12); color: #7c3aed; }

        .mc-v2-stat__body { display: flex; flex-direction: column; gap: 1px; min-width: 0; }

        .mc-v2-stat__val {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--titres);
            line-height: 1.05;
            font-family: var(--font-titres);
        }

        .mc-v2-stat__lbl {
            font-size: 0.73rem;
            font-weight: 700;
            color: var(--gris-moyen, #737373);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            white-space: nowrap;
        }

        /* ---- Disposition 2 colonnes ---- */
        .mc-v2-mid {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        /* ---- Card générique ---- */
        .mc-v2-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 16px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .mc-v2-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 17px 22px 13px;
            border-bottom: 1px solid rgba(53,100,166,0.07);
        }

        .mc-v2-card__head h2 {
            font-size: 0.93rem;
            font-weight: 700;
            color: var(--titres);
            display: flex; align-items: center; gap: 9px;
        }

        .mc-v2-card__head h2 i {
            width: 30px; height: 30px;
            border-radius: 9px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.82rem;
            background: rgba(53,100,166,0.1);
            color: var(--couleur-dominante, #3564a6);
        }

        .mc-v2-card__link {
            font-size: 0.77rem;
            font-weight: 700;
            color: var(--couleur-dominante, #3564a6);
            text-decoration: none;
            display: flex; align-items: center; gap: 4px;
            transition: gap 0.18s;
        }

        .mc-v2-card__link:hover { gap: 8px; }

        .mc-v2-card__link--green {
            color: #16a34a;
        }

        .mc-v2-card__link--green:hover {
            color: #15803d;
        }

        /* ---- Commande row ---- */
        .mc-commande-row {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px 22px;
            border-bottom: 1px solid rgba(53,100,166,0.05);
            text-decoration: none;
            color: inherit;
            transition: background 0.14s;
        }

        .mc-commande-row:last-child { border-bottom: none; }
        .mc-commande-row:hover { background: rgba(53,100,166,0.03); }

        .mc-commande-row__icon {
            width: 38px; height: 38px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .mc-commande-row__icon--wait     { background: rgba(255,193,7,0.12); color: #c8960f; }
        .mc-commande-row__icon--confirm  { background: rgba(255,107,53,0.12); color: var(--orange, #FF6B35); }
        .mc-commande-row__icon--delivery { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }
        .mc-commande-row__icon--done     { background: rgba(34,197,94,0.12); color: #16a34a; }
        .mc-commande-row__icon--cancel   { background: rgba(239,68,68,0.1); color: #b91c1c; }

        .mc-commande-row__body { flex: 1; min-width: 0; }

        .mc-commande-row__num {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--titres);
        }

        .mc-commande-row__meta {
            font-size: 0.72rem;
            color: var(--gris-moyen, #737373);
            margin-top: 1px;
        }

        .mc-commande-row__right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }

        .mc-commande-row__amount {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--titres);
            white-space: nowrap;
            font-family: var(--font-titres);
        }

        /* Badges */
        .mc-badge {
            font-size: 0.67rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 50px;
            white-space: nowrap;
        }

        .mc-badge--wait     { background: rgba(255,193,7,0.14); color: #9a6800; }
        .mc-badge--confirm  { background: rgba(255,107,53,0.14); color: #c04a10; }
        .mc-badge--delivery { background: rgba(53,100,166,0.12); color: var(--bleu-fonce, #2d5690); }
        .mc-badge--done     { background: rgba(34,197,94,0.12); color: #15803d; }
        .mc-badge--cancel   { background: rgba(239,68,68,0.1); color: #b91c1c; }

        /* ---- Liens rapides ---- */
        .mc-quick-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 22px;
            border-bottom: 1px solid rgba(53,100,166,0.05);
            text-decoration: none;
            color: var(--titres);
            transition: background 0.14s;
        }

        .mc-quick-link:last-child { border-bottom: none; }
        .mc-quick-link:hover { background: rgba(53,100,166,0.04); }

        .mc-quick-link__icon {
            width: 40px; height: 40px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.88rem;
            flex-shrink: 0;
        }

        .mc-quick-link:nth-child(1) .mc-quick-link__icon { background: rgba(53,100,166,0.1); color: var(--couleur-dominante); }
        .mc-quick-link:nth-child(2) .mc-quick-link__icon { background: rgba(255,107,53,0.12); color: var(--orange, #FF6B35); }
        .mc-quick-link:nth-child(3) .mc-quick-link__icon { background: rgba(239,68,68,0.1); color: #e03131; }
        .mc-quick-link:nth-child(4) .mc-quick-link__icon { background: rgba(255,193,7,0.14); color: #b57d0e; }
        .mc-quick-link:nth-child(5) .mc-quick-link__icon { background: rgba(139,92,246,0.12); color: #7c3aed; }

        .mc-quick-link__text { flex: 1; }
        .mc-quick-link__label { font-size: 0.87rem; font-weight: 600; color: var(--titres); }
        .mc-quick-link__sub   { font-size: 0.71rem; color: var(--gris-moyen, #737373); margin-top: 1px; }
        .mc-quick-link__arrow { color: var(--gris-clair, #a3a3a3); font-size: 0.73rem; }

        /* ---- Alerte commande active ---- */
        .mc-v2-alert {
            background: linear-gradient(135deg, rgba(255,193,7,0.09), rgba(255,193,7,0.04));
            border: 1px solid rgba(255,193,7,0.28);
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 11px;
            flex-wrap: wrap;
        }

        .mc-v2-alert i { color: #c8960f; font-size: 1.08rem; }
        .mc-v2-alert__text { flex: 1; font-size: 0.87rem; font-weight: 600; color: #7a5c00; }
        .mc-v2-alert__btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #c8960f;
            color: #fff;
            font-size: 0.79rem;
            font-weight: 700;
            padding: 7px 16px;
            border-radius: 50px;
            text-decoration: none;
            transition: background 0.2s;
        }

        .mc-v2-alert__btn:hover { background: #a97c0b; }

        /* ---- Section produits livrés ---- */
        .mc-v2-delivered {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 16px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .mc-v2-delivered__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 19px 24px 15px;
            border-bottom: 1px solid rgba(53,100,166,0.07);
        }

        .mc-v2-delivered__head h2 {
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--titres);
            display: flex; align-items: center; gap: 10px;
        }

        .mc-v2-delivered__head h2 i {
            width: 33px; height: 33px;
            border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.88rem;
            background: rgba(34,197,94,0.12);
            color: #16a34a;
        }

        .mc-v2-delivered__count {
            display: inline-flex; align-items: center;
            background: rgba(34,197,94,0.1);
            color: #15803d;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 50px;
            margin-left: 6px;
        }

        .mc-v2-products-grid {
            padding: 20px 24px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .mc-v2-product-card {
            border-radius: 14px;
            border: 1px solid rgba(53,100,166,0.08);
            background: #fff;
            overflow: hidden;
            transition: transform 0.22s, box-shadow 0.22s;
        }

        .mc-v2-product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 26px rgba(53,100,166,0.12);
        }

        .mc-v2-product-card__img {
            width: 100%;
            aspect-ratio: 4/3;
            overflow: hidden;
            background: var(--blanc-neige, #f5f5f5);
        }

        .mc-v2-product-card__img img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.35s;
        }

        .mc-v2-product-card:hover .mc-v2-product-card__img img { transform: scale(1.06); }

        .mc-v2-product-card__body { padding: 12px 13px; }

        .mc-v2-product-card__name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mc-v2-product-card__cat {
            font-size: 0.71rem;
            color: var(--gris-moyen, #737373);
            margin-bottom: 7px;
        }

        .mc-v2-product-card__price-row {
            display: flex; align-items: baseline; gap: 5px;
            margin-bottom: 5px;
        }

        .mc-v2-product-card__price {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--couleur-dominante, #3564a6);
            font-family: var(--font-titres);
        }

        .mc-v2-product-card__price-unit { font-size: 0.67rem; font-weight: 600; color: var(--gris-moyen); }

        .mc-v2-product-card__qty {
            display: flex; align-items: center; gap: 5px;
            font-size: 0.73rem;
            color: var(--gris-moyen, #737373);
            margin-bottom: 5px;
        }

        .mc-v2-product-card__qty i { font-size: 0.65rem; }

        .mc-v2-product-card__cmd {
            font-size: 0.7rem;
            color: var(--couleur-dominante, #3564a6);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mc-v2-product-card__actions { display: flex; gap: 7px; margin-top: 10px; }

        .mc-v2-product-btn {
            flex: 1;
            display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            padding: 7px 0;
            border-radius: 8px;
            font-size: 0.77rem;
            font-weight: 700;
            text-decoration: none;
            border: none;
            font-family: var(--font-corps);
            transition: background 0.2s;
        }

        .mc-v2-product-btn--view {
            background: rgba(53,100,166,0.08);
            color: var(--couleur-dominante, #3564a6);
        }

        .mc-v2-product-btn--view:hover { background: rgba(53,100,166,0.15); }

        .mc-v2-product-btn--detail {
            background: rgba(34,197,94,0.1);
            color: #15803d;
        }

        .mc-v2-product-btn--detail:hover { background: rgba(34,197,94,0.18); }

        /* Empty state */
        .mc-v2-empty {
            padding: 50px 24px;
            text-align: center;
            color: var(--gris-moyen, #737373);
        }

        .mc-v2-empty i { font-size: 2.5rem; opacity: 0.25; display: block; margin-bottom: 14px; }
        .mc-v2-empty p { font-size: 0.88rem; margin-bottom: 18px; }

        .mc-v2-cta-link {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 22px;
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
            border-radius: 11px;
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 700;
            transition: background 0.2s;
        }

        .mc-v2-cta-link:hover { background: var(--bleu-fonce, #2d5690); }

        /* Notify panel */
        .mc-v2-notify-panel {
            background: rgba(53,100,166,0.05);
            border: 1px solid rgba(53,100,166,0.15);
            border-radius: 13px;
            padding: 14px 18px;
            font-size: 0.84rem;
        }

        .mc-v2-notify-panel[hidden] { display: none; }

        .mc-v2-notify-panel h4 {
            font-size: 0.87rem;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 8px;
            display: flex; align-items: center; gap: 7px;
        }

        .mc-v2-notify-panel ol {
            padding-left: 18px;
            color: var(--gris-fonce, #4a4a4a);
            display: flex; flex-direction: column; gap: 4px;
        }

        .mc-v2-notify-panel ol li { font-size: 0.82rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .mc-v2-page { gap: 16px; }

            .mc-v2-hero {
                padding: 16px 14px;
                border-radius: 16px;
            }

            .mc-v2-hero__inner {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .mc-v2-hero__left {
                gap: 12px;
            }

            .mc-v2-avatar {
                width: 52px;
                height: 52px;
                border-radius: 14px;
                font-size: 1.35rem;
            }

            .mc-v2-hero__eyebrow {
                font-size: 0.62rem;
                letter-spacing: 0.1em;
            }

            .mc-v2-hero__name {
                font-size: 1.05rem;
            }

            .mc-v2-hero__email {
                font-size: 0.72rem;
            }

            .mc-v2-hero__cta-grid {
                display: flex;
                justify-content: stretch;
                width: 100%;
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

            .mc-v2-hero__btn,
            .mc-v2-hero__pill {
                width: 100%;
                min-width: 0;
                justify-content: center;
                text-align: center;
                white-space: normal;
                padding: 8px 10px;
                font-size: 0.72rem;
                border-radius: 10px;
                line-height: 1.25;
            }

            .mc-v2-hero__pill {
                border-radius: 10px;
            }

            .mc-v2-hero__btn i,
            .mc-v2-hero__pill i {
                font-size: 0.75rem;
            }

            .mc-v2-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .mc-v2-stat {
                padding: 12px 10px;
                border-radius: 14px;
                gap: 10px;
            }

            .mc-v2-stat__icon {
                width: 38px;
                height: 38px;
                border-radius: 11px;
                font-size: 0.95rem;
            }

            .mc-v2-stat__val {
                font-size: 1.35rem;
            }

            .mc-v2-stat__lbl {
                font-size: 0.62rem;
                letter-spacing: 0.04em;
                white-space: normal;
                line-height: 1.2;
            }

            .mc-v2-card__head {
                padding: 15px 18px 12px;
            }

            .mc-commande-row {
                flex-wrap: wrap;
            }

            .mc-commande-row__right {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .mc-v2-delivered__head h2 {
                font-size: 0.95rem;
            }

            .mc-quick-link {
                padding: 12px 18px;
            }

            .mc-quick-link__label {
                font-size: 0.82rem;
            }

            .prix-neg-card__list--client .prix-neg-row {
                padding-left: 0;
                padding-right: 0;
            }
        }

        @media (max-width: 580px) {
            .mc-v2-page {
                padding: 12px 10px 72px;
                gap: 14px;
            }

            .mc-v2-shop-promo {
                height: auto;
                min-height: 88px;
                max-height: none;
            }

            .mc-v2-shop-promo__content {
                flex-direction: column;
                align-items: stretch;
                max-height: none;
                padding: 10px 12px;
            }

            .mc-v2-shop-promo__cta {
                width: 100%;
                max-width: none;
            }

            .mc-v2-stats {
                grid-template-columns: 1fr 1fr;
            }

            .mc-v2-card__head {
                padding: 14px 16px 11px;
            }

            .mc-v2-card__head h2 {
                font-size: 0.86rem;
            }

            .mc-v2-card__head h2 i {
                width: 26px;
                height: 26px;
                font-size: 0.74rem;
            }

            .mc-commande-row {
                padding: 11px 16px;
                gap: 9px;
            }

            .mc-commande-row__amount {
                font-size: 0.82rem;
            }

            .mc-v2-delivered__head {
                padding: 14px 16px 12px;
            }

            .mc-v2-delivered__head h2 {
                font-size: 0.92rem;
            }

            .mc-v2-alert {
                padding: 12px 14px;
                gap: 8px;
            }

            .mc-v2-alert__text {
                font-size: 0.8rem;
            }

            .prix-neg-card__list--client {
                padding: 0 12px 12px;
            }

            .mc-v2-products-grid { grid-template-columns: 1fr 1fr; padding: 14px; }
        }

        @media (max-width: 420px) {
            .mc-v2-stats {
                grid-template-columns: 1fr;
            }

            .mc-v2-hero__name {
                font-size: 0.98rem;
            }

            .mc-commande-row__right {
                flex-direction: column;
                align-items: flex-end;
                gap: 4px;
            }

            .mc-v2-products-grid {
                grid-template-columns: 1fr;
            }

            .mc-v2-shop-promo__title {
                font-size: 0.72rem;
            }
        }
    </style>
</head>

<body class="user-page-mon-compte">
    <?php include 'includes/user_nav.php'; ?>

    <div class="mc-v2-page">

        <!-- ===== Bannière courses ===== -->
        <section class="mc-v2-shop-promo" aria-labelledby="mc-v2-shop-promo-title">
            <div class="mc-v2-shop-promo__icons" aria-hidden="true">
                <div class="mc-v2-shop-promo__track">
                    <?php
                    $mc_promo_track = array_merge($mc_promo_images, $mc_promo_images);
                    foreach ($mc_promo_track as $mc_promo_file) :
                        ?>
                    <img class="mc-v2-shop-promo__thumb"
                        src="/upload/<?php echo htmlspecialchars($mc_promo_file, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="" loading="lazy" decoding="async" width="36" height="36">
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mc-v2-shop-promo__veil" aria-hidden="true"></div>
            <div class="mc-v2-shop-promo__content">
                <div class="mc-v2-shop-promo__copy">
                    <p class="mc-v2-shop-promo__eyebrow">Globale Marketplace</p>
                    <h2 class="mc-v2-shop-promo__title" id="mc-v2-shop-promo-title">
                        Faites vos courses sur <span>COLObanes</span>
                    </h2>
                </div>
                <a href="/index.php" class="mc-v2-shop-promo__cta">
                    <i class="fas fa-cart-shopping" aria-hidden="true"></i>
                    Faire mes achats sur COLObanes
                </a>
            </div>
        </section>

        <!-- ===== HERO ===== -->
        <section class="mc-v2-hero" aria-labelledby="mc-v2-title">
            <div class="mc-v2-hero__inner">
                <div class="mc-v2-hero__left">
                    <div class="mc-v2-avatar"><?php echo htmlspecialchars($avatar_initial); ?></div>
                    <div class="mc-v2-hero__info">
                        <p class="mc-v2-hero__eyebrow">Espace client</p>
                        <h1 class="mc-v2-hero__name" id="mc-v2-title">
                            Bonjour, <?php echo htmlspecialchars($prenom_trim ?: $nom_trim); ?> &#x1F44B;
                        </h1>
                        <?php if ($user_email_display !== ''): ?>
                            <p class="mc-v2-hero__email">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($user_email_display); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mc-v2-hero__cta-grid">
                    <div class="mc-v2-hero__actions">
                        <button type="button" id="btn-enable-notifications"
                            class="mc-v2-hero__btn mc-v2-hero__btn--ghost btn-enable-notifications"
                            data-notify-type="user">
                            <i class="fas fa-bell-slash"></i> Notifications
                        </button>
                    </div>
                </div>
            </div>
            <!-- Panel aide notifications -->
            <div id="notify-help-panel" class="mc-v2-notify-panel" hidden aria-live="polite" style="margin-top:16px;">
                <h4><i class="fas fa-circle-info"></i> Autoriser les notifications manuellement</h4>
                <ol>
                    <li>Cliquez sur le <strong>cadenas</strong> (&agrave; gauche de l'adresse)</li>
                    <li><strong>Notifications</strong> &rarr; choisissez <strong>Autoriser</strong></li>
                    <li>Cliquez sur le bouton ci-dessous</li>
                </ol>
                <button type="button" id="btn-notify-continue"
                    class="mc-v2-cta-link" style="margin-top:10px;font-size:.8rem;padding:8px 16px;">
                    <i class="fas fa-check"></i> J'ai autoris&eacute; &mdash; continuer
                </button>
            </div>
        </section>

        <!-- ===== STAT CARDS ===== -->
        <div class="mc-v2-stats" role="region" aria-label="Indicateurs du compte">
            <a href="mes-commandes.php" class="mc-v2-stat mc-v2-stat--orders">
                <div class="mc-v2-stat__icon"><i class="fas fa-bag-shopping"></i></div>
                <div class="mc-v2-stat__body">
                    <span class="mc-v2-stat__val"><?php echo (int) $nb_commandes; ?></span>
                    <span class="mc-v2-stat__lbl">Commandes</span>
                </div>
            </a>
            <a href="/panier.php" class="mc-v2-stat mc-v2-stat--cart">
                <div class="mc-v2-stat__icon"><i class="fas fa-cart-shopping"></i></div>
                <div class="mc-v2-stat__body">
                    <span class="mc-v2-stat__val"><?php echo (int) $nb_panier; ?></span>
                    <span class="mc-v2-stat__lbl">Au panier</span>
                </div>
            </a>
            <a href="/produits.php" class="mc-v2-stat mc-v2-stat--visits">
                <div class="mc-v2-stat__icon"><i class="fas fa-eye"></i></div>
                <div class="mc-v2-stat__body">
                    <span class="mc-v2-stat__val"><?php echo (int) $nb_visites; ?></span>
                    <span class="mc-v2-stat__lbl">Produits visit&eacute;s</span>
                </div>
            </a>
        </div>

        <!-- ===== ALERTE COMMANDE EN COURS ===== -->
        <?php if ($nb_actives > 0): ?>
            <div class="mc-v2-alert">
                <i class="fas fa-truck"></i>
                <span class="mc-v2-alert__text">
                    <strong><?php echo $nb_actives; ?></strong>
                    commande<?php echo $nb_actives > 1 ? 's' : ''; ?> en cours de traitement
                </span>
                <a href="mes-commandes.php" class="mc-v2-alert__btn">
                    <i class="fas fa-arrow-right"></i> Suivre
                </a>
            </div>
        <?php endif; ?>

        <!-- ===== COMMANDES RÉCENTES ===== -->
        <div class="mc-v2-mid mc-v2-mid--orders">
            <?php if (empty($commandes_recentes)): ?>
                <div class="mc-v2-empty">
                    <i class="fas fa-bag-shopping"></i>
                    <p>Aucune commande en cours de traitement.</p>
                    <a href="mes-commandes.php" class="mc-v2-cta-link">
                        <i class="fas fa-list"></i> Voir mes commandes
                    </a>
                </div>
            <?php else: ?>
                <div class="uc-v2-list">
                    <?php
                    require_once __DIR__ . '/../includes/commande_client_card_ui.php';
                    foreach ($commandes_recentes as $cmd) {
                        client_commande_card_render($cmd, $mc_dashboard_cmd_ctx);
                    }
                    ?>
                </div>
                <p class="mc-v2-more-link">
                    <a href="mes-commandes.php"><i class="fas fa-arrow-right"></i> Voir toutes mes commandes</a>
                </p>
            <?php endif; ?>

            <!-- Mes négociations -->
            <div class="mc-v2-card">
                <div class="mc-v2-card__head">
                    <h2><i class="fas fa-handshake"></i> Mes n&eacute;gociations</h2>
                </div>
                <?php if (empty($prix_neg_recentes)): ?>
                    <div class="prix-neg-empty">
                        <i class="fas fa-inbox"></i>
                        <p>Aucune n&eacute;gociation en cours.<br>Proposez un prix sur une fiche produit vendeur.</p>
                    </div>
                <?php else: ?>
                    <div class="uc-v2-list prix-neg-card__list prix-neg-card__list--client">
                        <?php foreach ($prix_neg_recentes as $neg):
                            include __DIR__ . '/../includes/partials/prix_negociation_client_card.php';
                        endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($prix_neg_toutes)): ?>
                <div class="prix-neg-card__footer">
                    <button type="button" class="prix-neg-btn prix-neg-btn--ghost prix-neg-card__more"
                        data-prix-neg-full-open="prixNegClientFullscreen">
                        <i class="fas fa-expand"></i> Voir plus
                    </button>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.mc-v2-mid -->

        <!-- ===== PRODUITS LIVRÉS ===== -->
        <div class="mc-v2-delivered">
            <div class="mc-v2-delivered__head">
                <h2>
                    <i class="fas fa-circle-check"></i>
                    Mes produits livr&eacute;s
                    <?php if ($nb_commandes_recues > 0): ?>
                        <span class="mc-v2-delivered__count"><?php echo (int) $nb_commandes_recues; ?></span>
                    <?php endif; ?>
                </h2>
                <a href="produits-livres.php" class="mc-v2-card__link mc-v2-card__link--green">
                    Voir toutes les commandes re&ccedil;ues <i class="fas fa-chevron-right"></i>
                </a>
            </div>

            <?php if (empty($commandes_recues_recentes)): ?>
                <div class="mc-v2-empty">
                    <i class="fas fa-box-open"></i>
                    <p>Aucun produit livr&eacute; pour le moment.<br>Passez votre premi&egrave;re commande !</p>
                    <a href="/index.php" class="mc-v2-cta-link">
                        <i class="fas fa-store"></i> D&eacute;couvrir les produits
                    </a>
                </div>
            <?php else: ?>
                <div class="uc-v2-list">
                    <?php
                    require_once __DIR__ . '/../includes/commande_client_card_ui.php';
                    foreach ($commandes_recues_recentes as $cmd) {
                        client_commande_card_render($cmd, $mc_dashboard_cmd_ctx);
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /.mc-v2-page -->

    <?php if (!empty($prix_neg_recentes) || !empty($prix_neg_toutes)): ?>
    <?php if (!empty($prix_neg_toutes)): ?>
    <div class="prix-neg-fullscreen" id="prixNegClientFullscreen" aria-hidden="true" hidden>
        <header class="prix-neg-fullscreen__head">
            <h2><i class="fas fa-handshake"></i> Toutes mes n&eacute;gociations</h2>
            <button type="button" class="prix-neg-modal__close" data-prix-neg-full-close aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </header>
        <div class="prix-neg-fullscreen__body">
            <div class="prix-neg-card__list prix-neg-card__list--client uc-v2-list">
                <?php foreach ($prix_neg_toutes as $neg):
                    include __DIR__ . '/../includes/partials/prix_negociation_client_card.php';
                endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="prix-neg-modal prix-neg-modal--propose" id="prixNegClientModal" role="dialog" aria-modal="true"
        aria-labelledby="prixNegClientModalTitle" aria-hidden="true" hidden>
        <div class="prix-neg-modal__backdrop" data-prix-neg-client-close tabindex="-1"></div>
        <div class="prix-neg-modal__panel prix-neg-modal__panel--propose" role="document">
            <div class="prix-neg-modal__head">
                <h2 class="prix-neg-modal__title" id="prixNegClientModalTitle">Proposer un nouveau prix</h2>
                <button type="button" class="prix-neg-modal__close" data-prix-neg-client-close aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="prix-neg-modal__product-row">
                <div class="prix-neg-modal__thumb" id="prixNegClientModalThumb" hidden>
                    <img src="" alt="" id="prixNegClientModalImg" width="88" height="88">
                </div>
                <div class="prix-neg-modal__product-info">
                    <p class="prix-neg-modal__product" id="prixNegClientModalProduct"></p>
                    <p class="prix-neg-modal__ref">Prix catalogue : <strong id="prixNegClientModalRef"></strong></p>
                </div>
            </div>
            <p class="prix-neg-modal__question">Quel prix souhaitez-vous proposer ?</p>
            <form method="POST" action="/user/prix-negociation-action.php" id="prix-neg-client-form">
                <input type="hidden" name="action" value="propose">
                <input type="hidden" name="produit_id" id="prixNegClientProduitId" value="">
                <input type="hidden" name="prix_reference" id="prixNegClientRefInput" value="">
                <input type="hidden" name="redirect" value="/user/mon-compte.php">
                <input type="hidden" name="option_variante_id" id="prixNegClientVarId" value="">
                <input type="hidden" name="option_couleur" id="prixNegClientCouleur" value="">
                <input type="hidden" name="option_poids" id="prixNegClientPoids" value="">
                <input type="hidden" name="option_taille" id="prixNegClientTaille" value="">
                <input type="hidden" name="option_variante_nom" id="prixNegClientVarNom" value="">
                <input type="hidden" name="option_variante_image" id="prixNegClientVarImg" value="">
                <input type="hidden" name="option_surcout_poids" id="prixNegClientSurPoids" value="">
                <input type="hidden" name="option_surcout_taille" id="prixNegClientSurTaille" value="">
                <input type="number" name="prix_propose" id="prixNegClientPropose" class="prix-neg-modal__input"
                    min="1" step="1" required placeholder="Votre nouveau prix en FCFA" inputmode="numeric">
                <div class="prix-neg-modal__actions">
                    <button type="button" class="prix-neg-modal__btn prix-neg-modal__btn--cancel" data-prix-neg-client-close">Annuler</button>
                    <button type="submit" class="prix-neg-modal__btn prix-neg-modal__btn--submit">Envoyer mon offre</button>
                </div>
            </form>
        </div>
    </div>
    <script src="/js/prix-negociation-modal.js<?php echo asset_version_query(); ?>"></script>
    <?php require __DIR__ . '/../includes/partials/platform_share_modal.php'; ?>
    <?php require __DIR__ . '/../includes/partials/uc_gallery_lightbox.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-nav-apps.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/uc-gallery-lightbox.js<?php echo asset_version_query(); ?>"></script>
    <?php endif; ?>

    <script>
    (function () {
        document.addEventListener('click', function (e) {
            var zone = e.target.closest('.uc-v2-card--nav-suivi .uc-v2-card__body, .uc-v2-card--nav-suivi .uc-v2-card__meta-bar');
            if (!zone) {
                return;
            }
            if (e.target.closest('a, button, .uc-btn-open-gallery, input, form, select, textarea')) {
                return;
            }
            var card = zone.closest('.uc-v2-card--nav-suivi');
            var href = card && card.getAttribute('data-suivi-href');
            if (href) {
                window.location.href = href;
            }
        });
    })();
    </script>

    <?php include 'includes/user_footer.php'; ?>
