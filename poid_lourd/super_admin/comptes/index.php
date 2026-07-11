<?php
/**
 * Gestion des comptes super administrateur
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

$sa_session_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$csrf = super_admin_csrf_token();
$form_open = !empty($_GET['ajouter']) || !empty($_POST['create_super_admin']);
$create_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_super_admin'])) {
    $create_result = process_super_admin_create_account();
    if (!empty($create_result['success'])) {
        $_SESSION['super_admin_flash_ok'] = $create_result['message'];
        header('Location: index.php');
        exit;
    }
    $form_open = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_statut'])) {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $_SESSION['super_admin_flash_err'] = 'Jeton de sécurité invalide.';
        header('Location: index.php');
        exit;
    }

    $target_id = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
    $nouveau = isset($_POST['nouveau_statut']) ? (string) $_POST['nouveau_statut'] : '';

    if ($target_id <= 0 || !in_array($nouveau, ['actif', 'inactif'], true)) {
        $_SESSION['super_admin_flash_err'] = 'Paramètres invalides.';
        header('Location: index.php');
        exit;
    }

    if ($target_id === $sa_session_id) {
        $_SESSION['super_admin_flash_err'] = 'Vous ne pouvez pas modifier votre propre statut ici.';
        header('Location: index.php');
        exit;
    }

    if ($nouveau === 'inactif' && super_admin_count_actifs() <= 1) {
        $target = get_super_admin_by_id($target_id);
        if ($target && ($target['statut'] ?? '') === 'actif') {
            $_SESSION['super_admin_flash_err'] = 'Impossible de désactiver le dernier compte actif.';
            header('Location: index.php');
            exit;
        }
    }

    if (super_admin_set_statut($target_id, $nouveau)) {
        super_admin_log_action(
            $sa_session_id,
            $nouveau === 'actif' ? 'super_admin_active' : 'super_admin_desactive',
            'super_admin',
            $target_id,
            ''
        );
        $_SESSION['super_admin_flash_ok'] = $nouveau === 'actif' ? 'Compte activé.' : 'Compte désactivé.';
    } else {
        $_SESSION['super_admin_flash_err'] = 'Erreur lors de la mise à jour.';
    }
    header('Location: index.php');
    exit;
}

$comptes = super_admin_list_all();
$total = count($comptes);
$total_actifs = super_admin_count_actifs();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptes super admin — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-comptes.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-comptes-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-comptes-shell">
        <header class="sa-users-hero" aria-labelledby="sa-comptes-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-user-shield" aria-hidden="true"></i> Administration plateforme</p>
                    <h1 class="sa-users-hero__title" id="sa-comptes-title">Comptes super administrateur</h1>
                    <p class="sa-users-hero__lead">
                        Créez et gérez les accès super admin : e-mail, nom et mot de passe sécurisé pour chaque compte.
                    </p>
                </div>
                <div class="sa-users-kpis" role="group" aria-label="Indicateurs">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Total</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $total; ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Actifs</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $total_actifs; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($msg_ok !== ''): ?>
            <div class="sa-alert sa-alert--ok" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_ok, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($msg_err !== ''): ?>
            <div class="sa-alert sa-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <div class="sa-comptes-toolbar">
            <p style="margin:0;color:var(--gris-moyen,#737373);font-size:0.88rem;">
                <?php echo (int) $total; ?> compte<?php echo $total > 1 ? 's' : ''; ?> enregistré<?php echo $total > 1 ? 's' : ''; ?>
            </p>
            <button type="button" class="sa-comptes-btn-add" id="saComptesOpenForm" aria-controls="saComptesFormLayer" aria-expanded="false">
                <i class="fas fa-user-plus" aria-hidden="true"></i> Ajouter un compte super admin
            </button>
        </div>

        <div class="sa-comptes-table-wrap">
            <table class="sa-comptes-table">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Créé le</th>
                        <th scope="col">Dernière connexion</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comptes)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--gris-moyen,#737373);">Aucun compte.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($comptes as $c):
                        $cid = (int) ($c['id'] ?? 0);
                        $is_self = ($cid === $sa_session_id);
                        $statut = (string) ($c['statut'] ?? 'inactif');
                        $display_nom = trim((string) ($c['prenom'] ?? '') . ' ' . (string) ($c['nom'] ?? ''));
                        if ($display_nom === '') {
                            $display_nom = (string) ($c['nom'] ?? '—');
                        }
                    ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($display_nom, ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($is_self): ?>
                            <span class="sa-comptes-badge sa-comptes-badge--self">Vous</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($c['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo !empty($c['date_creation']) ? date('d/m/Y H:i', strtotime((string) $c['date_creation'])) : '—'; ?></td>
                        <td><?php echo !empty($c['derniere_connexion']) ? date('d/m/Y H:i', strtotime((string) $c['derniere_connexion'])) : '—'; ?></td>
                        <td>
                            <span class="sa-comptes-badge sa-comptes-badge--<?php echo $statut === 'actif' ? 'actif' : 'inactif'; ?>">
                                <?php echo $statut === 'actif' ? 'Actif' : 'Inactif'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!$is_self): ?>
                            <form method="post" class="sa-comptes-toggle-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="toggle_statut" value="1">
                                <input type="hidden" name="target_id" value="<?php echo $cid; ?>">
                                <input type="hidden" name="nouveau_statut" value="<?php echo $statut === 'actif' ? 'inactif' : 'actif'; ?>">
                                <button type="submit" class="sa-comptes-btn-toggle">
                                    <?php echo $statut === 'actif' ? 'Désactiver' : 'Activer'; ?>
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="color:var(--gris-moyen,#737373);font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="sa-comptes-form-layer<?php echo $form_open ? ' is-open' : ''; ?>" id="saComptesFormLayer" role="dialog" aria-modal="true" aria-labelledby="saComptesFormTitle"<?php echo $form_open ? '' : ' hidden'; ?>>
        <div class="sa-comptes-form-panel">
            <div class="sa-comptes-form-panel__hd">
                <h2 class="sa-comptes-form-panel__title" id="saComptesFormTitle">Nouveau compte super admin</h2>
                <button type="button" class="sa-comptes-form-close" id="saComptesCloseForm" aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <?php if (!empty($create_result['message']) && empty($create_result['success'])): ?>
            <div class="sa-alert sa-alert--err" role="alert" style="margin-bottom:0.9rem;">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo $create_result['message']; ?></span>
            </div>
            <?php endif; ?>

            <form method="post" action="index.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="create_super_admin" value="1">

                <div class="sa-comptes-field">
                    <label for="sa_nom">Nom</label>
                    <input type="text" id="sa_nom" name="nom" required maxlength="120"
                        value="<?php echo isset($_POST['nom']) ? htmlspecialchars((string) $_POST['nom'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="sa-comptes-field">
                    <label for="sa_email">Adresse e-mail</label>
                    <input type="email" id="sa_email" name="email" required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars((string) $_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="sa-comptes-field">
                    <label for="sa_password">Mot de passe</label>
                    <input type="password" id="sa_password" name="password" required autocomplete="new-password"
                        placeholder="10 car. min., maj., min., chiffre">
                </div>
                <div class="sa-comptes-field">
                    <label for="sa_password_confirm">Confirmation du mot de passe</label>
                    <input type="password" id="sa_password_confirm" name="password_confirm" required autocomplete="new-password">
                </div>

                <div class="sa-comptes-form-actions">
                    <button type="button" class="sa-comptes-btn-cancel" id="saComptesCancelForm">Annuler</button>
                    <button type="submit" class="sa-comptes-btn-submit">
                        <i class="fas fa-user-plus" aria-hidden="true"></i> Créer le compte
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var layer = document.getElementById('saComptesFormLayer');
        var openBtn = document.getElementById('saComptesOpenForm');
        var closeBtn = document.getElementById('saComptesCloseForm');
        var cancelBtn = document.getElementById('saComptesCancelForm');

        function openForm() {
            if (!layer) return;
            layer.classList.add('is-open');
            layer.hidden = false;
            if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            var first = document.getElementById('sa_nom');
            if (first) first.focus();
        }

        function closeForm() {
            if (!layer) return;
            layer.classList.remove('is-open');
            layer.hidden = true;
            if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        if (openBtn) openBtn.addEventListener('click', openForm);
        if (closeBtn) closeBtn.addEventListener('click', closeForm);
        if (cancelBtn) cancelBtn.addEventListener('click', closeForm);
        if (layer) {
            layer.addEventListener('click', function (e) {
                if (e.target === layer) closeForm();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeForm();
        });

        <?php if ($form_open): ?>
        openForm();
        <?php endif; ?>
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
