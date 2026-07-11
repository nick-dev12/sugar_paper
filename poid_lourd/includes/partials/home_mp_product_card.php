<?php
/**
 * Carte produit « marketplace » pour la page d'accueil (index.php).
 * Variables attendues : $produit (tableau), $return_url (string optionnel).
 * Optionnel : $show_nouveau_badge (bool) — affiche le badge « Nouveau ».
 */
if (empty($produit) || !is_array($produit)) {
    return;
}
if (!function_exists('upload_image_url')) {
    require_once __DIR__ . '/../image_optimizer.php';
}
$return_url = isset($return_url) ? (string) $return_url : (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');
$card_prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix_promotion']
    : $produit['prix'];
$has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
$show_nouveau_badge = !empty($show_nouveau_badge);
$pid = (int) ($produit['id'] ?? 0);
if ($pid <= 0) {
    return;
}
?>
<article class="mp-card">
    <?php require __DIR__ . '/product_share_button.php'; ?>
    <a href="produit.php?id=<?php echo $pid; ?>" class="mp-card-link">
        <div class="mp-card-img">
            <?php if ($show_nouveau_badge): ?>
            <span class="mp-card-badge mp-card-badge--nouveau">Nouveau</span>
            <?php endif; ?>
            <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                loading="lazy"
                onerror="this.src='/image/produit1.jpg'">
        </div>
        <div class="mp-card-body">
            <h3 class="mp-card-title"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?></h3>
            <?php if (!empty($produit['avis_count'])): ?>
                <?php
                $note = (float) ($produit['avis_moyenne'] ?? 0);
                $count = (int) ($produit['avis_count'] ?? 0);
                $size = 'sm';
                require __DIR__ . '/product_rating_stars.php';
                ?>
            <?php endif; ?>
            <div class="mp-card-price-row">
                <?php if ($has_promotion): ?>
                <span class="mp-card-price-old"><?php echo number_format((float) $produit['prix'], 0, ',', ' '); ?> FCFA</span>
                <span class="mp-card-price"><?php echo number_format((float) $card_prix_affichage, 0, ',', ' '); ?> FCFA</span>
                <?php else: ?>
                <span class="mp-card-price"><?php echo number_format((float) $card_prix_affichage, 0, ',', ' '); ?> FCFA</span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <form method="POST" action="/add-to-panier.php" class="mp-card-cart">
        <input type="hidden" name="produit_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="quantite" value="1">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
        <button type="submit" class="mp-card-btn">
            <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Ajouter
        </button>
    </form>
</article>
