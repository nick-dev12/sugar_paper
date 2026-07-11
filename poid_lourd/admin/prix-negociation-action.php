<?php
/**
 * Action POST — négociation de prix (vendeur)
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/../includes/flash_toast.php';

require_once __DIR__ . '/../controllers/controller_prix_negociation.php';

process_prix_negociation_vendor_action();
