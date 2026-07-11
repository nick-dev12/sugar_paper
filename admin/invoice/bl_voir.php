<?php
/**
 * Détails d'une facture B2B (équivalent devis/details.php)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_bl_retours_b2b()) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';

$bl_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bl_id <= 0 || !bl_tables_available()) {
    header('Location: index.php?tab=facture');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['marquer_facture_payee'])) {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $_SESSION['bl_erreur'] = 'Session expirée. Réessayez.';
    } else {
        $r = marquer_bl_facture_payee($bl_id);
        if (!empty($r['ok'])) {
            $_SESSION['success_message'] = 'Facture marquée comme payée.';
        } else {
            $_SESSION['bl_erreur'] = $r['error'] ?? 'Action impossible.';
        }
    }
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

$bl = get_bl_by_id($bl_id);
if (!$bl) {
    header('Location: index.php?tab=facture');
    exit;
}

$lignes = get_lignes_bl($bl_id);
$client_nom = trim($bl['raison_sociale'] ?? '');
$est_payee = bl_est_facture_payee($bl);
$montant_aff = bl_montant_facture_affichage($bl);
$remise_pct = (float) ($bl['remise_globale_pct'] ?? 0);
$total_ht = (float) ($bl['total_ht'] ?? 0);
$tva_incl = bl_tva_columns_ok() && !empty($bl['tva_incluse']);
$bl_peut_modifier = !bl_est_statut_verrouille($bl['statut'] ?? '') && ($bl['statut'] ?? 'brouillon') === 'brouillon';
$bl_tracking = livreur_bl_livraison_columns_ok() ? livreur_get_facture_tracking($bl_id) : false;
$bl_livraison_suivable = $bl_tracking && !empty($bl_tracking['livreur_id']);
$bl_livraison_statut = $bl_livraison_suivable ? livreur_facture_statut_livraison($bl_tracking) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?php echo htmlspecialchars($bl['numero_bl'] ?? ''); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-devis-compta-pages.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-invoice-onglets.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-admin-doc-detail">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1>
            <i class="fas fa-file-invoice"></i> Facture <?php echo htmlspecialchars($bl['numero_bl'] ?? ''); ?>
        </h1>
        <div class="header-actions header-actions--primary-row">
            <a href="bl_facture.php?id=<?php echo (int) $bl_id; ?>" class="btn-primary" target="_blank">
                <i class="fas fa-file-invoice"></i> Voir la facture
            </a>
            <?php if ($bl_livraison_suivable): ?>
            <a href="../livreurs/suivi.php?bl_id=<?php echo (int) $bl_id; ?>&amp;regarder=1" class="btn-secondary">
                <i class="fas fa-map-location-dot"></i> Suivre la livraison
            </a>
            <?php endif; ?>
            <?php if (!$est_payee && bl_col_facture_payee_ok()): ?>
            <form method="post" action="bl_voir.php?id=<?php echo (int) $bl_id; ?>" class="header-actions__form" onsubmit="return confirm('Marquer cette facture comme payée ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="marquer_facture_payee" value="1">
                <button type="submit" class="btn-secondary">
                    <i class="fas fa-check-circle"></i> Marquer comme payée
                </button>
            </form>
            <?php endif; ?>
            <?php if ($bl_peut_modifier): ?>
            <a href="index.php?tab=facture&amp;modal=bl&amp;edit=<?php echo (int) $bl_id; ?>" class="btn-secondary">
                <i class="fas fa-edit"></i> Modifier
            </a>
            <form method="post" action="bl_supprimer.php" class="header-actions__form" onsubmit="return confirm('Supprimer définitivement cette facture ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="bl_id" value="<?php echo (int) $bl_id; ?>">
                <button type="submit" class="btn-secondary">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            </form>
            <?php endif; ?>
            <a href="index.php?tab=facture" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['bl_erreur'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['bl_erreur']); unset($_SESSION['bl_erreur']); ?></span>
        </div>
    <?php endif; ?>

    <div class="commande-details-grid">
        <div class="detail-box">
            <h3><i class="fas fa-building"></i> Informations client</h3>
            <div class="detail-item">
                <label>Raison sociale</label>
                <div class="value"><?php echo htmlspecialchars($client_nom ?: '—'); ?></div>
            </div>
            <div class="detail-item">
                <label>Téléphone</label>
                <div class="value"><?php echo htmlspecialchars($bl['client_telephone'] ?? '—'); ?></div>
            </div>
            <div class="detail-item">
                <label>Email</label>
                <div class="value"><?php echo htmlspecialchars($bl['client_email'] ?? '—'); ?></div>
            </div>
        </div>

        <div class="detail-box">
            <h3><i class="fas fa-file-invoice-dollar"></i> Facture</h3>
            <div class="detail-item">
                <label>Numéro</label>
                <div class="value"><?php echo htmlspecialchars($bl['numero_bl'] ?? '—'); ?></div>
            </div>
            <div class="detail-item">
                <label>Date</label>
                <div class="value"><?php echo !empty($bl['date_bl']) ? htmlspecialchars($bl['date_bl']) : date('d/m/Y H:i', strtotime($bl['date_creation'] ?? 'now')); ?></div>
            </div>
            <div class="detail-item">
                <label>Montant</label>
                <div class="value"><?php echo number_format($montant_aff, 0, ',', ' '); ?> FCFA<?php echo $tva_incl ? ' TTC' : ' HT'; ?></div>
            </div>
            <?php if ($remise_pct > 0): ?>
            <div class="detail-item">
                <label>Réduction</label>
                <div class="value"><?php echo number_format($remise_pct, 2, ',', ' '); ?> %</div>
            </div>
            <?php endif; ?>
            <div class="detail-item">
                <label>Statut paiement</label>
                <div class="value">
                    <span class="commande-statut statut-<?php echo $est_payee ? 'paye' : 'impaye'; ?>">
                        <?php echo $est_payee ? 'Payée' : 'Impayée'; ?>
                    </span>
                </div>
            </div>
            <?php if ($est_payee && !empty($bl['date_paiement_bl'])): ?>
            <div class="detail-item">
                <label>Date de paiement</label>
                <div class="value"><?php echo date('d/m/Y à H:i', strtotime($bl['date_paiement_bl'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-box"></i> Produits de la facture</h2>
        </div>

        <div class="produits-list">
            <?php if (empty($lignes)): ?>
                <p>Aucun produit sur cette facture.</p>
            <?php else: ?>
                <?php foreach ($lignes as $l): ?>
                <div class="produit-item">
                    <div class="produit-info">
                        <h4><?php echo htmlspecialchars($l['designation'] ?? ''); ?></h4>
                        <div class="produit-info-lignes">
                            <div class="info-ligne">Quantité : <?php echo (int) ($l['quantite'] ?? 0); ?></div>
                            <div class="info-ligne">Prix unitaire HT : <?php echo number_format((float) ($l['prix_unitaire_ht'] ?? 0), 0, ',', ' '); ?> FCFA</div>
                        </div>
                    </div>
                    <div class="produit-total">
                        <?php echo number_format((float) ($l['total_ligne_ht'] ?? 0), 0, ',', ' '); ?> FCFA
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="produits-list-total">
                <?php if ($remise_pct > 0): ?>
                    <p style="margin-bottom: 8px;">Réduction globale : <?php echo number_format($remise_pct, 2, ',', ' '); ?> %</p>
                <?php endif; ?>
                <h3>Total HT : <span class="total-value"><?php echo number_format($total_ht, 0, ',', ' '); ?> FCFA</span></h3>
                <?php if ($tva_incl): ?>
                    <p style="margin-top: 8px;">Montant TTC : <?php echo number_format($montant_aff, 0, ',', ' '); ?> FCFA</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($bl['notes'])): ?>
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-sticky-note"></i> Notes</h2>
        </div>
        <div class="detail-box">
            <p><?php echo nl2br(htmlspecialchars($bl['notes'])); ?></p>
        </div>
    </section>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
