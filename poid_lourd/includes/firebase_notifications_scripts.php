<?php
/**
 * Scripts Firebase notifications — à inclure en fin de page (avant </body>)
 * Variables attendues :
 *   $enable_firebase_notifications (bool)
 *   $firebase_notify_type (string, optionnel : user|admin, défaut user)
 */
if (empty($enable_firebase_notifications)) {
    return;
}
$firebase_notify_type = isset($firebase_notify_type) && $firebase_notify_type === 'admin' ? 'admin' : 'user';
require_once __DIR__ . '/asset_version.php';
$firebase_js_path = __DIR__ . '/../js/firebase-notifications.js';
$firebase_js_v = file_exists($firebase_js_path) ? (string) filemtime($firebase_js_path) : get_asset_version();
?>
<script>console.log('[FCM] Chargement des scripts notifications…');</script>
<script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-messaging-compat.js"></script>
<?php require_once __DIR__ . '/firebase_init.php'; ?>
<script>
    if (window.FIREBASE_CONFIG && typeof firebase !== 'undefined') {
        try {
            var _fcmAppConfig = {
                apiKey: window.FIREBASE_CONFIG.apiKey,
                authDomain: window.FIREBASE_CONFIG.authDomain,
                projectId: window.FIREBASE_CONFIG.projectId,
                storageBucket: window.FIREBASE_CONFIG.storageBucket,
                messagingSenderId: window.FIREBASE_CONFIG.messagingSenderId,
                appId: window.FIREBASE_CONFIG.appId
            };
            if (window.FIREBASE_CONFIG.measurementId) {
                _fcmAppConfig.measurementId = window.FIREBASE_CONFIG.measurementId;
            }
            firebase.initializeApp(_fcmAppConfig);
            console.log('[FCM] Firebase initialisé (projet:', _fcmAppConfig.projectId + ')');
        } catch (e) {
            if (!String(e.message || e).includes('already exists')) {
                console.error('[FCM] Firebase init:', e);
            }
        }
    } else {
        console.error('[FCM] Firebase ou FIREBASE_CONFIG manquant');
    }
    window.FIREBASE_NOTIFY_TYPE = <?php echo json_encode($firebase_notify_type); ?>;
</script>
<script src="/js/firebase-notifications.js?v=<?php echo htmlspecialchars($firebase_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
    if (typeof window.FirebaseNotifications === 'undefined') {
        console.error('[FCM] firebase-notifications.js introuvable ou en erreur — vérifiez l’onglet Réseau (F12)');
    }
</script>
