<?php
/**
 * Partial — carte produit avec offres en attente (dashboard vendeur)
 * Variables : $prix_neg_groupe (array from prix_negociation_group_by_produit)
 */
if (!isset($prix_neg_groupe) || !is_array($prix_neg_groupe)) {
    return;
}

$pn_prod_id = (int) ($prix_neg_groupe['produit_id'] ?? 0);
$pn_prod_nom = (string) ($prix_neg_groupe['produit_nom'] ?? 'Produit');
$pn_prod_img = trim((string) ($prix_neg_groupe['produit_image'] ?? ''));
$pn_pending = (int) ($prix_neg_groupe['pending_count'] ?? 0);
$pn_modal_id = 'prixNegOffersProd' . $pn_prod_id;

if (!function_exists('upload_image_url')) {
    require_once dirname(__DIR__) . '/image_optimizer.php';
}

$pn_img_url = $pn_prod_img !== '' ? upload_image_url($pn_prod_img, 'sm') : '';
?>
<article class="prix-neg-produit-card" role="button" tabindex="0"
    data-prix-neg-offers-open="<?php echo htmlspecialchars($pn_modal_id, ENT_QUOTES, 'UTF-8'); ?>"
    aria-label="Voir les offres de prix pour <?php echo htmlspecialchars($pn_prod_nom, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="prix-neg-produit-card__media">
        <?php if ($pn_img_url !== ''): ?>
        <img src="<?php echo htmlspecialchars($pn_img_url, ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?php echo htmlspecialchars($pn_prod_nom, ENT_QUOTES, 'UTF-8'); ?>"
            loading="lazy" width="72" height="72">
        <?php else: ?>
        <span class="prix-neg-produit-card__placeholder" aria-hidden="true"><i class="fas fa-box"></i></span>
        <?php endif; ?>
        <?php if ($pn_pending > 0): ?>
        <span class="prix-neg-produit-card__badge"><?php echo $pn_pending; ?></span>
        <?php endif; ?>
    </div>
    <div class="prix-neg-produit-card__body">
        <h4 class="prix-neg-produit-card__title"><?php echo htmlspecialchars($pn_prod_nom, ENT_QUOTES, 'UTF-8'); ?></h4>
        <p class="prix-neg-produit-card__meta">
            <?php if ($pn_pending > 0): ?>
                <?php echo $pn_pending; ?> offre<?php echo $pn_pending > 1 ? 's' : ''; ?> en attente
            <?php else: ?>
                N&eacute;gociations en cours
            <?php endif; ?>
        </p>
    </div>
    <button type="button" class="prix-neg-btn prix-neg-btn--view-offers">
        <i class="fas fa-tags" aria-hidden="true"></i>
        <span>Voir les offres de prix</span>
    </button>
</article>
