<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

function getOfferLetterManager($conn) {
    $profile = ['name' => 'Inua Premium Services', 'email' => 'info@inuapremium.co.ke', 'phone' => 'N/A', 'region' => 'Unassigned'];
    $email = trim((string) ($_SESSION['email'] ?? ''));
    $stmt = $conn->prepare("SELECT u.name, u.email, u.phone, COALESCE(a.area_name, 'Unassigned') AS region_name
                            FROM users u LEFT JOIN areas a ON u.area = a.area_id
                            WHERE u.email = ? LIMIT 1");
    if ($stmt && $email !== '') {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $profile['name'] = trim((string) ($row['name'] ?? '')) ?: $profile['name'];
            $profile['email'] = trim((string) ($row['email'] ?? '')) ?: $profile['email'];
            $profile['phone'] = trim((string) ($row['phone'] ?? '')) ?: $profile['phone'];
            $profile['region'] = trim((string) ($row['region_name'] ?? '')) ?: $profile['region'];
        }
    }
    return $profile;
}

function offerValue($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function formatOfferDate($value, $fallback = '') {
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date ? $date->format('d/m/Y') : $fallback;
}

function generateOfferLetterPdf($candidate, $manager) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor($manager['name']);
    $pdf->SetTitle('Offer Letter - ' . $candidate['name']);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(false, 15);
    $pdf->AddPage();

    $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 150, 10, 40, 0, 'PNG');
    }
    $pdf->SetDrawColor(0, 47, 196);
    $pdf->SetLineWidth(1.2);
    $pdf->Line(15, 13, 145, 13);
    $pdf->SetDrawColor(210, 0, 0);
    $pdf->SetLineWidth(0.6);
    $pdf->Line(15, 15, 145, 15);

    $pdf->SetTextColor(0, 47, 196);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'INUA PREMIUM SERVICES', 0, 1, 'L');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Human Resources and Administration Department', 0, 1, 'L');
    $pdf->Cell(0, 5, 'Tel: ' . $manager['phone'] . ' | Email: ' . $manager['email'], 0, 1, 'L');
    $pdf->Cell(0, 5, 'Region: ' . $manager['region'], 0, 1, 'L');
    $pdf->SetFont('helvetica', 'BI', 9);
    $pdf->Cell(0, 5, 'Issuing Manager: ' . $manager['name'], 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 5, 'REFERENCE: OL-' . date('YmdHis'), 0, 1, 'R');
    $pdf->Cell(0, 5, 'DATE: ' . date('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(2);

    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'FORMAL OFFER LETTER', 0, 1, 'C');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Ln(2);

    $safeName = offerValue($candidate['name']);
    $safeRole = offerValue($candidate['role']);
    $safeStart = offerValue(formatOfferDate($candidate['start_date'], 'to be agreed'));
    $safePhone = offerValue($candidate['phone']);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<b>Dear ' . $safeName . ',</b>', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('We are pleased to offer you the position of <b>' . $safeRole . '</b> with <b>Inua Premium Services</b>. Following our assessment of your application and interview, we believe that your skills and experience will be valuable to our organization.', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('Your proposed commencement date will be <b>' . $safeStart . '</b>. The terms of your appointment, including duties, reporting arrangements, remuneration, working hours, probation, leave, confidentiality, and applicable company policies, will be explained and confirmed during onboarding and in the employment agreement.', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('This offer is subject to satisfactory verification of the information provided, submission of the required employment documents, and completion of the organization\'s appointment procedures. You may be required to provide identification, academic and professional certificates, references, and any other documents reasonably required by the company.', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('<b>Conditions of the offer:</b> This offer does not replace the formal employment agreement. Your appointment will be governed by the laws of Kenya, the company\'s policies, and the written employment terms issued upon acceptance. The company reserves the right to withdraw this offer where material information is found to be inaccurate or where the stated conditions are not met.', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('Please sign and return a copy of this letter or confirm your acceptance by email within the period communicated by the Human Resources and Administration Department. We look forward to welcoming you to Inua Premium Services.', true, false, true, false, 'L');
    $pdf->Ln(5);

    $pdf->MultiCell(0, 6, 'Yours faithfully,', 0, 'L');
    $pdf->Ln(2);
    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    if (file_exists($stampPath)) {
        $stampY = $pdf->GetY();
        $pdf->SetAlpha(0.45);
        $pdf->Image($stampPath, 150, $stampY - 2, 34, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
        $pdf->SetY($stampY + 17);
    }
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->MultiCell(0, 6, $manager['name'], 0, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'Issuing Manager', 0, 'L');
    $pdf->MultiCell(0, 5, 'Inua Premium Services', 0, 'L');
    $pdf->MultiCell(0, 5, 'Phone: ' . $manager['phone'] . ' | Email: ' . $manager['email'], 0, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 9.5);
    $pdf->Cell(0, 5, 'CANDIDATE ACCEPTANCE', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'I, ' . $safeName . ', accept the offer for the position of ' . $safeRole . ' on the terms communicated to me.', 0, 'L');
    $pdf->Ln(5);
    $pdf->Cell(85, 5, 'Candidate Signature: ____________________', 0, 0, 'L');
    $pdf->Cell(0, 5, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->Cell(0, 5, 'Candidate Phone: ' . $safePhone, 0, 1, 'L');

    $pdf->Line(15, 270, 195, 270);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'This document is an official offer issued by Inua Premium Services.', 0, 1, 'C');
    return $pdf->Output('', 'S');
}

function sendOfferLetterEmail($email, $candidateName, $pdfContent, $filename) {
    $credentials = getEmailAccount();
    if (empty($credentials['sender_email']) || empty($credentials['sender_app_password'])) {
        throw new Exception('Email settings are not configured.');
    }
    $mail = new PHPMailer(true);
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    $mail->Username = $credentials['sender_email'];
    $mail->Password = $credentials['sender_app_password'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($credentials['sender_email'], 'Inua Premium Services');
    $mail->addAddress($email, $candidateName);
    $mail->isHTML(true);
    $mail->Subject = 'Offer Letter - Inua Premium Services';
    $mail->Body = '<p>Dear ' . htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8') . ',</p><p>Please find attached your formal offer letter from Inua Premium Services.</p><p>Regards,<br>Human Resources and Administration Department</p>';
    $mail->AltBody = 'Please find attached your formal offer letter from Inua Premium Services.';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$manager = getOfferLetterManager($conn);
$sendMessage = '';
$sendStatus = '';
$formValues = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'Loan Officer', 'start_date' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_offer_letter'])) {
    foreach ($formValues as $key => $unused) {
        $formValues[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $allowedRoles = ['Loan Officer', 'Manager', 'Admin'];
    if ($formValues['name'] === '' || !filter_var($formValues['email'], FILTER_VALIDATE_EMAIL) || !in_array($formValues['role'], $allowedRoles, true)) {
        $sendMessage = 'Enter a valid candidate name, email address, and role.';
        $sendStatus = 'danger';
    } else {
        try {
            $pdf = generateOfferLetterPdf($formValues, $manager);
            $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $formValues['name']) ?: 'candidate';
            sendOfferLetterEmail($formValues['email'], $formValues['name'], $pdf, 'offer_letter_' . $safeName . '.pdf');
            $sendMessage = 'The formal offer letter was sent successfully to ' . $formValues['name'] . '.';
            $sendStatus = 'success';
        } catch (Exception $e) {
            $sendMessage = 'The offer letter could not be sent: ' . $e->getMessage();
            $sendStatus = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Letter</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background:#f8f8f8; color:#282828; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .page-container { max-width:1100px; margin:30px auto; padding:24px; }
        .shell { background:#fff; border-radius:18px; box-shadow:0 12px 28px rgba(0,0,0,.08); }
        .company-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #ef4444; padding-bottom:18px; margin-bottom:24px; }
        .company-header img { height:70px; width:auto; object-fit:contain; }
        .form-panel { background:#fff7f7; border:1px solid #f4d1d1; border-radius:12px; padding:20px; }
        .preview-panel { background:#fff; border:1px solid #e7e7e7; border-radius:16px; padding:28px; min-height:100%; }
        .section-title { color:#0b2f9f; font-size:1.05rem; font-weight:700; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid #ef4444; }
        .form-label { font-weight:600; }
        .form-control,.form-select { border-radius:8px; border-color:#d9d9d9; }
        .form-control:focus,.form-select:focus { border-color:#0b2f9f; box-shadow:0 0 0 .2rem rgba(11,47,159,.12); }
        .btn-primary { background:linear-gradient(135deg,#e84545,#ff6b6b); border:none; }
        .preview-line { border-bottom:1px solid #d9d9d9; padding:8px 0; }
        @media(max-width:768px){ .page-container{margin:10px auto;padding:10px;} .company-header img{height:52px;} }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="page-container">
    <div class="shell p-4">
        <div class="company-header">
            <div><div class="fw-bold text-uppercase text-secondary" style="letter-spacing:1px;">Inua Premium Services</div><div class="text-muted small">Human Resources and Administration Department</div></div>
            <img src="../assets/img/logo.png" alt="Inua Premium Services Logo">
        </div>
        <?php if ($sendMessage): ?><div class="alert alert-<?= htmlspecialchars($sendStatus); ?>"><?= htmlspecialchars($sendMessage); ?></div><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div><h2 class="mb-1">Offer Letter</h2><div class="text-muted">Prepare and email a formal offer letter to a shortlisted employee.</div></div>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="form-panel">
                    <div class="section-title">Shortlisted Employee</div>
                    <form method="post" action="offer_letter.php">
                        <div class="mb-3"><label class="form-label" for="name">Employee Full Name</label><input class="form-control" id="name" name="name" value="<?= offerValue($formValues['name']); ?>" required></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="email">Employee Email</label><input type="email" class="form-control" id="email" name="email" value="<?= offerValue($formValues['email']); ?>" required></div>
                            <div class="col-md-6"><label class="form-label" for="phone">Phone Number</label><input class="form-control" id="phone" name="phone" value="<?= offerValue($formValues['phone']); ?>"></div>
                            <div class="col-md-6"><label class="form-label" for="role">Offered Role</label><select class="form-select" id="role" name="role" required><?php foreach (['Loan Officer','Manager','Admin'] as $role): ?><option value="<?= $role; ?>" <?= $formValues['role'] === $role ? 'selected' : ''; ?>><?= $role; ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label" for="start_date">Proposed Start Date</label><input type="date" class="form-control" id="start_date" name="start_date" value="<?= offerValue($formValues['start_date']); ?>"></div>
                        </div>
                        <button type="submit" name="send_offer_letter" class="btn btn-primary btn-lg w-100 mt-4">Send Offer Letter</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="preview-panel">
                    <div class="section-title">Document Preview</div>
                    <p class="text-muted">The recipient will receive a branded PDF with a formal offer, conditions, acceptance section, and signature provision.</p>
                    <div class="preview-line"><strong>Candidate:</strong> <?= offerValue($formValues['name']) ?: 'Not entered'; ?></div>
                    <div class="preview-line"><strong>Role:</strong> <?= offerValue($formValues['role']); ?></div>
                    <div class="preview-line"><strong>Issuing Manager:</strong> <?= offerValue($manager['name']); ?></div>
                    <div class="preview-line"><strong>Region:</strong> <?= offerValue($manager['region']); ?></div>
                    <div class="alert alert-light border mt-4">The PDF is suitable for email delivery and formal candidate acceptance.</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
