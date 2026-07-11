<?php
/**
 * Script temporaire — test envoi annonce + push aux clients
 * Usage : php scripts/test_annonce_push_client.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/models/model_annonces.php';
require_once $root . '/models/model_fcm.php';
require_once $root . '/services/send_annonce_notification.php';
require_once $root . '/services/firebase_push.php';

function out(string $msg): void {
    echo $msg . PHP_EOL;
}

out('=================================================');
out('  TEST ANNONCE + PUSH CLIENT');
out('  ' . date('Y-m-d H:i:s'));
out('=================================================');
out('');

if (!annonces_table_exists()) {
    out('[FAIL] Table platform_annonces absente. Exécutez : php migrations/run_add_platform_annonces.php');
    exit(1);
}
out('[OK]   Tables annonces présentes');

$tokens = get_all_fcm_tokens_clients();
$cibles = count_fcm_clients_cibles();
out('[INFO] Tokens FCM clients (distincts) : ' . count($tokens));
out('[INFO] Comptes clients avec token     : ' . $cibles);

if (empty($tokens)) {
    out('');
    out('[WARN] Aucun token FCM client en base.');
    out('       → Connectez-vous en client sur http://localhost:5000/user/mon-compte.php');
    out('       → Cliquez « Activer les notifications » et acceptez la permission');
    out('       → Relancez ce script');
    out('');
    out('       L\'annonce sera quand même créée en BDD (visible dans user/annonces.php).');
}

// Détail tokens en base
try {
    global $db;
    $stmt = $db->query("
        SELECT ft.id, ft.user_id, u.email, LEFT(ft.token, 24) AS token_prefix, ft.date_creation
        FROM fcm_tokens ft
        LEFT JOIN users u ON u.id = ft.user_id
        WHERE ft.type = 'user' AND ft.user_id IS NOT NULL
        ORDER BY ft.date_creation DESC
        LIMIT 10
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        out('');
        out('--- Derniers tokens user en BDD ---');
        foreach ($rows as $r) {
            out(sprintf(
                '  id=%s user_id=%s email=%s token=%s… date=%s',
                $r['id'] ?? '?',
                $r['user_id'] ?? '?',
                $r['email'] ?? '(sans email)',
                $r['token_prefix'] ?? '',
                $r['date_creation'] ?? ''
            ));
        }
    }
} catch (PDOException $e) {
    out('[WARN] Lecture tokens : ' . $e->getMessage());
}

// Super admin pour auteur (premier compte ou id=1)
$sa_id = 1;
try {
    $sa = $db->query('SELECT id FROM super_admin ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($sa) {
        $sa_id = (int) $sa['id'];
    }
} catch (PDOException $e) {
    // ignore
}

$titre = 'Test annonce client — ' . date('H:i:s');
$message = 'Message de test automatique du système d\'annonces. Si vous voyez cette notification push, l\'envoi FCM fonctionne.';
$annonce_id = annonce_create($titre, $message, 'client', $sa_id, '/user/annonces.php');

if (!$annonce_id) {
    out('[FAIL] Impossible de créer l\'annonce en BDD');
    exit(1);
}
out('');
out('[OK]   Annonce créée en BDD — id=' . $annonce_id);

// Envoi push détaillé
if (!empty($tokens)) {
    $body = mb_strlen($message) > 180 ? mb_substr($message, 0, 177) . '…' : $message;
    $data = [
        'type' => 'annonce',
        'annonce_id' => (string) $annonce_id,
        'audience' => 'client',
        'link' => '/user/annonces.php?id=' . $annonce_id,
        'tag' => 'annonce-' . $annonce_id,
    ];

    out('');
    out('--- Envoi FCM direct ---');
    $push = firebase_send_notification($tokens, $titre, $body, $data);
    annonce_update_push_stats($annonce_id, $cibles, (int) ($push['success'] ?? 0), (int) ($push['failed'] ?? 0));

    out('[INFO] Push succès  : ' . (int) ($push['success'] ?? 0));
    out('[INFO] Push échecs  : ' . (int) ($push['failed'] ?? 0));
    if (!empty($push['errors'])) {
        out('[INFO] Erreurs FCM :');
        foreach (array_slice($push['errors'], 0, 5) as $err) {
            out('       - ' . $err);
        }
    }
} else {
    $push = send_annonce_push_notification($annonce_id);
    out('');
    out('[INFO] ' . ($push['message'] ?? ''));
}

// Vérification enregistrement
$row = annonce_get_by_id($annonce_id);
out('');
out('--- Vérification annonce #' . $annonce_id . ' ---');
if ($row) {
    out('  titre      : ' . ($row['titre'] ?? ''));
    out('  audience   : ' . ($row['audience'] ?? ''));
    out('  cibles     : ' . (int) ($row['nb_destinataires_cibles'] ?? 0));
    out('  push_ok    : ' . (int) ($row['nb_push_envoyes'] ?? 0));
    out('  push_fail  : ' . (int) ($row['nb_push_echecs'] ?? 0));
    out('  date_envoi : ' . ($row['date_envoi'] ?? ''));
} else {
    out('[FAIL] Annonce introuvable après création');
    exit(1);
}

$list = annonces_list_for_client(1, 5);
out('');
out('[INFO] Annonces visibles pour user_id=1 : ' . count($list) . ' (échantillon)');

out('');
if (!empty($tokens) && (int) ($row['nb_push_envoyes'] ?? 0) > 0) {
    out('SUCCÈS — push envoyé à au moins un appareil client.');
    exit(0);
}
if (empty($tokens)) {
    out('PARTIEL — annonce en BDD OK, mais aucun token client pour le push.');
    out('Activez les notifications sur le compte client puis relancez le script.');
    exit(2);
}
out('ÉCHEC PUSH — tokens présents mais envoi FCM a échoué (voir erreurs ci-dessus).');
exit(3);
