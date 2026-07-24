<?php
/**
 * Envoie une notification push et un email au client lors du changement de statut de commande
 * @param int $user_id ID du client
 * @param string $numero_commande Numéro de la commande
 * @param string $nouveau_statut Statut mis à jour
 * @param string $user_email Email du client (celui de son compte) pour l'envoi de l'email
 * @return void
 */
function send_commande_status_notification($user_id, $numero_commande, $nouveau_statut, $user_email = '') {
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/notify_helpers.php';

    $statut_labels = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'prise_en_charge' => 'Prise en charge',
        'en_preparation' => 'En préparation',
        'livraison_en_cours' => 'Livraison en cours',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'paye' => 'Payée',
        'annulee' => 'Annulée'
    ];

    $label = $statut_labels[$nouveau_statut] ?? ucfirst(str_replace('_', ' ', $nouveau_statut));

    // Messages push spécifiques (plus clairs pour le client)
    if ($nouveau_statut === 'livraison_en_cours') {
        $title = 'Livreur en route';
        $body = "Votre commande #{$numero_commande} est en cours de livraison.";
    } elseif ($nouveau_statut === 'livree') {
        $title = 'Livraison terminée';
        $body = "Votre commande #{$numero_commande} a été livrée. Bon appétit !";
    } elseif ($nouveau_statut === 'en_preparation') {
        $title = 'Commande en préparation';
        $body = "Votre commande #{$numero_commande} est en préparation.";
    } elseif ($nouveau_statut === 'prise_en_charge' || $nouveau_statut === 'confirmee') {
        $title = 'Commande confirmée';
        $body = "Votre commande #{$numero_commande} a été confirmée.";
    } elseif ($nouveau_statut === 'annulee') {
        $title = 'Commande annulée';
        $body = "Votre commande #{$numero_commande} a été annulée.";
    } else {
        $title = 'Mise à jour de votre commande';
        $body = "Commande #{$numero_commande} : {$label}";
    }

    require_once __DIR__ . '/../includes/site_url.php';
    $base_url = get_site_base_url();
    $link = $base_url . '/user/mes-commandes.php';

    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, [
            'link' => $link,
            'commande_id' => '',
            'statut' => $nouveau_statut,
            'numero_commande' => $numero_commande,
            'tag' => 'commande-' . $numero_commande . '-' . $nouveau_statut
        ]);
    }

    $user_email = trim($user_email ?? '');
    if (!empty($user_email) && filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $sujet = "[Sugar Paper] {$title} — #{$numero_commande}";
        $body_html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
        $body_html .= '<h2 style="color: #e5488a;">' . htmlspecialchars($title) . '</h2>';
        $body_html .= '<p>Bonjour,</p>';
        $body_html .= '<p>' . htmlspecialchars($body) . '</p>';
        $body_html .= '<p><strong>Statut :</strong> <span style="color: #6b2f20; font-weight: 600;">' . htmlspecialchars($label) . '</span></p>';
        $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #e5488a; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Voir mes commandes</a></p>';
        $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
        $body_html .= '<p style="font-size: 12px; color: #999;">Sugar Paper</p>';
        $body_html .= '</div>';

        notifications_mail_send($user_email, $sujet, $body_html, true, [
            'type' => 'commande_statut',
            'numero_commande' => $numero_commande,
            'statut' => $nouveau_statut,
            'user_id' => (int) $user_id,
        ]);
    }
}

/**
 * Push dédié « Livreur en route » (démarrage livraison), même si le statut
 * était déjà livraison_en_cours (ex. après prise en charge).
 *
 * @param int $commande_id
 * @return bool
 */
function notify_client_livreur_en_route($commande_id) {
    require_once __DIR__ . '/../models/model_commandes_admin.php';
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $commande_id = (int) $commande_id;
    if ($commande_id < 1) {
        return false;
    }

    $commande = get_commande_by_id($commande_id);
    if (!$commande) {
        return false;
    }

    $user_id = (int) ($commande['user_id'] ?? 0);
    if ($user_id < 1) {
        return false;
    }

    $numero = (string) ($commande['numero_commande'] ?? $commande_id);
    $title = 'Livreur en route';
    $body = "Votre commande #{$numero} est en cours de livraison. Suivez-la depuis votre espace client.";
    $link = rtrim(get_site_base_url(), '/') . '/user/mes-commandes.php';

    $tokens = get_fcm_tokens_by_user($user_id);
    if (empty($tokens)) {
        return false;
    }

    $result = firebase_send_notification($tokens, $title, $body, [
        'link' => $link,
        'statut' => 'livraison_en_cours',
        'numero_commande' => $numero,
        'tag' => 'livreur-en-route-' . $numero,
    ]);

    return ((int) ($result['success'] ?? 0)) > 0;
}

/**
 * Push dédié « Livraison terminée »
 *
 * @param int $commande_id
 * @return bool
 */
function notify_client_livraison_terminee($commande_id) {
    require_once __DIR__ . '/../models/model_commandes_admin.php';
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $commande_id = (int) $commande_id;
    if ($commande_id < 1) {
        return false;
    }

    $commande = get_commande_by_id($commande_id);
    if (!$commande) {
        return false;
    }

    $user_id = (int) ($commande['user_id'] ?? 0);
    if ($user_id < 1) {
        return false;
    }

    $numero = (string) ($commande['numero_commande'] ?? $commande_id);
    $title = 'Livraison terminée';
    $body = "Votre commande #{$numero} a été livrée. Merci pour votre confiance !";
    $link = rtrim(get_site_base_url(), '/') . '/user/mes-commandes.php';

    $tokens = get_fcm_tokens_by_user($user_id);
    if (empty($tokens)) {
        return false;
    }

    $result = firebase_send_notification($tokens, $title, $body, [
        'link' => $link,
        'statut' => 'livree',
        'numero_commande' => $numero,
        'tag' => 'livraison-terminee-' . $numero,
    ]);

    return ((int) ($result['success'] ?? 0)) > 0;
}
