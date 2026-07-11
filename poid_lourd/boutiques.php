<?php
/**
 * Catalogue des boutiques partenaires — recherche + carte de proximité.
 */

require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/models/model_boutiques_marketplace.php';
require_once __DIR__ . '/includes/geo_location_service.php';
require_once __DIR__ . '/includes/marketplace_country_filter.php';
require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/marketplace_boutique_card_helpers.php';
require_once __DIR__ . '/includes/boutique_types.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';

$base = get_site_base_url();
$seo_title = 'Boutiques partenaires — ' . SITE_BRAND_NAME . ' | Marketplace Sénégal';
$seo_description = 'Découvrez les boutiques vendeurs partenaires sur ' . SITE_BRAND_NAME . '. Recherchez par nom et explorez les commerces proches de vous sur la carte.';
$seo_keywords = site_brand_seo_keywords_default() . ', boutiques partenaires, vendeurs, marketplace, géolocalisation';
$seo_canonical = $base . '/boutiques.php';

$search = trim((string) ($_GET['q'] ?? ''));
$filter_type_id = (int) ($_GET['type'] ?? 0);
$filter_dist_raw = (int) ($_GET['dist'] ?? 0);
$filter_dist = boutique_types_distance_is_valid($filter_dist_raw) ? $filter_dist_raw : 0;
$geo_loc = geo_session_get_location();
$geo_error = !empty($_GET['geo_error']);
$country = marketplace_get_selected_country_code();

$types_actifs = boutique_types_list_active();
$types_filter_available = count($types_actifs) > 0;

$boutiques_map_max = 25;
$rayon_proche_default = 15;
$has_geo = $geo_loc !== null;
$lat_proche = $has_geo ? (float) $geo_loc['lat'] : null;
$lng_proche = $has_geo ? (float) $geo_loc['lng'] : null;

$use_geo_catalog = $has_geo;
$catalog_rayon_km = $filter_dist > 0 ? boutique_types_distance_to_rayon_km($filter_dist) : 0.0;

$map_rayon_km = 0.0;
if ($has_geo) {
    if ($filter_dist > 0) {
        $map_rayon_km = boutique_types_distance_to_rayon_km($filter_dist);
    } elseif (!isset($_GET['dist'])) {
        $map_rayon_km = (float) $rayon_proche_default;
    }
}

$rayon_proche = $map_rayon_km > 0
    ? $map_rayon_km
    : ($filter_dist > 0 && !boutique_types_distance_is_unlimited($filter_dist)
        ? (float) $filter_dist
        : ($filter_dist > 0 ? 0.0 : (float) $rayon_proche_default));

$per_page = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$nb_boutiques = marketplace_count_boutiques(
    $search,
    $country,
    $use_geo_catalog ? $lat_proche : null,
    $use_geo_catalog ? $lng_proche : null,
    $use_geo_catalog ? $catalog_rayon_km : 0.0,
    false,
    $filter_type_id
);
$total_pages = max(1, (int) ceil($nb_boutiques / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$boutiques = marketplace_list_boutiques(
    $search,
    $per_page,
    $offset,
    $country,
    $use_geo_catalog ? $lat_proche : null,
    $use_geo_catalog ? $lng_proche : null,
    $use_geo_catalog ? $catalog_rayon_km : 0.0,
    false,
    $filter_type_id
);

$boutiques_vignettes = marketplace_boutiques_produits_vignettes(array_column($boutiques, 'id'), 10);
foreach ($boutiques as $bk => $boutique_row) {
    $boutiques[$bk]['_produits_vignettes'] = $boutiques_vignettes[(int) ($boutique_row['id'] ?? 0)] ?? [];
}
unset($boutiques_vignettes, $bk, $boutique_row);

$boutiques_proches = [];
$map_boutiques_all = [];
if ($has_geo) {
    $map_boutiques_all = marketplace_list_boutiques(
        '',
        $boutiques_map_max,
        0,
        $country,
        $lat_proche,
        $lng_proche,
        $map_rayon_km,
        true,
        $filter_type_id
    );
    $boutiques_proches = $map_boutiques_all;
}
$map_payload = marketplace_boutiques_map_payload($map_boutiques_all);
$nb_proches = count($boutiques_proches);

$nearby_map_shops = marketplace_boutiques_map_deco_logos(5, $country);

$redirect_map = '/boutiques.php';
$redirect_params = [];
if ($search !== '') {
    $redirect_params['q'] = $search;
}
if ($filter_type_id > 0) {
    $redirect_params['type'] = $filter_type_id;
}
if ($filter_dist > 0) {
    $redirect_params['dist'] = $filter_dist;
}
$redirect_params['open_map'] = 1;
$redirect_map .= '?' . http_build_query($redirect_params, '', '&', PHP_QUERY_RFC3986);

if (!function_exists('boutiques_catalog_page_url')) {
    function boutiques_catalog_page_url(int $target_page, string $search_q = '', int $type_id = 0, int $dist_km = 0): string
    {
        $params = [];
        if (trim($search_q) !== '') {
            $params['q'] = trim($search_q);
        }
        if ($type_id > 0) {
            $params['type'] = $type_id;
        }
        if ($dist_km > 0) {
            $params['dist'] = $dist_km;
        }
        if ($target_page > 1) {
            $params['page'] = $target_page;
        }
        $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return '/boutiques.php' . ($qs !== '' ? '?' . $qs : '');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/boutiques-marketplace.css<?php echo asset_version_query(); ?>">
</head>

<body class="mp-bt-body">
    <?php include 'nav_bar.php'; ?>

    <main class="mp-bt-page">
        <header class="mp-bt-page__hero">
            <div class="mp-bt-page__hero-inner">
                <h1 class="mp-bt-page__title">Boutiques partenaires</h1>
            </div>
        </header>

        <div class="mp-bt-page__container">
            <?php if ($geo_error): ?>
                <div class="mp-bt-alert mp-bt-alert--error" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    Impossible d'utiliser votre position. Réessayez dans un instant.
                </div>
            <?php endif; ?>

            <section class="mp-bt-nearby" aria-label="Boutiques proches">
                <div class="mp-bt-nearby__inner">
                    <?php if (!empty($nearby_map_shops)): ?>
                    <div class="mp-bt-nearby__markers" aria-hidden="true">
                        <?php foreach ($nearby_map_shops as $ms): ?>
                            <div class="mp-bt-nearby__shop"
                                style="--shop-left: <?php echo htmlspecialchars((string) $ms['left'], ENT_QUOTES, 'UTF-8'); ?>%; --shop-top: <?php echo htmlspecialchars((string) $ms['top'], ENT_QUOTES, 'UTF-8'); ?>%; --shop-left-m: <?php echo htmlspecialchars((string) $ms['left_m'], ENT_QUOTES, 'UTF-8'); ?>%; --shop-top-m: <?php echo htmlspecialchars((string) $ms['top_m'], ENT_QUOTES, 'UTF-8'); ?>%; animation-delay: <?php echo htmlspecialchars((string) $ms['delay'], ENT_QUOTES, 'UTF-8'); ?>s;">
                                <span class="mp-bt-nearby__shop-stem"></span>
                                <span class="mp-bt-nearby__shop-logo">
                                    <img src="<?php echo htmlspecialchars($ms['logo'], ENT_QUOTES, 'UTF-8'); ?>"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        onerror="this.closest('.mp-bt-nearby__shop').remove();">
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="mp-bt-nearby__glass">
                    <form class="mp-bt-toolbar mp-bt-toolbar--glass" method="get" action="/boutiques.php" role="search" id="mpBtCatalogForm">
                        <label class="mp-bt-search-v2" for="mpBtSearch">
                            <span class="visually-hidden">Rechercher une boutique</span>
                            <span class="mp-bt-search-v2__wrap">
                                <input type="search"
                                    id="mpBtSearch"
                                    name="q"
                                    class="mp-bt-search-v2__input"
                                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Nom, région, adresse…"
                                    autocomplete="off">
                                <div class="mp-bt-search-v2__filter-group">
                                    <button type="button"
                                        class="mp-bt-search-v2__filter-btn"
                                        id="mpBtFilterToggle"
                                        aria-expanded="false"
                                        aria-controls="mpBtFilterPanel">
                                        <i class="fas fa-sliders" aria-hidden="true"></i>
                                        <span>Filtres</span>
                                    </button>
                                </div>
                                <button type="submit" class="mp-bt-search-v2__btn">
                                    <span>Rechercher</span>
                                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </span>
                        </label>
                        <?php if ($filter_dist > 0 && $geo_loc === null): ?>
                            <p class="mp-bt-toolbar__hint" role="status">
                                <i class="fas fa-info-circle" aria-hidden="true"></i>
                                Le filtre distance nécessite votre position. Activez-la avec le bouton ci-dessous.
                            </p>
                        <?php endif; ?>
                    </form>

                    <div class="mp-bt-nearby__glass-foot">
                    <?php if ($geo_loc !== null): ?>
                    <div class="mp-bt-nearby__copy">
                        <?php if ($nb_proches === 0): ?>
                            <?php if (boutique_types_distance_is_unlimited($filter_dist)): ?>
                            <p>Aucune boutique géolocalisée disponible<?php echo $filter_type_id > 0 ? ' pour ce type' : ''; ?> pour le moment.</p>
                            <?php else: ?>
                            <p>Aucune boutique géolocalisée trouvée dans un rayon de <?php echo (int) $rayon_proche; ?> km<?php echo $filter_type_id > 0 ? ' pour ce type' : ''; ?> pour le moment.</p>
                            <?php endif; ?>
                        <?php elseif ($nb_proches > 0): ?>
                            <?php if (boutique_types_distance_is_unlimited($filter_dist)): ?>
                            <p>
                                <strong><?php echo (int) $nb_proches; ?></strong>
                                boutique<?php echo $nb_proches > 1 ? 's' : ''; ?> géolocalisée<?php echo $nb_proches > 1 ? 's' : ''; ?> (toutes distances)<?php echo $filter_type_id > 0 ? ' — type sélectionné' : ''; ?>.
                            </p>
                            <?php else: ?>
                            <p>
                                <strong><?php echo (int) $nb_proches; ?></strong>
                                boutique<?php echo $nb_proches > 1 ? 's' : ''; ?> la plus proche<?php echo $nb_proches > 1 ? 's' : ''; ?>
                                <?php if ($map_rayon_km > 0): ?>
                                (≤ <?php echo (int) $map_rayon_km; ?> km)
                                <?php endif; ?>
                                — max. <?php echo (int) $boutiques_map_max; ?> sur la carte.
                            </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($geo_loc === null): ?>
                        <div class="mp-bt-nearby__actions">
                            <?php if ($types_filter_available): ?>
                            <select class="mp-bt-nearby__filter" id="mpBtNearbyType" aria-label="Type de boutique">
                                <?php echo boutique_types_filter_options_html($filter_type_id); ?>
                            </select>
                            <?php endif; ?>
                            <form method="POST" action="/set-location.php" id="geo-locate-form">
                                <input type="hidden" name="geo_lat" id="geo_lat" value="">
                                <input type="hidden" name="geo_lng" id="geo_lng" value="">
                                <input type="hidden" name="geo_precision" id="geo_precision" value="">
                                <input type="hidden" name="geo_source" id="geo_source" value="gps">
                                <input type="hidden" name="redirect"
                                    value="<?php echo htmlspecialchars($redirect_map, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="button" class="mp-bt-nearby__btn" id="btn-geo-locate">
                                    <i class="fas fa-location-crosshairs" aria-hidden="true"></i>
                                    Voir les boutiques proches de chez moi
                                </button>
                            </form>
                            <div id="geo-status" class="mp-bt-geo-status" hidden></div>
                        </div>
                    <?php else: ?>
                        <div class="mp-bt-nearby__actions">
                            <button type="button"
                                class="mp-bt-nearby__btn mp-bt-nearby__btn--map"
                                id="mpBtOpenMap"
                                <?php echo empty($map_payload) ? 'disabled' : ''; ?>>
                                <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                                Voir sur la carte
                            </button>
                        </div>
                    <?php endif; ?>
                    </div>
                    </div>
                </div>
            </section>

            <p class="mp-bt-results-count">
                <strong><?php echo (int) $nb_boutiques; ?></strong>
                boutique<?php echo $nb_boutiques > 1 ? 's' : ''; ?> partenaire<?php echo $nb_boutiques > 1 ? 's' : ''; ?>
                <?php if ($search !== ''): ?>
                    pour « <?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?> »
                <?php endif; ?>
                <?php if ($filter_type_id > 0): ?>
                    — type filtré
                <?php endif; ?>
                <?php if ($use_geo_catalog): ?>
                    <?php if ($filter_dist > 0 && !boutique_types_distance_is_unlimited($filter_dist)): ?>
                    — à moins de <?php echo (int) $filter_dist; ?> km
                    <?php elseif ($filter_dist > 0 && boutique_types_distance_is_unlimited($filter_dist)): ?>
                    — toutes distances
                    <?php else: ?>
                    — triées par proximité
                    <?php endif; ?>
                <?php endif; ?>
            </p>

            <?php if (empty($boutiques)): ?>
                <div class="mp-bt-empty">
                    <i class="fas fa-store-slash" aria-hidden="true"></i>
                    <h2>Aucune boutique trouvée</h2>
                    <p>
                        <?php if ($search !== ''): ?>
                            Essayez un autre mot-clé ou consultez toutes les boutiques.
                        <?php else: ?>
                            Les boutiques vendeurs apparaîtront ici dès leur inscription.
                        <?php endif; ?>
                    </p>
                    <?php if ($search !== ''): ?>
                        <a href="/boutiques.php" class="mp-bt-empty__link">Voir toutes les boutiques</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mp-bt-grid mp-bt-grid--cards" role="list">
                    <?php foreach ($boutiques as $boutique):
                        include __DIR__ . '/includes/partials/boutique_marketplace_card.php';
                    endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="mp-bt-pagination" aria-label="Pagination des boutiques">
                        <?php if ($page > 1): ?>
                            <a class="mp-bt-pagination__btn" href="<?php echo htmlspecialchars(boutiques_catalog_page_url($page - 1, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page précédente">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <span class="mp-bt-pagination__btn mp-bt-pagination__btn--disabled" aria-disabled="true"><i class="fas fa-chevron-left" aria-hidden="true"></i></span>
                        <?php endif; ?>

                        <?php
                        $page_window_start = max(1, $page - 2);
                        $page_window_end = min($total_pages, $page + 2);
                        if ($page_window_start > 1): ?>
                            <a class="mp-bt-pagination__btn" href="<?php echo htmlspecialchars(boutiques_catalog_page_url(1, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                            <?php if ($page_window_start > 2): ?>
                                <span class="mp-bt-pagination__ellipsis" aria-hidden="true">…</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($pi = $page_window_start; $pi <= $page_window_end; $pi++): ?>
                            <?php if ($pi === $page): ?>
                                <span class="mp-bt-pagination__btn mp-bt-pagination__btn--active" aria-current="page"><?php echo (int) $pi; ?></span>
                            <?php else: ?>
                                <a class="mp-bt-pagination__btn" href="<?php echo htmlspecialchars(boutiques_catalog_page_url($pi, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $pi; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page_window_end < $total_pages): ?>
                            <?php if ($page_window_end < $total_pages - 1): ?>
                                <span class="mp-bt-pagination__ellipsis" aria-hidden="true">…</span>
                            <?php endif; ?>
                            <a class="mp-bt-pagination__btn" href="<?php echo htmlspecialchars(boutiques_catalog_page_url($total_pages, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $total_pages; ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a class="mp-bt-pagination__btn" href="<?php echo htmlspecialchars(boutiques_catalog_page_url($page + 1, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page suivante">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <span class="mp-bt-pagination__btn mp-bt-pagination__btn--disabled" aria-disabled="true"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                        <?php endif; ?>
                    </nav>
                    <p class="mp-bt-pagination__info">
                        Page <strong><?php echo (int) $page; ?></strong> sur <strong><?php echo (int) $total_pages; ?></strong>
                        — <?php echo (int) $nb_boutiques; ?> boutique<?php echo $nb_boutiques > 1 ? 's' : ''; ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <div class="mp-bt-map-modal" id="mpBtMapModal" hidden aria-hidden="true">
        <div class="mp-bt-map-modal__backdrop" data-map-close></div>
        <div class="mp-bt-map-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mpBtMapTitle">
            <header class="mp-bt-map-modal__head">
                <div class="mp-bt-map-modal__head-main">
                    <p class="mp-bt-map-modal__eyebrow">Proximité</p>
                    <h2 id="mpBtMapTitle">Boutiques proches de vous</h2>
                </div>
                <div class="mp-bt-map-modal__filters" role="group" aria-label="Filtres carte">
                    <?php if ($types_filter_available): ?>
                    <label class="mp-bt-map-modal__filter">
                        <span class="visually-hidden">Type de boutique</span>
                        <select id="mpBtMapFilterType" aria-label="Type de boutique">
                            <?php echo boutique_types_filter_options_html($filter_type_id); ?>
                        </select>
                    </label>
                    <?php endif; ?>
                    <label class="mp-bt-map-modal__filter">
                        <span class="visually-hidden">Distance</span>
                        <select id="mpBtMapFilterDist" aria-label="Distance">
                            <?php echo boutique_types_distance_options_html($filter_dist); ?>
                        </select>
                    </label>
                </div>
                <button type="button" class="mp-bt-map-modal__close" data-map-close aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </header>
            <div class="mp-bt-map-modal__layout">
                <div class="mp-bt-map-modal__map-wrap">
                    <button type="button"
                        class="mp-bt-map-list-toggle"
                        id="mpBtMapListToggle"
                        aria-expanded="false"
                        aria-controls="mpBtMapList">
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <span>Boutiques trouvées</span>
                        <span class="mp-bt-map-list-toggle__count" id="mpBtMapListCount">0</span>
                    </button>

                    <aside class="mp-bt-map-list" id="mpBtMapList" aria-label="Liste des boutiques proches">
                        <header class="mp-bt-map-list__head">
                            <h3>Boutiques trouvées</h3>
                            <button type="button" class="mp-bt-map-list__close" id="mpBtMapListClose" aria-label="Fermer la liste">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </header>
                        <ul class="mp-bt-map-list__items" id="mpBtMapListItems" role="list"></ul>
                    </aside>

                    <div id="mpBtMapCanvas" class="mp-bt-map-modal__map" aria-label="Carte des boutiques"></div>

                    <aside class="mp-bt-map-detail" id="mpBtMapSide" hidden aria-label="Détail boutique">
                        <header class="mp-bt-map-detail__head">
                            <button type="button" class="mp-bt-map-detail__close" data-map-detail-close aria-label="Fermer">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </header>
                        <div class="mp-bt-map-side__detail" id="mpBtMapSideDetail">
                            <div class="mp-bt-map-side__logo" id="mpBtMapSideLogo"></div>
                            <h3 id="mpBtMapSideName"></h3>
                            <p class="mp-bt-map-side__dist" id="mpBtMapSideDist"></p>
                            <p class="mp-bt-map-side__addr" id="mpBtMapSideAddr"></p>
                            <div class="mp-bt-map-side__actions">
                                <a href="#" class="mp-bt-map-side__btn mp-bt-map-side__btn--primary" id="mpBtMapSideVisit" target="_blank" rel="noopener">
                                    <i class="fas fa-store" aria-hidden="true"></i> Voir la boutique
                                </a>
                                <a href="#" class="mp-bt-map-side__btn mp-bt-map-side__btn--ghost" id="mpBtMapSideMaps" target="_blank" rel="noopener">
                                    <i class="fab fa-google" aria-hidden="true"></i> Google Maps
                                </a>
                                <button type="button" class="mp-bt-map-side__btn mp-bt-map-side__btn--share" id="mpBtMapShareShop">
                                    <i class="fas fa-share-nodes" aria-hidden="true"></i> Partager la boutique
                                </button>
                                <button type="button" class="mp-bt-map-side__btn mp-bt-map-side__btn--share" id="mpBtMapShareGeo">
                                    <i class="fas fa-location-arrow" aria-hidden="true"></i> Partager la localisation
                                </button>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="mpBtMapData"><?php
        echo json_encode([
            'user' => $geo_loc ? ['lat' => (float) $geo_loc['lat'], 'lng' => (float) $geo_loc['lng']] : null,
            'boutiques' => $map_payload,
            'map_max' => $boutiques_map_max,
            'filters' => [
                'type_id' => $filter_type_id,
                'dist_km' => $filter_dist,
            ],
            'types' => boutique_types_filter_json_list(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?></script>

    <?php include 'footer.php'; ?>
    <?php require __DIR__ . '/includes/partials/platform_share_modal.php'; ?>

    <div class="mp-bt-filter-layer" id="mpBtFilterLayer" hidden aria-hidden="true">
        <button type="button" class="mp-bt-filter-layer__backdrop" id="mpBtFilterBackdrop" aria-label="Fermer les filtres"></button>
        <div class="mp-bt-search-v2__filter-panel" id="mpBtFilterPanel" role="dialog" aria-modal="true" aria-labelledby="mpBtFilterPanelTitle">
            <div class="mp-bt-search-v2__filter-panel-head">
                <h2 class="mp-bt-search-v2__filter-panel-title" id="mpBtFilterPanelTitle">Filtres</h2>
                <button type="button" class="mp-bt-search-v2__filter-panel-close" id="mpBtFilterClose" aria-label="Fermer les filtres">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <?php if ($types_filter_available): ?>
            <label class="mp-bt-search-v2__filter-field" for="mpBtFilterType">
                <span class="mp-bt-search-v2__filter-label">Type de boutique</span>
                <select form="mpBtCatalogForm" name="type" class="mp-bt-search-v2__filter" id="mpBtFilterType" aria-label="Type de boutique">
                    <?php echo boutique_types_filter_options_html($filter_type_id); ?>
                </select>
            </label>
            <?php endif; ?>
            <label class="mp-bt-search-v2__filter-field" for="mpBtFilterDist">
                <span class="mp-bt-search-v2__filter-label">Distance</span>
                <select form="mpBtCatalogForm" name="dist" class="mp-bt-search-v2__filter" id="mpBtFilterDist" aria-label="Distance"
                    <?php echo $geo_loc === null ? 'title="Activez votre position pour filtrer par distance"' : ''; ?>>
                    <?php echo boutique_types_distance_options_html($filter_dist); ?>
                </select>
            </label>
        </div>
    </div>

    <div class="mp-bt-geo-loading" id="mpBtGeoLoading" hidden aria-hidden="true" role="alertdialog" aria-labelledby="mpBtGeoLoadingTitle" aria-describedby="mpBtGeoLoadingText">
        <div class="mp-bt-geo-loading__backdrop"></div>
        <div class="mp-bt-geo-loading__dialog">
            <h2 class="mp-bt-geo-loading__title" id="mpBtGeoLoadingTitle">Recherche en cours</h2>
            <p class="mp-bt-geo-loading__text" id="mpBtGeoLoadingText">Veuillez patienter s'il vous plaît.</p>
            <div class="mp-bt-geo-loading__bar" aria-hidden="true">
                <span class="mp-bt-geo-loading__bar-fill" id="mpBtGeoLoadingBar"></span>
            </div>
            <p class="mp-bt-geo-loading__pct" id="mpBtGeoLoadingPct" aria-live="polite">0&nbsp;%</p>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="/js/geo-location.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-nav-apps.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/boutiques-proches-map.js<?php echo asset_version_query(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.GeoLocationCapture) {
                return;
            }
            var locateForm = document.getElementById('geo-locate-form');
            var geoLoading = document.getElementById('mpBtGeoLoading');
            var geoLoadingBar = document.getElementById('mpBtGeoLoadingBar');
            var geoLoadingPct = document.getElementById('mpBtGeoLoadingPct');
            var geoProgressTimer = null;
            var geoProgressValue = 0;

            function stopGeoProgress() {
                if (geoProgressTimer) {
                    clearInterval(geoProgressTimer);
                    geoProgressTimer = null;
                }
            }

            function setGeoProgress(value) {
                geoProgressValue = Math.max(0, Math.min(100, value));
                if (geoLoadingBar) {
                    geoLoadingBar.style.width = geoProgressValue + '%';
                }
                if (geoLoadingPct) {
                    geoLoadingPct.textContent = geoProgressValue + ' %';
                }
            }

            function showGeoLoading() {
                if (!geoLoading) {
                    return;
                }
                stopGeoProgress();
                setGeoProgress(0);
                geoLoading.hidden = false;
                geoLoading.setAttribute('aria-hidden', 'false');
                document.body.classList.add('mp-bt-geo-loading-open');
                geoProgressTimer = setInterval(function () {
                    if (geoProgressValue >= 92) {
                        return;
                    }
                    var step = geoProgressValue < 55 ? 4 : (geoProgressValue < 80 ? 2 : 1);
                    setGeoProgress(geoProgressValue + step);
                }, 180);
            }

            function hideGeoLoading() {
                stopGeoProgress();
                if (!geoLoading) {
                    return;
                }
                geoLoading.hidden = true;
                geoLoading.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('mp-bt-geo-loading-open');
                setGeoProgress(0);
            }

            function finishGeoLoading(callback) {
                stopGeoProgress();
                setGeoProgress(100);
                window.setTimeout(function () {
                    hideGeoLoading();
                    if (typeof callback === 'function') {
                        callback();
                    }
                }, 350);
            }

            if (locateForm) {
                window.GeoLocationCapture.init({
                    latInput: 'geo_lat',
                    lngInput: 'geo_lng',
                    precisionInput: 'geo_precision',
                    sourceInput: 'geo_source',
                    statusEl: 'geo-status',
                    auto: false,
                    onSuccess: function () {
                        finishGeoLoading(function () {
                            var typeSel = document.getElementById('mpBtNearbyType');
                            var distSel = document.getElementById('mpBtFilterDist');
                            var redirectInput = locateForm.querySelector('[name=redirect]');
                            if (redirectInput) {
                                try {
                                    var u = new URL(redirectInput.value, window.location.origin);
                                    if (typeSel && typeSel.value) {
                                        u.searchParams.set('type', typeSel.value);
                                    } else {
                                        u.searchParams.delete('type');
                                    }
                                    if (distSel && distSel.value && distSel.value !== '0') {
                                        u.searchParams.set('dist', distSel.value);
                                    } else {
                                        u.searchParams.set('dist', '15');
                                    }
                                    redirectInput.value = u.pathname + u.search;
                                } catch (e) { /* ignore */ }
                            }
                            locateForm.submit();
                        });
                    },
                    onError: function () {
                        hideGeoLoading();
                    }
                });

                var geoLocateBtn = document.getElementById('btn-geo-locate');
                if (geoLocateBtn) {
                    geoLocateBtn.addEventListener('click', function (event) {
                        event.preventDefault();
                        showGeoLoading();
                        window.GeoLocationCapture.capture(false, { fresh: true });
                    });
                }
            }

            var filterToggle = document.getElementById('mpBtFilterToggle');
            var filterLayer = document.getElementById('mpBtFilterLayer');
            var filterPanel = document.getElementById('mpBtFilterPanel');
            var filterBackdrop = document.getElementById('mpBtFilterBackdrop');
            var filterCloseBtn = document.getElementById('mpBtFilterClose');
            if (filterToggle && filterLayer && filterPanel) {
                function closeFilterPanel() {
                    filterLayer.hidden = true;
                    filterLayer.setAttribute('aria-hidden', 'true');
                    filterToggle.setAttribute('aria-expanded', 'false');
                    document.body.classList.remove('mp-bt-filter-open');
                }

                function openFilterPanel() {
                    filterLayer.hidden = false;
                    filterLayer.setAttribute('aria-hidden', 'false');
                    filterToggle.setAttribute('aria-expanded', 'true');
                    document.body.classList.add('mp-bt-filter-open');
                }

                filterToggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (filterLayer.hidden) {
                        openFilterPanel();
                    } else {
                        closeFilterPanel();
                    }
                });

                if (filterBackdrop) {
                    filterBackdrop.addEventListener('click', closeFilterPanel);
                }
                if (filterCloseBtn) {
                    filterCloseBtn.addEventListener('click', closeFilterPanel);
                }

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !filterLayer.hidden) {
                        closeFilterPanel();
                        filterToggle.focus();
                    }
                });

                var catalogType = document.getElementById('mpBtFilterType');
                var catalogDist = document.getElementById('mpBtFilterDist');
                var hasActiveFilter = (catalogType && catalogType.value !== '') || (catalogDist && catalogDist.value !== '0');
                if (hasActiveFilter) {
                    filterToggle.classList.add('is-active');
                }
            }

            var urlParams = new URLSearchParams(window.location.search || '');
            var shouldOpenMap = urlParams.get('open_map') === '1';
            if (shouldOpenMap && typeof window.openBoutiquesMapModal === 'function') {
                var mapBtn = document.getElementById('mpBtOpenMap');
                if (!mapBtn || !mapBtn.disabled) {
                    window.openBoutiquesMapModal();
                }
                urlParams.delete('open_map');
                var cleanQuery = urlParams.toString();
                var cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '');
                window.history.replaceState({}, '', cleanUrl);
            }
        });
    </script>
</body>

</html>
