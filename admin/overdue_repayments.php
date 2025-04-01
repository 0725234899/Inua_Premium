<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); error_reporting(E_ALL);
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

    // Query to get overdue repayments
    $sql_overdue = "SELECT 
                        borrowers.full_name AS borrower_name,  
                        loan_products.name AS loan_product,
                        repayments.amount, 
                        repayments.repayment_date, 
                        repayments.paid,
                        borrowers.id AS borrower_id
                    FROM 
                        repayments
                    INNER JOIN 
                        loan_applications ON repayments.loan_id = loan_applications.id
                    INNER JOIN 
                        loan_products ON loan_applications.loan_product = loan_products.id
                    INNER JOIN 
                        borrowers ON loan_applications.borrower = borrowers.id
                    WHERE 
                        repayments.repayment_date < CURDATE()";

    $result_overdue = $conn->query($sql_overdue);
    ?>
    <main class="main">
        <section class="section">
            <div class="table-container">
                <h2 class="text-center">Overdue Repayments</h2>
                <div class="d-flex justify-content-between mb-3">
                    <button id="downloadButton" class="btn btn-primary">Download PDF</button>
                    <input type="text" id="searchInput" placeholder="Search by borrower or product..." class="form-control" style="width: 300px;">
                </div>
                <table class="table table-striped" id="overdueTable">
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
                            while ($row = $result_overdue->fetch_assoc()) {
                                $diff = $row['amount'] - $row['paid'];
                                if ($diff > 1) {
                                    echo "<tr class='overdue'>
                                            <td><a href='client_details.php?borrower_id={$row['borrower_id']}'>{$row['borrower_name']}</a></td>
                                            <td>{$row['loan_product']}</td>
                                            <td>KSH " . number_format($diff, 2) . "</td>
                                            <td>{$row['repayment_date']}</td>
                                          </tr>";
                                }
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

    <script>
        document.getElementById('searchInput').addEventListener('input', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const match = Array.from(cells).some((cell, index) => 
                    (index === 0 || index === 1) && cell.textContent.toLowerCase().includes(filter)
                );
                row.style.display = match ? '' : 'none';
            });
        });

        document.getElementById('downloadButton').addEventListener('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Add title
            doc.setFontSize(18);
            doc.text('Overdue Repayments', 10, 10);

            // Extract table data
            const table = document.getElementById('overdueTable');
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            const rows = Array.from(table.querySelectorAll('tbody tr')).map(row => 
                Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim())
            );

            // Add table to PDF
            doc.autoTable({
                head: [headers],
                body: rows,
                startY: 20,
            });

            // Save the PDF
            doc.save('overdue_repayments.pdf');
        });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
