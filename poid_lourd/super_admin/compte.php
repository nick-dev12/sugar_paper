<?php
/**
 * Mon compte super administrateur — profil et mot de passe
 */
require_once __DIR__ . '/includes/require_login.php';
require_once dirname(__DIR__) . '/models/model_super_admin.php';
require_once dirname(__DIR__) . '/controllers/controller_super_admin.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$compte = get_super_admin_by_id($sa_id);
if (!$compte) {
    header('Location: logout.php');
    exit;
}

$csrf = super_admin_csrf_token();
$profile_result = ['success' => false, 'message' => ''];
$password_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $profile_result = process_super_admin_update_own_profile();
    if (!empty($profile_result['success'])) {
        $compte = get_super_admin_by_id($sa_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $password_result = process_super_admin_update_own_password();
}

$nom_val = isset($_POST['nom']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])
    ? trim((string) $_POST['nom'])
    : trim((string) ($compte['nom'] ?? ''));
$prenom_val = isset($_POST['prenom']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])
    ? trim((string) $_POST['prenom'])
    : trim((string) ($compte['prenom'] ?? ''));
$email_val = isset($_POST['email']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])
    ? trim((string) $_POST['email'])
    : trim((string) ($compte['email'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon compte — Super Admin</title>
    <?php require_once dirname(__DIR__) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-comptes.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-comptes-page sa-compte-page">
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="sa-comptes-shell">
        <header class="sa-users-hero" aria-labelledby="sa-compte-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-id-card" aria-hidden="true"></i> Paramètres personnels</p>
                    <h1 class="sa-users-hero__title" id="sa-compte-title">Mon compte</h1>
                    <p class="sa-users-hero__lead">
                        Modifiez vos informations et changez votre mot de passe de connexion.
                    </p>
                </div>
            </div>
        </header>

        <div class="sa-compte-grid">
            <section class="sa-compte-card" aria-labelledby="sa-profil-title">
                <h2 class="sa-compte-card__title" id="sa-profil-title">
                    <i class="fas fa-user-pen" aria-hidden="true"></i> Informations
                </h2>

                <?php if (!empty($profile_result['message'])): ?>
                <div class="sa-alert sa-alert--<?php echo !empty($profile_result['success']) ? 'ok' : 'err'; ?>" role="<?php echo !empty($profile_result['success']) ? 'status' : 'alert'; ?>" style="margin-bottom:1rem;">
                    <i class="fas fa-<?php echo !empty($profile_result['success']) ? 'check-circle' : 'exclamation-circle'; ?>" aria-hidden="true"></i>
                    <span><?php echo $profile_result['message']; ?></span>
                </div>
                <?php endif; ?>

                <form method="post" action="compte.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="sa-comptes-field">
                        <label for="profil_nom">Nom</label>
                        <input type="text" id="profil_nom" name="nom" required maxlength="120"
                            value="<?php echo htmlspecialchars($nom_val, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="sa-comptes-field">
                        <label for="profil_prenom">Prénom <span style="font-weight:400;color:var(--gris-moyen,#737373);">(optionnel)</span></label>
                        <input type="text" id="profil_prenom" name="prenom" maxlength="120"
                            value="<?php echo htmlspecialchars($prenom_val, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="sa-comptes-field">
                        <label for="profil_email">Adresse e-mail</label>
                        <input type="email" id="profil_email" name="email" required
                            value="<?php echo htmlspecialchars($email_val, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <button type="submit" class="sa-comptes-btn-submit" style="width:100%;margin-top:0.25rem;">
                        <i class="fas fa-save" aria-hidden="true"></i> Enregistrer
                    </button>
                </form>
            </section>

            <section class="sa-compte-card" aria-labelledby="sa-mdp-title">
                <h2 class="sa-compte-card__title" id="sa-mdp-title">
                    <i class="fas fa-lock" aria-hidden="true"></i> Mot de passe
                </h2>

                <?php if (!empty($password_result['message'])): ?>
                <div class="sa-alert sa-alert--<?php echo !empty($password_result['success']) ? 'ok' : 'err'; ?>" role="<?php echo !empty($password_result['success']) ? 'status' : 'alert'; ?>" style="margin-bottom:1rem;">
                    <i class="fas fa-<?php echo !empty($password_result['success']) ? 'check-circle' : 'exclamation-circle'; ?>" aria-hidden="true"></i>
                    <span><?php echo $password_result['message']; ?></span>
                </div>
                <?php endif; ?>

                <form method="post" action="compte.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="update_password" value="1">

                    <div class="sa-comptes-field">
                        <label for="current_password">Mot de passe actuel</label>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="sa-comptes-field">
                        <label for="new_password">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="password" required autocomplete="new-password"
                            placeholder="10 car. min., maj., min., chiffre">
                    </div>
                    <div class="sa-comptes-field">
                        <label for="new_password_confirm">Confirmation</label>
                        <input type="password" id="new_password_confirm" name="password_confirm" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="sa-comptes-btn-submit" style="width:100%;margin-top:0.25rem;">
                        <i class="fas fa-key" aria-hidden="true"></i> Changer le mot de passe
                    </button>
                </form>
            </section>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
