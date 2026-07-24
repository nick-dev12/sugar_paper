<?php
/**
 * Traitement des files notify + email (CLI et HTTP)
 */

require_once __DIR__ . '/notify_queue.php';
require_once __DIR__ . '/email_queue.php';

/**
 * @param int $notify_limit
 * @param int $email_limit
 * @return array{notify:array, email:array}
 */
function notify_queue_process_jobs($notify_limit = 20, $email_limit = 30) {
    @set_time_limit(180);
    @ignore_user_abort(true);

    if (!defined('NOTIFY_QUEUE_IN_WORKER')) {
        define('NOTIFY_QUEUE_IN_WORKER', true);
    }

    $result = [
        'notify' => ['processed' => 0, 'errors' => [], 'retried' => 0],
        'email' => ['processed' => 0, 'sent' => 0, 'failed' => 0],
    ];

    if (!notify_queue_ensure_dirs()) {
        $result['notify']['errors'][] = 'Dossiers notify indisponibles';
        return $result;
    }

    // Verrou exclusif : évite double traitement (HTTP + shutdown + cron)
    $lock_fp = @fopen(NOTIFY_QUEUE_LOCK_FILE, 'c+');
    if ($lock_fp === false) {
        $result['notify']['errors'][] = 'Impossible d\'ouvrir le verrou notify';
        // On tente quand même les emails
        $result['email'] = email_queue_process($email_limit);
        return $result;
    }
    if (!flock($lock_fp, LOCK_EX | LOCK_NB)) {
        fclose($lock_fp);
        // Un autre worker tourne déjà — ne pas bloquer
        return $result;
    }

    ftruncate($lock_fp, 0);
    fwrite($lock_fp, (string) getmypid());
    fflush($lock_fp);

    $files = glob(NOTIFY_QUEUE_PENDING_DIR . '/*.json') ?: [];
    usort($files, static function ($a, $b) {
        return filemtime($a) <=> filemtime($b);
    });

    foreach ($files as $file) {
        if ($result['notify']['processed'] >= $notify_limit) {
            break;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            @unlink($file);
            continue;
        }

        $job = json_decode($raw, true);
        if (!is_array($job) || empty($job['type'])) {
            @unlink($file);
            continue;
        }

        // Retirer du pending seulement après lecture OK — en cas d'échec on ré-enfile
        @unlink($file);

        $result['notify']['processed']++;
        $type = (string) $job['type'];
        $p = is_array($job['payload'] ?? null) ? $job['payload'] : [];

        try {
            switch ($type) {
                case 'nouvelle_commande':
                    require_once __DIR__ . '/send_new_commande_to_admin.php';
                    send_new_commande_to_admin(
                        (string) ($p['numero_commande'] ?? ''),
                        (float) ($p['montant_total'] ?? 0),
                        (int) ($p['nombre_articles'] ?? 0),
                        (string) ($p['telephone_livraison'] ?? ''),
                        (string) ($p['adresse_livraison'] ?? ''),
                        is_array($p['produits'] ?? null) ? $p['produits'] : []
                    );
                    break;

                case 'confirmation_client':
                    require_once __DIR__ . '/send_commande_confirmation_to_client.php';
                    send_new_commande_confirmation_to_client(
                        (int) ($p['user_id'] ?? 0),
                        (string) ($p['numero_commande'] ?? ''),
                        (float) ($p['montant_total'] ?? 0),
                        (string) ($p['user_email'] ?? '')
                    );
                    break;

                case 'nouvelle_cp':
                    require_once __DIR__ . '/send_commande_personnalisee_notification.php';
                    send_new_commande_personnalisee_to_admin(
                        (int) ($p['commande_perso_id'] ?? 0),
                        (string) ($p['nom'] ?? ''),
                        (string) ($p['telephone'] ?? ''),
                        (string) ($p['description'] ?? ''),
                        (string) ($p['type_produit'] ?? ''),
                        (string) ($p['quantite'] ?? '')
                    );
                    break;

                case 'confirmation_cp':
                    require_once __DIR__ . '/send_commande_personnalisee_notification.php';
                    send_commande_personnalisee_confirmation_to_client(
                        (int) ($p['user_id'] ?? 0),
                        (int) ($p['commande_perso_id'] ?? 0),
                        (string) ($p['user_email'] ?? '')
                    );
                    break;

                default:
                    $result['notify']['errors'][] = 'type inconnu: ' . $type;
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $result['notify']['errors'][] = $type . ': ' . $msg;
            error_log('[notify_queue_process_jobs] ' . $msg);
            if (notify_queue_requeue_job($job, $msg)) {
                $result['notify']['retried']++;
            }
        }
    }

    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);

    $result['email'] = email_queue_process($email_limit);
    return $result;
}

/**
 * Clé secrète pour déclencher le worker via HTTP (hébergement sans exec)
 * @return string
 */
function queue_worker_get_secret() {
    $path = dirname(__DIR__) . '/storage/.queue_worker_secret';
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (is_file($path)) {
        $secret = trim((string) @file_get_contents($path));
        if ($secret !== '') {
            return $secret;
        }
    }
    $secret = bin2hex(random_bytes(24));
    @file_put_contents($path, $secret, LOCK_EX);
    return $secret;
}

/**
 * Déclenche le worker via requête HTTP non bloquante (production / exec désactivé)
 */
function notify_queue_trigger_http_worker() {
    require_once __DIR__ . '/../includes/site_url.php';

    $secret = queue_worker_get_secret();
    $base = rtrim(get_site_base_url(), '/');
    $path = get_public_root_uri_path();
    $url_path = ($path !== '' ? $path : '') . '/api/process_queues.php?key=' . urlencode($secret);
    $full_url = $base . $url_path;
    $host = parse_url($base, PHP_URL_HOST);
    $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
    $port = parse_url($base, PHP_URL_PORT);
    if ($port === null) {
        $port = ($scheme === 'https') ? 443 : 80;
    }
    if ($host === null || $host === '') {
        return false;
    }

    // 1) cURL non bloquant (meilleur sur Webuzo / HTTPS)
    if (function_exists('curl_init')) {
        $ch = curl_init($full_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => ['Connection: Close'],
        ]);
        @curl_exec($ch);
        @curl_close($ch);
        return true;
    }

    // 2) Fallback fsockopen / stream_socket_client
    $errno = 0;
    $errstr = '';
    $remote = ($scheme === 'https' ? 'ssl://' : '') . $host . ':' . (int) $port;
    $ctx = null;
    if ($scheme === 'https') {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
    }
    $fp = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        1.0,
        STREAM_CLIENT_CONNECT,
        $ctx
    );
    if (!$fp) {
        return false;
    }

    stream_set_timeout($fp, 1);
    $out = "GET {$url_path} HTTP/1.1\r\n";
    $out .= "Host: {$host}\r\n";
    $out .= "Connection: Close\r\n\r\n";
    @fwrite($fp, $out);
    @fclose($fp);
    return true;
}
