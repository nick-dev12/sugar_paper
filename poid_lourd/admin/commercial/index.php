<?php
/**
 * Espace Commercial — redirection vers Devis & BL
 */
require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/includes/require_access.php';

header('Location: ../devis/index.php', true, 302);
exit;
