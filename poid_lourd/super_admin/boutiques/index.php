<?php
/**
 * Liste des boutiques (vendeurs) — recherche, filtres, pagination (GET)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
require_once dirname(__DIR__, 2) . '/includes/marketplace_countries.php';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

/**
 * Paramètres de requête (pagination, liens, cohérence avec toggle-statut.php)
 */
function sb_boutiques_query_params(array $overrides = []) {
    static $base = null;
    $countries_nav = marketplace_countries_nav_list();
    $default_pays = $countries_nav ? (string) array_key_first($countries_nav) : 'SN';

    if ($base === null) {
        $pays_in = isset($_GET['pays']) ? strtoupper(trim((string) $_GET['pays'])) : $default_pays;
        $statut_in = isset($_GET['statut']) ? trim((string) $_GET['statut']) : 'actif';
        $base = [
            'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
            'pays' => marketplace_country_is_valid($pays_in) ? $pays_in : $default_pays,
            'statut' => ($statut_in === 'actif' || $statut_in === 'inactif') ? $statut_in : 'actif',
            'cert' => isset($_GET['cert']) ? trim((string) $_GET['cert']) : 'non_certifie',
            'per' => isset($_GET['per']) ? (int) $_GET['per'] : 15,
            'p' => isset($_GET['p']) ? (int) $_GET['p'] : 1,
        ];
    }
    $m = array_merge($base, $overrides);
    if (!marketplace_country_is_valid($m['pays'])) {
        $m['pays'] = $default_pays;
    }
    if ($m['statut'] !== 'actif' && $m['statut'] !== 'inactif') {
        $m['statut'] = 'actif';
    }
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
    $q['pays'] = $m['pays'];
    if ($m['statut'] === 'actif' || $m['statut'] === 'inactif') {
        $q['statut'] = $m['statut'];
    }
    if ($m['cert'] === 'certifie' || $m['cert'] === 'non_certifie') {
        $q['cert'] = $m['cert'];
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
 * @return array<int|null>
 */
function sb_boutiques_pagination_model($current, $total_pages) {
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

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$countries_nav = marketplace_countries_nav_list();
$default_pays = $countries_nav ? (string) array_key_first($countries_nav) : 'SN';
$pays_tab = isset($_GET['pays']) ? strtoupper(trim((string) $_GET['pays'])) : $default_pays;
if (!marketplace_country_is_valid($pays_tab)) {
    $pays_tab = $default_pays;
}

$statut_filtre = isset($_GET['statut']) ? trim((string) $_GET['statut']) : 'actif';
if ($statut_filtre !== 'actif' && $statut_filtre !== 'inactif') {
    $statut_filtre = 'actif';
}

$cert_tab = isset($_GET['cert']) ? trim((string) $_GET['cert']) : 'non_certifie';
if ($cert_tab !== 'certifie' && $cert_tab !== 'non_certifie') {
    $cert_tab = 'non_certifie';
}
$cert_col_ok = vendeur_certification_admin_column_exists();
if (!$cert_col_ok) {
    $cert_tab = '';
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

$total = count_boutiques_platform_filtered($search, $statut_filtre, $cert_tab, $pays_tab);
$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}

$boutiques = get_boutiques_platform_paginated($search, $statut_filtre, $page, $per_page, $cert_tab, $pays_tab);
$csrf = super_admin_csrf_token();

$total_plateforme = count_boutiques_platform_filtered('', '');
$total_actives = count_boutiques_platform_filtered('', 'actif');
$total_certifiees = $cert_col_ok ? count_boutiques_platform_filtered('', '', 'certifie') : 0;
$total_non_certifiees = $cert_col_ok ? count_boutiques_platform_filtered('', '', 'non_certifie') : $total_plateforme;

$pays_counts = [];
$statut_counts_pays = ['actif' => 0, 'inactif' => 0];
foreach ($countries_nav as $code => $meta) {
    $pays_counts[$code] = count_boutiques_platform_filtered('', '', $cert_tab, $code);
}
$statut_counts_pays['actif'] = count_boutiques_platform_filtered('', 'actif', $cert_tab, $pays_tab);
$statut_counts_pays['inactif'] = count_boutiques_platform_filtered('', 'inactif', $cert_tab, $pays_tab);

$from_row = $total > 0 ? ($page - 1) * $per_page + 1 : 0;
$to_row = min($page * $per_page, $total);

$pagination_model = sb_boutiques_pagination_model($page, $total_pages);

/**
 * @param int $pageNum
 */
function sb_boutiques_page_url($pageNum) {
    $q = sb_boutiques_query_params(['p' => $pageNum]);

    return 'index.php?' . http_build_query($q);
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutiques — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-boutiques.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero" aria-labelledby="sa-btq-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-store" aria-hidden="true"></i> Marketplace — vendeurs</p>
                    <h1 class="sa-users-hero__title" id="sa-btq-title">Boutiques inscrites</h1>
                    <p class="sa-users-hero__lead">
                        Parcourez les boutiques par pays, filtrez les comptes actifs ou désactivés, recherchez par nom commercial ou contact et gérez l’activation des vendeurs.
                    </p>
                </div>
                <div class="sa-users-kpis" role="group" aria-label="Indicateurs boutiques">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Boutiques (total)</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $total_plateforme; ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Accès actifs</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $total_actives; ?></span>
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

        <nav class="sa-btq-tabs sa-btq-tabs--countries" aria-label="Filtrer par pays">
            <?php foreach ($countries_nav as $code => $meta): ?>
                <a href="<?php echo htmlspecialchars('index.php?' . http_build_query(sb_boutiques_query_params(['pays' => $code, 'p' => 1])), ENT_QUOTES, 'UTF-8'); ?>"
                    class="sa-btq-tab<?php echo $pays_tab === $code ? ' is-active' : ''; ?>">
                    <img src="<?php echo htmlspecialchars(marketplace_country_flag_url($code, 40), ENT_QUOTES, 'UTF-8'); ?>"
                        alt="" width="22" height="16" class="sa-btq-tab__flag" loading="lazy">
                    <?php echo htmlspecialchars((string) $meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                    <span class="sa-btq-tab__count"><?php echo (int) ($pays_counts[$code] ?? 0); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <nav class="sa-btq-subtabs" aria-label="Filtrer par statut d'accès">
            <a href="<?php echo htmlspecialchars('index.php?' . http_build_query(sb_boutiques_query_params(['statut' => 'actif', 'p' => 1])), ENT_QUOTES, 'UTF-8'); ?>"
                class="sa-btq-subtab<?php echo $statut_filtre === 'actif' ? ' is-active' : ''; ?>">
                <i class="fas fa-circle-check" aria-hidden="true"></i> Boutiques actives
                <span class="sa-btq-subtab__count"><?php echo (int) $statut_counts_pays['actif']; ?></span>
            </a>
            <a href="<?php echo htmlspecialchars('index.php?' . http_build_query(sb_boutiques_query_params(['statut' => 'inactif', 'p' => 1])), ENT_QUOTES, 'UTF-8'); ?>"
                class="sa-btq-subtab<?php echo $statut_filtre === 'inactif' ? ' is-active' : ''; ?>">
                <i class="fas fa-ban" aria-hidden="true"></i> Boutiques désactivées
                <span class="sa-btq-subtab__count"><?php echo (int) $statut_counts_pays['inactif']; ?></span>
            </a>
        </nav>

        <?php if ($cert_col_ok): ?>
            <nav class="sa-btq-tabs" aria-label="Filtrer par certification">
                <a href="<?php echo htmlspecialchars('index.php?' . http_build_query(sb_boutiques_query_params(['cert' => 'non_certifie', 'p' => 1])), ENT_QUOTES, 'UTF-8'); ?>"
                    class="sa-btq-tab<?php echo $cert_tab === 'non_certifie' ? ' is-active' : ''; ?>">
                    <i class="fas fa-store"></i> Non certifiées
                    <span class="sa-btq-tab__count"><?php echo (int) $total_non_certifiees; ?></span>
                </a>
                <a href="<?php echo htmlspecialchars('index.php?' . http_build_query(sb_boutiques_query_params(['cert' => 'certifie', 'p' => 1])), ENT_QUOTES, 'UTF-8'); ?>"
                    class="sa-btq-tab<?php echo $cert_tab === 'certifie' ? ' is-active' : ''; ?>">
                    <i class="fas fa-certificate"></i> Certifiées
                    <span class="sa-btq-tab__count"><?php echo (int) $total_certifiees; ?></span>
                </a>
            </nav>
        <?php endif; ?>

        <form class="sa-users-toolbar" method="get" action="index.php" role="search" aria-label="Filtrer les boutiques">
            <input type="hidden" name="pays" value="<?php echo htmlspecialchars($pays_tab, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="statut" value="<?php echo htmlspecialchars($statut_filtre, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($cert_col_ok && $cert_tab !== ''): ?>
                <input type="hidden" name="cert" value="<?php echo htmlspecialchars($cert_tab, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <div class="sa-users-search">
                <label for="sb-q">Rechercher une boutique</label>
                <div class="sa-users-search__wrap">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="sb-q" name="q" placeholder="Nom commercial, slug, nom, e-mail, téléphone…" autocomplete="off"
                        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="sa-users-search__submit">Rechercher</button>
                </div>
            </div>
            <div class="sa-users-filters">
                <div class="sa-field">
                    <label for="sb-per">Par page</label>
                    <select id="sb-per" name="per" onchange="this.form.submit()">
                        <?php foreach ([10, 15, 25, 50] as $n): ?>
                            <option value="<?php echo $n; ?>" <?php echo $per_page === $n ? 'selected' : ''; ?>><?php echo $n; ?> lignes</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a class="sa-users-filters__reset" href="index.php?<?php echo htmlspecialchars(http_build_query(['pays' => $pays_tab, 'statut' => $statut_filtre] + ($cert_col_ok && $cert_tab !== '' ? ['cert' => $cert_tab] : [])), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-rotate-left" aria-hidden="true"></i> Réinitialiser</a>
            </div>
        </form>

        <section class="sa-users-panel" aria-labelledby="sa-btq-panel-title">
            <div class="sa-users-panel__head">
                <h2 id="sa-btq-panel-title">
                    <i class="fas fa-store" aria-hidden="true"></i>
                    <?php echo htmlspecialchars(marketplace_country_label($pays_tab), ENT_QUOTES, 'UTF-8'); ?>
                    — <?php echo $statut_filtre === 'actif' ? 'Boutiques actives' : 'Boutiques désactivées'; ?>
                </h2>
                <p class="sa-users-panel__meta">
                    <?php if ($total > 0): ?>
                        Affichage <strong><?php echo (int) $from_row; ?></strong> – <strong><?php echo (int) $to_row; ?></strong>
                        sur <strong><?php echo (int) $total; ?></strong>
                    <?php else: ?>
                        Aucun résultat pour ces critères.
                    <?php endif; ?>
                </p>
            </div>

            <?php if (empty($boutiques)): ?>
                <div class="sa-users-empty">
                    <i class="fas fa-store-slash" aria-hidden="true"></i>
                    <p><strong>Aucune boutique ne correspond à votre recherche.</strong></p>
                    <p>Vérifiez le terme saisi ou élargissez les filtres.</p>
                </div>
            <?php else: ?>
                <div class="sa-users-table-wrap">
                    <table class="sa-users-table">
                        <thead>
                            <tr>
                                <th scope="col">Boutique</th>
                                <?php if ($cert_col_ok): ?><th scope="col">Certification</th><?php endif; ?>
                                <th scope="col">Identité / contact</th>
                                <th scope="col">Produits visibles</th>
                                <th scope="col">Statut accès</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($boutiques as $b): ?>
                                <tr>
                                    <td>
                                        <div class="sa-user-cell__name"><?php echo htmlspecialchars((string) ($b['boutique_nom'] ?: $b['nom']), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($b['boutique_slug'])): ?>
                                            <span class="sa-user-cell__email" style="display:block;margin-top:4px;"><code style="background:rgba(53,100,166,.08);padding:2px 8px;border-radius:6px;font-size:0.78rem;"><?php echo htmlspecialchars((string) $b['boutique_slug'], ENT_QUOTES, 'UTF-8'); ?></code></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($cert_col_ok): ?>
                                        <td>
                                            <?php
                                            $cn = trim((string) ($b['certification_niveau'] ?? ''));
                                            if ($cn !== '' && function_exists('vendeur_certification_niveau_label')):
                                                $cert_niveau = $cn;
                                                $cert_size = 'sm';
                                                require dirname(__DIR__, 2) . '/includes/partials/vendeur_certification_badge.php';
                                            else:
                                                ?>
                                                <span class="sa-badge sa-badge--mute">Non certifiée</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="sa-user-cell__name" style="font-weight:600;font-size:0.88rem;"><?php echo htmlspecialchars(trim($b['nom'] . ' ' . ($b['prenom'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($b['email'])): ?>
                                            <span class="sa-user-cell__email"><?php echo htmlspecialchars((string) $b['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($b['telephone'])): ?>
                                            <span class="sa-user-cell__tel"><?php echo htmlspecialchars((string) $b['telephone'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="sa-user-stat-num"><?php echo (int) $b['nb_produits_catalogue']; ?></span>
                                        <span style="display:block;font-size:0.8rem;color:var(--texte-mute);margin-top:4px;">au catalogue</span>
                                        <span style="font-size:0.78rem;color:var(--gris-moyen);">dont <strong><?php echo (int) $b['nb_produits_actifs']; ?></strong> actifs</span>
                                    </td>
                                    <td>
                                        <?php if (($b['statut'] ?? '') === 'actif'): ?>
                                            <span class="sa-badge sa-badge--ok">Actif</span>
                                        <?php else: ?>
                                            <span class="sa-badge sa-badge--off">Désactivé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                                            <a class="sa-btn-action sa-btn-action--primary" href="detail.php?id=<?php echo (int) $b['id']; ?>"><i class="fas fa-circle-info" aria-hidden="true"></i> Détails</a>
                                            <?php if (($b['statut'] ?? '') === 'actif'): ?>
                                                <form method="post" action="toggle-statut.php" style="margin:0;" onsubmit="return confirm('Désactiver cette boutique ? Les vendeurs ne pourront plus se connecter.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="vendeur_id" value="<?php echo (int) $b['id']; ?>">
                                                    <input type="hidden" name="nouveau_statut" value="inactif">
                                                    <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_pays" value="<?php echo htmlspecialchars($pays_tab, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_statut" value="<?php echo htmlspecialchars($statut_filtre, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_cert" value="<?php echo htmlspecialchars($cert_tab, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_per" value="<?php echo (int) $per_page; ?>">
                                                    <input type="hidden" name="return_p" value="<?php echo (int) $page; ?>">
                                                    <button type="submit" class="sa-btn-action sa-btn-action--danger"><i class="fas fa-ban" aria-hidden="true"></i> Désactiver</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="toggle-statut.php" style="margin:0;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="vendeur_id" value="<?php echo (int) $b['id']; ?>">
                                                    <input type="hidden" name="nouveau_statut" value="actif">
                                                    <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_pays" value="<?php echo htmlspecialchars($pays_tab, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_statut" value="<?php echo htmlspecialchars($statut_filtre, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_cert" value="<?php echo htmlspecialchars($cert_tab, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="return_per" value="<?php echo (int) $per_page; ?>">
                                                    <input type="hidden" name="return_p" value="<?php echo (int) $page; ?>">
                                                    <button type="submit" class="sa-btn-action sa-btn-action--primary"><i class="fas fa-check" aria-hidden="true"></i> Réactiver</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <nav class="sa-pagination" aria-label="Pagination des boutiques">
                    <p class="sa-pagination__info">Page <strong><?php echo (int) $page; ?></strong> sur <strong><?php echo (int) $total_pages; ?></strong></p>
                    <div style="display:flex;flex-wrap:wrap;gap:0.45rem;justify-content:center;align-items:center;">
                        <?php if ($page > 1): ?>
                            <a class="sa-pagination__btn" href="<?php echo htmlspecialchars(sb_boutiques_page_url($page - 1), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page précédente">
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
                                    <a class="sa-pagination__btn" href="<?php echo htmlspecialchars(sb_boutiques_page_url((int) $pn), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $pn; ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($page < $total_pages): ?>
                            <a class="sa-pagination__btn" href="<?php echo htmlspecialchars(sb_boutiques_page_url($page + 1), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page suivante">
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
