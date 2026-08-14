<?php
/**
 * Redirection client connecté vers la carte de suivi GPS d'une commande personnalisée.
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$cp_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($cp_id < 1) {
    header('Location: mes-commandes.php');
    exit;
}

require_once __DIR__ . '/../models/model_livreur_tracking.php';

$url = livreur_client_suivi_map_url_cp($cp_id, (int) $_SESSION['user_id']);
if (!$url) {
    header('Location: commande-personnalisee-details.php?id=' . $cp_id . '&suivi=indisponible');
    exit;
}

header('Location: ' . $url);
exit;
