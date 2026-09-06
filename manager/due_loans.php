<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';
include '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

function calculate_due_loans_totals($rows) {
    $totals = [
        'total_loan_book' => 0.0,
        'total_performing_book' => 0.0,
        'total_arrears' => 0.0,
        'total_active_customers' => 0,
        'total_customers_in_arrears' => 0,
        'total_par_percentage' => 0.0,
        'customer_arrears_percentage' => 0.0,
    ];

    $unique_customers = [];
    $customers_in_arrears = [];
    foreach ($rows as $row) {
        $loan_balance = (float) $row['loan_balance'];
        $totals['total_loan_book'] += $loan_balance;

        if (empty($row['is_past_due'])) {
            $totals['total_performing_book'] += $loan_balance;
        } else {
            $totals['total_arrears'] += (float) $row['due_amount'];
            $customers_in_arrears[trim($row['borrower_name']) . '|' . trim($row['phone_number'])] = true;
        }

        $customer_key = trim($row['borrower_name']) . '|' . trim($row['phone_number']);
        $unique_customers[$customer_key] = true;
    }

    $totals['total_active_customers'] = count($unique_customers);
    $totals['total_customers_in_arrears'] = count($customers_in_arrears);
    $totals['total_par_percentage'] = $totals['total_loan_book'] > 0 ? (($totals['total_arrears'] / $totals['total_loan_book']) * 100) : 0;
    $totals['customer_arrears_percentage'] = $totals['total_active_customers'] > 0 ? (($totals['total_customers_in_arrears'] / $totals['total_active_customers']) * 100) : 0;

    return $totals;
}

function generate_due_loans_pdf($rows, $officer_display_name, $day_label, $portfolio_metrics = []) {
    // Use landscape to fit all UI columns comfortably
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor('Inua Premium Services');
    $pdf->SetTitle('Due Loans Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $leftMargin = 12;
    $rightMargin = 12;
    $topMargin = 15;
    $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $pageWidth = $pdf->getPageWidth();
    $contentWidth = $pageWidth - ($leftMargin + $rightMargin);
    $logoPath = __DIR__ . '/../assets/img/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, $leftMargin, 10, 22, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    }

    $pdf->SetTextColor(15, 76, 129);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetXY($leftMargin + 28, 10);
    $pdf->Cell(0, 8, 'Inua Premium Services', 0, 1, 'L');
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetXY($leftMargin + 28, 18);
    $pdf->Cell(0, 6, 'Due Loans Report', 0, 1, 'L');

    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($leftMargin, 30);
    $pdf->Cell(0, 5, 'Loan Officer: ' . $officer_display_name, 0, 1, 'L');
    $pdf->SetXY($leftMargin, 35);
    $pdf->Cell(0, 5, 'Day Filter: ' . $day_label, 0, 1, 'L');

    $pdf->SetDrawColor(15, 76, 129);
    $pdf->SetLineWidth(0.35);
    $yLine = 42;
    $pdf->Line($leftMargin, $yLine, $leftMargin + $contentWidth, $yLine);
    $pdf->Ln(2);

    $totals = array_merge([
        'total_loan_book' => 0.0,
        'total_performing_book' => 0.0,
        'total_arrears' => 0.0,
        'total_active_customers' => 0,
        'total_customers_in_arrears' => 0,
        'total_par_percentage' => 0.0,
        'customer_arrears_percentage' => 0.0,
    ], (array) $portfolio_metrics);

    $totals['total_loan_book'] = (float) ($totals['total_loan_book'] ?? 0.0);
    $totals['total_performing_book'] = (float) ($totals['total_performing_book'] ?? 0.0);
    $totals['total_arrears'] = (float) ($totals['total_arrears'] ?? 0.0);
    $totals['total_active_customers'] = (int) ($totals['total_active_customers'] ?? 0);
    $totals['total_customers_in_arrears'] = (int) ($totals['total_customers_in_arrears'] ?? 0);
    $totals['total_par_percentage'] = (float) ($totals['total_par_percentage'] ?? 0.0);
    $totals['customer_arrears_percentage'] = (float) ($totals['customer_arrears_percentage'] ?? 0.0);

    $metricWidth = ($contentWidth - 20) / 3;
    $metricLabels = [
        ['Total Loan Book', 'KSH ' . number_format($totals['total_loan_book'], 2)],
        ['Total PAR', number_format($totals['total_par_percentage'], 2) . '%'],
        ['Total Arrears', 'KSH ' . number_format($totals['total_arrears'], 2)],
        ['Active Customers', (string) $totals['total_active_customers']],
        ['Customers in Arrears', (string) $totals['total_customers_in_arrears']],
        ['% Customers in Arrears', number_format($totals['customer_arrears_percentage'], 2) . '%'],
    ];

    $pdf->SetFillColor(239, 246, 255);
    $pdf->SetTextColor(15, 76, 129);
    $pdf->SetFont('helvetica', 'B', 8);
    $startY = 48;
    foreach ($metricLabels as $index => $metric) {
        $col = $index % 3;
        $row = intdiv($index, 3);
        $x = $leftMargin + ($col * ($metricWidth + 10));
        $y = $startY + ($row * 16);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($metricWidth, 6, $metric[0] . "\n" . $metric[1], 1, 'C', true, 0);
    }

    $pdf->SetY($startY + 34);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Performing Book: KSH ' . number_format((float) $totals['total_performing_book'], 2), 0, 1, 'L');
    $pdf->Ln(1);

    // Define column widths proportional to content width
    // Columns: Borrower, Phone Number, Loan ID, Total Loan Amount, Total Paid, Loan Balance, Due Amount, Due Date
    $colWidths = [
        intval($contentWidth * 0.20), // Borrower
        intval($contentWidth * 0.13), // Phone
        intval($contentWidth * 0.08), // Loan ID
        intval($contentWidth * 0.13), // Total Loan Amount
        intval($contentWidth * 0.13), // Total Paid
        intval($contentWidth * 0.13), // Loan Balance
        intval($contentWidth * 0.10), // Due Amount
        $contentWidth - (intval($contentWidth * 0.20) + intval($contentWidth * 0.13) + intval($contentWidth * 0.08) + intval($contentWidth * 0.13) + intval($contentWidth * 0.13) + intval($contentWidth * 0.13) + intval($contentWidth * 0.10)) // Due Date (remaining)
    ];

    // Header row
    $pdf->SetFillColor(56, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX($leftMargin);
    $headers = ['Borrower', 'Phone Number', 'Loan ID', 'Total Amount (KSH)', 'Total Paid (KSH)', 'LB(KSH)', 'Due Amount (KSH)', 'Due Date'];
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
            // highlight due amount if past due
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

// Get the selected day from the request or default to all days
$selected_day = isset($_GET['day']) ? $_GET['day'] : 'all';
$day_filter = ($selected_day !== 'all') ? "AND DAYNAME(repayments.repayment_date) = ?" : "";
$selected_area = isset($_GET['area_id']) ? $_GET['area_id'] : 'all';
$selected_area = ($selected_area !== 'all' && ctype_digit((string) $selected_area)) ? $selected_area : 'all';
$area_filter = ($selected_area !== 'all') ? "AND users.area = ?" : "";

$areasResult = $conn->query("SELECT area_id, area_name FROM areas ORDER BY area_name");
$areas = $areasResult ? $areasResult->fetch_all(MYSQLI_ASSOC) : [];

// Fetch loan officers for the selected region
$sql_officers = "SELECT id, name AS full_name, email FROM users WHERE role_id = '2'";
if ($selected_area !== 'all') {
    $sql_officers .= " AND area = ?";
}
$stmt_officers = $conn->prepare($sql_officers);
if ($selected_area !== 'all') {
    $stmt_officers->bind_param('i', $selected_area);
}
$stmt_officers->execute();
$result_officers = $stmt_officers->get_result();

$officer_name_map = [];
$officer_email_map = [];
while ($officer = $result_officers->fetch_assoc()) {
    $officer_name_map[$officer['id']] = $officer['full_name'];
    $officer_email_map[$officer['id']] = $officer['email'];
}
$result_officers->data_seek(0);

// Get selected loan officer (if any)
$selected_officer = isset($_GET['officer_id']) ? $_GET['officer_id'] : 'all';
$officer_filter = ($selected_officer !== 'all') ? "AND borrowers.loan_officer = (SELECT email FROM users WHERE id = ?)" : "";
$portfolio_metrics = [
    'total_loan_book' => 0.0,
    'total_par_percentage' => 0.0,
    'total_arrears' => 0.0,
    'total_active_customers' => 0,
    'total_customers_in_arrears' => 0,
    'customer_arrears_percentage' => 0.0,
];

$portfolio_metrics_sql = "SELECT
    COALESCE(SUM(CASE WHEN loan_outstanding > 0 THEN loan_outstanding ELSE 0 END), 0) AS total_loan_book,
    COALESCE(SUM(CASE WHEN overdue_total > 0 THEN overdue_total ELSE 0 END), 0) AS total_arrears,
    COUNT(DISTINCT CASE WHEN loan_outstanding > 0 THEN borrower_id END) AS total_active_customers,
    COUNT(DISTINCT CASE WHEN loan_outstanding > 0 AND overdue_total > 0 THEN borrower_id END) AS total_customers_in_arrears
FROM (
    SELECT
        la.borrower AS borrower_id,
        SUM(GREATEST(0, la.total_amount - COALESCE((SELECT SUM(r2.paid) FROM repayments r2 WHERE r2.loan_id = la.id), 0) - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = la.id), 0))) AS loan_outstanding,
        SUM(
            GREATEST(
                0,
                COALESCE((SELECT SUM(CASE WHEN r3.repayment_date < CURDATE() THEN COALESCE(r3.amount, 0) ELSE 0 END) FROM repayments r3 WHERE r3.loan_id = la.id), 0)
                - COALESCE((SELECT SUM(CASE WHEN r3.repayment_date < CURDATE() THEN COALESCE(r3.paid, 0) ELSE 0 END) FROM repayments r3 WHERE r3.loan_id = la.id), 0)
                - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = la.id), 0)
            )
        ) AS overdue_total
    FROM loan_applications la
    INNER JOIN borrowers b ON b.id = la.borrower
    WHERE (la.loan_status IS NULL OR la.loan_status != 'rolled_over')
      AND b.loan_officer = (SELECT email FROM users WHERE id = ?)
    GROUP BY la.borrower
) portfolio";

if ($selected_officer === 'all') {
    $portfolio_metrics_sql = "SELECT
        COALESCE(SUM(CASE WHEN loan_outstanding > 0 THEN loan_outstanding ELSE 0 END), 0) AS total_loan_book,
        COALESCE(SUM(CASE WHEN overdue_total > 0 THEN overdue_total ELSE 0 END), 0) AS total_arrears,
        COUNT(DISTINCT CASE WHEN loan_outstanding > 0 THEN borrower_id END) AS total_active_customers,
        COUNT(DISTINCT CASE WHEN loan_outstanding > 0 AND overdue_total > 0 THEN borrower_id END) AS total_customers_in_arrears
    FROM (
        SELECT
            la.borrower AS borrower_id,
            SUM(GREATEST(0, la.total_amount - COALESCE((SELECT SUM(r2.paid) FROM repayments r2 WHERE r2.loan_id = la.id), 0) - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = la.id), 0))) AS loan_outstanding,
            SUM(
                GREATEST(
                    0,
                    COALESCE((SELECT SUM(CASE WHEN r3.repayment_date < CURDATE() THEN COALESCE(r3.amount, 0) ELSE 0 END) FROM repayments r3 WHERE r3.loan_id = la.id), 0)
                    - COALESCE((SELECT SUM(CASE WHEN r3.repayment_date < CURDATE() THEN COALESCE(r3.paid, 0) ELSE 0 END) FROM repayments r3 WHERE r3.loan_id = la.id), 0)
                    - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = la.id), 0)
                )
            ) AS overdue_total
        FROM loan_applications la
        INNER JOIN borrowers b ON b.id = la.borrower
        WHERE (la.loan_status IS NULL OR la.loan_status != 'rolled_over')
        GROUP BY la.borrower
    ) portfolio";
    $portfolio_metrics_stmt = $conn->prepare($portfolio_metrics_sql);
    if ($portfolio_metrics_stmt) {
        $portfolio_metrics_stmt->execute();
        $portfolio_metrics_row = $portfolio_metrics_stmt->get_result()->fetch_assoc();
        $portfolio_metrics_stmt->close();
    }
} else {
    $portfolio_metrics_stmt = $conn->prepare($portfolio_metrics_sql);
    if ($portfolio_metrics_stmt) {
        $portfolio_metrics_stmt->bind_param('i', $selected_officer);
        $portfolio_metrics_stmt->execute();
        $portfolio_metrics_row = $portfolio_metrics_stmt->get_result()->fetch_assoc();
        $portfolio_metrics_stmt->close();
    }
}

if (!empty($portfolio_metrics_row)) {
    $portfolio_metrics['total_loan_book'] = (float) ($portfolio_metrics_row['total_loan_book'] ?? 0);
    $portfolio_metrics['total_arrears'] = (float) ($portfolio_metrics_row['total_arrears'] ?? 0);
    $portfolio_metrics['total_active_customers'] = (int) ($portfolio_metrics_row['total_active_customers'] ?? 0);
    $portfolio_metrics['total_customers_in_arrears'] = (int) ($portfolio_metrics_row['total_customers_in_arrears'] ?? 0);
    $portfolio_metrics['total_par_percentage'] = $portfolio_metrics['total_loan_book'] > 0 ? (($portfolio_metrics['total_arrears'] / $portfolio_metrics['total_loan_book']) * 100) : 0;
    $portfolio_metrics['customer_arrears_percentage'] = $portfolio_metrics['total_active_customers'] > 0 ? (($portfolio_metrics['total_customers_in_arrears'] / $portfolio_metrics['total_active_customers']) * 100) : 0;
}

$cleared_loan_filter = "AND NOT EXISTS (
                        SELECT 1
                        FROM (
                            SELECT loan_id, SUM(Amount) + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = payment_date_records.loan_id), 0) AS total_paid
                            FROM payment_date_records
                            GROUP BY loan_id
                        ) paid_totals
                        INNER JOIN (
                            SELECT loan_id, SUM(amount) AS total_due
                            FROM repayments
                            GROUP BY loan_id
                        ) due_totals ON due_totals.loan_id = paid_totals.loan_id
                        WHERE paid_totals.loan_id = loan_applications.id
                          AND paid_totals.total_paid >= due_totals.total_due
                    )";

// Query to fetch all clients with due loans
$sql_due_loans = "SELECT 
                    borrowers.full_name AS borrower_name, 
                    borrowers.mobile AS phone_number, 
                    loan_applications.id AS loan_id, 
                    loan_applications.total_amount AS total_disbursed, 
                    (SELECT SUM(r2.paid) FROM repayments r2 WHERE r2.loan_id = loan_applications.id) + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = loan_applications.id), 0) AS total_paid,
                    (loan_applications.total_amount - (SELECT SUM(r2.paid) FROM repayments r2 WHERE r2.loan_id = loan_applications.id) - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = loan_applications.id), 0)) AS loan_balance,
                    repayments.amount AS amount_due, 
                    repayments.paid AS paid_amount, 
                    repayments.repayment_date AS repayment_date_raw 
                  FROM 
                    repayments
                  INNER JOIN 
                    loan_applications ON repayments.loan_id = loan_applications.id
                  INNER JOIN 
                    borrowers ON loan_applications.borrower = borrowers.id
                                    INNER JOIN
                                        users ON borrowers.loan_officer = users.email
                  WHERE 
                    repayments.repayment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                                        AND (loan_applications.loan_status IS NULL OR loan_applications.loan_status != 'rolled_over')
                                        $cleared_loan_filter
                                        $day_filter
                                        $officer_filter
                                        $area_filter
                  ORDER BY repayments.repayment_date ASC, loan_applications.id ASC, repayments.id ASC";

$stmt_due_loans = $conn->prepare($sql_due_loans);
if ($selected_day !== 'all' && $selected_officer !== 'all' && $selected_area !== 'all') {
    $stmt_due_loans->bind_param("sii", $selected_day, $selected_officer, $selected_area);
} elseif ($selected_day !== 'all' && $selected_area !== 'all') {
    $stmt_due_loans->bind_param("ssi", $selected_day, $selected_area);
} elseif ($selected_officer !== 'all' && $selected_area !== 'all') {
    $stmt_due_loans->bind_param("ii", $selected_officer, $selected_area);
} elseif ($selected_area !== 'all') {
    $stmt_due_loans->bind_param("i", $selected_area);
} elseif ($selected_day !== 'all' && $selected_officer !== 'all') {
    $stmt_due_loans->bind_param("si", $selected_day, $selected_officer);
} elseif ($selected_day !== 'all') {
    $stmt_due_loans->bind_param("s", $selected_day);
} elseif ($selected_officer !== 'all') {
    $stmt_due_loans->bind_param("i", $selected_officer);
}
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

$email_message = '';
$email_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $sender_email = getConfiguredSenderEmail();
    $recipient_email = ($selected_officer !== 'all' && isset($officer_email_map[$selected_officer]) && !empty($officer_email_map[$selected_officer]))
        ? $officer_email_map[$selected_officer]
        : $sender_email;
    $officer_display_name = ($selected_officer !== 'all' && isset($officer_name_map[$selected_officer]))
        ? $officer_name_map[$selected_officer]
        : 'All Loan Officers';
    $day_label = ($selected_day !== 'all') ? htmlspecialchars($selected_day) : 'All days';
    $subject = 'Due Loans Report - ' . htmlspecialchars($officer_display_name);
    $greetName = ($selected_officer !== 'all' && isset($officer_name_map[$selected_officer])) ? $officer_name_map[$selected_officer] : 'Team';
    $body = '<p>Dear ' . htmlspecialchars($greetName) . ',</p><p>Please find the attached due loans report for <strong>' . htmlspecialchars($officer_display_name) . '</strong> and <strong>' . htmlspecialchars($day_label) . '</strong>.</p><p>This report was generated automatically by Inua Premium Services.</p>';

    try {
        $pdf_content = generate_due_loans_pdf($processed_due_loans, $officer_display_name, $day_label, $portfolio_metrics);
        $filename = 'due_loans_report_' . date('Ymd_His') . '.pdf';
        send_pdf_email($recipient_email, $subject, $body, $pdf_content, $filename);
        $email_status = 'success';
        $email_message = 'Due loans PDF sent successfully to ' . $recipient_email . '.';
    } catch (Exception $e) {
        error_log('Due loans email send failed: ' . $e->getMessage());
        $email_status = 'danger';
        $email_message = 'Unable to send the due loans PDF email. Please review the SMTP configuration.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Clients with Due Loans</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        :root { --ink: #172331; --muted: #687582; --line: #dbe3e8; --paper: #ffffff; --canvas: #f2f5f6; --teal: #147d78; --gold: #c7973e; }
        body { background: var(--canvas); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; }
        .sidebar { transition: all .3s ease; }
        .sidebar.collapsed { display: none; }
        body { padding-top: 70px; }
        .site-letterhead { align-items: center; background: linear-gradient(90deg, #00c6ff, #0072ff); color: white; display: flex; height: 70px; justify-content: space-between; padding: 0 24px; position: fixed; top: 0; width: 100%; z-index: 1100; }
        .site-letterhead-brand { align-items: center; display: flex; font-size: 24px; font-weight: bold; }
        .site-letterhead-brand img { height: 40px; margin-right: 10px; width: auto; }
        .site-letterhead-logout { color: white; font-size: 18px; text-decoration: none; }
        .site-letterhead-logout:hover { color: #e8f8ff; }
        .main { margin-left: 250px; padding: 34px 22px 60px; transition: margin-left .3s ease; }
        .main.sidebar-collapsed { margin-left: 0; }
        .report-shell { background: var(--paper); border: 1px solid var(--line); margin: 0 auto; max-width: 1280px; padding: 22px; }
        .report-header { align-items: center; background: var(--ink); border-top: 4px solid var(--gold); color: white; display: flex; gap: 16px; justify-content: space-between; margin: -22px -22px 22px; padding: 26px 34px; }
        .report-header h1 { font-family: Georgia, serif; font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: normal; margin: 0; }
        .sidebar-toggle-btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; margin-right: 4px; padding: 8px 12px; }
        .sidebar-toggle-btn:hover { background: var(--teal); border-color: var(--teal); color: white; }
        .report-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        .report-actions .form-control { background: white; border-radius: 0; color: var(--ink); max-width: 300px; }
        .btn-primary, .btn-success, .btn-outline-primary { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; }
        .btn-primary:hover, .btn-success:hover, .btn-outline-primary:hover { background: var(--teal); border-color: var(--teal); color: white; }
        .section-title { background: var(--ink); border-top: 4px solid var(--gold); color: white; font-family: Georgia, serif; font-size: 1.25rem; font-weight: normal; margin: 0 0 18px; padding: 18px 22px; }
        .filter-summary { border: 1px solid var(--line); color: var(--muted); margin-bottom: 18px; padding: 14px 18px; }
        .filter-summary .badge { background: #edf2f3 !important; color: #425460 !important; border-radius: 0; }
        .nav-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; margin-top: 0 !important; padding: 16px 22px 0; }
        .nav-tabs .nav-link { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); font-weight: normal; margin: 0; padding: 9px 14px; }
        .nav-tabs .nav-link.active { background: var(--teal); border-color: var(--teal); color: white; }
        .nav-tabs .nav-link:hover { background: #f7faf9; border-color: var(--teal); color: var(--teal); }
        .table-container { overflow-x: auto; margin-top: 18px !important; }
        .table { background: var(--paper); margin: 0; min-width: 900px; }
        .table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; padding: 14px 12px; text-transform: uppercase; white-space: nowrap; }
        .table tbody td { border-color: #e6ecef; padding: 15px 12px; vertical-align: middle; }
        .table tbody tr:hover { background: #f7faf9; }
        .table a { color: var(--teal); font-weight: bold; text-decoration: none; }
        .table a:hover { color: var(--ink); text-decoration: underline; }
        .table td[style*="f72500"] { background: #f8e8e5 !important; color: #8e4d47; font-weight: bold; }
        @media (max-width: 768px) { .main { margin-left: 0; padding: 20px 12px 40px; } .report-shell { padding: 16px 12px; } .report-header { align-items: flex-start; flex-direction: column; margin: -16px -12px 16px; padding: 20px; } .report-actions { justify-content: flex-start; width: 100%; } .report-actions .form-control { flex: 1 1 220px; max-width: none; } }
        @media (max-width: 520px) { .site-letterhead { padding: 0 12px; } .site-letterhead-brand { font-size: 18px; } .site-letterhead-brand img { height: 32px; } .site-letterhead-logout { font-size: 15px; } }
    </style>
</head>
<body>
<header class="site-letterhead">
    <div class="site-letterhead-brand"><img src="../assets/img/logo.png" alt="Inua Premium Logo">Inua Premium Services</div>
    <a class="site-letterhead-logout" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</header>
<div class="sidebar" id="sidebarWrapper">
    <?php include '../includes/sidebar.php'; ?>
</div>
<main class="main" id="mainContent">
<div class="report-shell">
    <div class="report-header">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
            <h1>Due Loans</h1>
        </div>
        <div class="report-actions">
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <form method="post" class="d-inline-block">
                <input type="hidden" name="send_email" value="1">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-envelope"></i> Send Email
                </button>
            </form>
            <button id="downloadDueLoansPdf" class="btn btn-success">
                <i class="bi bi-download"></i> Download PDF
            </button>
            <input type="text" id="searchInput" placeholder="Search by borrower or phone..." class="form-control" style="width: 300px;">
        </div>
    </div>
    <?php if (!empty($email_message)): ?>
        <div class="alert alert-<?= htmlspecialchars($email_status); ?> text-center" role="alert">
            <?= htmlspecialchars($email_message); ?>
        </div>
    <?php endif; ?>
    <h2 class="section-title">All Clients with Due Loans</h2>

    <ul class="nav nav-tabs report-tabs">
        <li class="nav-item"><a class="nav-link <?= ($selected_area === 'all') ? 'active' : '' ?>" href="?area_id=all&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=<?= htmlspecialchars($selected_day); ?>">All Regions</a></li>
        <?php foreach ($areas as $area): ?>
            <li class="nav-item"><a class="nav-link <?= ($selected_area == $area['area_id']) ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($area['area_id']); ?>&officer_id=all&day=<?= htmlspecialchars($selected_day); ?>"><?= htmlspecialchars($area['area_name']); ?></a></li>
        <?php endforeach; ?>
    </ul>

    <!-- Loan Officer Tabs -->
    <ul class="nav nav-tabs report-tabs mt-3">
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

    <!-- Day Tabs -->
    <ul class="nav nav-tabs report-tabs mt-3">
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'all') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=all">All Days</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Monday') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Monday">Monday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Tuesday') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Tuesday">Tuesday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Wednesday') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Wednesday">Wednesday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Thursday') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Thursday">Thursday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Friday') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Friday">Friday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Saturday') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Saturday">Saturday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Sunday') ? 'active' : '' ?>" href="?area_id=<?= htmlspecialchars($selected_area); ?>&officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Sunday">Sunday</a>
        </li>
    </ul>

    <div class="table-container mt-4">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Borrower</th>
                    <th>Phone Number</th>
                    <th>Loan ID</th>
                    <th>total Amount (KSH)</th>
                    <th>Total Paid (KSH)</th>
                    <th>Loan Balance (KSH)</th>
                    <th>Due Amount (KSH)</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($processed_due_loans)): ?>
                    <?php foreach ($processed_due_loans as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                            <td>
                                <a href="repayment_details.php?loanId=<?php echo $row['loan_id']; ?>">
                                    <?php echo htmlspecialchars($row['loan_id']); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($row['total_disbursed'], 2); ?></td>
                            <td><?php echo number_format($row['total_paid'], 2); ?></td>
                            <td><?php echo number_format($row['loan_balance'], 2); ?></td>
                            <td style="background-color: <?php echo !empty($row['is_past_due']) ? '#f72500' : 'transparent'; ?>;"><?php echo number_format($row['due_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No clients with due loans found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</main>

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

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            function normalizeSearchText(text) {
                return text.toLowerCase().replace(/[^a-z0-9]/g, '');
            }

            searchInput.addEventListener('input', function () {
                const filter = normalizeSearchText(this.value);
                const rows = document.querySelectorAll('.table tbody tr');

                rows.forEach(row => {
                    const cells = Array.from(row.cells).map(cell => normalizeSearchText(cell.textContent));
                    const match = cells.some(text => text.includes(filter));
                    row.style.display = match ? '' : 'none';
                });
            });
        }

        const downloadButton = document.getElementById('downloadDueLoansPdf');
        if (downloadButton) {
            downloadButton.addEventListener('click', function () {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('landscape');
                const logoPath = '../assets/img/logo.png';
                const img = new Image();

                function renderPdf(includeLogo) {
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const logoWidth = 28;
                    const logoHeight = 18;
                    const logoX = 14;
                    const logoY = 10;

                    if (includeLogo) {
                        doc.addImage(img, 'PNG', logoX, logoY, logoWidth, logoHeight);
                    }

                    doc.setFontSize(16);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Due Loans Report', pageWidth / 2, 18, { align: 'center' });
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'normal');
                    doc.text('Generated on ' + new Date().toLocaleDateString(), pageWidth - 14, 18, { align: 'right' });

                    const table = document.querySelector('.table');
                    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
                    const rows = Array.from(table.querySelectorAll('tbody tr'))
                        .filter(row => row.style.display !== 'none')
                        .map(row => Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim()));

                    doc.autoTable({
                        head: [headers],
                        body: rows,
                        startY: 35,
                        styles: { fontSize: 8, cellPadding: 2 },
                        headStyles: { fillColor: [0, 123, 255], textColor: [255, 255, 255] },
                        alternateRowStyles: { fillColor: [248, 249, 250] }
                    });

                    doc.save('due_loans_report.pdf');
                }

                img.onload = function () {
                    renderPdf(true);
                };

                img.onerror = function () {
                    renderPdf(false);
                };

                img.src = logoPath;
            });
        }
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
