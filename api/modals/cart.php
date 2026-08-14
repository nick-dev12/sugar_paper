<?php
require_once __DIR__ . '/../../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../../conn/conn.php';
require_once __DIR__ . '/../../includes/checkout_modals_data.php';

$rendered = checkout_modals_render_cart();
modal_json_response([
    'ok' => true,
    'html' => $rendered['html'],
    'count' => $rendered['count'],
    'empty' => !empty($rendered['empty']),
    'logged_in' => !empty($rendered['logged_in']),
]);
