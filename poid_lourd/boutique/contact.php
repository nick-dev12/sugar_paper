<?php
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/_init.php';

$vd = isset($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']) && is_array($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY'])
    ? $GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']
    : [];
$tel_vendeur = trim((string) ($vd['telephone'] ?? ''));
$telephones_contact = $tel_vendeur !== '' ? [$tel_vendeur] : ['+221 33 870 00 70'];
$contact_boutique_nom = trim((string) ($vd['boutique_nom'] ?? ''));
if ($contact_boutique_nom === '' && defined('BOUTIQUE_NOM')) {
    $contact_boutique_nom = (string) BOUTIQUE_NOM;
}
$contact_vendeur_nom = trim(trim((string) ($vd['prenom'] ?? '')) . ' ' . trim((string) ($vd['nom'] ?? '')));

// Meta SEO
require_once __DIR__ . '/../includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Contact — ' . $contact_boutique_nom;
$seo_description = 'Contactez ' . $contact_boutique_nom . ' : téléphone et coordonnées.';
$seo_canonical = $base . '/boutique/contact.php?boutique=' . rawurlencode(defined('BOUTIQUE_SLUG') ? BOUTIQUE_SLUG : '');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/../includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <style>
        .contact-page {
            max-width: 560px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .contact-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .contact-header h1 {
            font-size: 36px;
            color: var(--titres);
            margin-bottom: 12px;
        }

        .contact-header p {
            font-size: 16px;
            color: var(--texte-fonce);
            opacity: 0.9;
        }

        .contact-info {
            background: var(--blanc);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--glass-shadow);
        }

        .contact-info h3 {
            font-size: 20px;
            color: var(--titres);
            margin-bottom: 20px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: var(--texte-fonce);
        }

        .contact-item i {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bleu-pale);
            color: var(--couleur-dominante);
            border-radius: 12px;
            font-size: 18px;
        }

        .contact-item a {
            color: var(--couleur-dominante);
            text-decoration: none;
        }

        .contact-item a:hover {
            text-decoration: underline;
        }

        .contact-map {
            margin-top: 50px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--glass-shadow);
            border: 1px solid var(--glass-border);
        }

        .contact-map h3 {
            font-size: 20px;
            color: var(--titres);
            margin-bottom: 16px;
            padding: 0 4px;
        }

        .contact-map iframe {
            width: 100%;
            height: 350px;
            border: 0;
            display: block;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../nav_bar.php'; ?>

    <div class="contact-page">
        <div class="contact-header">
            <h1><i class="fas fa-phone"></i> Contact — <?php echo htmlspecialchars($contact_boutique_nom); ?></h1>
            <p>Une question sur les produits de cette boutique ? Utilisez les coordonnées ci-dessous.</p>
        </div>

        <div class="contact-info">
            <h3><i class="fas fa-store"></i> Informations du vendeur</h3>
            <?php if ($contact_vendeur_nom !== ''): ?>
            <div class="contact-item">
                <i class="fas fa-user"></i>
                <div>
                    <strong>Contact</strong><br>
                    <?php echo htmlspecialchars($contact_vendeur_nom); ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="contact-item">
                <i class="fas fa-phone"></i>
                <div>
                    <strong>Téléphone</strong><br>
                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $telephones_contact[0]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($telephones_contact[0]); ?></a>
                </div>
            </div>
        </div>

        <?php if (!defined('BOUTIQUE_ADMIN_ID')): ?>
        <div class="contact-map">
            <h3><i class="fas fa-map-marker-alt"></i> Notre localisation</h3>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3938.0!2d-17.4292527!3d14.7417241!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xec10dd1a8097e29%3A0x23db771389d8414!2sSugar%20Paper%20S%C3%A9n%C3%A9gal!5e0!3m2!1sen!2ssn!4v1730000000!5m2!1sen!2ssn"
                allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                title="Localisation — COLObanes marketplace Sénégal">
            </iframe>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../footer.php'; ?>
</body>

</html>
