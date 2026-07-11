<?php
/**
 * Active / désactive l'accès boutique (compte vendeur)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';
require_once dirname(__DIR__, 2) . '/includes/marketplace_countries.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$tok = $_POST['csrf_token'] ?? '';
if (!super_admin_csrf_valid($tok)) {
    $_SESSION['super_admin_flash_err'] = 'Jeton de sécurité invalide.';
    header('Location: index.php');
    exit;
}

$vid = isset($_POST['vendeur_id']) ? (int) $_POST['vendeur_id'] : 0;
$statut = isset($_POST['nouveau_statut']) ? (string) $_POST['nouveau_statut'] : '';

if ($vid <= 0 || !in_array($statut, ['actif', 'inactif'], true)) {
    $_SESSION['super_admin_flash_err'] = 'Données invalides.';
    header('Location: index.php');
    exit;
}

if (super_admin_set_vendeur_statut($vid, $statut)) {
    $detail = super_admin_get_boutique_stats($vid);
    $label = $detail ? ($detail['boutique_nom'] ?: $detail['nom']) : '#' . $vid;
    super_admin_log_action(
        (int) $_SESSION['super_admin_id'],
        $statut === 'actif' ? 'boutique_activée' : 'boutique_désactivée',
        'boutique',
        $vid,
        'Boutique : ' . $label
    );
    $_SESSION['super_admin_flash_ok'] = $statut === 'actif'
        ? 'La boutique a été réactivée.'
        : 'La boutique a été désactivée (connexion vendeur bloquée).';
} else {
    $_SESSION['super_admin_flash_err'] = 'Impossible de modifier le statut.';
}

$retQ = [
    'q' => isset($_POST['return_q']) ? trim((string) $_POST['return_q']) : '',
    'pays' => isset($_POST['return_pays']) ? strtoupper(trim((string) $_POST['return_pays'])) : '',
    'statut' => isset($_POST['return_statut']) ? trim((string) $_POST['return_statut']) : '',
    'cert' => isset($_POST['return_cert']) ? trim((string) $_POST['return_cert']) : '',
    'per' => isset($_POST['return_per']) ? (int) $_POST['return_per'] : 15,
    'p' => isset($_POST['return_p']) ? (int) $_POST['return_p'] : 1,
];

/**
 * Même logique que sb_boutiques_query_params dans index.php (redirect)
 */
function _sb_boutique_redirect_query(array $overrides) {
    $countries_nav = marketplace_countries_nav_list();
    $default_pays = $countries_nav ? (string) array_key_first($countries_nav) : 'SN';
    $m = array_merge([
        'q' => '',
        'pays' => $default_pays,
        'statut' => 'actif',
        'cert' => 'non_certifie',
        'per' => 15,
        'p' => 1,
    ], $overrides);
    if (!marketplace_country_is_valid($m['pays'])) {
        $m['pays'] = $default_pays;
    }
    if ($m['statut'] !== 'actif' && $m['statut'] !== 'inactif') {
        $m['statut'] = 'actif';
    }
    if ($m['per'] < 5) {
        $m['per'] = 5;
    }
    if ($m['per'] > 100) {
        $m['per'] = 100;
    }
    if ($m['p'] < 1) {
        $m['p'] = 1;
    }
    $q = [];
    if ($m['q'] !== '') {
        $q['q'] = $m['q'];
    }
    $q['pays'] = $m['pays'];
    if ($m['statut'] === 'actif' || $m['statut'] === 'inactif') {
        $q['statut'] = $m['statut'];
    }
    if ($m['cert'] === 'certifie' || $m['cert'] === 'non_certifie') {
        $q['cert'] = $m['cert'];
    }
    if ((int) $m['per'] !== 15) {
        $q['per'] = (int) $m['per'];
    }
    if ((int) $m['p'] > 1) {
        $q['p'] = (int) $m['p'];
    }
    return $q;
}

$qs = _sb_boutique_redirect_query($retQ);
header('Location: index.php' . ($qs ? '?' . http_build_query($qs) : ''));
exit;
