<?php
/**
 * Configuration Firebase - Source unique pour toutes les pages
 * En cas d'erreur "API key not valid", voir FIX_API_KEY_NOTIFICATIONS.md
 */
$firebase_config = require __DIR__ . '/../config/firebase_config.php';
$fcm_sw_file = dirname(__DIR__) . '/firebase-messaging-sw.js';
$fcm_sw_v = is_file($fcm_sw_file) ? (int) filemtime($fcm_sw_file) : time();
?>
<script>
    window.FIREBASE_CONFIG = <?php echo json_encode([
        'apiKey' => $firebase_config['apiKey'],
        'authDomain' => $firebase_config['authDomain'],
        'projectId' => $firebase_config['projectId'],
        'storageBucket' => $firebase_config['storageBucket'],
        'messagingSenderId' => $firebase_config['messagingSenderId'],
        'appId' => $firebase_config['appId'],
        'measurementId' => $firebase_config['measurementId'] ?? null
    ]); ?>;
    window.FIREBASE_VAPID_KEY = <?php echo json_encode(trim($firebase_config['vapidKey'] ?? '')); ?>;
    window.FCM_SW_PATH = <?php echo json_encode('/firebase-messaging-sw.js?v=' . $fcm_sw_v); ?>;
</script>
