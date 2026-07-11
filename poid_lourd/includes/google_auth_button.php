<?php
/**
 * Boutons connexion sociale (Google + Apple).
 * Variables optionnelles :
 * - $google_auth_type / $social_auth_type : auto|client|vendor
 * - $google_auth_redirect / $social_auth_redirect : URL relative après connexion
 * - $google_auth_position / $social_auth_position : top|bottom
 */
$social_auth_type = isset($social_auth_type) ? trim((string) $social_auth_type) : (isset($google_auth_type) ? trim((string) $google_auth_type) : 'auto');
if (!in_array($social_auth_type, ['auto', 'client', 'vendor'], true)) {
    $social_auth_type = 'auto';
}
$social_auth_redirect = isset($social_auth_redirect)
    ? (string) $social_auth_redirect
    : (isset($google_auth_redirect) ? (string) $google_auth_redirect : '');
$social_auth_position = (isset($social_auth_position) && $social_auth_position === 'bottom')
    ? 'bottom'
    : ((isset($google_auth_position) && $google_auth_position === 'bottom') ? 'bottom' : 'top');
$position_class = $social_auth_position === 'top' ? ' social-auth--top' : ' social-auth--bottom';
$social_auth_disabled = !empty($google_auth_disabled) || !empty($social_auth_disabled);
$social_auth_variant = isset($social_auth_variant) ? trim((string) $social_auth_variant) : '';
$is_hub_variant = ($social_auth_variant === 'hub');
$hub_class = $is_hub_variant ? ' social-auth--hub' : '';
?>
<div class="social-auth<?php echo $position_class . $hub_class; ?><?php echo $social_auth_disabled ? ' social-auth--disabled' : ''; ?>">
    <?php if ($social_auth_position === 'bottom' && !$is_hub_variant): ?>
        <div class="social-auth__divider"><span>ou</span></div>
    <?php endif; ?>

    <div class="social-auth__buttons">
        <button type="button"
            class="google-auth-btn"
            data-social-auth-type="<?php echo htmlspecialchars($social_auth_type, ENT_QUOTES, 'UTF-8'); ?>"
            data-social-auth-redirect="<?php echo htmlspecialchars($social_auth_redirect, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $social_auth_disabled ? 'disabled' : ''; ?>>
            <span class="google-auth-btn__icon" aria-hidden="true">G</span>
            <span class="social-auth-btn__label">
                <span class="social-auth-btn__label-full">Continuer avec Google</span>
                <span class="social-auth-btn__label-short" aria-hidden="true">Google</span>
            </span>
        </button>

        <button type="button"
            class="apple-auth-btn"
            data-social-auth-type="<?php echo htmlspecialchars($social_auth_type, ENT_QUOTES, 'UTF-8'); ?>"
            data-social-auth-redirect="<?php echo htmlspecialchars($social_auth_redirect, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $social_auth_disabled ? 'disabled' : ''; ?>>
            <span class="apple-auth-btn__icon" aria-hidden="true"><i class="fab fa-apple"></i></span>
            <span class="social-auth-btn__label">
                <span class="social-auth-btn__label-full">Continuer avec Apple</span>
                <span class="social-auth-btn__label-short" aria-hidden="true">Apple</span>
            </span>
        </button>
    </div>

    <?php if ($social_auth_position === 'top' && !$is_hub_variant): ?>
        <div class="social-auth__divider"><span>ou</span></div>
    <?php endif; ?>
</div>
