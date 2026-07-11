<?php
/**
 * Historique des ventes / Comptabilité vendeur & admin
 * Filtres : jour, mois, plage, année + statut + tri
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_route_access.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/includes/commandes_v2_helpers.php';

$vf_hist = admin_vendeur_filter_id();

$periode = isset($_GET['periode']) ? (string) $_GET['periode'] : 'jour';
if (!in_array($periode, ['jour', 'mois', 'plage', 'annee'], true)) {
    $periode = 'jour';
}

$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');
$mois = isset($_GET['mois']) ? (int) $_GET['mois'] : (int) date('n');
$jour = isset($_GET['jour']) ? (int) $_GET['jour'] : (int) date('j');
$date_debut = isset($_GET['date_debut']) ? trim((string) $_GET['date_debut']) : '';
$date_fin = isset($_GET['date_fin']) ? trim((string) $_GET['date_fin']) : '';

$filtre_statut = isset($_GET['filtre_statut']) ? (string) $_GET['filtre_statut'] : 'toutes';
if (!in_array($filtre_statut, ['toutes', 'vendues', 'en_cours', 'annulees'], true)) {
    $filtre_statut = 'toutes';
}

$tri = isset($_GET['tri']) ? (string) $_GET['tri'] : 'date_desc';
if (!in_array($tri, ['date_desc', 'date_asc', 'montant_desc', 'montant_asc'], true)) {
    $tri = 'date_desc';
}

if ($mois < 1 || $mois > 12) {
    $mois = (int) date('n');
}
if ($jour < 1 || $jour > 31) {
    $jour = (int) date('j');
}
if ($annee < 2000 || $annee > 2100) {
    $annee = (int) date('Y');
}

if (!empty($_GET['date_jour'])) {
    $parts = explode('-', (string) $_GET['date_jour']);
    if (count($parts) === 3) {
        $annee = (int) $parts[0];
        $mois = (int) $parts[1];
        $jour = (int) $parts[2];
    }
}

$filtrer_vendues_sql = ($filtre_statut === 'vendues');
$commandes = get_commandes_by_periode(
    $periode,
    $annee,
    $mois,
    $date_debut !== '' ? $date_debut : null,
    $date_fin !== '' ? $date_fin : null,
    $jour,
    $filtrer_vendues_sql,
    $vf_hist
);

if (!$filtrer_vendues_sql) {
    $commandes = cmd_v2_filtre_statut_commandes($commandes, $filtre_statut);
}

$commandes = cmd_v2_tri_commandes($commandes, $tri);
$stats = get_stats_comptabilite_periode($commandes);

$stats_globales_vendues = get_stats_commandes_vendues_globales($vf_hist);
$mt_all = get_montant_total_commandes(null, $vf_hist);
$mt_liv = get_montant_total_commandes('livree', $vf_hist) + get_montant_total_commandes('paye', $vf_hist);
$nb_livrees = count_commandes_by_statut('livree', $vf_hist) + count_commandes_by_statut('paye', $vf_hist);
$nb_annulees = count_commandes_by_statut('annulee', $vf_hist);
$nb_total = count_commandes_by_statut(null, $vf_hist);
$mt_ann = get_montant_total_commandes('annulee', $vf_hist);
$mt_en_cours = max(0, $mt_all - $mt_liv - $mt_ann);

$libelle_periode = '';
switch ($periode) {
    case 'jour':
        $libelle_periode = date('d/m/Y', strtotime(sprintf('%04d-%02d-%02d', $annee, $mois, $jour)));
        break;
    case 'mois':
        $mois_noms = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $libelle_periode = ucfirst($mois_noms[$mois] ?? '') . ' ' . $annee;
        break;
    case 'plage':
        if ($date_debut !== '' && $date_fin !== '') {
            $libelle_periode = date('d/m/Y', strtotime($date_debut)) . ' — ' . date('d/m/Y', strtotime($date_fin));
        } elseif ($date_debut !== '') {
            $libelle_periode = 'À partir du ' . date('d/m/Y', strtotime($date_debut));
        } elseif ($date_fin !== '') {
            $libelle_periode = "Jusqu'au " . date('d/m/Y', strtotime($date_fin));
        } else {
            $libelle_periode = 'Période personnalisée';
        }
        break;
    case 'annee':
        $libelle_periode = 'Année ' . $annee;
        break;
}

$date_jour_value = sprintf('%04d-%02d-%02d', $annee, $mois, $jour);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des ventes &mdash; Administration COLObanes</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-v2.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container">
        <div class="cmd-v2-page">

            <header class="cmd-v2-header">
                <div class="cmd-v2-header__left">
                    <p class="cmd-v2-header__eyebrow"><i class="fas fa-chart-line"></i> Comptabilit&eacute; &amp; ventes</p>
                    <h1 class="cmd-v2-header__title">Historique des ventes</h1>
                </div>
                <div class="cmd-v2-header__actions">
                    <a href="index.php" class="cmd-v2-btn cmd-v2-btn--outline">
                        <i class="fas fa-shopping-bag"></i> Commandes
                    </a>
                    <a href="livrees.php" class="cmd-v2-btn cmd-v2-btn--outline">
                        <i class="fas fa-check-circle"></i> Livr&eacute;es
                    </a>
                </div>
            </header>

            <div class="cmd-v2-hero">
                <div class="cmd-v2-hero__inner">
                    <div>
                        <p class="cmd-v2-hero__label">Gains r&eacute;alis&eacute;s — P&eacute;riode s&eacute;lectionn&eacute;e</p>
                        <div class="cmd-v2-hero__amount">
                            <?php echo number_format($stats['montant_livrees'], 0, ',', ' '); ?><span>FCFA</span>
                        </div>
                        <div class="cmd-v2-hero__pills">
                            <div class="cmd-v2-hero__pill cmd-v2-hero__pill--ok">
                                <i class="fas fa-receipt"></i>
                                <span><strong><?php echo $stats['nb_livrees']; ?></strong> vente<?php echo $stats['nb_livrees'] > 1 ? 's' : ''; ?> finalis&eacute;e<?php echo $stats['nb_livrees'] > 1 ? 's' : ''; ?></span>
                            </div>
                            <div class="cmd-v2-hero__pill">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?php echo htmlspecialchars($libelle_periode, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="cmd-v2-hero__right">
                        <span class="cmd-v2-hero__cta" style="cursor:default;">
                            <i class="fas fa-coins"></i>
                            Total global : <?php echo number_format($stats_globales_vendues['ca_total'], 0, ',', ' '); ?> FCFA
                        </span>
                    </div>
                </div>
            </div>

            <form method="get" action="historique-ventes.php" class="cmd-v2-filters" id="hist-filter-form">
                <div class="cmd-v2-filters__grid">
                    <div class="cmd-v2-filters__group">
                        <label for="periode-select"><i class="fas fa-filter"></i> P&eacute;riode</label>
                        <select name="periode" id="periode-select">
                            <option value="jour" <?php echo $periode === 'jour' ? 'selected' : ''; ?>>Jour</option>
                            <option value="mois" <?php echo $periode === 'mois' ? 'selected' : ''; ?>>Mois</option>
                            <option value="plage" <?php echo $periode === 'plage' ? 'selected' : ''; ?>>Plage de dates</option>
                            <option value="annee" <?php echo $periode === 'annee' ? 'selected' : ''; ?>>Ann&eacute;e</option>
                        </select>
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-jour" style="display:<?php echo $periode === 'jour' ? 'block' : 'none'; ?>;">
                        <label for="date-jour"><i class="fas fa-calendar-day"></i> Date</label>
                        <input type="date" name="date_jour" id="date-jour" value="<?php echo htmlspecialchars($date_jour_value, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-mois" style="display:<?php echo $periode === 'mois' ? 'block' : 'none'; ?>;">
                        <label for="hist-mois"><i class="fas fa-calendar"></i> Mois</label>
                        <select name="mois" id="hist-mois">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $mois === $m ? 'selected' : ''; ?>>
                                    <?php echo str_pad((string) $m, 2, '0', STR_PAD_LEFT); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-mois-annee" style="display:<?php echo $periode === 'mois' ? 'block' : 'none'; ?>;">
                        <label for="hist-annee-mois"><i class="fas fa-calendar-alt"></i> Ann&eacute;e</label>
                        <select name="annee" id="hist-annee-mois">
                            <?php for ($a = (int) date('Y'); $a >= (int) date('Y') - 8; $a--): ?>
                                <option value="<?php echo $a; ?>" <?php echo $annee === $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-plage" style="display:<?php echo $periode === 'plage' ? 'block' : 'none'; ?>;">
                        <label for="date-debut">Date d&eacute;but</label>
                        <input type="date" name="date_debut" id="date-debut" value="<?php echo htmlspecialchars($date_debut !== '' ? $date_debut : date('Y-m-01'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-plage-fin" style="display:<?php echo $periode === 'plage' ? 'block' : 'none'; ?>;">
                        <label for="date-fin">Date fin</label>
                        <input type="date" name="date_fin" id="date-fin" value="<?php echo htmlspecialchars($date_fin !== '' ? $date_fin : date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-annee" style="display:<?php echo $periode === 'annee' ? 'block' : 'none'; ?>;">
                        <label for="hist-annee"><i class="fas fa-calendar-alt"></i> Ann&eacute;e</label>
                        <select name="annee" id="hist-annee">
                            <?php for ($a = (int) date('Y'); $a >= (int) date('Y') - 8; $a--): ?>
                                <option value="<?php echo $a; ?>" <?php echo $annee === $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="cmd-v2-filters__group">
                        <label for="filtre-statut"><i class="fas fa-tags"></i> Statut</label>
                        <select name="filtre_statut" id="filtre-statut">
                            <option value="toutes" <?php echo $filtre_statut === 'toutes' ? 'selected' : ''; ?>>Toutes</option>
                            <option value="vendues" <?php echo $filtre_statut === 'vendues' ? 'selected' : ''; ?>>Ventes finalis&eacute;es (livr&eacute;e / pay&eacute;e)</option>
                            <option value="en_cours" <?php echo $filtre_statut === 'en_cours' ? 'selected' : ''; ?>>En cours de traitement</option>
                            <option value="annulees" <?php echo $filtre_statut === 'annulees' ? 'selected' : ''; ?>>Annul&eacute;es</option>
                        </select>
                    </div>
                    <div class="cmd-v2-filters__group">
                        <label for="tri-select"><i class="fas fa-sort"></i> Tri</label>
                        <select name="tri" id="tri-select">
                            <option value="date_desc" <?php echo $tri === 'date_desc' ? 'selected' : ''; ?>>Plus r&eacute;centes</option>
                            <option value="date_asc" <?php echo $tri === 'date_asc' ? 'selected' : ''; ?>>Plus anciennes</option>
                            <option value="montant_desc" <?php echo $tri === 'montant_desc' ? 'selected' : ''; ?>>Montant d&eacute;croissant</option>
                            <option value="montant_asc" <?php echo $tri === 'montant_asc' ? 'selected' : ''; ?>>Montant croissant</option>
                        </select>
                    </div>
                    <div class="cmd-v2-filters__group">
                        <label>&nbsp;</label>
                        <button type="submit" class="cmd-v2-filters__submit">
                            <i class="fas fa-search"></i> Appliquer
                        </button>
                    </div>
                </div>
            </form>

            <div class="cmd-v2-section-head">
                <div>
                    <h2><i class="fas fa-globe"></i> Vue d'ensemble globale</h2>
                    <p>Toutes dates — boutique<?php echo $vf_hist ? ' vendeur' : ''; ?></p>
                </div>
            </div>
            <div class="cmd-v2-kpi-row" style="margin-bottom:20px;">
                <div class="cmd-v2-kpi cmd-v2-kpi--highlight">
                    <label>Gains r&eacute;alis&eacute;s (livr&eacute; + pay&eacute;)</label>
                    <strong><?php echo number_format($stats_globales_vendues['ca_total'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>Ventes finalis&eacute;es</label>
                    <strong><?php echo $stats_globales_vendues['nb']; ?></strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>En cours (montant)</label>
                    <strong><?php echo number_format($mt_en_cours, 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>Commandes totales</label>
                    <strong><?php echo $nb_total; ?></strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>Annul&eacute;es</label>
                    <strong><?php echo $nb_annulees; ?></strong>
                </div>
            </div>

            <div class="cmd-v2-section-head">
                <div>
                    <h2><i class="fas fa-calendar-check"></i> P&eacute;riode : <?php echo htmlspecialchars($libelle_periode, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo count($commandes); ?> r&eacute;sultat<?php echo count($commandes) > 1 ? 's' : ''; ?> — tri : <?php echo htmlspecialchars(str_replace('_', ' ', $tri), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="cmd-v2-kpi-row" style="margin-bottom:22px;">
                <div class="cmd-v2-kpi cmd-v2-kpi--highlight">
                    <label>Gains p&eacute;riode</label>
                    <strong><?php echo number_format($stats['montant_livrees'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>Commandes p&eacute;riode</label>
                    <strong><?php echo $stats['nb_commandes']; ?></strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>Ventes finalis&eacute;es</label>
                    <strong><?php echo $stats['nb_livrees']; ?></strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>En cours</label>
                    <strong><?php echo number_format($stats['montant_non_traitees'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>Annul&eacute;es</label>
                    <strong><?php echo (int) ($stats['nb_annulees'] ?? 0); ?></strong>
                </div>
            </div>

            <?php if (empty($commandes)): ?>
                <div class="cmd-v2-empty">
                    <div class="cmd-v2-empty__icon"><i class="fas fa-receipt"></i></div>
                    <h3>Aucune vente sur cette p&eacute;riode</h3>
                    <p>Modifiez la p&eacute;riode, le statut ou le tri pour afficher l'historique.</p>
                </div>
            <?php else: ?>
                <div class="cmd-v2-table-wrap">
                    <table class="cmd-v2-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>N&deg; commande</th>
                                <th>Client</th>
                                <th>Statut</th>
                                <th class="montant">Montant</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $c): ?>
                                <?php
                                $client = trim(($c['user_prenom'] ?? '') . ' ' . ($c['user_nom'] ?? ''));
                                if ($client === '') {
                                    $client = 'Client inconnu';
                                }
                                $st = $c['statut'] ?? '';
                                ?>
                                <tr>
                                    <td><?php echo !empty($c['date_commande']) ? date('d/m/Y H:i', strtotime($c['date_commande'])) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($c['numero_commande'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($client, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="cmd-badge <?php echo cmd_v2_statut_class($st); ?>">
                                            <?php echo htmlspecialchars(cmd_v2_statut_label($st), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="montant"><?php echo number_format((float) ($c['montant_total'] ?? 0), 0, ',', ' '); ?> FCFA</td>
                                    <td>
                                        <a href="details.php?id=<?php echo (int) ($c['id'] ?? 0); ?>" class="cmd-v2-table__link" title="Voir le d&eacute;tail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    (function () {
        var periodeSelect = document.getElementById('periode-select');
        function togglePeriodeFields(periode) {
            var map = {
                jour: ['wrap-jour'],
                mois: ['wrap-mois', 'wrap-mois-annee'],
                plage: ['wrap-plage', 'wrap-plage-fin'],
                annee: ['wrap-annee']
            };
            ['wrap-jour', 'wrap-mois', 'wrap-mois-annee', 'wrap-plage', 'wrap-plage-fin', 'wrap-annee'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            (map[periode] || []).forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'block';
            });
        }
        if (periodeSelect) {
            periodeSelect.addEventListener('change', function () {
                togglePeriodeFields(this.value);
            });
            togglePeriodeFields(periodeSelect.value);
        }

        var form = document.getElementById('hist-filter-form');
        if (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('.cmd-v2-filters__group').forEach(function (group) {
                    if (group.style.display === 'none') {
                        group.querySelectorAll('select, input').forEach(function (el) {
                            el.disabled = true;
                        });
                    }
                });
            });
        }
    })();
    </script>
</body>

</html>
