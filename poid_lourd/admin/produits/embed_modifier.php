<?php
/**
 * Formulaire de modification produit — version embarquée (iframe modal stock).
 */

require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($produit_id <= 0) {
    http_response_code(404);
    echo 'Produit introuvable.';
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_variantes.php';
require_once __DIR__ . '/../../includes/admin_route_access.php';

$produit = get_produit_by_id($produit_id);
$variantes = $produit ? get_variantes_by_produit($produit_id) : [];

if (!$produit) {
    http_response_code(404);
    echo 'Produit introuvable.';
    exit;
}
admin_vendeur_assert_produit_owned($produit);

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_genres.php';
$categories = admin_categories_list_for_session();
$__role_mod = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$fap_use_category_hierarchy = categories_hierarchy_enabled() && ($__role_mod === 'vendeur');
$vendeur_genre_ids_prefill = [];
$vendeur_sous_categorie_ids_prefill = [];
if ($fap_use_category_hierarchy && function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif()) {
    $vendeur_genre_ids_prefill = get_genre_ids_for_produit($produit_id);
    require_once __DIR__ . '/../../models/model_produits_sous_categories.php';
    if (function_exists('get_sous_categorie_ids_for_produit')) {
        $vendeur_sous_categorie_ids_prefill = get_sous_categorie_ids_for_produit($produit_id);
    }
} elseif ($fap_use_category_hierarchy) {
    require_once __DIR__ . '/../../models/model_produits_sous_categories.php';
    if (function_exists('get_sous_categorie_ids_for_produit') && function_exists('produits_sous_categories_table_exists') && produits_sous_categories_table_exists()) {
        $vendeur_sous_categorie_ids_prefill = get_sous_categorie_ids_for_produit($produit_id);
    }
    if (empty($vendeur_sous_categorie_ids_prefill) && (int) ($produit['categorie_id'] ?? 0) > 0) {
        $vendeur_sous_categorie_ids_prefill = [(int) $produit['categorie_id']];
    }
}
$vcat_prefill_sub = 0;
$vcat_prefill_generale = 0;
if ($fap_use_category_hierarchy && function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif()) {
    if (produits_has_column('categorie_generale_id')) {
        $vcat_prefill_generale = (int) ($produit['categorie_generale_id'] ?? 0);
    }
} elseif ($fap_use_category_hierarchy) {
    $pcat = get_categorie_by_id((int) ($produit['categorie_id'] ?? 0));
    if ($pcat) {
        $vcat_prefill_sub = (int) $pcat['id'];
    }
    if (produits_has_column('categorie_generale_id')) {
        $pg = (int) ($produit['categorie_generale_id'] ?? 0);
        if ($pg > 0) {
            $vcat_prefill_generale = $pg;
        } elseif ($pcat && function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
            $vcat_prefill_generale = (int) ($pcat['categorie_generale_id'] ?? 0);
        }
    }
}

function embed_modifier_normalize_options_json(?string $raw): string {
    if ($raw === null || $raw === '' || $raw === '[]') {
        return '';
    }
    $dec = json_decode($raw, true);
    if (is_array($dec)) {
        $dec = array_filter($dec, function ($x) {
            $v = is_array($x) ? ($x['v'] ?? '') : $x;
            return $v !== '' && $v !== '[]';
        });
        return !empty($dec) ? json_encode(array_values($dec)) : '';
    }
    return $raw;
}

$poids_val = embed_modifier_normalize_options_json($produit['poids'] ?? '');
$taille_val = embed_modifier_normalize_options_json($produit['taille'] ?? '');
$couleurs_raw = trim((string) ($produit['couleurs'] ?? ''));
$couleurs_pm = $couleurs_raw;
if ($couleurs_raw !== '') {
    $dec = json_decode($couleurs_raw, true);
    if (is_array($dec)) {
        $ok = array_values(array_filter($dec, function ($c) {
            return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
        }));
        $couleurs_pm = !empty($ok) ? json_encode($ok) : $couleurs_raw;
    }
}

$PM = [
    'nom' => (string) ($produit['nom'] ?? ''),
    'description' => (string) ($produit['description'] ?? ''),
    'prix' => (string) ($produit['prix'] ?? ''),
    'prix_promotion' => ($produit['prix_promotion'] !== null && $produit['prix_promotion'] !== '')
        ? (string) $produit['prix_promotion'] : '',
    'stock' => (string) ($produit['stock'] ?? '0'),
    'categorie_id' => (string) (int) ($produit['categorie_id'] ?? 0),
    'unite' => (string) ($produit['unite'] ?? 'unité'),
    'mesure' => (string) ($produit['mesure'] ?? ''),
    'statut' => (string) ($produit['statut'] ?? 'actif'),
    'poids' => $poids_val,
    'taille' => $taille_val,
    'couleurs' => $couleurs_pm,
];
if (produits_has_column('categorie_generale_id')) {
    $PM['categorie_generale_id'] = (string) (int) ($produit['categorie_generale_id'] ?? 0);
}

$fap_is_edit = true;
$fap_edit_produit = $produit;
$fap_edit_modal = true;
$add_produit_modal = false;
$add_produit_form_action = '../stock/index.php';
$categorie_id_prefill = 0;
$add_produit_error_message = '';

$fap_edit_variantes_js = [];
foreach ($variantes as $v) {
    $vid = (int) ($v['id'] ?? 0);
    if ($vid <= 0) {
        continue;
    }
    $imgPath = isset($v['image']) ? trim((string) $v['image']) : '';
    $existingImage = ($imgPath !== '') ? ('/upload/' . $imgPath) : null;
    $pvp = isset($v['prix_promotion']) && (float) $v['prix_promotion'] > 0 ? (string) (float) $v['prix_promotion'] : '';
    $fap_edit_variantes_js[] = [
        'id' => $vid,
        'nom' => (string) ($v['nom'] ?? ''),
        'prix' => isset($v['prix']) ? (string) (float) $v['prix'] : '0',
        'promo' => $pvp,
        'existingImage' => $existingImage,
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le produit</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        body { margin: 0; background: var(--fond-secondaire, #fafafa); }
        .form-add-section { padding: clamp(10px, 2.5vw, 18px) clamp(8px, 2.5vw, 16px) clamp(28px, 5vw, 40px); }
        @media (max-width: 1024px) {
            .form-add-section { padding-bottom: calc(32px + env(safe-area-inset-bottom, 0px)); }
        }
    </style>
</head>
<body>
    <?php if (($produit['statut'] ?? '') === 'bloque' && function_exists('produit_bloque_champs_labels')):
        $bloque_lbls_mod = produit_bloque_champs_labels((string) ($produit['bloque_champs'] ?? ''));
        ?>
        <div class="cert-alert cert-alert--error" style="margin:12px 16px 0;padding:12px 14px;border-radius:12px;" role="alert">
            <i class="fas fa-ban"></i>
            <span>
                <strong>Produit bloqué par la plateforme.</strong>
                <?php if (!empty($produit['bloque_motif'])): ?>
                    Motif : <?php echo htmlspecialchars((string) $produit['bloque_motif'], ENT_QUOTES, 'UTF-8'); ?>.
                <?php endif; ?>
                <?php if (!empty($bloque_lbls_mod)): ?>
                    Modifiez : <strong><?php echo htmlspecialchars(implode(' et ', $bloque_lbls_mod), ENT_QUOTES, 'UTF-8'); ?></strong>.
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <section class="form-add-section">
        <div class="form-add-container">
            <?php require __DIR__ . '/inc_form_ajouter_produit.php'; ?>
        </div>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('btn-fap-cancel-edit-modal');
            if (btn) {
                btn.addEventListener('click', function () {
                    try { window.parent.postMessage({ type: 'stk-close-edit-modal' }, '*'); } catch (e) {}
                });
            }
        });
    </script>
</body>
</html>
