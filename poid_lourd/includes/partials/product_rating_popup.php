<?php
/**
 * Popup notation produits (tableau de bord client).
 * Variables : $pr_pending_items (array), $pr_show_popup (bool)
 */
if (empty($pr_pending_items) || !is_array($pr_pending_items)) {
    return;
}
$pr_auto = !empty($pr_show_popup) ? '1' : '0';
$pr_auto_cmd = isset($pr_auto_open_commande) ? (int) $pr_auto_open_commande : 0;
?>
<div id="prRatingPopup" class="pr-popup" data-pr-auto-open="<?php echo $pr_auto; ?>" data-pr-auto-commande="<?php echo $pr_auto_cmd; ?>" aria-hidden="true" role="dialog" aria-labelledby="prRatingTitle" aria-modal="true">
    <div class="pr-popup__backdrop" aria-hidden="true"></div>
    <div class="pr-popup__panel">
        <header class="pr-popup__header">
            <span class="pr-popup__badge"><i class="fa-solid fa-star" aria-hidden="true"></i> Votre avis compte</span>
            <h2 id="prRatingTitle" class="pr-popup__title">Comment &eacute;taient vos produits ?</h2>
            <p class="pr-popup__lead pr-popup__lead--bonus">
                <i class="fa-solid fa-gift" aria-hidden="true"></i>
                Commentez et gagnez des <strong>points bonus</strong>
            </p>
        </header>
        <div id="prRatingForm" class="pr-popup__form">
            <div class="pr-popup__body">
                <?php foreach ($pr_pending_items as $item):
                    $pid = (int) ($item['produit_id'] ?? 0);
                    $cid = (int) ($item['commande_id'] ?? 0);
                    if ($pid <= 0 || $cid <= 0) {
                        continue;
                    }
                    $nom = trim((string) ($item['nom'] ?? 'Produit'));
                    $img = trim((string) ($item['image_principale'] ?? 'produit1.jpg'));
                    $num = trim((string) ($item['numero_commande'] ?? ''));
                    $key = $pid . '-' . $cid;
                ?>
                <div class="pr-popup-item" data-produit-id="<?php echo $pid; ?>" data-commande-id="<?php echo $cid; ?>" data-note-key="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                    <img class="pr-popup-item__img"
                        src="/upload/<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>"
                        alt=""
                        loading="lazy"
                        onerror="this.src='/image/produit1.jpg'">
                    <div>
                        <p class="pr-popup-item__nom"><?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if ($num !== ''): ?>
                        <p class="pr-popup-item__cmd">Commande #<?php echo htmlspecialchars($num, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <span class="pr-stars pr-stars--input" style="--pr-rating: 0;" aria-hidden="true">
                            <span class="pr-stars__track">
                                <span class="pr-stars__empty"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>
                                <span class="pr-stars__fill"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>
                            </span>
                        </span>
                        <input type="hidden" class="pr-popup-note-input" value="0">
                        <p class="pr-popup-item__hint">Touchez les &eacute;toiles pour noter</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <footer class="pr-popup__footer">
                <button type="button" id="prRatingLater" class="pr-popup__btn pr-popup__btn--ghost">Plus tard</button>
            </footer>
        </div>
    </div>
</div>
