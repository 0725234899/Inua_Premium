<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);

include 'db.php';

// Fetch all loan officers
$sql_officers = "SELECT id, name AS full_name FROM users WHERE role_id = '2'";
$stmt_officers = $conn->prepare($sql_officers);
$stmt_officers->execute();
$result_officers = $stmt_officers->get_result();

// Get selected loan officer (if any)
$selected_officer = isset($_GET['officer_id']) ? $_GET['officer_id'] : 'all';
$officer_filter = ($selected_officer !== 'all') ? "AND borrowers.loan_officer = (SELECT email FROM users WHERE id = ?)" : "";

// Get selected day (if any)
$selected_day = isset($_GET['day']) ? $_GET['day'] : 'all';
$day_filter = ($selected_day !== 'all') ? "AND DAYNAME(repayments.repayment_date) = ?" : "";

// Query to get overdue repayments grouped by borrower
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
                    $officer_filter
                    $day_filter
                GROUP BY 
                    borrowers.full_name, borrowers.mobile
                ORDER BY 
                    borrowers.full_name";

$stmt_overdue = $conn->prepare($sql_overdue);
if ($selected_officer !== 'all' && $selected_day !== 'all') {
    $stmt_overdue->bind_param("ss", $selected_officer, $selected_day);
} elseif ($selected_officer !== 'all') {
    $stmt_overdue->bind_param("s", $selected_officer);
} elseif ($selected_day !== 'all') {
    $stmt_overdue->bind_param("s", $selected_day);
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
    <title>Overdue Repayments</title>
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
</head>
<body>
    <div class="header">
        <h1>Overdue Repayments</h1>
    </div>
    <div class="container mt-5">
        <div class="d-flex justify-content-start mb-3">
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <p class="text-center"><strong>Total Overdue:</strong> KSH <?= number_format($total_overdue, 2); ?></p>
        <ul class="nav nav-tabs justify-content-center">
            <li class="nav-item">
                <a class="nav-link <?= ($selected_officer === 'all') ? 'active' : '' ?>" href="?officer_id=all">All Loan Officers</a>
            </li>
            <?php while ($officer = $result_officers->fetch_assoc()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($selected_officer == $officer['id']) ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($officer['id']); ?>">
                        <?= htmlspecialchars($officer['full_name']); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>

        <!-- Day Tabs -->
        <ul class="nav nav-tabs justify-content-center mt-3">
            <li class="nav-item">
                <a class="nav-link <?= (!isset($_GET['day']) || $_GET['day'] === 'all') ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=all">All Days</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_GET['day'] ?? '') === 'Monday' ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Monday">Monday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_GET['day'] ?? '') === 'Tuesday' ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Tuesday">Tuesday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_GET['day'] ?? '') === 'Wednesday' ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Wednesday">Wednesday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_GET['day'] ?? '') === 'Thursday' ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Thursday">Thursday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_GET['day'] ?? '') === 'Friday' ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Friday">Friday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_GET['day'] ?? '') === 'Saturday' ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Saturday">Saturday</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_GET['day'] ?? '') === 'Sunday' ? 'active' : '' ?>" href="?officer_id=<?= htmlspecialchars($selected_officer); ?>&day=Sunday">Sunday</a>
            </li>
        </ul>

        <h3 class="section-title mt-4">Overdue Repayments</h3>
        <table id="overdueRepaymentsTable" class="table table-bordered">
            <thead>
                <tr>
                    <th>Borrower</th>
                    <th>Phone Number</th>
                    <th>Total Overdue Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_overdue->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['borrower_name']); ?></td>
                        <td><?= htmlspecialchars($row['phone_number']); ?></td>
                        <td>KSH <?= number_format($row['total_overdue'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result_overdue->num_rows === 0): ?>
                    <tr><td colspan="3">No overdue repayments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="text-center mt-3">
            <button id="downloadOverdueRepayments" class="btn btn-danger">Download Overdue Repayments</button>
        </div>
        <div class="text-center mt-3">
            <button id="downloadOverdueRepaymentsCSV" class="btn btn-secondary">Download Overdue Repayments (CSV)</button>
        </div>
    </div>
    <footer class="text-center mt-5">
        <p><em>Powered by AntonTech</em></p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function downloadTableAsPDF(tableId, title) {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Add logo
                const logoPath = "/Inua_Premium_services/assets/img/logo.png";
                const img = new Image();
                img.src = logoPath;

                img.onload = function () {
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const logoWidth = 50;
                    const logoHeight = 30;
                    const logoX = (pageWidth - logoWidth) / 2;

                    doc.addImage(img, 'PNG', logoX, 10, logoWidth, logoHeight);

                    // Add title
                    doc.setFontSize(18);
                    doc.text(title, pageWidth / 2, 50, { align: 'center' });

                    // Extract table data
                    const table = document.getElementById(tableId);
                    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
                    const rows = Array.from(table.querySelectorAll('tbody tr')).map(row =>
                        Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim())
                    );

                    // Add table to PDF
                    doc.autoTable({
                        head: [headers],
                        body: rows,
                        startY: 60,
                        headStyles: { fillColor: [232, 69, 69], textColor: [255, 255, 255] },
                    });

                    // Save the PDF
                    doc.save(`${title.replace(/\s+/g, '_')}.pdf`);
                };

                img.onerror = function () {
                    alert("Failed to load the logo. Please check the logo path.");
                };
            }

            document.getElementById('downloadOverdueRepayments').addEventListener('click', function () {
                downloadTableAsPDF('overdueRepaymentsTable', 'Overdue Repayments');
            });

            function downloadTableAsCSV(tableId, filename) {
                const table = document.getElementById(tableId);
                const rows = Array.from(table.querySelectorAll('tr'));
                const csvContent = rows.map(row => {
                    const cells = Array.from(row.querySelectorAll('th, td'));
                    return cells.map(cell => `"${cell.textContent.trim()}"`).join(',');
                }).join('\n');

                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                link.click();
            }

            document.getElementById('downloadOverdueRepaymentsCSV').addEventListener('click', function () {
                downloadTableAsCSV('overdueRepaymentsTable', 'Overdue_Repayments.csv');
            });
        });
    </script>
</body>
</html>
