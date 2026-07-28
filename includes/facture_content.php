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
 * $commande['remise_globale_pct'] (float, optionnel): pourcentage de réduction globale
 */
$client_nom = isset($client_nom) ? (string) $client_nom : '';
$client_telephone = isset($client_telephone) ? (string) $client_telephone : '';
$adresse_livraison = $adresse_livraison ?? '';
$produits = isset($produits) && is_array($produits) ? $produits : [];
$commande = isset($commande) && is_array($commande) ? $commande : [];
$facture = isset($facture) && is_array($facture) ? $facture : [];
$date_facture_aff = isset($date_facture_aff) ? (string) $date_facture_aff : '';
$is_public = !empty($is_public);
require_once __DIR__ . '/site_url.php';
require_once __DIR__ . '/fiscal_tva.php';
$facture_est_payee = isset($facture_est_payee) ? (bool) $facture_est_payee : (!empty($facture['payee']));
$facture_afficher_marquer_payee = !empty($facture_afficher_marquer_payee);
$facture_afficher_marquer_non_payee = !empty($facture_afficher_marquer_non_payee);
$facture_csrf_token = isset($facture_csrf_token) ? (string) $facture_csrf_token : '';
$facture_marquer_payee_confirm = isset($facture_marquer_payee_confirm) && (string) $facture_marquer_payee_confirm !== ''
    ? (string) $facture_marquer_payee_confirm
    : 'Confirmer le paiement de cette facture ?';
$facture_marquer_non_payee_confirm = isset($facture_marquer_non_payee_confirm) && (string) $facture_marquer_non_payee_confirm !== ''
    ? (string) $facture_marquer_non_payee_confirm
    : 'Marquer cette facture comme non payée ?';
$facture_page_flash_success = isset($facture_page_flash_success) ? (string) $facture_page_flash_success : '';
$facture_page_flash_error = isset($facture_page_flash_error) ? (string) $facture_page_flash_error : '';
$facture_document_type_label = isset($facture_document_type_label) && (string) $facture_document_type_label !== ''
    ? (string) $facture_document_type_label
    : 'FACTURE';
if (!isset($facture_numero_affichage) || (string) $facture_numero_affichage === '') {
    $facture_numero_affichage = (string) ($facture['numero_facture'] ?? '');
} else {
    $facture_numero_affichage = (string) $facture_numero_affichage;
}
$facture_recap_label_total = isset($facture_recap_label_total) && (string) $facture_recap_label_total !== ''
    ? (string) $facture_recap_label_total
    : 'TOTAL';
$facture_og_title = 'Facture ' . htmlspecialchars($facture['numero_facture'] ?? '') . ' - Sugar Paper';
$facture_og_desc = 'Facture Sugar Paper - ' . ($entreprise_nom ?? 'Sugar Paper') . ' - Montant : ' . number_format($facture['montant_total'] ?? 0, 0, ',', ' ') . ' CFA';
$facture_og_image = get_site_base_url() . '/image/sugar_paper.jpg';

if (!isset($facture_share_url)) {
    $facture_share_url = isset($facture_url) ? (string) $facture_url : '';
} else {
    $facture_share_url = (string) $facture_share_url;
}
if (!isset($facture_share_title)) {
    $facture_share_title = 'Facture ' . (string) ($facture_numero_affichage ?? $facture['numero_facture'] ?? '');
} else {
    $facture_share_title = (string) $facture_share_title;
}

/* Message de partage enrichi (WhatsApp / partage natif / desktop) */
$facture_share_numero = trim((string) ($facture_numero_affichage ?? $facture['numero_facture'] ?? ''));
$facture_share_montant = number_format((float) ($facture['montant_total'] ?? 0), 0, ',', ' ') . ' CFA';
$facture_share_client = trim((string) $client_nom);
$facture_share_tel = trim((string) $client_telephone);
$facture_share_ref = trim((string) ($commande['numero_commande'] ?? ''));
if ($facture_share_ref === '' && isset($devis) && is_array($devis)) {
    $facture_share_ref = trim((string) ($devis['numero_devis'] ?? ''));
}
if ($facture_share_ref === '' && isset($bl) && is_array($bl)) {
    $bl_ref = trim((string) ($bl['numero_bl'] ?? ''));
    if ($bl_ref !== '' && $bl_ref !== $facture_share_numero) {
        $facture_share_ref = $bl_ref;
    }
}

$facture_share_lines = [];
$facture_share_lines[] = 'Bonjour' . ($facture_share_client !== '' ? ' ' . $facture_share_client : '') . ',';
$facture_share_lines[] = '';
$facture_share_lines[] = 'Voici votre facture' . ($facture_share_numero !== '' ? ' n°' . $facture_share_numero : '') . '.';
if ($facture_share_ref !== '') {
    $facture_share_lines[] = 'Référence : #' . $facture_share_ref;
}
if ($date_facture_aff !== '') {
    $facture_share_lines[] = 'Date : ' . $date_facture_aff;
}
if ($facture_share_tel !== '') {
    $facture_share_lines[] = 'Téléphone : ' . $facture_share_tel;
}
$facture_share_lines[] = '';
if (!empty($produits)) {
    $facture_share_lines[] = 'Détail des articles :';
    foreach ($produits as $p_share) {
        $nom_share = trim((string) ($p_share['produit_nom'] ?? $p_share['nom'] ?? $p_share['nom_produit'] ?? 'Article'));
        $qte_share = (int) ($p_share['quantite'] ?? 0);
        $total_share = (float) ($p_share['prix_total'] ?? 0);
        if ($total_share <= 0) {
            $total_share = (float) ($p_share['prix_unitaire'] ?? 0) * $qte_share;
        }
        $ligne_share = '- ' . $nom_share;
        if ($qte_share > 0) {
            $ligne_share .= ' × ' . $qte_share;
        }
        if ($total_share > 0) {
            $ligne_share .= ' — ' . number_format($total_share, 0, ',', ' ') . ' CFA';
        }
        $facture_share_lines[] = $ligne_share;
    }
    $facture_share_lines[] = '';
}
$facture_share_lines[] = 'Montant total : ' . $facture_share_montant;
$facture_share_lines[] = 'Statut : ' . ($facture_est_payee ? 'Payée' : 'Solde dû');
$facture_share_lines[] = '';
$facture_share_lines[] = 'Consultez votre facture en ligne :';
$facture_share_lines[] = '';
$facture_share_lines[] = 'Cordialement,';
$facture_share_lines[] = 'Sugar Paper';
$facture_share_message = implode("\n", $facture_share_lines);

$facture_share_hint = isset($facture_share_hint) && (string) $facture_share_hint !== ''
    ? (string) $facture_share_hint
    : 'Le client pourra consulter la facture en ligne sans compte administrateur.';
$facture_can_share = empty($is_public) && $facture_share_url !== '';

if ($facture_can_share && !function_exists('asset_version_query')) {
    require_once __DIR__ . '/asset_version.php';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $facture_og_title; ?></title>
    <meta property="og:title" content="<?php echo htmlspecialchars($facture_og_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($facture_og_desc); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($facture_og_image); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Sugar Paper">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if ($facture_can_share): ?>
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: #444;
            background: #f5f5f5;
            margin: 0;
            padding: 8px 4px;
        }

        .facture-viewport {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            overflow: hidden;
        }

        .facture-scale-wrap {
            width: 210mm;
            max-width: 210mm;
            transform-origin: top center;
            flex-shrink: 0;
        }

        .facture-container {
            width: 210mm;
            min-height: 297mm;
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .facture-sheet-body {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
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
            margin-top: auto;
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

        .facture-summary .facture-remise-row {
            color: #c26638;
            font-weight: 600;
        }

        .facture-payee-mention {
            font-size: 15px;
            font-weight: 700;
            color: #1b5e20;
            margin-top: 6px;
            letter-spacing: 0.02em;
        }

        .facture-payee-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(27, 94, 32, 0.12);
            color: #1b5e20;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        .facture-flash-bar {
            width: 100%;
            max-width: 210mm;
            margin: 0 0 10px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            box-sizing: border-box;
        }

        .facture-flash-bar--success {
            background: #e8f5e9;
            border: 1px solid #1b5e20;
            color: #1b5e20;
        }

        .facture-flash-bar--error {
            background: #ffebee;
            border: 1px solid #c62828;
            color: #6a1b1b;
        }

        .facture-actions form.facture-form-marquer-paye {
            display: inline-flex;
            margin: 0;
        }

        .facture-actions .btn-marquer-paye {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            background: #1b5e20;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .facture-actions .btn-marquer-paye:hover {
            background: #145214;
        }

        .facture-actions .btn-marquer-non-paye {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid #c26638;
            background: #fff;
            color: #c26638;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .facture-actions .btn-marquer-non-paye:hover {
            background: rgba(194, 102, 56, 0.08);
        }

        .facture-summary .facture-solde-paye-row {
            background: rgba(27, 94, 32, 0.12);
            color: #1b5e20;
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
            flex-shrink: 0;
            background: linear-gradient(135deg, rgba(229, 72, 138, 0.3) 0%, rgba(244, 211, 94, 0.2) 50%, rgba(229, 72, 138, 0.25) 100%);
            background-image: repeating-linear-gradient(-45deg, transparent, transparent 10px, rgba(229, 72, 138, 0.2) 10px, rgba(229, 72, 138, 0.2) 20px);
        }

        .facture-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            width: 100%;
            max-width: 210mm;
            margin: 0 0 12px;
            padding: 8px 0;
            box-sizing: border-box;
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

        .facture-actions .btn-facture-share {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 999px;
            font-weight: 700;
            font-size: 14px;
            white-space: nowrap;
            cursor: pointer;
            color: #fff;
            background: #25D366;
            box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35);
            transition: background 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
        }

        .facture-actions .btn-facture-share i {
            font-size: 1.15rem;
            line-height: 1;
        }

        .facture-actions .btn-facture-share:hover {
            background: #1da851;
            box-shadow: 0 6px 18px rgba(37, 211, 102, 0.42);
            transform: translateY(-1px);
        }

        .facture-actions .btn-facture-share:active {
            transform: translateY(0);
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

            .facture-viewport {
                overflow: visible !important;
                height: auto !important;
            }

            .facture-scale-wrap {
                transform: none !important;
                width: auto !important;
            }

            .facture-container {
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
                box-shadow: none !important;
                margin: 0 !important;
            }

            .facture-sheet-body {
                min-height: auto !important;
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
                margin: 0;
            }
        }

        @media screen and (min-width: 993px) {
            body {
                padding: 20px 12px;
            }
        }
    </style>
</head>

<body>
    <div class="facture-viewport" id="facture-viewport">
    <div class="facture-scale-wrap" id="facture-scale-wrap">
    <?php if ($facture_page_flash_success !== ''): ?>
        <div class="facture-flash-bar facture-flash-bar--success" role="status"><?php echo htmlspecialchars($facture_page_flash_success); ?></div>
    <?php endif; ?>
    <?php if ($facture_page_flash_error !== ''): ?>
        <div class="facture-flash-bar facture-flash-bar--error" role="alert"><?php echo htmlspecialchars($facture_page_flash_error); ?></div>
    <?php endif; ?>
    <?php if (empty($is_public)): ?>
        <?php
        $back_url = $facture_back_url ?? ('details.php?id=' . (int) ($facture['commande_id'] ?? $facture['devis_id'] ?? 0));
        $back_label = $facture_back_label ?? 'Retour à la commande';
        ?>
        <div class="facture-actions facture-actions-top">
            <a href="<?php echo htmlspecialchars($back_url); ?>"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($back_label); ?></a>
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
            <?php if ($facture_afficher_marquer_payee): ?>
                <form method="post" action="" class="facture-form-marquer-paye"
                    onsubmit="return confirm(<?php echo json_encode($facture_marquer_payee_confirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($facture_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" name="marquer_facture_payee" value="1" class="btn-marquer-paye">
                        <i class="fas fa-check-circle" aria-hidden="true"></i> Marquer comme payée
                    </button>
                </form>
            <?php elseif ($facture_afficher_marquer_non_payee): ?>
                <form method="post" action="" class="facture-form-marquer-paye"
                    onsubmit="return confirm(<?php echo json_encode($facture_marquer_non_payee_confirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($facture_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" name="marquer_facture_non_payee" value="1" class="btn-marquer-non-paye">
                        <i class="fas fa-undo" aria-hidden="true"></i> Marquer comme non payée
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($facture_can_share): ?>
                <button type="button"
                    class="btn-facture-share js-platform-share"
                    aria-haspopup="dialog"
                    aria-controls="platformShareModal"
                    data-share-prefer="wa"
                    data-share-modal-title="Envoyer la facture"
                    data-share-title="<?php echo htmlspecialchars($facture_share_title, ENT_QUOTES, 'UTF-8'); ?>"
                    data-share-url="<?php echo htmlspecialchars($facture_share_url, ENT_QUOTES, 'UTF-8'); ?>"
                    data-share-text="<?php echo htmlspecialchars($facture_share_message, ENT_QUOTES, 'UTF-8'); ?>"
                    data-share-hint="<?php echo htmlspecialchars($facture_share_hint, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fab fa-whatsapp" aria-hidden="true"></i> Envoyer sur WhatsApp
                </button>
            <?php elseif (!empty($whatsapp_url)): ?>
                <a href="<?php echo htmlspecialchars($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer"
                    class="btn-whatsapp">
                    <i class="fab fa-whatsapp"></i> Envoyer sur WhatsApp
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="facture-actions facture-actions-top">
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
        </div>
    <?php endif; ?>

    <div class="facture-container">
        <div class="facture-sheet-body">
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
                <div class="label"><?php echo htmlspecialchars($facture_document_type_label ?? 'FACTURE'); ?></div>
                <div class="value"><?php echo htmlspecialchars($facture_numero_affichage ?? $facture['numero_facture']); ?></div>
                <div class="label" style="margin-top:12px;">DATE</div>
                <div class="value"><?php echo htmlspecialchars($date_facture_aff); ?></div>
                <div class="label" style="margin-top:12px;"><?php echo $facture_est_payee ? 'MONTANT' : 'SOLDE DÛ'; ?></div>
                <?php if ($facture_est_payee): ?>
                <div class="facture-payee-mention">Payée</div>
                <span class="facture-payee-badge">PAYÉE</span>
                <?php else: ?>
                <div class="solde">XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</div>
                <?php endif; ?>
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
                    $ligne_total = (float) ($p['prix_total'] ?? 0);
                    if ($ligne_total <= 0) {
                        $pu = (float) ($p['prix_unitaire'] ?? 0);
                        $qte = (int) ($p['quantite'] ?? 0);
                        $ligne_total = $pu * $qte;
                    }
                    $sous_total_produits += $ligne_total;
                }
                $frais_livraison = (float) ($commande['frais_livraison'] ?? 0);
                $remise_globale_pct = (float) ($commande['remise_globale_pct'] ?? $facture_remise_globale_pct ?? 0);
                $brut_avant_remise = $sous_total_produits + $frais_livraison;
                $remise_montant = $remise_globale_pct > 0 ? fiscal_montant_remise($brut_avant_remise, $remise_globale_pct) : 0;
                $afficher_detail = ($frais_livraison > 0 || $remise_globale_pct > 0);
                $tva_incluse_aff = !empty($facture_tva_incluse);
                $montant_ht_aff = isset($facture_fiscal_ht) ? (float) $facture_fiscal_ht : null;
                $montant_tva_aff = isset($facture_fiscal_tva) ? (float) $facture_fiscal_tva : null;
                $taux_tva_aff = isset($facture_fiscal_taux) ? (float) $facture_fiscal_taux : null;
                $label_total = $facture_recap_label_total ?? ($tva_incluse_aff ? 'TOTAL TTC' : 'TOTAL');
                ?>
                <?php if ($afficher_detail): ?>
                <div class="row">
                    <span>SOUS-TOTAL PRODUITS</span>
                    <span><?php echo number_format($sous_total_produits, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php if ($frais_livraison > 0): ?>
                <div class="row">
                    <span>FRAIS DE LIVRAISON</span>
                    <span><?php echo number_format($frais_livraison, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <?php if ($remise_globale_pct > 0): ?>
                <div class="row facture-remise-row">
                    <span>RÉDUCTION (<?php echo number_format($remise_globale_pct, 2, ',', ' '); ?> %)</span>
                    <span>-<?php echo number_format($remise_montant, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($tva_incluse_aff && $montant_ht_aff !== null && $montant_tva_aff !== null): ?>
                <div class="row">
                    <span>TOTAL HT</span>
                    <span><?php echo number_format($montant_ht_aff, 2, ',', ' '); ?> CFA</span>
                </div>
                <div class="row">
                    <span>TVA<?php echo $taux_tva_aff ? ' (' . rtrim(rtrim(number_format($taux_tva_aff, 2, ',', ' '), '0'), ',') . ' %)' : ''; ?></span>
                    <span><?php echo number_format($montant_tva_aff, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <div class="row total">
                    <span><?php echo htmlspecialchars($label_total); ?></span>
                    <span><?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php if ($facture_est_payee): ?>
                <div class="row solde-row facture-solde-paye-row">
                    <span>Payée</span>
                    <span>XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php else: ?>
                <div class="row solde-row">
                    <span>SOLDE DÛ</span>
                    <span>XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        </div>
        <div class="facture-banner-bottom"></div>
    </div>
    </div>
    </div>
    <script>
    (function() {
        var viewport = document.getElementById('facture-viewport');
        var wrap = document.getElementById('facture-scale-wrap');
        if (!viewport || !wrap) return;

        var MARGIN_X = 8;

        function fitFactureScale() {
            if (window.matchMedia('print').matches) {
                wrap.style.transform = 'none';
                viewport.style.height = 'auto';
                return;
            }
            wrap.style.transform = 'none';
            viewport.style.height = 'auto';
            var naturalW = wrap.offsetWidth;
            var naturalH = wrap.offsetHeight;
            if (naturalW <= 0 || naturalH <= 0) return;
            var available = window.innerWidth - MARGIN_X;
            var scale = Math.min(1, available / naturalW);
            wrap.style.transform = scale < 1 ? 'scale(' + scale + ')' : 'none';
            viewport.style.height = Math.ceil(naturalH * scale) + 'px';
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(fitFactureScale);
        }
        window.addEventListener('load', fitFactureScale);
        window.addEventListener('resize', fitFactureScale);
        window.addEventListener('orientationchange', function() {
            setTimeout(fitFactureScale, 100);
        });
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(fitFactureScale).observe(wrap);
        }
    })();
    </script>
    <?php if ($facture_can_share): ?>
    <?php include __DIR__ . '/partials/platform_share_modal.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <?php endif; ?>
</body>

</html>