<?php
/**
 * Worker cron — traite les files email (+ notify legacy si besoin)
 *
 * Usage CLI :
 *   php scripts/process_queues_cron.php
 *   php scripts/process_queues_cron.php 40
 *
 * À planifier toutes les 1–2 minutes sur le serveur (Webuzo / cPanel).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only — utilisez /api/process_queues.php?key=SECRET pour HTTP');
}

@set_time_limit(120);
ignore_user_abort(true);

require_once dirname(__DIR__) . '/services/notify_queue_worker.php';

$email_limit = 40;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $email_limit = max(1, min(100, (int) $argv[1]));
}

// Notify legacy (anciens jobs) + emails async
$result = notify_queue_process_jobs(10, $email_limit);

echo json_encode([
    'ok' => true,
    'at' => date('c'),
    'notify' => $result['notify'],
    'email' => $result['email'],
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
