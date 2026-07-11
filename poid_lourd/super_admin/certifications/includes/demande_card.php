<?php
/**
 * Carte demande certification — Super Admin
 * Variables : $d (array), $sa_cert_show_actions (bool)
 */
if (empty($d) || !is_array($d)) {
    return;
}
$sa_cert_show_actions = !empty($sa_cert_show_actions);
$nv = (string) ($d['niveau'] ?? 'standard');
$st_dem = (string) ($d['statut'] ?? '');
?>
<article class="sa-cert-item sa-cert-item--<?php echo htmlspecialchars($st_dem, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="sa-cert-item__head">
        <div class="sa-cert-item__main">
            <h2 class="sa-cert-item__title"><?php echo htmlspecialchars((string) ($d['boutique_nom'] ?? 'Boutique')); ?></h2>
            <p class="sa-cert-item__meta">
                <?php echo htmlspecialchars(trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''))); ?>
                · <?php echo htmlspecialchars((string) ($d['email'] ?? '')); ?>
            </p>
            <p class="sa-cert-item__dates">
                <span><i class="fas fa-calendar-plus"></i> Demandé le <?php echo date('d/m/Y à H:i', strtotime((string) ($d['date_creation'] ?? 'now'))); ?></span>
                <?php if (!empty($d['date_traitement'])): ?>
                    <span><i class="fas fa-calendar-check"></i> Traité le <?php echo date('d/m/Y à H:i', strtotime((string) $d['date_traitement'])); ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="sa-cert-item__badges">
            <?php $cert_niveau = $nv; require dirname(__DIR__, 3) . '/includes/partials/vendeur_certification_badge.php'; ?>
            <span class="sa-cert-statut sa-cert-statut--<?php echo htmlspecialchars($st_dem, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(vendeur_certification_statut_label($st_dem)); ?>
            </span>
        </div>
    </div>

    <div class="sa-cert-item__grid">
        <p><strong>Téléphone</strong> <?php echo htmlspecialchars((string) ($d['telephone'] ?? '—')); ?></p>
        <?php if (!empty($d['boutique_region'])): ?>
            <p><strong>Région</strong> <?php echo htmlspecialchars(senegal_region_label((string) $d['boutique_region'])); ?></p>
        <?php endif; ?>
        <?php if (!empty($d['boutique_slug'])): ?>
            <p><strong>Slug</strong> <?php echo htmlspecialchars((string) $d['boutique_slug']); ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($d['adresse_exacte'])): ?>
        <div class="sa-cert-block">
            <strong>Adresse du local</strong>
            <p><?php echo nl2br(htmlspecialchars((string) $d['adresse_exacte'])); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($d['description_activite'])): ?>
        <div class="sa-cert-block">
            <strong>Description de l'activité</strong>
            <p><?php echo nl2br(htmlspecialchars((string) $d['description_activite'])); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($d['numero_registre'])): ?>
        <p class="sa-cert-inline"><strong><?php echo $nv === 'vip' ? 'RCCM' : 'NINEA / RC'; ?> :</strong> <?php echo htmlspecialchars((string) $d['numero_registre']); ?></p>
    <?php endif; ?>

    <?php if (!empty($d['message_demande'])): ?>
        <div class="sa-cert-block sa-cert-block--muted">
            <strong>Message du vendeur</strong>
            <p><?php echo nl2br(htmlspecialchars((string) $d['message_demande'])); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $photo_labels = [
        'photo_local_1' => 'Façade',
        'photo_local_2' => 'Intérieur',
        'photo_local_3' => 'Vue 3',
        'photo_document' => $nv === 'vip' ? 'RCCM' : 'Document',
        'photo_piece_identite' => 'Pièce d\'identité',
    ];
    $photos = [];
    foreach ($photo_labels as $pk => $plabel) {
        if (!empty($d[$pk])) {
            $photos[] = ['path' => $d[$pk], 'label' => $plabel];
        }
    }
    ?>
    <?php if (!empty($photos)): ?>
        <div class="sa-cert-photos" aria-label="Pièces jointes">
            <?php foreach ($photos as $ph): ?>
                <a class="sa-cert-photo" href="/upload/<?php echo htmlspecialchars((string) $ph['path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                    <img src="/upload/<?php echo htmlspecialchars((string) $ph['path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ph['label'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo htmlspecialchars($ph['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($st_dem === 'refusee' && !empty($d['motif_refus'])): ?>
        <div class="sa-cert-refus-box">
            <i class="fas fa-circle-xmark"></i>
            <div>
                <strong>Motif du refus</strong>
                <p><?php echo nl2br(htmlspecialchars((string) $d['motif_refus'])); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($st_dem === 'approuvee' && !empty($d['niveau_actif_admin'])): ?>
        <p class="sa-cert-inline sa-cert-inline--ok">
            <i class="fas fa-circle-check"></i>
            Niveau actif sur la boutique : <strong><?php echo htmlspecialchars(vendeur_certification_niveau_label((string) $d['niveau_actif_admin'])); ?></strong>
        </p>
    <?php endif; ?>
</article>
