<?php
/**
 * Action POST — négociation de prix (client)
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../controllers/controller_prix_negociation.php';

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : 'propose';

if ($action === 'commander') {
    process_prix_negociation_client_commander();
}

process_prix_negociation_client_propose();
