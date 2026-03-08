<?php
/**
 * Contenu commun de la facture (admin et public)
 * Variables attendues: $facture, $commande, $produits, $client_nom, $client_telephone, $adresse_livraison,
 *   $date_facture_aff, $entreprise_nom, $entreprise_rc, $entreprise_ninea, $entreprise_adresse,
 *   $entreprise_tel1, $entreprise_tel2, $entreprise_site, $entreprise_email
 * $is_public (bool): true = page publique (pas d'actions admin), false = page admin
 * $whatsapp_url (string, optionnel): URL WhatsApp pour le bouton
 * $facture_back_url (string, optionnel): URL du lien "Retour" (ex: details.php?id=5)
 * $facture_back_label (string, optionnel): Libellé du lien Retour (défaut: "Retour à la commande")
 */
$adresse_livraison = $adresse_livraison ?? '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?php echo htmlspecialchars($facture['numero_facture']); ?> - Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #444;
            background: #f5f5f5;
            padding: 20px;
        }

        .facture-container {
            max-width: 918px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .facture-banner-top {
            height: 60px;
            background: linear-gradient(135deg, rgba(229, 72, 138, 0.25) 0%, rgba(244, 211, 94, 0.2) 50%, rgba(229, 72, 138, 0.2) 100%);
            background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(229, 72, 138, 0.15) 10px, rgba(229, 72, 138, 0.15) 20px);
        }

        .facture-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-direction: row;
            padding: 30px 45px 25px;
            border-bottom: 1px solid #eee;
        }

        .facture-entreprise {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }

        .facture-logo {
            width: 100px;
            height: 100px;
            border: 2px solid #e5488a;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .facture-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .facture-entreprise-info h1 {
            font-size: 28px;
            font-weight: 700;
            color: #000;
            margin-bottom: 8px;
        }

        .facture-entreprise-info p {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .facture-entreprise-info a {
            color: #3b82f6;
            text-decoration: underline;
        }

        .facture-entreprise-info .tel {
            margin-top: 6px;
        }

        .facture-meta {
            text-align: right;
        }

        .facture-meta .label {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .facture-meta .value {
            font-size: 18px;
            font-weight: 700;
            color: #000;
        }

        .facture-meta .solde {
            font-size: 16px;
            color: #e5488a;
            margin-top: 8px;
        }

        .facture-billing {
            padding: 25px 45px;
            border-bottom: 1px solid #eee;
        }

        .facture-billing .label {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .facture-billing .client-name {
            font-size: 18px;
            font-weight: 700;
            color: #000;
            margin-bottom: 4px;
        }

        .facture-billing .client-tel {
            font-size: 14px;
            color: #444;
        }

        .facture-billing .adresse-livraison {
            font-size: 13px;
            color: #555;
            margin-top: 8px;
            line-height: 1.4;
        }

        .facture-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .facture-table {
            width: 100%;
            border-collapse: collapse;
        }

        .facture-table th {
            background: #c91f6e;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 14px 20px;
            text-align: left;
        }

        .facture-table th:last-child,
        .facture-table td:last-child {
            text-align: right;
        }

        .facture-table th:nth-child(3),
        .facture-table td:nth-child(3) {
            text-align: center;
        }

        .facture-table td {
            padding: 14px 20px;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
        }

        .facture-table tr:nth-child(even) td {
            background: #fef5f9;
        }

        .facture-table tr:nth-child(odd) td {
            background: #fff;
        }

        .facture-footer-section {
            display: flex;
            justify-content: space-between;
            padding: 25px 45px 30px;
            gap: 40px;
        }

        .facture-payment h3 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #000;
        }

        .facture-payment p {
            font-size: 13px;
            color: #666;
        }

        .facture-summary {
            min-width: 280px;
        }

        .facture-summary .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .facture-summary .total {
            font-weight: 700;
            font-size: 16px;
            padding-top: 12px;
            border-top: 2px solid #e5488a;
            margin-top: 8px;
        }

        .facture-summary .solde-row {
            background: rgba(229, 72, 138, 0.12);
            padding: 12px 16px;
            margin-top: 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 16px;
        }

        .facture-banner-bottom {
            height: 40px;
            background: linear-gradient(135deg, rgba(229, 72, 138, 0.3) 0%, rgba(244, 211, 94, 0.2) 50%, rgba(229, 72, 138, 0.25) 100%);
            background-image: repeating-linear-gradient(-45deg, transparent, transparent 10px, rgba(229, 72, 138, 0.2) 10px, rgba(229, 72, 138, 0.2) 20px);
        }

        .facture-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 12px 0;
        }

        .facture-actions.facture-actions-top {
            margin-bottom: 20px;
            margin-top: 0;
        }

        .facture-actions.facture-actions-bottom {
            margin-top: 20px;
            margin-bottom: 0;
        }

        .facture-actions a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #918a44;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }

        .facture-actions a:hover {
            background: #6b2f20;
        }

        .facture-actions a.btn-whatsapp {
            background: #25D366;
        }

        .facture-actions a.btn-whatsapp:hover {
            background: #1da851;
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                padding: 0 !important;
            }

            .facture-actions {
                display: none !important;
            }

            .facture-container {
                max-width: 100% !important;
                box-shadow: none !important;
                margin: 0 !important;
            }

            .facture-banner-top,
            .facture-banner-bottom {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-table th {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-table tr:nth-child(even) td {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-summary .solde-row {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-logo img {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Forcer le layout desktop (identique à l'écran) */
            .facture-header {
                flex-direction: row !important;
                padding: 30px 45px 25px !important;
            }

            .facture-entreprise {
                flex-direction: row !important;
            }

            .facture-footer-section {
                flex-direction: row !important;
                padding: 25px 45px 30px !important;
            }

            .facture-billing {
                padding: 25px 45px !important;
            }

            @page {
                size: A4;
                margin: 15mm;
            }
        }

        @media (max-width: 992px) {
            .facture-header {
                padding: 24px 24px;
            }

            .facture-billing {
                padding: 20px 24px;
            }

            .facture-footer-section {
                padding: 24px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .facture-container {
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            }

            .facture-banner-top {
                height: 40px;
            }

            .facture-header {

                gap: 6px;
                padding: 20px 8px;
            }

            .facture-entreprise {
                flex-direction: column;
                gap: 12px;
            }

            .facture-logo {
                width: 70px;
                height: 70px;
            }

            .facture-entreprise-info h1 {
                font-size: 22px;
            }

            .facture-entreprise-info p {
                font-size: 11px;
            }

            .facture-meta {
                text-align: left;
            }

            .facture-billing {
                padding: 16px;
            }

            .facture-billing .client-name {
                font-size: 16px;
            }

            .facture-table-wrapper {
                margin: 0 -16px;
            }

            .facture-table {
                font-size: 13px;
                min-width: 400px;
            }

            .facture-table th,
            .facture-table td {
                padding: 10px 12px;
            }

            .facture-footer-section {
                flex-direction: column;
                padding: 20px 16px;
                gap: 20px;
            }

            .facture-summary {
                min-width: auto;
            }

            .facture-banner-bottom {
                height: 30px;
            }

            .facture-actions {
                padding: 8px 0;
                gap: 8px;
            }

            .facture-actions a {
                padding: 10px 16px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .facture-header {
                padding: 16px 8px;
            }

            .facture-billing {
                padding: 12px;
            }

            .facture-footer-section {
                padding: 16px 8px;
            }

            .facture-actions {
                flex-direction: column;
            }

            .facture-actions a {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php if (empty($is_public)): ?>
        <?php
        $back_url = $facture_back_url ?? ('details.php?id=' . (int) ($facture['commande_id'] ?? $facture['devis_id'] ?? 0));
        $back_label = $facture_back_label ?? 'Retour à la commande';
        ?>
        <div class="facture-actions facture-actions-top">
            <a href="<?php echo htmlspecialchars($back_url); ?>"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($back_label); ?></a>
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
            <?php if (!empty($whatsapp_url)): ?>
                <a href="<?php echo htmlspecialchars($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer"
                    class="btn-whatsapp">
                    <i class="fab fa-whatsapp"></i> Envoyer la facture sur WhatsApp
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="facture-actions facture-actions-top">
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
        </div>
    <?php endif; ?>

    <div class="facture-container">
        <div class="facture-banner-top"></div>

        <div class="facture-header">
            <div class="facture-entreprise">
                <div class="facture-logo">
                    <img src="/image/sugar_paper.jpg" alt="Sugar Paper"
                        onerror="this.style.background='#fef5f9';this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext x=%2250%22 y=%2255%22 text-anchor=%22middle%22 font-size=%2240%22%3E🍰%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="facture-entreprise-info">
                    <h1><?php echo htmlspecialchars($entreprise_nom); ?></h1>
                    <p>R.C : <?php echo htmlspecialchars($entreprise_rc); ?></p>
                    <p>N.I.N.E.A : <?php echo htmlspecialchars($entreprise_ninea); ?></p>
                    <p><?php echo htmlspecialchars($entreprise_adresse); ?></p>
                    <div class="tel">
                        <i class="fas fa-phone"
                            style="font-size:11px; margin-right:4px;"></i><?php echo htmlspecialchars($entreprise_tel1); ?><br>
                        <i class="fas fa-phone"
                            style="font-size:11px; margin-right:4px;"></i><?php echo htmlspecialchars($entreprise_tel2); ?>
                    </div>
                    <p style="margin-top:6px;">
                        <i class="fas fa-globe" style="font-size:11px; margin-right:4px;"></i>
                        <a href="<?php echo htmlspecialchars($entreprise_site); ?>"
                            target="_blank"><?php echo htmlspecialchars($entreprise_site); ?></a>
                    </p>
                    <p><i class="fas fa-envelope"
                            style="font-size:11px; margin-right:4px;"></i><?php echo htmlspecialchars($entreprise_email); ?>
                    </p>
                </div>
            </div>
            <div class="facture-meta">
                <div class="label">FACTURE</div>
                <div class="value"><?php echo htmlspecialchars($facture['numero_facture']); ?></div>
                <div class="label" style="margin-top:12px;">DATE</div>
                <div class="value"><?php echo htmlspecialchars($date_facture_aff); ?></div>
                <div class="label" style="margin-top:12px;">SOLDE DÛ</div>
                <div class="solde">XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</div>
            </div>
        </div>

        <div class="facture-billing">
            <div class="label">ADRESSE DE FACTURATION</div>
            <div class="client-name"><?php echo htmlspecialchars($client_nom); ?></div>
            <div class="client-tel"><i class="fas fa-phone"
                    style="font-size:11px; margin-right:4px;"></i><?php echo htmlspecialchars($client_telephone); ?>
            </div>
            <?php if (!empty($adresse_livraison)): ?>
                <div class="adresse-livraison"><i class="fas fa-map-marker-alt"
                        style="font-size:11px; margin-right:4px;"></i><?php echo nl2br(htmlspecialchars($adresse_livraison)); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="facture-table-wrapper">
            <table class="facture-table">
                <thead>
                    <tr>
                        <th>ARTICLE</th>
                        <th>PRIX</th>
                        <th>QTÉ</th>
                        <th>MONTANT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['produit_nom'] ?? $p['nom'] ?? ''); ?></td>
                            <td><?php echo number_format($p['prix_unitaire'], 2, ',', ' '); ?> CFA</td>
                            <td><?php echo (int) $p['quantite']; ?></td>
                            <td><?php echo number_format($p['prix_total'], 2, ',', ' '); ?> CFA</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="facture-footer-section">
            <div class="facture-payment">
                <h3>Information De Paiement</h3>
                <p>AUTRE</p>
                <p><?php echo nl2br(htmlspecialchars($commande['notes'] ?? '—')); ?></p>
            </div>
            <div class="facture-summary">
                <?php
                $sous_total_produits = 0;
                foreach ($produits as $p) {
                    $sous_total_produits += (float) ($p['prix_total'] ?? 0);
                }
                $frais_livraison = (float) ($commande['frais_livraison'] ?? 0);
                ?>
                <?php if ($frais_livraison > 0): ?>
                <div class="row">
                    <span>SOUS-TOTAL PRODUITS</span>
                    <span><?php echo number_format($sous_total_produits, 2, ',', ' '); ?> CFA</span>
                </div>
                <div class="row">
                    <span>FRAIS DE LIVRAISON</span>
                    <span><?php echo number_format($frais_livraison, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <div class="row">
                    <span>TOTAL</span>
                    <span><?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <div class="row solde-row">
                    <span>SOLDE DÛ</span>
                    <span>XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
            </div>
        </div>

        <div class="facture-banner-bottom"></div>
    </div>
</body>

</html>