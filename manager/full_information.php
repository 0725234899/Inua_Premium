<?php
include '../includes/functions.php';
include 'db.php';

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

function normalizeDurationUnit($loan_duration_unit) {
    $unit = strtolower(trim((string) $loan_duration_unit));
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
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    $maturity_date = new DateTime($loan_release_date);

    switch ($loan_duration_unit) {
        case 'days':
            $interval = new DateInterval('P' . (int) $loan_duration . 'D');
            break;
        case 'weeks':
            $interval = new DateInterval('P' . (int) $loan_duration . 'W');
            break;
        case 'months':
            $interval = new DateInterval('P' . (int) $loan_duration . 'M');
            break;
        case 'years':
            $interval = new DateInterval('P' . (int) $loan_duration . 'Y');
            break;
        default:
            $interval = new DateInterval('P' . (int) $loan_duration . 'M');
            break;
    }

    $maturity_date->add($interval);
    return $maturity_date;
}

function getRepaymentScheduleDates($loan_release_date, $loan_duration, $loan_duration_unit, $repayment_cycle) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    $maturity_date = getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit);

    if ($repayment_cycle === 'once') {
        switch ($loan_duration_unit) {
            case 'days':
            case 'weeks':
                $scheduleCycle = 'weekly';
                break;
            case 'months':
                $scheduleCycle = 'monthly';
                break;
            case 'years':
                $scheduleCycle = 'yearly';
                break;
            default:
                $scheduleCycle = 'monthly';
                break;
        }

        $scheduleDates = [];
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

    $scheduleDates = [];
    $current = new DateTime($loan_release_date);
    $interval = DateInterval::createFromDateString(getCycleInterval($repayment_cycle));

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

function getProjectedMaturityDateForLoan($conn, $loan) {
    if (!empty($loan['projected_maturity_date'])) {
        return $loan['projected_maturity_date'];
    }

    $stmt = $conn->prepare("SELECT MAX(repayment_date) AS projected_maturity_date FROM repayments WHERE loan_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $loan['loan_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!empty($row['projected_maturity_date'])) {
            return $row['projected_maturity_date'];
        }
    }

    return getProjectedMaturityDate(
        $loan['loan_release_date'],
        (int) ($loan['loan_duration'] ?? 0),
        $loan['loan_duration_unit'] ?? 'months',
        $loan['repayment_cycle'] ?? 'once'
    );
}

function getProjectedMaturityDate($releaseDate, $loan_duration, $loan_duration_unit, $repayment_cycle = null) {
    if (empty($releaseDate)) {
        return null;
    }

    $repayment_cycle = $repayment_cycle ?? 'once';
    $dates = getRepaymentScheduleDates($releaseDate, (int)$loan_duration, $loan_duration_unit, $repayment_cycle);
    if (empty($dates)) {
        return null;
    }

    $last = end($dates);
    try {
        return $last;
    } catch (Exception $e) {
        return null;
    }
}

function calculateDaysOverdueAfterMaturity($conn, $loanId, $projectedMaturityDate, $totalDue) {
    if (empty($projectedMaturityDate) || (float) $totalDue <= 0) {
        return 0;
    }

    $projected = new DateTime($projectedMaturityDate);
    $today = new DateTime('today');
    if ($projected >= $today) {
        return 0;
    }

    $maturityDate = clone $projected;
    $cumulativePaid = 0.0;
    $clearingDate = null;

    $stmt = $conn->prepare("SELECT repayment_date, repaid_date, paid FROM repayments WHERE loan_id = ? ORDER BY repayment_date ASC");
    if ($stmt) {
        $stmt->bind_param('i', $loanId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
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

        $stmt->close();
    }

    if ($clearingDate === null) {
        return 0;
    }

    return max(0, (int) $maturityDate->diff($clearingDate)->days);
}

$loanId = isset($_GET['loanId']) ? intval($_GET['loanId']) : 0;
if ($loanId <= 0) {
    die('Invalid loan ID.');
}

$loanStmt = $conn->prepare("SELECT borrower FROM loan_applications WHERE id = ? LIMIT 1");
$loanStmt->bind_param('i', $loanId);
$loanStmt->execute();
$loanResult = $loanStmt->get_result();
$loanRow = $loanResult->fetch_assoc();
$loanStmt->close();

if (!$loanRow || empty($loanRow['borrower'])) {
    die('Loan not found or borrower missing.');
}

$borrowerId = intval($loanRow['borrower']);

$borrowerStmt = $conn->prepare(
    "SELECT b.*, u.name AS loan_officer_name
     FROM borrowers b
     LEFT JOIN users u ON b.loan_officer = u.email
     WHERE b.id = ? LIMIT 1"
);
$borrowerStmt->bind_param('i', $borrowerId);
$borrowerStmt->execute();
$borrowerResult = $borrowerStmt->get_result();
$borrower = $borrowerResult->fetch_assoc();
$borrowerStmt->close();

if (!$borrower) {
    die('Borrower record not found.');
}

$loanListStmt = $conn->prepare(
    "SELECT
         l.id AS loan_id,
         l.principal,
         l.loan_status,
         l.loan_release_date,
         l.loan_duration,
         l.loan_duration_unit,
         l.repayment_cycle,
         l.processing_fee,
         COALESCE(SUM(r.amount), 0) AS total_amount_due,
         COALESCE(p.total_paid, 0) AS total_paid
     FROM loan_applications l
     LEFT JOIN repayments r ON l.id = r.loan_id
     LEFT JOIN (
         SELECT loan_id, SUM(Amount) AS total_paid
         FROM payment_date_records
         GROUP BY loan_id
     ) p ON l.id = p.loan_id
     WHERE l.borrower = ?
     GROUP BY l.id
     ORDER BY l.loan_release_date DESC"
);
$loanListStmt->bind_param('i', $borrowerId);
$loanListStmt->execute();
$loanListResult = $loanListStmt->get_result();
$loans = [];
$totalPrincipal = 0.0;
$totalProcessingFee = 0.0;
$totalInterest = 0.0;
$totalPaidAcrossLoans = 0.0;
$totalLoanAmount = 0.0;
$totalOverpayment = 0.0;

while ($row = $loanListResult->fetch_assoc()) {
    $row['principal'] = (float) ($row['principal'] ?? 0);
    $row['total_amount_due'] = (float) ($row['total_amount_due'] ?? 0);
    $row['processing_fee'] = (float) ($row['processing_fee'] ?? 0);
    $row['total_paid'] = (float) ($row['total_paid'] ?? 0);
    $row['projected_maturity_date'] = getProjectedMaturityDateForLoan($conn, $row);
    $row['days_overdue'] = calculateDaysOverdueAfterMaturity($conn, $row['loan_id'], $row['projected_maturity_date'], $row['total_amount_due']);

    $totalPrincipal += $row['principal'];
    $totalProcessingFee += $row['processing_fee'];
    $totalInterest += max(0, $row['total_amount_due'] - $row['principal']);
    $totalPaidAcrossLoans += $row['total_paid'];
    $totalLoanAmount += $row['total_amount_due'];
    $totalOverpayment += max(0, $row['total_paid'] - $row['total_amount_due']);

    $loans[] = $row;
}
$loanListStmt->close();

//$totalOverpayment = max(0, $totalPaidAcrossLoans - $totalLoanAmount);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Loan History</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
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
            margin: 0;
            font-size: 1.75rem;
        }
        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-radius: 10px;
        }
        .table thead th {
            background-color: #f1f1f1;
        }
        .loan-status {
            text-transform: capitalize;
        }
        .loan-status.approved { color: #198754; }
        .loan-status.pending { color: #0d6efd; }
        .loan-status.rejected { color: #dc3545; }
        .loan-status.rolled_over { color: #fd7e14; }
        .loan-status.active { color: #0d6efd; }
        .loan-status.closed { color: #6c757d; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Borrower Loan History</h1>
                <p class="mb-0">Showing all loans for <strong><?= htmlspecialchars($borrower['full_name'] ?? 'Unknown'); ?></strong>.</p>
                <p class="text-muted">Borrower ID: <?= intval($borrowerId); ?> | Phone: <?= htmlspecialchars($borrower['mobile'] ?? 'N/A'); ?></p>
            </div>
            <a href="repayment_details.php?loanId=<?= intval($loanId); ?>" class="btn btn-secondary">Back to Loan</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card p-3 mb-3">
                    <h5>Borrower Details</h5>
                    <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($borrower['full_name']); ?></p>
                    <p class="mb-1"><strong>ID Number:</strong> <?= htmlspecialchars($borrower['unique_number'] ?? 'N/A'); ?></p>
                    <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($borrower['mobile'] ?? 'N/A'); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($borrower['email'] ?? 'N/A'); ?></p>
                    <p class="mb-1"><strong>Loan Officer:</strong> <?= htmlspecialchars($borrower['loan_officer_name'] ?? 'Unassigned'); ?></p>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card p-3 mb-3">
                    <h5>Loan Summary</h5>
                    <p class="mb-1"><strong>Total Loans:</strong> <?= count($loans); ?></p>
                    <p class="mb-1"><strong>Total Principal:</strong> KSh <?= number_format($totalPrincipal, 2); ?></p>
                    <p class="mb-1"><strong>Total Processing Fee:</strong> KSh <?= number_format($totalProcessingFee, 2); ?></p>
                    <p class="mb-1"><strong>Total Interest:</strong> KSh <?= number_format($totalInterest, 2); ?></p>
                    <p class="mb-1"><strong>Total Overpayment:</strong> KSh <?= number_format($totalOverpayment, 2); ?></p>
                    <p class="mb-1"><strong>Current loan:</strong> <?= htmlspecialchars($loans[0]['loan_id'] ?? $loanId); ?></p>
                </div>
            </div>
        </div>

        <div class="card p-3">
            <h4 class="mb-3">Loans for this Borrower</h4>
            <?php if (empty($loans)): ?>
                <div class="alert alert-warning">No loans found for this borrower.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Loan ID</th>
                                <th scope="col">Principal</th>
                                <th scope="col">Total Amount</th>
                                <th scope="col">Total Paid</th>
                                <th scope="col">Balance</th>
                                <th scope="col">Release Date</th>
                                <th scope="col">Projected Maturity Date</th>
                                <th scope="col">Days Overdue</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                                <?php
                                    $balance = max(0, (float) $loan['total_amount_due'] - (float) $loan['total_paid']);
                                    $statusClass = strtolower(str_replace(' ', '_', $loan['loan_status'] ?? ''));
                                    $releaseDate = !empty($loan['loan_release_date']) ? date('d/m/Y', strtotime($loan['loan_release_date'])) : 'N/A';
                                    $projectedMaturity = !empty($loan['projected_maturity_date']) ? date('d/m/Y', strtotime($loan['projected_maturity_date'])) : 'N/A';
                                ?>
                                        <tr>
                                    <td><a href="repayment_details.php?loanId=<?= intval($loan['loan_id']); ?>"><?= intval($loan['loan_id']); ?></a></td>
                                    <td>KSh <?= number_format((float) $loan['principal'], 2); ?></td>
                                    <td>KSh <?= number_format((float) $loan['total_amount_due'], 2); ?></td>
                                    <td>KSh <?= number_format((float) $loan['total_paid'], 2); ?></td>
                                    <td>KSh <?= number_format($balance, 2); ?></td>
                                    <td><?= htmlspecialchars($releaseDate); ?></td>
                                    <td><?= htmlspecialchars($projectedMaturity); ?></td>
                                    <td><?= intval($loan['days_overdue']); ?></td>
                                    <td class="loan-status <?= htmlspecialchars($statusClass); ?>"><?= htmlspecialchars($loan['loan_status'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="repayment_details.php?loanId=<?= intval($loan['loan_id']); ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
