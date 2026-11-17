<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}
include 'db.php';
include '../includes/functions.php';

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

function bindDynamicParams($stmt, $params) {
    if (empty($params)) {
        return;
    }

    $types = '';
    foreach ($params as $param) {
        $types .= is_int($param) || is_float($param) ? 'i' : 's';
    }

    $stmt->bind_param($types, ...$params);
}

// Fetch available areas for loan officers
$sql_areas = "SELECT area_id, area_name FROM areas ORDER BY area_name";
$result_areas = $conn->query($sql_areas);
$areas = [];
while ($area = $result_areas->fetch_assoc()) {
    $areas[] = $area;
}

// Get selected area and loan officer
$selected_area = isset($_GET['area_id']) ? $_GET['area_id'] : 'all';
$selected_area = ($selected_area !== 'all' && !is_numeric($selected_area)) ? 'all' : $selected_area;
$selected_officer = isset($_GET['officer_id']) ? $_GET['officer_id'] : 'all';
$selected_officer = ($selected_officer !== 'all' && !is_numeric($selected_officer)) ? 'all' : $selected_officer;

$filter_sql = '';
$filter_params = [];
if ($selected_officer !== 'all') {
    $filter_sql .= " AND users.id = ?";
    $filter_params[] = (int) $selected_officer;
}
if ($selected_area !== 'all') {
    $filter_sql .= " AND users.area = ?";
    $filter_params[] = (int) $selected_area;
}

// Fetch loan officers for the selected area
$sql_officers = "SELECT id, name AS full_name, area FROM users WHERE role_id = '2'";
if ($selected_area !== 'all') {
    $sql_officers .= " AND area = ?";
}
$stmt_officers = $conn->prepare($sql_officers);
if ($selected_area !== 'all') {
    $stmt_officers->bind_param('i', $selected_area);
}
$stmt_officers->execute();
$result_officers = $stmt_officers->get_result();

$overdueFilters = [];
if ($selected_officer !== 'all') {
    $overdueFilters['officer_id'] = (int) $selected_officer;
}
if ($selected_area !== 'all') {
    $overdueFilters['area_id'] = (int) $selected_area;
}
$overdue_summary = getOverdueRepaymentSummary($conn, $overdueFilters);
$total_arrears = $overdue_summary['total_overdue'];


// Fetch total paid amount for approved loans
$sql_total_paid = "SELECT CEIL(SUM(repayments.paid + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = loan_applications.id), 0))) AS total_paid
                   FROM repayments
                   INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                   LEFT JOIN borrowers ON loan_applications.borrower = borrowers.id
                   LEFT JOIN users ON borrowers.loan_officer = users.email
                   WHERE loan_applications.loan_status = 'approved'
                   $filter_sql";
$stmt_total_paid = $conn->prepare($sql_total_paid);
bindDynamicParams($stmt_total_paid, $filter_params);
$stmt_total_paid->execute();
$total_paid_amount = $stmt_total_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;

// Fetch total interest and combined fee totals for approved loans
$sql_total_interest = "SELECT CEIL(COALESCE(SUM(total_amount - principal), 0)) AS total_interest
                       FROM loan_applications
                       LEFT JOIN borrowers ON loan_applications.borrower = borrowers.id
                       LEFT JOIN users ON borrowers.loan_officer = users.email
                       WHERE loan_applications.loan_status = 'approved'
                       $filter_sql";
$stmt_total_interest = $conn->prepare($sql_total_interest);
bindDynamicParams($stmt_total_interest, $filter_params);
$stmt_total_interest->execute();
$total_interest_amount = $stmt_total_interest->get_result()->fetch_assoc()['total_interest'] ?? 0;

$sql_total_fees = "SELECT CEIL(COALESCE(SUM(processing_fee + registration_fee), 0)) AS total_fees
                   FROM loan_applications
                   LEFT JOIN borrowers ON loan_applications.borrower = borrowers.id
                   LEFT JOIN users ON borrowers.loan_officer = users.email
                   WHERE loan_applications.loan_status = 'approved'
                   $filter_sql";
$stmt_total_fees = $conn->prepare($sql_total_fees);
bindDynamicParams($stmt_total_fees, $filter_params);
$stmt_total_fees->execute();
$total_fee_amount = $stmt_total_fees->get_result()->fetch_assoc()['total_fees'] ?? 0;

$sql_daily_inflows = "SELECT CEIL(COALESCE(SUM(payment_date_records.Amount), 0)) AS daily_inflows
                      FROM payment_date_records
                      INNER JOIN loan_applications ON payment_date_records.loan_id = loan_applications.id
                      LEFT JOIN borrowers ON loan_applications.borrower = borrowers.id
                      LEFT JOIN users ON borrowers.loan_officer = users.email
                      WHERE payment_date_records.PaymentDate = CURDATE()
                      AND loan_applications.loan_status = 'approved'
                      $filter_sql";
$stmt_daily_inflows = $conn->prepare($sql_daily_inflows);
bindDynamicParams($stmt_daily_inflows, $filter_params);
$stmt_daily_inflows->execute();
$daily_inflows = $stmt_daily_inflows->get_result()->fetch_assoc()['daily_inflows'] ?? 0;

// Fetch total disbursed loans (including interest)
$sql_total_loans = "SELECT CEIL(SUM(loan_applications.total_amount)) AS total_loans 
                    FROM loan_applications 
                    LEFT JOIN borrowers ON loan_applications.borrower = borrowers.id
                    LEFT JOIN users ON borrowers.loan_officer = users.email
                    WHERE loan_applications.loan_status = 'approved'
                    $filter_sql";
$stmt_total_loans = $conn->prepare($sql_total_loans);
bindDynamicParams($stmt_total_loans, $filter_params);
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

$sql_total_principal = "SELECT CEIL(COALESCE(SUM(loan_applications.principal), 0)) AS total_principal
                        FROM loan_applications
                        LEFT JOIN borrowers ON loan_applications.borrower = borrowers.id
                        LEFT JOIN users ON borrowers.loan_officer = users.email
                        WHERE loan_applications.loan_status = 'approved'
                        $filter_sql";
$stmt_total_principal = $conn->prepare($sql_total_principal);
bindDynamicParams($stmt_total_principal, $filter_params);
$stmt_total_principal->execute();
$total_disbursed_principal = $stmt_total_principal->get_result()->fetch_assoc()['total_principal'] ?? 0;

$interestCalculationColumnStmt = $conn->query("SELECT COUNT(*) AS column_count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_applications' AND COLUMN_NAME = 'interest_calculation'");
$hasInterestCalculationColumn = $interestCalculationColumnStmt && (int) $interestCalculationColumnStmt->fetch_assoc()['column_count'] > 0;
$weeklyLoanExpression = $hasInterestCalculationColumn
    ? "LOWER(COALESCE(l.interest_calculation, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.repayment_cycle, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.loan_duration_unit, '')) IN ('weekly', 'week', 'weeks')"
    : "LOWER(COALESCE(l.repayment_cycle, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.loan_duration_unit, '')) IN ('weekly', 'week', 'weeks')";
$totalPaidExpression = "COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)";
$penaltyThresholdExpression = "l.principal + (l.principal * CASE WHEN $weeklyLoanExpression THEN 0.06 ELSE 0.24 END * l.loan_duration)";
$loanPenaltyDebitExpression = "COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0)";

$sql_penalty_details = "SELECT
    GREATEST(0, (($totalPaidExpression) - ($penaltyThresholdExpression))) AS gross_penalty_amount,
    ($loanPenaltyDebitExpression) AS used_penalty_amount
FROM loan_applications l
LEFT JOIN borrowers ON l.borrower = borrowers.id
LEFT JOIN users ON borrowers.loan_officer = users.email
WHERE (
    l.loan_status IN ('approved', 'rolled_over')
    OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%'
)
$filter_sql";
$stmt_penalty_details = $conn->prepare($sql_penalty_details);
bindDynamicParams($stmt_penalty_details, $filter_params);
$stmt_penalty_details->execute();
$result_penalty_details = $stmt_penalty_details->get_result();
$gross_penalty_total = 0;
$used_penalty_total = 0;
while ($penaltyRow = $result_penalty_details->fetch_assoc()) {
    $gross_penalty_total += (float) $penaltyRow['gross_penalty_amount'];
    $used_penalty_total += (float) $penaltyRow['used_penalty_amount'];
}
$balance_penalty_amount = max(0, $gross_penalty_total - $used_penalty_total);

$conn->query("CREATE TABLE IF NOT EXISTS loan_officer_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NULL,
    template_name VARCHAR(100) NOT NULL,
    expense_type VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    loan_officer_id INT NOT NULL,
    loan_officer_name VARCHAR(100) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'cash',
    recorded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_loan_officer (loan_officer_id),
    KEY idx_expense_date (expense_date)
)");
$sql_total_expenses = "SELECT CEIL(COALESCE(SUM(expenses.amount), 0)) AS total_expenses
                       FROM loan_officer_expenses expenses
                       LEFT JOIN users ON expenses.loan_officer_id = users.id
                       WHERE 1=1
                       $filter_sql";
$stmt_total_expenses = $conn->prepare($sql_total_expenses);
bindDynamicParams($stmt_total_expenses, $filter_params);
$stmt_total_expenses->execute();
$total_expenses_amount = $stmt_total_expenses->get_result()->fetch_assoc()['total_expenses'] ?? 0;

// Calculate Performing Book
$performing_book = max(0, $total_loan_amount - $total_arrears - $total_paid_amount);

// Calculate Loan Book
$loan_book = $performing_book + $total_arrears;

// Portfolio at Risk (PAR) Calculation
$par = ($loan_book > 0) ? ($total_arrears / $loan_book) * 100 : 0;

// Fetch total due loans for today
$sql_due_loans = "SELECT CEIL(SUM(amount - paid - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = repayments.loan_id), 0))) AS total_due_loans
                  FROM repayments 
                  INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                  LEFT JOIN borrowers ON loan_applications.borrower = borrowers.id
                  LEFT JOIN users ON borrowers.loan_officer = users.email
                  WHERE repayment_date = CURDATE()
                  AND loan_applications.loan_status = 'approved' 
                  AND (amount - paid - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = loan_applications.id), 0)) > 0
                  $filter_sql";
$stmt_due_loans = $conn->prepare($sql_due_loans);
bindDynamicParams($stmt_due_loans, $filter_params);
$stmt_due_loans->execute();
$total_due_loans = $stmt_due_loans->get_result()->fetch_assoc()['total_due_loans'] ?? 0;

// Fetch total number of clients with outstanding loan balance > 0
$sql_total_clients = "SELECT COUNT(*) AS total_clients 
                      FROM (
                          SELECT borrowers.id
                          FROM borrowers
                          LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
                          LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
                          LEFT JOIN users ON borrowers.loan_officer = users.email
                          WHERE loan_applications.loan_status = 'approved'
                          $filter_sql
                          GROUP BY borrowers.id
                          HAVING SUM(COALESCE(repayments.amount - repayments.paid, 0)) - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa INNER JOIN loan_applications pla ON pla.id = pa.loan_id WHERE pla.borrower = borrowers.id), 0) > 0
                      ) AS clients_with_balance";
$stmt_total_clients = $conn->prepare($sql_total_clients);
bindDynamicParams($stmt_total_clients, $filter_params);
$stmt_total_clients->execute();
$total_clients = $stmt_total_clients->get_result()->fetch_assoc()['total_clients'] ?? 0;

$clients_in_arrears = $overdue_summary['total_overdue_count'];
$arrears_client_percentage = $total_clients > 0
    ? ($clients_in_arrears / $total_clients) * 100
    : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Microfinance</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --ink: #172331;
            --muted: #687582;
            --line: #dbe3e8;
            --paper: #ffffff;
            --canvas: #f2f5f6;
            --teal: #147d78;
            --gold: #c7973e;
        }

        body {
            background: var(--canvas);
            color: var(--ink);
            font-family: "Trebuchet MS", Arial, sans-serif;
        }

        .main {
            margin-left: 250px;
            padding: 34px 22px 60px;
            transition: margin-left 0.3s ease;
        }

        .main > .container {
            margin: 18px auto 0;
            max-width: 1280px;
            padding: 0;
        }

        .nav-tabs {
            border-bottom: 2px solid var(--teal);
        }

        .nav-tabs .nav-link {
            color: var(--muted);
            font-weight: 600;
            transition: 0.3s;
        }

        .nav-tabs .nav-link:hover,
        .nav-tabs .nav-link.active {
            color: var(--teal);
            border-color: var(--teal) var(--teal) var(--paper);
        }

        .dashboard-metrics {
            display: grid;
            gap: 28px;
            margin-top: 24px;
        }

        .metric-group > h2 {
            color: var(--ink);
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 14px;
        }

        .metric-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        }

        .metric,
        .metric-link .metric {
            background: var(--paper);
            border: 1px solid var(--line);
            border-left: 4px solid var(--teal);
            border-radius: 0;
            box-shadow: none;
            min-height: 120px;
            padding: 18px 20px;
            text-align: left;
            height: 100%;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .metric:hover,
        .metric-link:hover .metric {
            box-shadow: 0 3px 10px rgba(23, 35, 49, .08);
            transform: translateY(-2px);
        }

        .metric-link {
            color: inherit;
            display: block;
            text-decoration: none;
        }

        .metric-link:focus-visible {
            outline: 3px solid var(--teal);
            outline-offset: 3px;
        }

        .metric h2 {
            color: var(--ink);
            font-family: Georgia, serif;
            font-size: 1.45rem;
            margin: 8px 0 0;
            overflow-wrap: anywhere;
        }

        .metric p {
            color: var(--muted);
            font-size: .74rem;
            letter-spacing: .1em;
            margin: 0;
            text-transform: uppercase;
        }

        .metric-grid > :nth-child(2),
        .metric-grid > :nth-child(6) { border-left-color: var(--gold); }
        .metric-grid > :nth-child(3),
        .metric-grid > :nth-child(7) { border-left-color: #5b7180; }
        .metric-grid > :nth-child(4),
        .metric-grid > :nth-child(8) { border-left-color: #a95d55; }

        .sidebar { transition: all 0.3s ease; }
        .sidebar.collapsed { display: none; }
        .main.sidebar-collapsed { margin-left: 0; }

        #sidebarWrapper {
            box-sizing: border-box;
            height: 100vh;
            max-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            position: fixed;
            width: 250px;
            z-index: 1000;
        }

        #sidebarWrapper .sidebar {
            box-sizing: border-box;
            height: auto;
            max-height: none;
            overflow: visible;
            position: static;
            width: 100%;
        }

        #sidebarWrapper .sidebar-nav {
            box-sizing: border-box;
            min-height: 100%;
            padding-bottom: 80px;
        }

        #sidebarWrapper .sidebar-nav .collapse { overflow: visible; }

        @media (max-width: 850px) {
            .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 20px 12px 40px; }
        }

        @media (max-width: 575px) {

            .metric-grid { grid-template-columns: 1fr; }
        }

    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="sidebar" id="sidebarWrapper">
    <?php include '../includes/sidebar.php'; ?>
</div>
<main class="main" id="mainContent">
    <div class="container">
    <!-- Area Tabs -->
    <ul class="nav nav-tabs mt-3">
        <li class="nav-item">
            <a class="nav-link <?= ($selected_area == 'all') ? 'active' : '' ?>" href="?area_id=all&officer_id=all">All Areas</a>
        </li>
        <?php foreach ($areas as $area) { ?>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_area == $area['area_id']) ? 'active' : '' ?>"
                   href="?area_id=<?= urlencode($area['area_id']) ?>&officer_id=all">
                    <?= htmlspecialchars($area['area_name']); ?>
                </a>
            </li>
        <?php } ?>
    </ul>

    <!-- Loan Officer Tabs -->
    <ul class="nav nav-tabs mt-3">
        <li class="nav-item">
            <a class="nav-link <?= ($selected_officer == 'all') ? 'active' : '' ?>"
               href="?area_id=<?= urlencode($selected_area) ?>&officer_id=all">All Officers</a>
        </li>
        <?php while ($officer = $result_officers->fetch_assoc()) { ?>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_officer == $officer['id']) ? 'active' : '' ?>" 
                   href="?area_id=<?= urlencode($selected_area) ?>&officer_id=<?= $officer['id'] ?>">
                    <?= htmlspecialchars($officer['full_name']); ?>
                </a>
            </li>
        <?php } ?>
    </ul>

    <!-- Dashboard Metrics -->
    <div class="dashboard-metrics">
        <section class="metric-group" aria-labelledby="portfolioMetricsHeading">
            <h2 id="portfolioMetricsHeading">Portfolio health</h2>
            <div class="metric-grid">
                <div class="metric">
                    <h2>KSH <?php echo number_format(ceil($total_disbursed_principal)); ?></h2>
                    <p>Total Disbursed Loans</p>
                </div>
                <div class="metric">
                    <h2>KSH <?php echo number_format(ceil($loan_book)); ?></h2>
                    <p>Loan Book</p>
                </div>
                <div class="metric">
                    <h2>KSH <?php echo number_format(ceil($performing_book)); ?></h2>
                    <p>Performing Book</p>
                </div>
                <div class="metric">
                    <h2><?= number_format($par, 2); ?>%</h2>
                    <p>Portfolio At Risk</p>
                </div>
                <div class="metric">
                    <h2>KSH <?php echo number_format(ceil($total_arrears)); ?></h2>
                    <p>Total Arrears</p>
                </div>
            </div>
        </section>
        <section class="metric-group" aria-labelledby="financialMetricsHeading">
            <h2 id="financialMetricsHeading">Financial summary</h2>
            <div class="metric-grid">
                <div class="metric">
                    <h2>KSH <?php echo number_format(ceil($total_interest_amount)); ?></h2>
                    <p>Total Interest</p>
                </div>
                <div class="metric">
                    <h2>KSH <?php echo number_format(ceil($total_fee_amount)); ?></h2>
                    <p>Processing + Registration Fees</p>
                </div>
                <div class="metric">
                    <h2>KSH <?php echo number_format(ceil($daily_inflows)); ?></h2>
                    <p>Inflows Today</p>
                </div>
                <a class="metric-link" href="penalty_breakdown.php">
                    <div class="metric">
                        <h2>KSH <?php echo number_format(ceil($balance_penalty_amount)); ?></h2>
                        <p>Balance Penalty</p>
                    </div>
                </a>
                <a class="metric-link" href="expense_more_information.php">
                    <div class="metric">
                        <h2>KSH <?php echo number_format(ceil($total_expenses_amount)); ?></h2>
                        <p>Total Expenses</p>
                    </div>
                </a>
            </div>
        </section>
        <section class="metric-group" aria-labelledby="clientMetricsHeading">
            <h2 id="clientMetricsHeading">Client activity</h2>
            <div class="metric-grid">
                <div class="metric">
                    <h2><?php echo $total_clients; ?></h2>
                    <p>Total Clients</p>
                </div>
                <div class="metric">
                    <h2><?php echo $clients_in_arrears; ?></h2>
                    <p>Clients in Arrears</p>
                </div>
                <div class="metric">
                    <h2><?php echo number_format($arrears_client_percentage, 2); ?>%</h2>
                    <p>% of Clients in Arrears</p>
                </div>
                <div class="metric">
                    <h2>KSH <?php echo number_format(ceil($total_due_loans)); ?></h2>
                    <p>Due Loans</p>
                </div>
            </div>
        </section>
    </div>

    </div>
</main>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
