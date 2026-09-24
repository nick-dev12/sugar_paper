<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Adresse / GPS de livraison enregistrés pour un numéro de téléphone.
 */
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/admin_route_access.php';
admin_route_enforce_json_empty();

require_once __DIR__ . '/../models/model_livreur_tracking.php';

$telephone = trim((string) ($_GET['telephone'] ?? ''));
if ($telephone === '') {
    echo json_encode(['ok' => false, 'error' => 'missing_phone'], JSON_UNESCAPED_UNICODE);
    exit;
}

$profil = livreur_get_client_livraison_profil($telephone);
if ($profil === null) {
    $profil = livreur_lookup_adresse_par_telephone($telephone);
}

if ($profil === null || trim((string) ($profil['adresse'] ?? '')) === '') {
    echo json_encode(['ok' => true, 'found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'found' => true,
    'adresse' => (string) $profil['adresse'],
    'delivery_latitude' => isset($profil['delivery_latitude']) ? (float) $profil['delivery_latitude'] : null,
    'delivery_longitude' => isset($profil['delivery_longitude']) ? (float) $profil['delivery_longitude'] : null,
], JSON_UNESCAPED_UNICODE);
