<?php
/**
 * Page d'ajustement du stock d'un produit — redesign v2
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_route_access.php';

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($produit_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_ajuster_stock_produit($produit_id);

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: ajuster-stock.php?id=' . $produit_id);
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_commandes.php';
require_once __DIR__ . '/../../models/model_mouvements_stock.php';

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    header('Location: index.php');
    exit;
}
admin_vendeur_assert_produit_owned($produit);

require_once __DIR__ . '/../../includes/barcode_fpl.php';
$code_fpl_live = ensure_produit_identifiant_interne($produit_id);
if ($code_fpl_live !== null && $code_fpl_live !== '') {
    $produit['identifiant_interne'] = $code_fpl_live;
}
if (get_barcode_produit_web_path($produit_id) === '') {
    generer_barcode_produit_fpl($produit_id);
}
$barcode_url = get_barcode_produit_web_path($produit_id);

$quantite_vendue = get_quantite_vendue_produit($produit_id);
$stock_actuel = (int) ($produit['stock'] ?? 0);
$nombre_total = $stock_actuel + $quantite_vendue;
$stock_restant = $nombre_total - $quantite_vendue;

$prix_produit = (float) ($produit['prix'] ?? 0);
if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < $prix_produit) {
    $prix_produit = (float) $produit['prix_promotion'];
}
$valeur_stock_actuel = $stock_actuel * $prix_produit;
$valeur_ventes = $quantite_vendue * $prix_produit;

$mouvements = get_stock_mouvements(null, $produit_id, null, null, 50);

$qr_code_data_uri = '';
$stock_info_url = '';
$qr_file = __DIR__ . '/../../upload/qrcodes/produit_' . $produit_id . '.png';
require_once __DIR__ . '/../../includes/site_url.php';
$stock_info_url = get_site_base_url() . '/stock-info.php?id=' . $produit_id;
if (file_exists($qr_file)) {
    $qr_code_data_uri = 'data:image/png;base64,' . base64_encode(file_get_contents($qr_file));
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale'        => 8,
            'outputBase64' => true,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $qr_code_data_uri = $qr->render($stock_info_url);
    } catch (Throwable $e) {
        try {
            $qro = new \chillerlan\QRCode\QROptions([
                'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
                'scale'        => 8,
                'outputBase64' => true,
            ]);
            $qr = new \chillerlan\QRCode\QRCode($qro);
            $qr_code_data_uri = $qr->render($stock_info_url);
        } catch (Throwable $e2) {
            $qr_code_data_uri = '';
        }
    }
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Badge statut
$statut_raw = $produit['statut'] ?? 'actif';
$statut_labels = ['actif' => 'Actif', 'inactif' => 'Inactif', 'rupture_stock' => 'Rupture'];
$statut_lbl = $statut_labels[$statut_raw] ?? 'Actif';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock — <?php echo htmlspecialchars($produit['nom']); ?></title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== PAGE ===== */
        .as-page {
            max-width: 1040px;
            margin: 0 auto;
            padding: clamp(14px, 3vw, 28px) clamp(12px, 3vw, 20px) 96px;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* ===== HEADER ===== */
        .as-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .as-header__back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 10px;
            background: rgba(53,100,166,0.08);
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.18s;
        }

        .as-header__back:hover { background: rgba(53,100,166,0.15); }

        .as-header__title {
            font-size: clamp(1.05rem, 2.5vw, 1.3rem);
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
        }

        /* ===== HERO PRODUIT ===== */
        .as-hero {
            background: linear-gradient(135deg, var(--couleur-dominante, #3564a6) 0%, #1e3f7a 55%, #0f2550 100%);
            border-radius: 22px;
            padding: clamp(18px,3vw,32px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 48px rgba(53,100,166,0.32);
        }

        .as-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -40px;
            width: 260px; height: 260px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        .as-hero::after {
            content: '';
            position: absolute;
            bottom: -70px; right: 90px;
            width: 190px; height: 190px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }

        .as-hero__inner {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            flex-wrap: wrap;
        }

        .as-hero__img {
            width: 76px;
            height: 76px;
            border-radius: 16px;
            object-fit: cover;
            border: 2.5px solid rgba(255,255,255,0.25);
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
        }

        .as-hero__info {
            flex: 1;
            min-width: 0;
        }

        .as-hero__eyebrow {
            font-size: 0.64rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255,255,255,0.55);
            margin: 0 0 4px;
        }

        .as-hero__name {
            font-size: clamp(1.05rem, 3vw, 1.4rem);
            font-weight: 800;
            color: #fff;
            margin: 0 0 6px;
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.2;
        }

        .as-hero__chips {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
            align-items: center;
        }

        .as-hero__chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            color: #fff;
        }

        .as-hero__chip i { font-size: 0.68em; opacity: 0.85; }

        .as-hero__chip--actif    { background: rgba(34,197,94,0.22); border-color: rgba(34,197,94,0.35); }
        .as-hero__chip--inactif  { background: rgba(156,163,175,0.2); border-color: rgba(156,163,175,0.3); }
        .as-hero__chip--rupture_stock { background: rgba(239,68,68,0.22); border-color: rgba(239,68,68,0.35); }

        .as-hero__stock-big {
            text-align: right;
            flex-shrink: 0;
        }

        .as-hero__stock-big .num {
            font-size: clamp(2rem, 6vw, 3.2rem);
            font-weight: 900;
            color: #fff;
            line-height: 1;
            font-family: var(--font-titres, 'Poppins', sans-serif);
        }

        .as-hero__stock-big .lbl {
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.5);
            display: block;
            margin-top: 3px;
        }

        /* ===== STATS CARDS ===== */
        .as-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .as-stat {
            background: #fff;
            border-radius: 16px;
            padding: 18px 16px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .as-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(53,100,166,0.12); }

        .as-stat__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .as-stat__label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--gris-moyen, #737373);
        }

        .as-stat__icon {
            width: 32px; height: 32px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.82rem;
        }

        .as-stat--total   .as-stat__icon { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }
        .as-stat--vendu   .as-stat__icon { background: rgba(255,107,53,0.12); color: var(--orange, #FF6B35); }
        .as-stat--restant .as-stat__icon { background: rgba(34,197,94,0.12); color: #16a34a; }

        .as-stat__value {
            font-size: 1.85rem;
            font-weight: 900;
            color: var(--titres, #0d0d0d);
            line-height: 1;
            font-family: var(--font-titres, 'Poppins', sans-serif);
        }

        .as-stat--vendu   .as-stat__value { color: var(--orange, #FF6B35); }
        .as-stat--restant .as-stat__value { color: #16a34a; }

        .as-stat__sub {
            font-size: 0.68rem;
            color: var(--gris-moyen, #737373);
            font-weight: 500;
        }

        /* ===== LAYOUT 2 COLS ===== */
        .as-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 720px) {
            .as-body { grid-template-columns: 1fr; }
        }

        /* ===== CARD générique ===== */
        .as-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.09);
            box-shadow: 0 4px 22px rgba(53,100,166,0.07);
            overflow: hidden;
        }

        .as-card__head {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(53,100,166,0.07);
            background: linear-gradient(90deg, rgba(53,100,166,0.055), rgba(53,100,166,0.015));
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .as-card__head-icon {
            width: 30px; height: 30px;
            border-radius: 9px;
            background: rgba(53,100,166,0.12);
            color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .as-card__head h3 {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--titres, #0d0d0d);
        }

        .as-card__body { padding: 18px; }

        /* ===== FORM AJOUT STOCK ===== */
        .as-form-input-row {
            display: flex;
            align-items: center;
            gap: 0;
            border: 2px solid rgba(53,100,166,0.2);
            border-radius: 12px;
            background: var(--fond-secondaire, #fafafa);
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
            margin-bottom: 14px;
        }

        .as-form-input-row:focus-within {
            border-color: var(--couleur-dominante, #3564a6);
            box-shadow: 0 0 0 4px rgba(53,100,166,0.1);
        }

        .as-form-input-row__prefix {
            padding: 0 14px;
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            align-self: stretch;
        }

        .as-form-input-row input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 13px 14px;
            font-size: 1.2rem;
            font-weight: 700;
            font-family: var(--font-titres, 'Poppins', sans-serif);
            color: var(--titres, #0d0d0d);
            outline: none;
            width: 100%;
        }

        .as-form-input-row input::placeholder { color: #bbb; font-weight: 400; font-size: 1rem; }

        /* Prévisualisation du résultat */
        .as-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(53,100,166,0.07) 0%, rgba(53,100,166,0.02) 100%);
            border: 1.5px solid rgba(53,100,166,0.15);
            margin-bottom: 16px;
        }

        .as-preview__step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }

        .as-preview__num {
            font-size: 1.5rem;
            font-weight: 900;
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1;
        }

        .as-preview__num--current { color: var(--couleur-dominante, #3564a6); }
        .as-preview__num--add     { color: #16a34a; }
        .as-preview__num--result  { color: var(--orange, #FF6B35); }

        .as-preview__lbl {
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--gris-moyen, #737373);
        }

        .as-preview__op {
            font-size: 1.4rem;
            font-weight: 900;
            color: var(--gris-moyen, #737373);
            flex-shrink: 0;
        }

        /* Bouton submit */
        .as-submit-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 20px;
            background: linear-gradient(135deg, var(--couleur-dominante, #3564a6), #2d5690);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 700;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(53,100,166,0.28);
            transition: transform 0.18s, box-shadow 0.18s;
        }

        .as-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(53,100,166,0.35);
        }

        .as-submit-btn:disabled {
            background: #d1d5db;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== COMPTA ===== */
        .as-compta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .as-compta-item {
            padding: 14px 12px;
            border-radius: 12px;
            background: rgba(53,100,166,0.04);
            border: 1px solid rgba(53,100,166,0.1);
        }

        .as-compta-item__label {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--gris-moyen, #737373);
            margin-bottom: 5px;
        }

        .as-compta-item__val {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--couleur-dominante, #3564a6);
            font-family: var(--font-titres, 'Poppins', sans-serif);
        }

        .as-compta-item__detail {
            font-size: 0.62rem;
            color: var(--gris-moyen, #737373);
            margin-top: 2px;
        }

        /* ===== BARCODE / QR ===== */
        .as-tools {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .as-tool-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(53,100,166,0.09);
            box-shadow: 0 2px 14px rgba(53,100,166,0.06);
            overflow: hidden;
        }

        .as-tool-card__head {
            padding: 11px 16px;
            border-bottom: 1px solid rgba(53,100,166,0.07);
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(53,100,166,0.03);
        }

        .as-tool-card__head i {
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.88rem;
        }

        .as-tool-card__head h4 {
            margin: 0;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--titres, #0d0d0d);
        }

        .as-tool-card__body {
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .as-tool-card__desc {
            font-size: 0.72rem;
            color: var(--gris-moyen, #737373);
            text-align: center;
            line-height: 1.4;
        }

        .as-barcode-wrap, .as-qr-wrap {
            background: #fff;
            padding: 12px 16px 10px;
            border-radius: 10px;
            border: 1.5px solid rgba(53,100,166,0.1);
            text-align: center;
        }

        .as-barcode-wrap img { max-width: 100%; height: auto; display: block; image-rendering: pixelated; }
        .as-qr-wrap img { width: 150px; height: 150px; display: block; }
        .as-barcode-code { font-size: 0.78rem; font-weight: 700; letter-spacing: 0.08em; color: var(--titres, #0d0d0d); margin-top: 7px; font-family: ui-monospace, Consolas, monospace; }

        .as-tool-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 9px;
            background: rgba(53,100,166,0.08);
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.74rem;
            font-weight: 700;
            border: 1px solid rgba(53,100,166,0.18);
            cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: background 0.18s;
        }

        .as-tool-btn:hover { background: rgba(53,100,166,0.14); }

        /* ===== HISTORIQUE ===== */
        .as-history {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.09);
            box-shadow: 0 4px 22px rgba(53,100,166,0.07);
            overflow: hidden;
        }

        .as-history__head {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(53,100,166,0.07);
            background: linear-gradient(90deg, rgba(53,100,166,0.055), rgba(53,100,166,0.015));
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .as-history__head-icon {
            width: 30px; height: 30px;
            border-radius: 9px;
            background: rgba(53,100,166,0.12);
            color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.78rem;
        }

        .as-history__head h3 {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--titres, #0d0d0d);
            flex: 1;
        }

        .as-history__count {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            background: rgba(53,100,166,0.1);
            color: var(--couleur-dominante, #3564a6);
        }

        /* Timeline mouvements */
        .as-timeline { padding: 14px 18px 10px; }

        .as-mvt {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 11px 0;
            border-bottom: 1px solid rgba(53,100,166,0.06);
        }

        .as-mvt:last-child { border-bottom: none; }

        .as-mvt__dot {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .as-mvt__dot--entree    { background: rgba(53,100,166,0.12); color: var(--couleur-dominante, #3564a6); }
        .as-mvt__dot--sortie    { background: rgba(255,107,53,0.12); color: var(--orange, #FF6B35); }
        .as-mvt__dot--inventaire { background: rgba(34,197,94,0.12); color: #16a34a; }

        .as-mvt__body { flex: 1; min-width: 0; }

        .as-mvt__row1 {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .as-mvt__badge {
            padding: 2px 9px;
            border-radius: 50px;
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .as-mvt__badge--entree    { background: rgba(53,100,166,0.12); color: var(--couleur-dominante, #3564a6); }
        .as-mvt__badge--sortie    { background: rgba(255,107,53,0.12); color: var(--orange, #FF6B35); }
        .as-mvt__badge--inventaire { background: rgba(34,197,94,0.12); color: #16a34a; }

        .as-mvt__ref {
            font-size: 0.72rem;
            color: var(--gris-moyen, #737373);
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .as-mvt__qty {
            margin-left: auto;
            font-size: 0.84rem;
            font-weight: 800;
            font-family: var(--font-titres, 'Poppins', sans-serif);
            flex-shrink: 0;
        }

        .as-mvt__qty--entree    { color: var(--couleur-dominante, #3564a6); }
        .as-mvt__qty--sortie    { color: var(--orange, #FF6B35); }
        .as-mvt__qty--inventaire { color: #16a34a; }

        .as-mvt__row2 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
        }

        .as-mvt__date {
            font-size: 0.65rem;
            color: var(--gris-moyen, #737373);
        }

        .as-mvt__flow {
            font-size: 0.65rem;
            color: var(--gris-clair, #a3a3a3);
            font-variant-numeric: tabular-nums;
        }

        .as-mvt__notes {
            font-size: 0.64rem;
            color: var(--gris-moyen, #737373);
            margin-top: 3px;
            font-style: italic;
        }

        .as-empty {
            padding: 28px 20px;
            text-align: center;
            color: var(--gris-moyen, #737373);
            font-size: 0.82rem;
        }

        .as-empty i { font-size: 1.8rem; opacity: 0.25; display: block; margin-bottom: 8px; }

        /* Responsive */
        @media (max-width: 640px) {
            .as-stats { grid-template-columns: repeat(3, 1fr); }
            .as-stat__value { font-size: 1.45rem; }
            .as-hero__img { width: 60px; height: 60px; }
            .as-preview { flex-wrap: wrap; justify-content: center; }
        }

        @media (max-width: 420px) {
            .as-stats { grid-template-columns: 1fr 1fr; }
            .as-compta { grid-template-columns: 1fr; }
        }

        @media print {
            .as-header, .as-hero, .as-stats, .as-body, .as-history,
            nav, footer { display: none !important; }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="as-page">

        <!-- ===== HEADER ===== -->
        <div class="as-header">
            <a href="../stock/index.php" class="as-header__back">
                <i class="fas fa-arrow-left"></i> Retour aux produits
            </a>
            <p class="as-header__title">Gestion du stock</p>
        </div>

        <!-- ===== HERO PRODUIT ===== -->
        <div class="as-hero">
            <div class="as-hero__inner">
                <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>"
                    alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                    class="as-hero__img"
                    onerror="this.src='/image/produit1.jpg'">
                <div class="as-hero__info">
                    <p class="as-hero__eyebrow"><i class="fas fa-box"></i>&nbsp; Produit</p>
                    <h1 class="as-hero__name"><?php echo htmlspecialchars($produit['nom']); ?></h1>
                    <div class="as-hero__chips">
                        <span class="as-hero__chip as-hero__chip--<?php echo htmlspecialchars($statut_raw); ?>">
                            <i class="fas fa-circle" style="font-size:0.45em;"></i>
                            <?php echo $statut_lbl; ?>
                        </span>
                        <span class="as-hero__chip">
                            <i class="fas fa-tag"></i>
                            <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA / u
                        </span>
                        <?php if (!empty($produit['identifiant_interne'])): ?>
                        <span class="as-hero__chip">
                            <i class="fas fa-barcode"></i>
                            <?php echo htmlspecialchars($produit['identifiant_interne']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="as-hero__stock-big">
                    <div class="num"><?php echo $stock_actuel; ?></div>
                    <span class="lbl">en stock</span>
                </div>
            </div>
        </div>

        <!-- ===== STATS ===== -->
        <div class="as-stats">
            <div class="as-stat as-stat--total">
                <div class="as-stat__head">
                    <span class="as-stat__label">Total entré</span>
                    <div class="as-stat__icon"><i class="fas fa-layer-group"></i></div>
                </div>
                <div class="as-stat__value"><?php echo $nombre_total; ?></div>
                <div class="as-stat__sub">unités cumulées</div>
            </div>
            <div class="as-stat as-stat--vendu">
                <div class="as-stat__head">
                    <span class="as-stat__label">Vendues</span>
                    <div class="as-stat__icon"><i class="fas fa-shopping-bag"></i></div>
                </div>
                <div class="as-stat__value"><?php echo $quantite_vendue; ?></div>
                <div class="as-stat__sub">
                    <?php echo number_format($valeur_ventes, 0, ',', ' '); ?> FCFA
                </div>
            </div>
            <div class="as-stat as-stat--restant">
                <div class="as-stat__head">
                    <span class="as-stat__label">En stock</span>
                    <div class="as-stat__icon"><i class="fas fa-warehouse"></i></div>
                </div>
                <div class="as-stat__value"><?php echo $stock_restant; ?></div>
                <div class="as-stat__sub">
                    <?php echo number_format($valeur_stock_actuel, 0, ',', ' '); ?> FCFA
                </div>
            </div>
        </div>

        <!-- ===== BODY 2 COLONNES ===== -->
        <div class="as-body">

            <!-- Colonne gauche : formulaire + compta -->
            <div style="display:flex;flex-direction:column;gap:16px;">

                <!-- Formulaire d'ajout -->
                <div class="as-card">
                    <div class="as-card__head">
                        <div class="as-card__head-icon"><i class="fas fa-plus-circle"></i></div>
                        <h3>Ajouter du stock</h3>
                    </div>
                    <div class="as-card__body">
                        <form method="POST" action="?id=<?php echo $produit_id; ?>" id="asForm">
                            <input type="hidden" name="ajuster_stock" value="1">
                            <input type="hidden" name="nouveau_stock" id="hiddenNouveauStock" value="<?php echo $stock_actuel; ?>">

                            <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--gris-moyen,#737373);display:block;margin-bottom:8px;">
                                Quantité à ajouter
                            </label>
                            <div class="as-form-input-row">
                                <span class="as-form-input-row__prefix"><i class="fas fa-plus"></i></span>
                                <input type="number" id="quantiteAjout" name="quantite_ajout_ui"
                                    min="1" placeholder="ex : 50"
                                    autocomplete="off">
                            </div>

                            <button type="submit" class="as-submit-btn" id="asSubmitBtn" disabled>
                                <i class="fas fa-check"></i>
                                Confirmer l'ajout de stock
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Comptabilité -->
                <div class="as-card">
                    <div class="as-card__head">
                        <div class="as-card__head-icon"><i class="fas fa-calculator"></i></div>
                        <h3>Valeur comptable</h3>
                    </div>
                    <div class="as-card__body">
                        <div class="as-compta">
                            <div class="as-compta-item">
                                <div class="as-compta-item__label">Valeur stock actuel</div>
                                <div class="as-compta-item__val"><?php echo number_format($valeur_stock_actuel, 0, ',', ' '); ?> <small style="font-size:0.55em;">FCFA</small></div>
                                <div class="as-compta-item__detail"><?php echo $stock_actuel; ?> × <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="as-compta-item">
                                <div class="as-compta-item__label">Chiffre d'affaires</div>
                                <div class="as-compta-item__val"><?php echo number_format($valeur_ventes, 0, ',', ' '); ?> <small style="font-size:0.55em;">FCFA</small></div>
                                <div class="as-compta-item__detail"><?php echo $quantite_vendue; ?> vendu(s) × <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Colonne droite : barcode + QR -->
            <div class="as-tools">

                <?php if (!empty($barcode_url) && !empty($produit['identifiant_interne'])): ?>
                <div class="as-tool-card" id="barcode-fpl-print-area"
                    data-barcode-src="<?php echo htmlspecialchars($barcode_url); ?>"
                    data-code="<?php echo htmlspecialchars($produit['identifiant_interne']); ?>"
                    data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <div class="as-tool-card__head">
                        <i class="fas fa-barcode"></i>
                        <h4>Code-barres (réf. FPL)</h4>
                    </div>
                    <div class="as-tool-card__body">
                        <p class="as-tool-card__desc">Code <strong>Code 128</strong> — utilisable avec un scanner ou l'API produit.</p>
                        <div class="as-barcode-wrap">
                            <?php
                            $barcode_fs = __DIR__ . '/../../upload/barcodes/produit_' . $produit_id . '.png';
                            $barcode_ver = is_file($barcode_fs) ? (int) filemtime($barcode_fs) : 1;
                            ?>
                            <img src="<?php echo htmlspecialchars($barcode_url); ?>?v=<?php echo $barcode_ver; ?>"
                                alt="Code-barres <?php echo htmlspecialchars($produit['identifiant_interne']); ?>"
                                style="max-width:100%;height:auto;display:block;image-rendering:pixelated;">
                            <div class="as-barcode-code"><?php echo htmlspecialchars($produit['identifiant_interne']); ?></div>
                        </div>
                        <button type="button" class="as-tool-btn" onclick="imprimerCodeBarresFPL()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($qr_code_data_uri)): ?>
                <div class="as-tool-card" id="qr-code-print-area"
                    data-qr="<?php echo htmlspecialchars($qr_code_data_uri); ?>"
                    data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <div class="as-tool-card__head">
                        <i class="fas fa-qrcode"></i>
                        <h4>QR Code du produit</h4>
                    </div>
                    <div class="as-tool-card__body">
                        <p class="as-tool-card__desc">Scannez pour afficher les détails du stock sur mobile.</p>
                        <div class="as-qr-wrap">
                            <img src="<?php echo htmlspecialchars($qr_code_data_uri); ?>"
                                alt="QR Code - <?php echo htmlspecialchars($produit['nom']); ?>"
                                class="as-qr-wrap img" style="width:150px;height:150px;">
                        </div>
                        <button type="button" class="as-tool-btn" onclick="imprimerQRCode()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- ===== HISTORIQUE ===== -->
        <div class="as-history">
            <div class="as-history__head">
                <div class="as-history__head-icon"><i class="fas fa-clock-rotate-left"></i></div>
                <h3>Historique des mouvements</h3>
                <span class="as-history__count"><?php echo count($mouvements); ?></span>
            </div>

            <?php if (empty($mouvements)): ?>
            <div class="as-empty">
                <i class="fas fa-inbox"></i>
                Aucun mouvement enregistré pour ce produit.
            </div>
            <?php else: ?>
            <div class="as-timeline">
                <?php foreach ($mouvements as $mv):
                    $mv_type = $mv['type'] ?? 'inventaire';
                    $mv_icons = ['entree' => 'fa-arrow-down', 'sortie' => 'fa-arrow-up', 'inventaire' => 'fa-sliders'];
                    $mv_labels = ['entree' => 'Entrée', 'sortie' => 'Sortie', 'inventaire' => 'Inventaire'];
                    $mv_icon = $mv_icons[$mv_type] ?? 'fa-circle';
                    $mv_label = $mv_labels[$mv_type] ?? 'Autre';
                    $mv_ref = htmlspecialchars($mv['reference_numero'] ?? ($mv['reference_type'] ?? ''));
                    $mv_sign = $mv_type === 'sortie' ? '−' : '+';
                ?>
                <div class="as-mvt">
                    <div class="as-mvt__dot as-mvt__dot--<?php echo htmlspecialchars($mv_type); ?>">
                        <i class="fas <?php echo $mv_icon; ?>"></i>
                    </div>
                    <div class="as-mvt__body">
                        <div class="as-mvt__row1">
                            <span class="as-mvt__badge as-mvt__badge--<?php echo htmlspecialchars($mv_type); ?>"><?php echo $mv_label; ?></span>
                            <?php if ($mv_ref): ?>
                            <span class="as-mvt__ref"><?php echo $mv_ref; ?></span>
                            <?php endif; ?>
                            <span class="as-mvt__qty as-mvt__qty--<?php echo htmlspecialchars($mv_type); ?>"><?php echo $mv_sign . (int)$mv['quantite']; ?></span>
                        </div>
                        <div class="as-mvt__row2">
                            <span class="as-mvt__date"><i class="far fa-clock" style="font-size:0.75em;"></i> <?php echo date('d/m/Y H:i', strtotime($mv['date_mouvement'])); ?></span>
                            <?php if ($mv['quantite_avant'] !== null && $mv['quantite_apres'] !== null): ?>
                            <span class="as-mvt__flow"><?php echo (int)$mv['quantite_avant']; ?> → <?php echo (int)$mv['quantite_apres']; ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($mv['notes'])): ?>
                        <div class="as-mvt__notes"><?php echo htmlspecialchars($mv['notes']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /.as-page -->

    <?php include '../includes/footer.php'; ?>

    <script>
    (function() {
        var stockActuel = <?php echo (int)$stock_actuel; ?>;
        var inputAjout  = document.getElementById('quantiteAjout');
        var hiddenStock = document.getElementById('hiddenNouveauStock');
        var submitBtn   = document.getElementById('asSubmitBtn');

        inputAjout.addEventListener('input', function() {
            var val = parseInt(this.value, 10);
            var ajout = (!isNaN(val) && val > 0) ? val : 0;
            hiddenStock.value    = stockActuel + ajout;
            submitBtn.disabled   = (ajout <= 0);
        });
    })();

    function imprimerCodeBarresFPL() {
        var block = document.getElementById('barcode-fpl-print-area');
        if (!block) return;
        var src  = block.getAttribute('data-barcode-src');
        var code = block.getAttribute('data-code') || '';
        var nom  = block.getAttribute('data-nom') || 'Produit';
        if (!src) return;
        var w = window.open('', '_blank', 'width=420,height=360');
        w.document.write('<!DOCTYPE html><html><head><title>Code-barres ' + code + '</title><style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;} img{max-width:100%;height:auto;} .code{font-size:18px;font-weight:700;margin-top:12px;letter-spacing:0.08em;font-family:monospace;} h2{font-size:15px;margin:0 0 8px;text-align:center;color:#1a1a1a;}</style></head><body><h2>' + nom.replace(/</g,'&lt;') + '</h2><img src="' + src + '" alt="Code-barres"><div class="code">' + code.replace(/</g,'&lt;') + '</div><p style="font-size:12px;color:#737373;">Référence FPL</p></body></html>');
        w.document.close(); w.focus();
        setTimeout(function() { w.print(); w.close(); }, 300);
    }

    function imprimerQRCode() {
        var block = document.getElementById('qr-code-print-area');
        if (!block) return;
        var qr  = block.getAttribute('data-qr');
        var nom = block.getAttribute('data-nom') || 'Produit';
        var w = window.open('', '_blank', 'width=400,height=500');
        w.document.write('<!DOCTYPE html><html><head><title>QR Code - ' + nom + '</title><style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;} img{max-width:280px;height:auto;} h2{font-size:16px;margin-top:16px;text-align:center;color:#1a1a1a;}</style></head><body><img src="' + qr + '" alt="QR Code"><h2>' + nom + '</h2><p style="font-size:12px;color:#737373;">Scannez pour voir le stock</p></body></html>');
        w.document.close(); w.focus();
        setTimeout(function() { w.print(); w.close(); }, 300);
    }
    </script>
</body>

</html>
