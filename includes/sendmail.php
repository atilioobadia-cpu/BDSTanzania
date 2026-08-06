<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/sendmail-error.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

require_once __DIR__ . '/db.php';

$config = bdst_config();

$toEmail        = $config['toEmail'];
$fromEmail      = $config['fromEmail'];
$siteName       = $config['siteName'];
$smtpHost       = $config['smtpHost'];
$smtpUsername   = $config['smtpUsername'];
$smtpPassword   = $config['smtpPassword'];
$smtpPort       = (int) $config['smtpPort'];
$smtpEncryption = $config['smtpEncryption'];

function json_response(bool $success, string $message, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

function clean_value(string $value): string
{
    $value = trim($value);
    $value = strip_tags($value);
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    return preg_replace('/\s+/', ' ', $value) ?? '';
}

function clean_textarea(string $value): string
{
    return trim(strip_tags($value));
}

function clean_email(string $value): string
{
    $value = filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}

function send_smtp_mail(
    string $to,
    string $subject,
    string $htmlBody,
    string $replyEmail,
    string $replyName,
    string $fromEmail,
    string $siteName,
    string $smtpHost,
    string $smtpUsername,
    string $smtpPassword,
    int $smtpPort,
    string $smtpEncryption
): bool {
    try {
        $mail = new PHPMailer(true);

        // Debug to log file
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) {
            error_log("SMTP DEBUG [$level]: $str");
        };

        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUsername;
        $mail->Password   = $smtpPassword;
        $mail->Port       = $smtpPort;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        if ($smtpEncryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($fromEmail, $siteName);
        $mail->addAddress($to);

        if ($replyEmail !== '') {
            $mail->addReplyTo($replyEmail, $replyName);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        error_log('Attempting SMTP mail to: ' . $to . ' | Subject: ' . $subject);

        $mail->send();

        error_log('SMTP Mail send result: SUCCESS');
        return true;

    } catch (Exception $e) {
        error_log('SMTP Mail error: ' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', 405);
}

$formType = clean_value($_POST['form_type'] ?? '');
error_log('Form submitted. Type: ' . $formType);
error_log('POST Data: ' . print_r($_POST, true));

$name = '';
$email = '';
$phone = '';
$subject = '';
$message = '';
$source = '';

if ($formType === 'contact_page') {
    $name    = clean_value($_POST['conName'] ?? '');
    $email   = clean_email($_POST['conEmail'] ?? '');
    $phone   = clean_value($_POST['conPhone'] ?? '');
    $subject = clean_value($_POST['conSubject'] ?? '');
    $message = clean_textarea($_POST['conMessage'] ?? '');
    $source  = 'Contact Page';
} elseif ($formType === 'home_contact') {
    $name    = clean_value($_POST['cfName3'] ?? '');
    $email   = clean_email($_POST['cfEmail3'] ?? '');
    $phone   = clean_value($_POST['cfPhone3'] ?? '');
    $subject = clean_value($_POST['cfSubject3'] ?? '');
    $message = clean_textarea($_POST['cfMessage3'] ?? '');
    $source  = 'Homepage Form';
} else {
    json_response(false, 'Invalid form submission.', 400);
}

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    json_response(false, 'Please fill in all required fields.');
}

$mailSubject = 'Website Contact: ' . $subject;
$mailBody = '
    <h2>New Website Enquiry</h2>
    <p><strong>Source:</strong> ' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8') . '</p>
    <p><strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>
    <p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>
    <p><strong>Phone:</strong> ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</p>
    <p><strong>Subject:</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</p>
    <p><strong>Message:</strong><br>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>
';

$sent = send_smtp_mail(
    $toEmail,
    $mailSubject,
    $mailBody,
    $email,
    $name,
    $fromEmail,
    $siteName,
    $smtpHost,
    $smtpUsername,
    $smtpPassword,
    $smtpPort,
    $smtpEncryption
);

if ($sent) {
    json_response(true, 'Thank you! Your message has been sent successfully.');
}

json_response(false, 'Sorry, something went wrong. Please try again later.');