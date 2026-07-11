<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * @deprecated Redirection vers le formulaire unifié (clients + boutiques).
 */
header('Location: /mot-de-passe-oublie.php?type=client', true, 302);
exit;
