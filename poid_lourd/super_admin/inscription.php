<?php
require_once __DIR__ . '/../includes/session_admin.php';
/**
 * Création du premier compte super administrateur (une seule fois)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start_persistent();
}

require_once __DIR__ . '/includes/paths.php';
require_once dirname(__DIR__) . '/controllers/controller_super_admin.php';
require_once dirname(__DIR__) . '/models/model_super_admin.php';

if (!empty($_SESSION['super_admin_id'])) {
    header('Location: ' . super_admin_href('dashboard.php'));
    exit;
}

if (super_admin_exists()) {
    header('Location: ' . super_admin_href('login.php'));
    exit;
}

$csrf = super_admin_csrf_token();
$result = process_super_admin_inscription();

if (!empty($result['success'])) {
    $_SESSION['super_admin_inscription_ok'] = $result['message'];
    header('Location: ' . super_admin_href('login.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Super Admin — Plateforme</title>
    <?php require_once dirname(__DIR__) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-corps);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: var(--fond-page);
        }
        .auth-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 12px 30px;
            background: #ffffff;
            border-bottom: 1px solid rgba(0,0,0,.08);
            z-index: 100;
        }
        .auth-header .logo img { height: 55px; width: auto; max-width: 140px; object-fit: contain; }
        .auth-content { width: 100%; display: flex; justify-content: center; align-items: center; flex: 1; padding-top: 80px; }
        .container {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 480px;
            padding: 40px;
        }
        .header { text-align: center; margin-bottom: 24px; }
        .header .icon {
            width: 72px; height: 72px; margin: 0 auto 14px;
            background: linear-gradient(135deg, #6b2f20, #c26638);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 28px;
        }
        .header h1 { font-size: 24px; color: var(--titres); }
        .header p { color: var(--gris-moyen); font-size: 14px; margin-top: 8px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #6b2f20; font-size: 14px; }
        .form-group input {
            width: 100%; padding: 12px 14px;
            border: 1px solid rgba(53,100,166,.25);
            border-radius: 10px;
            font-size: 15px;
        }
        .error-message {
            padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px;
            background: var(--error-bg);
            border-left: 4px solid var(--orange);
        }
        .btn-submit {
            width: 100%; padding: 14px;
            background: #6b2f20;
            color: #fff; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 600; cursor: pointer;
            margin-top: 8px;
        }
        .footer-text { text-align: center; margin-top: 20px; font-size: 14px; }
        .footer-text a { color: var(--couleur-dominante); font-weight: 600; text-decoration: none; }
    </style>
</head>

<body>
    <header class="auth-header">
        <a class="logo" href="/index.php"><img src="/image/logo_market.png" alt="COLObanes"></a>
    </header>
    <div class="auth-content">
        <div class="container">
            <div class="header">
                <div class="icon"><i class="fas fa-user-shield" aria-hidden="true"></i></div>
                <h1>Premier compte super admin</h1>
                <p>Cette page n’est disponible qu’aucun super administrateur n’existe encore. Choisissez un mot de passe fort.</p>
            </div>

            <?php if (!empty($result['message']) && empty($result['success'])): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" required maxlength="120"
                        value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" required maxlength="120"
                        value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe (10 caractères min., maj., min., chiffre)</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmation</label>
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-user-plus"></i> Créer le compte</button>
            </form>
            <p class="footer-text"><a href="<?php echo htmlspecialchars(super_admin_href('login.php'), ENT_QUOTES, 'UTF-8'); ?>">Retour à la connexion</a></p>
        </div>
    </div>
</body>
</html>
