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

// Modify queries to filter by loan officer if selected
$officer_filter = ($selected_officer !== 'all') ? "AND users.id = ?" : "";

$officer_filter = ($selected_officer !== 'all') ? "AND users.id = ?" : "";

// Fetch total overdue amount
$sql_total_overdue = "SELECT SUM(repayments.amount) AS total_overdue 
                      FROM repayments 
                      INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id
                      INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                        INNER JOIN users ON borrowers.loan_officer = users.email
                      WHERE repayments.repayment_date < CURDATE() $officer_filter";

$stmt_total_overdue = $conn->prepare($sql_total_overdue);

if ($selected_officer !== 'all') {
    $stmt_total_overdue->bind_param("i", $selected_officer);
}

$stmt_total_overdue->execute();
$total_overdue_amount = $stmt_total_overdue->get_result()->fetch_assoc()['total_overdue'] ?? 0;
$sql_paid = "SELECT SUM(paid) AS total_paid FROM repayments
";
$stmt_paid = $conn->prepare($sql_paid);
$stmt_paid->execute();
$total_paid = $stmt_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;
// Fetch total disbursed loans
$sql_total_loans = "SELECT SUM(total_amount) AS total_loans FROM loan_applications 
INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
INNER JOIN users ON borrowers.loan_officer = users.email
                    WHERE loan_status = 'approved' $officer_filter";

$stmt_total_loans = $conn->prepare($sql_total_loans);
if ($selected_officer !== 'all') {
    $stmt_total_loans->bind_param("i", $selected_officer);
}
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

$total_arrears = $total_overdue_amount;  // Since no paid amount calculation
$performing_book = $total_loan_amount - $total_paid;
$loan_book = $performing_book + $total_arrears;

// Portfolio at Risk (PAR) Calculation
$par = ($total_loan_amount > 0) ? ($total_arrears / $total_loan_amount) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Microfinance</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <h1 class="text-center">Manager Dashboard</h1>
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
            <h2>KSH <?= number_format($total_arrears, 2); ?></h2>
            <p>Total Arrears</p>
        </div>
        <div class="metric">
            <h2>KSH <?= number_format($total_loan_amount, 2); ?></h2>
            <p>Total Disbursed Loans</p>
        </div>
        <div class="metric">
            <?php if($performing_book < 0) { ?>
                <h2>KSH 0.0</h2>
                <p>Performing Book</p>
            </div>
            <?php } else { ?>
            <h2>KSH <?= number_format($performing_book, 2); ?></h2>
            <p>Performing Book</p>
        </div>
        <?php } ?>
            
        <div class="metric">
            <?php if($loan_book < 0) { ?>
                <h2>KSH 0.0</h2>
                <p>Loan Book</p>
            </div>
            <?php } else { ?>
            <h2>KSH <?= number_format($loan_book, 2); ?></h2>
            <p>Loan Book</p>
        </div>
        <?php } ?>
        <div class="metric">
            <h2><?= number_format($par, 2); ?>%</h2>
            <p>Portfolio At Risk</p>
        </div>
    </div>

    <!-- Chart -->
    <div class="chart-container mt-5">
        <canvas id="loanChart"></canvas>
    </div>
</div>

<script>
    const ctx = document.getElementById('loanChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Total Disbursed Loans', 'Total Overdue', 'Portfolio At Risk'],
            datasets: [{
                label: 'Financial Overview',
                data: [<?= $total_loan_amount; ?>, <?= $total_overdue_amount; ?>, <?= $par; ?>],
                backgroundColor: ['blue', 'red', 'orange']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { title: { display: true, text: 'Amount (KSH)' } },
                x: { title: { display: true, text: 'Metrics' } }
            }
        }
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
