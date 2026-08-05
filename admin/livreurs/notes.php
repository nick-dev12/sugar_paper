<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Notes clients sur les livreurs — liste des profils
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce();

require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_livreur_notes.php';
require_once __DIR__ . '/../../models/model_admin.php';

if (!admin_can_view_livreur_notes()) {
    header('Location: ' . (admin_can_livreur_gps() ? 'index.php' : '../dashboard.php'));
    exit;
}

$tables_ready = livreur_notes_tables_ready();
$livreurs = $tables_ready ? livreur_notes_liste_livreurs() : [];

function livreur_notes_avatar_initials($prenom, $nom)
{
    $p = mb_substr(trim((string) $prenom), 0, 1);
    $n = mb_substr(trim((string) $nom), 0, 1);
    $init = strtoupper($p . $n);
    return $init !== '' ? $init : 'L';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes clients — Livreurs</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-livreur-suivi.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-livreurs-notes">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="content-header content-header--livreurs">
    <h1><i class="fas fa-star" aria-hidden="true"></i> Notes des clients</h1>
    <div class="header-actions">
        <?php if (admin_can_livreur_gps()): ?>
        <a href="index.php" class="btn-secondary"><i class="fas fa-motorcycle"></i> Livraisons</a>
        <?php else: ?>
        <a href="../dashboard.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Tableau de bord</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$tables_ready): ?>
<div class="message error">
    <i class="fas fa-database"></i>
    <span>Module non installé. Exécutez : <code>php migrations/run_add_livreur_notes_client.php</code></span>
</div>
<?php elseif (empty($livreurs)): ?>
<div class="empty-state">
    <i class="fas fa-star"></i>
    <p>Aucun livreur ou aucune note enregistrée pour le moment.</p>
    <?php if (admin_can_livreur_gps()): ?>
    <a href="index.php" class="btn-primary"><i class="fas fa-motorcycle"></i> Voir les livraisons</a>
    <?php else: ?>
    <a href="../dashboard.php" class="btn-primary"><i class="fas fa-arrow-left"></i> Tableau de bord</a>
    <?php endif; ?>
</div>
<?php else: ?>
<section class="livreur-card livreur-card--wide">
    <p class="livreur-notes-intro">Cliquez sur un livreur pour voir le détail de chaque note client.</p>
    <div class="livreur-notes-grid">
        <?php foreach ($livreurs as $lv): ?>
            <?php
            $lid = (int) ($lv['id'] ?? 0);
            $nom_complet = trim((string) ($lv['prenom'] ?? '') . ' ' . ($lv['nom'] ?? ''));
            $photo_url = !empty($lv['photo_profil']) ? admin_photo_profil_url($lv['photo_profil']) : '';
            $initials = livreur_notes_avatar_initials($lv['prenom'] ?? '', $lv['nom'] ?? '');
            $moyenne = isset($lv['livreur_note_moyenne']) ? $lv['livreur_note_moyenne'] : null;
            $nb = (int) ($lv['livreur_nb_notes'] ?? 0);
            ?>
            <a href="notes-detail.php?id=<?php echo $lid; ?>" class="livreur-notes-card">
                <div class="livreur-notes-card__head">
                    <?php if ($photo_url !== ''): ?>
                    <img src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="livreur-notes-card__avatar">
                    <?php else: ?>
                    <span class="livreur-notes-card__avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <div>
                        <p class="livreur-notes-card__name"><?php echo htmlspecialchars($nom_complet !== '' ? $nom_complet : 'Livreur #' . $lid); ?></p>
                        <p class="livreur-notes-card__meta"><?php echo (int) $nb; ?> avis client<?php echo $nb !== 1 ? 's' : ''; ?></p>
                    </div>
                </div>
                <div class="livreur-notes-card__score">
                    <i class="fas fa-star" aria-hidden="true"></i>
                    <?php echo htmlspecialchars(livreur_note_format_moyenne_label($moyenne, $nb)); ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
