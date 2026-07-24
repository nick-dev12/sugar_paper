<?php
/**
 * Dispatch post-commande :
 * - Push FCM : synchrone (premier plan)
 * - Emails SMTP : file d'attente async (cron / worker)
 */

/**
 * Push immédiat + emails mis en file après commande classique.
 *
 * @param array<string, mixed> $result Retour de process_create_commande()
 */
function notifications_dispatch_after_commande(array $result) {
    if (empty($result['success'])) {
        return;
    }

    // Push FCM uniquement — les emails partent en file async
    @set_time_limit(45);

    if (!empty($result['email_data']) && is_array($result['email_data'])) {
        $d = $result['email_data'];
        require_once __DIR__ . '/send_new_commande_to_admin.php';
        send_new_commande_to_admin(
            (string) ($d['numero_commande'] ?? $result['numero_commande'] ?? ''),
            (float) ($d['montant_total'] ?? 0),
            (int) ($d['nombre_articles'] ?? 0),
            (string) ($d['telephone_livraison'] ?? ''),
            (string) ($d['adresse_livraison'] ?? ''),
            is_array($d['produits'] ?? null) ? $d['produits'] : []
        );
    }

    if (empty($result['is_guest']) && !empty($result['numero_commande'])) {
        $user_id = 0;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            $user_id = (int) $_SESSION['user_id'];
        }
        if ($user_id > 0) {
            require_once __DIR__ . '/../models/model_users.php';
            require_once __DIR__ . '/send_commande_confirmation_to_client.php';
            $user = get_user_by_id($user_id);
            $client_email = trim($user['email'] ?? ($_SESSION['user_email'] ?? ''));
            send_new_commande_confirmation_to_client(
                $user_id,
                (string) $result['numero_commande'],
                (float) ($result['email_data']['montant_total'] ?? 0),
                $client_email
            );
        }
    }
}

/**
 * Push immédiat + emails mis en file après commande personnalisée.
 *
 * @param array<string, mixed> $notify_data
 */
function notifications_dispatch_after_commande_personnalisee(array $notify_data) {
    if (empty($notify_data)) {
        return;
    }

    @set_time_limit(45);

    require_once __DIR__ . '/send_commande_personnalisee_notification.php';

    send_new_commande_personnalisee_to_admin(
        (int) ($notify_data['commande_perso_id'] ?? 0),
        (string) ($notify_data['nom'] ?? ''),
        (string) ($notify_data['telephone'] ?? ''),
        (string) ($notify_data['description'] ?? ''),
        (string) ($notify_data['type_produit'] ?? ''),
        (string) ($notify_data['quantite'] ?? '')
    );

    $uid = (int) ($notify_data['user_id'] ?? 0);
    if ($uid > 0) {
        send_commande_personnalisee_confirmation_to_client(
            $uid,
            (int) ($notify_data['commande_perso_id'] ?? 0),
            (string) ($notify_data['user_email'] ?? '')
        );
    }
}
