<?php
/**
 * Fragment HTML pour chargement progressif des sections accueil
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/home_lazy_sections.php';

$section_key = isset($_GET['section']) ? trim((string) $_GET['section']) : '';

if (!is_valid_home_lazy_section_key($section_key)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Section invalide';
    exit;
}

$home_return_url = '/index.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer_path = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    if (is_string($referer_path) && $referer_path !== '') {
        $home_return_url = $referer_path;
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=60');

ob_start();
render_home_lazy_section($section_key, $home_return_url);
$html = ob_get_clean();

if (trim($html) === '') {
    http_response_code(204);
    exit;
}

echo $html;
