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
    die("Error executing query: " . $conn->error);
}
$borrowerRows = $result->fetch_all(MYSQLI_ASSOC);
$borrowerCount = count($borrowerRows);
$totalLoanTaken = array_sum(array_map('floatval', array_column($borrowerRows, 'total_loan_taken')));
$totalOpenBalance = array_sum(array_map('floatval', array_column($borrowerRows, 'open_loans_balance')));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>View Borrowers</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <style>
        body { background: #f8f8f8; color: #282828; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .main { box-sizing: border-box; height: 100vh; margin-left: 250px; overflow: hidden; padding: 88px 24px 24px; }
        .page-container { display: flex; flex-direction: column; height: 100%; margin: 0 auto; max-width: 1320px; min-height: 0; }
        .shell { background: #fff; border: 0; border-radius: 18px; box-shadow: 0 12px 28px rgba(0, 0, 0, .08); display: flex; flex: 1; flex-direction: column; min-height: 0; }
        .company-header { display: flex; justify-content: space-between; align-items: center; gap: 18px; border-bottom: 2px solid #ef4444; padding-bottom: 18px; margin-bottom: 24px; }
        .company-header img { height: 64px; width: auto; object-fit: contain; }
        .section-panel { background: #fff7f7; border: 1px solid #f4d1d1; border-radius: 12px; display: flex; flex: 1; flex-direction: column; min-height: 0; padding: 20px; }
        .borrower-page-heading, .metric-grid { flex: 0 0 auto; }
        .borrower-table-controls { flex: 0 0 auto; }
        .borrower-table-scroll { flex: 1 1 auto; min-height: 0; overflow: auto; overscroll-behavior: contain; }
        .section-title { color: #0b2f9f; font-size: 1.05rem; font-weight: 700; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #ef4444; }
        .metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 24px; }
        .metric { border-left: 4px solid #0b2f9f; background: #f7f9ff; border-radius: 8px; padding: 16px 18px; min-width: 0; }
        .metric:nth-child(2) { border-left-color: #147d78; }
        .metric:nth-child(3) { border-left-color: #c7973e; }
        .metric-label { color: #64748b; display: block; font-size: .82rem; }
        .metric-value { color: #0b2f9f; display: block; font-size: 1.25rem; margin-top: 5px; overflow-wrap: anywhere; }
        .table-responsive { border: 1px solid #e7e7e7; border-radius: 10px; overflow: visible; }
        .borrower-table { margin: 0; min-width: 920px; }
        .borrower-table thead th { background: #edf2f8; border-bottom: 2px solid #0b2f9f; color: #334155; font-size: .8rem; position: sticky; top: 0; white-space: nowrap; z-index: 2; }
        .borrower-table td { vertical-align: middle; }
        .amount { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .status-badge { background: #e8f4ed; border-radius: 999px; color: #256144; display: inline-block; font-size: .78rem; padding: 5px 10px; }
        .empty-state { color: #64748b; padding: 36px 18px; text-align: center; }
        @media (max-width: 1199px) { .main { margin-left: 0; padding: 88px 16px 20px; } }
        @media (max-width: 768px) { .main { padding: 78px 10px 12px; } .shell { padding: 18px !important; } .company-header img { height: 52px; } .metric-grid { grid-template-columns: 1fr; gap: 8px; margin-bottom: 14px; } .metric { padding: 10px 14px; } .metric-value { font-size: 1.05rem; } }
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
<?php include 'includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<main class="main">
    <div class="page-container">
        <div class="shell p-4">
            <div class="company-header">
                <div>
                    <div class="fw-bold text-uppercase text-secondary" style="letter-spacing: 1px;">Inua Premium Services</div>
                    <div class="text-muted small">Borrower Management</div>
                </div>
                <img src="../assets/img/logo.png" alt="Inua Premium Services Logo">
            </div>

            <div class="borrower-page-heading d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="mb-1">Borrower Register</h2>
                    <div class="text-muted">Borrower profiles and outstanding loan balances.</div>
                </div>
                <a href="add_borrower.php" class="btn btn-primary">Add Borrower</a>
            </div>

            <section class="metric-grid" aria-label="Borrower summary">
                <div class="metric"><span class="metric-label">Registered Borrowers</span><strong class="metric-value"><?php echo number_format($borrowerCount); ?></strong></div>
                <div class="metric"><span class="metric-label">Total Loans Taken</span><strong class="metric-value">KES <?php echo number_format($totalLoanTaken, 2); ?></strong></div>
                <div class="metric"><span class="metric-label">Open Loan Balance</span><strong class="metric-value">KES <?php echo number_format($totalOpenBalance, 2); ?></strong></div>
            </section>

            <section class="section-panel">
                <div class="borrower-table-controls d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h3 class="section-title mb-0 flex-grow-1">Borrower Details</h3>
                    <input type="search" id="borrowerSearch" class="form-control" placeholder="Search borrowers" aria-label="Search borrowers" style="max-width: 300px;">
                </div>
                <?php if ($borrowerRows): ?>
                    <div class="borrower-table-scroll mt-3">
                        <div class="table-responsive">
                        <table class="table table-hover borrower-table" id="borrowerTable">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Business Name</th>
                                    <th>Unique Number</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th class="text-end">Total Loan Taken</th>
                                    <th class="text-end">Open Loan Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrowerRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['business_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['unique_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['mobile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end amount">KES <?php echo number_format((float) ($row['total_loan_taken'] ?? 0), 2); ?></td>
                                        <td class="text-end amount">KES <?php echo number_format((float) ($row['open_loans_balance'] ?? 0), 2); ?></td>
                                        <td><span class="status-badge"><?php echo htmlspecialchars((string) ($row['status'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <div class="borrower-table-controls text-muted small mt-2" id="borrowerCountLabel"><?php echo number_format($borrowerCount); ?> borrowers</div>
                <?php else: ?>
                    <div class="empty-state">No borrowers found.</div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<script>
    const borrowerSearch = document.getElementById('borrowerSearch');
    const borrowerTable = document.getElementById('borrowerTable');
    const borrowerCountLabel = document.getElementById('borrowerCountLabel');
    if (borrowerSearch && borrowerTable) {
        borrowerSearch.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let visibleRows = 0;
            borrowerTable.querySelectorAll('tbody tr').forEach(function (row) {
                const matches = row.textContent.toLowerCase().includes(query);
                row.hidden = !matches;
                if (matches) visibleRows++;
            });
            borrowerCountLabel.textContent = visibleRows.toLocaleString() + ' borrowers';
        });
    }
</script>

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
