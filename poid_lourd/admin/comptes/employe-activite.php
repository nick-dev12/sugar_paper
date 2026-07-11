<?php
/**
 * Activité métier liée à un compte d'accès interne (BL, factures, etc.)
 */
require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_gestion_clients_comptes()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../models/model_employes.php';
require_once __DIR__ . '/../../models/model_admin_activite.php';

$admin_cible_id = isset($_GET['admin_id']) ? (int) $_GET['admin_id'] : 0;
if ($admin_cible_id <= 0) {
    header('Location: index.php');
    exit;
}

$admin_cible = get_admin_by_id($admin_cible_id);
if (!$admin_cible) {
    header('Location: index.php');
    exit;
}

$employe_lie = get_employe_by_admin_id($admin_cible_id);
$stats = get_stats_activite_par_admin_id($admin_cible_id);

$initiale = strtoupper(mb_substr(trim($admin_cible['prenom'] ?? ''), 0, 1, 'UTF-8'));
if ($initiale === '') {
    $initiale = '?';
}

$liens_activite = [
    ['type' => 'commandes_creees', 'icon' => 'fa-file-circle-plus', 'label' => 'Commandes créées (manuel)', 'hint' => 'Saisie manuelle depuis l’admin'],
    ['type' => 'commandes_traitees', 'icon' => 'fa-truck-fast', 'label' => 'Dernier traitement commande', 'hint' => 'Changements de statut enregistrés'],
    ['type' => 'devis', 'icon' => 'fa-file-invoice', 'label' => 'Devis créés', 'hint' => ''],
    ['type' => 'factures_devis', 'icon' => 'fa-receipt', 'label' => 'Factures (devis)', 'hint' => 'PDF / facture liée au devis'],
    ['type' => 'bl', 'icon' => 'fa-dolly', 'label' => 'Bons de livraison', 'hint' => 'BL créés par ce compte'],
    ['type' => 'factures_mensuelles', 'icon' => 'fa-calendar-check', 'label' => 'Factures mensuelles HT', 'hint' => 'B2B — regroupement BL'],
    ['type' => 'clients_b2b', 'icon' => 'fa-building', 'label' => 'Clients B2B enregistrés', 'hint' => 'Fiches créées depuis l’admin'],
];

$page_title = 'Activité — ' . htmlspecialchars($admin_cible['prenom'] . ' ' . $admin_cible['nom']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-users-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employe-activite.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-chart-line"></i> Activité du compte</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour aux comptes</a>
        </div>
    </div>

    <section class="ea-hero" aria-labelledby="ea-titre">
        <div class="ea-avatar" aria-hidden="true"><?php echo htmlspecialchars($initiale); ?></div>
        <div>
            <h2 id="ea-titre"><?php echo htmlspecialchars($admin_cible['prenom'] . ' ' . $admin_cible['nom']); ?></h2>
            <div class="ea-hero-meta">
                <?php echo htmlspecialchars($admin_cible['email']); ?>
                · <span class="role-badge role-<?php echo htmlspecialchars($admin_cible['role'] ?? 'utilisateur'); ?>"><?php echo htmlspecialchars(admin_role_label($admin_cible['role'] ?? 'utilisateur')); ?></span>
                <?php if ($employe_lie): ?>
                    <br><i class="fas fa-id-card"></i> Fiche employé :
                    <a href="employes/modifier.php?id=<?php echo (int) $employe_lie['id']; ?>">ouvrir la fiche RH</a>
                <?php else: ?>
                    <br><span class="ea-hero-muted">Aucune fiche employé liée.</span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <h2 class="ea-section-title"><i class="fas fa-gauge-high"></i> Synthèse</h2>
    <div class="ea-kpis" aria-label="Indicateurs d'activité">
        <div class="ea-kpi">
            <div class="kpi-label"><i class="fas fa-cart-plus"></i> Commandes créées</div>
            <div class="kpi-val"><?php echo $stats['trace_commandes_creees'] ? number_format($stats['nb_commandes_creees'], 0, ',', ' ') : '—'; ?></div>
        </div>
        <div class="ea-kpi">
            <div class="kpi-label"><i class="fas fa-clipboard-check"></i> Dernier traitement commande</div>
            <div class="kpi-val"><?php echo $stats['trace_commandes'] ? number_format($stats['nb_commandes_traitees'], 0, ',', ' ') : '—'; ?></div>
        </div>
        <div class="ea-kpi">
            <div class="kpi-label"><i class="fas fa-file-signature"></i> Devis créés</div>
            <div class="kpi-val"><?php echo $stats['trace_devis'] ? number_format($stats['nb_devis'], 0, ',', ' ') : '—'; ?></div>
        </div>
        <div class="ea-kpi">
            <div class="kpi-label"><i class="fas fa-file-invoice-dollar"></i> Factures (devis)</div>
            <div class="kpi-val"><?php echo $stats['trace_factures_devis'] ? number_format($stats['nb_factures_devis'], 0, ',', ' ') : '—'; ?></div>
        </div>
        <div class="ea-kpi">
            <div class="kpi-label"><i class="fas fa-calendar-alt"></i> Factures mensuelles HT</div>
            <div class="kpi-val"><?php echo number_format($stats['nb_factures_mensuelles'], 0, ',', ' '); ?></div>
        </div>
        <div class="ea-kpi">
            <div class="kpi-label"><i class="fas fa-truck-loading"></i> BL créés / validés</div>
            <div class="kpi-val"><?php echo number_format($stats['nb_bl_total'], 0, ',', ' '); ?> <span class="ea-kpi-sub">/ <?php echo number_format($stats['nb_bl_valides'], 0, ',', ' '); ?> val.</span></div>
        </div>
        <div class="ea-kpi">
            <div class="kpi-label"><i class="fas fa-industry"></i> Clients B2B</div>
            <div class="kpi-val"><?php echo $stats['trace_clients_b2b'] ? number_format($stats['nb_clients_b2b_crees'], 0, ',', ' ') : '—'; ?></div>
        </div>
        <div class="ea-kpi">
            <div class="kpi-label"><i class="fas fa-clock"></i> Heures (indicatif)</div>
            <div class="kpi-val"><?php echo $stats['heures_indicatif'] !== null ? number_format($stats['heures_indicatif'], 0, ',', ' ') . ' h' : '—'; ?></div>
        </div>
    </div>

    <h2 class="ea-section-title"><i class="fas fa-list-check"></i> Consulter le détail</h2>
    <div class="ea-actions">
        <?php foreach ($liens_activite as $la): ?>
        <a class="ea-action" href="employe-activite-liste.php?admin_id=<?php echo (int) $admin_cible_id; ?>&amp;type=<?php echo htmlspecialchars($la['type']); ?>">
            <span class="ea-action-icon"><i class="fas <?php echo htmlspecialchars($la['icon']); ?>"></i></span>
            <span>
                <strong><?php echo htmlspecialchars($la['label']); ?></strong>
                <?php if ($la['hint'] !== ''): ?>
                <small><?php echo htmlspecialchars($la['hint']); ?></small>
                <?php endif; ?>
            </span>
            <i class="fas fa-chevron-right chev" aria-hidden="true"></i>
        </a>
        <?php endforeach; ?>
    </div>

    <p class="ea-note">
        <strong>Traçabilité :</strong> les colonnes <code>admin_createur_id</code> / <code>admin_dernier_traitement_id</code> lient les actions au compte interne après exécution de la migration SQL
        <code>migrations/add_admin_tracabilite_interactions.sql</code>. Les indicateurs « — » signifient que la colonne n’existe pas encore ou qu’aucune donnée n’est enregistrée.
        L’indicateur « heures » est la différence entre la date de création du compte et la dernière connexion (valeur indicative, pas un relevé de temps de travail).
    </p>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
