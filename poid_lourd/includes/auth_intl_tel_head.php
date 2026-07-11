<?php
/**
 * Feuille de style intl-tel-input — inclure dans <head> après auth-connexion.css.
 * @see https://github.com/jackocnr/intl-tel-input
 */
require_once __DIR__ . '/ip_geo_resolver.php';
$auth_geo_country = strtolower(ip_geo_detect_country_code());
if (!preg_match('/^[a-z]{2}$/', $auth_geo_country)) {
    $auth_geo_country = 'sn';
}
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.11/build/css/intlTelInput.min.css" crossorigin="anonymous">
<meta name="auth-geo-country" content="<?php echo htmlspecialchars($auth_geo_country, ENT_QUOTES, 'UTF-8'); ?>">
