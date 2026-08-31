<?php
/**
 * Sitemap XML pour le référencement
 * Accessible via /sitemap.php ou /sitemap.xml (avec rewrite)
 */
header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/includes/seo_config.php';
require_once __DIR__ . '/includes/home_sections.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_categories.php';

$base = get_site_base_url();
$logo_url = $base . '/image/sugar_paper.jpg';

$static_pages = [
    ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily', 'image' => $logo_url, 'image_title' => 'Sugar Paper - Décoration de gâteaux personnalisée'],
    ['loc' => '/contact.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => '/produits.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/nouveautes.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/promo.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/commande-personnalisee.php', 'priority' => '0.85', 'changefreq' => 'weekly'],
    ['loc' => '/politique-confidentialite.php', 'priority' => '0.4', 'changefreq' => 'yearly'],
    ['loc' => '/conditions-utilisation.php', 'priority' => '0.4', 'changefreq' => 'yearly'],
];

$section_pages = [];
foreach (get_home_sections_config() as $section_key => $config) {
    $section_pages[] = [
        'loc' => '/section-produits.php?section=' . rawurlencode($section_key),
        'priority' => '0.85',
        'changefreq' => 'weekly',
    ];
}

$category_pages = [];
$categories = get_all_categories();
foreach ($categories as $cat) {
    if (empty($cat['id'])) {
        continue;
    }
    $category_pages[] = [
        'loc' => '/categorie.php?id=' . (int) $cat['id'],
        'priority' => '0.75',
        'changefreq' => 'weekly',
    ];
}

$produits = get_all_produits('actif');
$product_pages = [];
foreach ($produits as $p) {
    $entry = [
        'loc' => '/produit.php?id=' . (int) $p['id'],
        'priority' => '0.8',
        'changefreq' => 'weekly',
        'lastmod' => isset($p['date_modification']) ? $p['date_modification'] : ($p['date_creation'] ?? null),
    ];
    if (!empty($p['image_principale'])) {
        $entry['image'] = $base . '/' . ltrim(upload_image_url($p['image_principale'], 'original'), '/');
        $entry['image_title'] = $p['nom'] ?? 'Produit Sugar Paper';
    }
    $product_pages[] = $entry;
}

$urls = array_merge($static_pages, $section_pages, $category_pages, $product_pages);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach ($urls as $u):
    $loc = $base . $u['loc'];
    $lastmod = '';
    if (!empty($u['lastmod'])) {
        $lastmod = date('Y-m-d', strtotime($u['lastmod']));
    } else {
        $lastmod = date('Y-m-d');
    }
?>
    <url>
        <loc><?php echo htmlspecialchars($loc); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq><?php echo htmlspecialchars($u['changefreq'] ?? 'weekly'); ?></changefreq>
        <priority><?php echo htmlspecialchars($u['priority'] ?? '0.5'); ?></priority>
        <?php if (!empty($u['image'])): ?>
        <image:image>
            <image:loc><?php echo htmlspecialchars($u['image']); ?></image:loc>
            <?php if (!empty($u['image_title'])): ?>
            <image:title><?php echo htmlspecialchars($u['image_title']); ?></image:title>
            <?php endif; ?>
        </image:image>
        <?php endif; ?>
    </url>
<?php endforeach; ?>
</urlset>
