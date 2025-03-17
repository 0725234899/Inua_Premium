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

        .container {
            margin-top: 20px;
        }

        table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: #fff;
        }

        table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
        }

        table tbody tr {
            border-bottom: 1px solid #dee2e6;
        }

        table tbody td {
            vertical-align: middle;
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
    
    function getLoans() {
        global $conn;
        $loans = array();

        $sql = "SELECT 
                    l.id, 
                    b.full_name AS borrower_name, 
                    p.name AS loan_product_name, 
                    l.principal, 
                    l.interest, 
                    l.interest_method, 
                    l.loan_interest, 
                    l.loan_duration, 
                    l.repayment_cycle, 
                    l.number_of_repayments, 
                    l.processing_fee, 
                    l.registration_fee, 
                    l.total_amount, 
                    l.loan_status,
                    l.loan_release_date 
                FROM loan_applications l 
                INNER JOIN borrowers b ON l.borrower = b.id 
                INNER JOIN loan_products p ON l.loan_product = p.id where l.loan_status='approved'";

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
                <h1>Loan Applications</h1>
                <table class="table table-bordered">
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
                            <th>Processing Fee %</th>
                            <th>Registration Fee %</th>
                            <th>Total Amount</th>
                            <th>Loan Release Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($loans) > 0) {
                            foreach ($loans as $loan) {
                                echo "<tr>
                                    <td>{$loan['id']}</td>
                                    <td>{$loan['borrower_name']}</td>
                                    <td>{$loan['loan_product_name']}</td>
                                    <td>{$loan['principal']}</td>
                                    <td>{$loan['interest']}</td>
                                    <td>{$loan['interest_method']}</td>
                                    <td>{$loan['loan_interest']}</td>
                                    <td>{$loan['loan_duration']}</td>
                                    <td>{$loan['repayment_cycle']}</td>
                                    <td>{$loan['number_of_repayments']}</td>
                                    <td>{$loan['processing_fee']}</td>
                                    <td>{$loan['registration_fee']}</td>
                                    <td>{$loan['total_amount']}</td>
                                    <td>{$loan['loan_release_date']}</td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='14'>No loans found</td></tr>";
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
