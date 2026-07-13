<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Liste des commandes personnalisées (Admin)
 */

session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../models/model_commandes_personnalisees.php';

$statut_filter = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$commandes = get_all_commandes_personnalisees($statut_filter ?: null);

$total = count_commandes_personnalisees_by_statut();
$en_attente = count_commandes_personnalisees_by_statut('en_attente');

$montant_total_devis = 0;
foreach ($commandes as $cp_row) {
    if (!empty($cp_row['prix']) && (float) $cp_row['prix'] > 0) {
        $montant_total_devis += (float) $cp_row['prix'];
    }
}

$statuts_labels = get_statuts_commande_personnalisee();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes personnalisées - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-personnalisees-index.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-commandes-personnalisees-index">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-palette"></i> Commandes personnalisées</h1>
    </div>

    <div class="commandes-stats commandes-stats--compact">
        <div class="stat-box">
            <h3>Total Demandes</h3>
            <div class="stat-value"><?php echo $total; ?></div>
        </div>
        <div class="stat-box">
            <h3>En Attente</h3>
            <div class="stat-value"><?php echo $en_attente; ?></div>
        </div>
    </div>

    <div class="comptabilite-box">
        <div class="comptabilite-label"><i class="fas fa-calculator"></i> Montant total des devis affichés</div>
        <div class="comptabilite-value"><?php echo number_format($montant_total_devis, 0, ',', ' '); ?> FCFA</div>
    </div>

    <section class="content-section">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="fas fa-list"></i> Demandes (<?php echo count($commandes); ?>)</h2>
            </div>
            <form method="GET" class="cp-index-filter-form">
                <select name="statut" onchange="this.form.submit()" aria-label="Filtrer par statut">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($statuts_labels as $val => $label): ?>
                    <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $statut_filter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($commandes)): ?>
        <div class="empty-state">
            <i class="fas fa-palette"></i>
            <h3>Aucune commande personnalisée</h3>
            <p>Les demandes des clients apparaîtront ici.</p>
        </div>
        <?php else: ?>
        <div class="commandes-table-wrap">
            <table class="data-table commandes-data-table">
                <colgroup>
                    <col class="commandes-col-client">
                    <col class="commandes-col-montant">
                    <col class="commandes-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th class="commandes-col-montant">Montant</th>
                        <th class="commandes-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $cp): ?>
                    <?php
                    $client_nom = trim(($cp['prenom'] ?? '') . ' ' . ($cp['nom'] ?? '')) ?: '—';
                    $telephone_aff = trim($cp['telephone'] ?? '') ?: '—';
                    $date_aff = date('d/m/Y H:i', strtotime($cp['date_creation']));
                    $prix_val = isset($cp['prix']) && $cp['prix'] !== null && $cp['prix'] !== '' ? (float) $cp['prix'] : null;
                    ?>
                    <tr>
                        <td data-label="Client">
                            <span class="commandes-cell-nom"><?php echo htmlspecialchars($client_nom); ?></span>
                            <span class="commandes-cell-tel"><?php echo htmlspecialchars($telephone_aff); ?></span>
                        </td>
                        <td data-label="Montant" class="commandes-col-montant">
                            <?php if ($prix_val !== null && $prix_val > 0): ?>
                            <span class="commandes-cell-prix"><?php echo number_format($prix_val, 0, ',', ' '); ?> FCFA</span>
                            <?php else: ?>
                            <span class="commandes-cell-prix commandes-cell-prix--pending">À définir</span>
                            <?php endif; ?>
                            <span class="commandes-cell-date"><?php echo htmlspecialchars($date_aff); ?></span>
                        </td>
                        <td data-label="Actions" class="commandes-col-actions">
                            <a href="details.php?id=<?php echo (int) $cp['id']; ?>" class="btn-view">
                                <i class="fas fa-eye" aria-hidden="true"></i> Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
