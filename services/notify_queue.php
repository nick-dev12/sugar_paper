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
if (!defined('NOTIFY_QUEUE_FAILED_DIR')) {
    define('NOTIFY_QUEUE_FAILED_DIR', NOTIFY_QUEUE_BASE_DIR . '/failed');
}
if (!defined('NOTIFY_QUEUE_LOCK_FILE')) {
    define('NOTIFY_QUEUE_LOCK_FILE', NOTIFY_QUEUE_BASE_DIR . '/worker.lock');
}
if (!defined('NOTIFY_QUEUE_MAX_ATTEMPTS')) {
    define('NOTIFY_QUEUE_MAX_ATTEMPTS', 3);
}

/**
 * @return bool
 */
function notify_queue_ensure_dirs() {
    foreach ([NOTIFY_QUEUE_BASE_DIR, NOTIFY_QUEUE_PENDING_DIR, NOTIFY_QUEUE_FAILED_DIR] as $dir) {
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
 * Enfile un job de notification.
 * Le worker / cron traite push FCM + emails ensuite (hors requête client).
 *
 * @param string $type Ex. nouvelle_commande | confirmation_client | nouvelle_cp | confirmation_cp
 * @param array $payload
 * @param bool $spawn_worker Si true, tente de démarrer le worker immédiatement
 * @return array{success:bool, job_id:string|null, error:string|null}
 */
function notify_queue_enqueue($type, array $payload, $spawn_worker = true) {
    if (!notify_queue_ensure_dirs()) {
        return ['success' => false, 'job_id' => null, 'error' => 'File notify indisponible'];
    }

    $job_id = 'nq_' . bin2hex(random_bytes(8)) . '_' . time();
    $job = [
        'id' => $job_id,
        'type' => (string) $type,
        'payload' => $payload,
        'created_at' => time(),
        'attempts' => 0,
    ];

    $path = NOTIFY_QUEUE_PENDING_DIR . '/' . $job_id . '.json';
    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($path, $json, LOCK_EX) === false) {
        return ['success' => false, 'job_id' => null, 'error' => 'Écriture job notify impossible'];
    }

    if ($spawn_worker) {
        notify_queue_spawn_worker();
    }
    return ['success' => true, 'job_id' => $job_id, 'error' => null];
}

/**
 * Lance le worker (CLI, HTTP, puis shutdown PHP en secours)
 */
function notify_queue_spawn_worker() {
    $script = realpath(dirname(__DIR__) . '/scripts/process_notify_queue.php');

    if ($script !== false && is_readable($script)) {
        require_once __DIR__ . '/email_queue.php';
        $php_bin = email_queue_resolve_php_binary();
        $cmd = escapeshellarg($php_bin) . ' ' . escapeshellarg($script);

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @pclose(@popen('start /B "" ' . $cmd . ' > NUL 2>&1', 'r'));
        } elseif (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            @exec($cmd . ' > /dev/null 2>&1 &');
        }
    }

    // Production : exec souvent désactivé → requête HTTP interne non bloquante
    require_once __DIR__ . '/notify_queue_worker.php';
    notify_queue_trigger_http_worker();

    // Secours : traiter après la réponse HTTP (si le cron / HTTP échoue)
    if (!defined('NOTIFY_QUEUE_SHUTDOWN_REGISTERED')) {
        define('NOTIFY_QUEUE_SHUTDOWN_REGISTERED', true);
        register_shutdown_function(static function () {
            if (defined('NOTIFY_QUEUE_IN_WORKER') && NOTIFY_QUEUE_IN_WORKER) {
                return;
            }
            @ignore_user_abort(true);
            @set_time_limit(180);
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            require_once __DIR__ . '/notify_queue_worker.php';
            notify_queue_process_jobs(8, 15);
        });
    }
}

/**
 * Remet un job notify en pending après échec (retry)
 *
 * @param array $job
 * @param string $reason
 * @return bool
 */
function notify_queue_requeue_job(array $job, $reason = '') {
    if (!notify_queue_ensure_dirs()) {
        return false;
    }
    $attempts = (int) ($job['attempts'] ?? 0) + 1;
    $job['attempts'] = $attempts;
    $job['last_error'] = (string) $reason;
    $job['last_attempt_at'] = time();

    $id = !empty($job['id'])
        ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $job['id'])
        : ('nq_retry_' . time());
    $job['id'] = $id;

    if ($attempts >= NOTIFY_QUEUE_MAX_ATTEMPTS) {
        $job['failed_at'] = time();
        $job['fail_reason'] = (string) $reason;
        $dest = NOTIFY_QUEUE_FAILED_DIR . '/' . $id . '.json';
        $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false && @file_put_contents($dest, $json, LOCK_EX) !== false;
    }

    $dest = NOTIFY_QUEUE_PENDING_DIR . '/' . $id . '.json';
    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && @file_put_contents($dest, $json, LOCK_EX) !== false;
}

/**
 * Remet tous les jobs notify failed en pending
 * @return int
 */
function notify_queue_retry_failed() {
    if (!notify_queue_ensure_dirs()) {
        return 0;
    }
    $n = 0;
    foreach (glob(NOTIFY_QUEUE_FAILED_DIR . '/*.json') ?: [] as $file) {
        $raw = @file_get_contents($file);
        $job = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($job)) {
            @unlink($file);
            continue;
        }
        $job['attempts'] = 0;
        unset($job['failed_at'], $job['fail_reason'], $job['last_error']);
        $id = !empty($job['id'])
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $job['id'])
            : ('nq_retry_' . time() . '_' . $n);
        $job['id'] = $id;
        $dest = NOTIFY_QUEUE_PENDING_DIR . '/' . $id . '.json';
        $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false && @file_put_contents($dest, $json, LOCK_EX) !== false) {
            @unlink($file);
            $n++;
        }
    }
    return $n;
}

/**
 * Ferme la réponse HTTP au client puis laisse le script PHP continuer (best-effort).
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
