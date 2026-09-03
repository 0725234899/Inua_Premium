<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

// Fetch approved loans (basic list)
$sql_loans = "SELECT 
                                l.id, 
                                b.full_name AS borrower_name, 
                                p.name AS loan_product_name, 
                                l.principal, 
                                (l.total_amount - l.principal) AS interest, 
                                l.interest_method, 
                                l.loan_interest, 
                                l.loan_duration, 
                                l.repayment_cycle, 
                                l.number_of_repayments, 
                                l.processing_fee, 
                                l.registration_fee, 
                                l.total_amount, 
                                l.loan_release_date 
                            FROM loan_applications l 
                            INNER JOIN borrowers b ON l.borrower = b.id 
                            INNER JOIN loan_products p ON l.loan_product = p.id 
                            WHERE l.loan_status = 'approved'
                            ORDER BY l.loan_release_date DESC";

$result_loans = $conn->query($sql_loans);

// Fetch total interest metric for the clickable metric
$sql_total_interest = "SELECT CEIL(COALESCE(SUM(total_amount - principal),0)) AS total_interest FROM loan_applications WHERE loan_status = 'approved'";
$res_total_interest = $conn->query($sql_total_interest);
$total_interest_metric = 0;
if ($res_total_interest) {
        $total_interest_metric = $res_total_interest->fetch_assoc()['total_interest'] ?? 0;
}

// (Interest breakdown table moved to manager/index.php)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Loans</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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

        .sidebar {
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            display: none;
        }

        .main {
            margin-left: 250px;
            padding: 34px 22px 60px;
            transition: margin-left 0.3s ease;
        }

        .main.sidebar-collapsed {
            margin-left: 0;
        }

        .header {
            background: var(--ink);
            border-top: 4px solid var(--gold);
            color: white;
            margin: 0 auto;
            max-width: 1280px;
            padding: 30px 34px 27px;
            text-align: left;
        }

        .header h1 {
            font-family: Georgia, serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: normal;
            letter-spacing: .02em;
            margin: 0;
        }

        .sidebar-toggle-btn {
            background: transparent;
            border: 1px solid #82939c;
            border-radius: 0;
            color: white;
            margin-right: 14px;
            padding: 8px 12px;
        }

        .sidebar-toggle-btn:hover {
            background: var(--teal);
            border-color: var(--teal);
            color: white;
        }

        .container {
            max-width: 1280px;
        }

        .container.mt-5 {
            background: var(--paper);
            border: 1px solid var(--line);
            margin-top: 18px !important;
            overflow-x: auto;
            padding: 22px;
        }

        .section-title {
            color: var(--ink);
            font-family: Georgia, serif;
            font-size: 1.45rem;
            font-weight: normal;
            margin: 0;
            text-align: left;
        }

        .table {
            background: var(--paper);
            margin-top: 20px;
            min-width: 1150px;
        }

        .table th {
            background: #edf2f3;
            border-bottom: 2px solid var(--teal);
            color: #425460;
            font-size: .72rem;
            letter-spacing: .08em;
            padding: 14px 12px;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table td {
            border-color: #e6ecef;
            padding: 15px 12px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table tbody tr:hover {
            background: #f7faf9;
        }

        .table a {
            color: var(--teal);
            font-weight: bold;
            text-decoration: none;
        }

        .table a:hover {
            color: var(--ink);
            text-decoration: underline;
        }

        .btn-primary {
            background: transparent;
            border: 1px solid var(--teal);
            border-radius: 0;
            color: var(--teal);
            padding: 8px 14px;
        }

        .btn-primary:hover {
            background: var(--teal);
            border-color: var(--teal);
            color: white;
        }

        .btn-outline-primary {
            border-color: var(--teal);
            border-radius: 0;
            color: var(--teal);
        }

        .btn-outline-primary:hover {
            background: var(--teal);
            border-color: var(--teal);
            color: white;
        }

        footer {
            color: var(--muted);
            font-size: .78rem;
        }

        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 20px 12px 40px;
            }

            .header {
                padding: 24px;
            }

            .container.mt-5 {
                padding: 16px 12px;
            }

            .container.mt-5 > .d-flex {
                align-items: flex-start !important;
                flex-direction: column;
                gap: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebarWrapper">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <main class="main" id="mainContent">
        <div class="header d-flex align-items-center">
            <button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <h1>Approved Loans</h1>
        </div>
        <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title">Loan Applications</h3>
            <a href="index.php#interestTable" class="btn btn-outline-primary">
                <i class="bi bi-currency-exchange"></i> Total Interest: KSH <?php echo number_format($total_interest_metric); ?>
            </a>
        </div>
        <table id="approvedLoansTable" class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Borrower</th>
                    <th>Loan Product</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Interest Method</th>
                    <th>Loan Interest %</th>
                    <th>Duration (months)</th>
                    <th>Repayment Cycle</th>
                    <th>Number of Repayments</th>
                    <th>Processing Fee</th>
                    <th>Registration Fee</th>
                    <th>Total Amount</th>
                    <th>Loan Release Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($loan = $result_loans->fetch_assoc()): ?>
                    <tr>
                        <td><a href="repayment_details.php?loanId=<?= $loan['id']; ?>"><?= $loan['id']; ?></a></td>
                        <td><a href="repayment_details.php?loanId=<?= $loan['id']; ?>"><?= htmlspecialchars($loan['borrower_name']); ?></a></td>
                        <td><?= htmlspecialchars($loan['loan_product_name']); ?></td>
                        <td><?= number_format($loan['principal'], 2); ?></td>
                        <td><?= number_format($loan['interest'], 2); ?></td>
                        <td><?= htmlspecialchars($loan['interest_method']); ?></td>
                        <td><?= number_format($loan['loan_interest'], 2); ?>%</td>
                        <td><?= htmlspecialchars($loan['loan_duration']); ?></td>
                        <td><?= htmlspecialchars($loan['repayment_cycle']); ?></td>
                        <td><?= htmlspecialchars($loan['number_of_repayments']); ?></td>
                        <td><?= number_format($loan['processing_fee'], 2); ?></td>
                        <td><?= number_format($loan['registration_fee'], 2); ?></td>
                        <td><?= number_format($loan['total_amount'], 2); ?></td>
                        <td><?= htmlspecialchars($loan['loan_release_date']); ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result_loans->num_rows === 0): ?>
                    <tr><td colspan="14">No approved loans found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Interest breakdown moved to Manager Dashboard (index.php) -->
        </div>
        <footer class="text-center mt-5">
            <p><em>Powered by AntonTech</em></p>
        </footer>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleButton = document.getElementById('sidebarToggleMain');
            const sidebarWrapper = document.getElementById('sidebarWrapper');
            const mainContent = document.getElementById('mainContent');

            if (toggleButton && sidebarWrapper && mainContent) {
                toggleButton.addEventListener('click', function () {
                    sidebarWrapper.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                });
            }
        });
    </script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
