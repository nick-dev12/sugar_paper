<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/includes/checkout_modals_data.php';
require_once __DIR__ . '/includes/panier_invite.php';
require_once __DIR__ . '/models/model_panier.php';

$target = checkout_modals_return_url('/index.php');
$open = 'panier';
if (checkout_modals_user_logged_in() && !empty(panier_get_items_courant())) {
    $open = 'commande';
}
header('Location: ' . checkout_modals_append_query($target, 'open', $open));
exit;
