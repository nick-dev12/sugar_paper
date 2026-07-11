<?php
/**
 * Page de liste des commandes annulées — redesign v2
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

$success_message = '';
$error_message   = '';

// ---- Recommander ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recommander'])) {
    $commande_id = (int) ($_POST['commande_id'] ?? 0);
    if ($commande_id > 0) {
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);
        if ($commande && $commande['statut'] === 'annulee') {
            require_once __DIR__ . '/../models/model_panier.php';
            require_once __DIR__ . '/../models/model_produits.php';
            $produits_commande = get_commande_produits($commande_id);
            if (!empty($produits_commande)) {
                $added_count = 0;
                foreach ($produits_commande as $produit) {
                    $produit_info = get_produit_by_id($produit['produit_id']);
                    if ($produit_info && $produit_info['statut'] === 'actif' && $produit_info['stock'] > 0) {
                        $panier_existant = is_in_panier($_SESSION['user_id'], $produit['produit_id']);
                        if ($panier_existant) {
                            $new_q = min($panier_existant['quantite'] + $produit['quantite'], $produit_info['stock']);
                            if (update_panier_quantite($panier_existant['id'], $new_q)) $added_count++;
                        } else {
                            $q = min($produit['quantite'], $produit_info['stock']);
                            if (add_to_panier($_SESSION['user_id'], $produit['produit_id'], $q)) $added_count++;
                        }
                    }
                }
                if ($added_count > 0) { header('Location: /panier.php?recommande=1&count=' . $added_count); exit; }
                $error_message = 'Aucun produit disponible &agrave; recommander.';
            } else {
                $error_message = 'Aucun produit trouv&eacute; dans cette commande.';
            }
        } else {
            $error_message = 'Cette commande ne peut pas &ecirc;tre recommand&eacute;e.';
        }
    }
}

require_once __DIR__ . '/../includes/flash_toast.php';
if (!empty($error_message)) {
    flash_toast_queue_page('error', $error_message);
}

// ---- Données ----
$toutes_commandes   = get_commandes_by_user($_SESSION['user_id']);
$toutes_commandes   = is_array($toutes_commandes) ? $toutes_commandes : [];
$commandes_annulees = array_values(array_filter($toutes_commandes, fn($c) => $c['statut'] === 'annulee'));
$nb_annulees        = count($commandes_annulees);

// Stats globales pour les pilules
$nb_actives = count(array_filter($toutes_commandes, fn($c) => !in_array($c['statut'], ['livree','paye','annulee'])));
$nb_livrees = count(array_filter($toutes_commandes, fn($c) => in_array($c['statut'], ['livree','paye'])));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Commandes annul&eacute;es &mdash; COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mes-commandes.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-commandes-annulees.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== COMMANDES ANNULÉES v2 ===== */

        .ca-v2-page {
            max-width: 900px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 36px) clamp(14px, 4vw, 24px) 90px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            font-family: var(--font-corps);
        }

        /* ---- Header ---- */
        .ca-v2-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ca-v2-header__left { display: flex; flex-direction: column; gap: 3px; }

        .ca-v2-header__eyebrow {
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #b91c1c;
            display: flex; align-items: center; gap: 5px;
        }

        .ca-v2-header__title {
            font-size: clamp(1.3rem, 3vw, 1.75rem);
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres);
            line-height: 1.15;
            letter-spacing: -0.025em;
        }

        .ca-v2-header__actions { display: flex; gap: 9px; align-items: center; flex-wrap: wrap; }

        /* ---- Boutons ---- */
        .ca-btn {
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

        .ca-btn--primary  { background: var(--couleur-dominante, #3564a6); color: #fff; box-shadow: 0 4px 14px rgba(53,100,166,0.25); }
        .ca-btn--primary:hover  { background: var(--bleu-fonce, #2d5690); transform: translateY(-1px); }
        .ca-btn--outline  { background: #fff; color: var(--couleur-dominante, #3564a6); border: 1.5px solid rgba(53,100,166,0.22); }
        .ca-btn--outline:hover  { background: rgba(53,100,166,0.05); }

        /* ---- Notifications ---- */
        .ca-v2-notif {
            padding: 13px 18px;
            border-radius: 13px;
            display: flex; align-items: center; gap: 11px;
            font-size: 0.86rem; font-weight: 600;
        }

        .ca-v2-notif--success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.25); color: #15803d; }
        .ca-v2-notif--error   { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #b91c1c; }

        /* ---- Hero banner ---- */
        .ca-v2-hero {
            background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 50%, #450a0a 100%);
            border-radius: 20px;
            padding: clamp(20px, 3.5vw, 34px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 44px rgba(153,27,27,0.28);
        }

        .ca-v2-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -40px;
            width: 230px; height: 230px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .ca-v2-hero::after {
            content: '';
            position: absolute;
            bottom: -70px; right: 80px;
            width: 170px; height: 170px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .ca-v2-hero__inner {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .ca-v2-hero__label {
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255,255,255,0.55);
            margin-bottom: 5px;
        }

        .ca-v2-hero__count {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 900;
            color: #fff;
            font-family: var(--font-titres);
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .ca-v2-hero__count span {
            font-size: 0.38em;
            font-weight: 600;
            opacity: 0.7;
            margin-left: 4px;
        }

        .ca-v2-hero__sub {
            font-size: 0.82rem;
            color: rgba(255,255,255,0.65);
            margin-top: 6px;
        }

        .ca-v2-hero__pills {
            display: flex; gap: 10px; flex-wrap: wrap;
            margin-top: 16px;
        }

        .ca-v2-hero__pill {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 50px;
            padding: 7px 15px;
            display: flex; align-items: center; gap: 7px;
            color: #fff; font-size: 0.79rem; font-weight: 600;
        }

        .ca-v2-hero__pill i { opacity: 0.8; }
        .ca-v2-hero__pill--warn { background: rgba(255,193,7,0.2); border-color: rgba(255,193,7,0.3); }
        .ca-v2-hero__pill--ok   { background: rgba(34,197,94,0.18); border-color: rgba(34,197,94,0.28); }

        .ca-v2-hero__cta {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.13);
            border: 1.5px solid rgba(255,255,255,0.22);
            border-radius: 11px;
            color: #fff; font-size: 0.81rem; font-weight: 700;
            text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .ca-v2-hero__cta:hover { background: rgba(255,255,255,0.22); }

        /* ---- Fil d'Ariane ---- */
        .ca-v2-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--gris-moyen, #737373);
            flex-wrap: wrap;
        }

        .ca-v2-breadcrumb a {
            text-decoration: none;
            color: var(--couleur-dominante, #3564a6);
            font-weight: 600;
            display: flex; align-items: center; gap: 5px;
        }

        .ca-v2-breadcrumb a:hover { text-decoration: underline; }
        .ca-v2-breadcrumb i { font-size: 0.65rem; color: var(--gris-clair, #a3a3a3); }

        /* ---- Grille commandes ---- */
        .ca-v2-list { display: flex; flex-direction: column; gap: 14px; }

        /* ---- Card commande ---- */
        .ca-v2-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(239,68,68,0.1);
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .ca-v2-card:hover {
            box-shadow: 0 8px 26px rgba(239,68,68,0.1);
            transform: translateY(-2px);
        }

        /* Top */
        .ca-v2-card__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px 12px;
            border-bottom: 1px solid rgba(239,68,68,0.07);
            flex-wrap: wrap;
            gap: 10px;
        }

        .ca-v2-card__ref {
            display: flex; align-items: center; gap: 8px;
        }

        .ca-v2-card__num {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres);
            display: flex; align-items: center; gap: 6px;
        }

        .ca-v2-card__num i { color: #b91c1c; font-size: 0.75rem; }

        .ca-v2-card__date {
            font-size: 0.72rem;
            color: var(--gris-moyen, #737373);
            display: flex; align-items: center; gap: 4px;
        }

        /* Badge annulée */
        .ca-badge-annulee {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            background: rgba(239,68,68,0.1);
            color: #b91c1c;
            white-space: nowrap;
            display: inline-flex; align-items: center; gap: 5px;
        }

        /* Body */
        .ca-v2-card__body {
            padding: 16px 20px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-start;
        }

        /* Montant */
        .ca-v2-card__amount-block { flex: 1; min-width: 160px; }

        .ca-v2-card__amount {
            font-size: 1.45rem;
            font-weight: 900;
            color: var(--titres);
            font-family: var(--font-titres);
            line-height: 1.05;
            letter-spacing: -0.02em;
        }

        .ca-v2-card__amount small {
            font-size: 0.47em;
            font-weight: 600;
            color: var(--gris-moyen, #737373);
            margin-left: 3px;
        }

        /* Infos livraison */
        .ca-v2-card__details { flex: 2; min-width: 200px; display: flex; flex-direction: column; gap: 6px; }

        .ca-v2-detail-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.79rem;
            color: var(--gris-fonce, #4a4a4a);
        }

        .ca-v2-detail-row__icon {
            width: 22px; height: 22px;
            border-radius: 7px;
            background: rgba(239,68,68,0.07);
            color: #b91c1c;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.65rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .ca-v2-detail-row__label {
            font-size: 0.67rem;
            font-weight: 700;
            color: var(--gris-moyen, #737373);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: block;
            margin-bottom: 1px;
        }

        .ca-v2-detail-row__val { font-weight: 500; color: var(--titres); }

        /* Encart "Produits disponibles" */
        .ca-v2-card__reorder-info {
            background: rgba(255,107,53,0.05);
            border: 1px dashed rgba(255,107,53,0.25);
            border-radius: 11px;
            padding: 10px 14px;
            font-size: 0.78rem;
            color: var(--gris-fonce, #4a4a4a);
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0 20px 0;
        }

        .ca-v2-card__reorder-info i { color: var(--orange, #FF6B35); font-size: 0.85rem; }

        /* Footer */
        .ca-v2-card__footer {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: rgba(239,68,68,0.03);
            border-top: 1px solid rgba(239,68,68,0.07);
            flex-wrap: wrap;
        }

        .ca-card-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            border-radius: 9px;
            font-size: 0.79rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-family: var(--font-corps);
            transition: all 0.18s;
        }

        .ca-card-btn--view    { background: rgba(53,100,166,0.08); color: var(--couleur-dominante, #3564a6); }
        .ca-card-btn--view:hover    { background: rgba(53,100,166,0.15); }

        .ca-card-btn--reorder { background: rgba(255,107,53,0.1); color: var(--orange, #FF6B35); }
        .ca-card-btn--reorder:hover { background: rgba(255,107,53,0.18); }

        /* Empty state */
        .ca-v2-empty {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            padding: 60px 24px;
            text-align: center;
            color: var(--gris-moyen, #737373);
        }

        .ca-v2-empty__icon {
            width: 68px; height: 68px;
            border-radius: 18px;
            background: rgba(34,197,94,0.08);
            color: #16a34a;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 16px;
        }

        .ca-v2-empty h3 { font-size: 1.05rem; font-weight: 700; color: var(--titres); margin-bottom: 6px; }
        .ca-v2-empty p  { font-size: 0.86rem; max-width: 350px; margin: 0 auto 20px; }

        /* Responsive */
        @media (max-width: 580px) {
            .ca-v2-card__body { flex-direction: column; gap: 12px; }
        }
    </style>
</head>

<body class="user-page-commandes-annulees">
    <?php include 'includes/user_nav.php'; ?>

    <div class="ca-v2-page">

        <!-- ===== HEADER ===== -->
        <header class="ca-v2-header">
            <div class="ca-v2-header__left">
                <p class="ca-v2-header__eyebrow">
                    <i class="fas fa-ban"></i> Historique
                </p>
                <h1 class="ca-v2-header__title">Commandes annul&eacute;es</h1>
            </div>
        </header>

        <!-- Fil d'Ariane -->
        <nav class="ca-v2-breadcrumb" aria-label="Fil d'Ariane">
            <a href="mon-compte.php"><i class="fas fa-house"></i> Mon compte</a>
            <i class="fas fa-chevron-right"></i>
            <a href="mes-commandes.php">Mes commandes</a>
            <i class="fas fa-chevron-right"></i>
            <span>Annul&eacute;es</span>
        </nav>

        <!-- ===== HERO ===== -->
        <div class="ca-v2-hero">
            <div class="ca-v2-hero__inner">
                <div>
                    <p class="ca-v2-hero__label">Commandes annul&eacute;es &mdash; Total</p>
                    <div class="ca-v2-hero__count">
                        <?php echo $nb_annulees; ?><span>annul&eacute;e<?php echo $nb_annulees > 1 ? 's' : ''; ?></span>
                    </div>
                    <?php if ($nb_annulees > 0): ?>
                        <p class="ca-v2-hero__sub">
                            Vous pouvez recommander n'importe laquelle d'un seul clic.
                        </p>
                    <?php else: ?>
                        <p class="ca-v2-hero__sub">Aucune commande annul&eacute;e. C'est une bonne nouvelle !</p>
                    <?php endif; ?>
                </div>
                <a href="/produits.php" class="ca-v2-hero__cta">
                    <i class="fas fa-shopping-basket"></i> Recommander
                </a>
            </div>
            <div class="ca-v2-hero__pills">
                <?php if ($nb_actives > 0): ?>
                    <div class="ca-v2-hero__pill ca-v2-hero__pill--warn">
                        <i class="fas fa-clock"></i>
                        <span><strong><?php echo $nb_actives; ?></strong> en cours</span>
                    </div>
                <?php endif; ?>
                <div class="ca-v2-hero__pill ca-v2-hero__pill--ok">
                    <i class="fas fa-circle-check"></i>
                    <span><strong><?php echo $nb_livrees; ?></strong> livr&eacute;e<?php echo $nb_livrees > 1 ? 's' : ''; ?></span>
                </div>
                <div class="ca-v2-hero__pill" style="background:rgba(239,68,68,0.18);border-color:rgba(239,68,68,0.3);">
                    <i class="fas fa-ban"></i>
                    <span><strong><?php echo $nb_annulees; ?></strong> annul&eacute;e<?php echo $nb_annulees > 1 ? 's' : ''; ?></span>
                </div>
            </div>
        </div>

        <!-- ===== COMMANDES ===== -->
        <?php if (empty($commandes_annulees)): ?>

            <div class="ca-v2-empty">
                <div class="ca-v2-empty__icon">
                    <i class="fas fa-circle-check"></i>
                </div>
                <h3>Aucune commande annul&eacute;e</h3>
                <p>Toutes vos commandes suivent leur cours normalement. Retrouvez-les dans l'espace commandes.</p>
                <a href="mes-commandes.php" class="ca-btn ca-btn--primary">
                    <i class="fas fa-bag-shopping"></i> Voir mes commandes
                </a>
            </div>

        <?php else: ?>

            <div class="ca-v2-list">
                <?php foreach ($commandes_annulees as $commande):
                    $date_fmt = isset($commande['date_commande'])
                        ? date('d/m/Y &agrave; H:i', strtotime($commande['date_commande']))
                        : '&mdash;';
                    $adresse  = htmlspecialchars(substr((string)($commande['adresse_livraison'] ?? ''), 0, 80));
                    $adresse .= strlen((string)($commande['adresse_livraison'] ?? '')) > 80 ? '&hellip;' : '';
                    $telephone = htmlspecialchars($commande['telephone_livraison'] ?? '');
                ?>
                    <article class="ca-v2-card">

                        <!-- Top -->
                        <div class="ca-v2-card__top">
                            <div class="ca-v2-card__ref">
                                <span class="ca-v2-card__num">
                                    <i class="fas fa-hashtag"></i>
                                    <?php echo htmlspecialchars($commande['numero_commande']); ?>
                                </span>
                                <span class="ca-v2-card__date">
                                    <i class="far fa-clock"></i> <?php echo $date_fmt; ?>
                                </span>
                            </div>
                            <span class="ca-badge-annulee">
                                <i class="fas fa-ban" style="font-size:.75em;"></i> Annul&eacute;e
                            </span>
                        </div>

                        <!-- Body -->
                        <div class="ca-v2-card__body">
                            <div class="ca-v2-card__amount-block">
                                <div class="ca-v2-card__amount">
                                    <?php echo number_format((float) $commande['montant_total'], 0, ',', ' '); ?><small>FCFA</small>
                                </div>
                                <div style="font-size:.75rem;color:var(--gris-moyen);margin-top:5px;">
                                    Montant de la commande
                                </div>
                            </div>

                            <div class="ca-v2-card__details">
                                <?php if (!empty($adresse)): ?>
                                    <div class="ca-v2-detail-row">
                                        <div class="ca-v2-detail-row__icon"><i class="fas fa-map-marker-alt"></i></div>
                                        <div>
                                            <span class="ca-v2-detail-row__label">Adresse</span>
                                            <span class="ca-v2-detail-row__val"><?php echo $adresse; ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($telephone)): ?>
                                    <div class="ca-v2-detail-row">
                                        <div class="ca-v2-detail-row__icon"><i class="fas fa-phone"></i></div>
                                        <div>
                                            <span class="ca-v2-detail-row__label">T&eacute;l&eacute;phone</span>
                                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $telephone)); ?>"
                                                class="ca-v2-detail-row__val" style="text-decoration:none;color:inherit;">
                                                <?php echo $telephone; ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($commande['notes'])): ?>
                                    <div class="ca-v2-detail-row">
                                        <div class="ca-v2-detail-row__icon"><i class="fas fa-note-sticky"></i></div>
                                        <div>
                                            <span class="ca-v2-detail-row__label">Notes</span>
                                            <span class="ca-v2-detail-row__val"><?php echo htmlspecialchars(substr($commande['notes'], 0, 80)); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Encart recommander -->
                        <div class="ca-v2-card__reorder-info">
                            <i class="fas fa-rotate-right"></i>
                            <span>Vous pouvez recommander les articles de cette commande en un clic.</span>
                        </div>

                        <!-- Footer actions -->
                        <div class="ca-v2-card__footer">
                            <a href="commande-categorie.php?commande_id=<?php echo (int) $commande['id']; ?>"
                                class="ca-card-btn ca-card-btn--view">
                                <i class="fas fa-eye"></i> Voir les produits
                            </a>
                            <form method="post" action="" style="display:inline;">
                                <input type="hidden" name="commande_id" value="<?php echo (int) $commande['id']; ?>">
                                <button type="submit" name="recommander" class="ca-card-btn ca-card-btn--reorder">
                                    <i class="fas fa-rotate-right"></i> Recommander
                                </button>
                            </form>
                        </div>

                    </article>
                <?php endforeach; ?>
            </div>

            <!-- CTA bas de page -->
            <div style="text-align:center;padding:8px 0 4px;">
                <a href="mes-commandes.php" class="ca-btn ca-btn--outline">
                    <i class="fas fa-arrow-left"></i> Retour &agrave; mes commandes
                </a>
            </div>

        <?php endif; ?>

    </div><!-- /.ca-v2-page -->

    <?php include 'includes/user_footer.php'; ?>
</body>
</html>
