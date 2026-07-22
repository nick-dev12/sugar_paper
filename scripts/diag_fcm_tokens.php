<?php
/**
 * Diagnostic tokens FCM admin / utilisateurs
 * Usage: php scripts/diag_fcm_tokens.php
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_fcm.php';

echo "=== COMPTES ADMIN ===\n";
$admins = $db->query('SELECT id, email, role, statut FROM admin ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($admins as $a) {
    echo $a['id'] . ' | ' . $a['email'] . ' | role=' . $a['role'] . ' | ' . $a['statut'] . "\n";
}

echo "\n=== TOKENS FCM type=admin ===\n";
$stmt = $db->query("
    SELECT ft.id, ft.admin_id, LEFT(ft.token, 28) AS tok, a.email, a.role, a.statut
    FROM fcm_tokens ft
    LEFT JOIN admin a ON a.id = ft.admin_id
    WHERE ft.type = 'admin'
    ORDER BY ft.id DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $t) {
    $eligible = in_array($t['role'] ?? '', ['admin', 'utilisateur'], true) && ($t['statut'] ?? '') === 'actif';
    echo 'tok#' . $t['id']
        . ' admin_id=' . ($t['admin_id'] ?? 'NULL')
        . ' email=' . ($t['email'] ?? '-')
        . ' role=' . ($t['role'] ?? '-')
        . ' statut=' . ($t['statut'] ?? '-')
        . ' eligible=' . ($eligible ? 'OUI' : 'NON')
        . ' token=' . $t['tok'] . "...\n";
}

$eligibleTokens = get_all_fcm_tokens_admin();
echo "\nTokens éligibles (admin+utilisateur actifs): " . count($eligibleTokens) . "\n";

$userTok = (int) $db->query("SELECT COUNT(*) FROM fcm_tokens WHERE type='user' AND token IS NOT NULL AND token!=''")->fetchColumn();
echo "Tokens clients (type=user): {$userTok}\n";
