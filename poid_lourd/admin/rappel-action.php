<?php
/**
 * Actions inline des rappels vendeur (dashboard).
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/includes/require_access.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../models/model_vendeur_rappels.php';
require_once __DIR__ . '/../includes/flash_toast.php';
require_once __DIR__ . '/../includes/marketplace_countries.php';
require_once __DIR__ . '/../includes/geo_regions.php';
require_once __DIR__ . '/../includes/geo_location_service.php';
require_once __DIR__ . '/../includes/boutique_vendeur_display.php';
require_once __DIR__ . '/../includes/upload_image_limits.php';
require_once __DIR__ . '/../includes/boutique_types.php';

$redirect = 'dashboard.php';

$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '');
if ($role !== 'vendeur') {
    header('Location: ' . $redirect);
    exit;
}

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$admin = $admin_id > 0 ? get_admin_by_id($admin_id) : false;
if (!$admin || ($admin['role'] ?? '') !== 'vendeur') {
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$tok = (string) ($_POST['csrf_token'] ?? '');
if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
    flash_toast_queue_page('error', 'Session expirée. Veuillez recharger la page.');
    header('Location: ' . $redirect);
    exit;
}

$rappel_id = (int) ($_POST['rappel_id'] ?? 0);
$rappel = $rappel_id > 0 ? get_vendeur_rappel_by_id($rappel_id) : false;
if (!$rappel || (int) ($rappel['actif'] ?? 0) !== 1) {
    flash_toast_queue_page('error', 'Rappel introuvable.');
    header('Location: ' . $redirect);
    exit;
}

$atype = (string) ($rappel['action_type'] ?? '');
if (!vendeur_rappel_trigger_matches($admin_id, $atype)) {
    flash_toast_queue_page('success', 'Cette action n\'est plus nécessaire.');
    header('Location: ' . $redirect);
    exit;
}

$error_message = '';
$success_message = '';

switch ($atype) {
    case 'select_boutique_type':
        $type_id = (int) ($_POST['boutique_type_id'] ?? 0);
        if ($type_id <= 0) {
            $error_message = 'Veuillez sélectionner un type de boutique.';
        } elseif (!update_admin_boutique_type($admin_id, $type_id)) {
            $error_message = 'Type de boutique invalide ou enregistrement impossible.';
        } else {
            $success_message = 'Type de boutique enregistré.';
        }
        break;

    case 'select_region':
        $boutique_country = isset($_POST['boutique_country']) ? strtoupper(trim((string) $_POST['boutique_country'])) : '';
        $boutique_region = isset($_POST['boutique_region']) ? trim((string) $_POST['boutique_region']) : '';
        if ($boutique_country === '' || !marketplace_country_is_valid($boutique_country)) {
            $error_message = 'Veuillez sélectionner un pays valide.';
        } elseif ($boutique_region === '' || !geo_region_is_valid($boutique_country, $boutique_region)) {
            $error_message = 'Veuillez sélectionner une région valide.';
        } else {
            $data = [];
            if (admin_has_boutique_country_column()) {
                $data['boutique_country'] = $boutique_country;
            }
            if (admin_has_boutique_region_column()) {
                $data['boutique_region'] = $boutique_region;
            }
            if (!empty($data) && update_admin_boutique_branding($admin_id, $data)) {
                require_once __DIR__ . '/../includes/admin_vendeur_theme.php';
                $admin = get_admin_by_id($admin_id);
                if ($admin) {
                    admin_vendeur_theme_sync_session($admin);
                }
                $success_message = 'Pays et région enregistrés.';
            } else {
                $error_message = 'Enregistrement impossible.';
            }
        }
        break;

    case 'capture_geo':
        $g_lat = geo_parse_coord($_POST['geo_lat'] ?? null);
        $g_lng = geo_parse_coord($_POST['geo_lng'] ?? null);
        if (!geo_coords_valid($g_lat, $g_lng)) {
            $error_message = 'Position invalide. Autorisez la localisation puis réessayez.';
        } elseif (!geo_save_boutique_position_bundle($admin_id, $g_lat, $g_lng, 'gps')) {
            $error_message = 'Impossible d\'enregistrer la position GPS.';
        } else {
            $success_message = 'Position GPS enregistrée.';
        }
        break;

    case 'upload_logo':
        $current_logo = trim((string) ($admin['boutique_logo'] ?? ''));
        $logo_final = $current_logo;
        if (!isset($_FILES['boutique_logo']) || (int) ($_FILES['boutique_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error_message = 'Veuillez choisir une image pour votre logo.';
        } else {
            $f = $_FILES['boutique_logo'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $mime = (string) ($f['type'] ?? '');
            if (!in_array($mime, $allowed, true)) {
                $error_message = 'Logo : formats acceptés JPEG, PNG, GIF, WebP.';
            } elseif ((int) ($f['size'] ?? 0) > UPLOAD_MAX_IMAGE_BYTES) {
                $error_message = 'Logo trop volumineux (maximum 20 Mo).';
            } else {
                $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    $ext = 'jpg';
                }
                $dir = __DIR__ . '/../upload/boutique_branding/';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $fname = 'v_' . $admin_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $dest = $dir . $fname;
                if (move_uploaded_file($f['tmp_name'], $dest)) {
                    if ($current_logo !== '') {
                        $old = __DIR__ . '/../upload/' . str_replace('\\', '/', $current_logo);
                        if (is_file($old)) {
                            @unlink($old);
                        }
                    }
                    $logo_final = 'boutique_branding/' . $fname;
                } else {
                    $error_message = 'Impossible d\'enregistrer le fichier logo.';
                }
            }
        }
        if ($error_message === '' && $logo_final !== '') {
            $ok = update_admin_boutique_branding($admin_id, ['boutique_logo' => $logo_final]);
            if ($ok) {
                require_once __DIR__ . '/../includes/admin_vendeur_theme.php';
                $admin = get_admin_by_id($admin_id);
                if ($admin) {
                    admin_vendeur_theme_sync_session($admin);
                }
                $success_message = 'Logo de la boutique enregistré.';
            } else {
                $error_message = 'Enregistrement impossible.';
            }
        }
        break;

    case 'customize_colors':
        $raw_c1 = trim((string) ($_POST['couleur_principale'] ?? ''));
        $raw_c2 = trim((string) ($_POST['couleur_accent'] ?? ''));
        if ($raw_c1 !== '' && boutique_normalize_hex_color($raw_c1) === '') {
            $error_message = 'Couleur principale invalide (format #RRGGBB).';
        } elseif ($raw_c2 !== '' && boutique_normalize_hex_color($raw_c2) === '') {
            $error_message = 'Couleur d\'accent invalide (format #RRGGBB).';
        } else {
            $c1 = $raw_c1 !== '' ? boutique_normalize_hex_color($raw_c1) : '';
            $c2 = $raw_c2 !== '' ? boutique_normalize_hex_color($raw_c2) : '';
            if (strtoupper($c1) === '#3564A6' && strtoupper($c2) === '#FF6B35') {
                $error_message = 'Choisissez des couleurs différentes des couleurs par défaut du site.';
            } else {
                $branding_data = [
                    'boutique_couleur_principale' => $c1 !== '' ? $c1 : null,
                    'boutique_couleur_accent' => $c2 !== '' ? $c2 : null,
                ];
                if (update_admin_boutique_branding($admin_id, $branding_data)) {
                    require_once __DIR__ . '/../includes/admin_vendeur_theme.php';
                    $admin = get_admin_by_id($admin_id);
                    if ($admin) {
                        admin_vendeur_theme_sync_session($admin);
                    }
                    $success_message = 'Couleurs de la boutique enregistrées.';
                } else {
                    $error_message = 'Enregistrement impossible.';
                }
            }
        }
        break;

    default:
        $error_message = 'Action non prise en charge depuis cette page.';
        break;
}

if ($error_message !== '') {
    flash_toast_queue_page('error', $error_message);
    header('Location: ' . $redirect);
    exit;
}

if ($success_message !== '') {
    vendeur_rappel_mark_completed($admin_id, $rappel_id);
    $_SESSION['success_message'] = $success_message;
}

header('Location: ' . $redirect);
exit;
