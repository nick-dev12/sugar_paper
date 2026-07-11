<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

if (ob_get_level() === 0) {
    ob_start();
}

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_categories.php';
require_once __DIR__ . '/includes/produit_boutique_line.php';
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/models/model_visites.php';
require_once __DIR__ . '/models/model_variantes.php';
require_once __DIR__ . '/controllers/controller_panier.php';
require_once __DIR__ . '/includes/flash_toast.php';
require_once __DIR__ . '/includes/image_optimizer.php';

// Récupérer l'ID du produit depuis l'URL ou POST
$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produit_id'])) {
    $produit_id = (int) $_POST['produit_id'];
}

// Traitement de l'ajout au panier
$message = '';
$message_type = '';

// Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_panier') {
    $result = process_add_to_panier();

    if ($result['success']) {
        flash_toast_push('success', 'Produit ajouté au panier avec succès.');
        $back = $produit_id > 0 ? ('/produit.php?id=' . $produit_id . '&added=1') : '/index.php?added=1';
        http_redirect_safe($back);
    }
    $message = $result['message'] ?? '';
    $message_type = 'error';
}

if ($message !== '') {
    flash_toast_queue_page($message_type !== '' ? $message_type : 'error', $message);
}

// Récupérer les informations du produit
$produit = $produit_id > 0 ? get_produit_by_id($produit_id) : false;

// Si le produit n'existe pas, rediriger vers l'accueil
if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/marketplace_region_filter.php';
if (!produit_visible_in_marketplace_region($produit)) {
    header('Location: index.php');
    exit;
}

// Enregistrer la visite si l'utilisateur est connecté
if (isset($_SESSION['user_id']) && $produit_id > 0) {
    add_visite($_SESSION['user_id'], $produit_id);
}

// Calculer le prix à afficher (promotion si disponible)
$prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix_promotion']
    : $produit['prix'];
$prix_produit_detail = (float) $prix_affichage;
$prix_original = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix']
    : null;
$pourcentage_reduction = 0;
if ($prix_original) {
    $pourcentage_reduction = round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100);
}

// Récupérer les variantes du produit
$variantes = get_variantes_by_produit($produit_id);

// Produits similaires : priorité au même rayon (catégorie générale), toutes boutiques
$produit_generale_id = 0;
if (function_exists('produits_has_column') && produits_has_column('categorie_generale_id') && !empty($produit['categorie_generale_id'])) {
    $produit_generale_id = (int) $produit['categorie_generale_id'];
}
if ($produit_generale_id <= 0 && function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
    $cat_row_sim = get_categorie_by_id((int) ($produit['categorie_id'] ?? 0));
    if ($cat_row_sim && !empty($cat_row_sim['categorie_generale_id'])) {
        $produit_generale_id = (int) $cat_row_sim['categorie_generale_id'];
    }
}

$similaires_affichage = 8;
$similaires_pool = 40;
$produits_similaires_candidats = [];
if ($produit_generale_id > 0 && function_exists('get_produits_similaires_rayon_generale')) {
    $produits_similaires_candidats = get_produits_similaires_rayon_generale($produit_id, $produit_generale_id, $similaires_pool);
}
if (empty($produits_similaires_candidats)) {
    $fallback_sim = get_produits_by_categorie($produit['categorie_id']);
    if ($fallback_sim === false) {
        $fallback_sim = [];
    }
    $produits_similaires_candidats = is_array($fallback_sim) ? array_values($fallback_sim) : [];
}
require_once __DIR__ . '/includes/catalogue_shuffle.php';
if (count($produits_similaires_candidats) > $similaires_pool) {
    $seed_pool = abs(crc32(catalogue_shuffle_visiteur_cle() . '|similaires-pool|' . $produit_id)) % 2147483645 + 1;
    $produits_similaires_candidats = array_slice(
        catalogue_melanger_produits($produits_similaires_candidats, $seed_pool),
        0,
        $similaires_pool
    );
}
$produits_similaires = catalogue_tirer_produits_similaires($produits_similaires_candidats, $produit_id, $similaires_affichage);

if (file_exists(__DIR__ . '/models/model_produits_avis.php')) {
    require_once __DIR__ . '/models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $enriched = produits_avis_enrich_products([$produit]);
        if (!empty($enriched[0])) {
            $produit = $enriched[0];
        }
        if (!empty($produits_similaires)) {
            $produits_similaires = produits_avis_enrich_products($produits_similaires);
        }
    }
}

$produit_boutique_nom = produit_public_boutique_label($produit);
$produit_boutique_slug = trim((string) ($produit['vendeur_boutique_slug'] ?? ''));
$produit_boutique_url = $produit_boutique_slug !== ''
    ? boutique_vitrine_entry_href($produit_boutique_slug)
    : '/produits.php';

$produit_boutique_logo_url = '';
$produit_boutique_logo_rel = trim((string) ($produit['vendeur_boutique_logo'] ?? ''));
if ($produit_boutique_logo_rel !== '') {
    $produit_boutique_logo_url = '/upload/' . str_replace('\\', '/', $produit_boutique_logo_rel);
}

$produit_boutique_cert_niveau = null;
if (file_exists(__DIR__ . '/models/model_vendeur_certification.php') && !empty($produit['admin_id'])) {
    require_once __DIR__ . '/models/model_vendeur_certification.php';
    $produit_boutique_cert_niveau = vendeur_certification_get_niveau_actif((int) $produit['admin_id']);
}

$produit_boutique_admin_id = (int) ($produit['admin_id'] ?? 0);
$produit_boutique_abonne = false;
$produit_boutique_peut_abonner = ($produit_boutique_admin_id > 0 && $produit_boutique_slug !== '');
if ($produit_boutique_peut_abonner && !empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/models/model_boutique_abonnements.php';
    $produit_boutique_abonne = boutique_abonnement_is_subscribed((int) $_SESSION['user_id'], $produit_boutique_admin_id);
}
$produit_abonnement_redirect = '/produit.php?id=' . (int) $produit_id;
$produit_peut_negocier = ($produit_boutique_admin_id > 0 && produit_prix_negociable($produit));
$produit_negociation_redirect = '/produit.php?id=' . (int) $produit_id;

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
$base = get_site_base_url();
$seo_title = $produit['nom'] . ' — ' . SITE_BRAND_NAME . ' | ' . $produit_boutique_nom;
$desc = !empty($produit['description'])
    ? strip_tags($produit['description'])
    : $produit['nom'] . ' — en vente sur ' . SITE_BRAND_NAME . ' (boutique ' . $produit_boutique_nom . '). Marketplace Sénégal, achat en ligne.';
$seo_description = mb_substr($desc, 0, 160);
$seo_keywords = site_brand_seo_keywords_default() . ', ' . $produit['nom'] . ', ' . $produit_boutique_nom;
$seo_canonical = $base . '/produit.php?id=' . (int) $produit['id'];
$seo_og_type = 'product';
$img = !empty($produit['image_principale']) ? $produit['image_principale'] : '';
$seo_image = $img ? $base . '/upload/' . ltrim(str_replace('\\', '/', $img), '/') : $base . '/icons/icon-512.png';
require_once __DIR__ . '/includes/seo_structured_data.php';
$seo_json_ld_blocks = [
    seo_structured_data_product($produit, $produit_boutique_nom),
];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="/css/owl.carousel.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/owl.carousel.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-category-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/prix-negociation.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-share.css<?php echo asset_version_query(); ?>">
    <style>
        /* Styles pour la page produit - Palette gourmande */
        body {
            background: transparent;
        }

        .produit-detail-container {
            width: 100%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            padding-bottom: env(safe-area-inset-bottom, 20px);
            box-sizing: border-box;
        }

        .produit-detail-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 40px;
            width: 100%;
        }

        .produit-image-section {
            position: relative;
            flex: 1 1 100%;
            min-width: 0;
            max-width: 100%;
        }

        @media (min-width: 769px) {
            .produit-image-section {
                flex: 1 1 calc(50% - 15px);
                max-width: calc(50% - 15px);
            }
        }

        .produit-gallery-main {
            position: relative;
            margin-bottom: 15px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .produit-gallery-main > .pshare {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 12;
        }

        .produit-gallery-main > .pshare .pshare__toggle {
            width: 40px;
            height: 40px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 4px 14px rgba(13, 13, 13, 0.18);
        }

        @media (max-width: 640px) {
            .produit-gallery-main > .pshare {
                top: 10px;
                right: 10px;
            }

            .produit-gallery-main > .pshare .pshare__toggle {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
        }

        .produit-gallery-thumbs {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            width: 100%;
            max-width: 100%;
        }

        .gallery-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--border-input);
            background: var(--blanc);
            color: var(--couleur-dominante);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .gallery-nav:hover {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border-color: var(--couleur-dominante);
        }

        .gallery-thumbs-list {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0;
            flex: 1;
            min-width: 0;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .gallery-thumbs-list::-webkit-scrollbar {
            height: 4px;
        }

        .gallery-thumbs-list::-webkit-scrollbar-thumb {
            background: var(--border-input);
            border-radius: 4px;
        }

        .gallery-thumb {
            flex-shrink: 0;
            width: 70px;
            height: 70px;
            padding: 0;
            border: 3px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            background: var(--blanc-neige);
            transition: all 0.3s;
        }

        .gallery-thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .gallery-thumb:hover {
            border-color: var(--border-input);
        }

        .gallery-thumb.active {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 2px var(--focus-ring);
        }

        .produit-image-main {
            width: 100%;
            max-width: 100%;
            height: 400px;
            object-fit: contain;
            object-position: center;
            border-radius: 16px;
            border: 2px solid var(--border-input);
            background: var(--blanc-casse);
            box-shadow: var(--ombre-douce);
        }

        /* Bandeau boutique — au-dessus du bloc image + détails (pleine largeur du container) */
        .produit-detail-container > .produit-boutique-rail {
            width: 100%;
            margin-bottom: 24px;
            padding: 18px 22px;
            border-radius: 18px;
            background: linear-gradient(
                160deg,
                rgba(255, 255, 255, 0.95) 0%,
                var(--bleu-pale) 45%,
                rgba(74, 122, 184, 0.14) 100%
            );
            border: 1px solid rgba(53, 100, 166, 0.18);
            box-shadow: var(--ombre-douce),
                inset 0 1px 0 rgba(255, 255, 255, 0.85);
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        .produit-boutique-rail::before {
            content: "";
            position: absolute;
            top: -40%;
            right: -5%;
            width: 140px;
            height: 140px;
            background: radial-gradient(circle, rgba(53, 100, 166, 0.22) 0%, transparent 70%);
            pointer-events: none;
        }

        .produit-boutique-rail__inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px 16px;
        }

        .produit-boutique-rail__left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .produit-boutique-rail__cert {
            flex-shrink: 0;
            margin-left: auto;
            display: flex;
            align-items: center;
        }

        .produit-boutique-rail__text {
            min-width: 0;
            text-align: left;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 2px;
        }

        .produit-boutique-rail__eyebrow {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--couleur-dominante);
            opacity: 0.85;
            margin: 0;
            text-align: left;
        }

        .produit-boutique-rail__icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, var(--bleu-principal), var(--bleu-clair));
            color: var(--texte-clair);
            font-size: 1.35rem;
            box-shadow: 0 6px 20px rgba(53, 100, 166, 0.35);
            overflow: hidden;
            flex-shrink: 0;
        }

        .produit-boutique-rail__icon--logo {
            background: var(--blanc);
            border: 1px solid rgba(53, 100, 166, 0.15);
            box-shadow: 0 4px 14px rgba(53, 100, 166, 0.18);
        }

        .produit-boutique-rail__icon-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .produit-boutique-rail__nom {
            font-family: var(--font-titres);
            font-size: 1.12rem;
            font-weight: 700;
            color: var(--titres);
            line-height: 1.35;
            margin: 0;
            word-break: break-word;
            text-align: left;
        }

        .produit-boutique-rail__divider {
            display: none;
        }

        @media (min-width: 769px) {
            .produit-boutique-rail__divider {
                display: block;
                flex-shrink: 0;
                width: 1px;
                align-self: stretch;
                min-height: 44px;
                background: linear-gradient(
                    180deg,
                    transparent,
                    rgba(53, 100, 166, 0.28) 20%,
                    rgba(255, 107, 53, 0.35) 80%,
                    transparent
                );
                opacity: 1;
                border-radius: 1px;
            }
        }

        .produit-boutique-rail__cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: auto;
            flex: 0 0 auto;
            flex-shrink: 0;
            padding: 8px 20px;
            margin-top: 0;
            margin-left: auto;
            white-space: nowrap;
            font-size: 1rem;
            font-weight: 600;
            font-family: var(--font-corps);
            color: var(--texte-clair);
            text-decoration: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--bleu-principal) 0%, var(--bleu-fonce) 100%);
            border: 1px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 4px 16px rgba(53, 100, 166, 0.35);
            transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.28s ease, filter 0.2s ease;
        }

        .produit-boutique-rail__cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(53, 100, 166, 0.4);
            filter: brightness(1.04);
            color: var(--texte-clair);
        }

        .produit-boutique-rail__cta:focus-visible {
            outline: 3px solid var(--focus-ring);
            outline-offset: 3px;
        }

        .produit-boutique-rail__cta i {
            font-size: 1.08rem;
            opacity: 0.95;
            flex-shrink: 0;
        }

        .produit-boutique-rail__cta-label-short {
            display: none;
        }

        .produit-boutique-rail__actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex: 0 0 auto;
            margin-left: auto;
        }

        .produit-boutique-rail__subscribe-form {
            margin: 0;
            flex: 0 0 auto;
        }

        .produit-boutique-rail__subscribe {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 8px 18px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 600;
            font-family: var(--font-corps);
            text-decoration: none;
            cursor: pointer;
            border: 1.5px solid var(--orange);
            background: var(--orange);
            color: var(--texte-clair);
            box-shadow: 0 4px 14px rgba(255, 107, 53, 0.28);
            transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.28s ease, filter 0.2s ease, background 0.2s ease;
            white-space: nowrap;
            box-sizing: border-box;
            line-height: 1.2;
        }

        .produit-boutique-rail__subscribe i {
            font-size: 1.12rem;
            flex-shrink: 0;
        }

        .produit-boutique-rail__subscribe:hover {
            transform: translateY(-2px);
            filter: brightness(1.04);
            box-shadow: 0 8px 22px rgba(255, 107, 53, 0.35);
        }

        .produit-boutique-rail__subscribe--subscribed {
            background: transparent;
            color: var(--orange);
            box-shadow: none;
        }

        .produit-boutique-rail__subscribe--subscribed:hover {
            background: var(--orange-pale);
        }

        .produit-boutique-rail__subscribe--login {
            background: var(--orange);
            color: var(--texte-clair);
            border-color: var(--orange);
            box-shadow: 0 4px 14px rgba(255, 107, 53, 0.28);
        }

        .produit-boutique-rail__subscribe--login:hover {
            background: var(--orange-fonce);
            border-color: var(--orange-fonce);
            filter: brightness(1.04);
            box-shadow: 0 8px 22px rgba(255, 107, 53, 0.35);
        }

        @media (max-width: 768px) {
            .produit-detail-container > .produit-boutique-rail {
                padding: 12px 14px;
                margin-bottom: 20px;
            }

            .produit-boutique-rail__inner {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .produit-boutique-rail__left {
                width: 100%;
                flex: 0 0 auto;
                flex-wrap: nowrap;
                gap: 10px;
                min-width: 0;
            }

            .produit-boutique-rail__text {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                justify-content: center;
                gap: 2px;
                min-width: 0;
                flex: 1 1 auto;
            }

            .produit-boutique-rail__eyebrow {
                font-size: 12px;
                flex-shrink: 0;
                line-height: 1.2;
            }

            .produit-boutique-rail__nom {
                font-size: 1rem;
                line-height: 1.25;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin: 0;
                max-width: 100%;
            }

            .produit-boutique-rail__cert {
                margin-left: 0;
                flex-shrink: 0;
            }

            .produit-boutique-rail__divider {
                display: none;
            }

            .produit-boutique-rail__icon {
                width: 40px;
                height: 40px;
                border-radius: 12px;
                font-size: 1rem;
                flex-shrink: 0;
            }

            .produit-boutique-rail__actions {
                width: 100%;
                margin-left: 0;
                display: flex;
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: stretch;
                justify-content: flex-start;
                gap: 10px;
            }

            .produit-boutique-rail__subscribe-form {
                flex: 0 0 auto;
                min-width: 0;
                display: flex;
                align-items: stretch;
            }

            .produit-boutique-rail__subscribe {
                width: auto;
                max-width: none;
                min-height: 36px;
                height: auto;
                padding: 6px 14px;
                font-size: 0.96rem;
                gap: 8px;
                border-radius: 12px;
            }

            .produit-boutique-rail__subscribe i {
                font-size: 1.12rem;
            }

            .produit-boutique-rail__cta {
                flex: 0 1 auto;
                min-width: 0;
                max-width: calc(100% - 6.5rem);
                width: auto;
                min-height: 36px;
                padding: 6px 14px;
                font-size: 0.96rem;
                gap: 8px;
                border-radius: 12px;
                margin-left: 0;
                white-space: nowrap;
                box-sizing: border-box;
            }

            .produit-boutique-rail__cta i {
                font-size: 1.06rem;
                flex-shrink: 0;
            }

            .produit-boutique-rail__cta .produit-boutique-rail__cta-label-long {
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        @media (max-width: 480px) {
            .produit-boutique-rail__cta .produit-boutique-rail__cta-label-long {
                display: none;
            }

            .produit-boutique-rail__cta .produit-boutique-rail__cta-label-short {
                display: inline;
            }
        }

        .produit-info-section {
            display: flex;
            flex-direction: column;
            flex: 1 1 100%;
            min-width: 0;
            max-width: 100%;
        }

        @media (min-width: 769px) {
            .produit-info-section {
                flex: 1 1 calc(50% - 15px);
                max-width: calc(50% - 15px);
            }
        }

        .produit-nom {
            font-size: 24px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 10px;
            line-height: 1.3;
            font-family: var(--font-titres);
        }

        .produit-categorie {
            display: inline-block;
            font-size: 12px;
            color: var(--couleur-dominante);
            background: var(--bleu-pale);
            padding: 6px 12px;
            border-radius: 20px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .produit-prix-section {
            margin-bottom: 15px;
            padding: 18px;
            background: var(--blanc);
            border-radius: 12px;
            border-left: 4px solid var(--couleur-dominante);
        }

        .prix-principal {
            font-size: 26px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 5px;
        }

        .prix-original {
            font-size: 18px;
            color: var(--texte-fonce);
            text-decoration: line-through;
            margin-right: 8px;
            opacity: 0.7;
        }

        .prix-promo {
            font-size: 22px;
            color: var(--accent-promo);
            font-weight: 600;
        }

        .promo-badge {
            display: inline-block;
            background: var(--accent-promo);
            color: var(--texte-clair);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 8px;
        }

        .produit-stock-info {
            margin-bottom: 15px;
            padding: 14px;
            background: var(--blanc);
            border-radius: 12px;
            border: 1px solid var(--border-input);
        }

        .stock-item {
            font-size: 13px;
            color: var(--texte-fonce);
            margin-bottom: 6px;
        }

        .stock-item strong {
            color: var(--couleur-dominante);
            font-weight: 600;
            min-width: 90px;
            display: inline-block;
        }

        .stock-value {
            color: var(--couleur-dominante);
            font-weight: 700;
        }

        .couleurs-swatches-display {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-left: 4px;
        }

        .couleur-swatch-display {
            display: inline-block;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
            cursor: default;
        }

        .produit-options-section {
            margin-bottom: 20px;
            padding: 16px;
            background: var(--blanc);
            border-radius: 12px;
            border: 1px solid var(--border-input);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .option-group {
            margin-bottom: 14px;
        }

        .option-group:last-child {
            margin-bottom: 0;
        }

        .option-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
        }

        .couleurs-swatches-select {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            width: 100%;
            max-width: 100%;
        }

        .couleur-swatch-select {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #f5f5f5;
            border-radius: 20px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }

        .couleur-swatch-select:hover {
            border-color: var(--border-input);
            background: var(--blanc);
        }

        .couleur-swatch-select:has(input:checked) {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 2px var(--focus-ring);
            background: var(--blanc);
        }

        .couleur-swatch-select input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .couleur-swatch-select .swatch-preview {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        }

        .couleur-swatch-select .swatch-text {
            font-size: 13px;
            color: var(--texte-fonce);
        }

        .option-select {
            padding: 10px 14px;
            border: 2px solid var(--border-input);
            border-radius: 8px;
            font-size: 14px;
            min-width: 140px;
            background: var(--blanc);
            cursor: pointer;
        }

        .option-select:focus {
            outline: none;
            border-color: var(--couleur-dominante);
        }

        .option-value-display {
            font-size: 14px;
            color: var(--texte-fonce);
            font-weight: 500;
        }

        .produit-description {
            margin-bottom: 24px;
            padding: 24px;
            background: var(--blanc);
            border-radius: 16px;
            line-height: 1.7;
            color: var(--texte-fonce);
            font-size: 15px;
            box-shadow: var(--ombre-douce);
            border: 1px solid var(--border-input);
            border-left: 4px solid var(--couleur-dominante);
        }

        .produit-description h3 {
            font-size: 18px;
            color: var(--couleur-dominante);
            margin-bottom: 14px;
            font-weight: 600;
        }

        .produit-description p {
            margin: 0;
            color: var(--texte-fonce);
        }

        .quantite-section {
            margin-bottom: 20px;
        }

        .quantite-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
            display: block;
        }

        .quantite-controls {
            display: flex;
            align-items: stretch;
            gap: clamp(8px, 2vw, 12px);
            margin-bottom: 15px;
            width: 100%;
            max-width: 100%;
            flex-wrap: nowrap;
            min-width: 0;
        }

        .quantite-input-wrapper {
            display: flex;
            align-items: center;
            border: 2px solid var(--border-input);
            border-radius: 12px;
            overflow: hidden;
            flex: 0 1 auto;
            min-width: 0;
            height: 48px;
        }

        .quantite-btn {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            width: 38px;
            height: 40px;
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
            height: 38px;
            border: none;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--titres);
        }

        .prix-total-section {
            padding: 18px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .prix-total-label {
            font-size: 13px;
            margin-bottom: 5px;
            opacity: 0.95;
        }

        .prix-total-value {
            font-size: 24px;
            font-weight: 700;
        }

        .produit-add-form {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .produit-checkout-row {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .produit-checkout-row .prix-total-section {
            margin-bottom: 0;
        }

        .btn-add-panier {
            width: 100%;
            padding: 14px 25px;
            background: var(--orange);
            color: var(--texte-clair);
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 18px rgba(255, 107, 53, 0.35);
        }

        .btn-add-panier:hover {
            background: var(--orange-fonce);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 107, 53, 0.4);
        }

        .btn-add-panier:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .message {
            padding: 15px 20px;
            padding-right: 45px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            position: relative;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background-color: var(--success-bg);
            color: var(--bleu-fonce);
            border: 1px solid var(--bleu);
        }

        .message.error {
            background-color: var(--error-bg);
            color: var(--titres);
            border: 1px solid var(--error-border);
        }

        .message-close {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            color: inherit;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .message-close:hover {
            opacity: 1;
            background-color: rgba(0, 0, 0, 0.1);
        }

        .message.fade-out {
            animation: fadeOut 0.3s ease-out forwards;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-10px);
                max-height: 0;
                margin-bottom: 0;
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        .produits-similaires {
            margin-top: 60px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .produits-similaires h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 30px;
            text-align: center;
            font-family: var(--font-titres);
        }

        .produit-connect-cta {
            padding: 24px;
            background: var(--blanc);
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-input);
        }

        .produit-connect-cta p {
            margin-bottom: 18px;
            color: var(--texte-fonce);
        }

        .btn-connect-produit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: var(--ombre-promo);
        }

        .btn-connect-produit:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-gourmande);
            color: var(--texte-clair);
        }

        /* Responsive - Tablette */
        @media (max-width: 992px) {
            .produit-detail-wrapper {
                gap: 24px;
            }

            .produit-image-section,
            .produit-info-section {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .produit-image-main {
                height: 360px;
            }

            .produit-nom {
                font-size: 22px;
            }

            .produit-prix-section {
                padding: 14px 16px;
            }

            .prix-principal {
                font-size: 24px;
            }

            .produit-options-section,
            .produit-variantes-section.produit-section-bg {
                padding: 14px 16px;
            }

            .produit-description {
                padding: 18px 20px;
            }

            .produits-similaires {
                margin-top: 40px;
            }

            .produits-similaires h2 {
                font-size: 24px;
                margin-bottom: 24px;
            }

            .produit-checkout-row {
                flex-direction: row;
                align-items: stretch;
                gap: 14px;
            }

            .produit-checkout-row .prix-total-section {
                flex: 1 1 auto;
                min-width: 0;
                margin-bottom: 0;
            }

            .produit-checkout-row .btn-add-panier {
                flex: 0 0 auto;
                width: auto;
                min-width: 180px;
                max-width: 42%;
            }
        }

        /* Responsive - Mobile */
        @media (max-width: 768px) {
            .produit-detail-wrapper {
                flex-direction: column;
                gap: 20px;
                margin-bottom: 30px;
            }

            .produit-image-section,
            .produit-info-section {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .produit-image-main {
                height: 280px;
                border-radius: 12px;
            }

            .produit-gallery-main {
                margin-bottom: 12px;
            }

            .gallery-thumb {
                width: 56px;
                height: 56px;
            }

            .gallery-nav {
                width: 36px;
                height: 36px;
                font-size: 12px;
            }

            .produit-nom {
                font-size: 19px;
                line-height: 1.35;
                margin-bottom: 8px;
            }

            .produit-prix-section {
                padding: 12px 14px;
                margin-bottom: 12px;
            }

            .prix-principal {
                font-size: 20px;
            }

            .prix-original {
                font-size: 15px;
            }

            .prix-promo {
                font-size: 18px;
            }

            .promo-badge {
                font-size: 11px;
                padding: 3px 8px;
            }

            .produit-description {
                padding: 16px 18px;
                margin-bottom: 18px;
                font-size: 14px;
            }

            .produit-description h3 {
                font-size: 16px;
                margin-bottom: 10px;
            }

            .produit-section-bg {
                padding: 14px 16px;
                margin-bottom: 16px;
            }

            .produit-variantes-section.produit-section-bg {
                padding: 14px 16px;
            }

            .variante-option {
                flex: 0 0 140px;
                min-width: 140px;
                padding: 10px 12px;
            }

            .variante-option .variante-thumb {
                width: 42px;
                height: 42px;
            }

            .variante-option .variante-nom {
                font-size: 12px;
            }

            .variante-option .variante-prix {
                font-size: 11px;
            }

            .variantes-arrow {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }

            .produit-options-section .quantite-label {
                font-size: 13px;
            }

            .option-group {
                margin-bottom: 12px;
            }

            .options-list-select {
                gap: 8px;
            }

            .option-label {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .couleur-swatch-select {
                padding: 5px 8px;
            }

            .couleur-swatch-select .swatch-preview {
                width: 24px;
                height: 24px;
            }

            .couleur-swatch-select .swatch-text {
                font-size: 12px;
            }

            .option-swatch-select {
                min-width: 70px;
                padding: 8px 12px;
            }

            .option-swatch-select .option-swatch-text {
                font-size: 12px;
            }

            .quantite-section {
                margin-bottom: 16px;
            }

            .quantite-label {
                font-size: 13px;
            }

            .quantite-input-wrapper {
                border-radius: 10px;
            }

            .quantite-btn {
                width: 44px;
                height: 44px;
                font-size: 18px;
            }

            .quantite-input {
                width: 50px;
                height: 42px;
                font-size: 15px;
            }

            .produit-checkout-row {
                flex-direction: row;
                align-items: stretch;
                gap: 12px;
            }

            .produit-checkout-row .prix-total-section {
                flex: 1 1 auto;
                min-width: 0;
                margin-bottom: 0;
                padding: 12px 14px;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .prix-total-label {
                font-size: 11px;
                margin-bottom: 2px;
            }

            .prix-total-value {
                font-size: clamp(16px, 4.5vw, 20px);
                line-height: 1.2;
            }

            .produit-checkout-row .btn-add-panier {
                flex: 0 0 auto;
                width: auto;
                min-width: 148px;
                max-width: 46%;
                padding: 12px 16px;
                font-size: 14px;
                border-radius: 16px;
                align-self: stretch;
            }

            .produit-detail-container {
                margin: 12px auto;
                padding: 0 12px;
            }

            .produits-similaires {
                margin-top: 36px;
                padding: 0 4px;
            }

            .produits-similaires h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            .produits-similaires .produit_vedetes .articles,
            .produits-similaires .carousel11 {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                width: 100%;
            }

            .produits-similaires .carousel {
                flex: 1 1 calc(50% - 8px);
                min-width: 0;
                max-width: calc(50% - 8px);
            }

            .message {
                padding: 12px 16px;
                padding-right: 40px;
                font-size: 14px;
            }
        }

        /* Responsive - Petit mobile */
        @media (max-width: 480px) {
            .produit-detail-container {
                padding: 0 10px;
                margin: 10px auto;
            }

            .produit-image-main {
                height: 240px;
            }

            .gallery-thumb {
                width: 48px;
                height: 48px;
            }

            .produit-nom {
                font-size: 17px;
            }

            .prix-principal {
                font-size: 18px;
            }

            .prix-original {
                font-size: 14px;
            }

            .prix-promo {
                font-size: 16px;
            }

            .variante-option {
                flex: 0 0 120px;
                min-width: 120px;
                padding: 8px 10px;
            }

            .variante-option .variante-thumb {
                width: 36px;
                height: 36px;
            }

            .variante-option .variante-nom {
                font-size: 11px;
            }

            .variante-option .variante-prix {
                font-size: 10px;
            }

            .option-swatch-select {
                min-width: 65px;
                padding: 6px 10px;
            }

            .option-swatch-select .option-swatch-text {
                font-size: 11px;
                line-height: 1.3;
            }

            .quantite-btn {
                width: 40px;
                height: 42px;
            }

            .quantite-input {
                width: 44px;
            }

            .prix-total-value {
                font-size: 16px;
            }

            .produit-checkout-row .btn-add-panier {
                min-width: 120px;
                max-width: 52%;
                padding: 11px 12px;
                font-size: 13px;
            }

            .produits-similaires h2 {
                font-size: 18px;
                margin-bottom: 16px;
            }

            .produits-similaires .produit_vedetes .articles,
            .produits-similaires .carousel11 {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                width: 100%;
            }

            .produits-similaires .carousel {
                flex: 1 1 calc(50% - 6px);
                min-width: 0;
                max-width: calc(50% - 6px);
            }
        }

        /* Très petit écran : produits similaires en 1 colonne */
        @media (max-width: 360px) {
            .produits-similaires .carousel {
                flex: 1 1 100%;
                max-width: 100%;
            }
        }

        .produit-section-bg {
            background: var(--blanc);
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid var(--border-input);
            margin-bottom: 20px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .produit-variantes-section {
            margin-bottom: 20px;
        }

        .produit-variantes-section.produit-section-bg {
            padding: 18px 20px;
        }

        .variantes-carousel-wrapper {
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
        }

        .variantes-scroll-container {
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            width: 100%;
            max-width: 100%;
        }

        .variantes-scroll-container::-webkit-scrollbar {
            display: none;
        }

        .variantes-select {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            padding: 4px 0;
            width: 100%;
            min-width: 0;
        }

        .variante-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            flex: 0 0 calc(50% - 5px);
            min-width: 0;
            box-sizing: border-box;
        }

        @media (min-width: 768px) {
            .variante-option {
                flex: 0 0 calc(33.333% - 7px);
            }
        }

        @media (min-width: 1024px) {
            .variante-option {
                flex: 0 0 calc(25% - 8px);
            }
        }

        .variantes-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--couleur-dominante);
            background: var(--blanc);
            color: var(--couleur-dominante);
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .variantes-arrow:hover {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
        }

        .variantes-arrow.visible {
            display: flex;
        }

        .variantes-arrow-left {
            left: 8px;
        }

        .variantes-arrow-right {
            right: 8px;
        }

        .variante-option:hover,
        .variante-option.selected {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale);
        }

        .variante-option .variante-thumb {
            width: 50px;
            height: 50px;
            object-fit: contain;
            object-position: center;
            border-radius: 6px;
            margin-bottom: 6px;
        }

        .variante-option .variante-nom {
            font-size: 13px;
            font-weight: 600;
            color: var(--titres);
        }

        .variante-option .variante-prix {
            font-size: 12px;
            color: var(--couleur-dominante);
        }

        .options-list-select {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            width: 100%;
            max-width: 100%;
        }

        .option-swatch-select {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            background: var(--blanc);
            border-radius: 10px;
            border: 2px solid var(--glass-border);
            cursor: pointer;
            transition: all 0.2s;
            min-width: 80px;
            flex: 1 1 auto;
            max-width: 100%;
        }

        .option-swatch-select:hover {
            border-color: var(--border-input);
        }

        .option-swatch-select.selected,
        .option-swatch-select:has(input:checked) {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale);
        }

        .option-swatch-select input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .option-swatch-select .option-swatch-text {
            font-size: 13px;
            font-weight: 500;
            color: var(--titres);
            word-break: break-word;
            text-align: center;
            overflow-wrap: break-word;
            min-width: 0;
        }

        .options-list-select {
            flex-wrap: wrap;
        }

        .produit_vedetes,
        .produit_vedetes .articles,
        .produit_vedetes .carousel11 {
            width: 100%;
            max-width: 100%;
        }
    </style>
</head>

<body>

    <?php include('nav_bar.php') ?>

    <div class="produit-detail-container">

        <aside class="produit-boutique-rail" aria-label="Boutique du vendeur">
            <div class="produit-boutique-rail__inner">
                <div class="produit-boutique-rail__left">
                    <div class="produit-boutique-rail__icon<?php echo $produit_boutique_logo_url !== '' ? ' produit-boutique-rail__icon--logo' : ''; ?>"
                        aria-hidden="true">
                        <?php if ($produit_boutique_logo_url !== ''): ?>
                        <img src="<?php echo htmlspecialchars($produit_boutique_logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                            alt=""
                            class="produit-boutique-rail__icon-img"
                            width="52"
                            height="52"
                            loading="lazy"
                            decoding="async">
                        <?php else: ?>
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <div class="produit-boutique-rail__text">
                        <p class="produit-boutique-rail__eyebrow">Boutique</p>
                        <p class="produit-boutique-rail__nom"><?php echo htmlspecialchars($produit_boutique_nom); ?></p>
                    </div>
                    <?php if ($produit_boutique_cert_niveau): ?>
                    <div class="produit-boutique-rail__cert">
                        <?php
                        $cert_niveau = $produit_boutique_cert_niveau;
                        $cert_size = 'rail';
                        require __DIR__ . '/includes/partials/vendeur_certification_badge.php';
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="produit-boutique-rail__divider" aria-hidden="true"></span>
                <div class="produit-boutique-rail__actions">
                    <?php if ($produit_boutique_peut_abonner): ?>
                        <?php if (!empty($_SESSION['user_id'])): ?>
                        <form class="produit-boutique-rail__subscribe-form" method="post"
                            action="/user/boutique-abonnement-action.php">
                            <input type="hidden" name="admin_id" value="<?php echo (int) $produit_boutique_admin_id; ?>">
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($produit_abonnement_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($produit_boutique_abonne): ?>
                            <input type="hidden" name="action" value="unsubscribe">
                            <button type="submit" class="produit-boutique-rail__subscribe produit-boutique-rail__subscribe--subscribed"
                                aria-label="Ne plus suivre <?php echo htmlspecialchars($produit_boutique_nom, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-check" aria-hidden="true"></i>
                                <span>Suivi</span>
                            </button>
                            <?php else: ?>
                            <input type="hidden" name="action" value="subscribe">
                            <button type="submit" class="produit-boutique-rail__subscribe"
                                aria-label="Suivre <?php echo htmlspecialchars($produit_boutique_nom, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <span>Suivre</span>
                            </button>
                            <?php endif; ?>
                        </form>
                        <?php else: ?>
                        <a href="/choix-connexion.php?redirect=<?php echo rawurlencode($produit_abonnement_redirect); ?>"
                            class="produit-boutique-rail__subscribe produit-boutique-rail__subscribe--login"
                            aria-label="Se connecter pour suivre <?php echo htmlspecialchars($produit_boutique_nom, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-plus" aria-hidden="true"></i>
                            <span>Suivre</span>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($produit_boutique_url); ?>"
                        class="produit-boutique-rail__cta">
                        <?php if ($produit_boutique_slug !== ''): ?>
                        <span class="produit-boutique-rail__cta-label-long">Visiter la boutique du vendeur</span>
                        <span class="produit-boutique-rail__cta-label-short" aria-hidden="true">Voir la boutique</span>
                        <?php else: ?>
                        <span>Voir le catalogue</span>
                        <?php endif; ?>
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </aside>

        <div class="produit-detail-wrapper">
            <!-- Section Image avec galerie -->
            <div class="produit-image-section">
                <?php
                $galerie_images = [];
                if (!empty($produit['images'])) {
                    $dec = json_decode($produit['images'], true);
                    if (is_array($dec))
                        $galerie_images = $dec;
                }
                if (empty($galerie_images) && !empty($produit['image_principale'])) {
                    $galerie_images = [$produit['image_principale']];
                }
                ?>
                <div class="produit-gallery-main">
                    <?php require __DIR__ . '/includes/partials/product_share_button.php'; ?>
                    <img src="<?php echo htmlspecialchars(upload_image_url($galerie_images[0] ?? $produit['image_principale'] ?? '', 'original')); ?>"
                        alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-image-main"
                        id="produit-image-main" onerror="this.src='/image/produit1.jpg'">
                </div>
                <?php if (count($galerie_images) > 1): ?>
                    <div class="produit-gallery-thumbs">
                        <button type="button" class="gallery-nav gallery-prev" aria-label="Image précédente">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="gallery-thumbs-list">
                            <?php foreach ($galerie_images as $idx => $img_path): ?>
                                <button type="button" class="gallery-thumb <?php echo $idx === 0 ? 'active' : ''; ?>"
                                    data-index="<?php echo $idx; ?>"
                                    data-src="<?php echo htmlspecialchars(upload_image_url($img_path, 'original')); ?>">
                                    <img src="<?php echo htmlspecialchars(upload_image_url($img_path, 'sm')); ?>"
                                        alt="Vue <?php echo $idx + 1; ?>" onerror="this.src='/image/produit1.jpg'">
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="gallery-nav gallery-next" aria-label="Image suivante">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section Informations (détails produit) -->
            <div class="produit-info-section">
                <h1 class="produit-nom" id="produit-nom"><?php echo htmlspecialchars($produit['nom']); ?></h1>
                <?php if (!empty($produit['avis_count'])): ?>
                    <?php
                    $note = (float) ($produit['avis_moyenne'] ?? 0);
                    $count = (int) ($produit['avis_count'] ?? 0);
                    $size = 'md';
                    $class_extra = 'produit-rating-stars';
                    require __DIR__ . '/includes/partials/product_rating_stars.php';
                    ?>
                <?php endif; ?>

                <!-- Prix -->
                <div class="produit-prix-section">
                    <div class="prix-principal" id="produit-prix-affichage">
                        <?php if ($prix_original): ?>
                            <span class="prix-original"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                FCFA</span>
                            <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                            <span class="promo-badge">-<?php echo $pourcentage_reduction; ?>%</span>
                        <?php else: ?>
                            <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Variantes, Stock, Poids, Couleurs, Taille -->
                <?php
                $couleurs_options = [];
                if (!empty($produit['couleurs'])) {
                    $cr = trim($produit['couleurs']);
                    $dec = json_decode($cr, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        if (is_array($dec) && !empty($dec)) {
                            $couleurs_options = array_filter($dec, function ($c) {
                                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
                            });
                        }
                    } else {
                        $couleurs_options = array_map('trim', array_filter(explode(',', $cr)));
                    }
                }
                $poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
                $taille_options = parse_options_with_surcharge($produit['taille'] ?? null);
                $poids_options = array_values(array_filter($poids_options, function ($o) {
                    $v = trim((string) ($o['v'] ?? ''));
                    return $v !== '' && $v !== '[]' && $v !== '[ ]' && strtolower($v) !== 'null';
                }));
                $taille_options = array_values(array_filter($taille_options, function ($o) {
                    $v = trim((string) ($o['v'] ?? ''));
                    return $v !== '' && $v !== '[]' && $v !== '[ ]' && strtolower($v) !== 'null';
                }));
                $has_selectable_options = !empty($couleurs_options) || !empty($poids_options) || !empty($taille_options);
                $has_variantes = !empty($variantes);
                $prix_base_js = $prix_affichage;
                $variantes_js = [];
                foreach ($variantes as $v) {
                    $vp = !empty($v['prix_promotion']) && $v['prix_promotion'] < $v['prix'] ? $v['prix_promotion'] : $v['prix'];
                    $variantes_js[] = ['id' => $v['id'], 'nom' => $v['nom'], 'prix' => (float) $vp, 'image' => $v['image'] ?? ''];
                }
                $poids_js = [];
                foreach ($poids_options as $p) {
                    $poids_js[] = ['v' => $p['v'], 's' => (float) ($p['s'] ?? 0)];
                }
                $taille_js = [];
                foreach ($taille_options as $t) {
                    $taille_js[] = ['v' => $t['v'], 's' => (float) ($t['s'] ?? 0)];
                }
                ?>

                <!-- Description (en bas) -->
                <?php if (!empty($produit['description'])): ?>
                    <div class="produit-description produit-section-bg">
                        <h3>Description</h3>
                        <p>
                            <?php echo nl2br(htmlspecialchars($produit['description'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Variantes du produit -->
                <?php if ($has_variantes): ?>
                    <?php $total_variantes = 1 + count($variantes); ?>
                    <div class="produit-variantes-section produit-section-bg">
                        <div class="quantite-label" style="margin-bottom: 10px;"><i class="fas fa-layer-group"></i> Autres
                            quantités disponibles</div>
                        <div class="variantes-carousel-wrapper" data-total="<?php echo $total_variantes; ?>">
                            <button type="button" class="variantes-arrow variantes-arrow-left"
                                aria-label="Variantes précédentes" title="Précédent">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="variantes-arrow variantes-arrow-right"
                                aria-label="Variantes suivantes" title="Suivant">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <div class="variantes-scroll-container">
                                <div class="variantes-select">
                                    <?php
                                    $base_prix_orig = $prix_original ? (float) $produit['prix'] : 0;
                                    $base_prix_promo = $prix_affichage;
                                    $base_pct = $pourcentage_reduction;
                                    ?>
                                    <label class="variante-option variante-base selected" data-id=""
                                        data-prix="<?php echo $base_prix_promo; ?>"
                                        data-prix-original="<?php echo $base_prix_orig; ?>"
                                        data-prix-promo="<?php echo $base_prix_promo; ?>"
                                        data-pourcentage="<?php echo $base_pct; ?>"
                                        data-nom="<?php echo htmlspecialchars($produit['nom']); ?>"
                                        data-image="<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>">
                                        <input type="radio" name="option_variante_radio" value="" checked required>
                                        <?php if (!empty($produit['image_principale'])): ?><img
                                                src="/upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
                                                alt="" class="variante-thumb"
                                                onerror="this.style.display='none'"><?php endif; ?>
                                        <span class="variante-nom"><?php echo htmlspecialchars($produit['nom']); ?></span>
                                        <span
                                            class="variante-prix"><?php echo number_format($prix_affichage, 0, ',', ' '); ?>
                                            FCFA</span>
                                    </label>
                                    <?php foreach ($variantes as $var): ?>
                                        <?php
                                        $vp = !empty($var['prix_promotion']) && $var['prix_promotion'] < $var['prix'] ? $var['prix_promotion'] : $var['prix'];
                                        $v_prix_orig = (!empty($var['prix_promotion']) && $var['prix_promotion'] < $var['prix']) ? (float) $var['prix'] : 0;
                                        $v_pct = $v_prix_orig > 0 ? round((($var['prix'] - $var['prix_promotion']) / $var['prix']) * 100) : 0;
                                        ?>
                                        <label class="variante-option" data-id="<?php echo $var['id']; ?>"
                                            data-prix="<?php echo $vp; ?>" data-prix-original="<?php echo $v_prix_orig; ?>"
                                            data-prix-promo="<?php echo $vp; ?>" data-pourcentage="<?php echo $v_pct; ?>"
                                            data-nom="<?php echo htmlspecialchars($var['nom']); ?>"
                                            data-image="<?php echo htmlspecialchars($var['image'] ?? ''); ?>">
                                            <input type="radio" name="option_variante_radio" value="<?php echo $var['id']; ?>">
                                            <?php if (!empty($var['image'])): ?><img
                                                    src="/upload/<?php echo htmlspecialchars($var['image']); ?>" alt=""
                                                    class="variante-thumb" onerror="this.style.display='none'"><?php endif; ?>
                                            <span class="variante-nom"><?php echo htmlspecialchars($var['nom']); ?></span>
                                            <span class="variante-prix"><?php echo number_format($vp, 0, ',', ' '); ?>
                                                FCFA</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>




                <!-- Options (couleur, poids, taille) : affichées pour tous les utilisateurs -->
                <form method="POST" action="produit.php?id=<?php echo (int) $produit_id; ?>" id="add-to-panier-form" class="produit-add-form">
                    <input type="hidden" name="action" value="add_to_panier">
                    <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                    <?php if ($has_variantes): ?>
                        <input type="hidden" name="option_variante_id" id="option-variante-id" value="">
                        <input type="hidden" name="option_variante_nom" id="option-variante-nom"
                            value="<?php echo htmlspecialchars($produit['nom']); ?>">
                        <input type="hidden" name="option_variante_image" id="option-variante-image"
                            value="<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>">
                    <?php endif; ?>

                    <?php if ($has_selectable_options): ?>
                        <div class="produit-options-section produit-section-bg">
                            <div class="quantite-label" style="margin-bottom: 10px;"><i class="fas fa-palette"></i>
                                Choisissez vos options</div>
                            <?php if (!empty($couleurs_options)): ?>
                                <div class="option-group">
                                    <label class="option-label">Couleur</label>
                                    <?php if (count($couleurs_options) === 1): ?>
                                        <input type="hidden" name="option_couleur"
                                            value="<?php echo htmlspecialchars($couleurs_options[0]); ?>">
                                        <span class="couleurs-swatches-select">
                                            <?php $hex = $couleurs_options[0]; ?>
                                            <span class="couleur-swatch-select is-hex" style="opacity:0.9;">
                                                <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)): ?>
                                                    <span class="swatch-preview"
                                                        style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                        title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                    <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                <?php else: ?>
                                                    <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    <?php else: ?>
                                        <span class="couleurs-swatches-select">
                                            <?php foreach ($couleurs_options as $hex): ?>
                                                <label
                                                    class="couleur-swatch-select <?php echo preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? 'is-hex' : ''; ?>">
                                                    <input type="radio" name="option_couleur"
                                                        value="<?php echo htmlspecialchars($hex); ?>" class="option-radio-couleur">
                                                    <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)): ?>
                                                        <span class="swatch-preview"
                                                            style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                            title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                    <?php else: ?>
                                                        <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($poids_options) && count($poids_options) > 1): ?>
                                <div class="option-group">
                                    <label class="option-label">Poids</label>
                                    <input type="hidden" name="option_surcout_poids" id="option-surcout-poids" value="0">
                                    <span class="options-list-select poids-options-list">
                                        <label class="option-swatch-select selected" data-value="" data-surcout="0">
                                            <input type="radio" name="option_poids" value="" checked>
                                            <span class="option-swatch-text">Prix de base
                                                (<?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA)</span>
                                        </label>
                                        <?php foreach ($poids_options as $opt): ?>
                                            <label class="option-swatch-select"
                                                data-value="<?php echo htmlspecialchars($opt['v']); ?>"
                                                data-surcout="<?php echo (float) ($opt['s'] ?? 0); ?>">
                                                <input type="radio" name="option_poids"
                                                    value="<?php echo htmlspecialchars($opt['v']); ?>">
                                                <span
                                                    class="option-swatch-text"><?php echo htmlspecialchars($opt['v']); ?><?php echo ($opt['s'] ?? 0) > 0 ? ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            <?php elseif (!empty($poids_options)): ?>
                                <div class="option-group">
                                    <label class="option-label">Poids</label>
                                    <input type="hidden" name="option_poids"
                                        value="<?php echo htmlspecialchars($poids_options[0]['v']); ?>">
                                    <input type="hidden" name="option_surcout_poids" id="option-surcout-poids"
                                        value="<?php echo (float) ($poids_options[0]['s'] ?? 0); ?>">
                                    <span
                                        class="option-value-display"><?php echo htmlspecialchars($poids_options[0]['v']); ?><?php echo ($poids_options[0]['s'] ?? 0) > 0 ? ' (+' . number_format($poids_options[0]['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($taille_options) && count($taille_options) > 1): ?>
                                <div class="option-group">
                                    <label class="option-label">Taille</label>
                                    <input type="hidden" name="option_surcout_taille" id="option-surcout-taille" value="0">
                                    <span class="options-list-select taille-options-list">
                                        <label class="option-swatch-select selected" data-value="" data-surcout="0">
                                            <input type="radio" name="option_taille" value="" checked>
                                            <span class="option-swatch-text">Prix de base
                                                (<?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA)</span>
                                        </label>
                                        <?php foreach ($taille_options as $opt): ?>
                                            <label class="option-swatch-select"
                                                data-value="<?php echo htmlspecialchars($opt['v']); ?>"
                                                data-surcout="<?php echo (float) ($opt['s'] ?? 0); ?>">
                                                <input type="radio" name="option_taille"
                                                    value="<?php echo htmlspecialchars($opt['v']); ?>">
                                                <span
                                                    class="option-swatch-text"><?php echo htmlspecialchars($opt['v']); ?><?php echo ($opt['s'] ?? 0) > 0 ? ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            <?php elseif (!empty($taille_options)): ?>
                                <div class="option-group">
                                    <label class="option-label">Taille</label>
                                    <input type="hidden" name="option_taille"
                                        value="<?php echo htmlspecialchars($taille_options[0]['v']); ?>">
                                    <input type="hidden" name="option_surcout_taille" id="option-surcout-taille"
                                        value="<?php echo (float) ($taille_options[0]['s'] ?? 0); ?>">
                                    <span
                                        class="option-value-display"><?php echo htmlspecialchars($taille_options[0]['v']); ?><?php echo ($taille_options[0]['s'] ?? 0) > 0 ? ' (+' . number_format($taille_options[0]['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Sélection de quantité et ajout au panier -->
                    <input type="hidden" name="option_prix_unitaire" id="option-prix-unitaire"
                        value="<?php echo $prix_affichage; ?>">
                    <div class="quantite-section">
                        <label class="quantite-label">Quantité:</label>
                        <div class="quantite-controls">
                            <div class="quantite-input-wrapper">
                                <button type="button" class="quantite-btn" id="decrease-qty">-</button>
                                <input type="number" name="quantite" id="quantite" class="quantite-input" value="1"
                                    min="1" required>
                                <button type="button" class="quantite-btn" id="increase-qty">+</button>
                            </div>
                            <?php if ($produit_peut_negocier): ?>
                                <?php if (!empty($_SESSION['user_id'])): ?>
                                <button type="button" class="btn-negocier-prix" data-prix-neg-open
                                    aria-haspopup="dialog" aria-controls="prixNegModal">
                                    <i class="fas fa-handshake" aria-hidden="true"></i>
                                    <span>Négocier le prix</span>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn-negocier-prix" data-prix-neg-login-open
                                    data-login-redirect="<?php echo htmlspecialchars($produit_negociation_redirect, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-haspopup="dialog" aria-controls="prixNegLoginModal">
                                    <i class="fas fa-handshake" aria-hidden="true"></i>
                                    <span>Négocier le prix</span>
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>


                    <div class="produit-checkout-row">
                        <div class="prix-total-section">
                            <div class="prix-total-label">Prix total:</div>
                            <div class="prix-total-value" id="prix-total">
                                <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                            </div>
                        </div>
                        <button type="submit" class="btn-add-panier" id="btn-add-panier">
                            <i class="fa-solid fa-cart-shopping"></i>
                            <span class="btn-add-panier__label">Ajouter au panier</span>
                        </button>
                    </div>
                </form>

                <!-- Description (en bas) -->
                <!-- <?php if (!empty($produit['description'])): ?>
                    <div class="produit-description produit-section-bg">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($produit['description'])); ?></p>
                    </div>
                <?php endif; ?> -->
            </div>
        </div>

        <!-- Produits similaires (même rayon général, toutes boutiques — cartes type marketplace) -->
        <?php if (!empty($produits_similaires)): ?>
            <?php
            $card_partial_sim = __DIR__ . '/includes/partials/home_mp_product_card.php';
            $return_url_sim = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/produit.php?id=' . (int) $produit_id;
            $_produit_save = $produit;
            $_prix_affichage_save = $prix_affichage;
            ?>
            <section class="produit-similaires-mp" aria-labelledby="similaires-title">
                <header class="mp-block-head">
                    <div>
                        <h2 id="similaires-title">Produits similaires</h2>
                        <?php if (!empty($produit_generale_id)): ?>
                        <p class="mp-sub">Même rayon (toutes les boutiques).</p>
                        <?php endif; ?>
                    </div>
                </header>
                <div class="mp-grid" id="produits-similaires-grid">
                    <?php
                    foreach ($produits_similaires as $row_sim) {
                        $produit = $row_sim;
                        $return_url = $return_url_sim;
                        require $card_partial_sim;
                    }
                    $produit = $_produit_save;
                    $prix_affichage = $_prix_affichage_save;
                    ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php
    $prix_neg_modal_img = !empty($produit['image_principale'])
        ? upload_image_url($produit['image_principale'], 'md')
        : '';
    ?>
    <?php if ($produit_peut_negocier && empty($_SESSION['user_id'])): ?>
    <div class="prix-neg-modal prix-neg-modal--login" id="prixNegLoginModal" role="dialog" aria-modal="true"
        aria-labelledby="prixNegLoginTitle" aria-hidden="true" hidden>
        <div class="prix-neg-modal__backdrop" data-prix-neg-close tabindex="-1"></div>
        <div class="prix-neg-modal__panel prix-neg-modal__panel--login" role="document">
            <div class="prix-neg-modal__hero prix-neg-modal__hero--login">
                <span class="prix-neg-modal__hero-icon" aria-hidden="true"><i class="fas fa-user-circle"></i></span>
            </div>
            <div class="prix-neg-modal__head">
                <h2 class="prix-neg-modal__title" id="prixNegLoginTitle">Connexion requise</h2>
                <button type="button" class="prix-neg-modal__close" data-prix-neg-close aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <p class="prix-neg-modal__lead">Pour n&eacute;gocier le prix de ce produit, connectez-vous &agrave; votre compte client.</p>
            <div class="prix-neg-modal__actions prix-neg-modal__actions--stack">
                <a href="/choix-connexion.php?redirect=<?php echo rawurlencode($produit_negociation_redirect); ?>"
                    class="prix-neg-modal__btn prix-neg-modal__btn--submit" id="prixNegLoginBtn">Se connecter</a>
                <button type="button" class="prix-neg-modal__btn prix-neg-modal__btn--cancel" data-prix-neg-close">Annuler</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($produit_peut_negocier && !empty($_SESSION['user_id'])): ?>
    <div class="prix-neg-modal prix-neg-modal--propose" id="prixNegModal" role="dialog" aria-modal="true"
        aria-labelledby="prixNegModalTitle" aria-hidden="true" hidden>
        <div class="prix-neg-modal__backdrop" data-prix-neg-close tabindex="-1"></div>
        <div class="prix-neg-modal__panel prix-neg-modal__panel--propose" role="document">
            <div class="prix-neg-modal__head">
                <h2 class="prix-neg-modal__title" id="prixNegModalTitle">N&eacute;gocier le prix</h2>
                <button type="button" class="prix-neg-modal__close" data-prix-neg-close aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="prix-neg-modal__product-row">
                <?php if ($prix_neg_modal_img !== ''): ?>
                <div class="prix-neg-modal__thumb">
                    <img src="<?php echo htmlspecialchars($prix_neg_modal_img, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($produit['nom'], ENT_QUOTES, 'UTF-8'); ?>" width="88" height="88">
                </div>
                <?php endif; ?>
                <div class="prix-neg-modal__product-info">
                    <p class="prix-neg-modal__product"><?php echo htmlspecialchars($produit['nom'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="prix-neg-modal__ref">Prix affich&eacute; : <strong id="prix-neg-ref-display"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</strong></p>
                </div>
            </div>
            <p class="prix-neg-modal__question">Combien proposez-vous pour ce produit ?</p>
            <form method="POST" action="/user/prix-negociation-action.php" id="prix-neg-form">
                <input type="hidden" name="action" value="propose">
                <input type="hidden" name="produit_id" value="<?php echo (int) $produit_id; ?>">
                <input type="hidden" name="prix_reference" id="prix-neg-ref-input" value="<?php echo (float) $prix_affichage; ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($produit_negociation_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="number" name="prix_propose" id="prix-neg-propose" class="prix-neg-modal__input"
                    min="1" step="1" required placeholder="Votre prix en FCFA" inputmode="numeric">
                <div class="prix-neg-modal__actions">
                    <button type="button" class="prix-neg-modal__btn prix-neg-modal__btn--cancel" data-prix-neg-close">Annuler</button>
                    <button type="submit" class="prix-neg-modal__btn prix-neg-modal__btn--submit">Envoyer mon offre</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php include('footer.php') ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js" defer></script>
    <script>
        // Calcul automatique du prix total (variante + surcoûts)
        const prixBase = <?php echo $prix_produit_detail; ?>;
        const quantiteInput = document.getElementById('quantite');
        const prixTotalElement = document.getElementById('prix-total');
        const prixUnitaireInput = document.getElementById('option-prix-unitaire');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');
        const maxStock = 9999;

        function getPrixUnitaire() {
            var prix = prixBase;
            var selVariante = document.querySelector('.variante-option.selected, .variante-option input:checked');
            if (selVariante) {
                var el = selVariante.classList ? selVariante : selVariante.closest('.variante-option');
                if (el && el.dataset.prix) prix = parseFloat(el.dataset.prix);
            }
            var surcP = 0,
                surcT = 0;
            var spH = document.getElementById('option-surcout-poids');
            if (spH && spH.value) surcP = parseFloat(spH.value) || 0;
            var stH = document.getElementById('option-surcout-taille');
            if (stH && stH.value) surcT = parseFloat(stH.value) || 0;
            return prix + surcP + surcT;
        }

        function updatePrixTotal() {
            if (!quantiteInput || !prixTotalElement) return;
            const prixUnitaire = getPrixUnitaire();
            const quantite = parseInt(quantiteInput.value) || 1;
            const prixTotal = prixUnitaire * quantite;
            prixTotalElement.textContent = prixTotal.toLocaleString('fr-FR') + ' FCFA';
            if (prixUnitaireInput) prixUnitaireInput.value = prixUnitaire;

            var btnAdd = document.getElementById('btn-add-panier');
            if (btnAdd) btnAdd.disabled = (quantite <= 0);
        }

        var produitNomBase = '<?php echo addslashes(htmlspecialchars($produit['nom'])); ?>';

        function updatePrixEtNomAffichage() {
            var prixUnitaire = getPrixUnitaire();
            var selVariante = document.querySelector('.variante-option.selected, .variante-option input:checked');
            var el = selVariante && selVariante.classList ? selVariante : (selVariante ? selVariante.closest(
                '.variante-option') : null);
            var nomAffichage = produitNomBase;
            if (el && el.dataset.nom) nomAffichage = el.dataset.nom;

            var elNom = document.getElementById('produit-nom');
            var elPrix = document.getElementById('produit-prix-affichage');
            if (elNom) elNom.textContent = nomAffichage;

            if (elPrix) {
                var prixOrig = parseFloat(el && el.dataset.prixOriginal ? el.dataset.prixOriginal : 0) || 0;
                var pourcentage = parseInt(el && el.dataset.pourcentage ? el.dataset.pourcentage : 0) || 0;
                var surcP = 0,
                    surcT = 0;
                var spH = document.getElementById('option-surcout-poids');
                if (spH && spH.value) surcP = parseFloat(spH.value) || 0;
                var stH = document.getElementById('option-surcout-taille');
                if (stH && stH.value) surcT = parseFloat(stH.value) || 0;
                var prixOriginalAvecSurc = prixOrig + surcP + surcT;

                if (prixOrig > 0 && pourcentage > 0) {
                    elPrix.innerHTML = '<span class="prix-original">' + prixOriginalAvecSurc.toLocaleString('fr-FR') +
                        ' FCFA</span> ' +
                        '<span class="prix-promo">' + prixUnitaire.toLocaleString('fr-FR') + ' FCFA</span> ' +
                        '<span class="promo-badge">-' + pourcentage + '%</span>';
                } else {
                    elPrix.textContent = prixUnitaire.toLocaleString('fr-FR') + ' FCFA';
                }
            }
        }

        document.querySelectorAll('.variante-option').forEach(function (el) {
            el.addEventListener('click', function () {
                document.querySelectorAll('.variante-option').forEach(function (x) {
                    x.classList.remove('selected');
                });
                el.classList.add('selected');
                var inp = el.querySelector('input[type="radio"]');
                if (inp) inp.checked = true;
                var hid = document.getElementById('option-variante-id');
                var hnom = document.getElementById('option-variante-nom');
                var himg = document.getElementById('option-variante-image');
                if (hid) hid.value = el.dataset.id || '';
                if (hnom) hnom.value = el.dataset.nom || '';
                if (himg) himg.value = el.dataset.image || '';
                var mainImg = document.getElementById('produit-image-main');
                if (mainImg && el.dataset.image) mainImg.src = '/upload/' + el.dataset.image;
                else if (mainImg && !el.dataset.id) mainImg.src =
                    '/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>';
                updatePrixTotal();
                if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
            });
        });

        (function initVariantesCarousel() {
            var wrapper = document.querySelector('.variantes-carousel-wrapper');
            if (!wrapper) return;
            var scrollContainer = wrapper.querySelector('.variantes-scroll-container');
            var arrowLeft = wrapper.querySelector('.variantes-arrow-left');
            var arrowRight = wrapper.querySelector('.variantes-arrow-right');
            var total = parseInt(wrapper.dataset.total || 0, 10);
            if (!scrollContainer || !arrowLeft || !arrowRight || total <= 0) return;

            function getVisibleCount() {
                var w = window.innerWidth;
                if (w >= 1024) return 4;
                if (w >= 768) return 3;
                return 2;
            }

            function updateArrows() {
                var visible = getVisibleCount();
                if (total <= visible) {
                    arrowLeft.classList.remove('visible');
                    arrowRight.classList.remove('visible');
                    return;
                }
                var sc = scrollContainer.scrollLeft;
                var maxScroll = scrollContainer.scrollWidth - scrollContainer.clientWidth;
                arrowLeft.classList.toggle('visible', sc > 5);
                arrowRight.classList.toggle('visible', sc < maxScroll - 5);
            }

            scrollContainer.addEventListener('scroll', updateArrows);
            window.addEventListener('resize', function () {
                updateArrows();
            });

            arrowLeft.addEventListener('click', function () {
                var step = scrollContainer.clientWidth;
                scrollContainer.scrollBy({
                    left: -step,
                    behavior: 'smooth'
                });
            });
            arrowRight.addEventListener('click', function () {
                var step = scrollContainer.clientWidth;
                scrollContainer.scrollBy({
                    left: step,
                    behavior: 'smooth'
                });
            });

            updateArrows();
        })();

        function setupOptionSwatchListeners() {
            document.querySelectorAll('.poids-options-list .option-swatch-select').forEach(function (lbl) {
                lbl.addEventListener('click', function () {
                    lbl.closest('.poids-options-list').querySelectorAll('.option-swatch-select').forEach(
                        function (x) {
                            x.classList.remove('selected');
                        });
                    lbl.classList.add('selected');
                    var surc = document.getElementById('option-surcout-poids');
                    if (surc) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('.taille-options-list .option-swatch-select').forEach(function (lbl) {
                lbl.addEventListener('click', function () {
                    lbl.closest('.taille-options-list').querySelectorAll('.option-swatch-select').forEach(
                        function (x) {
                            x.classList.remove('selected');
                        });
                    lbl.classList.add('selected');
                    var surc = document.getElementById('option-surcout-taille');
                    if (surc) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('input[name="option_poids"]').forEach(function (rad) {
                rad.addEventListener('change', function () {
                    var lbl = this.closest('.option-swatch-select');
                    var surc = document.getElementById('option-surcout-poids');
                    if (surc && lbl) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('input[name="option_taille"]').forEach(function (rad) {
                rad.addEventListener('change', function () {
                    var lbl = this.closest('.option-swatch-select');
                    var surc = document.getElementById('option-surcout-taille');
                    if (surc && lbl) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
        }
        setupOptionSwatchListeners();

        var galleryThumbs = document.querySelectorAll('.gallery-thumb');
        var galleryMain = document.getElementById('produit-image-main');
        var galleryPrev = document.querySelector('.gallery-prev');
        var galleryNext = document.querySelector('.gallery-next');
        var galleryList = document.querySelector('.gallery-thumbs-list');
        if (galleryThumbs.length > 0 && galleryMain) {
            var currentIdx = 0;

            function setActiveThumb(idx) {
                galleryThumbs.forEach(function (t, i) {
                    t.classList.toggle('active', i === idx);
                });
                currentIdx = idx;
                var src = galleryThumbs[idx].getAttribute('data-src');
                if (src) galleryMain.src = src;
            }
            galleryThumbs.forEach(function (thumb, idx) {
                thumb.addEventListener('click', function () {
                    setActiveThumb(idx);
                });
            });
            if (galleryPrev) galleryPrev.addEventListener('click', function () {
                currentIdx = (currentIdx - 1 + galleryThumbs.length) % galleryThumbs.length;
                setActiveThumb(currentIdx);
                if (galleryList) galleryList.scrollLeft = galleryThumbs[currentIdx].offsetLeft - galleryList
                    .offsetWidth / 2 + 35;
            });
            if (galleryNext) galleryNext.addEventListener('click', function () {
                currentIdx = (currentIdx + 1) % galleryThumbs.length;
                setActiveThumb(currentIdx);
                if (galleryList) galleryList.scrollLeft = galleryThumbs[currentIdx].offsetLeft - galleryList
                    .offsetWidth / 2 + 35;
            });
        }

        if (quantiteInput) {
            quantiteInput.addEventListener('input', updatePrixTotal);
            quantiteInput.addEventListener('change', function () {
                let value = parseInt(this.value) || 1;
                if (value < 1) value = 1;
                if (value > maxStock) value = maxStock;
                this.value = value;
                updatePrixTotal();
            });
        }

        if (decreaseBtn) {
            decreaseBtn.addEventListener('click', function () {
                let value = parseInt(quantiteInput.value) || 1;
                if (value > 1) {
                    value--;
                    quantiteInput.value = value;
                    updatePrixTotal();
                }
            });
        }

        if (increaseBtn) {
            increaseBtn.addEventListener('click', function () {
                let value = parseInt(quantiteInput.value) || 1;
                if (value < maxStock) {
                    value++;
                    quantiteInput.value = value;
                    updatePrixTotal();
                }
            });
        }

        // Initialiser le prix total au chargement
        if (quantiteInput) {
            updatePrixTotal();
        }
    </script>
    <?php if ($produit_peut_negocier): ?>
    <script src="/js/prix-negociation-modal.js<?php echo asset_version_query(); ?>"></script>
    <?php endif; ?>

</body>

</html>