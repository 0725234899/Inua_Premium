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

function getInterviewManager($conn) {
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

function interviewValue($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function interviewDate($value, $fallback = 'Not provided') {
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date ? $date->format('d/m/Y') : $fallback;
}

function generateInterviewLetterPdf($candidate, $manager) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor($manager['name']);
    $pdf->SetTitle('Interview and KYC Record - ' . $candidate['name']);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 22);

    $safe = function ($value) {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    };
    $section = function ($title) use ($pdf) {
        $pdf->Ln(2);
        $pdf->SetFillColor(11, 47, 159);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell(0, 7, $title, 1, 1, 'L', true);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Ln(1);
    };
    $table = function ($html) use ($pdf) {
        $pdf->SetFont('helvetica', '', 8.2);
        $pdf->writeHTML($html, true, false, true, false, 'L');
    };
    $addHeader = function () use ($pdf, $manager) {
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
        $pdf->SetFont('helvetica', 'B', 17);
        $pdf->Cell(0, 9, 'INUA PREMIUM SERVICES', 0, 1, 'L');
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell(0, 5, 'Human Resources and Administration Department', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Tel: ' . $manager['phone'] . ' | Email: ' . $manager['email'], 0, 1, 'L');
        $pdf->Cell(0, 5, 'Region: ' . $manager['region'] . ' | Interview Officer: ' . $manager['name'], 0, 1, 'L');
        $pdf->Ln(5);
    };

    $pdf->AddPage();
    $addHeader();
    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'INTERVIEW AND KNOW YOUR CUSTOMER RECORD', 0, 1, 'C');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(0, 5, 'Reference: KYC-INT-' . date('YmdHis') . '    Interview date: ' . interviewDate($candidate['interview_date']), 0, 1, 'R');
    $pdf->Ln(3);

    $section('1. PERSONAL IDENTIFICATION');
    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><td width="25%"><b>Full name</b></td><td width="25%">' . $safe($candidate['name']) . '</td><td width="25%"><b>Preferred role</b></td><td width="25%">' . $safe($candidate['role']) . '</td></tr>'
        . '<tr><td><b>Date of birth</b></td><td>' . $safe(interviewDate($candidate['date_of_birth'])) . '</td><td><b>Nationality</b></td><td>' . $safe($candidate['nationality']) . '</td></tr>'
        . '<tr><td><b>National ID / Passport</b></td><td>' . $safe($candidate['id_number']) . '</td><td><b>Marital status</b></td><td>' . $safe($candidate['marital_status']) . '</td></tr>'
        . '<tr><td><b>Phone number</b></td><td>' . $safe($candidate['phone']) . '</td><td><b>Email address</b></td><td>' . $safe($candidate['email']) . '</td></tr></table>');
    $section('2. RESIDENTIAL, FAMILY AND EMERGENCY INFORMATION');
    $table('<table border="1" cellpadding="3"><tr><td width="25%"><b>Current residence</b></td><td width="75%">' . $safe($candidate['residence']) . '</td></tr>'
        . '<tr><td><b>Permanent address</b></td><td>' . $safe($candidate['permanent_address']) . '</td></tr>'
        . '<tr><td><b>Next of kin</b></td><td>' . $safe($candidate['next_of_kin']) . ' | Relationship: ' . $safe($candidate['next_of_kin_relationship']) . ' | Phone: ' . $safe($candidate['next_of_kin_phone']) . '</td></tr>'
        . '<tr><td><b>Emergency contact</b></td><td>' . $safe($candidate['emergency_contact']) . ' | Relationship: ' . $safe($candidate['emergency_relationship']) . ' | Phone: ' . $safe($candidate['emergency_phone']) . '</td></tr></table>');
    $section('3. EDUCATION AND PROFESSIONAL BACKGROUND');
    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><th width="25%"><b>Qualification</b></th><th width="30%"><b>Institution</b></th><th width="20%"><b>Year completed</b></th><th width="25%"><b>Certificate / professional body</b></th></tr>'
        . '<tr><td>' . $safe($candidate['qualification_1']) . '</td><td>' . $safe($candidate['institution_1']) . '</td><td>' . $safe($candidate['year_1']) . '</td><td>' . $safe($candidate['certificate_1']) . '</td></tr>'
        . '<tr><td>' . $safe($candidate['qualification_2']) . '</td><td>' . $safe($candidate['institution_2']) . '</td><td>' . $safe($candidate['year_2']) . '</td><td>' . $safe($candidate['certificate_2']) . '</td></tr></table>');

    $pdf->AddPage();
    $addHeader();
    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'INTERVIEW AND KNOW YOUR CUSTOMER RECORD', 0, 1, 'C');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(0, 5, 'Reference: KYC-INT-' . date('YmdHis') . '    Confidential personnel record', 0, 1, 'R');
    $pdf->Ln(3);
    $section('4. EMPLOYMENT HISTORY');
    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><th width="18%"><b>Period</b></th><th width="24%"><b>Employer and location</b></th><th width="20%"><b>Position held</b></th><th width="23%"><b>Main responsibilities</b></th><th width="15%"><b>Reason for leaving</b></th></tr>'
        . '<tr><td>' . $safe($candidate['employment_period_1']) . '</td><td>' . $safe($candidate['employer_1']) . '</td><td>' . $safe($candidate['position_1']) . '</td><td>' . $safe($candidate['duties_1']) . '</td><td>' . $safe($candidate['reason_1']) . '</td></tr>'
        . '<tr><td>' . $safe($candidate['employment_period_2']) . '</td><td>' . $safe($candidate['employer_2']) . '</td><td>' . $safe($candidate['position_2']) . '</td><td>' . $safe($candidate['duties_2']) . '</td><td>' . $safe($candidate['reason_2']) . '</td></tr>'
        . '<tr><td>' . $safe($candidate['employment_period_3']) . '</td><td>' . $safe($candidate['employer_3']) . '</td><td>' . $safe($candidate['position_3']) . '</td><td>' . $safe($candidate['duties_3']) . '</td><td>' . $safe($candidate['reason_3']) . '</td></tr></table>');
    $section('5. REFERENCES AND INTERVIEW ASSESSMENT');
    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><th width="25%"><b>Reference</b></th><th width="25%"><b>Organisation / relationship</b></th><th width="25%"><b>Phone / email</b></th><th width="25%"><b>Verification notes</b></th></tr>'
        . '<tr><td>' . $safe($candidate['reference_1']) . '</td><td>' . $safe($candidate['reference_org_1']) . '</td><td>' . $safe($candidate['reference_contact_1']) . '</td><td></td></tr>'
        . '<tr><td>' . $safe($candidate['reference_2']) . '</td><td>' . $safe($candidate['reference_org_2']) . '</td><td>' . $safe($candidate['reference_contact_2']) . '</td><td></td></tr></table>');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'Interview notes: ' . $candidate['interview_notes'], 1, 'L');
    $pdf->MultiCell(0, 5, 'Assessment outcome: [ ] Recommended   [ ] Further verification required   [ ] Not recommended', 1, 'L');
    $section('6. CANDIDATE DECLARATION AND CONSENT');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'I declare that the information provided in this interview and KYC record is true, complete and accurate to the best of my knowledge. I authorize Inua Premium Services to verify my identity, residential details, education, employment history and references for recruitment, compliance and employment purposes, subject to applicable law. I understand that providing materially false or misleading information may affect my application or employment.', 0, 'L');
    $pdf->Ln(4);
    $pdf->Cell(90, 7, 'Candidate signature: __________________________', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->Ln(2);
    $signatureY = $pdf->GetY();
    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    if (file_exists($stampPath)) {
        $pdf->SetAlpha(0.42);
        $pdf->Image($stampPath, 145, $signatureY - 8, 35, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
    }
    $pdf->Cell(90, 7, 'Interview officer: ' . $safe($manager['name']), 0, 0, 'L');
    $pdf->Cell(0, 6, 'Signature: __________________________', 0, 1, 'L');
    $pdf->Ln(8);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'I', 7.5);
    $pdf->MultiCell(0, 4, 'Confidential recruitment record. Handle and retain this document in accordance with the organization\'s privacy, records-management and applicable data-protection requirements.', 0, 'C');

    return $pdf->Output('', 'S');
}

function sendInterviewLetterEmail($email, $candidateName, $pdfContent, $filename) {
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
    $mail->Subject = 'Interview and KYC Information Form - Inua Premium Services';
    $mail->Body = '<p>Dear ' . htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8') . ',</p><p>Please find attached the formal interview and Know Your Customer information form for completion and review.</p><p>Kindly provide accurate information about your identity, residential background, education and previous employment. Please return the completed document as instructed by our Human Resources and Administration Department.</p><p>Regards,<br>Inua Premium Services<br>Human Resources and Administration Department</p>';
    $mail->AltBody = 'Please find attached the formal interview and Know Your Customer information form for completion and review.';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$manager = getInterviewManager($conn);
$sendMessage = '';
$sendStatus = '';
$formValues = [
    'name' => '', 'email' => '', 'phone' => '', 'role' => 'Loan Officer', 'date_of_birth' => '', 'nationality' => 'Kenyan',
    'id_number' => '', 'marital_status' => '', 'residence' => '', 'permanent_address' => '', 'next_of_kin' => '',
    'next_of_kin_relationship' => '', 'next_of_kin_phone' => '', 'emergency_contact' => '', 'emergency_relationship' => '',
    'emergency_phone' => '', 'qualification_1' => '', 'institution_1' => '', 'year_1' => '', 'certificate_1' => '',
    'qualification_2' => '', 'institution_2' => '', 'year_2' => '', 'certificate_2' => '', 'employment_period_1' => '',
    'employer_1' => '', 'position_1' => '', 'duties_1' => '', 'reason_1' => '', 'employment_period_2' => '',
    'employer_2' => '', 'position_2' => '', 'duties_2' => '', 'reason_2' => '', 'employment_period_3' => '',
    'employer_3' => '', 'position_3' => '', 'duties_3' => '', 'reason_3' => '', 'reference_1' => '',
    'reference_org_1' => '', 'reference_contact_1' => '', 'reference_2' => '', 'reference_org_2' => '',
    'reference_contact_2' => '', 'interview_date' => '', 'interview_notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_interview_letter'])) {
    foreach ($formValues as $key => $unused) {
        $formValues[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $allowedRoles = ['Loan Officer', 'Manager', 'Admin'];
    if ($formValues['name'] === '' || !filter_var($formValues['email'], FILTER_VALIDATE_EMAIL) || $formValues['id_number'] === '' || !in_array($formValues['role'], $allowedRoles, true)) {
        $sendMessage = 'Enter the candidate name, a valid email address, national ID or passport number, and a valid role.';
        $sendStatus = 'danger';
    } else {
        try {
            $pdf = generateInterviewLetterPdf($formValues, $manager);
            $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $formValues['name']) ?: 'candidate';
            sendInterviewLetterEmail($formValues['email'], $formValues['name'], $pdf, 'interview_kyc_record_' . $safeName . '.pdf');
            $sendMessage = 'The interview and KYC form was sent successfully to ' . $formValues['name'] . '.';
            $sendStatus = 'success';
        } catch (Exception $e) {
            $sendMessage = 'The interview form could not be sent: ' . $e->getMessage();
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
    <title>Interview and KYC Record</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background:#f8f8f8; color:#282828; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .page-container { max-width:1200px; margin:30px auto; padding:24px; }
        .shell { background:#fff; border-radius:18px; box-shadow:0 12px 28px rgba(0,0,0,.08); }
        .company-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #ef4444; padding-bottom:18px; margin-bottom:24px; }
        .company-header img { height:70px; width:auto; object-fit:contain; }
        .form-panel { background:#fff7f7; border:1px solid #f4d1d1; border-radius:12px; padding:20px; }
        .preview-panel { background:#fff; border:1px solid #e7e7e7; border-radius:16px; padding:28px; min-height:100%; }
        .section-title { color:#0b2f9f; font-size:1.05rem; font-weight:700; margin:20px 0 14px; padding-bottom:10px; border-bottom:2px solid #ef4444; }
        .section-title:first-child { margin-top:0; }
        .form-label { font-weight:600; }
        .form-control,.form-select { border-radius:8px; border-color:#d9d9d9; }
        .form-control:focus,.form-select:focus { border-color:#0b2f9f; box-shadow:0 0 0 .2rem rgba(11,47,159,.12); }
        .btn-primary { background:linear-gradient(135deg,#e84545,#ff6b6b); border:none; }
        .required::after { content:' *'; color:#e84545; }
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
            <div><h2 class="mb-1">Interview and KYC Record</h2><div class="text-muted">Capture the interviewee's identity, background, employment history and references, then send the formal PDF by email.</div></div>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="form-panel">
                    <form method="post" action="interview_letter.php">
                        <div class="section-title">Personal Identification</div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label required" for="name">Full Name</label><input class="form-control" id="name" name="name" value="<?= interviewValue($formValues['name']); ?>" required></div>
                            <div class="col-md-6"><label class="form-label required" for="email">Email Address</label><input type="email" class="form-control" id="email" name="email" value="<?= interviewValue($formValues['email']); ?>" required></div>
                            <div class="col-md-6"><label class="form-label required" for="id_number">National ID / Passport Number</label><input class="form-control" id="id_number" name="id_number" value="<?= interviewValue($formValues['id_number']); ?>" required></div>
                            <div class="col-md-6"><label class="form-label" for="phone">Phone Number</label><input class="form-control" id="phone" name="phone" value="<?= interviewValue($formValues['phone']); ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="date_of_birth">Date of Birth</label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= interviewValue($formValues['date_of_birth']); ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="nationality">Nationality</label><input class="form-control" id="nationality" name="nationality" value="<?= interviewValue($formValues['nationality']); ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="marital_status">Marital Status</label><select class="form-select" id="marital_status" name="marital_status"><option value="">Select</option><?php foreach (['Single','Married','Divorced','Widowed','Prefer not to say'] as $status): ?><option value="<?= $status; ?>" <?= $formValues['marital_status'] === $status ? 'selected' : ''; ?>><?= $status; ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label required" for="role">Position Applied For</label><select class="form-select" id="role" name="role" required><?php foreach (['Loan Officer','Manager','Admin'] as $role): ?><option value="<?= $role; ?>" <?= $formValues['role'] === $role ? 'selected' : ''; ?>><?= $role; ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label" for="interview_date">Interview Date</label><input type="date" class="form-control" id="interview_date" name="interview_date" value="<?= interviewValue($formValues['interview_date']); ?>"></div>
                        </div>
                        <div class="section-title">Residential and Emergency Information</div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="residence">Current Residence</label><textarea class="form-control" id="residence" name="residence" rows="2"><?= interviewValue($formValues['residence']); ?></textarea></div>
                            <div class="col-md-6"><label class="form-label" for="permanent_address">Permanent Address</label><textarea class="form-control" id="permanent_address" name="permanent_address" rows="2"><?= interviewValue($formValues['permanent_address']); ?></textarea></div>
                            <div class="col-md-4"><label class="form-label" for="next_of_kin">Next of Kin</label><input class="form-control" id="next_of_kin" name="next_of_kin" value="<?= interviewValue($formValues['next_of_kin']); ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="next_of_kin_relationship">Relationship</label><input class="form-control" id="next_of_kin_relationship" name="next_of_kin_relationship" value="<?= interviewValue($formValues['next_of_kin_relationship']); ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="next_of_kin_phone">Next of Kin Phone</label><input class="form-control" id="next_of_kin_phone" name="next_of_kin_phone" value="<?= interviewValue($formValues['next_of_kin_phone']); ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="emergency_contact">Emergency Contact</label><input class="form-control" id="emergency_contact" name="emergency_contact" value="<?= interviewValue($formValues['emergency_contact']); ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="emergency_relationship">Relationship</label><input class="form-control" id="emergency_relationship" name="emergency_relationship" value="<?= interviewValue($formValues['emergency_relationship']); ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="emergency_phone">Emergency Phone</label><input class="form-control" id="emergency_phone" name="emergency_phone" value="<?= interviewValue($formValues['emergency_phone']); ?>"></div>
                        </div>
                        <div class="section-title">Education and Professional Background</div>
                        <?php for ($row = 1; $row <= 2; $row++): ?><div class="row g-3 mb-2"><div class="col-md-3"><input class="form-control" name="qualification_<?= $row; ?>" placeholder="Qualification" value="<?= interviewValue($formValues['qualification_' . $row]); ?>"></div><div class="col-md-3"><input class="form-control" name="institution_<?= $row; ?>" placeholder="Institution" value="<?= interviewValue($formValues['institution_' . $row]); ?>"></div><div class="col-md-2"><input class="form-control" name="year_<?= $row; ?>" placeholder="Year" value="<?= interviewValue($formValues['year_' . $row]); ?>"></div><div class="col-md-4"><input class="form-control" name="certificate_<?= $row; ?>" placeholder="Certificate / professional body" value="<?= interviewValue($formValues['certificate_' . $row]); ?>"></div></div><?php endfor; ?>
                        <div class="section-title">Previous Employment</div>
                        <p class="small text-muted">List the current or most recent employer first, followed by other places where the interviewee has worked.</p>
                        <?php for ($row = 1; $row <= 3; $row++): ?><div class="border rounded p-3 mb-3"><div class="small fw-bold mb-2">Employment <?= $row; ?></div><div class="row g-2"><div class="col-md-3"><input class="form-control" name="employment_period_<?= $row; ?>" placeholder="Period" value="<?= interviewValue($formValues['employment_period_' . $row]); ?>"></div><div class="col-md-3"><input class="form-control" name="employer_<?= $row; ?>" placeholder="Employer and location" value="<?= interviewValue($formValues['employer_' . $row]); ?>"></div><div class="col-md-2"><input class="form-control" name="position_<?= $row; ?>" placeholder="Position" value="<?= interviewValue($formValues['position_' . $row]); ?>"></div><div class="col-md-2"><input class="form-control" name="duties_<?= $row; ?>" placeholder="Responsibilities" value="<?= interviewValue($formValues['duties_' . $row]); ?>"></div><div class="col-md-2"><input class="form-control" name="reason_<?= $row; ?>" placeholder="Reason for leaving" value="<?= interviewValue($formValues['reason_' . $row]); ?>"></div></div></div><?php endfor; ?>
                        <div class="section-title">References and Interview Notes</div>
                        <div class="row g-3">
                            <?php for ($row = 1; $row <= 2; $row++): ?><div class="col-md-6"><div class="border rounded p-3"><div class="small fw-bold mb-2">Reference <?= $row; ?></div><input class="form-control mb-2" name="reference_<?= $row; ?>" placeholder="Name" value="<?= interviewValue($formValues['reference_' . $row]); ?>"><input class="form-control mb-2" name="reference_org_<?= $row; ?>" placeholder="Organisation / relationship" value="<?= interviewValue($formValues['reference_org_' . $row]); ?>"><input class="form-control" name="reference_contact_<?= $row; ?>" placeholder="Phone / email" value="<?= interviewValue($formValues['reference_contact_' . $row]); ?>"></div></div><?php endfor; ?>
                            <div class="col-12"><label class="form-label" for="interview_notes">Interview Notes</label><textarea class="form-control" id="interview_notes" name="interview_notes" rows="4"><?= interviewValue($formValues['interview_notes']); ?></textarea></div>
                        </div>
                        <button type="submit" name="send_interview_letter" class="btn btn-primary btn-lg w-100 mt-4">Send Interview and KYC Form</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="preview-panel">
                    <div class="section-title">Document Preview</div>
                    <p class="text-muted">The interviewee will receive a formal, branded two-page PDF for completion and review.</p>
                    <div class="preview-line"><strong>Interviewee:</strong> <?= interviewValue($formValues['name']) ?: 'Not entered'; ?></div>
                    <div class="preview-line"><strong>Position:</strong> <?= interviewValue($formValues['role']); ?></div>
                    <div class="preview-line"><strong>Required KYC:</strong> Identity, nationality, residence, contacts and consent</div>
                    <div class="preview-line"><strong>Background:</strong> Education, three employment records and references</div>
                    <div class="preview-line"><strong>Issued by:</strong> <?= interviewValue($manager['name']); ?></div>
                    <div class="alert alert-light border mt-4">The form contains confidential personal information. Share and retain it only for authorized recruitment and compliance purposes.</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
