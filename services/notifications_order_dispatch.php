<?php
/**
 * Dispatch post-commande :
 * Tout (push FCM + emails) part en file d'attente — le cron / worker traite ensuite.
 * La réponse HTTP au client n'est plus bloquée par Firebase (~30s).
 */

require_once __DIR__ . '/notify_queue.php';

/**
 * Enfile les notifications après commande classique.
 *
 * @param array<string, mixed> $result Retour de process_create_commande()
 */
function notifications_dispatch_after_commande(array $result) {
    if (empty($result['success'])) {
        return;
    }

    if (!empty($result['email_data']) && is_array($result['email_data'])) {
        $d = $result['email_data'];
        notify_queue_enqueue('nouvelle_commande', [
            'numero_commande' => (string) ($d['numero_commande'] ?? $result['numero_commande'] ?? ''),
            'montant_total' => (float) ($d['montant_total'] ?? 0),
            'nombre_articles' => (int) ($d['nombre_articles'] ?? 0),
            'telephone_livraison' => (string) ($d['telephone_livraison'] ?? ''),
            'adresse_livraison' => (string) ($d['adresse_livraison'] ?? ''),
            'produits' => is_array($d['produits'] ?? null) ? $d['produits'] : [],
        ], false);
    }

    if (empty($result['is_guest']) && !empty($result['numero_commande'])) {
        $user_id = (int) ($result['user_id'] ?? 0);
        if ($user_id < 1 && session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if ($user_id < 1 && !empty($_SESSION['user_id'])) {
            $user_id = (int) $_SESSION['user_id'];
        }
        if ($user_id > 0) {
            require_once __DIR__ . '/../models/model_users.php';
            $user = get_user_by_id($user_id);
            $client_email = trim($user['email'] ?? ($_SESSION['user_email'] ?? ''));
            notify_queue_enqueue('confirmation_client', [
                'user_id' => $user_id,
                'numero_commande' => (string) $result['numero_commande'],
                'montant_total' => (float) ($result['email_data']['montant_total'] ?? 0),
                'user_email' => $client_email,
            ], false);
        }
    }

    // Déclenche le worker une seule fois (non bloquant) — le cron rattrape sinon
    notify_queue_spawn_worker();
}

/**
 * Enfile les notifications après commande personnalisée.
 *
 * @param array<string, mixed> $notify_data
 */
function notifications_dispatch_after_commande_personnalisee(array $notify_data) {
    if (empty($notify_data)) {
        return;
    }

    notify_queue_enqueue('nouvelle_cp', [
        'commande_perso_id' => (int) ($notify_data['commande_perso_id'] ?? 0),
        'nom' => (string) ($notify_data['nom'] ?? ''),
        'telephone' => (string) ($notify_data['telephone'] ?? ''),
        'description' => (string) ($notify_data['description'] ?? ''),
        'type_produit' => (string) ($notify_data['type_produit'] ?? ''),
        'quantite' => (string) ($notify_data['quantite'] ?? ''),
    ], false);

    $uid = (int) ($notify_data['user_id'] ?? 0);
    if ($uid > 0) {
        notify_queue_enqueue('confirmation_cp', [
            'user_id' => $uid,
            'commande_perso_id' => (int) ($notify_data['commande_perso_id'] ?? 0),
            'user_email' => (string) ($notify_data['user_email'] ?? ''),
        ], false);
    }

    notify_queue_spawn_worker();
}
