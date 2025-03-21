<?php
include '../includes/functions.php';
include 'db.php'; // Ensure this file sets up $conn as a valid MySQLi connection

// Error handling for query execution
$sql = "
    SELECT 
        b.full_name, 
        b.business_name, 
        b.unique_number, 
        b.mobile, 
        b.email, 
        b.status, 
        COALESCE(SUM(l.principal + l.interest), 0) AS total_loan_taken,
        COALESCE(SUM(l.principal + l.interest - r.amount), 0) AS open_loans_balance
    FROM 
        borrowers b
    LEFT JOIN 
        loan_applications l ON b.id = l.id
    LEFT JOIN 
        repayments r ON l.id = r.loan_id
    GROUP BY 
        b.full_name, b.business_name, b.unique_number, b.mobile, b.email, b.status
";

$result = $conn->query($sql);

if (!$result) {
    // Output error if the query fails
    die("Error executing query: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Admin Dashboard - Microfinance</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <style>
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

        .dashboard-metrics {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }

        .metric {
            background-color: var(--surface-color);
            border: 1px solid var(--default-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            flex: 1;
            margin: 0 10px;
        }

        .metric h2 {
            margin: 0;
            font-size: 2em;
        }

        .metric p {
            margin: 5px 0 0;
        }
    </style>

    <!-- Favicons -->
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="admin-page">

    <!-- End Header -->
    <?php
    include("../includes/sidebar.php");
    ?>
    <!-- ======= Main ======= -->
    <main class="main">
        <section id="admin-dashboard" class="admin-dashboard section">
            <div class="container">
                <h2>View Borrowers</h2>
                <table border="1" class="table">
                    <tr>
                        <th>Full Name</th>
                        <th>Business Name</th>
                        <th>Unique Number</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Total Loan Taken</th>
                        <th>Open Loans Balance</th>
                        <th>Status</th>
                    </tr>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>".$row["full_name"]."</td>
                                    <td>".$row["business_name"]."</td>
                                    <td>".$row["unique_number"]."</td>
                                    <td>".$row["mobile"]."</td>
                                    <td>".$row["email"]."</td>
                                    <td>".number_format($row["total_loan_taken"], 2)."</td>
                                    <td>".number_format($row["open_loans_balance"], 2)."</td>
                                    <td>".$row["status"]."</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>No borrowers found</td></tr>";
                    }
                    ?>
                </table>
            </div>
        </section><!-- End Admin Dashboard Section -->
    </main><!-- End Main -->

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
</body>
</html>

<?php
$conn->close(); // Close the MySQLi connection
?>
