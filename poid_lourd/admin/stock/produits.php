<?php
/**
 * Redirection : les produits ne sont plus liés aux articles en stock.
 * La gestion du stock utilise désormais la table produits et la colonne stock.
 */
require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';

header('Location: index.php');
exit;
