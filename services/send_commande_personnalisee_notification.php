<?php
/**
 * Notifications push + email pour les commandes personnalisées
 */

require_once __DIR__ . '/notify_helpers.php';

/**
 * Notifie les administrateurs d'une nouvelle demande personnalisée
 */
function send_new_commande_personnalisee_to_admin($cp_id, $nom, $telephone, $description, $type_produit = '', $quantite = '')
{
    require_once __DIR__ . '/../models/model_admin.php';
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $cp_id = (int) $cp_id;
    $nom = trim((string) $nom);
    $telephone = trim((string) $telephone);
    $snippet = trim(preg_replace('/\s+/', ' ', strip_tags((string) $description)));
    if (strlen($snippet) > 120) {
        $snippet = substr($snippet, 0, 117) . '…';
    }

    $title = 'Nouvelle commande personnalisée';
    $body = "Demande #{$cp_id} — {$nom}";
    if ($telephone !== '') {
        $body .= " ({$telephone})";
    }

    $base_url = get_site_base_url();
    $link = $base_url . '/admin/commandes-personnalisees/details.php?id=' . $cp_id;

    // Envoi individuel à chaque admin éligible (admin + rôle utilisateur) et à tous ses appareils
    firebase_send_notification_to_all_admins($title, $body, [
        'link' => $link,
        'commande_perso_id' => (string) $cp_id,
        'tag' => 'nouvelle-cp-' . $cp_id
    ]);

    notifications_ensure_mail_loaded();
    $admin_email = notifications_get_commande_admin_email();
    if ($admin_email === '' || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $sujet = "[Sugar Paper] Nouvelle commande personnalisée #{$cp_id}";
    $body_html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
    $body_html .= '<h2 style="color: #918a44;">Nouvelle demande personnalisée</h2>';
    $body_html .= '<p><strong>Demande n° :</strong> ' . htmlspecialchars((string) $cp_id) . '</p>';
    $body_html .= '<p><strong>Client :</strong> ' . htmlspecialchars($nom) . '</p>';
    if ($telephone !== '') {
        $body_html .= '<p><strong>Téléphone :</strong> ' . htmlspecialchars($telephone) . '</p>';
    }
    if ($type_produit !== '') {
        $body_html .= '<p><strong>Type :</strong> ' . htmlspecialchars($type_produit) . '</p>';
    }
    if ($quantite !== '') {
        $body_html .= '<p><strong>Quantité :</strong> ' . htmlspecialchars($quantite) . '</p>';
    }
    if ($snippet !== '') {
        $body_html .= '<p><strong>Description :</strong><br>' . nl2br(htmlspecialchars($snippet)) . '</p>';
    }
    $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Voir la demande</a></p>';
    $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
    $body_html .= '<p style="font-size: 12px; color: #999;">Sugar Paper - Produits naturels</p>';
    $body_html .= '</div>';

    notifications_mail_send($admin_email, $sujet, $body_html, true, [
        'type' => 'nouvelle_commande_personnalisee',
        'commande_perso_id' => $cp_id,
    ]);
}

/**
 * Notifie le client connecté d'une mise à jour de statut (commande personnalisée)
 */
function send_commande_personnalisee_status_notification($user_id, $cp_id, $nouveau_statut, $user_email = '')
{
    require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $user_id = (int) $user_id;
    $cp_id = (int) $cp_id;
    if ($user_id < 1 || $cp_id < 1) {
        return;
    }

    $statuts = get_statuts_commande_personnalisee();
    $label = $statuts[$nouveau_statut] ?? ucfirst(str_replace('_', ' ', $nouveau_statut));

    $title = 'Mise à jour de votre demande personnalisée';
    $body = "Demande #{$cp_id} : {$label}";

    $base_url = get_site_base_url();
    $link = $base_url . '/user/commande-personnalisee-details.php?id=' . $cp_id;

    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, [
            'link' => $link,
            'commande_perso_id' => (string) $cp_id,
            'statut' => $nouveau_statut,
            'tag' => 'cp-' . $cp_id
        ]);
    }

    $user_email = trim((string) $user_email);
    if ($user_email === '' || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $sujet = "[Sugar Paper] Mise à jour demande personnalisée #{$cp_id}";
    $body_html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
    $body_html .= '<h2 style="color: #918a44;">Mise à jour de votre demande</h2>';
    $body_html .= '<p>Bonjour,</p>';
    $body_html .= '<p>Le statut de votre demande personnalisée <strong>#' . htmlspecialchars((string) $cp_id) . '</strong> a été mis à jour.</p>';
    $body_html .= '<p><strong>Nouveau statut :</strong> <span style="color: #6b2f20; font-weight: 600;">' . htmlspecialchars($label) . '</span></p>';
    $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Voir ma demande</a></p>';
    $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
    $body_html .= '<p style="font-size: 12px; color: #999;">Sugar Paper - Produits naturels</p>';
    $body_html .= '</div>';

    notifications_mail_send($user_email, $sujet, $body_html, true, [
        'type' => 'commande_personnalisee_statut',
        'commande_perso_id' => $cp_id,
        'statut' => $nouveau_statut,
        'user_id' => $user_id,
    ]);
}

/**
 * Notifie le client qu'un prix / devis a été défini
 */
function send_commande_personnalisee_prix_notification($user_id, $cp_id, $prix, $user_email = '')
{
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $user_id = (int) $user_id;
    $cp_id = (int) $cp_id;
    if ($user_id < 1 || $cp_id < 1) {
        return;
    }

    $prix_aff = $prix !== null ? number_format((float) $prix, 0, ',', ' ') . ' FCFA' : 'À définir';

    $title = 'Devis disponible pour votre demande';
    $body = "Demande #{$cp_id} — Montant : {$prix_aff}";

    $base_url = get_site_base_url();
    $link = $base_url . '/user/commande-personnalisee-details.php?id=' . $cp_id;

    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, [
            'link' => $link,
            'commande_perso_id' => (string) $cp_id,
            'tag' => 'cp-devis-' . $cp_id
        ]);
    }

    $user_email = trim((string) $user_email);
    if ($user_email === '' || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $sujet = "[Sugar Paper] Devis pour votre demande #{$cp_id}";
    $body_html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
    $body_html .= '<h2 style="color: #918a44;">Votre devis est prêt</h2>';
    $body_html .= '<p>Bonjour,</p>';
    $body_html .= '<p>Un montant a été défini pour votre demande personnalisée <strong>#' . htmlspecialchars((string) $cp_id) . '</strong>.</p>';
    $body_html .= '<p><strong>Montant :</strong> <span style="color: #6b2f20; font-weight: 600;">' . htmlspecialchars($prix_aff) . '</span></p>';
    $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Consulter ma demande</a></p>';
    $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
    $body_html .= '<p style="font-size: 12px; color: #999;">Sugar Paper - Produits naturels</p>';
    $body_html .= '</div>';

    notifications_mail_send($user_email, $sujet, $body_html, true, [
        'type' => 'commande_personnalisee_devis',
        'commande_perso_id' => $cp_id,
        'user_id' => $user_id,
    ]);
}

/**
 * Confirmation au client connecté après envoi d'une demande personnalisée
 */
function send_commande_personnalisee_confirmation_to_client($user_id, $cp_id, $user_email = '')
{
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    require_once __DIR__ . '/../includes/site_url.php';

    $user_id = (int) $user_id;
    $cp_id = (int) $cp_id;
    if ($user_id < 1 || $cp_id < 1) {
        return;
    }

    $title = 'Demande personnalisée enregistrée';
    $body = "Votre demande #{$cp_id} a bien été reçue. Nous vous recontacterons rapidement.";

    $base_url = get_site_base_url();
    $link = $base_url . '/user/commande-personnalisee-details.php?id=' . $cp_id;

    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, [
            'link' => $link,
            'commande_perso_id' => (string) $cp_id,
            'tag' => 'cp-confirm-' . $cp_id
        ]);
    }

    $user_email = trim((string) $user_email);
    if ($user_email === '' || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $sujet = "[Sugar Paper] Confirmation demande personnalisée #{$cp_id}";
    $body_html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
    $body_html .= '<h2 style="color: #918a44;">Demande bien reçue</h2>';
    $body_html .= '<p>Bonjour,</p>';
    $body_html .= '<p>Votre demande de commande personnalisée <strong>#' . htmlspecialchars((string) $cp_id) . '</strong> a été enregistrée avec succès.</p>';
    $body_html .= '<p>Notre équipe l\'examinera et vous contactera rapidement.</p>';
    $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Suivre ma demande</a></p>';
    $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
    $body_html .= '<p style="font-size: 12px; color: #999;">Sugar Paper - Produits naturels</p>';
    $body_html .= '</div>';

    notifications_mail_send($user_email, $sujet, $body_html, true, [
        'type' => 'commande_personnalisee_confirmation',
        'commande_perso_id' => $cp_id,
        'user_id' => $user_id,
    ]);
}
