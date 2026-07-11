<?php
/**
 * Carte « Nouveautés » compacte — alignée à gauche, prix + ligne promo (index).
 * Variables : $produit, $return_url (optionnel).
 */
if (empty($produit) || !is_array($produit)) {
    return;
}
if (!function_exists('upload_image_url')) {
    require_once __DIR__ . '/../image_optimizer.php';
}
$return_url = isset($return_url) ? (string) $return_url : (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');
$pid = (int) ($produit['id'] ?? 0);
if ($pid <= 0) {
    return;
}
$prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? (float) $produit['prix_promotion']
    : (float) ($produit['prix'] ?? 0);
$has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
$nom = (string) ($produit['nom'] ?? 'Produit');
?>
<article class="mp-new-card">
    <a href="produit.php?id=<?php echo $pid; ?>" class="mp-new-card-link">
        <div class="mp-new-card-img">
            <span class="mp-new-badge" aria-label="Nouveau">Nouveau</span>
            <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                alt="<?php echo htmlspecialchars($nom); ?>"
                loading="lazy"
                onerror="this.src='/image/produit1.jpg'">
        </div>
        <h3 class="mp-new-title"><?php echo htmlspecialchars($nom); ?></h3>
        <?php if (!empty($produit['avis_count'])): ?>
            <?php
            $note = (float) ($produit['avis_moyenne'] ?? 0);
            $count = (int) ($produit['avis_count'] ?? 0);
            $size = 'sm';
            require __DIR__ . '/product_rating_stars.php';
            ?>
        <?php endif; ?>
        <?php if ($has_promotion): ?>
        <p class="mp-new-promo">Promotion en cours</p>
        <?php endif; ?>
        <p class="mp-new-price"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> <span>FCFA</span></p>
    </a>
    <form method="POST" action="/add-to-panier.php" class="mp-new-cart">
        <input type="hidden" name="produit_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="quantite" value="1">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
        <button type="submit" class="mp-new-cart-btn"><i class="fa-solid fa-cart-plus" aria-hidden="true"></i></button>
    </form>
</article>
