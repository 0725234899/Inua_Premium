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

$interestCalculationColumnStmt = $conn->query("SELECT COUNT(*) AS column_count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_applications' AND COLUMN_NAME = 'interest_calculation'");
$hasInterestCalculationColumn = $interestCalculationColumnStmt && (int) $interestCalculationColumnStmt->fetch_assoc()['column_count'] > 0;
$weeklyLoanExpression = $hasInterestCalculationColumn
    ? "LOWER(COALESCE(l.interest_calculation, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.repayment_cycle, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.loan_duration_unit, '')) IN ('weekly', 'week', 'weeks')"
    : "LOWER(COALESCE(l.repayment_cycle, '')) IN ('weekly', 'week', 'weeks') OR LOWER(COALESCE(l.loan_duration_unit, '')) IN ('weekly', 'week', 'weeks')";
$totalPaidExpression = "COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)";
$penaltyThresholdExpression = "l.principal + (l.principal * CASE WHEN $weeklyLoanExpression THEN 0.06 ELSE 0.24 END * l.loan_duration)";
$loanPenaltyDebitExpression = "COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0)";

$sql_penalty_details = "SELECT
    l.id,
    b.full_name AS borrower_name,
    COALESCE(u.name, 'Unassigned') AS loan_officer_name,
    COALESCE(a.area_name, 'Unassigned') AS region_name,
    l.principal,
    l.total_amount,
    l.loan_duration,
    l.loan_release_date,
    $totalPaidExpression AS total_paid,
    GREATEST(0, l.total_amount - ($totalPaidExpression)) AS total_balance,
    GREATEST(0, (
        ($totalPaidExpression)
        - ($penaltyThresholdExpression)
    )) AS gross_penalty_amount,
    GREATEST(0, (
        ($totalPaidExpression)
        - ($penaltyThresholdExpression)
        - ($loanPenaltyDebitExpression)
    )) AS penalty_amount,
    l.loan_status
FROM loan_applications l
INNER JOIN borrowers b ON l.borrower = b.id
LEFT JOIN users u ON b.loan_officer = u.email
LEFT JOIN areas a ON u.area = a.area_id
ORDER BY
    CASE WHEN ($totalPaidExpression) >= l.total_amount THEN 1 ELSE 0 END DESC,
    penalty_amount DESC,
    l.loan_release_date DESC,
    l.id DESC";
$result_penalty_details = $conn->query($sql_penalty_details);
$total_penalty_amount = 0;
$gross_penalty_amount = 0;
$used_penalty_amount = 0;
$loanOfficers = [];
$regions = [];

$usedPenaltyStmt = $conn->query('SELECT COALESCE(SUM(amount), 0) AS used_penalty FROM penalty_actions');
if ($usedPenaltyStmt && $usedPenaltyStmt->num_rows > 0) {
    $used_penalty_amount = (float) $usedPenaltyStmt->fetch_assoc()['used_penalty'];
}

if ($result_penalty_details) {
    while ($penaltyRow = $result_penalty_details->fetch_assoc()) {
        $gross_penalty_amount += (float) $penaltyRow['gross_penalty_amount'];
        $total_penalty_amount += (float) $penaltyRow['penalty_amount'];
        $loanOfficers[$penaltyRow['loan_officer_name']] = true;
        $regions[$penaltyRow['region_name']] = true;
    }
    $result_penalty_details->data_seek(0);
}
$balance_penalty_amount = max(0, $gross_penalty_amount - $used_penalty_amount);
$officerUsedPenalties = [];
$officerUsedPenaltyStmt = $conn->query("SELECT pa.officer_email, COALESCE(u.name, 'Unassigned') AS officer_name, COALESCE(SUM(pa.amount), 0) AS used_penalty
    FROM penalty_actions pa
    LEFT JOIN users u
        ON CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(pa.officer_email USING utf8mb4) COLLATE utf8mb4_general_ci
    GROUP BY pa.officer_email, u.name
    ORDER BY u.name");
if ($officerUsedPenaltyStmt) {
    while ($officerPenaltyRow = $officerUsedPenaltyStmt->fetch_assoc()) {
        $officerName = trim((string) ($officerPenaltyRow['officer_name'] ?? 'Unassigned'));
        $officerUsedPenalties[$officerName] = (float) ($officerPenaltyRow['used_penalty'] ?? 0);
    }
}
$loanOfficers = array_keys($loanOfficers);
sort($loanOfficers, SORT_NATURAL | SORT_FLAG_CASE);
$regions = array_keys($regions);
sort($regions, SORT_NATURAL | SORT_FLAG_CASE);
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
        .penalty-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 18px auto 0; max-width: 1280px; }
        .penalty-metric { background: var(--paper); border: 1px solid var(--line); border-left: 4px solid #a95d55; padding: 18px 20px; min-height: 128px; display: flex; flex-direction: column; justify-content: center; }
        .penalty-metric-label { color: var(--muted); display: block; font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
        .penalty-metric-value { color: var(--ink); display: block; font-family: Georgia, serif; font-size: 1.45rem; margin-top: 8px; }
        .period-filter { border-bottom: 1px solid var(--line); padding: 16px 22px; }
        .period-controls { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; }
        .period-controls label { color: var(--muted); font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
        .period-controls select { border: 1px solid var(--line); border-radius: 0; color: var(--ink); padding: 8px 10px; }
        .period-controls select.period-value { display: none; }
        .period-controls.specific select.period-value { display: inline-block; }
        .period-error { color: #a95d55; display: none; font-size: .85rem; margin: 8px 0 0; }
        .officer-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 22px 0; }
        .officer-tab { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); cursor: pointer; padding: 9px 14px; }
        .officer-tab:hover, .officer-tab.active { background: var(--teal); border-color: var(--teal); color: white; }
        .region-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 22px 0; }
        .region-tab { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); cursor: pointer; padding: 9px 14px; }
        .region-tab:hover, .region-tab.active { background: var(--ink); border-color: var(--ink); color: white; }
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
        <div class="d-flex gap-2">
            <a href="penalty_actions.php" class="btn">Penalty Actions</a>
            <a href="index.php" class="btn">Back to Dashboard</a>
        </div>
    </div>

    <div class="penalty-metrics" aria-label="Penalty summary">
        <div class="penalty-metric"><span class="penalty-metric-label">Total Penalties</span><strong class="penalty-metric-value" id="totalPenalty">KSH <?php echo number_format($gross_penalty_amount, 2); ?></strong></div>
        <div class="penalty-metric"><span class="penalty-metric-label">Balance Penalty</span><strong class="penalty-metric-value" id="balancePenalty">KSH <?php echo number_format($balance_penalty_amount, 2); ?></strong></div>
        <div class="penalty-metric"><span class="penalty-metric-label">Used Penalty</span><strong class="penalty-metric-value" id="usedPenalty">KSH <?php echo number_format($used_penalty_amount, 2); ?></strong></div>
    </div>

    <section class="report-panel">
        <h2 class="section-title">Penalty Breakdown (Clients)</h2>
        <div class="period-filter">
            <div class="period-controls" id="periodControls">
                <label for="periodType">Period</label>
                <select id="periodType" aria-label="Select penalty period">
                    <option value="general">General</option>
                    <option value="specific">Specific Period</option>
                </select>
                <select id="periodYear" class="period-value" aria-label="Select period year">
                    <option value="0">Select year</option>
                    <?php for ($year = (int) date('Y'); $year >= 2000; $year--): ?>
                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                    <?php endfor; ?>
                </select>
                <select id="periodMonth" class="period-value" aria-label="Select period month">
                    <option value="0">All months</option>
                    <?php foreach (range(1, 12) as $month): ?>
                        <option value="<?php echo $month; ?>"><?php echo date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="period-error" id="periodError">Select a year for the specific period. You may select a month or All months.</p>
        </div>
        <div class="region-tabs" id="regionTabs" role="tablist" aria-label="Filter penalties by region">
            <button type="button" class="region-tab active" data-region-filter="all" role="tab" aria-selected="true">All Regions</button>
            <?php foreach ($regions as $region): ?>
                <button type="button" class="region-tab" data-region-filter="<?php echo htmlspecialchars($region, ENT_QUOTES, 'UTF-8'); ?>" role="tab" aria-selected="false"><?php echo htmlspecialchars($region); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="officer-tabs" id="officerTabs" role="tablist" aria-label="Filter penalties by loan officer">
            <button type="button" class="officer-tab active" data-officer="all" data-used-penalty="<?php echo array_sum($officerUsedPenalties); ?>" role="tab" aria-selected="true">All Officers</button>
            <?php foreach ($loanOfficers as $loanOfficer): ?>
                <button type="button" class="officer-tab" data-officer="<?php echo htmlspecialchars($loanOfficer, ENT_QUOTES, 'UTF-8'); ?>" data-used-penalty="<?php echo (float) ($officerUsedPenalties[$loanOfficer] ?? 0); ?>" role="tab" aria-selected="false"><?php echo htmlspecialchars($loanOfficer); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="table-container">
            <input type="text" id="penaltySearch" placeholder="Search by borrower or loan ID..." class="form-control mb-3">
            <table id="penaltyTable" class="table table-bordered">
                <thead><tr><th>Borrower</th><th>Loan ID</th><th>Principal (KSH)</th><th>Total Amount (KSH)</th><th>Total Paid (KSH)</th><th>Total Balance (KSH)</th><th>Loan Duration</th><th>Penalty (KSH)</th></tr></thead>
                <tbody>
                    <?php if ($result_penalty_details && $result_penalty_details->num_rows > 0): ?>
                        <?php while ($row = $result_penalty_details->fetch_assoc()): ?>
                            <tr data-release-date="<?php echo htmlspecialchars(date('Y-m-d', strtotime($row['loan_release_date'])), ENT_QUOTES, 'UTF-8'); ?>" data-officer="<?php echo htmlspecialchars($row['loan_officer_name'], ENT_QUOTES, 'UTF-8'); ?>" data-region="<?php echo htmlspecialchars($row['region_name'], ENT_QUOTES, 'UTF-8'); ?>" data-gross-penalty-value="<?php echo (float) $row['gross_penalty_amount']; ?>" data-penalty-value="<?php echo (float) $row['penalty_amount']; ?>">
                                <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                                <td><a href="repayment_details.php?loanId=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['id']); ?></a></td>
                                <td><?php echo number_format($row['principal'], 2); ?></td>
                                <td><?php echo number_format($row['total_amount'], 2); ?></td>
                                <td><?php echo number_format($row['total_paid'], 2); ?></td>
                                <td><?php echo number_format($row['total_balance'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['loan_duration']); ?></td>
                                <td class="penalty-value"><?php echo number_format($row['penalty_amount'], 2); ?></td>
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
        const regionTabs = document.querySelectorAll('.region-tab');
        const periodControls = document.getElementById('periodControls');
        const periodType = document.getElementById('periodType');
        const periodYear = document.getElementById('periodYear');
        const periodMonth = document.getElementById('periodMonth');
        const periodError = document.getElementById('periodError');
        let selectedOfficer = 'all';
        let selectedRegion = 'all';

        function rowMatchesPeriod(row) {
            if (periodType.value === 'general') return true;
            if (periodYear.value === '0') return false;
            const releaseDate = row.dataset.releaseDate.split('-');
            return Number(releaseDate[0]) === Number(periodYear.value) && (periodMonth.value === '0' || Number(releaseDate[1]) === Number(periodMonth.value));
        }

        function validatePeriod() {
            const specific = periodType.value === 'specific';
            periodControls.classList.toggle('specific', specific);
            const valid = !specific || periodYear.value !== '0';
            periodError.style.display = valid ? 'none' : 'block';
            return valid;
        }

        function updatePenaltyMetric() {
            let gross = 0;
            let balance = 0;
            document.querySelectorAll('#penaltyTable tbody tr[data-officer]').forEach(row => {
                if ((selectedOfficer === 'all' || row.dataset.officer === selectedOfficer) && (selectedRegion === 'all' || row.dataset.region === selectedRegion) && rowMatchesPeriod(row)) {
                    gross += Number(row.dataset.grossPenaltyValue || 0);
                    balance += Number(row.dataset.penaltyValue || 0);
                }
            });

            let used = 0;
            if (selectedOfficer === 'all') {
                Array.from(officerTabs).forEach(tab => {
                    if (tab.dataset.officer !== 'all') {
                        used += Number(tab.dataset.usedPenalty || 0);
                    }
                });
            } else {
                const selectedTab = Array.from(officerTabs).find(tab => tab.dataset.officer === selectedOfficer);
                used = Number(selectedTab ? (selectedTab.dataset.usedPenalty || 0) : 0);
            }

            const remainingBalance = Math.max(0, gross - used);
            document.getElementById('totalPenalty').textContent = 'KSH ' + gross.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('balancePenalty').textContent = 'KSH ' + remainingBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('usedPenalty').textContent = 'KSH ' + used.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function filterPenaltyRows() {
            const filter = penaltySearch.value.toLowerCase().replace(/[^a-z0-9]/g, '');
            const periodValid = validatePeriod();
            document.querySelectorAll('#penaltyTable tbody tr[data-officer]').forEach(row => {
                const rowText = row.textContent.toLowerCase().replace(/[^a-z0-9]/g, '');
                const officerMatches = selectedOfficer === 'all' || row.dataset.officer === selectedOfficer;
                const regionMatches = selectedRegion === 'all' || row.dataset.region === selectedRegion;
                row.style.display = periodValid && officerMatches && regionMatches && rowMatchesPeriod(row) && rowText.includes(filter) ? '' : 'none';
            });
        }

        periodType.addEventListener('change', function () {
            filterPenaltyRows();
            updatePenaltyMetric();
        });
        [periodYear, periodMonth].forEach(input => input.addEventListener('change', function () {
            filterPenaltyRows();
            updatePenaltyMetric();
        }));

        if (penaltySearch) {
            penaltySearch.addEventListener('input', function () {
                filterPenaltyRows();
                updatePenaltyMetric();
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
                updatePenaltyMetric();
                filterPenaltyRows();
            });
        });

        regionTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                selectedRegion = this.dataset.regionFilter;
                regionTabs.forEach(item => {
                    const active = item === this;
                    item.classList.toggle('active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                updatePenaltyMetric();
                filterPenaltyRows();
            });
        });
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
