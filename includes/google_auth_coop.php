<?php
/**
 * En-têtes pour popups Google / Apple (Firebase signInWithPopup).
 */
if (!headers_sent()) {
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
}
