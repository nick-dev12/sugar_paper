<?php
/**
 * Détail d'une commande (contexte client super admin)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_users.php';
require_once dirname(__DIR__, 2) . '/models/model_commandes_admin.php';

$commande_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$client_id = isset($_GET['client']) ? (int) $_GET['client'] : 0;

if ($commande_id <= 0 || $client_id <= 0) {
    header('Location: index.php');
    exit;
}

$user = get_user_by_id($client_id);
if (!$user) {
    header('Location: index.php');
    exit;
}

$commande = get_commande_by_id($commande_id);
if (!$commande) {
    header('Location: detail.php?id=' . $client_id);
    exit;
}

if ((int) ($commande['user_id'] ?? 0) !== $client_id) {
    header('Location: detail.php?id=' . $client_id);
    exit;
}

$lignes = get_produits_by_commande($commande_id);
if (!is_array($lignes)) {
    $lignes = [];
}
if (empty($lignes)) {
    require_once dirname(__DIR__, 2) . '/models/model_commandes.php';
    $lignes = get_commande_produits($commande_id);
    if (!is_array($lignes)) {
        $lignes = [];
    }
}

$nom_complet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

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

$st = (string) ($commande['statut'] ?? '');
$d_cmd = (string) ($commande['date_commande'] ?? '');
$d_fmt = $d_cmd !== '' ? date('d/m/Y à H:i', strtotime($d_cmd)) : '—';
$num = (string) ($commande['numero_commande'] ?? '');
$addr = trim((string) ($commande['adresse_livraison'] ?? ''));
$tel_liv = (string) ($commande['telephone_livraison'] ?? '');
$notes = trim((string) ($commande['notes'] ?? ''));
$d_liv = (string) ($commande['date_livraison'] ?? '');
$d_liv_fmt = $d_liv !== '' ? date('d/m/Y à H:i', strtotime($d_liv)) : '';

$statut_badge_class = 'sa-badge--ok';
if ($st === 'annulee') {
    $statut_badge_class = 'sa-badge--off';
} elseif (in_array($st, ['en_attente', 'en_preparation'], true)) {
    $statut_badge_class = 'sa-badge--warn';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande <?php echo htmlspecialchars($num, ENT_QUOTES, 'UTF-8'); ?> — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <style>
        .sa-cmd-meta { display: grid; gap: 0.65rem; font-size: 0.92rem; margin: 1rem 0 0; }
        .sa-cmd-meta dt { color: var(--texte-mute); font-weight: 600; margin: 0; }
        .sa-cmd-meta dd { margin: 0 0 0.5rem; color: var(--texte-fonce); }
        .sa-cmd-lines th, .sa-cmd-lines td { font-size: 0.88rem; vertical-align: top; }
        .sa-cmd-lines .thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid var(--glass-border); }
        .sa-cmd-status-strip {
            margin: 0 0 1.25rem;
            padding: 1rem 1.25rem;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(53, 100, 166, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
            border: 1px solid rgba(53, 100, 166, 0.2);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem 1.25rem;
        }
        .sa-cmd-status-strip__label { display: block; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--texte-mute); margin-bottom: 0.35rem; }
        .sa-cmd-status-strip__lib { font-size: 1.35rem; font-weight: 800; color: var(--titres); font-family: var(--font-titres); }
        .sa-cmd-status-strip__code { font-size: 0.82rem; color: var(--gris-moyen); font-family: ui-monospace, monospace; margin-top: 0.25rem; }
        .sa-badge--warn { background: rgba(255, 107, 53, 0.18); color: #c24a1a; border: 1px solid rgba(255, 107, 53, 0.35); }
    </style>
</head>

<body class="page-users admin-clients-page sa-users-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero" aria-labelledby="sa-co-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-receipt" aria-hidden="true"></i> Commande</p>
                    <h1 class="sa-users-hero__title" id="sa-co-title"><?php echo htmlspecialchars($num !== '' ? $num : 'Commande #' . $commande_id, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="sa-users-hero__lead">
                        Client : <strong><?php echo htmlspecialchars($nom_complet !== '' ? $nom_complet : '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                        — <?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p style="margin-top:12px;display:flex;flex-wrap:wrap;gap:10px;">
                        <a class="sa-btn-action" href="detail.php?id=<?php echo (int) $client_id; ?>" style="display:inline-flex;text-decoration:none;">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la fiche client
                        </a>
                        <a class="sa-btn-action" href="index.php" style="display:inline-flex;text-decoration:none;">
                            <i class="fas fa-users" aria-hidden="true"></i> Liste des clients
                        </a>
                    </p>
                </div>
                <div class="sa-users-kpis" role="group" aria-label="Montant et statut">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Montant total</span>
                        <span class="sa-users-kpi__value" style="font-size:1rem;"><?php echo htmlspecialchars($fmt_fcfa($commande['montant_total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Statut</span>
                        <span class="sa-badge <?php echo htmlspecialchars($statut_badge_class, ENT_QUOTES, 'UTF-8'); ?>" style="margin-top:6px;display:inline-block;"><?php echo htmlspecialchars($statut_lib($st), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Date</span>
                        <span style="font-size:0.95rem;font-weight:600;"><?php echo htmlspecialchars($d_fmt, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="sa-cmd-status-strip" role="region" aria-label="Statut de la commande">
            <div>
                <span class="sa-cmd-status-strip__label">Statut de la commande</span>
                <div class="sa-cmd-status-strip__lib"><?php echo htmlspecialchars($statut_lib($st), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="sa-cmd-status-strip__code">Code technique : <?php echo htmlspecialchars($st !== '' ? $st : '—', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <span class="sa-badge <?php echo htmlspecialchars($statut_badge_class, ENT_QUOTES, 'UTF-8'); ?>" style="font-size:0.95rem;padding:0.5rem 0.9rem;"><?php echo htmlspecialchars($statut_lib($st), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <section class="sa-users-panel" aria-labelledby="sa-co-info">
            <div class="sa-users-panel__head">
                <h2 id="sa-co-info"><i class="fas fa-truck" aria-hidden="true"></i> Livraison &amp; informations</h2>
            </div>
            <div style="padding:0 1.25rem 1.25rem;">
                <dl class="sa-cmd-meta">
                    <dt>Téléphone livraison</dt>
                    <dd><?php echo htmlspecialchars($tel_liv !== '' ? $tel_liv : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt>Adresse</dt>
                    <dd><?php echo $addr !== '' ? nl2br(htmlspecialchars($addr, ENT_QUOTES, 'UTF-8')) : '—'; ?></dd>
                    <?php if ($d_liv_fmt !== ''): ?>
                        <dt>Date de livraison (système)</dt>
                        <dd><?php echo htmlspecialchars($d_liv_fmt, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <?php endif; ?>
                    <?php if ($notes !== ''): ?>
                        <dt>Notes</dt>
                        <dd><?php echo nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </section>

        <section class="sa-users-panel" aria-labelledby="sa-co-lines">
            <div class="sa-users-panel__head">
                <h2 id="sa-co-lines"><i class="fas fa-box-open" aria-hidden="true"></i> Lignes de commande</h2>
                <p class="sa-users-panel__meta"><?php echo count($lignes); ?> article<?php echo count($lignes) > 1 ? 's' : ''; ?></p>
            </div>

            <?php if (empty($lignes)): ?>
                <div class="sa-users-empty">
                    <p><strong>Aucune ligne produit</strong> n’a été trouvée pour cette commande en base.</p>
                    <p style="font-size:0.88rem;color:var(--texte-mute);margin-top:8px;">Si la commande devrait contenir des articles, vérifiez la table <code>commande_produits</code> pour l’ID <?php echo (int) $commande_id; ?>.</p>
                </div>
            <?php else: ?>
                <div class="sa-users-table-wrap">
                    <table class="sa-users-table sa-cmd-lines">
                        <thead>
                            <tr>
                                <th scope="col">Produit</th>
                                <th scope="col">Qté</th>
                                <th scope="col">Prix unitaire</th>
                                <th scope="col">Total ligne</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes as $L): ?>
                                <?php
                                $pnom = trim((string) ($L['produit_nom'] ?? $L['nom'] ?? ''));
                                if ($pnom === '') {
                                    $pnom = 'Produit #' . (int) ($L['produit_id'] ?? 0);
                                }
                                $img = (string) ($L['image_afficher'] ?? $L['image_principale'] ?? '');
                                if ($img === '') {
                                    $img = 'produit1.jpg';
                                }
                                $q = (int) ($L['quantite'] ?? 0);
                                $pu = (float) ($L['prix_unitaire'] ?? 0);
                                $pt = (float) ($L['prix_total'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;gap:10px;align-items:flex-start;">
                                            <img class="thumb" src="/upload/<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" onerror="this.src='/image/produit1.jpg'">
                                            <div>
                                                <strong><?php echo htmlspecialchars($pnom, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <?php if (!empty($L['variante_nom'])): ?>
                                                    <div style="font-size:0.82rem;color:var(--texte-mute);"><?php echo htmlspecialchars((string) $L['variante_nom'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo (int) $q; ?></td>
                                    <td><?php echo htmlspecialchars($fmt_fcfa($pu), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="sa-user-ca"><?php echo htmlspecialchars($fmt_fcfa($pt), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
