<?php
/**
 * Helpers communs pour l'envoi de notifications (email + push)
 */

/**
 * Charge PHPMailer et le service mail si nécessaire
 */
function notifications_ensure_mail_loaded() {
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
 * Envoie un email via la file d'attente (arrière-plan) avec repli synchrone si besoin
 *
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param bool $is_html
 * @param array $meta
 * @return array{success:bool, job_id:string|null, error:string|null}
 */
function notifications_mail_send($to, $subject, $body, $is_html = true, $meta = []) {
    notifications_ensure_mail_loaded();

    $queue_path = __DIR__ . '/email_queue.php';
    if (file_exists($queue_path)) {
        require_once $queue_path;
        if (function_exists('mail_send_async')) {
            $queued = mail_send_async($to, $subject, $body, $is_html, $meta);
            if (!empty($queued['success'])) {
                return $queued;
            }
            error_log('[notifications_mail_send] file d\'attente : ' . ($queued['error'] ?? 'erreur inconnue'));
        }
    }

    if (function_exists('mail_send')) {
        $sync = mail_send($to, $subject, $body, $is_html);
        return [
            'success' => !empty($sync['success']),
            'job_id' => null,
            'error' => $sync['error'] ?? null,
        ];
    }

    return ['success' => false, 'job_id' => null, 'error' => 'Service mail indisponible'];
}

/**
 * Notifie le client après changement de statut d'une commande classique
 * @param int $commande_id
 * @param string $nouveau_statut
 * @return bool
 */
function notify_client_commande_statut_changed($commande_id, $nouveau_statut) {
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
        trim($commande['user_email'] ?? '')
    );

    return true;
}
