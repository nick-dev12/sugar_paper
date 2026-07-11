<?php
/**
 * Session admin persistante + garde d'authentification
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../../includes/session_admin.php';
require_once __DIR__ . '/../../includes/auth_redirect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start_persistent();
}

if (!isset($_SESSION['admin_id'])) {
    admin_redirect_to_login();
}
