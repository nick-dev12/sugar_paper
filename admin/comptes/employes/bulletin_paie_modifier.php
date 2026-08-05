<?php
require_once __DIR__ . '/../../../includes/session_user.php';
/**
 * Modification d’un bulletin de paie existant
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

require_once __DIR__ . '/../../../models/model_employes.php';
require_once __DIR__ . '/../../../models/model_bulletin_paie.php';
require_once __DIR__ . '/../../../includes/asset_version.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf'];

$bid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bid <= 0) {
    header('Location: index.php');
    exit;
}

$brow = bp_get_bulletin_by_id($bid);
if (!$brow) {
    header('Location: index.php');
    exit;
}

$employe_id = (int) ($brow['employe_id'] ?? 0);
$emp = get_employe_by_id($employe_id);
if (!$emp) {
    header('Location: index.php');
    exit;
}

$snap = json_decode((string) ($brow['snapshot_json'] ?? ''), true);
if (!is_array($snap)) {
    $snap = [];
}

$params = bp_tables_parametres_disponibles() ? bp_get_parametres_effectifs() : null;
$rub_bp = $params ? $params['rubriques'] : bp_rubriques_defaut();
$bp_lg = bp_labels_gains();
$bp_lr = bp_labels_retenues();
$bp_taux = $params ? $params['retenues_taux'] : [];
$bp_pct_codes = bp_retenues_codes_taux_brut();

$gains_snap = isset($snap['gains']) && is_array($snap['gains']) ? $snap['gains'] : [];
$retenues_snap = isset($snap['retenues']) && is_array($snap['retenues']) ? $snap['retenues'] : [];
$travail_snap = isset($snap['travail']) && is_array($snap['travail']) ? $snap['travail'] : [];
$mentions_snap = isset($snap['mentions']) && is_array($snap['mentions']) ? $snap['mentions'] : [];
$periode_snap = isset($snap['periode']) && is_array($snap['periode']) ? $snap['periode'] : [];

$form = [];
if (!empty($_SESSION['bp_edit_form']) && is_array($_SESSION['bp_edit_form'])) {
    $form = $_SESSION['bp_edit_form'];
    unset($_SESSION['bp_edit_form']);
}

$flash_err = '';
if (!empty($_SESSION['bp_flash_err'])) {
    $flash_err = (string) $_SESSION['bp_flash_err'];
    unset($_SESSION['bp_flash_err']);
}

$val = function ($key, $default = '') use ($form) {
    if (array_key_exists($key, $form)) {
        return (string) $form[$key];
    }
    return (string) $default;
};

$mois_def = $val('mois_paie', (string) ($periode_snap['mois_paie'] ?? $brow['mois_paie'] ?? date('Y-m')));
$date_paiement_def = $val('date_paiement', (string) ($periode_snap['date_paiement'] ?? $brow['date_paiement'] ?? ''));
$salaire_def = $val('salaire_base', number_format((float) ($brow['salaire_base'] ?? 0), 2, '.', ''));
$mode_paiement_def = $val('mode_paiement', (string) ($mentions_snap['mode_paiement'] ?? ''));

$gain_codes_bp = ['heures_sup', 'prime_performance', 'prime_transport', 'assurance_maladie', 'sursalaire', 'indemnite_transport', 'indemnite_logement', 'indemnite_fonction'];
$ret_manual = ['accident_travail', 'pret_salaire', 'autres_retenues'];

$nom_complet = trim(($emp['prenom'] ?? '') . ' ' . ($emp['nom'] ?? ''));
$bp_irpp_fiche = max(0.0, round((float) ($emp['montant_irpp_mensuel'] ?? 0), 2));
$bp_trimf_fiche = max(0.0, round((float) ($emp['montant_trimf_mensuel'] ?? 0), 2));
$bp_forfait_hs = $params ? (float) ($params['forfait_heures_sup_mensuel'] ?? 0) : 0.0;
$prime_transport_cfg = $params ? (float) ($params['prime_transport_mensuelle'] ?? 0) : 0.0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier bulletin — <?php echo htmlspecialchars($nom_complet); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-comptes-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employes-rh.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes page-employes-rh page-employes-bp-edit">
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="page-comptes-wrap er-page er-page--ajouter">
        <header class="er-ajouter-hero">
            <div class="er-ajouter-hero__text">
                <p class="page-comptes-eyebrow">Bulletin de paie</p>
                <h1><i class="fas fa-pen-to-square" aria-hidden="true"></i> Modifier le bulletin</h1>
                <p class="comptes-lead">
                    <?php echo htmlspecialchars($nom_complet); ?>
                    — mois <?php echo htmlspecialchars($mois_def); ?>
                </p>
            </div>
            <a href="details.php?id=<?php echo (int) $employe_id; ?>&amp;tab=bp" class="er-ajouter-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Retour fiche</span>
            </a>
        </header>

        <?php if ($flash_err !== ''): ?>
            <div class="message error page-comptes-flash" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <?php echo htmlspecialchars($flash_err); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="bulletin_paie_enregistrer.php" class="er-bp-form er-bp-form--edit">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="employe_id" value="<?php echo (int) $employe_id; ?>">
            <input type="hidden" name="bulletin_id" value="<?php echo (int) $bid; ?>">
            <input type="hidden" name="modifier_bulletin_paie" value="1">

            <section class="er-ajouter-panel">
                <header class="er-ajouter-panel__head">
                    <span class="er-ajouter-panel__num" aria-hidden="true">1</span>
                    <div>
                        <h2 class="er-ajouter-panel__title">Période &amp; salaire</h2>
                        <p class="er-ajouter-panel__lead">Mois de paie et date de règlement</p>
                    </div>
                </header>
                <div class="er-ajouter-panel__body er-bp-form__grid">
                    <div class="er-bp-field">
                        <label for="bp_mois_paie">Mois de paie <span class="req">*</span></label>
                        <input type="month" id="bp_mois_paie" name="mois_paie" required value="<?php echo htmlspecialchars($mois_def); ?>">
                    </div>
                    <div class="er-bp-field">
                        <label for="bp_date_paiement">Date de paiement <span class="req">*</span></label>
                        <input type="date" id="bp_date_paiement" name="date_paiement" required value="<?php echo htmlspecialchars($date_paiement_def); ?>">
                    </div>
                    <div class="er-bp-field er-bp-field--full">
                        <label for="bp_salaire_base">Salaire de base (FCFA) <span class="req">*</span></label>
                        <input type="text" id="bp_salaire_base" name="salaire_base" required inputmode="decimal"
                            value="<?php echo htmlspecialchars($salaire_def); ?>">
                    </div>
                </div>
            </section>

            <?php
            $has_gain = false;
            foreach ($gain_codes_bp as $gc) {
                if (!empty($rub_bp['gains'][$gc])) {
                    $has_gain = true;
                    break;
                }
            }
            if ($has_gain):
            ?>
            <section class="er-ajouter-panel">
                <header class="er-ajouter-panel__head">
                    <span class="er-ajouter-panel__num" aria-hidden="true">2</span>
                    <div>
                        <h2 class="er-ajouter-panel__title">Gains complémentaires</h2>
                        <p class="er-ajouter-panel__lead">Montants en FCFA</p>
                    </div>
                </header>
                <div class="er-ajouter-panel__body er-bp-form__grid">
                    <?php foreach ($gain_codes_bp as $gc):
                        if (empty($rub_bp['gains'][$gc])) {
                            continue;
                        }
                        $snap_m = bp_snapshot_ligne_montant($gains_snap, $gc);
                        if ($gc === 'sursalaire' && $snap_m <= 0) {
                            $snap_m = $bp_forfait_hs;
                        }
                        if ($gc === 'prime_transport' && $snap_m <= 0) {
                            $snap_m = $prime_transport_cfg;
                        }
                        $gval = $val('g_' . $gc, number_format($snap_m, 2, '.', ''));
                        $fid = 'bp_g_' . $gc;
                        ?>
                    <div class="er-bp-field">
                        <label for="<?php echo htmlspecialchars($fid); ?>"><?php echo htmlspecialchars($bp_lg[$gc] ?? $gc); ?></label>
                        <?php if ($gc === 'prime_transport' || $gc === 'sursalaire'): ?>
                        <input type="text" id="<?php echo htmlspecialchars($fid); ?>" name="g_<?php echo htmlspecialchars($gc); ?>"
                            inputmode="decimal" value="<?php echo htmlspecialchars($gval); ?>" readonly>
                        <span class="er-bp-hint"><?php echo $gc === 'sursalaire' ? 'Forfait HS des paramètres bulletin.' : 'Prime transport (paramètres / déductions mois).'; ?></span>
                        <?php else: ?>
                        <input type="text" id="<?php echo htmlspecialchars($fid); ?>" name="g_<?php echo htmlspecialchars($gc); ?>"
                            inputmode="decimal" value="<?php echo htmlspecialchars($gval); ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php
            $has_ret_manual = false;
            foreach ($ret_manual as $rc) {
                if (!empty($rub_bp['retenues'][$rc])) {
                    $has_ret_manual = true;
                    break;
                }
            }
            ?>
            <section class="er-ajouter-panel">
                <header class="er-ajouter-panel__head">
                    <span class="er-ajouter-panel__num" aria-hidden="true">3</span>
                    <div>
                        <h2 class="er-ajouter-panel__title">Retenues</h2>
                        <p class="er-ajouter-panel__lead">IRPP / TRIMF / taux repris automatiquement ; saisissez les retenues manuelles</p>
                    </div>
                </header>
                <div class="er-ajouter-panel__body er-bp-form__grid">
                    <?php if (!empty($rub_bp['retenues']['irpp'])): ?>
                    <div class="er-bp-field">
                        <span class="er-bp-field-label">IRPP</span>
                        <p class="er-bp-pct-display"><strong><?php echo htmlspecialchars(number_format($bp_irpp_fiche, 0, ',', ' ')); ?> FCFA</strong></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($rub_bp['retenues']['trimf'])): ?>
                    <div class="er-bp-field">
                        <span class="er-bp-field-label">TRIMF</span>
                        <p class="er-bp-pct-display"><strong><?php echo htmlspecialchars(number_format($bp_trimf_fiche, 0, ',', ' ')); ?> FCFA</strong></p>
                    </div>
                    <?php endif; ?>
                    <?php foreach ($bp_pct_codes as $rc):
                        if (empty($rub_bp['retenues'][$rc])) {
                            continue;
                        }
                        $tp = (float) ($bp_taux[$rc] ?? 0);
                        ?>
                    <div class="er-bp-field">
                        <span class="er-bp-field-label"><?php echo htmlspecialchars($bp_lr[$rc] ?? $rc); ?></span>
                        <p class="er-bp-pct-display"><strong><?php echo htmlspecialchars(number_format($tp, 2, ',', ' ')); ?> %</strong> du brut</p>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($ret_manual as $rc):
                        if (empty($rub_bp['retenues'][$rc])) {
                            continue;
                        }
                        $rval = $val('r_' . $rc, number_format(bp_snapshot_ligne_montant($retenues_snap, $rc), 2, '.', ''));
                        $fid = 'bp_r_' . $rc;
                        ?>
                    <div class="er-bp-field">
                        <label for="<?php echo htmlspecialchars($fid); ?>"><?php echo htmlspecialchars($bp_lr[$rc] ?? $rc); ?> (FCFA)</label>
                        <input type="text" id="<?php echo htmlspecialchars($fid); ?>" name="r_<?php echo htmlspecialchars($rc); ?>"
                            inputmode="decimal" value="<?php echo htmlspecialchars($rval); ?>">
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$has_ret_manual && empty($rub_bp['retenues']['irpp']) && empty($rub_bp['retenues']['trimf'])): ?>
                    <p class="er-bp-hint">Aucune retenue active dans les paramètres bulletin.</p>
                    <?php endif; ?>
                </div>
            </section>

            <?php
            $tr = $rub_bp['travail'] ?? [];
            if (!empty($tr['heures_travaillees']) || !empty($tr['heures_sup']) || !empty($rub_bp['mentions']['mode_paiement'])):
            ?>
            <section class="er-ajouter-panel">
                <header class="er-ajouter-panel__head">
                    <span class="er-ajouter-panel__num" aria-hidden="true">4</span>
                    <div>
                        <h2 class="er-ajouter-panel__title">Temps &amp; paiement</h2>
                        <p class="er-ajouter-panel__lead">Mentions complémentaires du bulletin</p>
                    </div>
                </header>
                <div class="er-ajouter-panel__body er-bp-form__grid">
                    <?php if (!empty($tr['heures_travaillees'])): ?>
                    <div class="er-bp-field">
                        <label for="bp_t_heures_travaillees">Heures travaillées</label>
                        <input type="text" id="bp_t_heures_travaillees" name="t_heures_travaillees" inputmode="decimal"
                            value="<?php echo htmlspecialchars($val('t_heures_travaillees', (string) ($travail_snap['heures_travaillees'] ?? ''))); ?>">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($tr['heures_sup'])): ?>
                    <div class="er-bp-field">
                        <label for="bp_t_heures_sup_nombre">Heures sup. (nombre)</label>
                        <input type="text" id="bp_t_heures_sup_nombre" name="t_heures_sup_nombre" inputmode="decimal"
                            value="<?php echo htmlspecialchars($val('t_heures_sup_nombre', (string) ($travail_snap['heures_sup_nombre'] ?? ''))); ?>">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($rub_bp['mentions']['mode_paiement'])): ?>
                    <div class="er-bp-field er-bp-field--full">
                        <label for="bp_mode_paiement">Mode de paiement</label>
                        <select id="bp_mode_paiement" name="mode_paiement">
                            <?php
                            $modes = ['', 'Virement bancaire', 'Espèces', 'Chèque', 'Orange Money', 'Wave', 'Autre'];
                            foreach ($modes as $mopt):
                            ?>
                            <option value="<?php echo htmlspecialchars($mopt); ?>" <?php echo $mode_paiement_def === $mopt ? 'selected' : ''; ?>>
                                <?php echo $mopt === '' ? '—' : htmlspecialchars($mopt); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <div class="er-form-actions er-form-actions--ajouter" style="margin-top:1rem;">
                <a href="bulletin_paie_voir.php?id=<?php echo (int) $bid; ?>" class="er-btn er-btn--ghost">Annuler</a>
                <button type="submit" class="er-btn er-btn--primary">
                    <i class="fas fa-save" aria-hidden="true"></i> Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
