<?php
/**
 * Carte produit « Top classement » — centrée, badge TOP (index).
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
$nom = (string) ($produit['nom'] ?? 'Produit');
$nom_short = function_exists('mb_strlen') && mb_strlen($nom) > 38 ? mb_substr($nom, 0, 36) . '…' : $nom;
?>
<article class="mp-top-card">
    <a href="produit.php?id=<?php echo $pid; ?>" class="mp-top-card-link">
        <div class="mp-top-card-img-wrap">
            <div class="mp-top-card-img">
                <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                    alt="<?php echo htmlspecialchars($nom); ?>"
                    loading="lazy"
                    onerror="this.src='/image/produit1.jpg'">
            </div>
            <span class="mp-top-badge" aria-hidden="true">TOP</span>
        </div>
        <h3 class="mp-top-title"><?php echo htmlspecialchars($nom_short); ?></h3>
        <?php if (!empty($produit['avis_count'])): ?>
            <?php
            $note = (float) ($produit['avis_moyenne'] ?? 0);
            $count = (int) ($produit['avis_count'] ?? 0);
            $size = 'sm';
            require __DIR__ . '/product_rating_stars.php';
            ?>
        <?php endif; ?>
        <p class="mp-top-sub">Ventes à la une</p>
    </a>
    <form method="POST" action="/add-to-panier.php" class="mp-top-cart">
        <input type="hidden" name="produit_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="quantite" value="1">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
        <button type="submit" class="mp-top-cart-btn">Ajouter au panier</button>
    </form>
</article>
