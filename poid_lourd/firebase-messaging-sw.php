<?php
/**
 * Rétrocompatibilité — sert le même contenu que firebase-messaging-sw.js
 * Le SW officiel est /firebase-messaging-sw.js (fichier statique).
 */
header('Content-Type: application/javascript; charset=UTF-8');
header('Service-Worker-Allowed: /');
header('Cache-Control: no-cache, no-store, must-revalidate');

$js_path = __DIR__ . '/firebase-messaging-sw.js';
if (!file_exists($js_path)) {
    http_response_code(500);
    echo "/* firebase-messaging-sw.js introuvable — lancez php scripts/sync_firebase_sw.php */";
    exit;
}

readfile($js_path);
