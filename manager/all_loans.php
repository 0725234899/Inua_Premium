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
            $stmt->bind_param("iss", $loan_id, $schedule_date->format('Y-m-d'), $repayment_amount);
            $stmt->execute();

            $start_date = $schedule_date;
        }
    }

    function calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments) {
        $total_amount = $principal_amount + $interest_amount;
        return $number_of_repayments > 0 ? $total_amount / $number_of_repayments : 0;
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
            if (updateLoanStatus($loan_id, $status)) {
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
                $stmt = $conn->prepare("UPDATE loan_applications SET borrower = ?, loan_product = ?, principal = ?, loan_release_date = ?, interest = ?, interest_method = ?, loan_interest = ?, loan_duration = ?, repayment_cycle = ?, number_of_repayments = ?, processing_fee = ?, registration_fee = ?, loan_status = ?, total_amount = ?, total_amount_inclusive = ? WHERE id = ?");
                $stmt->bind_param("iiisdssissssddsi", $borrower, $loan_product, $principal, $loan_release_date, $loan_interest_percentage, $interest_method, $loan_interest_percentage, $loan_duration, $repayment_cycle, $number_of_repayments, $processing_fee, $registration_fee, $currentStatus, $total_amount, $total_amount_inclusive, $loan_id);
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
        }
    }

    function getLoans() {
        global $conn;
        $loans = array();

        $sql = "SELECT 
                    l.id AS loan_id, 
                    l.borrower,
                    b.full_name AS borrower_name, 
                    l.principal, 
                    l.loan_duration AS duration, 
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
                    l.loan_status AS status
                FROM loan_applications l 
                INNER JOIN borrowers b ON l.borrower = b.id";

        $result = $conn->query($sql);

        if ($result === FALSE) {
            echo "Error: " . $conn->error;
            return $loans;
        }

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $loans[] = $row;
            }
        } else {
            echo "No records found.";
        }

        return $loans;
    }
    
    $loans = getLoans();
    ?>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Loan Applications</h1>
                <a href="generate_pdf.php" class="btn btn-primary">Export Report as PDF</a>
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
                            <tr>
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
                                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editLoanModal<?= $loan['loan_id']; ?>">Edit</button>

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
                                                                    <option value="weekly">Weekly</option>
                                                                    <option value="monthly" selected>Monthly</option>
                                                                    <option value="yearly">Yearly</option>
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
                                                                    <option value="days">Days</option>
                                                                    <option value="weeks">Weeks</option>
                                                                    <option value="months" selected>Months</option>
                                                                    <option value="years">Years</option>
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
