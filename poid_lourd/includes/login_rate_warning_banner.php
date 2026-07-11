<?php
/**
 * Avertissement tentatives restantes (à partir du 4e échec).
 * Variables attendues : $login_show_warning (bool), optionnel $login_remaining_attempts (int)
 */
if (empty($login_show_warning) || !empty($login_locked)) {
    return;
}
$__lr_attempts = isset($login_remaining_attempts)
    ? max(0, (int) $login_remaining_attempts)
    : login_attempt_remaining_before_lock();
if ($__lr_attempts <= 0) {
    return;
}
$__lr_label = $__lr_attempts > 1 ? 'tentatives' : 'tentative';
?>
<div class="login-warn-banner" role="status" aria-live="polite">
    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
    <div class="login-warn-banner__body">
        <strong>Tentatives restantes</strong>
        <p>Il vous reste <strong><?php echo (int) $__lr_attempts; ?></strong> <?php echo $__lr_label; ?> avant un blocage temporaire de la connexion.</p>
    </div>
</div>
