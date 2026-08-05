<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Dernière position livreur — observateurs (admin ou lien public token).
 * GET : bl_id ou commande_id + token (optionnel pour public)
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../models/model_livreur_tracking.php';
require_once __DIR__ . '/../../models/model_livreur_notes.php';

$commande_id = (int) ($_GET['commande_id'] ?? 0);
$bl_id = (int) ($_GET['bl_id'] ?? 0);
$token = trim((string) ($_GET['token'] ?? ''));

if ($commande_id < 1 && $bl_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'bl_id ou commande_id requis'], JSON_UNESCAPED_UNICODE);
    exit;
}

$authorized = false;
$livreur_id = null;
$tracking_active = 0;
$livraison_row = null;
$livraison_type_poll = '';

if ($token !== '') {
    $row = livreur_get_watch_token_row(
        $token,
        $commande_id > 0 ? $commande_id : null,
        $bl_id > 0 ? $bl_id : null
    );
    if ($row) {
        $authorized = true;
        $livreur_id = (int) ($row['livreur_id'] ?? 0);
        $tracking_active = (int) ($row['tracking_active'] ?? 0);
        if ($bl_id > 0) {
            $livraison_row = livreur_get_facture_tracking($bl_id);
            $livraison_type_poll = 'facture';
        } elseif ($commande_id > 0) {
            $livraison_row = livreur_get_commande_tracking($commande_id);
            $livraison_type_poll = 'commande';
        }
    }
} else {
    session_start_persistent();
    if (isset($_SESSION['admin_id'])) {
        if ($bl_id > 0) {
            $facture = livreur_get_facture_tracking($bl_id);
            if ($facture) {
                $authorized = true;
                $livreur_id = !empty($facture['livreur_id']) ? (int) $facture['livreur_id'] : null;
                $tracking_active = (int) ($facture['tracking_active'] ?? 0);
            }
        } elseif ($commande_id > 0) {
            $commande = livreur_get_commande_tracking($commande_id);
            if ($commande) {
                $authorized = true;
                $livreur_id = !empty($commande['livreur_id']) ? (int) $commande['livreur_id'] : null;
                $tracking_active = (int) ($commande['tracking_active'] ?? 0);
            }
        }
    }
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé'], JSON_UNESCAPED_UNICODE);
    exit;
}

$last = null;
if ($livreur_id) {
    $last = livreur_get_last_position($livreur_id, $commande_id > 0 ? $commande_id : null, $bl_id > 0 ? $bl_id : null);
}

$countdown = livreur_countdown_state_for_livraison(
    $commande_id > 0 ? $commande_id : null,
    $bl_id > 0 ? $bl_id : null
);

$can_rate_livreur = false;
$arrivee_at = null;
if (is_array($livraison_row) && $livraison_type_poll !== '') {
    $can_rate_livreur = livreur_note_peut_noter($livraison_row, $livraison_type_poll);
    $arrivee_at = $livraison_row['livraison_arrivee_at'] ?? null;
}

echo json_encode([
    'success' => true,
    'tracking_active' => $tracking_active,
    'countdown' => $countdown,
    'last_position' => $last,
    'arrivee_at' => $arrivee_at,
    'can_rate_livreur' => $can_rate_livreur,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
