<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';
include '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure session variable is set
if (!isset($_SESSION['email'])) {
    die("Access denied. No session found.");
}

$email = $_SESSION['email'];

// Get the selected day from the request or default to all days
$selected_day = isset($_GET['day']) ? $_GET['day'] : 'all';
$day_filter = ($selected_day !== 'all') ? "AND DAYNAME(repayments.repayment_date) = ?" : "";

// Query to fetch all clients with due loans filtered by loan officer and day
$sql_due_loans = "SELECT 
                    borrowers.full_name AS borrower_name, 
                    borrowers.mobile AS phone_number, 
                    loan_applications.id AS loan_id, 
                    loan_applications.total_amount AS total_disbursed, 
                    SUM(repayments.paid) AS total_paid, 
                    (loan_applications.total_amount - SUM(repayments.paid)) AS loan_balance, 
                    repayments.amount - repayments.paid AS due_amount, 
                    DATE_FORMAT(repayments.repayment_date, '%d/%m/%Y') AS due_date 
                  FROM 
                    repayments
                  INNER JOIN 
                    loan_applications ON repayments.loan_id = loan_applications.id
                  INNER JOIN 
                    borrowers ON loan_applications.borrower = borrowers.id
                  WHERE 
                    (repayments.amount - repayments.paid) > 0
                    AND borrowers.loan_officer = ?
                    $day_filter
                  GROUP BY 
                    repayments.id
                  ORDER BY repayments.repayment_date ASC";

$stmt_due_loans = $conn->prepare($sql_due_loans);
if ($selected_day !== 'all') {
    $stmt_due_loans->bind_param("ss", $email, $selected_day);
} else {
    $stmt_due_loans->bind_param("s", $email);
}
$stmt_due_loans->execute();
$result_due_loans = $stmt_due_loans->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Clients with Due Loans</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 30px;
        }
        .table-container {
            overflow-x: auto;
        }
        .table thead th {
            background-color: #007bff;
            color: #ffffff;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <input type="text" id="searchInput" placeholder="Search by Borrower or Phone..." class="form-control" style="width: 300px;">
    </div>
    <h2 class="text-center">All Clients with Due Loans</h2>

    <!-- Day Tabs -->
    <ul class="nav nav-tabs justify-content-center mt-3">
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'all') ? 'active' : '' ?>" href="?day=all">All Days</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Monday') ? 'active' : '' ?>" href="?day=Monday">Monday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Tuesday') ? 'active' : '' ?>" href="?day=Tuesday">Tuesday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Wednesday') ? 'active' : '' ?>" href="?day=Wednesday">Wednesday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Thursday') ? 'active' : '' ?>" href="?day=Thursday">Thursday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Friday') ? 'active' : '' ?>" href="?day=Friday">Friday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Saturday') ? 'active' : '' ?>" href="?day=Saturday">Saturday</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($selected_day === 'Sunday') ? 'active' : '' ?>" href="?day=Sunday">Sunday</a>
        </li>
    </ul>

    <div class="table-container mt-4">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Borrower</th>
                    <th>Phone Number</th>
                    <th>Loan ID</th>
                    <th>Total Disbursed (KSH)</th>
                    <th>Total Paid (KSH)</th>
                    <th>Loan Balance (KSH)</th>
                    <th>Due Amount (KSH)</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_due_loans->num_rows > 0): ?>
                    <?php while ($row = $result_due_loans->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                            <td>
                                <a href="repayment_details.php?loanId=<?php echo $row['loan_id']; ?>">
                                    <?php echo htmlspecialchars($row['loan_id']); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($row['total_disbursed'], 2); ?></td>
                            <td><?php echo number_format($row['total_paid'], 2); ?></td>
                            <td><?php echo number_format($row['loan_balance'], 2); ?></td>
                            <td><?php echo number_format($row['due_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No clients with due loans found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('searchInput').addEventListener('input', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('.table tbody tr');

        rows.forEach(row => {
            const borrowerName = row.cells[0]?.textContent.toLowerCase() || '';
            const phoneNumber = row.cells[1]?.textContent.toLowerCase() || '';
            row.style.display = (borrowerName.includes(filter) || phoneNumber.includes(filter)) ? '' : 'none';
        });
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
