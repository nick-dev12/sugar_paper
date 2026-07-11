<?php
/**
 * Annonces plateforme — envoi et historique (super admin)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_annonces.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_annonces.php';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

$csrf = super_admin_csrf_token();
$form_open = !empty($_GET['envoyer']);
$send_result = ['success' => false, 'message' => ''];

$form_prefill = [
    'titre' => '',
    'message' => '',
    'audience' => 'client',
    'lien_url' => '',
];

$renvoyer_id = isset($_GET['renvoyer']) ? (int) $_GET['renvoyer'] : 0;
if ($renvoyer_id > 0) {
    $src = annonce_get_by_id($renvoyer_id);
    if ($src) {
        $form_prefill = [
            'titre' => (string) ($src['titre'] ?? ''),
            'message' => (string) ($src['message'] ?? ''),
            'audience' => (string) ($src['audience'] ?? 'client'),
            'lien_url' => (string) ($src['lien_url'] ?? ''),
        ];
        $form_open = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_annonce'])) {
    $send_result = process_super_admin_send_annonce();
    if (!empty($send_result['success'])) {
        $_SESSION['super_admin_flash_ok'] = $send_result['message'];
        header('Location: index.php');
        exit;
    }
    $form_open = true;
    $form_prefill = [
        'titre' => isset($_POST['titre']) ? trim((string) $_POST['titre']) : '',
        'message' => isset($_POST['message']) ? trim((string) $_POST['message']) : '',
        'audience' => isset($_POST['audience']) ? trim((string) $_POST['audience']) : 'client',
        'lien_url' => isset($_POST['lien_url']) ? trim((string) $_POST['lien_url']) : '',
    ];
}

$annonces = annonces_list_super_admin(150);
$total = count($annonces);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonces — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-comptes.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-annonces.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-comptes-page sa-annonces-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-comptes-shell">
        <header class="sa-users-hero" aria-labelledby="sa-annonces-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-bullhorn" aria-hidden="true"></i> Communication plateforme</p>
                    <h1 class="sa-users-hero__title" id="sa-annonces-title">Annonces</h1>
                    <p class="sa-users-hero__lead">
                        Envoyez des annonces aux clients ou aux vendeurs. Chaque envoi déclenche une notification push et est conservé dans l'historique.
                    </p>
                </div>
                <div class="sa-users-kpis" role="group" aria-label="Indicateurs">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Annonces envoyées</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $total; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($msg_ok !== ''): ?>
            <div class="sa-alert sa-alert--ok" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo $msg_ok; ?></span>
            </div>
        <?php endif; ?>
        <?php if ($msg_err !== ''): ?>
            <div class="sa-alert sa-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <div class="sa-comptes-toolbar">
            <p style="margin:0;color:var(--gris-moyen,#737373);font-size:0.88rem;">
                Historique des annonces diffusées
            </p>
            <button type="button" class="sa-comptes-btn-add" id="saAnnoncesOpenForm" aria-controls="saAnnoncesFormLayer" aria-expanded="false">
                <i class="fas fa-paper-plane" aria-hidden="true"></i> Envoyer une annonce
            </button>
        </div>

        <div class="sa-comptes-table-wrap">
            <table class="sa-comptes-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Titre</th>
                        <th scope="col">Message</th>
                        <th scope="col">Audience</th>
                        <th scope="col">Push</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($annonces)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--gris-moyen,#737373);">Aucune annonce envoyée.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($annonces as $a):
                        $aud = (string) ($a['audience'] ?? 'client');
                    ?>
                    <tr>
                        <td><?php echo !empty($a['date_envoi']) ? date('d/m/Y H:i', strtotime((string) $a['date_envoi'])) : '—'; ?></td>
                        <td><strong><?php echo htmlspecialchars((string) ($a['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                        <td><span class="sa-annonces-msg-preview"><?php echo htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td>
                            <span class="sa-annonces-audience sa-annonces-audience--<?php echo htmlspecialchars($aud, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-<?php echo $aud === 'vendeur' ? 'store' : 'users'; ?>" aria-hidden="true"></i>
                                <?php echo htmlspecialchars(annonce_audience_label($aud), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo (int) ($a['nb_push_envoyes'] ?? 0); ?> / <?php echo (int) ($a['nb_destinataires_cibles'] ?? 0); ?>
                        </td>
                        <td>
                            <button type="button" class="sa-annonces-btn-resend js-annonce-resend"
                                data-titre="<?php echo htmlspecialchars((string) ($a['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-message="<?php echo htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-audience="<?php echo htmlspecialchars($aud, ENT_QUOTES, 'UTF-8'); ?>"
                                data-lien="<?php echo htmlspecialchars((string) ($a['lien_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-redo" aria-hidden="true"></i> Renvoyer
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="sa-comptes-form-layer<?php echo $form_open ? ' is-open' : ''; ?>" id="saAnnoncesFormLayer" role="dialog" aria-modal="true" aria-labelledby="saAnnoncesFormTitle"<?php echo $form_open ? '' : ' hidden'; ?>>
        <div class="sa-comptes-form-panel" style="width:min(100%,520px);">
            <div class="sa-comptes-form-panel__hd">
                <h2 class="sa-comptes-form-panel__title" id="saAnnoncesFormTitle">Envoyer une annonce</h2>
                <button type="button" class="sa-comptes-form-close" id="saAnnoncesCloseForm" aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <?php if (!empty($send_result['message']) && empty($send_result['success'])): ?>
            <div class="sa-alert sa-alert--err" role="alert" style="margin-bottom:0.9rem;">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo $send_result['message']; ?></span>
            </div>
            <?php endif; ?>

            <form method="post" action="index.php" id="saAnnonceSendForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="send_annonce" value="1">

                <div class="sa-comptes-field sa-annonces-field">
                    <label for="annonce_audience">Destinataires</label>
                    <select id="annonce_audience" name="audience" required>
                        <option value="client"<?php echo $form_prefill['audience'] === 'client' ? ' selected' : ''; ?>>Clients (acheteurs)</option>
                        <option value="vendeur"<?php echo $form_prefill['audience'] === 'vendeur' ? ' selected' : ''; ?>>Vendeurs (boutiques)</option>
                    </select>
                </div>
                <div class="sa-comptes-field">
                    <label for="annonce_titre">Titre de l'annonce</label>
                    <input type="text" id="annonce_titre" name="titre" required maxlength="200"
                        value="<?php echo htmlspecialchars($form_prefill['titre'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="sa-comptes-field sa-annonces-field">
                    <label for="annonce_message">Message</label>
                    <textarea id="annonce_message" name="message" required maxlength="5000" placeholder="Contenu de l'annonce…"><?php echo htmlspecialchars($form_prefill['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="sa-comptes-field">
                    <label for="annonce_lien">Lien (optionnel)</label>
                    <input type="text" id="annonce_lien" name="lien_url" maxlength="500"
                        placeholder="/user/annonces.php ou https://…"
                        value="<?php echo htmlspecialchars($form_prefill['lien_url'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="sa-comptes-form-actions">
                    <button type="button" class="sa-comptes-btn-cancel" id="saAnnoncesCancelForm">Annuler</button>
                    <button type="submit" class="sa-comptes-btn-submit">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i> Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var layer = document.getElementById('saAnnoncesFormLayer');
        var openBtn = document.getElementById('saAnnoncesOpenForm');
        var closeBtn = document.getElementById('saAnnoncesCloseForm');
        var cancelBtn = document.getElementById('saAnnoncesCancelForm');
        var titreEl = document.getElementById('annonce_titre');
        var messageEl = document.getElementById('annonce_message');
        var audienceEl = document.getElementById('annonce_audience');
        var lienEl = document.getElementById('annonce_lien');

        function fillForm(data) {
            if (titreEl) titreEl.value = data.titre || '';
            if (messageEl) messageEl.value = data.message || '';
            if (audienceEl) audienceEl.value = data.audience === 'vendeur' ? 'vendeur' : 'client';
            if (lienEl) lienEl.value = data.lien || '';
        }

        function openForm(prefill) {
            if (!layer) return;
            if (prefill) fillForm(prefill);
            layer.classList.add('is-open');
            layer.hidden = false;
            if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            if (titreEl) titreEl.focus();
        }

        function closeForm() {
            if (!layer) return;
            layer.classList.remove('is-open');
            layer.hidden = true;
            if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        if (openBtn) openBtn.addEventListener('click', function () { openForm(null); });
        if (closeBtn) closeBtn.addEventListener('click', closeForm);
        if (cancelBtn) cancelBtn.addEventListener('click', closeForm);
        if (layer) {
            layer.addEventListener('click', function (e) {
                if (e.target === layer) closeForm();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeForm();
        });

        document.querySelectorAll('.js-annonce-resend').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openForm({
                    titre: btn.getAttribute('data-titre') || '',
                    message: btn.getAttribute('data-message') || '',
                    audience: btn.getAttribute('data-audience') || 'client',
                    lien: btn.getAttribute('data-lien') || ''
                });
            });
        });

        <?php if ($form_open): ?>
        openForm({
            titre: <?php echo json_encode($form_prefill['titre'], JSON_UNESCAPED_UNICODE); ?>,
            message: <?php echo json_encode($form_prefill['message'], JSON_UNESCAPED_UNICODE); ?>,
            audience: <?php echo json_encode($form_prefill['audience'], JSON_UNESCAPED_UNICODE); ?>,
            lien: <?php echo json_encode($form_prefill['lien_url'], JSON_UNESCAPED_UNICODE); ?>
        });
        <?php endif; ?>
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
