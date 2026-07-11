<?php
/**
 * Suppression d'un devis (POST, brouillon uniquement)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis()) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../invoice/index.php?tab=devis');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['devis_erreur'] = 'Session expirée. Réessayez.';
    header('Location: ../invoice/index.php?tab=devis');
    exit;
}

$devis_id = isset($_POST['devis_id']) ? (int) $_POST['devis_id'] : 0;
require_once __DIR__ . '/../../models/model_devis.php';

if ($devis_id > 0 && delete_devis($devis_id)) {
    $_SESSION['success_message'] = 'Le devis a été supprimé.';
} else {
    $_SESSION['devis_erreur'] = 'Suppression impossible (devis introuvable, déjà facturé ou statut différent de brouillon).';
}

header('Location: ../invoice/index.php?tab=devis');
exit;
