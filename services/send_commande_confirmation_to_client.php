<?php
/**
 * Confirmation push + email au client après création d'une commande classique
 */

require_once __DIR__ . '/notify_helpers.php';

/**
 * @param int $user_id
 * @param string $numero_commande
 * @param float $montant_total
 * @param string $user_email
 */
function send_new_commande_confirmation_to_client($user_id, $numero_commande, $montant_total, $user_email = '') {
    if (!notifications_db_bootstrap()) {
        throw new RuntimeException('Connexion BDD indisponible (confirmation client)');
    }
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $user_id = (int) $user_id;
    if ($user_id < 1 || $numero_commande === '') {
        return;
    }

    $montant_aff = number_format((float) $montant_total, 0, ',', ' ') . ' FCFA';
    $title = 'Commande envoyée avec succès';
    $body = "Votre commande #{$numero_commande} a bien été envoyée — {$montant_aff}. Nous la traitons rapidement.";

    $base_url = get_site_base_url();
    $link = $base_url . '/user/mes-commandes.php';

    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, [
            'link' => $link,
            'numero_commande' => $numero_commande,
            'tag' => 'commande-confirm-' . $numero_commande
        ]);
    }

    $user_email = trim((string) $user_email);
    if (strpos($user_email, '@guest.sugarpaper.local') !== false) {
        $user_email = '';
    }
    if ($user_email === '' || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $sujet = "[Sugar Paper] Confirmation de commande #{$numero_commande}";
    $body_html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
    $body_html .= '<h2 style="color: #918a44;">Merci pour votre commande</h2>';
    $body_html .= '<p>Bonjour,</p>';
    $body_html .= '<p>Votre commande <strong>#' . htmlspecialchars($numero_commande) . '</strong> a bien été enregistrée.</p>';
    $body_html .= '<p><strong>Montant total :</strong> ' . htmlspecialchars($montant_aff) . '</p>';
    $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Suivre ma commande</a></p>';
    $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
    $body_html .= '<p style="font-size: 12px; color: #999;">Sugar Paper - Produits naturels</p>';
    $body_html .= '</div>';

    notifications_mail_send($user_email, $sujet, $body_html, true, [
        'type' => 'commande_confirmation',
        'numero_commande' => $numero_commande,
        'user_id' => $user_id,
    ]);
}

/**
 * Renvoie la confirmation push si le token FCM arrive après la création de commande
 * (checkout invité : token enregistré sur la page succès).
 *
 * @param int $user_id
 * @param int $max_age_seconds
 */
function notifications_send_recent_order_confirmation_on_token_save($user_id, $max_age_seconds = 7200) {
    $user_id = (int) $user_id;
    if ($user_id < 1 || !notifications_db_bootstrap()) {
        return;
    }

    global $db;

    try {
        $stmt = $db->prepare("
            SELECT numero_commande, montant_total
            FROM commandes
            WHERE user_id = :uid
              AND date_commande >= DATE_SUB(NOW(), INTERVAL :sec SECOND)
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':sec', max(60, (int) $max_age_seconds), PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['numero_commande'])) {
            return;
        }

        $numero = (string) $row['numero_commande'];
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $session_key = 'fcm_confirm_sent_' . $numero;
        if (!empty($_SESSION[$session_key])) {
            return;
        }

        require_once __DIR__ . '/../models/model_users.php';
        $user = get_user_by_id($user_id);
        $email = trim($user['email'] ?? '');

        send_new_commande_confirmation_to_client(
            $user_id,
            $numero,
            (float) ($row['montant_total'] ?? 0),
            $email
        );

        $_SESSION[$session_key] = 1;
    } catch (PDOException $e) {
        error_log('[notifications_send_recent_order_confirmation_on_token_save] ' . $e->getMessage());
    }
}
