<?php
/**
 * Gestion du stock — affichage direct des produits avec recherche/filtres/pagination
 * Programmation procédurale uniquement
 */

ob_start();

require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$__stock_role = function_exists('admin_normalize_role_for_route')
    ? admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin')
    : 'admin';
$stock_catalogue_vendeur_seul = ($__stock_role === 'vendeur' && function_exists('get_categories_platform_for_vendeur_stock'));

/**
 * Redirection sûre après POST (évite page blanche si sortie envoyée avant header).
 */
function stock_index_redirect() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $url = function_exists('admin_route_build_url')
        ? admin_route_build_url('stock/index.php')
        : '/admin/stock/index.php';
    header('Location: ' . $url, true, 303);
    exit;
}

// ---- Traitement POST (avant chargement lourd des modèles / HTML) ----
$add_produit_error_message = '';
$add_produit_post_error = false;
$open_add_modal = isset($_GET['open_add']) && $_GET['open_add'] === '1';
$open_edit_modal_id = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$cat_modal_error       = '';
$cat_modal_open        = false;
$cat_modal_nom         = '';
$cat_modal_description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['admin_add_produit'])) {
        require_once __DIR__ . '/../../controllers/controller_produits.php';
        require_once __DIR__ . '/../../includes/flash_toast.php';
        define('PRODUIT_DEFER_POST_CREATE_EXTERNAL', true);
        try {
            $add_result = process_add_produit();
        } catch (Throwable $e) {
            error_log('[admin/stock/index.php] process_add_produit: ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $add_result = ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du produit.'];
        }
        if (!empty($add_result['success'])) {
            flash_toast_push('success', $add_result['message'] ?? 'Produit enregistré.');
            require_once __DIR__ . '/../../includes/produit_post_create_deferred.php';
            $redirect_url = function_exists('admin_route_build_url')
                ? admin_route_build_url('stock/index.php')
                : '/admin/stock/index.php';
            $deferred_id = (int) ($add_result['produit_id'] ?? 0);
            $deferred_owner = (int) ($add_result['owner_admin'] ?? 0);
            $deferred_nom = (string) ($add_result['produit_nom'] ?? '');
            $deferred_statut = (string) ($add_result['produit_statut'] ?? 'actif');
            admin_redirect_then(function () use ($deferred_id, $deferred_owner, $deferred_nom, $deferred_statut) {
                produit_run_deferred_post_create($deferred_id, $deferred_owner, $deferred_nom, $deferred_statut);
            }, $redirect_url);
        }
        $add_produit_error_message = $add_result['message'] ?? 'Erreur lors de l\'ajout.';
        $add_produit_post_error = true;
        flash_toast_push('error', $add_produit_error_message);
    } elseif (!empty($_POST['admin_edit_produit'])) {
        $edit_pid = (int) ($_POST['produit_id'] ?? 0);
        if ($edit_pid > 0) {
            require_once __DIR__ . '/../../controllers/controller_produits.php';
            require_once __DIR__ . '/../../includes/flash_toast.php';
            try {
                $edit_result = process_update_produit($edit_pid);
            } catch (Throwable $e) {
                error_log('[admin/stock/index.php] process_update_produit: ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine());
                $edit_result = ['success' => false, 'message' => 'Erreur lors de la modification du produit.'];
            }
            if (!empty($edit_result['success'])) {
                flash_toast_push('success', $edit_result['message'] ?? 'Produit modifié.');
                stock_index_redirect();
            }
            flash_toast_queue_page('error', $edit_result['message'] ?? 'Erreur lors de la modification.');
            $open_edit_modal_id = $edit_pid;
        }
    } elseif (isset($_POST['stock_add_categorie']) && !$stock_catalogue_vendeur_seul) {
        $tok = $_POST['csrf_token'] ?? '';
        if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), (string) $tok)) {
            $cat_modal_error = 'Session expirée ou formulaire invalide. Veuillez réessayer.';
            $cat_modal_open  = true;
        } else {
            require_once __DIR__ . '/../../controllers/controller_categories.php';
            require_once __DIR__ . '/../../includes/flash_toast.php';
            $cat_modal_result = process_add_categorie();
            if (!empty($cat_modal_result['success'])) {
                flash_toast_push('success', $cat_modal_result['message'] ?? 'Catégorie créée.');
                stock_index_redirect();
            }
            $cat_modal_error       = $cat_modal_result['message'] ?? 'Une erreur est survenue.';
            $cat_modal_open        = true;
            $cat_modal_nom         = isset($_POST['nom']) ? trim((string) $_POST['nom']) : '';
            $cat_modal_description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
        }
    }
}

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_genres.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';

$fap_use_category_hierarchy = categories_hierarchy_enabled() && ($__stock_role === 'vendeur');
$vcat_prefill_sub = 0;
$vcat_prefill_generale = 0;
$vendeur_genre_ids_prefill = [];
$categorie_id_prefill = 0;

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
    require_once __DIR__ . '/../../includes/flash_toast.php';
    flash_toast_push('success', $success_message);
    $success_message = '';
}

// ---- Catégories pour le filtre ----
$vf_stock = function_exists('admin_vendeur_filter_id') ? admin_vendeur_filter_id() : null;
$stock_filtre_par_rayon = false;
$categories = [];
if ($vf_stock !== null && function_exists('get_generales_for_vendeur_stock_filter')) {
    $categories = get_generales_for_vendeur_stock_filter($vf_stock);
    $stock_filtre_par_rayon = !empty($categories);
}
if (empty($categories)) {
    $categories = function_exists('admin_categories_list_for_session')
        ? admin_categories_list_for_session()
        : get_all_categories();
}
$categories = is_array($categories) ? $categories : [];

// ---- Produits du vendeur connecté (ou tous pour admin plateforme) ----
$tous_produits = get_all_produits(null, $vf_stock);
$tous_produits = is_array($tous_produits) ? $tous_produits : [];

// ---- Paramètres de recherche & filtre ----
$recherche  = isset($_GET['search'])  ? trim((string)$_GET['search'])  : '';
$cat_filter = isset($_GET['cat_id'])  ? (int)$_GET['cat_id']           : 0;
$statut_filter = isset($_GET['statut']) ? trim($_GET['statut'])         : '';
$page       = max(1, isset($_GET['page']) ? (int)$_GET['page']         : 1);
$per_page   = 15;

// ---- Filtrage ----
$produits_filtres = array_values(array_filter($tous_produits, function($p) use ($recherche, $cat_filter, $statut_filter, $stock_filtre_par_rayon) {
    if ($cat_filter > 0) {
        if ($stock_filtre_par_rayon && function_exists('produit_rayon_id_resolu')) {
            if (produit_rayon_id_resolu($p) !== $cat_filter) {
                return false;
            }
        } elseif ((int)($p['categorie_id'] ?? 0) !== $cat_filter) {
            return false;
        }
    }
    if ($statut_filter !== '' && ($p['statut'] ?? '') !== $statut_filter) return false;
    if ($recherche !== '') {
        $needle = mb_strtolower($recherche);
        $hay    = mb_strtolower(implode(' ', [
            $p['nom'] ?? '', $p['description'] ?? '', $p['categorie_nom'] ?? '', $p['statut'] ?? ''
        ]));
        if (strpos($hay, $needle) === false) return false;
    }
    return true;
}));

// ---- Pagination ----
$nb_total_filtres = count($produits_filtres);
$nb_pages         = max(1, (int)ceil($nb_total_filtres / $per_page));
$page             = min($page, $nb_pages);
$offset_page      = ($page - 1) * $per_page;
$produits_page    = array_slice($produits_filtres, $offset_page, $per_page);

// ---- Stats globales ----
$nb_total  = count($tous_produits);
$nb_actif  = count(array_filter($tous_produits, fn($p) => ($p['statut'] ?? '') === 'actif'));
$nb_rupture= count(array_filter($tous_produits, fn($p) => ($p['statut'] ?? '') === 'rupture_stock'));
$nb_bloque = count(array_filter($tous_produits, fn($p) => ($p['statut'] ?? '') === 'bloque'));
$nb_inactif= count(array_filter($tous_produits, fn($p) => ($p['statut'] ?? '') === 'inactif'));

// Helper URL de pagination
function stock_pag_url(int $pg, string $search, int $cat, string $statut): string {
    $params = ['page' => $pg];
    if ($search !== '') $params['search'] = $search;
    if ($cat > 0)        $params['cat_id'] = $cat;
    if ($statut !== '')  $params['statut']  = $statut;
    return 'index.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock &mdash; Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== STOCK INDEX v2 ===== */
        .stk-page {
            max-width: 1120px;
            margin: 0 auto;
            padding: clamp(14px, 3.5vw, 32px) clamp(12px, 3.5vw, 22px) 90px;
            display: flex; flex-direction: column; gap: 20px;
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        /* ---- Header ---- */
        .stk-header {
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }

        .stk-header__left  { display: flex; flex-direction: column; gap: 3px; }

        .stk-header__eyebrow {
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--couleur-dominante, #059669); display: flex; align-items: center; gap: 5px;
        }

        .stk-header__title {
            font-size: clamp(1.25rem, 3vw, 1.7rem);
            font-weight: 800; color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.15; letter-spacing: -0.025em;
        }

        .stk-header__actions { display: flex; gap: 9px; align-items: center; flex-wrap: wrap; }

        /* ---- Boutons ---- */
        .stk-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 11px;
            font-size: 0.81rem; font-weight: 700;
            cursor: pointer; border: none;
            text-decoration: none; font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: all 0.2s; white-space: nowrap;
        }

        .stk-btn--primary {
            background: var(--couleur-dominante, #059669); color: #fff;
            box-shadow: 0 4px 14px color-mix(in srgb, var(--couleur-dominante, #059669) 28%, transparent);
        }
        .stk-btn--primary:hover {
            background: var(--couleur-dominante-hover, #047857); transform: translateY(-1px);
        }
        .stk-btn--outline { background: #fff; color: var(--couleur-dominante, #3564a6); border: 1.5px solid rgba(53,100,166,0.22); }
        .stk-btn--outline:hover { background: rgba(53,100,166,0.05); }
        .stk-btn--blue { background: var(--couleur-dominante, #3564a6); color: #fff; box-shadow: 0 4px 14px rgba(53,100,166,0.25); }
        .stk-btn--blue:hover { background: var(--couleur-dominante-hover, #2d5690); transform: translateY(-1px); }

        /* ---- Hero ---- */
        .stk-hero {
            background: var(--couleur-dominante, #059669);
            border-radius: 20px;
            padding: clamp(18px, 3vw, 32px);
            position: relative; overflow: hidden;
            box-shadow: 0 16px 40px color-mix(in srgb, var(--couleur-dominante, #059669) 34%, transparent);
        }

        .stk-hero::before {
            content: ''; position: absolute; top: -60px; right: -40px;
            width: 220px; height: 220px; background: rgba(255,255,255,0.06);
            border-radius: 50%; pointer-events: none;
        }

        .stk-hero::after {
            content: ''; position: absolute; bottom: -70px; right: 90px;
            width: 170px; height: 170px; background: rgba(255,255,255,0.04);
            border-radius: 50%; pointer-events: none;
        }

        .stk-hero__inner {
            display: flex; align-items: flex-start;
            justify-content: space-between; flex-wrap: wrap; gap: 14px; position: relative;
        }

        .stk-hero__badge-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .stk-hero__badge-row .vendeur-hero-cert {
            position: static;
            top: auto;
            right: auto;
        }

        .stk-hero__pills  { display: flex; gap: 9px; flex-wrap: wrap; margin-top: 0; }

        .stk-hero__pill {
            background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
            border-radius: 50px; padding: 6px 14px;
            display: flex; align-items: center; gap: 6px;
            color: #fff; font-size: 0.77rem; font-weight: 600;
        }

        .stk-hero__pill--ok  { background: rgba(134,239,172,.18); border-color: rgba(134,239,172,.3); }
        .stk-hero__pill--warn{ background: rgba(255,193,7,.15); border-color: rgba(255,193,7,.3); }
        .stk-hero__pill--err { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.25); }

        .stk-hero__cta {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 20px;
            background: rgba(255,255,255,.15); border: 1.5px solid rgba(255,255,255,.22);
            border-radius: 12px; color: #fff;
            font-size: 0.82rem; font-weight: 700;
            text-decoration: none; transition: background .2s; white-space: nowrap; align-self: flex-start;
            cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        .stk-hero__cta:hover { background: rgba(255,255,255,.26); }

        /* ---- Stat cards ---- */
        .stk-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 12px;
        }

        .stk-stat {
            background: #fff; border-radius: 15px;
            border: 1px solid rgba(53,100,166,.08);
            box-shadow: 0 2px 12px rgba(0,0,0,.04);
            padding: 16px 15px;
            display: flex; align-items: center; gap: 13px;
            transition: transform .2s, box-shadow .2s;
        }

        .stk-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(53,100,166,.1); }

        .stk-stat__icon {
            width: 42px; height: 42px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem; flex-shrink: 0;
        }

        .stk-stat--total   .stk-stat__icon {
            background: color-mix(in srgb, var(--couleur-dominante, #059669) 12%, transparent);
            color: var(--couleur-dominante, #059669);
        }
        .stk-stat--actif   .stk-stat__icon { background: rgba(34,197,94,.1);  color: #15803d; }
        .stk-stat--rupture .stk-stat__icon { background: rgba(239,68,68,.1);  color: #b91c1c; }
        .stk-stat--inactif .stk-stat__icon { background: rgba(156,163,175,.15); color: #6b7280; }

        .stk-stat__val { font-size: 1.55rem; font-weight: 900; color: var(--titres, #0d0d0d); line-height: 1.0; font-family: var(--font-titres, 'Poppins', sans-serif); }
        .stk-stat__lbl { font-size: 0.7rem; font-weight: 700; color: var(--gris-moyen, #737373); text-transform: uppercase; letter-spacing: .06em; }

        /* ---- Alert ---- */
        .stk-alert {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 13px 17px; border-radius: 13px;
            font-size: 0.83rem; font-weight: 500;
            background: rgba(34,197,94,.09); border: 1px solid rgba(34,197,94,.22); color: #15803d;
        }

        .stk-alert i { margin-top: 2px; font-size: 1rem; flex-shrink: 0; }

        /* ---- Barre de recherche & filtres ---- */
        .stk-filters {
            background: #fff; border-radius: 16px;
            border: 1px solid rgba(53,100,166,.08);
            box-shadow: 0 2px 12px rgba(0,0,0,.04);
            padding: 16px 18px;
        }

        .stk-filters__form {
            display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;
        }

        .stk-filters__group { display: flex; flex-direction: column; gap: 4px; }

        .stk-filters__label {
            font-size: 0.68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--gris-moyen, #737373);
        }

        .stk-filters__search {
            display: flex; align-items: center; gap: 0;
            border: 1.5px solid rgba(53,100,166,.18);
            border-radius: 10px; background: #f9fbff;
            overflow: hidden; transition: border-color .2s, box-shadow .2s;
            flex: 1; min-width: 200px;
        }

        .stk-filters__search:focus-within {
            border-color: var(--couleur-dominante, #3564a6);
            box-shadow: 0 0 0 3px rgba(53,100,166,.1);
        }

        .stk-filters__search-icon {
            padding: 0 12px; color: var(--gris-clair, #a3a3a3); font-size: .85rem;
        }

        .stk-filters__search input {
            flex: 1; border: none; background: transparent;
            padding: 10px 10px 10px 0; font-size: .86rem;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            color: var(--titres, #0d0d0d); outline: none;
        }

        .stk-filters__select {
            padding: 10px 14px; border-radius: 10px;
            border: 1.5px solid rgba(53,100,166,.18);
            background: #f9fbff; font-size: .86rem;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            color: var(--titres, #0d0d0d); outline: none;
            transition: border-color .2s, box-shadow .2s; cursor: pointer;
        }

        .stk-filters__select:focus {
            border-color: var(--couleur-dominante, #3564a6);
            box-shadow: 0 0 0 3px rgba(53,100,166,.1);
        }

        .stk-filters__submit {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; border-radius: 10px;
            background: var(--couleur-dominante, #059669); color: #fff;
            font-size: .82rem; font-weight: 700;
            border: none; cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: background .2s; white-space: nowrap;
        }

        .stk-filters__submit:hover { background: var(--couleur-dominante-hover, #047857); }

        .stk-filters__reset {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 16px; border-radius: 10px;
            background: rgba(0,0,0,.04); color: var(--gris-moyen, #737373);
            font-size: .8rem; font-weight: 600;
            border: none; cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            text-decoration: none; transition: background .2s;
        }

        .stk-filters__reset:hover { background: rgba(0,0,0,.08); color: var(--titres, #0d0d0d); }

        .stk-filters__results {
            margin-top: 10px; font-size: .78rem;
            color: var(--gris-moyen, #737373); display: flex; align-items: center; gap: 6px;
        }

        .stk-filters__results strong { color: var(--titres, #0d0d0d); }

        .stk-filters__search-row {
            display: flex;
            align-items: stretch;
            gap: 8px;
        }

        .stk-filters__toggle {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 0 12px;
            border-radius: 10px;
            border: 1.5px solid rgba(53,100,166,.18);
            background: #f9fbff;
            color: var(--couleur-dominante, #3564a6);
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: background .2s, border-color .2s;
            flex-shrink: 0;
        }

        .stk-filters__toggle:hover { background: rgba(53,100,166,.08); }
        .stk-filters__toggle--active {
            background: rgba(53,100,166,.12);
            border-color: var(--couleur-dominante, #3564a6);
        }

        .stk-filters__extra-head {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .stk-filters__extra-head span {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--gris-moyen, #737373);
        }

        .stk-filters__extra-close {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 10px;
            border-radius: 8px;
            border: none;
            background: rgba(0,0,0,.05);
            color: var(--gris-fonce, #4a4a4a);
            font-size: .72rem;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        .stk-filters__extra-close:hover { background: rgba(0,0,0,.09); }

        .stk-filters__extra-grid {
            display: contents;
        }

        /* ---- Modales produit (plein écran) ---- */
        .adm-modal-add-produit[hidden] { display: none !important; }
        .adm-modal-add-produit {
            position: fixed;
            inset: 0;
            z-index: 10100; /* au-dessus du dock vendeur (10050) */
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
            padding: clamp(12px, 2.5vw, 16px) clamp(14px, 3vw, 22px);
            background: var(--blanc, #fff);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        .adm-modal-add-head h2 {
            margin: 0;
            font-size: clamp(1rem, 2.8vw, 1.28rem);
            font-family: var(--font-titres, inherit);
            color: var(--titres, #0d0d0d);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .adm-modal-add-head h2 i { color: var(--couleur-dominante, #3564a6); }
        .adm-modal-add-close {
            width: clamp(36px, 8vw, 44px);
            height: clamp(36px, 8vw, 44px);
            border: none;
            border-radius: 12px;
            background: rgba(53, 100, 166, 0.1);
            color: var(--couleur-dominante, #3564a6);
            font-size: 1.5rem;
            line-height: 1;
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
            padding: clamp(14px, 3vw, 22px) clamp(14px, 3vw, 24px) clamp(24px, 5vw, 40px);
        }
        .adm-modal-add-body .form-add-container { max-width: 1280px; margin: 0 auto; }
        .adm-modal-add-body--iframe { padding: 0; overflow: hidden; }
        .adm-modal-add-body--iframe iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }

        @media (max-width: 1024px) {
            .adm-modal-add-head {
                padding: 10px 14px;
                gap: 10px;
            }
            .adm-modal-add-head h2 {
                font-size: clamp(0.92rem, 2.8vw, 1.1rem);
                gap: 7px;
            }
            .adm-modal-add-body {
                padding: 12px 12px calc(12px + env(safe-area-inset-bottom, 0px));
                scroll-padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px));
            }
            body.stk-produit-modal-open #adminVendeurBottomDock {
                visibility: hidden;
                pointer-events: none;
            }
        }

        @media (max-width: 640px) {
            .adm-modal-add-head {
                padding: 8px 10px;
            }
            .adm-modal-add-head h2 {
                font-size: 0.88rem;
                gap: 6px;
            }
            .adm-modal-add-head h2 i { font-size: 0.9rem; }
            .adm-modal-add-close {
                width: 32px;
                height: 32px;
                font-size: 1.2rem;
                border-radius: 8px;
            }
            .adm-modal-add-body {
                padding: 8px 8px calc(24px + env(safe-area-inset-bottom, 0px));
            }
            .adm-modal-add-body .form-add-container {
                max-width: none;
            }
        }

        .js-open-add-produit,
        .js-open-edit-produit { cursor: pointer; }

        /* ---- Overlay enregistrement produit ---- */
        .stk-save-overlay {
            position: fixed;
            inset: 0;
            z-index: 12050;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(13, 13, 13, 0.55);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .stk-save-overlay.is-visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .stk-save-overlay__card {
            width: min(100%, 380px);
            padding: clamp(22px, 5vw, 30px) clamp(18px, 4vw, 26px);
            border-radius: 20px;
            background: linear-gradient(165deg, #ffffff 0%, #f8fbff 55%, rgba(53, 100, 166, 0.06) 100%);
            border: 1px solid rgba(53, 100, 166, 0.14);
            box-shadow: 0 24px 60px rgba(53, 100, 166, 0.22);
            text-align: center;
            animation: stkSavePop 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }
        @keyframes stkSavePop {
            from { opacity: 0; transform: scale(0.92) translateY(12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .stk-save-overlay__icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 14px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--couleur-dominante, #3564a6), var(--bleu-clair, #4a7ab8));
            color: #fff;
            font-size: 1.35rem;
            box-shadow: 0 8px 24px rgba(53, 100, 166, 0.35);
        }
        .stk-save-overlay__icon i {
            animation: stkSaveSpin 1.1s linear infinite;
        }
        @keyframes stkSaveSpin {
            to { transform: rotate(360deg); }
        }
        .stk-save-overlay__title {
            margin: 0 0 6px;
            font-size: clamp(1rem, 3.2vw, 1.12rem);
            font-weight: 700;
            color: var(--titres, #0d0d0d);
        }
        .stk-save-overlay__subtitle {
            margin: 0 0 18px;
            font-size: clamp(0.78rem, 2.5vw, 0.88rem);
            color: var(--gris-moyen, #737373);
            line-height: 1.45;
        }
        .stk-save-overlay__track {
            height: 10px;
            border-radius: 999px;
            background: rgba(53, 100, 166, 0.12);
            overflow: hidden;
            margin-bottom: 8px;
        }
        .stk-save-overlay__bar {
            height: 100%;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--couleur-dominante, #3564a6), var(--orange, #ff6b35));
            transition: width 0.35s ease;
            box-shadow: 0 0 12px rgba(53, 100, 166, 0.35);
        }
        .stk-save-overlay__pct {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--couleur-dominante, #3564a6);
            letter-spacing: 0.04em;
        }
        .btn-fap-submit:disabled,
        .btn-fap-submit.is-busy {
            opacity: 0.65;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ---- Grille produits ---- */
        .stk-section-head {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }

        .stk-section-title {
            font-size: 1.02rem; font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            display: flex; align-items: center; gap: 8px;
        }

        .stk-section-title::before {
            content: ''; width: 4px; height: 17px; border-radius: 3px;
            background: var(--couleur-dominante, #059669); display: inline-block;
        }

        .stk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(195px, 1fr));
            gap: 14px;
        }

        /* ---- Carte produit ---- */
        .stk-card {
            background: #fff; border-radius: 17px;
            border: 1px solid rgba(53,100,166,.08);
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
            overflow: hidden; display: flex; flex-direction: column;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
        }

        .stk-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(53,100,166,.13); }

        /* Image */
        .stk-card__img-wrap {
            position: relative; aspect-ratio: 4/3; overflow: hidden; background: #f1f5f9;
        }

        .stk-card__share {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 3;
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.92);
            color: var(--couleur-dominante, #3564a6);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
            transition: transform 0.15s ease, background 0.15s ease;
        }

        .stk-card__share:hover {
            transform: scale(1.06);
            background: #fff;
        }

        .stk-card__share i {
            font-size: 0.85rem;
        }

        .stk-card__img { width: 100%; height: 100%; object-fit: cover; transition: transform .35s; }
        .stk-card:hover .stk-card__img { transform: scale(1.05); }

        /* Badge statut */
        .stk-card__badge {
            position: absolute; top: 8px; right: 8px;
            font-size: .66rem; font-weight: 700;
            padding: 3px 9px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 4px;
            backdrop-filter: blur(6px);
        }

        .stk-card__badge--actif    { background: rgba(34,197,94,.18); color: #fff; border: 1px solid rgba(134,239,172,.35); }
        .stk-card__badge--inactif  { background: rgba(0,0,0,.45); color: rgba(255,255,255,.8); }
        .stk-card__badge--rupture_stock { background: rgba(239,68,68,.18); color: #fff; border: 1px solid rgba(239,68,68,.4); }
        .stk-card__badge--bloque { background: rgba(255,107,53,.25); color: #fff; border: 1px solid rgba(255,107,53,.5); }
        .stk-card--bloque { opacity: 0.92; }
        .stk-card--bloque .stk-card__img-wrap::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(13,13,13,.55));
            pointer-events: none;
        }
        .stk-card__bloque-msg {
            margin: 0 0 8px;
            padding: 8px 10px;
            font-size: 0.72rem;
            line-height: 1.35;
            color: #b45309;
            background: rgba(255, 107, 53, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(255, 107, 53, 0.25);
        }

        /* Stock bas */
        .stk-card__stock-bar {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 5px 10px;
            background: linear-gradient(0deg, rgba(0,0,0,.55) 0%, transparent 100%);
            color: #fff; font-size: .69rem; font-weight: 700;
            display: flex; align-items: center; gap: 4px;
        }

        .stk-card__stock-bar.low { color: #fca5a5; }
        .stk-card__stock-bar.empty { color: #f87171; }

        /* Body */
        .stk-card__body { padding: 13px 14px 11px; flex: 1; display: flex; flex-direction: column; gap: 5px; }

        .stk-card__name {
            font-size: .88rem; font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.2;
            overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;
        }

        .stk-card__cat {
            font-size: .71rem; color: var(--gris-moyen, #737373); font-weight: 600;
        }

        .stk-card__prix-row { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 8px; margin-top: 2px; }
        .stk-card__prix-groupe { display: inline-flex; align-items: baseline; gap: 4px; }
        .stk-card__prix     { font-size: 1.02rem; font-weight: 900; color: var(--titres, #0d0d0d); font-family: var(--font-titres, 'Poppins', sans-serif); }
        .stk-card__unit     { font-size: .65rem; font-weight: 600; color: var(--gris-moyen, #737373); }
        .stk-card__prix-groupe--ancien .stk-card__prix {
            font-size: .78rem; font-weight: 600;
            color: var(--gris-moyen, #737373);
            text-decoration: line-through;
        }
        .stk-card__prix-groupe--ancien .stk-card__unit {
            font-size: .58rem;
            color: var(--gris-clair, #a3a3a3);
            text-decoration: line-through;
        }

        /* Footer */
        .stk-card__footer {
            display: flex; gap: 7px;
            padding: 10px 14px;
            border-top: 1px solid rgba(53,100,166,.07);
            background: rgba(53,100,166,.02);
        }

        .stk-card-btn {
            flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            padding: 7px 10px; border-radius: 8px;
            font-size: .75rem; font-weight: 700;
            text-decoration: none; border: none; cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: all .18s;
        }

        .stk-card-btn--stock  {
            background: color-mix(in srgb, var(--couleur-dominante, #059669) 10%, transparent);
            color: var(--couleur-dominante, #059669);
        }
        .stk-card-btn--stock:hover  { background: var(--couleur-dominante, #059669); color: #fff; }
        .stk-card-btn--edit   { background: rgba(53,100,166,.08); color: var(--couleur-dominante, #3564a6); }
        .stk-card-btn--edit:hover   { background: var(--couleur-dominante, #3564a6); color: #fff; }
        .stk-card-btn--delete { background: rgba(239,68,68,.08); color: #b91c1c; }
        .stk-card-btn--delete:hover { background: #ef4444; color: #fff; }

        /* ---- Pagination ---- */
        .stk-pagination {
            display: flex; align-items: center; justify-content: center;
            gap: 6px; flex-wrap: wrap;
        }

        .stk-pag-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 10px;
            font-size: .82rem; font-weight: 700;
            text-decoration: none; color: var(--titres, #0d0d0d);
            background: #fff; border: 1.5px solid rgba(53,100,166,.14);
            transition: all .18s; font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        .stk-pag-btn:hover { background: rgba(53,100,166,.07); border-color: var(--couleur-dominante, #3564a6); }
        .stk-pag-btn--active {
            background: var(--couleur-dominante, #059669); color: #fff;
            border-color: var(--couleur-dominante, #059669);
            box-shadow: 0 4px 10px color-mix(in srgb, var(--couleur-dominante, #059669) 30%, transparent);
        }
        .stk-pag-btn--prev, .stk-pag-btn--next { width: auto; padding: 0 14px; gap: 5px; }
        .stk-pag-btn--disabled { opacity: .38; pointer-events: none; }

        .stk-pag-info { font-size: .78rem; color: var(--gris-moyen, #737373); padding: 0 6px; }

        /* ---- Empty state ---- */
        .stk-empty {
            background: #fff; border-radius: 18px;
            border: 1px solid rgba(53,100,166,.08);
            padding: 54px 24px; text-align: center;
            color: var(--gris-moyen, #737373);
        }

        .stk-empty__icon {
            width: 68px; height: 68px; border-radius: 18px;
            background: color-mix(in srgb, var(--couleur-dominante, #059669) 8%, transparent);
            color: var(--couleur-dominante, #059669);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; margin: 0 auto 16px;
        }

        .stk-empty h3 { font-size: 1rem; font-weight: 700; color: var(--titres, #0d0d0d); margin-bottom: 6px; }
        .stk-empty p  { font-size: .84rem; max-width: 340px; margin: 0 auto 18px; }

        /* ===== MODAL CATÉGORIE (repris du design original) ===== */
        .stock-cat-modal {
            position: fixed; inset: 0; z-index: 10050;
            display: flex; align-items: center; justify-content: center;
            padding: clamp(.75rem,3vw,1.5rem);
            background: rgba(13,13,13,.55);
            backdrop-filter: blur(10px);
            opacity: 0; visibility: hidden;
            transition: opacity .28s ease, visibility .28s ease;
        }

        .stock-cat-modal.stock-cat-modal--open { opacity: 1; visibility: visible; }

        .stock-cat-modal__panel {
            width: 100%; max-width: 32rem; max-height: min(92vh,46rem);
            overflow: hidden; display: flex; flex-direction: column;
            background: var(--fond-principal, #fff);
            border-radius: 22px;
            box-shadow: 0 25px 60px rgba(53,100,166,.22), 0 0 0 1px rgba(53,100,166,.12);
            transform: translateY(12px) scale(.98);
            transition: transform .32s cubic-bezier(.34,1.2,.64,1);
        }

        .stock-cat-modal.stock-cat-modal--open .stock-cat-modal__panel { transform: translateY(0) scale(1); }

        .stock-cat-modal__head {
            flex-shrink: 0; padding: 1.35rem 1.35rem 1rem;
            background: var(--couleur-dominante, #3564a6);
            color: #fff; position: relative;
        }

        .stock-cat-modal__head-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,.2); margin-bottom: .75rem; font-size: 1.25rem;
        }

        .stock-cat-modal__head h2 { margin: 0; font-size: 1.35rem; font-weight: 700; }
        .stock-cat-modal__head p  { margin: .4rem 0 0; font-size: .88rem; opacity: .92; line-height: 1.45; }

        .stock-cat-modal__close {
            position: absolute; top: 1rem; right: 1rem;
            width: 42px; height: 42px; border: none; border-radius: 12px;
            background: rgba(255,255,255,.18); color: #fff;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; transition: background .2s, transform .2s;
        }

        .stock-cat-modal__close:hover { background: rgba(255,255,255,.28); transform: scale(1.05); }

        .stock-cat-modal__body { flex: 1; overflow-y: auto; padding: 1.35rem 1.35rem 1.5rem; }

        .stock-cat-modal__err {
            display: flex; gap: .65rem; padding: .85rem 1rem; margin-bottom: 1.15rem;
            border-radius: 12px; background: rgba(255,107,53,.1); border: 1px solid rgba(255,107,53,.2);
            color: var(--titres, #0d0d0d); font-size: .88rem; line-height: 1.45;
        }

        .stock-cat-modal__err i { flex-shrink: 0; margin-top: .1rem; color: var(--orange,#FF6B35); }

        .stock-cat-field { margin-bottom: 1.15rem; }

        .stock-cat-field label {
            display: flex; align-items: center; gap: .35rem;
            font-size: .82rem; font-weight: 600; color: var(--titres,#0d0d0d); margin-bottom: .45rem;
        }

        .stock-cat-field label .hint { font-weight: 400; color: var(--gris-moyen,#737373); }

        .stock-cat-field input[type="text"],
        .stock-cat-field textarea {
            width: 100%; padding: .85rem 1rem;
            border: 2px solid rgba(53,100,166,.18); border-radius: 12px;
            font-size: .95rem; font-family: var(--font-corps,inherit);
            color: var(--titres,#0d0d0d); background: var(--fond-principal,#fff);
            transition: border-color .2s, box-shadow .2s; box-sizing: border-box;
        }

        .stock-cat-field textarea { min-height: 6.5rem; resize: vertical; }

        .stock-cat-field input:focus, .stock-cat-field textarea:focus {
            outline: none; border-color: var(--couleur-dominante,#3564a6);
            box-shadow: 0 0 0 4px rgba(53,100,166,.1);
        }

        .stock-cat-file {
            position: relative; border: 2px dashed rgba(53,100,166,.22);
            border-radius: 14px; padding: 1.1rem 1rem; text-align: center;
            background: #fafbff; transition: border-color .2s, background .2s;
        }

        .stock-cat-file:hover { border-color: var(--couleur-dominante,#3564a6); background: rgba(53,100,166,.04); }

        .stock-cat-file input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .stock-cat-file__hint { font-size: .8rem; color: var(--gris-moyen,#737373); margin-top: .35rem; }

        .stock-cat-modal__actions {
            display: flex; flex-wrap: wrap; gap: .65rem;
            margin-top: 1.25rem; padding-top: 1.1rem;
            border-top: 1px solid rgba(53,100,166,.1);
        }

        .stock-cat-modal__btn {
            flex: 1 1 auto; min-width: 8rem;
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            padding: .85rem 1.1rem; border-radius: 12px;
            font-weight: 600; font-size: .92rem;
            cursor: pointer; font-family: inherit; border: none; transition: transform .18s;
        }

        .stock-cat-modal__btn--ghost {
            background: #f9fafb; color: var(--gris-fonce,#4a4a4a);
            border: 2px solid rgba(53,100,166,.18);
        }

        .stock-cat-modal__btn--ghost:hover { border-color: var(--couleur-dominante,#3564a6); color: var(--couleur-dominante,#3564a6); }

        .stock-cat-modal__btn--primary {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
            box-shadow: 0 4px 14px color-mix(in srgb, var(--couleur-dominante, #3564a6) 28%, transparent);
        }

        .stock-cat-modal__btn--primary:hover { transform: translateY(-2px); }

        body.stock-cat-modal-active { overflow: hidden; }

        /* ---- Responsive ---- */
        @media (max-width: 1024px) {
            .stk-filters {
                padding: clamp(10px, 2.5vw, 14px) clamp(10px, 2.5vw, 12px);
            }

            .stk-filters__form {
                flex-direction: column;
                align-items: stretch;
                gap: 0;
            }

            .stk-filters__compact { width: 100%; }

            .stk-filters__group--search { gap: 3px; }

            .stk-filters__label--compact {
                font-size: 0.62rem;
                margin-bottom: 1px;
            }

            .stk-filters__search-row { width: 100%; }

            .stk-filters__search {
                min-width: 0;
                flex: 1;
            }

            .stk-filters__search input {
                padding: 8px 8px 8px 0;
                font-size: 0.8rem;
            }

            .stk-filters__search-icon {
                padding: 0 10px;
                font-size: 0.78rem;
            }

            .stk-filters__toggle { display: inline-flex; }

            .stk-filters__extra {
                display: none;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid rgba(53,100,166,.08);
            }

            .stk-filters__extra.stk-filters__extra--open { display: block; }

            .stk-filters__extra-head { display: flex; }

            .stk-filters__extra-grid {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .stk-filters__select {
                width: 100%;
                padding: 8px 12px;
                font-size: 0.8rem;
            }

            .stk-filters__label {
                font-size: 0.62rem;
            }

            .stk-filters__submit,
            .stk-filters__reset {
                width: 100%;
                justify-content: center;
                padding: 8px 14px;
                font-size: 0.76rem;
            }

            .stk-filters__actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (min-width: 1025px) {
            .stk-filters__form {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-end;
                gap: 10px;
            }

            .stk-filters__compact { flex: 2; min-width: 200px; }

            .stk-filters__extra { display: contents; }
            .stk-filters__extra-grid { display: contents; }
        }

        @media (max-width: 768px) {
            .stk-page { gap: 16px; padding-bottom: 96px; }

            .stk-header {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .stk-header__actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .stk-header__actions .stk-btn {
                width: 100%;
                justify-content: center;
                padding: 8px 10px;
                font-size: 0.74rem;
            }

            .stk-hero {
                padding: 16px 14px;
                border-radius: 16px;
            }

            .stk-hero__inner {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .stk-hero__pills {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                margin-top: 10px;
            }

            .stk-hero__pill {
                justify-content: center;
                padding: 7px 10px;
                font-size: 0.7rem;
                border-radius: 10px;
            }

            .stk-hero__cta {
                width: 100%;
                justify-content: center;
                padding: 9px 12px;
                font-size: 0.74rem;
            }

            .stk-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .stk-stat {
                padding: 12px 10px;
                border-radius: 14px;
                gap: 10px;
            }

            .stk-stat__icon { width: 36px; height: 36px; font-size: 0.85rem; }
            .stk-stat__val { font-size: 1.25rem; }
            .stk-stat__lbl { font-size: 0.62rem; }

            .stk-filters {
                padding: 14px 12px;
                border-radius: 14px;
            }
        }

        @media (max-width: 640px) {
            .stk-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }

            .stk-card__body { padding: 10px 10px 8px; }

            .stk-card__prix-row {
                gap: 4px 6px;
            }

            .stk-card__prix {
                font-size: 0.88rem;
                line-height: 1.25;
            }

            .stk-card__prix-groupe--ancien .stk-card__prix {
                font-size: 0.72rem;
            }
        }

        @media (max-width: 380px) {
            .stk-grid { grid-template-columns: 1fr; }
            .stk-header__actions { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body class="<?php echo ($cat_modal_open && !$stock_catalogue_vendeur_seul) ? 'stock-cat-modal-active' : ''; ?>">
    <?php include '../includes/nav.php'; ?>

    <div class="stk-page">

        <!-- ===== HEADER ===== -->
        <header class="stk-header">
            <div class="stk-header__left">
                <p class="stk-header__eyebrow"><i class="fas fa-boxes-stacked"></i> Inventaire &amp; stock</p>
                <h1 class="stk-header__title">Gestion du stock</h1>
            </div>
        </header>

        <!-- ===== HERO ===== -->
        <div class="stk-hero">
            <div class="stk-hero__inner">
                <div class="stk-hero__badge-row">
                    <?php require __DIR__ . '/../../includes/partials/vendeur_certification_hero_badge.php'; ?>
                    <div class="stk-hero__pills">
                        <div class="stk-hero__pill stk-hero__pill--ok">
                            <i class="fas fa-circle-check" style="font-size:.68rem;"></i>
                            <strong><?php echo $nb_actif; ?></strong> actif<?php echo $nb_actif > 1 ? 's' : ''; ?>
                        </div>
                        <?php if ($nb_rupture > 0): ?>
                            <div class="stk-hero__pill stk-hero__pill--err">
                                <i class="fas fa-triangle-exclamation" style="font-size:.68rem;"></i>
                                <strong><?php echo $nb_rupture; ?></strong> rupture<?php echo $nb_rupture > 1 ? 's' : ''; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($nb_inactif > 0): ?>
                            <div class="stk-hero__pill">
                                <i class="fas fa-eye-slash" style="font-size:.68rem;"></i>
                                <strong><?php echo $nb_inactif; ?></strong> inactif<?php echo $nb_inactif > 1 ? 's' : ''; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="stk-hero__cta js-open-add-produit">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </button>
            </div>
        </div>

        <!-- ===== STAT CARDS ===== -->
        <div class="stk-stats">
            <div class="stk-stat stk-stat--total">
                <div class="stk-stat__icon"><i class="fas fa-boxes-stacked"></i></div>
                <div><div class="stk-stat__val"><?php echo $nb_total; ?></div><div class="stk-stat__lbl">Total</div></div>
            </div>
            <div class="stk-stat stk-stat--actif">
                <div class="stk-stat__icon"><i class="fas fa-eye"></i></div>
                <div><div class="stk-stat__val"><?php echo $nb_actif; ?></div><div class="stk-stat__lbl">Actifs</div></div>
            </div>
            <div class="stk-stat stk-stat--rupture">
                <div class="stk-stat__icon"><i class="fas fa-triangle-exclamation"></i></div>
                <div><div class="stk-stat__val"><?php echo $nb_rupture; ?></div><div class="stk-stat__lbl">Ruptures</div></div>
            </div>
            <div class="stk-stat stk-stat--inactif">
                <div class="stk-stat__icon"><i class="fas fa-eye-slash"></i></div>
                <div><div class="stk-stat__val"><?php echo $nb_inactif; ?></div><div class="stk-stat__lbl">Inactifs</div></div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <?php $stk_filters_active = ($cat_filter > 0 || $statut_filter !== ''); ?>
        <div class="stk-filters">
            <form method="get" action="index.php" class="stk-filters__form" id="stk-filter-form">
                <div class="stk-filters__compact">
                    <div class="stk-filters__group stk-filters__group--search">
                        <label class="stk-filters__label stk-filters__label--compact" for="stk-search">Recherche</label>
                        <div class="stk-filters__search-row">
                            <div class="stk-filters__search">
                                <span class="stk-filters__search-icon"><i class="fas fa-magnifying-glass"></i></span>
                                <input type="text" id="stk-search" name="search"
                                    value="<?php echo htmlspecialchars($recherche); ?>"
                                    placeholder="Nom, description, statut&hellip;">
                            </div>
                            <button type="button"
                                class="stk-filters__toggle<?php echo $stk_filters_active ? ' stk-filters__toggle--active' : ''; ?>"
                                id="stkFiltersToggle"
                                aria-expanded="<?php echo $stk_filters_active ? 'true' : 'false'; ?>"
                                aria-controls="stkFiltersExtra">
                                <i class="fas fa-sliders"></i><span>Filtres</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__extra<?php echo $stk_filters_active ? ' stk-filters__extra--open' : ''; ?>"
                    id="stkFiltersExtra">
                    <div class="stk-filters__extra-head">
                        <span>Filtres avanc&eacute;s</span>
                        <button type="button" class="stk-filters__extra-close" id="stkFiltersClose">
                            <i class="fas fa-xmark"></i> Masquer
                        </button>
                    </div>
                    <div class="stk-filters__extra-grid">
                        <div class="stk-filters__group">
                            <label class="stk-filters__label" for="stk-cat">Cat&eacute;gorie</label>
                            <select id="stk-cat" name="cat_id" class="stk-filters__select">
                                <option value="0">Toutes les cat&eacute;gories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo (int)$cat['id']; ?>"
                                        <?php echo $cat_filter === (int)$cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="stk-filters__group">
                            <label class="stk-filters__label" for="stk-statut">Statut</label>
                            <select id="stk-statut" name="statut" class="stk-filters__select">
                                <option value="">Tous les statuts</option>
                                <option value="actif"         <?php echo $statut_filter === 'actif'          ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif"       <?php echo $statut_filter === 'inactif'        ? 'selected' : ''; ?>>Inactif</option>
                                <option value="rupture_stock" <?php echo $statut_filter === 'rupture_stock'  ? 'selected' : ''; ?>>Rupture de stock</option>
                                <option value="bloque" <?php echo $statut_filter === 'bloque' ? 'selected' : ''; ?>>Bloqué (plateforme)</option>
                            </select>
                        </div>

                        <div class="stk-filters__group stk-filters__actions">
                            <button type="submit" class="stk-filters__submit">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <?php if ($recherche !== '' || $cat_filter > 0 || $statut_filter !== ''): ?>
                                <a href="index.php" class="stk-filters__reset">
                                    <i class="fas fa-xmark"></i> R&eacute;initialiser
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ===== GRILLE PRODUITS ===== -->
        <?php if (empty($produits_page)): ?>
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="fas fa-box-open"></i></div>
                <h3><?php echo ($recherche !== '' || $cat_filter > 0 || $statut_filter !== '') ? 'Aucun produit trouv&eacute;' : 'Aucun produit enregistr&eacute;'; ?></h3>
                <p>
                    <?php if ($recherche !== '' || $cat_filter > 0 || $statut_filter !== ''): ?>
                        Essayez d&apos;autres crit&egrave;res de recherche ou <a href="index.php" style="color:var(--couleur-dominante,#059669);font-weight:600;">r&eacute;initialisez les filtres</a>.
                    <?php else: ?>
                        Ajoutez votre premier produit pour commencer &agrave; g&eacute;rer votre stock.
                    <?php endif; ?>
                </p>
                <button type="button" class="stk-btn stk-btn--primary js-open-add-produit">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </button>
            </div>

        <?php else: ?>

            <?php require_once __DIR__ . '/../../includes/product_share.php'; ?>

            <h2 class="stk-section-title">Produits (<?php echo $nb_total_filtres; ?>)</h2>

            <div class="stk-grid">
                <?php foreach ($produits_page as $produit):
                    $statut_p   = $produit['statut'] ?? 'inactif';
                    $is_bloque  = ($statut_p === 'bloque');
                    $stock_nb   = (int)($produit['stock'] ?? 0);
                    $stock_cls  = $stock_nb === 0 ? 'empty' : ($stock_nb <= 3 ? 'low' : '');
                    $badge_lbl  = match($statut_p) {
                        'actif'         => 'Actif',
                        'inactif'       => 'Inactif',
                        'rupture_stock' => 'Rupture',
                        'bloque'        => 'Bloqué',
                        default         => ucfirst($statut_p),
                    };
                    $bloque_champs_lbl = $is_bloque && function_exists('produit_bloque_champs_labels')
                        ? produit_bloque_champs_labels((string) ($produit['bloque_champs'] ?? ''))
                        : [];
                ?>
                    <article class="stk-card js-open-edit-produit<?php echo $is_bloque ? ' stk-card--bloque' : ''; ?>"
                        data-produit-id="<?php echo (int)$produit['id']; ?>"
                        role="button"
                        tabindex="0">

                        <div class="stk-card__img-wrap">
                            <?php
                            $stk_pid = (int) ($produit['id'] ?? 0);
                            $stk_share_url = product_share_abs_url($stk_pid);
                            $stk_share_text = product_share_text_short($produit);
                            $stk_share_nom = (string) ($produit['nom'] ?? 'Produit');
                            ?>
                            <?php if ($stk_share_url !== ''): ?>
                            <button type="button"
                                class="stk-card__share pshare__toggle"
                                aria-label="Partager <?php echo htmlspecialchars($stk_share_nom, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-url="<?php echo htmlspecialchars($stk_share_url, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-title="<?php echo htmlspecialchars($stk_share_nom, ENT_QUOTES, 'UTF-8'); ?>"
                                data-share-text="<?php echo htmlspecialchars($stk_share_text, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                            </button>
                            <?php endif; ?>
                            <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'sm')); ?>"
                                alt="<?php echo htmlspecialchars($produit['nom'] ?? ''); ?>"
                                class="stk-card__img"
                                onerror="this.src='/image/produit1.jpg'">

                            <span class="stk-card__badge stk-card__badge--<?php echo htmlspecialchars($statut_p); ?>">
                                <?php echo $badge_lbl; ?>
                            </span>

                            <div class="stk-card__stock-bar <?php echo $stock_cls; ?>">
                                <i class="fas fa-warehouse" style="font-size:.65rem;"></i>
                                Stock&nbsp;<strong><?php echo $stock_nb; ?></strong>
                            </div>
                        </div>

                        <div class="stk-card__body">
                            <div class="stk-card__name"><?php echo htmlspecialchars($produit['nom'] ?? ''); ?></div>
                            <?php if ($is_bloque): ?>
                                <p class="stk-card__bloque-msg">
                                    <i class="fas fa-ban"></i> <strong>Bloqué</strong> par la plateforme.
                                    <?php if (!empty($bloque_champs_lbl)): ?>
                                        Modifiez : <?php echo htmlspecialchars(implode(', ', $bloque_champs_lbl), ENT_QUOTES, 'UTF-8'); ?>.
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <div class="stk-card__cat"><?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans cat&eacute;gorie'); ?></div>
                            <div class="stk-card__prix-row">
                                <?php if (!empty($produit['prix_promotion'])): ?>
                                    <span class="stk-card__prix-groupe stk-card__prix-groupe--ancien">
                                        <span class="stk-card__prix"><?php echo number_format((float)($produit['prix'] ?? 0), 0, ',', ' '); ?></span>
                                        <span class="stk-card__unit">FCFA</span>
                                    </span>
                                    <span class="stk-card__prix-groupe">
                                        <span class="stk-card__prix"><?php echo number_format((float)$produit['prix_promotion'], 0, ',', ' '); ?></span>
                                        <span class="stk-card__unit">FCFA</span>
                                    </span>
                                <?php else: ?>
                                    <span class="stk-card__prix-groupe">
                                        <span class="stk-card__prix"><?php echo number_format((float)($produit['prix'] ?? 0), 0, ',', ' '); ?></span>
                                        <span class="stk-card__unit">FCFA</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="stk-card__footer" onclick="event.stopPropagation()">
                            <button type="button"
                                class="stk-card-btn stk-card-btn--edit js-open-edit-produit"
                                data-produit-id="<?php echo (int)$produit['id']; ?>">
                                <i class="fas fa-pen"></i> Modifier
                            </button>
                            <a href="../produits/supprimer.php?id=<?php echo (int)$produit['id']; ?>"
                                class="stk-card-btn stk-card-btn--delete"
                                onclick="event.stopPropagation();return confirm('Supprimer ce produit ?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- ===== PAGINATION ===== -->
            <?php if ($nb_pages > 1): ?>
                <nav class="stk-pagination" aria-label="Pagination">

                    <!-- Précédent -->
                    <?php if ($page > 1): ?>
                        <a href="<?php echo stock_pag_url($page - 1, $recherche, $cat_filter, $statut_filter); ?>"
                            class="stk-pag-btn stk-pag-btn--prev">
                            <i class="fas fa-chevron-left"></i> Pr&eacute;c.
                        </a>
                    <?php else: ?>
                        <span class="stk-pag-btn stk-pag-btn--prev stk-pag-btn--disabled">
                            <i class="fas fa-chevron-left"></i> Pr&eacute;c.
                        </span>
                    <?php endif; ?>

                    <!-- Pages numérotées -->
                    <?php
                    $window = 2;
                    for ($i = 1; $i <= $nb_pages; $i++):
                        if ($i === 1 || $i === $nb_pages || abs($i - $page) <= $window):
                    ?>
                        <?php if (abs($i - $page) === $window + 1 && $i !== 1 && $i !== $nb_pages): ?>
                            <span class="stk-pag-info">&hellip;</span>
                        <?php endif; ?>
                        <a href="<?php echo stock_pag_url($i, $recherche, $cat_filter, $statut_filter); ?>"
                            class="stk-pag-btn <?php echo $i === $page ? 'stk-pag-btn--active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; endfor; ?>

                    <!-- Suivant -->
                    <?php if ($page < $nb_pages): ?>
                        <a href="<?php echo stock_pag_url($page + 1, $recherche, $cat_filter, $statut_filter); ?>"
                            class="stk-pag-btn stk-pag-btn--next">
                            Suiv. <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="stk-pag-btn stk-pag-btn--next stk-pag-btn--disabled">
                            Suiv. <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>

                </nav>
            <?php endif; ?>

        <?php endif; ?>

    </div><!-- /.stk-page -->

    <?php
    $add_produit_modal = true;
    $add_produit_form_action = function_exists('admin_route_build_url')
        ? admin_route_build_url('stock/index.php')
        : 'index.php';
    $modal_add_should_show = $add_produit_post_error || $open_add_modal;
    $modal_edit_should_show = $open_edit_modal_id > 0;
    ?>
    <div id="modalAddProduit" class="adm-modal-add-produit" <?php echo $modal_add_should_show ? '' : 'hidden'; ?>
        aria-hidden="<?php echo $modal_add_should_show ? 'false' : 'true'; ?>">
        <div class="adm-modal-add-produit-inner">
            <div class="adm-modal-add-head">
                <h2><i class="fas fa-plus-circle"></i> Ajouter un produit</h2>
                <button type="button" class="adm-modal-add-close" id="btnCloseAddProduitModal" title="Fermer" aria-label="Fermer">&times;</button>
            </div>
            <div class="adm-modal-add-body">
                <div class="form-add-container">
                    <?php require __DIR__ . '/../produits/inc_form_ajouter_produit.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="modalEditProduit" class="adm-modal-add-produit" <?php echo $modal_edit_should_show ? '' : 'hidden'; ?>
        aria-hidden="<?php echo $modal_edit_should_show ? 'false' : 'true'; ?>">
        <div class="adm-modal-add-produit-inner">
            <div class="adm-modal-add-head">
                <h2><i class="fas fa-edit"></i> Modifier le produit</h2>
                <button type="button" class="adm-modal-add-close" id="btnCloseEditProduitModal" title="Fermer" aria-label="Fermer">&times;</button>
            </div>
            <div class="adm-modal-add-body adm-modal-add-body--iframe">
                <iframe id="stkEditIframe" title="Modifier le produit" src="<?php echo $modal_edit_should_show ? '../produits/embed_modifier.php?id=' . (int) $open_edit_modal_id : 'about:blank'; ?>"></iframe>
            </div>
        </div>
    </div>

    <!-- ===== MODAL NOUVELLE CATÉGORIE ===== -->
    <?php if (!$stock_catalogue_vendeur_seul): ?>
    <div class="stock-cat-modal<?php echo $cat_modal_open ? ' stock-cat-modal--open' : ''; ?>"
        id="stockCatModal" role="dialog" aria-modal="true"
        aria-labelledby="stockCatModalTitle"<?php echo $cat_modal_open ? '' : ' hidden'; ?>>
        <div class="stock-cat-modal__panel" role="document">
            <div class="stock-cat-modal__head">
                <button type="button" class="stock-cat-modal__close js-close-stock-cat-modal" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
                <div class="stock-cat-modal__head-icon"><i class="fas fa-folder-plus"></i></div>
                <h2 id="stockCatModalTitle">Nouvelle cat&eacute;gorie</h2>
                <p>Renseignez le nom de votre rayon. L&rsquo;image et la description sont optionnelles.</p>
            </div>
            <div class="stock-cat-modal__body">
                <?php if ($cat_modal_error !== ''): ?>
                    <div class="stock-cat-modal__err" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $cat_modal_error; ?></span>
                    </div>
                <?php endif; ?>
                <form method="post" action="" enctype="multipart/form-data" id="stockCatModalForm">
                    <input type="hidden" name="stock_add_categorie" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="stock-cat-field">
                        <label for="stock_cat_nom">Nom <span class="hint">(obligatoire)</span></label>
                        <input type="text" id="stock_cat_nom" name="nom" required maxlength="255"
                            autocomplete="off" placeholder="Ex. Huiles, Fruits&hellip;"
                            value="<?php echo htmlspecialchars($cat_modal_nom, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="stock-cat-field">
                        <label for="stock_cat_desc">Description <span class="hint">(optionnel)</span></label>
                        <textarea id="stock_cat_desc" name="description"
                            placeholder="Quelques mots pour d&eacute;crire ce rayon."><?php echo htmlspecialchars($cat_modal_description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="stock-cat-field">
                        <label>Visuel <span class="hint">(optionnel)</span></label>
                        <div class="stock-cat-file">
                            <i class="fas fa-cloud-arrow-up" style="font-size:1.35rem;color:#3564a6;display:block;margin-bottom:.35rem;"></i>
                            <div><strong>Glissez une image</strong> ou cliquez pour parcourir</div>
                            <p class="stock-cat-file__hint">JPG, PNG, GIF ou WebP — max. 20 Mo</p>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                    </div>

                    <div class="stock-cat-modal__actions">
                        <button type="button" class="stock-cat-modal__btn stock-cat-modal__btn--ghost js-close-stock-cat-modal">Annuler</button>
                        <button type="submit" class="stock-cat-modal__btn stock-cat-modal__btn--primary">
                            <i class="fas fa-check"></i> Enregistrer la cat&eacute;gorie
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="stkSaveOverlay" class="stk-save-overlay" hidden aria-hidden="true" role="alertdialog" aria-live="polite" aria-labelledby="stkSaveOverlayTitle" aria-describedby="stkSaveOverlayDesc">
        <div class="stk-save-overlay__card">
            <div class="stk-save-overlay__icon" aria-hidden="true"><i class="fas fa-circle-notch"></i></div>
            <h3 class="stk-save-overlay__title" id="stkSaveOverlayTitle">Enregistrement en cours</h3>
            <p class="stk-save-overlay__subtitle" id="stkSaveOverlayDesc">Veuillez patienter pendant le traitement de votre produit&hellip;</p>
            <div class="stk-save-overlay__track" aria-hidden="true">
                <div class="stk-save-overlay__bar" id="stkSaveOverlayBar"></div>
            </div>
            <div class="stk-save-overlay__pct" id="stkSaveOverlayPct">0&nbsp;%</div>
        </div>
    </div>

    <script>
    (function () {
        /* ---- Filtres compacts (tablette / mobile) ---- */
        var filtersExtra = document.getElementById('stkFiltersExtra');
        var filtersToggle = document.getElementById('stkFiltersToggle');
        var filtersClose = document.getElementById('stkFiltersClose');
        var mqCompact = window.matchMedia('(max-width: 1024px)');

        function setFiltersOpen(open) {
            if (!filtersExtra || !filtersToggle) return;
            if (open && mqCompact.matches) {
                filtersExtra.classList.add('stk-filters__extra--open');
                filtersToggle.setAttribute('aria-expanded', 'true');
            } else if (!open) {
                filtersExtra.classList.remove('stk-filters__extra--open');
                filtersToggle.setAttribute('aria-expanded', 'false');
            }
        }

        if (filtersToggle) {
            filtersToggle.addEventListener('click', function () {
                if (!filtersExtra) return;
                var willOpen = !filtersExtra.classList.contains('stk-filters__extra--open');
                setFiltersOpen(willOpen);
            });
        }
        if (filtersClose) {
            filtersClose.addEventListener('click', function () { setFiltersOpen(false); });
        }

        /* ---- Modal ajout produit ---- */
        var modalAdd = document.getElementById('modalAddProduit');
        var btnCloseAdd = document.getElementById('btnCloseAddProduitModal');
        var btnCancelAdd = document.getElementById('btn-fap-cancel-modal');

        function setProduitModalBodyState() {
            if (isAnyProduitModalOpen()) {
                document.body.classList.add('stk-produit-modal-open');
                document.body.style.overflow = 'hidden';
            } else {
                document.body.classList.remove('stk-produit-modal-open');
                document.body.style.overflow = '';
            }
        }

        function openAddModal() {
            if (!modalAdd) return;
            modalAdd.removeAttribute('hidden');
            modalAdd.setAttribute('aria-hidden', 'false');
            setProduitModalBodyState();
        }

        function closeAddModal() {
            if (!modalAdd) return;
            modalAdd.setAttribute('hidden', '');
            modalAdd.setAttribute('aria-hidden', 'true');
            setProduitModalBodyState();
        }

        document.querySelectorAll('.js-open-add-produit').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openAddModal();
            });
        });
        if (btnCloseAdd) btnCloseAdd.addEventListener('click', closeAddModal);
        if (btnCancelAdd) btnCancelAdd.addEventListener('click', closeAddModal);
        if (modalAdd) {
            modalAdd.addEventListener('click', function (ev) {
                if (ev.target === modalAdd) closeAddModal();
            });
        }

        /* ---- Overlay enregistrement (anti double envoi) ---- */
        var saveOverlay = document.getElementById('stkSaveOverlay');
        var saveBar = document.getElementById('stkSaveOverlayBar');
        var savePct = document.getElementById('stkSaveOverlayPct');
        var addForm = document.getElementById('form-add-produit-modal');
        var saveProgressTimer = null;
        var saveProgressValue = 0;
        var formSubmitLocked = false;

        function showSaveOverlay() {
            if (!saveOverlay) return;
            saveOverlay.removeAttribute('hidden');
            saveOverlay.classList.add('is-visible');
            saveOverlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('stk-save-busy');
            document.body.style.overflow = 'hidden';
        }

        function updateSaveProgress(value) {
            saveProgressValue = Math.max(saveProgressValue, Math.min(98, value));
            if (saveBar) saveBar.style.width = saveProgressValue + '%';
            if (savePct) savePct.textContent = Math.round(saveProgressValue) + '\u00a0%';
        }

        function startSaveProgress() {
            saveProgressValue = 0;
            updateSaveProgress(8);
            if (saveProgressTimer) clearInterval(saveProgressTimer);
            saveProgressTimer = setInterval(function () {
                if (saveProgressValue >= 92) return;
                var step = saveProgressValue < 40 ? 4 + Math.random() * 5
                    : saveProgressValue < 75 ? 2 + Math.random() * 3
                    : 0.4 + Math.random() * 1.2;
                updateSaveProgress(saveProgressValue + step);
            }, 420);
        }

        function lockAddFormSubmit(btn) {
            formSubmitLocked = true;
            if (btn) {
                btn.disabled = true;
                btn.classList.add('is-busy');
                btn.setAttribute('aria-busy', 'true');
            }
            if (addForm) {
                addForm.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (el) {
                    el.disabled = true;
                });
            }
            showSaveOverlay();
            startSaveProgress();
        }

        if (addForm) {
            addForm.addEventListener('submit', function (e) {
                if (formSubmitLocked) {
                    e.preventDefault();
                    return;
                }
                if (!addForm.checkValidity()) {
                    return;
                }
                var submitBtn = addForm.querySelector('.btn-fap-submit[type="submit"]');
                lockAddFormSubmit(submitBtn);
            });
        }

        /* ---- Modal modification produit ---- */
        var modalEdit = document.getElementById('modalEditProduit');
        var btnCloseEdit = document.getElementById('btnCloseEditProduitModal');
        var editIframe = document.getElementById('stkEditIframe');

        function openEditModal(id) {
            if (!modalEdit || !editIframe || !id) return;
            editIframe.src = '../produits/embed_modifier.php?id=' + encodeURIComponent(id);
            modalEdit.removeAttribute('hidden');
            modalEdit.setAttribute('aria-hidden', 'false');
            setProduitModalBodyState();
        }

        function closeEditModal() {
            if (!modalEdit) return;
            modalEdit.setAttribute('hidden', '');
            modalEdit.setAttribute('aria-hidden', 'true');
            if (editIframe) editIframe.src = 'about:blank';
            setProduitModalBodyState();
        }

        function isAnyProduitModalOpen() {
            return (modalAdd && !modalAdd.hasAttribute('hidden')) || (modalEdit && !modalEdit.hasAttribute('hidden'));
        }

        document.querySelectorAll('.js-open-edit-produit').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target.closest('.stk-card-btn--delete')) return;
                if (e.target.closest('.pshare__toggle, .stk-card__share')) return;
                e.preventDefault();
                e.stopPropagation();
                var id = el.getAttribute('data-produit-id');
                if (!id && el.closest('[data-produit-id]')) {
                    id = el.closest('[data-produit-id]').getAttribute('data-produit-id');
                }
                openEditModal(id);
            });
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    el.click();
                }
            });
        });

        if (btnCloseEdit) btnCloseEdit.addEventListener('click', closeEditModal);
        if (modalEdit) {
            modalEdit.addEventListener('click', function (ev) {
                if (ev.target === modalEdit) closeEditModal();
            });
        }

        window.addEventListener('message', function (ev) {
            if (ev.data && ev.data.type === 'stk-close-edit-modal') closeEditModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var vm = document.getElementById('fapVarianteModal');
            if (vm && !vm.hidden) return;
            if (modalEdit && !modalEdit.hasAttribute('hidden')) closeEditModal();
            else if (modalAdd && !modalAdd.hasAttribute('hidden')) closeAddModal();
        });

        if (modalAdd && !modalAdd.hasAttribute('hidden')) document.body.style.overflow = 'hidden';
        if (modalEdit && !modalEdit.hasAttribute('hidden')) document.body.style.overflow = 'hidden';

        /* ---- Modal catégorie ---- */
        var modal = document.getElementById('stockCatModal');
        if (!modal) return;

        function openCatModal() {
            modal.classList.add('stock-cat-modal--open');
            modal.removeAttribute('hidden');
            document.body.classList.add('stock-cat-modal-active');
        }

        function closeCatModal() {
            modal.classList.remove('stock-cat-modal--open');
            modal.setAttribute('hidden', 'hidden');
            document.body.classList.remove('stock-cat-modal-active');
        }

        document.querySelectorAll('.js-open-stock-cat-modal').forEach(function(btn) {
            btn.addEventListener('click', openCatModal);
        });
        document.querySelectorAll('.js-close-stock-cat-modal').forEach(function(btn) {
            btn.addEventListener('click', closeCatModal);
        });
        modal.addEventListener('click', function(e) { if (e.target === modal) closeCatModal(); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('stock-cat-modal--open')) closeCatModal();
        });
    })();
    </script>

    <?php require __DIR__ . '/../../includes/partials/platform_share_modal.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
