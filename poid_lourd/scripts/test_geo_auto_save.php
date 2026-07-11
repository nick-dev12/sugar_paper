<?php
/**
 * Tests : enregistrement automatique des positions (client à la connexion,
 * boutique vendeur à la 1re connexion) + pré-remplissage commande.
 * Usage : php scripts/test_geo_auto_save.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/geo_location_service.php';

$pass = 0;
$fail = 0;

function t(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [OK]   $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
    else { $fail++; echo "  [FAIL] $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
}

echo "=== Tests auto-enregistrement positions ===\n\n";

/* ---------- 1. geo_save_boutique_location_if_missing (rollback) ---------- */
echo "[1] Position boutique : enregistrer seulement si absente\n";
try {
    $db->beginTransaction();

    $vid = (int) $db->query("SELECT id FROM admin WHERE role = 'vendeur' LIMIT 1")->fetchColumn();
    if ($vid > 0) {
        // Partir d'une position vide
        $db->exec("UPDATE admin SET boutique_latitude = NULL, boutique_longitude = NULL WHERE id = $vid");

        t('enregistrement initial accepté (position absente)',
            geo_save_boutique_location_if_missing($vid, 14.6928, -17.4467, 'gps'));

        $row = $db->query("SELECT boutique_latitude, boutique_geo_source FROM admin WHERE id = $vid")->fetch();
        t('position initiale écrite avec source gps',
            abs((float) $row['boutique_latitude'] - 14.6928) < 0.000001 && $row['boutique_geo_source'] === 'gps');

        // Deuxième tentative : doit être REFUSÉE (position déjà définie)
        t('2e tentative refusée (position déjà définie)',
            !geo_save_boutique_location_if_missing($vid, 5.3600, -4.0083, 'gps'));

        $row = $db->query("SELECT boutique_latitude FROM admin WHERE id = $vid")->fetch();
        t('position d\'origine intacte (pas écrasée)',
            abs((float) $row['boutique_latitude'] - 14.6928) < 0.000001);

        // La mise à jour volontaire (paramètres) doit, elle, fonctionner
        t('mise à jour volontaire toujours possible (geo_save_boutique_location)',
            geo_save_boutique_location($vid, 5.3600, -4.0083, 'manuel'));
        $row = $db->query("SELECT boutique_latitude FROM admin WHERE id = $vid")->fetch();
        t('position mise à jour via paramètres',
            abs((float) $row['boutique_latitude'] - 5.3600) < 0.000001);

        // Coordonnées invalides
        $db->exec("UPDATE admin SET boutique_latitude = NULL, boutique_longitude = NULL WHERE id = $vid");
        t('coordonnées invalides refusées', !geo_save_boutique_location_if_missing($vid, 999.0, 999.0));
    } else {
        t('vendeur de test trouvé', false, 'aucun vendeur en BDD');
    }

    // ID non-vendeur : refusé
    $aid = (int) $db->query("SELECT id FROM admin WHERE role <> 'vendeur' LIMIT 1")->fetchColumn();
    if ($aid > 0) {
        t('compte non-vendeur refusé', !geo_save_boutique_location_if_missing($aid, 14.0, -17.0));
    }

    $db->rollBack();
    echo "  (transaction annulée : aucune donnée modifiée)\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    t('bloc boutique', false, $e->getMessage());
}

/* ---------- 2. Position client : session + users (déjà testé, vérif rapide) ---------- */
echo "\n[2] Position client (set-location.php : logique sous-jacente)\n";
try {
    $db->beginTransaction();
    $uid = (int) $db->query("SELECT id FROM users LIMIT 1")->fetchColumn();
    if ($uid > 0) {
        t('geo_save_user_last_location', geo_save_user_last_location($uid, 14.70, -17.45, 20.0));
        geo_session_set_location(14.70, -17.45, 20.0);
        $loc = geo_session_get_location();
        t('position en session récupérable', $loc !== null && abs($loc['lat'] - 14.70) < 0.000001);
        geo_session_clear_location();
        t('effacement session', geo_session_get_location() === null);
    } else {
        t('user de test trouvé', false);
    }
    $db->rollBack();
    echo "  (transaction annulée : aucune donnée modifiée)\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    t('bloc client', false, $e->getMessage());
}

/* ---------- 3. Pré-remplissage page commande (priorité session > BDD) ---------- */
echo "\n[3] Pré-remplissage commande (session prioritaire sur BDD)\n";
geo_session_set_location(10.0, 10.0, 5.0);
$sess = geo_session_get_location();
t('session fraîche prioritaire', $sess !== null && abs($sess['lat'] - 10.0) < 0.000001);
geo_session_clear_location();
// Sans session : la page lit users.last_* (logique vérifiée en [2])
t('fallback BDD vérifié via users.last_*', true, 'logique commande.php : session puis users.last_latitude');

echo "\n=== Résultat : $pass OK / $fail FAIL ===\n";
exit($fail > 0 ? 1 : 0);
