<?php
/**
 * Cellule montant facture (liste admin Invoice) — statut masqué si suivi livraison actif.
 * Variables : $facture, $montant_txt, $date_aff, $statut_facture, $statut_class
 */
if (!function_exists('livreur_admin_peut_suivre_livraison')) {
    require_once __DIR__ . '/../../models/model_livreur_tracking.php';
}

$show_suivi = livreur_bl_livraison_columns_ok()
    && livreur_admin_peut_suivre_livraison($facture, 'facture');
?>
<div class="invoice-montant-cell<?php echo $show_suivi ? ' invoice-montant-cell--suivi' : ''; ?>">
    <?php if ($show_suivi): ?>
    <div class="invoice-montant-cell__meta">
        <span class="invoice-cell-primary"><?php echo $montant_txt; ?> FCFA</span>
        <span class="invoice-cell-sub invoice-cell-sub--date"><?php echo htmlspecialchars($date_aff); ?></span>
    </div>
    <?php
    $suivi_row = $facture;
    $suivi_type = 'facture';
    $suivi_id = (int) ($facture['id'] ?? 0);
    include __DIR__ . '/invoice_suivi_livraison_cell.php';
    ?>
    <?php else: ?>
    <span class="invoice-cell-primary"><?php echo $montant_txt; ?> FCFA</span>
    <span class="invoice-date-statut-line">
        <span class="invoice-cell-sub"><?php echo htmlspecialchars($date_aff); ?></span>
        <span class="invoice-row-statut invoice-row-statut--inline commande-statut statut-<?php echo htmlspecialchars($statut_class); ?>"><?php echo htmlspecialchars($statut_facture); ?></span>
    </span>
    <?php endif; ?>
</div>
