<?php
/**
 * Ancienne page Produits livrés → redirection vers Mes commandes (onglet Reçues)
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: connexion.php');
    exit;
}

header('Location: mes-commandes.php?onglet=recues');
exit;
