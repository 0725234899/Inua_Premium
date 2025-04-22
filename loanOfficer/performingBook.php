<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performing Book</title>
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

    // Get the selected day from the request or default to all days
    $selected_day = isset($_GET['day']) ? $_GET['day'] : 'all';
    $day_filter = ($selected_day !== 'all') ? "AND DAYNAME(l.loan_release_date) = ?" : "";

    // Query to get performing book data filtered by day
    $email = $_SESSION['email'];
    $sql_performing = "SELECT 
                        l.id AS loan_id,
                        b.full_name AS borrower_name,
                        DATE_FORMAT(l.loan_release_date, '%d/%m/%Y') AS loan_release_date,
                        l.principal,
                        p.name AS loan_product_name,
                        SUM(r.amount) AS total_amount,
                        SUM(r.paid) AS total_paid,
                        (SUM(r.amount) - SUM(r.paid)) AS loan_balance
                    FROM 
                        loan_applications l
                    INNER JOIN 
                        borrowers b ON l.borrower = b.id
                    INNER JOIN 
                        loan_products p ON l.loan_product = p.id
                    LEFT JOIN 
                        repayments r ON l.id = r.loan_id
                    WHERE 
                        l.loan_status = 'approved' 
                        AND b.loan_officer = '$email'
                        $day_filter
                    GROUP BY 
                        l.id, b.full_name, p.name";

    $stmt_performing = $conn->prepare($sql_performing);
    if ($selected_day !== 'all') {
        $stmt_performing->bind_param("s", $selected_day);
    }
    $stmt_performing->execute();
    $result_performing = $stmt_performing->get_result();
    ?>
    <main class="main">
        <section class="section">
            <div class="table-container">
                <h2 class="text-center">Performing Book</h2>

                <!-- Day Tabs -->
                <ul class="nav nav-tabs justify-content-center mt-3">
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'all') ? 'active' : '' ?>" href="?day=all">All Days</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Monday') ? 'active' : '' ?>" href="?day=Monday">Monday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Tuesday') ? 'active' : '' ?>" href="?day=Tuesday">Tuesday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Wednesday') ? 'active' : '' ?>" href="?day=Wednesday">Wednesday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Thursday') ? 'active' : '' ?>" href="?day=Thursday">Thursday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Friday') ? 'active' : '' ?>" href="?day=Friday">Friday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Saturday') ? 'active' : '' ?>" href="?day=Saturday">Saturday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Sunday') ? 'active' : '' ?>" href="?day=Sunday">Sunday</a>
                    </li>
                </ul>

                <div class="d-flex justify-content-between mb-3">
                    <button id="downloadButton" class="btn btn-primary">Download PDF</button>
                    <input type="text" id="searchInput" placeholder="Search by borrower or product..." class="form-control" style="width: 300px;">
                </div>
                <table class="table table-striped" id="performingTable">
                    <thead>
                        <tr>
                            <th>Loan ID</th>
                            <th>Borrower</th>
                            <th>Loan Product</th>
                            <th>Loan Release Date</th>
                            <th>Principal</th>
                            <th>Total Amount</th>
                            <th>Total Paid</th>
                            <th>Loan Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result_performing->num_rows > 0) {
                            while ($row = $result_performing->fetch_assoc()) {
                                echo "<tr>
                                        <td><a href='repayment_details.php?loanId={$row['loan_id']}'>{$row['loan_id']}</a></td>
                                        <td><a href='repayment_details.php?loanId={$row['loan_id']}'>{$row['borrower_name']}</a></td>
                                        <td>{$row['loan_product_name']}</td>
                                        <td>{$row['loan_release_date']}</td>
                                        <td>KSH " . number_format(ceil($row['principal'])) . "</td>
                                        <td>KSH " . number_format(ceil($row['total_amount'])) . "</td>
                                        <td>KSH " . number_format(ceil($row['total_paid'])) . "</td>
                                        <td>KSH " . number_format(ceil($row['loan_balance'])) . "</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8'>No performing loans found</td></tr>";
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
                    (index === 1 || index === 2) && cell.textContent.toLowerCase().includes(filter)
                );
                row.style.display = match ? '' : 'none';
            });
        });

        document.getElementById('downloadButton').addEventListener('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Add title
            doc.setFontSize(18);
            doc.text('Performing Book', 10, 10);

            // Extract table data
            const table = document.getElementById('performingTable');
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
            doc.save('performing_book.pdf');
        });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>