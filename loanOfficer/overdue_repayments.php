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

// Query to get overdue repayments filtered by loan officer and day, sorted by the highest overdue amount
$sql_overdue = "SELECT 
                    borrowers.full_name AS borrower_name, 
                    borrowers.mobile AS phone_number, 
                    SUM(repayments.amount - repayments.paid) AS total_overdue
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
                GROUP BY 
                    borrowers.full_name, borrowers.mobile
                ORDER BY 
                    total_overdue DESC, borrowers.full_name";

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
    $total_overdue += $row['total_overdue'];
    $total_overdue_count++;
}
$result_overdue->data_seek(0); // Reset result pointer for display
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arrears list</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
            color: #212529;
        }
        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 15px 0;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
        }
        .table {
            margin-top: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #e84545;
            color: #ffffff;
            text-align: center;
        }
        .table td {
            text-align: center;
        }
        .btn-primary {
            background-color: #e84545;
            border: none;
            transition: all 0.3s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #d43d3d;
        }
        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #e84545;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <div class="header">
        <h1>Arrears list</h1>
    </div>
    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-3">
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            <button id="downloadOverdueRepayments" class="btn btn-danger">Download Arrears List</button>
        </div>
        <p class="text-center"><strong>Total Overdue:</strong> KSH <?= number_format($total_overdue, 2); ?></p>
        
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

        <!-- Search Input -->
        <div class="d-flex justify-content-end mt-3">
            <input type="text" id="searchInput" class="form-control" placeholder="Search..." style="width: 300px;">
        </div>

        <h3 class="section-title mt-4">Arrears List</h3>
        <table id="overdueRepaymentsTable" class="table table-bordered">
            <thead>
                <tr>
                    <th>Borrower</th>
                    <th>Phone Number</th>
                    <th class="text-end">Total Overdue Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_overdue->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['borrower_name']); ?></td>
                        <td><?= htmlspecialchars($row['phone_number']); ?></td>
                        <td class="text-end">KSH <?= number_format($row['total_overdue'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result_overdue->num_rows === 0): ?>
                    <tr><td colspan="3">No arrears found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <footer class="text-center mt-5">
        <p><em>Powered by AntonTech</em></p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function downloadTableAsPDF(tableId, title) {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Add logo and loan officer's name
                const logoPath = "/Inua_Premium_services/assets/img/logo.png";
                const img = new Image();
                img.src = logoPath;

                img.onload = function () {
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const logoWidth = 40;
                    const logoHeight = 25;
                    const logoX = (pageWidth - logoWidth) / 2; // Center the logo
                    const logoY = 10;

                    doc.addImage(img, 'PNG', logoX, logoY, logoWidth, logoHeight);

                    // Add loan officer's name below the logo
                    const loanOfficerEmail = "<?= htmlspecialchars($email); ?>";
                    doc.setFontSize(12);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`Loan Officer: ${loanOfficerEmail}`, pageWidth / 2, logoY + logoHeight + 5, { align: 'center' });

                    // Add title
                    doc.setFontSize(18);
                    doc.setFont('helvetica', 'bold');
                    doc.text(title, pageWidth / 2, logoY + logoHeight + 15, { align: 'center' });

                    // Add underline
                    doc.setDrawColor(0); // Black color
                    doc.setLineWidth(0.5);
                    doc.line(10, logoY + logoHeight + 17, pageWidth - 10, logoY + logoHeight + 17);

                    // Add total overdue summary
                    const summaryY = logoY + logoHeight + 25;
                    doc.setFontSize(12);
                    doc.text(`Total Overdue: KSH <?= number_format($total_overdue, 2); ?>`, 10, summaryY);
                    doc.text(`Total Clients in Arrears: <?= $total_overdue_count; ?>`, 10, summaryY + 7);

                    // Extract table data
                    const table = document.getElementById(tableId);
                    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
                    const rows = Array.from(table.querySelectorAll('tbody tr')).map(row =>
                        Array.from(row.querySelectorAll('td')).map((td, index) => {
                            return index === 2 ? { content: td.textContent.trim(), styles: { halign: 'right' } } : td.textContent.trim();
                        })
                    );

                    // Add table to PDF
                    doc.autoTable({
                        head: [headers],
                        body: rows,
                        startY: summaryY + 15, // Start below the summary
                        margin: { left: 10, right: 10 },
                        headStyles: { fillColor: [232, 69, 69], textColor: [255, 255, 255] },
                        bodyStyles: { fontSize: 10 },
                        styles: { overflow: 'linebreak' },
                    });

                    // Add footer
                    const footerY = doc.internal.pageSize.getHeight() - 10;
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'italic');
                    doc.setTextColor(135, 206, 235); // Sky blue color
                    doc.text('Powered by AntonTech', pageWidth / 2, footerY, { align: 'center' });

                    // Save the PDF
                    doc.save(`${title.replace(/\s+/g, '_')}.pdf`);
                };

                img.onerror = function () {
                    alert("Failed to load the logo. Please check the logo path.");
                };
            }

            document.getElementById('downloadOverdueRepayments').addEventListener('click', function () {
                downloadTableAsPDF('overdueRepaymentsTable', 'Arrears list');
            });

            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('overdueRepaymentsTable');
            searchInput.addEventListener('input', function () {
                const filter = searchInput.value.toLowerCase();
                const rows = table.getElementsByTagName('tr');
                Array.from(rows).forEach((row, index) => {
                    if (index === 0) return; // Skip header row
                    const cells = row.getElementsByTagName('td');
                    const match = Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(filter));
                    row.style.display = match ? '' : 'none';
                });
            });
        });
    </script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
