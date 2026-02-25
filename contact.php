<?php
session_start();

$message_envoye = false;
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($csrf) || !isset($_SESSION['contact_csrf']) || !hash_equals($_SESSION['contact_csrf'], $csrf)) {
        $erreur = 'Session expirée. Veuillez réessayer.';
    } else {
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $sujet = isset($_POST['sujet']) ? trim($_POST['sujet']) : '';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';

        if (empty($nom) || empty($email) || empty($message)) {
            $erreur = 'Veuillez remplir tous les champs obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = 'Adresse email invalide.';
        } else {
            $message_envoye = true;
            unset($_SESSION['contact_csrf']);
        }
    }
}
if (!isset($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}

$email_contact = 'service@sugarpaper.com';
$telephone_contact = '+221 77 120 20 41';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/a_style.css">
    <style>
        .contact-page { max-width: 900px; margin: 0 auto; padding: 40px 20px 80px; }
        .contact-header {
            text-align: center;
            margin-bottom: 50px;
        }
        .contact-header h1 { font-size: 36px; color: var(--titres); margin-bottom: 12px; }
        .contact-header p { font-size: 16px; color: var(--texte-fonce); opacity: 0.9; }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }
        @media (max-width: 768px) {
            .contact-grid { grid-template-columns: 1fr; }
        }
        .contact-info {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .contact-info h3 { font-size: 20px; color: var(--titres); margin-bottom: 20px; }
        .contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: var(--texte-fonce);
        }
        .contact-item i {
            width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(229, 72, 138, 0.1);
            color: var(--couleur-dominante);
            border-radius: 12px;
            font-size: 18px;
        }
        .contact-item a { color: var(--couleur-dominante); text-decoration: none; }
        .contact-item a:hover { text-decoration: underline; }
        .contact-form {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .contact-form h3 { font-size: 20px; color: var(--titres); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--texte-fonce); }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid rgba(0,0,0,0.08);
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--couleur-dominante);
        }
        .form-group textarea { min-height: 150px; resize: vertical; }
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
        .btn-submit:hover { background: rgba(229, 72, 138, 0.9); }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f8f8; color: #0d7377; border-left: 4px solid var(--turquoise); }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
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
                        <strong>Téléphone</strong><br>
                        <a href="tel:<?php echo preg_replace('/\s+/', '', $telephone_contact); ?>"><?php echo htmlspecialchars($telephone_contact); ?></a>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <h3><i class="fas fa-paper-plane"></i> Envoyez-nous un message</h3>
                <?php if ($message_envoye): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Merci ! Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.
                </div>
                <?php elseif (!empty($erreur)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($erreur); ?>
                </div>
                <?php endif; ?>
                <form method="post" action="/contact.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['contact_csrf']); ?>">
                    <div class="form-group">
                        <label for="nom">Nom complet *</label>
                        <input type="text" id="nom" name="nom" required
                            value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="sujet">Sujet</label>
                        <input type="text" id="sujet" name="sujet"
                            value="<?php echo isset($_POST['sujet']) ? htmlspecialchars($_POST['sujet']) : ''; ?>"
                            placeholder="Ex: Question sur une commande">
                    </div>
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" required placeholder="Votre message..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php include('footer.php'); ?>
</body>
</html>
