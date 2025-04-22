<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/functions.php';
include 'includes/header.php'; 
include 'db.php';

if (!isset($_GET['loanId']) || !is_numeric($_GET['loanId'])) {
    die("Invalid Loan ID.");
}

$loanId = intval($_GET['loanId']); // Secure the input

// Fetch loan details
$sql_loan = "SELECT 
                borrowers.full_name AS borrower_name, 
                borrowers.mobile AS phone_number,
                borrowers.unique_number AS id_number,
                loan_products.name AS loan_product_name,
                loan_applications.loan_product, 
                loan_applications.principal AS principal_amount,
                SUM(repayments.amount) AS total_amount_due, 
                SUM(repayments.paid) AS total_amount_paid,
                MAX(repayments.repayment_date) AS last_repayment_date
            FROM 
                repayments
            INNER JOIN 
                loan_applications ON repayments.loan_id = loan_applications.id
            INNER JOIN 
                borrowers ON loan_applications.borrower = borrowers.id
            INNER JOIN 
                loan_products ON loan_applications.loan_product = loan_products.id
            WHERE 
                repayments.loan_id = ?
            GROUP BY 
                repayments.loan_id, borrowers.full_name, borrowers.mobile, borrowers.unique_number, 
                loan_applications.loan_product, loan_applications.principal";

$stmt_loan = $conn->prepare($sql_loan);
$stmt_loan->bind_param("i", $loanId);
$stmt_loan->execute();
$result_loan = $stmt_loan->get_result();
$loan = $result_loan->fetch_assoc();

if (!$loan) {
    die("Loan details not found.");
}

$totalDue = $loan['total_amount_due'];
$totalPaid = $loan['total_amount_paid'];
$balance = $totalDue - $totalPaid;

// Fetch repayment history with adjusted logic
$sql_history = "SELECT 
                    amount, 
                    paid, 
                    repayment_date 
                FROM repayments 
                WHERE loan_id = ? 
                ORDER BY repayment_date ASC";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("i", $loanId);
$stmt_history->execute();
$result_history = $stmt_history->get_result();

$adjusted_history = [];
$remaining_paid = $totalPaid; // Start with the total paid amount

while ($row = $result_history->fetch_assoc()) {
    $amount_due = $row['amount'];
    $paid_for_this_due = min($amount_due, $remaining_paid); // Deduct from the remaining paid amount
    $remaining_paid -= $paid_for_this_due;

    $adjusted_history[] = [
        'amount_due' => $amount_due,
        'paid' => $paid_for_this_due,
        'repayment_date' => date('d/m/Y', strtotime($row['repayment_date'])) // Format date as dd/mm/yyyy
    ];
}

$sql_records = "SELECT Amount, PaymentDate 
                FROM payment_date_records 
                WHERE loan_id = ? 
                ORDER BY PaymentDate DESC";
$stmt_records = $conn->prepare($sql_records);
$stmt_records->bind_param("i", $loanId);
$stmt_records->execute();
$result_records = $stmt_records->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repayment Details</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
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
        .card {
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .card-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #e84545;
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
        .btn-primary, .btn-secondary {
            background-color: #e84545;
            border: none;
            transition: all 0.3s ease-in-out;
        }
        .btn-primary:hover, .btn-secondary:hover {
            background-color: #d43d3d;
        }
        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #e84545;
            margin-bottom: 20px;
            text-align: center;
        }
        .download-btn {
            margin-bottom: 15px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <div class="header">
        <h1>Repayment Details</h1>
    </div>
    <div class="container mt-5">
        <div class="d-flex justify-content-start mb-3">
            <a href="performingBook.php" class="btn btn-secondary">Back to Repayments</a>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Loan Information</h5>
                <div class="row">
                    <div class="col-md-12">
                        <p><strong>Borrower:</strong> <?php echo htmlspecialchars($loan['borrower_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($loan['phone_number']); ?></p>
                        <p><strong>ID Number:</strong> <?php echo htmlspecialchars($loan['id_number']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Loan Product:</strong> <?php echo htmlspecialchars($loan['loan_product_name']); ?></p>
                        <p><strong>Principal Amount:</strong> <?php echo number_format($loan['principal_amount'], 2); ?> KES</p>
                        <p><strong>Total Amount:</strong> <?php echo number_format($totalDue, 2); ?> KES</p>
                        <p><strong>Total Paid:</strong> <?php echo number_format($totalPaid, 2); ?> KES</p>
                        <p><strong>Total Due Overdue Repayment Amount:</strong> <?php echo number_format($balance, 2); ?> KES</p>
                    </div>
                </div>
            </div>
        </div>
        <h3 class="section-title">Payment Records</h3>
        <div class="d-flex justify-content-end download-btn">
            <button id="downloadPaymentRecords" class="btn btn-primary">Download Payment Records</button>
        </div>
        <table class="table table-bordered" id="paymentRecordsTable">
            <thead>
                <tr>
                    <th>Amount Paid</th>
                    <th>Payment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row_records = $result_records->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo number_format($row_records['Amount'], 2); ?> KES</td>
                        <td><?php echo htmlspecialchars($row_records['PaymentDate']); ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($result_records->num_rows === 0): ?>
                    <tr><td colspan="2">No payment records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <h3 class="section-title">Repayment Schedule</h3>
        <div class="d-flex justify-content-end download-btn">
            <button id="downloadRepaymentHistory" class="btn btn-primary">Download Repayment Schedule</button>
        </div>
        <table class="table table-bordered" id="repaymentHistoryTable">
            <thead>
                <tr>
                    <th>Amount Due</th>
                    <th>Amount Paid</th>
                    <th>Repayment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adjusted_history as $row): ?>
                    <tr>
                        <td><?php echo number_format($row['amount_due'], 2); ?> KES</td>
                        <td><?php echo number_format($row['paid'], 2); ?> KES</td>
                        <td><?php echo htmlspecialchars($row['repayment_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($adjusted_history)): ?>
                    <tr><td colspan="3">No repayment history found.</td></tr>
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

                // Add logo
                const logoPath = "/Inua_Premium_services/assets/img/logo.png";
                const img = new Image();
                img.src = logoPath;

                img.onload = function () {
                    // Center the logo
                    const logoWidth = 50;
                    const logoHeight = 30;
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const logoX = (pageWidth - logoWidth) / 2;
                    doc.addImage(img, 'PNG', logoX, 10, logoWidth, logoHeight);

                    // Add company name below the logo on the left side
                    doc.setFontSize(14);
                    doc.text('Inua Premium Services', 10, 50); // Adjusted position for the company name

                    // Add borrower's name on the right side of the company name
                    doc.setFontSize(14);
                    doc.text('Borrower: <?php echo htmlspecialchars($loan["borrower_name"]); ?>', pageWidth - 80, 50); // Adjusted position for the borrower's name

                    // Add "Loan Terms" header
                    doc.setFontSize(14);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Loan Terms', pageWidth / 2, 65, { align: 'center' });

                    // Add "Loan Terms" table-like structure
                    const loanTermsX = 10;
                    const loanTermsY = 70;
                    const loanTermsWidth = pageWidth - 20;

                    // Loan details data for the first column
                    const loanDetailsColumn1 = [
                        ['Loan ID:', "<?php echo htmlspecialchars($loanId); ?>"],
                        ['Released Date:', "<?php echo htmlspecialchars($loan['released_date'] ?? 'N/A'); ?>"],
                        ['Maturity Date:', "<?php echo htmlspecialchars($loan['last_repayment_date'] ?? 'N/A'); ?>"], // Updated to use 'last_repayment_date'
                        ['Repayment Cycle:', "<?php echo htmlspecialchars($loan['repayment_cycle'] ?? 'N/A'); ?>"],
                        ['Principal Amount:', "<?php echo number_format($loan['principal_amount'], 2); ?> KES"],
                        ['Duration:', "<?php echo htmlspecialchars($loan['duration'] ?? 'N/A'); ?>"]
                    ];

                    // Loan details data for the second column
                    const loanDetailsColumn2 = [
                        ['Phone Number:', "<?php echo htmlspecialchars($loan['phone_number']); ?>"],
                        ['ID Number:', "<?php echo htmlspecialchars($loan['id_number']); ?>"],
                        ['Loan Product:', "<?php echo htmlspecialchars($loan['loan_product_name']); ?>"],
                        ['Total Amount:', "<?php echo number_format($totalDue, 2); ?> KES"],
                        ['Total Paid:', "<?php echo number_format($totalPaid, 2); ?> KES"],
                        ['Balance:', "<?php echo number_format($balance, 2); ?> KES"]
                    ];

                    // Calculate dynamic height based on the number of loan details
                    const rowHeight = 7; // Height of each row
                    const tableHeight = Math.max(loanDetailsColumn1.length, loanDetailsColumn2.length) * rowHeight + 10; // Add padding for header

                    // Draw boundary
                    doc.setDrawColor(0);
                    doc.setLineWidth(0.5);
                    doc.rect(loanTermsX, loanTermsY, loanTermsWidth, tableHeight);

                    // Draw vertical line to split into two columns
                    const columnSplitX = loanTermsX + loanTermsWidth / 2;
                    doc.line(columnSplitX, loanTermsY, columnSplitX, loanTermsY + tableHeight);

                    // Add loan details inside the boundary in the first column
                    doc.setFont('helvetica', 'normal');
                    let textY = loanTermsY + 10;
                    const column1X = loanTermsX + 5;

                    loanDetailsColumn1.forEach(detail => {
                        const [label, value] = detail;
                        doc.text(label, column1X, textY);
                        doc.text(value, column1X + 50, textY); // Adjusted spacing for value
                        textY += rowHeight;
                    });

                    // Add loan details inside the boundary in the second column
                    textY = loanTermsY + 10; // Reset textY for the second column
                    const column2X = columnSplitX + 5;

                    loanDetailsColumn2.forEach(detail => {
                        const [label, value] = detail;
                        doc.text(label, column2X, textY);
                        doc.text(value, column2X + 50, textY); // Adjusted spacing for value
                        textY += rowHeight;
                    });

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
                        startY: loanTermsY + tableHeight + 10, // Start below the "Loan Terms" section
                        headStyles: { fontStyle: 'italic' }
                    });

                    // Add footer with sky blue text
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'italic');
                    doc.setTextColor(135, 206, 235); // Sky blue color
                    doc.text('Powered by AntonTech', pageWidth / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });

                    // Save the PDF
                    const sanitizedBorrowerName = "<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $loan['borrower_name']); ?>";
                    doc.save(`${sanitizedBorrowerName}_${title.replace(/\s+/g, '_').toLowerCase()}.pdf`);
                };

                img.onerror = function () {
                    alert("Failed to load the logo. Please check the logo path.");
                };
            }

            document.getElementById('downloadPaymentRecords').addEventListener('click', function () {
                downloadTableAsPDF('paymentRecordsTable', 'Payment Records');
            });

            document.getElementById('downloadRepaymentHistory').addEventListener('click', function () {
                downloadTableAsPDF('repaymentHistoryTable', 'Repayment Schedule');
            });
        });
    </script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php 
$stmt_loan->close();
$stmt_history->close();
$stmt_records->close();
$conn->close();
?>
