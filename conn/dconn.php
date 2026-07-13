<?php
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

// Détruire la session en cours
session_destroy();
unset($_SESSION['commercant_id']);

// Rediriger vers la page de connexion ou une autre page après la déconnexion
header('location: ../commerçant.php');
exit();