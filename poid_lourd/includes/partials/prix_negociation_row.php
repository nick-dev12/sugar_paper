<?php
/**
 * Partial — ligne négociation vendeur (design uc-v2-card)
 * Variables : $neg (array), $prix_neg_side, $prix_neg_compact
 */
if (!isset($neg) || !is_array($neg)) {
    return;
}
$prix_neg_side = ($prix_neg_side ?? '') === 'client' ? 'client' : 'vendor';
if ($prix_neg_side !== 'vendor') {
    include __DIR__ . '/prix_negociation_client_card.php';
    return;
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
$prix_propose = (float) ($neg['prix_propose_client'] ?? 0);
$prix_ref = (float) ($neg['prix_reference'] ?? 0);
$client_nom = trim(($neg['user_prenom'] ?? '') . ' ' . ($neg['user_nom'] ?? ''));
$produit_nom = (string) ($neg['produit_nom'] ?? 'Produit');
$reject_overlay_id = 'prixNegReject' . $neg_id;

if (!function_exists('upload_image_url')) {
    require_once dirname(__DIR__) . '/image_optimizer.php';
}
$produit_img = trim((string) ($neg['produit_image'] ?? ''));
$img_url = $produit_img !== '' ? upload_image_url($produit_img, 'sm') : '';
?>
<article class="uc-v2-card uc-v2-card--prix-neg uc-v2-card--prix-neg-vendor" data-neg-id="<?php echo $neg_id; ?>">
    <div class="uc-v2-card__top">
        <div class="uc-v2-card__ref">
            <div class="uc-v2-card__ref-head">
                <span class="uc-v2-card__boutique"><?php echo htmlspecialchars($client_nom !== '' ? $client_nom : 'Client', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <span class="prix-neg-statut <?php echo htmlspecialchars($statut_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statut_label, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>

    <div class="uc-v2-card__body">
        <?php if ($img_url !== ''): ?>
            <div class="uc-v2-card__thumb uc-v2-card__thumb--static" aria-hidden="true">
                <img src="<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" onerror="this.src='/image/produit1.jpg'">
            </div>
        <?php endif; ?>
        <div class="uc-v2-card__body-inner">
            <div class="uc-v2-card__info">
                <div class="uc-v2-card__amount uc-v2-card__amount--neg">
                    <?php echo number_format($prix_propose, 0, ',', ' '); ?><small>FCFA</small>
                </div>
                <div class="uc-v2-card__neg-meta">
                    <span class="uc-v2-card__neg-ref"><?php echo htmlspecialchars($produit_nom, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="uc-v2-card__neg-offer">Prix base : <?php echo number_format($prix_ref, 0, ',', ' '); ?> FCFA</span>
                </div>
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

    <?php if ($statut === 'en_attente'): ?>
    <div class="uc-v2-card__footer uc-v2-card__footer--vendor uc-v2-card__footer--vendor-has-pos">
        <form method="POST" action="/admin/prix-negociation-action.php" style="display:contents;">
            <input type="hidden" name="action" value="accept">
            <input type="hidden" name="negotiation_id" value="<?php echo $neg_id; ?>">
            <input type="hidden" name="redirect" value="/admin/dashboard.php">
            <button type="submit" class="uc-v2-card__pos-btn">
                <i class="fas fa-check"></i> Valider l'offre
            </button>
        </form>
        <button type="button" class="uc-card-btn uc-card-btn--vendor-detail"
            data-prix-neg-reject-open="<?php echo htmlspecialchars($reject_overlay_id, ENT_QUOTES, 'UTF-8'); ?>">
            <span>Rejeter l'offre</span>
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>
    <?php endif; ?>
</article>

<?php if ($statut === 'en_attente'): ?>
<div class="prix-neg-reject-overlay" id="<?php echo htmlspecialchars($reject_overlay_id, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true" hidden>
    <div class="prix-neg-reject-overlay__backdrop" data-prix-neg-reject-close tabindex="-1"></div>
    <div class="prix-neg-reject-panel" role="dialog" aria-modal="true">
        <div class="prix-neg-reject-panel__head">
            <h3>Rejeter ou contre-proposer</h3>
            <button type="button" class="prix-neg-modal__close" data-prix-neg-reject-close aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p class="prix-neg-reject-panel__hint">Laissez le champ vide pour refuser l'offre, ou saisissez un prix &agrave; proposer au client.</p>
        <form method="POST" action="/admin/prix-negociation-action.php" class="prix-neg-reject-panel__form" data-prix-neg-reject-form>
            <input type="hidden" name="negotiation_id" value="<?php echo $neg_id; ?>">
            <input type="hidden" name="redirect" value="/admin/dashboard.php">
            <input type="hidden" name="action" value="reject_final" data-prix-neg-reject-action>
            <label for="prix-contre-<?php echo $neg_id; ?>">Votre prix (FCFA) — optionnel</label>
            <input type="number" name="prix_contre" id="prix-contre-<?php echo $neg_id; ?>" min="1" step="1"
                placeholder="Ex : 420 000" inputmode="numeric" data-prix-neg-reject-input>
            <div class="prix-neg-reject-panel__actions">
                <button type="button" class="prix-neg-modal__btn prix-neg-modal__btn--cancel" data-prix-neg-reject-close>Annuler</button>
                <button type="submit" class="prix-neg-btn prix-neg-btn--reject" data-prix-neg-reject-submit>
                    <i class="fas fa-times"></i> Rejeter l'offre
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
