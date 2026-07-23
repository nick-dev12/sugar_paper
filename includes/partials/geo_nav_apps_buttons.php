<?php
/**
 * Bouton partage localisation client (admin / livreur).
 * Variables : $geo_nav_lat, $geo_nav_lng
 * Optionnel : $geo_nav_label, $geo_nav_wrap_class
 */
require_once __DIR__ . '/../geo_location.php';

if (!isset($geo_nav_lat, $geo_nav_lng) || !geo_coords_valid((float) $geo_nav_lat, (float) $geo_nav_lng)) {
    return;
}

$geo_nav_lat = (float) $geo_nav_lat;
$geo_nav_lng = (float) $geo_nav_lng;
$geo_nav_label = isset($geo_nav_label) ? (string) $geo_nav_label : 'Position client';
$geo_nav_wrap_class = isset($geo_nav_wrap_class) ? (string) $geo_nav_wrap_class : 'geo-nav-apps';
$geo_nav_maps_point = 'https://maps.google.com/?q=' . rawurlencode($geo_nav_lat . ',' . $geo_nav_lng);
?>
<div class="<?php echo htmlspecialchars($geo_nav_wrap_class, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button"
        class="geo-nav-app geo-nav-app--share js-geo-share-location"
        title="Partager la localisation du client"
        data-lat="<?php echo htmlspecialchars((string) $geo_nav_lat, ENT_QUOTES, 'UTF-8'); ?>"
        data-lng="<?php echo htmlspecialchars((string) $geo_nav_lng, ENT_QUOTES, 'UTF-8'); ?>"
        data-label="<?php echo htmlspecialchars($geo_nav_label, ENT_QUOTES, 'UTF-8'); ?>"
        data-share-title="<?php echo htmlspecialchars($geo_nav_label, ENT_QUOTES, 'UTF-8'); ?>"
        data-share-url="<?php echo htmlspecialchars($geo_nav_maps_point, ENT_QUOTES, 'UTF-8'); ?>"
        data-share-text="<?php echo htmlspecialchars($geo_nav_label, ENT_QUOTES, 'UTF-8'); ?>"
        data-share-modal-title="Partager la localisation du client"
        data-share-hint="Partagez le point GPS du client avec votre livreur ou vos contacts.">
        <i class="fas fa-share-nodes" aria-hidden="true"></i> Partager la localisation
    </button>
</div>
