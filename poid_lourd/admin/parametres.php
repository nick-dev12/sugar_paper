<?php
/**
 * Page des paramètres admin — redesign v2
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/includes/require_admin_session.php';



require_once __DIR__ . '/includes/require_access.php';

$__param_role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$__param_show_site_modules = in_array($__param_role, ['admin', 'plateforme', 'vendeur'], true);
$__param_show_comptes      = in_array($__param_role, ['admin', 'plateforme', 'vendeur', 'rh'], true);
$__param_retour            = admin_role_default_redirect_path($__param_role);

require_once __DIR__ . '/../includes/site_url.php';
require_once __DIR__ . '/../includes/marketplace_helpers.php';

$__cert_niveau_actif = null;
$__cert_demande_en_cours = false;
if ($__param_role === 'vendeur' && file_exists(__DIR__ . '/../models/model_vendeur_certification.php')) {
    require_once __DIR__ . '/../models/model_vendeur_certification.php';
    $__cert_niveau_actif = vendeur_certification_get_niveau_actif((int) ($_SESSION['admin_id'] ?? 0));
    $__cert_demande_en_cours = vendeur_certification_get_demande_en_cours((int) ($_SESSION['admin_id'] ?? 0)) !== null;
}

$__vendeur_boutique_slug = ($__param_role === 'vendeur')
    ? trim((string)($_SESSION['admin_boutique_slug'] ?? ''))
    : '';
$__vendeur_site_path     = '';
$__vendeur_site_full_url = '';
if ($__vendeur_boutique_slug !== '') {
    $__vendeur_site_path     = boutique_url('index.php', $__vendeur_boutique_slug);
    $__vendeur_site_full_url = rtrim(get_site_base_url(), '/') . $__vendeur_site_path;
}
$__voir_site_href = ($__vendeur_boutique_slug !== '') ? $__vendeur_site_path : '../index.php';
$__vendeur_boutique_nom_aff = trim((string)($_SESSION['admin_boutique_nom'] ?? ''));
if ($__vendeur_boutique_nom_aff === '') { $__vendeur_boutique_nom_aff = 'Ma boutique'; }

if ($__param_role === 'vendeur') {
    require_once __DIR__ . '/../models/model_admin.php';
    $vbl_admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    $vbl_admin = $vbl_admin_id > 0 ? get_admin_by_id($vbl_admin_id) : null;
    if ($vbl_admin && ($vbl_admin['role'] ?? '') === 'vendeur') {
        admin_sync_vendeur_boutique_session_from_admin($vbl_admin);
        $__vendeur_boutique_slug = trim((string) ($vbl_admin['boutique_slug'] ?? ''));
        $__vendeur_boutique_nom_aff = trim((string) ($vbl_admin['boutique_nom'] ?? ''));
        if ($__vendeur_boutique_nom_aff === '') {
            $__vendeur_boutique_nom_aff = 'Ma boutique';
        }
        $__vendeur_site_path = '';
        $__vendeur_site_full_url = '';
        if ($__vendeur_boutique_slug !== '') {
            $__vendeur_site_path = boutique_url('index.php', $__vendeur_boutique_slug);
            $__vendeur_site_full_url = rtrim(get_site_base_url(), '/') . $__vendeur_site_path;
        }
        $__voir_site_href = ($__vendeur_boutique_slug !== '') ? $__vendeur_site_path : '../index.php';
    }
    require_once __DIR__ . '/includes/vendeur_boutique_localisation.php';
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Infos admin
$admin_prenom   = trim((string)($_SESSION['admin_prenom'] ?? ''));
$admin_nom      = trim((string)($_SESSION['admin_nom'] ?? ''));
$admin_nom_aff  = trim($admin_prenom . ' ' . $admin_nom);
if ($admin_nom_aff === '') { $admin_nom_aff = 'Admin'; }
$admin_initial  = $admin_prenom !== '' ? mb_strtoupper(mb_substr($admin_prenom, 0, 1, 'UTF-8'), 'UTF-8') : 'A';

// Labels de rôle
$role_labels = [
    'admin'      => ['label' => 'Administrateur', 'icon' => 'fa-shield-halved', 'color' => '#3564a6'],
    'plateforme' => ['label' => 'Gestionnaire',   'icon' => 'fa-layer-group',   'color' => '#7c3aed'],
    'vendeur'    => ['label' => 'Vendeur',         'icon' => 'fa-store',         'color' => '#FF6B35'],
    'rh'         => ['label' => 'RH',              'icon' => 'fa-users',         'color' => '#16a34a'],
];
$rl = $role_labels[$__param_role] ?? ['label' => ucfirst($__param_role), 'icon' => 'fa-user-cog', 'color' => '#3564a6'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Param&egrave;tres &mdash; Administration</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-vendeur-share.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <?php if ($__param_role === 'vendeur'): ?>
    <link rel="stylesheet" href="/css/admin-boutique-localisation.css<?php echo asset_version_query(); ?>">
    <?php endif; ?>
    <style>
        /* ===== PARAMÈTRES v2 ===== */

        .prm-page {
            max-width: 980px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 36px) clamp(14px, 4vw, 24px) 80px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        /* ---- Page header ---- */
        .prm-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .prm-page-header__left  { display: flex; flex-direction: column; gap: 3px; }

        .prm-page-header__eyebrow {
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; gap: 5px;
        }

        .prm-page-header__title {
            font-size: clamp(1.3rem, 3vw, 1.75rem);
            font-weight: 800; color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.15; letter-spacing: -0.025em;
        }

        .prm-page-header__actions { display: flex; gap: 9px; align-items: center; flex-wrap: wrap; }

        /* ---- Boutons header ---- */
        .prm-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 11px;
            font-size: 0.81rem; font-weight: 700;
            cursor: pointer; border: none;
            text-decoration: none; font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: all 0.2s; white-space: nowrap;
        }

        .prm-btn--primary { background: var(--couleur-dominante, #3564a6); color: #fff; box-shadow: 0 4px 14px rgba(53,100,166,0.25); }
        .prm-btn--primary:hover { background: var(--bleu-fonce, #2d5690); transform: translateY(-1px); }
        .prm-btn--outline { background: #fff; color: var(--couleur-dominante, #3564a6); border: 1.5px solid rgba(53,100,166,0.22); }
        .prm-btn--outline:hover { background: rgba(53,100,166,0.05); }
        .prm-btn--orange { background: var(--orange, #FF6B35); color: #fff; box-shadow: 0 4px 14px rgba(255,107,53,0.25); }
        .prm-btn--orange:hover { background: var(--orange-fonce, #E85A2A); transform: translateY(-1px); }

        /* ---- Hero identité admin ---- */
        .prm-hero {
            background: linear-gradient(135deg, var(--bleu-fonce, #2d5690) 0%, var(--couleur-dominante, #3564a6) 65%, var(--bleu-clair, #4a7ab8) 100%);
            border-radius: 20px;
            padding: clamp(20px, 3.5vw, 34px);
            position: relative; overflow: hidden;
            box-shadow: 0 16px 44px rgba(53,100,166,0.28);
        }

        .prm-hero::before {
            content: ''; position: absolute; top: -60px; right: -40px;
            width: 220px; height: 220px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%; pointer-events: none;
        }

        .prm-hero::after {
            content: ''; position: absolute; bottom: -70px; right: 80px;
            width: 170px; height: 170px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%; pointer-events: none;
        }

        .prm-hero__inner {
            display: flex; align-items: center; gap: 20px;
            flex-wrap: wrap; position: relative;
        }

        .prm-hero__avatar {
            width: 68px; height: 68px; border-radius: 50%;
            background: rgba(255,255,255,0.18);
            border: 3px solid rgba(255,255,255,0.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.9rem; font-weight: 900; color: #fff;
            font-family: var(--font-titres, 'Poppins', sans-serif);
            flex-shrink: 0; box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .prm-hero__body    { flex: 1; min-width: 0; }
        .prm-hero__name    { font-size: clamp(1.1rem, 2.5vw, 1.45rem); font-weight: 900; color: #fff; font-family: var(--font-titres, 'Poppins', sans-serif); line-height: 1.1; }
        .prm-hero__role    { font-size: 0.79rem; color: rgba(255,255,255,0.65); margin-top: 4px; display: flex; align-items: center; gap: 6px; }
        .prm-hero__sub     { font-size: 0.74rem; color: rgba(255,255,255,0.48); margin-top: 2px; }

        .prm-hero__pills { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }

        .prm-hero__pill {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 50px; padding: 6px 16px;
            display: flex; align-items: center; gap: 7px;
            color: #fff; font-size: 0.78rem; font-weight: 600;
        }

        .prm-hero__links { display: flex; gap: 9px; flex-wrap: wrap; margin-left: auto; }

        .prm-hero__link {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 17px;
            background: rgba(255,255,255,0.13);
            border: 1.5px solid rgba(255,255,255,0.2);
            border-radius: 10px; color: #fff;
            font-size: 0.79rem; font-weight: 700;
            text-decoration: none; transition: background 0.2s;
            white-space: nowrap;
        }

        .prm-hero__link:hover { background: rgba(255,255,255,0.23); }

        /* ---- Alert success ---- */
        .prm-alert {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 14px 18px; border-radius: 14px;
            font-size: 0.84rem; font-weight: 500;
            border: 1px solid transparent;
        }

        .prm-alert--success { background: rgba(34,197,94,0.09); border-color: rgba(34,197,94,0.22); color: #15803d; }

        .prm-alert i { margin-top: 2px; font-size: 1rem; flex-shrink: 0; }

        /* ---- Section boutique vendeur ---- */
        .prm-boutique {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(255,107,53,0.18);
            box-shadow: 0 2px 14px rgba(255,107,53,0.08);
            overflow: hidden;
        }

        .prm-boutique__head {
            padding: 18px 22px 14px;
            border-bottom: 1px solid rgba(255,107,53,0.1);
            display: flex; align-items: center; gap: 13px;
        }

        .prm-boutique__head-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: rgba(255,107,53,0.1); color: var(--orange, #FF6B35);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }

        .prm-boutique__head-text h3 { font-size: 0.95rem; font-weight: 800; color: var(--titres, #0d0d0d); margin: 0; }
        .prm-boutique__head-text p  { font-size: 0.73rem; color: var(--gris-moyen, #737373); margin: 2px 0 0; }

        .prm-boutique__body { padding: 18px 22px 22px; display: flex; flex-direction: column; gap: 14px; }

        .prm-boutique__url-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: stretch; }

        .prm-boutique__url-input {
            flex: 1 1 220px; min-width: 0;
            padding: 10px 14px;
            border: 1.5px solid rgba(53,100,166,0.18);
            border-radius: 10px; background: #f9fbff;
            font-size: 0.83rem; font-family: ui-monospace, monospace;
            color: var(--titres, #0d0d0d); outline: none;
        }

        .prm-boutique__actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .prm-boutique__action-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 18px; border-radius: 10px;
            font-size: 0.81rem; font-weight: 700;
            border: none; cursor: pointer;
            text-decoration: none; font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: all 0.18s;
        }

        .prm-boutique__action-btn--copy   { background: var(--couleur-dominante, #3564a6); color: #fff; }
        .prm-boutique__action-btn--copy:hover  { background: var(--bleu-fonce, #2d5690); }
        .prm-boutique__action-btn--share  { background: var(--orange, #FF6B35); color: #fff; }
        .prm-boutique__action-btn--share:hover { background: var(--orange-fonce, #E85A2A); }

        .prm-boutique__feedback {
            font-size: 0.82rem; font-weight: 600; color: var(--couleur-dominante, #3564a6);
            min-height: 1.2em;
        }

        .prm-boutique__deeplink {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 11px 15px;
            background: rgba(53,100,166,0.06);
            border: 1px solid rgba(53,100,166,0.14);
            border-radius: 10px; font-size: 0.8rem;
            color: var(--couleur-dominante, #3564a6); line-height: 1.45;
        }

        .prm-boutique__deeplink i { flex-shrink: 0; margin-top: 2px; }

        .prm-boutique__warn {
            padding: 13px 16px; border-radius: 11px;
            background: rgba(255,107,53,0.09);
            border: 1px solid rgba(255,107,53,0.2);
            font-size: 0.84rem; color: #7c2d12;
            line-height: 1.5;
        }

        .prm-boutique__warn a { color: var(--couleur-dominante, #3564a6); font-weight: 600; }

        /* ---- Titre de section ---- */
        .prm-section-head {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }

        .prm-section-head__title {
            font-size: 1.05rem; font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            display: flex; align-items: center; gap: 8px;
        }

        .prm-section-head__title::before {
            content: ''; display: inline-block;
            width: 4px; height: 18px; border-radius: 3px;
            background: var(--couleur-dominante, #3564a6);
        }

        .prm-section-head__meta {
            font-size: 0.8rem; color: var(--gris-moyen, #737373); margin: 0;
        }

        /* ---- Grille de cartes modules ---- */
        .prm-modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 15px;
        }

        /* ---- Carte module ---- */
        .prm-module-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            padding: 22px 20px 18px;
            display: flex; flex-direction: column; gap: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .prm-module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 28px rgba(53,100,166,0.12);
        }

        .prm-module-card__icon-wrap {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }

        /* Couleurs d'icônes par module */
        .prm-module-card--home     .prm-module-card__icon-wrap { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }
        .prm-module-card--trending .prm-module-card__icon-wrap { background: rgba(234,179,8,0.12); color: #a16207; }
        .prm-module-card--slider   .prm-module-card__icon-wrap { background: rgba(139,92,246,0.12); color: #7c3aed; }
        .prm-module-card--video    .prm-module-card__icon-wrap { background: rgba(239,68,68,0.1); color: #b91c1c; }
        .prm-module-card--logos    .prm-module-card__icon-wrap { background: rgba(34,197,94,0.1); color: #15803d; }
        .prm-module-card--livraison .prm-module-card__icon-wrap { background: rgba(255,107,53,0.1); color: var(--orange, #FF6B35); }
        .prm-module-card--comptes  .prm-module-card__icon-wrap { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }

        .prm-module-card__title {
            font-size: 0.93rem; font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.2;
        }

        .prm-module-card__desc {
            font-size: 0.77rem; color: var(--gris-moyen, #737373);
            line-height: 1.55; flex: 1;
        }

        .prm-module-card__link {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 16px; border-radius: 10px;
            background: rgba(53,100,166,0.07);
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.8rem; font-weight: 700;
            text-decoration: none;
            transition: background 0.18s, color 0.18s;
            align-self: flex-start;
        }

        .prm-module-card__link:hover {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
        }

        /* Orange pour livraison vendeur */
        .prm-module-card--livraison .prm-module-card__link {
            background: rgba(255,107,53,0.08);
            color: var(--orange, #FF6B35);
        }

        .prm-module-card--livraison .prm-module-card__link:hover {
            background: var(--orange, #FF6B35);
            color: #fff;
        }

        /* ---- Section raccourcis (rôles limités) ---- */
        .prm-shortcuts {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 13px;
        }

        .prm-shortcut-card {
            background: #fff; border-radius: 16px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            padding: 18px 18px;
            display: flex; align-items: center; gap: 14px;
            text-decoration: none; color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .prm-shortcut-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(53,100,166,0.12); }

        .prm-shortcut-card__icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }

        .prm-shortcut-card__body { display: flex; flex-direction: column; gap: 1px; }
        .prm-shortcut-card__title { font-size: 0.88rem; font-weight: 700; color: var(--titres, #0d0d0d); }
        .prm-shortcut-card__sub   { font-size: 0.72rem; color: var(--gris-moyen, #737373); }

        /* ---- Responsive ---- */
        @media (max-width: 600px) {
            .prm-hero__inner { flex-direction: column; align-items: flex-start; }
            .prm-hero__links { margin-left: 0; }
            .prm-modules-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 420px) {
            .prm-modules-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="prm-page">

        <!-- ===== PAGE HEADER ===== -->
        <header class="prm-page-header">
            <div class="prm-page-header__left">
                <p class="prm-page-header__eyebrow">
                    <i class="fas fa-<?php echo $__param_show_site_modules ? 'sliders-h' : 'user-cog'; ?>"></i>
                    <?php echo $__param_show_site_modules ? 'Configuration &amp; exp&eacute;rience' : 'Espace connect&eacute;'; ?>
                </p>
                <h1 class="prm-page-header__title">
                    <?php echo $__param_show_site_modules ? 'Param&egrave;tres' : 'Compte &amp; raccourcis'; ?>
                </h1>
            </div>
        </header>

        <!-- ===== HERO IDENTITÉ ===== -->
        <div class="prm-hero">
            <?php require __DIR__ . '/../includes/partials/vendeur_certification_hero_badge.php'; ?>
            <div class="prm-hero__inner">
                <div class="prm-hero__avatar"><?php echo htmlspecialchars($admin_initial); ?></div>
                <div class="prm-hero__body">
                    <div class="prm-hero__name"><?php echo htmlspecialchars($admin_nom_aff); ?></div>
                    <div class="prm-hero__role">
                        <i class="fas <?php echo $rl['icon']; ?>" style="font-size:.75rem;"></i>
                        <?php echo htmlspecialchars($rl['label']); ?>
                    </div>
                    <?php if (!empty($_SESSION['admin_email'])): ?>
                        <div class="prm-hero__sub">
                            <i class="fas fa-envelope" style="font-size:.65rem;margin-right:3px;"></i>
                            <?php echo htmlspecialchars($_SESSION['admin_email']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="prm-hero__pills">
                        <div class="prm-hero__pill">
                            <i class="fas fa-circle-check" style="font-size:.7rem;color:rgba(134,239,172,.8);"></i>
                            Session active
                        </div>
                        <?php if ($__vendeur_boutique_slug !== ''): ?>
                            <div class="prm-hero__pill">
                                <i class="fas fa-store" style="font-size:.7rem;"></i>
                                <?php echo htmlspecialchars($__vendeur_boutique_nom_aff); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="prm-hero__links">
                    <a href="profil.php" class="prm-hero__link">
                        <i class="fas fa-user-pen"></i> Mon profil
                    </a>
                    <a href="<?php echo htmlspecialchars($__voir_site_href, ENT_QUOTES, 'UTF-8'); ?>"
                        class="prm-hero__link" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-external-link-alt"></i>
                        <?php echo $__vendeur_boutique_slug !== '' ? 'Ma boutique' : 'Le site'; ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- ===== ALERT SUCCESS ===== -->
        <?php if (!empty($success_message)): ?>
            <div class="prm-alert prm-alert--success" role="status">
                <i class="fas fa-circle-check"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- ===== BOUTIQUE VENDEUR ===== -->
        <?php if ($__param_role === 'vendeur'): ?>
            <div class="prm-boutique" role="region" aria-labelledby="prm-boutique-title">
                    <div class="prm-boutique__head">
                        <div class="prm-boutique__head-icon"><i class="fas fa-store"></i></div>
                        <div class="prm-boutique__head-text">
                            <h3 id="prm-boutique-title">Lien de votre boutique en ligne</h3>
                        </div>
                    </div>
                <div class="prm-boutique__body">
                    <?php if ($__vendeur_boutique_slug !== ''): ?>
                        <div class="prm-boutique__url-row">
                            <label class="visually-hidden" for="vendeurBoutiquePublicUrl">URL publique de la boutique</label>
                            <input type="text" id="vendeurBoutiquePublicUrl"
                                class="prm-boutique__url-input" readonly
                                value="<?php echo htmlspecialchars($__vendeur_site_full_url, ENT_QUOTES, 'UTF-8'); ?>"
                                autocomplete="off">
                            <div class="prm-boutique__actions">
                                <button type="button" class="prm-boutique__action-btn prm-boutique__action-btn--copy"
                                    id="vendeurCopyBoutiqueUrl">
                                    <i class="fas fa-copy"></i> Copier le lien
                                </button>
                                <button type="button" class="prm-boutique__action-btn prm-boutique__action-btn--share"
                                    id="vendeurShareBoutiqueUrl"
                                    aria-haspopup="dialog"
                                    aria-controls="platformShareModal">
                                    <i class="fas fa-share-alt"></i> Partager
                                </button>
                            </div>
                        </div>
                        <p id="vendeurCopyBoutiqueFeedback" class="prm-boutique__feedback" aria-live="polite"></p>
                    <?php else: ?>
                        <div class="prm-boutique__warn">
                            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i>
                            Aucun identifiant de boutique n&apos;est associ&eacute; &agrave; votre compte. D&eacute;finissez le nom et l&apos;URL
                            de votre boutique dans <a href="profil.php">votre profil administrateur</a>, puis reconnectez-vous si besoin.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php require __DIR__ . '/includes/partials/vendeur_boutique_localisation_ui.php'; ?>
        <?php endif; ?>

        <!-- ===== MODULES / RACCOURCIS ===== -->
        <?php if ($__param_show_site_modules): ?>

            <div class="prm-section-head">
                <h2 class="prm-section-head__title">Modules &agrave; configurer</h2>
                <?php if ($__param_role !== 'vendeur'): ?>
                    <p class="prm-section-head__meta">Cliquez sur &laquo;&nbsp;G&eacute;rer&nbsp;&raquo; pour ouvrir l&apos;&eacute;cran de configuration.</p>
                <?php endif; ?>
            </div>

            <div class="prm-modules-grid">

                <?php if ($__param_role !== 'vendeur'): ?>
                    <article class="prm-module-card prm-module-card--home">
                        <div class="prm-module-card__icon-wrap"><i class="fas fa-home"></i></div>
                        <div class="prm-module-card__title">Banni&egrave;re d&apos;Accueil</div>
                        <div class="prm-module-card__desc">
                            Personnalisez la banni&egrave;re principale&nbsp;: titre, accroche et image de fond pour une premi&egrave;re impression marquante.
                        </div>
                        <a href="parametres/section4.php" class="prm-module-card__link">
                            <i class="fas fa-pen-to-square"></i> Modifier la banni&egrave;re
                        </a>
                    </article>

                    <article class="prm-module-card prm-module-card--trending">
                        <div class="prm-module-card__icon-wrap"><i class="fas fa-star"></i></div>
                        <div class="prm-module-card__title">Section Mise en Avant</div>
                        <div class="prm-module-card__desc">
                            D&eacute;finissez le label, le titre promotionnel, le bouton d&apos;action et l&apos;image illustrative de la section vedette.
                        </div>
                        <a href="parametres/trending.php" class="prm-module-card__link">
                            <i class="fas fa-pen-to-square"></i> Modifier la section
                        </a>
                    </article>
                <?php endif; ?>

                <article class="prm-module-card prm-module-card--slider">
                    <div class="prm-module-card__icon-wrap"><i class="fas fa-images"></i></div>
                    <div class="prm-module-card__title">Images d&apos;affiche pub</div>
                    <?php if ($__param_role !== 'vendeur'): ?>
                        <div class="prm-module-card__desc">
                            G&eacute;rez le slider d&apos;images en haut de la page d&apos;accueil&nbsp;: ajout, modification, suppression des slides avec titres et boutons.
                        </div>
                    <?php endif; ?>
                    <a href="slider/index.php" class="prm-module-card__link">
                        <i class="fas fa-pen-to-square"></i> G&eacute;rer le slider
                    </a>
                </article>

                <?php if ($__param_role !== 'vendeur'): ?>
                    <article class="prm-module-card prm-module-card--video">
                        <div class="prm-module-card__icon-wrap"><i class="fas fa-video"></i></div>
                        <div class="prm-module-card__title">Section Vid&eacute;os</div>
                        <div class="prm-module-card__desc">
                            G&eacute;rez les vid&eacute;os du carrousel "Ils ont d&eacute;couvert ICON"&nbsp;: YouTube, Vimeo ou locales avec previews.
                        </div>
                        <a href="parametres/videos.php" class="prm-module-card__link">
                            <i class="fas fa-pen-to-square"></i> G&eacute;rer les vid&eacute;os
                        </a>
                    </article>

                    <article class="prm-module-card prm-module-card--logos">
                        <div class="prm-module-card__icon-wrap"><i class="fas fa-certificate"></i></div>
                        <div class="prm-module-card__title">Logos Partenaires</div>
                        <div class="prm-module-card__desc">
                            G&eacute;rez les logos affich&eacute;s en carrousel sur la page d&apos;accueil&nbsp;: ajout, modification, suppression.
                        </div>
                        <a href="parametres/logos.php" class="prm-module-card__link">
                            <i class="fas fa-pen-to-square"></i> G&eacute;rer les logos
                        </a>
                    </article>
                <?php endif; ?>

                <?php if ($__param_show_comptes): ?>
                    <article class="prm-module-card prm-module-card--comptes">
                        <div class="prm-module-card__icon-wrap"><i class="fas fa-user-shield"></i></div>
                        <div class="prm-module-card__title">Comptes d&apos;acc&egrave;s</div>
                        <a href="comptes/index.php" class="prm-module-card__link">
                            <i class="fas fa-pen-to-square"></i> G&eacute;rer les comptes
                        </a>
                    </article>
                <?php endif; ?>

            </div><!-- /.prm-modules-grid -->

        <?php else: ?>

            <div class="prm-section-head">
                <h2 class="prm-section-head__title">Raccourcis</h2>
                <p class="prm-section-head__meta">Acc&egrave;s rapide aux principales fonctionnalit&eacute;s de votre compte.</p>
            </div>

            <div class="prm-shortcuts">
                <a href="profil.php" class="prm-shortcut-card">
                    <div class="prm-shortcut-card__icon"><i class="fas fa-user-pen"></i></div>
                    <div class="prm-shortcut-card__body">
                        <span class="prm-shortcut-card__title">Mon profil</span>
                        <span class="prm-shortcut-card__sub">Modifier mes informations</span>
                    </div>
                </a>
                <?php if ($__param_show_comptes): ?>
                    <a href="comptes/index.php" class="prm-shortcut-card">
                        <div class="prm-shortcut-card__icon"><i class="fas fa-user-shield"></i></div>
                        <div class="prm-shortcut-card__body">
                            <span class="prm-shortcut-card__title">Comptes d&apos;acc&egrave;s</span>
                            <span class="prm-shortcut-card__sub">G&eacute;rer les administrateurs</span>
                        </div>
                    </a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($__param_retour); ?>" class="prm-shortcut-card">
                    <div class="prm-shortcut-card__icon"><i class="fas fa-gauge-high"></i></div>
                    <div class="prm-shortcut-card__body">
                        <span class="prm-shortcut-card__title">Tableau de bord</span>
                        <span class="prm-shortcut-card__sub">Retour &agrave; l&apos;accueil admin</span>
                    </div>
                </a>
            </div>

        <?php endif; ?>

    </div><!-- /.prm-page -->

    <?php require_once __DIR__ . '/includes/vendeur_share_boutique.php'; ?>
    <?php if (vendeur_share_boutique_is_available()): ?>
        <?php include dirname(__DIR__) . '/includes/partials/platform_share_modal.php'; ?>
        <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>" defer></script>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <?php if (vendeur_share_boutique_is_available()): ?>
    <?php
    vendeur_share_boutique_render_script([
        'open_button_ids' => ['vendeurShareBoutiqueUrl'],
        'external_copy_button_id' => 'vendeurCopyBoutiqueUrl',
        'feedback_id' => 'vendeurCopyBoutiqueFeedback',
    ]);
    ?>
    <?php endif; ?>

</body>
</html>
