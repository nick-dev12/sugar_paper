<?php
/**
 * Tâches lourdes après création produit (exécutées après envoi de la réponse HTTP).
 */

/**
 * QR code, code-barres FPL et notification abonnés — hors chemin critique utilisateur.
 *
 * @param int $produit_id
 * @param int $owner_admin
 * @param string $nom
 * @param string $statut
 */
function produit_run_deferred_post_create(int $produit_id, int $owner_admin, string $nom, string $statut): void
{
    if ($produit_id <= 0) {
        return;
    }

    require_once __DIR__ . '/../controllers/controller_produits.php';
    require_once __DIR__ . '/barcode_fpl.php';

    try {
        generer_qrcode_produit($produit_id);
    } catch (Throwable $e) {
        error_log('[produit_post_create_deferred] qrcode id=' . $produit_id . ': ' . $e->getMessage());
    }

    try {
        generer_barcode_produit_fpl($produit_id);
    } catch (Throwable $e) {
        error_log('[produit_post_create_deferred] barcode id=' . $produit_id . ': ' . $e->getMessage());
    }

    if ($owner_admin > 0) {
        require_once __DIR__ . '/../services/send_boutique_abonnement_notification.php';
        if (boutique_abonnement_produit_visible_catalogue($statut)) {
            try {
                boutique_abonnement_try_notify($owner_admin, 'nouveau_produit', $nom, $produit_id);
            } catch (Throwable $e) {
                error_log('[produit_post_create_deferred] notify id=' . $produit_id . ': ' . $e->getMessage());
            }
        }
    }
}

/**
 * Planifie QR / code-barres / push après la réponse (shutdown), sauf si géré par l'appelant.
 */
function produit_schedule_deferred_post_create(int $produit_id, int $owner_admin, string $nom, string $statut): void
{
    if (defined('PRODUIT_DEFER_POST_CREATE_EXTERNAL') && PRODUIT_DEFER_POST_CREATE_EXTERNAL) {
        return;
    }
    require_once __DIR__ . '/produit_post_create_deferred.php';
    register_shutdown_function(static function () use ($produit_id, $owner_admin, $nom, $statut) {
        produit_run_deferred_post_create($produit_id, $owner_admin, $nom, $statut);
    });
}

/**
 * Envoie la redirection puis exécute un callback (FastCGI : réponse immédiate au navigateur).
 */
function admin_redirect_then(callable $after_response, string $url): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $url, true, 303);
    header('Connection: close');
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        header('Content-Length: 0');
        flush();
    }
    try {
        $after_response();
    } catch (Throwable $e) {
        error_log('[admin_redirect_then] ' . $e->getMessage());
    }
    exit;
}
