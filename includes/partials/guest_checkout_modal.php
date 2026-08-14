<?php

/**

 * Modal checkout invité — nom/téléphone puis inscription/connexion automatique.

 *

 * Variables optionnelles :

 * - $guest_checkout_action : add_to_panier | go_commande

 * - $guest_checkout_return_url

 * - $guest_checkout_error

 * - $guest_checkout_open : bool — ouvrir la modal au chargement

 */

if (!function_exists('guest_checkout_csrf_token')) {

    require_once __DIR__ . '/../guest_checkout_auth.php';

}



$guest_checkout_action = isset($guest_checkout_action) ? (string) $guest_checkout_action : 'go_commande';

$guest_checkout_return_url = isset($guest_checkout_return_url) ? (string) $guest_checkout_return_url : ($_SERVER['REQUEST_URI'] ?? '/panier.php');

$guest_checkout_error = isset($guest_checkout_error) ? (string) $guest_checkout_error : '';

$guest_checkout_open = !empty($guest_checkout_open);



if ($guest_checkout_error === '' && isset($_GET['guest_error'])) {

    $guest_checkout_error = trim((string) $_GET['guest_error']);

}

if (!$guest_checkout_open && isset($_GET['guest_checkout'])) {

    $gc_param = trim((string) $_GET['guest_checkout']);

    if ($gc_param === 'open' || $gc_param === 'pin') {

        $guest_checkout_open = true;

    }

}



$guest_pending = guest_checkout_get_pending();

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

    data-open="<?php echo $guest_checkout_open ? '1' : '0'; ?>">

    <div class="guest-info-modal__backdrop" id="guest-checkout-backdrop"></div>

    <div class="guest-info-modal__panel">

        <div class="guest-info-modal__scroll">

            <div class="guest-info-modal__brand" aria-hidden="true">Sugar Paper</div>



            <h3 class="guest-info-modal__title" id="guest-checkout-modal-title">Vos coordonnées</h3>

            <p class="guest-info-modal__subtitle" id="guest-checkout-subtitle">Indiquez votre nom et votre numéro pour continuer.</p>



            <?php if ($guest_checkout_error !== ''): ?>

                <div class="guest-info-modal__error" id="guest-checkout-error"><?php echo htmlspecialchars($guest_checkout_error); ?></div>

            <?php else: ?>

                <div class="guest-info-modal__error" id="guest-checkout-error" hidden></div>

            <?php endif; ?>



            <form method="POST" action="/user/guest-checkout-auth.php" id="guest-checkout-form" class="guest-info-modal__form">

                <input type="hidden" name="guest_step" value="complete">

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($guest_csrf); ?>">

                <input type="hidden" name="checkout_action" value="<?php echo htmlspecialchars($guest_checkout_action); ?>">

                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($guest_checkout_return_url); ?>">

                <div id="guest-checkout-panier-fields"></div>



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



                <p class="guest-info-modal__legal">
                    En continuant, vous acceptez les
                    <a href="/conditions-utilisation.php" target="_blank" rel="noopener">conditions d'utilisation</a>.
                </p>

            </form>

        </div>



        <div class="guest-info-modal__actions" id="guest-checkout-actions">

            <button type="button" class="guest-info-modal__btn guest-info-modal__btn--cancel" id="guest-checkout-cancel">Annuler</button>

            <button type="submit" form="guest-checkout-form" class="guest-info-modal__btn guest-info-modal__btn--submit" id="guest-checkout-submit">Continuer</button>

        </div>

    </div>

</div>


