<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/includes/checkout_modals_data.php';

$target = checkout_modals_return_url('/index.php');
header('Location: ' . checkout_modals_append_query($target, 'open', 'panier'));
exit;
