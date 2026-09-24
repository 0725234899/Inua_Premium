<?php
include 'db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function analyticsValue($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function getAnalyticsManagers($conn) {
    $managers = [];
    $result = $conn->query("SELECT u.id, u.name, u.email, COALESCE(r.name, 'Manager') AS role_name
                            FROM users u LEFT JOIN roles r ON r.id = u.role_id
                            WHERE u.email IS NOT NULL AND u.email <> ''
                              AND (LOWER(COALESCE(r.name, '')) LIKE '%manager%' OR u.role_id IN (3, 4))
                            ORDER BY u.name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $managers[] = $row;
        }
    }
    return $managers;
}

function generateWeeklyReviewPdf($metrics, $officerData, $trendLabels, $trendLoanBook, $trendInterest, $trendExpenses, $periodLabel, $managerName) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor($managerName);
    $pdf->SetTitle('Weekly Portfolio Review - ' . $periodLabel);
    $pdf->SetMargins(15, 16, 15);
    $pdf->SetAutoPageBreak(false, 18);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $safe = function ($value) { return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8'); };
    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    $header = function ($title) use ($pdf, $safe, $managerName) {
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
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 9, 'INUA PREMIUM SERVICES', 0, 1, 'L');
        $pdf->SetTextColor(45, 45, 45);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell(0, 5, 'Management Information and Weekly Review', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Prepared by: ' . $safe($managerName) . ' | Generated: ' . date('d/m/Y H:i'), 0, 1, 'L');
        $pdf->Ln(4);
        $pdf->SetTextColor(210, 0, 0);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, $title, 0, 1, 'C');
        $pdf->SetTextColor(45, 45, 45);
        $pdf->Ln(2);
    };
    $section = function ($title) use ($pdf) {
        $pdf->Ln(2);
        $pdf->SetFillColor(11, 47, 159);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell(0, 7, $title, 1, 1, 'L', true);
        $pdf->SetTextColor(45, 45, 45);
        $pdf->Ln(1);
    };
    $table = function ($html) use ($pdf) {
        $pdf->SetFont('helvetica', '', 7.8);
        $pdf->writeHTML($html, true, false, true, false, 'L');
    };
    $footer = function ($page) use ($pdf) {
        $pdf->SetDrawColor(160, 160, 160);
        $pdf->Line(15, 276, 195, 276);
        $pdf->SetFont('helvetica', 'I', 7.2);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY(15, 278);
        $pdf->Cell(180, 4, 'Confidential management document | Weekly review | Page ' . $page . ' of 5', 0, 0, 'C');
    };
    $money = function ($value) { return 'KES ' . number_format((float) $value, 2); };

    $pdf->AddPage();
    $header('WEEKLY PORTFOLIO REVIEW REPORT');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Review period: ' . $safe($periodLabel), 0, 1, 'R');
    $pdf->Ln(3);
    $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><td width="35%"><b>Report scope</b></td><td width="65%">' . $safe($periodLabel) . '</td></tr><tr><td><b>Officers analysed</b></td><td>' . number_format($metrics['officers']) . '</td></tr><tr><td><b>Approved loans</b></td><td>' . number_format($metrics['loan_count']) . '</td></tr><tr><td><b>Active customers</b></td><td>' . number_format($metrics['customers']) . '</td></tr><tr><td><b>Prepared for</b></td><td>Management weekly review presentation</td></tr></table>');
    $section('1. EXECUTIVE SUMMARY');
    $pdf->SetFont('helvetica', '', 9);
    $summary = 'The portfolio contains ' . number_format($metrics['customers']) . ' active customers and ' . number_format($metrics['loan_count']) . ' approved loans. Total outstanding loan book is ' . $money($metrics['loan_book']) . ', with arrears of ' . $money($metrics['arrears']) . ' and portfolio-at-risk of ' . number_format($metrics['par'], 2) . '%. Interest generated is ' . $money($metrics['interest']) . ' against expenses of ' . $money($metrics['expenses']) . ', producing a net operating yield of ' . $money($metrics['net_yield']) . '.';
    $pdf->MultiCell(0, 5, $summary, 0, 'L');
    $section('2. MANAGEMENT DECISION SNAPSHOT');
    $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th width="35%"><b>Indicator</b></th><th width="25%"><b>Result</b></th><th width="40%"><b>Review implication</b></th></tr><tr><td>Loan book</td><td>' . $money($metrics['loan_book']) . '</td><td>Confirm growth is supported by quality underwriting and collections.</td></tr><tr><td>Arrears and PAR</td><td>' . $money($metrics['arrears']) . ' / ' . number_format($metrics['par'], 2) . '%</td><td>Prioritize high-risk officers and overdue customer action plans.</td></tr><tr><td>Revenue versus expense</td><td>' . $money($metrics['interest']) . ' / ' . $money($metrics['expenses']) . '</td><td>Review cost efficiency and protect positive operating yield.</td></tr><tr><td>Customer arrears rate</td><td>' . number_format($metrics['customer_arrears_pct'], 2) . '%</td><td>Strengthen early-warning follow-up and repayment education.</td></tr></table>');
    $footer(1);

    $pdf->AddPage();
    $header('FINANCIAL AND PORTFOLIO POSITION');
    $section('3. FINANCIAL METRICS');
    $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th width="45%"><b>Metric</b></th><th width="25%"><b>Amount</b></th><th width="30%"><b>Interpretation</b></th></tr><tr><td>Principal disbursed</td><td>' . $money($metrics['principal']) . '</td><td>Capital deployed to approved customers.</td></tr><tr><td>Outstanding loan book</td><td>' . $money($metrics['loan_book']) . '</td><td>Gross amount still outstanding.</td></tr><tr><td>Amount collected / paid</td><td>' . $money($metrics['paid']) . '</td><td>Repayment conversion recorded.</td></tr><tr><td>Interest generated</td><td>' . $money($metrics['interest']) . '</td><td>Interest component of approved loans.</td></tr><tr><td>Processing and registration fees</td><td>' . $money($metrics['fees']) . '</td><td>Additional fee income recorded.</td></tr><tr><td>Operating expenses</td><td>' . $money($metrics['expenses']) . '</td><td>Officer-related expenses in scope.</td></tr><tr><td>Net operating yield</td><td>' . $money($metrics['net_yield']) . '</td><td>Interest less recorded expenses.</td></tr></table>');
    $section('4. PORTFOLIO QUALITY');
    $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th width="40%"><b>Quality measure</b></th><th width="25%"><b>Value</b></th><th width="35%"><b>Management action</b></th></tr><tr><td>Arrears value</td><td>' . $money($metrics['arrears']) . '</td><td>Assign recovery owners and due dates.</td></tr><tr><td>Portfolio at Risk</td><td>' . number_format($metrics['par'], 2) . '%</td><td>Review exposures above the approved tolerance.</td></tr><tr><td>Customers in arrears</td><td>' . number_format($metrics['customers_arrears']) . '</td><td>Segment customers by age, amount, and cause.</td></tr><tr><td>Customer arrears percentage</td><td>' . number_format($metrics['customer_arrears_pct'], 2) . '%</td><td>Track weekly movement and collection effectiveness.</td></tr><tr><td>Performing book</td><td>' . $money($metrics['performing_book']) . '</td><td>Protect through consistent monitoring and service.</td></tr></table>');
    $section('5. TREND REVIEW');
    $trendRows = '';
    foreach ($trendLabels as $index => $label) {
        $trendRows .= '<tr><td>' . $safe($label) . '</td><td>' . $money($trendLoanBook[$index] ?? 0) . '</td><td>' . $money($trendInterest[$index] ?? 0) . '</td><td>' . $money($trendExpenses[$index] ?? 0) . '</td></tr>';
    }
    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><th><b>Period</b></th><th><b>Principal</b></th><th><b>Interest</b></th><th><b>Expenses</b></th></tr>' . ($trendRows ?: '<tr><td colspan="4">No trend data available.</td></tr>') . '</table>');
    $footer(2);

    $pdf->AddPage();
    $header('OFFICER PERFORMANCE AND RISK REVIEW');
    $section('6. OFFICER COMPARISON');
    $officerRows = '';
    foreach ($officerData as $officer) {
        $officerRows .= '<tr><td>' . $safe($officer['name']) . '</td><td>' . number_format($officer['loanCount']) . '</td><td>' . $money($officer['principal']) . '</td><td>' . $money($officer['loanBook']) . '</td><td>' . $money($officer['arrearsValue']) . '</td><td>' . number_format($officer['par'], 2) . '%</td><td>' . number_format($officer['customersInArrearsPercentage'], 2) . '%</td></tr>';
    }
    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><th width="18%"><b>Officer</b></th><th width="10%"><b>Loans</b></th><th width="17%"><b>Disbursed</b></th><th width="17%"><b>Loan book</b></th><th width="16%"><b>Arrears</b></th><th width="10%"><b>PAR</b></th><th width="12%"><b>Customer arrears</b></th></tr>' . ($officerRows ?: '<tr><td colspan="7">No officer data available.</td></tr>') . '</table>');
    $section('7. PERFORMANCE INTERPRETATION');
    $pdf->SetFont('helvetica', '', 8.8);
    $pdf->MultiCell(0, 5, 'Use this comparison to identify where coaching, portfolio redistribution, collection support, customer follow-up, or approval-quality review is required. High loan-book volume should be considered together with arrears, PAR, customer service, and documentation quality. Low volume should be investigated with regard to lead generation, conversion, market coverage, and operational constraints.', 0, 'L');
    $section('8. PRIORITY RISKS');
    $pdf->SetFont('helvetica', '', 8.8);
    $pdf->MultiCell(0, 5, '1. Review the highest-PAR officer and the largest individual arrears exposures.\n2. Confirm all overdue customers have documented contact attempts and next actions.\n3. Compare expense concentration with portfolio contribution.\n4. Escalate data gaps, unresolved complaints, suspected fraud, and policy exceptions.', 0, 'L');
    $footer(3);

    $pdf->AddPage();
    $header('OPERATING TRENDS AND MANAGEMENT ACTIONS');
    $section('9. TREND AND EFFICIENCY REVIEW');
    $pdf->SetFont('helvetica', '', 8.8);
    $pdf->MultiCell(0, 5, 'The accompanying dashboard charts present monthly principal deployment, interest generation, expenses, PAR mix, officer risk ranking, expense concentration, and customer-risk indicators. Management should compare current-period movement with the prior period, investigate material changes, and record the owner and deadline for each corrective action.', 0, 'L');
    $section('10. RECOMMENDED WEEKLY ACTION PLAN');
    $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th width="8%"><b>No.</b></th><th width="30%"><b>Action</b></th><th width="27%"><b>Owner</b></th><th width="20%"><b>Due</b></th><th width="15%"><b>Status</b></th></tr><tr><td>1</td><td>Review top arrears accounts and agree recovery plans.</td><td>Collections / Loan Officers</td><td>Within 7 days</td><td>Open</td></tr><tr><td>2</td><td>Coach officers with elevated PAR or customer arrears.</td><td>Manager</td><td>Next review</td><td>Open</td></tr><tr><td>3</td><td>Validate disbursement quality and supporting documentation.</td><td>Credit / Operations</td><td>Weekly</td><td>Open</td></tr><tr><td>4</td><td>Review expenses against interest and portfolio contribution.</td><td>Finance / Manager</td><td>Monthly</td><td>Open</td></tr><tr><td>5</td><td>Resolve data exceptions and update the management action register.</td><td>Reporting owner</td><td>Before presentation</td><td>Open</td></tr></table>');
    $section('11. DISCUSSION QUESTIONS');
    $pdf->SetFont('helvetica', '', 8.8);
    $pdf->MultiCell(0, 5, '• What caused the largest movement in arrears or PAR?\n• Which officer or customer segment needs immediate support?\n• Are current expenses producing measurable portfolio or service value?\n• What controls, staffing, training, or product changes are needed?\n• Which actions from the previous review remain incomplete?', 0, 'L');
    $footer(4);

    $pdf->AddPage();
    $header('APPENDIX AND MANAGEMENT SIGN-OFF');
    $section('12. METRIC DEFINITIONS');
    $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th width="30%"><b>Metric</b></th><th width="70%"><b>Definition used in this report</b></th></tr><tr><td>Principal disbursed</td><td>Approved loan principal released within the selected scope.</td></tr><tr><td>Loan book</td><td>Total approved loan amount less recorded payments and penalties, subject to the page query scope.</td></tr><tr><td>Interest</td><td>Total approved loan amount less principal.</td></tr><tr><td>Arrears</td><td>Scheduled overdue amounts less recorded payments and applicable penalty actions.</td></tr><tr><td>PAR</td><td>Arrears divided by outstanding loan book, expressed as a percentage.</td></tr><tr><td>Customer arrears</td><td>Active customers with a positive overdue amount divided by active customers.</td></tr><tr><td>Net operating yield</td><td>Interest generated less recorded loan-officer expenses.</td></tr></table>');
    $section('13. DATA QUALITY AND ASSUMPTIONS');
    $pdf->SetFont('helvetica', '', 8.8);
    $pdf->MultiCell(0, 5, 'This report is generated from approved loan applications, borrower assignments, repayment records, penalty actions, and loan-officer expense records available at generation time. Management should confirm that officer assignments, repayment postings, expense classifications, and period filters are complete before adopting the figures as final decisions.', 0, 'L');
    $section('14. MANAGEMENT SIGN-OFF');
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(90, 7, 'Reviewed by: __________________________', 0, 0, 'L');
    $pdf->Cell(0, 7, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->Ln(5);
    $pdf->Cell(90, 7, 'Action register owner: _________________', 0, 0, 'L');
    $pdf->Cell(0, 7, 'Next review: ____/____/________', 0, 1, 'L');
    $pdf->Ln(10);
    if (file_exists($stampPath)) {
        $pdf->SetAlpha(0.42);
        $pdf->Image($stampPath, 146, $pdf->GetY() - 8, 35, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
    }
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(90, 6, 'For Inua Premium Services', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Signature: __________________________', 0, 1, 'L');
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', 'I', 8.3);
    $pdf->Cell(0, 5, 'Prepared for informed weekly review, accountability, and continuous improvement.', 0, 1, 'C');
    $footer(5);
    return $pdf->Output('', 'S');
}

function sendWeeklyReviewEmail($recipient, $pdfContent, $filename, $periodLabel) {
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
    $mail->Subject = 'Weekly Portfolio Review - ' . $periodLabel;
    $mail->Body = '<p>Dear ' . htmlspecialchars($recipient['name'], ENT_QUOTES, 'UTF-8') . ',</p><p>Please find attached the detailed five-page weekly portfolio review for <strong>' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>The pack includes executive metrics, financial position, portfolio quality, officer comparison, risk review, trends, recommended actions, metric definitions, and management sign-off.</p><p>Regards,<br>Inua Premium Services<br>Management Information and Weekly Review</p>';
    $mail->AltBody = 'Please find attached the detailed five-page weekly portfolio review for ' . $periodLabel . '.';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

function getBorrowerLoans($conn, $borrowerId, $startDate = null, $endDate = null, $processingFeeColumn = null, $registrationFeeColumn = null) {
    $processingFeeSelect = $processingFeeColumn ? "COALESCE(la.$processingFeeColumn, 0) AS processing_fee" : '0 AS processing_fee';
    $registrationFeeSelect = $registrationFeeColumn ? "COALESCE(la.$registrationFeeColumn, 0) AS registration_fee" : '0 AS registration_fee';

    $sql = "SELECT la.id, la.principal, la.total_amount, la.loan_release_date,
                   (la.total_amount - la.principal) AS interest,
                   $processingFeeSelect,
                   $registrationFeeSelect,
                   COALESCE(SUM(r.paid), 0) + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = la.id), 0) AS total_paid
            FROM loan_applications la
            LEFT JOIN repayments r ON r.loan_id = la.id
            WHERE la.borrower = ? AND la.loan_status = 'approved'";

    $params = [$borrowerId];
    $types = 'i';

    if (!empty($startDate) && !empty($endDate)) {
        $sql .= " AND la.loan_release_date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= 'ss';
    }

    $sql .= ' GROUP BY la.id';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getBorrowerOverdue($conn, $borrowerId, $startDate = null, $endDate = null) {
    $sql = "SELECT GREATEST(
            COALESCE(SUM(CASE
                WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0)
                ELSE 0
            END), 0)
            - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0),
            0
        ) AS total_overdue
        FROM borrowers
        LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
        LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
        WHERE borrowers.id = ? AND loan_applications.loan_status = 'approved'";

    $params = [$borrowerId];
    $types = 'i';

    if (!empty($startDate) && !empty($endDate)) {
        $sql .= ' AND loan_applications.loan_release_date BETWEEN ? AND ?';
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= 'ss';
    }

    $sql .= ' GROUP BY borrowers.id';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0.0;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (float) ($row['total_overdue'] ?? 0.0);
}

function findFeeColumn($conn, $candidates = []) {
    if (empty($candidates)) {
        $candidates = ['processing_fee', 'processingfee', 'processing_fee_kes', 'reg_fee', 'registration_fee', 'registration_fees', 'regfee'];
    }

    foreach ($candidates as $col) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_applications' AND COLUMN_NAME = ?");
        if (!$stmt) continue;
        $stmt->bind_param('s', $col);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!empty($res) && (int) $res['cnt'] > 0) {
            return $col;
        }
    }
    return null;
}

$requestValues = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$reportScope = isset($requestValues['report_scope']) ? $requestValues['report_scope'] : 'all';
$selectedYear = isset($requestValues['report_year']) ? (int) $requestValues['report_year'] : 0;
$selectedMonth = isset($requestValues['report_month']) ? (int) $requestValues['report_month'] : 0;
$selectedWeek = isset($requestValues['report_week']) ? (int) $requestValues['report_week'] : 0;
$selected_area = isset($requestValues['area_id']) ? $requestValues['area_id'] : 'all';
$selected_area = ($selected_area !== 'all' && !is_numeric($selected_area)) ? 'all' : $selected_area;
$selected_officer = isset($requestValues['officer_id']) ? $requestValues['officer_id'] : 'all';
$selected_officer = ($selected_officer !== 'all' && !is_numeric($selected_officer)) ? 'all' : $selected_officer;

$areas = [];
$areasStmt = $conn->prepare('SELECT area_id, area_name FROM areas ORDER BY area_name');
$areasStmt->execute();
$areasResult = $areasStmt->get_result();
while ($area = $areasResult->fetch_assoc()) {
    $areas[] = $area;
}

$selectedYear = $selectedYear > 0 ? max(2000, min(2100, $selectedYear)) : 0;
$selectedMonth = $selectedMonth > 0 ? max(1, min(12, $selectedMonth)) : 0;
$selectedWeek = max(0, min(4, $selectedWeek));

$periodStart = null;
$periodEnd = null;
$periodLabel = 'General report';

if ($reportScope === 'custom' && ($selectedYear > 0 || $selectedMonth > 0 || $selectedWeek > 0)) {
    if ($selectedWeek > 0) {
        $periodStart = sprintf('%04d-%02d-%02d', $selectedYear > 0 ? $selectedYear : date('Y'), $selectedMonth > 0 ? $selectedMonth : date('m'), 1 + (($selectedWeek - 1) * 7));
        $periodEnd = date('Y-m-d', strtotime($periodStart . ' +6 days'));
        $periodLabel = 'Week ' . $selectedWeek . ' of ' . date('F Y', strtotime($periodStart));
    } elseif ($selectedYear > 0 && $selectedMonth > 0) {
        $periodStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $periodLabel = date('F Y', strtotime($periodStart));
    } elseif ($selectedYear > 0) {
        $periodStart = sprintf('%04d-01-01', $selectedYear);
        $periodEnd = sprintf('%04d-12-31', $selectedYear);
        $periodLabel = 'Year ' . $selectedYear;
    }
}

$officerSql = "SELECT id, name AS full_name, email, area FROM users WHERE role_id = '2'";
$officerParams = [];
$officerTypes = '';
if ($selected_area !== 'all') {
    $officerSql .= ' AND area = ?';
    $officerParams[] = (int) $selected_area;
    $officerTypes .= 'i';
}
if ($selected_officer !== 'all') {
    $officerSql .= ' AND id = ?';
    $officerParams[] = (int) $selected_officer;
    $officerTypes .= 'i';
}
$officerSql .= ' ORDER BY name';
$officerStmt = $conn->prepare($officerSql);
if (!empty($officerParams)) {
    $officerStmt->bind_param($officerTypes, ...$officerParams);
}
$officerStmt->execute();
$officerResult = $officerStmt->get_result();
$officerOptions = [];
while ($officer = $officerResult->fetch_assoc()) {
    $officerOptions[] = $officer;
}

$processingFeeColumn = findFeeColumn($conn, ['processing_fee', 'processingfee', 'processing_fee_kes']);
$registrationFeeColumn = findFeeColumn($conn, ['registration_fee', 'registration_fees', 'reg_fee', 'regfee']);

$officerData = [];
foreach ($officerOptions as $officer) {
    $officerEmail = $officer['email'];
    $borrowerStmt = $conn->prepare('SELECT id FROM borrowers WHERE loan_officer = ?');
    $borrowerStmt->bind_param('s', $officerEmail);
    $borrowerStmt->execute();
    $borrowerResult = $borrowerStmt->get_result();

    $principal = 0.0;
    $loanBook = 0.0;
    $arrearsValue = 0.0;
    $paidAmount = 0.0;
    $customers = 0;
    $customersInArrears = 0;
    $fundedCustomers = 0;
    $disbursementValue = 0.0;
    $interestTotal = 0.0;
    $processingFeeTotal = 0.0;
    $registrationFeeTotal = 0.0;
    $paidTotal = 0.0;
    $loanCount = 0;

    while ($borrower = $borrowerResult->fetch_assoc()) {
        $borrowerId = (int) $borrower['id'];
        $borrowerLoans = getBorrowerLoans($conn, $borrowerId, $periodStart, $periodEnd, $processingFeeColumn, $registrationFeeColumn);
        if (empty($borrowerLoans)) {
            continue;
        }

        $borrowerHasActiveBalance = false;
        $borrowerFundedInPeriod = false;

        foreach ($borrowerLoans as $loan) {
            $loanPrincipal = (float) $loan['principal'];
            $loanAmount = (float) $loan['total_amount'];
            $loanPaid = (float) $loan['total_paid'];
            $loanOutstanding = max(0.0, $loanAmount - $loanPaid);

            $loanProcessingFee = (float) ($loan['processing_fee'] ?? 0.0);
            $loanRegistrationFee = (float) ($loan['registration_fee'] ?? 0.0);
            $loanInterest = (float) ($loan['interest'] ?? max(0.0, $loanAmount - $loanPrincipal));

            $principal += $loanPrincipal;
            $loanBook += $loanOutstanding;
            $paidAmount += $loanPaid;
            $disbursementValue += $loanPrincipal;
            $interestTotal += $loanInterest;
            $processingFeeTotal += $loanProcessingFee;
            $registrationFeeTotal += $loanRegistrationFee;
            $paidTotal += $loanPaid;
            $loanCount++;

            if ($loanOutstanding > 0) {
                $borrowerHasActiveBalance = true;
            }

            $loanReleaseDate = $loan['loan_release_date'];
            if (empty($periodStart) || empty($periodEnd)) {
                $borrowerFundedInPeriod = true;
            } elseif (!empty($loanReleaseDate) && $loanReleaseDate >= $periodStart && $loanReleaseDate <= $periodEnd) {
                $borrowerFundedInPeriod = true;
            }
        }

        if ($borrowerHasActiveBalance) {
            $customers++;
        }
        if ($borrowerFundedInPeriod) {
            $fundedCustomers++;
        }

        $borrowerOverdue = getBorrowerOverdue($conn, $borrowerId, $periodStart, $periodEnd);
        if ($borrowerOverdue > 0 && $borrowerHasActiveBalance) {
            $customersInArrears++;
        }

        $arrearsValue += $borrowerOverdue;
    }

    $performingBook = max(0.0, $loanBook - $arrearsValue);
    $parPercentage = $loanBook > 0 ? ($arrearsValue / $loanBook) * 100 : 0;
    $customersInArrearsPercentage = $customers > 0 ? ($customersInArrears / $customers) * 100 : 0;

    $officerData[] = [
        'name' => $officer['full_name'],
        'principal' => round($principal, 2),
        'loanBook' => round($loanBook, 2),
        'performingBook' => round($performingBook, 2),
        'arrearsValue' => round($arrearsValue, 2),
        'interest' => round($interestTotal, 2),
        'processingFee' => round($processingFeeTotal, 2),
        'registrationFee' => round($registrationFeeTotal, 2),
        'paid' => round($paidTotal, 2),
        'loanCount' => $loanCount,
        'par' => round($parPercentage, 2),
        'customers' => (int) $customers,
        'customersInArrears' => (int) $customersInArrears,
        'customersInArrearsPercentage' => round($customersInArrearsPercentage, 2),
        'recruitedCustomers' => (int) $fundedCustomers,
        'fundedCustomers' => (int) $fundedCustomers,
        'disbursementValue' => round($disbursementValue, 2),
        'periodLabel' => $periodLabel,
    ];
}

$monthlyActivityMap = [];

$trendSql = "SELECT DATE_FORMAT(loan_release_date, '%Y-%m') AS month_key,
                SUM(principal) AS principal_value,
                SUM((total_amount - principal)) AS interest_value
            FROM loan_applications
            WHERE loan_status = 'approved'";
if ($reportScope === 'custom' && !empty($periodStart) && !empty($periodEnd)) {
    $trendSql .= ' AND loan_release_date BETWEEN ? AND ?';
    $trendSql .= " GROUP BY DATE_FORMAT(loan_release_date, '%Y-%m') ORDER BY month_key ASC";
    $trendStmt = $conn->prepare($trendSql);
    $trendStmt->bind_param('ss', $periodStart, $periodEnd);
    $trendStmt->execute();
} else {
    $trendSql .= " GROUP BY DATE_FORMAT(loan_release_date, '%Y-%m') ORDER BY month_key ASC";
    $trendStmt = $conn->prepare($trendSql);
    $trendStmt->execute();
}
$trendRows = $trendStmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($trendRows as $row) {
    $monthKey = (string) ($row['month_key'] ?? '');
    if ($monthKey === '') {
        continue;
    }

    $monthlyActivityMap[$monthKey] = [
        'loan_book' => (float) ($row['principal_value'] ?? 0),
        'interest' => (float) ($row['interest_value'] ?? 0),
        'expense' => 0.0,
    ];
}

$expenseTrendSql = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month_key,
                    SUM(amount) AS expense_value
                FROM loan_officer_expenses
                WHERE 1=1";
if ($reportScope === 'custom' && !empty($periodStart) && !empty($periodEnd)) {
    $expenseTrendSql .= ' AND expense_date BETWEEN ? AND ?';
    $expenseTrendSql .= " GROUP BY DATE_FORMAT(expense_date, '%Y-%m') ORDER BY month_key ASC";
    $expenseTrendStmt = $conn->prepare($expenseTrendSql);
    $expenseTrendStmt->bind_param('ss', $periodStart, $periodEnd);
    $expenseTrendStmt->execute();
} else {
    $expenseTrendSql .= " GROUP BY DATE_FORMAT(expense_date, '%Y-%m') ORDER BY month_key ASC";
    $expenseTrendStmt = $conn->prepare($expenseTrendSql);
    $expenseTrendStmt->execute();
}
$expenseTrendRows = $expenseTrendStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($expenseTrendRows as $row) {
    $monthKey = (string) ($row['month_key'] ?? '');
    if ($monthKey === '') {
        continue;
    }
    if (!isset($monthlyActivityMap[$monthKey])) {
        $monthlyActivityMap[$monthKey] = ['loan_book' => 0.0, 'interest' => 0.0, 'expense' => 0.0];
    }
    $monthlyActivityMap[$monthKey]['expense'] = (float) ($row['expense_value'] ?? 0.0);
}

uksort($monthlyActivityMap, fn ($a, $b) => strcmp($a, $b));

$trendLabels = [];
$trendLoanBook = [];
$trendInterest = [];
$trendExpenses = [];
foreach ($monthlyActivityMap as $monthKey => $monthData) {
    $trendLabels[] = date('M Y', strtotime($monthKey . '-01'));
    $trendLoanBook[] = (float) ($monthData['loan_book'] ?? 0);
    $trendInterest[] = (float) ($monthData['interest'] ?? 0);
    $trendExpenses[] = (float) ($monthData['expense'] ?? 0);
}

$expenseOfficerSql = "SELECT loan_officer_name AS officer_name, SUM(amount) AS total_expense
                    FROM loan_officer_expenses
                    WHERE 1=1";
$expenseOfficerParams = [];
$expenseOfficerTypes = '';
if ($reportScope === 'custom' && !empty($periodStart) && !empty($periodEnd)) {
    $expenseOfficerSql .= ' AND expense_date BETWEEN ? AND ?';
    $expenseOfficerParams[] = $periodStart;
    $expenseOfficerParams[] = $periodEnd;
    $expenseOfficerTypes = 'ss';
}
$expenseOfficerSql .= ' GROUP BY loan_officer_name ORDER BY total_expense DESC LIMIT 6';
$expenseOfficerStmt = $conn->prepare($expenseOfficerSql);
if (!empty($expenseOfficerParams)) {
    $expenseOfficerStmt->bind_param($expenseOfficerTypes, ...$expenseOfficerParams);
}
$expenseOfficerStmt->execute();
$expenseOfficerRows = $expenseOfficerStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$expenseOfficerLabels = [];
$expenseOfficerValues = [];
foreach ($expenseOfficerRows as $row) {
    $expenseOfficerLabels[] = $row['officer_name'] ?? 'Unknown';
    $expenseOfficerValues[] = (float) ($row['total_expense'] ?? 0);
}

function pickExtremeOfficer(array $officerData, string $metric, bool $highest = true): array {
    if (empty($officerData)) {
        return ['name' => 'N/A'];
    }

    $winner = $officerData[0];
    $bestValue = (float) ($winner[$metric] ?? 0.0);

    foreach ($officerData as $officer) {
        $currentValue = (float) ($officer[$metric] ?? 0.0);
        if (($highest && $currentValue > $bestValue) || (!$highest && $currentValue < $bestValue)) {
            $winner = $officer;
            $bestValue = $currentValue;
        }
    }

    return $winner;
}

$overallTotalLoanBook = array_sum(array_column($officerData, 'loanBook'));
$overallArrears = array_sum(array_column($officerData, 'arrearsValue'));
$overallCustomers = array_sum(array_column($officerData, 'customers'));
$overallCustomersInArrears = array_sum(array_column($officerData, 'customersInArrears'));
$overallInterest = array_sum(array_column($officerData, 'interest'));
$overallPrincipal = array_sum(array_column($officerData, 'principal'));
$overallPaid = array_sum(array_column($officerData, 'paid'));
$overallFees = array_sum(array_column($officerData, 'processingFee')) + array_sum(array_column($officerData, 'registrationFee'));
$overallLoanCount = array_sum(array_column($officerData, 'loanCount'));
$overallExpenses = 0.0;
$expenseStatement = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM loan_officer_expenses WHERE 1=1");
if ($reportScope === 'custom' && !empty($periodStart) && !empty($periodEnd)) {
    $expenseStatement = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM loan_officer_expenses WHERE expense_date BETWEEN ? AND ?");
    $expenseStatement->bind_param('ss', $periodStart, $periodEnd);
}
$expenseStatement->execute();
$expenseStatementResult = $expenseStatement->get_result()->fetch_assoc();
$overallExpenses = (float) ($expenseStatementResult['total_expenses'] ?? 0.0);
$overallPar = $overallTotalLoanBook > 0 ? ($overallArrears / $overallTotalLoanBook) * 100 : 0;
$overallCustomerArrearsPct = $overallCustomers > 0 ? ($overallCustomersInArrears / $overallCustomers) * 100 : 0;
$netOperatingYield = $overallInterest - $overallExpenses;
$topOfficer = pickExtremeOfficer($officerData, 'loanBook', true);
$riskOfficer = pickExtremeOfficer($officerData, 'par', true);

$reportMetrics = [
    'officers' => count($officerData),
    'loan_count' => $overallLoanCount,
    'customers' => $overallCustomers,
    'customers_arrears' => $overallCustomersInArrears,
    'customer_arrears_pct' => $overallCustomerArrearsPct,
    'principal' => $overallPrincipal,
    'paid' => $overallPaid,
    'loan_book' => $overallTotalLoanBook,
    'performing_book' => max(0, $overallTotalLoanBook - $overallArrears),
    'arrears' => $overallArrears,
    'par' => $overallPar,
    'interest' => $overallInterest,
    'fees' => $overallFees,
    'expenses' => $overallExpenses,
    'net_yield' => $netOperatingYield,
];

$performanceInsights = [];
if (!empty($officerData)) {
    $avgPar = array_sum(array_column($officerData, 'par')) / count($officerData);
    $performanceInsights[] = 'Portfolio average PAR stands at ' . number_format($avgPar, 2) . '%, which indicates the current portfolio quality trend.';
    $performanceInsights[] = 'The largest book is held by ' . htmlspecialchars($topOfficer['name']) . ' with KES ' . number_format((float) $topOfficer['loanBook'], 2) . ' in outstanding portfolio.';
    $performanceInsights[] = 'Highest risk exposure is recorded with ' . htmlspecialchars($riskOfficer['name']) . ' at ' . number_format((float) $riskOfficer['par'], 2) . '% PAR, warranting closer review.';
    $performanceInsights[] = 'Customers in arrears represent ' . number_format($overallCustomerArrearsPct, 2) . '% of active customers, which signals the need for targeted collections interventions.';
    $performanceInsights[] = 'Interest income totals KES ' . number_format($overallInterest, 2) . ' while expenses stand at KES ' . number_format($overallExpenses, 2) . ', leaving a net operating yield of KES ' . number_format($netOperatingYield, 2) . '.';
    $performanceInsights[] = 'The organization should monitor whether the expense base remains well below the revenue generated from portfolio growth and interest accumulation.';
}

$comparisonTable = [];
foreach ($officerData as $officer) {
    $comparisonTable[] = [
        'name' => $officer['name'],
        'loanBook' => (float) $officer['loanBook'],
        'par' => (float) $officer['par'],
        'arrears' => (float) $officer['arrearsValue'],
        'customers' => (int) $officer['customers'],
        'activeCustomersInArrears' => (int) $officer['customersInArrears'],
        'percentageInArrears' => (float) $officer['customersInArrearsPercentage'],
    ];
}
usort($comparisonTable, fn ($a, $b) => $b['par'] <=> $a['par']);

$analyticsManagers = getAnalyticsManagers($conn);
$sendMessage = '';
$sendStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_weekly_review'])) {
    $managerId = (int) ($_POST['manager_id'] ?? 0);
    $selectedManager = null;
    foreach ($analyticsManagers as $manager) {
        if ((int) $manager['id'] === $managerId) {
            $selectedManager = $manager;
            break;
        }
    }
    if (!$selectedManager || !filter_var($selectedManager['email'], FILTER_VALIDATE_EMAIL)) {
        $sendMessage = 'Select a manager with a valid email address.';
        $sendStatus = 'danger';
    } else {
        try {
            $pdfContent = generateWeeklyReviewPdf($reportMetrics, $officerData, $trendLabels, $trendLoanBook, $trendInterest, $trendExpenses, $periodLabel, $_SESSION['email'] ?? 'Management');
            $filename = 'weekly_portfolio_review_' . date('Ymd_His') . '.pdf';
            sendWeeklyReviewEmail($selectedManager, $pdfContent, $filename, $periodLabel);
            $sendMessage = 'The five-page weekly review was sent to ' . $selectedManager['name'] . '.';
            $sendStatus = 'success';
        } catch (Exception $e) {
            $sendMessage = 'The weekly review could not be sent: ' . $e->getMessage();
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
    <title>Analytical Reporting Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 35%, #f8fafc 100%);
            color: #0f172a;
        }
        .glass {
            background: rgba(255,255,255,0.86);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(148,163,184,0.2);
        }
        .metric-card {
            border-radius: 20px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }
        .chart-shell {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.06);
        }
        .insight-box {
            border-left: 5px solid #2563eb;
            background: linear-gradient(90deg, rgba(37,99,235,0.08), rgba(255,255,255,1));
        }
        table th {
            background: #f8fafc;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row items-center justify-between gap-4 mb-8">
            <div>
                <p class="text-sm uppercase tracking-[0.2em] text-blue-600 font-bold">Analytical Tool</p>
                <h1 class="text-4xl font-black text-slate-900">Portfolio Intelligence & Performance Analytics</h1>
            </div>
            <div class="flex gap-3">
                <a href="report_review.php" class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-3 rounded-xl font-semibold hover:bg-blue-700 shadow-lg">
                    Report Review
                </a>
                <a href="par.php<?php echo isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="inline-flex items-center gap-2 bg-slate-800 text-white px-5 py-3 rounded-xl font-semibold hover:bg-slate-700 shadow-lg">
                    ← Back to PAR Dashboard
                </a>
            </div>
        </div>

        <?php if ($sendMessage): ?><div class="mb-6 rounded-xl border px-5 py-4 <?php echo $sendStatus === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo htmlspecialchars($sendMessage); ?></div><?php endif; ?>

        <div class="glass p-5 rounded-2xl mb-8">
            <form method="post" class="flex flex-col lg:flex-row lg:items-end gap-4">
                <input type="hidden" name="report_scope" value="<?php echo htmlspecialchars($reportScope); ?>">
                <input type="hidden" name="report_year" value="<?php echo (int) $selectedYear; ?>">
                <input type="hidden" name="report_month" value="<?php echo (int) $selectedMonth; ?>">
                <input type="hidden" name="report_week" value="<?php echo (int) $selectedWeek; ?>">
                <input type="hidden" name="area_id" value="<?php echo htmlspecialchars($selected_area); ?>">
                <input type="hidden" name="officer_id" value="<?php echo htmlspecialchars($selected_officer); ?>">
                <div class="flex-1">
                    <label for="manager_id" class="block text-sm font-semibold text-slate-700 mb-2">Send five-page weekly review to manager</label>
                    <select id="manager_id" name="manager_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                        <option value="">Select manager recipient</option>
                        <?php foreach ($analyticsManagers as $manager): ?>
                            <option value="<?php echo (int) $manager['id']; ?>"><?php echo htmlspecialchars($manager['name'] . ' - ' . $manager['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="send_weekly_review" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-lg hover:bg-blue-700">
                    <span aria-hidden="true">✉</span> Send Weekly Review PDF
                </button>
            </form>
            <p class="mt-3 text-sm text-slate-500">The attachment contains five pages covering executive results, financial metrics, risk, officer comparison, trends, actions, definitions, and sign-off.</p>
        </div>

        <div class="glass p-5 rounded-2xl mb-8">
            <div class="flex flex-wrap gap-2">
                <span class="px-3 py-2 rounded-full bg-blue-100 text-blue-700 text-sm font-semibold">Period: <?php echo htmlspecialchars($periodLabel); ?></span>
                <span class="px-3 py-2 rounded-full bg-emerald-100 text-emerald-700 text-sm font-semibold">Total officers: <?php echo count($officerData); ?></span>
                <span class="px-3 py-2 rounded-full bg-violet-100 text-violet-700 text-sm font-semibold">Analysis depth: Detailed</span>
            </div>
        </div>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div class="metric-card p-6 bg-gradient-to-br from-blue-600 to-blue-500 text-white">
                <p class="text-sm uppercase tracking-wide text-blue-100">Total Loan Book</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($overallTotalLoanBook, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-amber-500 to-orange-500 text-white">
                <p class="text-sm uppercase tracking-wide text-orange-100">Total Arrears</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($overallArrears, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-emerald-500 to-teal-500 text-white">
                <p class="text-sm uppercase tracking-wide text-emerald-100">Interest Income</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($overallInterest, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-rose-500 to-red-500 text-white">
                <p class="text-sm uppercase tracking-wide text-rose-100">Portfolio PAR</p>
                <h3 class="text-3xl font-black mt-3"><?php echo number_format($overallPar, 2); ?>%</h3>
            </div>
        </section>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div class="metric-card p-6 bg-gradient-to-br from-violet-600 to-indigo-500 text-white">
                <p class="text-sm uppercase tracking-wide text-violet-100">Total Expenses</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($overallExpenses, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-cyan-600 to-sky-500 text-white">
                <p class="text-sm uppercase tracking-wide text-cyan-100">Net Operating Yield</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($netOperatingYield, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-lime-600 to-green-500 text-white">
                <p class="text-sm uppercase tracking-wide text-lime-100">Active Customers</p>
                <h3 class="text-3xl font-black mt-3"><?php echo number_format($overallCustomers, 0); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-slate-700 to-slate-600 text-white">
                <p class="text-sm uppercase tracking-wide text-slate-100">Customer Arrears</p>
                <h3 class="text-3xl font-black mt-3"><?php echo number_format($overallCustomerArrearsPct, 2); ?>%</h3>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
            <div class="chart-shell p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-900">Portfolio Trend Analysis</h2>
                    <span class="text-xs font-semibold uppercase text-slate-500">Loan book + interest + expenses</span>
                </div>
                <div class="h-[360px]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="chart-shell p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-900">PAR Mix Snapshot</h2>
                    <span class="text-xs font-semibold uppercase text-slate-500">Risk split</span>
                </div>
                <div class="h-[360px]">
                    <canvas id="parChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
            <div class="chart-shell p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-900">Monthly Performance Summary</h2>
                    <span class="text-xs font-semibold uppercase text-slate-500">Interest vs expense efficiency</span>
                </div>
                <div class="h-[360px]">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
            <div class="chart-shell p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-900">Top Expense Contributors</h2>
                    <span class="text-xs font-semibold uppercase text-slate-500">By officer</span>
                </div>
                <div class="h-[360px]">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
            <div class="chart-shell p-5 xl:col-span-2">
                <h2 class="text-xl font-bold text-slate-900 mb-5">Officer Performance Ranking</h2>
                <div class="h-[420px]">
                    <canvas id="officerChart"></canvas>
                </div>
            </div>
            <div class="chart-shell p-5">
                <h2 class="text-xl font-bold text-slate-900 mb-5">Executive Insights</h2>
                <div class="space-y-4">
                    <?php foreach ($performanceInsights as $insight): ?>
                        <div class="insight-box rounded-xl p-4 text-sm text-slate-700">
                            <?php echo htmlspecialchars($insight); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="chart-shell p-5 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
                <h2 class="text-xl font-bold text-slate-900">Detailed Officer Analytics</h2>
                <div class="text-sm text-slate-600">Risk, customer base, and operational exposure overview</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 border-b border-slate-200">Officer</th>
                            <th class="px-4 py-3 border-b border-slate-200">Loan Book</th>
                            <th class="px-4 py-3 border-b border-slate-200">Arrears</th>
                            <th class="px-4 py-3 border-b border-slate-200">PAR %</th>
                            <th class="px-4 py-3 border-b border-slate-200">Active Customers</th>
                            <th class="px-4 py-3 border-b border-slate-200">In Arrears</th>
                            <th class="px-4 py-3 border-b border-slate-200">% in Arrears</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparisonTable as $entry): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 border-b border-slate-100 font-semibold"><?php echo htmlspecialchars($entry['name']); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100">KES <?php echo number_format($entry['loanBook'], 2); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100">KES <?php echo number_format($entry['arrears'], 2); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100"><?php echo number_format($entry['par'], 2); ?>%</td>
                                <td class="px-4 py-3 border-b border-slate-100"><?php echo number_format($entry['customers'], 0); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100"><?php echo number_format($entry['activeCustomersInArrears'], 0); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100"><?php echo number_format($entry['percentageInArrears'], 2); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <div class="chart-shell p-5">
                <h2 class="text-xl font-bold text-slate-900 mb-5">Customer Risk Exposure</h2>
                <div class="h-[300px]">
                    <canvas id="customerRiskChart"></canvas>
                </div>
            </div>
            <div class="chart-shell p-5">
                <h2 class="text-xl font-bold text-slate-900 mb-5">Portfolio Quality Summary</h2>
                <div class="space-y-5 text-sm text-slate-700">
                    <div class="flex justify-between items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span>Total customers in arrears</span>
                        <strong class="text-slate-900"><?php echo number_format($overallCustomersInArrears, 0); ?></strong>
                    </div>
                    <div class="flex justify-between items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span>Customer arrears percentage</span>
                        <strong class="text-slate-900"><?php echo number_format($overallCustomerArrearsPct, 2); ?>%</strong>
                    </div>
                    <div class="flex justify-between items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span>Largest portfolio owner</span>
                        <strong class="text-slate-900"><?php echo htmlspecialchars($topOfficer['name']); ?></strong>
                    </div>
                    <div class="flex justify-between items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span>Highest PAR risk</span>
                        <strong class="text-slate-900"><?php echo htmlspecialchars($riskOfficer['name']); ?> (<?php echo number_format((float) $riskOfficer['par'], 2); ?>%)</strong>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        const officerData = <?php echo json_encode($officerData); ?>;
        const sortedOfficers = [...officerData].sort((a, b) => Number(b.par) - Number(a.par));
        const officerNames = sortedOfficers.map(item => item.name);
        const officerParValues = sortedOfficers.map(item => Number(item.par || 0));
        const officerArrearsValues = sortedOfficers.map(item => Number(item.arrearsValue || 0));
        const loanBookValues = sortedOfficers.map(item => Number(item.loanBook || 0));

        const trendLabels = <?php echo json_encode($trendLabels); ?>;
        const trendLoanBook = <?php echo json_encode($trendLoanBook); ?>;
        const trendInterest = <?php echo json_encode($trendInterest); ?>;
        const trendExpenses = <?php echo json_encode($trendExpenses); ?>;
        const expenseOfficerLabels = <?php echo json_encode($expenseOfficerLabels); ?>;
        const expenseOfficerValues = <?php echo json_encode($expenseOfficerValues); ?>;

        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendLabels.length ? trendLabels : ['No data'],
                datasets: [
                    {
                        label: 'Loan Book',
                        data: trendLoanBook.length ? trendLoanBook : [0],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.10)',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Interest Income',
                        data: trendInterest.length ? trendInterest : [0],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.08)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Expenses',
                        data: trendExpenses.length ? trendExpenses : [0],
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249,115,22,0.08)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { ticks: { callback: value => 'KES ' + Number(value).toLocaleString() } }
                }
            }
        });

        new Chart(document.getElementById('performanceChart'), {
            type: 'bar',
            data: {
                labels: trendLabels.length ? trendLabels : ['No data'],
                datasets: [
                    {
                        label: 'Interest',
                        data: trendInterest.length ? trendInterest : [0],
                        backgroundColor: 'rgba(16,185,129,0.85)',
                        borderRadius: 8
                    },
                    {
                        label: 'Expenses',
                        data: trendExpenses.length ? trendExpenses : [0],
                        backgroundColor: 'rgba(249,115,22,0.85)',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { ticks: { callback: value => 'KES ' + Number(value).toLocaleString() } }
                }
            }
        });

        new Chart(document.getElementById('expenseChart'), {
            type: 'doughnut',
            data: {
                labels: expenseOfficerLabels.length ? expenseOfficerLabels : ['No expense data'],
                datasets: [{
                    data: expenseOfficerValues.length ? expenseOfficerValues : [0],
                    backgroundColor: ['#8b5cf6', '#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#14b8a6'],
                    borderWidth: 2,
                    hoverOffset: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: context => 'KES ' + Number(context.parsed).toLocaleString() } }
                }
            }
        });

        new Chart(document.getElementById('parChart'), {
            type: 'doughnut',
            data: {
                labels: ['Performing Book', 'Arrears'],
                datasets: [{
                    data: [
                        <?php echo max(0, $overallTotalLoanBook - $overallArrears); ?>,
                        <?php echo max(0, $overallArrears); ?>
                    ],
                    backgroundColor: ['#10b981', '#f97316'],
                    borderWidth: 2,
                    hoverOffset: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: context => 'KES ' + Number(context.parsed).toLocaleString() } }
                }
            }
        });

        new Chart(document.getElementById('officerChart'), {
            type: 'bar',
            data: {
                labels: officerNames,
                datasets: [
                    {
                        label: 'PAR %',
                        data: officerParValues,
                        backgroundColor: '#2563eb',
                        borderRadius: 8
                    },
                    {
                        label: 'Arrears (KES)',
                        data: officerArrearsValues,
                        backgroundColor: '#f97316',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: { callback: value => Number(value).toLocaleString() }
                    }
                }
            }
        });

        new Chart(document.getElementById('customerRiskChart'), {
            type: 'radar',
            data: {
                labels: ['Risk', 'Customer Base', 'Arrears', 'Performance', 'Book Size'],
                datasets: [{
                    label: 'Portfolio Health Index',
                    data: [
                        <?php echo min(100, max(0, $overallPar)); ?>,
                        <?php echo min(100, max(0, ($overallCustomers / (count($officerData) || 1)) * 10)); ?>,
                        <?php echo min(100, max(0, $overallCustomerArrearsPct)); ?>,
                        <?php echo min(100, max(0, 100 - $overallPar)); ?>,
                        <?php echo min(100, max(0, ($overallTotalLoanBook / (count($officerData) || 1)) / 10000)); ?>
                    ],
                    backgroundColor: 'rgba(37,99,235,0.20)',
                    borderColor: '#2563eb',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
