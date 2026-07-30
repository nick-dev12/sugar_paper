<?php
/**
 * Redirection client connecté vers la carte de suivi GPS livreur.
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$commande_id = isset($_GET['commande_id']) ? (int) $_GET['commande_id'] : 0;
if ($commande_id < 1) {
    header('Location: mes-commandes.php');
    exit;
}

require_once __DIR__ . '/../models/model_livreur_tracking.php';

$url = livreur_client_suivi_map_url($commande_id, (int) $_SESSION['user_id']);
if (!$url) {
    header('Location: commande-categorie.php?commande_id=' . $commande_id . '&suivi=indisponible');
    exit;
}

header('Location: ' . $url);
exit;
