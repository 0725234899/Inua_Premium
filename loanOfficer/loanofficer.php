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
                borrowers.loan_officer, 
                loan_applications.loan_product,
                loan_applications.total_amount, 
                SUM(repayments.amount) AS total_amount_due, 
                repayments.repayment_date 
            FROM repayments 
            INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
            INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
            WHERE repayments.repayment_date >= CURDATE() AND borrowers.loan_officer = ? 
            GROUP BY repayments.loan_id, borrowers.full_name, loan_applications.loan_product, loan_applications.total_amount, repayments.repayment_date";

$stmt_due = $conn->prepare($sql_due);
$stmt_due->bind_param("s", $_SESSION['email']);
$stmt_due->execute();
$result_due = $stmt_due->get_result();

// Query for overdue repayments
$sql_overdue = "SELECT borrowers.full_name, borrowers.loan_officer, 
                       loan_applications.loan_product, 
                       COALESCE(SUM(repayments.amount), 0) AS total_amount, 
                       repayments.repayment_date, repayments.paid 
                FROM repayments 
                INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                WHERE repayments.repayment_date < CURDATE() 
                AND borrowers.loan_officer = ? 
                GROUP BY repayments.loan_id, borrowers.full_name, 
                         loan_applications.loan_product, repayments.repayment_date, repayments.paid";

$stmt_overdue = $conn->prepare($sql_overdue);
$stmt_overdue->bind_param("s", $_SESSION['email']);
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
        :root {
            --primary-color: #e84545;
            --secondary-color: #2a2c39;
            --accent-color: #0ea2bd;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --purple-color: #6f42c1;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
        }

        .dashboard-metrics {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .metric {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            flex: 1;
            margin: 10px;
            min-width: 220px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-top: 5px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }

        .metric:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .metric h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .metric p {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 0;
            font-weight: 500;
        }

        .metric-icon {
            font-size: 28px;
            margin-bottom: 15px;
            display: inline-block;
            background: rgba(232, 69, 69, 0.1);
            color: var(--primary-color);
            padding: 12px;
            border-radius: 50%;
        }

        .arrears-metric { border-top-color: var(--danger-color); }
        .arrears-metric .metric-icon { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }

        .loans-metric { border-top-color: var(--info-color); }
        .loans-metric .metric-icon { background: rgba(23, 162, 184, 0.1); color: var(--info-color); }

        .performing-metric { border-top-color: var(--success-color); }
        .performing-metric .metric-icon { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }

        .book-metric { border-top-color: var(--accent-color); }
        .book-metric .metric-icon { background: rgba(14, 162, 189, 0.1); color: var(--accent-color); }

        .risk-metric { border-top-color: var(--warning-color); }
        .risk-metric .metric-icon { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }

        .chart-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 20px;
            margin: 30px auto;
            width: 90%;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .header {
            background-color: var(--secondary-color);
            color: #ffffff;
            padding: 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header .logo h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 700;
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
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .header .navmenu ul li a.active, .header .navmenu ul li a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }

        .sidebar {
            background-color: var(--secondary-color);
            color: #ffffff;
            padding: 25px 0;
            width: 280px;
            position: fixed;
            height: 100%;
            overflow: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 100;
        }

        .sidebar-header {
            padding: 0 20px 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            text-align: center;
        }

        .sidebar .nav-item {
            margin-bottom: 5px;
            padding: 0 10px;
        }

        .sidebar .nav-item .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 25px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-radius: 6px;
            font-weight: 500;
        }

        .sidebar .nav-item .nav-link i {
            margin-right: 10px;
            font-size: 18px;
        }

        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .main {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .dashboard-header {
            margin-bottom: 30px;
            position: relative;
        }

        .container h1 {
            font-size: 28px;
            margin-bottom: 20px;
            font-weight: 700;
            color: var(--dark-color);
            position: relative;
            display: inline-block;
        }

        .container h1:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 60px;
            height: 4px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }

        .welcome-text {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 30px;
        }

        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 20px;
            margin-top: 30px;
        }

        .table thead th {
            background-color: rgba(42, 44, 57, 0.05);
            color: var(--dark-color);
            font-weight: 600;
            border-bottom: none;
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }

        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }

        .overdue {
            background-color: rgba(220, 53, 69, 0.1);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .status-badge.warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .status-badge.danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 240px;
            }
            .main {
                margin-left: 240px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                width: 280px;
                left: -280px;
                transition: all 0.3s ease;
                z-index: 1000;
            }
            .sidebar.active {
                left: 0;
            }
            .main {
                margin-left: 0;
                padding: 20px;
            }
            .dashboard-metrics {
                flex-direction: column;
            }
            .metric {
                min-width: 100%;
            }
            .toggle-sidebar {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background-color: var(--secondary-color);
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                text-align: center;
                line-height: 40px;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
<?php 
    include '../includes/functions.php';
    include 'includes/header.php'; 
?>

<!-- Mobile Toggle Button -->
<div class="toggle-sidebar d-md-none">
    <i class="bi bi-list"></i>
</div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3>Loan Officer Panel</h3>
    </div>
    <?php include '../includes/sidebar.php'; ?>
</div>

<main class="main">
    <div class="container">
        <div class="dashboard-header">
            <h1>Loan Officer Dashboard</h1>
            <p class="welcome-text">Welcome back! Here's an overview of your loan portfolio and key metrics.</p>
        </div>
        
        <div class="dashboard-metrics">
            <a href="overdue_repayments.php" class="text-decoration-none">
                <div class="metric arrears-metric">
                    <div class="metric-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h2>KSH <?php echo number_format($total_arrears, 2); ?></h2>
                    <p>Total Arrears</p>
                </div>
            </a>
            
            <a href="approved-loans.php" class="text-decoration-none">
                <div class="metric loans-metric">
                    <div class="metric-icon">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <h2>KSH <?php echo number_format($total_loan_amount, 2); ?></h2>
                    <p>Total Disbursed Loans</p>
                </div>
            </a>
            
            <a href="approved-loans.php" class="text-decoration-none">
                <div class="metric performing-metric">
                    <div class="metric-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h2>KSH <?php echo number_format($performing_book, 2); ?></h2>
                    <p>Performing Book</p>
                </div>
            </a>
            
            <a href="approved-loans.php" class="text-decoration-none">
                <div class="metric book-metric">
                    <div class="metric-icon">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <h2>KSH <?php echo number_format($loan_book, 2); ?></h2>
                    <p>Loan Book</p>
                </div>
            </a>
            
            <div class="metric risk-metric">
                <div class="metric-icon">
                    <i class="bi bi-shield-exclamation"></i>
                </div>
                <h2><?php echo number_format($par, 2); ?>%</h2>
                <p>Portfolio At Risk</p>
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">Financial Overview</h3>
                <button id="toggleChartType" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pie-chart"></i> To Pie
                </button>
            </div>
            <canvas id="loanChart" height="300"></canvas>
        </div>

        <div class="table-container">
            <h3 class="mb-3">Upcoming Repayments</h3>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Loan Product</th>
                            <th>Amount Due</th>
                            <th>Due Date</th>
                            <th>Days Remaining</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result_due->num_rows > 0) {
                            while($row = $result_due->fetch_assoc()) {
                                $due_date = new DateTime($row['repayment_date']);
                                $today = new DateTime();
                                $days_remaining = $today->diff($due_date)->days;
                                $status_class = '';
                                
                                if ($days_remaining < 3) {
                                    $status_class = 'warning';
                                } else {
                                    $status_class = 'success';
                                }
                                
                                echo '<tr>

                                    <td>' . htmlspecialchars($row['full_name']) . '</td>

                                    <td>' . htmlspecialchars($row['loan_product']) . '</td>

                                    <td>KSH ' . number_format($row['total_amount_due'], 2) . '</td>

                                    <td>' . date('d M Y', strtotime($row['repayment_date'])) . '</td>

                                    <td>' . $days_remaining . ' days</td>

                                    <td><span class="status-badge ' . $status_class . '">Upcoming</span></td>

                                </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="6" class="text-center">No upcoming repayments found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-container">
            <h3 class="mb-3">Overdue Repayments</h3>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Loan Product</th>
                            <th>Amount Due</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result_overdue->num_rows > 0) {
                            while($row = $result_overdue->fetch_assoc()) {
                                $due_date = new DateTime($row['repayment_date']);
                                $today = new DateTime();
                                $days_overdue = $today->diff($due_date)->days;
                                $status_class = '';
                                
                                if ($days_overdue > 30) {
                                    $status_class = 'danger';
                                } elseif ($days_overdue > 15) {
                                    $status_class = 'warning';
                                } else {
                                    $status_class = 'warning';
                                }
                                
                                echo '<tr>

                                    <td>' . htmlspecialchars($row['full_name']) . '</td>

                                    <td>' . htmlspecialchars($row['loan_product']) . '</td>

                                    <td>KSH ' . number_format($row['total_amount'] - $row['paid'], 2) . '</td>

                                    <td>' . date('d M Y', strtotime($row['repayment_date'])) . '</td>

                                    <td>' . $days_overdue . ' days</td>

                                    <td><span class="status-badge ' . $status_class . '">Overdue</span></td>

                                </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="6" class="text-center">No overdue repayments found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    // Chart initialization
    let chartType = 'bar';
    const ctx = document.getElementById('loanChart').getContext('2d');
    let myChart;
    
    // Function to create or update chart
    function initChart(type) {
        // If chart exists, destroy it first
        if (myChart) {
            myChart.destroy();
        }
        
        // Chart data
        const data = {
            labels: ['Total Disbursed Loans', 'Total Arrears', 'Performing Book', 'Loan Book', 'PAR (%)'],
            datasets: [{
                label: 'Financial Overview',
                data: [
                    <?php echo $total_loan_amount; ?>, 
                    <?php echo $total_arrears; ?>, 
                    <?php echo $performing_book; ?>, 
                    <?php echo $loan_book; ?>, 
                    <?php echo $par; ?>
                ],
                backgroundColor: [
                    'rgba(23, 162, 184, 0.7)',   // info color for loans
                    'rgba(220, 53, 69, 0.7)',    // danger color for arrears
                    'rgba(40, 167, 69, 0.7)',    // success color for performing
                    'rgba(14, 162, 189, 0.7)',   // accent color for book
                    'rgba(255, 193, 7, 0.7)'     // warning color for PAR
                ],
                borderColor: [
                    'rgba(23, 162, 184, 1)',
                    'rgba(220, 53, 69, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(14, 162, 189, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 1,
                hoverOffset: 4
            }]
        };
        
        // Chart options based on type
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: { 
                    display: type === 'pie' || type === 'doughnut',
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    titleFont: { size: 14 },
                    bodyFont: { size: 13 },
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            
                            const value = context.raw;
                            if (context.label === 'PAR (%)') {
                                return `${label}${value.toFixed(2)}%`;
                            } else {
                                return `${label}KSH ${value.toLocaleString()}`;
                            }
                        }
                    }
                }
            },
            scales: type !== 'pie' && type !== 'doughnut' ? {
                y: { 
                    title: { display: true, text: 'Amount (KSH)' },
                    grid: { drawBorder: false }
                },
                x: { 
                    title: { display: true, text: 'Metrics' },
                    grid: { display: false }
                }
            } : undefined
        };
        
        // Create new chart
        myChart = new Chart(ctx, {
            type: type,
            data: data,
            options: options
        });
    }
    
    // Initialize chart with default type
    initChart(chartType);
    
    // Chart type toggle functionality
    document.getElementById('toggleChartType').addEventListener('click', function() {
        // Toggle between chart types
        chartType = chartType === 'bar' ? 'pie' : 
                   chartType === 'pie' ? 'line' : 
                   chartType === 'line' ? 'doughnut' : 'bar';
        
        // Update button text based on next chart type
        const nextType = chartType === 'bar' ? 'pie' : 
                        chartType === 'pie' ? 'line' : 
                        chartType === 'line' ? 'doughnut' : 'bar';
        this.innerHTML = `<i class="bi bi-${nextType === 'line' ? 'graph-up' : 
                               nextType === 'bar' ? 'bar-chart' : 
                               nextType === 'pie' ? 'pie-chart' : 'circle'}"></i> To ${nextType.charAt(0).toUpperCase() + nextType.slice(1)}`;
        
        // Initialize chart with new type
        initChart(chartType);
    });
    
    // Mobile sidebar toggle
    document.querySelector('.toggle-sidebar').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
