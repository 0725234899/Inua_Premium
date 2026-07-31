<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Applications</title>
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

        .header .navmenu ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
        }

        .header .navmenu ul li {
            margin-right: 20px;
        }

        .header .navmenu ul li a {
            color: #ffffff;
            text-decoration: none;
        }

        .header .navmenu ul li a.active,
        .header .navmenu ul li a:hover {
            color: #e84545;
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

        .sidebar .nav-item .nav-link {
            color: #3a3939;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }

        .sidebar .nav-item .nav-link.active,
        .sidebar .nav-item .nav-link:hover {
            color: #e84545;
        }

        .main {
            margin-left: 270px;
            padding: 20px;
        }

        .container {
            margin-top: 20px;
        }

        table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: #fff;
        }

        table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
        }

        table tbody tr {
            border-bottom: 1px solid #dee2e6;
        }

        table tbody td {
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <?php 
    include '../includes/functions.php';
    include 'includes/header.php'; 
    ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <?php
    include 'db.php';

    function updateLoanStatus($loan_id, $status) {
        global $conn;
        $sql = "UPDATE loan_applications SET loan_status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $loan_id);
        return $stmt->execute();
    }

    function getBorrowers($conn) {
        $borrowers = array();
        $sql = "SELECT id, full_name FROM borrowers ORDER BY full_name";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $borrowers[] = $row;
            }
        }

        return $borrowers;
    }

    function calculateLoanDetails($principal, $loan_interest_percentage, $loan_duration, $loan_duration_unit, $interest_method, $interest_calculation, $repayment_cycle, $processing_fee, $registration_fee) {
        $duration_in_weeks = 0;

        switch ($loan_duration_unit) {
            case 'days':
                $duration_in_weeks = $loan_duration / 7;
                break;
            case 'weeks':
                $duration_in_weeks = $loan_duration;
                break;
            case 'months':
                $duration_in_weeks = $loan_duration * 4;
                break;
            case 'years':
                $duration_in_weeks = $loan_duration * 52;
                break;
        }

        $number_of_repayments = 0;
        switch ($repayment_cycle) {
            case 'daily':
                $number_of_repayments = $duration_in_weeks * 7;
                break;
            case 'weekly':
                $number_of_repayments = $duration_in_weeks;
                break;
            case 'monthly':
                $number_of_repayments = $duration_in_weeks / 4;
                break;
            case 'yearly':
                $number_of_repayments = $duration_in_weeks / 52;
                break;
            case 'once':
                $number_of_repayments = 1;
                break;
        }

        $total_interest = 0;
        switch ($interest_method) {
            case 'flat_rate':
                $total_interest = ($principal * $loan_interest_percentage * $loan_duration) / 100;
                break;
            case 'percentage':
                $interest_per_period = $principal * ($loan_interest_percentage / 100);
                switch ($interest_calculation) {
                    case 'weekly':
                        $total_interest = $interest_per_period * $duration_in_weeks;
                        break;
                    case 'monthly':
                        $total_interest = $interest_per_period * ($duration_in_weeks / 4);
                        break;
                    case 'yearly':
                        $total_interest = $interest_per_period * ($duration_in_weeks / 52);
                        break;
                }
                break;
            case 'fixed_amount':
                $total_interest = $loan_interest_percentage * $number_of_repayments;
                break;
        }

        $total_amount_inclusive = $principal + $total_interest + $processing_fee + $registration_fee;
        $total_amount = $principal + $total_interest;
        $repayment_amount = $number_of_repayments > 0 ? $total_amount / $number_of_repayments : 0;

        return array(
            'number_of_repayments' => round($number_of_repayments, 2),
            'total_amount' => round($total_amount, 2),
            'total_amount_inclusive' => round($total_amount_inclusive, 2),
            'repayment_amount' => round($repayment_amount, 2),
            'total_interest' => round($total_interest, 2)
        );
    }

    function generateRepaymentSchedule($conn, $loan_id, $principal_amount, $interest_amount, $repayment_cycle, $number_of_repayments, $loan_release_date) {
        $start_date = new DateTime($loan_release_date);

        for ($i = 1; $i <= $number_of_repayments; $i++) {
            $schedule_date = clone $start_date;
            $schedule_date->modify('+' . getCycleInterval($repayment_cycle));

            $repayment_amount = calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments);

            $sql = "INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return false;
            }

            $repayment_date = $schedule_date->format('Y-m-d');
            $stmt->bind_param("iss", $loan_id, $repayment_date, $repayment_amount);
            if (!$stmt->execute()) {
                return false;
            }

            $start_date = $schedule_date;
        }

        return true;
    }

    function calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments) {
        $total_amount = $principal_amount + $interest_amount;
        return $number_of_repayments > 0 ? $total_amount / $number_of_repayments : 0;
    }

    function syncLoanRepaymentSchedule($conn, $loan_id) {
        $loanStmt = $conn->prepare("SELECT principal, total_amount, loan_interest, repayment_cycle, loan_release_date, loan_duration, loan_duration_unit, number_of_repayments FROM loan_applications WHERE id = ?");
        if (!$loanStmt) {
            return false;
        }

        $loanStmt->bind_param("i", $loan_id);
        $loanStmt->execute();
        $loanRow = $loanStmt->get_result()->fetch_assoc();
        $loanStmt->close();

        if (!$loanRow) {
            return false;
        }

        $principalAmount = (float) ($loanRow['principal'] ?? 0);
        $interestAmount = max(0, ((float) ($loanRow['total_amount'] ?? 0)) - $principalAmount);
        $repaymentCycle = $loanRow['repayment_cycle'] ?? 'monthly';
        $numberOfRepayments = (int) ($loanRow['number_of_repayments'] ?? 0);
        $loanReleaseDate = $loanRow['loan_release_date'];
        $loanDuration = (int) ($loanRow['loan_duration'] ?? 0);
            // Default unit
            $loanDurationUnit = 'months';

            // Safely detect if the column exists and read it if present
            try {
                $colCheck = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_applications' AND COLUMN_NAME = 'loan_duration_unit'");
                if ($colCheck) {
                    $colCheck->execute();
                    $colRes = $colCheck->get_result()->fetch_assoc();
                    if ($colRes && (int)$colRes['c'] > 0) {
                        $unitStmt = $conn->prepare("SELECT loan_duration_unit FROM loan_applications WHERE id = ?");
                        if ($unitStmt) {
                            $unitStmt->bind_param("i", $loan_id);
                            $unitStmt->execute();
                            $unitRow = $unitStmt->get_result()->fetch_assoc();
                            if ($unitRow && !empty($unitRow['loan_duration_unit'])) {
                                $loanDurationUnit = $unitRow['loan_duration_unit'];
                            }
                            $unitStmt->close();
                        }
                    }
                    $colCheck->close();
                }
            } catch (Exception $e) {
                // ignore and proceed with default unit
            }

        return rebuildRepaymentSchedule(
            $conn,
            $loan_id,
            $principalAmount,
            $interestAmount,
            $repaymentCycle,
            $numberOfRepayments,
            $loanReleaseDate,
            $loanDuration,
            $loanDurationUnit
        );
    }

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

    function getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit) {
        $maturity_date = new DateTime($loan_release_date);

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
                break;
        }

        return $maturity_date;
    }

    function rebuildRepaymentSchedule($conn, $loan_id, $principal_amount, $interest_amount, $repayment_cycle, $number_of_repayments, $loan_release_date, $loan_duration, $loan_duration_unit) {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("DELETE FROM repayments WHERE loan_id = ?");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();

            $start_date = new DateTime($loan_release_date);

            if ($repayment_cycle === 'once') {
                $maturity_date = getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit);
                $repayment_amount = calculateRepaymentAmount($principal_amount, $interest_amount, 1);
                $repayment_date = $maturity_date->format('Y-m-d');
                $stmt = $conn->prepare("INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (?, ?, ?)");
                $stmt->bind_param("isd", $loan_id, $repayment_date, $repayment_amount);
                $stmt->execute();
            } else {
                for ($i = 1; $i <= $number_of_repayments; $i++) {
                    $schedule_date = clone $start_date;
                    $schedule_date->modify('+' . getCycleInterval($repayment_cycle));

                    $repayment_amount = calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments);
                    $repayment_date = $schedule_date->format('Y-m-d');
                    $stmt = $conn->prepare("INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("isd", $loan_id, $repayment_date, $repayment_amount);
                    $stmt->execute();

                    $start_date = $schedule_date;
                }
            }

            $paymentRecordsStmt = $conn->prepare("SELECT PaymentDate, Amount FROM payment_date_records WHERE loan_id = ? ORDER BY PaymentDate ASC, id ASC");
            $paymentRecordsStmt->bind_param("i", $loan_id);
            $paymentRecordsStmt->execute();
            $paymentRecordsResult = $paymentRecordsStmt->get_result();

            $paymentRecords = [];
            while ($paymentRecord = $paymentRecordsResult->fetch_assoc()) {
                $paymentRecords[] = $paymentRecord;
            }
            $paymentRecordsStmt->close();

            $repaymentRowsStmt = $conn->prepare("SELECT id, amount, paid FROM repayments WHERE loan_id = ? ORDER BY repayment_date ASC");
            $repaymentRowsStmt->bind_param("i", $loan_id);
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

    function deleteLoanAndRepayments($conn, $loan_id) {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("DELETE FROM repayments WHERE loan_id = ?");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM loan_applications WHERE id = ?");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }

    $borrowers = getBorrowers($conn);
    $loanProducts = getLoanProducts();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $loan_id = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0;
        $action = $_POST['action'] ?? '';

        if ($action === 'approve' || $action === 'deny') {
            $status = ($action === 'approve') ? 'approved' : 'declined';
            $scheduleSynced = true;

            if ($action === 'approve') {
                $scheduleSynced = syncLoanRepaymentSchedule($conn, $loan_id);
            }

            if ($scheduleSynced && updateLoanStatus($loan_id, $status)) {
                echo "<div class='alert alert-success'>Loan $action successfully.</div>";
            } else {
                echo "<div class='alert alert-danger'>Failed to $action the loan.</div>";
            }
        } elseif ($action === 'clear') {
            if (deleteLoanAndRepayments($conn, $loan_id)) {
                echo "<div class='alert alert-success'>Loan application and repayment records cleared successfully.</div>";
            } else {
                echo "<div class='alert alert-danger'>Failed to clear the loan application.</div>";
            }
        } elseif ($action === 'edit') {
            $borrower = $_POST['borrower'] ?? null;
            $loan_product = $_POST['loan_product'] ?? null;
            $principal = (float)($_POST['principal'] ?? 0);
            $loan_release_date = $_POST['loan_release_date'] ?? null;
            $interest_method = $_POST['interest_method'] ?? 'flat_rate';
            $interest_calculation = $_POST['interest_calculation'] ?? 'monthly';
            $loan_interest_percentage = (float)($_POST['loan_interest_percentage'] ?? 0);
            $loan_duration = (int)($_POST['loan_duration'] ?? 0);
            $loan_duration_unit = $_POST['loan_duration_unit'] ?? 'months';
            $repayment_cycle = $_POST['repayment_cycle'] ?? 'monthly';
            $processing_fee = (float)($_POST['processing_fee'] ?? 0);
            $registration_fee = (float)($_POST['registration_fee'] ?? 0);
            $loanDetails = calculateLoanDetails($principal, $loan_interest_percentage, $loan_duration, $loan_duration_unit, $interest_method, $interest_calculation, $repayment_cycle, $processing_fee, $registration_fee);
            $number_of_repayments = isset($_POST['number_of_repayments']) ? (int)round((float)$_POST['number_of_repayments']) : (int)round($loanDetails['number_of_repayments']);
            $total_amount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : $loanDetails['total_amount'];
            $total_amount_inclusive = isset($_POST['total_amount_inclusive']) ? (float)$_POST['total_amount_inclusive'] : $loanDetails['total_amount_inclusive'];
            $total_interest = $loanDetails['total_interest'];

            $statusRow = $conn->query("SELECT loan_status FROM loan_applications WHERE id = $loan_id");
            $currentStatus = 'pending';
            if ($statusRow && $statusRow->num_rows > 0) {
                $current = $statusRow->fetch_assoc();
                $currentStatus = $current['loan_status'];
            }

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE loan_applications SET borrower = ?, loan_product = ?, principal = ?, loan_release_date = ?, interest = ?, interest_method = ?, loan_interest = ?, loan_duration = ?, loan_duration_unit = ?, interest_calculation = ?, repayment_cycle = ?, number_of_repayments = ?, processing_fee = ?, registration_fee = ?, loan_status = ?, total_amount = ?, total_amount_inclusive = ? WHERE id = ?");
                $stmt->bind_param("iidsdsdisssiddsddi", $borrower, $loan_product, $principal, $loan_release_date, $loan_interest_percentage, $interest_method, $loan_interest_percentage, $loan_duration, $loan_duration_unit, $interest_calculation, $repayment_cycle, $number_of_repayments, $processing_fee, $registration_fee, $currentStatus, $total_amount, $total_amount_inclusive, $loan_id);
                $stmt->execute();

                $scheduleUpdated = rebuildRepaymentSchedule($conn, $loan_id, $principal, $total_interest, $repayment_cycle, $number_of_repayments, $loan_release_date, $loan_duration, $loan_duration_unit);
                if ($scheduleUpdated) {
                    $conn->commit();
                    echo "<div class='alert alert-success'>Loan application updated successfully and repayment schedule was refreshed.</div>";
                } else {
                    $conn->rollback();
                    echo "<div class='alert alert-danger'>Loan application updated, but repayment schedule refresh failed.</div>";
                }
            } catch (Exception $e) {
                $conn->rollback();
                echo "<div class='alert alert-danger'>Failed to update the loan application.</div>";
            }
        } elseif ($action === 'rollover') {
            $rollover_date = $_POST['rollover_date'] ?? null;
            $rollover_duration = (int)($_POST['rollover_duration'] ?? 0);
            $rollover_duration_unit = $_POST['rollover_duration_unit'] ?? 'months';

            $loanRow = $conn->query("SELECT * FROM loan_applications WHERE id = $loan_id");
            $loanRow = $loanRow ? $loanRow->fetch_assoc() : null;
            $paidRow = $conn->query("SELECT COALESCE(SUM(paid), 0) AS total_paid FROM repayments WHERE loan_id = $loan_id")->fetch_assoc();
            $total_paid = (float)($paidRow['total_paid'] ?? 0);

            if ($loanRow) {
                $interest_amount = (float)($loanRow['total_amount'] - $loanRow['principal']);
                $remaining_balance = max(0, $loanRow['total_amount'] - $total_paid);

                if ($total_paid >= $interest_amount && $remaining_balance > 0 && $rollover_date !== null && $rollover_duration > 0) {
                    $newLoanDetails = calculateLoanDetails($remaining_balance, $loanRow['loan_interest'], $rollover_duration, $rollover_duration_unit, $loanRow['interest_method'], $loanRow['interest_calculation'] ?? 'monthly', $loanRow['repayment_cycle'], 0, 0);
                    $newPrincipal = $remaining_balance;
                    $borrowerId = $loanRow['borrower'];
                    $loanProductName = $loanRow['loan_product'];
                    $newTotalAmount = $newLoanDetails['total_amount'];
                    $newTotalAmountInclusive = $newLoanDetails['total_amount_inclusive'];
                    $newRepayments = (int)round($newLoanDetails['number_of_repayments']);
                    $rolloverInterest = (float)$loanRow['loan_interest'];
                    $rolloverInterestMethod = $loanRow['interest_method'];
                    $processingFee = 0.0;
                    $registrationFee = 0.0;
                    $rolloverRepaymentCycle = $loanRow['repayment_cycle'];
                    $rolloverInterestCalculation = $loanRow['interest_calculation'] ?? 'monthly';

                    $conn->begin_transaction();
                    $rolloverError = false;

                    $stmt = $conn->prepare("INSERT INTO loan_applications (borrower, loan_product, principal, loan_release_date, interest, interest_method, loan_interest, loan_duration, loan_duration_unit, interest_calculation, repayment_cycle, number_of_repayments, processing_fee, registration_fee, loan_status, total_amount, total_amount_inclusive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?)");
                    if (!$stmt) {
                        $rolloverError = true;
                    } else {
                        $stmt->bind_param("iidsdsdisssidddd", $borrowerId, $loanProductName, $newPrincipal, $rollover_date, $rolloverInterest, $rolloverInterestMethod, $rolloverInterest, $rollover_duration, $rollover_duration_unit, $rolloverInterestCalculation, $rolloverRepaymentCycle, $newRepayments, $processingFee, $registrationFee, $newTotalAmount, $newTotalAmountInclusive);

                        if ($stmt->execute()) {
                            $newLoanId = $stmt->insert_id;
                            $scheduleCreated = generateRepaymentSchedule($conn, $newLoanId, $newPrincipal, $newTotalAmount - $newPrincipal, $rolloverRepaymentCycle, $newRepayments, $rollover_date);
                            if ($scheduleCreated === false) {
                                $rolloverError = true;
                            }

                            $updateOldLoanStmt = $conn->prepare("UPDATE loan_applications SET loan_status = 'rolled_over' WHERE id = ?");
                            if ($updateOldLoanStmt) {
                                $updateOldLoanStmt->bind_param("i", $loan_id);
                                if (!$updateOldLoanStmt->execute()) {
                                    $rolloverError = true;
                                }
                                $updateOldLoanStmt->close();
                            } else {
                                $rolloverError = true;
                            }
                        } else {
                            $rolloverError = true;
                        }
                    }

                    if ($rolloverError) {
                        $conn->rollback();
                        echo "<div class='alert alert-danger'>Failed to create rollover loan. Please try again and verify loan details.</div>";
                    } else {
                        $conn->commit();
                        echo "<div class='alert alert-success'>Rollover created successfully and new loan schedule generated.</div>";
                    }
                } else {
                    echo "<div class='alert alert-danger'>Rollover conditions were not met or invalid rollover details provided.</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>Loan not found for rollover.</div>";
            }
        }
    }

    function getLoans($search = '') {
        global $conn;
        $loans = array();

        $sql = "SELECT 
                    l.id AS loan_id, 
                    l.borrower,
                    b.full_name AS borrower_name, 
                    b.mobile AS borrower_mobile,
                    l.principal, 
                    l.loan_duration AS duration, 
                    l.loan_duration_unit,
                    l.interest_calculation,
                    l.number_of_repayments AS repayments_count, 
                    l.total_amount,
                    l.loan_product,
                    l.loan_release_date,
                    l.interest_method,
                    l.loan_interest,
                    l.repayment_cycle,
                    l.processing_fee,
                    l.registration_fee,
                    l.total_amount_inclusive,
                    l.loan_status AS status,
                    COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0) AS total_paid,
                    (l.total_amount - l.principal) AS interest_amount
                FROM loan_applications l 
                INNER JOIN borrowers b ON l.borrower = b.id";

        if ($search !== '') {
            $sql .= " WHERE (LOWER(COALESCE(b.full_name, '')) LIKE ? OR LOWER(COALESCE(b.mobile, '')) LIKE ?)";
        }

        $sql .= " ORDER BY CASE WHEN l.loan_status = 'pending' OR l.loan_status = '0' THEN 0 ELSE 1 END, l.id DESC";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            echo "Error preparing query: " . $conn->error;
            return $loans;
        }

        if ($search !== '') {
            $searchTerm = strtolower(trim($search));
            $param = "%" . $searchTerm . "%";
            $stmt->bind_param("ss", $param, $param);
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
    
    $searchQuery = trim($_GET['search'] ?? '');
    $loans = getLoans($searchQuery);
    ?>
    <main class="main">
        <section class="section">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1>Loan Applications</h1>
                    <div class="d-flex" style="max-width: 420px; width: 100%;">
                        <input type="text" id="searchInput" class="form-control me-2" placeholder="Search by name or phone number" value="<?= htmlspecialchars($searchQuery); ?>">
                    </div>
                </div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Borrower's Name</th>
                            <th>Principal</th>
                            <!-- Duration column hidden -->
                            <!-- Number of Repayments column hidden -->
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $loan): ?>
                            <tr data-search="<?= htmlspecialchars(strtolower($loan['borrower_name'] . ' ' . ($loan['borrower_mobile'] ?? ''))); ?>">
                                <td><?= htmlspecialchars($loan['borrower_name']); ?></td>
                                <td><?= number_format($loan['principal'], 2); ?> KES</td>
                                <!-- Duration column hidden -->
                                <!-- Number of Repayments column hidden -->
                                <td><?= number_format($loan['total_amount'], 2); ?> KES</td>
                                <td><?= htmlspecialchars($loan['status']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="loan_id" value="<?= $loan['loan_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="loan_id" value="<?= $loan['loan_id']; ?>">
                                        <input type="hidden" name="action" value="deny">
                                        <button type="submit" class="btn btn-danger btn-sm">Decline</button>
                                    </form>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Clear this loan application and all repayment records?');">
                                        <input type="hidden" name="loan_id" value="<?= $loan['loan_id']; ?>">
                                        <input type="hidden" name="action" value="clear">
                                        <button type="submit" class="btn btn-secondary btn-sm">Clear</button>
                                    </form>
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#rolloverLoanModal<?= $loan['loan_id']; ?>">Rollover</button>
                                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editLoanModal<?= $loan['loan_id']; ?>">Edit</button>

                                    <div class="modal fade" id="rolloverLoanModal<?= $loan['loan_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" class="rollover-loan-form">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Rollover Loan</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="loan_id" value="<?= $loan['loan_id']; ?>">
                                                        <input type="hidden" name="action" value="rollover">
                                                        <input type="hidden" name="loan_product" value="<?= htmlspecialchars($loan['loan_product']); ?>">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Borrower</label>
                                                                <input type="text" class="form-control" value="<?= htmlspecialchars($loan['borrower_name']); ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Loan Product</label>
                                                                <input type="text" class="form-control" value="<?= htmlspecialchars($loan['loan_product']); ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Principal to Rollover</label>
                                                                <input type="number" step="0.01" class="form-control" name="principal" value="<?= number_format(max(0, $loan['total_amount'] - $loan['total_paid']), 2, '.', ''); ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Interest Rate %</label>
                                                                <input type="number" step="0.01" class="form-control" value="<?= htmlspecialchars($loan['loan_interest']); ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Rollover Start Date</label>
                                                                <input type="date" class="form-control" name="rollover_date" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Rollover Duration</label>
                                                                <input type="number" min="1" class="form-control" name="rollover_duration" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Duration Unit</label>
                                                                <select class="form-select" name="rollover_duration_unit" required>
                                                                    <option value="months" selected>Months</option>
                                                                    <option value="weeks">Weeks</option>
                                                                    <option value="years">Years</option>
                                                                    <option value="days">Days</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Repayment Cycle</label>
                                                                <input type="text" class="form-control" value="<?= htmlspecialchars($loan['repayment_cycle']); ?>" readonly>
                                                            </div>
                                                        </div>
                                                        <p class="small mt-3 text-muted">This rollover uses the remaining balance as the new principal amount. A new loan will be created and the current loan will be marked as rolled over.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Confirm Rollover</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="editLoanModal<?= $loan['loan_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" class="edit-loan-form">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Loan Application</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="loan_id" value="<?= $loan['loan_id']; ?>">
                                                        <input type="hidden" name="action" value="edit">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Borrower</label>
                                                                <select class="form-select" name="borrower" required>
                                                                    <?php foreach ($borrowers as $borrower): ?>
                                                                        <option value="<?= $borrower['id']; ?>" <?= ($borrower['id'] == $loan['borrower']) ? 'selected' : ''; ?>><?= htmlspecialchars($borrower['full_name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Loan Product</label>
                                                                <select class="form-select" name="loan_product" required>
                                                                    <?php foreach ($loanProducts as $product): ?>
                                                                        <option value="<?= $product['id']; ?>" <?= ($product['id'] == $loan['loan_product']) ? 'selected' : ''; ?>><?= htmlspecialchars($product['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Principal</label>
                                                                <input type="number" step="0.01" class="form-control" name="principal" value="<?= htmlspecialchars($loan['principal']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Loan Release Date</label>
                                                                <input type="date" class="form-control" name="loan_release_date" value="<?= htmlspecialchars($loan['loan_release_date']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Interest Method</label>
                                                                <select class="form-select" name="interest_method" required>
                                                                    <option value="flat_rate" <?= ($loan['interest_method'] == 'flat_rate') ? 'selected' : ''; ?>>Flat Rate</option>
                                                                    <option value="percentage" <?= ($loan['interest_method'] == 'percentage') ? 'selected' : ''; ?>>Percentage</option>
                                                                    <option value="fixed_amount" <?= ($loan['interest_method'] == 'fixed_amount') ? 'selected' : ''; ?>>Fixed Amount</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Interest Calculation</label>
                                                                <select class="form-select" name="interest_calculation" required>
                                                                    <option value="weekly" <?= ($loan['interest_calculation'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                                    <option value="monthly" <?= ($loan['interest_calculation'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                                                    <option value="yearly" <?= ($loan['interest_calculation'] == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Loan Interest %</label>
                                                                <input type="number" step="0.01" class="form-control" name="loan_interest_percentage" value="<?= htmlspecialchars($loan['loan_interest']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Loan Duration</label>
                                                                <input type="number" class="form-control" name="loan_duration" value="<?= htmlspecialchars($loan['duration']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Loan Duration Unit</label>
                                                                <select class="form-select" name="loan_duration_unit" required>
                                                                    <option value="days" <?= ($loan['loan_duration_unit'] == 'days') ? 'selected' : ''; ?>>Days</option>
                                                                    <option value="weeks" <?= ($loan['loan_duration_unit'] == 'weeks') ? 'selected' : ''; ?>>Weeks</option>
                                                                    <option value="months" <?= ($loan['loan_duration_unit'] == 'months') ? 'selected' : ''; ?>>Months</option>
                                                                    <option value="years" <?= ($loan['loan_duration_unit'] == 'years') ? 'selected' : ''; ?>>Years</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Repayment Cycle</label>
                                                                <select class="form-select" name="repayment_cycle" required>
                                                                    <option value="daily" <?= ($loan['repayment_cycle'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                                                    <option value="weekly" <?= ($loan['repayment_cycle'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                                    <option value="monthly" <?= ($loan['repayment_cycle'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                                                    <option value="yearly" <?= ($loan['repayment_cycle'] == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                                                                    <option value="once" <?= ($loan['repayment_cycle'] == 'once') ? 'selected' : ''; ?>>Once</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Number of Repayments</label>
                                                                <input type="number" class="form-control" name="number_of_repayments" value="<?= htmlspecialchars($loan['repayments_count']); ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Processing Fee</label>
                                                                <input type="number" step="0.01" min="0" class="form-control" name="processing_fee" value="<?= htmlspecialchars($loan['processing_fee']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Registration Fee</label>
                                                                <input type="number" step="0.01" min="0" class="form-control" name="registration_fee" value="<?= htmlspecialchars($loan['registration_fee']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Total Amount</label>
                                                                <input type="number" step="0.01" class="form-control" name="total_amount" value="<?= htmlspecialchars($loan['total_amount']); ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Total Amount Inclusive</label>
                                                                <input type="number" step="0.01" class="form-control" name="total_amount_inclusive" value="<?= htmlspecialchars($loan['total_amount_inclusive']); ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Repayment Amount Per Cycle</label>
                                                                <input type="number" step="0.01" class="form-control" name="repayment_amount" readonly>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                const filterRows = () => {
                    const filter = searchInput.value.toLowerCase();
                    const rows = document.querySelectorAll('tbody tr[data-search]');

                    rows.forEach(row => {
                        const searchText = (row.getAttribute('data-search') || '').toLowerCase();
                        row.style.display = searchText.includes(filter) ? '' : 'none';
                    });
                };

                searchInput.addEventListener('input', filterRows);
                filterRows();
            }
        });

        function calculateEditLoanDetails(form) {
            const principal = parseFloat(form.querySelector('[name="principal"]').value) || 0;
            const loanDuration = parseFloat(form.querySelector('[name="loan_duration"]').value) || 0;
            const loanDurationUnit = form.querySelector('[name="loan_duration_unit"]').value;
            const interestMethod = form.querySelector('[name="interest_method"]').value;
            const interestCalculation = form.querySelector('[name="interest_calculation"]').value;
            const repaymentCycle = form.querySelector('[name="repayment_cycle"]').value;
            const processingFee = parseFloat(form.querySelector('[name="processing_fee"]').value) || 0;
            const registrationFee = parseFloat(form.querySelector('[name="registration_fee"]').value) || 0;
            const loanInterestPercentage = parseFloat(form.querySelector('[name="loan_interest_percentage"]').value) || 0;

            let durationInWeeks = 0;
            switch (loanDurationUnit) {
                case 'days':
                    durationInWeeks = loanDuration / 7;
                    break;
                case 'weeks':
                    durationInWeeks = loanDuration;
                    break;
                case 'months':
                    durationInWeeks = loanDuration * 4;
                    break;
                case 'years':
                    durationInWeeks = loanDuration * 52;
                    break;
            }

            let numberOfRepayments = 0;
            switch (repaymentCycle) {
                case 'daily':
                    numberOfRepayments = durationInWeeks * 7;
                    break;
                case 'weekly':
                    numberOfRepayments = durationInWeeks;
                    break;
                case 'monthly':
                    numberOfRepayments = durationInWeeks / 4;
                    break;
                case 'yearly':
                    numberOfRepayments = durationInWeeks / 52;
                    break;
                case 'once':
                    numberOfRepayments = 1;
                    break;
            }

            let totalInterest = 0;
            switch (interestMethod) {
                case 'flat_rate':
                    totalInterest = (principal * loanInterestPercentage * loanDuration) / 100;
                    break;
                case 'percentage':
                    const interestPerPeriod = principal * (loanInterestPercentage / 100);
                    switch (interestCalculation) {
                        case 'weekly':
                            totalInterest = interestPerPeriod * durationInWeeks;
                            break;
                        case 'monthly':
                            totalInterest = interestPerPeriod * (durationInWeeks / 4);
                            break;
                        case 'yearly':
                            totalInterest = interestPerPeriod * (durationInWeeks / 52);
                            break;
                    }
                    break;
                case 'fixed_amount':
                    totalInterest = loanInterestPercentage * numberOfRepayments;
                    break;
            }

            const totalAmount = principal + totalInterest;
            const totalAmountInclusive = totalAmount + processingFee + registrationFee;
            const repaymentAmount = numberOfRepayments > 0 ? totalAmount / numberOfRepayments : 0;

            form.querySelector('[name="number_of_repayments"]').value = numberOfRepayments.toFixed(2);
            form.querySelector('[name="total_amount"]').value = totalAmount.toFixed(2);
            form.querySelector('[name="total_amount_inclusive"]').value = totalAmountInclusive.toFixed(2);
            form.querySelector('[name="repayment_amount"]').value = repaymentAmount.toFixed(2);
        }

        document.querySelectorAll('.edit-loan-form').forEach(function(form) {
            form.addEventListener('input', function() {
                calculateEditLoanDetails(form);
            });
            form.addEventListener('change', function() {
                calculateEditLoanDetails(form);
            });
            calculateEditLoanDetails(form);
        });
    </script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>
