<?php
/**
 * Tuile boutique marketplace (logo + nom).
 * Variable : $boutique (array admin)
 * Optionnel : $tile_class suffixe CSS
 */
if (empty($boutique) || !is_array($boutique)) {
    return;
}

if (!function_exists('boutique_vitrine_entry_href')) {
    require_once dirname(__DIR__) . '/marketplace_helpers.php';
}
if (!function_exists('marketplace_boutique_card_theme')) {
    require_once dirname(__DIR__) . '/marketplace_boutique_card_helpers.php';
}

$slug = trim((string) ($boutique['boutique_slug'] ?? ''));
if ($slug === '') {
    return;
}

$nom = trim((string) ($boutique['boutique_nom'] ?? ''));
if ($nom === '') {
    $nom = 'Boutique';
}

$href = boutique_vitrine_entry_href($slug);
$logo_rel = trim((string) ($boutique['boutique_logo'] ?? ''));
$logo_url = $logo_rel !== '' ? '/upload/' . str_replace('\\', '/', $logo_rel) : '';
$tile_extra = isset($tile_class) ? trim((string) $tile_class) : '';
$distance_km = isset($boutique['distance_km']) && $boutique['distance_km'] !== null
    ? (float) $boutique['distance_km']
    : null;

$theme = marketplace_boutique_card_theme($boutique);
$style_vars = '--tile-main:' . htmlspecialchars($theme['main'], ENT_QUOTES, 'UTF-8')
    . ';--tile-accent:' . htmlspecialchars($theme['accent'], ENT_QUOTES, 'UTF-8');
?>
<a class="mp-bt-tile mp-bt-tile--showcase<?php echo $tile_extra !== '' ? ' ' . htmlspecialchars($tile_extra, ENT_QUOTES, 'UTF-8') : ''; ?>"
    href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
    style="<?php echo $style_vars; ?>">
    <span class="mp-bt-tile__glow" aria-hidden="true"></span>
    <span class="mp-bt-tile__logo" aria-hidden="true">
        <span class="mp-bt-tile__logo-ring"></span>
        <?php if ($logo_url !== ''): ?>
            <img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                alt=""
                loading="lazy"
                decoding="async"
                onerror="this.remove();">
        <?php endif; ?>
        <i class="fas fa-store"></i>
    </span>
    <span class="mp-bt-tile__body">
        <span class="mp-bt-tile__name"><?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php if ($distance_km !== null && function_exists('geo_format_distance')): ?>
            <span class="mp-bt-tile__dist">
                <i class="fas fa-route" aria-hidden="true"></i>
                <?php echo htmlspecialchars(geo_format_distance($distance_km), ENT_QUOTES, 'UTF-8'); ?>
            </span>
        <?php endif; ?>
    </span>
</a>
