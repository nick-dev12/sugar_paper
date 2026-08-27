<?php
/**
 * Alias public pour Google Play / App Store (URL courte, toujours active).
 * Redirection permanente vers la politique de confidentialité complète.
 */
header('HTTP/1.1 301 Moved Permanently');
header('Location: /politique-confidentialite.php', true, 301);
header('Cache-Control: public, max-age=86400');
exit;
