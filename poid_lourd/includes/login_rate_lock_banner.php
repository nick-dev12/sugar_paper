<?php
/**
 * Affichage blocage connexion + compte à rebours (inclus sur choix-connexion / user/connexion).
 * Variables attendues : $login_remaining_seconds (int) > 0
 */
$__lr_sec = max(0, (int) ($login_remaining_seconds ?? 0));
if ($__lr_sec <= 0) {
    return;
}
$__lr_m = intdiv($__lr_sec, 60);
$__lr_s = $__lr_sec % 60;
$__lr_initial = sprintf('%02d:%02d', $__lr_m, $__lr_s);
$__lr_lock_min = max(1, (int) round($__lr_sec / 60));
if (function_exists('login_attempt_active_lock_duration_seconds')) {
    $__lr_dur = login_attempt_active_lock_duration_seconds();
    if ($__lr_dur > 0) {
        $__lr_lock_min = max(1, (int) round($__lr_dur / 60));
    }
}
$__lr_next_min = $__lr_lock_min * 2;
?>
<div class="login-lock-banner error-message" role="alert" aria-live="polite">
    <i class="fas fa-lock" aria-hidden="true"></i>
    <div class="login-lock-banner__body">
        <strong>Connexion bloquée temporairement</strong>
        <p class="login-lock-banner__lead">Trop de tentatives incorrectes. Les champs sont désactivés pendant <?php echo (int) $__lr_lock_min; ?> minute<?php echo $__lr_lock_min > 1 ? 's' : ''; ?>. Réessayez après le compte à rebours.</p>
        <p class="login-lock-banner__sub">En cas de nouvelles erreurs répétées, le blocage pourra atteindre <?php echo (int) $__lr_next_min; ?> minutes.</p>
        <p class="login-lock-countdown"
            id="login-lock-countdown"
            data-seconds="<?php echo $__lr_sec; ?>"
            aria-label="Temps restant avant nouvel essai"><?php echo htmlspecialchars($__lr_initial); ?></p>
    </div>
</div>
<script>
(function () {
    var el = document.getElementById('login-lock-countdown');
    if (!el) return;
    var sec = parseInt(el.getAttribute('data-seconds'), 10);
    if (!(sec > 0)) return;
    function fmt(n) {
        var m = Math.floor(n / 60);
        var s = n % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }
    function tick() {
        if (sec < 0) return;
        el.textContent = fmt(sec);
        if (sec === 0) {
            window.location.reload();
            return;
        }
        sec--;
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
