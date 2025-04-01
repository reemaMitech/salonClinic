<?php
use CodeIgniter\Controller;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;;

require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';

function sendConfirmationEmail($email, $ccEmails = [], $receiverSubject = null, $receiverMsg = null, $senderSubject = null, $senderMsg = null, $otp = null, $password = null, $sameValues = null)
{
// print_r($receiverMsg);die;
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'aayurphysio.com'; // Replace 'mail.vedikastrologer.com' with your webmail SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'support@aayurphysio.com'; // Replace with your webmail username
        $mail->Password = 'P2%x}8Z%kQI0'; // Replace with your webmail password
        $mail->SMTPSecure = 'tls'; // or 'ssl' if your provider supports SSL encryption
        $mail->Port = 587; // or the port provided by your webmail provider

        $mail->setFrom('support@aayurphysio.com', 'Aayurphysio'); // Replace with your webmail email address and sender name

        // Validate the recipient email
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($email, 'Recipient Name');
        } else {
            // If invalid, send to default email
            $mail->addAddress('support@aayurphysio.com', 'Recipient Name');
        }

        // Add CC emails
        if ($ccEmails) {
            foreach ($ccEmails as $ccEmail) {
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($ccEmail);
                }
            }
        }

        // Add CC emails with same values
        if ($sameValues) {
            foreach ($ccEmails as $ccEmail) {
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($ccEmail, 'Recipient Name');
                }
            }
        }

        $mail->isHTML(true);

        // Receiver's email
        $mail->Subject = $receiverSubject;
        $mail->Body = $receiverMsg;
        $mail->send();

        // Sender's email
        $mail->clearAddresses();
        $mail->addAddress('support@aayurphysio.com', 'Aayurphysio'); // Replace with sender's email and name
        $mail->Subject = $senderSubject;
        $mail->Body = $senderMsg;
        $mail->send();
        
        return true;

    } catch (Exception $e) {
        echo "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
        return false;
    }

}

