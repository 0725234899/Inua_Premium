<?php
require 'C:\xampp\htdocs\inua_premium_services\manager\PHPMailer\src\PHPMailer.php';
require 'C:\xampp\htdocs\inua_premium_services\manager\PHPMailer\src\Exception.php';
require 'C:\xampp\htdocs\inua_premium_services\manager\PHPMailer\src\SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
$mail->SMTPDebug = 0;
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->Port = 587;
$mail->SMTPSecure = 'tls';
$mail->SMTPAuth = true;
$mail->Username = 'cheruiyotantonio@gmail.com';
$mail->Password = 'mdaj xpca xdok dxqq';
$mail->setFrom('cheruiyotantonio@gmail.com', 'Inua Premium Services');
$mail->addAddress('cheruiyotantonio@gmail.com');
$mail->Subject = 'SMTP test';
$mail->Body = 'SMTP test body';
$mail->AltBody = 'SMTP test body';

try {
    $mail->send();
    echo "SENT\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
