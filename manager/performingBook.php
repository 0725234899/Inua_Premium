<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

include '../includes/functions.php';
include("includes/header.php");
include 'db.php';

function send_pdf_email($recipient_email, $subject, $body, $pdf_content, $filename) {
    $emailCredentials = getEmailAccount();
    if (empty($emailCredentials['sender_email']) || empty($emailCredentials['sender_app_password'])) {
        throw new Exception('Email settings are not configured.');
    }

    $mail = new PHPMailer(true);
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    $mail->Username = $emailCredentials['sender_email'];
    $mail->Password = $emailCredentials['sender_app_password'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($emailCredentials['sender_email'], 'Inua Premium Services');
    $mail->addAddress($recipient_email);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = strip_tags($body);
    $mail->addStringAttachment($pdf_content, $filename, 'base64', 'application/pdf');
    $mail->send();
    return true;
}

function generate_performing_book_pdf($rows, $officer_display_name, $day_label, $region_label = 'All Regions') {
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor('Inua Premium Services');
    $pdf->SetTitle('Performing Book');
    $leftMargin = 12;
    $rightMargin = 12;
    $topMargin = 15;
    $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $contentWidth = $pdf->getPageWidth() - ($leftMargin + $rightMargin);
    $pdf->SetTextColor(56, 152, 219);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 8, 'Inua Premium Services', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Performing Book', 0, 1, 'C');
    $pdf->SetDrawColor(56, 152, 219);
    $pdf->SetLineWidth(0.35);
    $yLine = $pdf->GetY() + 2;
    $pdf->Line($leftMargin, $yLine, $leftMargin + $contentWidth, $yLine);
    $pdf->Ln(4);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(0, 6, 'Loan Officer: ' . $officer_display_name, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Region: ' . $region_label, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Day Filter: ' . $day_label, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Generated on: ' . date('d/m/Y H:i'), 0, 1, 'L');
    $pdf->Ln(2);

    // Define column widths proportional to content width
    $colWidths = [
        intval($contentWidth * 0.16),
        intval($contentWidth * 0.10),
        intval($contentWidth * 0.09),
        intval($contentWidth * 0.08),
        intval($contentWidth * 0.08),
        intval($contentWidth * 0.08),
        intval($contentWidth * 0.10),
        intval($contentWidth * 0.09),
        intval($contentWidth * 0.13),
        $contentWidth - (intval($contentWidth * 0.16) + intval($contentWidth * 0.10) + intval($contentWidth * 0.09) + intval($contentWidth * 0.08) + intval($contentWidth * 0.08) + intval($contentWidth * 0.08) + intval($contentWidth * 0.10) + intval($contentWidth * 0.09) + intval($contentWidth * 0.13))
    ];

    $headers = ['Borrower', 'Loan Release Date', 'Loan Duration', 'Principal', 'Total Amount', 'Total Paid', 'Loan Balance', 'Arrears', 'Maturity Date', 'Status'];

    // Table header rendering helper (used for initial page and repeated on new pages)
    $tableHeaderHeight = 8;
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(56, 152, 219);
    $pdf->SetTextColor(255, 255, 255);

    $printTableHeader = function() use ($pdf, $headers, $colWidths, $leftMargin, $tableHeaderHeight) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(56, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetX($leftMargin);
        foreach ($headers as $i => $h) {
            $pdf->Cell($colWidths[$i], $tableHeaderHeight, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();
        // prepare for row output
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->SetFont('helvetica', '', 8);
    };

    // Print the first header row
    $printTableHeader();

    $lineHeight = 4; // approximate
    // compute page break trigger once
    $pageBreakTrigger = $pdf->getPageHeight() - $pdf->getBreakMargin();
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $loanStatusValue = strtolower(trim((string) ($row['loan_status'] ?? '')));
            $isRolledOver = stripos($loanStatusValue, 'roll') !== false;

            if ($isRolledOver) {
                $duration = isset($row['active_loan_duration']) ? (int)$row['active_loan_duration'] : (int)($row['loan_duration'] ?? 0);
                $unit = strtolower($row['active_loan_duration_unit'] ?? $row['loan_duration_unit'] ?? '');
            } else {
                $duration = isset($row['loan_duration']) ? (int)$row['loan_duration'] : 0;
                $unit = strtolower($row['loan_duration_unit'] ?? '');
            }
            if ($duration === 1) {
                $unit = rtrim($unit, 's');
            } elseif ($unit !== '' && !str_ends_with($unit, 's')) {
                $unit .= 's';
            }
            $durationDisplay = $duration > 0 ? $duration . ' ' . ucfirst($unit) : '';

            $colTexts = [];
            $colTexts[0] = $row['borrower_name'] ?? '';
            $colTexts[1] = $row['loan_release_date'] ?? '';
            $colTexts[2] = $durationDisplay;
            $colTexts[3] = number_format((float)($row['principal'] ?? 0), 2);
            $colTexts[4] = number_format((float)($row['loan_total_amount'] ?? 0), 2);
            $colTexts[5] = number_format((float)($row['total_paid_amount'] ?? 0), 2);
            $colTexts[6] = number_format((float)($row['loan_balance'] ?? 0), 2);
            $colTexts[7] = number_format((float)($row['arrears_amount'] ?? 0), 2);
            $maturityDateObj = null;
            if ($isRolledOver && !empty($row['active_loan_release_date']) && !empty($row['active_loan_duration']) && !empty($row['active_loan_duration_unit'])) {
                $maturityDateObj = getMaturityDate($row['active_loan_release_date'], $row['active_loan_duration'], $row['active_loan_duration_unit']);
            } elseif (!empty($row['loan_release_date_raw']) && isset($row['loan_duration']) && isset($row['loan_duration_unit'])) {
                $maturityDateObj = getMaturityDate($row['loan_release_date_raw'], $row['loan_duration'], $row['loan_duration_unit']);
            }
            $colTexts[8] = $maturityDateObj ? $maturityDateObj->format('d/m/Y') : ($row['active_loan_release_date'] ?? $row['loan_release_date'] ?? '');
            $colTexts[9] = $isRolledOver ? 'Rolled Over' : 'Active';

            // compute required height for this row
            $maxLines = 1;
            foreach ($colTexts as $i => $txt) {
                $lines = $pdf->getNumLines($txt, $colWidths[$i]);
                if ($lines > $maxLines) $maxLines = $lines;
            }
            $rowHeight = $maxLines * $lineHeight;

            // output cells using MultiCell so text wraps and row height is consistent
            // check for page break before printing the row
            if ($pdf->GetY() + $rowHeight > $pageBreakTrigger) {
                $pdf->AddPage();
                $printTableHeader();
            }

            $pdf->SetX($leftMargin);
            foreach ($colTexts as $i => $txt) {
                $align = 'L';
                if (in_array($i, [3,4,5,6,7])) $align = 'R';
                if (in_array($i, [1,2,8,9])) $align = 'C';
                $pdf->MultiCell($colWidths[$i], $rowHeight, $txt, 1, $align, 0, 0, '', '', true, 0, false, true, $rowHeight, 'M');
            }
            $pdf->Ln();
        }
    } else {
        $pdf->SetX($leftMargin);
        $pdf->Cell($contentWidth, 8, 'No loans found.', 1, 1, 'C');
    }

    return $pdf->Output('', 'S');
}

// Fetch available regions/areas for loan officers
$sql_areas = "SELECT area_id, area_name FROM areas ORDER BY area_name";
$result_areas = $conn->query($sql_areas);
$areas = [];
while ($area = $result_areas->fetch_assoc()) {
    $areas[] = $area;
}

$rollover_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rollover') {
    $loan_id = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0;
    $rollover_date = $_POST['rollover_date'] ?? null;
    $rollover_duration = isset($_POST['rollover_duration']) ? (int)$_POST['rollover_duration'] : 0;
    $rollover_duration_unit = $_POST['rollover_duration_unit'] ?? 'months';

    if ($loan_id > 0) {
        $loanRowResult = $conn->query("SELECT * FROM loan_applications WHERE id = $loan_id");
        $loanRow = $loanRowResult ? $loanRowResult->fetch_assoc() : null;
        $paidRow = $conn->query("SELECT COALESCE(SUM(paid), 0) AS total_paid FROM repayments WHERE loan_id = $loan_id");
        $paidData = $paidRow ? $paidRow->fetch_assoc() : [];
        $total_paid = (float)($paidData['total_paid'] ?? 0);

        if ($loanRow) {
            $interest_amount = (float)($loanRow['total_amount'] - $loanRow['principal']);
            $remaining_balance = max(0, (float)$loanRow['total_amount'] - $total_paid);

            if ($total_paid >= $interest_amount && $remaining_balance > 0 && $rollover_date !== null && $rollover_duration > 0) {
                $newLoanDetails = calculateLoanDetails(
                    $remaining_balance,
                    (float)$loanRow['loan_interest'], 
                    $rollover_duration,
                    $rollover_duration_unit,
                    $loanRow['interest_method'], 
                    $loanRow['interest_calculation'] ?? 'monthly',
                    $loanRow['repayment_cycle'], 
                    0,
                    0
                );

                $newPrincipal = $remaining_balance;
                $borrowerId = (int)$loanRow['borrower'];
                $loanProductName = $loanRow['loan_product'];
                $newTotalAmount = $newLoanDetails['total_amount'];
                $newTotalAmountInclusive = $newLoanDetails['total_amount_inclusive'];
                $newRepayments = (int)round($newLoanDetails['number_of_repayments']);
                $rolloverInterest = (float)$loanRow['loan_interest'];
                $rolloverInterestMethod = $loanRow['interest_method'];
                $rolloverRepaymentCycle = $loanRow['repayment_cycle'];
                $processingFee = 0.0;
                $registrationFee = 0.0;

                $stmt = $conn->prepare("INSERT INTO loan_applications (borrower, loan_product, principal, loan_release_date, interest, interest_method, loan_interest, loan_duration, loan_duration_unit, repayment_cycle, number_of_repayments, processing_fee, registration_fee, loan_status, total_amount, total_amount_inclusive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?)");
                if ($stmt) {
                    $stmt->bind_param(
                        "isdsdsisdiddddd",
                        $borrowerId,
                        $loanProductName,
                        $newPrincipal,
                        $rollover_date,
                        $rolloverInterest,
                        $rolloverInterestMethod,
                        $rolloverInterest,
                        $rollover_duration,
                        $rollover_duration_unit,
                        $rolloverRepaymentCycle,
                        $newRepayments,
                        $processingFee,
                        $registrationFee,
                        $newTotalAmount,
                        $newTotalAmountInclusive
                    );
                }

                if ($stmt && $stmt->execute()) {
                    $newLoanId = $stmt->insert_id;
                    generateRepaymentSchedule($conn, $newLoanId, $newPrincipal, $newTotalAmount - $newPrincipal, $loanRow['repayment_cycle'], $newRepayments, $rollover_date);
                    $conn->query("UPDATE loan_applications SET loan_status = 'rolled_over' WHERE id = $loan_id");
                    $rollover_message = 'Rollover created successfully and new loan schedule generated.';
                } else {
                    $rollover_message = 'Failed to create rollover loan.';
                }
            } else {
                $rollover_message = 'Rollover conditions were not met or invalid rollover details provided.';
            }
        } else {
            $rollover_message = 'Loan not found for rollover.';
        }
    } else {
        $rollover_message = 'Invalid loan selected for rollover.';
    }
}

function getCycleInterval($cycle) {
    switch ($cycle) {
        case 'daily': return '1 day';
        case 'weekly': return '1 week';
        case 'monthly': return '1 month';
        case 'yearly': return '1 year';
        case 'once': return '0 days';
        default: return '1 month';
    }
}

function getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit) {
    try {
        $date = new DateTime($loan_release_date);
    } catch (Exception $e) {
        return null;
    }

    switch ($loan_duration_unit) {
        case 'days':
            $date->modify('+' . (int)$loan_duration . ' days');
            break;
        case 'weeks':
            $date->modify('+' . (int)$loan_duration . ' weeks');
            break;
        case 'months':
            $date->modify('+' . (int)$loan_duration . ' months');
            break;
        case 'years':
            $date->modify('+' . (int)$loan_duration . ' years');
            break;
        default:
            $date->modify('+' . (int)$loan_duration . ' months');
            break;
    }

    return $date;
}

function calculateLoanDetails($principal, $loan_interest_percentage, $loan_duration, $loan_duration_unit, $interest_method, $interest_calculation, $repayment_cycle, $processing_fee, $registration_fee) {
    $duration_in_weeks = 0;
    switch ($loan_duration_unit) {
        case 'days': $duration_in_weeks = $loan_duration / 7; break;
        case 'weeks': $duration_in_weeks = $loan_duration; break;
        case 'months': $duration_in_weeks = $loan_duration * 4; break;
        case 'years': $duration_in_weeks = $loan_duration * 52; break;
    }

    $number_of_repayments = 0;
    switch ($repayment_cycle) {
        case 'daily': $number_of_repayments = $duration_in_weeks * 7; break;
        case 'weekly': $number_of_repayments = $duration_in_weeks; break;
        case 'monthly': $number_of_repayments = $duration_in_weeks / 4; break;
        case 'yearly': $number_of_repayments = $duration_in_weeks / 52; break;
        case 'once': $number_of_repayments = 1; break;
    }

    $total_interest = 0;
    switch ($interest_method) {
        case 'flat_rate':
            $total_interest = ($principal * $loan_interest_percentage * $loan_duration) / 100;
            break;
        case 'percentage':
            $interest_per_period = $principal * ($loan_interest_percentage / 100);
            switch ($interest_calculation) {
                case 'weekly': $total_interest = $interest_per_period * $duration_in_weeks; break;
                case 'monthly': $total_interest = $interest_per_period * ($duration_in_weeks / 4); break;
                case 'yearly': $total_interest = $interest_per_period * ($duration_in_weeks / 52); break;
                default: $total_interest = $interest_per_period * ($duration_in_weeks / 4); break;
            }
            break;
        case 'fixed_amount':
            $total_interest = $loan_interest_percentage * $number_of_repayments;
            break;
    }

    $total_amount = $principal + $total_interest;
    $total_amount_inclusive = $total_amount + $processing_fee + $registration_fee;
    $repayment_amount = $number_of_repayments > 0 ? $total_amount / $number_of_repayments : 0;

    return [
        'number_of_repayments' => round($number_of_repayments, 2),
        'total_amount' => round($total_amount, 2),
        'total_amount_inclusive' => round($total_amount_inclusive, 2),
        'repayment_amount' => round($repayment_amount, 2),
        'total_interest' => round($total_interest, 2)
    ];
}

function generateRepaymentSchedule($conn, $loan_id, $principal_amount, $interest_amount, $repayment_cycle, $number_of_repayments, $loan_release_date) {
    $stmt = $conn->prepare("DELETE FROM repayments WHERE loan_id = ?");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();

    $start_date = new DateTime($loan_release_date);
    if ($repayment_cycle === 'once') {
        $maturity_date = new DateTime($loan_release_date);
        $maturity_date->modify('+1 day');
        $repayment_amount = $number_of_repayments > 0 ? ($principal_amount + $interest_amount) / $number_of_repayments : 0;
        $repayment_date = $maturity_date->format('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $loan_id, $repayment_date, $repayment_amount);
        $stmt->execute();
        return;
    }

    for ($i = 1; $i <= $number_of_repayments; $i++) {
        $start_date->modify('+' . getCycleInterval($repayment_cycle));
        $repayment_amount = $number_of_repayments > 0 ? ($principal_amount + $interest_amount) / $number_of_repayments : 0;
        $repayment_date = $start_date->format('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $loan_id, $repayment_date, $repayment_amount);
        $stmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performing Book</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }

        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header .logo h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }

        .sidebar {
            background-color: #ffffff;
            color: #3a3939;
            padding: 20px;
            width: 250px;
            position: fixed;
            height: 100%;
            overflow: auto;
        }

        .main {
            margin-left: 270px;
            padding: 20px;
        }
        .rolled-over-balance {
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 700;
            display: inline-block;
        }
        .nav-tabs .nav-link {
            border-radius: 0.5rem 0.5rem 0 0;
            margin-right: 0.25rem;
            font-weight: 600;
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            background-color: #dc3545;
            color: #ffffff;
            border-color: #dc3545 #dc3545 #fff;
            box-shadow: 0 0.2rem 0.4rem rgba(220, 53, 69, 0.2);
        }
        .nav-tabs .nav-link:hover {
            border-color: #dee2e6 #dee2e6 #dee2e6;
            color: #dc3545;
        }
        .filter-summary {
            font-size: 0.95rem;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .container {
            margin-top: 30px;
        }
        .table-container {
            overflow-x: auto;
        }
        .table thead th {
            background-color: #007bff;
            color: #ffffff;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
        }
        .btn-primary:hover {
            background-color: #008fb3;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>

    <div class="sidebar">
        <?php
        include '../includes/sidebar.php'; ?>
    </div>

    <?php
    // Get selected region/area, loan officer and day from the request
    $selected_area = isset($_GET['area_id']) ? trim($_GET['area_id']) : (isset($_POST['area_id']) ? trim($_POST['area_id']) : 'all');
    $selected_area = ($selected_area !== 'all' && !ctype_digit($selected_area)) ? 'all' : $selected_area;
    $selected_officer = isset($_GET['officer_id']) ? trim($_GET['officer_id']) : (isset($_POST['officer_id']) ? trim($_POST['officer_id']) : 'all');
    $selected_day = isset($_GET['day']) ? trim($_GET['day']) : (isset($_POST['day']) ? trim($_POST['day']) : 'all');

    // Fetch all loan officers, optionally filtered by selected region/area
    $sql_officers = "SELECT id, name AS full_name, email, area FROM users WHERE role_id = '2'";
    if ($selected_area !== 'all') {
        $sql_officers .= " AND area = ?";
    }
    $stmt_officers = $conn->prepare($sql_officers);
    if ($selected_area !== 'all') {
        $stmt_officers->bind_param('i', $selected_area);
    }
    $stmt_officers->execute();
    $result_officers = $stmt_officers->get_result();

    $officer_lookup = [];
    while ($officer = $result_officers->fetch_assoc()) {
        $officer_lookup[$officer['id']] = $officer;
    }
    $result_officers->data_seek(0);
    $has_selected_officer = $selected_officer !== '' && $selected_officer !== 'all' && ctype_digit((string) $selected_officer) && isset($officer_lookup[$selected_officer]);
    $selected_officer_email = $has_selected_officer ? $officer_lookup[$selected_officer]['email'] : '';

    if (!$has_selected_officer) {
        $selected_officer = 'all';
    }

    // Build the loan list using the borrower’s assigned loan officer, selected area, and day.
    function getLoans($conn, $selected_day, $selected_officer_email, $selected_area) {
        $day_filter = ($selected_day !== 'all') ? "AND DAYNAME(l.loan_release_date) = ?" : "";
        $officer_filter = ($selected_officer_email !== '') ? "AND b.loan_officer = ?" : "";
        $area_filter = ($selected_area !== 'all') ? "AND u.area = ?" : "";

        $loans = array();
        $sql = "SELECT 
                    l.id,
                    l.loan_product AS loan_product_raw,
                    DATE_FORMAT(l.loan_release_date, '%d/%m/%Y') AS loan_release_date, 
                    l.loan_release_date AS loan_release_date_raw,
                    l.principal,
                    l.total_amount AS loan_total_amount,
                    l.loan_interest,
                    l.repayment_cycle,
                    l.loan_status,
                    l.loan_duration,
                    l.loan_duration_unit,
                    b.full_name AS borrower_name, 
                    b.mobile AS phone_number,
                    b.loan_officer AS borrower_loan_officer,
                    p.name AS loan_product_name, 
                    COALESCE(SUM(r.paid), 0) AS total_paid_amount, 
                    CASE 
                        WHEN LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%' THEN 0 
                        ELSE COALESCE(SUM(CASE WHEN r.repayment_date < CURDATE() THEN GREATEST(COALESCE(r.amount, 0) - COALESCE(r.paid, 0), 0) ELSE 0 END), 0) 
                    END AS arrears_amount,
                    CASE 
                        WHEN LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%' THEN 0 
                        ELSE l.total_amount - COALESCE(SUM(r.paid), 0) 
                    END AS loan_balance,
                    COALESCE((
                        SELECT DATE_FORMAT(la2.loan_release_date, '%d/%m/%Y')
                        FROM loan_applications la2
                        WHERE la2.borrower = l.borrower AND la2.loan_status = 'approved'
                        ORDER BY la2.loan_release_date DESC
                        LIMIT 1
                    ), DATE_FORMAT(l.loan_release_date, '%d/%m/%Y')) AS active_loan_release_date,
                    COALESCE((
                        SELECT la3.loan_duration
                        FROM loan_applications la3
                        WHERE la3.borrower = l.borrower AND la3.loan_status = 'approved'
                        ORDER BY la3.loan_release_date DESC
                        LIMIT 1
                    ), l.loan_duration) AS active_loan_duration,
                    COALESCE((
                        SELECT la4.loan_duration_unit
                        FROM loan_applications la4
                        WHERE la4.borrower = l.borrower AND la4.loan_status = 'approved'
                        ORDER BY la4.loan_release_date DESC
                        LIMIT 1
                    ), l.loan_duration_unit) AS active_loan_duration_unit 
                FROM loan_applications l 
                INNER JOIN borrowers b ON l.borrower = b.id 
                INNER JOIN loan_products p ON l.loan_product = p.id 
                LEFT JOIN users u ON b.loan_officer = u.email 
                LEFT JOIN repayments r ON l.id = r.loan_id 
                WHERE (l.loan_status IN ('approved', 'rolled_over') OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%') 
                $day_filter
                $officer_filter
                $area_filter
                GROUP BY l.id, b.full_name, b.loan_officer, p.name, l.loan_status, l.loan_duration, l.loan_duration_unit, l.loan_release_date, l.loan_product, l.total_amount, l.loan_interest, l.repayment_cycle
                HAVING (LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%') OR (l.total_amount - COALESCE(SUM(r.paid), 0) > 0)";

        $sql .= " ORDER BY CASE WHEN LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%' THEN 0 ELSE 1 END, l.id DESC";

        $stmt = $conn->prepare($sql);
        $types = '';
        $params = [];

        if ($selected_day !== 'all') {
            $types .= 's';
            $params[] = $selected_day;
        }
        if ($selected_officer_email !== '') {
            $types .= 's';
            $params[] = $selected_officer_email;
        }
        if ($selected_area !== 'all') {
            $types .= 'i';
            $params[] = (int) $selected_area;
        }

        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === FALSE) {
            echo "Error: " . $conn->error;
            return $loans;
        }

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $loans[] = $row;
            }
        }

        return $loans;
    }

    $loans = getLoans($conn, $selected_day, $selected_officer_email, $selected_area);

    $email_message = '';
    $email_status = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
        $officer_display_name = ($selected_officer !== 'all' && isset($officer_lookup[$selected_officer]))
            ? $officer_lookup[$selected_officer]['full_name']
            : 'All Loan Officers';
        $day_label = ($selected_day !== 'all') ? htmlspecialchars($selected_day) : 'All days';

        // determine selected region label
        $area_label = 'All Regions';

        if ($selected_officer !== 'all') {
            if (!empty($selected_officer_email)) {
                $recipient_email = $selected_officer_email;
            } else {
                $recipient_email = '';
                $email_status = 'danger';
                $email_message = 'The selected loan officer does not have a valid email configured. Please update the loan officer email before sending.';
            }
        } else {
            $recipient_email = getConfiguredSenderEmail();
        }

        if (!empty($recipient_email)) {
            if ($selected_area !== 'all') {
                foreach ($areas as $a) {
                    if (isset($a['area_id']) && (string)$a['area_id'] === (string)$selected_area) {
                        $area_label = $a['area_name'];
                        break;
                    }
                }
            }

            $subject = 'Performing Book Report - ' . htmlspecialchars($officer_display_name) . ' - ' . htmlspecialchars($area_label);
            $body = '<p>Dear ' . htmlspecialchars($officer_display_name) . ',</p>';
            $body .= '<p>Please find the attached performing book report for <strong>' . htmlspecialchars($officer_display_name) . '</strong>';
            $body .= ' (Region: <strong>' . htmlspecialchars($area_label) . '</strong>; Day: <strong>' . htmlspecialchars($day_label) . '</strong>).</p>';
            $body .= '<p>This report was generated automatically by Inua Premium Services.</p>';

            try {
                $pdf_content = generate_performing_book_pdf($loans, $officer_display_name, $day_label, $area_label);
                $filename = 'performing_book_report_' . date('Ymd_His') . '.pdf';
                send_pdf_email($recipient_email, $subject, $body, $pdf_content, $filename);
                $email_status = 'success';
                $email_message = 'Performing book PDF sent successfully to ' . $recipient_email . '.';
            } catch (Exception $e) {
                error_log('Performing book email send failed: ' . $e->getMessage());
                $email_status = 'danger';
                $email_message = 'Unable to send the performing book PDF email. Please review the SMTP configuration.';
            }
        }
    }
    ?>
    <main class="main">
        <section class="section">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    <div class="header-actions">
                        <form method="post" class="d-inline-block">
                            <input type="hidden" name="send_email" value="1">
                            <input type="hidden" name="area_id" value="<?= htmlspecialchars($selected_area); ?>">
                            <input type="hidden" name="officer_id" value="<?= htmlspecialchars($selected_officer); ?>">
                            <input type="hidden" name="day" value="<?= htmlspecialchars($selected_day); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-envelope"></i> Send Email
                            </button>
                        </form>
                        <button id="downloadDueLoansPdf" class="btn btn-success">
                            <i class="bi bi-download"></i> Download PDF
                        </button>
                        <input type="text" id="searchInput" placeholder="Search by Borrower or Phone..." class="form-control" style="width: 300px;">
                    </div>
                </div>
                <?php if (!empty($email_message)): ?>
                    <div class="alert alert-<?= htmlspecialchars($email_status); ?> text-center" role="alert">
                        <?= htmlspecialchars($email_message); ?>
                    </div>
                <?php endif; ?>
                <h2 class="text-center">Performing Book</h2>
                <?php if (!empty($rollover_message)): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($rollover_message); ?></div>
                <?php endif; ?>
                <div class="filter-summary">
                    <span class="badge bg-danger-subtle text-danger-emphasis me-2">Officer:</span>
                    <strong><?= $selected_officer === 'all' ? 'All Loan Officers' : htmlspecialchars($officer_lookup[$selected_officer]['full_name'] ?? $selected_officer) ?></strong>
                    <span class="badge bg-danger-subtle text-danger-emphasis ms-3 me-2">Day:</span>
                    <strong><?= htmlspecialchars($selected_day === 'all' ? 'All Days' : $selected_day) ?></strong>
                </div>

                <ul class="nav nav-tabs justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_area === 'all') ? 'active' : '' ?>" href="?area_id=all&officer_id=all&day=<?= htmlspecialchars($selected_day); ?>">All Regions</a>
                    </li>
                    <?php foreach ($areas as $area): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($selected_area == $area['area_id']) ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($area['area_id']); ?>&officer_id=all&day=<?= htmlspecialchars($selected_day); ?>">
                                <?= htmlspecialchars($area['area_name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <ul class="nav nav-tabs justify-content-center mt-3">
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_officer === 'all') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=all&day=<?= htmlspecialchars($selected_day); ?>">All Loan Officers</a>
                    </li>
                    <?php while ($officer = $result_officers->fetch_assoc()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($selected_officer == $officer['id']) ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($officer['id']); ?>&day=<?= htmlspecialchars($selected_day); ?>">
                                <?= htmlspecialchars($officer['full_name']); ?>
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>

                <ul class="nav nav-tabs justify-content-center mt-3">
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'all') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=all">All Days</a>
                    </li>
                    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($selected_day === $day) ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=<?= $day; ?>"><?= $day; ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="table-container mt-4">
                    <table id="performingBookTable" class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Borrower</th>
                                <th>Loan Release Date</th>
                                <th>Loan Duration</th>
                                <th>Principal</th>
                                <th>Total Amount</th>
                                <th>Total Paid Amount</th>
                                <th>Loan Balance</th>
                                <th>Arrears</th>
                                <th>Maturity Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $modalHtml = '';
                        if (count($loans) > 0) {
                            foreach ($loans as $loan) {
                                $balance = (float) ($loan['loan_balance'] ?? 0);
                                $loanId = $loan['id'];
                                $loanStatusValue = strtolower(trim((string) ($loan['loan_status'] ?? '')));
                                $isRolledOver = stripos($loanStatusValue, 'roll') !== false;
                                $statusDisplay = $isRolledOver ? 'Rolled Over' : 'Active';
                                $balanceDisplay = $isRolledOver
                                    ? '<span class="rolled-over-balance">0</span>'
                                    : number_format(ceil($balance));
                                $rowClass = $isRolledOver ? "class='table-warning'" : '';
                                $maturity_date = '';
                                if ($isRolledOver && !empty($loan['active_loan_release_date']) && !empty($loan['active_loan_duration']) && !empty($loan['active_loan_duration_unit'])) {
                                    $m = getMaturityDate($loan['active_loan_release_date'], $loan['active_loan_duration'], $loan['active_loan_duration_unit']);
                                    $maturity_date = $m ? $m->format('d/m/Y') : htmlspecialchars($loan['active_loan_release_date'] ?? $loan['loan_release_date']);
                                } elseif (!empty($loan['loan_release_date_raw']) && isset($loan['loan_duration']) && isset($loan['loan_duration_unit'])) {
                                    $m = getMaturityDate($loan['loan_release_date_raw'], $loan['loan_duration'], $loan['loan_duration_unit']);
                                    $maturity_date = $m ? $m->format('d/m/Y') : '';
                                }
                                $durationDisplay = '';
                                if ($isRolledOver && isset($loan['active_loan_duration']) && isset($loan['active_loan_duration_unit']) && $loan['active_loan_duration'] !== '') {
                                    $duration = (int)$loan['active_loan_duration'];
                                    $unit = strtolower($loan['active_loan_duration_unit']);
                                } elseif (isset($loan['loan_duration']) && isset($loan['loan_duration_unit']) && $loan['loan_duration'] !== '') {
                                    $duration = (int)$loan['loan_duration'];
                                    $unit = strtolower($loan['loan_duration_unit']);
                                } else {
                                    $duration = 0;
                                    $unit = '';
                                }

                                if ($duration === 1) {
                                    $unit = rtrim($unit, 's');
                                } else {
                                    if ($unit !== '' && !str_ends_with($unit, 's')) {
                                        $unit .= 's';
                                    }
                                }

                                if ($duration > 0) {
                                    $durationDisplay = htmlspecialchars($duration . ' ' . ucfirst($unit));
                                }

                                $arrearsAmount = isset($loan['arrears_amount']) ? (float)$loan['arrears_amount'] : 0;
                                echo "<tr $rowClass>
                                    <td><a href='repayment_details.php?loanId=" . htmlspecialchars($loanId) . "'>" . htmlspecialchars($loan['borrower_name']) . "</a></td>
                                    <td>" . htmlspecialchars($loan['loan_release_date']) . "</td>
                                    <td>" . $durationDisplay . "</td>
                                    <td>" . number_format(ceil($loan['principal'])) . "</td>
                                    <td>" . number_format(ceil($loan['loan_total_amount'])) . "</td>
                                    <td><a href='repayment_details.php?loanId=" . htmlspecialchars($loanId) . "'>" . number_format(ceil($loan['total_paid_amount'])) . "</a></td>
                                    <td>" . $balanceDisplay . "</td>
                                    <td>" . number_format(ceil($arrearsAmount)) . "</td>
                                    <td>" . htmlspecialchars($maturity_date) . "</td>
                                    <td>" . htmlspecialchars($statusDisplay) . "</td>
                                    <td>
                                        <button type='button' class='btn btn-info btn-sm' data-bs-toggle='modal' data-bs-target='#rolloverLoanModal-" . htmlspecialchars($loanId) . "'>Rollover</button>
                                    </td>
                                </tr>";

                                $modalHtml .= "<div class='modal fade' id='rolloverLoanModal-" . htmlspecialchars($loanId) . "' tabindex='-1' aria-hidden='true'>
                                    <div class='modal-dialog modal-lg'>
                                        <div class='modal-content'>
                                            <form method='POST' class='rollover-loan-form'>
                                                <div class='modal-header'>
                                                    <h5 class='modal-title'>Rollover Loan</h5>
                                                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                                </div>
                                                <div class='modal-body'>
                                                    <input type='hidden' name='action' value='rollover'>
                                                    <input type='hidden' name='loan_id' value='" . htmlspecialchars($loanId) . "'>
                                                    <input type='hidden' name='loan_product' value='" . htmlspecialchars($loan['loan_product_raw']) . "'>
                                                    <div class='row g-3'>
                                                        <div class='col-md-6'>
                                                            <label class='form-label'>Borrower</label>
                                                            <input type='text' class='form-control' value='" . htmlspecialchars($loan['borrower_name']) . "' readonly>
                                                        </div>
                                                        <div class='col-md-6'>
                                                            <label class='form-label'>Loan Product</label>
                                                            <input type='text' class='form-control' value='" . htmlspecialchars($loan['loan_product_name']) . "' readonly>
                                                        </div>
                                                        <div class='col-md-6'>
                                                            <label class='form-label'>Principal to Rollover</label>
                                                            <input type='number' step='0.01' class='form-control' name='principal' value='" . number_format(max(0, ($loan['loan_total_amount'] ?? 0) - $loan['total_paid_amount']), 2, '.', '') . "' readonly>
                                                        </div>
                                                        <div class='col-md-6'>
                                                            <label class='form-label'>Interest Rate %</label>
                                                            <input type='number' step='0.01' class='form-control' value='" . htmlspecialchars($loan['loan_interest'] ?? '') . "' readonly>
                                                        </div>
                                                        <div class='col-md-6'>
                                                            <label class='form-label'>Rollover Start Date</label>
                                                            <input type='date' class='form-control' name='rollover_date' required>
                                                        </div>
                                                        <div class='col-md-6'>
                                                            <label class='form-label'>Rollover Duration</label>
                                                            <input type='number' min='1' class='form-control' name='rollover_duration' required>
                                                        </div>
                                                        <div class='col-md-6'>
                                                            <label class='form-label'>Duration Unit</label>
                                                            <select class='form-select' name='rollover_duration_unit' required>
                                                                <option value='months' selected>Months</option>
                                                                <option value='weeks'>Weeks</option>
                                                                <option value='years'>Years</option>
                                                                <option value='days'>Days</option>
                                                            </select>
                                                        </div>
                                                        <div class='col-md-6'>
                                                            <label class='form-label'>Repayment Cycle</label>
                                                            <input type='text' class='form-control' value='" . htmlspecialchars($loan['repayment_cycle'] ?? '') . "' readonly>
                                                        </div>
                                                    </div>
                                                    <p class='small mt-3 text-muted'>This rollover uses the remaining balance as the new principal amount. A new loan will be created and the current loan will be marked as rolled over.</p>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                                    <button type='submit' class='btn btn-primary'>Confirm Rollover</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>";
                            }
                        } else {
                            echo "<tr><td colspan='11'>No loans found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <?php echo $modalHtml; ?>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const rows = document.querySelectorAll('#performingBookTable tbody tr');

            function normalizeSearchText(text) {
                return (text || '').toString().trim().toLowerCase().replace(/[^a-z0-9]/g, '');
            }

            if (!searchInput || rows.length === 0) {
                return;
            }

            searchInput.addEventListener('input', function () {
                const filter = normalizeSearchText(this.value);

                rows.forEach(row => {
                    const cells = Array.from(row.cells).map(cell => normalizeSearchText(cell.textContent));
                    const match = cells.some(text => text.includes(filter));
                    row.style.display = match ? '' : 'none';
                });
            });
        });

        // PDF Download functionality
        function getPerformingBookPdfData(includeLogo, callback) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            const logoPath = '../assets/img/logo.png';
            const img = new Image();

            function renderPdf() {
                const pageWidth = doc.internal.pageSize.getWidth();
                const logoWidth = 28;
                const logoHeight = 18;
                const logoX = 14;
                const logoY = 10;

                if (includeLogo) {
                    try {
                        doc.addImage(img, 'PNG', logoX, logoY, logoWidth, logoHeight);
                    } catch (error) {
                        // ignore logo if it fails to render
                    }
                }

                doc.setFontSize(16);
                doc.setFont('helvetica', 'bold');
                doc.text('Performing Book', pageWidth / 2, 18, { align: 'center' });
                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                doc.text('Generated on ' + new Date().toLocaleDateString(), pageWidth - 14, 18, { align: 'right' });

                const table = document.getElementById('performingBookTable');
                const headers = Array.from(table.querySelectorAll('thead th'))
                    .slice(0, -1)
                    .map(th => th.textContent.trim());
                const rows = Array.from(table.querySelectorAll('tbody tr'))
                    .filter(row => row.style.display !== 'none')
                    .map(row => Array.from(row.querySelectorAll('td'))
                        .slice(0, -1)
                        .map(td => td.textContent.trim())
                    );

                doc.autoTable({
                    head: [headers],
                    body: rows,
                    startY: 28,
                    styles: { fontSize: 8, cellPadding: 2 },
                    headStyles: { fillColor: [0, 123, 255], textColor: [255, 255, 255] },
                    alternateRowStyles: { fillColor: [248, 249, 250] },
                    margin: { left: 14, right: 14 }
                });

                callback(doc);
            }

            if (includeLogo) {
                img.onload = renderPdf;
                img.onerror = function () { renderPdf(); };
                img.src = logoPath;
            } else {
                renderPdf();
            }
        }

        const downloadButton = document.getElementById('downloadDueLoansPdf');

        if (downloadButton) {
            downloadButton.addEventListener('click', function () {
                getPerformingBookPdfData(true, function (doc) {
                    doc.save('performing_book_report.pdf');
                });
            });
        }
    </script>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>