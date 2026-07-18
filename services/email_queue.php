<?php
/**
 * File d'attente d'emails — envoi en arrière-plan (hors requête HTTP)
 * Programmation procédurale uniquement
 */

if (!defined('EMAIL_QUEUE_BASE_DIR')) {
    define('EMAIL_QUEUE_BASE_DIR', dirname(__DIR__) . '/storage/email_queue');
}
if (!defined('EMAIL_QUEUE_PENDING_DIR')) {
    define('EMAIL_QUEUE_PENDING_DIR', EMAIL_QUEUE_BASE_DIR . '/pending');
}
if (!defined('EMAIL_QUEUE_FAILED_DIR')) {
    define('EMAIL_QUEUE_FAILED_DIR', EMAIL_QUEUE_BASE_DIR . '/failed');
}
if (!defined('EMAIL_QUEUE_LOCK_FILE')) {
    define('EMAIL_QUEUE_LOCK_FILE', EMAIL_QUEUE_BASE_DIR . '/worker.lock');
}

define('EMAIL_QUEUE_MAX_ATTEMPTS', 3);
define('EMAIL_QUEUE_BATCH_LIMIT', 30);

/**
 * @return bool
 */
function email_queue_ensure_dirs() {
    foreach ([EMAIL_QUEUE_BASE_DIR, EMAIL_QUEUE_PENDING_DIR, EMAIL_QUEUE_FAILED_DIR] as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('[email_queue] Impossible de créer le dossier : ' . $dir);
            return false;
        }
    }
    return true;
}

/**
 * Met un email en file d'attente et tente de lancer le worker CLI.
 *
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param bool $is_html
 * @param array $meta Contexte optionnel (type, numero_commande, etc.)
 * @return array{success:bool, job_id:string|null, error:string|null}
 */
function mail_send_async($to, $subject, $body, $is_html = true, $meta = []) {
    $to = trim((string) $to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'job_id' => null, 'error' => 'Adresse email invalide'];
    }

    if (!email_queue_ensure_dirs()) {
        return ['success' => false, 'job_id' => null, 'error' => 'File d\'attente indisponible'];
    }

    $job_id = 'eq_' . bin2hex(random_bytes(8)) . '_' . time();
    $job = [
        'id' => $job_id,
        'to' => $to,
        'subject' => (string) $subject,
        'body' => (string) $body,
        'is_html' => (bool) $is_html,
        'meta' => is_array($meta) ? $meta : [],
        'created_at' => time(),
        'attempts' => 0,
    ];

    $path = EMAIL_QUEUE_PENDING_DIR . '/' . $job_id . '.json';
    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['success' => false, 'job_id' => null, 'error' => 'Sérialisation JSON impossible'];
    }

    $written = @file_put_contents($path, $json, LOCK_EX);
    if ($written === false) {
        return ['success' => false, 'job_id' => null, 'error' => 'Écriture file d\'attente impossible'];
    }

    email_queue_spawn_worker();

    return ['success' => true, 'job_id' => $job_id, 'error' => null];
}

/**
 * Lance le script worker en processus séparé (non bloquant).
 */
function email_queue_spawn_worker() {
    $script = realpath(dirname(__DIR__) . '/scripts/process_email_queue.php');
    if ($script === false || !is_readable($script)) {
        return;
    }

    $php_bin = email_queue_resolve_php_binary();
    $cmd = escapeshellarg($php_bin) . ' ' . escapeshellarg($script);

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @pclose(@popen('start /B "" ' . $cmd . ' > NUL 2>&1', 'r'));
        return;
    }

    @exec($cmd . ' > /dev/null 2>&1 &');
}

/**
 * @return string
 */
function email_queue_resolve_php_binary() {
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
        return PHP_BINARY;
    }

    $ini = trim((string) ini_get('php_binary'));
    if ($ini !== '' && is_file($ini)) {
        return $ini;
    }

    return 'php';
}

/**
 * Traite les emails en attente (appel CLI ou fallback interne).
 *
 * @param int $limit Nombre max de jobs par exécution
 * @return array{processed:int, sent:int, failed:int}
 */
function email_queue_process($limit = EMAIL_QUEUE_BATCH_LIMIT) {
    $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];

    if (!email_queue_ensure_dirs()) {
        return $stats;
    }

    $lock_fp = @fopen(EMAIL_QUEUE_LOCK_FILE, 'c+');
    if ($lock_fp === false) {
        return $stats;
    }

    if (!flock($lock_fp, LOCK_EX | LOCK_NB)) {
        fclose($lock_fp);
        return $stats;
    }

    ftruncate($lock_fp, 0);
    fwrite($lock_fp, (string) getmypid());
    fflush($lock_fp);

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    require_once __DIR__ . '/mail.php';

    $files = glob(EMAIL_QUEUE_PENDING_DIR . '/*.json');
    if (!is_array($files)) {
        $files = [];
    }
    usort($files, static function ($a, $b) {
        return filemtime($a) <=> filemtime($b);
    });

    foreach ($files as $file) {
        if ($stats['processed'] >= $limit) {
            break;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            @unlink($file);
            continue;
        }

        $job = json_decode($raw, true);
        if (!is_array($job) || empty($job['to']) || empty($job['subject'])) {
            @unlink($file);
            continue;
        }

        $stats['processed']++;

        if (!function_exists('mail_send')) {
            email_queue_mark_failed($job, $file, 'Service mail indisponible');
            $stats['failed']++;
            continue;
        }

        $result = mail_send(
            (string) $job['to'],
            (string) $job['subject'],
            (string) ($job['body'] ?? ''),
            !empty($job['is_html'])
        );

        if (!empty($result['success'])) {
            @unlink($file);
            $stats['sent']++;
            continue;
        }

        $attempts = (int) ($job['attempts'] ?? 0) + 1;
        $job['attempts'] = $attempts;
        $job['last_error'] = (string) ($result['error'] ?? 'Erreur inconnue');
        $job['last_attempt_at'] = time();

        if ($attempts >= EMAIL_QUEUE_MAX_ATTEMPTS) {
            email_queue_mark_failed($job, $file, $job['last_error']);
            $stats['failed']++;
            continue;
        }

        $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            @file_put_contents($file, $json, LOCK_EX);
        }
    }

    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);

    return $stats;
}

/**
 * @param array $job
 * @param string $source_file
 * @param string $reason
 */
function email_queue_mark_failed($job, $source_file, $reason) {
    $job['failed_at'] = time();
    $job['fail_reason'] = $reason;

    $id = !empty($job['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $job['id']) : ('eq_fail_' . time());
    $dest = EMAIL_QUEUE_FAILED_DIR . '/' . $id . '.json';
    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
        @file_put_contents($dest, $json, LOCK_EX);
    }
    @unlink($source_file);
}
