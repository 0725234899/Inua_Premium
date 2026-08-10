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
    // Use landscape to fit all UI columns comfortably
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

// Fetch all loan officers
$sql_officers = "SELECT id, name AS full_name, email FROM users WHERE role_id = '2'";
$stmt_officers = $conn->prepare($sql_officers);
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

// Query to fetch all clients with due loans
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
                    $day_filter
                    $officer_filter
                  ORDER BY repayments.repayment_date ASC, loan_applications.id ASC, repayments.id ASC";

$stmt_due_loans = $conn->prepare($sql_due_loans);
if ($selected_day !== 'all' && $selected_officer !== 'all') {
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
        $pdf_content = generate_due_loans_pdf($processed_due_loans, $officer_display_name, $day_label);
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
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
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
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <div class="header-actions">
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
    <h2 class="text-center">All Clients with Due Loans</h2>

    <!-- Loan Officer Tabs -->
    <ul class="nav nav-tabs justify-content-center">
        <li class="nav-item">
            <a class="nav-link <?= ($selected_officer === 'all') ? 'active' : '' ?>" href="?officer_id=all&day=<?= htmlspecialchars($selected_day); ?>">All Loan Officers</a>
        </li>
        <?php while ($officer = $result_officers->fetch_assoc()): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_officer == $officer['id']) ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($officer['id']); ?>&day=<?= htmlspecialchars($selected_day); ?>">
                    <?= htmlspecialchars($officer['full_name']); ?>
                </a>
            </li>
        <?php endwhile; ?>
    </ul>

    <!-- Day Tabs -->
    <ul class="nav nav-tabs justify-content-center mt-3">
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'all') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=all">All Days</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Monday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Monday">Monday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Tuesday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Tuesday">Tuesday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Wednesday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Wednesday">Wednesday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Thursday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Thursday">Thursday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Friday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Friday">Friday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Saturday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Saturday">Saturday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Sunday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Sunday">Sunday</a>
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
                            <td style="background-color: <?php echo !empty($row['is_past_due']) ? '#f700ea' : 'transparent'; ?>;"><?php echo number_format($row['due_amount'], 2); ?></td>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
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
