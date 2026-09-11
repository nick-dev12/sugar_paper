<?php
/**
 * Fragment HTML pour chargement progressif page catégorie
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/categorie_lazy.php';

$categorie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$part_key = isset($_GET['part']) ? trim((string) $_GET['part']) : '';

if ($categorie_id <= 0 || !get_categorie_by_id($categorie_id) || !is_valid_categorie_lazy_part($part_key)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Requête invalide';
    exit;
}

$return_url = '/categorie.php?id=' . $categorie_id;
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer_path = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $referer_query = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
    if (is_string($referer_path) && $referer_path !== '') {
        $return_url = $referer_path . (is_string($referer_query) && $referer_query !== '' ? '?' . $referer_query : '');
    }
}

$limit = 20;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=60');

ob_start();
render_categorie_lazy_part($part_key, $categorie_id, $return_url, $limit);
$html = ob_get_clean();

if (trim($html) === '') {
    http_response_code(204);
    exit;
}

echo $html;
