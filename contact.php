<?php
session_start();

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

$email_contact = 'sugarpaper26@gmail.com';
$telephones_contact = ['+221 77 416 12 12', '+221 33 823 35 14'];
$adresse_contact = 'Hann Mariste 2 LOT R/01';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Contact - Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/a_style.css">
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
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
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
            background: rgba(229, 72, 138, 0.1);
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
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
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
            border: 2px solid rgba(0, 0, 0, 0.08);
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
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background: rgba(229, 72, 138, 0.9);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #e8f8f8;
            color: #0d7377;
            border-left: 4px solid var(--turquoise);
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.08);
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
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>Email</strong><br>
                        <a href="mailto:<?php echo htmlspecialchars($email_contact); ?>"><?php echo htmlspecialchars($email_contact); ?></a>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <strong>Téléphones</strong><br>
                        <a href="tel:<?php echo preg_replace('/\s+/', '', $telephones_contact[0]); ?>"><?php echo htmlspecialchars($telephones_contact[0]); ?></a><br>
                        <a href="tel:<?php echo preg_replace('/\s+/', '', $telephones_contact[1]); ?>"><?php echo htmlspecialchars($telephones_contact[1]); ?></a>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Adresse</strong><br>
                        <?php echo htmlspecialchars($adresse_contact); ?>
                    </div>
                </div>
            </div>


        </div>

        <div class="contact-map">
            <h3><i class="fas fa-map-marker-alt"></i> Notre localisation</h3>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3938.0!2d-17.4292527!3d14.7417241!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xec10dd1a8097e29%3A0x23db771389d8414!2sSugar%20Paper%20S%C3%A9n%C3%A9gal!5e0!3m2!1sen!2ssn!4v1730000000!5m2!1sen!2ssn"
                allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                title="Localisation Sugar Paper Sénégal">
            </iframe>
        </div>
    </div>

    <?php include('footer.php'); ?>
</body>

</html>