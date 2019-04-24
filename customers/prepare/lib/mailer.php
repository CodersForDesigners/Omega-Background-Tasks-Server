<?php

namespace Mailer;

ini_set( "display_errors", 0 );
ini_set( "error_reporting", E_ALL );

require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



function log ( $subject, $body ) {

	date_default_timezone_set( 'Asia/Kolkata' );

	$username = 'google@lazaro.in';
	$password = 't34m,l4z4r0';
	$fromEmail = 'omega@lazaro.in';
	$fromName = 'adi';
	$toEmail = 'adityabhat@lazaro.in';

	// Create a new PHPMailer instance
	$mail = new PHPMailer( true );

	try {

		// Enable SMTP debugging
		// 0 = off (for production use)
		// 1 = client messages
		// 2 = client and server messages
		$mail->SMTPDebug = 0;

		// Tell PHPMailer to use SMTP
		$mail->isSMTP();

		// Ask for HTML-friendly debug output
		$mail->Debugoutput = 'html';

		// Set the hostname of the mail server
		$mail->Host = 'smtp.gmail.com';

		// Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
		$mail->Port = 587;

		// Set the encryption system to use - ssl (deprecated) or tls
		$mail->SMTPSecure = 'tls';

		// Use SMTP authentication
		$mail->SMTPAuth = true;

		// Username to use for SMTP authentication - use full email address for gmail
		$mail->Username = $username;

		// Password to use for SMTP authentication
		$mail->Password = $password;

		// Set who the message is to be sent from
		$mail->setFrom( $fromEmail, $fromName );

		// Set an alternative reply-to address
		// $mail->addReplyTo('replyto@example.com', 'First Last');

		// Set who the message is to be sent to
		$mail->addAddress( $toEmail, '' );
		if ( ! empty( $toCC ) ) {
			foreach ( $email as $toCC ) {
				$mail->addCC( $email, '' );
			}
		}

		$mail->isHTML( true );

		// Set the subject line
		$mail->Subject = $subject;

		// Set the mail body
		$mail->Body = $body;

		// Send the message
		$mail->send();

		return 'Mail sent.';

	} catch ( \Exception $e ) {

		throw new \Exception( $mail->ErrorInfo );

	}

}
