<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';
include '../includes/functions.php';

$message = ""; // To store success or error messages

// Search functionality
if (isset($_POST['search'])) {
    $search_key = trim($_POST['search_key']);
    if (strlen($search_key) < 4 && !preg_match('/^\d{10}$/', $search_key)) {
        $message = "<div class='alert alert-warning text-center'>Please enter at least 4 characters or a valid 10-digit phone number.</div>";
    } else {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('search_btn').style.display = 'none';
            document.getElementById('search_form').style.display = 'none';
        });
        </script>";
        // Query to get loan details
        $sql = "SELECT 
                borrowers.id AS borrower_id, 
                borrowers.full_name, 
                borrowers.mobile, 
                loan_products.name AS loan_product_name,
                loan_applications.id AS loan_id, 
                loan_applications.loan_product, 
                COALESCE(SUM(repayments.amount), 0) AS total_due,
                COALESCE(SUM(repayments.paid), 0) AS total_paid
            FROM borrowers
            INNER JOIN loan_applications ON borrowers.id = loan_applications.borrower
            INNER JOIN loan_products ON loan_applications.loan_product = loan_products.id
            INNER JOIN repayments ON loan_applications.id = repayments.loan_id
            WHERE 
                borrowers.mobile LIKE ? 
                OR borrowers.full_name LIKE ? 
                OR borrowers.unique_number LIKE ?
            GROUP BY 
                borrowers.id, borrowers.full_name, borrowers.mobile, 
                loan_applications.id, loan_applications.loan_product
            ORDER BY total_due DESC";

        $search_term = "%$search_key%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $message = "<div class='alert alert-danger text-center'>No such client on our database</div>";
        }
    }
}

// Repayment functionality
if (isset($_POST['repay'])) {
    $loan_id = $_POST['loan_id'];
    $amount_paid = $_POST['amount_paid'];
    
    if ($amount_paid > 0) {
        $message = distributeRepayment($loan_id, $amount_paid, $conn);
        
        $insertPayment = "INSERT INTO payment_date_records (loan_id, PaymentDate, Amount) VALUES (?, CURDATE(), ?)";
        $insert_stmt = $conn->prepare($insertPayment);
        $insert_stmt->bind_param("id", $loan_id, $amount_paid);
        $insert_stmt->execute();
    } else {
        $message = "<div class='alert alert-warning text-center'>Please enter a valid amount.</div>";
    }
}

// Function to distribute repayment
function distributeRepayment($loan_id, $amount_paid, $conn) {
    $sql = "SELECT * FROM repayments WHERE loan_id = ? AND COALESCE(paid, 0) < amount ORDER BY repayment_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_distributed = 0;

    while ($row = $result->fetch_assoc()) {
        $installment_id = $row['id'];
        $remaining_due = $row['amount'] - $row['paid'];

        if ($amount_paid <= 0) {
            break;
        }

        if ($amount_paid >= $remaining_due) {
            $new_amount_paid = $row['amount']; // Full payment
            $amount_paid -= $remaining_due;
        } else {
            $new_amount_paid = $row['paid'] + $amount_paid;
            $amount_paid = 0;
        }

        // Update the installment with the new paid amount
        $update_sql = "UPDATE repayments SET paid = ?, repaid_date = CURDATE() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("di", $new_amount_paid, $installment_id);
        $update_stmt->execute();
        
        $total_distributed += $new_amount_paid;
    }

    // Fetch the updated outstanding loan balance
    $outstanding_sql = "SELECT SUM(amount - paid) AS outstanding_balance FROM repayments WHERE loan_id = ?";
    $outstanding_stmt = $conn->prepare($outstanding_sql);
    $outstanding_stmt->bind_param("i", $loan_id);
    $outstanding_stmt->execute();
    $outstanding_balance = $outstanding_stmt->get_result()->fetch_assoc()['outstanding_balance'] ?? 0;

    if ($total_distributed > 0) {
        return "<div class='alert alert-success text-center'>
                    Successfully🤝. total paid is:" . number_format($total_distributed, 2) . " KES.<br>
                    Outstanding Loan Balance: " . number_format($outstanding_balance, 2) . " KES.
                </div>";
    } else {
        return "<div class='alert alert-warning text-center'>No repayments were necessary for this loan.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Repayment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 700px;
            margin: 50px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        h2 {
            font-size: 26px;
            font-weight: bold;
            text-align: center;
            color: #343a40;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #555;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px;
            font-size: 16px;
        }

        .btn-primary, .btn-success {
            font-size: 16px;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 8px;
            transition: 0.3s ease-in-out;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-success:hover {
            background-color: #1e7e34;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .table thead {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }

        .table td, .table th {
            padding: 12px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .container {
                width: 90%;
                padding: 20px;
            }

            .table {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <a href="manager_dashboard.php" class="btn btn-primary mb-3">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </a>
        <h2>Make a Loan Payments here 👇</h2>
        <?= $message; ?>

        <form method="POST" class="mb-4" id="search_form">
            <label for="search_key" class="form-label">Enter Search Key:</label>
            <input type="text" name="search_key" id="search_key" class="form-control" required placeholder="Search by Name, Phone, or Unique ID">
            <button type="submit" name="search" id="search_btn" class="btn btn-primary mt-2">Search</button>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('search_key');
                const suggestionsBox = document.createElement('div');
                suggestionsBox.className = 'suggestions-box';
                suggestionsBox.style.position = 'absolute';
                suggestionsBox.style.zIndex = '1000';
                suggestionsBox.style.backgroundColor = '#fff';
                suggestionsBox.style.border = '1px solid #ddd';
                suggestionsBox.style.width = searchInput.offsetWidth + 'px';
                suggestionsBox.style.display = 'none';
                document.body.appendChild(suggestionsBox);

                searchInput.addEventListener('input', function () {
                    const query = this.value.trim();
                    if (query.length > 1) {
                        fetch(`search_borrowers.php?query=${encodeURIComponent(query)}`)
                            .then(response => response.json())
                            .then(data => {
                                suggestionsBox.innerHTML = '';
                                if (data.length > 0) {
                                    data.forEach(borrower => {
                                        const suggestion = document.createElement('div');
                                        suggestion.textContent = `${borrower.full_name} (${borrower.mobile})`;
                                        suggestion.style.padding = '10px';
                                        suggestion.style.cursor = 'pointer';
                                        suggestion.addEventListener('click', function () {
                                            searchInput.value = borrower.mobile;
                                            suggestionsBox.style.display = 'none';
                                        });
                                        suggestionsBox.appendChild(suggestion);
                                    });
                                    suggestionsBox.style.display = 'block';
                                    const rect = searchInput.getBoundingClientRect();
                                    suggestionsBox.style.top = rect.bottom + window.scrollY + 'px';
                                    suggestionsBox.style.left = rect.left + window.scrollX + 'px';
                                } else {
                                    suggestionsBox.style.display = 'none';
                                }
                            });
                    } else {
                        suggestionsBox.style.display = 'none';
                    }
                });

                document.addEventListener('click', function (e) {
                    if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                        suggestionsBox.style.display = 'none';
                    }
                });
            });
        </script>

        <?php if (isset($result) && $result->num_rows > 0): ?>
            <h3 class="text-center mb-4">Repayment Details</h3>
            <div class="table-container">
                <table class="table table-bordered table-hover shadow-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Borrower</th>
                            <th>Phone</th>
                            <th>Loan Product</th>
                            <th>Amount Due</th>
                            <th>Repay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['full_name']); ?></td>
                                <td><?= htmlspecialchars($row['mobile']); ?></td>
                                <td><?= htmlspecialchars($row['loan_product_name']); ?></td>
                                <td><strong><?= number_format($row['total_due'] - $row['total_paid'], 2); ?> KES</strong></td>
                                <td>
                                    <form method="POST" class="d-flex flex-column align-items-center">
                                        <input type="hidden" name="loan_id" value="<?= $row['loan_id']; ?>">
                                        <input type="number" name="amount_paid" class="form-control mb-2" required placeholder="Enter amount" style="width: 150px;">
                                        <button type="submit" name="repay" class="btn btn-success btn-sm">Repay</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
