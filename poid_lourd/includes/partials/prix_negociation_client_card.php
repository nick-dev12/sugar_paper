<?php
/**
 * Partial — carte négociation client (design uc-v2-card)
 * Variables : $neg (array)
 */


if (!isset($neg) || !is_array($neg)) {
    return;
}

if (!function_exists('upload_image_url')) {
    require_once dirname(__DIR__) . '/image_optimizer.php';
}

$neg_id = (int) ($neg['id'] ?? 0);
$statut = (string) ($neg['statut'] ?? '');
$statut_label = function_exists('prix_negociation_statut_label')
    ? prix_negociation_statut_label($statut)
    : $statut;
$statut_class = function_exists('prix_negociation_statut_css_class')
    ? prix_negociation_statut_css_class($statut)
    : 'prix-neg-statut--attente';
$date_fmt = !empty($neg['date_maj']) ? date('d/m/Y H:i', strtotime($neg['date_maj'])) : '';
$prix_ref = (float) ($neg['prix_reference'] ?? 0);
$prix_propose = (float) ($neg['prix_propose_client'] ?? 0);
$prix_contre = isset($neg['prix_contre_vendeur']) ? (float) $neg['prix_contre_vendeur'] : 0;
$prix_convenu = function_exists('prix_negociation_prix_convenu_effectif')
    ? prix_negociation_prix_convenu_effectif($neg)
    : null;
$produit_id = (int) ($neg['produit_id'] ?? 0);
$produit_nom = (string) ($neg['produit_nom'] ?? 'Produit');
$produit_img = trim((string) ($neg['produit_image'] ?? ''));
$img_url = $produit_img !== '' ? upload_image_url($produit_img, 'sm') : '';
$shop = (string) ($neg['vendeur_boutique_nom'] ?? 'Boutique');
$opts = function_exists('prix_negociation_options_json_decode')
    ? prix_negociation_options_json_decode($neg['options_json'] ?? null)
    : [];
$can_repropose = in_array($statut, ['contre_proposee', 'refusee_finale'], true);
$can_order = function_exists('prix_negociation_peut_commander') && prix_negociation_peut_commander($neg);
$prix_affiche = $prix_contre > 0 && in_array($statut, ['contre_proposee', 'acceptee', 'commandee'], true)
    ? $prix_contre
    : ($prix_convenu !== null && $statut === 'acceptee' ? (float) $prix_convenu : $prix_propose);
?>
<article class="uc-v2-card uc-v2-card--prix-neg" data-neg-id="<?php echo $neg_id; ?>">
    <div class="uc-v2-card__top">
        <div class="uc-v2-card__ref">
            <div class="uc-v2-card__ref-head">
                <span
                    class="uc-v2-card__boutique"><?php echo htmlspecialchars($produit_nom, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <span
            class="prix-neg-statut <?php echo htmlspecialchars($statut_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statut_label, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>

    <div class="uc-v2-card__body">
        <?php if ($img_url !== ''): ?>
            <div class="uc-v2-card__thumb uc-v2-card__thumb--static" aria-hidden="true">
                <img src="<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars($produit_nom, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"
                    onerror="this.src='/image/produit1.jpg'">
            </div>
        <?php endif; ?>
        <div class="uc-v2-card__body-inner">
            <div class="uc-v2-card__info">
                <div class="uc-v2-card__amount uc-v2-card__amount--neg">
                    <?php echo number_format($prix_affiche, 0, ',', ' '); ?><small>FCFA</small>
                </div>
                <div class="uc-v2-card__neg-meta">
                    <span class="uc-v2-card__neg-ref">Prix du vendeur :
                        <s><?php echo number_format($prix_ref, 0, ',', ' '); ?> FCFA</s></span>
                    <span class="uc-v2-card__neg-offer"><strong>Votre offre :
                        <?php echo number_format($prix_propose, 0, ',', ' '); ?> FCFA</strong></span>
                </div>
                <span class="uc-v2-card__tel uc-v2-card__tel--boutique">
                    <i class="fas fa-store"></i>
                    <?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="uc-v2-card__meta-bar">
        <span class="uc-v2-card__meta-line">
            <span class="uc-v2-card__ref-num">#NEG-<?php echo $neg_id; ?></span>
            <span class="uc-v2-card__sep" aria-hidden="true">&middot;</span>
            <span class="uc-v2-card__date"><?php echo htmlspecialchars($date_fmt, ENT_QUOTES, 'UTF-8'); ?></span>
        </span>
    </div>

    <div class="uc-v2-card__footer">
        <?php if ($can_order): ?>
            <form method="POST" action="/user/prix-negociation-action.php" style="display:contents;">
                <input type="hidden" name="action" value="commander">
                <input type="hidden" name="negotiation_id" value="<?php echo $neg_id; ?>">
                <input type="hidden" name="redirect" value="/user/mon-compte.php">
                <button type="submit" class="uc-card-btn uc-card-btn--confirm">
                    <i class="fas fa-cart-shopping"></i> Commander
                </button>
            </form>
        <?php endif; ?>
        <?php if ($can_repropose): ?>
            <button type="button" class="uc-card-btn uc-card-btn--track" data-prix-neg-client-open
                data-produit-id="<?php echo $produit_id; ?>"
                data-produit-nom="<?php echo htmlspecialchars($produit_nom, ENT_QUOTES, 'UTF-8'); ?>"
                data-produit-image="<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>"
                data-prix-reference="<?php echo (float) $prix_ref; ?>"
                data-option-variante-id="<?php echo (int) ($opts['variante_id'] ?? 0); ?>"
                data-option-couleur="<?php echo htmlspecialchars((string) ($opts['couleur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-option-poids="<?php echo htmlspecialchars((string) ($opts['poids'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-option-taille="<?php echo htmlspecialchars((string) ($opts['taille'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-option-variante-nom="<?php echo htmlspecialchars((string) ($opts['variante_nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-option-variante-image="<?php echo htmlspecialchars((string) ($opts['variante_image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-option-surcout-poids="<?php echo (float) ($opts['surcout_poids'] ?? 0); ?>"
                data-option-surcout-taille="<?php echo (float) ($opts['surcout_taille'] ?? 0); ?>">
                <i class="fas fa-handshake"></i> Nouvelle offre
            </button>
        <?php endif; ?>
        <?php if ($produit_id > 0): ?>
            <a href="/produit.php?id=<?php echo $produit_id; ?>" class="uc-card-btn uc-card-btn--detail">
                <i class="fas fa-eye"></i> Voir le produit
            </a>
        <?php endif; ?>
    </div>
</article>