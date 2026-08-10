<?php
/**
 * Cellule montant commande (liste admin) — statut masqué si suivi GPS actif.
 * Variables : $commande (array)
 */
if (!function_exists('livreur_admin_peut_suivre_livraison')) {
    require_once __DIR__ . '/../../models/model_livreur_tracking.php';
}

$montant_txt = number_format((float) ($commande['montant_total'] ?? 0), 0, ',', ' ');
$date_aff = !empty($commande['date_commande'])
    ? date('d/m/Y H:i', strtotime($commande['date_commande']))
    : '—';
$statut_cmd = (string) ($commande['statut'] ?? '');
$statut_label = function_exists('commandes_statut_label')
    ? commandes_statut_label($statut_cmd)
    : ucfirst(str_replace('_', ' ', $statut_cmd));
$show_suivi = livreur_admin_peut_suivre_livraison($commande, 'commande');
?>
<div class="invoice-montant-cell<?php echo $show_suivi ? ' invoice-montant-cell--suivi' : ''; ?>">
    <?php if ($show_suivi): ?>
    <div class="invoice-montant-cell__meta">
        <span class="invoice-cell-primary"><?php echo $montant_txt; ?> FCFA</span>
        <span class="invoice-cell-sub invoice-cell-sub--date"><?php echo htmlspecialchars($date_aff); ?></span>
    </div>
    <?php
    $suivi_row = $commande;
    $suivi_type = 'commande';
    $suivi_id = (int) ($commande['id'] ?? 0);
    include __DIR__ . '/invoice_suivi_livraison_cell.php';
    ?>
    <?php else: ?>
    <span class="invoice-cell-primary"><?php echo $montant_txt; ?> FCFA</span>
    <span class="invoice-date-statut-line">
        <span class="invoice-cell-sub"><?php echo htmlspecialchars($date_aff); ?></span>
        <span class="invoice-row-statut invoice-row-statut--inline commande-statut statut-<?php echo htmlspecialchars($statut_cmd); ?>"><?php echo htmlspecialchars($statut_label); ?></span>
    </span>
    <?php endif; ?>
</div>
