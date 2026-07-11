<?php
/**
 * Contenu commun de la facture (admin et public)
 * Variables attendues: $facture, $commande, $produits, $client_nom, $client_telephone, $adresse_livraison,
 *   $date_facture_aff, $entreprise_nom, $entreprise_rc, $entreprise_ninea, $entreprise_adresse,
 *   $entreprise_tel1, $entreprise_tel2, $entreprise_site, $entreprise_email
 * $is_public (bool): true = page publique (pas d'actions admin), false = page admin
 * $facture_share_url, $facture_share_message, $facture_share_title (optionnel): partage modal unifiée
 * $whatsapp_url (string, optionnel, déprécié): ancien lien WhatsApp direct
 * $facture_back_url (string, optionnel): URL du lien "Retour" (ex: details.php?id=5)
 * $facture_back_label (string, optionnel): Libellé du lien Retour (défaut: "Retour à la commande")
 * $facture_bl_statut_libelle (string, optionnel): statut du BL (ex. facture BL)
 * $facture_bl_statut_code (string, optionnel): brouillon | valide (ou ancien paye) — couleur du libellé
 * $facture_show_client_zone (bool, optionnel): zone réservée au client en bas de page (signature)
 * $facture_logo_url (string, optionnel): logo en-tête
 * $facture_couleur_principale, $facture_couleur_accent (string, optionnel): thème couleurs
 */
$adresse_livraison = $adresse_livraison ?? '';
$facture_logo_url = $facture_logo_url ?? '/image/logo_market.png';
$facture_couleur_principale = $facture_couleur_principale ?? '#3564a6';
$facture_couleur_accent = $facture_couleur_accent ?? '#ff6b35';
$facture_branding_vendeur = !empty($facture_branding_vendeur);
$facture_bl_statut_libelle = isset($facture_bl_statut_libelle) ? (string) $facture_bl_statut_libelle : '';
$facture_bl_statut_code = isset($facture_bl_statut_code) ? (string) $facture_bl_statut_code : '';
$facture_show_client_zone = !empty($facture_show_client_zone);
$facture_bl_meta_color = '#2d5690';
if ($facture_bl_statut_libelle !== '') {
    if (in_array($facture_bl_statut_code, ['valide', 'paye'], true)) {
        $facture_bl_meta_color = '#1b5e20';
    } elseif ($facture_bl_statut_code === 'brouillon') {
        $facture_bl_meta_color = '#856404';
    }
}
require_once __DIR__ . '/site_url.php';
if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/asset_version.php';
}
$facture_share_url = $facture_share_url ?? '';
$facture_share_message = $facture_share_message ?? '';
$facture_share_title = $facture_share_title ?? ('Facture ' . ($facture['numero_facture'] ?? ''));
$facture_can_share = $facture_share_url !== '' && $facture_share_message !== '';
$facture_og_title = 'Facture ' . htmlspecialchars($facture['numero_facture'] ?? '') . ' - COLObanes';
$facture_og_desc = 'Facture COLObanes - ' . ($entreprise_nom ?? 'COLObanes') . ' - Montant : ' . number_format($facture['montant_total'] ?? 0, 0, ',', ' ') . ' CFA';
$facture_og_image = get_site_base_url() . $facture_logo_url;
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
    <meta property="og:site_name" content="COLObanes">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <?php if ($facture_can_share): ?>
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <?php endif; ?>
    <style>
        :root {
            --facture-primary: <?php echo htmlspecialchars($facture_couleur_principale, ENT_QUOTES, 'UTF-8'); ?>;
            --facture-accent: <?php echo htmlspecialchars($facture_couleur_accent, ENT_QUOTES, 'UTF-8'); ?>;
            --facture-primary-soft: color-mix(in srgb, var(--facture-primary) 25%, white);
            --facture-primary-muted: color-mix(in srgb, var(--facture-primary) 12%, white);
            --facture-primary-row: color-mix(in srgb, var(--facture-primary) 6%, white);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, Helvetica, sans-serif;
            color: #444;
            background: #f5f5f5;
            padding: 16px;
            font-size: 9pt;
            line-height: 1.45;
        }

        .facture-scale-viewport {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            display: flex;
            justify-content: center;
            padding: 8px 0 24px;
            -webkit-overflow-scrolling: touch;
        }

        .facture-scale-inner {
            transform-origin: top center;
            flex-shrink: 0;
        }

        .facture-container {
            width: 148mm;
            min-height: 210mm;
            max-width: none;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .facture-body {
            width: 100%;
            max-width: 550px;
            margin: 0 auto;
            padding: 0 10px;
            box-sizing: border-box;
        }

        .facture-banner-top {
            height: 28px;
            background: linear-gradient(135deg, var(--facture-primary-soft) 0%, color-mix(in srgb, var(--facture-primary) 20%, white) 50%, var(--facture-primary-muted) 100%);
            background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, color-mix(in srgb, var(--facture-primary) 15%, transparent) 10px, color-mix(in srgb, var(--facture-primary) 15%, transparent) 20px);
        }

        .facture-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-direction: row;
            gap: 10px;
            padding: 10px 4px 8px;
            border-bottom: 1px solid #eee;
        }

        .facture-entreprise {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            min-width: 0;
            flex: 1 1 auto;
        }

        .facture-logo {
            width: 52px;
            height: 52px;
            border: 2px solid var(--facture-primary);
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
            font-size: 11pt;
            font-weight: 700;
            color: #000;
            margin-bottom: 3px;
            line-height: 1.2;
        }

        .facture-entreprise-info p {
            font-size: 7.5pt;
            color: #666;
            margin-bottom: 2px;
            line-height: 1.3;
            word-break: break-word;
        }

        .facture-entreprise-info a {
            color: #3b82f6;
            text-decoration: underline;
        }

        .facture-entreprise-info .tel {
            margin-top: 3px;
            font-size: 7.5pt;
        }

        .facture-meta {
            text-align: right;
            flex-shrink: 0;
            max-width: 42%;
        }

        .facture-meta .label {
            font-size: 10pt;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .facture-meta .value {
            font-size: 11pt;
            font-weight: 700;
            color: #000;
        }

        .facture-meta-kv {
            margin-top: 4px;
        }

        .facture-meta-kv:first-of-type {
            margin-top: 0;
        }

        .facture-meta-kv .label {
            font-size: 7.5pt;
            margin-bottom: 1px;
        }

        .facture-meta-kv .value {
            font-size: 8.5pt;
            line-height: 1.25;
        }

        .facture-meta-kv .value.facture-meta-bl-statut {
            font-weight: 700;
        }

        .facture-meta-kv--total .label {
            font-size: 9px;
            margin-bottom: 2px;
        }

        .facture-meta .solde {
            font-size: 16px;
            color: var(--facture-primary);
            margin-top: 8px;
        }

        .facture-billing {
            padding: 8px 4px;
            border-bottom: 1px solid #eee;
        }

        .facture-billing .label {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .facture-billing .client-name {
            font-size: 11pt;
            font-weight: 700;
            color: #000;
            margin-bottom: 2px;
        }

        .facture-billing .client-tel {
            font-size: 9pt;
            color: #444;
            margin-top: 0;
        }

        .facture-billing .adresse-livraison {
            font-size: 13px;
            color: #555;
            margin-top: 4px;
            line-height: 1.35;
        }

        .facture-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0 4px;
        }

        .facture-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .facture-table th {
            background: var(--facture-primary);
            color: #fff;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 5px;
            text-align: left;
            line-height: 1.25;
        }

        .facture-table th:nth-child(1),
        .facture-table td:nth-child(1) {
            width: 10%;
        }

        .facture-table th:nth-child(2),
        .facture-table td:nth-child(2) {
            width: 48%;
        }

        .facture-table th:nth-child(3),
        .facture-table td:nth-child(3) {
            width: 21%;
        }

        .facture-table th:nth-child(4),
        .facture-table td:nth-child(4) {
            width: 21%;
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
            padding: 4px 5px;
            font-size: 8pt;
            line-height: 1.35;
            border-bottom: 1px solid #f0f0f0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .facture-table tr:nth-child(even) td {
            background: var(--facture-primary-row);
        }

        .facture-table tr:nth-child(odd) td {
            background: #fff;
        }

        .facture-footer-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 4px 12px;
            gap: 12px;
        }

        .facture-payment {
            flex: 1 1 auto;
            min-width: 0;
        }

        .facture-payment h3 {
            font-size: 9pt;
            font-weight: 700;
            margin-bottom: 4px;
            color: #000;
        }

        .facture-payment p {
            font-size: 8pt;
            color: #666;
            line-height: 1.35;
        }

        .facture-summary {
            min-width: 0;
            flex: 0 0 42%;
            max-width: 42%;
        }

        .facture-summary .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 4px 0;
            font-size: 8pt;
        }

        .facture-summary .total {
            font-weight: 700;
            font-size: 9pt;
            padding-top: 6px;
            border-top: 2px solid var(--facture-primary);
            margin-top: 4px;
        }

        .facture-summary .solde-row {
            background: var(--facture-primary-muted);
            padding: 8px 12px;
            margin-top: 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
        }

        .facture-client-zone {
            padding: 10px 32px 14px;
            border-top: 1px solid #eee;
            background: #fafafa;
        }

        .facture-client-zone .facture-client-zone__title {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 4px;
            letter-spacing: 0.06em;
        }

        .facture-reglement-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            margin-top: 8px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
        }

        .facture-reglement-row--paye {
            background: rgba(46, 125, 50, 0.12);
            color: #1b5e20;
            border: 1px solid rgba(46, 125, 50, 0.35);
        }

        .facture-banner-bottom {
            height: 20px;
            margin-top: auto;
            flex-shrink: 0;
            background: linear-gradient(135deg, color-mix(in srgb, var(--facture-primary) 30%, white) 0%, color-mix(in srgb, var(--facture-primary) 20%, white) 50%, color-mix(in srgb, var(--facture-primary) 25%, white) 100%);
            background-image: repeating-linear-gradient(-45deg, transparent, transparent 10px, color-mix(in srgb, var(--facture-primary) 20%, transparent) 10px, color-mix(in srgb, var(--facture-primary) 20%, transparent) 20px);
        }

        .facture-invoice-title {
            font-size: 12pt;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: #000;
            margin: 0 0 2px;
        }

        .facture-invoice-no {
            font-size: 8.5pt;
            font-weight: 700;
            color: var(--facture-accent);
            margin-bottom: 2px;
        }

        .facture-customer-box {
            border: 1px solid #ddd;
            padding: 8px 10px;
            margin-top: 0;
            background: #fafafa;
        }

        .facture-customer-box__title {
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #666;
            margin-bottom: 4px;
        }

        .facture-thankyou {
            padding: 10px 18px 14px;
            text-align: center;
            font-size: 8pt;
            font-weight: 600;
            color: #555;
            border-top: 1px solid #eee;
        }

        @media screen and (max-width: 620px) {
            .facture-scale-inner {
                transform: scale(0.92);
                margin-bottom: calc(-8% * 1);
            }
        }

        @media screen and (max-width: 520px) {
            .facture-scale-inner {
                transform: scale(0.82);
                margin-bottom: calc(-18% * 1);
            }
        }

        @media screen and (max-width: 420px) {
            body { padding: 8px; }
            .facture-scale-inner {
                transform: scale(0.72);
                margin-bottom: calc(-28% * 1);
            }
        }

        @media screen and (max-width: 360px) {
            .facture-scale-inner {
                transform: scale(0.64);
                margin-bottom: calc(-36% * 1);
            }
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

        .facture-actions a,
        .facture-actions button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--facture-primary);
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
            cursor: pointer;
            font-family: inherit;
        }

        .facture-actions a:hover,
        .facture-actions button:hover {
            background: color-mix(in srgb, var(--facture-primary) 78%, black);
        }

        .facture-actions a.btn-whatsapp,
        .facture-actions button.btn-whatsapp {
            background: #25D366;
        }

        .facture-actions a.btn-whatsapp:hover,
        .facture-actions button.btn-whatsapp:hover {
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
                width: 148mm !important;
                min-height: 210mm !important;
                box-shadow: none !important;
                margin: 0 auto !important;
                overflow: visible !important;
                transform: none !important;
            }

            .facture-body {
                max-width: 550px !important;
                width: 100% !important;
                margin: 0 auto !important;
                padding: 0 6px !important;
            }

            .facture-scale-inner {
                transform: none !important;
                margin-bottom: 0 !important;
            }

            .facture-table-wrapper {
                overflow: visible !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                -webkit-overflow-scrolling: auto !important;
            }

            .facture-table {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
            }

            .facture-table th,
            .facture-table td {
                padding: 4px 6px !important;
                font-size: 10px !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                hyphens: auto !important;
            }

            .facture-table th:nth-child(1),
            .facture-table td:nth-child(1) {
                width: 36% !important;
            }

            .facture-table th:nth-child(2),
            .facture-table td:nth-child(2) {
                width: 22% !important;
            }

            .facture-table th:nth-child(3),
            .facture-table td:nth-child(3) {
                width: 12% !important;
            }

            .facture-table th:nth-child(4),
            .facture-table td:nth-child(4) {
                width: 30% !important;
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

            .facture-header {
                flex-direction: row !important;
                padding: 8px 2px 6px !important;
                box-sizing: border-box !important;
            }

            .facture-entreprise {
                flex-direction: row !important;
                min-width: 0 !important;
                flex: 1 1 auto !important;
            }

            .facture-meta {
                flex-shrink: 0 !important;
                max-width: 42% !important;
            }

            .facture-footer-section {
                flex-direction: row !important;
                padding: 8px 2px 10px !important;
                gap: 10px !important;
                box-sizing: border-box !important;
            }

            .facture-summary {
                min-width: 0 !important;
                flex: 0 0 42% !important;
                max-width: 42% !important;
            }

            .facture-billing {
                padding: 6px 2px !important;
                box-sizing: border-box !important;
            }

            .facture-client-zone {
                padding: 8px 8px 10px !important;
            }

            html, body {
                overflow: visible !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            @page {
                size: A4 portrait;
                margin: 10mm;
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
                height: 24px;
            }

            .facture-body {
                padding: 0 8px;
            }

            .facture-footer-section {
                flex-direction: column;
                gap: 14px;
            }

            .facture-summary {
                flex: 1 1 auto;
                max-width: 100%;
            }

            .facture-banner-bottom {
                height: 18px;
            }

            .facture-actions {
                padding: 8px 0;
                gap: 8px;
            }

            .facture-actions a,
            .facture-actions button {
                padding: 10px 16px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .facture-body {
                padding: 0 6px;
            }

            .facture-actions {
                flex-direction: column;
            }

            .facture-actions a,
            .facture-actions button {
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
            <?php if ($facture_can_share): ?>
                <button type="button" class="btn-whatsapp js-platform-share"
                    aria-haspopup="dialog" aria-controls="platformShareModal"
                    data-share-modal-title="Envoyer la facture"
                    data-share-title="<?php echo htmlspecialchars($facture_share_title, ENT_QUOTES, 'UTF-8'); ?>"
                    data-share-url="<?php echo htmlspecialchars($facture_share_url, ENT_QUOTES, 'UTF-8'); ?>"
                    data-share-text="<?php echo htmlspecialchars($facture_share_message, ENT_QUOTES, 'UTF-8'); ?>"
                    data-share-hint="Partagez le lien et le récapitulatif de la facture avec votre client.">
                    <i class="fab fa-whatsapp"></i> Envoyer la facture sur WhatsApp
                </button>
            <?php elseif (!empty($whatsapp_url)): ?>
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

    <div class="facture-scale-viewport">
    <div class="facture-scale-inner">
    <div class="facture-container">
        <div class="facture-banner-top"></div>

        <div class="facture-body">
        <div class="facture-header">
            <div class="facture-entreprise">
                <div class="facture-logo">
                    <img src="<?php echo htmlspecialchars($facture_logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($entreprise_nom, ENT_QUOTES, 'UTF-8'); ?>"
                        onerror="this.style.background='#fafafa';this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext x=%2250%22 y=%2255%22 text-anchor=%22middle%22 font-size=%2240%22%3E🍰%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="facture-entreprise-info">
                    <h1><?php echo htmlspecialchars($entreprise_nom); ?></h1>
                    <?php if (!empty($entreprise_rc)): ?>
                    <p>R.C : <?php echo htmlspecialchars($entreprise_rc); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($entreprise_ninea)): ?>
                    <p>N.I.N.E.A : <?php echo htmlspecialchars($entreprise_ninea); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($entreprise_tel1)): ?>
                    <div class="tel">
                        <i class="fas fa-phone"
                            style="font-size:11px; margin-right:4px;"></i>+221 <?php echo htmlspecialchars($entreprise_tel1); ?><?php if (!empty($entreprise_tel2)): ?><br>
                        <i class="fas fa-phone"
                            style="font-size:11px; margin-right:4px;"></i>+221 <?php echo htmlspecialchars($entreprise_tel2); ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($entreprise_site)): ?>
                    <p style="margin-top:6px;">
                        <i class="fas fa-globe" style="font-size:11px; margin-right:4px;"></i>
                        <a href="<?php echo htmlspecialchars($entreprise_site); ?>"
                            target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($entreprise_site); ?></a>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($entreprise_email)): ?>
                    <p><i class="fas fa-envelope"
                            style="font-size:11px; margin-right:4px;"></i><?php echo htmlspecialchars($entreprise_email); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="facture-meta">
                <p class="facture-invoice-title">RE&Ccedil;U</p>
                <p class="facture-invoice-no">N&deg; : <?php echo htmlspecialchars($facture['numero_facture']); ?></p>
                <div class="facture-meta-kv">
                    <div class="label">DATE</div>
                    <div class="value"><?php echo htmlspecialchars($date_facture_aff); ?></div>
                </div>
                <?php if (!empty($commande['numero_commande'])): ?>
                <div class="facture-meta-kv">
                    <div class="label">COMMANDE</div>
                    <div class="value">#<?php echo htmlspecialchars($commande['numero_commande']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($facture_bl_statut_libelle !== ''): ?>
                <div class="facture-meta-kv">
                    <div class="label">STATUT DU BL</div>
                    <div class="value facture-meta-bl-statut" style="color:<?php echo htmlspecialchars($facture_bl_meta_color); ?>;"><?php echo htmlspecialchars($facture_bl_statut_libelle); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="facture-billing">
            <div class="facture-customer-box">
                <div class="facture-customer-box__title">INFORMATIONS CLIENT</div>
                <div class="client-name"><?php echo htmlspecialchars($client_nom); ?></div>
                <?php if ($client_telephone !== ''): ?>
                <div class="client-tel"><i class="fas fa-phone"
                        style="font-size:11px; margin-right:4px;"></i><?php echo htmlspecialchars($client_telephone); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="facture-table-wrapper">
            <table class="facture-table">
                <thead>
                    <tr>
                        <th>QTÉ</th>
                        <th>DESCRIPTION</th>
                        <th>PRIX</th>
                        <th>MONTANT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $p): ?>
                        <tr>
                            <td><?php
                            $qte_ent = (int) round((float) ($p['quantite'] ?? 0));
                            echo number_format($qte_ent, 0, ',', ' ');
                            ?></td>
                            <td><?php echo htmlspecialchars($p['produit_nom'] ?? $p['nom'] ?? ''); ?></td>
                            <td><?php echo number_format((float) ($p['prix_unitaire'] ?? 0), 0, ',', ' '); ?> FCFA</td>
                            <td><?php echo number_format((float) ($p['prix_total'] ?? 0), 0, ',', ' '); ?> FCFA</td>
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
                <div class="row total">
                    <span>TOTAL</span>
                    <span><?php echo number_format($facture['montant_total'], 0, ',', ' '); ?> FCFA</span>
                </div>
                <?php if ($facture_bl_statut_libelle !== '' && in_array($facture_bl_statut_code, ['valide', 'paye'], true)): ?>
                <div class="facture-reglement-row facture-reglement-row--paye">
                    <span>STATUT (BL)</span>
                    <span>Validé (comptabilité)</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <?php if ($facture_show_client_zone): ?>
        <div class="facture-client-zone">
            <div class="facture-client-zone__title">Client</div>
        </div>
        <?php endif; ?>

        <p class="facture-thankyou">Merci ! &mdash; Nous appr&eacute;cions votre confiance.</p>

        <div class="facture-banner-bottom"></div>
    </div>
    </div>
    </div>
    <?php if ($facture_can_share): ?>
        <?php require __DIR__ . '/partials/platform_share_modal.php'; ?>
        <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <?php endif; ?>
</body>

</html>