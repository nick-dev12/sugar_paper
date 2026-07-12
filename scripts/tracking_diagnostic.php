<?php
/**
 * Diagnostic suivi GPS — à lancer sur le VPS :
 *   php scripts/tracking_diagnostic.php
 *   php scripts/tracking_diagnostic.php 9
 */
declare(strict_types=1);

$bl_id = isset($argv[1]) ? (int) $argv[1] : 9;

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/tracking_config.php';
require_once __DIR__ . '/../models/model_livreur_tracking.php';

echo "=== Diagnostic suivi GPS (BL id={$bl_id}) ===\n\n";

$secret = tracking_internal_secret();
echo '1. config/tracking.php : ' . ($secret !== '' && $secret !== 'REMPLACEZ_PAR_UNE_CLE_SECRETE_LONGUE_ET_ALEATOIRE' ? 'OK' : 'MANQUANT') . "\n";
echo '2. tracking_realtime_available : ' . (tracking_realtime_available() ? 'oui' : 'non') . "\n";
echo '3. livreur_bl_livraison_columns_ok : ' . (livreur_bl_livraison_columns_ok() ? 'oui' : 'non') . "\n";
echo '4. tracking_watch_tokens.bl_id : ' . (function_exists('livreur_watch_token_has_bl_column') && livreur_watch_token_has_bl_column() ? 'oui' : 'non') . "\n";

$facture = livreur_get_facture_tracking($bl_id);
if (!$facture) {
    echo "\nERREUR : bon de livraison #{$bl_id} introuvable.\n";
    exit(1);
}

echo "\n--- Facture BL #{$bl_id} ---\n";
echo '  livreur_id      : ' . ($facture['livreur_id'] ?? 'null') . "\n";
echo '  tracking_active : ' . ($facture['tracking_active'] ?? '0') . "\n";
echo '  geo             : ' . ($facture['delivery_latitude'] ?? '?') . ', ' . ($facture['delivery_longitude'] ?? '?') . "\n";

$livreur_id = (int) ($facture['livreur_id'] ?? 0);
if ($livreur_id > 0) {
    $last = livreur_get_last_position($livreur_id, null, $bl_id);
    echo '  last_position   : ';
    if ($last) {
        echo $last['latitude'] . ', ' . $last['longitude'] . ' @ ' . ($last['recorded_at'] ?? '') . "\n";
    } else {
        echo "aucune\n";
    }
}

echo "\n--- Test token watch (comme Node.js) ---\n";
$watch = livreur_create_watch_token(null, 'admin', 1, null, $bl_id);
if (!$watch || empty($watch['token'])) {
    echo "ERREUR : impossible de créer un token watch.\n";
    exit(1);
}

$row = livreur_get_watch_token_row($watch['token'], null, $bl_id);
if (!$row) {
    echo "ERREUR : livreur_get_watch_token_row a échoué (cause probable de watch_unauthorized).\n";
    exit(1);
}

echo "  token lookup    : OK\n";
echo '  tracking_active : ' . ($row['tracking_active'] ?? '?') . "\n";
echo '  livreur_id      : ' . ($row['livreur_id'] ?? '?') . "\n";

echo "\n--- Test HTTP interne (comme Node → PHP) ---\n";
$payload = json_encode([
    'token' => $watch['token'],
    'bl_id' => $bl_id,
    'internal_secret' => $secret,
]);

$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nHost: sugar-paper.com\r\nX-Tracking-Secret: {$secret}\r\n",
        'content' => $payload,
        'ignore_errors' => true,
        'timeout' => 10,
    ],
]);

$ping_url = 'http://127.0.0.1:8081/api/tracking/ping.php';
$verify_url = 'http://127.0.0.1:8081/api/tracking/verify-watch.php';

$ping_raw = @file_get_contents($ping_url, false, $ctx);
echo '  ping.php        : ' . trim((string) $ping_raw) . "\n";

$verify_raw = @file_get_contents($verify_url, false, $ctx);
$verify = json_decode((string) $verify_raw, true);
if (is_array($verify) && !empty($verify['valid'])) {
    echo "  verify-watch    : OK (can_emit=" . (!empty($verify['can_emit_position']) ? '1' : '0') . ")\n";
} else {
    echo '  verify-watch    : ECHEC → ' . trim((string) $verify_raw) . "\n";
    exit(1);
}

echo "\n--- Test enregistrement position (simulation livreur-web) ---\n";
$test_lat = 14.692;
$test_lng = -17.446;
$save = livreur_save_web_position($livreur_id, $test_lat, $test_lng, null, $bl_id, 10);
if (empty($save['ok'])) {
    echo '  INSERT test      : ECHEC → ' . ($save['error'] ?? 'inconnu') . "\n";
} else {
    echo "  INSERT test      : OK ({$test_lat}, {$test_lng})\n";
    $last2 = livreur_get_last_position($livreur_id, null, $bl_id);
    echo '  last_position    : ' . ($last2 ? $last2['latitude'] . ', ' . $last2['longitude'] : 'aucune') . "\n";
}

echo "\n--- Positions récentes en BDD ---\n";
try {
    $stmt = $db->prepare("
        SELECT latitude, longitude, recorded_at, bl_id
        FROM livreur_positions
        WHERE livreur_id = :lid
        ORDER BY recorded_at DESC
        LIMIT 5
    ");
    $stmt->execute(['lid' => $livreur_id > 0 ? $livreur_id : 1]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$rows) {
        echo "  (aucune ligne — le téléphone n'a pas encore envoyé de GPS)\n";
    } else {
        foreach ($rows as $r) {
            echo '  ' . ($r['recorded_at'] ?? '') . ' → ' . $r['latitude'] . ', ' . $r['longitude']
                . ' (bl_id=' . ($r['bl_id'] ?? 'null') . ")\n";
        }
    }
} catch (PDOException $e) {
    echo '  Erreur lecture positions : ' . $e->getMessage() . "\n";
}

echo "\n=== Tout est OK côté PHP. Si l'observateur ne voit rien :\n";
echo "   - pm2 restart sugar-tracking\n";
echo "   - Ctrl+F5 sur suivi.php?bl_id={$bl_id}&regarder=1\n";
echo "   - pm2 logs sugar-tracking (plus de watch_unauthorized)\n";
