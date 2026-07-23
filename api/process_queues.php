<?php
/**
 * Endpoint HTTP : traite les files notify + email (hébergement sans exec CLI)
 * GET/POST ?key=SECRET (storage/.queue_worker_secret)
 */
require_once __DIR__ . '/../services/notify_queue_worker.php';

header('Content-Type: application/json; charset=utf-8');

$key = isset($_REQUEST['key']) ? (string) $_REQUEST['key'] : '';
$expected = queue_worker_get_secret();

if ($key === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

@set_time_limit(120);
ignore_user_abort(true);

if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

$result = notify_queue_process_jobs(20, 30);

echo json_encode([
    'success' => true,
    'notify_pending_left' => count(glob(NOTIFY_QUEUE_PENDING_DIR . '/*.json') ?: []),
    'email_pending_left' => count(glob(EMAIL_QUEUE_PENDING_DIR . '/*.json') ?: []),
    'result' => $result,
], JSON_UNESCAPED_UNICODE);
