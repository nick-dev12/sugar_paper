<?php
if (!defined('SITE_BRAND_TAGLINE')) {
    require_once __DIR__ . '/includes/site_brand.php';
}
if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/includes/asset_version.php';
}
$asset_version = isset($asset_version) ? $asset_version : get_asset_version();
$u_home = $GLOBALS['nav_home'] ?? '/index.php';
$u_produits = $GLOBALS['nav_produits'] ?? '/produits.php';
$u_panier = $GLOBALS['nav_panier'] ?? '/panier.php';
$u_nouveautes = $GLOBALS['nav_nouveautes'] ?? '/nouveautes.php';
$u_promo = $GLOBALS['nav_promo'] ?? '/promo.php';
$u_contact = $GLOBALS['nav_contact'] ?? '/contact.php';
$nav_show_country_filter = false;
$__nav_req_path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (strpos($__nav_req_path, '/admin/') !== 0 && strpos($__nav_req_path, '/super_admin/') !== 0) {
    $nav_show_country_filter = true;
}
$nav_show_region_filter = false;
$nav_needs_country_welcome = false;
$nav_suggested_country = 'SN';
$nav_region_selected = '';
$nav_region_selected_label = 'Toutes les régions';
$nav_country_selected = '';
$nav_country_selected_label = 'Pays';
$nav_country_flag_url = '';
$nav_country_flag_url_alt = '';
$nav_geo_redirect = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/index.php';
if ($nav_geo_redirect === '' || strpos($nav_geo_redirect, '/') !== 0 || strpos($nav_geo_redirect, '//') === 0) {
    $nav_geo_redirect = '/index.php';
}
$nav_regions_for_country = [];
if ($nav_show_country_filter) {
    require_once __DIR__ . '/includes/marketplace_country_filter.php';
    require_once __DIR__ . '/includes/marketplace_countries.php';
    require_once __DIR__ . '/includes/geo_regions.php';
    $nav_needs_country_welcome = marketplace_needs_country_welcome();
    if ($nav_needs_country_welcome) {
        require_once __DIR__ . '/includes/ip_geo_resolver.php';
        $nav_suggested_country = ip_geo_detect_country_code();
        if (!marketplace_country_is_valid($nav_suggested_country)) {
            $nav_suggested_country = marketplace_country_default_code();
        }
    } else {
        marketplace_bootstrap_country_from_ip();
        $nav_country_selected = marketplace_get_selected_country_code() ?? '';
        $nav_country_label_full = marketplace_get_selected_country_label();
        if ($nav_country_label_full !== '') {
            $nav_country_selected_label = $nav_country_selected;
            $nav_country_flag_url = marketplace_country_flag_url($nav_country_selected, 40);
            $nav_country_flag_url_alt = marketplace_country_flag_url_alt($nav_country_selected, 40);
        }
    }
}
if ($nav_show_country_filter && !$nav_needs_country_welcome) {
    require_once __DIR__ . '/includes/marketplace_region_filter.php';
    if (marketplace_region_filter_applies()) {
        $nav_show_region_filter = true;
        $nav_country_for_regions = marketplace_region_context_country();
        $nav_regions_for_country = geo_regions_for_country($nav_country_for_regions);
        $nav_region_selected = marketplace_get_selected_region_code() ?? '';
        $nav_region_selected_label = marketplace_get_selected_region_label();
    }
}
$nav_region_redirect = $nav_geo_redirect;
if (!function_exists('nav_categorie_href')) {
    require_once __DIR__ . '/includes/marketplace_helpers.php';
}
$categories_menu = [];
$nav_megamenu = [];
if (file_exists(__DIR__ . '/models/model_categories.php')) {
    require_once __DIR__ . '/models/model_categories.php';
    $boutique_mid = defined('BOUTIQUE_ADMIN_ID') ? (int) BOUTIQUE_ADMIN_ID : null;
    if ($boutique_mid > 0 && function_exists('get_all_categories_for_vendeur')) {
        // Vitrine : uniquement les catégories ayant des produits de ce vendeur (pas tout le méga-menu plateforme)
        $categories_menu = get_all_categories_for_vendeur($boutique_mid);
        $nav_megamenu = [];
    } elseif (function_exists('categories_hierarchy_enabled') && categories_hierarchy_enabled() && function_exists('get_megamenu_categories')) {
        $nav_megamenu = get_megamenu_categories(null);
        foreach ($nav_megamenu as $bl) {
            $categories_menu[] = $bl['general'];
        }
    } else {
        $categories_menu = get_all_categories();
    }
}
// Compter les articles du panier (connecté ou invité)
$panier_count = 0;
$panier_invite_path = file_exists(__DIR__ . '/includes/panier_invite.php')
    ? __DIR__ . '/includes/panier_invite.php'
    : dirname(__DIR__) . '/includes/panier_invite.php';
if (file_exists($panier_invite_path)) {
    require_once $panier_invite_path;
}
if (isset($_SESSION['user_id'])) {
    $conn_path = file_exists(__DIR__ . '/conn/conn.php') ? __DIR__ . '/conn/conn.php' : dirname(__DIR__) . '/conn/conn.php';
    if (file_exists($conn_path)) {
        require_once $conn_path;
    }
    $model_path = file_exists(__DIR__ . '/models/model_panier.php')
        ? __DIR__ . '/models/model_panier.php'
        : dirname(__DIR__) . '/models/model_panier.php';

    if (file_exists($model_path)) {
        require_once $model_path;
        $panier_vid = null;
        if (defined('BOUTIQUE_ADMIN_ID') && (int) BOUTIQUE_ADMIN_ID > 0) {
            $panier_vid = (int) BOUTIQUE_ADMIN_ID;
        } elseif (!empty($GLOBALS['nav_panier_count_for_vendeur_id'])) {
            $panier_vid = (int) $GLOBALS['nav_panier_count_for_vendeur_id'];
        }
        if ($panier_vid > 0 && function_exists('count_panier_items_for_vendeur')) {
            $panier_count = count_panier_items_for_vendeur($_SESSION['user_id'], $panier_vid);
        } else {
            $panier_count = count_panier_items($_SESSION['user_id']);
        }
    }
} elseif (function_exists('panier_invite_count_items')) {
    $panier_count = panier_invite_count_items();
}
$nav_panier_connect_redirect = $GLOBALS['nav_panier_login_redirect'] ?? '/panier.php';
$nav_compte_href = '/choix-connexion.php';
$nav_compte_btn_class = 'nav-compte-btn';
$nav_compte_title = 'Mon compte';
$nav_compte_subtitle = 'Se connecter';
$nav_compte_avatar_letter = '';
$nav_compte_logged = false;

if (isset($_SESSION['commercant_id'])) {
    $nav_compte_logged = true;
    $nav_compte_href = '/view/profil_commercent.php';
    if (isset($commercant) && !empty($commercant['nom'])) {
        $explode_nom = explode(' ', $commercant['nom']);
        $nav_compte_subtitle = htmlspecialchars($explode_nom[0] ?? $commercant['nom'], ENT_QUOTES, 'UTF-8');
    }
} elseif (isset($_SESSION['admin_id'])) {
    $nav_compte_logged = true;
    $nav_compte_href = '/admin/dashboard.php';
    $nav_compte_btn_class .= ' nav-compte-btn--admin';
    $__role_nav = (string) ($_SESSION['admin_role'] ?? 'admin');
    if ($__role_nav === 'vendeur') {
        $nav_compte_btn_class .= ' nav-compte-btn--boutique';
        $__bn = trim((string) ($_SESSION['admin_boutique_nom'] ?? ''));
        if ($__bn === '') {
            $mid_nav = (int) ($_SESSION['admin_id'] ?? 0);
            if ($mid_nav > 0 && file_exists(__DIR__ . '/models/model_admin.php')) {
                require_once __DIR__ . '/models/model_admin.php';
                if (function_exists('get_admin_by_id')) {
                    $__adm_nav = get_admin_by_id($mid_nav);
                    if ($__adm_nav) {
                        $__bn = trim((string) ($__adm_nav['boutique_nom'] ?? ''));
                        if ($__bn !== '') {
                            $_SESSION['admin_boutique_nom'] = $__bn;
                        }
                        $__bs = trim((string) ($__adm_nav['boutique_slug'] ?? ''));
                        if ($__bs !== '') {
                            $_SESSION['admin_boutique_slug'] = $__bs;
                        }
                    }
                }
            }
        }
        if ($__bn !== '') {
            $nav_compte_title = htmlspecialchars($__bn, ENT_QUOTES, 'UTF-8');
            $nav_compte_subtitle = htmlspecialchars('Ma boutique', ENT_QUOTES, 'UTF-8');
        } else {
            $nav_compte_title = htmlspecialchars('Ma boutique', ENT_QUOTES, 'UTF-8');
            $__nom_ad = trim((string) ($_SESSION['admin_nom'] ?? ''));
            $nav_compte_subtitle = htmlspecialchars($__nom_ad !== '' ? $__nom_ad : 'Espace vendeur', ENT_QUOTES, 'UTF-8');
        }
    } else {
        $nav_compte_title = htmlspecialchars('Espace équipe', ENT_QUOTES, 'UTF-8');
        $__nom_ad = trim((string) ($_SESSION['admin_nom'] ?? ''));
        $nav_compte_subtitle = htmlspecialchars($__nom_ad !== '' ? $__nom_ad : 'Administration', ENT_QUOTES, 'UTF-8');
    }
} elseif (isset($_SESSION['user_id'])) {
    $nav_compte_logged = true;
    $nav_compte_href = '/user/mon-compte.php';
    $__nom_cli = trim((string) ($_SESSION['user_nom'] ?? ''));
    if ($__nom_cli !== '') {
        $nav_compte_title = htmlspecialchars($__nom_cli, ENT_QUOTES, 'UTF-8');
        $nav_compte_subtitle = htmlspecialchars('Mon compte', ENT_QUOTES, 'UTF-8');
    } elseif (!empty($_SESSION['user_prenom'])) {
        $nav_compte_title = htmlspecialchars(trim((string) $_SESSION['user_prenom']), ENT_QUOTES, 'UTF-8');
        $nav_compte_subtitle = htmlspecialchars('Mon compte', ENT_QUOTES, 'UTF-8');
    }
}

if ($nav_compte_logged) {
    $nav_compte_btn_class .= ' nav-compte-btn--logged';
}

$nav_annonces_count = 0;
$nav_annonces_href = '';
$nav_annonces_show = false;
$nav_annonces_model = __DIR__ . '/models/model_annonces.php';
if (file_exists($nav_annonces_model)) {
    require_once $nav_annonces_model;
    if (function_exists('annonces_table_exists') && annonces_table_exists()) {
        if (isset($_SESSION['user_id'])) {
            $nav_annonces_count = annonce_count_unread_client((int) $_SESSION['user_id']);
            $nav_annonces_href = '/user/annonces.php';
            $nav_annonces_show = true;
        } elseif (isset($_SESSION['admin_id'])) {
            $__nav_admin_role = (string) ($_SESSION['admin_role'] ?? '');
            if ($__nav_admin_role === 'vendeur') {
                $nav_annonces_count = annonce_count_unread_vendeur((int) $_SESSION['admin_id']);
                $nav_annonces_href = '/admin/annonces.php';
                $nav_annonces_show = true;
            }
        }
    } elseif (isset($_SESSION['user_id'])) {
        $nav_annonces_href = '/user/annonces.php';
        $nav_annonces_show = true;
    } elseif (isset($_SESSION['admin_id']) && (string) ($_SESSION['admin_role'] ?? '') === 'vendeur') {
        $nav_annonces_href = '/admin/annonces.php';
        $nav_annonces_show = true;
    }
}

if (!function_exists('nav_compte_pick_initial')) {
    function nav_compte_pick_initial(...$sources)
    {
        foreach ($sources as $source) {
            $text = trim((string) $source);
            if ($text === '') {
                continue;
            }
            if (function_exists('mb_substr')) {
                return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8');
            }
            return strtoupper(substr($text, 0, 1));
        }
        return '';
    }
}

if ($nav_compte_logged) {
    if (isset($_SESSION['user_id'])) {
        $nav_compte_avatar_letter = nav_compte_pick_initial(
            $_SESSION['user_prenom'] ?? '',
            $_SESSION['user_nom'] ?? '',
            strip_tags((string) $nav_compte_title)
        );
    } elseif (isset($_SESSION['admin_id'])) {
        $__role_nav_avatar = (string) ($_SESSION['admin_role'] ?? 'admin');
        if ($__role_nav_avatar === 'vendeur') {
            $nav_compte_avatar_letter = nav_compte_pick_initial(
                $_SESSION['admin_boutique_nom'] ?? '',
                $_SESSION['admin_prenom'] ?? '',
                $_SESSION['admin_nom'] ?? '',
                strip_tags((string) $nav_compte_title)
            );
        } else {
            $nav_compte_avatar_letter = nav_compte_pick_initial(
                $_SESSION['admin_prenom'] ?? '',
                $_SESSION['admin_nom'] ?? '',
                strip_tags((string) $nav_compte_title)
            );
        }
    } elseif (isset($_SESSION['commercant_id']) && isset($commercant) && !empty($commercant['nom'])) {
        $nav_compte_avatar_letter = nav_compte_pick_initial($commercant['nom']);
    }
}
$nav_panier_href = isset($_SESSION['user_id']) ? $u_panier : '/panier.php';

$shop_nav_script = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ($_SERVER['PHP_SELF'] ?? ''));
$shop_dock_home_href = '/index.php';
$shop_dock_home_act = (defined('BOUTIQUE_ADMIN_ID') && (int) BOUTIQUE_ADMIN_ID > 0)
    ? false
    : ($shop_nav_script === 'index.php' && strpos($_SERVER['REQUEST_URI'] ?? '', '/boutique/') === false);
$shop_dock_produits_act = in_array($shop_nav_script, ['produits.php', 'categorie.php', 'produit.php'], true);
$shop_dock_panier_act = in_array($shop_nav_script, ['panier.php', 'commande.php'], true);
$shop_dock_compte_act = (strpos($_SERVER['REQUEST_URI'] ?? '', '/user/') !== false);
?>
<link rel="stylesheet" href="/css/variables.css<?php echo $asset_version ? '?v=' . $asset_version : ''; ?>">
<link rel="stylesheet" href="/css/nabare.css<?php echo $asset_version ? '?v=' . $asset_version : ''; ?>">
<link rel="stylesheet" href="/css/shop-mobile-dock.css<?php echo $asset_version ? '?v=' . $asset_version : ''; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<?php
// Injection des couleurs personnalisées du vendeur — après variables.css pour que la surcharge prenne effet
if (defined('BOUTIQUE_ADMIN_ID') && (int) BOUTIQUE_ADMIN_ID > 0) {
    if (!function_exists('boutique_echo_theme_style_override')) {
        require_once __DIR__ . '/includes/boutique_vendeur_display.php';
    }
    boutique_echo_theme_style_override();
}
?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="dns-prefetch" href="https://code.jquery.com">
<style>
/* Nav style Planète Gâteau - fond dégradé, barre recherche, Mon compte, panier */
.nav-planete-gateau {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 12px 30px;
    background: var(--blanc);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.5);
    position: sticky;
    top: 0;
    z-index: 11000;
    overflow: visible;
    isolation: isolate;
}

.section1 {
    position: relative;
    z-index: 50;
}

.nav-planete-gateau .logo {
    flex-shrink: 0;
}

.nav-planete-gateau .logo img {
    height: 70px;
    width: auto;
    max-width: 160px;
    object-fit: contain;

    transform: scale(2);
    margin-left: 20px;
}

/* Logo vendeur (branding personnalisé) — dimensions propres, bords arrondis */
.nav-planete-gateau .logo img.nav-logo--branding {
    height: 58px;
    width: auto;
    max-width: 160px;
    object-fit: contain;
    transform: none;
    margin-left: 0;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.18);
    background: rgba(255, 255, 255, 0.12);
    padding: 2px;
}

.nav-top-row {
    display: contents;
}

.nav-top-row .logo {
    order: 1;
}

.nav-search-wrapper {
    order: 2;
}

.nav-top-row .nav-panier-link {
    order: 3;
}

.nav-top-row .nav-region-wrap--top {
    order: 3;
    display: none;
}

.nav-top-row .nav-country-wrap--top {
    order: 3;
    display: none;
}

.nav-top-row .nav-compte-btn {
    order: 4;
}

@media (min-width: 993px) {
    .nav-top-row .nav-region-wrap--top {
        display: flex;
    }

    .nav-top-row .nav-country-wrap--top {
        display: flex;
    }
}

/* Barre de recherche avec filtres */
.nav-search-wrapper {
    display: flex;
    flex: 1;
    max-width: 500px;
    margin: 0 20px;
    position: relative;
    z-index: 1;
    overflow: visible;
}

.nav-search-form {
    display: flex;
    align-items: stretch;
    flex: 1;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: var(--ombre-douce);
}

.nav-language-switcher {
    margin-left: 8px;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
    overflow: visible;
}

.nav-language-switcher.is-open {
    z-index: 11005;
}

.nav-lang-trigger {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 7px;
    min-width: 88px;
    height: 38px;
    padding: 0 10px 0 8px;
    border: 1px solid rgba(53, 100, 166, 0.14);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, var(--blanc-neige, #f5f5f5) 100%);
    color: var(--couleur-dominante);
    cursor: pointer;
    font-family: var(--font-corps);
    box-shadow:
        0 2px 10px rgba(53, 100, 166, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.15s ease;
    -webkit-tap-highlight-color: transparent;
}

.nav-lang-trigger:hover {
    border-color: rgba(53, 100, 166, 0.35);
    box-shadow:
        0 4px 16px rgba(53, 100, 166, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.nav-lang-trigger:focus-visible {
    outline: none;
    box-shadow:
        0 0 0 3px var(--focus-ring, rgba(53, 100, 166, 0.15)),
        0 2px 10px rgba(53, 100, 166, 0.08);
}

.nav-language-switcher.is-open .nav-lang-trigger {
    border-color: var(--couleur-dominante);
    box-shadow:
        0 4px 18px rgba(53, 100, 166, 0.14),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.nav-language-switcher .gtranslate_wrapper {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.nav-lang-flag {
    display: block;
    width: 22px;
    height: 16px;
    border-radius: 3px;
    background-image: url('https://flagcdn.com/w40/fr.png');
    background-size: cover;
    background-position: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.22);
    flex-shrink: 0;
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.nav-lang-code {
    font: 700 12px/1 var(--font-corps);
    color: var(--texte-fonce);
    letter-spacing: 0.04em;
    min-width: 22px;
    text-transform: uppercase;
}

.nav-lang-chevron {
    color: var(--gris-moyen);
    font-size: 10px;
    margin-left: auto;
    pointer-events: none;
    transition: transform 0.22s ease, color 0.2s ease;
}

.nav-language-switcher.is-open .nav-lang-chevron {
    transform: rotate(180deg);
    color: var(--couleur-dominante);
}

.nav-lang-select--hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
    pointer-events: none;
    opacity: 0;
}

.nav-lang-panel {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 196px;
    padding: 6px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(53, 100, 166, 0.12);
    box-shadow:
        0 12px 40px rgba(15, 23, 42, 0.14),
        0 2px 8px rgba(53, 100, 166, 0.08);
    backdrop-filter: blur(16px) saturate(1.2);
    -webkit-backdrop-filter: blur(16px) saturate(1.2);
    display: flex;
    flex-direction: column;
    gap: 2px;
    animation: navLangPanelIn 0.2s ease;
    z-index: 11010;
}

.nav-lang-panel[hidden] {
    display: none !important;
}

@keyframes navLangPanelIn {
    from {
        opacity: 0;
        transform: translateY(-6px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.nav-lang-option {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border: none;
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
    font-family: var(--font-corps);
    text-align: left;
    color: var(--texte-fonce);
    transition: background 0.18s ease;
    -webkit-tap-highlight-color: transparent;
}

.nav-lang-option:hover {
    background: rgba(53, 100, 166, 0.08);
}

.nav-lang-option:focus-visible {
    outline: none;
    background: rgba(53, 100, 166, 0.1);
    box-shadow: inset 0 0 0 2px var(--couleur-dominante);
}

.nav-lang-option.is-active {
    background: rgba(53, 100, 166, 0.1);
}

.nav-lang-option-flag {
    flex-shrink: 0;
    width: 24px;
    height: 18px;
    border-radius: 3px;
    background-size: cover;
    background-position: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.18);
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.nav-lang-option-label {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.25;
}

.nav-lang-option-check {
    flex-shrink: 0;
    font-size: 12px;
    color: var(--couleur-dominante);
    opacity: 0;
    transform: scale(0.85);
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.nav-lang-option.is-active .nav-lang-option-check {
    opacity: 1;
    transform: scale(1);
}

.nav-search-btn {
    padding: 12px 20px;
    background: var(--couleur-dominante);
    border: none;
    color: var(--texte-clair);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s;
}

.nav-search-btn:hover {
    background: var(--couleur-dominante-hover);
}

.nav-search-btn i {
    font-size: 18px;
}

.nav-search-input {
    flex: 1;
    padding: 12px 20px;
    border: 2px solid var(--border-input);
    border-left: none;
    background: var(--blanc);
    font-size: 15px;
    outline: none;
    border-radius: 0 25px 25px 0;
}

.nav-search-input::placeholder {
    color: var(--gris-clair);
}

.nav-search-input:focus {
    border-color: var(--couleur-dominante);
}

.nav-search-suggestions {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 6px);
    z-index: 11010;
    background: #fff;
    border: 1px solid rgba(53, 100, 166, 0.16);
    border-radius: 14px;
    box-shadow: 0 12px 32px rgba(53, 100, 166, 0.18);
    max-height: min(360px, 52vh);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.nav-search-suggestions[hidden] {
    display: none !important;
}

.nav-search-suggestion {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border: none;
    border-bottom: 1px solid rgba(53, 100, 166, 0.08);
    background: #fff;
    text-align: left;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}

.nav-search-suggestion:last-child {
    border-bottom: none;
}

.nav-search-suggestion:hover,
.nav-search-suggestion.is-active {
    background: rgba(53, 100, 166, 0.08);
}

.nav-search-suggestion__img {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
    background: var(--blanc-neige, #f5f5f5);
}

.nav-search-suggestion__body {
    min-width: 0;
    flex: 1;
}

.nav-search-suggestion__name {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--titres, #0d0d0d);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-search-suggestion__price {
    display: block;
    font-size: 0.72rem;
    color: var(--couleur-dominante, #3564a6);
    font-weight: 700;
    margin-top: 2px;
}

.nav-search-suggestion__all {
    display: block;
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: rgba(53, 100, 166, 0.06);
    color: var(--couleur-dominante, #3564a6);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    border-radius: 0 0 14px 14px;
}

.nav-search-suggestion__all:hover {
    background: rgba(53, 100, 166, 0.12);
}

/* Bouton Mon compte / Se connecter */
.nav-compte-btn {
    display: inline-flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    padding: 7px 14px 7px 7px;
    background: linear-gradient(135deg, var(--couleur-dominante) 0%, var(--bleu-fonce, #2d5690) 100%);
    color: var(--texte-clair);
    text-decoration: none;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 4px 16px rgba(53, 100, 166, 0.22);
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    position: relative;
    min-width: 0;
    max-width: 190px;
}

.nav-compte-btn:hover {
    color: var(--texte-clair);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(53, 100, 166, 0.28);
}

.nav-compte-avatar {
    width: 36px;
    height: 36px;
    min-width: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.18);
    border: 1.5px solid rgba(255, 255, 255, 0.35);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    line-height: 1;
    flex-shrink: 0;
}

.nav-compte-avatar i {
    font-size: 15px;
    line-height: 1;
}

.nav-compte-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    flex: 1;
}

.nav-compte-title {
    font-size: 13px;
    font-weight: 700;
    display: block;
    line-height: 1.15;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.nav-compte-subtitle {
    font-size: 11px;
    opacity: 0.92;
    font-weight: 500;
    line-height: 1.15;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.nav-compte-btn--logged .nav-compte-avatar {
    background: #fff;
    color: var(--couleur-dominante);
    border-color: rgba(255, 255, 255, 0.85);
}

.nav-compte-btn--boutique {
    background: linear-gradient(135deg, var(--couleur-dominante) 0%, var(--orange, #ff6b35) 120%);
}

/* Panier */
.nav-panier-link {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    color: var(--texte-fonce);
    text-decoration: none;
    transition: color 0.3s;
}

.nav-panier-link:hover {
    color: var(--couleur-dominante);
}

.nav-panier-link i {
    font-size: 26px;
}

.nav-panier-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background: var(--orange);
    color: var(--texte-clair);
    border-radius: 50%;
    min-width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    border: 2px solid var(--blanc);
    padding: 0 4px;
    box-shadow: var(--ombre-promo);
}

.nav-annonces-link {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    color: var(--texte-fonce);
    background: transparent;
    text-decoration: none;
    flex-shrink: 0;
    transition: color 0.3s;
    -webkit-tap-highlight-color: transparent;
}

.nav-annonces-link:hover {
    color: var(--couleur-dominante);
    background: transparent;
}

.nav-annonces-link i {
    font-size: 26px;
}

.nav-annonces-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background: var(--orange, #FF6B35);
    color: #fff;
    border-radius: 50%;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 800;
    border: 2px solid var(--blanc);
    padding: 0 4px;
    line-height: 1;
    box-shadow: 0 2px 8px rgba(255, 107, 53, 0.35);
}

.nav-annonces-link--tablet-slot {
    display: none;
}

@media (min-width: 993px) {
    .nav-annonces-link--desktop {
        order: 3;
    }
}

@media (max-width: 992px) {
    .nav-planete-gateau {
        flex-wrap: wrap;
        padding: 10px 20px;
        gap: 12px;
    }

    .nav-top-row {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        width: 100%;
        order: 1;
        flex-shrink: 0;
    }

    .nav-top-row .logo {
        flex-shrink: 0;
        margin-right: auto;
        order: 1;
    }

    .nav-planete-gateau .logo img {
        height: 50px;
        max-width: 120px;
        transform: none;
        object-position: left center;
        transform: scale(2);
        margin-left: 10px;
    }

    .nav-planete-gateau .logo img.nav-logo--branding {
        height: 44px;
        max-width: 110px;
        transform: none;
        margin-left: 0;
    }

    .nav-top-row .nav-region-wrap--top {
        flex-shrink: 0;
        order: 2;
    }

    .nav-top-row .nav-country-wrap--top {
        flex-shrink: 0;
        order: 3;
    }

    .nav-annonces-link--tablet-slot {
        display: flex;
        flex: 0 0 auto;
        width: 48px;
        height: 48px;
        margin: 0;
        order: 4;
    }

    .nav-annonces-link--tablet-slot i {
        font-size: 26px;
    }

    .nav-top-row .nav-panier-link {
        order: 5;
    }

    .nav-top-row .nav-compte-btn {
        order: 6;
    }

    .nav-annonces-link--desktop {
        display: none !important;
    }

    .nav-search-wrapper {
        order: 2;
        width: 100%;
        max-width: 100%;
        margin: 0;
        flex-direction: row;
    }

    .nav-search-wrapper {
        min-width: 0;
    }

    .nav-lang-trigger {
        min-width: 84px;
        height: 35px;
        padding: 0 8px 0 7px;
    }

    .nav-search-input {
        font-size: 14px;
        padding: 10px 16px;
    }

    .nav-compte-btn {
        max-width: 132px;
        padding: 5px 9px 5px 5px;
        gap: 7px;
    }

    .nav-compte-avatar {
        width: 28px;
        height: 28px;
        min-width: 28px;
        font-size: 12px;
    }

    .nav-compte-avatar i {
        font-size: 12px;
    }

    .nav-compte-title {
        font-size: 10px;
    }

    .nav-compte-subtitle {
        font-size: 9px;
        font-weight: 600;
    }

    .nav-top-row .nav-panier-link {
        display: none !important;
    }

    .nav-top-row .nav-region-wrap--top {
        display: flex;
    }

    .nav-top-row .nav-country-wrap--top {
        display: flex;
    }

    .nav-top-row .nav-compte-btn {
        display: none !important;
    }

    .section1-right .nav-region-wrap:not(.nav-region-wrap--top) {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .nav-planete-gateau {
        flex-wrap: wrap;
        padding: 10px 12px;
        gap: 10px;
    }

    .nav-top-row {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        width: 100%;
        order: 1;
        flex-shrink: 0;
    }

    .nav-top-row .logo {
        flex-shrink: 0;
        margin-right: auto;
        order: 1;
    }

    .nav-planete-gateau .logo img {
        height: 50px;
        max-width: 120px;
        transform: none;
        object-position: left center;
        transform: scale(2);
        margin-left: 10px;
    }

    .nav-planete-gateau .logo img.nav-logo--branding {
        height: 38px;
        max-width: 100px;
        transform: none;
        margin-left: 0;
    }

    .nav-top-row .nav-region-wrap--top {
        order: 2;
    }

    .nav-top-row .nav-country-wrap--top {
        order: 3;
    }

    .nav-annonces-link--tablet-slot {
        order: 4;
        width: 42px;
        height: 42px;
    }

    .nav-annonces-link--tablet-slot i {
        font-size: 22px;
    }

    .nav-panier-link {
        width: 42px;
        height: 42px;
        flex-shrink: 0;
    }

    .nav-panier-link i {
        font-size: 22px;
    }

    .nav-panier-badge {
        min-width: 18px;
        height: 18px;
        font-size: 10px;
    }

    .nav-compte-btn {
        max-width: 108px;
        padding: 4px 7px 4px 4px;
        gap: 5px;
    }

    .nav-compte-avatar {
        width: 26px;
        height: 26px;
        min-width: 26px;
        font-size: 11px;
    }

    .nav-compte-avatar i {
        font-size: 11px;
    }

    .nav-compte-title {
        font-size: 9px;
    }

    .nav-compte-subtitle {
        font-size: 8px;
        font-weight: 600;
    }

    /* Invité : une seule ligne « Se connecter » */
    .nav-compte-btn:not(.nav-compte-btn--logged) .nav-compte-title {
        display: none;
    }

    .nav-compte-btn:not(.nav-compte-btn--logged) .nav-compte-text {
        flex-direction: row;
        align-items: center;
    }

    .nav-compte-btn:not(.nav-compte-btn--logged) .nav-compte-subtitle {
        font-size: 9px;
    }

    .nav-search-wrapper {
        order: 2;
        width: 100%;
        max-width: 100%;
        margin: 0;
        flex-direction: row;
    }

    .nav-search-form {
        flex: 1;
    }

    .nav-search-btn {
        padding: 10px 14px;
    }

    .nav-search-input {
        padding: 10px 14px;
        font-size: 14px;
    }

    .nav-language-switcher {
        min-width: 0;
    }

    .nav-lang-trigger {
        min-width: 80px;
        height: 34px;
        padding: 0 7px 0 6px;
        gap: 5px;
    }
}

@media (max-width: 480px) {
    .nav-planete-gateau {
        padding: 8px 10px;
        gap: 8px;
    }

    .nav-planete-gateau .logo img {
        height: 50px;
        max-width: 120px;
        transform: none;
        object-position: left center;
        transform: scale(2);
        margin-left: 10px;
    }

    .nav-planete-gateau .logo img.nav-logo--branding {
        height: 34px;
        max-width: 90px;
    }

    .nav-compte-btn {
        max-width: 96px;
        padding: 4px 6px 4px 4px;
        gap: 4px;
    }

    .nav-compte-avatar {
        width: 24px;
        height: 24px;
        min-width: 24px;
        font-size: 10px;
    }

    .nav-compte-avatar i {
        font-size: 10px;
    }

    .nav-compte-title {
        font-size: 8px;
    }

    .nav-compte-subtitle {
        font-size: 8px;
    }

    .nav-compte-btn:not(.nav-compte-btn--logged) .nav-compte-subtitle {
        font-size: 8px;
    }

    .nav-panier-link {
        width: 38px;
        height: 38px;
    }

    .nav-panier-link i {
        font-size: 20px;
    }

    .nav-annonces-link--tablet-slot {
        width: 38px;
        height: 38px;
    }

    .nav-annonces-link--tablet-slot i {
        font-size: 20px;
    }

    .nav-search-btn {
        padding: 8px 12px;
    }

    .nav-search-input {
        padding: 8px 12px;
        font-size: 13px;
    }

    .nav-language-switcher {
        min-width: 0;
    }

    .nav-lang-trigger {
        min-width: 76px;
        height: 34px;
        padding: 0 7px 0 6px;
        gap: 5px;
    }

    .nav-lang-flag {
        width: 20px;
        height: 15px;
    }

    .nav-lang-code {
        font-size: 11px;
        min-width: 20px;
    }

    .nav-lang-chevron {
        font-size: 9px;
    }

    .nav-lang-panel {
        min-width: 180px;
        right: 0;
        left: auto;
    }

    .nav-lang-option {
        padding: 9px 10px;
    }

    .nav-lang-option-label {
        font-size: 13px;
    }
}
</style>

<div class="info">

</div>
<nav class="nav-planete-gateau">
    <div class="nav-top-row">
        <a class="logo" href="<?php echo htmlspecialchars($u_home); ?>">
            <?php
            $__nav_logo_src = '/image/logo_market.png';
            $__nav_logo_alt = SITE_BRAND_NAME;
            $__nav_logo_is_branding = false;
            if (!empty($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']['boutique_logo'])) {
                $__nav_logo_src = '/upload/' . str_replace('\\', '/', $GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']['boutique_logo']);
                $__nav_logo_is_branding = true;
                $__bn = trim((string) ($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']['boutique_nom'] ?? ''));
                $__nav_logo_alt = $__bn !== '' ? $__bn : 'Boutique';
            }
            ?>
            <img src="<?php echo htmlspecialchars($__nav_logo_src, ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars($__nav_logo_alt, ENT_QUOTES, 'UTF-8'); ?>"
                <?php if ($__nav_logo_is_branding): ?>class="nav-logo--branding" <?php endif; ?>>
        </a>
        <?php if ($nav_show_region_filter): ?>
        <div class="nav-region-wrap nav-region-wrap--top" id="navRegionWrapTop">
            <button type="button" class="nav-btn-region nav-btn-region--top" id="navRegionToggleTop"
                aria-expanded="false" aria-haspopup="listbox" aria-controls="navRegionMenuTop"
                title="Choisir une région">
                <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                <span
                    class="nav-btn-region__label"><?php echo htmlspecialchars($nav_region_selected_label, ENT_QUOTES, 'UTF-8'); ?></span>
                <i class="fa-solid fa-chevron-down nav-btn-region__chev" aria-hidden="true"></i>
            </button>
            <div class="nav-region-menu nav-region-menu--top" id="navRegionMenuTop" role="listbox" hidden>
                <form method="post" action="/set-region.php" class="nav-region-form">
                    <input type="hidden" name="redirect"
                        value="<?php echo htmlspecialchars($nav_region_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" name="region" value="all"
                        class="nav-region-item<?php echo $nav_region_selected === '' ? ' is-active' : ''; ?>">Toutes les
                        régions</button>
                    <?php foreach ($nav_regions_for_country as $code => $label): ?>
                    <button type="submit" name="region"
                        value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                        class="nav-region-item<?php echo $nav_region_selected === $code ? ' is-active' : ''; ?>">
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <?php endforeach; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($nav_annonces_show): ?>
        <a href="<?php echo htmlspecialchars($nav_annonces_href, ENT_QUOTES, 'UTF-8'); ?>"
            class="nav-annonces-link nav-annonces-link--tablet-slot" title="<?php echo (int) $nav_annonces_count > 0
                    ? (int) $nav_annonces_count . ' annonce' . ($nav_annonces_count > 1 ? 's' : '') . ' non lue' . ($nav_annonces_count > 1 ? 's' : '')
                    : 'Annonces'; ?>" aria-label="<?php echo (int) $nav_annonces_count > 0
                      ? (int) $nav_annonces_count . ' annonce' . ($nav_annonces_count > 1 ? 's' : '') . ' non lue' . ($nav_annonces_count > 1 ? 's' : '')
                      : 'Annonces'; ?>">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <?php if ($nav_annonces_count > 0): ?>
            <span
                class="nav-annonces-badge"><?php echo $nav_annonces_count > 99 ? '99+' : (int) $nav_annonces_count; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($nav_panier_href, ENT_QUOTES, 'UTF-8'); ?>" class="nav-panier-link"
            title="<?php echo $panier_count > 0 ? 'Voir mon panier (' . $panier_count . ' article' . ($panier_count > 1 ? 's' : '') . ')' : 'Voir mon panier'; ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            <?php if ($panier_count > 0): ?>
            <span class="nav-panier-badge"><?php echo $panier_count > 99 ? '99+' : $panier_count; ?></span>
            <?php endif; ?>
        </a>
        <?php if ($nav_annonces_show): ?>
        <a href="<?php echo htmlspecialchars($nav_annonces_href, ENT_QUOTES, 'UTF-8'); ?>"
            class="nav-annonces-link nav-annonces-link--desktop" title="<?php echo (int) $nav_annonces_count > 0
                    ? (int) $nav_annonces_count . ' annonce' . ($nav_annonces_count > 1 ? 's' : '') . ' non lue' . ($nav_annonces_count > 1 ? 's' : '')
                    : 'Annonces'; ?>" aria-label="<?php echo (int) $nav_annonces_count > 0
                      ? (int) $nav_annonces_count . ' annonce' . ($nav_annonces_count > 1 ? 's' : '') . ' non lue' . ($nav_annonces_count > 1 ? 's' : '')
                      : 'Annonces'; ?>">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <?php if ($nav_annonces_count > 0): ?>
            <span
                class="nav-annonces-badge"><?php echo $nav_annonces_count > 99 ? '99+' : (int) $nav_annonces_count; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($nav_compte_href, ENT_QUOTES, 'UTF-8'); ?>"
            class="<?php echo htmlspecialchars($nav_compte_btn_class, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="nav-compte-avatar" aria-hidden="true">
                <?php if ($nav_compte_logged && $nav_compte_avatar_letter !== ''): ?>
                <?php echo htmlspecialchars($nav_compte_avatar_letter, ENT_QUOTES, 'UTF-8'); ?>
                <?php else: ?>
                <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </span>
            <span class="nav-compte-text">
                <span class="nav-compte-title"><?php echo $nav_compte_title; ?></span>
                <span class="nav-compte-subtitle"><?php echo $nav_compte_subtitle; ?></span>
            </span>
        </a>
    </div>

    <div class="nav-search-wrapper">
        <form class="nav-search-form" action="<?php echo htmlspecialchars($u_produits); ?>" method="get"
            id="nav-search-form">
            <?php if (defined('BOUTIQUE_SLUG') && BOUTIQUE_SLUG !== ''): ?>
            <input type="hidden" name="boutique"
                value="<?php echo htmlspecialchars(BOUTIQUE_SLUG, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <button type="submit" class="nav-search-btn" aria-label="Rechercher">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <input type="text" name="recherche" id="nav-search" class="nav-search-input"
                placeholder="Que recherchez-vous ?"
                value="<?php echo !empty($_GET['recherche']) ? htmlspecialchars($_GET['recherche']) : ''; ?>">
            <input type="hidden" name="prix_min" id="nav-prix-min"
                value="<?php echo isset($_GET['prix_min']) ? htmlspecialchars($_GET['prix_min']) : ''; ?>">
            <input type="hidden" name="prix_max" id="nav-prix-max"
                value="<?php echo isset($_GET['prix_max']) ? htmlspecialchars($_GET['prix_max']) : ''; ?>">
            <input type="hidden" name="categorie" id="nav-categorie"
                value="<?php echo isset($_GET['categorie']) ? htmlspecialchars($_GET['categorie']) : ''; ?>">
            <input type="hidden" name="tri" id="nav-tri"
                value="<?php echo isset($_GET['tri']) ? htmlspecialchars($_GET['tri']) : ''; ?>">
        </form>
        <div class="nav-search-suggestions" id="nav-search-suggestions" hidden role="listbox" aria-label="Suggestions de recherche"></div>
        <div class="nav-language-switcher" id="navLangSwitcher">
            <button type="button" class="nav-lang-trigger" id="navLangTrigger" aria-expanded="false"
                aria-haspopup="listbox" aria-controls="navLangPanel" title="Changer la langue">
                <span class="nav-lang-flag" id="navLangFlag" aria-hidden="true"></span>
                <span class="nav-lang-code" id="navLangCode">FR</span>
                <i class="fa-solid fa-chevron-down nav-lang-chevron" aria-hidden="true"></i>
            </button>
            <div class="nav-lang-panel" id="navLangPanel" role="listbox" aria-label="Changer la langue" hidden>
                <button type="button" class="nav-lang-option" role="option" data-lang="fr"
                    data-flag-src="https://flagcdn.com/w40/fr.png" data-code="FR">
                    <span class="nav-lang-option-flag"
                        style="background-image:url('https://flagcdn.com/w40/fr.png')"></span>
                    <span class="nav-lang-option-label">Français</span>
                    <i class="fa-solid fa-check nav-lang-option-check" aria-hidden="true"></i>
                </button>
                <button type="button" class="nav-lang-option" role="option" data-lang="en"
                    data-flag-src="https://flagcdn.com/w40/gb.png" data-code="EN">
                    <span class="nav-lang-option-flag"
                        style="background-image:url('https://flagcdn.com/w40/gb.png')"></span>
                    <span class="nav-lang-option-label">English</span>
                    <i class="fa-solid fa-check nav-lang-option-check" aria-hidden="true"></i>
                </button>
                <button type="button" class="nav-lang-option" role="option" data-lang="es"
                    data-flag-src="https://flagcdn.com/w40/es.png" data-code="ES">
                    <span class="nav-lang-option-flag"
                        style="background-image:url('https://flagcdn.com/w40/es.png')"></span>
                    <span class="nav-lang-option-label">Español</span>
                    <i class="fa-solid fa-check nav-lang-option-check" aria-hidden="true"></i>
                </button>
                <button type="button" class="nav-lang-option" role="option" data-lang="pt"
                    data-flag-src="https://flagcdn.com/w40/pt.png" data-code="PT">
                    <span class="nav-lang-option-flag"
                        style="background-image:url('https://flagcdn.com/w40/pt.png')"></span>
                    <span class="nav-lang-option-label">Português</span>
                    <i class="fa-solid fa-check nav-lang-option-check" aria-hidden="true"></i>
                </button>
                <button type="button" class="nav-lang-option" role="option" data-lang="ar"
                    data-flag-src="https://flagcdn.com/w40/sa.png" data-code="AR">
                    <span class="nav-lang-option-flag"
                        style="background-image:url('https://flagcdn.com/w40/sa.png')"></span>
                    <span class="nav-lang-option-label">العربية</span>
                    <i class="fa-solid fa-check nav-lang-option-check" aria-hidden="true"></i>
                </button>
            </div>
            <select class="nav-lang-select nav-lang-select--hidden" id="navLangSelect" tabindex="-1" aria-hidden="true">
                <option value="fr" data-flag-src="https://flagcdn.com/w40/fr.png">FR</option>
                <option value="en" data-flag-src="https://flagcdn.com/w40/gb.png">EN</option>
                <option value="es" data-flag-src="https://flagcdn.com/w40/es.png">ES</option>
                <option value="pt" data-flag-src="https://flagcdn.com/w40/pt.png">PT</option>
                <option value="ar" data-flag-src="https://flagcdn.com/w40/sa.png">AR</option>
            </select>
            <div class="gtranslate_wrapper"></div>
        </div>
    </div>
    <script>
    window.gtranslateSettings = {
        default_language: 'fr',
        native_language_names: true,
        detect_browser_language: false,
        languages: ['fr', 'en', 'es', 'pt', 'ar'],
        wrapper_selector: '.gtranslate_wrapper'
    };

    (function() {
        var win = window;
        var switcher = document.getElementById('navLangSwitcher');
        var trigger = document.getElementById('navLangTrigger');
        var panel = document.getElementById('navLangPanel');
        var select = document.getElementById('navLangSelect');
        var flag = document.getElementById('navLangFlag');
        var code = document.getElementById('navLangCode');
        var options = panel ? panel.querySelectorAll('.nav-lang-option') : [];
        if (!switcher || !trigger || !panel || !select || !flag || !code) {
            return;
        }

        function getCookie(name) {
            var parts = ('; ' + document.cookie).split('; ' + name + '=');
            if (parts.length >= 2) {
                return parts.pop().split(';').shift();
            }
            return '';
        }

        function setCookie(name, value) {
            var expires = '; expires=' + new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toUTCString();
            document.cookie = name + '=' + value + expires + '; path=/';
            if (location.hostname.indexOf('.') !== -1) {
                document.cookie = name + '=' + value + expires + '; path=/; domain=.' + location.hostname.replace(
                    /^www\./, '');
            }
        }

        function clearCookie(name) {
            var past = '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.cookie = name + '=' + past + '; path=/';
            if (location.hostname.indexOf('.') !== -1) {
                document.cookie = name + '=' + past + '; path=/; domain=.' + location.hostname.replace(/^www\./,
                    '');
            }
        }

        function currentLangFromCookie() {
            /* 1. localStorage — source la plus fiable (pas de problème de domaine) */
            try {
                var stored = localStorage.getItem('nav_selected_lang');
                if (stored && /^[a-z]{2}$/.test(stored)) {
                    return stored;
                }
            } catch (e) {}

            /* 2. Fallback : lecture du cookie googtrans */
            var raw = getCookie('googtrans');
            if (!raw) {
                return 'fr';
            }
            try {
                raw = decodeURIComponent(raw.replace(/\+/g, ' ')).trim();
            } catch (e1) {
                raw = raw.trim();
            }
            /* Variante sans slash : "fr|es" */
            if (raw.indexOf('/') === -1 && raw.indexOf('|') !== -1) {
                var pv = raw.split('|').filter(function(s) {
                    return s.length > 0;
                });
                if (pv.length) {
                    var lp = pv[pv.length - 1].toLowerCase().trim();
                    if (/^[a-z]{2}$/.test(lp)) {
                        return lp;
                    }
                }
            }
            /* googtrans : souvent "/fr/es", "/auto/en", ou encodé %2Ffr%2Fes */
            var segments = raw.split('/').filter(function(s) {
                return s.length > 0;
            });
            if (!segments.length) {
                return 'fr';
            }
            var target = segments[segments.length - 1].toLowerCase();
            target = target.split('|')[0].trim();
            if (target.indexOf('-') !== -1) {
                target = target.split('-')[0];
            }
            if (/^[a-z]{2}$/.test(target)) {
                return target;
            }
            return 'fr';
        }

        function syncFlag() {
            var lang = select.value || 'fr';
            var opt = select.querySelector('option[value="' + lang + '"]');
            var url =
                opt && opt.getAttribute('data-flag-src') ?
                opt.getAttribute('data-flag-src') :
                'https://flagcdn.com/w40/fr.png';
            flag.style.backgroundImage = 'url("' + url + '")';
            code.textContent = opt ? opt.textContent.trim().toUpperCase() : 'FR';
            options.forEach(function(btn) {
                var active = btn.getAttribute('data-lang') === lang;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function openPanel() {
            panel.removeAttribute('hidden');
            switcher.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        }

        function closePanel() {
            panel.setAttribute('hidden', '');
            switcher.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function togglePanel() {
            if (panel.hasAttribute('hidden')) {
                openPanel();
            } else {
                closePanel();
            }
        }

        function applyLanguage(lang) {
            /* Mémoriser dans localStorage pour survie multi-domaine / multi-cookie */
            try {
                if (lang === 'fr') {
                    localStorage.removeItem('nav_selected_lang');
                } else {
                    localStorage.setItem('nav_selected_lang', lang);
                }
            } catch (e) {}
            if (lang === 'fr') {
                clearCookie('googtrans');
            } else {
                setCookie('googtrans', '/fr/' + lang);
            }

            function reloadPage() {
                window.location.reload();
            }
            if (lang !== 'fr' && win.ColobanesPerf && typeof win.ColobanesPerf.loadGtranslate === 'function') {
                win.ColobanesPerf.loadGtranslate(reloadPage);
                win.setTimeout(reloadPage, 1200);
                return;
            }
            reloadPage();
        }

        var initial = currentLangFromCookie();
        if (select.querySelector('option[value="' + initial + '"]')) {
            select.value = initial;
        } else {
            select.value = 'fr';
        }
        syncFlag();

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            togglePanel();
        });

        options.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var lang = btn.getAttribute('data-lang');
                if (!lang || lang === select.value) {
                    closePanel();
                    return;
                }
                select.value = lang;
                applyLanguage(lang);
            });
        });

        document.addEventListener('click', function(e) {
            if (!switcher.contains(e.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePanel();
            }
        });

        window.addEventListener('pageshow', function() {
            var lang = currentLangFromCookie();
            if (select.querySelector('option[value="' + lang + '"]')) {
                select.value = lang;
            }
            syncFlag();
        });

        window.addEventListener('load', function() {
            syncFlag();
        });
    })();
    </script>
</nav>

<!-- Overlay et sidebar menu latéral (apparaît au clic sur MENU) -->
<div class="nav-sidebar-overlay" id="navSidebarOverlay"></div>
<aside class="nav-sidebar" id="navSidebar">
    <div class="nav-sidebar-header">
        <a href="<?php echo htmlspecialchars($u_home); ?>" class="nav-sidebar-logo">
            <img src="/image/logo_market.png"
                alt="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>">
        </a>
        <p class="nav-sidebar-tagline">GLOBALE Marketplace tous vos produits</p>
    </div>
    <div class="nav-sidebar-content">
        <ul class="nav-glass-rayons-list nav-glass-rayons-list--shortcuts" role="list">
            <li>
                <a href="<?php echo htmlspecialchars($u_nouveautes); ?>"
                    class="nav-glass-rayon-card nav-glass-rayon-card--nouveautes">
                    <span class="nav-glass-rayon-icon" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                    <span class="nav-glass-rayon-label">NOUVEAUTÉS</span>
                    <span class="nav-glass-rayon-arrow" aria-hidden="true"><i
                            class="fa-solid fa-arrow-right"></i></span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($u_promo); ?>"
                    class="nav-glass-rayon-card nav-glass-rayon-card--promo">
                    <span class="nav-glass-rayon-icon" aria-hidden="true"><i class="fa-solid fa-percent"></i></span>
                    <span class="nav-glass-rayon-label">PROMO</span>
                    <span class="nav-glass-rayon-arrow" aria-hidden="true"><i
                            class="fa-solid fa-arrow-right"></i></span>
                </a>
            </li>
        </ul>
        <div class="nav-sidebar-categories nav-sidebar-categories--glass">
            <?php if (!empty($nav_megamenu)): ?>
            <p class="nav-sidebar-section-label">Nos rayons</p>
            <ul class="nav-glass-rayons-list" role="list">
                <?php foreach ($nav_megamenu as $mega): ?>
                <?php
                        $g = $mega['general'];
                        $gid = (int) $g['id'];
                        $ic_class = function_exists('categorie_fa_icon_class') ? categorie_fa_icon_class($g) : 'fa-solid fa-layer-group';
                        $cg_img = function_exists('categorie_image_public_path') ? categorie_image_public_path($g) : null;
                        $has_cg_img = is_string($cg_img) && $cg_img !== '';
                        $rayon_href = function_exists('nav_categorie_generale_href')
                            ? nav_categorie_generale_href($gid)
                            : nav_categorie_href($gid);
                        ?>
                <li>
                    <a class="nav-glass-rayon-card" href="<?php echo htmlspecialchars($rayon_href); ?>">
                        <span
                            class="nav-glass-rayon-icon<?php echo $has_cg_img ? ' nav-glass-rayon-icon--photo' : ''; ?>"
                            aria-hidden="true">
                            <?php if ($has_cg_img): ?>
                            <img src="<?php echo htmlspecialchars($cg_img, ENT_QUOTES, 'UTF-8'); ?>" alt=""
                                loading="lazy" decoding="async">
                            <?php else: ?>
                            <i class="<?php echo htmlspecialchars($ic_class); ?>"></i>
                            <?php endif; ?>
                        </span>
                        <span class="nav-glass-rayon-label"><?php echo htmlspecialchars($g['nom']); ?></span>
                        <span class="nav-glass-rayon-arrow" aria-hidden="true"><i
                                class="fa-solid fa-arrow-right"></i></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php elseif (!empty($categories_menu)): ?>
            <p class="nav-sidebar-section-label">Catégories</p>
            <?php foreach ($categories_menu as $categorie): ?>
            <a href="<?php echo htmlspecialchars(nav_categorie_href($categorie['id'])); ?>"
                class="nav-sidebar-category">
                <span><?php echo htmlspecialchars($categorie['nom']); ?></span>
                <span class="nav-sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
            </a>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="nav-sidebar-section-label">Catalogue</p>
            <a href="<?php echo htmlspecialchars($u_produits); ?>" class="nav-sidebar-category">
                <span>Tous les produits</span>
                <span class="nav-sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="nav-sidebar-footer">
        <a href="<?php echo htmlspecialchars($u_contact); ?>" class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-phone"></i>
            <span>CONTACTEZ<br>NOUS</span>
        </a>
        <a href="<?php echo htmlspecialchars($u_contact); ?>#livraison" class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-truck"></i>
            <span>PORTS ET<br>EXPÉDITION</span>
        </a>
        <a href="<?php echo isset($_SESSION['user_id']) ? '/user/mon-compte.php' : '/choix-connexion.php'; ?>"
            class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-briefcase"></i>
            <span>COMPTE<br>PRO</span>
        </a>
    </div>
</aside>

<?php include __DIR__ . '/includes/shop_mobile_dock.php'; ?>

<section class="section1">
    <div class="section1-left">
        <button type="button" class="toggle-categories-btn" id="navMenuToggle" aria-label="Ouvrir le menu">
            <i class="fa-solid fa-bars"></i>
            <span>MENU</span>
        </button>
    </div>
    <?php if (isset($section1_mp_page_title) && trim((string) $section1_mp_page_title) !== ''): ?>
    <div class="section1-center section1-center--mp-title">
        <h1 class="section1-page-title">
            <?php echo htmlspecialchars(trim((string) $section1_mp_page_title), ENT_QUOTES, 'UTF-8'); ?>
        </h1>
    </div>
    <?php endif; ?>
    <div class="section1-right">
        <a href="<?php echo htmlspecialchars($u_nouveautes); ?>" class="nav-action-btn nav-btn-nouveautes">
            <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
            <span>NOUVEAUTÉS</span>
        </a>
        <a href="<?php echo htmlspecialchars($u_promo); ?>" class="nav-action-btn nav-btn-promo">
            <i class="fa-solid fa-percent"></i>
            <span>PROMO</span>
        </a>
        <?php if ($nav_show_region_filter): ?>
        <div class="nav-region-wrap" id="navRegionWrap">
            <button type="button" class="nav-action-btn nav-btn-region" id="navRegionToggle" aria-expanded="false"
                aria-haspopup="listbox" aria-controls="navRegionMenu">
                <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                <span
                    class="nav-btn-region__label"><?php echo htmlspecialchars($nav_region_selected_label, ENT_QUOTES, 'UTF-8'); ?></span>
                <i class="fa-solid fa-chevron-down nav-btn-region__chev" aria-hidden="true"></i>
            </button>
            <div class="nav-region-menu" id="navRegionMenu" role="listbox" hidden>
                <form method="post" action="/set-region.php" class="nav-region-form">
                    <input type="hidden" name="redirect"
                        value="<?php echo htmlspecialchars($nav_region_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" name="region" value="all"
                        class="nav-region-item<?php echo $nav_region_selected === '' ? ' is-active' : ''; ?>">Toutes les
                        régions</button>
                    <?php foreach ($nav_regions_for_country as $code => $label): ?>
                    <button type="submit" name="region"
                        value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                        class="nav-region-item<?php echo $nav_region_selected === $code ? ' is-active' : ''; ?>">
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <?php endforeach; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($nav_needs_country_welcome): ?>
<div class="mp-country-welcome" id="mpCountryWelcome" role="dialog" aria-modal="true"
    aria-labelledby="mpCountryWelcomeTitle">
    <div class="mp-country-welcome__backdrop" aria-hidden="true">
        <div class="mp-country-welcome__glow mp-country-welcome__glow--blue"></div>
        <div class="mp-country-welcome__glow mp-country-welcome__glow--orange"></div>
    </div>
    <div class="mp-country-welcome__panel">
        <h2 class="mp-country-welcome__title" id="mpCountryWelcomeTitle">Bienvenue sur
            <?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="mp-country-welcome__text">Choisissez votre pays pour afficher les produits disponibles près de chez
            vous.</p>
        <form method="post" action="/set-country.php" class="mp-country-welcome__form">
            <input type="hidden" name="redirect"
                value="<?php echo htmlspecialchars($nav_geo_redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mp-country-welcome__grid">
                <?php foreach (marketplace_countries_nav_list() as $code => $meta): ?>
                <?php $flag_src = marketplace_country_flag_url($code, 80); ?>
                <button type="submit" name="country" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                    class="mp-country-welcome__choice<?php echo $nav_suggested_country === $code ? ' is-suggested' : ''; ?>">
                    <span class="mp-country-welcome__flag-wrap" aria-hidden="true">
                        <img class="mp-country-welcome__flag mp-country-flag-img"
                            src="<?php echo htmlspecialchars($flag_src, ENT_QUOTES, 'UTF-8'); ?>"
                            data-fallback="<?php echo htmlspecialchars(marketplace_country_flag_url_alt($code, 80), ENT_QUOTES, 'UTF-8'); ?>"
                            alt="" width="40" height="28" loading="eager" decoding="async" referrerpolicy="no-referrer">
                    </span>
                    <span
                        class="mp-country-welcome__name"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($nav_suggested_country === $code): ?>
                    <span class="mp-country-welcome__hint">Suggéré</span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>
<style>
.mp-country-welcome {
    position: fixed;
    inset: 0;
    z-index: 20000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow: hidden;
}

.mp-country-welcome__backdrop {
    position: absolute;
    inset: 0;
    overflow: hidden;
    background:
        linear-gradient(145deg, rgba(53, 100, 166, 0.18) 0%, rgba(13, 13, 13, 0.72) 45%, rgba(255, 107, 53, 0.14) 100%);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}

.mp-country-welcome__glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(48px);
    pointer-events: none;
    opacity: 0.45;
}

.mp-country-welcome__glow--blue {
    width: 280px;
    height: 280px;
    top: -60px;
    left: -40px;
    background: rgba(53, 100, 166, 0.55);
}

.mp-country-welcome__glow--orange {
    width: 240px;
    height: 240px;
    right: -30px;
    bottom: -50px;
    background: rgba(255, 107, 53, 0.45);
}

.mp-country-welcome__panel {
    position: relative;
    z-index: 2;
    width: min(100%, 480px);
    padding: 28px 24px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid rgba(53, 100, 166, 0.12);
    box-shadow:
        0 20px 60px rgba(53, 100, 166, 0.28),
        0 0 0 1px rgba(255, 255, 255, 0.6) inset;
    text-align: center;
}

.mp-country-welcome__title {
    margin: 0 0 10px;
    font-size: 1.35rem;
    color: var(--titres, #0d0d0d);
}

.mp-country-welcome__text {
    margin: 0 0 22px;
    color: var(--gris-moyen, #737373);
    font-size: 0.95rem;
    line-height: 1.5;
}

.mp-country-welcome__grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.mp-country-welcome__choice {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid rgba(53, 100, 166, 0.2);
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
    font-family: inherit;
    text-align: left;
    transition: border-color 0.18s, background 0.18s, transform 0.15s, box-shadow 0.18s;
}

.mp-country-welcome__choice:hover,
.mp-country-welcome__choice.is-suggested {
    border-color: var(--couleur-dominante, #3564a6);
    background: rgba(53, 100, 166, 0.06);
    box-shadow: 0 4px 16px rgba(53, 100, 166, 0.12);
}

.mp-country-welcome__choice:active {
    transform: scale(0.99);
}

.mp-country-welcome__flag-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 28px;
    flex-shrink: 0;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: #f0f0f0;
}

.mp-country-welcome__flag {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.mp-country-welcome__name {
    flex: 1;
    font-size: 1rem;
    font-weight: 600;
    color: var(--texte-fonce, #0d0d0d);
}

.mp-country-welcome__hint {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--couleur-dominante, #3564a6);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
</style>
<script>
document.documentElement.classList.add('mp-country-welcome-open');
document.body.style.overflow = 'hidden';
document.querySelectorAll('.mp-country-flag-img[data-fallback]').forEach(function(img) {
    img.addEventListener('error', function onFlagError() {
        var fb = img.getAttribute('data-fallback');
        if (!fb || img.dataset.fallbackUsed === '1') {
            return;
        }
        img.dataset.fallbackUsed = '1';
        img.src = fb;
    }, {
        once: true
    });
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('navMenuToggle');
    var sidebar = document.getElementById('navSidebar');
    var overlay = document.getElementById('navSidebarOverlay');

    function openSidebarMenu() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('show');
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        var icon = toggle ? toggle.querySelector('i') : null;
        if (icon) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        }
    }

    function closeSidebarMenu() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        var icon = toggle ? toggle.querySelector('i') : null;
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }

    window.openBoutiqueNavSidebar = openSidebarMenu;
    window.closeBoutiqueNavSidebar = closeSidebarMenu;

    if (toggle) toggle.addEventListener('click', function() {
        if (sidebar && sidebar.classList.contains('open')) closeSidebarMenu();
        else openSidebarMenu();
    });
    if (overlay) overlay.addEventListener('click', closeSidebarMenu);

    var dockSidebarBtn = document.getElementById('shopDockSidebarBtn');
    if (dockSidebarBtn) {
        dockSidebarBtn.addEventListener('click', function() {
            if (sidebar && sidebar.classList.contains('open')) closeSidebarMenu();
            else openSidebarMenu();
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth <= 1024) return;
        if (sidebar && sidebar.classList.contains('open')) {
            closeSidebarMenu();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        closeSidebarMenu();
        closeAllRegionMenus();
    });

    function closeAllRegionMenus() {
        [
            ['navRegionWrap', 'navRegionToggle', 'navRegionMenu'],
            ['navRegionWrapTop', 'navRegionToggleTop', 'navRegionMenuTop']
        ].forEach(function(ids) {
            var wrap = document.getElementById(ids[0]);
            var toggle = document.getElementById(ids[1]);
            var menu = document.getElementById(ids[2]);
            if (wrap) wrap.classList.remove('open');
            if (menu) menu.hidden = true;
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
    }

    function initRegionDropdown(wrapId, toggleId, menuId) {
        var regionWrap = document.getElementById(wrapId);
        var regionToggle = document.getElementById(toggleId);
        var regionMenu = document.getElementById(menuId);
        if (!regionWrap || !regionToggle || !regionMenu) return;
        regionToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var willOpen = !regionWrap.classList.contains('open');
            closeAllRegionMenus();
            if (willOpen) {
                regionWrap.classList.add('open');
                regionMenu.hidden = false;
                regionToggle.setAttribute('aria-expanded', 'true');
            }
        });
    }

    initRegionDropdown('navRegionWrap', 'navRegionToggle', 'navRegionMenu');
    initRegionDropdown('navRegionWrapTop', 'navRegionToggleTop', 'navRegionMenuTop');

    document.addEventListener('click', function(e) {
        var inRegion = e.target.closest('#navRegionWrap, #navRegionWrapTop');
        if (!inRegion) {
            closeAllRegionMenus();
        }
    });

    document.querySelectorAll('.mp-country-flag-img[data-fallback]').forEach(function(img) {
        if (img.dataset.fallbackBound === '1') {
            return;
        }
        img.dataset.fallbackBound = '1';
        img.addEventListener('error', function onFlagError() {
            var fb = img.getAttribute('data-fallback');
            if (!fb || img.dataset.fallbackUsed === '1') {
                return;
            }
            img.dataset.fallbackUsed = '1';
            img.src = fb;
        }, {
            once: true
        });
    });

    (function initNavSearchSuggestions() {
        var input = document.getElementById('nav-search');
        var panel = document.getElementById('nav-search-suggestions');
        var form = document.getElementById('nav-search-form');
        if (!input || !panel || !form) {
            return;
        }

        var debounceTimer = null;
        var activeIndex = -1;
        var lastQuery = '';
        var boutiqueParam = '';
        var hiddenBoutique = form.querySelector('input[name="boutique"]');
        if (hiddenBoutique && hiddenBoutique.value) {
            boutiqueParam = '&boutique=' + encodeURIComponent(hiddenBoutique.value);
        }

        function hidePanel() {
            panel.hidden = true;
            panel.innerHTML = '';
            activeIndex = -1;
        }

        function formatPrice(n) {
            return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') + ' FCFA';
        }

        function escapeHtml(t) {
            var d = document.createElement('div');
            d.textContent = t == null ? '' : t;
            return d.innerHTML;
        }

        function setActive(index) {
            var items = panel.querySelectorAll('.nav-search-suggestion');
            activeIndex = index;
            items.forEach(function(el, i) {
                el.classList.toggle('is-active', i === activeIndex);
            });
        }

        function renderSuggestions(items, query) {
            if (!items || !items.length) {
                hidePanel();
                return;
            }
            var html = items.map(function(item, idx) {
                return '<a href="' + escapeHtml(item.url) + '" class="nav-search-suggestion" role="option" data-index="' + idx + '">' +
                    '<img class="nav-search-suggestion__img" src="' + escapeHtml(item.image_url || '/image/produit1.jpg') + '" alt="" loading="lazy" onerror="this.src=\'/image/produit1.jpg\'">' +
                    '<span class="nav-search-suggestion__body">' +
                    '<span class="nav-search-suggestion__name">' + escapeHtml(item.nom) + '</span>' +
                    '<span class="nav-search-suggestion__price">' + formatPrice(item.prix || 0) + '</span>' +
                    '</span></a>';
            }).join('');
            html += '<button type="button" class="nav-search-suggestion__all">Voir tous les résultats pour « ' + escapeHtml(query) + ' »</button>';
            panel.innerHTML = html;
            panel.hidden = false;
            activeIndex = -1;

            panel.querySelector('.nav-search-suggestion__all').addEventListener('click', function() {
                form.submit();
            });
        }

        function fetchSuggestions(q) {
            fetch('/api/search_suggestions.php?q=' + encodeURIComponent(q) + boutiqueParam)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (input.value.trim() !== q) {
                        return;
                    }
                    if (data && data.success) {
                        renderSuggestions(data.suggestions || [], q);
                    } else {
                        hidePanel();
                    }
                })
                .catch(function() {
                    hidePanel();
                });
        }

        input.addEventListener('input', function() {
            var q = input.value.trim();
            clearTimeout(debounceTimer);
            if (q.length < 2) {
                hidePanel();
                return;
            }
            debounceTimer = setTimeout(function() {
                if (q === lastQuery && !panel.hidden) {
                    return;
                }
                lastQuery = q;
                fetchSuggestions(q);
            }, 280);
        });

        input.addEventListener('keydown', function(e) {
            var items = panel.querySelectorAll('.nav-search-suggestion');
            if (panel.hidden || !items.length) {
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive(Math.min(activeIndex + 1, items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(Math.max(activeIndex - 1, 0));
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                items[activeIndex].click();
            } else if (e.key === 'Escape') {
                hidePanel();
            }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-search-wrapper')) {
                hidePanel();
            }
        });
    })();
});
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
<?php include __DIR__ . '/includes/geo_auto_capture.php'; ?>