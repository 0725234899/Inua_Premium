<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

function getDashboardStaff($conn) {
    $staff = [];
    $result = $conn->query("SELECT u.id, u.name, u.email, COALESCE(r.name, 'Staff') AS role_name
                            FROM users u LEFT JOIN roles r ON r.id = u.role_id
                            WHERE u.email IS NOT NULL AND u.email <> '' ORDER BY u.name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
    }
    return $staff;
}

function dashboardNoticeValue($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function getDashboardCommunicationTemplates() {
    return [
        'repayment_reminder' => ['type' => 'Reminder', 'subject' => 'Repayment and portfolio follow-up reminder', 'message' => 'Please review all assigned repayment schedules, contact customers with upcoming or overdue instalments, update the system records, and escalate unresolved repayment risks before the next review.'],
        'kyc_documentation' => ['type' => 'Notice', 'subject' => 'KYC and loan documentation compliance notice', 'message' => 'All customer identification, KYC, affordability, approval, and supporting documents must be complete, accurate, and properly filed before a facility is processed or disbursed. Any documentation gap must be reported and corrected promptly.'],
        'collections_followup' => ['type' => 'Notice', 'subject' => 'Collections and arrears follow-up notice', 'message' => 'Review overdue accounts, make documented customer contacts, agree realistic repayment actions, and update all follow-up outcomes. Escalate persistent arrears, customer complaints, and material repayment risks through the approved collections procedure.'],
        'compliance_conduct' => ['type' => 'Notice', 'subject' => 'Professional conduct and compliance notice', 'message' => 'All staff must act honestly, protect customer information and company property, disclose conflicts of interest, maintain accurate records, follow approved procedures, and report suspected fraud, misconduct, or control weaknesses immediately.'],
        'weekly_meeting' => ['type' => 'Reminder', 'subject' => 'Weekly review meeting reminder', 'message' => 'Please prepare your weekly activity summary, portfolio movement, collections progress, customer issues, unresolved exceptions, and proposed actions before the scheduled management review.'],
        'policy_reminder' => ['type' => 'Notice', 'subject' => 'Microfinance policy and procedure reminder', 'message' => 'Please comply with the organization\'s approved lending, customer service, data protection, cash handling, complaints, reporting, and records-management policies and procedures. Seek clarification before taking an action outside your authority.'],
        'termination_fraud' => ['type' => 'Notice', 'subject' => 'Corrective notice: fraud or dishonest conduct concern', 'message' => 'A concern has been raised regarding possible fraud, dishonesty, misrepresentation, or misuse of company or client information or funds. You are required to provide your response, cooperate with any review, preserve relevant records, and correct any confirmed breach by the lapse date. Failure to respond or meet the required standard may result in disciplinary action, recovery of losses, referral to authorities, or termination in accordance with policy and applicable law.', 'termination_item' => 'Fraud', 'individual_only' => true],
        'termination_conflict' => ['type' => 'Notice', 'subject' => 'Corrective notice: conflict of interest concern', 'message' => 'You are required to disclose and resolve any actual, potential, or undisclosed conflict of interest affecting your duties, clients, suppliers, or the organization. Submit the required disclosure and corrective action by the lapse date. Failure to comply may result in disciplinary action or termination in accordance with policy and applicable law.', 'termination_item' => 'Conflict of Interest', 'individual_only' => true],
        'termination_theft' => ['type' => 'Notice', 'subject' => 'Corrective notice: property or funds concern', 'message' => 'A concern has been raised regarding possible theft, unauthorized taking, conversion, or misuse of company, client, or colleague property. Preserve records, cooperate with the review, and return or account for any property or funds by the lapse date. Confirmed misconduct may lead to disciplinary action, recovery, referral to authorities, or termination in accordance with policy and applicable law.', 'termination_item' => 'Theft', 'individual_only' => true],
        'termination_behavior' => ['type' => 'Notice', 'subject' => 'Corrective notice: professional conduct concern', 'message' => 'Your conduct is being reviewed against the standards of professionalism, respect, integrity, dignity, and workplace behavior required by Inua Premium Services. Provide your response and demonstrate the required correction by the lapse date. Failure to improve may result in disciplinary action or termination in accordance with policy and applicable law.', 'termination_item' => 'Unbecoming Behavior', 'individual_only' => true],
        'termination_performance' => ['type' => 'Notice', 'subject' => 'Performance improvement notice', 'message' => 'Your performance has remained below the reasonable duties, targets, service standards, or role expectations communicated to you. Submit your response and demonstrate measurable improvement by the lapse date. Continued poor performance after reasonable review and support may result in further disciplinary action or termination in accordance with policy and applicable law.', 'termination_item' => 'Poor Performance', 'individual_only' => true]
    ];
}

function dashboardNoticeDate($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date ? $date->format('d/m/Y') : '';
}

function dashboardNoticeReference() {
    return 'NT-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function generateDashboardNoticePdf($recipient, $sender, $noticeType, $subject, $message, $lapseDate = '', $investigationDays = null, $reference = '') {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor($sender['name']);
    $pdf->SetTitle(ucfirst($noticeType) . ' - ' . $subject);
    $pdf->SetMargins(15, 16, 15);
    $pdf->SetAutoPageBreak(false, 18);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $safe = function ($value) { return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8'); };
    $pdf->AddPage();
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
    $pdf->Cell(0, 5, 'Formal Staff Communication', 0, 1, 'L');
    $pdf->Ln(8);
    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, strtoupper($noticeType), 0, 1, 'C');
    $pdf->SetTextColor(45, 45, 45);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Reference: ' . $safe($reference ?: dashboardNoticeReference()) . '    Date: ' . date('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(6);
    $pdf->SetFont('helvetica', '', 10);
    $lapseLine = $noticeType === 'Notice' && $lapseDate !== '' ? '<br><b>Notice lapses:</b> ' . $safe($lapseDate) : '';
    $suspensionLine = $investigationDays !== null ? '<br><b>Administrative workplace exclusion:</b> ' . (int) $investigationDays . ' day(s) pending investigation' : '';
    $pdf->writeHTML('<b>To:</b> ' . $safe($recipient['name']) . '<br><b>Role:</b> ' . $safe($recipient['role_name']) . '<br><b>Subject:</b> ' . $safe($subject) . $lapseLine . $suspensionLine, true, false, true, false, 'L');
    $pdf->Ln(5);
    $pdf->writeHTML('Dear ' . $safe($recipient['name']) . ',', true, false, true, false, 'L');
    $pdf->Ln(3);
    $pdf->writeHTML(nl2br($safe($message)), true, false, true, false, 'L');
    if ($investigationDays !== null) {
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->writeHTML('<b>Pending investigation instruction:</b> You must not attend the workplace or perform workplace duties for ' . (int) $investigationDays . ' day(s) from the date of this notice, unless Human Resources provides written instructions otherwise. This is an administrative measure and is not a finding of misconduct. You must remain available to cooperate with the investigation and comply with lawful instructions.', true, false, true, false, 'L');
    }
    $pdf->Ln(8);
    $pdf->writeHTML('Please acknowledge receipt where required and direct any clarification to your manager or the Human Resources and Administration Department.', true, false, true, false, 'L');
    $pdf->Ln(12);
    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    if (file_exists($stampPath)) {
        $stampY = $pdf->GetY();
        $pdf->SetAlpha(0.42);
        $pdf->Image($stampPath, 146, $stampY - 8, 35, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
    }
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, $sender['name'], 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(0, 5, 'Manager, Inua Premium Services', 0, 1, 'L');
    $pdf->Cell(0, 5, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->Line(15, 276, 195, 276);
    $pdf->SetFont('helvetica', 'I', 7.2);
    $pdf->SetXY(15, 278);
    $pdf->Cell(180, 4, 'Confidential staff communication | Inua Premium Services', 0, 0, 'C');
    return $pdf->Output('', 'S');
}

function sendDashboardNoticeEmail($recipient, $pdfContent, $filename, $noticeType, $subject, $message, $lapseDate = '', $investigationDays = null, $reference = '') {
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
    $mail->addAddress($recipient['email'], $recipient['name']);
    $mail->isHTML(true);
    $mail->Subject = $noticeType . ': ' . $subject;
    $lapseText = $noticeType === 'Notice' && $lapseDate !== '' ? '<p><strong>Notice lapses:</strong> ' . htmlspecialchars($lapseDate, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    $suspensionText = $investigationDays !== null ? '<p><strong>Administrative workplace exclusion:</strong> ' . (int) $investigationDays . ' day(s) pending investigation. This is an administrative measure and not a finding of misconduct.</p>' : '';
    $mail->Body = '<p>Dear ' . htmlspecialchars($recipient['name'], ENT_QUOTES, 'UTF-8') . ',</p><p>Please find attached a formal <strong>' . htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8') . '</strong> from Inua Premium Services.</p><p><strong>Reference:</strong> ' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '<br><strong>Subject:</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</p>' . $lapseText . $suspensionText . '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p><p>Regards,<br>Management and Human Resources<br>Inua Premium Services</p>';
    $mail->AltBody = 'Reference: ' . $reference . "\n" . $noticeType . ': ' . $subject . ($lapseDate !== '' ? "\nLapses: " . $lapseDate : '') . ($investigationDays !== null ? "\nWorkplace exclusion: " . (int) $investigationDays . " day(s) pending investigation." : '') . "\n\n" . $message;
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$dashboardStaff = getDashboardStaff($conn);
$dashboardTemplates = getDashboardCommunicationTemplates();
$noticeMessage = '';
$noticeStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_dashboard_notice'])) {
    $recipientId = (int) ($_POST['notice_recipient'] ?? 0);
    $noticeType = trim((string) ($_POST['notice_type'] ?? 'Notice'));
    $noticeSubject = trim((string) ($_POST['notice_subject'] ?? ''));
    $noticeBody = trim((string) ($_POST['notice_message'] ?? ''));
    $noticeTemplate = trim((string) ($_POST['notice_template'] ?? ''));
    $investigationDaysInput = trim((string) ($_POST['investigation_days'] ?? ''));
    $noticeLapseDate = $noticeType === 'Notice' ? dashboardNoticeDate($_POST['notice_lapse_date'] ?? '') : '';
    $noticeReference = dashboardNoticeReference();
    $validTypes = ['Notice', 'Reminder'];
    $selectedTemplate = $dashboardTemplates[$noticeTemplate] ?? null;
    $individualOnly = !empty($selectedTemplate['individual_only']);
    $isFraudNotice = $noticeTemplate === 'termination_fraud';
    $investigationDays = $isFraudNotice && ctype_digit($investigationDaysInput) && (int) $investigationDaysInput > 0 ? (int) $investigationDaysInput : null;
    $recipients = [];
    foreach ($dashboardStaff as $staffMember) {
        if (($recipientId === 0 || (int) $staffMember['id'] === $recipientId) && filter_var($staffMember['email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $staffMember;
        }
    }
    if ($individualOnly && $recipientId === 0) {
        $noticeMessage = 'Termination-related corrective notices can only be sent to one selected staff member.';
        $noticeStatus = 'danger';
    } elseif (empty($recipients) || !in_array($noticeType, $validTypes, true) || $noticeSubject === '' || $noticeBody === '' || ($noticeType === 'Notice' && ($noticeLapseDate === '' || trim((string) ($_POST['notice_lapse_date'] ?? '')) === '')) || ($isFraudNotice && $investigationDays === null)) {
        $noticeMessage = $isFraudNotice ? 'For a fraud-related notice, provide a valid investigation exclusion period in whole days.' : 'Select a recipient and type, complete the subject and message, and provide a valid lapse date for notices.';
        $noticeStatus = 'danger';
    } else {
        try {
            $sender = ['name' => 'Management'];
            foreach ($dashboardStaff as $staffMember) {
                if (trim((string) ($_SESSION['email'] ?? '')) === trim((string) $staffMember['email'])) {
                    $sender['name'] = $staffMember['name'];
                    break;
                }
            }
            foreach ($recipients as $recipient) {
                $pdfContent = generateDashboardNoticePdf($recipient, $sender, $noticeType, $noticeSubject, $noticeBody, $noticeLapseDate, $investigationDays, $noticeReference);
                $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $recipient['name']) ?: 'staff';
                sendDashboardNoticeEmail($recipient, $pdfContent, strtolower($noticeType) . '_' . $safeName . '_' . date('Ymd_His') . '.pdf', $noticeType, $noticeSubject, $noticeBody, $noticeLapseDate, $investigationDays, $noticeReference);
            }
            $noticeMessage = 'The ' . strtolower($noticeType) . ' was sent to ' . count($recipients) . ' staff member(s).';
            $noticeStatus = 'success';
        } catch (Exception $e) {
            $noticeMessage = 'The notice could not be sent: ' . $e->getMessage();
            $noticeStatus = 'danger';
        }
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS penalty_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    borrower_id INT NOT NULL,
    officer_email VARCHAR(255) NOT NULL,
    officer_name VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    note VARCHAR(500) DEFAULT NULL,
    acted_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_penalty_actions_loan (loan_id),
    INDEX idx_penalty_actions_officer (officer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch total overdue amount using same calculation as overdue_repayments.php (sum per-borrower amounts)
$sql_total_overdue = "SELECT 
                    borrowers.id AS borrower_id,
                    borrowers.full_name AS borrower_name, 
                    borrowers.mobile AS phone_number, 
                    GREATEST(
                        COALESCE(SUM(CASE 
                            WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0) 
                            ELSE 0 
                        END), 0) 
                        - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0)
                        - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa INNER JOIN loan_applications pla ON pla.id = pa.loan_id WHERE pla.borrower = borrowers.id), 0),
                        0
                    ) AS total_overdue
                FROM 
                    borrowers
                LEFT JOIN 
                    loan_applications ON borrowers.id = loan_applications.borrower
                LEFT JOIN 
                    repayments ON loan_applications.id = repayments.loan_id
                WHERE 
                    1=1
                    AND loan_applications.loan_status = 'approved'
                GROUP BY 
                    borrowers.id, borrowers.full_name, borrowers.mobile
                HAVING 
                    total_overdue > 0";
$stmt_total_overdue = $conn->prepare($sql_total_overdue);
$stmt_total_overdue->execute();
$result_total_overdue = $stmt_total_overdue->get_result();

// Calculate total overdue amount
$total_overdue_amount = 0;
while ($row = $result_total_overdue->fetch_assoc()) {
    $total_overdue_amount += $row['total_overdue'];
}

// Fetch total paid amount for approved loans
$sql_total_paid = "SELECT CEIL(SUM(repayments.paid + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = loan_applications.id), 0))) AS total_paid
                   FROM repayments 
                   INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                   WHERE loan_applications.loan_status = 'approved'";
$stmt_total_paid = $conn->prepare($sql_total_paid);
$stmt_total_paid->execute();
$total_paid_amount = $stmt_total_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;

// Calculate total arrears (overdue repayments)
$total_arrears = $total_overdue_amount;

// Fetch total interest and combined fee totals for approved loans
$sql_total_interest = "SELECT CEIL(COALESCE(SUM(total_amount - principal), 0)) AS total_interest
                       FROM loan_applications
                       WHERE loan_status = 'approved'";
$stmt_total_interest = $conn->prepare($sql_total_interest);
$stmt_total_interest->execute();
$total_interest_amount = $stmt_total_interest->get_result()->fetch_assoc()['total_interest'] ?? 0;

$conn->query("CREATE TABLE IF NOT EXISTS loan_officer_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NULL,
    template_name VARCHAR(100) NOT NULL,
    expense_type VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    loan_officer_id INT NOT NULL,
    loan_officer_name VARCHAR(100) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'cash',
    recorded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_loan_officer (loan_officer_id),
    KEY idx_expense_date (expense_date)
)");
$totalExpensesResult = $conn->query("SELECT CEIL(COALESCE(SUM(amount), 0)) AS total_expenses FROM loan_officer_expenses");
$total_expenses_amount = $totalExpensesResult ? ($totalExpensesResult->fetch_assoc()['total_expenses'] ?? 0) : 0;

$interestCalculationColumnStmt = $conn->query("SELECT COUNT(*) AS column_count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_applications' AND COLUMN_NAME = 'interest_calculation'");
$hasInterestCalculationColumn = $interestCalculationColumnStmt && (int) $interestCalculationColumnStmt->fetch_assoc()['column_count'] > 0;
$weeklyLoanExpression = $hasInterestCalculationColumn
    ? "LOWER(COALESCE(l.interest_calculation, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.repayment_cycle, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.loan_duration_unit, '')) IN ('weekly', 'week', 'weeks')"
    : "LOWER(COALESCE(l.repayment_cycle, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.loan_duration_unit, '')) IN ('weekly', 'week', 'weeks')";
$totalPaidExpression = "COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)";
$penaltyThresholdExpression = "l.principal + (l.principal * CASE WHEN $weeklyLoanExpression THEN 0.06 ELSE 0.24 END * l.loan_duration)";
$loanPenaltyDebitExpression = "COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0)";

$sql_penalty_details = "SELECT
    l.id,
    l.borrower,
    GREATEST(0, (
        ($totalPaidExpression)
        - ($penaltyThresholdExpression)
    )) AS gross_penalty_amount,
    GREATEST(0, (
        ($totalPaidExpression)
        - ($penaltyThresholdExpression)
        - ($loanPenaltyDebitExpression)
    )) AS penalty_amount
FROM loan_applications l
WHERE l.loan_status IN ('approved', 'rolled_over')
   OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%'";

$result_penalty_details = $conn->query($sql_penalty_details);
$gross_penalty_total = 0;
$used_penalty_total = (float) ($conn->query('SELECT COALESCE(SUM(amount), 0) AS used_penalty FROM penalty_actions')->fetch_row()[0] ?? 0);
if ($result_penalty_details) {
    while ($penaltyRow = $result_penalty_details->fetch_assoc()) {
        $gross_penalty_total += (float) $penaltyRow['gross_penalty_amount'];
    }
}
$balance_penalty_amount = max(0, $gross_penalty_total - $used_penalty_total);

$sql_total_fees = "SELECT CEIL(COALESCE(SUM(processing_fee + registration_fee), 0)) AS total_fees
                   FROM loan_applications
                   WHERE loan_status = 'approved'";
$stmt_total_fees = $conn->prepare($sql_total_fees);
$stmt_total_fees->execute();
$total_fee_amount = $stmt_total_fees->get_result()->fetch_assoc()['total_fees'] ?? 0;

$sql_total_principal = "SELECT CEIL(SUM(loan_applications.principal)) AS total_principal 
                        FROM loan_applications 
                        WHERE loan_status = 'approved'";
$stmt_total_principal = $conn->prepare($sql_total_principal);
$stmt_total_principal->execute();
$total_disbursed_principal = $stmt_total_principal->get_result()->fetch_assoc()['total_principal'] ?? 0;

$sql_total_loans = "SELECT CEIL(SUM(loan_applications.total_amount)) AS total_loans 
                    FROM loan_applications 
                    WHERE loan_status = 'approved'";
$stmt_total_loans = $conn->prepare($sql_total_loans);
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

// Calculate Performing Book
$performing_book = max(0, $total_loan_amount - $total_arrears - $total_paid_amount);

// Calculate Loan Book
$loan_book = $performing_book + $total_arrears;

// Calculate Portfolio at Risk (PAR)
$par = ($loan_book > 0) ? ($total_arrears / $loan_book) * 100 : 0;

// Fetch total performing loans
$sql_total_performing = "SELECT CEIL(SUM(amount - paid - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = repayments.loan_id), 0))) AS total_performing
                         FROM repayments 
                         INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                         WHERE loan_applications.loan_status = 'approved' 
                         AND (amount - paid - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = repayments.loan_id), 0)) = 0";
$stmt_total_performing = $conn->prepare($sql_total_performing);
$stmt_total_performing->execute();
$total_performing_loans = $stmt_total_performing->get_result()->fetch_assoc()['total_performing'] ?? 0;

// Fetch total number of clients with outstanding loan balance > 0
$sql_total_clients = "SELECT COUNT(*) AS total_clients 
                      FROM (
                          SELECT borrowers.id
                          FROM borrowers
                          LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
                          LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
                          WHERE loan_applications.loan_status = 'approved'
                          GROUP BY borrowers.id
                          HAVING SUM(COALESCE(repayments.amount - repayments.paid, 0)) - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa INNER JOIN loan_applications pla ON pla.id = pa.loan_id WHERE pla.borrower = borrowers.id), 0) > 0
                      ) AS clients_with_balance";
$stmt_total_clients = $conn->prepare($sql_total_clients);
$stmt_total_clients->execute();
$total_clients = $stmt_total_clients->get_result()->fetch_assoc()['total_clients'] ?? 0;

// Fetch total number of clients in arrears (with total overdue amount > 0)
$sql_clients_in_arrears = "SELECT COUNT(*) AS clients_in_arrears 
                           FROM (
                               SELECT borrowers.id,
                                   GREATEST(
                                       COALESCE(SUM(CASE 
                                           WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0) 
                                           ELSE 0 
                                       END), 0) 
                                       - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0)
                                       - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa INNER JOIN loan_applications pla ON pla.id = pa.loan_id WHERE pla.borrower = borrowers.id), 0),
                                       0
                                   ) AS total_overdue
                               FROM borrowers
                               LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
                               LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
                               WHERE loan_applications.loan_status = 'approved'
                               GROUP BY borrowers.id
                               HAVING total_overdue > 0
                           ) AS arrears_summary";
$stmt_clients_in_arrears = $conn->prepare($sql_clients_in_arrears);
$stmt_clients_in_arrears->execute();
$clients_in_arrears = $stmt_clients_in_arrears->get_result()->fetch_assoc()['clients_in_arrears'] ?? 0;

// Fetch total due loans
$sql_due_loans = "SELECT CEIL(SUM(amount - paid - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = loan_applications.id), 0))) AS total_due_loans
                  FROM repayments 
                  INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                  WHERE repayment_date = CURDATE() 
                  AND loan_applications.loan_status = 'approved' 
                  AND (amount - paid - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = repayments.loan_id), 0)) > 0";
$stmt_due_loans = $conn->prepare($sql_due_loans);
$stmt_due_loans->execute();
$total_due_loans = $stmt_due_loans->get_result()->fetch_assoc()['total_due_loans'] ?? 0;

// Query for upcoming repayments
$sql_due = "SELECT 
                borrowers.full_name,
                loan_applications.loan_status,  
                loan_applications.loan_product,
                loan_applications.total_amount, 
                SUM(repayments.amount - repayments.paid) AS total_amount_due, 
                DATE_FORMAT(MIN(repayments.repayment_date), '%d/%m/%Y') AS next_due_date 
            FROM repayments 
            INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
            INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
            WHERE repayments.repayment_date >= CURDATE() 
            AND loan_applications.loan_status = 'approved'
            AND (repayments.amount - repayments.paid) > 0
            GROUP BY repayments.loan_id, borrowers.full_name, loan_applications.loan_product, loan_applications.total_amount";

$stmt_due = $conn->prepare($sql_due);
$stmt_due->execute();
$result_due = $stmt_due->get_result();

// Query for overdue repayments
$sql_overdue = "SELECT borrowers.full_name, 
                       loan_applications.loan_product, 
                       loan_applications.loan_status, 
                       SUM(repayments.amount - repayments.paid) AS total_amount, 
                       DATE_FORMAT(MIN(repayments.repayment_date), '%d/%m/%Y') AS earliest_due_date 
                FROM repayments 
                INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                WHERE repayments.repayment_date < CURDATE() 
                  AND loan_applications.loan_status = 'approved' 
                  AND (repayments.amount - repayments.paid) > 0
                GROUP BY repayments.loan_id, 
                         borrowers.full_name, 
                         loan_applications.loan_product";

$stmt_overdue = $conn->prepare($sql_overdue);
$stmt_overdue->execute();
$result_overdue = $stmt_overdue->get_result();

// Start session before any HTML output so includes/header.php can safely manage auth headers.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Microfinance</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --ink: #172331;
            --muted: #687582;
            --line: #dbe3e8;
            --paper: #ffffff;
            --canvas: #f2f5f6;
            --teal: #147d78;
            --gold: #c7973e;
        }

        body {
            background: var(--canvas);
            color: var(--ink);
            font-family: "Trebuchet MS", Arial, sans-serif;
        }

        .main {
            margin-left: 250px;
            padding: 34px 22px 60px;
            transition: margin-left 0.3s ease;
        }

        .main > .header {
            background: var(--ink);
            border-top: 4px solid var(--gold);
            color: white;
            margin: 0 auto;
            max-width: 1280px;
            padding: 26px 34px;
        }

        .main > .header h1 {
            color: white;
            font-family: Georgia, serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: normal;
            letter-spacing: .02em;
        }

        .sidebar-toggle-btn {
            background: transparent;
            border: 1px solid #82939c;
            border-radius: 0;
            color: white;
            margin-right: 14px;
            padding: 8px 12px;
        }

        .sidebar-toggle-btn:hover {
            background: var(--teal);
            border-color: var(--teal);
            color: white;
        }

        .notice-toast {
            position: fixed;
            right: 24px;
            top: 82px;
            z-index: 1100;
            min-width: 280px;
            max-width: 420px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .16);
        }

        .main > .header .btn-primary {
            background: transparent;
            border: 1px solid #82939c;
            border-radius: 0;
            color: white;
            margin-right: 0 !important;
            padding: 8px 14px;
        }

        .main > .header .btn-primary:hover {
            background: var(--teal);
            border-color: var(--teal);
        }

        .dashboard-client-search {
            display: flex;
            gap: 8px;
            margin-left: auto;
            margin-right: 18px;
            max-width: 390px;
            width: 100%;
        }

        .dashboard-client-search input {
            border: 1px solid #82939c;
            border-radius: 0;
            min-width: 0;
        }

        .dashboard-client-search .btn {
            background: var(--teal);
            border: 1px solid var(--teal);
            border-radius: 0;
            color: white;
            white-space: nowrap;
        }

        .dashboard-client-search .btn:hover { background: #0f625e; }
        .client-search-error { color: #a95d55; display: none; font-size: .85rem; margin-top: 10px; }
        .client-summary { background: #f7faf9; border-left: 4px solid var(--teal); padding: 16px 18px; }
        .client-summary h3 { font-family: Georgia, serif; font-size: 1.35rem; margin: 0 0 8px; }
        .client-summary p { color: var(--muted); margin: 3px 0; }
        .client-loans-title { color: var(--ink); font-family: Georgia, serif; font-size: 1.2rem; margin: 22px 0 10px; }
        .client-loans-table { font-size: .9rem; }
        .client-loans-table th { white-space: nowrap; }

        .main > .container {
            margin: 18px auto 0;
            max-width: 1280px;
            padding: 0;
        }

        .dashboard-metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin: 0;
        }

        .dashboard-metrics a {
            color: inherit;
            text-decoration: none;
        }

        .metric {
            background: var(--paper);
            border: 1px solid var(--line);
            border-left: 4px solid var(--teal);
            border-radius: 0;
            box-shadow: none;
            padding: 18px 20px;
            text-align: left;
            transition: border-color 0.2s ease, transform 0.2s ease;
            width: auto;
        }

        .metric:nth-child(2), .metric:nth-child(6) { border-left-color: var(--gold); }
        .metric:nth-child(3), .metric:nth-child(7) { border-left-color: #5b7180; }
        .metric:nth-child(4), .metric:nth-child(8) { border-left-color: #a95d55; }
        .metric:hover { box-shadow: 0 3px 10px rgba(23, 35, 49, .08); transform: translateY(-2px); }
        .metric.loan-book { width: auto; }
        .metric h2 { color: var(--ink); font-family: Georgia, serif; font-size: 1.45rem; margin: 8px 0 0; }
        .metric p { color: var(--muted); font-size: .74rem; letter-spacing: .1em; margin: 0; text-transform: uppercase; }

        .chart-container {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 0;
            box-shadow: none;
            margin: 18px 0 !important;
            max-width: none;
            padding: 24px 18px;
            width: auto;
        }

        .main > .container > .container {
            margin: 18px 0 0;
            max-width: none;
            padding: 0;
        }

        .section-title {
            background: var(--ink);
            border-top: 4px solid var(--gold);
            color: white;
            font-family: Georgia, serif;
            font-size: 1.25rem;
            font-weight: normal;
            margin: 0;
            padding: 18px 22px;
        }

        .table-container {
            background: var(--paper);
            border: 1px solid var(--line);
            border-top: 0;
            overflow-x: auto;
            padding: 18px 22px 0;
        }

        #interestSearch { border-color: var(--line); border-radius: 0; color: var(--ink); }
        #interestSearch:focus { border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(20, 125, 120, .12); }
        .table { margin: 0; }
        .table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; padding: 14px 18px; text-transform: uppercase; white-space: nowrap; }
        .table tbody td { border-color: #e6ecef; padding: 15px 18px; vertical-align: middle; }
        .table tbody tr:nth-child(odd), .table tbody tr.cleared-loan, .table tbody tr.rolled-over-loan { background: transparent; }
        .table tbody tr:hover { background: #f7faf9; }
        .table a { color: var(--teal); font-weight: bold; text-decoration: none; }
        .table a:hover { color: var(--ink); text-decoration: underline; }
        .cleared-loan-badge, .rolled-over-badge { border-radius: 20px; display: inline-block; font-size: .72rem; font-weight: bold; letter-spacing: .05em; padding: 5px 10px; text-transform: uppercase; }
        .cleared-loan-badge { background: #edf0f2; color: #53636d; }
        .rolled-over-badge { background: #e6f3f1; color: #146b67; }

        .sidebar { transition: all 0.3s ease; }
        .sidebar.collapsed { display: none; }
        .main.sidebar-collapsed { margin-left: 0; }

        /* The shared sidebar is included inside this dashboard wrapper. Keep one scroll container. */
        #sidebarWrapper {
            box-sizing: border-box;
            height: 100vh;
            max-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            position: fixed;
            width: 250px;
            z-index: 1000;
        }
        #sidebarWrapper .sidebar {
            box-sizing: border-box;
            height: auto;
            max-height: none;
            overflow: visible;
            position: static;
            width: 100%;
        }
        #sidebarWrapper .sidebar-nav {
            box-sizing: border-box;
            min-height: 100%;
            padding-bottom: 80px;
        }
        #sidebarWrapper .sidebar-nav .collapse {
            overflow: visible;
        }

        @media (max-width: 850px) {
            .main > .header { padding: 24px; }
            .dashboard-metrics { grid-template-columns: repeat(2, 1fr); }
            .dashboard-client-search { margin: 16px 0 0; max-width: none; order: 3; }
            .main > .header { flex-wrap: wrap; }
        }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 20px 12px 40px; }
            .main.sidebar-collapsed { margin-left: 0; }
        }

        @media (max-width: 520px) {
            .dashboard-metrics { grid-template-columns: 1fr; }
            .main > .header { padding: 20px; }
            .main > .header h1 { font-size: 1.8rem; }
            .table-container { padding-left: 12px; padding-right: 12px; }
            .dashboard-client-search { flex-direction: column; }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<?php if ($noticeMessage): ?><div class="notice-toast alert alert-<?= htmlspecialchars($noticeStatus); ?>"><?= htmlspecialchars($noticeMessage); ?></div><?php endif; ?>
<div class="modal fade" id="dashboardNoticeModal" tabindex="-1" aria-labelledby="dashboardNoticeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="dashboardNoticeModalLabel"><i class="fas fa-bell me-2"></i>Send Staff Notice or Reminder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="index.php">
                <div class="modal-body">
                    <div class="alert alert-light border">Choose one staff member for a private communication, or select all staff to send the same formal notice or reminder to everyone.</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="notice_recipient">Recipient</label>
                            <select class="form-select" id="notice_recipient" name="notice_recipient" required>
                                <option value="0">All staff</option>
                                <?php foreach ($dashboardStaff as $staffMember): ?>
                                    <option value="<?= (int) $staffMember['id']; ?>"><?= dashboardNoticeValue($staffMember['name'] . ' - ' . $staffMember['role_name'] . ' (' . $staffMember['email'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="notice_type">Communication Type</label>
                            <select class="form-select" id="notice_type" name="notice_type" required>
                                <option value="Notice">Notice</option>
                                <option value="Reminder">Reminder</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notice_template">Common Microfinance Template</label>
                            <select class="form-select" id="notice_template" name="notice_template">
                                <option value="">Write a custom communication</option>
                                <?php foreach ($dashboardTemplates as $templateKey => $template): ?>
                                    <option value="<?= dashboardNoticeValue($templateKey); ?>"><?= dashboardNoticeValue($template['type'] . (!empty($template['individual_only']) ? ' - Individual only - ' : ' - ') . $template['subject']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="templateHelp">Templates cover repayments, KYC, collections, compliance, weekly reviews, policy reminders, and individual corrective notices based on termination grounds. You can edit the loaded text.</div>
                        </div>
                        <div class="col-md-6" id="noticeLapseGroup">
                            <label class="form-label" for="notice_lapse_date">Notice Lapse Date</label>
                            <input type="date" class="form-control" id="notice_lapse_date" name="notice_lapse_date">
                            <div class="form-text">Optional for notices; reminders do not use a lapse date.</div>
                        </div>
                        <div class="col-md-6" id="investigationDaysGroup" style="display:none;">
                            <label class="form-label" for="investigation_days">Workplace Exclusion Period (Days)</label>
                            <input type="number" class="form-control" id="investigation_days" name="investigation_days" min="1" step="1" placeholder="e.g. 7">
                            <div class="form-text">Fraud-related notices require the number of days the employee must not attend the workplace pending investigation.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notice_subject">Subject</label>
                            <input class="form-control" id="notice_subject" name="notice_subject" maxlength="180" required placeholder="Enter notice subject">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notice_message">Message</label>
                            <textarea class="form-control" id="notice_message" name="notice_message" rows="7" maxlength="5000" required placeholder="Write the formal notice or reminder message"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_dashboard_notice" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Send PDF Communication</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        const templates = <?= json_encode($dashboardTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const typeField = document.getElementById('notice_type');
        const templateField = document.getElementById('notice_template');
        const subjectField = document.getElementById('notice_subject');
        const messageField = document.getElementById('notice_message');
        const recipientField = document.getElementById('notice_recipient');
        const recipientAllOption = recipientField.querySelector('option[value="0"]');
        const templateHelp = document.getElementById('templateHelp');
        const lapseGroup = document.getElementById('noticeLapseGroup');
        const lapseField = document.getElementById('notice_lapse_date');
        const investigationDaysGroup = document.getElementById('investigationDaysGroup');
        const investigationDaysField = document.getElementById('investigation_days');
        function updateLapseVisibility() {
            const isNotice = typeField.value === 'Notice';
            lapseGroup.style.display = isNotice ? '' : 'none';
            lapseField.required = isNotice;
            if (!isNotice) lapseField.value = '';
        }
        function updateInvestigationVisibility() {
            const isFraudNotice = templateField.value === 'termination_fraud';
            investigationDaysGroup.style.display = isFraudNotice ? '' : 'none';
            investigationDaysField.required = isFraudNotice;
            if (!isFraudNotice) investigationDaysField.value = '';
        }
        templateField.addEventListener('change', function () {
            const template = templates[this.value];
            if (!template) {
                recipientAllOption.disabled = false;
                templateHelp.textContent = 'Templates cover repayments, KYC, collections, compliance, weekly reviews, policy reminders, and individual corrective notices based on termination grounds. You can edit the loaded text.';
                updateLapseVisibility();
                updateInvestigationVisibility();
                return;
            }
            typeField.value = template.type;
            subjectField.value = template.subject;
            messageField.value = template.message;
            recipientAllOption.disabled = Boolean(template.individual_only);
            if (template.individual_only && recipientField.value === '0') {
                recipientField.value = '';
            }
            templateHelp.textContent = template.individual_only
                ? 'This corrective notice is individual-only. Select the staff member whose conduct or performance requires correction. It cannot be sent to all staff.'
                : 'You can edit the loaded template before sending it to one staff member or all staff.';
            updateLapseVisibility();
            updateInvestigationVisibility();
        });
        typeField.addEventListener('change', updateLapseVisibility);
        updateLapseVisibility();
        updateInvestigationVisibility();
    })();
</script>
<div class="sidebar" id="sidebarWrapper">
    <?php include '../includes/sidebar.php'; ?>
</div>
<main class="main" id="mainContent">
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="mb-0">Manager Dashboard</h1>
        </div>
        <form class="dashboard-client-search" id="clientSearchForm" novalidate>
            <input type="search" class="form-control" id="clientSearchInput" placeholder="Search client name or phone" aria-label="Search client name or phone">
            <button type="submit" class="btn" id="clientSearchButton"><i class="bi bi-search"></i> Search</button>
        </form>
        <a href="add_repayments.php" class="btn btn-primary" style="margin-right:20px;">Add Repayments</a>
    </div>
    <div class="container mt-4">
        <div class="dashboard-metrics">
            <!-- Metrics -->
            <a href="overdue_repayments.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_arrears)); ?></h2>
                <p>Total Overdue Amount</p>
            </div></a>
            <a href="approved-loans.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_disbursed_principal)); ?></h2>
                <p>Total Disbursed Loans</p>
            </div></a>
            <a href="performingBook.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($performing_book)); ?></h2>
                <p>Performing Book</p>
            </div></a>
            <div class="metric loan-book">
                <h2>KSH <?php echo number_format(ceil($loan_book)); ?></h2>
                <p>Loan Book</p>
            </div>
            <div class="metric">
                <h2><?php echo number_format($par, 2); ?>%</h2>
                <p>Portfolio At Risk</p>
            </div>
            <a href="expense_more_information.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_expenses_amount)); ?></h2>
                <p>Total Expenses</p>
            </div></a>
            <a href="interest_breakdown.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_interest_amount)); ?></h2>
                <p>Interest Breakdown</p>
            </div></a>
            <a href="penalty_breakdown.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($balance_penalty_amount)); ?></h2>
                <p>Balance Penalty</p>
            </div></a>
            <div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_fee_amount)); ?></h2>
                <p>Processing + Registration Fees</p>
            </div>
            <div class="metric">
                <h2><?php echo $total_clients; ?></h2>
                <p>Total Clients</p>
            </div>
            <div class="metric">
                <h2><?php echo $clients_in_arrears; ?></h2>
                <p>Clients in Arrears</p>
            </div>
            <a href="due_loans.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_due_loans)); ?></h2>
                <p>Due Loans</p>
            </div></a>
        </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="clientSearchModal" tabindex="-1" aria-labelledby="clientSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="clientSearchModalLabel">Client Information</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="clientSearchResults"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleButton = document.getElementById('sidebarToggleMain');
        const sidebarWrapper = document.getElementById('sidebarWrapper');
        const mainContent = document.getElementById('mainContent');

        if (toggleButton && sidebarWrapper && mainContent) {
            toggleButton.addEventListener('click', function () {
                sidebarWrapper.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
            });
        }

        const clientSearchForm = document.getElementById('clientSearchForm');
        const clientSearchInput = document.getElementById('clientSearchInput');
        const clientSearchButton = document.getElementById('clientSearchButton');
        const clientSearchModal = document.getElementById('clientSearchModal');
        const clientSearchResults = document.getElementById('clientSearchResults');

        if (clientSearchForm) {
            clientSearchForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                const query = clientSearchInput.value.trim();
                if (!query) {
                    clientSearchResults.innerHTML = '<p class="client-search-error" style="display:block;">Enter a client name or phone number.</p>';
                    bootstrap.Modal.getOrCreateInstance(clientSearchModal).show();
                    return;
                }

                clientSearchButton.disabled = true;
                clientSearchButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Searching';
                try {
                    const response = await fetch('search_client_details.php?query=' + encodeURIComponent(query), { headers: { 'Accept': 'application/json' } });
                    const clients = await response.json();
                    if (!response.ok || clients.error) throw new Error(clients.error || 'Unable to search clients.');
                    clientSearchResults.innerHTML = renderClientSearchResults(clients);
                    bootstrap.Modal.getOrCreateInstance(clientSearchModal).show();
                } catch (error) {
                    clientSearchResults.innerHTML = '<p class="client-search-error" style="display:block;">' + escapeClientSearchText(error.message) + '</p>';
                    bootstrap.Modal.getOrCreateInstance(clientSearchModal).show();
                } finally {
                    clientSearchButton.disabled = false;
                    clientSearchButton.innerHTML = '<i class="bi bi-search"></i> Search';
                }
            });
        }

        function escapeClientSearchText(value) {
            const element = document.createElement('div');
            element.textContent = value;
            return element.innerHTML;
        }

        function renderClientSearchResults(clients) {
            if (!clients.length) return '<p class="client-search-error" style="display:block;">No client found with that name or phone number.</p>';
            return clients.map(client => {
                const loans = client.loans.length ? client.loans.map(loan => `
                    <tr>
                        <td><a href="repayment_details.php?loanId=${encodeURIComponent(loan.id)}">${escapeClientSearchText(loan.id)}</a></td>
                        <td>KSH ${Number(loan.principal || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td>KSH ${Number(loan.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td>${escapeClientSearchText(loan.loan_duration || '')}</td>
                        <td>KSH ${Number(loan.total_paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td>KSH ${Number(loan.dues_arrears || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td>${escapeClientSearchText(loan.loan_release_date || 'Not specified')}</td>
                        <td><span class="status ${loan.loan_status === 'Cleared' ? 'status-cleared' : 'status-not-cleared'}">${escapeClientSearchText(loan.loan_status || 'Not Cleared')}</span></td>
                    </tr>`).join('') : '<tr><td colspan="7" class="text-center">No loans found.</td></tr>';
                return `<div class="client-summary mb-3">
                    <h3>${escapeClientSearchText(client.full_name)}</h3>
                    <p><strong>Phone:</strong> ${escapeClientSearchText(client.mobile || 'Not specified')}</p>
                    <p><strong>Client number:</strong> ${escapeClientSearchText(client.unique_number || 'Not specified')}</p>
                    <p><strong>Loan officer:</strong> ${escapeClientSearchText(client.loan_officer_name || 'Not specified')}</p>
                </div>
                <h3 class="client-loans-title">Loan Terms and IDs</h3>
                <div class="table-responsive"><table class="table table-bordered client-loans-table">
                    <thead><tr><th>Loan ID</th><th>Principal</th><th>Total Amount</th><th>Duration</th><th>Total Paid</th><th>Dues/Arrears</th><th>Release Date</th><th>Status</th></tr></thead>
                    <tbody>${loans}</tbody>
                </table></div>`;
            }).join('<hr>');
        }
    });

    // Pie Chart for PAR
    new Chart(document.getElementById('parPieChart'), {
        type: 'pie',
        data: {
            labels: ['At Risk', 'Performing'],
            datasets: [{
                data: [<?php echo $par; ?>, <?php echo 100 - $par; ?>],
                backgroundColor: ['#ef5350', '#42a5f5']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            }
        }
    });

    // Bar Chart for Loan Metrics
    new Chart(document.getElementById('loanChart'), {
        type: 'bar',
        data: {
            labels: ['Total Principal', 'Performing Book', 'Loan Book'],
            datasets: [{
                label: 'Loan Metrics',
                data: [<?php echo $total_loan_amount; ?>, <?php echo $performing_book; ?>, <?php echo $loan_book; ?>],
                backgroundColor: ['#42a5f5', '#66bb6a', '#ffca28']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { title: { display: true, text: 'Amount (KSH)' } },
                x: { title: { display: true, text: 'Metrics' } }
            }
        }
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
