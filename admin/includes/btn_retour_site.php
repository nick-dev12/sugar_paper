<?php
/**
 * Lien « Accueil site » — retour vers la boutique publique
 * Programmation procédurale uniquement
 */

if (!function_exists('get_public_root_uri_path')) {
    require_once __DIR__ . '/../../includes/site_url.php';
}

$site_home_url = rtrim(get_public_root_uri_path(), '/') . '/index.php';
?>
<a href="<?php echo htmlspecialchars($site_home_url); ?>" class="btn-primary btn-site-home"
    title="Retour à l'accueil du site">
    <i class="fas fa-house" aria-hidden="true"></i>
    <span>Accueil site</span>
</a>
