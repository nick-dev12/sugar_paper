<?php
/**
 * Action POST — abonnement / désabonnement boutique
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../controllers/controller_boutique_abonnement.php';

process_boutique_abonnement_action();
