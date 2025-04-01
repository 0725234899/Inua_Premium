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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
</head>
<body>

    <div class="sidebar">
        <?php
         
        include '../includes/sidebar.php'; ?>
    </div>
    <?php
    include 'db.php';
    
    // Fetch all loan officers
    $sql_officers = "SELECT id, name AS full_name FROM users WHERE role_id = '2'";
    $stmt_officers = $conn->prepare($sql_officers);
    $stmt_officers->execute();
    $result_officers = $stmt_officers->get_result();

    // Get selected loan officer (if any)
    $selected_officer = isset($_GET['officer_id']) ? $_GET['officer_id'] : 'all';
    $officer_filter = ($selected_officer !== 'all') ? "AND b.loan_officer = (SELECT email FROM users WHERE id = ?)" : "";

    // Get the selected day from the request or default to all days
    $selected_day = isset($_GET['day']) ? $_GET['day'] : 'all';
    $day_filter = ($selected_day !== 'all') ? "AND DAYNAME(l.loan_release_date) = ?" : "";

    // Modify the query to filter loans by loan officer and day
    function getLoans($selected_officer, $selected_day) {
        global $conn, $officer_filter, $day_filter;

        $loans = array();
        $sql = "SELECT 
                    l.id,
                    DATE_FORMAT(l.loan_release_date, '%d/%m/%Y') AS loan_release_date, 
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
                $officer_filter
                $day_filter
                GROUP BY l.id, b.full_name, p.name";

        $stmt = $conn->prepare($sql);
        if ($selected_officer !== 'all' && $selected_day !== 'all') {
            $stmt->bind_param("is", $selected_officer, $selected_day);
        } elseif ($selected_officer !== 'all') {
            $stmt->bind_param("i", $selected_officer);
        } elseif ($selected_day !== 'all') {
            $stmt->bind_param("s", $selected_day);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === FALSE) {
            echo "Error: " . $conn->error;
            return $loans;
        }

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $loans[] = $row;
            }
        }

        return $loans;
    }

    $loans = getLoans($selected_officer, $selected_day);
    ?>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Performing Book</h1>

                <!-- Loan Officer Tabs -->
                <ul class="nav nav-tabs justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_officer === 'all') ? 'active' : '' ?>" href="?officer_id=all&day=<?= htmlspecialchars($selected_day); ?>">All Loan Officers</a>
                    </li>
                    <?php while ($officer = $result_officers->fetch_assoc()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($selected_officer == $officer['id']) ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($officer['id']); ?>&day=<?= htmlspecialchars($selected_day); ?>">
                                <?= htmlspecialchars($officer['full_name']); ?>
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>

                <!-- Day Tabs -->
                <ul class="nav nav-tabs justify-content-center mt-3">
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'all') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=all">All Days</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Monday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Monday">Monday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Tuesday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Tuesday">Tuesday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Wednesday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Wednesday">Wednesday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Thursday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Thursday">Thursday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Friday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Friday">Friday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Saturday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Saturday">Saturday</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($selected_day === 'Sunday') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Sunday">Sunday</a>
                    </li>
                </ul>

                <!-- Search Input and Download PDF Button -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by Borrower Name or Loan Product" style="max-width: 400px;">
                    <button id="downloadPdf" class="btn btn-danger">Download PDF</button>
                </div>

                <table id="performingBookTable" class="table table-bordered">
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
                            echo "<tr><td colspan='8'>No loans found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#performingBookTable tbody tr');

            rows.forEach(row => {
                const borrowerName = row.cells[1]?.textContent.toLowerCase() || '';
                const loanProduct = row.cells[4]?.textContent.toLowerCase() || '';
                row.style.display = (borrowerName.includes(filter) || loanProduct.includes(filter)) ? '' : 'none';
            });
        });

        // PDF Download functionality
        document.getElementById('downloadPdf').addEventListener('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Add title
            doc.setFontSize(18);
            doc.text('Performing Book', 10, 10);

            // Add table
            const table = document.getElementById('performingBookTable');
            const rows = table.querySelectorAll('tr');
            let y = 20;

            rows.forEach((row, index) => {
                const cells = row.querySelectorAll('td, th');
                let x = 10;

                cells.forEach(cell => {
                    doc.text(cell.innerText, x, y);
                    x += 40; // Adjust column width
                });

                y += 10; // Adjust row height
                if (y > 280) { // Create a new page if content exceeds page height
                    doc.addPage();
                    y = 10;
                }
            });

            // Save the PDF
            doc.save('Performing_Book.pdf');
        });
    </script>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>