<?php
/**
 * Tests du géocodeur Nominatim (nécessite Internet).
 * Usage : php scripts/test_geo_geocoder.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/geo_geocoder.php';

$pass = 0;
$fail = 0;
$skip = 0;

function t(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [OK]   $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
    else { $fail++; echo "  [FAIL] $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
}

echo "=== Tests géocodeur Nominatim ===\n\n";

echo "[1] Validation hors-ligne\n";
t('adresse vide -> null', geo_geocode_address('') === null);
t('reverse coords invalides -> null', geo_reverse_geocode(95.0, 200.0) === null);

echo "\n[2] Géocodage en ligne (Marché Colobane, Dakar)\n";
$r = geo_geocode_address('Marché Colobane, Dakar', 'SN');
if ($r === null) {
    $skip++;
    echo "  [SKIP] Pas de réponse Nominatim (Internet indisponible ou quota) — non bloquant.\n";
} else {
    t('coordonnées retournées', geo_coords_valid($r['lat'], $r['lng']),
        sprintf('%.5f, %.5f', $r['lat'], $r['lng']));
    // Colobane est à Dakar : ~14.68 / -17.44 (tolérance large)
    $d = geo_distance_km($r['lat'], $r['lng'], 14.6928, -17.4467);
    t('résultat situé dans Dakar (< 15 km du centre)', $d < 15, sprintf('%.1f km du centre', $d));
    t('display_name non vide', $r['display_name'] !== '', mb_substr($r['display_name'], 0, 60) . '…');
}

echo "\n[3] Géocodage inverse (centre de Dakar)\n";
$addr = geo_reverse_geocode(14.6928, -17.4467);
if ($addr === null) {
    $skip++;
    echo "  [SKIP] Pas de réponse Nominatim — non bloquant.\n";
} else {
    t('adresse retournée', $addr !== '', mb_substr($addr, 0, 70) . '…');
    t('mention Dakar ou Sénégal', stripos($addr, 'dakar') !== false || stripos($addr, 'sénégal') !== false || stripos($addr, 'senegal') !== false);
}

echo "\n[4] Géocodage boutique (rollback à la fin)\n";
if (empty($db) || !($db instanceof PDO)) {
    echo "  [SKIP] BDD indisponible.\n";
} else {
    try {
        $db->beginTransaction();
        $vid = (int) $db->query("SELECT id FROM admin WHERE role = 'vendeur' LIMIT 1")->fetchColumn();
        if ($vid > 0) {
            // Donner une adresse géocodable connue à la boutique de test
            $db->prepare("UPDATE admin SET boutique_adresse = 'Marché Sandaga, Dakar' WHERE id = :id")
               ->execute(['id' => $vid]);
            $ok = geo_geocode_boutique($vid);
            if (!$ok) {
                $skip++;
                echo "  [SKIP] geo_geocode_boutique sans réponse réseau — non bloquant.\n";
            } else {
                $row = $db->query("SELECT boutique_latitude, boutique_longitude, boutique_geo_source FROM admin WHERE id = $vid")->fetch();
                t('coordonnées boutique enregistrées',
                    $row['boutique_latitude'] !== null && $row['boutique_geo_source'] === 'adresse',
                    sprintf('%.5f, %.5f', (float) $row['boutique_latitude'], (float) $row['boutique_longitude']));
            }
        } else {
            echo "  [SKIP] Aucun vendeur en BDD.\n";
        }
        $db->rollBack();
        echo "  (transaction annulée : aucune donnée modifiée)\n";
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        t('bloc géocodage boutique', false, $e->getMessage());
    }
}

echo "\n=== Résultat : $pass OK / $fail FAIL / $skip SKIP ===\n";
exit($fail > 0 ? 1 : 0);
