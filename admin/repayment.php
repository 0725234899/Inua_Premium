<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);
include 'db.php'; // Database connection file
include '../includes/functions.php';
$message = ""; // To store success or error messages

// Search functionality
if (isset($_POST['search'])) {
    $phone_number = trim($_POST['phone_number']);

    // Query to get loan details
    $sql = "SELECT borrowers.id AS borrower_id, borrowers.full_name, borrowers.mobile, 
                   loan_applications.id AS loan_id, loan_applications.loan_product, 
                   SUM(repayments.amount) AS total_due 
            FROM borrowers
            INNER JOIN loan_applications ON borrowers.id = loan_applications.borrower
            INNER JOIN repayments ON loan_applications.id = repayments.loan_id
            WHERE borrowers.mobile = ? 
            GROUP BY loan_applications.id, borrowers.full_name, borrowers.mobile, loan_applications.loan_product";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $message = "<div class='alert alert-danger'>No repayments found for this phone number.</div>";
    }
}

// Repayment functionality
if (isset($_POST['repay'])) {
    $loan_id = $_POST['loan_id'];
    $amount_paid = $_POST['amount_paid'];
    $message = distributeRepayment($loan_id, $amount_paid, $conn);
}

function distributeRepayment($loan_id, $amount_paid, $conn) {
    // Step 1: Get all unpaid installments ordered by due date (earliest first)
    $sql = "SELECT * 
    FROM repayments 
    WHERE loan_id = ? AND COALESCE(paid, 0) < amount 
    ORDER BY repayment_date ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_distributed = 0;

    // Step 2: Loop through installments and allocate payment
    while ($row = $result->fetch_assoc()) {
        echo $row['id'];
        $installment_id = $row['id'];
        $remaining_due = $row['amount'] - $row['paid'];
        echo $installment_id;
        if ($amount_paid <= 0) {
            break; // Stop if all money has been distributed
        }

        if ($amount_paid >= $remaining_due) {
            // Pay off this installment completely
            $new_amount_paid = $row['amount']; // Full payment
            $amount_paid -= $remaining_due; // Deduct from total payment
        } else {
            // Partially pay this installment
            $new_amount_paid = $row['paid'] + $amount_paid;
            $amount_paid = 0; // Payment fully used up
        }

        // Update the installment with the new paid amount
        $update_sql = "UPDATE repayments SET paid = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("di", $new_amount_paid, $installment_id);
        $update_stmt->execute();
        
        $total_distributed += $new_amount_paid; // Track how much is distributed
    }

    // Return success message
    if ($total_distributed > 0) {
        return "<div class='alert alert-success'>Repayment successfully distributed to unpaid installments. Total distributed: " . number_format($total_distributed, 2) . ".</div>";
    } else {
        return "<div class='alert alert-warning'>No repayments were necessary for this loan.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Repayment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" integrity="sha512-jnSuA4Ss2PkkikSOLtYs8BlYIeeIK1h99ty4YfvRPAlzr377vr3CXDb7sb7eEEBYjDtcYj+AjBH3FLv5uSJuXg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="container mt-4">
        <h2>Make a Loan Repayment</h2>
        <?= $message; ?>

        <!-- Search Form -->
        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label for="phone_number" class="form-label">Enter Phone Number:</label>
                <input type="text" name="phone_number" id="phone_number" class="form-control" required>
            </div>
            <button type="submit" name="search" class="btn btn-primary">Search</button>
        </form>

        <?php if (isset($result) && $result->num_rows > 0): ?>
            <h3>Repayment Details</h3>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Phone</th>
                        <th>Loan Product</th>
                        <th>Amount Due</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['full_name']); ?></td>
                            <td><?= htmlspecialchars($row['mobile']); ?></td>
                            <td><?= htmlspecialchars($row['loan_product']); ?></td>
                            <td><?= number_format($row['total_due'], 2); ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="loan_id" value="<?= $row['loan_id']; ?>">
                                    <div class="mb-2">
                                        <input type="number" name="amount_paid" class="form-control" step="0.01" required placeholder="Enter amount">
                                    </div>
                                    <button type="submit" name="repay" class="btn btn-success">Make Repayment</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
