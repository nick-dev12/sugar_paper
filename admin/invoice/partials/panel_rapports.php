<?php
/**
 * Panneau Rapports — intégré dans admin/invoice/index.php
 * Variables : $rapport_annee, $rapport_vue, $rapport_annees, $rapport_mensuel, $rapport_clients, $rapport_articles, $bl_tables_ok
 */
$rapport_vue_allowed = ['paye', 'clients', 'articles'];
$rapport_vue = in_array($rapport_vue, $rapport_vue_allowed, true) ? $rapport_vue : 'paye';

function invoice_rapport_url($annee, $vue)
{
    return 'index.php?tab=rapports&annee=' . (int) $annee . '&vue=' . urlencode($vue);
}

function invoice_rapport_fmt_montant($montant)
{
    return number_format((float) $montant, 0, ',', ' ') . ' F CFA';
}

function invoice_rapport_fmt_quantite($quantite)
{
    return rtrim(rtrim(number_format((float) $quantite, 2, ',', ' '), '0'), ',');
}

$rapport_clients_totaux = ['nb_factures' => 0, 'montant' => 0.0];
foreach ($rapport_clients as $rc) {
    $rapport_clients_totaux['nb_factures'] += (int) ($rc['nb_factures'] ?? 0);
    $rapport_clients_totaux['montant'] += (float) ($rc['montant'] ?? 0);
}

$rapport_articles_totaux = ['nb_factures' => 0, 'quantite' => 0.0, 'montant' => 0.0];
foreach ($rapport_articles as $ra) {
    $rapport_articles_totaux['nb_factures'] += (int) ($ra['nb_factures'] ?? 0);
    $rapport_articles_totaux['quantite'] += (float) ($ra['quantite'] ?? 0);
    $rapport_articles_totaux['montant'] += (float) ($ra['montant'] ?? 0);
}
?>
<div class="invoice-rapports-wrap">
    <div class="invoice-rapports-header">
        <a href="../parametres.php" class="invoice-rapports-header__btn" title="Paramètres" aria-label="Paramètres">
            <i class="fas fa-cog"></i>
        </a>
        <h2>Rapports</h2>
        <a href="<?php echo htmlspecialchars(invoice_rapport_url($rapport_annee, $rapport_vue)); ?>" class="invoice-rapports-header__btn" title="Actualiser" aria-label="Actualiser">
            <i class="fas fa-sync-alt"></i>
        </a>
    </div>

    <div class="invoice-rapports-subtabs" role="tablist" aria-label="Type de rapport">
        <a href="<?php echo htmlspecialchars(invoice_rapport_url($rapport_annee, 'paye')); ?>"
           class="invoice-rapports-subtab <?php echo $rapport_vue === 'paye' ? 'is-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $rapport_vue === 'paye' ? 'true' : 'false'; ?>">Payé</a>
        <a href="<?php echo htmlspecialchars(invoice_rapport_url($rapport_annee, 'clients')); ?>"
           class="invoice-rapports-subtab <?php echo $rapport_vue === 'clients' ? 'is-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $rapport_vue === 'clients' ? 'true' : 'false'; ?>">Clients</a>
        <a href="<?php echo htmlspecialchars(invoice_rapport_url($rapport_annee, 'articles')); ?>"
           class="invoice-rapports-subtab <?php echo $rapport_vue === 'articles' ? 'is-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $rapport_vue === 'articles' ? 'true' : 'false'; ?>">Articles</a>
    </div>

    <?php if (!$bl_tables_ok): ?>
        <div class="invoice-rapports-empty">
            <p>Les rapports nécessitent la migration B2B (bons de livraison).</p>
        </div>
    <?php else: ?>

    <div class="invoice-rapports-table-wrap">
        <?php if ($rapport_vue === 'paye'): ?>
            <?php
            $total = $rapport_mensuel['total'] ?? ['nb_clients' => 0, 'nb_factures' => 0, 'montant' => 0];
            $mois_rows = $rapport_mensuel['mois'] ?? [];
            ?>
            <table class="invoice-rapports-table invoice-rapports-table--paye">
                <thead>
                    <tr>
                        <th scope="col" class="invoice-rapports-col-label"></th>
                        <th scope="col" class="invoice-rapports-col-num">Clients</th>
                        <th scope="col" class="invoice-rapports-col-num">Factures</th>
                        <th scope="col" class="invoice-rapports-col-num">Payé</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="invoice-rapports-row--total">
                        <td class="invoice-rapports-col-label">Année d'imposition <?php echo (int) $rapport_annee; ?></td>
                        <td class="invoice-rapports-col-num"><?php echo (int) ($total['nb_clients'] ?? 0); ?></td>
                        <td class="invoice-rapports-col-num"><?php echo (int) ($total['nb_factures'] ?? 0); ?></td>
                        <td class="invoice-rapports-col-num"><?php echo invoice_rapport_fmt_montant($total['montant'] ?? 0); ?></td>
                    </tr>
                    <?php foreach ($mois_rows as $row): ?>
                    <tr>
                        <td class="invoice-rapports-col-label"><?php echo htmlspecialchars($row['label'] ?? ''); ?></td>
                        <td class="invoice-rapports-col-num"><?php echo (int) ($row['nb_clients'] ?? 0); ?></td>
                        <td class="invoice-rapports-col-num"><?php echo (int) ($row['nb_factures'] ?? 0); ?></td>
                        <td class="invoice-rapports-col-num"><?php echo invoice_rapport_fmt_montant($row['montant'] ?? 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($rapport_vue === 'clients'): ?>
            <table class="invoice-rapports-table invoice-rapports-table--clients">
                <thead>
                    <tr>
                        <th scope="col" class="invoice-rapports-col-label"></th>
                        <th scope="col" class="invoice-rapports-col-num">Factures</th>
                        <th scope="col" class="invoice-rapports-col-num">Payé</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="invoice-rapports-row--total">
                        <td class="invoice-rapports-col-label">Année d'imposition <?php echo (int) $rapport_annee; ?></td>
                        <td class="invoice-rapports-col-num"><?php echo (int) $rapport_clients_totaux['nb_factures']; ?></td>
                        <td class="invoice-rapports-col-num"><?php echo invoice_rapport_fmt_montant($rapport_clients_totaux['montant']); ?></td>
                    </tr>
                    <?php if (empty($rapport_clients)): ?>
                    <tr>
                        <td colspan="3" class="invoice-rapports-empty-cell">Aucune donnée pour <?php echo (int) $rapport_annee; ?>.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rapport_clients as $rc): ?>
                        <tr>
                            <td class="invoice-rapports-col-label"><?php echo htmlspecialchars($rc['client_label']); ?></td>
                            <td class="invoice-rapports-col-num"><?php echo (int) $rc['nb_factures']; ?></td>
                            <td class="invoice-rapports-col-num"><?php echo invoice_rapport_fmt_montant($rc['montant']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <table class="invoice-rapports-table invoice-rapports-table--articles">
                <thead>
                    <tr>
                        <th scope="col" class="invoice-rapports-col-label"></th>
                        <th scope="col" class="invoice-rapports-col-num">Factures</th>
                        <th scope="col" class="invoice-rapports-col-num">Quantité</th>
                        <th scope="col" class="invoice-rapports-col-num">Payé</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="invoice-rapports-row--total">
                        <td class="invoice-rapports-col-label">Année d'imposition <?php echo (int) $rapport_annee; ?></td>
                        <td class="invoice-rapports-col-num"><?php echo (int) $rapport_articles_totaux['nb_factures']; ?></td>
                        <td class="invoice-rapports-col-num"><?php echo invoice_rapport_fmt_quantite($rapport_articles_totaux['quantite']); ?></td>
                        <td class="invoice-rapports-col-num"><?php echo invoice_rapport_fmt_montant($rapport_articles_totaux['montant']); ?></td>
                    </tr>
                    <?php if (empty($rapport_articles)): ?>
                    <tr>
                        <td colspan="4" class="invoice-rapports-empty-cell">Aucune donnée pour <?php echo (int) $rapport_annee; ?>.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rapport_articles as $ra): ?>
                        <tr>
                            <td class="invoice-rapports-col-label invoice-rapports-col-label--truncate" title="<?php echo htmlspecialchars($ra['article_label']); ?>"><?php echo htmlspecialchars($ra['article_label']); ?></td>
                            <td class="invoice-rapports-col-num"><?php echo (int) $ra['nb_factures']; ?></td>
                            <td class="invoice-rapports-col-num"><?php echo invoice_rapport_fmt_quantite($ra['quantite']); ?></td>
                            <td class="invoice-rapports-col-num"><?php echo invoice_rapport_fmt_montant($ra['montant']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="invoice-rapports-years" role="group" aria-label="Sélection de l'année">
        <?php foreach ($rapport_annees as $y): ?>
            <a href="<?php echo htmlspecialchars(invoice_rapport_url($y, $rapport_vue)); ?>"
               class="invoice-rapports-year-btn <?php echo (int) $y === (int) $rapport_annee ? 'is-active' : ''; ?>">
                <?php echo (int) $y; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>
