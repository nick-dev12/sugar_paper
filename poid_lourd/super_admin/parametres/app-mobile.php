<?php
/**
 * Mise à jour obligatoire — apps mobiles Android / iOS.
 */
require_once __DIR__ . '/../includes/require_login.php';

if (ob_get_level() === 0) {
    ob_start();
}
require_once dirname(__DIR__, 2) . '/includes/flash_toast.php';
require_once dirname(__DIR__, 2) . '/includes/app_mobile_version.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $flash_err = 'Jeton de sécurité invalide.';
    } elseif (!isset($_POST['save_app_mobile'])) {
        $flash_err = 'Action non reconnue.';
    } elseif (app_mobile_version_save($_POST)) {
        super_admin_log_action($sa_id, 'app_mobile_version_modifie', 'app_mobile_version', 0, 'config');
        http_redirect_safe('/super_admin/parametres/app-mobile.php?ok=1');
    } else {
        $flash_err = 'Enregistrement impossible. Vérifiez les champs obligatoires.';
    }
}

if (isset($_GET['ok'])) {
    $flash_ok = 'Configuration enregistrée.';
}

$cfg = app_mobile_version_load();
$csrf = super_admin_csrf_token();
$config_path = app_mobile_version_config_path();
$config_writable = is_writable($config_path) || (!is_file($config_path) && is_writable(dirname($config_path)));

$force_on = !empty($cfg['force_update']);
$android_build = (int) ($cfg['android']['min_build'] ?? 0);
$ios_build = (int) ($cfg['ios']['min_build'] ?? 0);
$android_version = (string) ($cfg['android']['min_version'] ?? '');
$ios_version = (string) ($cfg['ios']['min_version'] ?? '');
$popup_title = (string) ($cfg['title'] ?? '');
$popup_message = (string) ($cfg['message'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App mobile — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-app-mobile.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-param-hub-page sa-cat-page sa-am-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell sa-cat-shell">
        <a class="sa-cat-back" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>

        <header class="sa-param-hero" aria-labelledby="sa-app-mobile-title">
            <div class="sa-param-hero__grid">
                <div>
                    <nav class="sa-param-breadcrumb" aria-label="Fil d'Ariane">
                        <ol>
                            <li><a href="../dashboard.php">Tableau de bord</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li><a href="index.php">Paramètres</a></li>
                            <li class="sa-param-breadcrumb__sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li aria-current="page">App mobile</li>
                        </ol>
                    </nav>
                    <p class="sa-param-hero__eyebrow">
                        <i class="fas fa-mobile-screen-button" aria-hidden="true"></i> Applications natives
                    </p>
                    <h1 class="sa-param-hero__title" id="sa-app-mobile-title">
                        Mise à jour app mobile
                        <span class="sa-param-hero__badge">Android &amp; iOS</span>
                    </h1>
                    <p class="sa-param-hero__lead">
                        Contrôlez la version minimale requise. Les utilisateurs avec une version obsolète verront un
                        <strong>écran bloquant élégant</strong> les invitant à mettre à jour via le store.
                    </p>
                </div>
                <div class="sa-param-hero__stamp" aria-hidden="true">
                    <div class="sa-param-hero__stamp-box">
                        <i class="fas fa-cloud-arrow-down"></i>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($flash_ok !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--ok" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$config_writable): ?>
            <div class="sa-cat-alert sa-cat-alert--err" role="alert">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Le fichier de configuration n'est pas accessible en écriture :
                    <code><?php echo htmlspecialchars($config_path, ENT_QUOTES, 'UTF-8'); ?></code></span>
            </div>
        <?php endif; ?>

        <div class="sa-am-status-grid" aria-label="État actuel">
            <article class="sa-am-status-card">
                <div class="sa-am-status-card__icon sa-am-status-card__icon--force" aria-hidden="true">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="sa-am-status-card__body">
                    <p class="sa-am-status-card__label">Blocage</p>
                    <p class="sa-am-status-card__value"><?php echo $force_on ? 'Activé' : 'Désactivé'; ?></p>
                    <span class="sa-am-status-pill <?php echo $force_on ? 'sa-am-status-pill--on' : 'sa-am-status-pill--off'; ?>">
                        <i class="fas fa-circle" style="font-size:0.45rem"></i>
                        <?php echo $force_on ? 'Popup obligatoire' : 'Aucun blocage'; ?>
                    </span>
                </div>
            </article>
            <article class="sa-am-status-card">
                <div class="sa-am-status-card__icon sa-am-status-card__icon--android" aria-hidden="true">
                    <i class="fab fa-android"></i>
                </div>
                <div class="sa-am-status-card__body">
                    <p class="sa-am-status-card__label">Android</p>
                    <p class="sa-am-status-card__value">Build <?php echo $android_build; ?></p>
                    <p class="sa-am-status-card__sub">v<?php echo htmlspecialchars($android_version, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </article>
            <article class="sa-am-status-card">
                <div class="sa-am-status-card__icon sa-am-status-card__icon--ios" aria-hidden="true">
                    <i class="fab fa-apple"></i>
                </div>
                <div class="sa-am-status-card__body">
                    <p class="sa-am-status-card__label">iOS</p>
                    <p class="sa-am-status-card__value">Build <?php echo $ios_build; ?></p>
                    <p class="sa-am-status-card__sub">v<?php echo htmlspecialchars($ios_version, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </article>
        </div>

        <div class="sa-am-layout">
            <div class="sa-am-form-col">
                <section class="sa-cat-panel sa-am-form-panel" aria-labelledby="sa-app-mobile-form-title">
                    <div class="sa-cat-panel__head">
                        <div class="sa-cat-panel__head-icon" aria-hidden="true">
                            <i class="fas fa-sliders"></i>
                        </div>
                        <div class="sa-cat-panel__head-text">
                            <h2 id="sa-app-mobile-form-title">Configuration</h2>
                            <p>Définissez les builds minimums et le contenu du popup affiché dans l'app.</p>
                        </div>
                    </div>
                    <div class="sa-cat-panel__body">
                        <form method="post" class="sa-cat-form" action="app-mobile.php" id="saAmForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="save_app_mobile" value="1">

                            <div class="sa-am-toggle-card">
                                <div class="sa-am-toggle-card__text">
                                    <h3>Mise à jour obligatoire</h3>
                                    <p>Si activé, les utilisateurs dont le build est inférieur au minimum verront un écran bloquant jusqu'à la mise à jour.</p>
                                </div>
                                <label class="sa-am-switch" aria-label="Activer la mise à jour obligatoire">
                                    <input type="checkbox" name="force_update" value="1" id="force_update" <?php echo $force_on ? 'checked' : ''; ?>>
                                    <span class="sa-am-switch__track"></span>
                                    <span class="sa-am-switch__thumb" aria-hidden="true"></span>
                                </label>
                            </div>

                            <div class="sa-am-platform-grid">
                                <div class="sa-am-platform-card sa-am-platform-card--android">
                                    <div class="sa-am-platform-card__head">
                                        <i class="fab fa-android" aria-hidden="true"></i>
                                        <span>Google Play</span>
                                    </div>
                                    <div class="sa-am-platform-card__body">
                                        <div class="sa-cat-field">
                                            <label for="android_min_build">Build minimum (versionCode)</label>
                                            <input type="number" min="0" step="1" id="android_min_build" name="android_min_build"
                                                value="<?php echo $android_build; ?>" required data-preview-build="android">
                                            <p class="sa-am-field-hint">Numéro après le <code>+</code> dans pubspec.yaml</p>
                                        </div>
                                        <div class="sa-cat-field">
                                            <label for="android_min_version">Version affichée</label>
                                            <input type="text" id="android_min_version" name="android_min_version"
                                                value="<?php echo htmlspecialchars($android_version, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="sa-am-platform-card sa-am-platform-card--ios">
                                    <div class="sa-am-platform-card__head">
                                        <i class="fab fa-apple" aria-hidden="true"></i>
                                        <span>App Store</span>
                                    </div>
                                    <div class="sa-am-platform-card__body">
                                        <div class="sa-cat-field">
                                            <label for="ios_min_build">Build minimum (CFBundleVersion)</label>
                                            <input type="number" min="0" step="1" id="ios_min_build" name="ios_min_build"
                                                value="<?php echo $ios_build; ?>" required data-preview-build="ios">
                                        </div>
                                        <div class="sa-cat-field">
                                            <label for="ios_min_version">Version affichée</label>
                                            <input type="text" id="ios_min_version" name="ios_min_version"
                                                value="<?php echo htmlspecialchars($ios_version, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="sa-am-section-title">
                                <i class="fas fa-link" aria-hidden="true"></i> Liens des stores
                            </h3>
                            <div class="sa-cat-field">
                                <label for="store_android">Google Play</label>
                                <input type="url" id="store_android" name="store_android" required
                                    value="<?php echo htmlspecialchars((string) ($cfg['store_android'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="sa-cat-field">
                                <label for="store_ios">App Store</label>
                                <input type="url" id="store_ios" name="store_ios" required
                                    value="<?php echo htmlspecialchars((string) ($cfg['store_ios'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <h3 class="sa-am-section-title">
                                <i class="fas fa-message" aria-hidden="true"></i> Contenu du popup
                            </h3>
                            <div class="sa-cat-field">
                                <label for="title">Titre</label>
                                <input type="text" id="title" name="title" required maxlength="120"
                                    value="<?php echo htmlspecialchars($popup_title, ENT_QUOTES, 'UTF-8'); ?>" data-preview="title">
                            </div>
                            <div class="sa-cat-field">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" rows="4" required maxlength="500" data-preview="message"><?php echo htmlspecialchars($popup_message, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>

                            <div class="sa-am-form-actions">
                                <button type="submit" class="sa-am-save-btn" <?php echo $config_writable ? '' : 'disabled'; ?>>
                                    <i class="fas fa-save" aria-hidden="true"></i> Enregistrer la configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="sa-cat-panel" aria-labelledby="sa-app-mobile-api-title">
                    <div class="sa-cat-panel__head">
                        <div class="sa-cat-panel__head-icon" aria-hidden="true">
                            <i class="fas fa-code"></i>
                        </div>
                        <div class="sa-cat-panel__head-text">
                            <h2 id="sa-app-mobile-api-title">API &amp; procédure</h2>
                            <p>Endpoint consulté par l'application au démarrage.</p>
                        </div>
                    </div>
                    <div class="sa-cat-panel__body">
                        <div class="sa-am-api-card">
                            <strong>Endpoint public</strong>
                            <code>/api/app_version.php?platform=android&amp;build=12</code>
                        </div>
                        <ol class="sa-am-steps">
                            <li>
                                <span class="sa-am-steps__num">1</span>
                                <span>Publiez la nouvelle version sur Google Play et l'App Store.</span>
                            </li>
                            <li>
                                <span class="sa-am-steps__num">2</span>
                                <span>Notez le numéro de build (ex. <code>1.3.3+13</code> → build <strong>13</strong>).</span>
                            </li>
                            <li>
                                <span class="sa-am-steps__num">3</span>
                                <span>Mettez à jour les builds minimums ci-dessus et activez le blocage.</span>
                            </li>
                        </ol>
                    </div>
                </section>
            </div>

            <aside class="sa-am-preview-col" aria-labelledby="sa-am-preview-title">
                <section class="sa-cat-panel sa-am-preview-sticky">
                    <div class="sa-cat-panel__head">
                        <div class="sa-cat-panel__head-icon sa-cat-panel--sub" aria-hidden="true" style="background:linear-gradient(145deg,var(--orange-fonce),var(--orange))">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="sa-cat-panel__head-text">
                            <h2 id="sa-am-preview-title">Aperçu mobile</h2>
                            <p>Simulation de l'écran bloquant dans l'application.</p>
                        </div>
                    </div>
                    <div class="sa-cat-panel__body">
                        <div class="sa-am-phone" role="img" aria-label="Aperçu du popup de mise à jour">
                            <div class="sa-am-phone__notch" aria-hidden="true"></div>
                            <div class="sa-am-phone__screen">
                                <div class="sa-am-phone__hero">
                                    <div class="sa-am-phone__logo" aria-hidden="true">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <span class="sa-am-phone__badge">
                                        <i class="fas fa-cloud-arrow-down"></i>
                                        Mise à jour store
                                    </span>
                                </div>
                                <div class="sa-am-phone__body">
                                    <h3 class="sa-am-phone__title" id="saAmPreviewTitle"><?php echo htmlspecialchars($popup_title, ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="sa-am-phone__message" id="saAmPreviewMessage"><?php echo htmlspecialchars($popup_message, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="sa-am-phone__versions" id="saAmPreviewVersions">
                                        Installée <strong>build 11</strong>
                                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                        Requise <strong>build <?php echo max($android_build, $ios_build); ?></strong>
                                    </div>
                                    <button type="button" class="sa-am-phone__cta" tabindex="-1" aria-hidden="true">Mettre à jour maintenant</button>
                                </div>
                            </div>
                        </div>
                        <p class="sa-am-preview-note">L'aperçu se met à jour en temps réel pendant la saisie.</p>
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        var titleEl = document.getElementById('title');
        var messageEl = document.getElementById('message');
        var previewTitle = document.getElementById('saAmPreviewTitle');
        var previewMessage = document.getElementById('saAmPreviewMessage');
        var previewVersions = document.getElementById('saAmPreviewVersions');
        var androidBuild = document.getElementById('android_min_build');
        var iosBuild = document.getElementById('ios_min_build');

        function updatePreview() {
            if (previewTitle && titleEl) {
                previewTitle.textContent = titleEl.value.trim() || 'Mise à jour requise';
            }
            if (previewMessage && messageEl) {
                previewMessage.textContent = messageEl.value.trim() || 'Message du popup…';
            }
            if (previewVersions && androidBuild && iosBuild) {
                var maxBuild = Math.max(
                    parseInt(androidBuild.value, 10) || 0,
                    parseInt(iosBuild.value, 10) || 0
                );
                previewVersions.innerHTML =
                    'Installée <strong>build 11</strong> ' +
                    '<i class="fas fa-arrow-right" aria-hidden="true"></i> ' +
                    'Requise <strong>build ' + maxBuild + '</strong>';
            }
        }

        [titleEl, messageEl, androidBuild, iosBuild].forEach(function (el) {
            if (el) {
                el.addEventListener('input', updatePreview);
            }
        });
    })();
    </script>
</body>

</html>
