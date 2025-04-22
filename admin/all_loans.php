<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Loans</title>
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
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table thead th {
            background-color: #007bff;
            color: #ffffff;
            text-align: center;
            padding: 10px;
            border-bottom: 2px solid #dee2e6;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .table tbody td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <?php 
    include '../includes/functions.php';
    include 'includes/header.php'; 
    include 'db.php';

    // Fetch all loans
    $sql_loans = "SELECT 
                    l.id AS loan_id, 
                    b.full_name AS borrower_name, 
                    p.name AS loan_product_name, 
                    l.principal, 
                    l.loan_duration, 
                    l.loan_status, 
                    DATE_FORMAT(loan_release_date, '%d/%m/%Y') AS formatted_date 
                  FROM 
                    loan_applications l
                  INNER JOIN 
                    borrowers b ON l.borrower = b.id
                  INNER JOIN 
                    loan_products p ON l.loan_product = p.id
                  ORDER BY l.loan_status, b.full_name";

    $result_loans = $conn->query($sql_loans);
    ?>
    <div class="container">
        <h2 class="text-center mb-4">All Loans</h2>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Loan Product</th>
                        <th>Principal Amount (KSH)</th>
                        <th>Duration (Months)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_loans->num_rows > 0): ?>
                        <?php while ($row = $result_loans->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['loan_product_name']); ?></td>
                                <td><?php echo number_format(ceil($row['principal'])); ?></td>
                                <td><?php echo htmlspecialchars($row['loan_duration']); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($row['loan_status'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No loan records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>
