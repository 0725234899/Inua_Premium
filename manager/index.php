<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// Fetch total overdue amount for approved loans
$sql_total_overdue = "SELECT SUM(amount - paid) AS total_overdue 
                      FROM repayments 
                      INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                      WHERE repayment_date < CURDATE() 
                      AND loan_applications.loan_status = 'approved' 
                      AND (amount - paid) > 0";
$stmt_total_overdue = $conn->prepare($sql_total_overdue);
$stmt_total_overdue->execute();
$total_overdue_amount = $stmt_total_overdue->get_result()->fetch_assoc()['total_overdue'] ?? 0;

// Fetch total paid amount for approved loans
$sql_total_paid = "SELECT SUM(paid) AS total_paid 
                   FROM repayments 
                   INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                   WHERE loan_applications.loan_status = 'approved'";
$stmt_total_paid = $conn->prepare($sql_total_paid);
$stmt_total_paid->execute();
$total_paid_amount = $stmt_total_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;

// Calculate total arrears
$total_arrears = $total_overdue_amount;

// Fetch total disbursed loans
$sql_total_loans = "SELECT SUM(total_amount) AS total_loans 
                    FROM loan_applications 
                    WHERE loan_status = 'approved'";
$stmt_total_loans = $conn->prepare($sql_total_loans);
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

// Calculate Portfolio at Risk (PAR)
$par = ($total_loan_amount > 0) ? ($total_arrears / $total_loan_amount) * 100 : 0;

// Calculate Performing Book
$performing_book = max(0, $total_loan_amount - $total_arrears - $total_paid_amount);

// Calculate Loan Book
$loan_book = $performing_book + $total_arrears;

// Fetch total performing loans
$sql_total_performing = "SELECT SUM(amount - paid) AS total_performing 
                         FROM repayments 
                         INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                         WHERE loan_applications.loan_status = 'approved' 
                         AND (amount - paid) = 0";
$stmt_total_performing = $conn->prepare($sql_total_performing);
$stmt_total_performing->execute();
$total_performing_loans = $stmt_total_performing->get_result()->fetch_assoc()['total_performing'] ?? 0;

// Fetch total number of clients
$sql_total_clients = "SELECT COUNT(*) AS total_clients FROM borrowers";
$stmt_total_clients = $conn->prepare($sql_total_clients);
$stmt_total_clients->execute();
$total_clients = $stmt_total_clients->get_result()->fetch_assoc()['total_clients'] ?? 0;

// Fetch total number of clients in arrears
$sql_clients_in_arrears = "SELECT COUNT(DISTINCT borrowers.id) AS clients_in_arrears 
                           FROM borrowers
                           INNER JOIN loan_applications ON borrowers.id = loan_applications.borrower
                           INNER JOIN repayments ON loan_applications.id = repayments.loan_id
                           WHERE repayments.repayment_date < CURDATE() 
                           AND (repayments.amount - repayments.paid) > 0";
$stmt_clients_in_arrears = $conn->prepare($sql_clients_in_arrears);
$stmt_clients_in_arrears->execute();
$clients_in_arrears = $stmt_clients_in_arrears->get_result()->fetch_assoc()['clients_in_arrears'] ?? 0;

// Query for upcoming repayments
$sql_due = "SELECT 
                borrowers.full_name,
                loan_applications.loan_status,  
                loan_applications.loan_product,
                loan_applications.total_amount, 
                SUM(repayments.amount - repayments.paid) AS total_amount_due, 
                MIN(repayments.repayment_date) AS next_due_date 
            FROM repayments 
            INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
            INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
            WHERE repayments.repayment_date >= CURDATE() 
            AND loan_applications.loan_status = 'approved'
            AND (repayments.amount - repayments.paid) > 0
            GROUP BY repayments.loan_id, borrowers.full_name, loan_applications.loan_product, loan_applications.total_amount";

$stmt_due = $conn->prepare($sql_due);
$stmt_due->execute();
$result_due = $stmt_due->get_result();

// Query for overdue repayments
$sql_overdue = "SELECT borrowers.full_name, 
                       loan_applications.loan_product, 
                       loan_applications.loan_status, 
                       SUM(repayments.amount - repayments.paid) AS total_amount, 
                       MIN(repayments.repayment_date) AS earliest_due_date 
                FROM repayments 
                INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                WHERE repayments.repayment_date < CURDATE() 
                  AND loan_applications.loan_status = 'approved' 
                  AND (repayments.amount - repayments.paid) > 0
                GROUP BY repayments.loan_id, 
                         borrowers.full_name, 
                         loan_applications.loan_product";

$stmt_overdue = $conn->prepare($sql_overdue);
$stmt_overdue->execute();
$result_overdue = $stmt_overdue->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Microfinance</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #e3f2fd; /* Sky-blue background */
            margin: 0;
            padding: 0;
        }
        .dashboard-metrics {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end; /* Align metrics to the right edge */
            gap: 15px; /* Gap between metrics */
            margin-top: 20px;
            margin-right: 20px; /* Slightly touch the right edge */
        }
        .metric {
            background-color: #ffffff;
            border: 1px solid #90caf9;
            border-radius: 10px;
            padding: 10px; /* Reduced padding */
            text-align: center;
            width: 180px; /* Reduced width for uniformity */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
        }
        .metric.loan-book {
            width: 150px; /* Further reduced size for Loan Book metric */
        }
        .metric:hover {
            transform: scale(1.05);
        }
        .metric h2 {
            font-size: 18px; /* Reduced font size */
            font-weight: bold;
            color: #1976d2;
        }
        .metric p {
            font-size: 14px; /* Reduced font size */
            color: #424242;
        }
        .chart-container {
            width: 100%;
            max-width: 700px; /* Reduced max width */
            margin: 20px auto; /* Reduced margin */
            background-color: #ffffff;
            padding: 15px; /* Reduced padding */
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            padding: 10px 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px; /* Reduced font size */
            color: #000; /* Removed theme color */
        }
        @media (max-width: 768px) {
            .metric {
                flex: 1 1 100%; /* Stack metrics vertically on smaller screens */
            }
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
<main class="main">
    <div class="header">
        <h1>Manager Dashboard</h1>
    </div>
    <div class="container mt-4">
        <div class="dashboard-metrics">
            <!-- Metrics -->
            <a href="overdue_repayments.php"><div class="metric">
                <h2>KSH <?php echo number_format($total_arrears, 2); ?></h2>
                <p>Total Arrears</p>
            </div></a>
            <a href="approved-loans.php"><div class="metric">
                <h2>KSH <?php echo number_format($total_loan_amount, 2); ?></h2>
                <p>Total Disbursed Loans</p>
            </div></a>
            <a href="performingBook.php"><div class="metric">
                <h2>KSH <?php echo number_format($performing_book, 2); ?></h2>
                <p>Performing Book</p>
            </div></a>
            <div class="metric loan-book">
                <h2>KSH <?php echo number_format($loan_book, 2); ?></h2>
                <p>Loan Book</p>
            </div>
            <div class="metric">
                <h2><?php echo number_format($par, 2); ?>%</h2>
                <p>Portfolio At Risk</p>
            </div>
            <div class="metric">
                <h2><?php echo $total_clients; ?></h2>
                <p>Total Clients</p>
            </div>
            <div class="metric">
                <h2><?php echo $clients_in_arrears; ?></h2>
                <p>Clients in Arrears</p>
            </div>
        </div>

        <div class="chart-container mt-5">
            <canvas id="loanChart"></canvas>
        </div>
    </div>
</main>

<script>
    const ctx = document.getElementById('loanChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Total Disbursed Loans', 'Total Overdue', 'Portfolio At Risk'],
            datasets: [{
                label: 'Loan Metrics',
                data: [<?php echo $total_loan_amount; ?>, <?php echo $total_arrears; ?>, <?php echo $par; ?>],
                backgroundColor: ['#42a5f5', '#ef5350', '#ffca28']
            }]
        },
        options: {
            responsive: true,
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
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
