<?php
/**
 * Connexion super administrateur
 */
require_once dirname(__DIR__) . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/paths.php';
require_once dirname(__DIR__) . '/controllers/controller_super_admin.php';

if (!empty($_SESSION['super_admin_id'])) {
    header('Location: ' . super_admin_href('dashboard.php'));
    exit;
}

require_once dirname(__DIR__) . '/models/model_super_admin.php';
if (!super_admin_exists()) {
    header('Location: ' . super_admin_href('inscription.php'));
    exit;
}

$csrf = super_admin_csrf_token();
$result = process_super_admin_login();

if (!empty($result['success']) && !empty($result['super_admin'])) {
    session_regenerate_persistent();
    $u = $result['super_admin'];
    $_SESSION['super_admin_id'] = (int) $u['id'];
    $_SESSION['super_admin_nom'] = $u['nom'] ?? '';
    $_SESSION['super_admin_prenom'] = $u['prenom'] ?? '';
    $_SESSION['super_admin_email'] = $u['email'] ?? '';
    $_SESSION['super_admin_csrf'] = bin2hex(random_bytes(32));
    header('Location: ' . super_admin_href('dashboard.php'));
    exit;
}

$ins_ok = '';
if (!empty($_SESSION['super_admin_inscription_ok'])) {
    $ins_ok = (string) $_SESSION['super_admin_inscription_ok'];
    unset($_SESSION['super_admin_inscription_ok']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Super Admin — Plateforme</title>
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
            max-width: 450px;
            padding: 40px;
        }
        .header { text-align: center; margin-bottom: 28px; }
        .header .icon {
            width: 72px; height: 72px; margin: 0 auto 14px;
            background: linear-gradient(135deg, #6b2f20, #c26638);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 28px;
        }
        .header h1 { font-size: 26px; color: var(--titres); margin-bottom: 8px; }
        .header p { color: var(--gris-moyen); font-size: 15px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #6b2f20; font-size: 14px; }
        .input-wrapper { position: relative; }
        .input-wrapper input {
            width: 100%; padding: 13px 16px 13px 44px;
            border: 1px solid rgba(53,100,166,.25);
            border-radius: 10px;
            font-size: 15px;
        }
        .input-wrapper i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--couleur-dominante); }
        .error-message, .success-message {
            padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px;
        }
        .error-message {
            background: var(--error-bg);
            border-left: 4px solid var(--orange);
            color: var(--titres);
        }
        .success-message {
            background: var(--success-bg);
            border-left: 4px solid var(--bleu);
            color: var(--titres);
        }
        .btn-submit {
            width: 100%; padding: 14px;
            background: #6b2f20;
            color: #fff; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 600; cursor: pointer;
            margin-top: 8px;
        }
        .btn-submit:hover { filter: brightness(1.05); }
        .footer-text { text-align: center; margin-top: 22px; font-size: 14px; color: var(--gris-moyen); }
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
                <div class="icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></div>
                <h1>Super administrateur</h1>
                <p>Accès réservé à la gestion de la marketplace</p>
            </div>

            <?php if ($ins_ok !== ''): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($ins_ok, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if (!empty($result['message']) && empty($result['success'])): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <input type="email" id="email" name="email" required autocomplete="username"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt"></i> Se connecter</button>
            </form>
            <p class="footer-text"><a href="/choix-connexion.php">Espace boutique / admin</a> — <a href="/index.php">Site public</a></p>
        </div>
    </div>
</body>
</html>
