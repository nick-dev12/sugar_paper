<?php
/**
 * Badge pays (drapeau + nom) — barre info, footer, etc.
 * Variable optionnelle : $country_badge_variant = 'light' | 'dark'
 */
$country_badge_variant = isset($country_badge_variant) && $country_badge_variant === 'dark' ? 'dark' : 'light';
?>
<span class="site-country-badge site-country-badge--<?php echo $country_badge_variant; ?>">
    <span class="site-flag site-flag--sn" aria-hidden="true"></span>
    <span class="site-country-name">Sénégal</span>
</span>
