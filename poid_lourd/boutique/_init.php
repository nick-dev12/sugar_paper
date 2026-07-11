<?php
/**
 * Contexte boutique : à inclure juste après session_start dans les pages boutique/*
 */
require_once __DIR__ . '/../includes/boutique_context.php';
require_once __DIR__ . '/../includes/marketplace_helpers.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

boutique_bootstrap_or_404();

$GLOBALS['nav_home'] = boutique_url('index.php', BOUTIQUE_SLUG);
$GLOBALS['nav_produits'] = boutique_url('produits.php', BOUTIQUE_SLUG);
$GLOBALS['nav_panier'] = boutique_url('panier.php', BOUTIQUE_SLUG);
$GLOBALS['nav_panier_login_redirect'] = boutique_url('panier.php', BOUTIQUE_SLUG);
$GLOBALS['nav_nouveautes'] = boutique_url('nouveautes.php', BOUTIQUE_SLUG);
$GLOBALS['nav_promo'] = boutique_url('promo.php', BOUTIQUE_SLUG);
$GLOBALS['nav_contact'] = boutique_url('contact.php', BOUTIQUE_SLUG);
