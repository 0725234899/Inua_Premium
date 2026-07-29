<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);

include 'db.php';
include '../includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

function get_projected_maturity_date_for_loan($loan) {
    if (empty($loan['loan_release_date'])) {
        return null;
    }

    $date = new DateTime($loan['loan_release_date']);
    $count = (int) ($loan['number_of_repayments'] ?? 0);

    if ($count <= 0) {
        return $date;
    }

    $intervalSpec = 'P1M';
    switch (($loan['repayment_cycle'] ?? 'monthly')) {
        case 'daily':
            $intervalSpec = 'P1D';
            break;
        case 'weekly':
            $intervalSpec = 'P1W';
            break;
        case 'monthly':
            $intervalSpec = 'P1M';
            break;
        case 'yearly':
            $intervalSpec = 'P1Y';
            break;
    }

    $interval = new DateInterval($intervalSpec);
    for ($i = 0; $i < $count; $i++) {
        $date->add($interval);
    }

    return $date;
}

function get_eligible_loan_ids_for_arrears($conn) {
    $eligible_ids = [];
    $stmt = $conn->prepare("SELECT id, loan_status, loan_release_date, repayment_cycle, number_of_repayments FROM loan_applications WHERE loan_status IN ('approved', 'rolled_over')");
    if (!$stmt) {
        return $eligible_ids;
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $today = new DateTime('today');
    while ($loan = $result->fetch_assoc()) {
        if (($loan['loan_status'] ?? '') !== 'rolled_over') {
            $eligible_ids[] = (int) $loan['id'];
            continue;
        }

        $projected_maturity_date = get_projected_maturity_date_for_loan($loan);
        if ($projected_maturity_date !== null && $projected_maturity_date <= $today) {
            $eligible_ids[] = (int) $loan['id'];
        }
    }

    return $eligible_ids;
}

function fetch_arrears_report_data($conn, $selected_officer = 'all', $selected_day = 'all') {
    $day_filter = ($selected_day !== 'all') ? "AND DAYNAME(repayments.repayment_date) = ?" : "";
    $officer_filter = ($selected_officer !== 'all') ? "AND borrowers.loan_officer = ?" : "";

    $eligible_loan_ids = get_eligible_loan_ids_for_arrears($conn);
    $eligible_loan_filter = !empty($eligible_loan_ids)
        ? "AND loan_applications.id IN (" . implode(',', $eligible_loan_ids) . ")"
        : "AND 1=0";

    $sql = "SELECT 
                borrowers.full_name AS borrower_name, 
                borrowers.mobile AS phone_number, 
                GREATEST(
                    COALESCE(SUM(CASE 
                        WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0) 
                        ELSE 0 
                    END), 0) 
                    - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0), 
                    0
                ) AS total_overdue,
                DATEDIFF(CURDATE(), MIN(CASE 
                    WHEN repayments.repayment_date < CURDATE() 
                    THEN repayments.repayment_date 
                    ELSE NULL 
                END)) + 1 AS days_in_arrears,
                GREATEST(
                    COALESCE((SELECT SUM(la.total_amount) FROM loan_applications la WHERE la.borrower = borrowers.id), 0)
                    - COALESCE((SELECT SUM(rp.paid) FROM loan_applications la2 LEFT JOIN repayments rp ON la2.id = rp.loan_id WHERE la2.borrower = borrowers.id), 0),
                    0
                ) AS outstanding_loan_balance
            FROM borrowers
            LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
            LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
            WHERE 1=1
            $eligible_loan_filter
            $officer_filter
            $day_filter
            GROUP BY borrowers.full_name, borrowers.mobile
            HAVING total_overdue > 0
            ORDER BY days_in_arrears DESC, total_overdue DESC, borrowers.full_name";

    $stmt = $conn->prepare($sql);
    if ($selected_officer !== 'all' && $selected_day !== 'all') {
        $stmt->bind_param('ss', $selected_officer, $selected_day);
    } elseif ($selected_officer !== 'all') {
        $stmt->bind_param('s', $selected_officer);
    } elseif ($selected_day !== 'all') {
        $stmt->bind_param('s', $selected_day);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    $total_overdue = 0;
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $total_overdue += (float) $row['total_overdue'];
        $count++;
    }

    return [
        'rows' => $rows,
        'total_overdue' => $total_overdue,
        'total_overdue_count' => $count,
    ];
}

function generate_arrears_pdf($rows, $total_overdue, $total_overdue_count, $loan_officer_label, $day_label) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor('Inua Premium Services');
    $pdf->SetTitle('Arrears List Report');
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $pdf->SetTextColor(56, 152, 219);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetY(10);
    $pdf->Cell(0, 8, 'Inua Premium Services', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Arrears List', 0, 1, 'C');
    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Loan Officer: ' . $loan_officer_label, 0, 1, 'C');
    $pdf->Cell(0, 6, 'Day Filter: ' . $day_label, 0, 1, 'C');
    $pdf->Ln(3);

    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX(110);
    $pdf->Cell(80, 6, 'Total Arrears: KSH ' . number_format($total_overdue, 2), 0, 1, 'R');
    $pdf->SetX(110);
    $pdf->Cell(80, 6, 'Total Clients in Arrears: ' . $total_overdue_count, 0, 1, 'R');
    $pdf->Ln(2);

    $pdf->SetFillColor(56, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(60, 8, 'Borrower', 1, 0, 'L', true);
    $pdf->Cell(30, 8, 'Phone', 1, 0, 'L', true);
    $pdf->Cell(25, 8, 'Days', 1, 0, 'L', true);
    $pdf->Cell(35, 8, 'OLB', 1, 0, 'R', true);
    $pdf->Cell(35, 8, 'Arrears', 1, 1, 'R', true);

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetFont('helvetica', '', 9);
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $pdf->Cell(60, 7, $row['borrower_name'], 1, 0, 'L');
            $pdf->Cell(30, 7, $row['phone_number'], 1, 0, 'L');
            $pdf->Cell(25, 7, (int) $row['days_in_arrears'] . ' days', 1, 0, 'L');
            $pdf->Cell(35, 7, 'KSH ' . number_format($row['outstanding_loan_balance'], 2), 1, 0, 'R');
            $pdf->Cell(35, 7, 'KSH ' . number_format($row['total_overdue'], 2), 1, 1, 'R');
        }
    } else {
        $pdf->Cell(0, 8, 'No arrears found.', 1, 1, 'C');
    }

    $pdf->Ln(4);
    $pdf->SetFillColor(248, 249, 250);
    $pdf->SetDrawColor(56, 152, 219);
    $pdf->SetTextColor(56, 152, 219);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 6, 'Powered by AntonTech', 0, 1, 'C');

    return $pdf->Output('', 'S');
}

function send_arrears_pdf_email($recipient_email, $subject, $body, $pdf_content, $filename) {
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

// Fetch all loan officers
$sql_officers = "SELECT email, name AS full_name FROM users WHERE role_id = '2'";
$stmt_officers = $conn->prepare($sql_officers);
$stmt_officers->execute();
$result_officers = $stmt_officers->get_result();

$officer_name_map = [];
while ($officer = $result_officers->fetch_assoc()) {
    $officer_name_map[$officer['email']] = $officer['full_name'];
}

// Rewind the result set so the later UI loop can use it
$result_officers->data_seek(0);

// Get selected loan officer (if any)
$selected_officer = isset($_GET['officer_email']) ? $_GET['officer_email'] : 'all';
$officer_filter = ($selected_officer !== 'all') ? "AND borrowers.loan_officer = ?" : "";

$eligible_loan_ids = get_eligible_loan_ids_for_arrears($conn);
$eligible_loan_filter = !empty($eligible_loan_ids)
    ? "AND loan_applications.id IN (" . implode(',', $eligible_loan_ids) . ")"
    : "AND 1=0";

// Query to get all clients with overdue repayments, using scheduled elapsed installments minus total repaid
$sql_overdue = "SELECT 
                    borrowers.full_name AS borrower_name, 
                    borrowers.mobile AS phone_number, 
                    GREATEST(
                        COALESCE(SUM(CASE 
                            WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0) 
                            ELSE 0 
                        END), 0) 
                        - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0), 
                        0
                    ) AS total_overdue,
                    DATEDIFF(CURDATE(), MIN(CASE 
                        WHEN repayments.repayment_date < CURDATE() 
                        THEN repayments.repayment_date 
                        ELSE NULL 
                    END)) + 1 AS days_in_arrears,
                    GREATEST(
                        COALESCE((SELECT SUM(la.total_amount) FROM loan_applications la WHERE la.borrower = borrowers.id), 0)
                        - COALESCE((SELECT SUM(rp.paid) FROM loan_applications la2 LEFT JOIN repayments rp ON la2.id = rp.loan_id WHERE la2.borrower = borrowers.id), 0),
                        0
                    ) AS outstanding_loan_balance
                FROM 
                    borrowers
                LEFT JOIN 
                    loan_applications ON borrowers.id = loan_applications.borrower
                LEFT JOIN 
                    repayments ON loan_applications.id = repayments.loan_id
                WHERE 
                    1=1
                    $eligible_loan_filter
                    $officer_filter
                    $day_filter
                GROUP BY 
                    borrowers.full_name, borrowers.mobile
                HAVING 
                    total_overdue > 0
                ORDER BY 
                    days_in_arrears DESC, total_overdue DESC, borrowers.full_name";

$stmt_overdue = $conn->prepare($sql_overdue);
if ($selected_officer !== 'all' && $selected_day !== 'all') {
    $stmt_overdue->bind_param("ss", $selected_officer, $selected_day);
} elseif ($selected_officer !== 'all') {
    $stmt_overdue->bind_param("s", $selected_officer);
} elseif ($selected_day !== 'all') {
    $stmt_overdue->bind_param("s", $selected_day);
}
$stmt_overdue->execute();
$result_overdue = $stmt_overdue->get_result();

// Calculate total overdue and count, and store rows for display/email
$arrears_rows = [];
$total_overdue = 0;
$total_overdue_count = 0;
while ($row = $result_overdue->fetch_assoc()) {
    $arrears_rows[] = $row;
    $total_overdue += $row['total_overdue'];
    $total_overdue_count++;
}

$email_message = '';
$email_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $selected_officer_for_email = isset($_POST['officer_email']) ? $_POST['officer_email'] : 'all';
    $selected_day_for_email = isset($_POST['day']) ? $_POST['day'] : 'all';
    $sender_email = function_exists('getConfiguredSenderEmail') ? getConfiguredSenderEmail() : '';
    $recipient_email = ($selected_officer_for_email !== 'all' && !empty($selected_officer_for_email) && filter_var($selected_officer_for_email, FILTER_VALIDATE_EMAIL))
        ? $selected_officer_for_email
        : $sender_email;

    $officer_display_name = ($selected_officer_for_email !== 'all' && isset($officer_name_map[$selected_officer_for_email]))
        ? $officer_name_map[$selected_officer_for_email]
        : 'All loan officers';
    $officer_label = 'Loan officer: ' . htmlspecialchars($officer_display_name);
    $day_label = ($selected_day_for_email !== 'all') ? 'Day filter: ' . htmlspecialchars($selected_day_for_email) : 'All days';

    $email_body = '<p>Dear Team,</p><p>Please find the attached arrears report for <strong>' . htmlspecialchars($officer_label) . '</strong> and <strong>' . htmlspecialchars($day_label) . '</strong>.</p><p><strong>Total Arrears:</strong> KSH ' . number_format($total_overdue, 2) . '<br><strong>Total Clients in Arrears:</strong> ' . $total_overdue_count . '</p><p>This report was generated from the arrears list screen for Inua Premium Services.</p>';
    $subject = 'Arrears List Report - ' . htmlspecialchars($officer_label);

    if ($recipient_email === '') {
        $email_status = 'warning';
        $email_message = 'Please configure the sender email and app password in the email settings page before sending reports.';
    } else {
        try {
            $pdf_content = generate_arrears_pdf($arrears_rows, $total_overdue, $total_overdue_count, htmlspecialchars($officer_label), htmlspecialchars($day_label));
            $filename = 'arrears_report_' . date('Ymd_His') . '.pdf';
            send_arrears_pdf_email($recipient_email, $subject, $email_body, $pdf_content, $filename);
            $email_status = 'success';
            $email_message = 'Arrears report PDF sent successfully to ' . $recipient_email . '.';
        } catch (Exception $e) {
            error_log('Arrears PDF email send failed: ' . $e->getMessage());
            $email_status = 'danger';
            $email_message = strpos($e->getMessage(), 'Email settings are not configured') !== false
                ? 'Please configure the sender email and app password in the email settings page before sending reports.'
                : 'Unable to send the arrears PDF email. Please review the SMTP configuration.';
        }
    }
}

if ((PHP_SAPI === 'cli' && isset($argv[1]) && $argv[1] === 'auto') || (isset($_GET['mode']) && $_GET['mode'] === 'auto')) {
    $officer_sql = "SELECT email, name AS full_name FROM users WHERE role_id = '2' ORDER BY name";
    $stmt_officers_auto = $conn->prepare($officer_sql);
    $stmt_officers_auto->execute();
    $officers_result = $stmt_officers_auto->get_result();

    $sender_email = function_exists('getConfiguredSenderEmail') ? getConfiguredSenderEmail() : '';
    $admin_recipient = $sender_email !== '' ? $sender_email : '';
    $sent_count = 0;
    while ($officer = $officers_result->fetch_assoc()) {
        if (empty($officer['email'])) {
            continue;
        }

        try {
            $officer_report = fetch_arrears_report_data($conn, $officer['email'], 'all');
            $pdf_content = generate_arrears_pdf($officer_report['rows'], $officer_report['total_overdue'], $officer_report['total_overdue_count'], $officer['full_name'], 'All days');
            $filename = 'arrears_report_' . strtolower(str_replace(' ', '_', $officer['full_name'])) . '_' . date('Ymd_His') . '.pdf';
            $subject = 'Arrears Report - ' . $officer['full_name'];
            $body = '<p>Dear ' . htmlspecialchars($officer['full_name']) . ',</p><p>Please find your attached arrears report for today.</p><p>This report was generated automatically by Inua Premium Services.</p>';

            send_arrears_pdf_email($officer['email'], $subject, $body, $pdf_content, $filename);
            if ($admin_recipient !== '') {
                send_arrears_pdf_email($admin_recipient, $subject, $body, $pdf_content, $filename);
            }
            $sent_count++;
        } catch (Exception $e) {
            error_log('Auto arrears report send failed for ' . $officer['email'] . ': ' . $e->getMessage());
        }
    }

    if (PHP_SAPI === 'cli') {
        echo "Sent {$sent_count} arrears reports.\n";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arrears list</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
            color: #212529;
        }
        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 15px 0;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
        }
        .table {
            margin-top: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #e84545;
            color: #ffffff;
            text-align: center;
        }
        .table td {
            text-align: center;
        }
        .btn-primary {
            background-color: #e84545;
            border: none;
            transition: all 0.3s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #d43d3d;
        }
        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #e84545;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <div class="header">
        <h1>Arrears List</h1>
    </div>
    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-3">
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            <div>
                <form method="post" class="d-inline-block">
                    <input type="hidden" name="send_email" value="1">
                    <input type="hidden" name="officer_email" value="<?= htmlspecialchars($selected_officer); ?>">
                    <input type="hidden" name="day" value="<?= htmlspecialchars($selected_day); ?>">
                    <button type="submit" class="btn btn-outline-danger me-2">Send Email</button>
                </form>
                <button id="downloadArrearsList" class="btn btn-danger">Download Arrears List</button>
            </div>
        </div>
        <?php if (!empty($email_message)): ?>
            <div class="alert alert-<?= htmlspecialchars($email_status); ?> text-center" role="alert">
                <?= htmlspecialchars($email_message); ?>
            </div>
        <?php endif; ?>
        <p class="text-center"><strong>Total Arrears:</strong> KSH <?= number_format($total_overdue, 2); ?></p>
        
        <!-- Loan Officer Tabs -->
        <ul class="nav nav-tabs justify-content-center">
            <li class="nav-item">
                <a class="nav-link <?= ($selected_officer === 'all') ? 'active' : '' ?>" href="?officer_email=all">All Loan Officers</a>
            </li>
            <?php while ($officer = $result_officers->fetch_assoc()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($selected_officer == $officer['email']) ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($officer['email']); ?>">
                        <?= htmlspecialchars($officer['full_name']); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>

        <!-- Day Tabs -->
        <ul class="nav nav-tabs justify-content-center mt-3">
            <li class="nav-item">
                <a class="nav-link <?= ($selected_day === 'all') ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($selected_officer); ?>&day=all">All Days</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_day === 'Monday') ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($selected_officer); ?>&day=Monday">Monday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_day === 'Tuesday') ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($selected_officer); ?>&day=Tuesday">Tuesday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_day === 'Wednesday') ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($selected_officer); ?>&day=Wednesday">Wednesday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_day === 'Thursday') ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($selected_officer); ?>&day=Thursday">Thursday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_day === 'Friday') ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($selected_officer); ?>&day=Friday">Friday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_day === 'Saturday') ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($selected_officer); ?>&day=Saturday">Saturday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_day === 'Sunday') ? 'active' : '' ?>" href="?officer_email=<?= htmlspecialchars($selected_officer); ?>&day=Sunday">Sunday</a>
            </li>
        </ul>

        <!-- Search Input -->
        <div class="d-flex justify-content-end mt-3">
            <input type="text" id="searchInput" class="form-control" placeholder="Search..." style="width: 300px;">
        </div>

        <h3 class="section-title mt-4">Arrears List</h3>
        <table id="arrearsListTable" class="table table-bordered">
            <thead>
                <tr>
                    <th>Borrower</th>
                    <th>Phone Number</th>
                    <th>Days in Arrears</th>
                    <th class="text-end">Outstanding Loan Balance</th>
                    <th class="text-end">Total Overdue Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($arrears_rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['borrower_name']); ?></td>
                        <td><?= htmlspecialchars($row['phone_number']); ?></td>
                        <td><?= (int) $row['days_in_arrears']; ?> days</td>
                        <td class="text-end">KSH <?= number_format($row['outstanding_loan_balance'], 2); ?></td>
                        <td class="text-end">KSH <?= number_format($row['total_overdue'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($arrears_rows)): ?>
                    <tr><td colspan="5">No arrears found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <footer class="text-center mt-5">
        <p><em>Powered by AntonTech</em></p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('arrearsListTable');
            searchInput.addEventListener('input', function () {
                const filter = searchInput.value.toLowerCase();
                const rows = table.getElementsByTagName('tr');
                Array.from(rows).forEach((row, index) => {
                    if (index === 0) return; // Skip header row
                    const cells = row.getElementsByTagName('td');
                    const match = Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(filter));
                    row.style.display = match ? '' : 'none';
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function downloadTableAsPDF(tableId, title) {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Add logo
                const logoPath = "/Inua_Premium_services/assets/img/logo.png";
                const img = new Image();
                img.src = logoPath;

                img.onload = function () {
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const logoWidth = 40;
                    const logoHeight = 25;
                    const logoX = (pageWidth - logoWidth) / 2; // Center the logo
                    const logoY = 10;

                    doc.addImage(img, 'PNG', logoX, logoY, logoWidth, logoHeight);

                    // Add loan officer's email if selected
                    const loanOfficerEmail = "<?= ($selected_officer !== 'all') ? htmlspecialchars($selected_officer) : 'All Loan Officers'; ?>";
                    doc.setFontSize(12);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`Loan Officer: ${loanOfficerEmail}`, pageWidth / 2, logoY + logoHeight + 5, { align: 'center' });

                    // Add title
                    const documentTitle = `Arrears List - ${loanOfficerEmail}`;
                    doc.setFontSize(18);
                    doc.setFont('helvetica', 'bold');
                    doc.text(documentTitle, pageWidth / 2, logoY + logoHeight + 15, { align: 'center' });

                    // Add underline
                    doc.setDrawColor(0); // Black color
                    doc.setLineWidth(0.5);
                    doc.line(10, logoY + logoHeight + 17, pageWidth - 10, logoY + logoHeight + 17);

                    // Add total overdue summary
                    const summaryY = logoY + logoHeight + 25;
                    doc.setFontSize(12);
                    doc.text(`Total Arrears: KSH <?= number_format($total_overdue, 2); ?>`, 10, summaryY);
                    doc.text(`Total Clients in Arrears: <?= $total_overdue_count; ?>`, 10, summaryY + 7);

                    // Extract table data
                    const table = document.getElementById(tableId);
                    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
                    const rows = Array.from(table.querySelectorAll('tbody tr')).map(row =>
                        Array.from(row.querySelectorAll('td')).map((td, index) => {
                            const headerText = headers[index]?.toLowerCase() || '';
                            return headerText.includes('amount') || headerText.includes('balance')
                                ? { content: td.textContent.trim(), styles: { halign: 'right' } }
                                : td.textContent.trim();
                        })
                    );

                    // Add table to PDF
                    doc.autoTable({
                        head: [headers],
                        body: rows,
                        startY: summaryY + 15, // Start below the summary
                        margin: { left: 10, right: 10 },
                        headStyles: { fillColor: [232, 69, 69], textColor: [255, 255, 255] },
                        bodyStyles: { fontSize: 10 },
                        styles: { overflow: 'linebreak' },
                    });

                    // Add footer
                    const footerY = doc.internal.pageSize.getHeight() - 10;
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'italic');
                    doc.setTextColor(135, 206, 235); // Sky blue color
                    doc.text('Powered by AntonTech', pageWidth / 2, footerY, { align: 'center' });

                    // Save the PDF
                    doc.save(`${documentTitle.replace(/\s+/g, '_')}.pdf`);
                };

                img.onerror = function () {
                    alert("Failed to load the logo. Please check the logo path.");
                };
            }

            document.getElementById('downloadArrearsList').addEventListener('click', function () {
                downloadTableAsPDF('arrearsListTable', 'Arrears List');
            });
        });
    </script>
</body>
</html>
