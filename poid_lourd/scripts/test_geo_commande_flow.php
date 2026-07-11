<?php
/**
 * Test d'intégration : commande complète avec position GPS du client.
 * Crée un user + panier de test, simule le POST du formulaire de commande,
 * vérifie que la position est attachée à la commande, puis nettoie tout.
 *
 * Usage : php scripts/test_geo_commande_flow.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

$pass = 0;
$fail = 0;

function t(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [OK]   $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
    else { $fail++; echo "  [FAIL] $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
}

echo "=== Test intégration : commande avec position GPS ===\n\n";

/* ---------- Préparation des données de test ---------- */
$test_user_id = 0;
$test_panier_id = 0;
$created_commande_ids = [];

try {
    // Produit actif d'un vendeur actif, avec stock
    $prod = $db->query("
        SELECT p.id, p.admin_id, p.prix
        FROM produits p
        INNER JOIN admin a ON a.id = p.admin_id
        WHERE p.statut = 'actif' AND p.stock > 0 AND a.role = 'vendeur' AND a.statut = 'actif'
        LIMIT 1
    ")->fetch();

    if (!$prod) {
        echo "ABANDON : aucun produit actif avec stock trouvé.\n";
        exit(1);
    }
    echo "Produit de test : #{$prod['id']} (vendeur #{$prod['admin_id']})\n";

    // Utilisateur de test jetable
    $stmt = $db->prepare("
        INSERT INTO users (nom, prenom, email, telephone, password, date_creation, statut, accepte_conditions)
        VALUES ('TestGeo', 'Script', :email, :tel, :pwd, NOW(), 'actif', 1)
    ");
    $suffix = bin2hex(random_bytes(4));
    $stmt->execute([
        'email' => "test.geo.$suffix@test.local",
        'tel' => '+2210000' . rand(100000, 999999),
        'pwd' => password_hash('test', PASSWORD_BCRYPT),
    ]);
    $test_user_id = (int) $db->lastInsertId();
    t('utilisateur de test créé', $test_user_id > 0, "#$test_user_id");

    // Ligne panier
    $stmt = $db->prepare("
        INSERT INTO panier (user_id, vendeur_id, produit_id, quantite, prix_unitaire, date_ajout)
        VALUES (:uid, :vid, :pid, 1, :prix, NOW())
    ");
    $stmt->execute([
        'uid' => $test_user_id,
        'vid' => (int) $prod['admin_id'],
        'pid' => (int) $prod['id'],
        'prix' => (float) $prod['prix'],
    ]);
    $test_panier_id = (int) $db->lastInsertId();
    t('panier de test créé', $test_panier_id > 0);

    /* ---------- Simulation du POST de commande.php ---------- */
    $_SESSION = [];
    $_SESSION['user_id'] = $test_user_id;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'action' => 'create_commande',
        'telephone_livraison' => '+221770000001',
        'adresse_livraison' => 'Adresse test géolocalisation',
        // Champs cachés remplis par js/geo-location.js en conditions réelles
        'geo_lat' => '14.69370000',
        'geo_lng' => '-17.44410000',
        'geo_precision' => '12.5',
        'geo_source' => 'gps',
    ];

    require_once $root . '/controllers/controller_commandes.php';
    $result = process_create_commande();

    t('commande créée avec succès', !empty($result['success']), $result['message'] ?? '');

    if (!empty($result['success'])) {
        $nums = !empty($result['numeros_commandes']) ? $result['numeros_commandes'] : [$result['numero_commande']];
        foreach ($nums as $num) {
            $stmt = $db->prepare("SELECT * FROM commandes WHERE numero_commande = :n");
            $stmt->execute(['n' => $num]);
            $cmd = $stmt->fetch();
            if ($cmd) {
                $created_commande_ids[] = (int) $cmd['id'];
                t("commande $num : delivery_latitude renseignée",
                    $cmd['delivery_latitude'] !== null && abs((float) $cmd['delivery_latitude'] - 14.6937) < 0.000001,
                    (string) $cmd['delivery_latitude']);
                t("commande $num : delivery_longitude renseignée",
                    $cmd['delivery_longitude'] !== null && abs((float) $cmd['delivery_longitude'] - (-17.4441)) < 0.000001,
                    (string) $cmd['delivery_longitude']);
                t("commande $num : précision et source",
                    abs((float) $cmd['delivery_geo_precision'] - 12.5) < 0.01 && $cmd['delivery_geo_source'] === 'gps');
                t("commande $num : date de capture", $cmd['delivery_geo_date'] !== null);
            } else {
                t("commande $num retrouvée en BDD", false);
            }
        }

        // Dernière position user mémorisée
        $stmt = $db->prepare("SELECT last_latitude, last_geo_date FROM users WHERE id = :id");
        $stmt->execute(['id' => $test_user_id]);
        $u = $stmt->fetch();
        t('users.last_latitude mémorisée', $u && $u['last_latitude'] !== null && $u['last_geo_date'] !== null);

        // Panier vidé par le contrôleur
        $stmt = $db->prepare("SELECT COUNT(*) FROM panier WHERE user_id = :id");
        $stmt->execute(['id' => $test_user_id]);
        t('panier vidé après commande', (int) $stmt->fetchColumn() === 0);
    }

    /* ---------- Cas négatif : coordonnées invalides ignorées ---------- */
    echo "\nCas négatif : coordonnées invalides\n";
    $stmt = $db->prepare("
        INSERT INTO panier (user_id, vendeur_id, produit_id, quantite, prix_unitaire, date_ajout)
        VALUES (:uid, :vid, :pid, 1, :prix, NOW())
    ");
    $stmt->execute([
        'uid' => $test_user_id,
        'vid' => (int) $prod['admin_id'],
        'pid' => (int) $prod['id'],
        'prix' => (float) $prod['prix'],
    ]);
    $_POST['geo_lat'] = '999';
    $_POST['geo_lng'] = 'abc';
    $result2 = process_create_commande();
    t('commande créée même sans position valide', !empty($result2['success']));
    if (!empty($result2['success'])) {
        $stmt = $db->prepare("SELECT id, delivery_latitude FROM commandes WHERE numero_commande = :n");
        $stmt->execute(['n' => $result2['numero_commande']]);
        $cmd2 = $stmt->fetch();
        if ($cmd2) {
            $created_commande_ids[] = (int) $cmd2['id'];
            t('coordonnées invalides NON enregistrées', $cmd2['delivery_latitude'] === null);
        }
    }
} catch (Throwable $e) {
    t('déroulement du test', false, $e->getMessage());
}

/* ---------- Nettoyage ---------- */
echo "\nNettoyage des données de test…\n";
try {
    if (!empty($created_commande_ids)) {
        $in = implode(',', array_map('intval', $created_commande_ids));
        $db->exec("DELETE FROM commande_produits WHERE commande_id IN ($in)");
        $db->exec("DELETE FROM commandes WHERE id IN ($in)");
        echo "  - commandes de test supprimées (" . count($created_commande_ids) . ")\n";
    }
    if ($test_user_id > 0) {
        $db->prepare("DELETE FROM panier WHERE user_id = :id")->execute(['id' => $test_user_id]);
        $db->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $test_user_id]);
        echo "  - utilisateur de test supprimé\n";
    }
} catch (Throwable $e) {
    echo "  ! Nettoyage incomplet : " . $e->getMessage() . "\n";
}

echo "\n=== Résultat : $pass OK / $fail FAIL ===\n";
exit($fail > 0 ? 1 : 0);
