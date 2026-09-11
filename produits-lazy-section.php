<?php
/**
 * Fragment HTML pour chargement progressif page catalogue produits
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/produits_lazy.php';

$part_key = isset($_GET['part']) ? trim((string) $_GET['part']) : '';

if (!is_valid_produits_lazy_part($part_key)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Requête invalide';
    exit;
}

$filter_params = [];
foreach (['recherche', 'prix_min', 'prix_max', 'categorie', 'tri'] as $key) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $filter_params[$key] = $_GET[$key];
    }
}

$return_url = '/produits.php';
$query = http_build_query($filter_params);
if ($query !== '') {
    $return_url .= '?' . $query;
}
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
render_produits_lazy_part($part_key, $filter_params, $return_url, $limit);
$html = ob_get_clean();

if (trim($html) === '') {
    http_response_code(204);
    exit;
}

echo $html;
