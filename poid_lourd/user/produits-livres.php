<?php
/**
 * Page des commandes livrées — redesign v2
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

require_once __DIR__ . '/../models/model_commandes.php';

$toutes_commandes = get_commandes_by_user($_SESSION['user_id']);
$toutes_commandes = is_array($toutes_commandes) ? $toutes_commandes : [];

$commandes_livrees = array_values(array_filter($toutes_commandes, fn($c) => in_array($c['statut'], ['livree', 'paye'], true)));
$nb_livrees        = count($commandes_livrees);

// Montant total livré
$montant_total_livre = array_sum(array_column($commandes_livrees, 'montant_total'));

// Stats globales
$nb_actives  = count(array_filter($toutes_commandes, fn($c) => !in_array($c['statut'], ['livree','paye','annulee'])));
$nb_annulees = count(array_filter($toutes_commandes, fn($c) => $c['statut'] === 'annulee'));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Produits livr&eacute;s &mdash; COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mes-commandes.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-produits-livres.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== PRODUITS LIVRÉS v2 ===== */

        .pl-v2-page {
            max-width: 900px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 36px) clamp(14px, 4vw, 24px) 90px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            font-family: var(--font-corps);
        }

        /* ---- Header ---- */
        .pl-v2-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pl-v2-header__left { display: flex; flex-direction: column; gap: 3px; }

        .pl-v2-header__eyebrow {
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #16a34a;
            display: flex; align-items: center; gap: 5px;
        }

        .pl-v2-header__title {
            font-size: clamp(1.3rem, 3vw, 1.75rem);
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres);
            line-height: 1.15;
            letter-spacing: -0.025em;
        }

        .pl-v2-header__actions { display: flex; gap: 9px; align-items: center; flex-wrap: wrap; }

        /* ---- Boutons ---- */
        .pl-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px;
            border-radius: 11px;
            font-size: 0.81rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: var(--font-corps);
            transition: all 0.2s;
            white-space: nowrap;
        }

        .pl-btn--primary { background: var(--couleur-dominante, #3564a6); color: #fff; box-shadow: 0 4px 14px rgba(53,100,166,0.25); }
        .pl-btn--primary:hover { background: var(--bleu-fonce, #2d5690); transform: translateY(-1px); }
        .pl-btn--outline { background: #fff; color: var(--couleur-dominante, #3564a6); border: 1.5px solid rgba(53,100,166,0.22); }
        .pl-btn--outline:hover { background: rgba(53,100,166,0.05); }
        .pl-btn--green   { background: #16a34a; color: #fff; box-shadow: 0 4px 14px rgba(22,163,74,0.25); }
        .pl-btn--green:hover { background: #15803d; transform: translateY(-1px); }

        /* ---- Fil d'Ariane ---- */
        .pl-v2-breadcrumb {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.8rem; color: var(--gris-moyen, #737373); flex-wrap: wrap;
        }

        .pl-v2-breadcrumb a {
            text-decoration: none; color: var(--couleur-dominante, #3564a6);
            font-weight: 600; display: flex; align-items: center; gap: 5px;
        }

        .pl-v2-breadcrumb a:hover { text-decoration: underline; }
        .pl-v2-breadcrumb i { font-size: 0.65rem; color: var(--gris-clair, #a3a3a3); }

        /* ---- Hero banner vert ---- */
        .pl-v2-hero {
            background: linear-gradient(135deg, #166534 0%, #14532d 55%, #052e16 100%);
            border-radius: 20px;
            padding: clamp(20px, 3.5vw, 34px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 44px rgba(22,101,52,0.28);
        }

        .pl-v2-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -40px;
            width: 240px; height: 240px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .pl-v2-hero::after {
            content: '';
            position: absolute;
            bottom: -70px; right: 80px;
            width: 180px; height: 180px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .pl-v2-hero__inner {
            display: flex; align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
        }

        .pl-v2-hero__label {
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: rgba(255,255,255,0.55); margin-bottom: 5px;
        }

        .pl-v2-hero__amount {
            font-size: clamp(1.7rem, 4.5vw, 2.7rem);
            font-weight: 900; color: #fff;
            font-family: var(--font-titres);
            line-height: 1.05; letter-spacing: -0.03em;
        }

        .pl-v2-hero__amount span {
            font-size: 0.4em; font-weight: 600;
            opacity: 0.75; margin-left: 5px;
        }

        .pl-v2-hero__sub {
            font-size: 0.8rem; color: rgba(255,255,255,0.6);
            margin-top: 5px;
        }

        .pl-v2-hero__pills {
            display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px;
        }

        .pl-v2-hero__pill {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 50px; padding: 7px 16px;
            display: flex; align-items: center; gap: 7px;
            color: #fff; font-size: 0.8rem; font-weight: 600;
        }

        .pl-v2-hero__pill i { opacity: 0.8; }
        .pl-v2-hero__pill strong { font-size: 1.06em; }
        .pl-v2-hero__pill--ok   { background: rgba(134,239,172,0.2); border-color: rgba(134,239,172,0.35); }
        .pl-v2-hero__pill--warn { background: rgba(255,193,7,0.2); border-color: rgba(255,193,7,0.3); }
        .pl-v2-hero__pill--neutral { background: rgba(255,255,255,0.1); }

        .pl-v2-hero__cta {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.14);
            border: 1.5px solid rgba(255,255,255,0.22);
            border-radius: 11px; color: #fff;
            font-size: 0.81rem; font-weight: 700;
            text-decoration: none; transition: background 0.2s;
            white-space: nowrap;
        }

        .pl-v2-hero__cta:hover { background: rgba(255,255,255,0.24); }

        /* ---- Stat cards ---- */
        .pl-v2-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 13px;
        }

        .pl-v2-stat {
            background: #fff;
            border-radius: 16px;
            padding: 18px 16px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            display: flex; align-items: center; gap: 14px;
            text-decoration: none; color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .pl-v2-stat:hover { transform: translateY(-3px); box-shadow: 0 8px 22px rgba(53,100,166,0.13); }

        .pl-v2-stat__icon {
            width: 46px; height: 46px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }

        .pl-v2-stat--livrees  .pl-v2-stat__icon { background: rgba(34,197,94,0.12); color: #16a34a; }
        .pl-v2-stat--montant  .pl-v2-stat__icon { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }
        .pl-v2-stat--actives  .pl-v2-stat__icon { background: rgba(255,193,7,0.12); color: #c8960f; }
        .pl-v2-stat--annulees .pl-v2-stat__icon { background: rgba(239,68,68,0.1); color: #b91c1c; }

        .pl-v2-stat__body { display: flex; flex-direction: column; gap: 1px; min-width: 0; }

        .pl-v2-stat__val {
            font-size: 1.6rem; font-weight: 900;
            color: var(--titres); line-height: 1.05;
            font-family: var(--font-titres); overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap;
        }

        .pl-v2-stat__val--sm { font-size: 1.1rem; }

        .pl-v2-stat__lbl {
            font-size: 0.72rem; font-weight: 700;
            color: var(--gris-moyen, #737373);
            text-transform: uppercase; letter-spacing: 0.06em;
        }

        /* ---- Cartes commandes livrées ---- */
        .pl-v2-list { display: flex; flex-direction: column; gap: 14px; }

        .pl-v2-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(34,197,94,0.12);
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .pl-v2-card:hover {
            box-shadow: 0 8px 26px rgba(34,197,94,0.12);
            transform: translateY(-2px);
        }

        /* Top */
        .pl-v2-card__top {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 14px 20px 12px;
            border-bottom: 1px solid rgba(34,197,94,0.08);
            flex-wrap: wrap; gap: 10px;
        }

        .pl-v2-card__ref {
            display: flex; align-items: center; gap: 8px;
        }

        .pl-v2-card__num {
            font-size: 0.88rem; font-weight: 800;
            color: var(--titres); font-family: var(--font-titres);
            display: flex; align-items: center; gap: 6px;
        }

        .pl-v2-card__num i { color: #16a34a; font-size: 0.75rem; }

        .pl-v2-card__date {
            font-size: 0.72rem; color: var(--gris-moyen, #737373);
            display: flex; align-items: center; gap: 4px;
        }

        /* Badge livré */
        .pl-badge-livree {
            font-size: 0.7rem; font-weight: 700;
            padding: 4px 12px; border-radius: 50px;
            background: rgba(34,197,94,0.12); color: #15803d;
            white-space: nowrap;
            display: inline-flex; align-items: center; gap: 5px;
        }

        /* Body */
        .pl-v2-card__body {
            padding: 16px 20px;
            display: flex; gap: 20px;
            flex-wrap: wrap; align-items: flex-start;
        }

        /* Montant */
        .pl-v2-card__amount-block { flex: 1; min-width: 160px; }

        .pl-v2-card__amount {
            font-size: 1.55rem; font-weight: 900;
            color: var(--titres); font-family: var(--font-titres);
            line-height: 1.05; letter-spacing: -0.02em;
        }

        .pl-v2-card__amount small {
            font-size: 0.45em; font-weight: 600;
            color: var(--gris-moyen, #737373); margin-left: 3px;
        }

        .pl-v2-card__amount-sub {
            font-size: 0.75rem; color: var(--gris-moyen, #737373); margin-top: 4px;
        }

        /* Détails livraison */
        .pl-v2-card__details { flex: 2; min-width: 200px; display: flex; flex-direction: column; gap: 7px; }

        .pl-v2-detail-row {
            display: flex; align-items: flex-start; gap: 9px;
            font-size: 0.79rem; color: var(--gris-fonce, #4a4a4a);
        }

        .pl-v2-detail-row__icon {
            width: 24px; height: 24px; border-radius: 7px;
            background: rgba(34,197,94,0.09); color: #16a34a;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.65rem; flex-shrink: 0; margin-top: 1px;
        }

        .pl-v2-detail-row__label {
            font-size: 0.67rem; font-weight: 700;
            color: var(--gris-moyen, #737373);
            text-transform: uppercase; letter-spacing: 0.06em;
            display: block; margin-bottom: 1px;
        }

        .pl-v2-detail-row__val { font-weight: 500; color: var(--titres); }

        /* Date livraison badge */
        .pl-v2-delivery-date {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(34,197,94,0.08);
            border: 1px solid rgba(34,197,94,0.18);
            border-radius: 9px; padding: 6px 12px;
            font-size: 0.78rem; font-weight: 700; color: #15803d;
        }

        /* Footer */
        .pl-v2-card__footer {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 20px;
            background: rgba(34,197,94,0.04);
            border-top: 1px solid rgba(34,197,94,0.08);
            flex-wrap: wrap;
        }

        .pl-card-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 9px;
            font-size: 0.79rem; font-weight: 700;
            border: none; cursor: pointer;
            text-decoration: none; font-family: var(--font-corps);
            transition: all 0.18s;
        }

        .pl-card-btn--view   { background: rgba(34,197,94,0.1); color: #16a34a; }
        .pl-card-btn--view:hover   { background: rgba(34,197,94,0.18); }
        .pl-card-btn--detail { background: rgba(53,100,166,0.08); color: var(--couleur-dominante, #3564a6); }
        .pl-card-btn--detail:hover { background: rgba(53,100,166,0.14); }
        .pl-card-btn--reorder { background: rgba(255,107,53,0.1); color: var(--orange, #FF6B35); }
        .pl-card-btn--reorder:hover { background: rgba(255,107,53,0.18); }

        /* Empty state */
        .pl-v2-empty {
            background: #fff; border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            padding: 60px 24px; text-align: center;
            color: var(--gris-moyen, #737373);
        }

        .pl-v2-empty__icon {
            width: 68px; height: 68px; border-radius: 18px;
            background: rgba(53,100,166,0.07);
            color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; margin: 0 auto 16px;
        }

        .pl-v2-empty h3 { font-size: 1.05rem; font-weight: 700; color: var(--titres); margin-bottom: 7px; }
        .pl-v2-empty p  { font-size: 0.86rem; max-width: 360px; margin: 0 auto 20px; }

        /* Responsive */
        @media (max-width: 600px) {
            .pl-v2-stats { grid-template-columns: 1fr 1fr; }
            .pl-v2-card__body { flex-direction: column; gap: 12px; }
            .pl-v2-stat__val--sm { font-size: 0.9rem; }
        }
    </style>
</head>

<body class="user-page-produits-livres">
    <?php include 'includes/user_nav.php'; ?>

    <div class="pl-v2-page">

        <!-- ===== HEADER ===== -->
        <header class="pl-v2-header">
            <div class="pl-v2-header__left">
                <p class="pl-v2-header__eyebrow">
                    <i class="fas fa-circle-check"></i> Historique livr&eacute;
                </p>
                <h1 class="pl-v2-header__title">Produits livr&eacute;s</h1>
            </div>
        </header>

        <!-- Fil d'Ariane -->
        <nav class="pl-v2-breadcrumb" aria-label="Fil d'Ariane">
            <a href="mon-compte.php"><i class="fas fa-house"></i> Mon compte</a>
            <i class="fas fa-chevron-right"></i>
            <a href="mes-commandes.php">Mes commandes</a>
            <i class="fas fa-chevron-right"></i>
            <span>Produits livr&eacute;s</span>
        </nav>

        <!-- ===== HERO VERT ===== -->
        <div class="pl-v2-hero">
            <div class="pl-v2-hero__inner">
                <div>
                    <p class="pl-v2-hero__label">Total des achats livr&eacute;s</p>
                    <div class="pl-v2-hero__amount">
                        <?php echo number_format($montant_total_livre, 0, ',', ' '); ?><span>FCFA</span>
                    </div>
                    <p class="pl-v2-hero__sub">
                        <?php echo $nb_livrees; ?> commande<?php echo $nb_livrees > 1 ? 's' : ''; ?> re&ccedil;ue<?php echo $nb_livrees > 1 ? 's' : ''; ?> avec succ&egrave;s
                    </p>
                </div>
                <a href="/produits.php" class="pl-v2-hero__cta">
                    <i class="fas fa-shopping-basket"></i> Commander &agrave; nouveau
                </a>
            </div>
            <div class="pl-v2-hero__pills">
                <div class="pl-v2-hero__pill pl-v2-hero__pill--ok">
                    <i class="fas fa-circle-check"></i>
                    <span><strong><?php echo $nb_livrees; ?></strong> livr&eacute;e<?php echo $nb_livrees > 1 ? 's' : ''; ?></span>
                </div>
                <?php if ($nb_actives > 0): ?>
                    <div class="pl-v2-hero__pill pl-v2-hero__pill--warn">
                        <i class="fas fa-clock"></i>
                        <span><strong><?php echo $nb_actives; ?></strong> en cours</span>
                    </div>
                <?php endif; ?>
                <?php if ($nb_annulees > 0): ?>
                    <div class="pl-v2-hero__pill pl-v2-hero__pill--neutral">
                        <i class="fas fa-ban"></i>
                        <span><strong><?php echo $nb_annulees; ?></strong> annul&eacute;e<?php echo $nb_annulees > 1 ? 's' : ''; ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== STAT CARDS ===== -->
        <div class="pl-v2-stats">
            <div class="pl-v2-stat pl-v2-stat--livrees">
                <div class="pl-v2-stat__icon"><i class="fas fa-truck-fast"></i></div>
                <div class="pl-v2-stat__body">
                    <span class="pl-v2-stat__val"><?php echo $nb_livrees; ?></span>
                    <span class="pl-v2-stat__lbl">Re&ccedil;ues</span>
                </div>
            </div>
            <div class="pl-v2-stat pl-v2-stat--montant">
                <div class="pl-v2-stat__icon"><i class="fas fa-coins"></i></div>
                <div class="pl-v2-stat__body">
                    <span class="pl-v2-stat__val pl-v2-stat__val--sm">
                        <?php echo number_format($montant_total_livre, 0, ',', ' '); ?>&nbsp;F
                    </span>
                    <span class="pl-v2-stat__lbl">D&eacute;pens&eacute;</span>
                </div>
            </div>
            <?php if ($nb_actives > 0): ?>
                <a href="mes-commandes.php" class="pl-v2-stat pl-v2-stat--actives">
                    <div class="pl-v2-stat__icon"><i class="fas fa-clock"></i></div>
                    <div class="pl-v2-stat__body">
                        <span class="pl-v2-stat__val"><?php echo $nb_actives; ?></span>
                        <span class="pl-v2-stat__lbl">En cours</span>
                    </div>
                </a>
            <?php endif; ?>
            <?php if ($nb_annulees > 0): ?>
                <a href="commandes-annulees.php" class="pl-v2-stat pl-v2-stat--annulees">
                    <div class="pl-v2-stat__icon"><i class="fas fa-ban"></i></div>
                    <div class="pl-v2-stat__body">
                        <span class="pl-v2-stat__val"><?php echo $nb_annulees; ?></span>
                        <span class="pl-v2-stat__lbl">Annul&eacute;es</span>
                    </div>
                </a>
            <?php endif; ?>
        </div>

        <!-- ===== LISTE COMMANDES LIVRÉES ===== -->
        <?php if (empty($commandes_livrees)): ?>
            <div class="pl-v2-empty">
                <div class="pl-v2-empty__icon"><i class="fas fa-parachute-box"></i></div>
                <h3>Aucune livraison pour le moment</h3>
                <p>
                    Vos commandes livr&eacute;es appara&icirc;tront ici d&egrave;s que vous aurez re&ccedil;u votre premier colis.
                </p>
                <a href="mes-commandes.php" class="pl-btn pl-btn--outline" style="margin-right:8px;">
                    <i class="fas fa-arrow-left"></i> Mes commandes
                </a>
                <a href="/produits.php" class="pl-btn pl-btn--green">
                    <i class="fas fa-store"></i> D&eacute;couvrir les produits
                </a>
            </div>
        <?php else: ?>

            <div class="pl-v2-list">
                <?php foreach ($commandes_livrees as $commande):
                    $statut   = $commande['statut'] ?? 'livree';
                    $telephone = htmlspecialchars($commande['telephone_livraison'] ?? '');
                    $badge_label = $statut === 'paye' ? 'Re&ccedil;ue &amp; pay&eacute;e' : 'Livr&eacute;e';
                    $badge_icon  = $statut === 'paye' ? 'fa-circle-check' : 'fa-check';
                ?>
                    <article class="pl-v2-card">

                        <!-- Top -->
                        <div class="pl-v2-card__top">
                            <div class="pl-v2-card__ref">
                                <span class="pl-v2-card__num">
                                    <i class="fas fa-hashtag"></i>
                                    <?php echo htmlspecialchars($commande['numero_commande']); ?>
                                </span>
                            </div>
                            <span class="pl-badge-livree">
                                <i class="fas <?php echo $badge_icon; ?>" style="font-size:.75em;"></i>
                                <?php echo $badge_label; ?>
                            </span>
                        </div>

                        <!-- Body -->
                        <div class="pl-v2-card__body">
                            <!-- Montant -->
                            <div class="pl-v2-card__amount-block">
                                <div class="pl-v2-card__amount">
                                    <?php echo number_format((float)$commande['montant_total'], 0, ',', ' '); ?><small>FCFA</small>
                                </div>
                                <div class="pl-v2-card__amount-sub">Montant total</div>

                                <?php if (!empty($commande['date_livraison'])): ?>
                                    <div class="pl-v2-delivery-date" style="margin-top:10px;">
                                        <i class="fas fa-truck-fast"></i>
                                        Livr&eacute; le <?php echo date('d/m/Y', strtotime($commande['date_livraison'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Infos livraison -->
                            <div class="pl-v2-card__details">
                                <?php if (!empty($telephone)): ?>
                                    <div class="pl-v2-detail-row">
                                        <div class="pl-v2-detail-row__icon"><i class="fas fa-phone"></i></div>
                                        <div>
                                            <span class="pl-v2-detail-row__label">T&eacute;l&eacute;phone</span>
                                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $telephone)); ?>"
                                                class="pl-v2-detail-row__val" style="text-decoration:none;color:inherit;">
                                                <?php echo $telephone; ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($commande['notes'])): ?>
                                    <div class="pl-v2-detail-row">
                                        <div class="pl-v2-detail-row__icon"><i class="fas fa-note-sticky"></i></div>
                                        <div>
                                            <span class="pl-v2-detail-row__label">Notes</span>
                                            <span class="pl-v2-detail-row__val">
                                                <?php echo htmlspecialchars(substr($commande['notes'], 0, 80)); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Footer actions -->
                        <div class="pl-v2-card__footer">
                            <a href="commande-categorie.php?commande_id=<?php echo (int)$commande['id']; ?>"
                                class="pl-card-btn pl-card-btn--view">
                                <i class="fas fa-box-open"></i> Voir les produits re&ccedil;us
                            </a>
                            <a href="commande-categorie.php?commande_id=<?php echo (int)$commande['id']; ?>"
                                class="pl-card-btn pl-card-btn--detail" style="margin-left:auto;">
                                <i class="fas fa-eye"></i> D&eacute;tail
                            </a>
                        </div>

                    </article>
                <?php endforeach; ?>
            </div>

            <!-- CTA bas de page -->
            <div style="text-align:center;padding:8px 0 4px;">
                <a href="/produits.php" class="pl-btn pl-btn--green">
                    <i class="fas fa-rotate-right"></i> Commander &agrave; nouveau
                </a>
            </div>

        <?php endif; ?>

    </div><!-- /.pl-v2-page -->

    <?php include 'includes/user_footer.php'; ?>
</body>
</html>
