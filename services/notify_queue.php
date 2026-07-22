<?php
/**
 * File d'attente pour notifications post-commande (push + emails)
 * Évite de bloquer la réponse HTTP (surtout sous Apache/WAMP sans fastcgi_finish_request)
 */

if (!defined('NOTIFY_QUEUE_BASE_DIR')) {
    define('NOTIFY_QUEUE_BASE_DIR', dirname(__DIR__) . '/storage/notify_queue');
}
if (!defined('NOTIFY_QUEUE_PENDING_DIR')) {
    define('NOTIFY_QUEUE_PENDING_DIR', NOTIFY_QUEUE_BASE_DIR . '/pending');
}

/**
 * @return bool
 */
function notify_queue_ensure_dirs() {
    foreach ([NOTIFY_QUEUE_BASE_DIR, NOTIFY_QUEUE_PENDING_DIR] as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('[notify_queue] Impossible de créer : ' . $dir);
            return false;
        }
    }
    return true;
}

/**
 * Enfile un job de notification et lance le worker CLI (non bloquant).
 *
 * @param string $type Ex. nouvelle_commande | confirmation_client | nouvelle_cp | confirmation_cp
 * @param array $payload
 * @return array{success:bool, job_id:string|null, error:string|null}
 */
function notify_queue_enqueue($type, array $payload) {
    if (!notify_queue_ensure_dirs()) {
        return ['success' => false, 'job_id' => null, 'error' => 'File notify indisponible'];
    }

    $job_id = 'nq_' . bin2hex(random_bytes(8)) . '_' . time();
    $job = [
        'id' => $job_id,
        'type' => (string) $type,
        'payload' => $payload,
        'created_at' => time(),
    ];

    $path = NOTIFY_QUEUE_PENDING_DIR . '/' . $job_id . '.json';
    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($path, $json, LOCK_EX) === false) {
        return ['success' => false, 'job_id' => null, 'error' => 'Écriture job notify impossible'];
    }

    notify_queue_spawn_worker();
    return ['success' => true, 'job_id' => $job_id, 'error' => null];
}

/**
 * Lance scripts/process_notify_queue.php en arrière-plan
 */
function notify_queue_spawn_worker() {
    $script = realpath(dirname(__DIR__) . '/scripts/process_notify_queue.php');
    if ($script === false || !is_readable($script)) {
        return;
    }

    require_once __DIR__ . '/email_queue.php';
    $php_bin = email_queue_resolve_php_binary();
    $cmd = escapeshellarg($php_bin) . ' ' . escapeshellarg($script);

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // start /B détache vraiment le processus sous Windows
        @pclose(@popen('start /B "" ' . $cmd . ' > NUL 2>&1', 'r'));
        return;
    }

    @exec($cmd . ' > /dev/null 2>&1 &');
}

/**
 * Ferme la réponse HTTP au client puis laisse le script PHP continuer (best-effort).
 * Sous Apache/WAMP, fastcgi_finish_request n'existe souvent pas.
 */
function notifications_close_http_response() {
    ignore_user_abort(true);
    @set_time_limit(120);

    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    while (ob_get_level() > 0) {
        @ob_end_flush();
    }

    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
        return;
    }

    if (!headers_sent()) {
        header('Connection: close');
    }
    @flush();
}
