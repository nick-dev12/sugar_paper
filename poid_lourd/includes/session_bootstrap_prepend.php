<?php
/**
 * Ancien auto_prepend_file — désactivé.
 *
 * Ne plus charger via auto_prepend_file (chemin relatif cassé en /boutique/, /user/, etc.).
 * Conservé pour compatibilité si un php.ini serveur pointe encore ici.
 * La config session est faite par includes/session_user.php + session_start_persistent().
 */
if (session_status() !== PHP_SESSION_NONE) {
    return;
}

require_once __DIR__ . '/session_user.php';
session_configure_persistent();
