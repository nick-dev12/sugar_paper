<?php
/**
 * Enregistrement automatique de la position GPS de la boutique d'un vendeur
 * à la connexion / création de compte (formulaire POST classique, silencieux).
 * N'écrase JAMAIS une position déjà définie : la mise à jour volontaire
 * se fait dans parametres-boutique-vendeur.php.
 */

require_once __DIR__ . '/includes/require_admin_session.php';
require_once dirname(__DIR__) . '/conn/conn.php';
require_once dirname(__DIR__) . '/includes/geo_location_service.php';

function sbl_safe_redirect(string $target): string
{
    $target = trim($target);
    if ($target === '' || $target[0] !== '/' || str_starts_with($target, '//')) {
        return '/admin/dashboard.php';
    }
    return $target;
}

$redirect = sbl_safe_redirect((string) ($_POST['redirect'] ?? '/admin/dashboard.php'));

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$admin_role = (string) ($_SESSION['admin_role'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin_id > 0 && $admin_role === 'vendeur') {
    $lat = geo_parse_coord($_POST['geo_lat'] ?? null);
    $lng = geo_parse_coord($_POST['geo_lng'] ?? null);
    if (geo_coords_valid($lat, $lng)) {
        geo_save_boutique_location_if_missing($admin_id, $lat, $lng, 'gps');
    }
}

header('Location: ' . $redirect);
exit;
