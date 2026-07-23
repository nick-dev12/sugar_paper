<?php
/**
 * Worker CLI : traite les jobs de notification post-commande
 * Usage : php scripts/process_notify_queue.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once __DIR__ . '/../services/notify_queue_worker.php';

$result = notify_queue_process_jobs(20, 30);

echo json_encode([
    'processed' => $result['notify']['processed'],
    'errors' => $result['notify']['errors'],
    'email' => $result['email'],
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
