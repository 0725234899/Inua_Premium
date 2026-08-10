<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
include '../includes/functions.php';

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

function get_projected_maturity_date_for_loan($loan) {
    if (empty($loan['loan_release_date'])) {
        return null;
    }

    $date = new DateTime($loan['loan_release_date']);
    $count = (int) ($loan['number_of_repayments'] ?? 0);
    $cycle = ($loan['repayment_cycle'] ?? 'monthly');

    if ($count <= 0) {
        return $date;
    }

    if ($cycle === 'once') {
        $loan_duration = (int) ($loan['loan_duration'] ?? 0);
        $loan_duration_unit = $loan['loan_duration_unit'] ?? 'months';
        switch ($loan_duration_unit) {
            case 'days':
                $date->modify('+' . max(1, $loan_duration) . ' days');
                break;
            case 'weeks':
                $date->modify('+' . max(1, $loan_duration) . ' weeks');
                break;
            case 'months':
                $date->modify('+' . max(1, $loan_duration) . ' months');
                break;
            case 'years':
                $date->modify('+' . max(1, $loan_duration) . ' years');
                break;
            default:
                $date->modify('+' . max(1, $loan_duration) . ' months');
                break;
        }
        return $date;
    }

    $intervalSpec = 'P1M';
    switch ($cycle) {
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
    $stmt = $conn->prepare("SELECT id, loan_status, loan_release_date, repayment_cycle, number_of_repayments, loan_duration FROM loan_applications WHERE loan_status IN ('approved', 'rolled_over') OR LOWER(TRIM(COALESCE(loan_status, ''))) LIKE '%roll%'");
    if (!$stmt) {
        return $eligible_ids;
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $today = new DateTime('today');
    while ($loan = $result->fetch_assoc()) {
        $loanStatus = strtolower(trim((string) ($loan['loan_status'] ?? '')));
        $isRolledOver = strpos($loanStatus, 'roll') !== false;

        if (!$isRolledOver) {
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

$eligible_loan_ids = get_eligible_loan_ids_for_arrears($conn);
$eligible_loan_filter = !empty($eligible_loan_ids)
    ? "AND loan_applications.id IN (" . implode(',', $eligible_loan_ids) . ")"
    : "AND 1=0";

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

// Fetch total overdue amount using the overdue_repayments logic
$sql_total_overdue = "SELECT COALESCE(SUM(overdue_summary.total_overdue), 0) AS total_overdue
                FROM (
                    SELECT 
                        borrowers.full_name AS borrower_name, 
                        borrowers.mobile AS phone_number, 
                        GREATEST(
                            COALESCE(SUM(CASE 
                                WHEN repayments.repayment_date < CURDATE() 
                                THEN COALESCE(repayments.amount, 0) 
                                ELSE 0 
                            END), 0)
                            - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0), 
                            0
                        ) AS total_overdue
                    FROM 
                        borrowers
                    LEFT JOIN 
                        loan_applications ON borrowers.id = loan_applications.borrower
                    LEFT JOIN 
                        repayments ON loan_applications.id = repayments.loan_id
                    LEFT JOIN 
                        users ON borrowers.loan_officer = users.email
                    WHERE 
                        1=1
                        $eligible_loan_filter
                        $filter_sql
                    GROUP BY 
                        borrowers.full_name, borrowers.mobile
                    HAVING 
                        total_overdue > 0
                ) AS overdue_summary";

$stmt_total_overdue = $conn->prepare($sql_total_overdue);
bindDynamicParams($stmt_total_overdue, $filter_params);
$stmt_total_overdue->execute();
$result_total_overdue = $stmt_total_overdue->get_result();

$total_arrears = $result_total_overdue->fetch_assoc()['total_overdue'] ?? 0;


// Fetch total paid amount for approved loans
$sql_total_paid = "SELECT CEIL(SUM(paid)) AS total_paid 
                   FROM repayments 
                   INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                   INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                   INNER JOIN users ON borrowers.loan_officer = users.email
                   WHERE loan_applications.loan_status = 'approved'
                   $filter_sql";
$stmt_total_paid = $conn->prepare($sql_total_paid);
bindDynamicParams($stmt_total_paid, $filter_params);
$stmt_total_paid->execute();
$total_paid_amount = $stmt_total_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;

// Fetch total interest and combined fee totals for approved loans
$sql_total_interest = "SELECT CEIL(COALESCE(SUM(total_amount - principal), 0)) AS total_interest
                       FROM loan_applications
                       INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                       INNER JOIN users ON borrowers.loan_officer = users.email
                       WHERE loan_applications.loan_status = 'approved'
                       $filter_sql";
$stmt_total_interest = $conn->prepare($sql_total_interest);
bindDynamicParams($stmt_total_interest, $filter_params);
$stmt_total_interest->execute();
$total_interest_amount = $stmt_total_interest->get_result()->fetch_assoc()['total_interest'] ?? 0;

$sql_total_fees = "SELECT CEIL(COALESCE(SUM(processing_fee + registration_fee), 0)) AS total_fees
                   FROM loan_applications
                   INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                   INNER JOIN users ON borrowers.loan_officer = users.email
                   WHERE loan_applications.loan_status = 'approved'
                   $filter_sql";
$stmt_total_fees = $conn->prepare($sql_total_fees);
bindDynamicParams($stmt_total_fees, $filter_params);
$stmt_total_fees->execute();
$total_fee_amount = $stmt_total_fees->get_result()->fetch_assoc()['total_fees'] ?? 0;

// Fetch total disbursed loans (including interest)
$sql_total_loans = "SELECT CEIL(SUM(loan_applications.total_amount)) AS total_loans 
                    FROM loan_applications 
                    INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                    INNER JOIN users ON borrowers.loan_officer = users.email
                    WHERE loan_applications.loan_status = 'approved'
                    $filter_sql";
$stmt_total_loans = $conn->prepare($sql_total_loans);
bindDynamicParams($stmt_total_loans, $filter_params);
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

// Calculate Performing Book
$performing_book = max(0, $total_loan_amount - $total_arrears - $total_paid_amount);

// Calculate Loan Book
$loan_book = $performing_book + $total_arrears;

// Portfolio at Risk (PAR) Calculation
$par = ($loan_book > 0) ? ($total_arrears / $loan_book) * 100 : 0;

// Fetch total due loans for today
$sql_due_loans = "SELECT CEIL(SUM(amount - paid)) AS total_due_loans 
                  FROM repayments 
                  INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                  INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                  INNER JOIN users ON borrowers.loan_officer = users.email
                  WHERE repayment_date = CURDATE() 
                  AND loan_applications.loan_status = 'approved' 
                  AND (amount - paid) > 0
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
                          HAVING SUM(COALESCE(repayments.amount - repayments.paid, 0)) > 0
                      ) AS clients_with_balance";
$stmt_total_clients = $conn->prepare($sql_total_clients);
bindDynamicParams($stmt_total_clients, $filter_params);
$stmt_total_clients->execute();
$total_clients = $stmt_total_clients->get_result()->fetch_assoc()['total_clients'] ?? 0;

// Fetch total number of clients in arrears (with total overdue amount > 0)
$sql_clients_in_arrears = "SELECT COUNT(*) AS clients_in_arrears 
                           FROM (
                               SELECT borrowers.id,
                                   SUM(CASE 
                                       WHEN repayments.repayment_date < CURDATE() 
                                       THEN GREATEST(COALESCE(repayments.amount, 0) - COALESCE(repayments.paid, 0), 0) 
                                       ELSE 0 
                                   END) AS total_overdue
                               FROM borrowers
                               LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
                               LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
                               LEFT JOIN users ON borrowers.loan_officer = users.email
                               WHERE 1=1
                                   $eligible_loan_filter
                                   $filter_sql
                               GROUP BY borrowers.id
                               HAVING total_overdue > 0
                           ) AS arrears_summary";
$stmt_clients_in_arrears = $conn->prepare($sql_clients_in_arrears);
bindDynamicParams($stmt_clients_in_arrears, $filter_params);
$stmt_clients_in_arrears->execute();
$clients_in_arrears = $stmt_clients_in_arrears->get_result()->fetch_assoc()['clients_in_arrears'] ?? 0;

// Fetch names of clients in arrears and their arrears amounts (consistent with clients_in_arrears count)
$sql_clients_in_arrears_details = "SELECT 
    borrowers.full_name AS client_name,
    GREATEST(
        COALESCE(SUM(CASE 
            WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0) 
            ELSE 0 
        END), 0) 
        - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0), 
        0
    ) AS arrears_amount
FROM 
    borrowers
LEFT JOIN 
    loan_applications ON borrowers.id = loan_applications.borrower
LEFT JOIN 
    repayments ON loan_applications.id = repayments.loan_id
LEFT JOIN 
    users ON borrowers.loan_officer = users.email
WHERE 
    1=1
    $eligible_loan_filter
    $filter_sql
GROUP BY 
    borrowers.id, borrowers.full_name
HAVING 
    arrears_amount > 0
ORDER BY 
    arrears_amount DESC";
$stmt_clients_in_arrears_details = $conn->prepare($sql_clients_in_arrears_details);
bindDynamicParams($stmt_clients_in_arrears_details, $filter_params);
$stmt_clients_in_arrears_details->execute();
$result_clients_in_arrears_details = $stmt_clients_in_arrears_details->get_result();

// Fetch names of clients with due repayments today and their due amounts
$sql_clients_due_today = "SELECT 
    borrowers.full_name AS client_name,
    SUM(repayments.amount - repayments.paid) AS due_amount
FROM 
    borrowers
INNER JOIN 
    loan_applications ON borrowers.id = loan_applications.borrower
INNER JOIN 
    repayments ON loan_applications.id = repayments.loan_id
INNER JOIN 
    users ON borrowers.loan_officer = users.email
WHERE 
    repayments.repayment_date = CURDATE()
    AND (repayments.amount - repayments.paid) > 0
    $filter_sql
GROUP BY 
    borrowers.full_name";
$stmt_clients_due_today = $conn->prepare($sql_clients_due_today);
bindDynamicParams($stmt_clients_due_today, $filter_params);
$stmt_clients_due_today->execute();
$result_clients_due_today = $stmt_clients_due_today->get_result();

// Fetch the last ten clients who made their payments recently
$sql_recent_repayments = "SELECT 
    borrowers.full_name AS client_name,
    payment_date_records.Amount AS repaid_amount,
    DATE_FORMAT(payment_date_records.PaymentDate, '%d/%m/%Y') AS payment_date
FROM 
    payment_date_records
INNER JOIN 
    loan_applications ON payment_date_records.loan_id = loan_applications.id
INNER JOIN 
    borrowers ON loan_applications.borrower = borrowers.id
INNER JOIN 
    users ON borrowers.loan_officer = users.email
WHERE 
    1=1
    $filter_sql
ORDER BY 
    payment_date_records.PaymentDate DESC
LIMIT 10";

$stmt_recent_repayments = $conn->prepare($sql_recent_repayments);
bindDynamicParams($stmt_recent_repayments, $filter_params);
$stmt_recent_repayments->execute();
$result_recent_repayments = $stmt_recent_repayments->get_result();
$recent_repayments = [];
while ($row = $result_recent_repayments->fetch_assoc()) {
    $recent_repayments[] = "💰 " . htmlspecialchars($row['client_name']) . " repaid KSH " . number_format($row['repaid_amount'], 2) . " on " . htmlspecialchars($row['payment_date']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Microfinance</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        /* General Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Open Sans', sans-serif;
            color: #212529;
        }

        /* Container */
        .container {
            max-width: 1200px;
        }

        /* Back Button */
        .btn-primary {
            background-color: #e84545;
            border: none;
            transition: all 0.3s ease-in-out;
        }

        .btn-primary:hover {
            background-color: #d43d3d;
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid #e84545;
        }

        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 600;
            transition: 0.3s;
        }

        .nav-tabs .nav-link:hover,
        .nav-tabs .nav-link.active {
            color: #e84545;
            border-color: #e84545 #e84545 #fff;
        }

        /* Dashboard Metrics */
        .dashboard-metrics {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
        }

        .metric {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 220px;
            transition: transform 0.3s ease-in-out;
        }

        .metric:hover {
            transform: scale(1.05);
        }

        .metric h2 {
            font-size: 24px;
            font-weight: bold;
            color: #e84545;
        }

        .metric p {
            font-size: 16px;
            color: #6c757d;
        }

        /* Chart Container */
        .chart-container {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Blinking Effect */
        .blinking-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
        }

        .blinking {
            font-weight: bold;
            color: #ffc107;
            animation: blink 5s infinite;
            margin-right: 10px;
        }

        @keyframes blink {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-metrics {
                flex-direction: column;
                align-items: center;
            }

            .metric {
                width: 100%;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-center">Manager Dashboard</h1>
        <div class="blinking-container">
            <div class="blinking">
                <span id="blinking-text"></span>
            </div>
            <a href="add_repayments.php" class="btn btn-success">
                <i class="fa fa-money-bill"></i> Add Repayments
            </a>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const repayments = <?php echo json_encode($recent_repayments); ?>;
            const blinkingText = document.getElementById('blinking-text');
            let index = 0;

            function updateBlinkingText() {
                if (repayments.length > 0) {
                    blinkingText.textContent = repayments[index];
                    index = (index + 1) % repayments.length;
                }
            }

            updateBlinkingText();
            setInterval(updateBlinkingText, 5000); // Change text every 5 seconds
        });
    </script>

    <marquee behavior="scroll" direction="left" style="background-color: #f8f9fa; padding: 10px; font-weight: bold; color: #e84545; border: 1px solid #ddd; border-radius: 5px;">
        <?php while ($row = $result_clients_in_arrears_details->fetch_assoc()): ?>
            <?php echo htmlspecialchars($row['client_name']) . " (Arrears: KSH " . number_format($row['arrears_amount'], 2) . ")"; ?> &nbsp;&nbsp;&nbsp;
        <?php endwhile; ?>
    </marquee>

    <marquee behavior="scroll" direction="left" style="background-color: #f8f9fa; padding: 10px; font-weight: bold; color: #28a745; border: 1px solid #ddd; border-radius: 5px; margin-top: 10px;">
        <?php while ($row = $result_clients_due_today->fetch_assoc()): ?>
            <?php echo htmlspecialchars($row['client_name']) . " (Due Today: KSH " . number_format($row['due_amount'], 2) . ")"; ?> &nbsp;&nbsp;&nbsp;
        <?php endwhile; ?>
    </marquee>

    <a href="index.php" class="btn btn-primary" style="width:100px;margin-bottom:20px">
        <i class="fa fa-arrow-left"></i> Back
    </a>

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
    <div class="dashboard-metrics d-flex justify-content-around mt-4">
        <div class="metric">
            <h2>KSH <?php echo number_format(ceil($total_arrears)); ?></h2>
            <p>Total Arrears</p>
        </div>
        <div class="metric">
            <h2>KSH <?php echo number_format(ceil($total_loan_amount)); ?></h2>
            <p>Total Disbursed Loans</p>
        </div>
        <div class="metric">
            <h2>KSH <?php echo number_format(ceil($performing_book)); ?></h2>
            <p>Performing Book</p>
        </div>
        <div class="metric">
            <h2>KSH <?php echo number_format(ceil($loan_book)); ?></h2>
            <p>Loan Book</p>
        </div>
        <div class="metric">
            <h2><?= number_format($par, 2); ?>%</h2>
            <p>Portfolio At Risk</p>
        </div>
        <div class="metric">
            <h2>KSH <?php echo number_format(ceil($total_interest_amount)); ?></h2>
            <p>Total Interest</p>
        </div>
        <div class="metric">
            <h2>KSH <?php echo number_format(ceil($total_fee_amount)); ?></h2>
            <p>Processing + Registration Fees</p>
        </div>
        <div class="metric">
            <h2><?php echo $total_clients; ?></h2>
            <p>Total Clients</p>
        </div>
        <div class="metric">
            <h2><?php echo $clients_in_arrears; ?></h2>
            <p>Clients in Arrears</p>
        </div>
        <div class="metric">
            <h2>KSH <?php echo number_format(ceil($total_due_loans)); ?></h2>
            <p>Due Loans</p>
        </div>
    </div>

    <!-- Chart -->
    <div class="chart-container mt-5 d-flex flex-wrap">
        <div style="flex: 1; min-width: 300px; max-width: 50%;">
            <canvas id="parPieChart"></canvas>
        </div>
        <div style="flex: 1; min-width: 300px; max-width: 50%;">
            <canvas id="loanMetricsBarChart"></canvas>
        </div>
    </div>

    <script>
        // Pie Chart for PAR
        new Chart(document.getElementById('parPieChart'), {
            type: 'pie',
            data: {
                labels: ['At Risk', 'Performing'],
                datasets: [{
                    data: [<?= $par; ?>, <?= 100 - $par; ?>],
                    backgroundColor: ['red', 'green']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        // Bar Chart for Loan Metrics
        new Chart(document.getElementById('loanMetricsBarChart'), {
            type: 'bar',
            data: {
                labels: ['Loan Book', 'Performing Book', 'Total Arrears'],
                datasets: [{
                    label: 'Loan Metrics',
                    data: [<?= $loan_book; ?>, <?= $performing_book; ?>, <?= $total_arrears; ?>],
                    backgroundColor: ['blue', 'green', 'red']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { title: { display: true, text: 'Amount (KSH)' } },
                    x: { title: { display: true, text: 'Metrics' } }
                }
            }
        });
    </script>
</div>
</body>
</html>
