<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/functions.php';
include 'includes/header.php'; 
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

function getCycleInterval($cycle) {
    switch ($cycle) {
        case 'daily':
            return '1 day';
        case 'weekly':
            return '1 week';
        case 'monthly':
            return '1 month';
        case 'yearly':
            return '1 year';
        case 'once':
            return '0 days';
        default:
            return '1 month';
    }
}

function rebuildLoanRepaymentScheduleFromCurrentTerms($conn, $loanId) {
    $loanStmt = $conn->prepare("SELECT principal, total_amount, loan_release_date, loan_duration, loan_duration_unit, repayment_cycle, number_of_repayments FROM loan_applications WHERE id = ?");
    if (!$loanStmt) {
        return false;
    }

    $loanStmt->bind_param("i", $loanId);
    $loanStmt->execute();
    $loanRow = $loanStmt->get_result()->fetch_assoc();
    $loanStmt->close();

    if (!$loanRow) {
        return false;
    }

    $principalAmount = (float) ($loanRow['principal'] ?? 0);
    $interestAmount = max(0, ((float) ($loanRow['total_amount'] ?? 0)) - $principalAmount);
    $loanReleaseDate = $loanRow['loan_release_date'];
    $loanDuration = (int) ($loanRow['loan_duration'] ?? 0);
    $loanDurationUnit = $loanRow['loan_duration_unit'] ?? 'months';
    $repaymentCycle = $loanRow['repayment_cycle'] ?? 'monthly';
    $numberOfRepayments = (int) ($loanRow['number_of_repayments'] ?? 0);

    $conn->begin_transaction();

    try {
        $deleteStmt = $conn->prepare("DELETE FROM repayments WHERE loan_id = ?");
        $deleteStmt->bind_param("i", $loanId);
        $deleteStmt->execute();
        $deleteStmt->close();

        $startDate = new DateTime($loanReleaseDate);

        if ($repaymentCycle === 'once') {
            $maturityDate = new DateTime($loanReleaseDate);
            switch ($loanDurationUnit) {
                case 'days':
                    $maturityDate->modify('+' . max(1, $loanDuration) . ' days');
                    break;
                case 'weeks':
                    $maturityDate->modify('+' . max(1, $loanDuration) . ' weeks');
                    break;
                case 'months':
                    $maturityDate->modify('+' . max(1, $loanDuration) . ' months');
                    break;
                case 'years':
                    $maturityDate->modify('+' . max(1, $loanDuration) . ' years');
                    break;
                default:
                    $maturityDate->modify('+' . max(1, $loanDuration) . ' months');
                    break;
            }
            $repaymentAmount = $principalAmount + $interestAmount;
            $repaymentDate = $maturityDate->format('Y-m-d');
            $insertStmt = $conn->prepare("INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (?, ?, ?)");
            $insertStmt->bind_param("isd", $loanId, $repaymentDate, $repaymentAmount);
            $insertStmt->execute();
            $insertStmt->close();
        } else {
            for ($i = 1; $i <= $numberOfRepayments; $i++) {
                $scheduleDate = clone $startDate;
                $scheduleDate->modify('+' . getCycleInterval($repaymentCycle));
                $repaymentAmount = ($principalAmount + $interestAmount) / max(1, $numberOfRepayments);
                $repaymentDate = $scheduleDate->format('Y-m-d');
                $insertStmt = $conn->prepare("INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (?, ?, ?)");
                $insertStmt->bind_param("isd", $loanId, $repaymentDate, $repaymentAmount);
                $insertStmt->execute();
                $insertStmt->close();
                $startDate = $scheduleDate;
            }
        }

        $paymentStmt = $conn->prepare("SELECT id, Amount, PaymentDate FROM payment_date_records WHERE loan_id = ? ORDER BY PaymentDate ASC, id ASC");
        $paymentStmt->bind_param("i", $loanId);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $paymentRecords = [];
        while ($paymentRow = $paymentResult->fetch_assoc()) {
            $paymentRecords[] = $paymentRow;
        }
        $paymentStmt->close();

        $repaymentRowsStmt = $conn->prepare("SELECT id, amount, paid FROM repayments WHERE loan_id = ? ORDER BY repayment_date ASC");
        $repaymentRowsStmt->bind_param("i", $loanId);
        $repaymentRowsStmt->execute();
        $repaymentRowsResult = $repaymentRowsStmt->get_result();
        $repaymentRows = [];
        while ($repaymentRow = $repaymentRowsResult->fetch_assoc()) {
            $repaymentRows[] = $repaymentRow;
        }
        $repaymentRowsStmt->close();

        foreach ($paymentRecords as $paymentRecord) {
            $paymentAmount = (float) ($paymentRecord['Amount'] ?? 0);
            $paymentDate = !empty($paymentRecord['PaymentDate']) ? date('Y-m-d', strtotime($paymentRecord['PaymentDate'])) : null;

            if ($paymentAmount <= 0 || $paymentDate === null) {
                continue;
            }

            foreach ($repaymentRows as &$repaymentRow) {
                if ($paymentAmount <= 0) {
                    break;
                }

                $dueAmount = (float) ($repaymentRow['amount'] ?? 0);
                $alreadyPaid = (float) ($repaymentRow['paid'] ?? 0);
                $remainingDue = max(0, $dueAmount - $alreadyPaid);

                if ($remainingDue <= 0) {
                    continue;
                }

                $appliedAmount = min($paymentAmount, $remainingDue);
                $paymentAmount -= $appliedAmount;
                $repaymentRow['paid'] = $alreadyPaid + $appliedAmount;

                $updateStmt = $conn->prepare("UPDATE repayments SET paid = ?, repaid_date = ? WHERE id = ?");
                $updateStmt->bind_param("dsi", $repaymentRow['paid'], $paymentDate, $repaymentRow['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }
            unset($repaymentRow);
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

function generate_repayment_details_pdf($loan, $guarantors, $adjusted_history, $daysInArrears, $projectedMaturityDate, $daysAfterProjectedMaturity, $payment_records) {
    $overdueAmount = 0.0;
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor('Inua Premium Services');
    $pdf->SetTitle('Payment Records');
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $pdf->SetTextColor(56, 152, 219);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 8, 'Inua Premium Services', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Payment Records', 0, 1, 'C');
    // draw a top divider that matches the content margins
    $margin = 15; // left/right margin in mm (same as SetMargins above)
    $contentWidth = $pdf->getPageWidth() - ($margin * 2);
    $pdf->SetDrawColor(56, 152, 219);
    $pdf->SetLineWidth(0.3);
    $yLine = $pdf->GetY() + 2;
    $pdf->Line($margin, $yLine, $margin + $contentWidth, $yLine);
    $pdf->Ln(4);

    $pdf->SetTextColor(33, 37, 41);
    // print loan officer under title for clarity
    $pdf->SetFont('helvetica', '', 10);
    $loanOfficerDisplay = !empty($loan['loan_officer_name']) ? $loan['loan_officer_name'] : 'Unassigned';
    $pdf->Cell(0, 6, 'Loan Officer: ' . $loanOfficerDisplay, 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Loan Terms', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8);

    $colWidth = 58;
    $leftX = 15;
    $midX = 15 + $colWidth;
    $rightX = 15 + ($colWidth * 2);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY($leftX, $pdf->GetY());
    $pdf->Cell($colWidth, 5, 'Borrower Information', 0, 0, 'L');
    $pdf->SetXY($midX, $pdf->GetY());
    $pdf->Cell($colWidth, 5, 'Loan Summary', 0, 0, 'L');
    $pdf->SetXY($rightX, $pdf->GetY());
    $pdf->Cell($colWidth, 5, 'Arrears Summary', 0, 1, 'L');

    $guarantor_names = [];
    foreach ($guarantors as $guarantor) {
        $guarantor_names[] = $guarantor['full_name'];
    }

    $guarantorNameDisplay = !empty($loan['guarantor_name'])
        ? $loan['guarantor_name']
        : (!empty($guarantor_names) ? implode(', ', $guarantor_names) : 'No guarantor recorded.');
    $guarantorPhoneDisplay = !empty($loan['guarantor_phone']) ? $loan['guarantor_phone'] : 'N/A';

    $borrowerInfo = [
        'Borrower: ' . $loan['borrower_name'],
        'Phone: ' . $loan['phone_number'],
        'ID Number: ' . $loan['id_number'],
        'Loan Product: ' . $loan['loan_product_name'],
        'Guarantor Name: ' . $guarantorNameDisplay,
        'Guarantor Phone: ' . $guarantorPhoneDisplay
    ];

    $loanSummary = [
        'Principal: KSH ' . number_format($loan['principal_amount'], 2),
        'Total Amount: KSH ' . number_format(($loan['total_amount_due'] ?? 0), 2),
        'Total Paid: KSH ' . number_format(($loan['total_amount_paid'] ?? 0), 2),
        'Balance: KSH ' . number_format((($loan['total_amount_due'] ?? 0) - ($loan['total_amount_paid'] ?? 0)), 2),
        'Release Date: ' . (!empty($loan['loan_release_date']) ? date('d/m/Y', strtotime($loan['loan_release_date'])) : 'N/A'),
        'Loan Officer: ' . (!empty($loan['loan_officer_name']) ? $loan['loan_officer_name'] : 'Unassigned')
    ];

    $arrearsInfo = [
        'Arrears Amount: KSH ' . number_format($overdueAmount, 2),
        'Days in arrears: ' . $daysInArrears,
        'Projected maturity date: ' . ($projectedMaturityDate ? $projectedMaturityDate->format('d/m/Y') : 'N/A'),
        'Days overdue: ' . $daysAfterProjectedMaturity
    ];

    $pdf->SetFont('helvetica', '', 8);
    $startY = $pdf->GetY() + 2;
    $pdf->SetXY($leftX, $startY);
    $pdf->MultiCell($colWidth, 4.2, implode(PHP_EOL, $borrowerInfo), 0, 'L');

    $pdf->SetXY($midX, $startY);
    $pdf->MultiCell($colWidth, 4.2, implode(PHP_EOL, $loanSummary), 0, 'L');

    $pdf->SetXY($rightX, $startY);
    $pdf->MultiCell($colWidth, 4.2, implode(PHP_EOL, $arrearsInfo), 0, 'L');

    $pdf->Ln(6);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Payment Records', 0, 1, 'L');
    // align table with same margins and content width
    $pdf->Ln(1);
    $pdf->SetFillColor(56, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    // calculate column widths (sum must be <= contentWidth)
    $col1 = 120; // Payment Date column width
    $col2 = $contentWidth - $col1; // Amount Paid column width
    // ensure X is at left margin
    $pdf->SetX($margin);
    $pdf->Cell($col1, 9, 'Payment Date', 1, 0, 'L', true);
    $pdf->Cell($col2, 9, 'Amount Paid', 1, 1, 'R', true);

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetFont('helvetica', '', 9);
    if (!empty($payment_records)) {
        foreach ($payment_records as $row) {
            $payment_date = !empty($row['PaymentDate']) ? date('d/m/Y', strtotime($row['PaymentDate'])) : 'N/A';
            $amount = !empty($row['Amount']) ? number_format((float) $row['Amount'], 2) : '0.00';
            $pdf->SetX($margin);
            $pdf->Cell($col1, 7, $payment_date, 1, 0, 'L');
            $pdf->Cell($col2, 7, 'KSH ' . $amount, 1, 1, 'R');
        }
    } else {
        $pdf->SetX($margin);
        $pdf->Cell($contentWidth, 8, 'No payment records found.', 1, 1, 'C');
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

/**
 * Determine projected maturity date using loan duration and unit.
 * This uses the explicit loan duration (eg 2) and unit (days/weeks/months/years)
 */
function getProjectedMaturityDate($releaseDate, $loan_duration, $loan_duration_unit) {
    if (empty($releaseDate)) {
        return null;
    }

    $maturity_date = new DateTime($releaseDate);
    switch ($loan_duration_unit) {
        case 'days':
            $maturity_date->modify('+' . (int) $loan_duration . ' days');
            break;
        case 'weeks':
            $maturity_date->modify('+' . (int) $loan_duration . ' weeks');
            break;
        case 'months':
            $maturity_date->modify('+' . (int) $loan_duration . ' months');
            break;
        case 'years':
            $maturity_date->modify('+' . (int) $loan_duration . ' years');
            break;
        default:
            // fallback to months if unit missing
            $maturity_date->modify('+' . (int) $loan_duration . ' months');
            break;
    }

    return $maturity_date;
}

function calculateDaysOverdueAfterMaturity($projectedMaturityDate, $repaymentRows, $totalDue) {
    if (!$projectedMaturityDate || (float) $totalDue <= 0) {
        return 0;
    }

    $today = new DateTime('today');
    if ($projectedMaturityDate >= $today) {
        return 0;
    }

    $maturityDate = clone $projectedMaturityDate;
    $cumulativePaid = 0.0;
    $clearingDate = null;

    foreach ($repaymentRows as $row) {
        $paymentDateValue = $row['repaid_date'] ?? $row['repayment_date'] ?? null;
        if (empty($paymentDateValue)) {
            continue;
        }

        $paymentDate = new DateTime($paymentDateValue);
        if ($paymentDate < $maturityDate) {
            continue;
        }

        $paidAmount = (float) ($row['paid'] ?? 0);
        if ($paidAmount <= 0) {
            continue;
        }

        $cumulativePaid += $paidAmount;
        if ($cumulativePaid >= (float) $totalDue) {
            $clearingDate = $paymentDate;
            break;
        }
    }

    if ($clearingDate === null) {
        return 0;
    }

    return max(0, (int) $maturityDate->diff($clearingDate)->days);
}

if (!isset($_GET['loanId']) || !is_numeric($_GET['loanId'])) {
    die("Invalid Loan ID.");
}

$loanId = intval($_GET['loanId']); // Secure the input

rebuildLoanRepaymentScheduleFromCurrentTerms($conn, $loanId);

// Distribute a payment amount across unpaid installments and set repaid_date
function distributeRepayment($loan_id, $amount_paid, $conn, $payment_date) {
    $sql = "SELECT * FROM repayments WHERE loan_id = ? AND COALESCE(paid, 0) < amount ORDER BY repayment_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $installment_id = $row['id'];
        $remaining_due = $row['amount'] - $row['paid'];

        if ($amount_paid <= 0) {
            break;
        }

        if ($amount_paid >= $remaining_due) {
            $new_amount_paid = $row['amount']; // full payment
            $amount_paid -= $remaining_due;
        } else {
            $new_amount_paid = $row['paid'] + $amount_paid;
            $amount_paid = 0;
        }

        $update_sql = "UPDATE repayments SET paid = ?, repaid_date = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("dsi", $new_amount_paid, $payment_date, $installment_id);
        $update_stmt->execute();
    }
}

function resetRepaymentSchedule($loan_id, $conn) {
    $resetStmt = $conn->prepare("UPDATE repayments SET paid = 0, repaid_date = NULL WHERE loan_id = ?");
    $resetStmt->bind_param("i", $loan_id);
    $resetStmt->execute();
    $resetStmt->close();
}

function rebuildRepaymentSchedule($loan_id, $conn) {
    resetRepaymentSchedule($loan_id, $conn);

    $recordsStmt = $conn->prepare("SELECT PaymentDate, Amount FROM payment_date_records WHERE loan_id = ? ORDER BY PaymentDate ASC, id ASC");
    $recordsStmt->bind_param("i", $loan_id);
    $recordsStmt->execute();
    $recordsResult = $recordsStmt->get_result();

    while ($recordRow = $recordsResult->fetch_assoc()) {
        $paymentAmount = isset($recordRow['Amount']) ? floatval($recordRow['Amount']) : 0.0;
        $paymentDate = !empty($recordRow['PaymentDate']) ? date('Y-m-d', strtotime($recordRow['PaymentDate'])) : null;

        if ($paymentAmount > 0 && $paymentDate) {
            distributeRepayment($loan_id, $paymentAmount, $conn, $paymentDate);
        }
    }

    $recordsStmt->close();
}

// Save payment records if submitted
$message = '';
if (isset($_POST['action']) && $_POST['action'] === 'clear' && isset($_POST['record_id'])) {
    $recordId = intval($_POST['record_id']);
    // fetch record details before deletion
    $fetchStmt = $conn->prepare("SELECT Amount, PaymentDate FROM payment_date_records WHERE id = ? AND loan_id = ? LIMIT 1");
    $fetchStmt->bind_param("ii", $recordId, $loanId);
    $fetchStmt->execute();
    $resFetch = $fetchStmt->get_result();
    $rowFetch = $resFetch->fetch_assoc();
    $fetchStmt->close();

    // delete the payment_date_records row
    $deleteStmt = $conn->prepare("DELETE FROM payment_date_records WHERE id = ? AND loan_id = ?");
    $deleteStmt->bind_param("ii", $recordId, $loanId);
    $deleteStmt->execute();

    // also remove any matching payments entry (best-effort): match on loan_id, amount and date
    if ($rowFetch) {
        $amt = $rowFetch['Amount'];
        $pdate = $rowFetch['PaymentDate']; // YYYY-MM-DD
        $delPay = $conn->prepare("DELETE FROM payments WHERE loan_id = ? AND amount = ? AND DATE(payment_date) = ? LIMIT 1");
        $delPay->bind_param("ids", $loanId, $amt, $pdate);
        $delPay->execute();
        $delPay->close();
    }

    rebuildRepaymentSchedule($loanId, $conn);

    header("Location: repayment_details.php?loanId={$loanId}&cleared=1");
    exit;
}

if (isset($_POST['save_payment_records'])) {
    $recordIds = $_POST['record_id'] ?? [];
    $amounts = $_POST['amount_paid_record'] ?? [];
    $dates = $_POST['payment_date_record'] ?? [];

    foreach ($recordIds as $index => $recordId) {
        $recordId = intval($recordId);
        $amount = isset($amounts[$index]) && $amounts[$index] !== '' ? floatval($amounts[$index]) : 0.0;
        $paymentDate = !empty($dates[$index]) ? date('Y-m-d', strtotime($dates[$index])) : null;

        if ($recordId > 0) {
            $updateStmt = $conn->prepare("UPDATE payment_date_records SET Amount = ?, PaymentDate = ? WHERE id = ? AND loan_id = ?");
            $updateStmt->bind_param("dsii", $amount, $paymentDate, $recordId, $loanId);
            $updateStmt->execute();
        }
    }

    $newAmounts = $_POST['new_amount_paid'] ?? [];
    $newDates = $_POST['new_payment_date'] ?? [];
    foreach ($newAmounts as $index => $newAmountValue) {
        $newAmount = isset($newAmountValue) && $newAmountValue !== '' ? floatval($newAmountValue) : 0.0;
        $newDate = !empty($newDates[$index]) ? date('Y-m-d', strtotime($newDates[$index])) : null;
        if ($newAmount > 0 && $newDate !== null) {
            $insertStmt = $conn->prepare("INSERT INTO payment_date_records (loan_id, PaymentDate, Amount) VALUES (?, ?, ?)");
            $insertStmt->bind_param("isd", $loanId, $newDate, $newAmount);
            $insertStmt->execute();
        }
    }

    rebuildRepaymentSchedule($loanId, $conn);

    header("Location: repayment_details.php?loanId={$loanId}&saved=1");
    exit;
}

// Fetch loan details
$sql_loan = "SELECT 
                borrowers.full_name AS borrower_name, 
                borrowers.mobile AS phone_number,
                borrowers.unique_number AS id_number,
                borrowers.guarantor_name,
                borrowers.guarantor_phone,
                loan_products.name AS loan_product_name,
                loan_applications.loan_product, 
                loan_applications.principal AS principal_amount,
                loan_applications.loan_release_date,
                loan_applications.loan_duration,
                loan_applications.loan_duration_unit,
                loan_applications.repayment_cycle,
                loan_applications.number_of_repayments,
                loan_applications.total_amount,
                COALESCE(users.name, '') AS loan_officer_name,
                COALESCE(users.email, '') AS loan_officer_email,
                SUM(repayments.amount) AS total_amount_due, 
                SUM(repayments.paid) AS total_amount_paid,
                MAX(repayments.repayment_date) AS last_repayment_date
            FROM 
                repayments
            INNER JOIN 
                loan_applications ON repayments.loan_id = loan_applications.id
            INNER JOIN 
                borrowers ON loan_applications.borrower = borrowers.id
            INNER JOIN 
                loan_products ON loan_applications.loan_product = loan_products.id
            LEFT JOIN
                users ON borrowers.loan_officer = users.email
            WHERE 
                repayments.loan_id = ?
            GROUP BY 
                repayments.loan_id, borrowers.full_name, borrowers.mobile, borrowers.unique_number, borrowers.guarantor_name, borrowers.guarantor_phone,
                loan_applications.loan_product, loan_applications.principal, loan_applications.loan_release_date,
                loan_applications.loan_duration, loan_applications.repayment_cycle, loan_applications.number_of_repayments,
                loan_applications.total_amount, users.name";

$stmt_loan = $conn->prepare($sql_loan);
$stmt_loan->bind_param("i", $loanId);
$stmt_loan->execute();
$result_loan = $stmt_loan->get_result();
$loan = $result_loan->fetch_assoc();

if (!$loan) {
    die("Loan details not found.");
}

$totalDue = $loan['total_amount_due'];
$totalPaid = $loan['total_amount_paid'];
$balance = $totalDue - $totalPaid;

$guarantorStmt = $conn->prepare(
    "SELECT g.full_name, g.phone, g.email FROM guarantors g INNER JOIN loan_guarantors lg ON g.id = lg.guarantor_id WHERE lg.loan_id = ? ORDER BY g.full_name"
);
$guarantorStmt->bind_param("i", $loanId);
$guarantorStmt->execute();
$guarantorResult = $guarantorStmt->get_result();
$guarantors = [];
while ($guarantorRow = $guarantorResult->fetch_assoc()) {
    $guarantors[] = [
        'full_name' => $guarantorRow['full_name'] ?? 'N/A',
        'phone' => $guarantorRow['phone'] ?? 'N/A',
        'email' => $guarantorRow['email'] ?? 'N/A'
    ];
}
$guarantorStmt->close();

$overdueStmt = $conn->prepare("SELECT repayment_date, amount, paid FROM repayments WHERE loan_id = ? ORDER BY repayment_date ASC");
$overdueStmt->bind_param("i", $loanId);
$overdueStmt->execute();
$overdueResult = $overdueStmt->get_result();
$daysInArrears = 0;
$overdueAmount = 0;
while ($overdueRow = $overdueResult->fetch_assoc()) {
    $repaymentDate = $overdueRow['repayment_date'];
    $amount = (float) ($overdueRow['amount'] ?? 0);
    $paid = (float) ($overdueRow['paid'] ?? 0);
    $amountOutstanding = max(0, $amount - $paid);

    if (!empty($repaymentDate) && $repaymentDate < date('Y-m-d') && $amountOutstanding > 0) {
        $overdueAmount += $amountOutstanding;
        $today = new DateTime('today');
        $dueDate = new DateTime($repaymentDate);
        if ($dueDate < $today && $daysInArrears < (int) $dueDate->diff($today)->days) {
            $daysInArrears = (int) $dueDate->diff($today)->days;
        }
    }
}
$overdueStmt->close();

    $projectedMaturityDate = getProjectedMaturityDate(
        $loan['loan_release_date'],
        (int) ($loan['loan_duration'] ?? 0),
        $loan['loan_duration_unit'] ?? 'months'
    );
$daysAfterProjectedMaturity = 0;

// Fetch repayment history with adjusted logic
$sql_history = "SELECT 
                    amount, 
                    paid, 
                    repayment_date 
                FROM repayments 
                WHERE loan_id = ? 
                ORDER BY repayment_date ASC";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("i", $loanId);
$stmt_history->execute();
$result_history = $stmt_history->get_result();

$adjusted_history = [];
$remaining_paid = $totalPaid; // Start with the total paid amount

while ($row = $result_history->fetch_assoc()) {
    $amount_due = $row['amount'];
    $paid_for_this_due = min($amount_due, $remaining_paid); // Deduct from the remaining paid amount
    $remaining_paid -= $paid_for_this_due;

    $adjusted_history[] = [
        'amount_due' => $amount_due,
        'paid' => $paid_for_this_due,
        'repayment_date' => date('d/m/Y', strtotime($row['repayment_date'])) // Format date as dd/mm/yyyy
    ];
}

$sql_records = "SELECT id, Amount, PaymentDate 
                FROM payment_date_records 
                WHERE loan_id = ? 
                ORDER BY PaymentDate DESC";
$stmt_records = $conn->prepare($sql_records);
$stmt_records->bind_param("i", $loanId);
$stmt_records->execute();
$result_records = $stmt_records->get_result();

$payment_records = [];
while ($row_records = $result_records->fetch_assoc()) {
    $payment_records[] = $row_records;
}
$result_records->data_seek(0);

$repayment_rows_stmt = $conn->prepare("SELECT amount, paid, repayment_date, repaid_date FROM repayments WHERE loan_id = ? ORDER BY repaid_date ASC, repayment_date ASC");
$repayment_rows_stmt->bind_param("i", $loanId);
$repayment_rows_stmt->execute();
$repayment_rows_result = $repayment_rows_stmt->get_result();
$repayment_rows = [];
while ($repayment_row = $repayment_rows_result->fetch_assoc()) {
    $repayment_rows[] = $repayment_row;
}
$repayment_rows_stmt->close();

$daysAfterProjectedMaturity = calculateDaysOverdueAfterMaturity(
    $projectedMaturityDate,
    $repayment_rows,
    (float) ($totalDue ?? 0)
);

$email_message = '';
$email_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $payment_records_for_email = $payment_records;

    $posted_record_ids = $_POST['record_id'] ?? [];
    $posted_payment_dates = $_POST['payment_date_record'] ?? [];
    $posted_payment_amounts = $_POST['amount_paid_record'] ?? [];
    $posted_new_dates = $_POST['new_payment_date'] ?? [];
    $posted_new_amounts = $_POST['new_amount_paid'] ?? [];

    $payment_records_for_email = [];
    foreach ($posted_record_ids as $index => $record_id) {
        $date_value = isset($posted_payment_dates[$index]) ? $posted_payment_dates[$index] : null;
        $amount_value = isset($posted_payment_amounts[$index]) ? (float) $posted_payment_amounts[$index] : 0.0;

        if (!empty($date_value) || $amount_value > 0) {
            $payment_records_for_email[] = [
                'id' => (int) $record_id,
                'PaymentDate' => $date_value,
                'Amount' => $amount_value,
            ];
        }
    }

    foreach ($posted_new_dates as $index => $date_value) {
        $amount_value = isset($posted_new_amounts[$index]) ? (float) $posted_new_amounts[$index] : 0.0;

        if (!empty($date_value) || $amount_value > 0) {
            $payment_records_for_email[] = [
                'id' => 0,
                'PaymentDate' => $date_value,
                'Amount' => $amount_value,
            ];
        }
    }

    if (empty($payment_records_for_email)) {
        $payment_records_for_email = $payment_records;
    }

    $sender_email = getConfiguredSenderEmail();
    $recipient_email = (!empty($loan['loan_officer_email']) && filter_var($loan['loan_officer_email'], FILTER_VALIDATE_EMAIL))
        ? $loan['loan_officer_email']
        : $sender_email;
    $subject = 'Payment Records - ' . htmlspecialchars($loan['borrower_name']);
    $loan_officer_name = !empty($loan['loan_officer_name']) ? trim($loan['loan_officer_name']) : 'Team';
    $body = '<p>Dear ' . htmlspecialchars($loan_officer_name) . ',</p><p>Please find the attached payment records report for <strong>' . htmlspecialchars($loan['borrower_name']) . '</strong>.</p><p>This report was generated automatically by Inua Premium Services.</p>';

    try {
        $pdf_content = generate_repayment_details_pdf($loan, $guarantors, $adjusted_history, $daysInArrears, $projectedMaturityDate, $daysAfterProjectedMaturity, $payment_records_for_email);
        $filename = 'repayment_details_' . date('Ymd_His') . '.pdf';
        send_pdf_email($recipient_email, $subject, $body, $pdf_content, $filename);
        $email_status = 'success';
        $email_message = 'Repayment details PDF sent successfully to ' . $recipient_email . '.';
    } catch (Exception $e) {
        error_log('Repayment details email send failed: ' . $e->getMessage());
        $email_status = 'danger';
        $email_message = 'Unable to send the repayment details PDF email. Please review the SMTP configuration.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repayment Details</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
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
        .card {
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .card-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #e84545;
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
        .btn-primary, .btn-secondary {
            background-color: #e84545;
            border: none;
            transition: all 0.3s ease-in-out;
        }
        .btn-primary:hover, .btn-secondary:hover {
            background-color: #d43d3d;
        }
        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #e84545;
            margin-bottom: 20px;
            text-align: center;
        }
        .download-btn {
            margin-bottom: 15px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <div class="header">
        <h1>Repayment Details</h1>
    </div>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            <form method="post" class="d-inline-block">
                <input type="hidden" name="send_email" value="1">
                <input type="hidden" name="loanId" value="<?= intval($loanId); ?>">
                <button type="submit" class="btn btn-outline-danger">Send Email</button>
            </form>
        </div>
        <?php if (!empty($email_message)): ?>
            <div class="alert alert-<?= htmlspecialchars($email_status); ?> text-center" role="alert">
                <?= htmlspecialchars($email_message); ?>
            </div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Loan Terms</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <h6 class="fw-bold">Borrower Information</h6>
                        <p class="mb-1"><strong>Borrower:</strong> <?php echo htmlspecialchars($loan['borrower_name']); ?></p>
                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($loan['phone_number']); ?></p>
                        <p class="mb-1"><strong>ID Number:</strong> <?php echo htmlspecialchars($loan['id_number']); ?></p>
                        <p class="mb-1"><strong>Loan Product:</strong> <?php echo htmlspecialchars($loan['loan_product_name']); ?></p>
                        <p class="mb-1"><strong>Guarantor Name:</strong> <?php echo htmlspecialchars(!empty($loan['guarantor_name']) ? $loan['guarantor_name'] : (!empty($guarantors) ? implode(', ', array_map(function($guarantor) { return $guarantor['full_name']; }, $guarantors)) : 'No guarantor recorded.')); ?></p>
                        <p class="mb-1"><strong>Guarantor Phone:</strong> <?php echo htmlspecialchars(!empty($loan['guarantor_phone']) ? $loan['guarantor_phone'] : (!empty($guarantors) ? implode(', ', array_filter(array_map(function($guarantor) { return $guarantor['phone'] ?? null; }, $guarantors))) : 'N/A')); ?></p>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold">Loan Summary</h6>
                        <p class="mb-1"><strong>Principal:</strong> <?php echo number_format($loan['principal_amount'], 2); ?> KES</p>
                        <p class="mb-1"><strong>Total Amount:</strong> <?php echo number_format($totalDue, 2); ?> KES</p>
                        <p class="mb-1"><strong>Total Paid:</strong> <?php echo number_format($totalPaid, 2); ?> KES</p>
                        <p class="mb-1"><strong>Balance:</strong> <?php echo number_format($balance, 2); ?> KES</p>
                        <p class="mb-1"><strong>Release Date:</strong> <?php echo !empty($loan['loan_release_date']) ? date('d/m/Y', strtotime($loan['loan_release_date'])) : 'N/A'; ?></p>
                        <p class="mb-1"><strong>Loan Officer:</strong> <?php echo !empty($loan['loan_officer_name']) ? htmlspecialchars($loan['loan_officer_name']) : 'Unassigned'; ?></p>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold">Arrears Summary</h6>
                        <p class="mb-1"><strong>Arrears Amount:</strong> <?php echo number_format($overdueAmount, 2); ?> KES</p>
                        <p class="mb-1"><strong>Days in Arrears:</strong> <?php echo (int) $daysInArrears; ?></p>
                        <p class="mb-1"><strong>Projected Maturity Date:</strong> <?php echo $projectedMaturityDate ? $projectedMaturityDate->format('d/m/Y') : 'N/A'; ?></p>
                        <p class="mb-1"><strong>Days Overdue:</strong> <?php echo (int) $daysAfterProjectedMaturity; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <h3 class="section-title">Payment Records</h3>
        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success">Payment records saved successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['cleared'])): ?>
            <div class="alert alert-success">Payment record cleared successfully.</div>
        <?php endif; ?>
        <form method="POST" id="paymentRecordsForm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button type="button" id="addPaymentRow" class="btn btn-secondary">Add Payment Row</button>
                <div>
                    <button type="submit" name="save_payment_records" class="btn btn-primary">Save Changes</button>
                    <button type="button" id="downloadPaymentRecords" class="btn btn-primary">Download Payment Records</button>
                </div>
            </div>
            <table class="table table-bordered" id="paymentRecordsTable">
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>Amount Paid</th>
                        <th class="no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_records as $row_records): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="record_id[]" value="<?= intval($row_records['id']); ?>">
                                <input type="date" name="payment_date_record[]" class="form-control" value="<?= !empty($row_records['PaymentDate']) ? date('Y-m-d', strtotime($row_records['PaymentDate'])) : ''; ?>" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="amount_paid_record[]" class="form-control" value="<?= number_format($row_records['Amount'], 2, '.', ''); ?>" required>
                            </td>
                            <td class="text-center no-export">
                                <button type="button" class="btn btn-secondary btn-sm clear-record-btn" data-record-id="<?= intval($row_records['id']); ?>">Clear</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payment_records)): ?>
                        <tr><td colspan="3">No payment records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="newPaymentRows"></div>
            <input type="hidden" name="loanId" value="<?= $loanId; ?>">
        </form>

        <form id="clearRecordForm" method="POST" style="display:none;">
            <input type="hidden" name="action" value="clear">
            <input type="hidden" name="record_id" id="clearRecordId" value="">
        </form>
        <h3 class="section-title">Repayment Schedule</h3>
        <div class="d-flex justify-content-end download-btn">
            <button id="downloadRepaymentHistory" class="btn btn-primary">Download Repayment Schedule</button>
        </div>
        <table class="table table-bordered" id="repaymentHistoryTable">
            <thead>
                <tr>
                    <th>Amount Due</th>
                    <th>Amount Paid</th>
                    <th>Repayment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adjusted_history as $row): ?>
                    <tr>
                        <td><?php echo number_format($row['amount_due'], 2); ?> KES</td>
                        <td><?php echo number_format($row['paid'], 2); ?> KES</td>
                        <td><?php echo htmlspecialchars($row['repayment_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($adjusted_history)): ?>
                    <tr><td colspan="3">No repayment history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <footer class="text-center mt-5">
        <p><em>Powered by AntonTech</em></p>
    </footer>
    <script>
        const loanDetails = {
            borrower: <?= json_encode($loan['borrower_name']); ?>,
            phone: <?= json_encode($loan['phone_number']); ?>,
            idNumber: <?= json_encode($loan['id_number']); ?>,
            loanProduct: <?= json_encode($loan['loan_product_name']); ?>,
            principal: <?= json_encode(number_format($loan['principal_amount'], 2)); ?>,
            totalAmount: <?= json_encode(number_format($totalDue, 2)); ?>,
            totalPaid: <?= json_encode(number_format($totalPaid, 2)); ?>,
            balance: <?= json_encode(number_format($balance, 2)); ?>,
            overdueAmount: <?= json_encode(number_format($overdueAmount, 2)); ?>,
            loanReleaseDate: <?= json_encode(!empty($loan['loan_release_date']) ? date('d/m/Y', strtotime($loan['loan_release_date'])) : 'N/A'); ?>,
            loanOfficer: <?= json_encode(!empty($loan['loan_officer_name']) ? $loan['loan_officer_name'] : 'Unassigned'); ?>
        };
        const guarantorDetails = <?= json_encode($guarantors); ?>;
        const arrearsSummary = {
            daysInArrears: <?= json_encode($daysInArrears); ?>,
            projectedMaturityDate: <?= json_encode($projectedMaturityDate ? $projectedMaturityDate->format('d/m/Y') : 'N/A'); ?>,
            daysAfterProjectedMaturity: <?= json_encode($daysAfterProjectedMaturity); ?>
        };

        document.addEventListener('DOMContentLoaded', function () {
            function generateLoanTermsLines() {
                return [
                    `Borrower: ${loanDetails.borrower}`,
                    `Phone Number: ${loanDetails.phone}`,
                    `ID Number: ${loanDetails.idNumber}`,
                    `Loan Product: ${loanDetails.loanProduct}`,
                    `Principal Amount: ${loanDetails.principal} KES`,
                    `Total Amount: ${loanDetails.totalAmount} KES`,
                    `Total Paid: ${loanDetails.totalPaid} KES`,
                    `Balance: ${loanDetails.balance} KES`,
                    `Loan Release Date: ${loanDetails.loanReleaseDate}`
                ];
            }

            async function loadImageAsDataUrl(url) {
                const response = await fetch(url);
                const blob = await response.blob();
                return await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });
            }

            async function downloadTableAsPDF(tableId, title, includeTerms = false) {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ unit: 'pt', format: 'a4' });
                const pageWidth = doc.internal.pageSize.getWidth();
                const margin = 40;
                let cursorY = 48;

                doc.setTextColor(33, 37, 41);
                doc.setFontSize(18);
                doc.setFont('helvetica', 'bold');

                if (includeTerms) {
                    try {
                        const logoDataUrl = await loadImageAsDataUrl('/assets/img/logo.png');
                        doc.addImage(logoDataUrl, 'PNG', margin, 12, 90, 28);
                    } catch (error) {
                        // Ignore logo errors and continue with the PDF
                    }

                    cursorY = 50;
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Loan Terms', margin + 220, cursorY, { align: 'center' });
                    cursorY += 16;

                    const sectionGap = 10;
                    const lineHeight = 12;
                    const colWidth = (pageWidth - margin * 2) / 3;
                    const leftX = margin;
                    const midX = margin + colWidth;
                    const rightX = margin + colWidth * 2;

                    doc.setFontSize(9);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Borrower Information', leftX, cursorY);
                    doc.text('Loan Summary', midX, cursorY);
                    doc.text('Arrears Summary', rightX, cursorY);
                    cursorY += sectionGap;

                    doc.setFont('helvetica', 'normal');
                    const borrowerInfo = [
                        `Borrower: ${loanDetails.borrower}`,
                        `Phone: ${loanDetails.phone}`,
                        `ID Number: ${loanDetails.idNumber}`,
                        `Loan Product: ${loanDetails.loanProduct}`,
                        `Guarantor: ${guarantorDetails.length > 0 ? guarantorDetails.map(g => g.full_name).join(', ') : 'No guarantor recorded.'}`
                    ];
                    const guarantorContact = guarantorDetails.length > 0
                        ? guarantorDetails.map(g => `Phone: ${g.phone}${g.email ? ' • ' + g.email : ''}`).join(' | ')
                        : '';
                    if (guarantorContact) borrowerInfo.push(guarantorContact);

                    const loanSummary = [
                        `Principal: ${loanDetails.principal} KES`,
                        `Total Amount: ${loanDetails.totalAmount} KES`,
                        `Total Paid: ${loanDetails.totalPaid} KES`,
                        `Balance: ${loanDetails.balance} KES`,
                        `Release Date: ${loanDetails.loanReleaseDate}`,
                        `Loan Officer: ${loanDetails.loanOfficer}`
                    ];

                    const arrearsInfo = [
                        `Arrears Amount: ${loanDetails.overdueAmount} KES`,
                        `Days in arrears: ${arrearsSummary.daysInArrears}`,
                        `Projected maturity date: ${arrearsSummary.projectedMaturityDate}`,
                        `Days overdue: ${arrearsSummary.daysAfterProjectedMaturity}`
                    ];

                    const maxRows = Math.max(borrowerInfo.length, loanSummary.length, arrearsInfo.length);
                    for (let i = 0; i < maxRows; i++) {
                        const y = cursorY + i * lineHeight;
                        if (borrowerInfo[i]) {
                            doc.text(borrowerInfo[i], leftX, y);
                        }
                        if (loanSummary[i]) {
                            doc.text(loanSummary[i], midX, y);
                        }
                        if (arrearsInfo[i]) {
                            doc.text(arrearsInfo[i], rightX, y);
                        }
                    }
                    cursorY += maxRows * lineHeight + 10;
                }

                const table = document.getElementById(tableId);
                const headers = ['Payment Date', 'Amount Paid'];
                const rows = Array.from(table.querySelectorAll('tbody tr')).map(row => {
                    const dateInput = row.querySelector('input[name="payment_date_record[]"], input[name="new_payment_date[]"]');
                    const amountInput = row.querySelector('input[name="amount_paid_record[]"], input[name="new_amount_paid[]"]');
                    const paymentDate = dateInput && dateInput.value ? new Date(dateInput.value).toLocaleDateString('en-GB') : 'N/A';
                    const amountPaid = amountInput && amountInput.value ? parseFloat(amountInput.value).toFixed(2) : '0.00';
                    return [paymentDate, `${amountPaid} KES`];
                }).filter(row => row[0] !== 'N/A' || row[1] !== '0.00 KES');

                doc.setFontSize(12);
                doc.setFont('helvetica', 'bold');
                doc.text('Payment History', margin, cursorY);
                cursorY += 16;

                doc.autoTable({
                    startY: cursorY,
                    head: [headers],
                    body: rows,
                    theme: 'grid',
                    headStyles: { fillColor: [232, 69, 69], textColor: [255, 255, 255], fontStyle: 'bold' },
                    styles: { fontSize: 9, cellPadding: 5 },
                    alternateRowStyles: { fillColor: [248, 249, 250] },
                    margin: { left: margin, right: margin }
                });

                const footerText = `Prepared for ${loanDetails.borrower} • Loan Officer: ${loanDetails.loanOfficer}`;
                doc.setFontSize(9);
                doc.setFont('helvetica', 'italic');
                doc.text(footerText, pageWidth / 2, doc.internal.pageSize.getHeight() - 20, { align: 'center' });

                doc.save(`${title.replace(/\s+/g, '_')}.pdf`);
            }

            async function downloadPaymentRecordsAsPDF() {
                await downloadTableAsPDF('paymentRecordsTable', 'Payment Records', true);
            }

            document.getElementById('addPaymentRow').addEventListener('click', function () {
                const tbody = document.querySelector('#paymentRecordsTable tbody');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="hidden" name="record_id[]" value="0">
                        <input type="date" name="new_payment_date[]" class="form-control" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" name="new_amount_paid[]" class="form-control" placeholder="0.00" required>
                    </td>
                    <td class="text-center no-export">
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();">Remove</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            document.querySelectorAll('.clear-record-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    const recordId = this.getAttribute('data-record-id');
                    document.getElementById('clearRecordId').value = recordId;
                    document.getElementById('clearRecordForm').submit();
                });
            });

            document.getElementById('downloadPaymentRecords').addEventListener('click', async function () {
                await downloadPaymentRecordsAsPDF();
            });

            document.getElementById('downloadRepaymentHistory').addEventListener('click', function () {
                downloadTableAsPDF('repaymentHistoryTable', 'Repayment Schedule', false);
            });
        });
    </script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php 
$stmt_loan->close();
$stmt_history->close();
$stmt_records->close();
$conn->close();
?>
