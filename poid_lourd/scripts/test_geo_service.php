<?php
/**
 * Tests du service de localisation exacte.
 * Usage : php scripts/test_geo_service.php
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
    if ($ok) {
        $pass++;
        echo "  [OK]   $label" . ($detail !== '' ? " — $detail" : '') . "\n";
    } else {
        $fail++;
        echo "  [FAIL] $label" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

echo "=== Tests service de localisation exacte ===\n\n";

/* ---------- 1. Colonnes BDD ---------- */
echo "[1] Colonnes BDD\n";
t('connexion BDD', !empty($db) && $db instanceof PDO);
t('commandes prêtes (delivery_latitude)', geo_commandes_ready());
t('boutiques prêtes (boutique_latitude)', geo_boutiques_ready());
t('users prêts (last_latitude)', geo_users_ready());

/* ---------- 2. Parsing / validation ---------- */
echo "\n[2] Parsing et validation\n";
t('parse "14.6928" -> 14.6928', geo_parse_coord('14.6928') === 14.6928);
t('parse virgule "14,6928"', geo_parse_coord('14,6928') === 14.6928);
t('parse invalide "abc" -> null', geo_parse_coord('abc') === null);
t('parse vide -> null', geo_parse_coord('') === null);
t('coords Dakar valides', geo_coords_valid(14.6928, -17.4467));
t('lat 91 invalide', !geo_coords_valid(91.0, 0.5));
t('lng 181 invalide', !geo_coords_valid(0.5, 181.0));
t('(0,0) rejeté', !geo_coords_valid(0.0, 0.0));
t('null rejeté', !geo_coords_valid(null, 10.0));
t('précision -5 -> null', geo_parse_precision('-5') === null);
t('précision "12.5" -> 12.5', geo_parse_precision('12.5') === 12.5);

/* ---------- 3. Distance Haversine PHP ---------- */
echo "\n[3] Distance Haversine (PHP)\n";
// Dakar (14.6928, -17.4467) -> Thiès (14.7910, -16.9359) : ~56 km
$d = geo_distance_km(14.6928, -17.4467, 14.7910, -16.9359);
t('Dakar -> Thiès ~56 km', $d > 50 && $d < 62, sprintf('%.2f km', $d));
// Dakar -> Abidjan (5.3600, -4.0083) : ~1700 km
$d2 = geo_distance_km(14.6928, -17.4467, 5.3600, -4.0083);
t('Dakar -> Abidjan ~1710 km', $d2 > 1650 && $d2 < 1800, sprintf('%.0f km', $d2));
// Distance nulle
t('même point = 0 km', geo_distance_km(14.5, -17.0, 14.5, -17.0) < 0.001);
t('format 0.35 km -> "350 m"', geo_format_distance(0.35) === '350 m');
t('format 5.27 km -> "5,3 km"', geo_format_distance(5.27) === '5,3 km');
t('format 56.6 km -> "57 km"', geo_format_distance(56.6) === '57 km');

/* ---------- 4. Distance Haversine SQL (cohérence avec PHP) ---------- */
echo "\n[4] Distance Haversine (SQL MySQL)\n";
try {
    $expr = geo_sql_distance_expr('t.lat', 't.lng');
    $stmt = $db->prepare("
        SELECT $expr AS d
        FROM (SELECT 14.7910 AS lat, -16.9359 AS lng) t
    ");
    $stmt->execute(['geo_lat' => 14.6928, 'geo_lat2' => 14.6928, 'geo_lng' => -17.4467]);
    $d_sql = (float) $stmt->fetchColumn();
    t('SQL Dakar -> Thiès cohérent avec PHP', abs($d_sql - $d) < 0.01, sprintf('SQL=%.3f / PHP=%.3f', $d_sql, $d));
} catch (PDOException $e) {
    t('SQL Haversine', false, $e->getMessage());
}

/* ---------- 5. Sauvegarde BDD (transaction annulée) ---------- */
echo "\n[5] Sauvegarde positions (rollback à la fin)\n";
try {
    $db->beginTransaction();

    // Vendeur de test
    $vid = (int) $db->query("SELECT id FROM admin WHERE role = 'vendeur' LIMIT 1")->fetchColumn();
    if ($vid > 0) {
        t('geo_save_boutique_location', geo_save_boutique_location($vid, 14.7167, -17.4677, 'manuel'));
        $row = $db->query("SELECT boutique_latitude, boutique_longitude, boutique_geo_source FROM admin WHERE id = $vid")->fetch();
        t('boutique lat/lng relus', abs((float) $row['boutique_latitude'] - 14.7167) < 0.000001
            && abs((float) $row['boutique_longitude'] - (-17.4677)) < 0.000001
            && $row['boutique_geo_source'] === 'manuel');
        t('effacement position boutique', geo_save_boutique_location($vid, null, null));
        $row = $db->query("SELECT boutique_latitude FROM admin WHERE id = $vid")->fetch();
        t('position boutique effacée', $row['boutique_latitude'] === null);
    } else {
        t('vendeur de test trouvé', false, 'aucun vendeur en BDD');
    }

    // Commande de test
    $cid = (int) $db->query("SELECT id FROM commandes LIMIT 1")->fetchColumn();
    if ($cid > 0) {
        t('geo_save_commande_location', geo_save_commande_location($cid, 14.6937, -17.4441, 8.5, 'gps'));
        $row = $db->query("SELECT delivery_latitude, delivery_geo_precision, delivery_geo_source FROM commandes WHERE id = $cid")->fetch();
        t('commande lat/précision/source relues', abs((float) $row['delivery_latitude'] - 14.6937) < 0.000001
            && abs((float) $row['delivery_geo_precision'] - 8.5) < 0.01
            && $row['delivery_geo_source'] === 'gps');
        t('coords invalides refusées', !geo_save_commande_location($cid, 95.0, 200.0));
    } else {
        t('commande de test trouvée', false, 'aucune commande en BDD');
    }

    // User de test
    $uid = (int) $db->query("SELECT id FROM users LIMIT 1")->fetchColumn();
    if ($uid > 0) {
        t('geo_save_user_last_location', geo_save_user_last_location($uid, 14.70, -17.45, 15.0));
        $row = $db->query("SELECT last_latitude, last_geo_date FROM users WHERE id = $uid")->fetch();
        t('user position relue', abs((float) $row['last_latitude'] - 14.70) < 0.000001 && $row['last_geo_date'] !== null);
    } else {
        t('user de test trouvé', false, 'aucun user en BDD');
    }

    $db->rollBack();
    echo "  (transaction annulée : aucune donnée modifiée)\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    t('bloc sauvegarde', false, $e->getMessage());
}

/* ---------- 6. Recherche proximité (données temporaires, rollback) ---------- */
echo "\n[6] Recherche boutiques / produits proches (rollback à la fin)\n";
try {
    $db->beginTransaction();

    // Positionner deux vendeurs : un à Dakar centre, un à Thiès
    $vendeurs = $db->query("SELECT id FROM admin WHERE role = 'vendeur' AND statut = 'actif' LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    if (count($vendeurs) >= 2) {
        geo_save_boutique_location((int) $vendeurs[0], 14.6928, -17.4467, 'manuel'); // Dakar
        geo_save_boutique_location((int) $vendeurs[1], 14.7910, -16.9359, 'manuel'); // Thiès

        // Depuis Dakar, rayon 10 km : seule la boutique Dakar doit sortir
        $res10 = geo_boutiques_proches(14.6928, -17.4467, 10.0, 20);
        $ids10 = array_map(fn($r) => (int) $r['id'], $res10);
        t('rayon 10 km : boutique Dakar trouvée', in_array((int) $vendeurs[0], $ids10, true));
        t('rayon 10 km : boutique Thiès exclue', !in_array((int) $vendeurs[1], $ids10, true));

        // Rayon 100 km : les deux, triées par distance
        $res100 = geo_boutiques_proches(14.6928, -17.4467, 100.0, 20);
        $ids100 = array_map(fn($r) => (int) $r['id'], $res100);
        t('rayon 100 km : les deux boutiques', in_array((int) $vendeurs[0], $ids100, true) && in_array((int) $vendeurs[1], $ids100, true));
        if (count($res100) >= 2) {
            t('tri par distance croissante', (float) $res100[0]['distance_km'] <= (float) $res100[1]['distance_km'],
                sprintf('%.1f km puis %.1f km', (float) $res100[0]['distance_km'], (float) $res100[1]['distance_km']));
        }

        // Produits proches
        $prods = geo_produits_proches(14.6928, -17.4467, 100.0, 10);
        t('geo_produits_proches s\'exécute', is_array($prods), count($prods) . ' produit(s) avec distance');
        if (!empty($prods)) {
            t('produit a une distance_km', isset($prods[0]['distance_km']));
        }
    } else {
        t('deux vendeurs actifs requis', false, 'pas assez de vendeurs en BDD');
    }

    $db->rollBack();
    echo "  (transaction annulée : aucune donnée modifiée)\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    t('bloc proximité', false, $e->getMessage());
}

/* ---------- 7. Session ---------- */
echo "\n[7] Position visiteur en session\n";
geo_session_set_location(14.6928, -17.4467, 10.0);
$loc = geo_session_get_location();
t('session set/get', $loc !== null && abs($loc['lat'] - 14.6928) < 0.000001);
$_SESSION['geo_location']['time'] = time() - GEO_SESSION_MAX_AGE - 10;
t('session expirée -> null', geo_session_get_location() === null);
geo_session_clear_location();

/* ---------- 8. Liens cartes ---------- */
echo "\n[8] Liens cartes\n";
t('lien OSM', str_contains(geo_osm_link(14.6928, -17.4467), 'openstreetmap.org/?mlat=14.6928&mlon=-17.4467'));
t('lien Google Maps', str_contains(geo_gmaps_link(14.6928, -17.4467), 'google.com/maps?q=14.6928%2C-17.4467'));

echo "\n=== Résultat : $pass OK / $fail FAIL ===\n";
exit($fail > 0 ? 1 : 0);
