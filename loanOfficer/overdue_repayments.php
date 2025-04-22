<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);

include 'db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure session variable is set
if (!isset($_SESSION['email'])) {
    die("Access denied. No session found.");
}

$email = $_SESSION['email'];

// Get the selected day from the request or default to all days
$selected_day = isset($_GET['day']) ? $_GET['day'] : 'all';
$day_filter = ($selected_day !== 'all') ? "AND DAYNAME(repayments.repayment_date) = ?" : "";

// Query to get overdue repayments filtered by loan officer and day
$sql_overdue = "SELECT 
                    borrowers.full_name AS borrower_name, 
                    borrowers.mobile AS phone_number, 
                    repayments.amount, 
                    repayments.paid, 
                    DATE_FORMAT(repayments.repayment_date, '%d/%m/%Y') AS repayment_date
                FROM 
                    repayments
                INNER JOIN 
                    loan_applications ON repayments.loan_id = loan_applications.id
                INNER JOIN 
                    borrowers ON loan_applications.borrower = borrowers.id
                WHERE 
                    repayments.repayment_date < CURDATE()  
                    AND (repayments.amount - repayments.paid) > 0 
                    AND borrowers.loan_officer = ?
                    $day_filter
                ORDER BY borrowers.full_name, repayments.repayment_date";

$stmt_overdue = $conn->prepare($sql_overdue);
if ($selected_day !== 'all') {
    $stmt_overdue->bind_param("ss", $email, $selected_day);
} else {
    $stmt_overdue->bind_param("s", $email);
}
$stmt_overdue->execute();
$result_overdue = $stmt_overdue->get_result();

// Calculate total overdue and count
$total_overdue = 0;
$total_overdue_count = 0;
while ($row = $result_overdue->fetch_assoc()) {
    $total_overdue += $row['amount'] - $row['paid'];
    $total_overdue_count++;
}
$result_overdue->data_seek(0); // Reset result pointer for display

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overdue Repayments</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
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
        .nav-tabs {
            border-bottom: 2px solid #007bff;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            color: #007bff;
            font-weight: 600;
            padding: 10px 15px;
            border: 1px solid transparent;
            border-radius: 5px;
            transition: 0.3s;
        }
        .nav-tabs .nav-link:hover {
            background-color: #e9f5ff;
            border-color: #007bff;
        }
        .nav-tabs .nav-link.active {
            background-color: #007bff;
            color: #ffffff;
            border-color: #007bff;
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
    <main class="main">
        <section class="section">
            <div class="container">
                <a href="index.php" class="btn btn-primary mb-3">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <h2 class="text-center">Arrears List</h2>
                <p class="text-center"><strong>Total Arrears:</strong> KSH <?= number_format($total_overdue, 2); ?></p>
                <p class="text-center"><strong>Total clients in arrears:</strong> <?= $total_overdue_count; ?></p>

                <!-- Search Input -->
                <div class="mb-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by Borrower Name or Phone Number" style="max-width: 400px;">
                </div>

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

                <!-- Download PDF Button -->
                <div class="text-center mt-3">
                    <button id="downloadPdf" class="btn btn-danger">Download PDF</button>
                </div>

                <table id="overdueTable" class="table table-striped mt-3">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Phone Number</th>
                            <th>Amount Due</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentBorrower = null;
                        $borrowerSubtotal = 0;

                        if ($result_overdue->num_rows > 0): 
                            while ($row = $result_overdue->fetch_assoc()): 
                                $arrears = $row['amount'] - $row['paid'];
                                if ($arrears > 0): 
                                    // Check if the borrower has changed
                                    if ($currentBorrower !== $row['borrower_name'] && $currentBorrower !== null): 
                        ?>
                                        <tr>
                                            <td colspan="3" style="font-weight: bold;">Subtotal for <?= htmlspecialchars($currentBorrower); ?>:</td>
                                            <td style="font-weight: bold; text-align: right;">KSH <?= number_format($borrowerSubtotal, 2); ?></td>
                                        </tr>
                        <?php 
                                        $borrowerSubtotal = 0; // Reset subtotal for the next borrower
                                    endif;

                                    $currentBorrower = $row['borrower_name'];
                                    $borrowerSubtotal += $arrears; // Add to the borrower's subtotal
                        ?>
                                    <tr class="overdue">
                                        <td><?= htmlspecialchars($row['borrower_name']); ?></td>
                                        <td><?= htmlspecialchars($row['phone_number']); ?></td>
                                        <td>KSH <?= number_format($arrears, 2); ?></td>
                                        <td><?= htmlspecialchars($row['repayment_date']); ?></td>
                                    </tr>
                        <?php 
                                endif;
                            endwhile;

                            // Display the final subtotal for the last borrower
                            if ($currentBorrower !== null): 
                        ?>
                                <tr>
                                    <td colspan="3" style="font-weight: bold;">Subtotal for <?= htmlspecialchars($currentBorrower); ?>:</td>
                                    <td style="font-weight: bold; text-align: right;">KSH <?= number_format($borrowerSubtotal, 2); ?></td>
                                </tr>
                        <?php 
                            endif;
                        else: 
                        ?>
                            <tr>
                                <td colspan="4" class="text-center">No overdue repayments found.</td>
                            </tr>
                        <?php endif; ?>
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

        // PDF Download functionality using jsPDF
        document.getElementById('downloadPdf').addEventListener('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Add title
            doc.setFontSize(18);
            doc.text('Overdue Repayments', 10, 10);

            // Add loan officer's name
            const loanOfficerName = "<?= htmlspecialchars($_SESSION['email']); ?>"; // Loan officer's email
            doc.setFontSize(12);
            doc.text(`Loan Officer: ${loanOfficerName}`, 10, 20);

            // Add table
            const table = document.getElementById('overdueTable');
            const rows = table.querySelectorAll('tr');
            let y = 30;

            rows.forEach((row, index) => {
                const cells = row.querySelectorAll('td, th');
                let x = 10;

                cells.forEach(cell => {
                    doc.text(cell.innerText, x, y);
                    x += 50; // Adjust column width
                });

                y += 10; // Adjust row height
                if (y > 280) { // Create a new page if content exceeds page height
                    doc.addPage();
                    y = 10;
                }
            });

            // Save the PDF
            doc.save(`Overdue_Repayments_${loanOfficerName}.pdf`);
        });
    </script>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
