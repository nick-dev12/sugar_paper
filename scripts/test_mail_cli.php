<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../services/mail.php';

$to = $argv[1] ?? 'sugarpaper26@gmail.com';
$result = mail_send($to, 'Test Sugar Paper CLI', '<p>Test email depuis scripts/test_mail_cli.php</p>', true);
var_export($result);
echo PHP_EOL;
