<?php
/**
 * Journal d'audit (actions super admin)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';

$logs = super_admin_logs_recent(150);
$nb_logs = count($logs);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal d'audit — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero" aria-labelledby="sa-log-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-shield-halved" aria-hidden="true"></i> Traçabilité</p>
                    <h1 class="sa-users-hero__title" id="sa-log-title">Journal d'audit</h1>
                    <p class="sa-users-hero__lead">
                        Historique des actions sensibles réalisées depuis cet espace : activation ou désactivation des <strong>boutiques</strong>
                        et des <strong>comptes clients</strong>, avec horodatage et adresse IP.
                    </p>
                </div>
                <div class="sa-users-kpis" role="group" aria-label="Synthèse">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Entrées chargées</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $nb_logs; ?></span>
                        <span style="display:block;font-size:0.72rem;opacity:0.88;margin-top:6px;">150 dernières actions</span>
                    </div>
                </div>
            </div>
        </header>

        <section class="sa-users-panel" aria-labelledby="sa-log-panel-title">
            <div class="sa-users-panel__head">
                <h2 id="sa-log-panel-title"><i class="fas fa-table" aria-hidden="true"></i> Détail des événements</h2>
                <p class="sa-users-panel__meta">
                    <?php if ($nb_logs === 0): ?>
                        Aucune entrée enregistrée pour l’instant.
                    <?php else: ?>
                        <strong><?php echo (int) $nb_logs; ?></strong> ligne<?php echo $nb_logs > 1 ? 's' : ''; ?> — tri par date décroissante
                    <?php endif; ?>
                </p>
            </div>

            <?php if (empty($logs)): ?>
                <div class="sa-users-empty">
                    <i class="fas fa-clipboard" aria-hidden="true"></i>
                    <p><strong>Aucune action enregistrée.</strong></p>
                    <p>Les opérations sur les boutiques et les clients apparaîtront ici automatiquement.</p>
                </div>
            <?php else: ?>
                <div class="sa-users-table-wrap">
                    <table class="sa-users-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Acteur</th>
                                <th scope="col">Action</th>
                                <th scope="col">Cible</th>
                                <th scope="col">Détails</th>
                                <th scope="col">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $L): ?>
                                <tr>
                                    <td><span style="font-size:0.88rem;white-space:nowrap;"><?php echo htmlspecialchars((string) ($L['date_action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <span class="sa-user-cell__name" style="font-size:0.88rem;"><?php echo htmlspecialchars((string) ($L['super_admin_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td>
                                        <code style="font-size:0.78rem;padding:3px 8px;border-radius:6px;background:var(--fond-secondaire);border:1px solid var(--border-input);"><?php echo htmlspecialchars((string) ($L['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                                    </td>
                                    <td>
                                        <span style="font-size:0.88rem;"><?php echo htmlspecialchars((string) ($L['cible_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($L['cible_id'])): ?>
                                            <strong style="color:var(--couleur-dominante);"> #<?php echo (int) $L['cible_id']; ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="sa-user-cell__email" style="font-size:0.86rem;"><?php echo htmlspecialchars((string) ($L['details'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><small style="color:var(--texte-mute);font-family:ui-monospace,monospace;"><?php echo htmlspecialchars((string) ($L['ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
