<?php
/**
 * Envoie une notification push au client lors du changement de statut de commande
 * @param int $user_id ID du client
 * @param string $numero_commande Numéro de la commande
 * @param string $nouveau_statut Statut mis à jour
 * @return void
 */
function send_commande_status_notification($user_id, $numero_commande, $nouveau_statut) {
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';
    
    $tokens = get_fcm_tokens_by_user($user_id);
    if (empty($tokens)) {
        return;
    }
    
    $statut_labels = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'prise_en_charge' => 'Prise en charge',
        'en_preparation' => 'En préparation',
        'livraison_en_cours' => 'Livraison en cours',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'annulee' => 'Annulée'
    ];
    
    $label = $statut_labels[$nouveau_statut] ?? ucfirst(str_replace('_', ' ', $nouveau_statut));
    
    $title = 'Mise à jour de votre commande';
    $body = "Commande #{$numero_commande} : {$label}";
    
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $link = $base_url . '/user/mes-commandes.php';
    
    firebase_send_notification($tokens, $title, $body, [
        'link' => $link,
        'commande_id' => '',
        'statut' => $nouveau_statut,
        'numero_commande' => $numero_commande,
        'tag' => 'commande-' . $numero_commande
    ]);
}
