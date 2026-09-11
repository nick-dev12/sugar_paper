<?php
/**
 * Fragment HTML pour chargement progressif des sections page produit
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/produit_lazy_sections.php';

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$section_key = isset($_GET['section']) ? trim((string) $_GET['section']) : '';

if ($produit_id <= 0 || !is_valid_produit_lazy_section_key($section_key)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Requête invalide';
    exit;
}

$return_url = '/produit.php?id=' . $produit_id;
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer_path = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $referer_query = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
    if (is_string($referer_path) && $referer_path !== '') {
        $return_url = $referer_path . (is_string($referer_query) && $referer_query !== '' ? '?' . $referer_query : '');
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=60');

ob_start();
render_produit_lazy_section($section_key, $produit_id, $return_url);
$html = ob_get_clean();

if (trim($html) === '') {
    http_response_code(204);
    exit;
}

echo $html;
