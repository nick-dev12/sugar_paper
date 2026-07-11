<?php
/**
 * Actions blocage / déblocage produit — Super Admin.
 * Variables : $produit, $vendeur_id, $csrf, $return_to ('detail'|'produit')
 */
if (empty($produit) || !is_array($produit) || empty($moderation_ok)) {
    return;
}
$pid = (int) ($produit['id'] ?? 0);
$vendeur_id = (int) ($vendeur_id ?? 0);
$pst = (string) ($produit['statut'] ?? '');
$is_bloque = ($pst === 'bloque');
$return_to = isset($return_to) ? (string) $return_to : 'detail';
if (!function_exists('super_admin_csrf_token')) {
    require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';
}
$csrf_token = (isset($csrf) && is_string($csrf) && $csrf !== '')
    ? $csrf
    : super_admin_csrf_token();
?>
<div class="sa-prod-mod-actions">
    <?php if ($is_bloque): ?>
        <form method="post" action="toggle-produit-bloque.php" class="sa-prod-mod-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="produit_id" value="<?php echo $pid; ?>">
            <input type="hidden" name="vendeur_id" value="<?php echo $vendeur_id; ?>">
            <input type="hidden" name="action" value="debloquer">
            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="sa-bd-produit-btn sa-bd-produit-btn--ok"><i class="fas fa-unlock"></i> Débloquer le produit</button>
        </form>
    <?php else: ?>
        <details class="sa-bd-bloque-form sa-bd-bloque-form--wide">
            <summary class="sa-bd-produit-btn sa-bd-produit-btn--no"><i class="fas fa-ban"></i> Bloquer ce produit</summary>
            <form method="post" action="toggle-produit-bloque.php" class="sa-bd-bloque-form__inner">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="produit_id" value="<?php echo $pid; ?>">
                <input type="hidden" name="vendeur_id" value="<?php echo $vendeur_id; ?>">
                <input type="hidden" name="action" value="bloquer">
                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Motif <span class="req">*</span></label>
                <textarea name="motif" required maxlength="500" placeholder="Raison du blocage visible par le vendeur…"></textarea>
                <fieldset>
                    <legend>Le vendeur devra modifier :</legend>
                    <label><input type="checkbox" name="champ_nom" value="1"> Nom du produit</label>
                    <label><input type="checkbox" name="champ_image" value="1" checked> Image(s) du produit</label>
                </fieldset>
                <button type="submit" class="sa-bd-produit-btn sa-bd-produit-btn--no">Confirmer le blocage</button>
            </form>
        </details>
    <?php endif; ?>
</div>
