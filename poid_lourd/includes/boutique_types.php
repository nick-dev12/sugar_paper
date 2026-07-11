<?php
/**
 * Helpers types de boutique — options pour les formulaires vendeur.
 */

if (!function_exists('boutique_types_table_exists')) {
    require_once dirname(__DIR__) . '/models/model_boutique_types.php';
}

/**
 * Au moins un type actif est configuré (champ obligatoire à l'inscription).
 */
function boutique_types_inscription_required()
{
    if (!boutique_types_table_exists()) {
        return false;
    }
    return count(boutique_types_list_active()) > 0;
}

/**
 * HTML <option> pour un select type de boutique.
 */
function boutique_types_options_html($selected_id = 0, $with_empty = true, $empty_label = 'Sélectionnez un type de boutique')
{
    $selected_id = (int) $selected_id;
    $types = boutique_types_list_active();
    $html = '';
    if ($with_empty) {
        $html .= '<option value="">' . htmlspecialchars($empty_label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    foreach ($types as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $nom = trim((string) ($row['nom'] ?? ''));
        if ($nom === '') {
            continue;
        }
        $sel = $id === $selected_id ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel . '>'
            . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

/**
 * Valide un type pour l'inscription vendeur.
 */
function boutique_type_validate_inscription($type_id)
{
    if (!boutique_types_inscription_required()) {
        return ['ok' => true, 'id' => null];
    }
    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return ['ok' => false, 'message' => 'Veuillez sélectionner le type de votre boutique.'];
    }
    if (!boutique_type_is_valid_active($type_id)) {
        return ['ok' => false, 'message' => 'Le type de boutique sélectionné n\'est pas valide.'];
    }
    return ['ok' => true, 'id' => $type_id];
}

/**
 * Valeur spéciale : pas de plafond de distance (toutes les boutiques géolocalisées).
 */
function boutique_types_distance_unlimited_km()
{
    return 999;
}

function boutique_types_distance_is_unlimited($km)
{
    return (int) $km === boutique_types_distance_unlimited_km();
}

/**
 * Convertit la valeur filtre (km) en rayon SQL (0 = sans limite).
 */
function boutique_types_distance_to_rayon_km($km)
{
    $km = (int) $km;
    if ($km <= 0 || boutique_types_distance_is_unlimited($km)) {
        return 0.0;
    }
    return (float) $km;
}

/**
 * Rayons de distance autorisés (km) pour le filtre catalogue.
 *
 * @return array<int, string>
 */
function boutique_types_distance_options()
{
    return [
        0 => 'Toutes distances',
        1 => '1 km',
        2 => '2 km',
        5 => '5 km',
        10 => '10 km',
        20 => '20 km',
        30 => '30 km',
        999 => '50 km et plus',
    ];
}

function boutique_types_distance_is_valid($km)
{
    $km = (int) $km;
    return array_key_exists($km, boutique_types_distance_options());
}

function boutique_types_distance_options_html($selected_km = 0)
{
    $selected_km = (int) $selected_km;
    if (!boutique_types_distance_is_valid($selected_km)) {
        $selected_km = 0;
    }
    $html = '';
    foreach (boutique_types_distance_options() as $km => $label) {
        $sel = ((int) $km === $selected_km) ? ' selected' : '';
        $html .= '<option value="' . (int) $km . '"' . $sel . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

/**
 * HTML options filtre type (inclut « Tous les types »).
 */
function boutique_types_filter_options_html($selected_id = 0)
{
    return boutique_types_options_html((int) $selected_id, true, 'Tous les types');
}

/**
 * @return array<int, array{id:int,nom:string}>
 */
function boutique_types_filter_json_list()
{
    $out = [];
    foreach (boutique_types_list_active() as $row) {
        $id = (int) ($row['id'] ?? 0);
        $nom = trim((string) ($row['nom'] ?? ''));
        if ($id > 0 && $nom !== '') {
            $out[] = ['id' => $id, 'nom' => $nom];
        }
    }
    return $out;
}
