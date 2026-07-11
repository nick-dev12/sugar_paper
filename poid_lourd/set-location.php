<?php
/**
 * Enregistre la position GPS du visiteur (session + users si connecté).
 * Reçoit un POST de formulaire classique (champs cachés remplis par js/geo-location.js).
 * Redirige ensuite vers la page demandée.
 */

require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/includes/geo_location_service.php';

/* Redirection de retour : uniquement chemin interne au site */
function set_location_safe_redirect(string $target): string
{
    $target = trim($target);
    if ($target === '' || $target[0] !== '/' || str_starts_with($target, '//')) {
        return '/index.php';
    }
    return $target;
}

$redirect = set_location_safe_redirect((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? '/index.php'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

/* Effacement volontaire (bouton "Ne plus utiliser ma position") */
if (isset($_POST['action']) && $_POST['action'] === 'clear_location') {
    geo_session_clear_location();
    header('Location: ' . $redirect);
    exit;
}

$lat = geo_parse_coord($_POST['geo_lat'] ?? $_POST['lat'] ?? null);
$lng = geo_parse_coord($_POST['geo_lng'] ?? $_POST['lng'] ?? null);
$precision = geo_parse_precision($_POST['geo_precision'] ?? $_POST['precision'] ?? null);

if (geo_coords_valid($lat, $lng)) {
    geo_session_set_location($lat, $lng, $precision);

    if (!empty($_SESSION['user_id'])) {
        geo_save_user_last_location((int) $_SESSION['user_id'], $lat, $lng, $precision);
    }
} else {
    $sep = (str_contains($redirect, '?')) ? '&' : '?';
    $redirect .= $sep . 'geo_error=1';
}

header('Location: ' . $redirect);
exit;
