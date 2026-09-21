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

function getTerminationManager($conn) {
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

function getTerminationStaff($conn) {
    $staff = [];
    $result = $conn->query("SELECT u.id, u.name, u.email, u.phone, u.role_id, COALESCE(r.name, 'Unassigned Role') AS role_name
                            FROM users u LEFT JOIN roles r ON r.id = u.role_id
                            WHERE u.email IS NOT NULL AND u.email <> '' ORDER BY u.name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
    }
    return $staff;
}

function terminationValue($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function terminationDate($value, $fallback = 'Not specified') {
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date ? $date->format('d/m/Y') : $fallback;
}

function getFourMonthLoanPerformance($conn, $employeeEmail, $periodEnd) {
    $periodEndDate = DateTime::createFromFormat('Y-m-d', $periodEnd) ?: new DateTime();
    $periodStartDate = (clone $periodEndDate)->modify('-4 months')->modify('+1 day');
    $periodStart = $periodStartDate->format('Y-m-d');
    $periodEnd = $periodEndDate->format('Y-m-d');
    $metrics = [
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'monthly_salary' => null,
        'disbursed_amount' => 0,
        'interest_generated' => 0,
        'loan_count' => 0
    ];
    $salaryColumnCheck = $conn->query("SELECT COUNT(*) AS column_count FROM INFORMATION_SCHEMA.COLUMNS
                                      WHERE TABLE_SCHEMA = DATABASE()
                                        AND TABLE_NAME = 'users'
                                        AND COLUMN_NAME = 'basic_salary'");
    $hasSalaryColumn = $salaryColumnCheck && (int) $salaryColumnCheck->fetch_assoc()['column_count'] > 0;
    if ($hasSalaryColumn) {
        $salaryStmt = $conn->prepare('SELECT COALESCE(basic_salary, 0) AS monthly_salary FROM users WHERE email = ? LIMIT 1');
        if ($salaryStmt) {
            $salaryStmt->bind_param('s', $employeeEmail);
            $salaryStmt->execute();
            $salaryRow = $salaryStmt->get_result()->fetch_assoc();
            $salaryStmt->close();
            if ($salaryRow) {
                $metrics['monthly_salary'] = (float) ($salaryRow['monthly_salary'] ?? 0);
            }
        }
    }
    $stmt = $conn->prepare("SELECT
                COUNT(la.id) AS loan_count,
                COALESCE(SUM(la.principal), 0) AS disbursed_amount,
                COALESCE(SUM(GREATEST(la.total_amount - la.principal, 0)), 0) AS interest_generated
            FROM borrowers b
            INNER JOIN loan_applications la ON la.borrower = b.id
            WHERE b.loan_officer = ?
              AND la.loan_status = 'approved'
              AND la.loan_release_date BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param('sss', $employeeEmail, $periodStart, $periodEnd);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $metrics['loan_count'] = (int) ($row['loan_count'] ?? 0);
            $metrics['disbursed_amount'] = (float) ($row['disbursed_amount'] ?? 0);
            $metrics['interest_generated'] = (float) ($row['interest_generated'] ?? 0);
        }
    }
    return $metrics;
}

function terminationReasonDetails($reason) {
    $details = [
        'Fraud' => [
            'heading' => 'Fraud or dishonest conduct',
            'summary' => 'This notice concerns allegations or findings of fraud, dishonesty, misrepresentation, or deliberate misuse of company or client information or funds.',
            'action' => 'The employee is required to cooperate with the investigation and return all company property, records, funds, and access credentials. The Employer reserves all rights available under the employment agreement and applicable law.'
        ],
        'Conflict of Interest' => [
            'heading' => 'Conflict of interest',
            'summary' => 'This notice concerns an actual, potential, or undisclosed conflict of interest that is inconsistent with the employee\'s duty of loyalty, transparency, and professional conduct.',
            'action' => 'The employee is required to disclose all relevant information, complete a proper handover, and avoid any further action that may compromise the Employer, its clients, or its confidential information.'
        ],
        'Theft' => [
            'heading' => 'Theft or unauthorized appropriation',
            'summary' => 'This notice concerns the alleged or established theft, unauthorized taking, conversion, or misuse of company, client, or colleague property.',
            'action' => 'The employee must return all property and records immediately. The Employer reserves the right to pursue recovery, disciplinary action, and any lawful report or remedy arising from the conduct.'
        ],
        'Voluntary Request' => [
            'heading' => 'Voluntary separation request',
            'summary' => 'This notice records the employee\'s voluntary request to end the employment relationship, subject to the applicable notice period, handover, clearance, and final settlement procedures.',
            'action' => 'The employee shall complete the agreed handover, return company property, settle authorized obligations, and remain bound by confidentiality and other continuing obligations after separation.'
        ],
        'Unbecoming Behavior' => [
            'heading' => 'Unbecoming or unprofessional behavior',
            'summary' => 'This notice concerns conduct that falls below the standards of professionalism, respect, integrity, dignity, or workplace behavior required by Inua Premium Services.',
            'action' => 'The employee is required to complete a full handover and return company property. The Employer may take any further action permitted by the employment agreement, company procedure, and applicable law.'
        ],
        'Poor Performance' => [
            'heading' => 'Poor or unsatisfactory performance',
            'summary' => 'This notice concerns performance that has remained below the reasonable standards, duties, targets, or service requirements of the employee\'s role after review and reasonable performance-management support.',
            'action' => 'The employee shall complete a proper handover and return company property. Any final decision and notice arrangements remain subject to the employment agreement, documented process, and applicable law.'
        ]
    ];
    return $details[$reason] ?? $details['Poor Performance'];
}

function generateTerminationPdf($employee, $manager, $reason, $effectiveDate, $noticeDate, $details, $additionalNotes, $awarenessNotice = false, $performanceMetrics = null) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor($manager['name']);
    $pdf->SetTitle(($awarenessNotice ? 'Staff Conduct Awareness Notice - ' : 'Termination Letter - ') . $employee['name']);
    $pdf->SetMargins(15, 16, 15);
    $pdf->SetAutoPageBreak(false, 18);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $safe = function ($value) {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    };
    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    $header = function () use ($pdf, $manager) {
        $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 150, 10, 40, 0, 'PNG');
        }
        $pdf->SetDrawColor(0, 47, 196);
        $pdf->SetLineWidth(1.1);
        $pdf->Line(15, 13, 145, 13);
        $pdf->SetDrawColor(210, 0, 0);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, 15, 145, 15);
        $pdf->SetTextColor(0, 47, 196);
        $pdf->SetFont('helvetica', 'B', 17);
        $pdf->Cell(0, 9, 'INUA PREMIUM SERVICES', 0, 1, 'L');
        $pdf->SetTextColor(45, 45, 45);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell(0, 5, 'Human Resources and Administration Department', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Tel: ' . $manager['phone'] . ' | Email: ' . $manager['email'], 0, 1, 'L');
        $pdf->Cell(0, 5, 'Region: ' . $manager['region'], 0, 1, 'L');
        $pdf->Ln(6);
    };
    $section = function ($title) use ($pdf) {
        $pdf->Ln(3);
        $pdf->SetFillColor(11, 47, 159);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell(0, 7, $title, 1, 1, 'L', true);
        $pdf->SetTextColor(45, 45, 45);
        $pdf->Ln(1);
    };
    $footer = function () use ($pdf) {
        $pdf->SetDrawColor(160, 160, 160);
        $pdf->Line(15, 276, 195, 276);
        $pdf->SetFont('helvetica', 'I', 7.2);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY(15, 278);
        $pdf->Cell(180, 4, 'Confidential employment document | Inua Premium Services', 0, 0, 'C');
    };
    $table = function ($html) use ($pdf) {
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->writeHTML($html, true, false, true, false, 'L');
    };

    $pdf->AddPage();
    $header();
    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, $awarenessNotice ? 'STAFF CONDUCT AND CONSEQUENCES NOTICE' : 'FORMAL TERMINATION NOTICE', 0, 1, 'C');
    $pdf->SetTextColor(45, 45, 45);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Reference: TL-' . date('YmdHis') . '    Date: ' . date('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<b>To:</b> ' . $safe($employee['name']) . '<br><b>Email:</b> ' . $safe($employee['email']) . '<br><b>Role:</b> ' . $safe($employee['role_name']), true, false, true, false, 'L');
    $pdf->Ln(4);
    $pdf->writeHTML('<b>Subject: ' . ($awarenessNotice ? 'Workplace conduct and employment consequences' : 'Notice of termination of employment') . '</b>', true, false, true, false, 'L');
    $pdf->Ln(3);
    $pdf->writeHTML('Dear ' . $safe($employee['name']) . ',', true, false, true, false, 'L');
    $pdf->Ln(2);
    $opening = $awarenessNotice
        ? 'This notice is issued to all staff as a formal reminder of the standards expected at Inua Premium Services. It explains the consequences associated with <b>' . $safe($reason) . '</b> and is intended to prevent conduct that may harm clients, colleagues, or the organization.'
        : 'We write on behalf of Inua Premium Services to formally notify you of the Employer\'s decision regarding the termination of your employment. This notice has been prepared for the selected reason of <b>' . $safe($reason) . '</b>.';
    $pdf->writeHTML($opening, true, false, true, false, 'L');
    $section($awarenessNotice ? '1. STAFF AWARENESS PARTICULARS' : '1. TERMINATION PARTICULARS');
    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><td width="30%"><b>Recipient</b></td><td width="70%">' . $safe($employee['name']) . '</td></tr><tr><td><b>Position / role</b></td><td>' . $safe($employee['role_name']) . '</td></tr><tr><td><b>Date issued</b></td><td>' . date('d/m/Y') . '</td></tr>' . ($awarenessNotice ? '' : '<tr><td><b>Notice date</b></td><td>' . $safe(terminationDate($noticeDate)) . '</td></tr><tr><td><b>Effective separation date</b></td><td>' . $safe(terminationDate($effectiveDate)) . '</td></tr>') . '<tr><td><b>Subject</b></td><td>' . $safe($details['heading']) . '</td></tr></table>');
    $section($awarenessNotice ? '2. WHY THIS MATTERS' : '2. REASON FOR THE NOTICE');
    $pdf->SetFont('helvetica', '', 9.5);
    $pdf->writeHTML($safe($details['summary']), true, false, true, false, 'L');
    $pdf->Ln(3);
    if (!$awarenessNotice && $reason === 'Poor Performance' && is_array($performanceMetrics)) {
        $section('PERFORMANCE SUMMARY FOR THE LAST FOUR MONTHS');
        $salaryDisplay = $performanceMetrics['monthly_salary'] === null ? 'Not recorded' : 'KES ' . number_format((float) $performanceMetrics['monthly_salary'], 2) . ' per month';
        $salaryEquivalent = $performanceMetrics['monthly_salary'] === null ? 'Not available' : 'KES ' . number_format((float) $performanceMetrics['monthly_salary'] * 4, 2);
        $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><td width="35%"><b>Performance period</b></td><td width="65%">' . $safe(terminationDate($performanceMetrics['period_start']) . ' to ' . terminationDate($performanceMetrics['period_end'])) . '</td></tr><tr><td><b>Monthly salary</b></td><td>' . $safe($salaryDisplay) . '</td></tr><tr><td><b>Four-month salary equivalent</b></td><td>' . $safe($salaryEquivalent) . '</td></tr><tr><td><b>Approved loans disbursed</b></td><td>' . number_format((int) $performanceMetrics['loan_count']) . ' loans | KES ' . number_format((float) $performanceMetrics['disbursed_amount'], 2) . '</td></tr><tr><td><b>Interest generated</b></td><td>KES ' . number_format((float) $performanceMetrics['interest_generated'], 2) . '</td></tr></table>');
        $pdf->SetFont('helvetica', '', 9.2);
        $pdf->writeHTML('The figures above are drawn from approved loans recorded under your portfolio during the stated four-month period. They are provided to support a transparent performance discussion and should be considered together with collection quality, arrears, documentation, customer service, compliance, and the performance expectations of your role.', true, false, true, false, 'L');
    }
    if ($awarenessNotice) {
        $pdf->writeHTML('Any employee who engages in this conduct may face investigation, disciplinary action, loss of employment, recovery of losses, reporting to the appropriate authorities, civil or criminal proceedings, or other lawful consequences. Staff must ask for guidance before acting where a conflict, client-risk, compliance, or ethical concern may arise.', true, false, true, false, 'L');
        $section('3. REQUIRED STAFF CONDUCT');
        $pdf->writeHTML('All staff must act honestly, disclose conflicts of interest, protect company and client property, treat others respectfully, meet role expectations, follow approved procedures, keep accurate records, protect confidential information, and report suspected misconduct or risk promptly. No employee may retaliate against a person who raises a concern in good faith.', true, false, true, false, 'L');
    } else {
        $pdf->writeHTML('The Employer will administer this matter in accordance with the employment agreement, applicable company policies and procedures, and the applicable employment laws of Kenya. Where a disciplinary process, hearing, investigation, notice, or opportunity to respond is required, it shall be handled through the appropriate process.', true, false, true, false, 'L');
        $section('3. FINAL DUTIES AND HANDOVER');
        $pdf->writeHTML($safe($details['action']), true, false, true, false, 'L');
        $pdf->Ln(2);
        $pdf->writeHTML('', true, false, true, false, 'L');
    }
    if (!$awarenessNotice && trim($additionalNotes) !== '') {
        $section('4. ADDITIONAL MANAGEMENT NOTES');
        $pdf->writeHTML($safe($additionalNotes), true, false, true, false, 'L');
    }
    $footer();

    $pdf->AddPage();
    $header();
    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, $awarenessNotice ? 'STAFF CONDUCT AND CONSEQUENCES NOTICE' : 'FORMAL TERMINATION NOTICE', 0, 1, 'C');
    $pdf->SetTextColor(45, 45, 45);
    $section($awarenessNotice ? '4. ACKNOWLEDGEMENT AND PREVENTION' : '5. CONTINUING OBLIGATIONS');
    $pdf->SetFont('helvetica', '', 9.2);
    $pdf->writeHTML($awarenessNotice ? 'Every employee is expected to understand this notice, avoid the conduct described, seek guidance when uncertain, and contribute to a culture of integrity, accountability, customer protection, and respect.' : 'Termination does not release either party from obligations that are intended to continue after separation. You must continue to protect confidential client, financial, operational, employee, and business information; return or delete records as directed; avoid unauthorized use of company systems; and cooperate with any lawful audit, investigation, reconciliation, or handover request.', true, false, true, false, 'L');
    $section($awarenessNotice ? '5. ACKNOWLEDGEMENT OF RECEIPT' : '6. ACKNOWLEDGEMENT OF RECEIPT');
    $pdf->writeHTML($awarenessNotice ? 'Your signature confirms that you received and understood this staff awareness notice. It does not indicate that you are being terminated. You are required to comply with the organization\'s policies, standards, and procedures.' : 'Your signature below confirms receipt of this notice. It does not necessarily mean that you agree with all matters stated in it. You may provide a written response or raise any question through the Human Resources and Administration Department in accordance with the applicable procedure.', true, false, true, false, 'L');
    $pdf->Ln(8);
    $pdf->Cell(92, 6, 'Employee signature: __________________________', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->Ln(7);
    $pdf->Cell(92, 6, 'Witness name: _________________________________', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Signature: __________________________', 0, 1, 'L');
    $pdf->Ln(9);
    $signatureY = $pdf->GetY();
    if (file_exists($stampPath)) {
        $pdf->SetAlpha(0.42);
        $pdf->Image($stampPath, 146, $signatureY - 8, 35, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
    }
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(92, 6, 'For Inua Premium Services', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.8);
    $pdf->Cell(92, 5, 'Authorized representative: ' . $safe($manager['name']), 0, 0, 'L');
    $pdf->Cell(0, 5, 'Signature: __________________________', 0, 1, 'L');
    $pdf->Ln(12);
    $pdf->SetFont('helvetica', 'I', 8.3);
    $pdf->Cell(0, 5, 'This document is issued in accordance with the organization\'s employment procedures.', 0, 1, 'C');
    $footer();

    return $pdf->Output('', 'S');
}

function sendTerminationEmail($employee, $pdfContent, $filename, $reason, $awarenessNotice = false) {
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
    $mail->addAddress($employee['email'], $employee['name']);
    $mail->isHTML(true);
    $mail->Subject = $awarenessNotice ? 'Staff Conduct and Consequences Notice - Inua Premium Services' : 'Formal Termination Notice - Inua Premium Services';
    $mail->Body = $awarenessNotice
        ? '<p>Dear ' . htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') . ',</p><p>Please find attached a formal staff awareness notice for your role as <strong>' . htmlspecialchars($employee['role_name'], ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>The notice explains the prohibited conduct and consequences associated with <strong>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</strong>. Please read it carefully and comply with the organization\'s policies and procedures.</p><p>Regards,<br>Human Resources and Administration Department<br>Inua Premium Services</p>'
        : '<p>Dear ' . htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') . ',</p><p>Please find attached the formal termination notice relating to your employment as <strong>' . htmlspecialchars($employee['role_name'], ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>The notice records the selected reason: <strong>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</strong>. Please review the document and follow the handover and response instructions stated in it.</p><p>Regards,<br>Human Resources and Administration Department<br>Inua Premium Services</p>';
    $mail->AltBody = $awarenessNotice
        ? 'Please find attached the staff conduct and consequences notice for ' . $reason . '. Please read and comply with the organization policies and procedures.'
        : 'Please find attached the formal termination notice. Selected reason: ' . $reason . '. Please review and follow the instructions in the notice.';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$manager = getTerminationManager($conn);
$staffList = getTerminationStaff($conn);
$reasons = ['Fraud', 'Conflict of Interest', 'Theft', 'Voluntary Request', 'Unbecoming Behavior', 'Poor Performance'];
$sendMessage = '';
$sendStatus = '';
$formValues = ['staff_id' => '', 'reason' => 'Poor Performance', 'effective_date' => date('Y-m-d'), 'notice_date' => date('Y-m-d'), 'additional_notes' => ''];
$selectedEmployee = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_termination_letter'])) {
    $formValues['staff_id'] = (int) ($_POST['staff_id'] ?? 0);
    $formValues['reason'] = trim((string) ($_POST['reason'] ?? ''));
    $formValues['effective_date'] = trim((string) ($_POST['effective_date'] ?? ''));
    $formValues['notice_date'] = trim((string) ($_POST['notice_date'] ?? ''));
    $formValues['additional_notes'] = trim((string) ($_POST['additional_notes'] ?? ''));
    foreach ($staffList as $employee) {
        if ((int) $employee['id'] === $formValues['staff_id']) {
            $selectedEmployee = $employee;
            break;
        }
    }
    $awarenessNotice = !in_array($formValues['reason'], ['Voluntary Request', 'Poor Performance'], true);
    if (!$awarenessNotice && (!$selectedEmployee || !filter_var($selectedEmployee['email'], FILTER_VALIDATE_EMAIL))) {
        $sendMessage = 'Select a valid staff member with a valid email address for a voluntary separation request.';
        $sendStatus = 'danger';
    } elseif (!in_array($formValues['reason'], $reasons, true)) {
        $sendMessage = 'Select a valid termination reason.';
        $sendStatus = 'danger';
    } else {
        try {
            $details = terminationReasonDetails($formValues['reason']);
            $recipients = $awarenessNotice ? array_values(array_filter($staffList, function ($employee) {
                return filter_var($employee['email'], FILTER_VALIDATE_EMAIL);
            })) : [$selectedEmployee];
            if (empty($recipients)) {
                throw new Exception('No staff members with valid email addresses were found.');
            }
            foreach ($recipients as $recipient) {
                $performanceMetrics = (!$awarenessNotice && $formValues['reason'] === 'Poor Performance')
                    ? getFourMonthLoanPerformance($conn, $recipient['email'], $formValues['notice_date'])
                    : null;
                $pdf = generateTerminationPdf($recipient, $manager, $formValues['reason'], $formValues['effective_date'], $formValues['notice_date'], $details, $formValues['additional_notes'], $awarenessNotice, $performanceMetrics);
                $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $recipient['name']) ?: 'employee';
                sendTerminationEmail($recipient, $pdf, ($awarenessNotice ? 'staff_conduct_notice_' : 'termination_notice_') . $safeName . '.pdf', $formValues['reason'], $awarenessNotice);
            }
            $sendMessage = $awarenessNotice
                ? 'The reason-specific staff awareness notice was sent to ' . count($recipients) . ' staff members.'
                : 'The voluntary termination notice was sent successfully to ' . $selectedEmployee['name'] . '.';
            $sendStatus = 'success';
        } catch (Exception $e) {
            $sendMessage = 'The termination notice could not be sent: ' . $e->getMessage();
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
    <title>Termination Letter</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background:#f8f8f8; color:#282828; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .page-container { max-width:1100px; margin:30px auto; padding:24px; }
        .shell { background:#fff; border-radius:18px; box-shadow:0 12px 28px rgba(0,0,0,.08); }
        .company-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #ef4444; padding-bottom:18px; margin-bottom:24px; }
        .company-header img { height:70px; width:auto; object-fit:contain; }
        .form-panel { background:#fff7f7; border:1px solid #f4d1d1; border-radius:12px; padding:22px; }
        .preview-panel { background:#fff; border:1px solid #e7e7e7; border-radius:16px; padding:28px; min-height:100%; }
        .section-title { color:#0b2f9f; font-size:1.05rem; font-weight:700; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid #ef4444; }
        .form-label { font-weight:600; }
        .form-control,.form-select { border-radius:8px; border-color:#d9d9d9; }
        .form-control:focus,.form-select:focus { border-color:#0b2f9f; box-shadow:0 0 0 .2rem rgba(11,47,159,.12); }
        .btn-primary { background:linear-gradient(135deg,#e84545,#ff6b6b); border:none; }
        .preview-line { border-bottom:1px solid #d9d9d9; padding:9px 0; }
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
            <div><h2 class="mb-1">Termination Letter</h2><div class="text-muted">Prepare and email a formal termination notice to selected staff.</div></div>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="form-panel">
                    <div class="section-title">Termination Details</div>
                    <form method="post" action="termination_letter.php">
                        <div class="mb-3"><label class="form-label" for="staff_id">Select Staff Member</label><select class="form-select" id="staff_id" name="staff_id"><option value="">Choose staff member (required for private notices)</option><?php foreach ($staffList as $employee): ?><option value="<?= (int) $employee['id']; ?>" data-name="<?= terminationValue($employee['name']); ?>" data-email="<?= terminationValue($employee['email']); ?>" data-role="<?= terminationValue($employee['role_name']); ?>" <?= (int) $formValues['staff_id'] === (int) $employee['id'] ? 'selected' : ''; ?>><?= terminationValue($employee['name']); ?> - <?= terminationValue($employee['role_name']); ?> (<?= terminationValue($employee['email']); ?>)</option><?php endforeach; ?></select><div class="form-text" id="recipientHelp">Poor Performance and Voluntary Request require a selected employee and are sent privately. Other reasons send awareness notices to all valid staff emails.</div></div>
                        <div class="mb-3"><label class="form-label" for="reason">Reason for Termination</label><select class="form-select" id="reason" name="reason" required><?php foreach ($reasons as $reason): ?><option value="<?= terminationValue($reason); ?>" <?= $formValues['reason'] === $reason ? 'selected' : ''; ?>><?= terminationValue($reason); ?></option><?php endforeach; ?></select></div>
                        <div class="row g-3"><div class="col-md-6"><label class="form-label" for="notice_date">Notice Date</label><input type="date" class="form-control" id="notice_date" name="notice_date" value="<?= terminationValue($formValues['notice_date']); ?>" required></div><div class="col-md-6"><label class="form-label" for="effective_date">Effective Separation Date</label><input type="date" class="form-control" id="effective_date" name="effective_date" value="<?= terminationValue($formValues['effective_date']); ?>" required></div></div>
                        <div class="mt-3"><label class="form-label" for="additional_notes">Additional Management Notes</label><textarea class="form-control" id="additional_notes" name="additional_notes" rows="4" placeholder="Optional facts, handover instructions, or approved notes"><?= terminationValue($formValues['additional_notes']); ?></textarea></div>
                        <div class="alert alert-light border mt-4 mb-0">The PDF includes due-process language, handover instructions, continuing confidentiality obligations, acknowledgement fields, and the company stamp.</div>
                        <button type="submit" name="send_termination_letter" class="btn btn-primary btn-lg w-100 mt-4" id="sendButton">Send Staff Awareness Notice</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="preview-panel">
                    <div class="section-title">Document Preview</div>
                    <p class="text-muted">The selected employee will receive a formal, branded PDF by email.</p>
                    <div class="preview-line"><strong>Employee:</strong> <span id="previewName"><?= $selectedEmployee ? terminationValue($selectedEmployee['name']) : 'Not selected'; ?></span></div>
                    <div class="preview-line"><strong>Email:</strong> <span id="previewEmail"><?= $selectedEmployee ? terminationValue($selectedEmployee['email']) : 'Not selected'; ?></span></div>
                    <div class="preview-line"><strong>Role:</strong> <span id="previewRole"><?= $selectedEmployee ? terminationValue($selectedEmployee['role_name']) : 'Not selected'; ?></span></div>
                    <div class="preview-line"><strong>Reason:</strong> <span id="previewReason"><?= terminationValue($formValues['reason']); ?></span></div>
                    <div class="alert alert-light border mt-4" id="sendScope">Select the reason carefully. Poor Performance and Voluntary Request send private notices to the selected employee; other reasons send customized awareness notices to all staff.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('staff_id').addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        document.getElementById('previewName').textContent = option.dataset.name || 'Not selected';
        document.getElementById('previewEmail').textContent = option.dataset.email || 'Not selected';
        document.getElementById('previewRole').textContent = option.dataset.role || 'Not selected';
    });
    document.getElementById('reason').addEventListener('change', function () {
        document.getElementById('previewReason').textContent = this.value || 'Not selected';
        const isPrivate = this.value === 'Voluntary Request' || this.value === 'Poor Performance';
        document.getElementById('sendButton').textContent = isPrivate ? 'Send Private Termination Notice' : 'Send Staff Awareness Notice';
        document.getElementById('recipientHelp').textContent = isPrivate
            ? (this.value === 'Poor Performance' ? 'Select the employee whose four-month performance summary will be included. Only that employee will receive the notice.' : 'Select the employee who submitted the voluntary separation request. Only that employee will receive the notice.')
            : 'The reason-specific awareness notice will be personalized and sent to all staff with valid email addresses.';
    });
    document.getElementById('reason').dispatchEvent(new Event('change'));
</script>
</body>
</html>
