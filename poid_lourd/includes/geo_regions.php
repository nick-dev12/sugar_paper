<?php
/**
 * Régions par pays marketplace (SN, CI, GA).
 */

require_once __DIR__ . '/senegal_regions.php';
require_once __DIR__ . '/marketplace_countries.php';

function geo_regions_registry(): array
{
    return [
        'SN' => senegal_regions_list(),
        'CI' => [
            'abidjan' => 'Abidjan',
            'yamoussoukro' => 'Yamoussoukro',
            'bouake' => 'Bouaké',
            'san-pedro' => 'San-Pédro',
            'korhogo' => 'Korhogo',
            'daloa' => 'Daloa',
            'man' => 'Man',
            'gagnoa' => 'Gagnoa',
            'divo' => 'Divo',
            'bondoukou' => 'Bondoukou',
            'abengourou' => 'Abengourou',
            'seguela' => 'Séguela',
        ],
        'GA' => [
            'estuaire' => 'Estuaire (Libreville)',
            'haut-ogooue' => 'Haut-Ogooué',
            'moyen-ogooue' => 'Moyen-Ogooué',
            'ngounie' => 'Ngounié',
            'nyanga' => 'Nyanga',
            'ogooue-ivindo' => 'Ogooué-Ivindo',
            'ogooue-lolo' => 'Ogooué-Lolo',
            'ogooue-maritime' => 'Ogooué-Maritime',
            'woleu-ntem' => 'Woleu-Ntem',
        ],
    ];
}

function geo_country_has_regions(string $country): bool
{
    $country = strtoupper(trim($country));
    if (!marketplace_country_is_valid($country)) {
        return false;
    }
    $regions = geo_regions_registry();
    return isset($regions[$country]) && !empty($regions[$country]);
}

function geo_regions_for_country(string $country): array
{
    $country = strtoupper(trim($country));
    $registry = geo_regions_registry();
    return $registry[$country] ?? [];
}

function geo_region_is_valid(string $country, string $code): bool
{
    $country = strtoupper(trim($country));
    $code = trim($code);
    if ($code === '') {
        return false;
    }
    $list = geo_regions_for_country($country);
    return isset($list[$code]);
}

function geo_region_label(string $country, string $code): string
{
    $list = geo_regions_for_country($country);
    return isset($list[$code]) ? (string) $list[$code] : '';
}

function geo_regions_options_html(string $country, string $selected = '', bool $include_empty = false, string $empty_label = 'Choisir une région'): string
{
    $country = strtoupper(trim($country));
    $html = '';
    if ($include_empty) {
        $sel = ($selected === '') ? ' selected' : '';
        $html .= '<option value=""' . $sel . '>' . htmlspecialchars($empty_label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    foreach (geo_regions_for_country($country) as $code => $label) {
        $sel = ((string) $selected === (string) $code) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

function geo_regions_json_for_js(): string
{
    $out = [];
    foreach (array_keys(marketplace_countries_list()) as $country) {
        $out[$country] = [];
        foreach (geo_regions_for_country($country) as $code => $label) {
            $out[$country][] = ['code' => $code, 'label' => $label];
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}

function marketplace_countries_options_html(string $selected = '', bool $include_empty = false, string $empty_label = 'Choisir un pays'): string
{
    $html = '';
    if ($include_empty) {
        $sel = ($selected === '') ? ' selected' : '';
        $html .= '<option value=""' . $sel . '>' . htmlspecialchars($empty_label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    foreach (marketplace_countries_nav_list() as $code => $meta) {
        $sel = (strtoupper($selected) === $code) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}
