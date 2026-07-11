<?php
/**
 * Page de liste des commandes — Admin / Vendeur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_zones_livraison.php';
require_once __DIR__ . '/includes/commandes_v2_helpers.php';

$vf_cmd = admin_vendeur_filter_id();
$toutes_commandes = get_all_commandes(null, $vf_cmd);
$zones_livraison = get_all_zones_livraison('actif', $vf_cmd !== null ? $vf_cmd : false);

$show_modal_commande_manuelle = isset($_GET['modal']) && $_GET['modal'] === 'commande_manuelle';
$commande_manuelle_erreur = $_SESSION['commande_manuelle_erreur'] ?? null;
$commande_manuelle_post   = $_SESSION['commande_manuelle_post'] ?? null;
if (isset($_SESSION['commande_manuelle_erreur'])) unset($_SESSION['commande_manuelle_erreur']);
if (isset($_SESSION['commande_manuelle_post']))   unset($_SESSION['commande_manuelle_post']);

// Onglet actif
$tab_actif = $_GET['statut'] ?? 'actives';

// Commandes à traiter (non livrées / payées / annulées)
$commandes_actives = array_filter($toutes_commandes ?: [], function ($c) {
    return !in_array($c['statut'], ['livree', 'paye', 'annulee']);
});
$commandes_actives = array_values($commandes_actives);

// Comptes par statut
$nb_en_attente      = count_commandes_by_statut('en_attente', $vf_cmd);
$nb_prise           = count_commandes_by_statut('prise_en_charge', $vf_cmd);
$nb_livraison       = count_commandes_by_statut('livraison_en_cours', $vf_cmd);
$nb_livrees         = count_commandes_by_statut('livree', $vf_cmd) + count_commandes_by_statut('paye', $vf_cmd);
$nb_annulees        = count_commandes_by_statut('annulee', $vf_cmd);
$total_commandes    = count_commandes_by_statut(null, $vf_cmd);

// Montant total des commandes actives
$montant_total_a_traiter = array_sum(array_column($commandes_actives, 'montant_total'));

// Commandes affichées selon l'onglet
if ($tab_actif === 'en_attente') {
    $commandes_affichees = array_values(array_filter($commandes_actives, fn($c) => $c['statut'] === 'en_attente'));
} elseif ($tab_actif === 'prise_en_charge') {
    $commandes_affichees = array_values(array_filter($commandes_actives, fn($c) => $c['statut'] === 'prise_en_charge'));
} elseif ($tab_actif === 'livraison_en_cours') {
    $commandes_affichees = array_values(array_filter($commandes_actives, fn($c) => $c['statut'] === 'livraison_en_cours'));
} else {
    $commandes_affichees = $commandes_actives;
}

// Helpers label/couleur statut
function statut_label_cmd($s) {
    $map = [
        'en_attente'        => 'En attente',
        'prise_en_charge'   => 'Pris en charge',
        'livraison_en_cours' => 'En livraison',
        'livree'            => 'Livr&eacute;e',
        'paye'              => 'Pay&eacute;e',
        'annulee'           => 'Annul&eacute;e',
    ];
    return $map[$s] ?? ucfirst(str_replace('_', ' ', $s));
}

function statut_class_cmd($s) {
    $map = [
        'en_attente'        => 'cmd-badge--attente',
        'prise_en_charge'   => 'cmd-badge--prise',
        'livraison_en_cours' => 'cmd-badge--livraison',
        'livree'            => 'cmd-badge--livree',
        'paye'              => 'cmd-badge--paye',
        'annulee'           => 'cmd-badge--annulee',
    ];
    return $map[$s] ?? 'cmd-badge--attente';
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes &mdash; Administration COLObanes</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-index.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-card-uc.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        /* ===== REDESIGN COMMANDES v2 ===== */

        .cmd-v2-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: clamp(16px, 3vw, 32px);
            display: flex;
            flex-direction: column;
            gap: 24px;
            font-family: var(--font-corps);
        }

        /* ---- Header ---- */
        .cmd-v2-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }

        .cmd-v2-header__left { display: flex; flex-direction: column; gap: 3px; }

        .cmd-v2-header__eyebrow {
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--bleu-clair, #4a7ab8);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cmd-v2-header__title {
            font-size: clamp(1.35rem, 3vw, 1.85rem);
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres);
            line-height: 1.15;
            letter-spacing: -0.025em;
        }

        .cmd-v2-header__actions {
            display: flex;
            gap: 9px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* ---- Boutons ---- */
        .cmd-v2-btn {
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
            transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
            white-space: nowrap;
        }

        .cmd-v2-btn--primary {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
            box-shadow: 0 4px 14px rgba(53,100,166,0.28);
        }

        .cmd-v2-btn--primary:hover {
            background: var(--bleu-fonce, #2d5690);
            transform: translateY(-1px);
        }

        .cmd-v2-btn--outline {
            background: #fff;
            color: var(--couleur-dominante, #3564a6);
            border: 1.5px solid rgba(53,100,166,0.22);
        }

        .cmd-v2-btn--outline:hover { background: rgba(53,100,166,0.05); }

        .cmd-v2-btn--danger {
            background: #fff;
            color: #b91c1c;
            border: 1.5px solid rgba(239,68,68,0.22);
        }

        .cmd-v2-btn--danger:hover { background: rgba(239,68,68,0.06); }

        /* ---- Notification ---- */
        .cmd-v2-notif {
            padding: 13px 20px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            gap: 11px;
            font-size: 0.87rem;
            font-weight: 600;
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.25);
            color: #15803d;
        }

        /* ---- Hero banner ---- */
        .cmd-v2-hero {
            background: var(--couleur-dominante, #3564a6);
            border-radius: 22px;
            padding: clamp(22px, 3vw, 36px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 40px color-mix(in srgb, var(--couleur-dominante, #3564a6) 34%, transparent);
        }

        .cmd-v2-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -40px;
            width: 260px; height: 260px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        .cmd-v2-hero::after {
            content: '';
            position: absolute;
            bottom: -70px; right: 100px;
            width: 190px; height: 190px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }

        .cmd-v2-hero__inner {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .cmd-v2-hero__label {
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255,255,255,0.6);
            margin-bottom: 6px;
        }

        .cmd-v2-hero__amount-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cmd-v2-hero__amount {
            font-size: clamp(1.8rem, 4.5vw, 2.9rem);
            font-weight: 900;
            color: #fff;
            line-height: 1.05;
            letter-spacing: -0.03em;
            font-family: var(--font-titres);
        }

        .cmd-v2-hero__amount span {
            font-size: 0.42em;
            font-weight: 600;
            opacity: 0.75;
            margin-left: 5px;
        }

        .cmd-v2-hero__pill {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50px;
            padding: 7px 16px;
            display: flex;
            align-items: center;
            gap: 7px;
            color: #fff;
            font-size: 0.81rem;
            font-weight: 600;
        }

        .cmd-v2-hero__pill i { opacity: 0.8; font-size: 0.85rem; }
        .cmd-v2-hero__pill strong { font-size: 1.06em; }
        .cmd-v2-hero__pill--warn { background: rgba(255,193,7,0.2); border-color: rgba(255,193,7,0.35); }
        .cmd-v2-hero__pill--ok   { background: rgba(34,197,94,0.18); border-color: rgba(34,197,94,0.3); }

        .cmd-v2-hero__right {
            display: flex;
            flex-direction: row;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }

        .cmd-v2-hero__cta--outline {
            background: transparent;
            border: 1.5px solid rgba(255,255,255,0.35);
        }

        .cmd-v2-hero__cta--outline:hover {
            background: rgba(255,255,255,0.12);
        }

        /* ---- Raccourcis Livrées / Annulées (ex-onglets) ---- */
        .cmd-v2-quick-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .cmd-v2-quick-links .cmd-v2-btn {
            width: 100%;
            justify-content: center;
        }

        @media (min-width: 640px) {
            .cmd-v2-quick-links {
                grid-template-columns: repeat(2, minmax(160px, auto));
                justify-content: flex-start;
            }
            .cmd-v2-quick-links .cmd-v2-btn { width: auto; }
        }

        .cmd-v2-hero__cta {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 22px;
            background: rgba(255,255,255,0.15);
            border: 1.5px solid rgba(255,255,255,0.25);
            border-radius: 12px;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            font-family: var(--font-corps);
            transition: background 0.2s;
        }

        a.cmd-v2-hero__cta {
            flex-wrap: wrap;
        }

        .cmd-v2-hero__cta:hover { background: rgba(255,255,255,0.25); }

        /* ---- Stat cards ---- */
        .cmd-v2-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 13px;
        }

        .cmd-v2-stat {
            background: #fff;
            border-radius: 16px;
            padding: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .cmd-v2-stat:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(53,100,166,0.14); }

        .cmd-v2-stat__icon {
            width: 46px; height: 46px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .cmd-v2-stat--total   .cmd-v2-stat__icon { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }
        .cmd-v2-stat--attente .cmd-v2-stat__icon { background: rgba(255,193,7,0.12); color: #c8960f; }
        .cmd-v2-stat--prise   .cmd-v2-stat__icon { background: rgba(255,107,53,0.12); color: var(--orange, #FF6B35); }
        .cmd-v2-stat--livraison .cmd-v2-stat__icon { background: rgba(53,100,166,0.12); color: var(--bleu-clair, #4a7ab8); }
        .cmd-v2-stat--ok      .cmd-v2-stat__icon { background: rgba(34,197,94,0.12); color: #16a34a; }
        .cmd-v2-stat--annulee .cmd-v2-stat__icon { background: rgba(239,68,68,0.1); color: #b91c1c; }

        .cmd-v2-stat__content { display: flex; flex-direction: column; gap: 1px; min-width: 0; }

        .cmd-v2-stat__label {
            font-size: 0.71rem;
            font-weight: 700;
            color: var(--gris-moyen, #737373);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            white-space: nowrap;
        }

        .cmd-v2-stat__value {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--titres);
            line-height: 1.05;
            font-family: var(--font-titres);
        }

        /* ---- Onglets statut ---- */
        .cmd-v2-tabs-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .cmd-v2-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            background: rgba(53,100,166,0.05);
            border-radius: 14px;
            padding: 5px;
        }

        .cmd-v2-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.81rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--gris-moyen, #737373);
            transition: all 0.18s;
            white-space: nowrap;
        }

        .cmd-v2-tab:hover { color: var(--titres); background: rgba(255,255,255,0.8); }

        .cmd-v2-tab.active {
            background: #fff;
            color: var(--couleur-dominante, #3564a6);
            box-shadow: 0 2px 8px rgba(53,100,166,0.12);
        }

        .cmd-v2-tab__count {
            background: rgba(53,100,166,0.1);
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.68rem;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 50px;
            min-width: 20px;
            text-align: center;
        }

        .cmd-v2-tab.active .cmd-v2-tab__count {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
        }

        .cmd-v2-tab--warn.active .cmd-v2-tab__count { background: #c8960f; }
        .cmd-v2-tab--warn .cmd-v2-tab__count { background: rgba(255,193,7,0.15); color: #c8960f; }
        .cmd-v2-tab--warn.active { color: #c8960f; }

        .cmd-v2-tabs-actions {
            display: flex;
            gap: 8px;
        }

        /* ---- Liste cartes commandes (design client uc-v2) ---- */
        .cmd-v2-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: 900px;
        }

        .cmd-v2-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.09);
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .cmd-v2-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(53,100,166,0.15);
        }

        .cmd-v2-card__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 18px 12px;
            border-bottom: 1px solid rgba(53,100,166,0.06);
        }

        .cmd-v2-card__num {
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--gris-fonce, #4a4a4a);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cmd-v2-card__num i { color: var(--couleur-dominante, #3564a6); font-size: 0.8rem; }

        .cmd-v2-card__num span {
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.95em;
        }

        /* Badges statut */
        .cmd-badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 4px 11px;
            border-radius: 50px;
            white-space: nowrap;
        }

        .cmd-badge--attente   { background: rgba(255,193,7,0.14); color: #9a6800; }
        .cmd-badge--prise     { background: rgba(255,107,53,0.14); color: #c04a10; }
        .cmd-badge--livraison { background: rgba(53,100,166,0.12); color: var(--bleu-fonce, #2d5690); }
        .cmd-badge--livree    { background: rgba(34,197,94,0.12); color: #15803d; }
        .cmd-badge--paye      { background: rgba(34,197,94,0.12); color: #15803d; }
        .cmd-badge--annulee   { background: rgba(239,68,68,0.1); color: #b91c1c; }

        .cmd-v2-card__body {
            padding: 15px 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Client identité */
        .cmd-v2-client {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cmd-v2-client__avatar {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--couleur-dominante, #3564a6), var(--bleu-clair, #4a7ab8));
            color: #fff;
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-family: var(--font-titres);
        }

        .cmd-v2-client__info { min-width: 0; }

        .cmd-v2-client__name {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--titres);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cmd-v2-client__date {
            font-size: 0.72rem;
            color: var(--gris-moyen, #737373);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }

        /* Montant */
        .cmd-v2-amount-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(53,100,166,0.04);
            border-radius: 11px;
            padding: 11px 14px;
        }

        .cmd-v2-amount-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--gris-moyen, #737373);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .cmd-v2-amount-value {
            font-size: 1.12rem;
            font-weight: 900;
            color: var(--couleur-dominante, #3564a6);
            font-family: var(--font-titres);
            letter-spacing: -0.02em;
        }

        .cmd-v2-amount-value small {
            font-size: 0.58em;
            font-weight: 600;
            color: var(--gris-moyen, #737373);
            margin-left: 3px;
        }

        /* Adresse */
        .cmd-v2-addr {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            font-size: 0.78rem;
            color: var(--gris-moyen, #737373);
        }

        .cmd-v2-addr i { color: var(--couleur-dominante, #3564a6); margin-top: 2px; flex-shrink: 0; font-size: 0.72rem; }

        /* CTA voir fiche */
        .cmd-v2-card__cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: linear-gradient(135deg, rgba(53,100,166,0.05) 0%, rgba(53,100,166,0.02) 100%);
            border-top: 1px solid rgba(53,100,166,0.07);
            text-decoration: none;
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.82rem;
            font-weight: 700;
            transition: background 0.18s;
        }

        .cmd-v2-card__cta:hover {
            background: rgba(53,100,166,0.09);
        }

        .cmd-v2-card__cta i { font-size: 0.78rem; transition: transform 0.18s; }
        .cmd-v2-card__cta:hover i { transform: translateX(4px); }

        /* Urgence badge dans le card top */
        .cmd-v2-urgence-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #ef4444;
            display: inline-block;
            animation: pulse-dot 1.5s ease-in-out infinite;
            flex-shrink: 0;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.8); }
        }

        /* Empty state */
        .cmd-v2-empty {
            grid-column: 1 / -1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 64px 24px;
            text-align: center;
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(53,100,166,0.08);
        }

        .cmd-v2-empty__icon {
            width: 72px; height: 72px;
            border-radius: 20px;
            background: rgba(53,100,166,0.07);
            color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 18px;
        }

        .cmd-v2-empty h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 7px;
        }

        .cmd-v2-empty p {
            font-size: 0.87rem;
            color: var(--gris-moyen, #737373);
            max-width: 360px;
        }

        /* ===== MODAL COMMANDE MANUELLE ===== */
        .modal-cmd-v2[hidden] { display: none !important; }

        .modal-cmd-v2 {
            position: fixed;
            inset: 0;
            z-index: 9990;
            display: flex;
            flex-direction: column;
            background: rgba(13,13,13,0.52);
            backdrop-filter: blur(6px);
        }

        .modal-cmd-v2__inner {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            width: 100%;
            background: linear-gradient(165deg, var(--fond-secondaire, #fafafa) 0%, #fff 42%, rgba(53,100,166,0.04) 100%);
            border-top: 3px solid var(--couleur-dominante, #3564a6);
            overflow: hidden;
        }

        .modal-cmd-v2__head {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 15px 22px;
            background: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }

        .modal-cmd-v2__head h2 {
            margin: 0;
            font-size: 1.18rem;
            font-family: var(--font-titres);
            color: var(--titres);
            font-weight: 700;
            display: flex; align-items: center; gap: 9px;
        }

        .modal-cmd-v2__head h2 i { color: var(--couleur-dominante, #3564a6); }

        .modal-cmd-v2__close {
            width: 42px; height: 42px;
            border: none;
            border-radius: 11px;
            background: rgba(53,100,166,0.1);
            color: var(--couleur-dominante, #3564a6);
            font-size: 1.4rem;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, color 0.2s;
        }

        .modal-cmd-v2__close:hover { background: var(--couleur-dominante, #3564a6); color: #fff; }

        .modal-cmd-v2__body {
            flex: 1;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            padding: 22px 24px 40px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cmd-v2-page { gap: 16px; }

            .cmd-v2-header {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .cmd-v2-header__actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .cmd-v2-header__actions .cmd-v2-btn {
                width: 100%;
                justify-content: center;
                padding: 8px 10px;
                font-size: 0.74rem;
            }

            .cmd-v2-hero {
                padding: 16px 14px;
                border-radius: 16px;
            }

            .cmd-v2-hero__inner {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .cmd-v2-hero__label {
                font-size: 0.62rem;
                letter-spacing: 0.1em;
            }

            .cmd-v2-hero__amount {
                font-size: 1.75rem;
            }

            .cmd-v2-hero__amount-row {
                gap: 8px;
            }

            .cmd-v2-hero__pill {
                justify-content: center;
                padding: 7px 10px;
                font-size: 0.7rem;
                border-radius: 10px;
            }

            .cmd-v2-hero__right {
                align-items: stretch;
                width: 100%;
            }

            .cmd-v2-hero__cta {
                width: 100%;
                justify-content: center;
                padding: 9px 12px;
                font-size: 10px;
            }

            .cmd-v2-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .cmd-v2-stat {
                padding: 12px 10px;
                border-radius: 14px;
                gap: 10px;
            }

            .cmd-v2-stat__icon {
                width: 36px;
                height: 36px;
                font-size: 0.85rem;
                border-radius: 10px;
            }

            .cmd-v2-stat__label { font-size: 0.62rem; }
            .cmd-v2-stat__value { font-size: 1.25rem; }
        }

        @media (max-width: 640px) {
            .cmd-v2-grid { max-width: 100%; }
            .cmd-v2-tabs { gap: 3px; }
            .cmd-v2-tab { padding: 7px 11px; font-size: 0.76rem; }
        }

        @media (max-width: 380px) {
            .cmd-v2-header__actions { grid-template-columns: 1fr; }
            .cmd-v2-hero__amount-row { flex-direction: column; align-items: flex-start; }
        }

        /* ---- Modal position client ---- */
        .cmd-pos-modal {
            position: fixed;
            inset: 0;
            z-index: 10050;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .cmd-pos-modal.is-open { display: flex; }
        .cmd-pos-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(13, 13, 13, 0.55);
        }
        .cmd-pos-modal__panel {
            position: relative;
            z-index: 1;
            width: min(560px, 100%);
            max-height: 92vh;
            overflow: auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }
        .cmd-pos-modal__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(53,100,166,0.1);
        }
        .cmd-pos-modal__head h2 {
            font-size: 12.1px;
            font-weight: 800;
            margin: 0;
            color: var(--titres);
        }
        .cmd-pos-modal__close {
            border: none;
            background: rgba(53,100,166,0.08);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            cursor: pointer;
            color: var(--couleur-dominante);
        }
        .cmd-pos-modal__body { padding: 16px 20px 20px; }
        .cmd-pos-modal__addr {
            font-size: 8px;
            color: var(--gris-fonce);
            margin-bottom: 10px;
            line-height: 1.45;
        }
        #cmd-pos-map {
            height: 180px;
            border-radius: 10px;
            border: 1px solid rgba(53,100,166,0.15);
            margin-bottom: 12px;
        }
        @media (max-width: 480px) {
            #cmd-pos-map { height: 120px; }
            .cmd-pos-modal { padding: 8px; align-items: flex-end; }
            .cmd-pos-modal__panel { width: 100%; max-height: 88vh; border-radius: 14px 14px 0 0; }
            .cmd-pos-modal__head { padding: 12px 14px; }
            .cmd-pos-modal__head h2 { font-size: 12.1px; }
            .cmd-pos-modal__body { padding: 10px 12px 14px; }
            .cmd-pos-modal__addr { font-size: 8px; }
            .cmd-pos-btn-livreur,
            .cmd-pos-btn-whatsapp { flex: 1 1 calc(50% - 4px); font-size: 0.72rem; padding: 7px 8px; }
        }
        .cmd-pos-modal__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-start;
        }
        .cmd-pos-btn-livreur {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: auto;
            max-width: 100%;
            padding: 8px 14px;
            min-height: 36px;
            border: none;
            border-radius: 8px;
            background: var(--orange, #FF6B35);
            color: #fff;
            font-weight: 700;
            font-size: 10px;
            cursor: pointer;
        }
        .cmd-pos-btn-livreur:hover { filter: brightness(1.05); }
        .cmd-pos-btn-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            min-height: 36px;
            border-radius: 8px;
            border: none;
            background: #25D366;
            color: #fff;
            font-weight: 700;
            font-size: 9px;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
        }
        .cmd-pos-btn-whatsapp i.fab { font-size: 25px; }
        .cmd-pos-btn-whatsapp:hover { filter: brightness(1.05); }
        .cmd-pos-btn-whatsapp[hidden] { display: none !important; }
        .cmd-pos-loading {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 40px 20px;
            color: var(--gris-moyen);
        }
        .cmd-pos-no-geo {
            padding: 32px 20px;
            text-align: center;
            color: var(--gris-moyen);
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container">
    <div class="cmd-v2-page">

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="cmd-v2-notif" role="status">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
            </div>
        <?php endif; ?>

        <!-- ===== HEADER ===== -->
        <header class="cmd-v2-header">
            <div class="cmd-v2-header__left">
                <p class="cmd-v2-header__eyebrow">
                    <i class="fas fa-bolt"></i> Gestion &mdash; File active
                </p>
                <h1 class="cmd-v2-header__title">Mes Commandes</h1>
            </div>
            <div class="cmd-v2-header__actions">
            </div>
        </header>

        <!-- ===== HERO BANNER ===== -->
        <div class="cmd-v2-hero">
            <div class="cmd-v2-hero__inner">
                <div>
                    <p class="cmd-v2-hero__label">Montant total &mdash; Commandes &agrave; traiter</p>
                    <div class="cmd-v2-hero__amount-row">
                        <div class="cmd-v2-hero__amount">
                            <?php echo number_format($montant_total_a_traiter, 0, ',', ' '); ?><span>FCFA</span>
                        </div>
                        <?php if ($nb_en_attente > 0): ?>
                        <div class="cmd-v2-hero__pill cmd-v2-hero__pill--warn">
                            <i class="fas fa-clock"></i>
                            <span><strong><?php echo $nb_en_attente; ?></strong> en attente</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cmd-v2-hero__right">
                    <a href="historique-ventes.php" class="cmd-v2-hero__cta cmd-v2-hero__cta--outline">
                        <i class="fas fa-chart-line"></i>
                        Historique
                    </a>
                    <button type="button" class="cmd-v2-hero__cta" id="btn-commande-manuelle-hero">
                        <i class="fas fa-plus-circle"></i>
                        Cr&eacute;er une commande
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== STAT CARDS ===== -->
        <div class="cmd-v2-stats">
            <a href="?statut=actives" class="cmd-v2-stat cmd-v2-stat--total">
                <div class="cmd-v2-stat__icon"><i class="fas fa-layer-group"></i></div>
                <div class="cmd-v2-stat__content">
                    <span class="cmd-v2-stat__label">Toutes</span>
                    <span class="cmd-v2-stat__value"><?php echo $total_commandes; ?></span>
                </div>
            </a>
            <a href="?statut=en_attente" class="cmd-v2-stat cmd-v2-stat--attente">
                <div class="cmd-v2-stat__icon"><i class="fas fa-clock"></i></div>
                <div class="cmd-v2-stat__content">
                    <span class="cmd-v2-stat__label">En attente</span>
                    <span class="cmd-v2-stat__value"><?php echo $nb_en_attente; ?></span>
                </div>
            </a>
            <a href="?statut=prise_en_charge" class="cmd-v2-stat cmd-v2-stat--prise">
                <div class="cmd-v2-stat__icon"><i class="fas fa-box-open"></i></div>
                <div class="cmd-v2-stat__content">
                    <span class="cmd-v2-stat__label">Pris en charge</span>
                    <span class="cmd-v2-stat__value"><?php echo $nb_prise; ?></span>
                </div>
            </a>
            <a href="?statut=livraison_en_cours" class="cmd-v2-stat cmd-v2-stat--livraison">
                <div class="cmd-v2-stat__icon"><i class="fas fa-truck"></i></div>
                <div class="cmd-v2-stat__content">
                    <span class="cmd-v2-stat__label">En livraison</span>
                    <span class="cmd-v2-stat__value"><?php echo $nb_livraison; ?></span>
                </div>
            </a>
        </div>

        <!-- ===== Raccourcis Livrées / Annulées ===== -->
        <div class="cmd-v2-quick-links">
            <a href="livrees.php" class="cmd-v2-btn cmd-v2-btn--outline">
                <i class="fas fa-check-circle"></i> Livr&eacute;es
            </a>
            <a href="annulees.php" class="cmd-v2-btn cmd-v2-btn--danger">
                <i class="fas fa-ban"></i> Annul&eacute;es
            </a>
        </div>

        <!-- ===== GRILLE DES COMMANDES ===== -->
        <div class="cmd-v2-grid">
            <?php if (empty($commandes_affichees)): ?>
                <div class="cmd-v2-empty">
                    <div class="cmd-v2-empty__icon"><i class="fas fa-clipboard-check"></i></div>
                    <h3>Rien &agrave; traiter</h3>
                    <p>Il n'y a aucune commande dans cette cat&eacute;gorie pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($commandes_affichees as $commande): ?>
                    <?php cmd_v2_render_card($commande); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /.cmd-v2-page -->
    </div><!-- /.contents-container -->

    <!-- ===== MODAL POSITION CLIENT ===== -->
    <div id="cmd-pos-modal" class="cmd-pos-modal" aria-hidden="true" role="dialog" aria-labelledby="cmd-pos-modal-title">
        <div class="cmd-pos-modal__backdrop" id="cmd-pos-modal-backdrop"></div>
        <div class="cmd-pos-modal__panel">
            <div class="cmd-pos-modal__head">
                <h2 id="cmd-pos-modal-title">Position du client</h2>
                <button type="button" class="cmd-pos-modal__close" id="cmd-pos-modal-close" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="cmd-pos-loading" class="cmd-pos-loading">
                <i class="fas fa-circle-notch fa-spin"></i> Localisation en cours…
            </div>
            <div id="cmd-pos-no-geo" class="cmd-pos-no-geo" style="display:none;">
                <i class="fas fa-map-marker-alt" style="font-size:2rem;opacity:.4;display:block;margin-bottom:10px;"></i>
                <p>Aucune position disponible.</p>
            </div>
            <div id="cmd-pos-modal-body" class="cmd-pos-modal__body" style="display:none;">
                <p class="cmd-pos-modal__addr" id="cmd-pos-modal-addr"></p>
                <div id="cmd-pos-map"></div>
                <div class="cmd-pos-modal__actions">
                    <button type="button" class="cmd-pos-btn-livreur" id="cmd-pos-btn-livreur">
                        <i class="fas fa-motorcycle"></i> Commander un livreur
                    </button>
                    <button type="button" class="cmd-pos-btn-whatsapp js-geo-share-location" id="cmd-pos-btn-whatsapp" hidden
                        data-share-modal-title="Partager la localisation du client"
                        data-share-hint="Partagez le point GPS du client avec votre livreur ou vos contacts.">
                        <i class="fab fa-whatsapp"></i> Partager la localisation du client
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL COMMANDE MANUELLE ===== -->
    <div id="modal-commande-manuelle"
        class="modal-commande-manuelle <?php echo $show_modal_commande_manuelle ? 'modal-open' : ''; ?>"
        role="dialog" aria-modal="true" aria-labelledby="modal-cmd-title">
        <div class="modal-commande-manuelle-backdrop"></div>
        <div class="modal-commande-manuelle-content">
            <div class="modal-commande-manuelle-header">
                <h2 id="modal-cmd-title"><i class="fas fa-plus-circle"></i> Nouvelle commande manuelle</h2>
                <button type="button" class="modal-commande-manuelle-close" id="modal-commande-manuelle-close" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-commande-manuelle-body">
                <?php if ($commande_manuelle_erreur): ?>
                    <div class="message error modal-commande-erreur">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($commande_manuelle_erreur); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="create_manuelle.php" id="form-commande-manuelle">
                    <div class="form-commande-manuelle-grid">
                        <div class="form-commande-manuelle-col form-col-articles">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-search"></i>
                                    <h3>Rechercher un produit</h3>
                                </div>
                                <div class="form-group search-group">
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-produit" name="search_produit"
                                            placeholder="Tapez le nom du produit ou de la cat&eacute;gorie..."
                                            autocomplete="off">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-loading" aria-hidden="true">
                                            <i class="fas fa-spinner fa-spin"></i>
                                        </span>
                                    </div>
                                    <div id="search-produit-results" class="search-produit-results" role="listbox" aria-hidden="true"></div>
                                </div>
                                <p class="form-hint"><i class="fas fa-info-circle"></i> Tapez au moins 1 caract&egrave;re ou laissez vide pour afficher tous les produits en stock.</p>
                            </div>

                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h3>Produits de la commande</h3>
                                    <span class="lignes-count" id="lignes-count">0 produit(s)</span>
                                </div>
                                <div id="lignes-commande" class="lignes-commande">
                                    <div class="lignes-empty" id="lignes-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun produit ajout&eacute;. Utilisez la recherche ci-dessus.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-commande-manuelle-col form-col-client">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-user"></i>
                                    <h3>Informations client</h3>
                                </div>
                                <div class="form-group search-group" style="position:relative;">
                                    <label for="search-client">Rechercher un client</label>
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-client"
                                            placeholder="Nom, t&eacute;l&eacute;phone ou email..."
                                            autocomplete="off">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-client-loading" style="visibility:hidden;">
                                            <i class="fas fa-spinner fa-spin"></i>
                                        </span>
                                    </div>
                                    <div id="search-client-results" class="search-produit-results" role="listbox"
                                        aria-hidden="true"
                                        style="position:absolute; left:0; right:0; top:100%; z-index:100;"></div>
                                    <p class="form-hint"><i class="fas fa-info-circle"></i> Recherchez un client existant ou saisissez manuellement.</p>
                                </div>
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label for="client_nom">Nom <span class="required">*</span></label>
                                        <input type="text" id="client_nom" name="client_nom" required
                                            value="<?php echo htmlspecialchars($commande_manuelle_post['client_nom'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="client_prenom">Pr&eacute;nom <span class="required">*</span></label>
                                        <input type="text" id="client_prenom" name="client_prenom" required
                                            value="<?php echo htmlspecialchars($commande_manuelle_post['client_prenom'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="client_telephone">T&eacute;l&eacute;phone <span class="required">*</span></label>
                                    <input type="tel" id="client_telephone" name="client_telephone" required
                                        placeholder="Ex: 07 12 34 56 78"
                                        value="<?php echo htmlspecialchars($commande_manuelle_post['client_telephone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="client_email">Email <span class="optional">(optionnel)</span></label>
                                    <input type="email" id="client_email" name="client_email"
                                        placeholder="Optionnel"
                                        value="<?php echo htmlspecialchars($commande_manuelle_post['client_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="zone_livraison_id"><i class="fas fa-map-marker-alt"></i> Adresse de livraison <span class="required">*</span></label>
                                    <select id="zone_livraison_id" name="zone_livraison_id">
                                        <option value="">&mdash; S&eacute;lectionnez une adresse &mdash;</option>
                                        <?php foreach ($zones_livraison as $z): ?>
                                            <option value="<?php echo (int) $z['id']; ?>"
                                                data-adresse="<?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>"
                                                data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                                <?php echo (isset($commande_manuelle_post['zone_livraison_id']) && (int) $commande_manuelle_post['zone_livraison_id'] === (int) $z['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                                (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="custom"
                                            <?php echo (isset($commande_manuelle_post['zone_livraison_id']) && $commande_manuelle_post['zone_livraison_id'] === 'custom') ? 'selected' : ''; ?>>
                                            &mdash; Adresse personnalis&eacute;e &mdash;
                                        </option>
                                    </select>
                                    <div id="adresse-custom-wrap" style="display:none; margin-top:10px;">
                                        <textarea id="adresse_livraison_ta" rows="3"
                                            placeholder="Saisissez l'adresse compl&egrave;te"><?php echo htmlspecialchars($commande_manuelle_post['adresse_livraison'] ?? ''); ?></textarea>
                                    </div>
                                    <div id="adresse-zone-display"
                                        style="display:none; margin-top:8px; padding:10px; background:#f5f5f4; border-radius:8px;"></div>
                                    <input type="hidden" name="adresse_livraison" id="adresse_livraison" value="">
                                    <input type="hidden" name="frais_livraison" id="frais_livraison" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <textarea id="notes" name="notes" rows="2"
                                        placeholder="Instructions suppl&eacute;mentaires..."><?php echo htmlspecialchars($commande_manuelle_post['notes'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Date de la commande</label>
                                    <div class="value-static"><i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d/m/Y &agrave; H:i'); ?>
                                    </div>
                                </div>
                                <div class="commande-manuelle-recap">
                                    <div class="recap-line">
                                        <span>Sous-total produits</span>
                                        <span id="recap-sous-total">0 FCFA</span>
                                    </div>
                                    <div class="recap-line">
                                        <span>Frais de livraison</span>
                                        <span id="recap-frais">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-total">
                                        <span>Total</span>
                                        <span id="recap-total">0 FCFA</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-commande-manuelle-actions">
                        <button type="button" class="btn-secondary" id="modal-commande-manuelle-cancel">Annuler</button>
                        <button type="submit" class="btn-primary btn-submit-commande" name="submit_commande_manuelle">
                            <i class="fas fa-check"></i> Enregistrer (En attente)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    (function () {
        // ---- Modal ouvrir/fermer ----
        var modal    = document.getElementById('modal-commande-manuelle');
        var backdrop = modal ? modal.querySelector('.modal-commande-manuelle-backdrop') : null;
        var btnsOpen = [
            document.getElementById('btn-commande-manuelle'),
            document.getElementById('btn-commande-manuelle-hero')
        ];
        var btnClose  = document.getElementById('modal-commande-manuelle-close');
        var btnCancel = document.getElementById('modal-commande-manuelle-cancel');

        function openModal()  { if (modal) { modal.classList.add('modal-open'); document.body.style.overflow = 'hidden'; } }
        function closeModal() { if (modal) { modal.classList.remove('modal-open'); document.body.style.overflow = ''; } }

        btnsOpen.forEach(function (b) { if (b) b.addEventListener('click', openModal); });
        if (btnClose)  btnClose.addEventListener('click', closeModal);
        if (btnCancel) btnCancel.addEventListener('click', closeModal);
        if (backdrop)  backdrop.addEventListener('click', closeModal);
        if (modal && modal.classList.contains('modal-open')) document.body.style.overflow = 'hidden';

        // ---- Recherche produits ----
        var searchInput   = document.getElementById('search-produit');
        var searchResults = document.getElementById('search-produit-results');
        var searchLoading = document.getElementById('search-loading');
        var lignesContainer = document.getElementById('lignes-commande');
        var lignesEmpty   = document.getElementById('lignes-empty');
        var lignesCount   = document.getElementById('lignes-count');
        var ligneIndex    = 0;
        var ajaxUrl       = 'ajax_search_produits.php';

        function updateLignesUI() {
            var items = lignesContainer ? lignesContainer.querySelectorAll('.ligne-commande-item') : [];
            var n = items.length;
            if (lignesEmpty)  lignesEmpty.style.display  = n === 0 ? 'flex' : 'none';
            if (lignesCount)  lignesCount.textContent    = n + ' produit(s)';
        }

        function addLigne(produit) {
            var prix     = parseFloat(produit.prix) || 0;
            var prixPromo = produit.prix_promotion && parseFloat(produit.prix_promotion) > 0
                ? parseFloat(produit.prix_promotion) : '';
            var nom = (produit.nom || '');
            var idx = ligneIndex++;
            var div = document.createElement('div');
            div.className = 'ligne-commande-item';
            div.dataset.produitId = produit.id;
            div.innerHTML =
                '<input type="hidden" name="lignes[' + idx + '][produit_id]" value="' + produit.id + '">' +
                '<input type="text" name="lignes[' + idx + '][nom_produit]" value="' + nom.replace(/"/g, '&quot;') +
                '" placeholder="Nom du produit" class="ligne-nom-input">' +
                '<input type="number" name="lignes[' + idx + '][quantite]" value="1" min="1" max="' +
                (produit.stock_dispo || produit.stock || 999) + '" class="ligne-qte" title="Quantit&eacute;">' +
                '<input type="number" name="lignes[' + idx + '][prix_unitaire]" value="' + (prixPromo || prix) +
                '" min="0" step="0.01" class="ligne-prix" title="Prix unitaire FCFA">' +
                '<input type="number" name="lignes[' + idx + '][prix_promotion]" value="' + (prixPromo || '') +
                '" min="0" step="0.01" placeholder="Optionnel" class="ligne-prix-promo" title="Prix promo">' +
                '<button type="button" class="ligne-remove" aria-label="Retirer"><i class="fas fa-trash"></i></button>';
            if (lignesEmpty) lignesEmpty.style.display = 'none';
            div.querySelector('.ligne-remove').addEventListener('click', function () {
                div.remove(); updateLignesUI(); updateRecap();
            });
            lignesContainer.appendChild(div);
            updateLignesUI(); updateRecap();
        }

        function doSearch(q) {
            if (searchLoading) searchLoading.style.visibility = 'visible';
            fetch(ajaxUrl + '?q=' + encodeURIComponent(q) + '&limit=25')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var items = data.items || [];
                    searchResults.innerHTML = '';
                    if (items.length === 0) {
                        searchResults.innerHTML = '<div class="search-no-results"><i class="fas fa-box-open"></i> Aucun produit en stock trouv&eacute;.</div>';
                    } else {
                        items.forEach(function (p) {
                            var el = document.createElement('div');
                            el.className = 'search-result-item';
                            el.setAttribute('role', 'option');
                            el.setAttribute('tabindex', '0');
                            var stock = p.stock_dispo || p.stock || 0;
                            var prix  = parseFloat(p.prix) || 0;
                            el.innerHTML =
                                '<span class="sr-nom">' + (p.nom || '') + '</span>' +
                                '<span class="sr-meta">' + (p.categorie_nom || '') + ' &bull; Stock\u00a0: ' + stock + ' &bull; ' + prix + ' FCFA</span>';
                            function selectProduit() {
                                addLigne(p);
                                searchInput.value = '';
                                searchResults.innerHTML = '';
                                searchResults.setAttribute('aria-hidden', 'true');
                            }
                            el.addEventListener('mousedown', function (ev) { ev.preventDefault(); selectProduit(); });
                            el.addEventListener('keydown', function (ev) {
                                if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); selectProduit(); }
                            });
                            searchResults.appendChild(el);
                        });
                    }
                    searchResults.setAttribute('aria-hidden', 'false');
                })
                .catch(function () {
                    searchResults.innerHTML = '<div class="search-no-results"><i class="fas fa-exclamation-triangle"></i> Erreur de recherche.</div>';
                })
                .finally(function () { if (searchLoading) searchLoading.style.visibility = 'hidden'; });
        }

        // ---- Zone livraison & récap ----
        var zoneSelect        = document.getElementById('zone_livraison_id');
        var adresseCustomWrap = document.getElementById('adresse-custom-wrap');
        var adresseZoneDisplay = document.getElementById('adresse-zone-display');
        var adresseLivraison  = document.getElementById('adresse_livraison');
        var adresseTa         = document.getElementById('adresse_livraison_ta');
        var fraisInput        = document.getElementById('frais_livraison');
        var recapSousTotal    = document.getElementById('recap-sous-total');
        var recapFrais        = document.getElementById('recap-frais');
        var recapTotal        = document.getElementById('recap-total');
        var formCommande      = document.getElementById('form-commande-manuelle');

        function formatNumber(n) {
            return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
        }

        function getSousTotal() {
            var total = 0;
            var items = lignesContainer ? lignesContainer.querySelectorAll('.ligne-commande-item') : [];
            items.forEach(function (row) {
                var qte   = parseFloat(row.querySelector('.ligne-qte').value) || 0;
                var prix  = parseFloat(row.querySelector('.ligne-prix').value) || 0;
                var promo = row.querySelector('.ligne-prix-promo');
                var p     = promo && promo.value && parseFloat(promo.value) > 0 ? parseFloat(promo.value) : prix;
                total    += p * qte;
            });
            return total;
        }

        function getFraisLivraison() {
            if (!zoneSelect || zoneSelect.value === '' || zoneSelect.value === 'custom') return 0;
            var opt = zoneSelect.options[zoneSelect.selectedIndex];
            return opt && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
        }

        function updateRecap() {
            var sousTotal = getSousTotal();
            var frais = getFraisLivraison();
            var total = sousTotal + frais;
            if (recapSousTotal) recapSousTotal.textContent = formatNumber(sousTotal) + ' FCFA';
            if (recapFrais)     recapFrais.textContent     = formatNumber(frais) + ' FCFA';
            if (recapTotal)     recapTotal.textContent     = formatNumber(total) + ' FCFA';
            if (fraisInput)     fraisInput.value           = frais;
        }

        function onZoneChange() {
            var val = zoneSelect ? zoneSelect.value : '';
            if (val === 'custom') {
                if (adresseCustomWrap)  adresseCustomWrap.style.display  = 'block';
                if (adresseZoneDisplay) adresseZoneDisplay.style.display = 'none';
                if (adresseLivraison)   adresseLivraison.value           = '';
            } else if (val !== '') {
                var opt = zoneSelect.options[zoneSelect.selectedIndex];
                var adr = opt && opt.dataset.adresse ? opt.dataset.adresse : '';
                if (adresseLivraison)   adresseLivraison.value           = adr;
                if (adresseCustomWrap)  adresseCustomWrap.style.display  = 'none';
                if (adresseZoneDisplay) { adresseZoneDisplay.textContent = adr; adresseZoneDisplay.style.display = 'block'; }
            } else {
                if (adresseCustomWrap)  adresseCustomWrap.style.display  = 'none';
                if (adresseZoneDisplay) adresseZoneDisplay.style.display = 'none';
                if (adresseLivraison)   adresseLivraison.value           = '';
            }
            updateRecap();
        }

        if (zoneSelect) zoneSelect.addEventListener('change', onZoneChange);

        if (lignesContainer) {
            lignesContainer.addEventListener('input', function (ev) {
                if (ev.target.classList.contains('ligne-qte') ||
                    ev.target.classList.contains('ligne-prix') ||
                    ev.target.classList.contains('ligne-prix-promo')) {
                    updateRecap();
                }
            });
        }

        if (formCommande) {
            formCommande.addEventListener('submit', function (ev) {
                if (zoneSelect && zoneSelect.value === 'custom' && adresseTa) {
                    if (adresseLivraison) adresseLivraison.value = adresseTa.value.trim();
                } else if (zoneSelect && zoneSelect.value && zoneSelect.value !== 'custom') {
                    onZoneChange();
                }
                if (adresseLivraison && !adresseLivraison.value.trim()) {
                    ev.preventDefault();
                    alert("Veuillez s\u00e9lectionner une adresse de livraison ou saisir une adresse personnalis\u00e9e.");
                    return false;
                }
            });
        }

        // ---- Recherche produit (debounce) ----
        var searchTimeout;
        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                var q = searchInput.value.trim();
                searchTimeout = setTimeout(function () { doSearch(q); }, 250);
            });
            searchInput.addEventListener('focus', function () {
                var q = searchInput.value.trim();
                if (searchResults.getAttribute('aria-hidden') === 'true' || searchResults.innerHTML === '') doSearch(q);
            });
            searchInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (!searchResults.contains(document.activeElement)) {
                        searchResults.innerHTML = '';
                        searchResults.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchResults.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        }

        // ---- Recherche client ----
        var searchClientInput   = document.getElementById('search-client');
        var searchClientResults = document.getElementById('search-client-results');
        var searchClientLoading = document.getElementById('search-client-loading');
        var clientNomInput      = document.getElementById('client_nom');
        var clientPrenomInput   = document.getElementById('client_prenom');
        var clientTelInput      = document.getElementById('client_telephone');
        var clientEmailInput    = document.getElementById('client_email');
        var clientSearchTimeout;

        if (searchClientInput && searchClientResults && clientNomInput && clientPrenomInput && clientTelInput) {
            function doClientSearch(q) {
                if (q.length < 1) {
                    searchClientResults.innerHTML = '';
                    searchClientResults.setAttribute('aria-hidden', 'true');
                    return;
                }
                if (searchClientLoading) searchClientLoading.style.visibility = 'visible';
                fetch('ajax_search_clients.php?q=' + encodeURIComponent(q) + '&limit=15')
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        searchClientResults.innerHTML = '';
                        if (data.length === 0) {
                            searchClientResults.innerHTML = '<div class="search-no-results">Aucun client trouv&eacute;.</div>';
                        } else {
                            data.forEach(function (c) {
                                var el = document.createElement('div');
                                el.className = 'search-result-item';
                                el.setAttribute('role', 'option');
                                el.innerHTML =
                                    '<span class="sr-nom">' + (c.nom_complet || '') + '</span>' +
                                    '<span class="sr-meta">' + (c.telephone || '') + (c.email ? ' &bull; ' + c.email : '') + '</span>';
                                el.addEventListener('mousedown', function (ev) {
                                    ev.preventDefault();
                                    clientNomInput.value    = c.nom    || '';
                                    clientPrenomInput.value = c.prenom || '';
                                    clientTelInput.value    = c.telephone || '';
                                    if (clientEmailInput) clientEmailInput.value = c.email || '';
                                    searchClientInput.value = '';
                                    searchClientResults.innerHTML = '';
                                    searchClientResults.setAttribute('aria-hidden', 'true');
                                });
                                searchClientResults.appendChild(el);
                            });
                        }
                        searchClientResults.setAttribute('aria-hidden', 'false');
                    })
                    .catch(function () {
                        searchClientResults.innerHTML = '<div class="search-no-results">Erreur de recherche.</div>';
                    })
                    .finally(function () {
                        if (searchClientLoading) searchClientLoading.style.visibility = 'hidden';
                    });
            }

            searchClientInput.addEventListener('input', function () {
                clearTimeout(clientSearchTimeout);
                var q = searchClientInput.value.trim();
                clientSearchTimeout = setTimeout(function () { doClientSearch(q); }, 300);
            });
            searchClientInput.addEventListener('focus', function () {
                var q = searchClientInput.value.trim();
                if (q.length >= 1) doClientSearch(q);
            });
            searchClientInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (!searchClientResults.contains(document.activeElement)) {
                        searchClientResults.innerHTML = '';
                        searchClientResults.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchClientResults.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        }

        updateLignesUI();
        if (modal && modal.classList.contains('modal-open') && zoneSelect && zoneSelect.value) {
            onZoneChange();
        }
    })();
    </script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <?php require __DIR__ . '/../../includes/partials/platform_share_modal.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-nav-apps.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/admin-commande-position-modal.js<?php echo asset_version_query(); ?>"></script>
    <?php require __DIR__ . '/../../includes/partials/uc_gallery_lightbox.php'; ?>
    <script src="/js/uc-gallery-lightbox.js<?php echo asset_version_query(); ?>"></script>

</body>
</html>
