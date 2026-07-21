<?php
require 'C:\xampp\htdocs\inua_premium_services\manager\PHPMailer\src\PHPMailer.php';
require 'C:\xampp\htdocs\inua_premium_services\manager\PHPMailer\src\Exception.php';
require 'C:\xampp\htdocs\inua_premium_services\manager\PHPMailer\src\SMTP.php';
$mail = new PHPMailer\PHPMailer\PHPMailer(true);
$mail->SMTPDebug = 2;
$mail->isSMTP();
$mail->Host = getenv('SMTP_HOST');
$mail->Port = (int)getenv('SMTP_PORT');
$mail->SMTPSecure = getenv('SMTP_ENCRYPTION');
$mail->SMTPAuth = true;
$mail->Username = getenv('SMTP_USERNAME');
$mail->Password = getenv('SMTP_PASSWORD');
$mail->setFrom(getenv('SMTP_FROM_EMAIL'), getenv('SMTP_FROM_NAME'));
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
