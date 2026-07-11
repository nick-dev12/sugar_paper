<?php
/**
 * Redirection vers la page Stock (gestion des catégories)
 * Le contenu des catégories a été déplacé dans admin/stock/index.php
 */
require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';

header('Location: ../stock/index.php');
exit;
