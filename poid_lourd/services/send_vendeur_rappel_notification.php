<?php
/**
 * Envoi push FCM — rappels vendeur (super admin).
 */

require_once __DIR__ . '/../models/model_vendeur_rappels.php';
require_once __DIR__ . '/../models/model_fcm.php';
require_once __DIR__ . '/firebase_push.php';

/**
 * Notifie les vendeurs (tous ou uniquement ceux concernés par le rappel).
 *
 * @param array{only_concerned?:bool,clear_snooze?:bool} $options
 * @return array{ok:bool,message:string,cibles:int,envoyes:int,echecs:int,concernes:int}
 */
function send_vendeur_rappel_push_notification($rappel_id, array $options = [])
{
    $rappel_id = (int) $rappel_id;
    $only_concerned = !empty($options['only_concerned']);
    $clear_snooze = !empty($options['clear_snooze']);

    $rappel = $rappel_id > 0 ? get_vendeur_rappel_by_id($rappel_id) : false;
    if (!$rappel) {
        return ['ok' => false, 'message' => 'Rappel introuvable.', 'cibles' => 0, 'envoyes' => 0, 'echecs' => 0, 'concernes' => 0];
    }
    if ((int) ($rappel['actif'] ?? 0) !== 1) {
        return ['ok' => true, 'message' => 'Rappel inactif — aucune notification envoyée.', 'cibles' => 0, 'envoyes' => 0, 'echecs' => 0, 'concernes' => 0];
    }

    if ($clear_snooze) {
        vendeur_rappel_republish_clear_snoozes($rappel_id);
    }

    $concerned_ids = vendeur_rappel_list_concerned_admin_ids($rappel_id);
    $concernes = count($concerned_ids);

    if ($only_concerned || $clear_snooze) {
        $tokens = [];
        foreach ($concerned_ids as $admin_id) {
            $tokens = array_merge($tokens, get_fcm_tokens_by_admin($admin_id));
        }
        $tokens = array_values(array_unique(array_filter($tokens)));
        $cibles = $concernes;
    } else {
        $tokens = get_all_fcm_tokens_vendeurs();
        $cibles = count_fcm_vendeurs_cibles();
    }

    $titre = trim((string) ($rappel['titre'] ?? 'Rappel boutique'));
    if ($titre === '') {
        $titre = 'Rappel boutique';
    }
    $message = trim((string) ($rappel['message'] ?? ''));
    $body = $message !== '' ? $message : 'Une action est attendue sur votre tableau de bord.';
    if (mb_strlen($body) > 180) {
        $body = mb_substr($body, 0, 177) . '…';
    }

    $data = [
        'type' => 'vendeur_rappel',
        'rappel_id' => (string) $rappel_id,
        'action_type' => (string) ($rappel['action_type'] ?? ''),
        'link' => '/admin/dashboard.php',
        'tag' => 'vendeur-rappel-' . $rappel_id,
    ];

    $result = firebase_send_notification($tokens, $titre, $body, $data);
    $envoyes = (int) ($result['success'] ?? 0);
    $echecs = (int) ($result['failed'] ?? 0);

    if ($clear_snooze) {
        if ($concernes === 0) {
            $msg = 'Aucun vendeur concerné par ce rappel (action déjà réalisée partout).';
        } elseif ($envoyes > 0) {
            $msg = "Rappel republié : {$envoyes} notification(s) envoyée(s) à {$concernes} vendeur(s) concerné(s). La popup réapparaîtra sur leur tableau de bord.";
        } else {
            $msg = "Rappel republié pour {$concernes} vendeur(s) concerné(s), mais aucun appareil push n'a reçu la notification.";
        }
    } elseif ($envoyes > 0) {
        $msg = $only_concerned
            ? "Notification push envoyée à {$envoyes} appareil(s) ({$concernes} vendeur(s) concerné(s))."
            : "Notification push envoyée à {$envoyes} appareil(s) vendeur.";
    } elseif ($only_concerned && $concernes === 0) {
        $msg = 'Rappel enregistré. Aucun vendeur concerné pour le moment.';
    } elseif ($cibles > 0) {
        $msg = 'Rappel enregistré. Aucun envoi push réussi.';
    } else {
        $msg = 'Rappel enregistré. Aucun vendeur abonné aux notifications push.';
    }

    if ($echecs > 0) {
        $msg .= " ({$echecs} échec(s))";
    }

    return [
        'ok' => true,
        'message' => $msg,
        'cibles' => $cibles,
        'envoyes' => $envoyes,
        'echecs' => $echecs,
        'concernes' => $concernes,
    ];
}

/**
 * Republie un rappel : réinitialise le snooze et notifie les vendeurs concernés.
 */
function republish_vendeur_rappel_notification($rappel_id)
{
    return send_vendeur_rappel_push_notification($rappel_id, [
        'only_concerned' => true,
        'clear_snooze' => true,
    ]);
}
