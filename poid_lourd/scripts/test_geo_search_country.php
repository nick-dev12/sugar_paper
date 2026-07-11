<?php
/** Test filtre pays recherche adresse. Usage : php scripts/test_geo_search_country.php */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/geo_location_service.php';
require_once $root . '/includes/marketplace_country_filter.php';

session_start();

$pass = 0;
$fail = 0;

function t(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [OK]   $label\n"; }
    else { $fail++; echo "  [FAIL] $label\n"; }
}

echo "=== Test pays recherche adresse ===\n\n";

$_SESSION['marketplace_country_welcome_done'] = true;
$_SESSION['marketplace_country_code'] = 'SN';
$_SESSION['marketplace_country_set_by_user'] = true;

t('session SN -> SN', geo_search_country_code(null) === 'SN');
t('nominatim SN -> sn', geo_search_country_nominatim(null) === 'sn');

$_SESSION['marketplace_country_code'] = 'CI';
t('session CI -> CI', geo_search_country_code(null) === 'CI');

$fake_boutique = ['boutique_country' => 'GA'];
t('boutique GA prioritaire sur session CI', geo_search_country_code($fake_boutique) === 'GA');

echo "\n=== Résultat : $pass OK / $fail FAIL ===\n";
exit($fail > 0 ? 1 : 0);
