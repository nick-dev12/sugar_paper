<?php
/**
 * Carte produit style marketplace (mp-card) — Super Admin modération boutique.
 * Variables : $produit, $vendeur_id (int), $sa_produit_detail_base (optionnel, défaut produit.php)
 */
if (empty($produit) || !is_array($produit)) {
    return;
}
if (!function_exists('upload_image_url')) {
    require_once __DIR__ . '/../image_optimizer.php';
}
$vendeur_id = isset($vendeur_id) ? (int) $vendeur_id : 0;
$pid = (int) ($produit['id'] ?? 0);
if ($pid <= 0 || $vendeur_id <= 0) {
    return;
}
$detail_base = isset($sa_produit_detail_base) ? (string) $sa_produit_detail_base : 'produit.php';
$detail_url = $detail_base . '?id=' . $pid . '&vendeur_id=' . $vendeur_id;
$pst = (string) ($produit['statut'] ?? '');
$is_bloque = ($pst === 'bloque');
$prix_affichage = !empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < (float) $produit['prix']
    ? (float) $produit['prix_promotion']
    : (float) ($produit['prix'] ?? 0);
$has_promotion = !empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < (float) $produit['prix'];
$gallery = produit_images_list_from_row($produit);
$thumb = !empty($gallery[0]) ? $gallery[0] : trim((string) ($produit['image_principale'] ?? ''));
?>
<article class="mp-card mp-card--sa<?php echo $is_bloque ? ' mp-card--sa-bloque' : ''; ?>">
    <a href="<?php echo htmlspecialchars($detail_url, ENT_QUOTES, 'UTF-8'); ?>" class="mp-card-link">
        <div class="mp-card-img">
            <span class="sa-mp-card-statut sa-mp-card-statut--<?php echo htmlspecialchars($pst, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(produit_statut_label($pst), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <img src="<?php echo htmlspecialchars(upload_image_url($thumb, 'md'), ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars((string) ($produit['nom'] ?? 'Produit'), ENT_QUOTES, 'UTF-8'); ?>"
                loading="lazy"
                onerror="this.src='/image/produit1.jpg'">
        </div>
        <div class="mp-card-body">
            <h3 class="mp-card-title"><?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="mp-card-price-row">
                <?php if ($has_promotion): ?>
                <span class="mp-card-price-old"><?php echo number_format((float) $produit['prix'], 0, ',', ' '); ?> FCFA</span>
                <span class="mp-card-price"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                <?php else: ?>
                <span class="mp-card-price"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <div class="mp-card-cart">
        <a href="<?php echo htmlspecialchars($detail_url, ENT_QUOTES, 'UTF-8'); ?>" class="mp-card-btn mp-card-btn--sa-detail">
            <i class="fas fa-eye" aria-hidden="true"></i> Voir détails
        </a>
    </div>
</article>
