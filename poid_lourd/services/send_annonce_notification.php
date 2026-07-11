<?php
/**
 * Envoi push FCM pour une annonce plateforme
 */

require_once __DIR__ . '/../models/model_annonces.php';
require_once __DIR__ . '/../models/model_fcm.php';
require_once __DIR__ . '/firebase_push.php';

/**
 * Envoie les notifications push pour une annonce enregistrée
 *
 * @return array{ok:bool,message:string,cibles:int,envoyes:int,echecs:int}
 */
function send_annonce_push_notification($annonce_id) {
    $annonce = annonce_get_by_id($annonce_id);
    if (!$annonce) {
        return ['ok' => false, 'message' => 'Annonce introuvable.', 'cibles' => 0, 'envoyes' => 0, 'echecs' => 0];
    }

    $audience = (string) ($annonce['audience'] ?? 'client');
    $titre = (string) ($annonce['titre'] ?? 'Annonce');
    $message = (string) ($annonce['message'] ?? '');
    $lien = trim((string) ($annonce['lien_url'] ?? ''));
    if ($lien === '') {
        $lien = annonce_default_link($audience);
    }
    if (strpos($lien, '?') === false) {
        $lien .= '?id=' . (int) $annonce_id;
    }

    if ($audience === 'vendeur') {
        $tokens = get_all_fcm_tokens_vendeurs();
        $cibles = count_fcm_vendeurs_cibles();
    } else {
        $tokens = get_all_fcm_tokens_clients();
        $cibles = count_fcm_clients_cibles();
    }

    $body = mb_strlen($message) > 180 ? mb_substr($message, 0, 177) . '…' : $message;
    $data = [
        'type' => 'annonce',
        'annonce_id' => (string) (int) $annonce_id,
        'audience' => $audience,
        'link' => $lien,
        'tag' => 'annonce-' . (int) $annonce_id,
    ];

    $result = firebase_send_notification($tokens, $titre, $body, $data);
    $envoyes = (int) ($result['success'] ?? 0);
    $echecs = (int) ($result['failed'] ?? 0);

    annonce_update_push_stats((int) $annonce_id, $cibles, $envoyes, $echecs);

    $msg = $envoyes > 0
        ? "Annonce envoyée : $envoyes notification(s) push."
        : 'Annonce enregistrée. Aucun appareil abonné aux notifications pour cette audience.';

    if ($echecs > 0) {
        $msg .= " ($echecs échec(s))";
    }

    return [
        'ok' => true,
        'message' => $msg,
        'cibles' => $cibles,
        'envoyes' => $envoyes,
        'echecs' => $echecs,
    ];
}
