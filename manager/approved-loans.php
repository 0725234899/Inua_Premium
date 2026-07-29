<?php
include 'db.php';

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
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
            color: #212529;
        }
        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 15px 0;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
        }
        .table {
            margin-top: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #e84545;
            color: #ffffff;
            text-align: center;
        }
        .table td {
            text-align: center;
        }
        .btn-primary {
            background-color: #e84545;
            border: none;
            transition: all 0.3s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #d43d3d;
        }
        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #e84545;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
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
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
