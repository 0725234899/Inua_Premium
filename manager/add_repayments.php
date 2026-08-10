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

$message = ""; // To store success or error messages
if (isset($_POST['search'])) {
    $search_key = trim($_POST['search_key']);
    if (strlen($search_key) < 4 && !preg_match('/^\d{10}$/', $search_key)) {
        $message = "<div class='alert alert-warning text-center'>Please enter at least 4 characters or a valid 10-digit phone number.</div>";
    } else {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('search_btn').style.display = 'none';
            document.getElementById('search_form').style.display = 'none';
        });
        </script>";
        // Query to get loan details (include assigned loan officer name)
        $sql = "SELECT 
                borrowers.id AS borrower_id, 
                borrowers.full_name, 
                borrowers.mobile, 
                COALESCE(users.name, '') AS loan_officer_name,
                loan_applications.id AS loan_id, 
                loan_applications.loan_product, 
                COALESCE(SUM(repayments.amount), 0) AS total_due,
                COALESCE(SUM(repayments.paid), 0) AS total_paid
            FROM borrowers
            INNER JOIN loan_applications ON borrowers.id = loan_applications.borrower
            LEFT JOIN users ON borrowers.loan_officer = users.email
            INNER JOIN repayments ON loan_applications.id = repayments.loan_id
            WHERE 
                borrowers.mobile LIKE ? 
                OR borrowers.full_name LIKE ? 
                OR borrowers.unique_number LIKE ?
            GROUP BY 
                borrowers.id, borrowers.full_name, borrowers.mobile, 
                loan_applications.id, loan_applications.loan_product, users.name
            ORDER BY total_due DESC";

        $search_term = "%$search_key%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $message = "<div class='alert alert-danger text-center'>No such client on our database</div>";
        }
    }
}

// Repayment functionality
if (isset($_POST['repay'])) {
    $loan_id = $_POST['loan_id'];
    $amount_paid = $_POST['amount_paid'];
    $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    $payment_date = date('Y-m-d', strtotime($payment_date));

    if (!$payment_date || $payment_date === '1970-01-01') {
        $payment_date = date('Y-m-d');
    }

    if ($amount_paid > 0) {
        $message = distributeRepayment($loan_id, $amount_paid, $conn, $payment_date);

        $insertPayment = "INSERT INTO payment_date_records (loan_id, PaymentDate, Amount) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insertPayment);
        $insert_stmt->bind_param("isd", $loan_id, $payment_date, $amount_paid);
        $insert_stmt->execute();

        $message .= sendPaymentNotificationEmail($loan_id, $amount_paid, $conn);
    } else {
        $message = "<div class='alert alert-warning text-center'>Please enter a valid amount.</div>";
    }
}

// Bulk repayment functionality
if (isset($_POST['bulk_repay'])) {
    $mobiles = $_POST['bulk_mobile'] ?? [];
    $amounts = $_POST['bulk_amount'] ?? [];
    $dates = $_POST['bulk_date'] ?? [];
    $bulk_messages = [];

    for ($i = 0; $i < count($mobiles); $i++) {
        $mobile = trim($mobiles[$i]);
        $amount = floatval($amounts[$i] ?? 0);
        $payment_date = !empty($dates[$i]) ? date('Y-m-d', strtotime($dates[$i])) : date('Y-m-d');

        if ($mobile === '' || $amount <= 0) {
            $bulk_messages[] = "<div class='alert alert-warning'>Skipped empty or invalid row.</div>";
            continue;
        }

        // Find borrower by mobile or unique number
        $stmtB = $conn->prepare("SELECT id FROM borrowers WHERE mobile = ? OR unique_number = ? LIMIT 1");
        $stmtB->bind_param("ss", $mobile, $mobile);
        $stmtB->execute();
        $resB = $stmtB->get_result()->fetch_assoc();

        if (!$resB) {
            $bulk_messages[] = "<div class='alert alert-warning'>No borrower found for: " . htmlspecialchars($mobile) . "</div>";
            continue;
        }

        $borrower_id = $resB['id'];

        // Find an approved loan for borrower with outstanding balance
        $stmtL = $conn->prepare(
            "SELECT la.id FROM loan_applications la INNER JOIN repayments r ON la.id = r.loan_id WHERE la.borrower = ? AND la.loan_status = 'approved' GROUP BY la.id HAVING SUM(r.amount - r.paid) > 0 ORDER BY MIN(r.repayment_date) ASC LIMIT 1"
        );
        $stmtL->bind_param("i", $borrower_id);
        $stmtL->execute();
        $resL = $stmtL->get_result()->fetch_assoc();

        if (!$resL) {
            $bulk_messages[] = "<div class='alert alert-warning'>No outstanding approved loan for borrower: " . htmlspecialchars($mobile) . "</div>";
            continue;
        }

        $loan_id = $resL['id'];

        // Apply the repayment distribution
        $msg = distributeRepayment($loan_id, $amount, $conn, $payment_date);
        $bulk_messages[] = $msg;

        // Record payment date entry
        $ins = $conn->prepare("INSERT INTO payment_date_records (loan_id, PaymentDate, Amount) VALUES (?, ?, ?)");
        $ins->bind_param("isd", $loan_id, $payment_date, $amount);
        $ins->execute();

        $bulk_messages[] = sendPaymentNotificationEmail($loan_id, $amount, $conn);
    }

    $message = implode('', $bulk_messages);
}

// Function to distribute repayment
function distributeRepayment($loan_id, $amount_paid, $conn, $payment_date) {
    $sql = "SELECT * FROM repayments WHERE loan_id = ? AND COALESCE(paid, 0) < amount ORDER BY repayment_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_distributed = 0;

    // Fetch borrower name for a friendlier success message
    $borrowerName = '';
    $bstmt = $conn->prepare("SELECT b.full_name FROM loan_applications la JOIN borrowers b ON la.borrower = b.id WHERE la.id = ? LIMIT 1");
    if ($bstmt) {
        $bstmt->bind_param('i', $loan_id);
        $bstmt->execute();
        $brow = $bstmt->get_result()->fetch_assoc();
        if ($brow && !empty($brow['full_name'])) {
            $borrowerName = $brow['full_name'];
        }
        $bstmt->close();
    }

    while ($row = $result->fetch_assoc()) {
        $installment_id = $row['id'];
        $remaining_due = $row['amount'] - $row['paid'];

        if ($amount_paid <= 0) {
            break;
        }

        if ($amount_paid >= $remaining_due) {
            $new_amount_paid = $row['amount']; // Full payment
            $applied_amount = $remaining_due;
            $amount_paid -= $remaining_due;
        } else {
            $new_amount_paid = $row['paid'] + $amount_paid;
            $applied_amount = $amount_paid;
            $amount_paid = 0;
        }

        // Update the installment with the new paid amount
        $update_sql = "UPDATE repayments SET paid = ?, repaid_date = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("dsi", $new_amount_paid, $payment_date, $installment_id);
        $update_stmt->execute();
        
        $total_distributed += $applied_amount;
    }

    // Fetch the updated outstanding loan balance
    $outstanding_sql = "SELECT SUM(amount - COALESCE(paid, 0)) AS outstanding_balance FROM repayments WHERE loan_id = ?";
    $outstanding_stmt = $conn->prepare($outstanding_sql);
    $outstanding_stmt->bind_param("i", $loan_id);
    $outstanding_stmt->execute();
    $outstanding_balance = $outstanding_stmt->get_result()->fetch_assoc()['outstanding_balance'] ?? 0;

    if ($total_distributed > 0) {
        $clientLabel = $borrowerName !== '' ? htmlspecialchars($borrowerName) : 'Client';
        return "<div class='alert alert-success text-center'>" .
                    htmlspecialchars($clientLabel) . " has successfully paid KES: " . number_format($total_distributed, 2) . ".<br>" .
                    "Outstanding Loan Balance: " . number_format($outstanding_balance, 2) . " KES." .
                "</div>";
    } else {
        return "<div class='alert alert-warning text-center'>No repayments were necessary for this loan.</div>";
    }
}

function getLoanNotificationDetails($loan_id, $conn) {
    $sql = "SELECT b.full_name AS borrower_name, b.loan_officer AS officer_email, COALESCE(u.name, '') AS loan_officer_name, la.id AS loan_id
            FROM loan_applications la
            JOIN borrowers b ON la.borrower = b.id
            LEFT JOIN users u ON b.loan_officer = u.email
            WHERE la.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getLoanBalanceSummary($loan_id, $conn) {
    $sql = "SELECT 
                COALESCE(SUM(amount - COALESCE(paid, 0)), 0) AS outstanding_balance,
                COALESCE(SUM(CASE WHEN repayment_date < CURDATE() THEN GREATEST(amount - COALESCE(paid, 0), 0) ELSE 0 END), 0) AS overdue_balance
            FROM repayments
            WHERE loan_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getLoanOfficerPortfolioMetrics($officerEmail, $conn) {
    // Aggregate per-loan totals using subqueries, then compute officer-level aggregates.
    $sql1 = "SELECT 
                COALESCE(SUM(GREATEST(la.total_amount - COALESCE(rep.total_paid, 0), 0)), 0) AS loan_book,
                COALESCE(SUM(COALESCE(overdue.loan_overdue, 0)), 0) AS overdue_balance,
                COALESCE(SUM(COALESCE(rep.total_amount, 0) - COALESCE(rep.total_paid, 0)), 0) AS total_dues
            FROM loan_applications la
            INNER JOIN borrowers b ON la.borrower = b.id
            LEFT JOIN (
                SELECT loan_id, SUM(amount) AS total_amount, SUM(paid) AS total_paid
                FROM repayments
                GROUP BY loan_id
            ) rep ON la.id = rep.loan_id
            LEFT JOIN (
                SELECT loan_id, SUM(GREATEST(amount - COALESCE(paid, 0), 0)) AS loan_overdue
                FROM repayments
                WHERE repayment_date < CURDATE()
                GROUP BY loan_id
            ) overdue ON la.id = overdue.loan_id
            WHERE b.loan_officer = ?";

    $stmt1 = $conn->prepare($sql1);
    if (!$stmt1) {
        return ['loan_book' => 0.0, 'overdue_balance' => 0.0, 'par' => 0.0, 'total_clients' => 0, 'clients_in_arrears' => 0, 'total_dues' => 0.0];
    }
    $stmt1->bind_param('s', $officerEmail);
    $stmt1->execute();
    $metrics = $stmt1->get_result()->fetch_assoc();
    $stmt1->close();

    $loanBook = (float) ($metrics['loan_book'] ?? 0);
    $overdueBalance = (float) ($metrics['overdue_balance'] ?? 0);
    $totalDues = (float) ($metrics['total_dues'] ?? 0);

    // Total clients assigned to this officer with active loans and positive outstanding balance
    $sql2 = "SELECT COUNT(DISTINCT la.borrower) AS total_clients
             FROM loan_applications la
             INNER JOIN borrowers b ON la.borrower = b.id
             LEFT JOIN (
                 SELECT loan_id, SUM(paid) AS total_paid
                 FROM repayments
                 GROUP BY loan_id
             ) rep ON la.id = rep.loan_id
             WHERE b.loan_officer = ?
               AND (la.loan_status IN ('approved', 'rolled_over') OR LOWER(TRIM(COALESCE(la.loan_status, ''))) LIKE '%roll%')
               AND (la.total_amount - COALESCE(rep.total_paid, 0)) > 0";
    $stmt2 = $conn->prepare($sql2);
    $totalClients = 0;
    if ($stmt2) {
        $stmt2->bind_param('s', $officerEmail);
        $stmt2->execute();
        $totalClients = (int) ($stmt2->get_result()->fetch_assoc()['total_clients'] ?? 0);
        $stmt2->close();
    }

    // Clients in arrears (distinct borrowers with any overdue amount)
    $sql3 = "SELECT COUNT(DISTINCT la.borrower) AS clients_in_arrears
             FROM loan_applications la
             INNER JOIN borrowers b ON la.borrower = b.id
             INNER JOIN (
                 SELECT loan_id, SUM(GREATEST(amount - COALESCE(paid, 0), 0)) AS loan_overdue
                 FROM repayments
                 WHERE repayment_date < CURDATE()
                 GROUP BY loan_id
             ) overdue ON la.id = overdue.loan_id
             WHERE b.loan_officer = ? AND overdue.loan_overdue > 0";

    $stmt3 = $conn->prepare($sql3);
    $clientsInArrears = 0;
    if ($stmt3) {
        $stmt3->bind_param('s', $officerEmail);
        $stmt3->execute();
        $clientsInArrears = (int) ($stmt3->get_result()->fetch_assoc()['clients_in_arrears'] ?? 0);
        $stmt3->close();
    }

    $par = $loanBook > 0 ? ($overdueBalance / $loanBook) * 100 : 0;

    return [
        'loan_book' => $loanBook,
        'overdue_balance' => $overdueBalance,
        'par' => $par,
        'total_clients' => $totalClients,
        'clients_in_arrears' => $clientsInArrears,
        'total_dues' => $totalDues,
    ];
}

// Returns the sum of due amounts scheduled for the given date (default: today)
function getLoanOfficerTodayDues($officerEmail, $conn, $date = null) {
        $date = $date ?? date('Y-m-d');
        $sql = "SELECT COALESCE(SUM(GREATEST(r.amount - COALESCE(r.paid,0), 0)), 0) AS total_dues_today
                        FROM repayments r
                        INNER JOIN loan_applications la ON r.loan_id = la.id
                        INNER JOIN borrowers b ON la.borrower = b.id
                        WHERE DATE(r.repayment_date) = ?
                            AND b.loan_officer = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0.0;
        $stmt->bind_param('ss', $date, $officerEmail);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float) ($row['total_dues_today'] ?? 0);
}

function sendPaymentNotificationEmail($loan_id, $amount_paid, $conn) {
    $details = getLoanNotificationDetails($loan_id, $conn);
    if (!$details) {
        return "<div class='alert alert-warning text-center'>Payment saved but loan details were not found for notification.</div>";
    }

    $officerEmail = trim($details['officer_email'] ?? '');
    $senderEmail = getConfiguredSenderEmail();
    if (empty($officerEmail) || !filter_var($officerEmail, FILTER_VALIDATE_EMAIL)) {
        $officerEmail = $senderEmail;
    }

    if (empty($officerEmail) || !filter_var($officerEmail, FILTER_VALIDATE_EMAIL)) {
        return "<div class='alert alert-warning text-center'>Payment saved but no valid loan officer email exists for notification.</div>";
    }

    $recipient_email = $officerEmail;

    $loanSummary = getLoanBalanceSummary($loan_id, $conn);
    $outstandingBalance = $loanSummary['outstanding_balance'] ?? 0;
    $overdueBalance = $loanSummary['overdue_balance'] ?? 0;
    $loanStatus = $outstandingBalance <= 0 ? 'Loan fully cleared.' : 'Loan still has an outstanding balance.';
    $arrearsStatus = $overdueBalance <= 0 ? 'No overdue balance remains.' : 'Overdue balance remaining: KSH ' . number_format($overdueBalance, 2) . '.';
    $officerDisplay = !empty($details['loan_officer_name']) ? $details['loan_officer_name'] : $officerEmail;

    $portfolioMetrics = getLoanOfficerPortfolioMetrics($officerEmail, $conn);
    $totalLoanBook = number_format($portfolioMetrics['loan_book'] ?? 0, 2);
    $totalArrears = number_format($portfolioMetrics['overdue_balance'] ?? 0, 2);
    $totalClients = intval($portfolioMetrics['total_clients'] ?? 0);
    $clientsInArrears = intval($portfolioMetrics['clients_in_arrears'] ?? 0);
    $totalDues = number_format($portfolioMetrics['total_dues'] ?? 0, 2);
    $todayDuesValue = getLoanOfficerTodayDues($officerEmail, $conn);
    $todayDues = number_format($todayDuesValue, 2);
    $parPercentage = number_format($portfolioMetrics['par'] ?? 0, 2);

        $subject = 'Payment Received for ' . $details['borrower_name'];
        $recipient_email = $officerEmail;
        $greetName = !empty($officerDisplay) ? $officerDisplay : 'Team';
        $body = '<p>Dear ' . htmlspecialchars($greetName) . ',</p>'
              . '<p>The client <strong>' . htmlspecialchars($details['borrower_name']) . '</strong> has paid <strong>KSH ' . number_format($amount_paid, 2) . '</strong>.</p>'
              . '<p><strong>Outstanding balance:</strong> KSH ' . number_format($outstandingBalance, 2) . '</p>'
              . '<p><strong>Arrears status:</strong> ' . $arrearsStatus . '</p>'
              . '<hr />'
              . '<p><strong>Total loan book:</strong> KSH ' . $totalLoanBook . '</p>'
              . '<p><strong>Total arrears (overdue):</strong> KSH ' . $totalArrears . '</p>'
              . '<p><strong>Total clients:</strong> ' . $totalClients . '</p>'
              . '<p><strong>Clients in arrears:</strong> ' . $clientsInArrears . '</p>'
              . '<p><strong>Total dues (today):</strong> KSH ' . $todayDues . '</p>'
              . '<p><strong>Portfolio at Risk (PAR):</strong> ' . $parPercentage . '%</p>'
              . '<p>Thank you,<br>Inua Premium Services</p>';

        $emailCredentials = getEmailAccount();
        if (!$emailCredentials || empty($emailCredentials['sender_email']) || empty($emailCredentials['sender_app_password'])) {
            return "<div class='alert alert-warning text-center'>Payment saved but notification email was not sent because email settings are not configured. Please configure sender email and app password in the email settings page.</div>";
        }

        try {
            ignore_user_abort(true);
            set_time_limit(130);

            $mail = new PHPMailer(true);
            $mail->Timeout = 120;
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
            $mail->send();

            return "<div class='alert alert-success text-center'>Payment notification sent to " . htmlspecialchars($recipient_email) . ".</div>";
        } catch (Exception $e) {
            $errorInfo = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
            return "<div class='alert alert-warning text-center'>Payment saved successfully. Notification email failed or timed out: " . htmlspecialchars($errorInfo) . "</div>";
        }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Repayment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 700px;
            margin: 50px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        h2 {
            font-size: 26px;
            font-weight: bold;
            text-align: center;
            color: #343a40;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #555;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px;
            font-size: 16px;
        }

        .btn-primary, .btn-success {
            font-size: 16px;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 8px;
            transition: 0.3s ease-in-out;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-success:hover {
            background-color: #1e7e34;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .table thead {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }

        .table td, .table th {
            padding: 12px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .container {
                width: 90%;
                padding: 20px;
            }

            .table {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <a href="manager_dashboard.php" class="btn btn-primary mb-3">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </a>
        <h2>Make a Loan Payments here 👇</h2>
        <?= $message; ?>

        <!-- Bulk-only page: single repayment/search removed -->

        <!-- Bulk Repayments Section -->
        <div class="container mb-4">
            <h3>Bulk Repayments</h3>
            <p class="text-muted">Add multiple repayments at once. You can also search borrowers and add them to the bulk list.</p>
            <div id="bulk_section">
                <div class="mb-3 d-flex">
                    <input type="text" id="bulk_search_key" class="form-control" placeholder="Search by name, phone, or unique ID">
                    <button type="button" id="bulk_search_btn" class="btn btn-primary ms-2">Search Borrowers</button>
                </div>
                <div id="bulk_search_results" style="max-height:250px; overflow:auto; display:none;" class="mb-3"></div>
                <form method="POST" id="bulk_form">
                    <table class="table table-bordered" id="bulk_table">
                        <thead>
                            <tr>
                                <th>Borrower Mobile / ID</th>
                                <th>Amount (KES)</th>
                                <th>Payment Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="bulk_mobile[]" class="form-control bulk_mobile_input" placeholder="Mobile or Unique ID" required autocomplete="off"></td>
                                <td><input type="number" step="0.01" name="bulk_amount[]" class="form-control" required></td>
                                <td><input type="date" name="bulk_date[]" class="form-control" value="<?= date('Y-m-d'); ?>" required></td>
                                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">Add Row</button>
                    <button type="submit" name="bulk_repay" class="btn btn-success btn-sm">Submit Bulk Repayments</button>
                </form>
            </div>
        </div>

        <script>
            document.getElementById('bulk_search_btn').addEventListener('click', function() {
                const key = document.getElementById('bulk_search_key').value.trim();
                const results = document.getElementById('bulk_search_results');
                results.innerHTML = '';
                if (key.length < 2) {
                    results.style.display = 'none';
                    return;
                }
                fetch(`search_borrowers.php?query=${encodeURIComponent(key)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data || data.length === 0) {
                            results.style.display = 'none';
                            return;
                        }
                        data.forEach(b => {
                            const div = document.createElement('div');
                            div.className = 'd-flex justify-content-between align-items-center p-2 border-bottom';
                            const officer = b.loan_officer_name && b.loan_officer_name.trim() !== '' ? b.loan_officer_name : 'Unassigned';
                            div.innerHTML = `<div>${b.full_name} (${b.mobile}) - ${officer}</div><div><button type="button" class="btn btn-sm btn-success">Add to Bulk</button></div>`;
                            div.querySelector('button').addEventListener('click', function() {
                                addRowFromData(b.mobile);
                            });
                            results.appendChild(div);
                        });
                        results.style.display = 'block';
                    })
                    .catch(() => { results.style.display = 'none'; });
            });

            function addRowFromData(mobile) {
                const tbody = document.querySelector('#bulk_table tbody');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" name="bulk_mobile[]" class="form-control bulk_mobile_input" value="${mobile}" required autocomplete="off"></td>
                    <td><input type="number" step="0.01" name="bulk_amount[]" class="form-control" required></td>
                    <td><input type="date" name="bulk_date[]" class="form-control" value="<?= date('Y-m-d'); ?>" required></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button></td>
                `;
                tbody.appendChild(tr);
                document.getElementById('bulk_search_results').style.display = 'none';
            }
        </script>

        <script>
                function addRow() {
                const tbody = document.querySelector('#bulk_table tbody');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" name="bulk_mobile[]" class="form-control bulk_mobile_input" placeholder="Mobile or Unique ID" required autocomplete="off"></td>
                    <td><input type="number" step="0.01" name="bulk_amount[]" class="form-control" required></td>
                    <td><input type="date" name="bulk_date[]" class="form-control" value="<?= date('Y-m-d'); ?>" required></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button></td>
                `;
                tbody.appendChild(tr);
            }

            function removeRow(btn) {
                const tr = btn.closest('tr');
                if (tr) tr.remove();
            }
        </script>

        <script>
            (function() {
                let bulkSuggestionsBox = document.createElement('div');
                bulkSuggestionsBox.className = 'bulk-suggestions-box';
                bulkSuggestionsBox.style.position = 'absolute';
                bulkSuggestionsBox.style.zIndex = '1100';
                bulkSuggestionsBox.style.backgroundColor = '#fff';
                bulkSuggestionsBox.style.border = '1px solid #ddd';
                bulkSuggestionsBox.style.display = 'none';
                bulkSuggestionsBox.style.maxHeight = '250px';
                bulkSuggestionsBox.style.overflowY = 'auto';
                document.body.appendChild(bulkSuggestionsBox);

                let activeInput = null;

                document.addEventListener('input', function(e) {
                    if (!e.target.classList.contains('bulk_mobile_input')) return;
                    const input = e.target;
                    activeInput = input;
                    const query = input.value.trim();
                    if (query.length < 2) {
                        bulkSuggestionsBox.style.display = 'none';
                        return;
                    }

                    fetch(`search_borrowers.php?query=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(data => {
                            bulkSuggestionsBox.innerHTML = '';
                            if (!data || data.length === 0) {
                                bulkSuggestionsBox.style.display = 'none';
                                return;
                            }
                            data.forEach(b => {
                                const div = document.createElement('div');
                                div.style.padding = '8px';
                                div.style.cursor = 'pointer';
                                const officer = b.loan_officer_name && b.loan_officer_name.trim() !== '' ? b.loan_officer_name : 'Unassigned';
                                div.textContent = `${b.full_name} (${b.mobile}) - ${officer}`;
                                div.addEventListener('click', function() {
                                    if (activeInput) {
                                        activeInput.value = b.mobile;
                                        bulkSuggestionsBox.style.display = 'none';
                                    }
                                });
                                bulkSuggestionsBox.appendChild(div);
                            });
                            const rect = input.getBoundingClientRect();
                            bulkSuggestionsBox.style.top = rect.bottom + window.scrollY + 'px';
                            bulkSuggestionsBox.style.left = rect.left + window.scrollX + 'px';
                            bulkSuggestionsBox.style.minWidth = rect.width + 'px';
                            bulkSuggestionsBox.style.display = 'block';
                        })
                        .catch(() => {
                            bulkSuggestionsBox.style.display = 'none';
                        });
                });

                document.addEventListener('click', function(e) {
                    if (e.target.classList && e.target.classList.contains('bulk_mobile_input')) return;
                    if (bulkSuggestionsBox.contains(e.target)) return;
                    bulkSuggestionsBox.style.display = 'none';
                });
            })();
        </script>

        <!-- Single repayment results removed; bulk repayments only -->
    </div>
</body>
</html>
