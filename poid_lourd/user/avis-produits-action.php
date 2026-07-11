<?php
/**
 * API avis produits (notation + report popup 48h).
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Connexion requise.']);
    exit;
}

require_once __DIR__ . '/../models/model_produits_avis.php';

$user_id = (int) $_SESSION['user_id'];
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

if ($action === 'snooze') {
    if (!produits_avis_table_exists()) {
        echo json_encode(['success' => false, 'message' => 'Service indisponible.']);
        exit;
    }
    produits_avis_set_popup_snooze($user_id, 48);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save_one') {
    if (!produits_avis_table_exists()) {
        echo json_encode(['success' => false, 'message' => 'Migration avis requise.']);
        exit;
    }
    $pid = (int) ($_POST['produit_id'] ?? 0);
    $cid = (int) ($_POST['commande_id'] ?? 0);
    $note = produits_avis_normaliser_note($_POST['note'] ?? 0);
    if ($pid <= 0 || $cid <= 0 || $note < 0.33) {
        echo json_encode(['success' => false, 'message' => 'Note invalide.']);
        exit;
    }
    if (!produits_avis_save($user_id, $pid, $cid, $note)) {
        echo json_encode(['success' => false, 'message' => 'Enregistrement impossible.']);
        exit;
    }
    $remaining = count(produits_avis_get_pending_for_user($user_id, 12));
    if ($remaining <= 0) {
        produits_avis_clear_popup_snooze($user_id);
    }
    echo json_encode([
        'success' => true,
        'message' => 'Merci ! Points bonus ajoutés.',
        'note' => $note,
        'remaining' => $remaining,
    ]);
    exit;
}

if ($action === 'save') {
    if (!produits_avis_table_exists()) {
        echo json_encode(['success' => false, 'message' => 'Migration avis requise.']);
        exit;
    }
    $notes = isset($_POST['notes']) && is_array($_POST['notes']) ? $_POST['notes'] : [];
    $saved = 0;
    foreach ($notes as $key => $val) {
        if (!is_string($key) || strpos($key, '-') === false) {
            continue;
        }
        list($pid, $cid) = array_map('intval', explode('-', $key, 2));
        $note = produits_avis_normaliser_note($val);
        if ($pid <= 0 || $cid <= 0 || $note < 0.33) {
            continue;
        }
        if (produits_avis_save($user_id, $pid, $cid, $note)) {
            $saved++;
        }
    }
    if ($saved <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sélectionnez au moins une note (glissez sur les étoiles).']);
        exit;
    }
    produits_avis_clear_popup_snooze($user_id);
    echo json_encode([
        'success' => true,
        'message' => $saved > 1 ? 'Merci ! Vos notes ont été enregistrées.' : 'Merci pour votre avis !',
        'saved' => $saved,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action invalide.']);
