<?php
/**
 * Classification vendeur : genres (cases à cocher) ou ancien couple rayon + sous-catégorie.
 * Variables optionnelles : $vcat_prefill_sub, $vcat_prefill_generale, $vendeur_subcats_for_form,
 * $vendeur_genre_ids_prefill (tableau d’IDs pour édition).
 * $vendeur_sous_categorie_ids_prefill (tableau d’IDs sous-cat. pour édition).
 */
if (!function_exists('get_plateforme_sous_categories_for_form')) {
    require_once __DIR__ . '/../../models/model_categories.php';
}
if (!function_exists('vendeur_genres_mode_actif')) {
    require_once __DIR__ . '/../../models/model_genres.php';
}

$vcat_s = isset($vcat_prefill_sub) ? (int) $vcat_prefill_sub : (int) ($_POST['categorie_id'] ?? 0);
$vcat_g = isset($vcat_prefill_generale) ? (int) $vcat_prefill_generale : (int) ($_POST['categorie_generale_id'] ?? 0);
$vendeur_genre_ids_prefill = isset($vendeur_genre_ids_prefill) && is_array($vendeur_genre_ids_prefill)
    ? array_map('intval', $vendeur_genre_ids_prefill)
    : [];
$vendeur_sous_categorie_ids_prefill = isset($vendeur_sous_categorie_ids_prefill) && is_array($vendeur_sous_categorie_ids_prefill)
    ? array_values(array_filter(array_map('intval', $vendeur_sous_categorie_ids_prefill), function ($x) {
        return (int) $x > 0;
    }))
    : [];

if (function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif()) {
    $rayons_liste = (function_exists('categories_generales_table_exists') && categories_generales_table_exists())
        ? get_general_categories_ordered() : [];
    $genres_liste = genres_list_all();
    $genres_wrap_hidden = (!function_exists('genres_cg_links_table_exists') || !genres_cg_links_table_exists());
    $vendeur_subcats_for_form = [];
    if (function_exists('get_plateforme_sous_categories_for_form')) {
        $vendeur_subcats_for_form = get_plateforme_sous_categories_for_form();
    }
    ?>
<div class="fap-field fap-field-cat-generale fap-field-cat-vendeur-only">
    <label for="categorie_generale_id">Catégorie principale <span class="required">*</span></label>
    <select id="categorie_generale_id" name="categorie_generale_id" required>
        <option value=""><?php echo !empty($rayons_liste) ? 'Choisir une catégorie principale' : 'Aucune catégorie configurée'; ?></option>
        <?php foreach ($rayons_liste as $rg): ?>
            <?php $rid = (int) ($rg['id'] ?? 0); ?>
            <?php if ($rid <= 0) {
                continue;
            } ?>
        <option value="<?php echo $rid; ?>" <?php echo ($vcat_g === $rid) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars((string) ($rg['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>

<div id="fap-genres-wrap" class="fap-field fap-field-genres fap-field-cat-vendeur-only"<?php echo $genres_wrap_hidden ? ' hidden' : ''; ?>>
    <fieldset class="fap-fieldset-genres">
        <legend>Genres <span class="required" id="fap-genres-legend-required" hidden aria-hidden="true">*</span></legend>
        <?php if (empty($genres_liste)): ?>
            <p class="fap-hint fap-hint--warn">Aucun genre n’est encore configuré. Contactez le super administrateur.</p>
        <?php else: ?>
        <div class="fap-genres-grid" role="group" aria-label="Genres du produit" id="fap-genres-grid-inner">
            <?php foreach ($genres_liste as $gr): ?>
                <?php
                $gid = (int) ($gr['id'] ?? 0);
                if ($gid <= 0) {
                    continue;
                }
                $rayons_genre = function_exists('get_categorie_generale_ids_for_genre') ? get_categorie_generale_ids_for_genre($gid) : [];
                $rayons_attr = implode(',', $rayons_genre);
                $checked = false;
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['genre_ids']) && is_array($_POST['genre_ids'])) {
                    $checked = in_array($gid, array_map('intval', $_POST['genre_ids']), true);
                } else {
                    $checked = in_array($gid, $vendeur_genre_ids_prefill, true);
                }
                ?>
            <label class="fap-genre-label" data-genre-rayons="<?php echo htmlspecialchars($rayons_attr, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="checkbox" name="genre_ids[]" value="<?php echo $gid; ?>" <?php echo $checked ? 'checked' : ''; ?> disabled>
                <span><?php echo htmlspecialchars((string) ($gr['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </fieldset>
</div>

<?php if (!empty($vendeur_subcats_for_form)): ?>
<div id="fap-souscats-wrap" class="fap-field fap-field-souscats fap-field-cat-vendeur-only" hidden>
    <fieldset class="fap-fieldset-genres fap-fieldset-souscats">
        <legend>Sous-catégories <span class="required" id="fap-souscats-legend-required" hidden aria-hidden="true">*</span></legend>
        <div class="fap-genres-grid" role="group" aria-label="Sous-catégories du produit" id="fap-souscats-grid-inner">
            <?php foreach ($vendeur_subcats_for_form as $srow):
                $sid = (int) ($srow['id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $ray_sous = function_exists('plateforme_get_rayons_ids_for_categorie') ? plateforme_get_rayons_ids_for_categorie($sid) : [];
                $ray_sous_attr = implode(',', $ray_sous);
                $s_checked = false;
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sous_categorie_ids']) && is_array($_POST['sous_categorie_ids'])) {
                    $s_checked = in_array($sid, array_map('intval', $_POST['sous_categorie_ids']), true);
                } else {
                    $s_checked = in_array($sid, $vendeur_sous_categorie_ids_prefill, true);
                }
                ?>
            <label class="fap-genre-label fap-sousc-label" data-sousc-rayons="<?php echo htmlspecialchars($ray_sous_attr, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="checkbox" name="sous_categorie_ids[]" value="<?php echo $sid; ?>" <?php echo $s_checked ? 'checked' : ''; ?> disabled>
                <span><?php echo htmlspecialchars((string) ($srow['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
</div>
<?php endif; ?>

<script>
(function () {
    var sel = document.getElementById('categorie_generale_id');
    var wrap = document.getElementById('fap-genres-wrap');
    var reqStar = document.getElementById('fap-genres-legend-required');
    if (!sel || !wrap) return;
    var labels = wrap.querySelectorAll('.fap-genre-label');
    function syncGenresVisibility() {
        var cg = parseInt(sel.value, 10) || 0;
        var anyVisible = false;
        for (var i = 0; i < labels.length; i++) {
            var lab = labels[i];
            var raw = lab.getAttribute('data-genre-rayons') || '';
            var parts = raw.split(',');
            var ids = [];
            for (var j = 0; j < parts.length; j++) {
                var n = parseInt(parts[j].trim(), 10);
                if (n > 0) ids.push(n);
            }
            var ok = cg > 0 && ids.indexOf(cg) !== -1;
            lab.style.display = ok ? '' : 'none';
            var inp = lab.querySelector('input[type="checkbox"]');
            if (inp) {
                inp.disabled = !ok;
                if (!ok) inp.checked = false;
            }
            if (ok) anyVisible = true;
        }
        wrap.hidden = !anyVisible;
        if (reqStar) {
            reqStar.hidden = !anyVisible;
            reqStar.setAttribute('aria-hidden', anyVisible ? 'false' : 'true');
        }
    }
    sel.addEventListener('change', syncGenresVisibility);
    syncGenresVisibility();
})();
</script>
<?php if (!empty($vendeur_subcats_for_form)): ?>
<script>
(function () {
    var sel = document.getElementById('categorie_generale_id');
    var wrap = document.getElementById('fap-souscats-wrap');
    var reqS = document.getElementById('fap-souscats-legend-required');
    if (!sel || !wrap) return;
    var labels = wrap.querySelectorAll('.fap-sousc-label');
    function syncSouscats() {
        var cg = parseInt(sel.value, 10) || 0;
        var anyVisible = false;
        for (var i = 0; i < labels.length; i++) {
            var lab = labels[i];
            var raw = lab.getAttribute('data-sousc-rayons') || '';
            var parts = raw.split(',');
            var ids = [];
            for (var j = 0; j < parts.length; j++) {
                var n = parseInt(parts[j].trim(), 10);
                if (n > 0) ids.push(n);
            }
            var ok = cg > 0 && ids.indexOf(cg) !== -1;
            lab.style.display = ok ? '' : 'none';
            var inp = lab.querySelector('input[type="checkbox"]');
            if (inp) {
                inp.disabled = !ok;
                if (!ok) inp.checked = false;
            }
            if (ok) anyVisible = true;
        }
        wrap.hidden = !anyVisible;
        if (reqS) {
            reqS.hidden = !anyVisible;
            reqS.setAttribute('aria-hidden', anyVisible ? 'false' : 'true');
        }
    }
    sel.addEventListener('change', syncSouscats);
    syncSouscats();
})();
</script>
<?php endif; ?>
    <?php
    return;
}

if (!isset($vendeur_subcats_for_form) || !is_array($vendeur_subcats_for_form)) {
    $vendeur_subcats_for_form = [];
    if (function_exists('get_plateforme_sous_categories_for_form')) {
        $vendeur_subcats_for_form = get_plateforme_sous_categories_for_form();
    }
}

$rayons_liste = (function_exists('categories_generales_table_exists') && categories_generales_table_exists())
    ? get_general_categories_ordered() : [];
$afficher_rayon = !empty($rayons_liste);
?>
<?php if ($afficher_rayon): ?>
<div class="fap-field fap-field-cat-generale fap-field-cat-vendeur-only">
    <label for="categorie_generale_id">Catégorie générale <span class="required">*</span></label>
    <select id="categorie_generale_id" name="categorie_generale_id" required>
        <option value=""><?php echo !empty($rayons_liste) ? 'Choisir un rayon' : 'Aucun rayon configuré'; ?></option>
        <?php foreach ($rayons_liste as $rg): ?>
            <?php $rid = (int) ($rg['id'] ?? 0); ?>
            <?php if ($rid <= 0) {
                continue;
            } ?>
        <option value="<?php echo $rid; ?>" <?php echo ($vcat_g === $rid) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars((string) ($rg['nom'] ?? '')); ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<?php if (empty($vendeur_sous_categorie_ids_prefill) && $vcat_s > 0): ?>
    <?php $vendeur_sous_categorie_ids_prefill = [$vcat_s]; ?>
<?php endif; ?>

<?php if (!empty($vendeur_subcats_for_form)): ?>
<div id="fap-souscats-wrap" class="fap-field fap-field-souscats fap-field-cat-vendeur-only"<?php echo $afficher_rayon ? ' hidden' : ''; ?>>
    <fieldset class="fap-fieldset-genres fap-fieldset-souscats">
        <legend>Sous-catégories <span class="required" id="fap-souscats-legend-required" hidden aria-hidden="true">*</span></legend>
        <p class="fap-hint">Cochez une ou plusieurs rubriques pour le rayon choisi. Au moins une case lorsque le rayon comporte des sous-catégories.</p>
        <div class="fap-genres-grid" role="group" aria-label="Sous-catégories du produit" id="fap-souscats-grid-inner">
            <?php foreach ($vendeur_subcats_for_form as $srow):
                $sid = (int) ($srow['id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $ray_sous = function_exists('plateforme_get_rayons_ids_for_categorie') ? plateforme_get_rayons_ids_for_categorie($sid) : [];
                $ray_sous_attr = implode(',', $ray_sous);
                $s_checked = false;
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sous_categorie_ids']) && is_array($_POST['sous_categorie_ids'])) {
                    $s_checked = in_array($sid, array_map('intval', $_POST['sous_categorie_ids']), true);
                } else {
                    $s_checked = in_array($sid, $vendeur_sous_categorie_ids_prefill, true);
                }
                ?>
            <label class="fap-genre-label fap-sousc-label" data-sousc-rayons="<?php echo htmlspecialchars($ray_sous_attr, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="checkbox" name="sous_categorie_ids[]" value="<?php echo $sid; ?>" <?php echo $s_checked ? 'checked' : ''; ?><?php echo $afficher_rayon ? ' disabled' : ''; ?>>
                <span><?php echo htmlspecialchars((string) ($srow['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
</div>
<?php endif; ?>

<?php if ($afficher_rayon && !empty($vendeur_subcats_for_form)): ?>
<script>
(function () {
    var sel = document.getElementById('categorie_generale_id');
    var wrap = document.getElementById('fap-souscats-wrap');
    var reqS = document.getElementById('fap-souscats-legend-required');
    if (!sel || !wrap) return;
    var labels = wrap.querySelectorAll('.fap-sousc-label');
    function syncSouscatsHier() {
        var cg = parseInt(sel.value, 10) || 0;
        var anyVisible = false;
        for (var i = 0; i < labels.length; i++) {
            var lab = labels[i];
            var raw = lab.getAttribute('data-sousc-rayons') || '';
            var parts = raw.split(',');
            var ids = [];
            for (var j = 0; j < parts.length; j++) {
                var n = parseInt(parts[j].trim(), 10);
                if (n > 0) ids.push(n);
            }
            var ok = cg > 0 && ids.indexOf(cg) !== -1;
            lab.style.display = ok ? '' : 'none';
            var inp = lab.querySelector('input[type="checkbox"]');
            if (inp) {
                inp.disabled = !ok;
                if (!ok) inp.checked = false;
            }
            if (ok) anyVisible = true;
        }
        wrap.hidden = !anyVisible;
        if (reqS) {
            reqS.hidden = !anyVisible;
            reqS.setAttribute('aria-hidden', anyVisible ? 'false' : 'true');
        }
    }
    sel.addEventListener('change', syncSouscatsHier);
    syncSouscatsHier();
})();
</script>
<?php endif; ?>
