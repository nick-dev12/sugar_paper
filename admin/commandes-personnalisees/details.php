<?php
/**
 * Détails et traitement d'une commande personnalisée (Admin)
 * Design élégant, ergonomique et responsive
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$cp_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($cp_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_commandes_personnalisees.php';
$cp = get_commande_personnalisee_by_id($cp_id);

if (!$cp) {
    header('Location: index.php');
    exit;
}

$statuts_labels = get_statuts_commande_personnalisee();
$is_annulee = $cp['statut'] === 'annulee';
$is_refusee = $cp['statut'] === 'refusee';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_annulee && !$is_refusee) {
    if (isset($_POST['changer_statut'])) {
        $nouveau_statut = $_POST['statut'] ?? '';
        $notes_admin = isset($_POST['notes_admin']) ? trim($_POST['notes_admin']) : null;
        if (in_array($nouveau_statut, array_keys($statuts_labels))) {
            if (update_commande_personnalisee_statut($cp_id, $nouveau_statut, $notes_admin)) {
                $_SESSION['success_message'] = 'Statut mis à jour avec succès.';
                header('Location: details.php?id=' . $cp_id);
                exit;
            }
        }
    } elseif (isset($_POST['sauvegarder_notes'])) {
        $notes_admin = isset($_POST['notes_admin']) ? trim($_POST['notes_admin']) : '';
        if (update_commande_personnalisee_notes($cp_id, $notes_admin)) {
            $_SESSION['success_message'] = 'Notes enregistrées.';
            header('Location: details.php?id=' . $cp_id);
            exit;
        }
    }
}

$cp = get_commande_personnalisee_by_id($cp_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande personnalisée #<?php echo $cp['id']; ?> - Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css">
    <link rel="stylesheet" href="/css/admin-commandes-personnalisees.css">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="cp-details-header">
        <h1><i class="fas fa-palette"></i> Demande #<?php echo $cp['id']; ?></h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <div class="cp-details-grid">
        <div class="cp-detail-box">
            <h3><i class="fas fa-user"></i> Client</h3>
            <div class="cp-detail-item">
                <label>Nom complet</label>
                <div class="value"><?php echo htmlspecialchars($cp['prenom'] . ' ' . $cp['nom']); ?></div>
            </div>
            <div class="cp-detail-item">
                <label>Email</label>
                <div class="value"><a href="mailto:<?php echo htmlspecialchars($cp['email']); ?>"><?php echo htmlspecialchars($cp['email']); ?></a></div>
            </div>
            <div class="cp-detail-item">
                <label>Téléphone</label>
                <div class="value"><a href="tel:<?php echo htmlspecialchars($cp['telephone']); ?>"><?php echo htmlspecialchars($cp['telephone']); ?></a></div>
            </div>
            <div class="cp-detail-item">
                <label>Compte client</label>
                <div class="value"><?php echo $cp['user_id'] ? 'Oui (ID: ' . $cp['user_id'] . ')' : 'Visiteur (non inscrit)'; ?></div>
            </div>
        </div>

        <div class="cp-detail-box">
            <h3><i class="fas fa-file-alt"></i> Demande</h3>
            <div class="cp-detail-item">
                <label>Description</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($cp['description'])); ?></div>
            </div>
            <?php if ($cp['type_produit']): ?>
            <div class="cp-detail-item">
                <label>Type de produit</label>
                <div class="value"><?php echo htmlspecialchars($cp['type_produit']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($cp['quantite']): ?>
            <div class="cp-detail-item">
                <label>Quantité souhaitée</label>
                <div class="value"><?php echo htmlspecialchars($cp['quantite']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($cp['date_souhaitee']): ?>
            <div class="cp-detail-item">
                <label>Date souhaitée</label>
                <div class="value"><?php echo date('d/m/Y', strtotime($cp['date_souhaitee'])); ?></div>
            </div>
            <?php endif; ?>
            <div class="cp-detail-item">
                <label>Date de demande</label>
                <div class="value"><?php echo date('d/m/Y à H:i', strtotime($cp['date_creation'])); ?></div>
            </div>
        </div>
    </div>

    <section class="cp-traitement-section">
        <h2><i class="fas fa-cog"></i> Traitement</h2>

        <?php if ($is_annulee || $is_refusee): ?>
            <div class="cp-alert-closed">
                <h3><i class="fas fa-ban"></i> Demande <?php echo $is_annulee ? 'annulée' : 'refusée'; ?></h3>
                <p>Cette demande n'est plus en cours de traitement.</p>
            </div>
        <?php else: ?>
            <div class="cp-statut-form">
                <div class="form-group">
                    <label>Statut actuel</label>
                    <div class="statut-current-wrap">
                        <span class="commande-statut statut-<?php echo $cp['statut']; ?>">
                            <?php echo $statuts_labels[$cp['statut']] ?? $cp['statut']; ?>
                        </span>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="statut">Changer le statut</label>
                        <select id="statut" name="statut" required>
                            <?php foreach ($statuts_labels as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $cp['statut'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes_admin">Notes internes (optionnel)</label>
                        <textarea id="notes_admin" name="notes_admin" rows="4" placeholder="Notes pour le suivi interne..."><?php echo htmlspecialchars($cp['notes_admin'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="changer_statut" class="cp-btn-submit">
                        <i class="fas fa-save"></i> Mettre à jour le statut
                    </button>
                </form>

                <p class="cp-info-hint">
                    <i class="fas fa-info-circle"></i>
                    <strong>Statuts :</strong> En attente → Confirmée → En préparation → Devis envoyé → Acceptée → Terminée.
                    Refusée ou Annulée pour clôturer sans suite.
                </p>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
