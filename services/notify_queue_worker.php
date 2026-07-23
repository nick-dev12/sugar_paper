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
    $result = [
        'notify' => ['processed' => 0, 'errors' => []],
        'email' => ['processed' => 0, 'sent' => 0, 'failed' => 0],
    ];

    if (!notify_queue_ensure_dirs()) {
        $result['notify']['errors'][] = 'Dossiers notify indisponibles';
        return $result;
    }

    $files = glob(NOTIFY_QUEUE_PENDING_DIR . '/*.json') ?: [];
    usort($files, static function ($a, $b) {
        return filemtime($a) <=> filemtime($b);
    });

    foreach ($files as $file) {
        if ($result['notify']['processed'] >= $notify_limit) {
            break;
        }

        $raw = @file_get_contents($file);
        @unlink($file);
        if ($raw === false || $raw === '') {
            continue;
        }

        $job = json_decode($raw, true);
        if (!is_array($job) || empty($job['type'])) {
            continue;
        }

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
            $result['notify']['errors'][] = $type . ': ' . $e->getMessage();
            error_log('[notify_queue_process_jobs] ' . $e->getMessage());
        }
    }

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
    $host = parse_url($base, PHP_URL_HOST);
    $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
    $port = parse_url($base, PHP_URL_PORT);
    if ($port === null) {
        $port = ($scheme === 'https') ? 443 : 80;
    }

    $errno = 0;
    $errstr = '';
    $fp = @fsockopen(
        ($scheme === 'https' ? 'ssl://' : '') . $host,
        (int) $port,
        $errno,
        $errstr,
        3
    );
    if (!$fp) {
        error_log('[notify_queue_trigger_http_worker] fsockopen failed: ' . $errstr);
        return false;
    }

    $out = "GET {$url_path} HTTP/1.1\r\n";
    $out .= "Host: {$host}\r\n";
    $out .= "Connection: Close\r\n\r\n";
    @fwrite($fp, $out);
    @fclose($fp);
    return true;
}
