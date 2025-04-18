<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/functions.php';
include 'includes/header.php'; 
include 'db.php';

if (!isset($_GET['loanId']) || !is_numeric($_GET['loanId'])) {
    die("Invalid Loan ID.");
}

$loanId = intval($_GET['loanId']); // Secure the input

// Fetch loan details
$sql_loan = "SELECT 
    borrowers.full_name AS borrower_name, 
    borrowers.unique_number AS client_id,
    loan_products.name AS loan_product_name,
    loan_applications.principal AS principal_amount,
    DATE_FORMAT(loan_applications.loan_release_date, '%d/%m/%Y') AS loan_release_date,
    SUM(repayments.amount) AS total_amount_due, 
    SUM(repayments.paid) AS total_amount_paid 
FROM 
    repayments
INNER JOIN 
    loan_applications ON repayments.loan_id = loan_applications.id
INNER JOIN 
    borrowers ON loan_applications.borrower = borrowers.id
INNER JOIN 
    loan_products ON loan_applications.loan_product = loan_products.id
WHERE 
    repayments.loan_id = ?
GROUP BY 
    repayments.loan_id, borrowers.full_name, borrowers.unique_number, loan_products.name, 
    loan_applications.principal, loan_applications.loan_release_date";

$stmt_loan = $conn->prepare($sql_loan);
$stmt_loan->bind_param("i", $loanId);
$stmt_loan->execute();
$result_loan = $stmt_loan->get_result();
$loan = $result_loan->fetch_assoc();

if (!$loan) {
    die("Loan details not found.");
}

$totalDue = $loan['total_amount_due'];
$totalPaid = $loan['total_amount_paid'];
$balance = $totalDue - $totalPaid;

// Fetch repayment history
$sql_history = "SELECT 
    amount, 
    paid, 
    DATE_FORMAT(repayment_date, '%d/%m/%Y') AS repayment_date 
FROM 
    repayments 
WHERE 
    loan_id = ? 
ORDER BY 
    repayment_date DESC";

$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("i", $loanId);
$stmt_history->execute();
$result_history = $stmt_history->get_result();

$sql_records = "SELECT 
    Amount, 
    DATE_FORMAT(PaymentDate, '%d/%m/%Y') AS PaymentDate 
FROM 
    payment_date_records 
WHERE 
    loan_id = ? 
ORDER BY 
    PaymentDate DESC";

$stmt_records = $conn->prepare($sql_records);
$stmt_records->bind_param("i", $loanId);
$stmt_records->execute();
$result_records= $stmt_records->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repayment Details</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .container {
            margin-top: 30px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
        }
        .card-title {
            font-size: 22px;
            font-weight: bold;
            color: #e84545;
        }
        .table {
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead {
            background-color: #e84545;
            color: #ffffff;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .btn-secondary {
            background-color: #e84545;
            border: none;
        }
        .btn-secondary:hover {
            background-color: #d43d3d;
        }
        .section-title {
            font-size: 26px;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 20px;
        }
        .highlight {
            color: #e84545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center section-title">Repayment Details</h2>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Loan Information</h5>
                <p><strong>Borrower:</strong> <span class="highlight"><?php echo htmlspecialchars($loan['borrower_name']); ?></span></p>
                <p><strong>Client ID:</strong> <span class="highlight"><?php echo htmlspecialchars($loan['client_id']); ?></span></p>
                <p><strong>Loan Product:</strong> <span class="highlight"><?php echo htmlspecialchars($loan['loan_product_name']); ?></span></p>
                <p><strong>Principal Amount:</strong> <span class="highlight"><?php echo number_format($loan['principal_amount'], 2); ?> KES</span></p>
                <p><strong>Loan Release Date:</strong> <span class="highlight"><?php echo htmlspecialchars($loan['loan_release_date']); ?></span></p>
                <p><strong>Total Amount:</strong> <span class="highlight"><?php echo number_format($totalDue, 2); ?> KES</span></p>
                <p><strong>Total Paid:</strong> <span class="highlight"><?php echo number_format($totalPaid, 2); ?> KES</span></p>
                <p><strong>Balance:</strong> <span class="highlight"><?php echo number_format($balance, 2); ?> KES</span></p>
            </div>
        </div>

        <h3 class="section-title">Payment Records</h3>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Amount Paid</th>
                        <th>Payment Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row_records = $result_records->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo number_format($row_records['Amount'], 2); ?> KES</td>
                            <td><?php echo htmlspecialchars($row_records['PaymentDate']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result_records->num_rows === 0): ?>
                        <tr><td colspan="2" class="text-center">No payment records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h3 class="section-title">Repayment History</h3>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Amount Due</th>
                        <th>Amount Paid</th>
                        <th>Repayment Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result_history->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo number_format($row['amount'], 2); ?> KES</td>
                            <td><?php echo number_format($row['paid'], 2); ?> KES</td>
                            <td><?php echo htmlspecialchars($row['repayment_date']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result_history->num_rows === 0): ?>
                        <tr><td colspan="3" class="text-center">No repayment history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="performingBook.php" class="btn btn-secondary mt-3">Back to Repayments</a>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php 
$stmt_loan->close();
$stmt_history->close();
$conn->close();
?>
