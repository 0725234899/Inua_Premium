<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/functions.php';
include 'db.php'; // Ensure this file sets up $conn as a valid MySQLi connection

// Error handling for query execution
$sql = "
    SELECT 
        b.full_name, 
        b.business_name, 
        b.mobile, 
        b.loan_officer,
        COALESCE(SUM(l.principal), 0) AS total_loan_taken,
        COALESCE(SUM(l.total_amount - r.total_paid), 0) AS open_loans_balance,
        COALESCE(SUM(CASE WHEN r.total_paid < l.total_amount THEN l.total_amount - r.total_paid ELSE 0 END), 0) AS arrears_amount
    FROM 
        borrowers b
    LEFT JOIN 
        loan_applications l ON b.id = l.borrower
    LEFT JOIN 
        (
            SELECT 
                loan_id, 
                SUM(paid) AS total_paid 
            FROM 
                repayments 
            GROUP BY 
                loan_id
        ) r ON l.id = r.loan_id
    WHERE 
        b.loan_officer = '".$_SESSION['email']."'
    GROUP BY 
        b.full_name, b.business_name, b.mobile
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: var(--surface-color);
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid var(--default-color);
        }

        table th {
            background-color: var(--accent-color);
            color: var(--contrast-color);
            font-weight: bold;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tr:hover {
            background-color: #f1f1f1;
        }

        table td {
            color: var(--default-color);
        }

        .search-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
        }

        .search-container input {
            padding: 8px;
            border: 1px solid var(--default-color);
            border-radius: 4px;
            width: 250px;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .print-button {
            margin-top: 10px;
            background-color: var(--accent-color);
            color: var(--contrast-color);
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .print-button:hover {
            background-color: #d43d3d;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .printable-area, .printable-area * {
                visibility: visible;
            }

            .printable-area {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                padding: 20px;
            }

            .print-button, .search-container {
                display: none;
            }
        }

        .print-logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .print-logo img {
            max-width: 150px;
        }

        .print-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .print-header h1 {
            font-size: 24px;
            color: var(--accent-color);
        }

        .print-content {
            margin-top: 20px;
        }

        .print-content div {
            margin-bottom: 10px;
            font-size: 16px;
            color: var(--default-color);
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>

<body class="admin-page">

    <!-- End Header -->
    <?php
    include("../includes/sidebar.php");
    include("includes/header.php");
    ?>
    <!-- ======= Main ======= -->
    <main class="main">
        <section id="admin-dashboard" class="admin-dashboard section">
            <div class="container mt-3">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search borrowers...">
                </div>
                <div class="table-responsive printable-area" id="printableArea">
                    <div class="print-logo">
                        <img src="/assets/img/logo.png" alt="Logo">
                    </div>
                    <div class="print-header">
                        <h1>Borrowers Report</h1>
                    </div>
                    <table class="table" id="borrowersTable">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Business Name</th>
                                <th>Mobile</th>
                                <th>Total Loan Taken</th>
                                <th>Open Loans Balance</th>
                                <th>Arrears Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    if ($row['loan_officer'] == $_SESSION['email']) {
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row["full_name"]) . "</td>
                                                <td>" . htmlspecialchars($row["business_name"]) . "</td>
                                                <td>" . htmlspecialchars($row["mobile"]) . "</td>
                                                <td>KSH " . number_format($row["total_loan_taken"], 2) . "</td>
                                                <td>KSH " . number_format($row["open_loans_balance"], 2) . "</td>
                                                <td>KSH " . number_format($row["arrears_amount"], 2) . "</td>
                                              </tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align: center;'>No borrowers found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
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
    <script>
        function printTable() {
            const printContents = document.getElementById('printableArea').innerHTML;
            const originalContents = document.body.innerHTML;

            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
        }

        document.getElementById('searchInput').addEventListener('input', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#borrowersTable tbody tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const match = Array.from(cells).some(cell =>
                    cell.textContent.toLowerCase().includes(filter)
                );
                row.style.display = match ? '' : 'none';
            });
        });
    </script>
</body>
</html>

<?php
$conn->close(); // Close the MySQLi connection
?>
