<?php
/**
 * Vérification token watch (admin/client) — appel interne Node.js
 * POST JSON : token, commande_id ou bl_id, internal_secret
 */
require_once __DIR__ . '/../../includes/tracking_config.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tracking_json_response(['valid' => false, 'error' => 'method_not_allowed'], 405);
}

if (!tracking_verify_internal_request()) {
    tracking_json_response(['valid' => false, 'error' => 'unauthorized'], 403);
}

$input = $_POST;
$content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($content_type, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$token = trim((string) ($input['token'] ?? ''));
$commande_id = (int) ($input['commande_id'] ?? 0);
$bl_id = (int) ($input['bl_id'] ?? 0);
$cp_id = (int) ($input['cp_id'] ?? 0);

if ($token === '' || ($commande_id < 1 && $bl_id < 1 && $cp_id < 1)) {
    tracking_json_response(['valid' => false, 'error' => 'invalid_params'], 400);
}

$row = livreur_get_watch_token_row(
    $token,
    $commande_id > 0 ? $commande_id : null,
    $bl_id > 0 ? $bl_id : null,
    $cp_id > 0 ? $cp_id : null
);
if (!$row) {
    tracking_json_response(['valid' => false, 'error' => 'invalid_watch_token'], 401);
}

$last = null;
if (!empty($row['livreur_id'])) {
    if (!empty($row['cp_id'])) {
        $last = livreur_get_last_position((int) $row['livreur_id'], null, null, (int) $row['cp_id']);
    } elseif (!empty($row['bl_id'])) {
        $last = livreur_get_last_position((int) $row['livreur_id'], null, (int) $row['bl_id']);
    } else {
        $last = livreur_get_last_position((int) $row['livreur_id'], (int) ($row['commande_id'] ?? 0));
    }
}

$is_facture = !empty($row['bl_id']) || (($row['livraison_type'] ?? '') === 'facture');
$is_cp = !empty($row['cp_id']) || (($row['livraison_type'] ?? '') === 'personnalisee');
$livraison_type = $is_cp ? 'personnalisee' : ($is_facture ? 'facture' : 'commande');

$livreur_id = !empty($row['livreur_id']) ? (int) $row['livreur_id'] : 0;
$admin_id = !empty($row['admin_id']) ? (int) $row['admin_id'] : 0;
$row_bl_id = !empty($row['bl_id']) ? (int) $row['bl_id'] : 0;
$row_cp_id = !empty($row['cp_id']) ? (int) $row['cp_id'] : 0;
$ctx_bl_id = $bl_id > 0 ? $bl_id : $row_bl_id;
$ctx_cp_id = $cp_id > 0 ? $cp_id : $row_cp_id;
$ctx_commande_id = $commande_id > 0 ? $commande_id : (int) ($row['commande_id'] ?? 0);

$can_emit_position = false;
if (($row['type'] ?? '') === 'admin' && $livreur_id > 0 && $admin_id > 0) {
    if ($admin_id === $livreur_id) {
        $can_emit_position = true;
    } elseif (livreur_web_can_manage_livraison(
        $admin_id,
        $ctx_commande_id > 0 ? $ctx_commande_id : null,
        $ctx_bl_id > 0 ? $ctx_bl_id : null,
        $ctx_cp_id > 0 ? $ctx_cp_id : null
    )) {
        $can_emit_position = true;
    }
}

$numero = $is_cp
    ? ($row['numero_commande'] ?? ('CP-' . $ctx_cp_id))
    : ($is_facture ? ($row['numero_bl'] ?? null) : ($row['numero_commande'] ?? null));

tracking_json_response([
    'valid' => true,
    'type' => $row['type'],
    'livraison_type' => $livraison_type,
    'commande_id' => !empty($row['commande_id']) ? (int) $row['commande_id'] : null,
    'bl_id' => !empty($row['bl_id']) ? (int) $row['bl_id'] : null,
    'cp_id' => $ctx_cp_id > 0 ? $ctx_cp_id : null,
    'numero_commande' => $numero,
    'livreur_id' => $livreur_id > 0 ? $livreur_id : null,
    'livreur_nom' => trim((string) ($row['livreur_prenom'] ?? '') . ' ' . (string) ($row['livreur_nom'] ?? '')),
    'can_emit_position' => $can_emit_position,
    'tracking_active' => (int) ($row['tracking_active'] ?? 0) === 1,
    'countdown' => livreur_countdown_state_for_livraison(
        $ctx_commande_id > 0 ? $ctx_commande_id : null,
        $ctx_bl_id > 0 ? $ctx_bl_id : null,
        $ctx_cp_id > 0 ? $ctx_cp_id : null
    ),
    'delivery_latitude' => livreur_parse_coord($row['delivery_latitude'] ?? null),
    'delivery_longitude' => livreur_parse_coord($row['delivery_longitude'] ?? null),
    'adresse_livraison' => $row['adresse_livraison'] ?? '',
    'last_position' => $last,
]);