<?php
/**
 * Dock mobile/tablette de la navigation boutique.
 * Ce composant reste volontairement autonome pour eviter les conflits CSS du dock historique.
 *
 * @var string $shop_dock_home_href
 * @var string $u_produits
 * @var string $nav_panier_href
 * @var string $nav_compte_href
 * @var bool $shop_dock_home_act
 * @var bool $shop_dock_produits_act
 * @var bool $shop_dock_panier_act
 * @var bool $shop_dock_compte_act
 * @var int $panier_count
 */
?>
<div class="shop-mobile-dock" id="shopMobileDock" aria-label="Navigation boutique rapide">
    <div class="shop-mobile-dock__bar" id="shopDockBar" aria-label="Navigation boutique reduite">
        <nav class="shop-mobile-dock__nav" aria-label="Raccourcis boutique">
            <a href="<?php echo htmlspecialchars($shop_dock_home_href ?? '/index.php', ENT_QUOTES, 'UTF-8'); ?>"
                class="shop-mobile-dock__item<?php echo $shop_dock_home_act ? ' is-active' : ''; ?>">
                <span class="shop-mobile-dock__icon" aria-hidden="true"><i class="fa-solid fa-house"></i></span>
                <span class="shop-mobile-dock__label">Accueil</span>
            </a>
            <a href="<?php echo htmlspecialchars($u_produits, ENT_QUOTES, 'UTF-8'); ?>"
                class="shop-mobile-dock__item<?php echo $shop_dock_produits_act ? ' is-active' : ''; ?>">
                <span class="shop-mobile-dock__icon" aria-hidden="true"><i class="fa-solid fa-bag-shopping"></i></span>
                <span class="shop-mobile-dock__label">Produits</span>
            </a>
            <a href="<?php echo htmlspecialchars($nav_panier_href, ENT_QUOTES, 'UTF-8'); ?>"
                class="shop-mobile-dock__item shop-mobile-dock__item--badge<?php echo $shop_dock_panier_act ? ' is-active' : ''; ?>"
                title="Panier">
                <span class="shop-mobile-dock__icon shop-mobile-dock__icon--badge-host" aria-hidden="true">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($panier_count > 0): ?>
                        <span class="shop-mobile-dock__badge"
                            aria-label="<?php echo (int) $panier_count; ?> article<?php echo $panier_count > 1 ? 's' : ''; ?> dans le panier"><?php echo $panier_count > 99 ? '99+' : (int) $panier_count; ?></span>
                    <?php endif; ?>
                </span>
                <span class="shop-mobile-dock__label">Panier</span>
            </a>
            <a href="<?php echo htmlspecialchars($nav_compte_href, ENT_QUOTES, 'UTF-8'); ?>"
                class="shop-mobile-dock__item<?php echo $shop_dock_compte_act ? ' is-active' : ''; ?>">
                <span class="shop-mobile-dock__icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                <span class="shop-mobile-dock__label">Compte</span>
            </a>
            <button type="button" id="shopDockSidebarBtn" class="shop-mobile-dock__item shop-mobile-dock__button"
                aria-label="Ouvrir le menu catalogue">
                <span class="shop-mobile-dock__icon" aria-hidden="true"><i
                        class="fa-solid fa-table-cells-large"></i></span>
                <span class="shop-mobile-dock__label">Menu</span>
            </button>
        </nav>
    </div>
</div>