<?php
/**
 * Migration : session_start() → session_start_persistent() sur pages publiques racine.
 * Usage : php scripts/fix_public_sessions.php
 */

$root = dirname(__DIR__);
$files = [
    'choix-inscription.php',
    'contact.php',
    'promo.php',
    'nouveautes.php',
    'boutiques-proches.php',
    'commande-personnalisee.php',
    'commerçant.php',
    'conditions-utilisation.php',
    'politique-confidentialite.php',
    'politique-suppression-compte.php',
    'mot-de-passe-oublie.php',
    'stock-info.php',
    'auth-google-choose-type.php',
];

foreach ($files as $rel) {
    $file = $root . '/' . $rel;
    if (!is_file($file)) {
        continue;
    }
    $content = file_get_contents($file);
    if ($content === false || $content === '') {
        fwrite(STDERR, "SKIP empty: $rel\n");
        continue;
    }
    $original = $content;

    if (strpos($content, 'session_user.php') === false && strpos($content, 'session_start') !== false) {
        $content = preg_replace(
            '/^<\?php\r?\n/',
            "<?php\nrequire_once __DIR__ . '/includes/session_user.php';\n",
            $content,
            1
        );
    }

    $content = str_replace('session_start();', 'session_start_persistent();', $content);

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: $rel\n";
    }
}

echo "Done.\n";
