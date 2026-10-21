<?php
/**
 * Sélection multiple commandes — toggle et/ou barre d'actions.
 * Variables : $commandes_bulk_suffix, $commandes_bulk_archive_mode, $commandes_bulk_is_admin,
 *             $active_tab, $jours_precedents, $commandes_bulk_render ('toggle'|'bar'|'all')
 */
if (empty($commandes_bulk_enabled)) {
    return;
}
$suffix = preg_replace('/[^a-z0-9_-]/', '', (string) ($commandes_bulk_suffix ?? 'list'));
$form_id = 'commandes-bulk-form-' . $suffix;
$redirect_tab_map = [
    'a-traiter' => 'a_traiter',
    'livrees' => 'livrees',
    'annulees' => 'annulees',
];
$bulk_redirect_tab = $redirect_tab_map[$suffix] ?? ($active_tab ?? 'a_traiter');
$render = (string) ($commandes_bulk_render ?? 'all');
$show_toggle = ($render === 'toggle' || $render === 'all');
$show_bar = ($render === 'bar' || $render === 'all');

if ($show_toggle): ?>
<div class="invoice-panel-period-trigger">
    <button type="button" class="btn-secondary invoice-period-toggle commandes-select-toggle" data-commandes-panel="<?php echo htmlspecialchars($suffix); ?>" aria-pressed="false" aria-label="Sélectionner plusieurs commandes">
        <i class="fas fa-check-square" aria-hidden="true"></i>
        <span class="invoice-period-toggle__label">Sélectionner</span>
    </button>
</div>
<?php endif;

if ($show_bar): ?>
<form method="post" action="actions_groupees.php" id="<?php echo htmlspecialchars($form_id); ?>" class="invoice-bulk-bar commandes-bulk-bar" hidden>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'] ?? ''); ?>">
    <input type="hidden" name="redirect_tab" value="<?php echo htmlspecialchars($bulk_redirect_tab); ?>">
    <?php if (!empty($commandes_bulk_archive_mode)): ?>
    <input type="hidden" name="from_archives" value="1">
    <?php endif; ?>
    <?php if ($suffix === 'livrees' && !empty($jours_precedents)): ?>
    <input type="hidden" name="jours_precedents" value="1">
    <?php endif; ?>
    <label class="invoice-bulk-bar__all">
        <input type="checkbox" class="commandes-select-all" data-panel="<?php echo htmlspecialchars($suffix); ?>">
        <span>Tout (page)</span>
    </label>
    <span class="invoice-bulk-bar__count commandes-bulk-count" data-panel="<?php echo htmlspecialchars($suffix); ?>" aria-live="polite">0 sélectionnée</span>
    <div class="invoice-bulk-bar__actions">
        <?php if (!empty($commandes_bulk_archive_mode) && !empty($commandes_bulk_is_admin)): ?>
        <button type="submit" name="action_groupee" value="desarchiver" class="btn-secondary invoice-bulk-btn" data-confirm="Désarchiver les commandes sélectionnées ?">
            <i class="fas fa-box-open" aria-hidden="true"></i> <span>Désarchiver</span>
        </button>
        <?php elseif (empty($commandes_bulk_archive_mode)): ?>
        <button type="submit" name="action_groupee" value="archiver" class="btn-secondary invoice-bulk-btn" data-confirm="Archiver les commandes sélectionnées ? Elles disparaîtront de la liste active.">
            <i class="fas fa-box-archive" aria-hidden="true"></i> <span>Archiver</span>
        </button>
        <?php endif; ?>
        <?php if (!empty($commandes_bulk_is_admin)): ?>
        <button type="submit" name="action_groupee" value="supprimer" class="btn-secondary invoice-bulk-btn invoice-bulk-btn--danger" data-confirm="Supprimer définitivement les commandes sélectionnées ? Action irréversible.">
            <i class="fas fa-trash-alt" aria-hidden="true"></i> <span>Supprimer</span>
        </button>
        <?php endif; ?>
        <button type="button" class="btn-secondary invoice-bulk-btn commandes-bulk-cancel" data-panel="<?php echo htmlspecialchars($suffix); ?>">
            <i class="fas fa-times" aria-hidden="true"></i> <span>Annuler</span>
        </button>
    </div>
</form>
<?php endif; ?>
