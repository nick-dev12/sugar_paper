<?php
/**
 * Traitement + variables UI — localisation boutique vendeur (parametres.php).
 */
if (!isset($vbl_admin_id) || (int) $vbl_admin_id <= 0) {
    return;
}

require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../includes/flash_toast.php';
require_once __DIR__ . '/../../includes/marketplace_countries.php';
require_once __DIR__ . '/../../includes/geo_regions.php';
require_once __DIR__ . '/../../includes/geo_location_service.php';
require_once __DIR__ . '/../../includes/geo_geocoder.php';

$vbl_admin_id = (int) $vbl_admin_id;
if (!isset($vbl_admin) || !is_array($vbl_admin)) {
    $vbl_admin = get_admin_by_id($vbl_admin_id);
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$vbl_redirect = 'parametres.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['boutique_localisation_save'])) {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        flash_toast_queue_page('error', 'Session expirée. Veuillez recharger la page.');
    } else {
        $save_part = (string) ($_POST['localisation_part'] ?? 'all');
        $boutique_country = isset($_POST['boutique_country']) ? strtoupper(trim((string) $_POST['boutique_country'])) : '';
        $boutique_region = isset($_POST['boutique_region']) ? trim((string) $_POST['boutique_region']) : '';
        $error_message = '';

        if (in_array($save_part, ['region', 'all'], true)) {
            if (admin_has_boutique_country_column() && ($boutique_country === '' || !marketplace_country_is_valid($boutique_country))) {
                $error_message = 'Veuillez sélectionner un pays valide pour votre boutique.';
            } elseif (admin_has_boutique_region_column() && ($boutique_region === '' || !geo_region_is_valid($boutique_country !== '' ? $boutique_country : 'SN', $boutique_region))) {
                $error_message = 'Veuillez sélectionner une région valide pour votre boutique.';
            }
        }

        if ($error_message === '') {
            $data = [];
            if (in_array($save_part, ['region', 'all'], true)) {
                if (admin_has_boutique_country_column()) {
                    $data['boutique_country'] = $boutique_country;
                }
                if (admin_has_boutique_region_column()) {
                    $data['boutique_region'] = $boutique_region;
                }
            }

            if (!empty($data) && update_admin_boutique_branding($vbl_admin_id, $data)) {
                $vbl_admin = get_admin_by_id($vbl_admin_id);
                if ($vbl_admin) {
                    require_once __DIR__ . '/../../includes/admin_vendeur_theme.php';
                    admin_vendeur_theme_sync_session($vbl_admin);
                }
                $_SESSION['success_message'] = $save_part === 'region' ? 'Pays et région enregistrés.' : 'Localisation enregistrée.';
                header('Location: ' . $vbl_redirect);
                exit;
            }
            flash_toast_queue_page('error', 'Enregistrement impossible.');
        } else {
            flash_toast_queue_page('error', $error_message);
        }
        $vbl_admin = get_admin_by_id($vbl_admin_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['geo_boutique_action'])) {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        flash_toast_queue_page('error', 'Session expirée. Veuillez recharger la page.');
    } else {
        $geo_action = (string) $_POST['geo_boutique_action'];
        $country = strtoupper(trim((string) ($vbl_admin['boutique_country'] ?? 'SN')));

        if ($geo_action === 'capture') {
            $g_lat = geo_parse_coord($_POST['geo_lat'] ?? null);
            $g_lng = geo_parse_coord($_POST['geo_lng'] ?? null);
            if (geo_coords_valid($g_lat, $g_lng) && geo_save_boutique_position_bundle($vbl_admin_id, $g_lat, $g_lng, 'gps')) {
                $_SESSION['success_message'] = 'Position GPS de votre boutique enregistrée.';
            } else {
                flash_toast_queue_page('error', 'Position invalide. Autorisez la localisation puis réessayez.');
            }
        } elseif ($geo_action === 'manual') {
            $position_text = geo_address_concise_normalize((string) ($_POST['boutique_position_text'] ?? ''));
            if ($position_text === '') {
                flash_toast_queue_page('error', 'Indiquez une adresse ou un lieu pour votre boutique.');
            } else {
                $geocoded = geo_geocode_address($position_text, marketplace_country_is_valid($country) ? $country : null);
                if ($geocoded === null) {
                    flash_toast_queue_page('error', 'Adresse introuvable. Précisez le quartier ou la ville.');
                } elseif (geo_save_boutique_position_bundle($vbl_admin_id, $geocoded['lat'], $geocoded['lng'], 'manuel', $position_text)) {
                    $_SESSION['success_message'] = 'Position personnalisée enregistrée.';
                } else {
                    flash_toast_queue_page('error', 'Impossible d\'enregistrer la position.');
                }
            }
        } elseif ($geo_action === 'clear') {
            geo_save_boutique_location($vbl_admin_id, null, null);
            update_admin_boutique_branding($vbl_admin_id, ['boutique_adresse' => null]);
            $_SESSION['success_message'] = 'Position GPS de votre boutique supprimée.';
        }

        if (!empty($_SESSION['success_message'])) {
            header('Location: ' . $vbl_redirect);
            exit;
        }
        $vbl_admin = get_admin_by_id($vbl_admin_id);
    }
}

require_once __DIR__ . '/vendeur_boutique_localisation_ui_vars.php';
extract(vendeur_boutique_localisation_prepare_ui_vars($vbl_admin, [
    'admin_id' => $vbl_admin_id,
    'allow_reverse_geocode_upgrade' => $_SERVER['REQUEST_METHOD'] !== 'POST',
]));
