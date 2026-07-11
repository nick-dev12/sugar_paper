<?php
/**
 * Badge certification dans les bandeaux hero vendeur
 */
if (empty($__vendeur_cert_niveau_hero)) {
    return;
}
$cert_niveau = (string) $__vendeur_cert_niveau_hero;
$cert_size = 'sm';
?>
<div class="vendeur-hero-cert" aria-label="Certification active">
    <?php require __DIR__ . '/vendeur_certification_badge.php'; ?>
</div>
