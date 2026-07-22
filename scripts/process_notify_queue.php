<?php
/**
 * Worker CLI : traite les jobs de notification post-commande
 * Usage : php scripts/process_notify_queue.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once __DIR__ . '/../services/notify_queue.php';
require_once __DIR__ . '/../services/email_queue.php';

if (!notify_queue_ensure_dirs()) {
    echo json_encode(['processed' => 0, 'error' => 'dirs']);
    exit(1);
}

$files = glob(NOTIFY_QUEUE_PENDING_DIR . '/*.json') ?: [];
usort($files, static function ($a, $b) {
    return filemtime($a) <=> filemtime($b);
});

$processed = 0;
$errors = [];

foreach ($files as $file) {
    $raw = @file_get_contents($file);
    @unlink($file);
    if ($raw === false || $raw === '') {
        continue;
    }

    $job = json_decode($raw, true);
    if (!is_array($job) || empty($job['type'])) {
        continue;
    }

    $processed++;
    $type = (string) $job['type'];
    $p = is_array($job['payload'] ?? null) ? $job['payload'] : [];

    try {
        switch ($type) {
            case 'nouvelle_commande':
                require_once __DIR__ . '/../services/send_new_commande_to_admin.php';
                send_new_commande_to_admin(
                    (string) ($p['numero_commande'] ?? ''),
                    (float) ($p['montant_total'] ?? 0),
                    (int) ($p['nombre_articles'] ?? 0),
                    (string) ($p['telephone_livraison'] ?? ''),
                    (string) ($p['adresse_livraison'] ?? ''),
                    is_array($p['produits'] ?? null) ? $p['produits'] : []
                );
                break;

            case 'confirmation_client':
                require_once __DIR__ . '/../services/send_commande_confirmation_to_client.php';
                send_new_commande_confirmation_to_client(
                    (int) ($p['user_id'] ?? 0),
                    (string) ($p['numero_commande'] ?? ''),
                    (float) ($p['montant_total'] ?? 0),
                    (string) ($p['user_email'] ?? '')
                );
                break;

            case 'nouvelle_cp':
                require_once __DIR__ . '/../services/send_commande_personnalisee_notification.php';
                send_new_commande_personnalisee_to_admin(
                    (int) ($p['commande_perso_id'] ?? 0),
                    (string) ($p['nom'] ?? ''),
                    (string) ($p['telephone'] ?? ''),
                    (string) ($p['description'] ?? ''),
                    (string) ($p['type_produit'] ?? ''),
                    (string) ($p['quantite'] ?? '')
                );
                break;

            case 'confirmation_cp':
                require_once __DIR__ . '/../services/send_commande_personnalisee_notification.php';
                send_commande_personnalisee_confirmation_to_client(
                    (int) ($p['user_id'] ?? 0),
                    (int) ($p['commande_perso_id'] ?? 0),
                    (string) ($p['user_email'] ?? '')
                );
                break;

            default:
                $errors[] = 'type inconnu: ' . $type;
        }
    } catch (Throwable $e) {
        $errors[] = $type . ': ' . $e->getMessage();
        error_log('[process_notify_queue] ' . $e->getMessage());
    }
}

// Traite aussi la file email (SMTP hors requête HTTP)
$email_stats = email_queue_process(30);

echo json_encode([
    'processed' => $processed,
    'errors' => $errors,
    'email' => $email_stats,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
