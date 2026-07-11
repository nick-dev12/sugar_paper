<?php
/**
 * Carte résumé demande certification — Super Admin (liste)
 * Variables : $d (array)
 */
if (empty($d) || !is_array($d)) {
    return;
}
$nv = (string) ($d['niveau'] ?? 'standard');
$st_dem = (string) ($d['statut'] ?? '');
$demande_id = (int) ($d['id'] ?? 0);
?>
<article class="sa-cert-summary sa-cert-summary--<?php echo htmlspecialchars($st_dem, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="sa-cert-summary__top">
        <div class="sa-cert-summary__main">
            <h2 class="sa-cert-summary__title"><?php echo htmlspecialchars((string) ($d['boutique_nom'] ?? 'Boutique')); ?></h2>
            <p class="sa-cert-summary__meta">
                <?php echo htmlspecialchars(trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''))); ?>
                · <?php echo htmlspecialchars((string) ($d['telephone'] ?? '—')); ?>
            </p>
            <p class="sa-cert-summary__date">
                <i class="fas fa-calendar-plus"></i>
                <?php echo date('d/m/Y à H:i', strtotime((string) ($d['date_creation'] ?? 'now'))); ?>
            </p>
        </div>
        <div class="sa-cert-summary__badges">
            <?php $cert_niveau = $nv; $cert_size = 'sm'; require dirname(__DIR__, 3) . '/includes/partials/vendeur_certification_badge.php'; ?>
            <span class="sa-cert-statut sa-cert-statut--<?php echo htmlspecialchars($st_dem, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(vendeur_certification_statut_label($st_dem)); ?>
            </span>
        </div>
    </div>
    <div class="sa-cert-summary__keys">
        <?php if (!empty($d['boutique_region'])): ?>
            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(senegal_region_label((string) $d['boutique_region'])); ?></span>
        <?php endif; ?>
        <?php if (!empty($d['email'])): ?>
            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars((string) $d['email']); ?></span>
        <?php endif; ?>
        <?php if (!empty($d['boutique_slug'])): ?>
            <span><i class="fas fa-link"></i> <?php echo htmlspecialchars((string) $d['boutique_slug']); ?></span>
        <?php endif; ?>
    </div>
    <?php if ($st_dem === 'refusee' && !empty($d['motif_refus'])): ?>
        <p class="sa-cert-summary__refus"><i class="fas fa-circle-xmark"></i> <?php echo htmlspecialchars(mb_strimwidth((string) $d['motif_refus'], 0, 120, '…')); ?></p>
    <?php endif; ?>
    <div class="sa-cert-summary__actions">
        <?php if ($st_dem === 'en_attente'): ?>
            <a href="traiter.php?id=<?php echo $demande_id; ?>" class="sa-cert-btn sa-cert-btn--primary">
                <i class="fas fa-clipboard-check"></i> Traiter la demande
            </a>
        <?php else: ?>
            <a href="traiter.php?id=<?php echo $demande_id; ?>&amp;vue=lecture" class="sa-cert-btn sa-cert-btn--ghost">
                <i class="fas fa-eye"></i> Voir le détail
            </a>
        <?php endif; ?>
    </div>
</article>
