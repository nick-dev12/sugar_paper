<?php
/**
 * Redirection client connecté vers la carte de suivi GPS d'une facture B2B.
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$bl_id = isset($_GET['bl_id']) ? (int) $_GET['bl_id'] : 0;
if ($bl_id < 1) {
    header('Location: mes-commandes.php');
    exit;
}

require_once __DIR__ . '/../models/model_livreur_tracking.php';
require_once __DIR__ . '/../models/model_bl.php';

$url = livreur_client_suivi_map_url_bl($bl_id, (int) $_SESSION['user_id']);
if (!$url) {
    header('Location: mes-commandes.php?suivi=indisponible');
    exit;
}

header('Location: ' . $url);
exit;
