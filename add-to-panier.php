<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Traitement de l'ajout direct au panier depuis les cartes produits
 * Redirige vers la page d'origine ou le panier avec un message
 */
session_start_persistent();

require_once __DIR__ . '/controllers/controller_panier.php';

// Méthode POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['produit_id'])) {
    header('Location: /index.php');
    exit;
}

$result = process_add_to_panier();

$user_logged_in = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;

if ($result['success']) {
    header('Location: /panier.php?added=1');
} else {
    if (!$user_logged_in) {
        header('Location: /panier.php?need_identity=1');
        exit;
    }
    $return_url = isset($_POST['return_url']) && $_POST['return_url'] !== '' ? $_POST['return_url'] : '/panier.php';
    $separator = (strpos($return_url, '?') !== false) ? '&' : '?';
    header('Location: ' . $return_url . $separator . 'error=' . urlencode($result['message']));
}
exit;
