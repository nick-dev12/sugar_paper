<?php
/**
 * Redirection — contenu déplacé dans index.php?tab=livrees
 */
header('Location: index.php?tab=livrees' . (isset($_GET['jours_precedents']) && $_GET['jours_precedents'] === '1' ? '&jours_precedents=1' : ''));
exit;
