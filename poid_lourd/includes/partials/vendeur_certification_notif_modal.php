<?php
/**
 * Popup notification certification vendeur (validation / refus)
 * Variable : $__vendeur_cert_notif (array demande)
 */
if (empty($__vendeur_cert_notif) || !is_array($__vendeur_cert_notif)) {
    return;
}
$notif = $__vendeur_cert_notif;
$notif_id = (int) ($notif['id'] ?? 0);
$notif_st = (string) ($notif['statut'] ?? '');
$notif_niveau = (string) ($notif['niveau'] ?? 'standard');
$redirect_enc = urlencode($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
$is_ok = ($notif_st === 'approuvee');
?>
<div class="vcert-notif-overlay" id="vcertNotifOverlay" role="dialog" aria-modal="true" aria-labelledby="vcertNotifTitle">
    <div class="vcert-notif-modal vcert-notif-modal--<?php echo $is_ok ? 'ok' : 'no'; ?>">
        <button type="button" class="vcert-notif-close" id="vcertNotifClose" aria-label="Fermer">
            <i class="fas fa-times"></i>
        </button>
        <div class="vcert-notif-modal__badge">
            <?php $cert_niveau = $notif_niveau; $cert_size = 'md'; require __DIR__ . '/vendeur_certification_badge.php'; ?>
        </div>
        <?php if ($is_ok): ?>
            <h2 id="vcertNotifTitle" class="vcert-notif-modal__title">Félicitations !</h2>
            <p class="vcert-notif-modal__text">
                Votre certification <strong><?php echo htmlspecialchars(vendeur_certification_niveau_label($notif_niveau), ENT_QUOTES, 'UTF-8'); ?></strong>
                est validée. Votre badge officiel est désormais visible sur votre vitrine — continuez sur cette lancée !
            </p>
        <?php else: ?>
            <h2 id="vcertNotifTitle" class="vcert-notif-modal__title">Demande refusée</h2>
            <p class="vcert-notif-modal__text">
                Votre demande <strong><?php echo htmlspecialchars(vendeur_certification_niveau_label($notif_niveau), ENT_QUOTES, 'UTF-8'); ?></strong>
                n'a pas pu être acceptée.
            </p>
            <?php if (!empty($notif['motif_refus'])): ?>
                <div class="vcert-notif-modal__motif">
                    <strong>Motif :</strong>
                    <p><?php echo nl2br(htmlspecialchars((string) $notif['motif_refus'], ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="vcert-notif-modal__actions">
            <a href="/admin/parametres/certification-suivi.php?id=<?php echo $notif_id; ?>&amp;notif_lu=1" class="vcert-notif-btn vcert-notif-btn--primary">
                <i class="fas fa-eye"></i> Voir plus
            </a>
            <a href="/admin/certification-notif-lue.php?id=<?php echo $notif_id; ?>&amp;redirect=<?php echo $redirect_enc; ?>" class="vcert-notif-btn vcert-notif-btn--ghost">
                Fermer
            </a>
        </div>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('vcertNotifOverlay');
    var closeBtn = document.getElementById('vcertNotifClose');
    if (!overlay) return;
    var closeUrl = '/admin/certification-notif-lue.php?id=<?php echo $notif_id; ?>&redirect=<?php echo $redirect_enc; ?>';
    function closeNotif() { window.location.href = closeUrl; }
    if (closeBtn) closeBtn.addEventListener('click', closeNotif);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeNotif(); });
})();
</script>
