<?php
/**
 * Helpers communs pour l'envoi de notifications (email + push)
 */

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
 * Notifie le client après changement de statut d'une commande classique
 * @param int $commande_id
 * @param string $nouveau_statut
 * @return bool
 */
function notify_client_commande_statut_changed($commande_id, $nouveau_statut)
{
    require_once __DIR__ . '/../models/model_commandes_admin.php';
    require_once __DIR__ . '/send_commande_notification.php';

    $commande = get_commande_by_id((int) $commande_id);
    if (!$commande) {
        return false;
    }

    $user_id = (int) ($commande['user_id'] ?? 0);
    if ($user_id < 1) {
        return false;
    }

    notifications_ensure_mail_loaded();
    send_commande_status_notification(
        $user_id,
        (string) ($commande['numero_commande'] ?? ''),
        $nouveau_statut,
        trim($commande['user_email'] ?? ''),
        (int) $commande_id
    );

    return true;
}
