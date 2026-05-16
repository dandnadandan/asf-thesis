<?php
/**
 * Email Configuration for ASF Surveillance System
 * Centralized email settings for password reset and other email functionality
 */

// Email server configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'docvic.santiago@gmail.com');
define('SMTP_PASSWORD', 'namvefmupnuydbon');
define('SMTP_ENCRYPTION', 'tls');

// Development fallback settings
define('USE_MAIL_FALLBACK', false);
define('MAIL_FROM_EMAIL', 'noreply@localhost');

// Email sender configuration
define('FROM_EMAIL', 'docvic.santiago@gmail.com');
define('FROM_NAME', 'ASF Surveillance System');
define('REPLY_TO_EMAIL', 'docvic.santiago@gmail.com');
define('REPLY_TO_NAME', 'ASF Surveillance Support');

// Application configuration
define('APP_NAME', 'ASF Surveillance System');
define('APP_URL', 'https://asf-surveillance.ph');
define('SUPPORT_EMAIL', 'support@asf-surveillance.ph');

// Password reset configuration
define('PASSWORD_RESET_EXPIRY_HOURS', 1);
define('PASSWORD_RESET_SUBJECT', 'Password Reset Request - ASF Surveillance System');

// Email templates
define('PASSWORD_RESET_TEMPLATE', '
Hello {first_name},

You have requested to reset your password for your {app_name} account.

Click the following link to reset your password:
{reset_link}

This link will expire in {expiry_hours} hour(s).

If you did not request this password reset, please ignore this email.

Best regards,
{app_name} Team
CALABARZON Region
');

// Function to get formatted email template
function getEmailTemplate($template, $variables) {
    foreach ($variables as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    return $template;
}

// Function to get application URL
function getAppUrl() {
    if (defined('APP_URL') && !empty(APP_URL)) {
        return APP_URL;
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['REQUEST_URI'] ?? '');
    
    $url = $protocol . '://' . $host . $path;
    error_log("Generated URL: " . $url);
    
    return $url;
}

// Function to send email with fallback
function sendEmailWithFallback($to, $subject, $message, $fromEmail = null, $fromName = null) {
    $fromEmail = $fromEmail ?: FROM_EMAIL;
    $fromName = $fromName ?: FROM_NAME;
    
    if (!USE_MAIL_FALLBACK) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = 'error_log';
            
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = !empty(SMTP_USERNAME);
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = SMTP_PORT;
            
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo(REPLY_TO_EMAIL, REPLY_TO_NAME);
            
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            error_log("PHPMailer: Email sent successfully to " . $to);
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            return false;
        }
    }
    
    $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . REPLY_TO_NAME . " <" . REPLY_TO_EMAIL . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}
?>
