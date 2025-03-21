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
    loan_products.name AS loan_product_name,
    loan_applications.loan_product, 
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
    repayments.loan_id, borrowers.full_name, loan_applications.loan_product";

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
$sql_history = "SELECT amount, paid, repayment_date FROM repayments WHERE loan_id = ? ORDER BY repayment_date DESC";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("i", $loanId);
$stmt_history->execute();
$result_history = $stmt_history->get_result();
$sql_records = "SELECT Amount, PaymentDate FROM payment_date_records WHERE loan_id = ? ORDER BY PaymentDate DESC";
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
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Repayment Details</h2>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Loan Information</h5>
                <p><strong>Borrower:</strong> <?php echo htmlspecialchars($loan['borrower_name']); ?></p>
                <p><strong>Loan Product:</strong> <?php echo htmlspecialchars($loan['loan_product_name']); ?></p>
                <p><strong>Total Amount:</strong> <?php echo number_format($totalDue, 2); ?> KES</p>
                <p><strong>Total Paid:</strong> <?php echo number_format($totalPaid, 2); ?> KES</p>
                <p><strong>Balance:</strong> <?php echo number_format($balance, 2); ?> KES</p>
            </div>
        </div>
        <h3>Payment Records</h3>
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
                        <td><?php echo $row_records['Amount']; ?> KES</td>
                        
                        <td><?php echo $row_records['PaymentDate']; ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result_history->num_rows === 0): ?>
                    <tr><td colspan="3">No repayment records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <h3>Repayment History</h3>
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
                        <td><?php echo $row['paid']; ?> KES</td>
                        <td><?php echo htmlspecialchars($row['repayment_date']); ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result_history->num_rows === 0): ?>
                    <tr><td colspan="3">No repayment records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
       

        <a href="performingBook.php" class="btn btn-secondary">Back to Repayments</a>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php 
$stmt_loan->close();
$stmt_history->close();
$conn->close();
?>
