<?php
declare(strict_types=1);

/**
 * BDS Tanzania — registration confirmation + management notification mails.
 *
 * Uses the bundled PHPMailer with the SMTP account from mail-config.php.
 * Both functions throw on configuration problems; callers decide whether
 * a mail failure should block the flow (it should not).
 */

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function bdst_reg_mailer(): PHPMailer
{
    $config = bdst_config();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = (string) $config['smtpHost'];
    $mail->SMTPAuth   = true;
    $mail->Username   = (string) $config['smtpUsername'];
    $mail->Password   = (string) $config['smtpPassword'];
    $mail->Port       = (int) ($config['smtpPort'] ?? 465);
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 20;

    if (($config['smtpEncryption'] ?? 'ssl') === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->setFrom((string) $config['fromEmail'], (string) $config['siteName']);

    return $mail;
}

function bdst_reg_status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Approved',
        'rejected' => 'Not Approved',
        'reviewed' => 'Under Review',
        default    => 'Pending Review',
    };
}

function bdst_reg_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM bds_registrations WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function bdst_send_registration_emails(PDO $pdo, int $id): void
{
    $reg = bdst_reg_fetch($pdo, $id);
    if ($reg === null) {
        return;
    }

    $config  = bdst_config();
    $site    = (string) $config['siteName'];
    $toEmail = (string) $config['toEmail'];
    $ref     = (string) $reg['reference_no'];
    $name    = (string) $reg['full_name'];
    $email   = (string) $reg['email'];

    // ---------- 1. Confirmation to the applicant ----------
    $memberBody = '
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#364e52;">
        <div style="background:#0c1e21;padding:26px 30px;border-radius:10px 10px 0 0;text-align:center;">
            <h2 style="color:#ffffff;margin:0;font-size:21px;">BDS Tanzania</h2>
            <p style="color:#1997C6;margin:6px 0 0;font-size:13px;">Business Development Society of Tanzania</p>
        </div>
        <div style="border:1px solid #c9d1d1;border-top:none;border-radius:0 0 10px 10px;padding:30px;">
            <h3 style="color:#0c1e21;margin-top:0;">Dear ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</h3>
            <p>Thank you for registering with BDS Tanzania. We confirm that your membership registration has been
            <strong style="color:#1e7e34;">successfully received by BDS Management</strong>.</p>
            <div style="background:#ecf0f0;border:2px dashed #1997C6;border-radius:10px;padding:16px 22px;text-align:center;margin:22px 0;">
                <small style="display:block;letter-spacing:.08em;color:#67787a;font-weight:bold;">YOUR REFERENCE NUMBER</small>
                <strong style="font-size:24px;color:#0c1e21;letter-spacing:.04em;">' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</strong>
            </div>
            <p><strong>What happens next?</strong></p>
            <ol style="padding-left:20px;line-height:1.9;">
                <li>The Secretariat reviews your application for eligibility and membership category.</li>
                <li>You can check your status anytime using your reference number and email on our website.</li>
                <li>Admission decisions are communicated formally in writing.</li>
                <li>Approved members receive guidance on fees to activate membership.</li>
            </ol>
            <p>Please keep your reference number safe — you will need it for all follow-up communication.</p>
            <p style="margin-bottom:0;">Best regards,<br><strong>BDS Tanzania Secretariat</strong></p>
        </div>
    </div>';

    $member = bdst_reg_mailer();
    $member->addAddress($email, $name);
    $member->Subject = 'Registration Received - ' . $site . ' [' . $ref . ']';
    $member->isHTML(true);
    $member->Body    = $memberBody;
    $member->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</ol>'], "\n", $memberBody));
    $member->send();

    // ---------- 2. Notification to BDS Management ----------
    $list = static fn (?string $json): string => implode(', ', bdst_decode_list($json)) ?: '-';

    $rows = [
        'Reference Number'    => $ref,
        'Full Name'           => $name,
        'Gender'              => (string) $reg['gender'],
        'Age Group'           => (string) $reg['age_group'],
        'Phone'               => (string) $reg['phone'],
        'Email'               => $email,
        'Region / District'   => $reg['region'] . ' / ' . $reg['district'],
        'Professional Status' => (string) $reg['professional_status'],
        'Firm / Organization' => ($reg['firm_name'] ?? '') !== '' ? (string) $reg['firm_name'] : '-',
        'Experience'          => (string) $reg['experience_years'],
        'Education'           => (string) $reg['education'],
        'Service Areas'       => $list($reg['service_areas']),
        'Industries Served'   => $list($reg['industries']),
        'Client Segments'     => $list($reg['client_segments']),
        'Clients (3 years)'   => (string) $reg['clients_served'],
        'Service Nature'      => $list($reg['service_nature']),
        'Certifications'      => ((int) $reg['has_certification']) === 1 ? $list($reg['certifications']) : 'None',
        'Zones Covered'       => $list($reg['zones']),
        'CV Document'         => 'Attached in the admin panel (' . ($reg['cv_original_name'] ?: 'file') . ')',
        'Submitted At'        => (string) $reg['submitted_at'],
    ];

    $tableRows = '';
    foreach ($rows as $label => $value) {
        $tableRows .= '<tr>'
            . '<td style="padding:8px 12px;border:1px solid #dfe7e7;background:#f4f8f8;font-weight:bold;width:32%;color:#0c1e21;">'
            . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px 12px;border:1px solid #dfe7e7;">'
            . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }

    $adminPanelUrl = 'https://www.bdst.or.tz/nexus/registrations.php';
    $adminBody = '
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:700px;margin:0 auto;color:#364e52;">
        <h2 style="color:#0c1e21;">New Member Registration — ' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</h2>
        <p>A new member has registered through the BDS Tanzania website and is awaiting review.</p>
        <table style="border-collapse:collapse;width:100%;font-size:14px;">' . $tableRows . '</table>
        <p style="margin-top:22px;">
            <a href="' . $adminPanelUrl . '" style="background:#1997C6;color:#fff;padding:11px 26px;border-radius:50px;text-decoration:none;font-weight:bold;">
                Review in Admin Panel
            </a>
        </p>
    </div>';

    $admin = bdst_reg_mailer();
    $admin->addAddress($toEmail);
    $admin->addReplyTo($email, $name);
    $admin->Subject = 'New Member Registration: ' . $name . ' [' . $ref . ']';
    $admin->isHTML(true);
    $admin->Body    = $adminBody;
    $admin->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</tr>', '</p>'], "\n", $adminBody));
    $admin->send();
}
