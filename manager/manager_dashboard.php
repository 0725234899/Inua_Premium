<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
include '../includes/functions.php';

// Fetch all loan officers
$sql_officers = "SELECT id, name AS full_name FROM users WHERE role_id = '2'";
$stmt_officers = $conn->prepare($sql_officers);
$stmt_officers->execute();
$result_officers = $stmt_officers->get_result();

// Get selected loan officer (if any)
$selected_officer = isset($_GET['officer_id']) ? $_GET['officer_id'] : 'all';
$officer_filter = ($selected_officer !== 'all') ? "AND users.id = ?" : "";

// Fetch total overdue amount for the selected loan officer
$sql_total_overdue = "SELECT 
                        SUM(repayments.amount - repayments.paid) AS total_overdue 
                      FROM 
                        repayments
                      INNER JOIN 
                        loan_applications ON repayments.loan_id = loan_applications.id
                      INNER JOIN 
                        borrowers ON loan_applications.borrower = borrowers.id
                      INNER JOIN 
                        users ON borrowers.loan_officer = users.email
                      WHERE 
                        repayments.repayment_date < CURDATE() 
                        AND (repayments.amount - repayments.paid) > 0
                        $officer_filter";

$stmt_total_overdue = $conn->prepare($sql_total_overdue);
if ($selected_officer !== 'all') {
    $stmt_total_overdue->bind_param("i", $selected_officer);
}
$stmt_total_overdue->execute();
$total_overdue_amount = $stmt_total_overdue->get_result()->fetch_assoc()['total_overdue'] ?? 0;

// Calculate total arrears (overdue repayments)
$total_arrears = $total_overdue_amount;

// Fetch total disbursed loans
$sql_total_loans = "SELECT SUM(total_amount) AS total_loans 
                    FROM loan_applications 
                    INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                    INNER JOIN users ON borrowers.loan_officer = users.email
                    WHERE loan_status = 'approved' $officer_filter";

$stmt_total_loans = $conn->prepare($sql_total_loans);
if ($selected_officer !== 'all') {
    $stmt_total_loans->bind_param("i", $selected_officer);
}
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

// Fetch total paid amount
$sql_paid = "SELECT SUM(paid) AS total_paid FROM repayments";
$stmt_paid = $conn->prepare($sql_paid);
$stmt_paid->execute();
$total_paid = $stmt_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;

// Calculate outstanding loan balance
$outstanding_loan_balance = $total_loan_amount - $total_paid;

// Calculate Performing Book
$performing_book = max(0, $total_loan_amount - $total_arrears);

// Calculate Loan Book
$loan_book = $performing_book + $total_arrears;

// Portfolio at Risk (PAR) Calculation
$par = ($total_loan_amount > 0) ? ($total_arrears / $total_loan_amount) * 100 : 0;

// Fetch total due loans for today
$sql_due_loans = "SELECT SUM(repayments.amount - repayments.paid) AS total_due_loans 
                  FROM repayments 
                  INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                  INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                  INNER JOIN users ON borrowers.loan_officer = users.email
                  WHERE repayments.repayment_date = CURDATE() 
                  AND loan_applications.loan_status = 'approved'
                  $officer_filter";
$stmt_due_loans = $conn->prepare($sql_due_loans);
if ($selected_officer !== 'all') {
    $stmt_due_loans->bind_param("i", $selected_officer);
}
$stmt_due_loans->execute();
$total_due_loans = $stmt_due_loans->get_result()->fetch_assoc()['total_due_loans'] ?? 0;

// Fetch names of clients in arrears and their arrears amounts
$sql_clients_in_arrears_details = "SELECT 
    borrowers.full_name AS client_name,
    SUM(repayments.amount - repayments.paid) AS arrears_amount
FROM 
    borrowers
INNER JOIN 
    loan_applications ON borrowers.id = loan_applications.borrower
INNER JOIN 
    repayments ON loan_applications.id = repayments.loan_id
WHERE 
    repayments.repayment_date < CURDATE()
    AND (repayments.amount - repayments.paid) > 0
GROUP BY 
    borrowers.full_name";
$stmt_clients_in_arrears_details = $conn->prepare($sql_clients_in_arrears_details);
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
WHERE 
    repayments.repayment_date = CURDATE()
    AND (repayments.amount - repayments.paid) > 0
GROUP BY 
    borrowers.full_name";
$stmt_clients_due_today = $conn->prepare($sql_clients_due_today);
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
ORDER BY 
    payment_date_records.PaymentDate DESC
LIMIT 10";

$stmt_recent_repayments = $conn->prepare($sql_recent_repayments);
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

    <!-- Loan Officer Tabs -->
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link <?= ($selected_officer == 'all') ? 'active' : '' ?>" href="?officer_id=all">All Officers</a>
        </li>
        <?php while ($officer = $result_officers->fetch_assoc()) { ?>
            <li class="nav-item">
                <a class="nav-link <?= ($selected_officer == $officer['id']) ? 'active' : '' ?>" 
                   href="?officer_id=<?= $officer['id'] ?>">
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
