<?php
/**
 * Clients inscrits sur la plateforme (table users)
 * Recherche, filtres, pagination (GET) — actions en POST + CSRF
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_users.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

/**
 * Paramètres de requête pour liens (pagination, reset, retour POST)
 */
function sa_users_query_params(array $overrides = []) {
    static $base = null;
    if ($base === null) {
        $base = [
            'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
            'statut' => isset($_GET['statut']) ? trim((string) $_GET['statut']) : '',
            'per' => isset($_GET['per']) ? (int) $_GET['per'] : 15,
            'p' => isset($_GET['p']) ? (int) $_GET['p'] : 1,
        ];
    }
    $m = array_merge($base, $overrides);
    if ($m['per'] < 5) {
        $m['per'] = 5;
    }
    if ($m['per'] > 100) {
        $m['per'] = 100;
    }
    if ($m['p'] < 1) {
        $m['p'] = 1;
    }
    $q = [];
    if ($m['q'] !== '') {
        $q['q'] = $m['q'];
    }
    if ($m['statut'] === 'actif' || $m['statut'] === 'inactif') {
        $q['statut'] = $m['statut'];
    }
    if ((int) $m['per'] !== 15) {
        $q['per'] = (int) $m['per'];
    }
    if ((int) $m['p'] > 1) {
        $q['p'] = (int) $m['p'];
    }
    return $q;
}

/**
 * Pages à afficher pour la pagination (avec ellipses)
 *
 * @return array<int|null> null = élipse
 */
function sa_users_pagination_model($current, $total_pages) {
    if ($total_pages <= 1) {
        return [];
    }
    $candidats = [];
    $near = 2;
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i === 1 || $i === $total_pages) {
            $candidats[$i] = true;
        } elseif ($i >= $current - $near && $i <= $current + $near) {
            $candidats[$i] = true;
        }
    }
    ksort($candidats);
    $keys = array_keys($candidats);
    $out = [];
    $prev = 0;
    foreach ($keys as $p) {
        if ($prev && $p - $prev > 1) {
            $out[] = null;
        }
        $out[] = $p;
        $prev = $p;
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_statut'])) {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $_SESSION['super_admin_flash_err'] = 'Jeton de sécurité invalide.';
        header('Location: index.php');
        exit;
    }
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $nouveau = isset($_POST['nouveau_statut']) ? (string) $_POST['nouveau_statut'] : '';
    if ($user_id > 0 && in_array($nouveau, ['actif', 'inactif'], true)) {
        if (update_user_statut($user_id, $nouveau)) {
            super_admin_log_action(
                (int) $_SESSION['super_admin_id'],
                $nouveau === 'actif' ? 'client_activé' : 'client_désactivé',
                'user',
                $user_id,
                ''
            );
            $_SESSION['super_admin_flash_ok'] = $nouveau === 'actif' ? 'Client activé.' : 'Client désactivé.';
        } else {
            $_SESSION['super_admin_flash_err'] = 'Erreur lors de la mise à jour.';
        }
    }
    $retQ = [
        'q' => isset($_POST['return_q']) ? trim((string) $_POST['return_q']) : '',
        'statut' => isset($_POST['return_statut']) ? trim((string) $_POST['return_statut']) : '',
        'per' => isset($_POST['return_per']) ? (int) $_POST['return_per'] : 15,
        'p' => isset($_POST['return_p']) ? (int) $_POST['return_p'] : 1,
    ];
    header('Location: index.php?' . http_build_query(sa_users_query_params($retQ)));
    exit;
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$statut_filtre = isset($_GET['statut']) ? trim((string) $_GET['statut']) : '';
if ($statut_filtre !== 'actif' && $statut_filtre !== 'inactif') {
    $statut_filtre = '';
}

$per_page = isset($_GET['per']) ? (int) $_GET['per'] : 15;
if ($per_page < 5) {
    $per_page = 5;
}
if ($per_page > 100) {
    $per_page = 100;
}

$page = isset($_GET['p']) ? (int) $_GET['p'] : 1;
if ($page < 1) {
    $page = 1;
}

$total = count_users_platform_filtered($search, $statut_filtre);
$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}

$users = get_users_platform_paginated($search, $statut_filtre, $page, $per_page);
$csrf = super_admin_csrf_token();

$total_plateforme = count_users_platform_filtered('', '');
$total_actifs = count_users_platform_filtered('', 'actif');

$from_row = $total > 0 ? ($page - 1) * $per_page + 1 : 0;
$to_row = min($page * $per_page, $total);

$pagination_model = sa_users_pagination_model($page, $total_pages);

/**
 * @param int $pageNum
 */
function sa_users_page_url($pageNum) {
    $q = sa_users_query_params(['p' => $pageNum]);
    return 'index.php?' . http_build_query($q);
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients plateforme — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero" aria-labelledby="sa-users-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-shield-halved" aria-hidden="true"></i> Espace super administrateur</p>
                    <h1 class="sa-users-hero__title" id="sa-users-title">Clients de la plateforme</h1>
                    <p class="sa-users-hero__lead">
                        Gérez les comptes acheteurs : recherchez par nom, e-mail ou téléphone, filtrez par statut, parcourez les pages et agissez sur l’accès (connexion client).
                    </p>
                </div>
                <div class="sa-users-kpis" role="group" aria-label="Indicateurs">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Inscrits (total)</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $total_plateforme; ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Comptes actifs</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $total_actifs; ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Résultats filtre</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $total; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($msg_ok !== ''): ?>
            <div class="sa-alert sa-alert--ok" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_ok, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($msg_err !== ''): ?>
            <div class="sa-alert sa-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <form class="sa-users-toolbar" method="get" action="index.php" role="search" aria-label="Filtrer les clients">
            <div class="sa-users-search">
                <label for="sa-q">Rechercher un client</label>
                <div class="sa-users-search__wrap">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="sa-q" name="q" placeholder="Nom, prénom, e-mail, téléphone…" autocomplete="off"
                        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="sa-users-search__submit">Rechercher</button>
                </div>
            </div>
            <div class="sa-users-filters">
                <div class="sa-field">
                    <label for="sa-statut">Statut</label>
                    <select id="sa-statut" name="statut" onchange="this.form.submit()">
                        <option value="" <?php echo $statut_filtre === '' ? 'selected' : ''; ?>>Tous</option>
                        <option value="actif" <?php echo $statut_filtre === 'actif' ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactif" <?php echo $statut_filtre === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                </div>
                <div class="sa-field">
                    <label for="sa-per">Par page</label>
                    <select id="sa-per" name="per" onchange="this.form.submit()">
                        <?php foreach ([10, 15, 25, 50] as $n): ?>
                            <option value="<?php echo $n; ?>" <?php echo $per_page === $n ? 'selected' : ''; ?>><?php echo $n; ?> lignes</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a class="sa-users-filters__reset" href="index.php"><i class="fas fa-rotate-left" aria-hidden="true"></i> Réinitialiser</a>
            </div>
        </form>

        <section class="sa-users-panel" aria-labelledby="sa-panel-title">
            <div class="sa-users-panel__head">
                <h2 id="sa-panel-title"><i class="fas fa-table-list" aria-hidden="true"></i> Liste des clients</h2>
                <p class="sa-users-panel__meta">
                    <?php if ($total > 0): ?>
                        Affichage <strong><?php echo (int) $from_row; ?></strong> – <strong><?php echo (int) $to_row; ?></strong>
                        sur <strong><?php echo (int) $total; ?></strong>
                    <?php else: ?>
                        Aucun résultat pour ces critères.
                    <?php endif; ?>
                </p>
            </div>

            <?php if (empty($users)): ?>
                <div class="sa-users-empty">
                    <i class="fas fa-user-slash" aria-hidden="true"></i>
                    <p><strong>Aucun client ne correspond à votre recherche.</strong></p>
                    <p>Élargissez les filtres ou réinitialisez le formulaire ci-dessus.</p>
                </div>
            <?php else: ?>
                <div class="sa-users-table-wrap">
                    <table class="sa-users-table">
                        <thead>
                            <tr>
                                <th scope="col">Client</th>
                                <th scope="col">Contact</th>
                                <th scope="col">Inscription</th>
                                <th scope="col">Commandes</th>
                                <th scope="col">CA (FCFA, hors annulées)</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Fiche</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <?php
                                $dc = $u['date_creation'] ?? '';
                                $dc_fmt = $dc !== '' ? date('d/m/Y à H:i', strtotime($dc)) : '—';
                                ?>
                                <tr>
                                    <td>
                                        <div class="sa-user-cell__name"><?php echo htmlspecialchars(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td>
                                        <span class="sa-user-cell__email"><?php echo htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($u['telephone'])): ?>
                                            <span class="sa-user-cell__tel"><?php echo htmlspecialchars((string) $u['telephone'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="sa-user-stat-num" style="font-weight:500;color:var(--texte-mute);"><?php echo htmlspecialchars($dc_fmt, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><span class="sa-user-stat-num"><?php echo (int) ($u['nb_commandes'] ?? 0); ?></span></td>
                                    <td><span class="sa-user-ca"><?php echo number_format((float) ($u['ca_total_ht'] ?? 0), 0, ',', ' '); ?> FCFA</span></td>
                                    <td>
                                        <?php if (($u['statut'] ?? '') === 'actif'): ?>
                                            <span class="sa-badge sa-badge--ok">Actif</span>
                                        <?php else: ?>
                                            <span class="sa-badge sa-badge--off">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="sa-btn-action sa-btn-action--primary" href="detail.php?id=<?php echo (int) $u['id']; ?>"
                                            style="display:inline-flex;text-decoration:none;white-space:nowrap;">
                                            <i class="fas fa-eye" aria-hidden="true"></i> Détails
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (($u['statut'] ?? '') === 'actif'): ?>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Désactiver ce client ? Il ne pourra plus se connecter.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                                <input type="hidden" name="nouveau_statut" value="inactif">
                                                <input type="hidden" name="toggle_statut" value="1">
                                                <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="return_statut" value="<?php echo htmlspecialchars($statut_filtre, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="return_per" value="<?php echo (int) $per_page; ?>">
                                                <input type="hidden" name="return_p" value="<?php echo (int) $page; ?>">
                                                <button type="submit" class="sa-btn-action sa-btn-action--danger"><i class="fas fa-user-lock" aria-hidden="true"></i> Désactiver</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                                <input type="hidden" name="nouveau_statut" value="actif">
                                                <input type="hidden" name="toggle_statut" value="1">
                                                <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="return_statut" value="<?php echo htmlspecialchars($statut_filtre, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="return_per" value="<?php echo (int) $per_page; ?>">
                                                <input type="hidden" name="return_p" value="<?php echo (int) $page; ?>">
                                                <button type="submit" class="sa-btn-action sa-btn-action--primary"><i class="fas fa-user-check" aria-hidden="true"></i> Réactiver</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <nav class="sa-pagination" aria-label="Pagination des résultats">
                    <p class="sa-pagination__info">Page <strong><?php echo (int) $page; ?></strong> sur <strong><?php echo (int) $total_pages; ?></strong></p>
                    <div style="display:flex;flex-wrap:wrap;gap:0.45rem;justify-content:center;align-items:center;">
                        <?php if ($page > 1): ?>
                            <a class="sa-pagination__btn" href="<?php echo htmlspecialchars(sa_users_page_url($page - 1), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page précédente">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <span class="sa-pagination__btn sa-pagination__btn--disabled" aria-disabled="true"><i class="fas fa-chevron-left" aria-hidden="true"></i></span>
                        <?php endif; ?>

                        <?php foreach ($pagination_model as $pn): ?>
                            <?php if ($pn === null): ?>
                                <span class="sa-pagination__ellipsis" aria-hidden="true">…</span>
                            <?php else: ?>
                                <?php if ((int) $pn === (int) $page): ?>
                                    <span class="sa-pagination__btn sa-pagination__btn--active" aria-current="page"><?php echo (int) $pn; ?></span>
                                <?php else: ?>
                                    <a class="sa-pagination__btn" href="<?php echo htmlspecialchars(sa_users_page_url((int) $pn), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $pn; ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($page < $total_pages): ?>
                            <a class="sa-pagination__btn" href="<?php echo htmlspecialchars(sa_users_page_url($page + 1), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page suivante">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <span class="sa-pagination__btn sa-pagination__btn--disabled" aria-disabled="true"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
