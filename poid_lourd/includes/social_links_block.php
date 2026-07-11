<?php
/**
 * Liens réseaux sociaux (footer, page contact, etc.)
 * Nécessite variables.css / Font Awesome 6 (fab) pour les icônes brands.
 */
if (!isset($social_config) || !is_array($social_config)) {
    $social_config = [];
    if (file_exists(__DIR__ . '/../config/social.php')) {
        $social_config = require __DIR__ . '/../config/social.php';
    }
}

$instagram = isset($social_config['instagram']) ? trim((string) $social_config['instagram']) : '';
$facebook = isset($social_config['facebook']) ? trim((string) $social_config['facebook']) : '';
$linkedin = isset($social_config['linkedin']) ? trim((string) $social_config['linkedin']) : '';
$tiktok = isset($social_config['tiktok']) ? trim((string) $social_config['tiktok']) : '';

$has_social = ($instagram !== '' || $facebook !== '' || $linkedin !== '' || $tiktok !== '');
if (!$has_social) {
    return;
}
?>
<div class="site-social-links" role="list" aria-label="Nos réseaux sociaux">
    <?php if ($facebook !== ''): ?>
    <a class="site-social-links__a site-social-links__a--fb" href="<?php echo htmlspecialchars($facebook, ENT_QUOTES, 'UTF-8'); ?>"
        target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook" role="listitem">
        <i class="fab fa-facebook-f" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
    <?php if ($instagram !== ''): ?>
    <a class="site-social-links__a site-social-links__a--ig" href="<?php echo htmlspecialchars($instagram, ENT_QUOTES, 'UTF-8'); ?>"
        target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram" role="listitem">
        <i class="fab fa-instagram" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
    <?php if ($linkedin !== ''): ?>
    <a class="site-social-links__a site-social-links__a--li" href="<?php echo htmlspecialchars($linkedin, ENT_QUOTES, 'UTF-8'); ?>"
        target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn" role="listitem">
        <i class="fab fa-linkedin-in" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
    <?php if ($tiktok !== ''): ?>
    <a class="site-social-links__a site-social-links__a--tt" href="<?php echo htmlspecialchars($tiktok, ENT_QUOTES, 'UTF-8'); ?>"
        target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok" role="listitem">
        <i class="fab fa-tiktok" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
</div>
