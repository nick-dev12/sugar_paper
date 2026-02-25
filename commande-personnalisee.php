<?php
/**
 * Page de demande de commande personnalisée
 * Accessible à tous (connectés ou non)
 */

session_start();

require_once __DIR__ . '/controllers/controller_commandes_personnalisees.php';
$result = process_commande_personnalisee();

if ($result['success']) {
    $_SESSION['commande_perso_success'] = $result['message'];
    header('Location: index.php?commande_perso=1');
    exit;
}

$prefill = [
    'nom' => $_SESSION['user_nom'] ?? '',
    'prenom' => $_SESSION['user_prenom'] ?? '',
    'email' => $_SESSION['user_email'] ?? '',
    'telephone' => $_SESSION['user_telephone'] ?? ''
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefill = [
        'nom' => $_POST['nom'] ?? '',
        'prenom' => $_POST['prenom'] ?? '',
        'email' => $_POST['email'] ?? '',
        'telephone' => $_POST['telephone'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande personnalisée - Sugar Paper</title>
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .page-commande-perso { padding: 100px 20px 60px; max-width: 700px; margin: 0 auto; }
        .page-commande-perso h1 { font-family: var(--font-titres); color: var(--titres); margin-bottom: 10px; font-size: 28px; }
        .page-commande-perso .intro { color: var(--texte-fonce); margin-bottom: 30px; line-height: 1.6; }
        .form-commande-perso { background: var(--glass-bg); border-radius: 16px; padding: 30px; border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; color: var(--titres); margin-bottom: 8px; font-size: 14px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 15px; border: 2px solid rgba(229, 72, 138, 0.2); border-radius: 8px; font-size: 15px; font-family: inherit; }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--couleur-dominante); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
        .error-message { background: rgba(229, 72, 138, 0.1); border-left: 4px solid var(--couleur-dominante); padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; color: var(--titres); }
        .btn-submit { width: 100%; padding: 14px; background: var(--couleur-dominante); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-submit:hover { background: rgba(229, 72, 138, 0.9); transform: translateY(-2px); }
        .back-link { display: inline-block; margin-top: 20px; color: var(--couleur-dominante); text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include 'nav_bar.php'; ?>

    <div class="page-commande-perso">
        <h1><i class="fas fa-palette"></i> Commande personnalisée</h1>
        <p class="intro">
            Vous avez une demande spécifique ? Décrivez-nous vos besoins et notre équipe vous contactera pour préparer un devis sur mesure.
        </p>

        <?php if (!empty($result['message']) && !$result['success']): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="form-commande-perso">
            <div class="form-row">
                <div class="form-group">
                    <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                    <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($prefill['nom']); ?>" placeholder="Votre nom">
                </div>
                <div class="form-group">
                    <label for="prenom"><i class="fas fa-user"></i> Prénom *</label>
                    <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($prefill['prenom']); ?>" placeholder="Votre prénom">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($prefill['email']); ?>" placeholder="votre@email.com">
                </div>
                <div class="form-group">
                    <label for="telephone"><i class="fas fa-phone"></i> Téléphone *</label>
                    <input type="tel" id="telephone" name="telephone" required value="<?php echo htmlspecialchars($prefill['telephone']); ?>" placeholder="+237 6XX XXX XXX">
                </div>
            </div>
            <div class="form-group">
                <label for="description"><i class="fas fa-align-left"></i> Décrivez votre demande *</label>
                <textarea id="description" name="description" required placeholder="Décrivez en détail ce que vous souhaitez : type de produit, quantités, spécificités, etc."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="type_produit"><i class="fas fa-tag"></i> Type de produit (optionnel)</label>
                    <input type="text" id="type_produit" name="type_produit" value="<?php echo htmlspecialchars($_POST['type_produit'] ?? ''); ?>" placeholder="Ex: Huile de coco, Noix de cajou...">
                </div>
                <div class="form-group">
                    <label for="quantite"><i class="fas fa-cubes"></i> Quantité souhaitée (optionnel)</label>
                    <input type="text" id="quantite" name="quantite" value="<?php echo htmlspecialchars($_POST['quantite'] ?? ''); ?>" placeholder="Ex: 5 kg, 10 bouteilles...">
                </div>
            </div>
            <div class="form-group">
                <label for="date_souhaitee"><i class="fas fa-calendar"></i> Date souhaitée (optionnel)</label>
                <input type="date" id="date_souhaitee" name="date_souhaitee" value="<?php echo htmlspecialchars($_POST['date_souhaitee'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Envoyer ma demande
            </button>
        </form>

        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à l'accueil</a>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
