<?php
/**
 * Mot de passe oublié — point d’entrée unique (clients + boutiques / admin vendeur).
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}
if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/controllers/controller_users.php';
require_once __DIR__ . '/controllers/controller_admin.php';

$account_type = 'client';
if (isset($_GET['type']) && (string) $_GET['type'] === 'boutique') {
    $account_type = 'boutique';
}

$result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_type = (isset($_POST['account_type']) && (string) $_POST['account_type'] === 'boutique') ? 'boutique' : 'client';
    $result = $account_type === 'boutique'
        ? process_forgot_password()
        : process_user_forgot_password();
}

require_once __DIR__ . '/includes/asset_version.php';
$vq = asset_version_query();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Mot de passe oublié — COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo $vq; ?>">
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
            position: relative;
            background-color: var(--fond-page);
        }
        .auth-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 12px 30px;
            background: #fff;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            z-index: 100;
        }
        .auth-header .logo img {
            height: 55px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
        }
        .auth-content {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding-top: 80px;
        }
        .container {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 460px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: var(--couleur-dominante);
        }
        .header { text-align: center; margin-bottom: 26px; }
        .header .icon {
            width: 70px; height: 70px;
            background: var(--couleur-dominante);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: var(--texte-clair);
            font-size: 30px;
        }
        .header h1 {
            color: var(--titres);
            font-size: 26px;
            margin-bottom: 8px;
            font-weight: 600;
            font-family: var(--font-titres);
        }
        .header p { color: var(--texte-fonce); font-size: 14px; opacity: 0.9; }
        .account-type {
            display: grid;
            gap: 10px;
            margin-bottom: 22px;
        }
        .account-type label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border: 2px solid rgba(53, 100, 166, 0.2);
            border-radius: 12px;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            font-size: 14px;
            color: var(--titres);
        }
        .account-type input { margin-top: 3px; accent-color: var(--couleur-dominante); }
        .account-type input:checked + span { font-weight: 600; }
        .account-type label:focus-within {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale, rgba(53, 100, 166, 0.08));
        }
        .account-type .hint { display: block; font-size: 12px; color: var(--gris-moyen); font-weight: 400; margin-top: 4px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            color: var(--titres);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-group input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(53, 100, 166, 0.2);
            border-radius: 8px;
            font-size: 15px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--texte-fonce);
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 3px rgba(53, 100, 166, 0.15);
        }
        .error-message {
            background: var(--error-bg);
            border-left: 4px solid var(--error-border);
            color: var(--titres);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.5;
        }
        .success-message {
            background: var(--success-bg);
            border-left: 4px solid var(--success-border);
            color: var(--bleu-fonce);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.5;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            box-shadow: var(--ombre-douce);
        }
        .btn-submit:hover { background: var(--couleur-dominante-hover); }
        .footer-text { text-align: center; margin-top: 22px; font-size: 14px; color: var(--texte-fonce); }
        .footer-text a { color: var(--couleur-dominante); font-weight: 600; text-decoration: none; }
        .footer-text a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="auth-header">
        <a class="logo" href="/index.php">
            <img src="/image/logo_market.png" alt="COLObanes">
        </a>
    </header>

    <div class="auth-content">
        <div class="container">
            <div class="header">
                <div class="icon"><i class="fas fa-key" aria-hidden="true"></i></div>
                <h1>Mot de passe oublié</h1>
                <p>Indiquez votre type de compte et l’adresse email liée au compte.</p>
            </div>

            <?php if (!empty($result['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($result['message']); ?>
                </div>
                <div class="footer-text">
                    <p><a href="/choix-connexion.php">Retour à la connexion</a></p>
                </div>
            <?php else: ?>
                <?php if (!empty($result['message'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="account-type" role="group" aria-label="Type de compte">
                        <label>
                            <input type="radio" name="account_type" value="client" <?php echo $account_type === 'client' ? 'checked' : ''; ?>>
                            <span>
                                Compte client (acheteur)
                                <span class="hint">Même email que sur votre espace acheteur.</span>
                            </span>
                        </label>
                        <label>
                            <input type="radio" name="account_type" value="boutique" <?php echo $account_type === 'boutique' ? 'checked' : ''; ?>>
                            <span>
                                Compte vendeur / boutique
                                <span class="hint">Email enregistré pour l’administration de votre boutique.</span>
                            </span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Adresse email</label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                            placeholder="vous@exemple.com"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Envoyer le lien de réinitialisation
                    </button>
                </form>

                <div class="footer-text">
                    <p><a href="/choix-connexion.php">Retour à la connexion</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include __DIR__ . '/includes/social_floating.php'; ?>
</body>
</html>
