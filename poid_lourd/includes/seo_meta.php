<?php
/**
 * Meta tags SEO pour le référencement
 * Usage: include avec les variables $seo_title, $seo_description, $seo_keywords (optionnel),
 *        $seo_image (optionnel), $seo_canonical (optionnel), $seo_noindex (optionnel),
 *        $seo_json_ld_blocks (optionnel, tableau JSON-LD schema.org)
 *
 * Inclure pwa_meta.php AVANT ce fichier (favicon + manifest).
 */
require_once __DIR__ . '/site_brand.php';
if (!function_exists('get_site_base_url')) {
    require_once __DIR__ . '/site_url.php';
}
$base = get_site_base_url();
$seo_title = isset($seo_title) ? $seo_title : site_brand_seo_title_default();
$seo_description = isset($seo_description) ? $seo_description : site_brand_seo_description_default();
$seo_keywords = isset($seo_keywords) ? $seo_keywords : site_brand_seo_keywords_default();
$seo_image = isset($seo_image) ? $seo_image : $base . '/image/logo_market.png';
$seo_canonical = isset($seo_canonical) ? $seo_canonical : '';
$seo_noindex = isset($seo_noindex) && $seo_noindex;
$seo_og_type = isset($seo_og_type) ? $seo_og_type : 'website';

$seo_config_path = __DIR__ . '/../config/seo.php';
if (is_file($seo_config_path)) {
    require_once $seo_config_path;
}
?>
<title><?php echo htmlspecialchars($seo_title); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($seo_description); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($seo_keywords); ?>">
<meta name="robots" content="<?php echo $seo_noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'; ?>">
<meta name="geo.region" content="SN-DK">
<meta name="geo.placename" content="Dakar, Colobane, Sénégal">
<meta name="geo.position" content="14.6937;-17.4441">
<meta name="ICBM" content="14.6937, -17.4441">
<meta name="author" content="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="application-name" content="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!empty($seo_google_site_verification)): ?>
<meta name="google-site-verification" content="<?php echo htmlspecialchars((string) $seo_google_site_verification, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<?php if ($seo_canonical): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($seo_canonical); ?>">
<?php endif; ?>
<!-- Open Graph / Facebook -->
<meta property="og:type" content="<?php echo htmlspecialchars($seo_og_type); ?>">
<meta property="og:url"
    content="<?php echo htmlspecialchars($seo_canonical ?: $base . ($_SERVER['REQUEST_URI'] ?? '/')); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($seo_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($seo_description); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($seo_image); ?>">
<meta property="og:image:alt" content="<?php echo htmlspecialchars(SITE_BRAND_NAME . ' — logo marketplace Colobane Sénégal', ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:locale" content="fr_SN">
<meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>">
<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($seo_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($seo_description); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($seo_image); ?>">
<meta name="twitter:image:alt" content="<?php echo htmlspecialchars(SITE_BRAND_NAME . ' — Colobane marketplace', ENT_QUOTES, 'UTF-8'); ?>">
<?php
if (!empty($seo_json_ld_blocks) && is_array($seo_json_ld_blocks)) {
    require_once __DIR__ . '/seo_structured_data.php';
    seo_meta_echo_json_ld($seo_json_ld_blocks);
}
if (defined('BOUTIQUE_ADMIN_ID')) {
    if (!function_exists('boutique_echo_theme_style_override')) {
        require_once __DIR__ . '/boutique_vendeur_display.php';
    }
    boutique_echo_theme_style_override();
}
?>
