<?php
/**
 * Comptes d'accès internes (administration) — réservé au rôle administrateur
 * Comptes boutique : collaborateurs créés par le vendeur titulaire (téléphone + mot de passe à la connexion vendeur).
 */
require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_gestion_clients_comptes()) {
    $_SESSION['error_message'] = 'Accès réservé aux administrateurs, à la plateforme, aux vendeurs ou aux RH.';
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../models/model_vendeur_comptes_acces.php';

$__role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$__is_collab = admin_is_vendeur_collaborateur();
$__is_vendeur_owner = ($__role === 'vendeur' && !$__is_collab);
$__show_internal_admins = ($__role !== 'vendeur');

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), (string) $tok)) {
        $error_message = 'Session expirée ou jeton de sécurité invalide. Veuillez réessayer.';
    } elseif (isset($_POST['creer_compte_acces_boutique']) && $__is_vendeur_owner) {
        $nom_raw = isset($_POST['collab_nom']) ? trim((string) $_POST['collab_nom']) : '';
        $tel_raw = isset($_POST['collab_telephone']) ? (string) $_POST['collab_telephone'] : '';
        $pw1 = isset($_POST['collab_password']) ? (string) $_POST['collab_password'] : '';
        $pw2 = isset($_POST['collab_password_confirm']) ? (string) $_POST['collab_password_confirm'] : '';

        $nom_len = function_exists('mb_strlen') ? mb_strlen($nom_raw) : strlen($nom_raw);
        if ($nom_raw === '' || $nom_len > 190) {
            $error_message = 'Indiquez un nom valide (obligatoire, 190 caractères max).';
        } else {
            $tel_n = vendeur_compte_acces_normalize_tel($tel_raw);
            if ($tel_n === '') {
                $error_message = 'Le numéro de téléphone est obligatoire.';
            } elseif (strlen($pw1) < 6) {
                $error_message = 'Le mot de passe doit contenir au moins 6 caractères.';
            } elseif ($pw1 !== $pw2) {
                $error_message = 'Les deux mots de passe ne correspondent pas.';
            } elseif (!vendeur_compte_acces_telephone_libre($tel_n)) {
                $error_message = 'Ce numéro est déjà utilisé (compte admin ou autre collaborateur).';
            } else {
                $hash = password_hash($pw1, PASSWORD_BCRYPT);
                $new_id = create_vendeur_compte_acces((int) $_SESSION['admin_id'], $nom_raw, $tel_n, $hash);
                if ($new_id) {
                    $success_message = 'Compte d’accès créé. La personne peut se connecter avec son téléphone et ce mot de passe (écran connexion vendeur).';
                } else {
                    $error_message = 'Impossible de créer le compte. Vérifiez les données ou réessayez.';
                }
            }
        }
    } elseif (isset($_POST['toggle_compte_acces_statut']) && $__is_vendeur_owner) {
        $cid = isset($_POST['compte_acces_id']) ? (int) $_POST['compte_acces_id'] : 0;
        $nouveau = $_POST['nouveau_statut_collab'] ?? '';
        if ($cid <= 0 || !in_array($nouveau, ['actif', 'inactif'], true)) {
            $error_message = 'Action invalide.';
        } elseif (update_vendeur_compte_acces_statut($cid, (int) $_SESSION['admin_id'], $nouveau)) {
            $success_message = $nouveau === 'actif' ? 'Compte d’accès activé.' : 'Compte d’accès désactivé.';
        } else {
            $error_message = 'Impossible de modifier ce compte.';
        }
    } elseif (!empty($_POST['admin_id']) && $__show_internal_admins) {
        $admin_id = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;

        if ($admin_id > 0) {
            if ($admin_id === (int) $_SESSION['admin_id']) {
                $error_message = 'Vous ne pouvez pas modifier votre propre compte depuis cette page.';
            } else {
                if (isset($_POST['toggle_statut'])) {
                    $nouveau_statut = $_POST['nouveau_statut'] ?? '';
                    if (in_array($nouveau_statut, ['actif', 'inactif']) && update_admin_statut($admin_id, $nouveau_statut)) {
                        $success_message = $nouveau_statut === 'actif' ? 'Compte activé avec succès.' : 'Compte désactivé avec succès.';
                    } else {
                        $error_message = 'Erreur lors de la modification du statut.';
                    }
                } elseif (isset($_POST['definir_role'])) {
                    $nouveau_role = $_POST['nouveau_role'] ?? '';
                    if (in_array($nouveau_role, admin_roles_valides(), true) && update_admin_role($admin_id, $nouveau_role)) {
                        $success_message = 'Rôle mis à jour avec succès.';
                    } else {
                        $error_message = 'Erreur lors de la modification du rôle.';
                    }
                }
            }
        }
    }
}

$comptes_acces_boutique = [];
if ($__role === 'vendeur') {
    $comptes_acces_boutique = get_vendeur_comptes_acces_by_vendeur_id((int) $_SESSION['admin_id']);
}
$total_collabs = count($comptes_acces_boutique);
$collabs_actifs = count(array_filter($comptes_acces_boutique, function ($c) {
    return ($c['statut'] ?? '') === 'actif';
}));

$admins = $__show_internal_admins ? get_all_admins() : [];
$total = count($admins);
$admins_actifs = count(array_filter($admins, function ($a) {
    return $a['statut'] === 'actif';
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptes d’accès — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-users-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-comptes-page.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes">
    <?php include '../includes/nav.php'; ?>

    <header class="comptes-header-bar">
        <div>
            <h1>
                <i class="fas fa-user-shield" aria-hidden="true"></i>
                <?php echo $__role === 'vendeur' ? 'Comptes d’accès boutique' : 'Comptes d’accès administration'; ?>
            </h1>
            <?php if ($__role === 'vendeur'): ?>
                <p class="comptes-lead">
                    <?php if ($__is_collab): ?>
                        Vous êtes connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['vendeur_collaborateur_nom'] ?? 'collaborateur', ENT_QUOTES, 'UTF-8'); ?></strong>.
                        Seul le titulaire de la boutique peut créer ou désactiver des comptes d’accès.
                    <?php else: ?>
                        Créez des accès pour des personnes qui se connectent avec le <strong>numéro de téléphone</strong> et le <strong>mot de passe</strong> définis ici (connexion vendeur sur la page <a href="/choix-connexion.php">Connexion</a>).
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="comptes-header-actions">
            <?php if ($__show_internal_admins): ?>
            <a href="../inscription-admin.php" class="btn-open-emp-modal btn-inscription-admin-link">
                <i class="fas fa-user-plus"></i> Ajouter un compte d’accès
            </a>
            <?php endif; ?>
        </div>
    </header>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($__role === 'vendeur'): ?>
    <div class="users-stats">
        <div class="stat-box">
            <h3>Collaborateurs</h3>
            <div class="stat-value"><?php echo (int) $total_collabs; ?></div>
        </div>
        <div class="stat-box">
            <h3>Accès actifs</h3>
            <div class="stat-value"><?php echo (int) $collabs_actifs; ?></div>
        </div>
    </div>

    <?php if ($__is_vendeur_owner): ?>
    <h2 class="hub-section-title"><i class="fas fa-user-plus"></i> Ajouter un compte d’accès</h2>
    <div class="comptes-emp-form-modal comptes-acces-add-card" style="max-width: 560px; margin-bottom: 28px; border-radius: 18px; border: 1px solid var(--border-input); background: var(--glass-bg); box-shadow: var(--ombre-douce);">
        <form method="post" action="" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="creer_compte_acces_boutique" value="1">
            <div class="form-group">
                <label for="collab_nom">Nom</label>
                <input type="text" id="collab_nom" name="collab_nom" required maxlength="190"
                       value="<?php echo isset($_POST['collab_nom']) ? htmlspecialchars((string) $_POST['collab_nom'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label for="collab_telephone">Numéro de téléphone</label>
                <input type="text" id="collab_telephone" name="collab_telephone" required inputmode="tel" autocomplete="off"
                       value="<?php echo isset($_POST['collab_telephone']) ? htmlspecialchars((string) $_POST['collab_telephone'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label for="collab_password">Mot de passe</label>
                    <input type="password" id="collab_password" name="collab_password" required minlength="6" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="collab_password_confirm">Confirmer le mot de passe</label>
                    <input type="password" id="collab_password_confirm" name="collab_password_confirm" required minlength="6" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn-submit-emp" style="width: auto; min-width: 220px;">
                <i class="fas fa-check"></i> Créer le compte d’accès
            </button>
        </form>
    </div>
    <?php endif; ?>

    <h2 class="hub-section-title"><i class="fas fa-users"></i> Comptes d’accès et détails</h2>
    <p class="section-subtitle" style="margin: -8px 0 20px; max-width: 720px;">
        Chaque ligne correspond à une personne autorisée à ouvrir votre espace vendeur. Connexion : téléphone + code (même écran que pour le titulaire).
    </p>

    <?php if (empty($comptes_acces_boutique)): ?>
        <div class="empty-state">
            <i class="fas fa-user-friends"></i>
            <h3>Aucun compte d’accès</h3>
            <p><?php echo $__is_vendeur_owner ? 'Ajoutez un collaborateur avec le formulaire ci-dessus.' : 'Le titulaire n’a pas encore créé de compte d’accès.'; ?></p>
        </div>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($comptes_acces_boutique as $c): ?>
                <?php
                $cid = (int) $c['id'];
                $is_me = $__is_collab && isset($_SESSION['vendeur_collaborateur_id']) && (int) $_SESSION['vendeur_collaborateur_id'] === $cid;
                ?>
                <div class="user-card <?php echo ($c['statut'] ?? '') === 'inactif' ? 'inactive' : ''; ?>">
                    <div class="card-header-wrap">
                        <div class="card-badges">
                            <span class="user-statut statut-<?php echo htmlspecialchars($c['statut'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo ($c['statut'] ?? '') === 'actif' ? 'Actif' : 'Inactif'; ?>
                            </span>
                            <?php if ($is_me): ?>
                                <span class="role-badge role-vendeur" style="background: var(--bleu-pale); color: var(--couleur-dominante);">Vous</span>
                            <?php endif; ?>
                        </div>
                        <div class="user-header">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr((string) $c['nom'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars((string) $c['nom'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars((string) $c['telephone'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="user-details">
                            <div class="detail-item">
                                <label>Date de création</label>
                                <div class="value"><?php echo date('d/m/Y H:i', strtotime((string) $c['date_creation'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <label>Dernière connexion</label>
                                <div class="value"><?php echo !empty($c['derniere_connexion']) ? date('d/m/Y H:i', strtotime((string) $c['derniere_connexion'])) : '—'; ?></div>
                            </div>
                        </div>
                        <?php if ($__is_vendeur_owner): ?>
                        <div class="user-actions" style="flex-wrap: wrap;">
                            <?php if (($c['statut'] ?? '') === 'actif'): ?>
                                <form method="post" action="" class="user-action-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="compte_acces_id" value="<?php echo $cid; ?>">
                                    <input type="hidden" name="nouveau_statut_collab" value="inactif">
                                    <button type="submit" name="toggle_compte_acces_statut" class="btn-action btn-deactivate"
                                            onclick="return confirm('Désactiver ce compte d’accès ?');">
                                        <i class="fas fa-ban"></i> Désactiver
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="" class="user-action-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="compte_acces_id" value="<?php echo $cid; ?>">
                                    <input type="hidden" name="nouveau_statut_collab" value="actif">
                                    <button type="submit" name="toggle_compte_acces_statut" class="btn-action btn-activate"
                                            onclick="return confirm('Réactiver ce compte ?');">
                                        <i class="fas fa-check"></i> Activer
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($__is_collab): ?>
                        <p style="margin: 0; font-size: 0.9rem; color: var(--gris-moyen);">Modification réservée au titulaire.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php else: ?>

    <div class="users-stats">
        <div class="stat-box">
            <h3>Comptes (interne)</h3>
            <div class="stat-value"><?php echo $total; ?></div>
        </div>
        <div class="stat-box">
            <h3>Actifs</h3>
            <div class="stat-value"><?php echo $admins_actifs; ?></div>
        </div>
    </div>

    <h2 class="hub-section-title"><i class="fas fa-users-gear"></i> Utilisateurs de l’espace admin</h2>
    <p class="section-subtitle" style="margin: -8px 0 20px; max-width: 720px;">
        Connexions à l’administration (rôles : commercial, comptabilité, RH, etc.). Distinct des
        <strong>clients du site</strong> (<a href="../users/index.php">Clients du site</a>).
    </p>

    <?php if (empty($admins)): ?>
        <div class="empty-state">
            <i class="fas fa-user-shield"></i>
            <h3>Aucun compte</h3>
            <p>Aucun compte administrateur n'est enregistré.</p>
            <a href="../inscription-admin.php" class="btn-primary"><i class="fas fa-plus"></i> Créer le premier compte</a>
        </div>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($admins as $admin): ?>
                <?php $is_self = ($admin['id'] == $_SESSION['admin_id']); ?>
                <div class="user-card <?php echo $admin['statut'] === 'inactif' ? 'inactive' : ''; ?>">
                    <div class="card-header-wrap">
                        <div class="card-badges">
                            <span class="user-statut statut-<?php echo $admin['statut']; ?>">
                                <?php echo $admin['statut'] === 'actif' ? 'Actif' : 'Inactif'; ?>
                            </span>
                            <span class="role-badge role-<?php echo htmlspecialchars($admin['role']); ?>">
                                <?php echo htmlspecialchars(admin_role_label($admin['role'] ?? 'utilisateur')); ?>
                            </span>
                        </div>
                        <div class="user-header">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($admin['prenom'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($admin['email']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="user-details">
                            <div class="detail-item">
                                <label>Date création</label>
                                <div class="value"><?php echo date('d/m/Y H:i', strtotime($admin['date_creation'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <label>Dernière connexion</label>
                                <div class="value"><?php echo $admin['derniere_connexion'] ? date('d/m/Y H:i', strtotime($admin['derniere_connexion'])) : '—'; ?></div>
                            </div>
                        </div>

                        <div class="user-actions" style="flex-wrap: wrap;">
                            <a href="employe-activite.php?admin_id=<?php echo (int) $admin['id']; ?>" class="btn-action btn-role">
                                <i class="fas fa-chart-line"></i> Activité
                            </a>
                        <?php if (!$is_self): ?>
                        <?php if ($admin['statut'] === 'actif'): ?>
                            <form method="POST" action="" class="user-action-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                <input type="hidden" name="nouveau_statut" value="inactif">
                                <button type="submit" name="toggle_statut" class="btn-action btn-deactivate"
                                        onclick="return confirm('Désactiver ce compte ?');">
                                    <i class="fas fa-ban"></i> Désactiver
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="" class="user-action-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                <input type="hidden" name="nouveau_statut" value="actif">
                                <button type="submit" name="toggle_statut" class="btn-action btn-activate"
                                        onclick="return confirm('Activer ce compte ?');">
                                    <i class="fas fa-check"></i> Activer
                                </button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" action="" class="user-action-form" style="flex-wrap: wrap; align-items: center; gap: 8px;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                            <label for="role-<?php echo (int) $admin['id']; ?>" class="visually-hidden">Rôle</label>
                            <select name="nouveau_role" id="role-<?php echo (int) $admin['id']; ?>" class="comptes-role-select">
                                <?php foreach (admin_roles_valides() as $r): ?>
                                <option value="<?php echo htmlspecialchars($r); ?>" <?php echo (($admin['role'] ?? '') === $r) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(admin_role_label($r)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="definir_role" class="btn-action btn-role"
                                    onclick="return confirm('Enregistrer ce rôle pour ce compte ?');">
                                <i class="fas fa-save"></i> Enregistrer le rôle
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="btn-action btn-self"><i class="fas fa-user"></i> Votre compte</span>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
