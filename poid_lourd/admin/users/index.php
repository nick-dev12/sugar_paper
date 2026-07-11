<?php
/**
 * Page de gestion des utilisateurs (Admin)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_gestion_clients_comptes()) {
    header('Location: ../dashboard.php');
    exit;
}

// Traitement de la désactivation/activation
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_statut'])) {
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $nouveau_statut = isset($_POST['nouveau_statut']) ? $_POST['nouveau_statut'] : '';

    if ($user_id > 0 && in_array($nouveau_statut, ['actif', 'inactif'], true)) {
        require_once __DIR__ . '/../../models/model_users.php';
        $vf_toggle = admin_vendeur_filter_id();
        if ($vf_toggle !== null && $vf_toggle > 0 && !user_a_commande_chez_boutique($user_id, $vf_toggle)) {
            $error_message = 'Vous ne pouvez pas modifier le statut de ce client (aucune commande de vos produits).';
        } elseif (update_user_statut($user_id, $nouveau_statut)) {
            $success_message = $nouveau_statut === 'actif'
                ? 'Utilisateur activé avec succès !'
                : 'Utilisateur désactivé avec succès !';
        } else {
            $error_message = 'Une erreur est survenue lors de la modification du statut.';
        }
    }
}

// Récupérer tous les utilisateurs avec leurs statistiques (périmètre boutique pour les vendeurs)
require_once __DIR__ . '/../../models/model_users.php';
$vf_clients = admin_vendeur_filter_id();
$users = get_all_users_with_stats($vf_clients);

// Statistiques globales
$total_users = count($users);
$users_actifs = count(array_filter($users, function ($u) { return $u['statut'] === 'actif'; }));
$users_inactifs = count(array_filter($users, function ($u) { return $u['statut'] === 'inactif'; }));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients du site — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-clients-index.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page">
    <?php include '../includes/nav.php'; ?>

    <div class="admin-clients-shell">
        <header class="admin-clients-hero">
            <div class="admin-clients-hero__text">
                <h1><i class="fas fa-store" aria-hidden="true"></i> <?php echo $vf_clients ? 'Clients de votre boutique' : 'Clients du site'; ?></h1>
            </div>
        </header>

        <?php if ($success_message): ?>
        <div class="message success">
            <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="admin-clients-kpis" aria-label="Synthèse clients">
            <div class="admin-clients-kpi">
                <div class="admin-clients-kpi__icon" aria-hidden="true"><i class="fas fa-users"></i></div>
                <div>
                    <div class="admin-clients-kpi__label">Total</div>
                    <div class="admin-clients-kpi__value"><?php echo (int) $total_users; ?></div>
                </div>
            </div>
            <div class="admin-clients-kpi">
                <div class="admin-clients-kpi__icon" aria-hidden="true"><i class="fas fa-user-check"></i></div>
                <div>
                    <div class="admin-clients-kpi__label">Actifs</div>
                    <div class="admin-clients-kpi__value"><?php echo (int) $users_actifs; ?></div>
                </div>
            </div>
            <div class="admin-clients-kpi">
                <div class="admin-clients-kpi__icon" aria-hidden="true"><i class="fas fa-user-slash"></i></div>
                <div>
                    <div class="admin-clients-kpi__label">Inactifs</div>
                    <div class="admin-clients-kpi__value"><?php echo (int) $users_inactifs; ?></div>
                </div>
            </div>
        </div>

        <section class="admin-clients-panel" aria-labelledby="clients-list-title">
            <div class="admin-clients-panel__head">
                <h2 id="clients-list-title"><i class="fas fa-list" aria-hidden="true"></i> Liste des clients (<?php echo count($users); ?>)</h2>
            </div>

            <?php if (empty($users)): ?>
            <div class="admin-clients-empty">
                <i class="fas fa-users" aria-hidden="true"></i>
                <h3><?php echo $vf_clients ? 'Aucun client pour votre boutique' : 'Aucun utilisateur'; ?></h3>
                <p><?php echo $vf_clients
                    ? 'Aucun client n’a encore commandé un produit publié par votre boutique.'
                    : 'Aucun compte client n’est encore enregistré.'; ?></p>
            </div>
            <?php else: ?>
            <div class="admin-clients-grid">
                <?php foreach ($users as $user): ?>
                <?php
                $is_actif = ($user['statut'] ?? '') === 'actif';
                $initiale = strtoupper(substr((string) ($user['prenom'] ?? '?'), 0, 1));
                $ca_ht = number_format((float) ($user['ca_total_ht'] ?? 0), 0, ',', ' ');
                ?>
                <article class="admin-client-card<?php echo $is_actif ? '' : ' admin-client-card--inactive'; ?>">
                    <div class="admin-client-card__top">
                        <span class="admin-client-card__badge<?php echo $is_actif ? ' admin-client-card__badge--actif' : ' admin-client-card__badge--inactif'; ?>">
                            <?php echo $is_actif ? 'Actif' : 'Inactif'; ?>
                        </span>
                        <div class="admin-client-card__identity">
                            <div class="admin-client-card__avatar" aria-hidden="true"><?php echo htmlspecialchars($initiale); ?></div>
                            <div>
                                <p class="admin-client-card__name"><?php echo htmlspecialchars(trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))); ?></p>
                                <p class="admin-client-card__email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="admin-client-card__body">
                        <div class="admin-client-card__phone">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <div>
                                <span class="admin-client-card__phone-label">Téléphone</span>
                                <?php echo htmlspecialchars($user['telephone'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="admin-client-card__metrics">
                            <div class="admin-client-card__metric">
                                <div class="admin-client-card__metric-label">Commandes</div>
                                <div class="admin-client-card__metric-value"><?php echo (int) ($user['nb_commandes'] ?? 0); ?></div>
                            </div>
                            <div class="admin-client-card__metric">
                                <div class="admin-client-card__metric-label">Livrées</div>
                                <div class="admin-client-card__metric-value"><?php echo (int) ($user['nb_commandes_livrees'] ?? 0); ?></div>
                            </div>
                            <div class="admin-client-card__metric admin-client-card__metric--total">
                                <div class="admin-client-card__metric-label admin-client-card__metric-label--total">Montant total</div>
                                <div class="admin-client-card__metric-value admin-client-card__metric-value--total">
                                    <?php echo htmlspecialchars($ca_ht); ?>
                                    <span class="admin-client-card__metric-unit" aria-label="francs CFA"> FCFA</span>
                                </div>
                            </div>
                        </div>
                        <div class="admin-client-card__actions">
                            <a href="details.php?id=<?php echo (int) $user['id']; ?>" class="admin-client-btn-detail">
                                <i class="fas fa-id-card" aria-hidden="true"></i> Fiche détail
                            </a>
                            <?php if (!$is_actif): ?>
                            <form method="post" action="">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                <input type="hidden" name="nouveau_statut" value="actif">
                                <button type="submit" name="toggle_statut" class="admin-client-btn-on"
                                    onclick="return confirm('Réactiver cet utilisateur ?');">
                                    <i class="fas fa-check" aria-hidden="true"></i> Activer
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include '../includes/footer.php'; ?>

</body>

</html>
