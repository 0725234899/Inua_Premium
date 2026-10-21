<?php
session_start();
include 'db.php';
include '../includes/functions.php';
include 'includes/header.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

$conn->query("CREATE TABLE IF NOT EXISTS advances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_officer_id INT NOT NULL,
    loan_officer_name VARCHAR(255) NOT NULL,
    template_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance DECIMAL(12,2) NOT NULL,
    start_date DATE NOT NULL,
    booking_date DATE DEFAULT NULL,
    monthly_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    duration_months INT NOT NULL DEFAULT 1,
    recorded_by INT DEFAULT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$advanceDurationCheck = $conn->query("SHOW COLUMNS FROM advances LIKE 'duration_months'");
if ($advanceDurationCheck && $advanceDurationCheck->num_rows === 0) {
    $conn->query("ALTER TABLE advances ADD COLUMN duration_months INT NOT NULL DEFAULT 1");
}
$bookingDateCheck = $conn->query("SHOW COLUMNS FROM advances LIKE 'booking_date'");
if (!$bookingDateCheck) {
    throw new RuntimeException('Unable to check the advance booking date column: ' . $conn->error);
}
if ($bookingDateCheck->num_rows === 0) {
    if (!$conn->query("ALTER TABLE advances ADD COLUMN booking_date DATE NULL AFTER start_date")) {
        throw new RuntimeException('Unable to add the advance booking date column: ' . $conn->error);
    }
}
if (!$conn->query("UPDATE advances SET booking_date = DATE(recorded_at) WHERE booking_date IS NULL")) {
    throw new RuntimeException('Unable to populate existing advance booking dates: ' . $conn->error);
}

$conn->query("CREATE TABLE IF NOT EXISTS advance_repayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advance_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    recorded_by INT DEFAULT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS advance_repayment_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advance_id INT NOT NULL,
    due_amount DECIMAL(12,2) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$messages = [];

function get_advance_schedule_date($startDate, $monthOffset) {
    $anchorDate = new DateTimeImmutable($startDate);
    $dayOfMonth = (int) $anchorDate->format('j');
    $targetMonth = $anchorDate->modify('first day of this month')->modify('+' . (int) $monthOffset . ' months');

    return $targetMonth
        ->setDate(
            (int) $targetMonth->format('Y'),
            (int) $targetMonth->format('m'),
            min($dayOfMonth, (int) $targetMonth->format('t'))
        )
        ->format('Y-m-d');
}

function build_advance_email_body($subject, $body, $advanceDetails, $scheduleRows) {
    $escape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $logoPath = __DIR__ . '/../assets/img/logo.png';
    $html = '<html><body style="margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#eaf5ff;color:#0f172a;">';
    $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#eaf5ff;padding:24px;"><tr><td align="center">';
    $html .= '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:24px;overflow:hidden;border:1px solid #dbeafe;">';
    $html .= '<tr><td style="padding:28px 32px;background:#0ea5e9;color:#fff;text-align:center;">';
    if (file_exists($logoPath)) {
        $html .= '<img src="cid:company_logo" alt="Company Logo" width="96" style="display:block;margin:0 auto 18px;">';
    }
    $html .= '<h1 style="margin:0;font-size:26px;">Inua Premium Services</h1>';
    $html .= '<p style="margin:10px 0 0;font-size:15px;color:#dbeafe;">' . $escape($subject) . '</p></td></tr>';
    $html .= '<tr><td style="padding:24px 32px 28px;font-size:14px;line-height:1.6;color:#475569;">';
    $html .= $body;

    if ($advanceDetails) {
        $html .= '<h3 style="margin:18px 0 8px;color:#0f172a;">Advance Details</h3>';
        $html .= '<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;">';
        $html .= '<tr style="background:#f1f5f9;"><td><strong>Advance Amount</strong></td><td align="right">KES ' . number_format((float) ($advanceDetails['amount'] ?? 0), 2) . '</td></tr>';
        $html .= '<tr><td><strong>Current Balance</strong></td><td align="right">KES ' . number_format((float) ($advanceDetails['balance'] ?? 0), 2) . '</td></tr>';
        $html .= '<tr style="background:#f8fafc;"><td><strong>Monthly Deduction</strong></td><td align="right">KES ' . number_format((float) ($advanceDetails['monthly_deduction'] ?? 0), 2) . '</td></tr>';
        $html .= '<tr><td><strong>Booking Date</strong></td><td align="right">' . $escape(!empty($advanceDetails['booking_date']) ? date('d-m-Y', strtotime($advanceDetails['booking_date'])) : '') . '</td></tr>';
        $html .= '<tr><td><strong>Schedule Start Date</strong></td><td align="right">' . $escape(!empty($advanceDetails['start_date']) ? date('d-m-Y', strtotime($advanceDetails['start_date'])) : '') . '</td></tr>';
        $html .= '</table>';
    }

    if ($scheduleRows) {
        $html .= '<h3 style="margin:18px 0 8px;color:#0f172a;">Repayment Schedule</h3>';
        $html .= '<table width="100%" cellpadding="7" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;font-size:13px;">';
        $html .= '<tr style="background:#f1f5f9;"><th align="left">Installment</th><th align="right">Amount Due</th><th align="right">Amount Paid</th><th align="right">Balance</th><th align="right">Due Date</th></tr>';
        foreach ($scheduleRows as $index => $row) {
            $html .= '<tr style="background:' . ($index % 2 === 0 ? '#ffffff' : '#f8fafc') . ';">';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td align="right">KES ' . number_format((float) ($row['amount_due'] ?? 0), 2) . '</td>';
            $html .= '<td align="right">KES ' . number_format((float) ($row['paid'] ?? 0), 2) . '</td>';
            $html .= '<td align="right">KES ' . number_format((float) ($row['balance'] ?? 0), 2) . '</td>';
            $html .= '<td align="right">' . $escape($row['repayment_date'] ?? '') . '</td></tr>';
        }
        $html .= '</table>';
    }

    $html .= '<p style="margin:18px 0 0;font-size:12px;color:#64748b;">This is an automated advance notification from Inua Premium Services.</p>';
    $html .= '</td></tr></table></td></tr></table></body></html>';

    return $html;
}

function send_advance_email($recipient_email, $subject, $body, $advanceDetails = [], $scheduleRows = []) {
    try {
        $emailCredentials = getPayrollEmailAccount();
        if (empty($emailCredentials['sender_email']) || empty($emailCredentials['sender_app_password'])) {
            throw new Exception('Manager payroll email settings are not configured. Configure the sender email and app password in Payroll Settings.');
        }
        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('The loan officer does not have a valid email address.');
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
        $smtpSettings = getPayrollSmtpSettings($emailCredentials['sender_email']);
        $mail->Host = $smtpSettings['host'];
        $mail->Port = $smtpSettings['port'];
        $mail->SMTPSecure = $smtpSettings['encryption'];
        $mail->SMTPAutoTLS = true;
        $mail->SMTPAuth = true;
        $mail->Username = $emailCredentials['sender_email'];
        $mail->Password = $emailCredentials['sender_app_password'];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($emailCredentials['sender_email'], 'Inua Premium Services');
        $mail->addReplyTo($emailCredentials['sender_email'], 'Inua Premium Services');
        $mail->addAddress($recipient_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $logoPath = __DIR__ . '/../assets/img/logo.png';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'company_logo');
        }
        $mail->Body = build_advance_email_body($subject, $body, $advanceDetails, $scheduleRows);
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $body));
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}

// Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Book advance
    if (isset($_POST['book_advance'])) {
        $loan_officer_id = (int) $_POST['loan_officer_id'];
        $amount = round((float) $_POST['amount'], 2);
        $monthly = round((float) $_POST['monthly_deduction'], 2);
        $booking_date = trim((string) ($_POST['booking_date'] ?? ''));
        $bookingDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $booking_date);
        $bookingDateErrors = DateTimeImmutable::getLastErrors();
        if (
            !$bookingDateObject
            || ($bookingDateErrors !== false && ($bookingDateErrors['warning_count'] > 0 || $bookingDateErrors['error_count'] > 0))
            || $bookingDateObject->format('Y-m-d') !== $booking_date
        ) {
            $messages[] = 'Please select a valid advance booking date.';
        } else {
            if ($amount > 0 && $monthly > 0) {
                $duration = max(1, min(36, (int) ceil($amount / $monthly)));
            } else {
                $duration = 1;
            }
            $start_date = $_POST['start_date'];
            $recorded_by = $_SESSION['user_id'] ?? null;

        // get officer name and email
        $res = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
        $res->bind_param('i', $loan_officer_id);
        $res->execute();
        $res->bind_result($loan_officer_name, $loan_officer_email);
        $res->fetch();
        $res->close();

        if (empty($loan_officer_name)) {
            $messages[] = 'Loan officer not found.';
        } else {
            if ($monthly <= 0) {
                $monthly = $amount;
                $duration = 1;
            }
            $balance = $amount;
            $stmt = $conn->prepare("INSERT INTO advances (loan_officer_id, loan_officer_name, amount, balance, start_date, booking_date, monthly_deduction, duration_months, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isddssdii', $loan_officer_id, $loan_officer_name, $amount, $balance, $start_date, $booking_date, $monthly, $duration, $recorded_by);
            if ($stmt->execute()) {
                $advance_id = $conn->insert_id;
                $messages[] = 'Advance booked successfully.';

                // Generate repayment schedule
                $installment = $monthly;
                $last_amount = round($amount - $monthly * ($duration - 1), 2);
                $scheduleRows = [];
                $runningBalance = $amount;
                $scheduleStmt = $conn->prepare("INSERT INTO advance_repayment_schedule (advance_id, due_amount, due_date, status) VALUES (?, ?, ?, 'pending')");
                for ($i = 0; $i < $duration; $i++) {
                    $due_amount = ($i === $duration - 1) ? $last_amount : $installment;
                    $due_date_str = get_advance_schedule_date($start_date, $i);
                    $scheduleStmt->bind_param('ids', $advance_id, $due_amount, $due_date_str);
                    $scheduleStmt->execute();
                    $runningBalance = max(0, round($runningBalance - $due_amount, 2));
                    $scheduleRows[] = [
                        'amount_due' => $due_amount,
                        'paid' => 0.00,
                        'balance' => $runningBalance,
                        'repayment_date' => date('d-m-Y', strtotime($due_date_str))
                    ];
                }
                $scheduleStmt->close();

                // Send schedule by email
                if (!empty($loan_officer_email)) {
                    $subject = 'Advance Repayment Schedule';
                    $body = 'Dear ' . htmlspecialchars($loan_officer_name) . ',<br><br>Please find your advance repayment schedule below.';
                    $advanceDetails = [
                        'amount' => $amount,
                        'balance' => $balance,
                        'monthly_deduction' => $monthly,
                        'start_date' => $start_date,
                        'booking_date' => $booking_date
                    ];
                    if (send_advance_email($loan_officer_email, $subject, $body, $advanceDetails, $scheduleRows)) {
                        $messages[] = 'Schedule sent to loan officer email.';
                    } else {
                        $messages[] = 'Advance booked, but email send failed.';
                    }
                } else {
                    $messages[] = 'Advance booked; loan officer has no email address.';
                }
            } else {
                $messages[] = 'Error booking advance: ' . $conn->error;
            }
            $stmt->close();
        }
        }
    }

    // Update advance
    if (isset($_POST['update_advance']) && !empty($_POST['edit_advance_id'])) {
        $advance_id = (int) $_POST['edit_advance_id'];
        $loan_officer_id = (int) $_POST['loan_officer_id'];
        $amount = round((float) $_POST['amount'], 2);
        $monthly = round((float) $_POST['monthly_deduction'], 2);
        $duration = ($amount > 0 && $monthly > 0)
            ? max(1, min(36, (int) ceil($amount / $monthly)))
            : 1;
        $start_date = $_POST['start_date'];
        $recorded_by = $_SESSION['user_id'] ?? null;

        $res = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
        $res->bind_param('i', $loan_officer_id);
        $res->execute();
        $res->bind_result($loan_officer_name);
        $res->fetch();
        $res->close();

        if (empty($loan_officer_name)) {
            $messages[] = 'Loan officer not found.';
        } else {
            $paidQ = $conn->prepare("SELECT IFNULL(SUM(amount), 0) FROM advance_repayments WHERE advance_id = ?");
            $paidQ->bind_param('i', $advance_id);
            $paidQ->execute();
            $paidQ->bind_result($total_paid);
            $paidQ->fetch();
            $paidQ->close();

            $balance = max(0, $amount - $total_paid);
            $upd = $conn->prepare("UPDATE advances SET loan_officer_id = ?, loan_officer_name = ?, amount = ?, balance = ?, start_date = ?, monthly_deduction = ?, duration_months = ?, recorded_by = ? WHERE id = ?");
            $upd->bind_param('isddsdiii', $loan_officer_id, $loan_officer_name, $amount, $balance, $start_date, $monthly, $duration, $recorded_by, $advance_id);
            if ($upd->execute()) {
                $messages[] = 'Advance updated successfully.';
                $del = $conn->prepare("DELETE FROM advance_repayment_schedule WHERE advance_id = ?");
                $del->bind_param('i', $advance_id);
                $del->execute();
                $del->close();

                $remaining = max(0, $amount - $total_paid);
                if ($remaining > 0) {
                    $installment = $monthly;
                    if ($installment <= 0 || $installment * $duration > $remaining) {
                        $installment = round($remaining / $duration, 2);
                    }
                    $last_amount = round($remaining - $installment * ($duration - 1), 2);
                    $scheduleStmt = $conn->prepare("INSERT INTO advance_repayment_schedule (advance_id, due_amount, due_date, status) VALUES (?, ?, ?, 'pending')");
                    for ($i = 0; $i < $duration; $i++) {
                        $due_amount = ($i === $duration - 1) ? $last_amount : $installment;
                        $due_date_str = get_advance_schedule_date($start_date, $i);
                        $scheduleStmt->bind_param('ids', $advance_id, $due_amount, $due_date_str);
                        $scheduleStmt->execute();
                    }
                    $scheduleStmt->close();
                }
            } else {
                $messages[] = 'Error updating advance: ' . $conn->error;
            }
            $upd->close();
        }
    }

    // Add repayment
    if (isset($_POST['add_repayment'])) {
        $advance_id = (int) $_POST['advance_id'];
        $rep_amount = round((float) $_POST['repayment_amount'], 2);
        $payment_date = $_POST['payment_date'];
        $recorded_by = $_SESSION['user_id'] ?? null;

        $stmt = $conn->prepare("INSERT INTO advance_repayments (advance_id, amount, payment_date, recorded_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('idsi', $advance_id, $rep_amount, $payment_date, $recorded_by);
        if ($stmt->execute()) {
            $upd = $conn->prepare("UPDATE advances SET balance = balance - ? WHERE id = ?");
            $upd->bind_param('di', $rep_amount, $advance_id);
            $upd->execute();
            $upd->close();

            // get loan officer details and current balance
            $q = $conn->prepare("SELECT a.loan_officer_name, u.email, a.amount, a.balance, a.monthly_deduction, a.duration_months, a.start_date FROM advances a LEFT JOIN users u ON a.loan_officer_id = u.id WHERE a.id = ? LIMIT 1");
            $q->bind_param('i', $advance_id);
            $q->execute();
            $q->bind_result($loan_officer_name, $loan_officer_email, $advance_amount, $advance_balance, $advance_monthly_deduction, $advance_duration, $advance_start_date);
            $q->fetch();
            $q->close();

            if (!empty($loan_officer_email)) {
                $scheduleRows = [];
                $scheduleRows[] = [
                    'amount_due' => $rep_amount,
                    'paid' => $rep_amount,
                    'balance' => $advance_balance,
                    'repayment_date' => date('d-m-Y', strtotime($payment_date))
                ];

                $runningBalance = (float) $advance_balance;
                $scheduleQuery = $conn->prepare("SELECT due_amount, due_date FROM advance_repayment_schedule WHERE advance_id = ? ORDER BY due_date ASC");
                $scheduleQuery->bind_param('i', $advance_id);
                $scheduleQuery->execute();
                $scheduleResult = $scheduleQuery->get_result();
                while ($row = $scheduleResult->fetch_assoc()) {
                    $scheduleRows[] = [
                        'amount_due' => $row['due_amount'],
                        'paid' => 0.00,
                        'balance' => max(0, $runningBalance - $row['due_amount']),
                        'repayment_date' => date('d-m-Y', strtotime($row['due_date']))
                    ];
                    $runningBalance = max(0, round($runningBalance - (float) $row['due_amount'], 2));
                }
                $scheduleQuery->close();

                $subject = 'Advance Payment Applied';
                $body = 'Dear ' . htmlspecialchars($loan_officer_name) . ',<br><br>Your repayment of KES ' . number_format($rep_amount, 2) . ' has been recorded. Your current balance is KES ' . number_format($advance_balance, 2) . '. Please find the updated schedule below.';
                $advanceDetails = [
                    'amount' => $advance_amount,
                    'balance' => $advance_balance,
                    'monthly_deduction' => $advance_monthly_deduction,
                    'start_date' => $advance_start_date
                ];
                if (send_advance_email($loan_officer_email, $subject, $body, $advanceDetails, $scheduleRows)) {
                    $messages[] = 'Repayment added and email sent to loan officer.';
                } else {
                    $messages[] = 'Repayment added, but email send failed.';
                }
            } else {
                $messages[] = 'Repayment added; no loan officer email available.';
            }
        } else {
            $messages[] = 'Error adding repayment: ' . $conn->error;
        }
        $stmt->close();
    }

    // Apply monthly deduction
    if (isset($_POST['apply_deduction'])) {
        $advance_id = (int) $_POST['advance_id'];
        $q = $conn->prepare("SELECT monthly_deduction, loan_officer_id, loan_officer_name, amount, balance, duration_months, start_date FROM advances WHERE id = ? LIMIT 1");
        $q->bind_param('i', $advance_id);
        $q->execute();
        $q->bind_result($monthly_deduction, $loan_officer_id, $loan_officer_name, $advance_amount, $advance_balance, $advance_duration, $advance_start_date);
        $q->fetch();
        $q->close();

        if ($monthly_deduction > 0) {
            $payment_date = date('Y-m-d');
            $recorded_by = $_SESSION['user_id'] ?? null;
            $stmt = $conn->prepare("INSERT INTO advance_repayments (advance_id, amount, payment_date, recorded_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('idsi', $advance_id, $monthly_deduction, $payment_date, $recorded_by);
            if ($stmt->execute()) {
                $upd = $conn->prepare("UPDATE advances SET balance = balance - ? WHERE id = ?");
                $upd->bind_param('di', $monthly_deduction, $advance_id);
                $upd->execute();
                $upd->close();

                $balanceQuery = $conn->prepare("SELECT a.balance, u.email FROM advances a LEFT JOIN users u ON a.loan_officer_id = u.id WHERE a.id = ? LIMIT 1");
                $balanceQuery->bind_param('i', $advance_id);
                $balanceQuery->execute();
                $balanceQuery->bind_result($advance_balance, $loan_officer_email);
                $balanceQuery->fetch();
                $balanceQuery->close();

                if (!empty($loan_officer_email)) {
                    $scheduleRows = [];
                    $scheduleRows[] = [
                        'amount_due' => $monthly_deduction,
                        'paid' => $monthly_deduction,
                        'balance' => $advance_balance,
                        'repayment_date' => date('d-m-Y', strtotime($payment_date))
                    ];

                    $runningBalance = $advance_balance;
                    $scheduleQuery = $conn->prepare("SELECT due_amount, due_date FROM advance_repayment_schedule WHERE advance_id = ? ORDER BY due_date ASC");
                    $scheduleQuery->bind_param('i', $advance_id);
                    $scheduleQuery->execute();
                    $scheduleResult = $scheduleQuery->get_result();
                    while ($row = $scheduleResult->fetch_assoc()) {
                        $scheduleRows[] = [
                            'amount_due' => $row['due_amount'],
                            'paid' => 0.00,
                            'balance' => max(0, $runningBalance - $row['due_amount']),
                            'repayment_date' => date('d-m-Y', strtotime($row['due_date']))
                        ];
                        $runningBalance = max(0, round($runningBalance - (float) $row['due_amount'], 2));
                    }
                    $scheduleQuery->close();

                    $subject = 'Monthly Advance Deduction Applied';
                    $body = 'Dear ' . htmlspecialchars($loan_officer_name) . ',<br><br>Your monthly deduction of KES ' . number_format($monthly_deduction, 2) . ' has been applied. Your current balance is KES ' . number_format($advance_balance, 2) . '. Please find the updated schedule below.';
                    $advanceDetails = [
                        'amount' => $advance_amount,
                        'balance' => $advance_balance,
                        'monthly_deduction' => $monthly_deduction,
                        'start_date' => $advance_start_date
                    ];
                    if (send_advance_email($loan_officer_email, $subject, $body, $advanceDetails, $scheduleRows)) {
                        $messages[] = 'Monthly deduction applied and email sent to loan officer.';
                    } else {
                        $messages[] = 'Monthly deduction applied, but email send failed.';
                    }
                } else {
                    $messages[] = 'Monthly deduction applied; no loan officer email available.';
                }
            } else {
                $messages[] = 'Error applying deduction: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $messages[] = 'Monthly deduction is zero for this advance.';
        }
    }

    // Delete advance
    if (isset($_POST['delete_advance'])) {
        $aid = (int) $_POST['advance_id'];
        $stmt = $conn->prepare("DELETE FROM advance_repayments WHERE advance_id = ?");
        $stmt->bind_param('i', $aid); $stmt->execute(); $stmt->close();
        $stmt = $conn->prepare("DELETE FROM advances WHERE id = ?");
        $stmt->bind_param('i', $aid);
        if ($stmt->execute()) $messages[] = 'Advance deleted.'; else $messages[] = 'Error deleting advance: '.$conn->error;
        $stmt->close();
    }
}

// Fetch loan officers
$loan_officers = [];
$lor = $conn->query("SELECT id, name FROM users WHERE role_id = 2");
if ($lor) {
    while ($row = $lor->fetch_assoc()) $loan_officers[] = $row;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Advance Management</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Advance Bookings</h1>
        <div>
            <a href="view_Advance.php" class="btn btn-info btn-sm">View Booked Advances</a>
            <a href="./index.php" class="btn btn-secondary btn-sm">Back to Home</a>
        </div>
    </div>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($m); ?></div>
    <?php endforeach; ?>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Book Advance to Loan Officer</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Loan Officer</label>
                            <select id="loan_officer_id" name="loan_officer_id" class="form-select" required>
                                <?php foreach ($loan_officers as $lo): ?>
                                    <option value="<?php echo $lo['id']; ?>"><?php echo htmlspecialchars($lo['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Advance Amount</label>
                            <input id="amount" name="amount" class="form-control" type="number" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Booking Date</label>
                            <input id="booking_date" name="booking_date" class="form-control" type="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Monthly Deduction</label>
                            <input name="monthly_deduction" id="monthly_deduction" class="form-control" type="number" step="0.01" value="0.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration (months)</label>
                            <input id="duration_months" name="duration_months" class="form-control" type="number" min="1" max="36" value="1" readonly required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input id="start_date" name="start_date" class="form-control" type="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <input type="hidden" name="edit_advance_id" id="edit_advance_id" value="">
                        <div class="col-12 text-end">
                            <button class="btn btn-success" type="submit" name="book_advance" id="book_advance_btn">Book Advance</button>
                            <button class="btn btn-warning d-none" type="submit" name="update_advance" id="update_advance_btn">Update Advance</button>
                            <button type="button" class="btn btn-secondary d-none" id="cancel_edit_btn" onclick="cancelEdit()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function updateDuration() {
    var amount = parseFloat(document.getElementById('amount').value) || 0;
    var monthly = parseFloat(document.getElementById('monthly_deduction').value) || 0;
    var duration = amount > 0 && monthly > 0 ? Math.ceil(amount / monthly) : 1;
    document.getElementById('duration_months').value = Math.min(36, Math.max(1, duration));
}

function editAdvance(id, loanOfficerId, amount, monthly, duration, startDate) {
    document.getElementById('edit_advance_id').value = id;
    document.getElementById('loan_officer_id').value = loanOfficerId;
    document.getElementById('amount').value = amount;
    document.getElementById('monthly_deduction').value = monthly;
    updateDuration();
    document.getElementById('start_date').value = startDate;
    document.getElementById('book_advance_btn').classList.add('d-none');
    document.getElementById('update_advance_btn').classList.remove('d-none');
    document.getElementById('cancel_edit_btn').classList.remove('d-none');
}
function cancelEdit() {
    document.getElementById('edit_advance_id').value = '';
    document.getElementById('loan_officer_id').selectedIndex = 0;
    document.getElementById('amount').value = '';
    document.getElementById('monthly_deduction').value = '0.00';
    document.getElementById('duration_months').value = '1';
    document.getElementById('start_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('book_advance_btn').classList.remove('d-none');
    document.getElementById('update_advance_btn').classList.add('d-none');
    document.getElementById('cancel_edit_btn').classList.add('d-none');
}
    document.getElementById('amount').addEventListener('input', updateDuration);
    document.getElementById('monthly_deduction').addEventListener('input', updateDuration);
</script>

</body>
</html>
