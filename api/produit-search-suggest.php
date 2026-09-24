<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Suggestions recherche produit (barre nav) — nom uniquement, fuzzy.
 */
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/produit_recherche_fuzzy.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = min(12, max(3, (int) ($_GET['limit'] ?? 8)));

if ($q === '' || mb_strlen($q) < 1) {
    echo json_encode(['items' => []]);
    exit;
}

try {
    global $db;
    $stmt = $db->query("
        SELECT p.id, p.nom, p.image_principale, p.images, p.statut
        FROM produits p
        WHERE p.statut = 'actif'
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $rows = produit_recherche_filter_sort_rows($rows, $q, 'nom', $limit);

    $items = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $items[] = [
            'id' => $id,
            'nom' => $row['nom'] ?? '',
            'url' => '/produit.php?id=' . $id,
            'image_thumb' => produit_search_thumb_url($row),
        ];
    }

    echo json_encode(['items' => $items]);
} catch (Throwable $e) {
    echo json_encode(['items' => [], 'error' => 'search_failed']);
}
