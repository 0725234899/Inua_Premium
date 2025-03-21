<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/functions.php';
include("includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Applications</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }

        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header .logo h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }

        .sidebar {
            background-color: #ffffff;
            color: #3a3939;
            padding: 20px;
            width: 250px;
            position: fixed;
            height: 100%;
            overflow: auto;
        }

        .main {
            margin-left: 270px;
            padding: 20px;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <?php
         
        include '../includes/sidebar.php'; ?>
    </div>
    <?php
    include 'db.php';
    
    function getLoans() {
        global $conn;
        $loans = array();

        $sql = "SELECT 
                    l.id,
                    l.loan_release_date, 
                    l.principal,
                    b.full_name AS borrower_name, 
                    p.name AS loan_product_name, 
                    SUM(r.paid) AS total_paid_amount, 
                    SUM(r.amount) AS total_amount, 
                    (SUM(r.amount) - SUM(r.paid)) AS loan_balance 
                FROM loan_applications l 
                INNER JOIN borrowers b ON l.borrower = b.id 
                INNER JOIN loan_products p ON l.loan_product = p.id 
                LEFT JOIN repayments r ON l.id = r.loan_id 
                WHERE l.loan_status='approved' 
                GROUP BY l.id, b.full_name, p.name";

        $result = $conn->query($sql);

        if ($result === FALSE) {
            echo "Error: " . $conn->error;
            return $loans;
        }

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $loans[] = $row;
            }
        } else {
            echo "No records found.";
        }

        return $loans;
    }
    
    $loans = getLoans();
    ?>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Performing Book</h1>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Borrower</th>
                            <th>Loan Release Date</th>
                            <th>Principal</th>
                            <th>Loan Product</th>
                            <th>Total Amount</th>
                            <th>Total Paid Amount</th>
                            <th>Loan Balance</th>
                            
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($loans) > 0) {
                            foreach ($loans as $loan) {
                                $balance=$loan['total_amount']-$loan['total_paid_amount'];
                                $loanId=$loan['id'];
                                echo "<tr>
                                    <td><a href='repayment_details.php?loanId=$loanId'>{$loan['id']}</a></td>
                                    <td><a href='repayment_details.php?loanId=$loanId'>{$loan['borrower_name']}</a></td>
                                    <td>{$loan['loan_release_date']}</td>
                                    <td>{$loan['principal']}</td>
                                    <td>{$loan['loan_product_name']}</td>
                                    <td>{$loan['total_amount']}</td>
                                    <td><a href='repayment_details.php?loanId=$loanId'>{$loan['total_paid_amount']}</a></td>
                                    <td>{$balance}</td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No loans found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>