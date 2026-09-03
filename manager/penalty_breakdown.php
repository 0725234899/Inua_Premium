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

$sql_penalty_details = "SELECT
    l.id,
    b.full_name AS borrower_name,
    COALESCE(u.name, 'Unassigned') AS loan_officer_name,
    l.principal,
    l.loan_duration,
    COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0) AS total_paid,
    GREATEST(0, (
        COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)
        - (l.principal + (l.principal * 0.06 * l.loan_duration))
    )) AS penalty_amount,
    l.loan_status
FROM loan_applications l
INNER JOIN borrowers b ON l.borrower = b.id
LEFT JOIN users u ON b.loan_officer = u.email
WHERE (l.loan_status IN ('approved', 'rolled_over')
   OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%')
  AND GREATEST(0, (
        COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)
        - (l.principal + (l.principal * 0.06 * l.loan_duration))
    )) > 0
ORDER BY penalty_amount DESC, l.loan_release_date DESC";
$result_penalty_details = $conn->query($sql_penalty_details);
$total_penalty_amount = 0;
$loanOfficers = [];
if ($result_penalty_details) {
    while ($penaltyRow = $result_penalty_details->fetch_assoc()) {
        $total_penalty_amount += (float) $penaltyRow['penalty_amount'];
        $loanOfficers[$penaltyRow['loan_officer_name']] = true;
    }
    $result_penalty_details->data_seek(0);
}
$loanOfficers = array_keys($loanOfficers);
sort($loanOfficers, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penalty Breakdown - Manager</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --ink: #172331; --muted: #687582; --line: #dbe3e8; --paper: #ffffff; --canvas: #f2f5f6; --teal: #147d78; --gold: #c7973e; }
        body { background: var(--canvas); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; }
        .main { margin-left: 250px; padding: 34px 22px 60px; transition: margin-left 0.3s ease; }
        .main > .header { background: var(--ink); border-top: 4px solid var(--gold); color: white; margin: 0 auto; max-width: 1280px; padding: 26px 34px; }
        .main > .header h1 { color: white; font-family: Georgia, serif; font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: normal; letter-spacing: .02em; }
        .sidebar-toggle-btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; margin-right: 14px; padding: 8px 12px; }
        .sidebar-toggle-btn:hover, .main > .header .btn:hover { background: var(--teal); border-color: var(--teal); color: white; }
        .main > .header .btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; padding: 8px 14px; }
        .report-panel { background: var(--paper); border: 1px solid var(--line); margin: 18px auto 0; max-width: 1280px; }
        .panel-summary { border-bottom: 1px solid var(--line); color: var(--muted); padding: 18px 22px; }
        .panel-summary strong { color: var(--ink); font-family: Georgia, serif; font-size: 1.45rem; margin-left: 8px; }
        .officer-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 22px 0; }
        .officer-tab { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); cursor: pointer; padding: 9px 14px; }
        .officer-tab:hover, .officer-tab.active { background: var(--teal); border-color: var(--teal); color: white; }
        .section-title { background: var(--ink); border-top: 4px solid var(--gold); color: white; font-family: Georgia, serif; font-size: 1.25rem; font-weight: normal; margin: 0; padding: 18px 22px; }
        .table-container { overflow-x: auto; padding: 18px 22px 0; }
        #penaltySearch { border-color: var(--line); border-radius: 0; color: var(--ink); max-width: 400px; }
        #penaltySearch:focus { border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(20, 125, 120, .12); }
        .table { margin: 0; }
        .table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; padding: 14px 18px; text-transform: uppercase; white-space: nowrap; }
        .table tbody td { border-color: #e6ecef; padding: 15px 18px; vertical-align: middle; }
        .table tbody tr:hover { background: #f7faf9; }
        .table a { color: var(--teal); font-weight: bold; text-decoration: none; }
        .table a:hover { color: var(--ink); text-decoration: underline; }
        .penalty-value { color: #a95d55; font-weight: bold; white-space: nowrap; }
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
            <button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
            <h1 class="mb-0">Penalty Breakdown</h1>
        </div>
        <a href="index.php" class="btn">Back to Dashboard</a>
    </div>

    <section class="report-panel">
        <div class="panel-summary">Total penalties<strong>KSH <?php echo number_format($total_penalty_amount, 2); ?></strong></div>
        <h2 class="section-title">Penalty Breakdown (Clients)</h2>
        <div class="officer-tabs" id="officerTabs" role="tablist" aria-label="Filter penalties by loan officer">
            <button type="button" class="officer-tab active" data-officer="all" role="tab" aria-selected="true">All Officers</button>
            <?php foreach ($loanOfficers as $loanOfficer): ?>
                <button type="button" class="officer-tab" data-officer="<?php echo htmlspecialchars($loanOfficer, ENT_QUOTES, 'UTF-8'); ?>" role="tab" aria-selected="false"><?php echo htmlspecialchars($loanOfficer); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="table-container">
            <input type="text" id="penaltySearch" placeholder="Search by borrower or loan ID..." class="form-control mb-3">
            <table id="penaltyTable" class="table table-bordered">
                <thead><tr><th>Borrower</th><th>Loan ID</th><th>Loan Officer</th><th>Principal (KSH)</th><th>Total Paid (KSH)</th><th>Loan Duration</th><th>Penalty (KSH)</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if ($result_penalty_details && $result_penalty_details->num_rows > 0): ?>
                        <?php while ($row = $result_penalty_details->fetch_assoc()): ?>
                            <tr data-officer="<?php echo htmlspecialchars($row['loan_officer_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                                <td><a href="repayment_details.php?loanId=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['id']); ?></a></td>
                                <td><?php echo htmlspecialchars($row['loan_officer_name']); ?></td>
                                <td><?php echo number_format($row['principal'], 2); ?></td>
                                <td><?php echo number_format($row['total_paid'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['loan_duration']); ?></td>
                                <td class="penalty-value"><?php echo number_format($row['penalty_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['loan_status']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No penalties found.</td></tr>
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

        const penaltySearch = document.getElementById('penaltySearch');
        const officerTabs = document.querySelectorAll('.officer-tab');
        let selectedOfficer = 'all';

        function filterPenaltyRows() {
            const filter = penaltySearch.value.toLowerCase().replace(/[^a-z0-9]/g, '');
            document.querySelectorAll('#penaltyTable tbody tr[data-officer]').forEach(row => {
                const rowText = row.textContent.toLowerCase().replace(/[^a-z0-9]/g, '');
                const officerMatches = selectedOfficer === 'all' || row.dataset.officer === selectedOfficer;
                row.style.display = officerMatches && rowText.includes(filter) ? '' : 'none';
            });
        }

        if (penaltySearch) {
            penaltySearch.addEventListener('input', function () {
                filterPenaltyRows();
            });
        }

        officerTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                selectedOfficer = this.dataset.officer;
                officerTabs.forEach(item => {
                    const active = item === this;
                    item.classList.toggle('active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                filterPenaltyRows();
            });
        });
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
