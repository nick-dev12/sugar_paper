<?php
require_once __DIR__ . '/../services/notify_queue.php';

$r = notify_queue_enqueue('nouvelle_commande', [
    'numero_commande' => 'TEST-SPEED',
    'montant_total' => 1000,
    'nombre_articles' => 1,
    'telephone_livraison' => '',
    'adresse_livraison' => '',
    'produits' => [],
]);
echo 'enqueue: ';
var_export($r);
echo PHP_EOL;

sleep(4);
$pending = glob(NOTIFY_QUEUE_PENDING_DIR . '/*.json') ?: [];
echo 'pending left after spawn: ' . count($pending) . PHP_EOL;

// Si le spawn Windows a échoué, traiter manuellement
if (count($pending) > 0) {
    echo "Worker spawn may have failed — running process_notify_queue.php\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/process_notify_queue.php'));
}
