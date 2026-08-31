<?php
/**
 * Meta tags SEO pour le référencement
 * Usage: include avec les variables $seo_title, $seo_description, $seo_keywords (optionnel), $seo_image (optionnel), $seo_canonical (optionnel), $seo_noindex (optionnel), $seo_schema_graphs (optionnel)
 */
include __DIR__ . '/favicon.php';
if (!function_exists('get_site_base_url')) {
    require_once __DIR__ . '/site_url.php';
}
require_once __DIR__ . '/seo_config.php';
require_once __DIR__ . '/seo_schema.php';

$base = get_site_base_url();
$home_meta = get_seo_home_meta();
$seo_title = isset($seo_title) ? $seo_title : $home_meta['title'];
$seo_description = isset($seo_description) ? $seo_description : $home_meta['description'];
$seo_keywords = isset($seo_keywords) ? $seo_keywords : get_seo_default_keywords();
$seo_image = isset($seo_image) ? $seo_image : $base . '/image/sugar_paper.jpg';
$seo_canonical = isset($seo_canonical) ? $seo_canonical : '';
$seo_noindex = isset($seo_noindex) && $seo_noindex;
$seo_og_type = isset($seo_og_type) ? $seo_og_type : 'website';
$seo_image_alt = isset($seo_image_alt) ? $seo_image_alt : '';
$seo_image_width = isset($seo_image_width) ? (int) $seo_image_width : 0;
$seo_image_height = isset($seo_image_height) ? (int) $seo_image_height : 0;
$seo_image_type = isset($seo_image_type) ? $seo_image_type : '';
$seo_product_price = isset($seo_product_price) ? $seo_product_price : '';
$seo_product_currency = isset($seo_product_currency) ? $seo_product_currency : 'XOF';
$seo_og_url = $seo_canonical !== '' ? $seo_canonical : $base . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<title><?php echo htmlspecialchars($seo_title); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($seo_description); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($seo_keywords); ?>">
<meta name="robots" content="<?php echo $seo_noindex ? 'noindex, nofollow' : 'index, follow'; ?>">
<?php if ($seo_canonical): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($seo_canonical); ?>">
<?php endif; ?>
<!-- Open Graph / Facebook -->
<meta property="og:type" content="<?php echo htmlspecialchars($seo_og_type); ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($seo_og_url); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($seo_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($seo_description); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($seo_image); ?>">
<?php if (stripos($seo_image, 'https://') === 0): ?>
<meta property="og:image:secure_url" content="<?php echo htmlspecialchars($seo_image); ?>">
<?php endif; ?>
<?php if ($seo_image_width > 0): ?>
<meta property="og:image:width" content="<?php echo (int) $seo_image_width; ?>">
<?php endif; ?>
<?php if ($seo_image_height > 0): ?>
<meta property="og:image:height" content="<?php echo (int) $seo_image_height; ?>">
<?php endif; ?>
<?php if ($seo_image_type !== ''): ?>
<meta property="og:image:type" content="<?php echo htmlspecialchars($seo_image_type); ?>">
<?php endif; ?>
<?php if ($seo_image_alt !== ''): ?>
<meta property="og:image:alt" content="<?php echo htmlspecialchars($seo_image_alt); ?>">
<?php endif; ?>
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="Sugar Paper">
<?php if ($seo_og_type === 'product' && $seo_product_price !== ''): ?>
<meta property="product:price:amount" content="<?php echo htmlspecialchars($seo_product_price); ?>">
<meta property="product:price:currency" content="<?php echo htmlspecialchars($seo_product_currency); ?>">
<?php endif; ?>
<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($seo_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($seo_description); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($seo_image); ?>">
<?php if ($seo_image_alt !== ''): ?>
<meta name="twitter:image:alt" content="<?php echo htmlspecialchars($seo_image_alt); ?>">
<?php endif; ?>
<?php
$org = get_seo_site_organization();
?>
<meta name="geo.region" content="SN-DK">
<meta name="geo.placename" content="Dakar">
<meta name="geo.position" content="<?php echo htmlspecialchars($org['geo']['latitude'] . ';' . $org['geo']['longitude']); ?>">
<meta name="ICBM" content="<?php echo htmlspecialchars($org['geo']['latitude'] . ', ' . $org['geo']['longitude']); ?>">
<?php
$schema_graphs = [];
if (empty($seo_noindex)) {
    if (!empty($seo_schema_graphs) && is_array($seo_schema_graphs)) {
        $schema_graphs = $seo_schema_graphs;
    } else {
        $schema_graphs = seo_schema_default_graphs();
    }
    seo_schema_render_scripts($schema_graphs);
}
?>