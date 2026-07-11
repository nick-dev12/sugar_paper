<?php
/**
 * Pays marketplace supportés : Sénégal, Côte d'Ivoire, Gabon.
 */

function marketplace_countries_list(): array
{
    return [
        'SN' => [
            'code' => 'SN',
            'label' => 'Sénégal',
            'flag_iso' => 'sn',
        ],
        'CI' => [
            'code' => 'CI',
            'label' => "Côte d'Ivoire",
            'flag_iso' => 'ci',
        ],
        'GA' => [
            'code' => 'GA',
            'label' => 'Gabon',
            'flag_iso' => 'ga',
        ],
    ];
}

/**
 * Pays affichés dans le sélecteur navigation.
 */
function marketplace_countries_nav_list(): array
{
    $all = marketplace_countries_list();
    $order = ['CI', 'GA', 'SN'];
    $out = [];
    foreach ($order as $code) {
        if (isset($all[$code])) {
            $out[$code] = $all[$code];
        }
    }
    return $out;
}

function marketplace_country_is_valid(string $code): bool
{
    $code = strtoupper(trim($code));
    return $code !== '' && isset(marketplace_countries_list()[$code]);
}

function marketplace_country_label(string $code): string
{
    $code = strtoupper(trim($code));
    $list = marketplace_countries_list();
    return isset($list[$code]) ? (string) $list[$code]['label'] : '';
}

function marketplace_country_flag_iso(string $code): string
{
    $code = strtoupper(trim($code));
    $list = marketplace_countries_list();
    return isset($list[$code]) ? (string) $list[$code]['flag_iso'] : 'sn';
}

function marketplace_country_flag_url(string $code, int $width = 40): string
{
    $iso = marketplace_country_flag_iso($code);
    $w = max(20, min(80, $width));
    return 'https://flagcdn.com/w' . $w . '/' . $iso . '.png';
}

function marketplace_country_flag_url_alt(string $code, int $width = 40): string
{
    $iso = strtoupper(marketplace_country_flag_iso($code));
    $w = max(20, min(80, $width));
    if ($w <= 40) {
        $size = 32;
    } elseif ($w <= 56) {
        $size = 48;
    } else {
        $size = 64;
    }
    return 'https://flagsapi.com/' . $iso . '/flat/' . $size . '.png';
}

function marketplace_country_default_code(): string
{
    return 'SN';
}

function marketplace_country_supports_regions(string $code): bool
{
    require_once __DIR__ . '/geo_regions.php';
    return geo_country_has_regions($code);
}
