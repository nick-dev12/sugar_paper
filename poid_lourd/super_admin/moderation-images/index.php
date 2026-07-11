<?php
/**
 * Modération images produits vendeurs — Super Admin
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_produit_image_moderation.php';
require_once dirname(__DIR__, 2) . '/includes/image_optimizer.php';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

$pending = produit_image_moderation_list_pending(80);
$pending_count = produit_image_moderation_count_pending();
$csrf = super_admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modération images — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <style>
        .sa-img-mod-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        .sa-img-mod-card { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(53,100,166,.08); }
        .sa-img-mod-card img { width: 100%; aspect-ratio: 1; object-fit: cover; background: #f5f5f5; }
        .sa-img-mod-card__body { padding: 14px; }
        .sa-img-mod-card__meta { font-size: .82rem; color: #666; margin: 0 0 8px; line-height: 1.45; }
        .sa-img-mod-card__actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .sa-img-mod-btn { border: 0; border-radius: 8px; padding: 8px 12px; font-size: .85rem; font-weight: 600; cursor: pointer; }
        .sa-img-mod-btn--ok { background: #3564a6; color: #fff; }
        .sa-img-mod-btn--no { background: #ff6b35; color: #fff; }
        .sa-img-mod-empty { padding: 40px 20px; text-align: center; color: #666; background: #fff; border-radius: 12px; border: 1px dashed rgba(0,0,0,.12); }
    </style>
</head>
<body class="page-users admin-clients-page sa-users-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-shield-alt"></i> Contrôle contenu</p>
                    <h1 class="sa-users-hero__title">Modération des images produits</h1>
                    <p class="sa-users-hero__lead">Vérifiez les visuels publiés par les vendeurs avant mise en ligne sur le catalogue public.</p>
                </div>
                <div class="sa-users-kpis">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">En attente</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $pending_count; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($msg_ok !== ''): ?>
            <div class="sa-flash sa-flash--ok"><?php echo htmlspecialchars($msg_ok, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($msg_err !== ''): ?>
            <div class="sa-flash sa-flash--err"><?php echo htmlspecialchars($msg_err, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (empty($pending)): ?>
            <div class="sa-img-mod-empty">
                <p><i class="fas fa-check-circle" style="color:#3564a6;font-size:2rem;"></i></p>
                <p>Aucune image en attente de validation.</p>
            </div>
        <?php else: ?>
            <div class="sa-img-mod-grid">
                <?php foreach ($pending as $row): ?>
                    <?php
                    $eid = (int) ($row['id'] ?? 0);
                    $img = trim((string) ($row['image_path'] ?? ''));
                    $pid = (int) ($row['produit_id'] ?? 0);
                    $aid = (int) ($row['admin_id'] ?? 0);
                    $boutique = trim((string) ($row['boutique_nom'] ?? $row['vendeur_nom'] ?? 'Boutique'));
                    $pname = trim((string) ($row['produit_nom'] ?? 'Produit'));
                    $motif = trim((string) ($row['motif'] ?? ''));
                    $img_url = upload_image_url($img, 'md');
                    ?>
                    <article class="sa-img-mod-card">
                        <img src="<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="Aperçu produit"
                            loading="lazy"
                            onerror="this.src='/image/produit1.jpg'">
                        <div class="sa-img-mod-card__body">
                            <p class="sa-img-mod-card__meta">
                                <strong><?php echo htmlspecialchars($boutique, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($pid > 0): ?> · #<?php echo $pid; ?><?php endif; ?>
                            </p>
                            <?php if ($motif !== ''): ?>
                                <p class="sa-img-mod-card__meta"><?php echo htmlspecialchars($motif, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <div class="sa-img-mod-card__actions">
                                <form method="post" action="traiter.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="entry_id" value="<?php echo $eid; ?>">
                                    <input type="hidden" name="action" value="approuver">
                                    <button type="submit" class="sa-img-mod-btn sa-img-mod-btn--ok"><i class="fas fa-check"></i> Approuver</button>
                                </form>
                                <form method="post" action="traiter.php" onsubmit="return confirm('Refuser cette image ? Le produit pourra rester masqué.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="entry_id" value="<?php echo $eid; ?>">
                                    <input type="hidden" name="action" value="refuser">
                                    <button type="submit" class="sa-img-mod-btn sa-img-mod-btn--no"><i class="fas fa-ban"></i> Refuser</button>
                                </form>
                                <?php if ($aid > 0): ?>
                                    <a class="sa-img-mod-btn" style="background:#eee;color:#333;text-decoration:none;display:inline-flex;align-items:center;"
                                        href="../boutiques/detail.php?id=<?php echo $aid; ?>">Fiche boutique</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
