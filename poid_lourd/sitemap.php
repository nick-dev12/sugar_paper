<?php
/**
 * Sitemap XML dynamique — COLObanes (marketplace Sénégal).
 * Accessible via /sitemap.xml (réécriture .htaccess → ce script).
 *
 * Liste : marketplace, légales/inscription, catégories, produits actifs (avec image si dispo),
 * vitrines vendeur (/slug/, … /categorie.php).
 */
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=1800'); // permettre mise en cache CDN / reverse-proxy

require_once __DIR__ . '/includes/sitemap_data.php';

$urls = build_sitemap_url_rows();
$base_xml = get_site_base_url();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach ($urls as $u):
    $loc = rtrim((string) $base_xml, '/') . $u['loc'];
    $lastmod = '';
    if (!empty($u['lastmod'])) {
        $ts = strtotime($u['lastmod']);
        $lastmod = $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
    } else {
        $lastmod = date('Y-m-d');
    }
?>
    <url>
        <loc><?php echo htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></loc>
        <lastmod><?php echo htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></lastmod>
        <changefreq><?php echo htmlspecialchars($u['changefreq'] ?? 'weekly', ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></changefreq>
        <priority><?php echo htmlspecialchars($u['priority'] ?? '0.5', ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></priority>
        <?php if (!empty($u['image'])): ?>
        <image:image>
            <image:loc><?php echo htmlspecialchars($u['image'], ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></image:loc>
            <?php if (!empty($u['image_title'])): ?>
            <image:title><?php echo htmlspecialchars($u['image_title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></image:title>
            <?php endif; ?>
        </image:image>
        <?php endif; ?>
    </url>
<?php endforeach; ?>
</urlset>
