<?php
/**
 * Scripts Firebase Auth (Google + Apple).
 */
require_once __DIR__ . '/asset_version.php';
$social_auth_js = __DIR__ . '/../js/firebase-social-auth.js';
$social_auth_v = file_exists($social_auth_js) ? (string) filemtime($social_auth_js) : get_asset_version();
?>
<script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-app-compat.js" defer></script>
<script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-auth-compat.js" defer></script>
<?php require_once __DIR__ . '/firebase_init.php'; ?>
<script defer>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.FIREBASE_CONFIG || typeof firebase === 'undefined') {
            return;
        }
        try {
            var _socialAuthConfig = {
                apiKey: window.FIREBASE_CONFIG.apiKey,
                authDomain: window.FIREBASE_CONFIG.authDomain,
                projectId: window.FIREBASE_CONFIG.projectId,
                storageBucket: window.FIREBASE_CONFIG.storageBucket,
                messagingSenderId: window.FIREBASE_CONFIG.messagingSenderId,
                appId: window.FIREBASE_CONFIG.appId
            };
            if (window.FIREBASE_CONFIG.measurementId) {
                _socialAuthConfig.measurementId = window.FIREBASE_CONFIG.measurementId;
            }
            if (!firebase.apps.length) {
                firebase.initializeApp(_socialAuthConfig);
            }
            if (typeof window.colobanesScheduleAppleRedirect === 'function') {
                window.colobanesScheduleAppleRedirect(0);
            } else if (typeof window.colobanesCompleteAppleRedirect === 'function') {
                window.colobanesCompleteAppleRedirect();
            }
        } catch (e) {
            if (!String(e.message || e).includes('already exists')) {
                console.error('[Social Auth] Firebase init:', e);
            }
        }
    });
</script>
<script
    src="/js/firebase-social-auth.js?v=<?php echo htmlspecialchars($social_auth_v, ENT_QUOTES, 'UTF-8'); ?>"
    defer></script>