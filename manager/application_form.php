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

function getBorrowersForApplicationForm($conn) {
    $borrowers = [];
    $result = $conn->query("SELECT b.id, b.full_name, b.mobile, b.unique_number, b.email, '' AS address, b.loan_officer,
                                   COALESCE(u.name, 'Unassigned') AS loan_officer_name,
                                   COALESCE(u.email, '') AS loan_officer_email
                            FROM borrowers b
                            LEFT JOIN users u ON b.loan_officer = u.email
                            ORDER BY b.full_name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $borrowers[] = $row;
        }
    }
    return $borrowers;
}

function getLoanOfficersForApplicationForm($conn) {
    $officers = [];
    $result = $conn->query("SELECT id, name, email FROM users WHERE role_id = 2 ORDER BY name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $officers[] = $row;
        }
    }
    return $officers;
}

function getManagerProfileForApplicationForm($conn) {
    $profile = ['name' => 'Inua Premium Services', 'email' => 'info@inuapremium.co.ke', 'phone' => 'N/A', 'region' => 'Unassigned'];
    $email = trim((string) ($_SESSION['email'] ?? ''));
    if ($email === '') {
        return $profile;
    }

    $stmt = $conn->prepare("SELECT u.name, u.email, u.phone, COALESCE(a.area_name, 'Unassigned') AS region_name
                            FROM users u LEFT JOIN areas a ON u.area = a.area_id
                            WHERE u.email = ? LIMIT 1");
    if ($stmt) {
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

function applicationFormValue($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function generateLoanApplicationPdf($details, $manager) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor($manager['name']);
    $pdf->SetTitle('Loan Application Form');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(false, 15);

    $addHeader = function () use ($pdf, $manager, $details) {
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
        $pdf->Cell(0, 5, 'Loan Application Department', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Tel: ' . $manager['phone'] . ' | Email: ' . $manager['email'], 0, 1, 'L');
        $pdf->Cell(0, 5, 'Region: ' . $manager['region'], 0, 1, 'L');
        $pdf->SetFont('helvetica', 'BI', 9);
        $pdf->Cell(0, 5, 'Loan Officer: ' . $details['loan_officer_name'], 0, 1, 'L');
        $pdf->Ln(4);
    };

    $safe = function ($value) {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    };

    $section = function ($title) use ($pdf) {
        $pdf->SetFillColor(11, 47, 159);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, $title, 1, 1, 'L', true);
        $pdf->SetTextColor(40, 40, 40);
    };

    $table = function ($html) use ($pdf) {
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->writeHTML($html, true, false, true, false, 'L');
    };

    $pdf->AddPage();
    $addHeader();
    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'LOAN AGREEMENT FORM', 0, 1, 'C');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(0, 5, 'This loan agreement is made and effective on ____/____/________ (DD/MM/YYYY)', 0, 1, 'C');
    $pdf->Cell(0, 5, 'BETWEEN INUA PREMIUM SERVICES (the "Lender") AND ______________________________ (the "Borrower")', 0, 1, 'C');
    $pdf->Cell(0, 5, 'National ID: ______________________________    Mobile: ______________________________', 0, 1, 'C');
    $pdf->Ln(3);

    $section('TERMS AND CONDITIONS');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Liability:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'Although this agreement may be signed by more than one person, each undersigned understands that they are individually responsible and jointly and severally liable for paying back the full amount.', 0, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'THE BORROWER\'S PLEDGE', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'I am applying for a loan of Ksh. ______________________________ only, to be repaid in ______________________________ from the effective date. I irrevocably assign all rights, title and interest in the assets listed below (the "Assets") to the Lender.', 0, 'L');
    $pdf->Ln(2);

    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><th width="8%">No.</th><th width="27%"><b>Item<br>(Business or Household Item)</b></th><th width="25%"><b>Description<br>(Make and Model)</b></th><th width="20%"><b>Identification<br>(Serial No.)</b></th><th width="20%"><b>Estimate Market<br>Value (Ksh)</b></th></tr>'
        . '<tr><td>1.</td><td></td><td></td><td></td><td></td></tr><tr><td>2.</td><td></td><td></td><td></td><td></td></tr><tr><td>3.</td><td></td><td></td><td></td><td></td></tr><tr><td>4.</td><td></td><td></td><td></td><td></td></tr><tr><td>5.</td><td></td><td></td><td></td><td></td></tr><tr><td colspan="4" align="right"><b>Total Value (Ksh)</b></td><td></td></tr></table>');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'Details of Loan and Repayment: Agreed between Borrower and Lender', 0, 1, 'L');
    $table('<table border="1" cellpadding="2.5"><tr bgcolor="#e8edff"><th width="35%"><b>Installment</b></th><th width="30%"><b>Date (DD/MM/YYYY)</b></th><th width="35%"><b>Repayment Amount (Ksh.)</b></th></tr>'
        . '<tr><td>Loan Issued</td><td>____/____/________</td><td></td></tr>'
        . '<tr><td>First Installment Repayment</td><td></td><td></td></tr><tr><td>Second Installment Repayment</td><td></td><td></td></tr><tr><td>Third Installment Repayment</td><td></td><td></td></tr><tr><td>Fourth Installment Repayment</td><td></td><td></td></tr><tr><td>Fifth Installment Repayment</td><td></td><td></td></tr><tr><td>Sixth Installment Repayment</td><td></td><td></td></tr><tr><td>Seventh Installment Repayment</td><td></td><td></td></tr><tr><td>Eighth Installment Repayment</td><td></td><td></td></tr></table>');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Payment of loan:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'The borrower has the right to pay back the whole or exceptional amount at any time. If the borrower pays before time, no penalties will be charged or interest refunded.', 0, 'L');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Late Charges:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'Any payment not remunerated within one day of its due date shall be subject to a belated payment charge in accordance with the applicable loan terms.', 0, 'L');

    $pdf->AddPage();
    $addHeader();
    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'LOAN AGREEMENT FORM - CONSENT AND APPROVAL', 0, 1, 'C');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Ln(3);
    $section('CRB CONSENT');
    $pdf->SetFont('helvetica', '', 8.5);
    $consentText = implode("\n", [
        'a) I warrant that the information given in this application is true and complete and authorize the Lender to make any enquiries necessary in connection with this application.',
        'b) I hereby confirm that Inua Premium Services may share my credit information and access my credit profile and those of my guarantors for credit appraisal and credit reference bureau purposes.',
        'c) I release the Lender and its officers, employees and agents from liability arising from any unauthorized disclosure or use of information where permitted by law.',
        'd) I agree to comply with the terms and conditions of the Credit Application and Loan Agreement.'
    ]);
    $pdf->MultiCell(0, 5, $consentText, 0, 'L');
    $pdf->Ln(3);
    $section('THE GUARANTORS');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'Any co-borrowers signing this agreement agree to be likewise accountable with the Borrower of this Loan. The Borrower and the Lender agree to follow the above conditions together with the Credit Facility Application.', 0, 'L');
    $table('<table border="0" cellpadding="2"><tr><td width="50%">1) Next of Kin: __________________________</td><td width="25%">Tel: __________________</td><td width="25%">Relation: ______________</td></tr><tr><td width="50%">2) Guarantor Name: ______________________</td><td width="25%">Tel: __________________</td><td width="25%">ID No.: ________________</td></tr></table>');
    $table('<table border="0" cellpadding="2"><tr><td width="100%"><b>Customer Residence:</b> ________________________________________________________________</td></tr><tr><td width="100%"><b>Business Location:</b> ___________________________________________________________________</td></tr></table>');
    $pdf->Ln(3);
    $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th>Borrower\'s Signature</th><th>Guarantor\'s Signature</th><th>Lender\'s Signature</th><th>Witness Signature</th></tr><tr><td height="14"></td><td></td><td></td><td></td></tr></table>');
    $pdf->Ln(4);
    $section('FOR OFFICIAL USE ONLY');
    $pdf->SetFont('helvetica', '', 8.5);
    $officialUseText = implode("\n", [
        'Credit Committee',
        'Application Originated by: ' . $safe($details['loan_officer_name']) . '    Signature: __________________ Date: __________',
        'Comment: _______________________________________________________________________',
        '',
        'Loan Approval:',
        'Relationship Officer: ' . $safe($details['loan_officer_name']) . '    Signature: __________________ Date: __________',
        '[ ] Approved        [ ] Rejected        [ ] Deferred',
        'Comment: _______________________________________________________________________',
        '',
        'Branch Manager: ' . $safe($manager['name']) . '    Signature: __________________ Date: __________',
        '[ ] Approved        [ ] Rejected        [ ] Deferred',
        'Comment: _______________________________________________________________________'
    ]);
    $pdf->MultiCell(0, 5, $officialUseText, 0, 'L');
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Results of the Evaluation:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(0, 5, '[ ] Approved              [ ] Rejected              [ ] Deferred', 0, 1, 'L');

    $evaluationStampY = $pdf->GetY();
    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    if (file_exists($stampPath)) {
        $pdf->SetAlpha(0.45);
        $pdf->Image($stampPath, 145, $evaluationStampY, 38, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
        $pdf->SetY($evaluationStampY + 24);
    }

    $pdf->Cell(0, 5, 'Amount Approved: __________________________    Loan Period: __________________________', 0, 1, 'L');
    $pdf->Line(15, 270, 195, 270);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'This document is an official loan agreement form issued by Inua Premium Services.', 0, 1, 'C');

    return $pdf->Output('', 'S');
}

function sendLoanApplicationEmail($recipientEmail, $pdfContent, $filename, $clientName) {
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
    $mail->addAddress($recipientEmail);
    $mail->isHTML(true);
    $mail->Subject = 'Loan Application Form - ' . $clientName;
    $mail->Body = '<p>Dear Loan Officer,</p><p>Please find attached the two-page loan application form for <strong>' . htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') . '</strong> for completion and review.</p><p>Regards,<br>Inua Premium Services</p>';
    $mail->AltBody = 'Please find attached the two-page loan application form for ' . $clientName . ' for completion and review.';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$loanOfficers = getLoanOfficersForApplicationForm($conn);
$selectedOfficer = null;
$sendMessage = '';
$sendStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_application_form'])) {
    $officerId = (int) ($_POST['officer_id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? AND role_id = 2 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $officerId);
        $stmt->execute();
        $selectedOfficer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$selectedOfficer) {
        $sendMessage = 'Please select a valid loan officer.';
        $sendStatus = 'danger';
    } elseif (!filter_var($selectedOfficer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $sendMessage = 'The selected loan officer does not have a valid email address.';
        $sendStatus = 'danger';
    } else {
        $details = [
            'full_name' => '', 'unique_number' => '', 'mobile' => '', 'email' => '', 'address' => '',
            'occupation' => '', 'employer' => '', 'monthly_income' => '', 'loan_amount' => '',
            'loan_purpose' => '', 'duration' => '', 'repayment_cycle' => '', 'repayment_source' => '',
            'next_of_kin' => '', 'next_of_kin_relationship' => '', 'next_of_kin_phone' => '',
            'loan_officer_name' => $selectedOfficer['name'] ?: 'Unassigned',
        ];

        try {
            $manager = getManagerProfileForApplicationForm($conn);
            $pdf = generateLoanApplicationPdf($details, $manager);
            sendLoanApplicationEmail($selectedOfficer['email'], $pdf, 'loan_application_form.pdf', 'Selected Loan Officer');
            $sendMessage = 'The blank two-page loan application form was sent to ' . $selectedOfficer['name'] . '.';
            $sendStatus = 'success';
        } catch (Exception $e) {
            $sendMessage = 'The form could not be sent: ' . $e->getMessage();
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
    <title>Send Loan Application Form</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background: #f8f8f8; color: #282828; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-container { max-width: 1180px; margin: 30px auto; padding: 24px; }
        .shell { background: #fff; border-radius: 18px; box-shadow: 0 12px 28px rgba(0,0,0,.08); }
        .company-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #ef4444; padding-bottom:18px; margin-bottom:24px; }
        .company-header img { height:70px; width:auto; object-fit:contain; }
        .form-panel { background:#fff7f7; border:1px solid #f4d1d1; border-radius:12px; padding:20px; }
        .preview-panel { background:#fff; border:1px solid #e7e7e7; border-radius:16px; padding:28px; min-height:100%; }
        .section-title { color:#0b2f9f; font-size:1.05rem; font-weight:700; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid #ef4444; }
        .form-label { font-weight:600; }
        .form-control, .form-select { border-radius:8px; border-color:#d9d9d9; }
        .form-control:focus, .form-select:focus { border-color:#0b2f9f; box-shadow:0 0 0 .2rem rgba(11,47,159,.12); }
        .btn-primary { background:linear-gradient(135deg,#e84545,#ff6b6b); border:none; }
        .required::after { content:' *'; color:#e84545; }
        .preview-line { border-bottom:1px solid #d9d9d9; padding:8px 0; }
        @media (max-width:768px) { .page-container { margin:10px auto; padding:10px; } .company-header img { height:52px; } }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="page-container">
    <div class="shell p-4">
        <div class="company-header">
            <div><div class="fw-bold text-uppercase text-secondary" style="letter-spacing:1px;">Inua Premium Services</div><div class="text-muted small">Loan Application Department</div></div>
            <img src="../assets/img/logo.png" alt="Inua Premium Services Logo">
        </div>

        <?php if ($sendMessage): ?><div class="alert alert-<?= htmlspecialchars($sendStatus); ?>"><?= htmlspecialchars($sendMessage); ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div><h2 class="mb-1">Send Loan Application Form</h2><div class="text-muted">Complete the customer details and send the two-page form to the assigned loan officer.</div></div>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="form-panel">
                    <div class="section-title">Send To Loan Officer</div>
                    <form method="post" action="application_form.php">
                        <div class="mb-3">
                            <label for="officerSelect" class="form-label required">Select Loan Officer</label>
                            <select class="form-select" name="officer_id" id="officerSelect" required>
                                <option value="">Choose a loan officer</option>
                                <?php foreach ($loanOfficers as $officer): ?>
                                    <option value="<?= (int) $officer['id']; ?>" data-officer-name="<?= applicationFormValue($officer['name']); ?>" data-officer-email="<?= applicationFormValue($officer['email']); ?>">
                                        <?= htmlspecialchars($officer['name']); ?> - <?= htmlspecialchars($officer['email']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-light border">The selected officer will receive a blank two-page loan application form and will write the client details manually.</div>
                        <button type="submit" name="send_application_form" class="btn btn-primary btn-lg w-100 mt-4">Send Form to Loan Officer</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="preview-panel">
                    <div class="section-title">Document Preview</div>
                    <p class="text-muted">The sent PDF will contain exactly two pages and will be addressed to the selected loan officer.</p>
                    <div class="preview-line"><strong>Selected Officer:</strong> <span id="officerName">Not selected</span></div>
                    <div class="preview-line"><strong>Officer Email:</strong> <span id="officerEmail">Not selected</span></div>
                    <div class="preview-line"><strong>Page 1:</strong> Agreement, client credentials, assets, and repayment schedule</div>
                    <div class="preview-line"><strong>Page 2:</strong> Consent, guarantors, signatures, and approvals</div>
                    <div class="alert alert-light border mt-4">The officer will complete the client name, date, phone number, loan amount, duration, residence, and business location by hand.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('officerSelect').addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        document.getElementById('officerName').textContent = option.dataset.officerName || 'Not selected';
        document.getElementById('officerEmail').textContent = option.dataset.officerEmail || 'Not selected';
    });
</script>
</body>
</html>
