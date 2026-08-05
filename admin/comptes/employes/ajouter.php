<?php
require_once __DIR__ . '/../../../includes/session_user.php';
/**
 * Ajout rapide d’une fiche employé (nom, prénom, fonction)
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh', 'informaticien', 'developpeur', 'contable'], true)) {
    header('Location: ../../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../../controllers/controller_employes.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $_SESSION['error_message'] = 'Session expirée. Réessayez.';
        header('Location: ajouter.php');
        exit;
    }
    $result = process_employe_ajout_rh_simple();
    if (!empty($result['success'])) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: index.php');
        exit;
    }
}
$result = isset($result) ? $result : ['success' => false, 'message' => ''];
$err_flash = !$result['success'] && ($result['message'] ?? '') !== '' ? $result['message'] : '';
$p = $_POST;

$sf_sel = isset($p['statut_familial']) ? trim((string) $p['statut_familial']) : '';
if ($sf_sel === '') {
    $sf_sel = 'non_renseigne';
}
$tc_sel = isset($p['type_contrat']) ? trim((string) $p['type_contrat']) : '';
if ($tc_sel === '') {
    $tc_sel = 'non_renseigne';
}
$photo_max_mo = (int) (EMPLOYE_PHOTO_UPLOAD_MAX_BYTES / (1024 * 1024));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un employé — Administration</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-comptes-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employes-rh.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes page-employes-rh page-employes-ajouter">
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="page-comptes-wrap er-page er-page--ajouter">
        <header class="er-ajouter-hero">
            <div class="er-ajouter-hero__text">
                <p class="page-comptes-eyebrow">Ressources humaines</p>
                <h1><i class="fas fa-user-plus" aria-hidden="true"></i> Ajouter un employé</h1>
                <p class="comptes-lead">Créez la fiche de base. Les informations complémentaires (contact, documents, paie) pourront être complétées ensuite.</p>
            </div>
            <a href="index.php" class="er-ajouter-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Retour à la liste</span>
            </a>
        </header>

        <?php if ($err_flash): ?>
            <div class="message error page-comptes-flash" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <?php echo $err_flash; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="ajouter.php" class="er-ajouter-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="creer_employe_rh_simple" value="1">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">

            <div class="er-ajouter-layout">
                <div class="er-ajouter-main">
                    <section class="er-ajouter-panel" aria-labelledby="er-sec-identite">
                        <header class="er-ajouter-panel__head">
                            <span class="er-ajouter-panel__num" aria-hidden="true">1</span>
                            <div>
                                <h2 id="er-sec-identite" class="er-ajouter-panel__title">Identité</h2>
                                <p class="er-ajouter-panel__lead">Informations essentielles de la fiche</p>
                            </div>
                        </header>
                        <div class="er-ajouter-panel__body er-form-grid">
                            <div class="er-field">
                                <label for="nom">Nom <span class="er-req">*</span></label>
                                <input type="text" id="nom" name="nom" required autocomplete="family-name" placeholder="Ex. : Diop"
                                    value="<?php echo htmlspecialchars($p['nom'] ?? ''); ?>">
                            </div>
                            <div class="er-field">
                                <label for="prenom">Prénom <span class="er-req">*</span></label>
                                <input type="text" id="prenom" name="prenom" required autocomplete="given-name" placeholder="Ex. : Aminata"
                                    value="<?php echo htmlspecialchars($p['prenom'] ?? ''); ?>">
                            </div>
                            <div class="er-field er-field--full">
                                <label for="poste">Fonction <span class="er-req">*</span></label>
                                <input type="text" id="poste" name="poste" required placeholder="Ex. : Magasinier, Comptable, Chauffeur…"
                                    value="<?php echo htmlspecialchars($p['poste'] ?? ''); ?>">
                            </div>
                        </div>
                    </section>

                    <section class="er-ajouter-panel" aria-labelledby="er-sec-coord">
                        <header class="er-ajouter-panel__head">
                            <span class="er-ajouter-panel__num" aria-hidden="true">2</span>
                            <div>
                                <h2 id="er-sec-coord" class="er-ajouter-panel__title">Coordonnées &amp; contrat</h2>
                                <p class="er-ajouter-panel__lead">Contact, situation et type d’engagement</p>
                            </div>
                        </header>
                        <div class="er-ajouter-panel__body er-form-grid">
                            <div class="er-field">
                                <label for="telephone">Téléphone</label>
                                <input type="text" id="telephone" name="telephone" autocomplete="tel" inputmode="tel" placeholder="+221 …"
                                    value="<?php echo htmlspecialchars($p['telephone'] ?? ''); ?>">
                            </div>
                            <div class="er-field">
                                <label for="date_embauche">Date d’embauche <span class="er-opt">(optionnel)</span></label>
                                <input type="date" id="date_embauche" name="date_embauche"
                                    value="<?php echo !empty($p['date_embauche']) ? htmlspecialchars(substr((string) $p['date_embauche'], 0, 10)) : ''; ?>">
                            </div>
                            <div class="er-field">
                                <label for="statut_familial">Statut familial</label>
                                <select id="statut_familial" name="statut_familial">
                                    <?php foreach (employe_statuts_familiaux_choices() as $k => $label): ?>
                                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($sf_sel === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="er-field">
                                <label for="type_contrat">Type de contrat</label>
                                <select id="type_contrat" name="type_contrat">
                                    <?php foreach (employe_types_contrat_choices() as $k => $label): ?>
                                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($tc_sel === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="er-ajouter-panel" aria-labelledby="er-sec-paie">
                        <header class="er-ajouter-panel__head">
                            <span class="er-ajouter-panel__num" aria-hidden="true">3</span>
                            <div>
                                <h2 id="er-sec-paie" class="er-ajouter-panel__title">Rémunération &amp; fiscalité</h2>
                                <p class="er-ajouter-panel__lead">Montants utilisés pour préremplir le bulletin de paie</p>
                            </div>
                        </header>
                        <div class="er-ajouter-panel__body er-form-grid">
                            <div class="er-field">
                                <label for="salaire_base">Salaire brut (FCFA) <span class="er-opt">(optionnel)</span></label>
                                <input type="text" id="salaire_base" name="salaire_base" inputmode="decimal" autocomplete="off"
                                    placeholder="Base mensuelle"
                                    value="<?php echo htmlspecialchars($p['salaire_base'] ?? ''); ?>">
                            </div>
                            <div class="er-field">
                                <label for="montant_irpp_mensuel">IRPP mensuel (FCFA) <span class="er-opt">(optionnel)</span></label>
                                <input type="text" id="montant_irpp_mensuel" name="montant_irpp_mensuel" inputmode="decimal" autocomplete="off"
                                    placeholder="Impôt sur le revenu"
                                    value="<?php echo htmlspecialchars($p['montant_irpp_mensuel'] ?? ''); ?>">
                            </div>
                            <div class="er-field">
                                <label for="montant_trimf_mensuel">TRIMF mensuel (FCFA) <span class="er-opt">(optionnel)</span></label>
                                <input type="text" id="montant_trimf_mensuel" name="montant_trimf_mensuel" inputmode="decimal" autocomplete="off"
                                    placeholder="Taxe REP. / TRIMF"
                                    value="<?php echo htmlspecialchars($p['montant_trimf_mensuel'] ?? ''); ?>">
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="er-ajouter-aside">
                    <section class="er-ajouter-panel er-ajouter-panel--aside" aria-labelledby="er-sec-pieces">
                        <header class="er-ajouter-panel__head">
                            <span class="er-ajouter-panel__num" aria-hidden="true">4</span>
                            <div>
                                <h2 id="er-sec-pieces" class="er-ajouter-panel__title">Pièces jointes</h2>
                                <p class="er-ajouter-panel__lead">Photo et contrat (optionnels)</p>
                            </div>
                        </header>
                        <div class="er-ajouter-panel__body er-ajouter-uploads">
                            <div class="er-upload-block er-photo-field">
                                <label for="photo_employe" class="er-upload-block__label">
                                    <span class="er-upload-block__ic" aria-hidden="true"><i class="fas fa-camera"></i></span>
                                    <span class="er-upload-block__text">
                                        <strong>Photo de l’employé</strong>
                                        <span>JPG, PNG, WEBP ou GIF — max <?php echo $photo_max_mo; ?> Mo</span>
                                    </span>
                                </label>
                                <input type="file" id="photo_employe" name="photo_employe" accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="er-photo-preview-wrap" id="photoPreviewWrap" hidden>
                                    <img src="" alt="Aperçu photo employé" class="er-photo-preview" id="photoPreviewImg" width="160" height="160">
                                </div>
                            </div>
                            <div class="er-upload-block">
                                <label for="contrat_pdf" class="er-upload-block__label">
                                    <span class="er-upload-block__ic" aria-hidden="true"><i class="fas fa-file-pdf"></i></span>
                                    <span class="er-upload-block__text">
                                        <strong>Contrat (PDF)</strong>
                                        <span>Fichier PDF uniquement — max 8 Mo</span>
                                    </span>
                                </label>
                                <input type="file" id="contrat_pdf" name="contrat_pdf" accept="application/pdf">
                            </div>
                        </div>
                    </section>

                    <div class="er-ajouter-sticky-actions">
                        <p class="er-ajouter-hint"><span class="er-req">*</span> Champs obligatoires</p>
                        <div class="er-form-actions er-form-actions--ajouter">
                            <a href="index.php" class="er-btn er-btn--ghost">Annuler</a>
                            <button type="submit" class="er-btn er-btn--primary">
                                <i class="fas fa-check" aria-hidden="true"></i> Enregistrer
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </form>
    </div>
    <script>
    (function () {
        var input = document.getElementById('photo_employe');
        var wrap = document.getElementById('photoPreviewWrap');
        var img = document.getElementById('photoPreviewImg');
        if (!input || !wrap || !img) return;
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) {
                wrap.hidden = true;
                img.removeAttribute('src');
                return;
            }
            var r = new FileReader();
            r.onload = function (e) {
                img.src = e.target.result || '';
                wrap.hidden = false;
            };
            r.readAsDataURL(input.files[0]);
        });
    })();
    </script>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
