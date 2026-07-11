<?php
/**
 * Filtre marketplace par région (session visiteur).
 */

require_once __DIR__ . '/geo_regions.php';
require_once __DIR__ . '/marketplace_country_filter.php';
require_once __DIR__ . '/marketplace_countries.php';

function marketplace_region_filter_applies(): bool
{
    return true;
}

/**
 * Pays de référence pour la liste des régions (SN par défaut si filtre pays inactif).
 */
function marketplace_region_context_country(): string
{
    $country = marketplace_get_selected_country_code();
    if ($country !== null && marketplace_country_supports_regions($country)) {
        return $country;
    }
    $default = marketplace_country_default_code();
    if (marketplace_country_supports_regions($default)) {
        return $default;
    }
    return 'SN';
}

function marketplace_get_selected_region_code(): ?string
{
    if (!marketplace_region_filter_applies()) {
        return null;
    }
    if (session_status() === PHP_SESSION_NONE) {
        marketplace_ensure_session_started();
    }
    $code = $_SESSION['marketplace_region_code'] ?? null;
    if ($code === null || $code === '' || $code === 'all') {
        return null;
    }
    $region = (string) $code;
    $country = marketplace_region_context_country();
    return geo_region_is_valid($country, $region) ? $region : null;
}

function marketplace_set_selected_region(string $code): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        marketplace_ensure_session_started();
    }
    $code = trim($code);
    if ($code === '' || $code === 'all') {
        marketplace_clear_selected_region();
        return true;
    }
    $country = marketplace_region_context_country();
    if (!geo_region_is_valid($country, $code)) {
        return false;
    }
    $_SESSION['marketplace_region_code'] = $code;
    return true;
}

function marketplace_clear_selected_region(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        marketplace_ensure_session_started();
    }
    unset($_SESSION['marketplace_region_code']);
}

function marketplace_get_selected_region_label(): string
{
    $code = marketplace_get_selected_region_code();
    if ($code === null) {
        return 'Toutes les régions';
    }
    return geo_region_label(marketplace_region_context_country(), $code);
}

function produit_visible_in_marketplace_region(array $produit): bool
{
    if (!produit_visible_in_marketplace_country($produit)) {
        return false;
    }
    if (!marketplace_region_filter_applies()) {
        return true;
    }
    $code = marketplace_get_selected_region_code();
    if ($code === null) {
        return true;
    }
    $admin_id = (int) ($produit['admin_id'] ?? 0);
    if ($admin_id <= 0) {
        return false;
    }
    if (!function_exists('admin_has_boutique_region_column')) {
        require_once __DIR__ . '/../models/model_admin.php';
    }
    if (!admin_has_boutique_region_column()) {
        return true;
    }
    if (!function_exists('get_admin_by_id')) {
        require_once __DIR__ . '/../models/model_admin.php';
    }
    $admin = get_admin_by_id($admin_id);
    if (!$admin || ($admin['role'] ?? '') !== 'vendeur') {
        return false;
    }
    return (string) ($admin['boutique_region'] ?? '') === $code;
}
