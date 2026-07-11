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

    $title = 'Mise à jour de votre commande';
    $body = "Commande #{$numero_commande} : {$label}";

    require_once __DIR__ . '/../includes/site_url.php';
    $base_url = get_site_base_url();
    $link = $base_url . '/user/mes-commandes.php';

    $user_id = (int) $user_id;
    if ($user_id > 0) {
        $tokens = get_fcm_tokens_by_user($user_id);
        if (!empty($tokens)) {
            firebase_send_notification($tokens, $title, $body, [
                'link' => $link,
                'commande_id' => '',
                'statut' => $nouveau_statut,
                'numero_commande' => $numero_commande,
                'tag' => 'commande-' . $numero_commande
            ]);
        }
    }

    $user_email = trim($user_email ?? '');
    if (!empty($user_email) && filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/email_queue.php';

        $sujet = "[COLObanes] Mise à jour de votre commande #{$numero_commande}";
        $body_html = '<div style="font-family: \'Poppins\', Arial, sans-serif; max-width: 600px;">';
        $body_html .= '<h2 style="color: #918a44;">Mise à jour de votre commande</h2>';
        $body_html .= '<p>Bonjour,</p>';
        $body_html .= '<p>Le statut de votre commande <strong>#' . htmlspecialchars($numero_commande) . '</strong> a été mis à jour.</p>';
        $body_html .= '<p><strong>Nouveau statut :</strong> <span style="color: #6b2f20; font-weight: 600;">' . htmlspecialchars($label) . '</span></p>';
        $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Voir mes commandes</a></p>';
        $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
        $body_html .= '<p style="font-size: 12px; color: #999;">COLObanes — marketplace Sénégal</p>';
        $body_html .= '</div>';

        mail_send_async($user_email, $sujet, $body_html, true, [
            'type' => 'commande_statut',
            'numero_commande' => $numero_commande,
            'statut' => $nouveau_statut,
        ]);
    }
}

/**
 * Notification push au client lors de la création de commande(s)
 *
 * @param int $user_id
 * @param array $numeros_commandes Liste des numéros de commande créés
 * @param float $montant_total Montant total (toutes commandes)
 * @param string $user_email Email du client pour confirmation par email
 */
function send_new_commande_confirmation_to_client($user_id, $numeros_commandes, $montant_total = 0, $user_email = '') {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || empty($numeros_commandes)) {
        return;
    }

    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $nums = array_values(array_filter(array_map('strval', $numeros_commandes)));
    $numero_affichage = count($nums) > 1 ? implode(', ', $nums) : $nums[0];
    $title = count($nums) > 1 ? 'Commandes enregistrées' : 'Commande enregistrée';
    $body = count($nums) > 1
        ? 'Vos commandes #' . $numero_affichage . ' ont bien été reçues.'
        : 'Votre commande #' . $numero_affichage . ' a bien été enregistrée.';
    if ($montant_total > 0) {
        $body .= ' Total : ' . number_format($montant_total, 0, ',', ' ') . ' FCFA.';
    }

    $base_url = get_site_base_url();
    $link = $base_url . '/user/mes-commandes.php';

    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, [
            'link' => $link,
            'numero_commande' => $numero_affichage,
            'tag' => 'nouvelle-commande-client-' . $nums[0]
        ]);
    }

    $user_email = trim($user_email ?? '');
    if ($user_email !== '' && filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/email_queue.php';

        $sujet = count($nums) > 1
            ? '[COLObanes] Vos commandes ont bien été enregistrées'
            : '[COLObanes] Votre commande #' . $numero_affichage . ' est enregistrée';

        $body_html = '<div style="font-family: \'Poppins\', Arial, sans-serif; max-width: 600px;">';
        $body_html .= '<h2 style="color: #918a44;">' . htmlspecialchars($title) . '</h2>';
        $body_html .= '<p>Bonjour,</p>';
        if (count($nums) > 1) {
            $body_html .= '<p>Vos commandes <strong>#' . htmlspecialchars($numero_affichage) . '</strong> ont bien été reçues.</p>';
        } else {
            $body_html .= '<p>Votre commande <strong>#' . htmlspecialchars($numero_affichage) . '</strong> a bien été enregistrée.</p>';
        }
        if ($montant_total > 0) {
            $body_html .= '<p><strong>Montant total :</strong> ' . number_format($montant_total, 0, ',', ' ') . ' FCFA</p>';
        }
        $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Suivre mes commandes</a></p>';
        $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
        $body_html .= '<p style="font-size: 12px; color: #999;">COLObanes — marketplace Sénégal</p>';
        $body_html .= '</div>';

        mail_send_async($user_email, $sujet, $body_html, true, [
            'type' => 'commande_creation_client',
            'numero_commande' => $numero_affichage,
        ]);
    }
}

/**
 * Notification push au vendeur lors d'une action client sur une commande (annulation, confirmation réception)
 *
 * @param int $vendeur_id
 * @param int $commande_id
 * @param string $numero_commande
 * @param string $action_label Libellé court (ex. « Annulée par le client »)
 */
function send_commande_vendeur_action_notification($vendeur_id, $commande_id, $numero_commande, $action_label) {
    $vendeur_id = (int) $vendeur_id;
    if ($vendeur_id <= 0) {
        return;
    }

    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $tokens = get_fcm_tokens_by_admin($vendeur_id);
    if (empty($tokens)) {
        return;
    }

    $commande_id = (int) $commande_id;
    $base_url = get_site_base_url();
    $link = $commande_id > 0
        ? $base_url . '/admin/commandes/details.php?id=' . $commande_id
        : $base_url . '/admin/commandes/index.php';

    $title = 'Mise à jour commande (client)';
    $body = "Commande #{$numero_commande} : {$action_label}";

    firebase_send_notification($tokens, $title, $body, [
        'link' => $link,
        'numero_commande' => $numero_commande,
        'tag' => 'commande-vendeur-' . $numero_commande
    ]);
}
