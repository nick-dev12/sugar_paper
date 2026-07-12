<?php
require_once __DIR__ . '/../includes/geo_geocode_suggest.php';

$queries = ['Almadies', 'Plateau Dakar', 'Parcelles Assainies', 'Mermoz'];

foreach ($queries as $q) {
    $start = microtime(true);
    $suggestions = geo_geocode_suggest($q, 'sn', 6);
    $ms = (int) round((microtime(true) - $start) * 1000);
    echo $q . ' => ' . count($suggestions) . ' (' . $ms . " ms)\n";
    if (!empty($suggestions[0])) {
        echo '  ' . ($suggestions[0]['label'] ?? '') . "\n";
    }
    $err = geo_geocode_suggest_last_error();
    if ($err) {
        echo '  err: ' . $err . "\n";
    }
}
