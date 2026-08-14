<?php
/**
 * Corps de la modale panier.
 * Variables : $panier_items, $panier_total, $nombre_total_articles,
 *             $user_logged_in, $ckm_message, $ckm_message_type
 */
$panier_items = isset($panier_items) && is_array($panier_items) ? $panier_items : [];
$panier_total = isset($panier_total) ? (float) $panier_total : 0;
$nombre_total_articles = isset($nombre_total_articles) ? (int) $nombre_total_articles : 0;
$user_logged_in = !empty($user_logged_in);
$ckm_message = isset($ckm_message) ? (string) $ckm_message : '';
$ckm_message_type = isset($ckm_message_type) ? (string) $ckm_message_type : '';
?>
<div class="ckm-cart">
    <?php if ($ckm_message !== ''): ?>
        <div class="ckm-flash ckm-flash--<?php echo $ckm_message_type === 'error' ? 'error' : 'success'; ?>">
            <i class="fas fa-<?php echo $ckm_message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
            <?php echo htmlspecialchars($ckm_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($panier_items)): ?>
        <div class="ckm-empty">
            <div class="ckm-empty__icon"><i class="fas fa-shopping-bag"></i></div>
            <p class="ckm-empty__title">Votre panier est vide</p>
            <p class="ckm-empty__text">Ajoutez un produit pour continuer.</p>
            <button type="button" class="ckm-btn ckm-btn--primary js-ckm-close">Continuer mes achats</button>
        </div>
    <?php else: ?>
        <div class="ckm-cart__list">
            <?php foreach ($panier_items as $item): ?>
                <?php
                $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                    ? (float) $item['panier_prix_unitaire']
                    : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
                $prix_total_item = $prix_unitaire * $item['quantite'];
                $item_img = !empty($item['panier_variante_image']) ? $item['panier_variante_image'] : ($item['image_principale'] ?? '');
                $item_nom = !empty($item['panier_variante_nom'])
                    ? $item['nom'] . ' → ' . $item['panier_variante_nom']
                    : ($item['nom'] ?? 'Produit');
                ?>
                <article class="ckm-item" data-item-id="<?php echo (int) $item['panier_id']; ?>">
                    <img class="ckm-item__img" src="<?php echo htmlspecialchars(upload_image_url($item_img, 'sm')); ?>"
                        alt="<?php echo htmlspecialchars($item_nom); ?>"
                        onerror="this.src='/image/produit1.jpg'">
                    <div class="ckm-item__body">
                        <h3 class="ckm-item__name"><?php echo htmlspecialchars($item_nom); ?></h3>
                        <?php if (!empty($item['categorie_nom'])): ?>
                            <p class="ckm-item__cat"><?php echo htmlspecialchars($item['categorie_nom']); ?></p>
                        <?php endif; ?>
                        <?php
                        $opts = [];
                        if (!empty(trim($item['panier_couleur'] ?? ''))) {
                            $hex = trim($item['panier_couleur']);
                            $opts[] = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)
                                ? '<span class="ckm-swatch" style="background:' . htmlspecialchars($hex) . '"></span>'
                                : htmlspecialchars($hex);
                        }
                        if (!empty(trim($item['panier_poids'] ?? ''))) {
                            $opts[] = htmlspecialchars($item['panier_poids']);
                        }
                        if (!empty(trim($item['panier_taille'] ?? ''))) {
                            $opts[] = htmlspecialchars($item['panier_taille']);
                        }
                        ?>
                        <?php if (!empty($opts)): ?>
                            <p class="ckm-item__opts"><?php echo implode(' · ', $opts); ?></p>
                        <?php endif; ?>
                        <p class="ckm-item__price"><?php echo number_format($prix_unitaire, 0, ',', ' '); ?> FCFA</p>
                        <div class="ckm-item__row">
                            <form method="POST" action="/api/modals/cart-action.php" class="ckm-qty-form js-ckm-qty-form">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="panier_id" value="<?php echo (int) $item['panier_id']; ?>">
                                <button type="button" class="ckm-qty-btn js-ckm-qty-minus" aria-label="Diminuer">−</button>
                                <input type="number" name="quantite" class="ckm-qty-input" value="<?php echo (int) $item['quantite']; ?>"
                                    min="1" max="<?php echo (int) $item['stock']; ?>" required>
                                <button type="button" class="ckm-qty-btn js-ckm-qty-plus" aria-label="Augmenter">+</button>
                            </form>
                            <form method="POST" action="/api/modals/cart-action.php" class="js-ckm-delete-form">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="panier_id" value="<?php echo (int) $item['panier_id']; ?>">
                                <button type="submit" class="ckm-item__remove" aria-label="Retirer">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                            <span class="ckm-item__line"><?php echo number_format($prix_total_item, 0, ',', ' '); ?> FCFA</span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="ckm-summary">
            <div class="ckm-summary__row">
                <span><?php echo $nombre_total_articles; ?> article<?php echo $nombre_total_articles > 1 ? 's' : ''; ?></span>
                <span><?php echo count($panier_items); ?> produit<?php echo count($panier_items) > 1 ? 's' : ''; ?></span>
            </div>
            <div class="ckm-summary__row ckm-summary__row--muted">
                <span>Livraison</span>
                <span>À calculer</span>
            </div>
            <div class="ckm-summary__total">
                <span>Total</span>
                <strong><?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA</strong>
            </div>
            <button type="button" class="ckm-btn ckm-btn--primary js-ckm-go-checkout">
                <i class="fas fa-bag-shopping"></i> Passer la commande
            </button>
        </aside>
    <?php endif; ?>
</div>
