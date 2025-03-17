<?php
use AfricasTalking\SDK\AfricasTalking;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once "PHPMailer/src/PHPMailer.php";
require_once "PHPMailer/src/Exception.php";
require_once "PHPMailer/src/SMTP.php";
require_once"vendor/autoload.php";

$mail = new PHPMailer(true);
$email=$_GET['email'];
try {
	$mail->SMTPOptions = array(
		'ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true,
		)
	  );
	$mail->SMTPDebug = 0;									
	$mail->isSMTP();											
	$mail->Host	 = 'smtp.gmail.com;';					
	$mail->SMTPAuth = true;							
	$mail->Username = 'vincentbettoh@gmail.com';				
	$mail->Password = 'pwjc ryxi niml firh';						
	$mail->SMTPSecure = 'tls';							
	$mail->Port	 = 587;
	$mail->setFrom("student@gmail.com", "Login");		
	$mail->addAddress($email);	
	$mail->isHTML(true);								
	$mail->Subject = 'Account Activation';
	$mail->Body="Follow this <a href='#'>link to activate your account";
	$mail->AltBody = 'Body in plain text for non-HTML mail clients';
	$mail->send();
	echo "Mail has been sent successfully!";
	?>

	<script>
		location.replace("admin.php");
		</script>
		<?php

} catch (Exception $e) {
	echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>
