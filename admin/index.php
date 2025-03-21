<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// Fetch total overdue amount
$sql_total_overdue = "SELECT SUM(amount) AS total_overdue FROM repayments WHERE repayment_date < CURDATE()";
$sql_total_paid = "SELECT SUM(paid) AS total_paid FROM repayments WHERE repayment_date < CURDATE()";

$stmt_total_overdue = $conn->prepare($sql_total_overdue);
$stmt_total_overdue->execute();
$total_overdue_amount = $stmt_total_overdue->get_result()->fetch_assoc()['total_overdue'] ?? 0;

$stmt_total_paid = $conn->prepare($sql_total_paid);
$stmt_total_paid->execute();
$total_paid_amount = $stmt_total_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;
$total_arrears=$total_overdue_amount-$total_paid_amount;
// Fetch total disbursed loans
$sql_total_loans = "SELECT SUM(total_amount) AS total_loans FROM loan_applications";
$stmt_total_loans = $conn->prepare($sql_total_loans);
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

// Calculate Portfolio at Risk (PAR) - Assuming PAR is (Overdue / Total Loans) * 100
$par = ($total_loan_amount > 0) ? ($total_arrears / $total_loan_amount) * 100 : 0;
$performing_book=$total_loan_amount-$total_paid_amount;
$loan_book=$performing_book+$total_arrears;
// Query for upcoming repayments
$sql_due = "SELECT 
                borrowers.full_name,
                loan_applications.loan_status,  
                loan_applications.loan_product,
                loan_applications.total_amount, 
                SUM(repayments.amount) AS total_amount_due, 
                repayments.repayment_date 
            FROM repayments 
            INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
            INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
            WHERE repayments.repayment_date >= CURDATE() 
            AND loan_applications.loan_status = 'approved' 
            GROUP BY repayments.loan_id, borrowers.full_name, loan_applications.loan_product, loan_applications.total_amount, repayments.repayment_date";

$stmt_due = $conn->prepare($sql_due);
$stmt_due->execute();
$result_due = $stmt_due->get_result();

// Query for overdue repayments
$sql_overdue = "SELECT borrowers.full_name, 
                       loan_applications.loan_product, 
                       loan_applications.loan_status, 
                       SUM(repayments.amount) AS total_amount, 
                       repayments.repayment_date, 
                       repayments.paid 
                FROM repayments 
                INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                WHERE repayments.repayment_date < CURDATE() 
                  AND loan_applications.loan_status = 'approved' 
                GROUP BY repayments.loan_id, 
                         borrowers.full_name, 
                         loan_applications.loan_product, 
                         repayments.repayment_date, 
                         repayments.paid";

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
        .dashboard-metrics {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .metric {
            background-color: #ffffff;
            border: 1px solid #212529;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            flex: 1;
            margin: 10px;
            min-width: 250px;
        }
        .chart-container {
            width: 80%;
            margin: auto;
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
        .header .navmenu ul li a.active, .header .navmenu ul li a:hover {
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
        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: #e84545;
        }
        .main {
            margin-left: 270px;
            padding: 20px;
        }
        .table-container {
            overflow-x: auto;
        }
        .container h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        .table thead th {
            background-color: #f5f5f5;
        }
        .overdue {
            background-color: #f8d7da;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
            }
            .main {
                margin-left: 0;
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
    <div class="container mt-5">
        <h1 class="text-center">Admin Dashboard</h1>
        
        <div class="dashboard-metrics">
            <a href="overdue_repayments.php"><div class="metric">
            
                <h2>KSH <?php echo number_format($total_arrears, 2); ?></h2>
                <p>Total Arrears</p>
            </div>
    </a>
            <a href="http://localhost/InuaPremium/admin/approved-loans.php"><div class="metric">
                
                <h2>KSH <?php echo number_format($total_loan_amount, 2); ?></h2>
                <p>Total Disbursed Loans</p>
            </div></a>
            </a>
            <a href="performingBook.php"><div class="metric">
                
                <h2>KSH <?php echo number_format($performing_book, 2); ?></h2>
                <p>Performing Book</p>
            </div></a>
            <a href="approved-loans.php"><div class="metric">
                
                <h2>KSH <?php echo number_format($loan_book, 2); ?></h2>
                <p>Loan Book</p>
            </div></a>
            <div class="metric">
                <h2><?php echo number_format($par, 2); ?>%</h2>
                <p>Portfolio At Risk</p>
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
                label: 'Financial Overview',
                data: [<?php echo $total_loan_amount; ?>, <?php echo $total_overdue_amount; ?>, <?php echo $par; ?>],
                backgroundColor: ['blue', 'red', 'orange']
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
