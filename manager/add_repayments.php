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

function formatLoanDurationDisplay($duration, $unit) {
    $duration = (int) $duration;
    $unit = strtolower(trim((string) $unit));

    if ($duration === 1) {
        $unit = rtrim($unit, 's');
    } elseif ($unit !== '' && !str_ends_with($unit, 's')) {
        $unit .= 's';
    }

    return $duration > 0 && $unit !== '' ? $duration . ' ' . ucfirst($unit) : '';
}

function normalizeDurationUnit($loan_duration_unit) {
    $unit = strtolower(trim((string) ($loan_duration_unit ?? 'months')));

    switch ($unit) {
        case 'day':
        case 'days':
            return 'days';
        case 'week':
        case 'weeks':
            return 'weeks';
        case 'month':
        case 'months':
            return 'months';
        case 'year':
        case 'years':
            return 'years';
        default:
            return 'months';
    }
}

function getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit) {
    $loan_duration = (int) $loan_duration;
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);

    if (empty($loan_release_date) || $loan_duration <= 0) {
        return null;
    }

    try {
        $maturity_date = new DateTime($loan_release_date);
        switch ($loan_duration_unit) {
            case 'days':
                $interval = new DateInterval('P' . $loan_duration . 'D');
                break;
            case 'weeks':
                $interval = new DateInterval('P' . $loan_duration . 'W');
                break;
            case 'months':
                $interval = new DateInterval('P' . $loan_duration . 'M');
                break;
            case 'years':
                $interval = new DateInterval('P' . $loan_duration . 'Y');
                break;
            default:
                $interval = new DateInterval('P' . $loan_duration . 'M');
                break;
        }

        return $maturity_date->add($interval);
    } catch (Exception $e) {
        return null;
    }
}

function getCycleInterval($cycle) {
    switch (strtolower(trim((string) $cycle))) {
        case 'daily':
            return '1 day';
        case 'weekly':
            return '1 week';
        case 'monthly':
            return '1 month';
        case 'yearly':
        case 'year':
            return '1 year';
        case 'once':
            return '1 month';
        default:
            return '1 month';
    }
}

function getRepaymentScheduleDates($loan_release_date, $loan_duration, $loan_duration_unit, $repayment_cycle) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    $maturity_date = getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit);
    if (!$maturity_date) {
        return [];
    }

    $scheduleDates = [];
    $repayment_cycle = strtolower(trim((string) ($repayment_cycle ?? 'once')));

    if ($repayment_cycle === 'once') {
        $scheduleCycle = match ($loan_duration_unit) {
            'days', 'weeks' => 'weekly',
            'months' => 'monthly',
            'years' => 'yearly',
            default => 'monthly'
        };
    } else {
        $scheduleCycle = $repayment_cycle;
    }

    $current = new DateTime($loan_release_date);
    $interval = DateInterval::createFromDateString(getCycleInterval($scheduleCycle));

    while (true) {
        $next = clone $current;
        $next->add($interval);

        if ($next >= $maturity_date) {
            $scheduleDates[] = $maturity_date->format('Y-m-d');
            break;
        }

        $scheduleDates[] = $next->format('Y-m-d');
        $current = $next;
    }

    return $scheduleDates;
}

function getProjectedMaturityDate($releaseDate, $loan_duration, $loan_duration_unit, $repayment_cycle = null) {
    if (empty($releaseDate) || (int) $loan_duration <= 0) {
        return null;
    }

    $dates = getRepaymentScheduleDates($releaseDate, (int) $loan_duration, $loan_duration_unit, $repayment_cycle ?? 'once');
    if (empty($dates)) {
        return null;
    }

    $last = end($dates);
    try {
        return new DateTime($last);
    } catch (Exception $e) {
        return null;
    }
}

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

/**
 * Send a single combined email to the loan officer containing:
 * - payment received notification and portfolio metrics
 * - payslip-style payment record with recent payments and loan terms
 */
function sendCombinedStaffNotification($loan_id, $amount_paid, $conn) {
    $details = getLoanNotificationDetails($loan_id, $conn);
    if (!$details) return "<div class='alert alert-warning text-center'>Payment saved but loan/staff details not found for notification.</div>";

    $officerEmail = trim($details['officer_email'] ?? '');
    $officerName = !empty($details['loan_officer_name']) ? $details['loan_officer_name'] : $officerEmail;
    $senderEmail = getConfiguredSenderEmail();
    if (empty($officerEmail) || !filter_var($officerEmail, FILTER_VALIDATE_EMAIL)) {
        $officerEmail = $senderEmail;
    }

    if (empty($officerEmail) || !filter_var($officerEmail, FILTER_VALIDATE_EMAIL)) {
        return "<div class='alert alert-warning text-center'>Payment saved but no valid loan officer email exists for notification.</div>";
    }

    $loanSummary = getLoanBalanceSummary($loan_id, $conn);
    $outstanding = number_format($loanSummary['outstanding_balance'] ?? 0, 2);
    $overdue = number_format($loanSummary['overdue_balance'] ?? 0, 2);

    $portfolioMetrics = getLoanOfficerPortfolioMetrics($officerEmail, $conn);
    $totalLoanBook = number_format($portfolioMetrics['loan_book'] ?? 0, 2);
    $totalArrears = number_format($portfolioMetrics['overdue_balance'] ?? 0, 2);
    $totalClients = intval($portfolioMetrics['total_clients'] ?? 0);
    $clientsInArrears = intval($portfolioMetrics['clients_in_arrears'] ?? 0);
    $todayDuesValue = getLoanOfficerTodayDues($officerEmail, $conn);
    $todayDues = number_format($todayDuesValue, 2);
    $parPercentage = number_format($portfolioMetrics['par'] ?? 0, 2);

    // Recent payments
    $payments = [];
    $ps = $conn->prepare("SELECT id, PaymentDate, Amount FROM payment_date_records WHERE loan_id = ? ORDER BY PaymentDate ASC, id ASC");
    if ($ps) {
        $ps->bind_param('i', $loan_id);
        $ps->execute();
        $payments = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
        $ps->close();
    }

    // Loan terms (best-effort)
    $loanTerms = getLoanTermsForEmail($loan_id, $conn);

    $emailCredentials = getEmailAccount();
    if (!$emailCredentials || empty($emailCredentials['sender_email']) || empty($emailCredentials['sender_app_password'])) {
        return "<div class='alert alert-warning text-center'>Payment saved but notification email was not sent because email settings are not configured.</div>";
    }

    $logoPath = __DIR__ . '/../assets/img/logo.png';

    // Build combined HTML
    $subject = 'Payment Received for ' . ($details['borrower_name'] ?? 'client');
    $greet = !empty($officerName) ? $officerName : 'Team';

    $body = '<html><body style="margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#eaf5ff;color:#0f172a;">';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#eaf5ff;padding:24px;">';
    $body .= '<tr><td align="center">';
    $body .= '<table width="700" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(14,88,161,0.12);border:1px solid #dbeafe;">';
    $body .= '<tr><td style="padding:20px 24px;background:#0ea5e9;color:#ffffff;text-align:center;">';
    if (file_exists($logoPath)) $body .= '<img src="cid:company_logo" alt="Company Logo" width="80" style="display:block;margin:0 auto 12px;">';
    $body .= '<h2 style="margin:0;font-size:20px;">Inua Premium Services</h2>';
    $body .= '<p style="margin:6px 0 0;font-size:13px;color:#dbeafe;">Payment Notification & Record</p>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding:18px 24px;">';
    $body .= '<p style="margin:0 0 8px;">Dear ' . htmlspecialchars($greet) . ',</p>';
    $body .= '<p style="margin:0 0 12px;">The client <strong>' . htmlspecialchars($details['borrower_name'] ?? '') . '</strong> has paid <strong>KES ' . number_format($amount_paid, 2) . '</strong>.</p>';

    // Loan terms if available
    if (!empty($loanTerms)) {
        $prod = htmlspecialchars($loanTerms['loan_product_name'] ?? $loanTerms['loan_product'] ?? '');
        $principal = isset($loanTerms['principal']) ? number_format((float)$loanTerms['principal'], 2) : '';
        $totalAmt = isset($loanTerms['total_amount']) ? number_format((float)$loanTerms['total_amount'], 2) : '';
        $numRepay = isset($loanTerms['number_of_repayments']) ? intval($loanTerms['number_of_repayments']) : 0;
        $duration = isset($loanTerms['loan_duration']) ? intval($loanTerms['loan_duration']) : '';
        $durationUnit = htmlspecialchars($loanTerms['loan_duration_unit'] ?? '');
        $release = !empty($loanTerms['loan_release_date']) ? htmlspecialchars(date('d/m/Y', strtotime($loanTerms['loan_release_date']))) : '';
        $maturity = !empty($loanTerms['projected_maturity_date']) ? htmlspecialchars(date('d/m/Y', strtotime($loanTerms['projected_maturity_date']))) : '';
        $status = htmlspecialchars($loanTerms['loan_status'] ?? '');

        // Calculate total paid from actual payment records
        $totalPaidAmount = 0;
        foreach ($payments as $p) {
            $totalPaidAmount += (float)$p['Amount'];
        }
        $totalPaid = $totalPaidAmount > 0 ? number_format($totalPaidAmount, 2) : '';
        $totalBalance = number_format(max(0, (float)($loanTerms['total_amount'] ?? 0) - $totalPaidAmount), 2);
        $installmentAmount = ($numRepay > 0) ? number_format((float)($loanTerms['total_amount'] ?? 0) / $numRepay, 2) : '';

        $body .= '<h4 style="margin:12px 0 8px;font-size:15px;color:#0f172a;">Loan Terms</h4>';
        $body .= '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;">';
        $body .= '<tr style="background:#f8fafc;color:#0f172a;font-weight:700;"><td><strong>Product</strong></td><td align="right"><strong>' . $prod . '</strong></td></tr>';
        if ($principal) {
            $body .= '<tr><td>Principal Amount</td><td align="right">KES ' . $principal . '</td></tr>';
        }
        $body .= '<tr><td>Total Amount</td><td align="right">KES ' . $totalAmt . '</td></tr>';
        if ($installmentAmount) {
            $body .= '<tr><td>Installment Amount</td><td align="right">KES ' . $installmentAmount . '</td></tr>';
        }
        if ($totalPaid) {
            $body .= '<tr><td>Total Paid</td><td align="right">KES ' . $totalPaid . '</td></tr>';
        }
        $body .= '<tr><td>Total Balance</td><td align="right">KES ' . $totalBalance . '</td></tr>';
        if ($status) {
            $statusDisplay = ucfirst($status);
            $body .= '<tr><td>Status</td><td align="right"><strong>' . $statusDisplay . '</strong></td></tr>';
        }
        $body .= '</table>';

        $summaryBlock = [];
        if ($release) {
            $summaryBlock[] = '<p style="margin:0 0 6px;font-size:14px;color:#0f172a;"><strong>Release Date:</strong> ' . htmlspecialchars($release) . '</p>';
        }
        if ($maturity) {
            $summaryBlock[] = '<p style="margin:0;font-size:14px;color:#0f172a;"><strong>Maturity Date:</strong> ' . htmlspecialchars($maturity) . '</p>';
        }

        if (!empty($summaryBlock)) {
            $body .= '<div style="margin-top:18px;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;">';
            $body .= implode('', $summaryBlock);
            $body .= '</div>';
        }

        // Fetch the assigned loan officer's portfolio metrics for footer
        $officerDetails = getLoanNotificationDetails($loan_id, $conn);
        $officerEmail = trim($officerDetails['officer_email'] ?? '');
        $portfolioMetrics = getLoanOfficerPortfolioMetrics($officerEmail, $conn);
        $lb = number_format($portfolioMetrics['loan_book'] ?? 0, 2);
        $par = number_format($portfolioMetrics['par'] ?? 0, 2);
        $noc = intval($portfolioMetrics['total_clients'] ?? 0);
        $nocA = intval($portfolioMetrics['clients_in_arrears'] ?? 0);
    }

    // Recent payments table
    if (!empty($payments)) {
        $body .= '<h4 style="margin:12px 0 8px;font-size:15px;color:#0f172a;">Recent Payments</h4>';
        $body .= '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;">';
        $body .= '<tr style="background:#f1f5f9;color:#0f172a;font-weight:700;"><th align="left">Date</th><th align="right">Amount (KES)</th></tr>';
        foreach ($payments as $p) {
            $d = htmlspecialchars(date('d/m/Y', strtotime($p['PaymentDate'])));
            $a = number_format((float)$p['Amount'], 2);
            $body .= '<tr><td>' . $d . '</td><td align="right">' . $a . '</td></tr>';
        }
        $body .= '</table>';
    }

    if (empty($footerMetricsAppended)) {
        $body .= '<div style="margin-top:12px;padding:8px 10px;background:#f0f4f8;border:1px solid #e2e8f0;border-radius:8px;font-style:italic;font-size:13px;color:#475569;">LB: KES ' . $lb . ' | PAR: ' . $par . '% | NoC: ' . $noc . ' | NoC-A: ' . $nocA . '</div>';
        $body .= '<p style="margin:12px 0 0;font-size:14px;color:#0f172a;">Thank you,<br>' . htmlspecialchars(!empty($officerName) ? $officerName : ($officerDetails['loan_officer_name'] ?? $officerEmail)) . (!empty($officerEmail) ? '<br>' . htmlspecialchars($officerEmail) : '') . '</p>';
        $footerMetricsAppended = true;
    }
    $body .= '</td></tr></table></td></tr></table></body></html>';

    // send
    try {
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
        $mail->SMTPAuth = true;
        $mail->Username = $emailCredentials['sender_email'];
        $mail->Password = $emailCredentials['sender_app_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($emailCredentials['sender_email'], 'Inua Premium Services');
        $mail->addAddress($officerEmail, $officerName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        if (file_exists($logoPath)) $mail->addEmbeddedImage($logoPath, 'company_logo');
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], "\n", $body));
        $mail->send();
        return "<div class='alert alert-success text-center'>Combined payment notification & record sent to " . htmlspecialchars($officerEmail) . "</div>";
    } catch (Exception $e) {
        return "<div class='alert alert-warning text-center'>Payment saved but failed sending combined notification to staff: " . htmlspecialchars($e->getMessage()) . "</div>";
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
        $amount_paid = floatval($amount_paid); // Ensure proper numeric type
        $message = distributeRepayment($loan_id, $amount_paid, $conn, $payment_date);

        $insertPayment = "INSERT INTO payment_date_records (loan_id, PaymentDate, Amount) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insertPayment);
        if (!$insert_stmt) {
            $message .= "<div class='alert alert-danger text-center'>Error preparing payment record: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            $insert_stmt->bind_param("isd", $loan_id, $payment_date, $amount_paid);
            if (!$insert_stmt->execute()) {
                $message .= "<div class='alert alert-danger text-center'>Error saving payment record: " . htmlspecialchars($insert_stmt->error) . "</div>";
            } else {
                $message .= "<div class='alert alert-info text-center'>Payment record saved successfully.</div>";
            }
            $insert_stmt->close();
        }

            // Send a single combined email to the loan officer that includes
            // the payment-received notification + payslip-style payment record
            $message .= sendCombinedStaffNotification($loan_id, $amount_paid, $conn);
            // Also send payment receipt to the client (borrower) formatted like payslip
            $message .= sendPaymentRecordToClient($loan_id, $amount_paid, $conn);
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
        $amount = floatval($amount); // Ensure proper numeric type
        $msg = distributeRepayment($loan_id, $amount, $conn, $payment_date);
        $bulk_messages[] = $msg;

        // Record payment date entry
        $ins = $conn->prepare("INSERT INTO payment_date_records (loan_id, PaymentDate, Amount) VALUES (?, ?, ?)");
        if (!$ins) {
            $bulk_messages[] = "<div class='alert alert-danger'>Error preparing payment record for " . htmlspecialchars($mobile) . ": " . htmlspecialchars($conn->error) . "</div>";
        } else {
            $ins->bind_param("isd", $loan_id, $payment_date, $amount);
            if (!$ins->execute()) {
                $bulk_messages[] = "<div class='alert alert-danger'>Error saving payment record for " . htmlspecialchars($mobile) . ": " . htmlspecialchars($ins->error) . "</div>";
            } else {
                $bulk_messages[] = "<div class='alert alert-info'>Payment record saved for " . htmlspecialchars($mobile) . ".</div>";
            }
            $ins->close();
        }

        // Send combined notification+record to loan officer
        $bulk_messages[] = sendCombinedStaffNotification($loan_id, $amount, $conn);
        // Also send client receipt email
        $bulk_messages[] = sendPaymentRecordToClient($loan_id, $amount, $conn);
    }

    $message = implode('', $bulk_messages);
}

// Function to distribute repayment
function distributeRepayment($loan_id, $amount_paid, $conn, $payment_date) {
    $sql = "SELECT * FROM repayments WHERE loan_id = ? AND COALESCE(paid, 0) < amount ORDER BY repayment_date ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return "<div class='alert alert-danger text-center'>Error preparing repayment query: " . htmlspecialchars($conn->error) . "</div>";
    }
    
    $stmt->bind_param("i", $loan_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return "<div class='alert alert-danger text-center'>Error executing repayment query: " . htmlspecialchars($stmt->error) . "</div>";
    }
    
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
        if ($update_stmt) {
            $update_stmt->bind_param("dsi", $new_amount_paid, $payment_date, $installment_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        $total_distributed += $applied_amount;
    }
    
    $stmt->close();

    // Fetch the updated outstanding loan balance
    $outstanding_sql = "SELECT SUM(amount - COALESCE(paid, 0)) AS outstanding_balance FROM repayments WHERE loan_id = ?";
    $outstanding_stmt = $conn->prepare($outstanding_sql);
    if (!$outstanding_stmt) {
        return "<div class='alert alert-danger text-center'>Error preparing balance query: " . htmlspecialchars($conn->error) . "</div>";
    }
    
    $outstanding_stmt->bind_param("i", $loan_id);
    if (!$outstanding_stmt->execute()) {
        $outstanding_stmt->close();
        return "<div class='alert alert-danger text-center'>Error executing balance query: " . htmlspecialchars($outstanding_stmt->error) . "</div>";
    }
    
    $outstanding_balance = $outstanding_stmt->get_result()->fetch_assoc()['outstanding_balance'] ?? 0;
    $outstanding_stmt->close();

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

function getLoanTermsForEmail($loan_id, $conn) {
    $sql = "SELECT 
        lp.name AS loan_product_name,
        la.loan_product,
        la.principal,
        la.total_amount,
        la.repayment_cycle,
        la.number_of_repayments,
        la.loan_release_date,
        la.loan_duration,
        la.loan_duration_unit,
        la.loan_status
    FROM loan_applications la
    LEFT JOIN loan_products lp ON la.loan_product = lp.id
    WHERE la.id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $loan_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return [];
    }

    $loanReleaseDate = $row['loan_release_date'] ?? null;
    $loanDuration = isset($row['loan_duration']) ? (int) $row['loan_duration'] : 0;
    $loanDurationUnit = normalizeDurationUnit($row['loan_duration_unit'] ?? 'months');
    $repaymentCycle = $row['repayment_cycle'] ?? 'once';
    
    // First, try to get the maturity date from the actual repayments table (last repayment date)
    $maturityStmt = $conn->prepare("SELECT MAX(repayment_date) AS projected_maturity_date FROM repayments WHERE loan_id = ?");
    $maturityStmt->bind_param('i', $loan_id);
    $maturityStmt->execute();
    $maturityResult = $maturityStmt->get_result()->fetch_assoc();
    $maturityStmt->close();
    
    $projectedMaturityDate = null;
    if (!empty($maturityResult['projected_maturity_date'])) {
        $projectedMaturityDate = $maturityResult['projected_maturity_date'];
    } else {
        // Fall back to calculating from schedule if no repayments exist
        $calcMaturityDate = getProjectedMaturityDate($loanReleaseDate, $loanDuration, $loanDurationUnit, $repaymentCycle);
        $projectedMaturityDate = $calcMaturityDate ? $calcMaturityDate->format('Y-m-d') : null;
        
        // Further fallback to duration-based calculation
        if (!$projectedMaturityDate) {
            $fallbackMaturityDate = getMaturityDate($loanReleaseDate, $loanDuration, $loanDurationUnit);
            $projectedMaturityDate = $fallbackMaturityDate ? $fallbackMaturityDate->format('Y-m-d') : null;
        }
    }

    $row['loan_duration_unit'] = $loanDurationUnit;
    $row['projected_maturity_date'] = $projectedMaturityDate;

    return $row;
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
    $officerEmail = trim((string) $officerEmail);
    if ($officerEmail === '') {
        return ['loan_book' => 0.0, 'overdue_balance' => 0.0, 'par' => 0.0, 'total_clients' => 0, 'clients_in_arrears' => 0, 'total_dues' => 0.0];
    }

    $borrowerStmt = $conn->prepare("SELECT id FROM borrowers WHERE loan_officer = ?");
    if (!$borrowerStmt) {
        return ['loan_book' => 0.0, 'overdue_balance' => 0.0, 'par' => 0.0, 'total_clients' => 0, 'clients_in_arrears' => 0, 'total_dues' => 0.0];
    }

    $borrowerStmt->bind_param('s', $officerEmail);
    $borrowerStmt->execute();
    $borrowerResult = $borrowerStmt->get_result();

    $loanBook = 0.0;
    $overdueBalance = 0.0;
    $totalClients = 0;
    $clientsInArrears = 0;

    while ($borrower = $borrowerResult->fetch_assoc()) {
        $borrowerId = (int) ($borrower['id'] ?? 0);
        if ($borrowerId <= 0) {
            continue;
        }

        $loanStmt = $conn->prepare("SELECT la.id, la.total_amount, COALESCE(SUM(r.paid), 0) AS total_paid
            FROM loan_applications la
            LEFT JOIN repayments r ON r.loan_id = la.id
            WHERE la.borrower = ? AND la.loan_status = 'approved'
            GROUP BY la.id, la.total_amount");
        if (!$loanStmt) {
            continue;
        }

        $loanStmt->bind_param('i', $borrowerId);
        $loanStmt->execute();
        $loanRows = $loanStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $loanStmt->close();

        $borrowerOutstanding = 0.0;
        foreach ($loanRows as $loan) {
            $totalAmount = (float) ($loan['total_amount'] ?? 0);
            $totalPaid = (float) ($loan['total_paid'] ?? 0);
            $borrowerOutstanding += max(0.0, $totalAmount - $totalPaid);
        }

        if ($borrowerOutstanding > 0) {
            $totalClients++;
        }

        $loanBook += $borrowerOutstanding;

        $arrearsStmt = $conn->prepare("SELECT GREATEST(
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
            WHERE borrowers.id = ? AND loan_applications.loan_status = 'approved'
            GROUP BY borrowers.id");
        if ($arrearsStmt) {
            $arrearsStmt->bind_param('i', $borrowerId);
            $arrearsStmt->execute();
            $arrearsRow = $arrearsStmt->get_result()->fetch_assoc();
            $arrearsStmt->close();
            $borrowerOverdue = (float) ($arrearsRow['total_overdue'] ?? 0.0);
            $overdueBalance += $borrowerOverdue;

            if ($borrowerOutstanding > 0 && $borrowerOverdue > 0) {
                $clientsInArrears++;
            }
        }
    }

    $borrowerStmt->close();

    $par = $loanBook > 0 ? ($overdueBalance / $loanBook) * 100 : 0;

    return [
        'loan_book' => $loanBook,
        'overdue_balance' => $overdueBalance,
        'par' => $par,
        'total_clients' => $totalClients,
        'clients_in_arrears' => $clientsInArrears,
        'total_dues' => 0.0,
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
              . '<p>Thank you,<br>' . htmlspecialchars($officerDisplay) . (!empty($officerEmail) ? '<br>' . htmlspecialchars($officerEmail) : '') . '</p>';

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

function sendPaymentRecordToClient($loan_id, $amount_paid, $conn) {
    // Get borrower contact and loan details
    $sql = "SELECT b.full_name AS borrower_name, COALESCE(b.email, '') AS borrower_email, b.mobile AS borrower_mobile, la.loan_product, la.id AS loan_id
            FROM loan_applications la
            JOIN borrowers b ON la.borrower = b.id
            WHERE la.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return "";
    $stmt->bind_param('i', $loan_id);
    $stmt->execute();
    $details = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $borrowerEmail = trim($details['borrower_email'] ?? '');
    if (empty($borrowerEmail) || !filter_var($borrowerEmail, FILTER_VALIDATE_EMAIL)) {
        // No valid borrower email to send to — return informative warning
        return "<div class='alert alert-warning text-center'>Payment saved but borrower has no valid email; receipt not sent to client.</div>";
    }

    $borrowerName = $details['borrower_name'] ?? 'Client';

    // Fetch updated loan balances
    $summary = getLoanBalanceSummary($loan_id, $conn);
    $outstanding = number_format($summary['outstanding_balance'] ?? 0, 2);
    $overdue = number_format($summary['overdue_balance'] ?? 0, 2);

    // Fetch loan terms (best-effort)
    $loanTerms = getLoanTermsForEmail($loan_id, $conn);

    // Optional: fetch recent payment records for this loan
    $payments = [];
    $ps = $conn->prepare("SELECT id, PaymentDate, Amount FROM payment_date_records WHERE loan_id = ? ORDER BY PaymentDate ASC, id ASC");
    if ($ps) {
        $ps->bind_param('i', $loan_id);
        $ps->execute();
        $payments = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
        $ps->close();
    }

    $emailCredentials = getEmailAccount();
    if (!$emailCredentials || empty($emailCredentials['sender_email']) || empty($emailCredentials['sender_app_password'])) {
        return "";
    }

    $logoPath = __DIR__ . '/../assets/img/logo.png';

    // Build HTML body similar to payslip in view_payroll.php
    $body = '<html><body style="margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#eaf5ff;color:#0f172a;">';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#eaf5ff;padding:24px;">';
    $body .= '<tr><td align="center">';
    $body .= '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 28px 80px rgba(14,88,161,0.15);border:1px solid #dbeafe;">';
    $body .= '<tr><td style="padding:28px 32px;background:#0ea5e9;color:#ffffff;text-align:center;">';
    if (file_exists($logoPath)) {
        $body .= '<img src="cid:company_logo" alt="Company Logo" width="96" style="display:block;margin:0 auto 18px;">';
    }
    $body .= '<h1 style="margin:0;font-size:28px;font-weight:700;letter-spacing:-0.04em;">Inua Premium Services</h1>';
    $body .= '<p style="margin:10px 0 0;font-size:15px;color:#dbeafe;">Payment Receipt</p>';
    $body .= '</td></tr>';
    $body .= '<tr><td style="padding:0 32px 16px;">';
    $body .= '<p style="margin:0;font-size:14px;color:#475569;">Dear ' . htmlspecialchars($borrowerName) . ',</p>';
    $body .= '<p style="margin:8px 0 0;font-size:14px;color:#475569;">This email confirms receipt of your payment.</p>';
    $body .= '</td></tr>';
    $body .= '<tr><td style="padding:28px 32px 16px;">';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
    $body .= '<tr><td style="padding:8px 0;font-size:14px;color:#111827;"><strong>Loan ID:</strong> ' . htmlspecialchars($loan_id) . '</td><td style="padding:8px 0;font-size:14px;color:#111827;text-align:right;"><strong>Amount Paid:</strong> KES ' . number_format((float)$amount_paid, 2) . '</td></tr>';
    $body .= '<tr><td style="padding:8px 0;font-size:14px;color:#111827;"><strong>Outstanding Balance:</strong> KES ' . $outstanding . '</td><td style="padding:8px 0;font-size:14px;color:#111827;text-align:right;"><strong>Overdue:</strong> KES ' . $overdue . '</td></tr>';
    $body .= '</table>';

    // Loan terms section
    if (!empty($loanTerms)) {
        $prod = htmlspecialchars($loanTerms['loan_product_name'] ?? $loanTerms['loan_product'] ?? '');
        $principal = isset($loanTerms['principal']) ? number_format((float)$loanTerms['principal'], 2) : '';
        $totalAmt = isset($loanTerms['total_amount']) ? number_format((float)$loanTerms['total_amount'], 2) : '';
        $numRepay = isset($loanTerms['number_of_repayments']) ? intval($loanTerms['number_of_repayments']) : 0;
        $duration = isset($loanTerms['loan_duration']) ? intval($loanTerms['loan_duration']) : 0;
        $durationUnit = $loanTerms['loan_duration_unit'] ?? '';
        $release = !empty($loanTerms['loan_release_date']) ? htmlspecialchars(date('d/m/Y', strtotime($loanTerms['loan_release_date']))) : '';
        $maturity = !empty($loanTerms['projected_maturity_date']) ? htmlspecialchars(date('d/m/Y', strtotime($loanTerms['projected_maturity_date']))) : '';
        $status = htmlspecialchars($loanTerms['loan_status'] ?? '');
        $durationDisplay = formatLoanDurationDisplay($duration, $durationUnit);

        // Calculate total paid from actual payment records
        $totalPaidAmount = 0;
        foreach ($payments as $p) {
            $totalPaidAmount += (float)$p['Amount'];
        }
        $totalPaid = $totalPaidAmount > 0 ? number_format($totalPaidAmount, 2) : '';
        $totalBalance = number_format(max(0, (float)($loanTerms['total_amount'] ?? 0) - $totalPaidAmount), 2);
        $installmentAmount = ($numRepay > 0) ? number_format((float)($loanTerms['total_amount'] ?? 0) / $numRepay, 2) : '';

        // Fetch the assigned loan officer's portfolio metrics for footer
        $officerInfo = getLoanNotificationDetails($loan_id, $conn);
        $officerEmail = trim($officerInfo['officer_email'] ?? '');
        $portfolioMetrics = getLoanOfficerPortfolioMetrics($officerEmail, $conn);
        $lb = number_format($portfolioMetrics['loan_book'] ?? 0, 2);
        $par = number_format($portfolioMetrics['par'] ?? 0, 2);
        $noc = intval($portfolioMetrics['total_clients'] ?? 0);
        $nocA = intval($portfolioMetrics['clients_in_arrears'] ?? 0);

        $body .= '<div style="margin-top:18px;">';
        $body .= '<h4 style="margin:0 0 8px;font-size:16px;color:#0f172a;">Loan Terms</h4>';
        $body .= '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;">';
        $body .= '<tr style="background:#f8fafc;color:#0f172a;font-weight:700;"><td><strong>Product</strong></td><td align="right"><strong>' . $prod . '</strong></td></tr>';
        if ($principal) {
            $body .= '<tr><td>Principal Amount</td><td align="right">KES ' . $principal . '</td></tr>';
        }
        $body .= '<tr><td>Total Amount</td><td align="right">KES ' . $totalAmt . '</td></tr>';
        if ($installmentAmount) {
            $body .= '<tr><td>Installment Amount</td><td align="right">KES ' . $installmentAmount . '</td></tr>';
        }
        if ($totalPaid) {
            $body .= '<tr><td>Total Paid</td><td align="right">KES ' . $totalPaid . '</td></tr>';
        }
        $body .= '<tr><td>Total Balance</td><td align="right">KES ' . $totalBalance . '</td></tr>';
        if ($status) {
            $statusDisplay = ucfirst($status);
            $body .= '<tr><td>Status</td><td align="right"><strong>' . $statusDisplay . '</strong></td></tr>';
        }
        $body .= '</table></div>';

        $summaryBlock = [];
        if ($release) {
            $summaryBlock[] = '<p style="margin:0 0 6px;font-size:14px;color:#0f172a;"><strong>Release Date:</strong> ' . $release . '</p>';
        }
        if ($maturity) {
            $summaryBlock[] = '<p style="margin:0;font-size:14px;color:#0f172a;"><strong>Maturity Date:</strong> ' . $maturity . '</p>';
        }

        if (!empty($summaryBlock)) {
            $body .= '<div style="margin-top:18px;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;">';
            $body .= implode('', $summaryBlock);
            $body .= '</div>';
        }

        $body .= '<div style="margin-top:12px;padding:8px 10px;background:#f0f4f8;border:1px solid #e2e8f0;border-radius:8px;font-style:italic;font-size:13px;color:#475569;">LB: KES ' . $lb . ' | PAR: ' . $par . '% | NoC: ' . $noc . ' | NoC-A: ' . $nocA . '</div>';
    }

    if (!empty($payments)) {
        $body .= '<div style="margin-top:18px;">';
        $body .= '<h4 style="margin:0 0 8px;font-size:16px;color:#0f172a;">Recent Payments</h4>';
        $body .= '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;">';
        $body .= '<tr style="background:#f1f5f9;color:#0f172a;font-weight:700;"><th align="left">Date</th><th align="right">Amount (KES)</th></tr>';
        foreach ($payments as $p) {
            $d = htmlspecialchars(date('d/m/Y', strtotime($p['PaymentDate'])));
            $a = number_format((float)$p['Amount'], 2);
            $body .= '<tr><td>' . $d . '</td><td align="right">' . $a . '</td></tr>';
        }
        $body .= '</table>';
        $body .= '</div>';
    }

    if (empty($footerMetricsAppended)) {
        $body .= '<div style="margin-top:12px;padding:8px 10px;background:#f0f4f8;border:1px solid #e2e8f0;border-radius:8px;font-style:italic;font-size:13px;color:#475569;">LB: KES ' . $lb . ' | PAR: ' . $par . '% | NoC: ' . $noc . ' | NoC-A: ' . $nocA . '</div>';
        $footerMetricsAppended = true;
    }
    $body .= '</td></tr></table></td></tr></table></body></html>';

    // Send email
    try {
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
        $mail->SMTPAuth = true;
        $mail->Username = $emailCredentials['sender_email'];
        $mail->Password = $emailCredentials['sender_app_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($emailCredentials['sender_email'], 'Inua Premium Services');
        $mail->addAddress($borrowerEmail, $borrowerName);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Receipt - Inua Premium Services';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'company_logo');
        }
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], "\n", $body));
        $mail->send();
        return "<div class='alert alert-success text-center'>Payment receipt sent to client: " . htmlspecialchars($borrowerEmail) . "</div>";
    } catch (Exception $e) {
        return "<div class='alert alert-warning text-center'>Payment saved but failed sending receipt to client: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

function sendPaymentRecordToStaff($loan_id, $amount_paid, $conn) {
    $details = getLoanNotificationDetails($loan_id, $conn);
    if (!$details) return "<div class='alert alert-warning text-center'>Payment saved but staff details not found for sending payment record.</div>";

    $officerEmail = trim($details['officer_email'] ?? '');
    $officerName = !empty($details['loan_officer_name']) ? $details['loan_officer_name'] : $officerEmail;
    $senderEmail = getConfiguredSenderEmail();
    if (empty($officerEmail) || !filter_var($officerEmail, FILTER_VALIDATE_EMAIL)) {
        $officerEmail = $senderEmail;
    }

    $summary = getLoanBalanceSummary($loan_id, $conn);
    $outstanding = number_format($summary['outstanding_balance'] ?? 0, 2);
    $overdue = number_format($summary['overdue_balance'] ?? 0, 2);

    // Fetch loan terms (best-effort)
    $loanTerms = getLoanTermsForEmail($loan_id, $conn);

    $payments = [];
    $ps = $conn->prepare("SELECT id, PaymentDate, Amount FROM payment_date_records WHERE loan_id = ? ORDER BY PaymentDate ASC, id ASC");
    if ($ps) {
        $ps->bind_param('i', $loan_id);
        $ps->execute();
        $payments = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
        $ps->close();
    }

    $emailCredentials = getEmailAccount();
    if (!$emailCredentials || empty($emailCredentials['sender_email']) || empty($emailCredentials['sender_app_password'])) {
        return "<div class='alert alert-warning text-center'>Payment saved but notification email was not sent because email settings are not configured.</div>";
    }

    $logoPath = __DIR__ . '/../assets/img/logo.png';

    $body = '<html><body style="margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#eaf5ff;color:#0f172a;">';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#eaf5ff;padding:24px;">';
    $body .= '<tr><td align="center">';
    $body .= '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 28px 80px rgba(14,88,161,0.15);border:1px solid #dbeafe;">';
    $body .= '<tr><td style="padding:28px 32px;background:#0ea5e9;color:#ffffff;text-align:center;">';
    if (file_exists($logoPath)) {
        $body .= '<img src="cid:company_logo" alt="Company Logo" width="96" style="display:block;margin:0 auto 18px;">';
    }
    $body .= '<h1 style="margin:0;font-size:28px;font-weight:700;letter-spacing:-0.04em;">Inua Premium Services</h1>';
    $body .= '<p style="margin:10px 0 0;font-size:15px;color:#dbeafe;">Payment Record</p>';
    $body .= '</td></tr>';
    $body .= '<tr><td style="padding:0 32px 16px;">';
    $body .= '<p style="margin:0;font-size:14px;color:#475569;">Dear ' . htmlspecialchars($officerName) . ',</p>';
    $body .= '<p style="margin:8px 0 0;font-size:14px;color:#475569;">A payment has been received for Loan ID ' . htmlspecialchars($loan_id) . '.</p>';
    $body .= '</td></tr>';
    $body .= '<tr><td style="padding:28px 32px 16px;">';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
    $body .= '<tr><td style="padding:8px 0;font-size:14px;color:#111827;"><strong>Loan ID:</strong> ' . htmlspecialchars($loan_id) . '</td><td style="padding:8px 0;font-size:14px;color:#111827;text-align:right;"><strong>Amount Paid:</strong> KES ' . number_format((float)$amount_paid, 2) . '</td></tr>';
    $body .= '<tr><td style="padding:8px 0;font-size:14px;color:#111827;"><strong>Outstanding Balance:</strong> KES ' . $outstanding . '</td><td style="padding:8px 0;font-size:14px;color:#111827;text-align:right;"><strong>Overdue:</strong> KES ' . $overdue . '</td></tr>';
    $body .= '</table>';

    // Loan terms section for staff
    if (!empty($loanTerms)) {
        $prod = htmlspecialchars($loanTerms['loan_product_name'] ?? $loanTerms['loan_product'] ?? '');
        $principal = isset($loanTerms['principal']) ? number_format((float)$loanTerms['principal'], 2) : '';
        $totalAmt = isset($loanTerms['total_amount']) ? number_format((float)$loanTerms['total_amount'], 2) : '';
        $numRepay = isset($loanTerms['number_of_repayments']) ? intval($loanTerms['number_of_repayments']) : 0;
        $duration = isset($loanTerms['loan_duration']) ? intval($loanTerms['loan_duration']) : 0;
        $durationUnit = $loanTerms['loan_duration_unit'] ?? '';
        $release = !empty($loanTerms['loan_release_date']) ? htmlspecialchars(date('d/m/Y', strtotime($loanTerms['loan_release_date']))) : '';
        $maturity = !empty($loanTerms['projected_maturity_date']) ? htmlspecialchars(date('d/m/Y', strtotime($loanTerms['projected_maturity_date']))) : '';
        $status = htmlspecialchars($loanTerms['loan_status'] ?? '');

        // Calculate total paid from actual payment records
        $totalPaidAmount = 0;
        foreach ($payments as $p) {
            $totalPaidAmount += (float)$p['Amount'];
        }
        $totalPaid = $totalPaidAmount > 0 ? number_format($totalPaidAmount, 2) : '';
        $totalBalance = number_format(max(0, (float)($loanTerms['total_amount'] ?? 0) - $totalPaidAmount), 2);
        $installmentAmount = ($numRepay > 0) ? number_format((float)($loanTerms['total_amount'] ?? 0) / $numRepay, 2) : '';

        // Fetch the assigned loan officer's portfolio metrics for footer
        $officerDetails = getLoanNotificationDetails($loan_id, $conn);
        $officerEmail = trim($officerDetails['officer_email'] ?? '');
        $portfolioMetrics = getLoanOfficerPortfolioMetrics($officerEmail, $conn);
        $lb = number_format($portfolioMetrics['loan_book'] ?? 0, 2);
        $par = number_format($portfolioMetrics['par'] ?? 0, 2);
        $noc = intval($portfolioMetrics['total_clients'] ?? 0);
        $nocA = intval($portfolioMetrics['clients_in_arrears'] ?? 0);

        $body .= '<div style="margin-top:18px;">';
        $body .= '<h4 style="margin:0 0 8px;font-size:16px;color:#0f172a;">Loan Terms</h4>';
        $body .= '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;">';
        $body .= '<tr style="background:#f8fafc;color:#0f172a;font-weight:700;"><td><strong>Product</strong></td><td align="right"><strong>' . $prod . '</strong></td></tr>';
        if ($principal) {
            $body .= '<tr><td>Principal Amount</td><td align="right">KES ' . $principal . '</td></tr>';
        }
        $body .= '<tr><td>Total Amount</td><td align="right">KES ' . $totalAmt . '</td></tr>';
        if ($installmentAmount) {
            $body .= '<tr><td>Installment Amount</td><td align="right">KES ' . $installmentAmount . '</td></tr>';
        }
        if ($totalPaid) {
            $body .= '<tr><td>Total Paid</td><td align="right">KES ' . $totalPaid . '</td></tr>';
        }
        $body .= '<tr><td>Total Balance</td><td align="right">KES ' . $totalBalance . '</td></tr>';
        if ($status) {
            $statusDisplay = ucfirst($status);
            $body .= '<tr><td>Status</td><td align="right"><strong>' . $statusDisplay . '</strong></td></tr>';
        }
        $body .= '</table></div>';

        $summaryBlock = [];
        if ($release) {
            $summaryBlock[] = '<p style="margin:0 0 6px;font-size:14px;color:#0f172a;"><strong>Release Date:</strong> ' . $release . '</p>';
        }
        if ($maturity) {
            $summaryBlock[] = '<p style="margin:0;font-size:14px;color:#0f172a;"><strong>Maturity Date:</strong> ' . $maturity . '</p>';
        }

        if (!empty($summaryBlock)) {
            $body .= '<div style="margin-top:18px;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;">';
            $body .= implode('', $summaryBlock);
            $body .= '</div>';
        }

        $body .= '<div style="margin-top:12px;padding:8px 10px;background:#f0f4f8;border:1px solid #e2e8f0;border-radius:8px;font-style:italic;font-size:13px;color:#475569;">LB: KES ' . $lb . ' | PAR: ' . $par . '% | NoC: ' . $noc . ' | NoC-A: ' . $nocA . '</div>';
    }

    if (!empty($payments)) {
        $body .= '<div style="margin-top:18px;">';
        $body .= '<h4 style="margin:0 0 8px;font-size:16px;color:#0f172a;">Recent Payments</h4>';
        $body .= '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;">';
        $body .= '<tr style="background:#f1f5f9;color:#0f172a;font-weight:700;"><th align="left">Date</th><th align="right">Amount (KES)</th></tr>';
        foreach ($payments as $p) {
            $d = htmlspecialchars(date('d/m/Y', strtotime($p['PaymentDate'])));
            $a = number_format((float)$p['Amount'], 2);
            $body .= '<tr><td>' . $d . '</td><td align="right">' . $a . '</td></tr>';
        }
        $body .= '</table>';
        $body .= '</div>';
    }

    if (empty($footerMetricsAppended)) {
        $body .= '<div style="margin-top:12px;padding:8px 10px;background:#f0f4f8;border:1px solid #e2e8f0;border-radius:8px;font-style:italic;font-size:13px;color:#475569;">LB: KES ' . $lb . ' | PAR: ' . $par . '% | NoC: ' . $noc . ' | NoC-A: ' . $nocA . '</div>';
        $footerMetricsAppended = true;
    }
    $body .= '</td></tr></table></td></tr></table></body></html>';

    try {
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
        $mail->SMTPAuth = true;
        $mail->Username = $emailCredentials['sender_email'];
        $mail->Password = $emailCredentials['sender_app_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($emailCredentials['sender_email'], 'Inua Premium Services');
        $mail->addAddress($officerEmail, $officerName);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Record - Inua Premium Services';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'company_logo');
        }
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], "\n", $body));
        $mail->send();
        return "<div class='alert alert-success text-center'>Payment record sent to staff: " . htmlspecialchars($officerEmail) . "</div>";
    } catch (Exception $e) {
        return "<div class='alert alert-warning text-center'>Payment saved but failed sending record to staff: " . htmlspecialchars($e->getMessage()) . "</div>";
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
