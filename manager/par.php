<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repayments</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
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
        .header .navmenu ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
        }
        .header .navmenu ul li {
            margin-right: 20px;
        }
        .header .navmenu ul li a {
            color: #ffffff;
            text-decoration: none;
        }
        .header .navmenu ul li a.active, .header .navmenu ul li a:hover {
            color: #e84545;
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
        .sidebar .nav-item .nav-link {
            color: #3a3939;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }
        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: #e84545;
        }
        .main {
            margin-left: 270px;
            padding: 20px;
        }
        .table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        .container h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        .table thead th {
            background-color: #f5f5f5;
        }
        .overdue {
            background-color: #f8d7da;
        }
    </style>
</head>
<body>
    <?php 
    include '../includes/functions.php';
    include 'includes/header.php'; 
    ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <?php
    include 'db.php';

    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

    // Query to get overdue repayments
    $sql_overdue = "SELECT 
                        borrowers.full_name AS borrower_name, 
                        loan_applications.loan_product, 
                        repayments.amount, 
                        repayments.repayment_date
                    FROM 
                        repayments
                    INNER JOIN 
                        loan_applications ON repayments.loan_id = loan_applications.id
                    INNER JOIN 
                        borrowers ON loan_applications.borrower= borrowers.id
                    WHERE 
                        repayments.repayment_date < CURDATE() AND DATEDIFF(CURDATE(), repayments.repayment_date) > $days";

    $result_overdue = $conn->query($sql_overdue);

    // Query to calculate total loan amount and overdue amount for PAR
    $sql_total_loan = "SELECT SUM(total_amount) AS total_loan_amount FROM loan_applications";
    $result_total_loan = $conn->query($sql_total_loan);
    $row_total_loan = $result_total_loan->fetch_assoc();
    $total_loan_amount = $row_total_loan['total_loan_amount'];

    $sql_overdue_amount = "SELECT SUM(repayments.amount) AS total_overdue_amount 
                           FROM repayments 
                           WHERE repayment_date < CURDATE() AND DATEDIFF(CURDATE(), repayment_date) > $days";
    $result_overdue_amount = $conn->query($sql_overdue_amount);
    $row_overdue_amount = $result_overdue_amount->fetch_assoc();
    $total_overdue_amount = $row_overdue_amount['total_overdue_amount'];

    $par = ($total_overdue_amount / $total_loan_amount) * 100;
    ?>
    <main class="main">
        <section class="section">
            <form method="GET" action="">
                <label for="days">Select overdue days:</label>
                <select name="days" id="days" onchange="this.form.submit()" class="form-control" hidden>
                    <option value="30" <?php if($days == 30) echo 'selected'; ?>>30 days</option>
                    <option value="60" <?php if($days == 60) echo 'selected'; ?>>60 days</option>
                    <option value="90" <?php if($days == 90) echo 'selected'; ?>>90 days</option>
                </select>
                <input type="number" name="days" placeholder="Custom days" min="1" oninput="this.form.submit()" class="form-control" value="<?php echo $days; ?>">
            </form>
            <div class="table-container">
                <h2>Portfolio at Risk (PAR) > <?php echo $days; ?> days</h2>
                <p><strong>Total Loan Amount:</strong> <?php echo number_format($total_loan_amount, 2); ?></p>
                <p><strong>Total Overdue Amount:</strong> <?php echo number_format($total_overdue_amount, 2); ?></p>
                <p><strong>Portfolio at Risk (PAR) > <?php echo $days; ?> days:</strong> <?php echo number_format($par, 2); ?>%</p>
            </div>
            <div class="table-container">
                <h2>Overdue Repayments</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Loan Product</th>
                            <th>Amount Due</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result_overdue->num_rows > 0) {
                            while($row = $result_overdue->fetch_assoc()) {
                                echo "<tr class='overdue'>
                                        <td>{$row['borrower_name']}</td>
                                        <td>{$row['loan_product']}</td>
                                        <td>{$row['amount']}</td>
                                        <td>{$row['repayment_date']}</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4'>No overdue repayments found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
