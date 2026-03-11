<?php
/**
 * Page de gestion des utilisateurs (Admin)
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Seuls les admins avec rôle "admin" peuvent gérer les utilisateurs clients
if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// Traitement de la désactivation/activation
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_statut'])) {
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $nouveau_statut = isset($_POST['nouveau_statut']) ? $_POST['nouveau_statut'] : '';
    
    if ($user_id > 0 && in_array($nouveau_statut, ['actif', 'inactif'])) {
        require_once __DIR__ . '/../../models/model_users.php';
        if (update_user_statut($user_id, $nouveau_statut)) {
            $success_message = $nouveau_statut === 'actif' 
                ? 'Utilisateur activé avec succès !' 
                : 'Utilisateur désactivé avec succès !';
        } else {
            $error_message = 'Une erreur est survenue lors de la modification du statut.';
        }
    }
}

// Récupérer tous les utilisateurs avec leurs statistiques
require_once __DIR__ . '/../../models/model_users.php';
$users = get_all_users_with_stats();

// Statistiques globales
$total_users = count($users);
$users_actifs = count(array_filter($users, function($u) { return $u['statut'] === 'actif'; }));
$users_inactifs = count(array_filter($users, function($u) { return $u['statut'] === 'inactif'; }));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-users-cards.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-users"></i> Gestion des Utilisateurs</h1>
        <div class="header-actions">
            <a href="contacts/index.php" class="btn-primary btn-secondary-style">
                <i class="fas fa-address-book"></i> Contacts
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
    <div class="message success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="message error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="users-stats">
        <div class="stat-box">
            <h3>Total Utilisateurs</h3>
            <div class="stat-value"><?php echo $total_users; ?></div>
        </div>
        <div class="stat-box">
            <h3>Utilisateurs Actifs</h3>
            <div class="stat-value"><?php echo $users_actifs; ?></div>
        </div>
        <div class="stat-box">
            <h3>Utilisateurs Inactifs</h3>
            <div class="stat-value"><?php echo $users_inactifs; ?></div>
        </div>
    </div>

    <!-- Liste des utilisateurs -->
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-list"></i> Liste des Utilisateurs (<?php echo count($users); ?>)</h2>
            <p class="section-subtitle">
                Classés par nombre de commandes (du plus actif au moins actif)
            </p>
        </div>

        <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>Aucun utilisateur</h3>
            <p>Aucun utilisateur n'est enregistré dans le système.</p>
        </div>
        <?php else: ?>
        <div class="users-grid">
            <?php foreach ($users as $user): ?>
            <div class="user-card <?php echo $user['statut'] === 'inactif' ? 'inactive' : ''; ?>">
                <div class="card-header-wrap">
                    <span class="user-statut statut-<?php echo $user['statut']; ?>">
                        <?php echo $user['statut'] === 'actif' ? 'Actif' : 'Inactif'; ?>
                    </span>
                    <div class="user-header">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['prenom'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
                            </div>
                            <div class="user-email">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="user-details">
                        <div class="detail-item">
                            <label>Téléphone</label>
                            <div class="value">
                                <?php echo htmlspecialchars($user['telephone']); ?>
                            </div>
                        </div>

                    </div>

                    <div class="user-stats">
                        <div class="stat-item">
                            <div class="stat-item-label">Commandes</div>
                            <div class="stat-item-value"><?php echo (int)$user['nb_commandes']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-item-label">Livrées</div>
                            <div class="stat-item-value"><?php echo (int)$user['nb_commandes_livrees']; ?></div>
                        </div>
                    </div>

                    <div class="user-actions">
                        <?php if ($user['statut'] === 'actif'): ?>
                        <form method="POST" action="" class="user-action-form">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="nouveau_statut" value="inactif">
                            <button type="submit" name="toggle_statut" class="btn-action btn-deactivate"
                                onclick="return confirm('Êtes-vous sûr de vouloir désactiver cet utilisateur ?');">
                                <i class="fas fa-ban"></i> Désactiver
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="" class="user-action-form">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="nouveau_statut" value="actif">
                            <button type="submit" name="toggle_statut" class="btn-action btn-activate"
                                onclick="return confirm('Êtes-vous sûr de vouloir activer cet utilisateur ?');">
                                <i class="fas fa-check"></i> Activer
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>

</body>

</html>