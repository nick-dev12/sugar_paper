<?php
/**
 * Régions administratives du Sénégal (14 régions).
 */

function senegal_regions_list()
{
    return [
        'dakar' => 'Dakar',
        'diourbel' => 'Diourbel',
        'fatick' => 'Fatick',
        'kaffrine' => 'Kaffrine',
        'kaolack' => 'Kaolack',
        'kedougou' => 'Kédougou',
        'kolda' => 'Kolda',
        'louga' => 'Louga',
        'matam' => 'Matam',
        'saint-louis' => 'Saint-Louis',
        'sedhiou' => 'Sédhiou',
        'tambacounda' => 'Tambacounda',
        'thies' => 'Thiès',
        'ziguinchor' => 'Ziguinchor',
    ];
}

function senegal_region_is_valid($code)
{
    if (!is_string($code) || $code === '') {
        return false;
    }
    $list = senegal_regions_list();
    return isset($list[$code]);
}

function senegal_region_label($code)
{
    $list = senegal_regions_list();
    return $list[$code] ?? '';
}

function senegal_regions_options_html($selected = '', $include_empty = false, $empty_label = 'Choisir une région')
{
    $html = '';
    if ($include_empty) {
        $sel = ($selected === '' || $selected === null) ? ' selected' : '';
        $html .= '<option value=""' . $sel . '>' . htmlspecialchars($empty_label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    foreach (senegal_regions_list() as $code => $label) {
        $sel = ((string) $selected === (string) $code) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}
