<?php
$numero_commande = isset($numero_commande) ? (string) $numero_commande : '';
$whatsapp_url = isset($whatsapp_url) ? (string) $whatsapp_url : '#';
$whatsapp_display = isset($whatsapp_display) ? (string) $whatsapp_display : '+221 77 329 2123';
$montant = isset($montant) ? (string) $montant : '';
?>
<div class="ckm-success">
    <div class="ckm-success__icon"><i class="fas fa-check"></i></div>
    <h3 class="ckm-success__title">Commande confirmée</h3>
    <p class="ckm-success__text">Merci ! Votre commande a bien été enregistrée. WhatsApp s'ouvre pour envoyer le récapitulatif à notre équipe.</p>
    <?php if ($numero_commande !== ''): ?>
        <p class="ckm-success__numero">N° <?php echo htmlspecialchars($numero_commande); ?></p>
    <?php endif; ?>
    <?php if ($montant !== ''): ?>
        <p class="ckm-success__montant"><?php echo htmlspecialchars($montant); ?></p>
    <?php endif; ?>
    <a class="ckm-btn ckm-btn--whatsapp" href="<?php echo htmlspecialchars($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer">
        <i class="fab fa-whatsapp"></i> WhatsApp <?php echo htmlspecialchars($whatsapp_display); ?>
    </a>
    <div class="ckm-success__actions">
        <a href="/index.php" class="ckm-btn ckm-btn--ghost">Accueil</a>
        <a href="/produits.php" class="ckm-btn ckm-btn--primary">Continuer mes achats</a>
    </div>
</div>
