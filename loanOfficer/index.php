<?php
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure session variable is set
if (!isset($_SESSION['email'])) {
    die("Access denied. No session found.");
}

include_once('db.php');

$email = $_SESSION['email'];

// Fetch total overdue amount (Filtered by Loan Officer)
$sql_total_overdue = "SELECT CEIL(SUM(amount - paid)) AS total_overdue 
                      FROM repayments 
                      INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                      INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                      WHERE repayment_date < CURDATE() 
                        AND loan_applications.loan_status = 'approved'
                        AND borrowers.loan_officer = ?
                        AND (amount - paid) > 0";
$stmt_total_overdue = $conn->prepare($sql_total_overdue);
$stmt_total_overdue->bind_param("s", $email);
$stmt_total_overdue->execute();
$total_overdue_amount = $stmt_total_overdue->get_result()->fetch_assoc()['total_overdue'] ?? 0;

// Fetch total paid amount (Filtered by Loan Officer)
$sql_total_paid = "SELECT CEIL(SUM(paid)) AS total_paid 
                   FROM repayments 
                   INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                   INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                   WHERE loan_applications.loan_status = 'approved'
                     AND borrowers.loan_officer = ?";
$stmt_total_paid = $conn->prepare($sql_total_paid);
$stmt_total_paid->bind_param("s", $email);
$stmt_total_paid->execute();
$total_paid_amount = $stmt_total_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;

// Fetch total disbursed loans (Filtered by Loan Officer)
$sql_total_loans = "SELECT CEIL(SUM(loan_applications.total_amount)) AS total_loans 
                    FROM loan_applications 
                    INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                    WHERE loan_applications.loan_status = 'approved' 
                      AND borrowers.loan_officer = ?";
$stmt_total_loans = $conn->prepare($sql_total_loans);
$stmt_total_loans->bind_param("s", $email);
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

// Calculate Performing Book
$performing_book = max(0, $total_loan_amount - $total_overdue_amount - $total_paid_amount);

// Calculate Loan Book
$loan_book = $performing_book + $total_overdue_amount;

// Calculate Portfolio at Risk (PAR)
$par = ($total_loan_amount > 0) ? ($total_overdue_amount / $total_loan_amount) * 100 : 0;

// Fetch total due amount for today (Filtered by Loan Officer)
$sql_due_loans = "SELECT CEIL(SUM(amount - paid)) AS total_due_loans 
                  FROM repayments 
                  INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                  INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                  WHERE repayment_date = CURDATE() 
                    AND loan_applications.loan_status = 'approved' 
                    AND borrowers.loan_officer = ?
                    AND (amount - paid) > 0";
$stmt_due_loans = $conn->prepare($sql_due_loans);
$stmt_due_loans->bind_param("s", $email);
$stmt_due_loans->execute();
$total_due_loans = $stmt_due_loans->get_result()->fetch_assoc()['total_due_loans'] ?? 0;

// Fetch total number of clients for the loan officer
$sql_total_clients = "SELECT COUNT(DISTINCT borrowers.id) AS total_clients 
                      FROM borrowers 
                      WHERE loan_officer = ?";
$stmt_total_clients = $conn->prepare($sql_total_clients);
$stmt_total_clients->bind_param("s", $email);
$stmt_total_clients->execute();
$total_clients = $stmt_total_clients->get_result()->fetch_assoc()['total_clients'] ?? 0;

// Fetch total number of clients in arrears (distinct clients with overdue repayments)
$sql_clients_in_arrears = "SELECT COUNT(DISTINCT borrowers.id) AS clients_in_arrears 
                           FROM borrowers
                           INNER JOIN loan_applications ON borrowers.id = loan_applications.borrower
                           INNER JOIN repayments ON loan_applications.id = repayments.loan_id
                           WHERE repayment_date < CURDATE() 
                             AND (amount - paid) > 0
                             AND borrowers.loan_officer = ?";
$stmt_clients_in_arrears = $conn->prepare($sql_clients_in_arrears);
$stmt_clients_in_arrears->bind_param("s", $email);
$stmt_clients_in_arrears->execute();
$clients_in_arrears = $stmt_clients_in_arrears->get_result()->fetch_assoc()['clients_in_arrears'] ?? 0;

// Fetch names of clients in arrears and their arrears amounts filtered by loan officer
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
    AND borrowers.loan_officer = ?
GROUP BY 
    borrowers.full_name";

$stmt_clients_in_arrears_details = $conn->prepare($sql_clients_in_arrears_details);
$stmt_clients_in_arrears_details->bind_param("s", $email);
$stmt_clients_in_arrears_details->execute();
$result_clients_in_arrears_details = $stmt_clients_in_arrears_details->get_result();

// Fetch names of clients with due repayments today and their due amounts filtered by loan officer
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
    AND borrowers.loan_officer = ?
GROUP BY 
    borrowers.full_name";

$stmt_clients_due_today = $conn->prepare($sql_clients_due_today);
$stmt_clients_due_today->bind_param("s", $email);
$stmt_clients_due_today->execute();
$result_clients_due_today = $stmt_clients_due_today->get_result();

// Fetch recent repayments
$sql_recent_repayments = "SELECT 
    borrowers.full_name AS client_name,
    repayments.paid AS repaid_amount,
    DATE_FORMAT(repayments.repayment_date, '%d/%m/%Y') AS repayment_date
FROM 
    borrowers
INNER JOIN 
    loan_applications ON borrowers.id = loan_applications.borrower
INNER JOIN 
    repayments ON loan_applications.id = repayments.loan_id
WHERE 
    repayments.paid > 0
    AND borrowers.loan_officer = ?
ORDER BY repayments.repayment_date DESC
LIMIT 15";

$stmt_recent_repayments = $conn->prepare($sql_recent_repayments);
$stmt_recent_repayments->bind_param("s", $email);
$stmt_recent_repayments->execute();
$result_recent_repayments = $stmt_recent_repayments->get_result();
$recent_repayments = [];
while ($row = $result_recent_repayments->fetch_assoc()) {
    $recent_repayments[] = htmlspecialchars($row['client_name']) . " repaid KSH " . number_format($row['repaid_amount'], 2) . " on " . htmlspecialchars($row['repayment_date']);
}
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
            --bg: #edf6ff;
            --panel: #ffffff;
            --panel-border: #dfeaf7;
            --primary: #0d6efd;
            --primary-dark: #0a58ca;
            --text: #1a2433;
            --muted: #5d6d7a;
            --shadow: 0 12px 30px rgba(15, 76, 130, 0.08);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding-top: 70px;
        }

        .admin-page {
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            width: 250px;
            background: #f8f9fa;
            border-right: 1px solid var(--panel-border);
            padding: 18px 14px;
            overflow-y: auto;
            z-index: 100;
            box-shadow: inset -1px 0 0 rgba(18, 45, 89, 0.05);
        }

        .sidebar .nav-link {
            color: #27364a;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(90deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.18));
            color: var(--primary-dark);
        }

        .main {
            margin-left: 250px;
            padding: 24px 28px 40px;
        }

        .page-header {
            background: linear-gradient(90deg, rgba(13, 110, 253, 0.12), rgba(0, 198, 255, 0.08));
            border: 1px solid var(--panel-border);
            border-radius: 14px;
            box-shadow: var(--shadow);
            padding: 22px 26px;
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin: 0;
            font-size: clamp(1.8rem, 2vw, 2.2rem);
            font-weight: 700;
            color: #11253f;
        }

        .dashboard-metrics {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-top: 4px;
            margin-bottom: 24px;
        }

        .metric {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: 14px;
            box-shadow: var(--shadow);
            padding: 18px 16px;
            min-height: 132px;
            min-width: 180px;
            flex: 1 1 180px;
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .metric:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(15, 76, 130, 0.12);
        }

        .metric h2 {
            margin: 0 0 8px;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .metric p {
            margin: 0;
            font-size: 0.92rem;
            color: var(--muted);
            font-weight: 600;
        }

        .metric.loan-book {
            min-width: 150px;
        }

        .chart-container {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: var(--shadow);
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 24px;
        }

        .chart-container > div {
            flex: 1 1 320px;
            max-width: 420px;
        }

        .marquee-box {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 10px 12px;
            margin-bottom: 20px;
            font-size: 0.92rem;
            color: #304459;
        }

        @media (max-width: 991px) {
            body {
                padding-top: 66px;
            }

            .sidebar {
                width: min(82vw, 260px);
                transform: translateX(-110%);
                transition: transform 0.3s ease;
                z-index: 1040;
            }

            .sidebar.collapsed {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
                padding: 18px 14px 28px;
            }

            .page-header {
                padding: 18px 18px;
            }

            .dashboard-metrics {
                gap: 12px;
            }

            .metric {
                flex: 1 1 calc(50% - 12px);
                min-width: 140px;
            }
        }

        @media (max-width: 600px) {
            .page-header h1 {
                font-size: 1.5rem;
                text-align: center;
            }

            .metric {
                flex: 1 1 100%;
                min-width: 100%;
                min-height: 112px;
            }

            .metric h2 {
                font-size: 1.25rem;
            }

            .chart-container {
                padding: 12px;
            }

            .chart-container > div {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .marquee-box {
                font-size: 0.82rem;
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body class="admin-page">
<?php include '../includes/functions.php'; ?>
<?php include 'includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1>Loan Officer Dashboard</h1>
    </div>

    <div class="container-fluid px-0">
        <marquee behavior="scroll" direction="left" scrollamount="3" class="marquee-box">
            <?php while ($row = $result_clients_in_arrears_details->fetch_assoc()): ?>
                <span style="color: #d32f2f; margin-right: 26px;">
                    <?= htmlspecialchars($row['client_name']) . " (Arrears: KSH " . number_format($row['arrears_amount'], 2) . ")"; ?>
                </span>
            <?php endwhile; ?>
            <?php while ($row = $result_clients_due_today->fetch_assoc()): ?>
                <span style="color: #1b8f5a; margin-right: 26px;">
                    <?= htmlspecialchars($row['client_name']) . " (Due Today: KSH " . number_format($row['due_amount'], 2) . ")"; ?>
                </span>
            <?php endwhile; ?>
        </marquee>

        <div class="dashboard-metrics">
            <a href="overdue_repayments.php" class="metric">
                <h2>KSH <?php echo number_format(ceil($total_overdue_amount)); ?></h2>
                <p>Total Arrears</p>
            </a>
            <a href="approved-loans.php" class="metric">
                <h2>KSH <?php echo number_format(ceil($total_loan_amount)); ?></h2>
                <p>Total Disbursed Loans</p>
            </a>
            <a href="performingBook.php" class="metric">
                <h2>KSH <?php echo number_format(ceil($performing_book)); ?></h2>
                <p>Performing Book</p>
            </a>
            <div class="metric loan-book">
                <h2>KSH <?php echo number_format(ceil($loan_book)); ?></h2>
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
            <a href="due_loans.php" class="metric">
                <h2>KSH <?php echo number_format(ceil($total_due_loans)); ?></h2>
                <p>Due Loans</p>
            </a>
        </div>

        <div class="chart-container mt-4">
            <div>
                <canvas id="parPieChart" style="width: 100%; height: 300px;"></canvas>
            </div>
            <div>
                <canvas id="loanChart" style="width: 100%; height: 300px;"></canvas>
            </div>
        </div>
    </div>
</main>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvasConfig = {
            responsive: true,
            maintainAspectRatio: false
        };

        if (window.Chart) {
            new Chart(document.getElementById('parPieChart'), {
                type: 'pie',
                data: {
                    labels: ['Portfolio at Risk', 'Healthy Portfolio'],
                    datasets: [{
                        data: [<?php echo max(0, min(100, $par)); ?>, <?php echo max(0, 100 - min(100, $par)); ?>],
                        backgroundColor: ['#ff6b6b', '#4dabf7']
                    }]
                },
                options: canvasConfig
            });

            new Chart(document.getElementById('loanChart'), {
                type: 'bar',
                data: {
                    labels: ['Total Loans', 'Total Overdue', 'Performing Book', 'Due Today'],
                    datasets: [{
                        label: 'KSH',
                        data: [
                            <?php echo (float) $total_loan_amount; ?>,
                            <?php echo (float) $total_overdue_amount; ?>,
                            <?php echo (float) $performing_book; ?>,
                            <?php echo (float) $total_due_loans; ?>
                        ],
                        backgroundColor: ['#4dabf7', '#ff8c69', '#63c76a', '#ffc857']
                    }]
                },
                options: {
                    ...canvasConfig,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    });
</script>
</body>
</html>
