<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require __DIR__ . '/../config/email.php';
$hosts = [
    'mail.sugar-paper.com',
    'smtp.sugar-paper.com',
    'sugar-paper.com',
    'mail.sugarpaper.com',
];

foreach ($hosts as $host) {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = 465;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'];
    $mail->Password = $config['smtp']['password'];
    $mail->Timeout = 15;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->setFrom($config['from']['email'], $config['from']['name']);
    $mail->addAddress('sugarpaper26@gmail.com');
    $mail->Subject = 'Test host ' . $host;
    $mail->Body = 'Test';
    $mail->isHTML(true);
    try {
        $mail->send();
        echo "OK: $host\n";
        exit(0);
    } catch (Exception $e) {
        echo "FAIL: $host — " . ($mail->ErrorInfo ?: $e->getMessage()) . "\n";
    }
}
