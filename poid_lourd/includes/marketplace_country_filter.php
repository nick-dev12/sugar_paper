<?php
/**
 * Filtre marketplace par pays (session visiteur + détection IP).
 */

require_once __DIR__ . '/marketplace_countries.php';
require_once __DIR__ . '/ip_geo_resolver.php';

function marketplace_country_filter_applies(): bool
{
    // Filtre pays désactivé : tous les produits publiés visibles partout.
    return false;
}

function marketplace_ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (function_exists('session_touch_persistent_if_authenticated')) {
            session_touch_persistent_if_authenticated();
        }
        return;
    }
    if (!function_exists('session_start_persistent')) {
        require_once __DIR__ . '/session_user.php';
    }
    session_start_persistent();
}

function marketplace_country_welcome_done(): bool
{
    marketplace_ensure_session_started();
    return !empty($_SESSION['marketplace_country_welcome_done']);
}

function marketplace_mark_country_welcome_done(): void
{
    marketplace_ensure_session_started();
    $_SESSION['marketplace_country_welcome_done'] = true;
}

function marketplace_needs_country_welcome(): bool
{
    if (!marketplace_country_filter_applies()) {
        return false;
    }
    return !marketplace_country_welcome_done();
}

/**
 * Initialise le pays en session depuis l'IP après le choix initial de l'utilisateur.
 */
function marketplace_bootstrap_country_from_ip(): void
{
    if (!marketplace_country_filter_applies()) {
        return;
    }
    marketplace_ensure_session_started();
    if (!marketplace_country_welcome_done()) {
        return;
    }
    if (!empty($_SESSION['marketplace_country_set_by_user'])) {
        return;
    }
    $session_code = isset($_SESSION['marketplace_country_code'])
        ? (string) $_SESSION['marketplace_country_code']
        : '';
    if ($session_code !== '' && marketplace_country_is_valid($session_code)) {
        return;
    }
    $_SESSION['marketplace_country_code'] = ip_geo_detect_country_code();
    $_SESSION['marketplace_country_source'] = 'ip';
}

function marketplace_get_selected_country_code(): ?string
{
    if (!marketplace_country_filter_applies()) {
        return null;
    }
    marketplace_ensure_session_started();
    if (!marketplace_country_welcome_done()) {
        return null;
    }
    marketplace_bootstrap_country_from_ip();
    $code = isset($_SESSION['marketplace_country_code'])
        ? (string) $_SESSION['marketplace_country_code']
        : marketplace_country_default_code();
    return marketplace_country_is_valid($code) ? strtoupper($code) : marketplace_country_default_code();
}

function marketplace_clear_region_if_invalid_for_country(string $country): void
{
    require_once __DIR__ . '/geo_regions.php';
    require_once __DIR__ . '/marketplace_region_filter.php';
    marketplace_ensure_session_started();
    $region = isset($_SESSION['marketplace_region_code'])
        ? (string) $_SESSION['marketplace_region_code']
        : '';
    if ($region === '' || $region === 'all') {
        return;
    }
    if (!geo_region_is_valid($country, $region)) {
        marketplace_clear_selected_region();
    }
}

function marketplace_set_selected_country(string $code, bool $by_user = true): bool
{
    marketplace_ensure_session_started();
    $code = strtoupper(trim($code));
    if (!marketplace_country_is_valid($code)) {
        return false;
    }
    $_SESSION['marketplace_country_code'] = $code;
    $_SESSION['marketplace_country_source'] = $by_user ? 'user' : 'ip';
    if ($by_user) {
        $_SESSION['marketplace_country_set_by_user'] = true;
    }
    marketplace_mark_country_welcome_done();
    marketplace_clear_region_if_invalid_for_country($code);
    return true;
}

function marketplace_get_selected_country_label(): string
{
    $code = marketplace_get_selected_country_code();
    if ($code === null) {
        return '';
    }
    return marketplace_country_label($code);
}

/**
 * Pays suggéré à l'inscription vendeur (IP), sans écraser le choix manuel.
 */
function marketplace_detect_country_for_new_boutique(): string
{
    return ip_geo_detect_country_code();
}

function produit_visible_in_marketplace_country(array $produit): bool
{
    if (!marketplace_country_filter_applies()) {
        return true;
    }
    if (!marketplace_country_welcome_done()) {
        return true;
    }
    $country = marketplace_get_selected_country_code();
    if ($country === null) {
        return true;
    }
    $admin_id = (int) ($produit['admin_id'] ?? 0);
    if ($admin_id <= 0) {
        return false;
    }
    if (!function_exists('get_admin_by_id')) {
        require_once __DIR__ . '/../models/model_admin.php';
    }
    $admin = get_admin_by_id($admin_id);
    if (!$admin || ($admin['role'] ?? '') !== 'vendeur') {
        return false;
    }
    $boutique_country = strtoupper(trim((string) ($admin['boutique_country'] ?? 'SN')));
    if ($boutique_country === '') {
        $boutique_country = 'SN';
    }
    return $boutique_country === $country;
}
