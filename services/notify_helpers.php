<?php
/**
 * Helpers communs pour l'envoi de notifications (email + push)
 */

/**
 * Garantit $db en portée globale (requis pour le worker notify en CLI / shutdown).
 * conn.php inclus dans une fonction ne remplit pas global $db sans cela.
 *
 * @return bool
 */
function notifications_db_bootstrap()
{
    global $db;

    if (isset($db) && $db instanceof PDO) {
        return true;
    }

    require_once __DIR__ . '/../conn/conn.php';

    return isset($db) && $db instanceof PDO;
}

/**
 * Charge PHPMailer et le service mail si nécessaire
 */
function notifications_ensure_mail_loaded()
{
    if (function_exists('mail_send')) {
        return;
    }
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }
    $mail_path = __DIR__ . '/mail.php';
    if (file_exists($mail_path)) {
        require_once $mail_path;
    }
}

/**
 * Met un email en file d'attente (traité en arrière-plan par cron / worker).
 * Les notifications push restent synchrones ailleurs.
 *
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param bool $is_html
 * @param array $meta Conservé pour le job (type, numero_commande, etc.)
 * @return array{success:bool, job_id:string|null, error:string|null}
 */
function notifications_mail_send($to, $subject, $body, $is_html = true, $meta = [])
{
    require_once __DIR__ . '/email_queue.php';

    $queued = mail_send_async($to, $subject, $body, $is_html, is_array($meta) ? $meta : []);
    if (empty($queued['success'])) {
        error_log('[notifications_mail_send] file d\'attente : ' . ($queued['error'] ?? 'échec'));
    }

    return [
        'success' => !empty($queued['success']),
        'job_id' => $queued['job_id'] ?? null,
        'error' => $queued['error'] ?? null,
    ];
}

/**
 * Envoi SMTP immédiat (tests admin uniquement — ne pas utiliser en production commande)
 *
 * @return array{success:bool, job_id:string|null, error:string|null}
 */
function notifications_mail_send_sync($to, $subject, $body, $is_html = true, $meta = [])
{
    notifications_ensure_mail_loaded();

    if (!function_exists('mail_send')) {
        return ['success' => false, 'job_id' => null, 'error' => 'Service mail indisponible'];
    }

    $sync = mail_send($to, $subject, $body, $is_html);
    if (empty($sync['success'])) {
        error_log('[notifications_mail_send_sync] ' . ($sync['error'] ?? 'échec SMTP'));
    }

    return [
        'success' => !empty($sync['success']),
        'job_id' => null,
        'error' => $sync['error'] ?? null,
    ];
}

/**
 * Email unique pour les alertes nouvelles commandes (classiques et personnalisées)
 * @return string
 */
function notifications_get_commande_admin_email()
{
    $default = 'sugarpaper26@gmail.com';
    $config_path = __DIR__ . '/../config/email.php';
    if (!file_exists($config_path)) {
        return $default;
    }
    $config = require $config_path;
    if (!empty($config['commande_notification_email'])) {
        return trim((string) $config['commande_notification_email']);
    }
    if (!empty($config['contact_email'])) {
        return trim((string) $config['contact_email']);
    }
    return $default;
}

/**
 * Envoie un push FCM à un client (tokens user) et journalise le résultat.
 *
 * @param int $user_id
 * @param string $title
 * @param string $body
 * @param array<string, mixed> $data
 * @param string $context Libellé pour fcm_send.log
 * @return array{success:int,failed:int,errors:array}
 */
function notifications_send_user_push($user_id, $title, $body, array $data = [], $context = 'client')
{
    notifications_db_bootstrap();
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';

    $user_id = (int) $user_id;
    $context = trim((string) $context);
    if ($context === '') {
        $context = 'client';
    }
    $context .= ' user#' . $user_id;

    if ($user_id < 1) {
        $empty = ['success' => 0, 'failed' => 0, 'errors' => ['user_id invalide'], 'token_results' => []];
        _firebase_log_send($context, $empty);
        return $empty;
    }

    $tokens = get_fcm_tokens_by_user($user_id);
    if (empty($tokens)) {
        $empty = [
            'success' => 0,
            'failed' => 0,
            'errors' => ['aucun token FCM pour le client #' . $user_id],
            'token_results' => [],
        ];
        _firebase_log_send($context, $empty);
        return $empty;
    }

    $token_meta = get_fcm_user_token_meta($user_id);
    $result = firebase_send_notification($tokens, $title, $body, $data, $token_meta);
    _firebase_log_send($context, $result);
    return $result;
}

/**
 * Notifie le client après changement de statut d'une commande classique.
 * Par défaut : file d'attente (ne bloque pas la requête admin).
 *
 * @param int $commande_id
 * @param string $nouveau_statut
 * @param bool $immediate Si true, envoi immédiat (worker)
 * @return bool
 */
function notify_client_commande_statut_changed($commande_id, $nouveau_statut, $immediate = false)
{
    $commande_id = (int) $commande_id;
    $nouveau_statut = (string) $nouveau_statut;
    if ($commande_id < 1 || $nouveau_statut === '') {
        return false;
    }

    if (!$immediate) {
        require_once __DIR__ . '/notify_queue.php';
        $queued = notify_queue_enqueue('commande_statut', [
            'commande_id' => $commande_id,
            'nouveau_statut' => $nouveau_statut,
        ], true);
        return !empty($queued['success']);
    }

    notifications_db_bootstrap();
    require_once __DIR__ . '/../models/model_commandes_admin.php';
    require_once __DIR__ . '/send_commande_notification.php';

    $commande = get_commande_by_id($commande_id);
    if (!$commande) {
        error_log('[notify_client_commande_statut_changed] commande introuvable #' . $commande_id);
        return false;
    }

    $user_id = (int) ($commande['user_id'] ?? 0);
    if ($user_id < 1) {
        error_log('[notify_client_commande_statut_changed] pas de user_id commande #' . $commande_id);
        return false;
    }

    notifications_ensure_mail_loaded();
    send_commande_status_notification(
        $user_id,
        (string) ($commande['numero_commande'] ?? ''),
        $nouveau_statut,
        trim($commande['user_email'] ?? ''),
        $commande_id
    );

    return true;
}
