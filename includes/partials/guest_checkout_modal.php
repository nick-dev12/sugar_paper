<?php
/**
 * Modal checkout invité — nom/téléphone puis PIN 4 chiffres.
 *
 * Variables optionnelles :
 * - $guest_checkout_action : add_to_panier | go_commande
 * - $guest_checkout_return_url
 * - $guest_checkout_error
 * - $guest_checkout_open_pin : bool
 */
if (!function_exists('guest_checkout_csrf_token')) {
    require_once __DIR__ . '/../guest_checkout_auth.php';
}

$guest_checkout_action = isset($guest_checkout_action) ? (string) $guest_checkout_action : 'go_commande';
$guest_checkout_return_url = isset($guest_checkout_return_url) ? (string) $guest_checkout_return_url : ($_SERVER['REQUEST_URI'] ?? '/panier.php');
$guest_checkout_error = isset($guest_checkout_error) ? (string) $guest_checkout_error : '';
$guest_checkout_open_pin = !empty($guest_checkout_open_pin);

if ($guest_checkout_error === '' && isset($_GET['guest_error'])) {
    $guest_checkout_error = trim((string) $_GET['guest_error']);
}
if (!$guest_checkout_open_pin && isset($_GET['guest_checkout']) && $_GET['guest_checkout'] === 'pin') {
    $guest_checkout_open_pin = true;
}

$guest_pending = guest_checkout_get_pending();
$guest_phone_exists = !empty($guest_pending['phone_exists']) || !empty($_SESSION['guest_checkout_phone_exists']);
$guest_csrf = guest_checkout_csrf_token();
$guest_nom_val = $guest_pending['nom'] ?? '';
$guest_tel_val = $guest_pending['telephone'] ?? '';

if ($guest_nom_val === '' && function_exists('guest_client_get')) {
    $gc = guest_client_get();
    if ($gc) {
        $guest_nom_val = $gc['nom'] ?? '';
        $guest_tel_val = $gc['telephone'] ?? '';
    }
}
?>
<link rel="stylesheet" href="/css/guest-checkout-modal.css<?php echo function_exists('asset_version_query') ? asset_version_query() : ''; ?>">

<div class="guest-info-modal" id="guest-checkout-modal" hidden aria-hidden="true" role="dialog" aria-modal="true"
    aria-labelledby="guest-checkout-modal-title"
    data-open-pin="<?php echo $guest_checkout_open_pin ? '1' : '0'; ?>"
    data-phone-exists="<?php echo $guest_phone_exists ? '1' : '0'; ?>"
    data-current-step="1">
    <div class="guest-info-modal__backdrop" id="guest-checkout-backdrop"></div>
    <div class="guest-info-modal__panel">
        <div class="guest-info-modal__brand" aria-hidden="true">Sugar Paper</div>

        <div class="guest-info-modal__steps" aria-hidden="true">
            <span class="guest-info-modal__step-dot is-active" data-step-dot="1">1</span>
            <span class="guest-info-modal__step-line"></span>
            <span class="guest-info-modal__step-dot" data-step-dot="2">2</span>
        </div>

        <h3 class="guest-info-modal__title" id="guest-checkout-modal-title">Vos coordonnées</h3>
        <p class="guest-info-modal__subtitle" id="guest-checkout-subtitle">Indiquez votre nom et votre numéro pour continuer.</p>

        <?php if ($guest_checkout_error !== ''): ?>
            <div class="guest-info-modal__error" id="guest-checkout-error"><?php echo htmlspecialchars($guest_checkout_error); ?></div>
        <?php else: ?>
            <div class="guest-info-modal__error" id="guest-checkout-error" hidden></div>
        <?php endif; ?>

        <!-- Étape 1 : préparation -->
        <form method="POST" action="/user/guest-checkout-auth.php" id="guest-checkout-form-prepare" class="guest-info-modal__step" data-step="1">
            <input type="hidden" name="guest_step" value="prepare">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($guest_csrf); ?>">
            <input type="hidden" name="checkout_action" value="<?php echo htmlspecialchars($guest_checkout_action); ?>">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($guest_checkout_return_url); ?>">

            <div class="guest-info-modal__field">
                <label for="guest-checkout-nom">Nom *</label>
                <input type="text" id="guest-checkout-nom" name="nom" autocomplete="name" required
                    placeholder="Votre nom"
                    value="<?php echo htmlspecialchars($guest_nom_val); ?>">
            </div>
            <div class="guest-info-modal__field">
                <label for="guest-checkout-telephone">Numéro de téléphone *</label>
                <input type="tel" id="guest-checkout-telephone" name="telephone" autocomplete="tel" required
                    value="<?php echo htmlspecialchars($guest_tel_val); ?>">
            </div>
        </form>

        <div class="guest-info-modal__actions" id="guest-checkout-actions-1" data-step-actions="1">
            <button type="button" class="guest-info-modal__btn guest-info-modal__btn--cancel" id="guest-checkout-cancel">Annuler</button>
            <button type="submit" form="guest-checkout-form-prepare" class="guest-info-modal__btn guest-info-modal__btn--submit">Continuer</button>
        </div>

        <!-- Étape 2 : PIN + création compte / connexion -->
        <form method="POST" action="/user/guest-checkout-auth.php" id="guest-checkout-form-auth" class="guest-info-modal__step" data-step="2" hidden>
            <input type="hidden" name="guest_step" value="auth">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($guest_csrf); ?>">
            <input type="hidden" name="checkout_action" value="<?php echo htmlspecialchars($guest_checkout_action); ?>">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($guest_checkout_return_url); ?>">
            <input type="hidden" name="nom" id="guest-checkout-nom-hidden" value="<?php echo htmlspecialchars($guest_nom_val); ?>">
            <input type="hidden" name="telephone" id="guest-checkout-telephone-hidden" value="<?php echo htmlspecialchars($guest_tel_val); ?>">
            <div id="guest-checkout-panier-fields"></div>

            <div class="guest-info-modal__existing-hint" id="guest-checkout-existing-hint" <?php echo $guest_phone_exists ? '' : 'hidden'; ?>>
                Ce numéro est déjà enregistré. Entrez votre code PIN pour vous connecter.
            </div>

            <div class="guest-info-modal__field guest-info-modal__field--pin">
                <label for="guest-checkout-pin" id="guest-checkout-pin-label"><?php echo $guest_phone_exists ? 'Code PIN (4 ou 6 chiffres) *' : 'Code PIN (4 chiffres) *'; ?></label>
                <input type="text" id="guest-checkout-pin" name="pin" inputmode="numeric" pattern="[0-9]*"
                    maxlength="<?php echo $guest_phone_exists ? 6 : 4; ?>"
                    autocomplete="<?php echo $guest_phone_exists ? 'one-time-code' : 'off'; ?>"
                    required placeholder="••••"
                    aria-describedby="guest-checkout-pin-help">
                <p class="guest-info-modal__help" id="guest-checkout-pin-help">Les chiffres restent visibles pendant la saisie.</p>
            </div>

            <label class="guest-info-modal__checkbox">
                <input type="checkbox" name="accepte_conditions" value="1" required>
                <span>J'accepte les <a href="/conditions-utilisation.php" target="_blank" rel="noopener">conditions d'utilisation</a> *</span>
            </label>
        </form>

        <div class="guest-info-modal__actions" id="guest-checkout-actions-2" data-step-actions="2" hidden>
            <button type="button" class="guest-info-modal__btn guest-info-modal__btn--back" id="guest-checkout-back">Retour</button>
            <button type="submit" form="guest-checkout-form-auth" class="guest-info-modal__btn guest-info-modal__btn--submit" id="guest-checkout-submit-pin">Valider</button>
        </div>
    </div>
</div>
