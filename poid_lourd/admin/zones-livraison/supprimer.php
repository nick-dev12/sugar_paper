<?php
/**
 * Page de suppression de zone de livraison
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/require_admin_session.php';


require_once __DIR__ . '/../includes/require_access.php';


$zone_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($zone_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_zones_livraison.php';
require_once __DIR__ . '/../../includes/admin_param_boutique_scope.php';
$scope = admin_param_boutique_scope_id();
$scope_del = $scope !== null ? (int) $scope : null;
$result = delete_zone_livraison($zone_id, $scope_del);

$_SESSION['success_message'] = $result['message'];
header('Location: index.php');
exit;
