<?php
/**
 * Worker CLI — traite la file d'attente d'emails
 * Usage : php scripts/process_email_queue.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/services/email_queue.php';

$limit = EMAIL_QUEUE_BATCH_LIMIT;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $limit = max(1, min(100, (int) $argv[1]));
}

$stats = email_queue_process($limit);

echo json_encode($stats, JSON_UNESCAPED_UNICODE) . PHP_EOL;
