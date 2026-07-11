<?php
/**
 * Redirection — modification d'affiche via modal sur index.php
 */
require_once __DIR__ . '/../includes/require_admin_session.php';

$slide_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($slide_id <= 0) {
    header('Location: index.php');
    exit;
}

header('Location: index.php?modifier=' . $slide_id);
exit;
