<?php
/**
 * Génère un lien public de suivi livraison (token client).
 * POST JSON : bl_id ou commande_id
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non connecté'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../includes/tracking_config.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';

$input = $_POST;
$content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($content_type, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$commande_id = (int) ($input['commande_id'] ?? 0);
$bl_id = (int) ($input['bl_id'] ?? 0);

if ($commande_id < 1 && $bl_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'bl_id ou commande_id requis'], JSON_UNESCAPED_UNICODE);
    exit;
}

$label = 'Suivi livraison';
$client_name = '';

if ($bl_id > 0) {
    $facture = livreur_get_facture_tracking($bl_id);
    if (!$facture || empty($facture['livreur_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucune livraison en cours pour cette facture.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $watch = livreur_create_watch_token(null, 'client', null, null, $bl_id);
    if (!$watch) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossible de générer le lien.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $label = 'Suivi livraison — ' . ($facture['numero_bl'] ?? 'Facture');
    $client_name = trim((string) ($facture['client_nom'] ?? $facture['raison_sociale'] ?? ''));
    $path = '/suivi-livraison.php?bl_id=' . $bl_id . '&token=' . rawurlencode($watch['token']);
} else {
    $commande = livreur_get_commande_tracking($commande_id);
    if (!$commande || empty($commande['livreur_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucune livraison en cours pour cette commande.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $watch = livreur_create_watch_token($commande_id, 'client', null, null);
    if (!$watch) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossible de générer le lien.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $label = 'Suivi livraison — ' . ($commande['numero_commande'] ?? 'Commande');
    $client_name = trim((string) ($commande['client_prenom'] ?? '') . ' ' . (string) ($commande['client_nom'] ?? ''));
    $path = '/suivi-livraison.php?commande_id=' . $commande_id . '&token=' . rawurlencode($watch['token']);
}

$base = rtrim((string) tracking_config_get('public_site_url', ''), '/');
if ($base === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = $scheme . '://' . $host;
}

$url = $base . $path;
$message = 'Suivez la livraison en direct';
if ($client_name !== '') {
    $message .= ' — ' . $client_name;
}

echo json_encode([
    'success' => true,
    'url' => $url,
    'title' => $label,
    'message' => $message,
    'hint' => 'Ce lien permet de suivre la livraison sans compte administrateur.',
    'expires_at' => $watch['expires_at'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
