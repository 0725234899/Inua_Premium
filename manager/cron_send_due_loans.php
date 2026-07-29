<?php
// CLI script: cron_send_due_loans.php
// Run with: C:\xampp\php\php.exe cron_send_due_loans.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure this runs only from CLI
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

// Copy of the generate_due_loans_pdf from manager/due_loans.php (landscape, full columns)
function calculate_due_loans_totals($rows) {
    $totals = [
        'total_loan_book' => 0.0,
        'total_performing_book' => 0.0,
        'total_active_customers' => 0,
    ];

    $unique_customers = [];
    foreach ($rows as $row) {
        $loan_balance = (float) $row['loan_balance'];
        $totals['total_loan_book'] += $loan_balance;

        if (empty($row['is_past_due'])) {
            $totals['total_performing_book'] += $loan_balance;
        }

        $customer_key = trim($row['borrower_name']) . '|' . trim($row['phone_number']);
        $unique_customers[$customer_key] = true;
    }

    $totals['total_active_customers'] = count($unique_customers);
    return $totals;
}

function generate_due_loans_pdf($rows, $officer_display_name, $day_label) {
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor('Inua Premium Services');
    $pdf->SetTitle('Due Loans Report');
    $leftMargin = 12;
    $rightMargin = 12;
    $topMargin = 15;
    $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $pageWidth = $pdf->getPageWidth();
    $contentWidth = $pageWidth - ($leftMargin + $rightMargin);

    $pdf->SetTextColor(56, 152, 219);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 8, 'Inua Premium Services', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Due Loans Report', 0, 1, 'C');

    // top divider aligned to content margins
    $pdf->SetDrawColor(56, 152, 219);
    $pdf->SetLineWidth(0.35);
    $yLine = $pdf->GetY() + 2;
    $pdf->Line($leftMargin, $yLine, $leftMargin + $contentWidth, $yLine);
    $pdf->Ln(4);

    $totals = calculate_due_loans_totals($rows);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(0, 6, 'Loan Officer: ' . $officer_display_name, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Day Filter: ' . $day_label, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total Loan Book: KSH ' . number_format($totals['total_loan_book'], 2), 0, 1, 'L');
    $pdf->Cell(0, 6, 'Performing Book: KSH ' . number_format($totals['total_performing_book'], 2), 0, 1, 'L');
    $pdf->Cell(0, 6, 'Active Customers: ' . $totals['total_active_customers'], 0, 1, 'L');
    $pdf->Ln(2);

    // Define column widths proportional to content width
    $colWidths = [
        intval($contentWidth * 0.20), // Borrower
        intval($contentWidth * 0.13), // Phone
        intval($contentWidth * 0.08), // Loan ID
        intval($contentWidth * 0.13), // Total Loan Amount
        intval($contentWidth * 0.13), // Total Paid
        intval($contentWidth * 0.13), // Loan Balance
        intval($contentWidth * 0.10), // Due Amount
        $contentWidth - (intval($contentWidth * 0.20) + intval($contentWidth * 0.13) + intval($contentWidth * 0.08) + intval($contentWidth * 0.13) + intval($contentWidth * 0.13) + intval($contentWidth * 0.13) + intval($contentWidth * 0.10))
    ];

    // Header row
    $pdf->SetFillColor(56, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX($leftMargin);
    $headers = ['Borrower', 'Phone Number', 'Loan ID', 'Total Loan Amount (KSH)', 'Total Paid (KSH)', 'Loan Balance (KSH)', 'Due Amount (KSH)', 'Due Date'];
    foreach ($headers as $i => $h) {
        $pdf->Cell($colWidths[$i], 8, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Data rows
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetFont('helvetica', '', 9);
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $pdf->SetX($leftMargin);
            $pdf->Cell($colWidths[0], 7, $row['borrower_name'], 1, 0, 'L');
            $pdf->Cell($colWidths[1], 7, $row['phone_number'], 1, 0, 'L');
            $pdf->Cell($colWidths[2], 7, $row['loan_id'], 1, 0, 'C');
            $pdf->Cell($colWidths[3], 7, number_format($row['total_disbursed'], 2), 1, 0, 'R');
            $pdf->Cell($colWidths[4], 7, number_format($row['total_paid'], 2), 1, 0, 'R');
            $pdf->Cell($colWidths[5], 7, number_format($row['loan_balance'], 2), 1, 0, 'R');
            if (!empty($row['is_past_due'])) {
                $pdf->SetFillColor(247, 0, 234);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell($colWidths[6], 7, number_format($row['due_amount'], 2), 1, 0, 'R', true);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetTextColor(33, 37, 41);
            } else {
                $pdf->Cell($colWidths[6], 7, number_format($row['due_amount'], 2), 1, 0, 'R');
            }
            $pdf->Cell($colWidths[7], 7, $row['due_date'], 1, 1, 'C');
        }
    } else {
        $pdf->SetX($leftMargin);
        $pdf->Cell($contentWidth, 8, 'No due loans found.', 1, 1, 'C');
    }

    $pdf->Ln(4);
    $pdf->SetTextColor(56, 152, 219);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 6, 'Powered by AntonTech', 0, 1, 'C');

    return $pdf->Output('', 'S');
}

// Basic send_pdf_email (same as in other manager files)
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
}

// Fetch all loan officers with emails
$sql_officers = "SELECT id, name AS full_name, email FROM users WHERE role_id = '2'";
$stmt_officers = $conn->prepare($sql_officers);
$stmt_officers->execute();
$result_officers = $stmt_officers->get_result();

$officers = [];
while ($officer = $result_officers->fetch_assoc()) {
    // only include officers with an email address
    if (!empty($officer['email'])) {
        $officers[] = $officer;
    }
}

if (empty($officers)) {
    echo "No loan officers with email found. Exiting.\n";
    exit(0);
}

$selected_day = date('l');
$day_label = $selected_day;

foreach ($officers as $officer) {
    $officer_id = $officer['id'];
    $officer_email = $officer['email'];
    $officer_name = $officer['full_name'];

    // Prepare and execute the same due-loans query but filtered by officer and current weekday
    $sql_due_loans = "SELECT 
                    borrowers.full_name AS borrower_name, 
                    borrowers.mobile AS phone_number, 
                    loan_applications.id AS loan_id, 
                    loan_applications.total_amount AS total_disbursed, 
                    (SELECT SUM(r2.paid) FROM repayments r2 WHERE r2.loan_id = loan_applications.id) AS total_paid, 
                    (loan_applications.total_amount - (SELECT SUM(r2.paid) FROM repayments r2 WHERE r2.loan_id = loan_applications.id)) AS loan_balance, 
                    repayments.amount AS amount_due, 
                    repayments.paid AS paid_amount, 
                    repayments.repayment_date AS repayment_date_raw 
                  FROM 
                    repayments
                  INNER JOIN 
                    loan_applications ON repayments.loan_id = loan_applications.id
                  INNER JOIN 
                    borrowers ON loan_applications.borrower = borrowers.id
                  WHERE 
                    repayments.repayment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    AND DAYNAME(repayments.repayment_date) = ?
                    AND borrowers.loan_officer = (SELECT email FROM users WHERE id = ?)
                  ORDER BY repayments.repayment_date ASC, loan_applications.id ASC, repayments.id ASC";

    $stmt_due_loans = $conn->prepare($sql_due_loans);
    $stmt_due_loans->bind_param('si', $selected_day, $officer_id);
    $stmt_due_loans->execute();
    $result_due_loans = $stmt_due_loans->get_result();

    $processed_due_loans = [];
    $remaining_paid_by_loan = [];
    $today = date('Y-m-d');
    $cutoff_date = date('Y-m-d', strtotime('+7 days'));
    $loan_groups = [];
    $individual_due_loans = [];

    while ($row = $result_due_loans->fetch_assoc()) {
        $loan_id = (int) $row['loan_id'];
        $amount_due = (float) $row['amount_due'];

        if (!isset($remaining_paid_by_loan[$loan_id])) {
            $remaining_paid_by_loan[$loan_id] = (float) $row['total_paid'];
        }

        $paid_for_this_due = min($amount_due, max(0, $remaining_paid_by_loan[$loan_id]));
        $remaining_paid_by_loan[$loan_id] -= $paid_for_this_due;
        $outstanding_due = max(0, $amount_due - $paid_for_this_due);
        $due_date = '';

        if (!empty($row['repayment_date_raw'])) {
            $due_date = date('d/m/Y', strtotime($row['repayment_date_raw']));
        }

        $repayment_date = '';
        if (!empty($row['repayment_date_raw'])) {
            $repayment_date = date('Y-m-d', strtotime($row['repayment_date_raw']));
        }

        if ($outstanding_due > 0 && $repayment_date <= $cutoff_date) {
            $is_past_due = ($repayment_date < $today);

            if ($is_past_due) {
                if (!isset($loan_groups[$loan_id])) {
                    $loan_groups[$loan_id] = [
                        'borrower_name' => $row['borrower_name'],
                        'phone_number' => $row['phone_number'],
                        'loan_id' => $loan_id,
                        'total_disbursed' => (float) $row['total_disbursed'],
                        'total_paid' => (float) $row['total_paid'],
                        'loan_balance' => (float) $row['loan_balance'],
                        'due_amount' => 0.0,
                        'due_date' => $due_date,
                        'due_date_raw' => $repayment_date,
                        'is_past_due' => true,
                    ];
                }

                $loan_groups[$loan_id]['due_amount'] += $outstanding_due;

                if ($loan_groups[$loan_id]['due_date_raw'] === '' || $repayment_date < $loan_groups[$loan_id]['due_date_raw']) {
                    $loan_groups[$loan_id]['due_date_raw'] = $repayment_date;
                    $loan_groups[$loan_id]['due_date'] = $due_date;
                }
            } else {
                $individual_due_loans[] = [
                    'borrower_name' => $row['borrower_name'],
                    'phone_number' => $row['phone_number'],
                    'loan_id' => $loan_id,
                    'total_disbursed' => (float) $row['total_disbursed'],
                    'total_paid' => (float) $row['total_paid'],
                    'loan_balance' => (float) $row['loan_balance'],
                    'due_amount' => $outstanding_due,
                    'due_date' => $due_date,
                    'due_date_raw' => $repayment_date,
                    'is_past_due' => false,
                ];
            }
        }
    }

    $processed_due_loans = array_merge(array_values($loan_groups), $individual_due_loans);
    usort($processed_due_loans, function ($a, $b) {
        return strcmp($a['due_date_raw'] ?? '', $b['due_date_raw'] ?? '');
    });

    // Always send a report — generate empty report when there are no due loans
    $has_due = !empty($processed_due_loans);

    $subject = 'Due Loans Report - ' . $officer_name;
    if ($has_due) {
        $body = '<p>Dear ' . htmlspecialchars($officer_name) . ',</p><p>Please find the attached due loans report for <strong>' . htmlspecialchars($officer_name) . '</strong> covering <strong>' . $day_label . '</strong>.</p><p>This report was generated automatically by Inua Premium Services.</p>';
    } else {
        $body = '<p>Dear ' . htmlspecialchars($officer_name) . ',</p><p>There are currently <strong>no due loans</strong> for your portfolio within the next 7 days. Attached is an automated summary report.</p><p>This report was generated automatically by Inua Premium Services.</p>';
    }

    try {
        $pdf_content = generate_due_loans_pdf($processed_due_loans, $officer_name, $day_label);
        $filename = 'due_loans_report_' . preg_replace('/\W+/', '_', strtolower($officer_name)) . '_' . date('Ymd_His') . '.pdf';
        send_pdf_email($officer_email, $subject, $body, $pdf_content, $filename);
        echo "Sent due loans report to {$officer_name} <{$officer_email}>\n";
    } catch (Exception $e) {
        echo "Failed to send to {$officer_name} <{$officer_email}>: " . $e->getMessage() . "\n";
        error_log('Cron due loans send failed: ' . $e->getMessage());
    }

    // small pause to avoid overwhelming SMTP
    sleep(1);
}

echo "Done.\n";
