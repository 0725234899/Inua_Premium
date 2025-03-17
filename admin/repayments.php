<?php 
include '../includes/functions.php';
include 'includes/header.php'; 
include 'db.php';
?>
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
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }

        .container {
            max-width: 500px;
            margin: 50px auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        h1 {
            font-size: 24px;
            font-weight: bold;
            color: #343a40;
        }

        .form-label {
            font-weight: 500;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            font-size: 16px;
            font-weight: bold;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        #responseMessage p {
            font-size: 16px;
            text-align: center;
        }

        .form-control {
            border-radius: 5px;
        }

        .icon {
            font-size: 40px;
            color: #007bff;
            display: block;
            margin: 0 auto 20px;
        }

        footer {
            padding-top: 20px;
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }

        footer a {
            color: #007bff;
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }
        :root {
            --background-color: #ffffff;
            --default-color: #212529;
            --heading-color: #32353a;
            --accent-color: #e84545;
            --surface-color: #ffffff;
            --contrast-color: #ffffff;
            --nav-color: #3a3939;
            --nav-hover-color: #e84545;
            --nav-mobile-background-color: #ffffff;
            --nav-dropdown-background-color: #ffffff;
            --nav-dropdown-color: #3a3939;
            --nav-dropdown-hover-color: #e84545;
        }

        body {
            background-color: var(--background-color);
            color: var(--default-color);
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }

        .header {
            background-color: var(--accent-color);
            color: var(--contrast-color);
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header .logo h1 {
            color: var(--contrast-color);
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
            color: var(--contrast-color);
            text-decoration: none;
        }

        .header .navmenu ul li a.active, .header .navmenu ul li a:hover {
            color: var(--nav-hover-color);
        }

        .sidebar {
            background-color: var(--nav-mobile-background-color);
            color: var(--nav-color);
            padding: 20px;
            width: 250px;
            position: fixed;
            height: 100%;
            overflow: auto;
        }

        .sidebar .nav-item .nav-link {
            color: var(--nav-color);
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }

        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: var(--nav-hover-color);
        }

        .main {
            margin-left: 270px;
            padding: 20px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .grid-container ul {
            list-style: none;
            padding: 0;
            margin: 0;
            border: 1px solid var(--default-color);
            border-radius: 8px;
            padding: 20px;
        }

        .grid-container ul li {
            margin: 10px 0;
        }

        .grid-container ul li a {
            color: blue;
            text-decoration: none;
        }

        .grid-container ul li a:hover {
            color: var(--nav-hover-color);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    
    <?php
    // Secure SQL queries with prepared statements
    $sql_due = "SELECT borrowers.full_name, loan_applications.loan_product, SUM(repayments.amount) AS total_amount_due, repayments.repayment_date 
                FROM repayments 
                INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                WHERE repayments.repayment_date >= CURDATE() 
                GROUP BY repayments.loan_id, borrowers.full_name, loan_applications.loan_product, repayments.repayment_date";
    
    $stmt_due = $conn->prepare($sql_due);
    $stmt_due->execute();
    $result_due = $stmt_due->get_result();
    
    $sql_overdue = "SELECT borrowers.full_name, loan_applications.loan_product, repayments.amount, repayments.repayment_date,repayments.paid 
                    FROM repayments 
                    INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                    INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                    WHERE repayments.repayment_date < CURDATE()";
    
    $stmt_overdue = $conn->prepare($sql_overdue);
    $stmt_overdue->execute();
    $result_overdue = $stmt_overdue->get_result();
    ?>

    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Repayments</h1>
                
                <div class="table-container">
                    <h2>Due Repayments</h2>
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
                            <?php while ($row = $result_due->fetch_assoc()) : ?>
                                <?php $diff=$row['amount']-$row['paid'];
                                if($diff>0)
                                {
                                ?>
                                <tr>
                                
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['loan_product']); ?></td>
                                    <td><?php echo number_format($row['total_amount_due'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['repayment_date']); ?></td>
                                </tr>
                                <?php } ?>
                            <?php endwhile; ?>
                            <?php if ($result_due->num_rows === 0) echo "<tr><td colspan='4'>No due repayments found</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-container">
                    <h2>Overdue Repayments</h2>
                    <table class="table table-striped table-danger">
                        <thead>
                            <tr>
                                <th>Borrower</th>
                                <th>Loan Product</th>
                                <th>Amount Due</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_overdue->fetch_assoc()) : ?>
                                <?php
                                $diff=$row['amount']-$row['paid'];
                                if($diff>0)
                                {
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['loan_product']); ?></td>
                                    <td><?php echo $diff." KES"?></td>
                                    <td><?php echo htmlspecialchars($row['repayment_date']); ?></td>
                                </tr>
                                <?php } ?>
                            <?php endwhile; ?>
                            <?php if ($result_overdue->num_rows === 0) echo "<tr><td colspan='4'>No overdue repayments found</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>