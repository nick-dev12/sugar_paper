<?php
/**
 * Bouton « Suivre la livraison » — cellule montant (factures / commandes).
 * Variables : $suivi_row, $suivi_type ('commande'|'facture'), $suivi_id (int)
 */
if (!function_exists('livreur_admin_peut_suivre_livraison')) {
    require_once __DIR__ . '/../../models/model_livreur_tracking.php';
}
$suivi_type = isset($suivi_type) && $suivi_type === 'facture' ? 'facture' : 'commande';
$suivi_id = (int) ($suivi_id ?? 0);
$suivi_row = is_array($suivi_row ?? null) ? $suivi_row : [];
$show_suivi = livreur_admin_peut_suivre_livraison($suivi_row, $suivi_type);
$suivi_href = $show_suivi ? livreur_admin_suivi_livraison_href($suivi_id, $suivi_type) : '';
?>
<?php if ($show_suivi && $suivi_href !== ''): ?>
<a href="<?php echo htmlspecialchars($suivi_href, ENT_QUOTES, 'UTF-8'); ?>"
    class="invoice-suivi-livraison-btn invoice-suivi-livraison-btn--live"
    onclick="event.stopPropagation();"
    aria-label="Suivre la livraison en temps réel">
    <span class="invoice-suivi-livraison-btn__pulse" aria-hidden="true"></span>
    <i class="fas fa-location-arrow" aria-hidden="true"></i>
    <span class="invoice-suivi-livraison-btn__label">Suivre la livraison</span>
</a>
<?php endif; ?>
