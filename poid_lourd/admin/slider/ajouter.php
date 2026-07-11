<?php
/**
 * Redirection — ajout d'affiche via modal sur index.php
 */
require_once __DIR__ . '/../includes/require_admin_session.php';

header('Location: index.php?ajouter=1');
exit;
