<?php
/**
 * Diagnostic push pour un email client
 * Usage : php scripts/diag_annonce_push_user.php oyonoeffell@gmail.com
 */
declare(strict_types=1);

$email = $argv[1] ?? 'oyonoeffell@gmail.com';
$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/models/model_fcm.php';
require_once $root . '/services/firebase_push.php';

echo "=== Diagnostic push pour: $email ===" . PHP_EOL;

$stmt = $db->prepare("SELECT id, nom, prenom, email, statut FROM users WHERE email = :e LIMIT 1");
$stmt->execute(['e' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo "[FAIL] Utilisateur introuvable" . PHP_EOL;
    exit(1);
}
$uid = (int) $user['id'];
echo "[OK] user_id=$uid statut=" . ($user['statut'] ?? '') . PHP_EOL;

$tokens = get_fcm_tokens_by_user($uid);
echo "[INFO] Tokens pour ce user: " . count($tokens) . PHP_EOL;
foreach ($tokens as $i => $t) {
    echo "  token[$i]: " . substr($t, 0, 32) . '…' . PHP_EOL;
}

$all = $db->query("SELECT ft.user_id, u.email, LEFT(ft.token,28) t FROM fcm_tokens ft LEFT JOIN users u ON u.id=ft.user_id WHERE ft.type='user'")->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . "--- Tous tokens user en BDD ---" . PHP_EOL;
foreach ($all as $r) {
    echo "  user_id={$r['user_id']} email={$r['email']} token={$r['t']}…" . PHP_EOL;
}

if (empty($tokens)) {
    echo PHP_EOL . "[WARN] Ce compte n'a pas de token — réactivez les notifications sur mon-compte.php" . PHP_EOL;
    exit(2);
}

$title = 'Test diagnostic — ' . date('H:i:s');
$body = 'Si vous voyez cette notification Windows, le push fonctionne pour ' . $email;
$link = 'http://localhost:5000/user/annonces.php';
$data = [
    'type' => 'annonce',
    'annonce_id' => '0',
    'link' => $link,
    'tag' => 'diag-' . time(),
    'title' => $title,
    'body' => $body,
];

echo PHP_EOL . "--- Envoi FCM ---" . PHP_EOL;
$result = firebase_send_notification($tokens, $title, $body, $data);
echo "success: " . (int) ($result['success'] ?? 0) . PHP_EOL;
echo "failed:  " . (int) ($result['failed'] ?? 0) . PHP_EOL;
if (!empty($result['errors'])) {
    foreach ($result['errors'] as $e) {
        echo "error: $e" . PHP_EOL;
    }
}
