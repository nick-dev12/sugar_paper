<?php
/**
 * Badge certification vendeur — image officielle (Standard / VIP / Premium).
 * Variables : $cert_niveau (standard|vip|premium), $cert_size (xs|sm|md|lg|rail|tier, optionnel)
 */
if (empty($cert_niveau) || !in_array((string) $cert_niveau, ['standard', 'vip', 'premium'], true)) {
    return;
}
require_once __DIR__ . '/../../models/model_vendeur_certification.php';
$cert_meta = vendeur_certification_niveaux()[(string) $cert_niveau];
$cert_size_val = isset($cert_size) ? (string) $cert_size : 'md';
$cert_sizes_ok = ['xs', 'sm', 'md', 'lg', 'rail', 'tier'];
$cert_size_class = in_array($cert_size_val, $cert_sizes_ok, true) ? ' cert-badge-img--' . $cert_size_val : ' cert-badge-img--md';
$cert_label = (string) ($cert_meta['label'] ?? ucfirst((string) $cert_niveau));
$cert_img_src = vendeur_certification_badge_image_src((string) $cert_niveau);
if ($cert_img_src === '') {
    return;
}
$cert_alt = 'Boutique certifiée ' . $cert_label;
?>
<span class="cert-badge-wrap cert-badge-wrap--<?php echo htmlspecialchars((string) $cert_niveau, ENT_QUOTES, 'UTF-8'); ?><?php echo $cert_size_class; ?>"
    title="<?php echo htmlspecialchars($cert_alt, ENT_QUOTES, 'UTF-8'); ?>"
    role="img"
    aria-label="<?php echo htmlspecialchars($cert_alt, ENT_QUOTES, 'UTF-8'); ?>">
    <img src="<?php echo htmlspecialchars($cert_img_src, ENT_QUOTES, 'UTF-8'); ?>"
        alt=""
        class="cert-badge-img cert-badge-img--<?php echo htmlspecialchars((string) $cert_niveau, ENT_QUOTES, 'UTF-8'); ?><?php echo $cert_size_class; ?>"
        loading="lazy"
        decoding="async"
        draggable="false">
</span>
