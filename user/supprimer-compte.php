<?php
/**
 * Formulaire de suppression de compte client — connexion obligatoire
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../includes/asset_version.php';
require_once __DIR__ . '/../controllers/controller_users.php';
require_once __DIR__ . '/../models/model_fcm.php';

$redirect_after_login = '/user/supprimer-compte.php';

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] < 1) {
    header('Location: /user/connexion.php?redirect=' . rawurlencode($redirect_after_login));
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$user = get_user_by_id($user_id);

if (!$user) {
    $_SESSION = [];
    session_destroy();
    header('Location: /user/connexion.php?redirect=' . rawurlencode($redirect_after_login));
    exit;
}

if (empty($_SESSION['user_csrf'])) {
    $_SESSION['user_csrf'] = bin2hex(random_bytes(32));
}
$user_csrf = (string) $_SESSION['user_csrf'];

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = process_account_deletion($user_id);

    if (!empty($result['success'])) {
        delete_fcm_tokens_by_user($user_id);

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] !== '' ? $params['path'] : '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();

        header('Location: /index.php?compte_supprime=1');
        exit;
    }

    if (!empty($result['message'])) {
        $error_message = $result['message'];
    }
}

$has_pending = user_has_pending_orders($user_id);
$user_label = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Supprimer mon compte — Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/legal-page.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include __DIR__ . '/../nav_bar.php'; ?>

    <article class="legal-page">
        <h1><i class="fas fa-user-slash" aria-hidden="true"></i> Supprimer mon compte</h1>

        <p>
            Compte connecté&nbsp;: <strong><?php echo htmlspecialchars($user_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            <?php if (!empty($user['email'])): ?>
                (<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>)
            <?php elseif (!empty($user['telephone'])): ?>
                (<?php echo htmlspecialchars($user['telephone'], ENT_QUOTES, 'UTF-8'); ?>)
            <?php endif; ?>
        </p>

        <p>
            Cette action est <strong>irréversible</strong>. Consultez la
            <a href="/politique-suppression-compte.php">politique de suppression de compte</a>
            pour connaître les données effacées et conservées.
        </p>

        <?php if ($error_message !== ''): ?>
            <div class="legal-alert-error" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_pending): ?>
            <div class="legal-alert-login" role="alert">
                <p><strong>Suppression temporairement indisponible</strong></p>
                <p>
                    Vous avez des commandes en cours de traitement ou de livraison.
                    Finalisez-les ou contactez-nous à
                    <a href="mailto:sugarpaper26@gmail.com">sugarpaper26@gmail.com</a>
                    avant de supprimer votre compte.
                </p>
                <p><a href="/user/mes-commandes.php">Voir mes commandes</a></p>
            </div>
        <?php else: ?>
            <form class="legal-delete-form" method="post" action="/user/supprimer-compte.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($user_csrf, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="legal-form-group">
                    <label for="password">Mot de passe actuel <span aria-hidden="true">*</span></label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="Confirmez avec votre mot de passe">
                </div>

                <div class="legal-form-group">
                    <label for="confirm_text">Saisissez SUPPRIMER pour confirmer <span aria-hidden="true">*</span></label>
                    <input type="text" id="confirm_text" name="confirm_text" required autocomplete="off"
                           placeholder="SUPPRIMER" pattern="SUPPRIMER" title="Saisissez exactement SUPPRIMER">
                </div>

                <div class="legal-form-check">
                    <label>
                        <input type="checkbox" name="accepte_suppression" value="1" required>
                        Je comprends que la suppression de mon compte est définitive et que je perdrai l'accès à mon espace client.
                    </label>
                </div>

                <div class="legal-form-actions">
                    <button type="submit" class="legal-btn-delete">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer définitivement mon compte
                    </button>
                    <a href="/politique-suppression-compte.php" class="legal-btn-cancel">Annuler</a>
                </div>
            </form>
        <?php endif; ?>

        <a href="/user/mon-compte.php" class="back-link">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à mon compte
        </a>
    </article>

    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
