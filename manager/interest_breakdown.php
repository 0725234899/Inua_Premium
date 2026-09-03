<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

$sql_interest_details = "SELECT
    l.id,
    b.full_name AS borrower_name,
    l.principal,
    l.loan_duration,
    l.total_amount,
    (l.total_amount - l.principal) AS interest,
    COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0) AS total_paid,
    (l.total_amount - COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)) AS loan_balance,
    GREATEST(0, (
        COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)
        - (l.principal + (l.principal * 0.06 * l.loan_duration))
    )) AS penalty_amount,
    l.loan_status
FROM loan_applications l
INNER JOIN borrowers b ON l.borrower = b.id
WHERE l.loan_status IN ('approved', 'rolled_over')
   OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%'
ORDER BY
    CASE
        WHEN LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%' THEN 0
        WHEN (l.total_amount - COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)) <= 0 THEN 1
        ELSE 2
    END,
    l.loan_release_date DESC";
$result_interest_details = $conn->query($sql_interest_details);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interest Breakdown - Manager</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --ink: #172331;
            --muted: #687582;
            --line: #dbe3e8;
            --paper: #ffffff;
            --canvas: #f2f5f6;
            --teal: #147d78;
            --gold: #c7973e;
        }

        body { background: var(--canvas); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; }
        .main { margin-left: 250px; padding: 34px 22px 60px; transition: margin-left 0.3s ease; }
        .main > .header { background: var(--ink); border-top: 4px solid var(--gold); color: white; margin: 0 auto; max-width: 1280px; padding: 26px 34px; }
        .main > .header h1 { color: white; font-family: Georgia, serif; font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: normal; letter-spacing: .02em; }
        .sidebar-toggle-btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; margin-right: 14px; padding: 8px 12px; }
        .sidebar-toggle-btn:hover { background: var(--teal); border-color: var(--teal); color: white; }
        .main > .header .btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; padding: 8px 14px; }
        .main > .header .btn:hover { background: var(--teal); border-color: var(--teal); }
        .report-panel { background: var(--paper); border: 1px solid var(--line); margin: 18px auto 0; max-width: 1280px; }
        .section-title { background: var(--ink); border-top: 4px solid var(--gold); color: white; font-family: Georgia, serif; font-size: 1.25rem; font-weight: normal; margin: 0; padding: 18px 22px; }
        .table-container { overflow-x: auto; padding: 18px 22px 0; }
        #interestSearch { border-color: var(--line); border-radius: 0; color: var(--ink); max-width: 400px; }
        #interestSearch:focus { border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(20, 125, 120, .12); }
        .table { margin: 0; }
        .table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; padding: 14px 18px; text-transform: uppercase; white-space: nowrap; }
        .table tbody td { border-color: #e6ecef; padding: 15px 18px; vertical-align: middle; }
        .table tbody tr:hover { background: #f7faf9; }
        .table a { color: var(--teal); font-weight: bold; text-decoration: none; }
        .table a:hover { color: var(--ink); text-decoration: underline; }
        .cleared-loan-badge, .rolled-over-badge { border-radius: 20px; display: inline-block; font-size: .72rem; font-weight: bold; letter-spacing: .05em; padding: 5px 10px; text-transform: uppercase; }
        .cleared-loan-badge { background: #edf0f2; color: #53636d; }
        .rolled-over-badge { background: #e6f3f1; color: #146b67; }
        .sidebar { transition: all 0.3s ease; }
        .sidebar.collapsed { display: none; }
        .main.sidebar-collapsed { margin-left: 0; }
        @media (max-width: 768px) { .main { margin-left: 0; padding: 20px 12px 40px; } }
        @media (max-width: 520px) { .main > .header { padding: 20px; } .main > .header h1 { font-size: 1.8rem; } .table-container { padding-left: 12px; padding-right: 12px; } }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="sidebar" id="sidebarWrapper">
    <?php include '../includes/sidebar.php'; ?>
</div>
<main class="main" id="mainContent">
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="mb-0">Interest Breakdown</h1>
        </div>
        <a href="index.php" class="btn">Back to Dashboard</a>
    </div>

    <section class="report-panel">
        <h2 id="interestTable" class="section-title">Interest Breakdown (Clients)</h2>
        <div class="table-container">
            <input type="text" id="interestSearch" placeholder="Search by borrower or phone..." class="form-control mb-3">
            <table id="interestBreakdownTable" class="table table-bordered">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Loan ID</th>
                        <th>Principal (KSH)</th>
                        <th>Total Amount (KSH)</th>
                        <th>Interest (KSH)</th>
                        <th>Total Paid (KSH)</th>
                        <th>Loan Balance (KSH)</th>
                        <th>Penalties (KSH)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_interest_details && $result_interest_details->num_rows > 0): ?>
                        <?php while ($row = $result_interest_details->fetch_assoc()): ?>
                            <?php
                                $isRolledOver = preg_match('/roll/i', trim($row['loan_status'] ?? ''));
                                $isCleared = !$isRolledOver && floatval($row['loan_balance']) <= 0;
                                $penalty = floatval($row['penalty_amount'] ?? 0);
                                $rowClass = $isRolledOver ? 'rolled-over-loan' : ($isCleared ? 'cleared-loan' : '');
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                                <td><a href="repayment_details.php?loanId=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['id']); ?></a></td>
                                <td><?php echo number_format($row['principal'], 2); ?></td>
                                <td><?php echo number_format($row['total_amount'], 2); ?></td>
                                <td><?php echo number_format($row['interest'], 2); ?></td>
                                <td><?php echo number_format($row['total_paid'], 2); ?></td>
                                <td><?php echo number_format($row['loan_balance'], 2); ?></td>
                                <td><?php echo number_format($penalty, 2); ?></td>
                                <td>
                                    <?php if ($isRolledOver): ?>
                                        <span class="rolled-over-badge">Rolled Over</span>
                                    <?php elseif ($isCleared): ?>
                                        <span class="cleared-loan-badge">Cleared</span>
                                    <?php else: ?>
                                        Not Cleared
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center">No loans found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleButton = document.getElementById('sidebarToggleMain');
        const sidebarWrapper = document.getElementById('sidebarWrapper');
        const mainContent = document.getElementById('mainContent');

        if (toggleButton && sidebarWrapper && mainContent) {
            toggleButton.addEventListener('click', function () {
                sidebarWrapper.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
            });
        }

        const interestSearch = document.getElementById('interestSearch');
        if (interestSearch) {
            interestSearch.addEventListener('input', function () {
                const filter = this.value.toLowerCase().replace(/[^a-z0-9]/g, '');
                const rows = document.querySelectorAll('#interestBreakdownTable tbody tr');

                rows.forEach(row => {
                    const cells = Array.from(row.cells).map(cell => cell.textContent.toLowerCase().replace(/[^a-z0-9]/g, ''));
                    row.style.display = cells.some(text => text.includes(filter)) ? '' : 'none';
                });
            });
        }
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
