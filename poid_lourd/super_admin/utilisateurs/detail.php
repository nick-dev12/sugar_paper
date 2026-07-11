<?php
/**
 * Fiche client — détail + liste des commandes (super admin)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_users.php';
require_once dirname(__DIR__, 2) . '/models/model_commandes_admin.php';

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user = $user_id > 0 ? get_user_by_id($user_id) : false;
if (!$user) {
    header('Location: index.php');
    exit;
}

$commandes = get_commandes_by_user_id($user_id);
if (!is_array($commandes)) {
    $commandes = [];
}

$nb_cmd = count($commandes);
$ca_non_annule = 0.0;
foreach ($commandes as $c) {
    if (($c['statut'] ?? '') !== 'annulee') {
        $ca_non_annule += (float) ($c['montant_total'] ?? 0);
    }
}

$nom_complet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$dc = $user['date_creation'] ?? '';
$dc_fmt = $dc !== '' ? date('d/m/Y à H:i', strtotime((string) $dc)) : '—';
$est_actif = (($user['statut'] ?? '') === 'actif');

$fmt_fcfa = static function ($n) {
    return number_format((float) $n, 0, ',', ' ') . ' FCFA';
};

$statut_lib = static function ($s) {
    $map = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'en_preparation' => 'En préparation',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'annulee' => 'Annulée',
        'prise_en_charge' => 'Prise en charge',
        'livraison_en_cours' => 'Livraison en cours',
        'paye' => 'Payée',
    ];
    return $map[$s] ?? (string) $s;
};
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nom_complet !== '' ? $nom_complet : 'Client', ENT_QUOTES, 'UTF-8'); ?> — Fiche client · Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <style>
        .sa-user-detail-grid { display: grid; gap: 1.25rem; grid-template-columns: 1fr; }
        @media (min-width: 720px) { .sa-user-detail-grid { grid-template-columns: 1fr 1fr; } }
        .sa-user-detail-card { background: var(--blanc); border: 1px solid var(--glass-border); border-radius: 14px; padding: 1.1rem 1.25rem; box-shadow: var(--glass-shadow); }
        .sa-user-detail-card h3 { margin: 0 0 0.75rem; font-size: 1rem; color: var(--titres); display: flex; align-items: center; gap: 0.5rem; }
        .sa-user-detail-row { display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem 1rem; font-size: 0.9rem; padding: 0.35rem 0; border-bottom: 1px solid rgba(53,100,166,.08); }
        .sa-user-detail-row:last-child { border-bottom: none; }
        .sa-user-detail-label { color: var(--texte-mute); font-weight: 600; }
        .sa-user-detail-val { color: var(--texte-fonce); word-break: break-word; }
    </style>
</head>

<body class="page-users admin-clients-page sa-users-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero" aria-labelledby="sa-ud-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-user" aria-hidden="true"></i> Fiche client</p>
                    <h1 class="sa-users-hero__title" id="sa-ud-title"><?php echo htmlspecialchars($nom_complet !== '' ? $nom_complet : 'Client #' . $user_id, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="sa-users-hero__lead">
                        Consultez les informations du compte et l’historique des commandes passées sur la plateforme.
                    </p>
                    <p style="margin-top:12px;">
                        <a class="sa-btn-action" href="index.php" style="display:inline-flex;text-decoration:none;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste</a>
                    </p>
                </div>
                <div class="sa-users-kpis" role="group" aria-label="Synthèse client">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Commandes</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $nb_cmd; ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">CA (hors annulées)</span>
                        <span class="sa-users-kpi__value" style="font-size:1rem;"><?php echo htmlspecialchars($fmt_fcfa($ca_non_annule), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Compte</span>
                        <?php if ($est_actif): ?>
                            <span class="sa-badge sa-badge--ok" style="margin-top:6px;display:inline-block;">Actif</span>
                        <?php else: ?>
                            <span class="sa-badge sa-badge--off" style="margin-top:6px;display:inline-block;">Inactif</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="sa-user-detail-grid" style="margin-bottom:1.5rem;">
            <div class="sa-user-detail-card">
                <h3><i class="fas fa-id-card" aria-hidden="true"></i> Identité &amp; contact</h3>
                <div class="sa-user-detail-row">
                    <span class="sa-user-detail-label">E-mail</span>
                    <span class="sa-user-detail-val"><?php echo htmlspecialchars((string) ($user['email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="sa-user-detail-row">
                    <span class="sa-user-detail-label">Téléphone</span>
                    <span class="sa-user-detail-val"><?php echo htmlspecialchars((string) ($user['telephone'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="sa-user-detail-row">
                    <span class="sa-user-detail-label">Inscription</span>
                    <span class="sa-user-detail-val"><?php echo htmlspecialchars($dc_fmt, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="sa-user-detail-row">
                    <span class="sa-user-detail-label">ID utilisateur</span>
                    <span class="sa-user-detail-val"><?php echo (int) $user_id; ?></span>
                </div>
            </div>
        </div>

        <section class="sa-users-panel" aria-labelledby="sa-ud-cmd">
            <div class="sa-users-panel__head">
                <h2 id="sa-ud-cmd"><i class="fas fa-shopping-bag" aria-hidden="true"></i> Commandes du client</h2>
                <p class="sa-users-panel__meta">
                    <?php if ($nb_cmd === 0): ?>
                        Aucune commande enregistrée pour ce compte.
                    <?php else: ?>
                        <strong><?php echo (int) $nb_cmd; ?></strong> commande<?php echo $nb_cmd > 1 ? 's' : ''; ?> — du plus récent au plus ancien
                    <?php endif; ?>
                </p>
            </div>

            <?php if (empty($commandes)): ?>
                <div class="sa-users-empty">
                    <i class="fas fa-inbox" aria-hidden="true"></i>
                    <p><strong>Aucune commande.</strong></p>
                    <p>Ce client n’a pas encore passé de commande sur la plateforme.</p>
                </div>
            <?php else: ?>
                <div class="sa-users-table-wrap">
                    <table class="sa-users-table">
                        <thead>
                            <tr>
                                <th scope="col">N° commande</th>
                                <th scope="col">Date</th>
                                <th scope="col">Montant</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Détail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $c): ?>
                                <?php
                                $cid = (int) ($c['id'] ?? 0);
                                $d_cmd = (string) ($c['date_commande'] ?? '');
                                $d_fmt = $d_cmd !== '' ? date('d/m/Y H:i', strtotime($d_cmd)) : '—';
                                $st = (string) ($c['statut'] ?? '');
                                ?>
                                <tr>
                                    <td><strong class="sa-user-cell__name"><?php echo htmlspecialchars((string) ($c['numero_commande'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><span style="font-size:0.88rem;color:var(--texte-mute);"><?php echo htmlspecialchars($d_fmt, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><span class="sa-user-ca"><?php echo htmlspecialchars($fmt_fcfa($c['montant_total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <span class="sa-badge <?php echo $st === 'annulee' ? 'sa-badge--off' : 'sa-badge--ok'; ?>"><?php echo htmlspecialchars($statut_lib($st), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($cid > 0): ?>
                                            <a class="sa-btn-action sa-btn-action--primary" style="display:inline-flex;text-decoration:none;white-space:nowrap;"
                                                href="commande.php?id=<?php echo $cid; ?>&amp;client=<?php echo (int) $user_id; ?>">
                                                <i class="fas fa-file-invoice" aria-hidden="true"></i> Voir le détail
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
