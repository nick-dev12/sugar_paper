<?php
/**
 * Bouton partage produit — ouvre la modal unifiée (platformShareModal).
 * Variable : $produit (tableau avec id, nom, prix, prix_promotion optionnels).
 */
if (empty($produit) || !is_array($produit)) {
    return;
}
$pid = (int) ($produit['id'] ?? 0);
if ($pid <= 0) {
    return;
}
if (!function_exists('product_share_abs_url')) {
    require_once __DIR__ . '/../product_share.php';
}
$share_url = product_share_abs_url($pid);
$share_msg = product_share_text_short($produit);
$share_nom = htmlspecialchars((string) ($produit['nom'] ?? 'Produit'), ENT_QUOTES, 'UTF-8');
?>
<div class="pshare" data-pshare>
    <button type="button"
        class="pshare__toggle"
        aria-label="Partager <?php echo $share_nom; ?>"
        data-share-url="<?php echo htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8'); ?>"
        data-share-text="<?php echo htmlspecialchars($share_msg, ENT_QUOTES, 'UTF-8'); ?>"
        data-share-title="<?php echo $share_nom; ?>">
        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
    </button>
</div>
