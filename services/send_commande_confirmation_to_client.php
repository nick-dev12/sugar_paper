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
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $user_id = (int) $user_id;
    if ($user_id < 1 || $numero_commande === '') {
        return;
    }

    $montant_aff = number_format((float) $montant_total, 0, ',', ' ') . ' FCFA';
    $title = 'Commande confirmée';
    $body = "Commande #{$numero_commande} enregistrée — {$montant_aff}";

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
