<?php
/**
 * Complément téléphone après authentification Google (email fourni par Google, pas le téléphone).
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/firebase_auth_flow.php';
require_once __DIR__ . '/models/model_users.php';

$pending = firebase_auth_get_pending();

if (!$pending || empty($pending['uid']) || empty($pending['email'])) {
    firebase_auth_redirect_safe('/user/connexion.php');
}

$auth_provider = (isset($pending['provider']) && trim((string) $pending['provider']) === 'apple') ? 'apple' : 'google';
$provider_label = firebase_auth_pending_provider_label($pending);
$errors = [];

function google_complete_safe_redirect($redirect)
{
    $redirect = trim((string) $redirect);
    if ($redirect === '' || strpos($redirect, '//') !== false) {
        return '/index.php';
    }
    return $redirect[0] === '/' ? $redirect : '/' . $redirect;
}

function google_complete_name_from_pending(array $pending)
{
    $default_name = trim((string) ($pending['name'] ?? ''));
    $nom = '';
    $prenom = '';
    if ($default_name !== '') {
        $parts = preg_split('/\s+/', $default_name, 2);
        $nom = $parts[0] ?? '';
        $prenom = $parts[1] ?? '';
    }
    if ($nom === '' && !empty($pending['email'])) {
        $email = (string) $pending['email'];
        $local = strstr($email, '@', true);
        $nom = $local !== false && $local !== '' ? $local : 'Utilisateur';
    }
    if ($nom === '') {
        $nom = 'Utilisateur';
    }
    return [$nom, $prenom];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telephone = isset($_POST['telephone']) ? trim((string) $_POST['telephone']) : '';
    $telephone_digits = users_normalize_phone_digits($telephone);

    if ($telephone_digits === '' || strlen($telephone_digits) < 8) {
        $errors[] = 'Le numéro de téléphone est obligatoire.';
    } elseif (get_user_by_telephone($telephone_digits)) {
        $errors[] = 'Ce numéro de téléphone est déjà enregistré.';
    } elseif (user_email_exists($pending['email'])) {
        $errors[] = 'Cet email est déjà utilisé par un autre compte.';
    }

    if (empty($errors)) {
        list($nom, $prenom) = google_complete_name_from_pending($pending);
        $user_id = create_google_user($nom, $prenom, $pending['email'], $telephone_digits, $pending['uid'], $auth_provider);
        if ($user_id) {
            update_user_accepte_conditions((int) $user_id, true);
            $user = get_user_by_id((int) $user_id);
            firebase_auth_set_user_session($user);
            $redirect = google_complete_safe_redirect($pending['redirect'] ?? '/index.php');
            unset($_SESSION['firebase_auth_pending'], $_SESSION['google_auth_pending']);
            firebase_auth_redirect_safe($redirect);
        }
        $errors[] = 'Erreur lors de la création du compte. Réessayez.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Finaliser mon compte - Sugar Paper</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-pages.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body.auth-page {
            font-family: var(--font-corps);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-page .container {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        .auth-page .header { text-align: center; margin-bottom: 24px; }
        .auth-page .header h1 { color: var(--titres); font-size: 24px; margin-bottom: 8px; }
        .auth-page .header p { color: var(--texte-fonce); font-size: 14px; line-height: 1.5; }
        .auth-page .email-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(229, 72, 138, 0.1);
            color: var(--titres);
            font-size: 13px;
            font-weight: 600;
        }
        .auth-page .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--couleur-dominante);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .auth-page .error-message {
            background: rgba(229, 72, 138, 0.1);
            border-left: 4px solid var(--couleur-dominante);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
    </style>
</head>
<body class="auth-page">
    <div class="container">
        <div class="header">
            <h1>Finaliser mon compte</h1>
            <p>
                <?php echo htmlspecialchars($provider_label, ENT_QUOTES, 'UTF-8'); ?> a confirmé votre compte.
                Indiquez votre numéro de téléphone pour terminer l'inscription.
            </p>
            <span class="email-badge"><?php echo htmlspecialchars($pending['email'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="auth-google-complete.php">
            <div class="form-group">
                <label for="telephone"><i class="fas fa-phone"></i> Téléphone *</label>
                <div class="input-wrapper input-wrapper--intl-tel">
                    <input type="tel" id="telephone" name="telephone" placeholder="77 123 45 67" required
                        value="<?php echo htmlspecialchars($_POST['telephone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-check"></i> Valider et continuer
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }
        });
    </script>
    <?php include __DIR__ . '/includes/auth_intl_tel_scripts.php'; ?>
</body>
</html>
