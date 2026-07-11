<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

$message_envoye = false;
$erreur = '';

// Charger Composer (PHPMailer) pour l'envoi d'emails
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($csrf) || !isset($_SESSION['contact_csrf']) || !hash_equals($_SESSION['contact_csrf'], $csrf)) {
        $erreur = 'Session expirée. Veuillez réessayer.';
    } else {
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $sujet = isset($_POST['sujet']) ? trim($_POST['sujet']) : 'Contact';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';

        if (empty($nom) || empty($email) || empty($message)) {
            $erreur = 'Veuillez remplir tous les champs obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = 'Adresse email invalide.';
        } else {
            if (function_exists('mail_send_contact')) {
                $result = mail_send_contact($nom, $email, $sujet, $message);
                if ($result['success']) {
                    $message_envoye = true;
                    unset($_SESSION['contact_csrf']);
                } else {
                    $erreur = $result['error'] ?? 'Erreur lors de l\'envoi. Vérifiez config/email.php.';
                }
            } else {
                $erreur = 'Service email non configuré. Exécutez "composer install" et configurez config/email.php.';
            }
        }
    }
}
if (!isset($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}

$email_contact = 'contact@colobanes.com';
$adresse_contact = 'Rond Point Colobane, Dakar, Sénégal';

$contact_social_cfg = file_exists(__DIR__ . '/config/social.php') ? require __DIR__ . '/config/social.php' : [];
$contact_social_cfg = is_array($contact_social_cfg) ? $contact_social_cfg : [];
$__ig_s = trim((string) ($contact_social_cfg['instagram'] ?? ''));
$__fb_s = trim((string) ($contact_social_cfg['facebook'] ?? ''));
$__li_s = trim((string) ($contact_social_cfg['linkedin'] ?? ''));
$__tt_s = trim((string) ($contact_social_cfg['tiktok'] ?? ''));
$contact_show_social = $__ig_s !== '' || $__fb_s !== '' || $__li_s !== '' || $__tt_s !== '';

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
$base = get_site_base_url();
$seo_title = 'Contact — ' . SITE_BRAND_NAME . ' | Marketplace Sénégal';
$seo_description = 'Contactez ' . SITE_BRAND_NAME . ', marketplace des boutiques du Sénégal. Service client, partenariat vendeurs, questions sur votre commande.';
$seo_keywords = site_brand_seo_keywords_default() . ', contact marketplace, service client Sénégal';
$seo_canonical = $base . '/contact.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <?php if (!empty($contact_show_social)): ?>
    <link rel="stylesheet" href="/css/site-social-links.css<?php echo asset_version_query(); ?>">
    <?php endif; ?>
    <style>
        .contact-page {
            max-width: 900px;
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

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        @media (max-width: 768px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
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

        .contact-form {
            background: var(--blanc);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--glass-shadow);
        }

        .contact-form h3 {
            font-size: 20px;
            color: var(--titres);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--texte-fonce);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border-input);
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--couleur-dominante);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .btn-submit {
            padding: 14px 32px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background: var(--couleur-dominante-hover);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--bleu-fonce);
            border-left: 4px solid var(--bleu);
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
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
    <?php include('nav_bar.php'); ?>

    <div class="contact-page">
        <div class="contact-header">
            <h1><i class="fas fa-phone"></i> Contactez-nous</h1>
            <p>Une question ? Une suggestion ? N'hésitez pas à nous écrire.</p>
        </div>

        <div class="contact-grid">
            <div class="contact-info">
                <h3><i class="fas fa-info-circle"></i> Nos coordonnées</h3>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Localisation</strong><br>
                        <?php echo htmlspecialchars($adresse_contact); ?>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>Courrier</strong><br>
                        <a href="mailto:<?php echo htmlspecialchars($email_contact); ?>"><?php echo htmlspecialchars($email_contact); ?></a>
                    </div>
                </div>
                <?php if (!empty($contact_show_social)): ?>
                <div class="contact-social">
                    <h4><i class="fas fa-share-alt" aria-hidden="true"></i> Suivez-nous</h4>
                    <p>Rejoignez la communauté sur les réseaux sociaux.</p>
                    <?php
                    $social_config = $contact_social_cfg;
                    include __DIR__ . '/includes/social_links_block.php';
                    ?>
                </div>
                <?php endif; ?>
            </div>


        </div>

        <div class="contact-map">
            <h3><i class="fas fa-map-marker-alt"></i> Notre localisation</h3>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3858.0!2d-17.4486!3d14.7167!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xec112b5f6e5e6e5%3A0x6e5e5e5e5e5e5e5e!2sRond%20Point%20Colobane%2C%20Dakar!5e0!3m2!1sfr!2ssn!4v1700000000!5m2!1sfr!2ssn"
                allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                title="Localisation COLObanes - Rond Point Colobane, Dakar">
            </iframe>
        </div>
    </div>

    <?php include('footer.php'); ?>
</body>

</html>