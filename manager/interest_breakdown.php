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
    COALESCE(u.name, 'Unassigned') AS loan_officer_name,
    COALESCE(a.area_name, 'Unassigned') AS region_name,
    l.principal,
    l.loan_duration,
    l.loan_release_date,
    l.total_amount,
    (l.total_amount - l.principal) AS interest,
    COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0) + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0) AS total_paid,
    GREATEST(0, l.total_amount - COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0) - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0)) AS total_balance,
    LEAST(
        GREATEST(0, l.total_amount - l.principal),
        GREATEST(0, COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0) + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0) - l.principal)
    ) AS cleared_interest,
    GREATEST(0, (
        (l.total_amount - l.principal) - LEAST(
            GREATEST(0, l.total_amount - l.principal),
            GREATEST(0, COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0) + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0) - l.principal)
        )
    )) AS pending_interest
FROM loan_applications l
INNER JOIN borrowers b ON l.borrower = b.id
LEFT JOIN users u ON b.loan_officer = u.email
LEFT JOIN areas a ON u.area = a.area_id
WHERE l.loan_status IN ('approved', 'rolled_over')
   OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%'
ORDER BY
    CASE
        WHEN LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%' THEN 0
        WHEN LEAST(
            GREATEST(0, l.total_amount - l.principal),
            GREATEST(0, COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0) + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0) - l.principal)
        ) >= (l.total_amount - l.principal) THEN 1
        ELSE 2
    END,
    l.loan_release_date DESC";
$result_interest_details = $conn->query($sql_interest_details);
$loanOfficers = [];
$loanOfficerRegions = [];
$regions = [];
$totalClearedInterest = 0;
$totalPendingInterest = 0;
if ($result_interest_details) {
    while ($interestRow = $result_interest_details->fetch_assoc()) {
        $loanOfficers[$interestRow['loan_officer_name']] = true;
        $loanOfficerRegions[$interestRow['loan_officer_name']] = $interestRow['region_name'];
        $regions[$interestRow['region_name']] = true;
        $totalClearedInterest += (float) $interestRow['cleared_interest'];
        $totalPendingInterest += (float) $interestRow['pending_interest'];
    }
    $result_interest_details->data_seek(0);
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
        .interest-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin: 18px auto 0; max-width: 1280px; }
        .interest-metric { background: var(--paper); border: 1px solid var(--line); border-left: 4px solid var(--teal); padding: 18px 20px; }
        .interest-metric.total { border-left-color: #5b7180; }
        .interest-metric.pending { border-left-color: var(--gold); }
        .interest-metric-label { color: var(--muted); display: block; font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
        .interest-metric-value { color: var(--ink); display: block; font-family: Georgia, serif; font-size: 1.45rem; margin-top: 8px; }
        .section-title { background: var(--ink); border-top: 4px solid var(--gold); color: white; font-family: Georgia, serif; font-size: 1.25rem; font-weight: normal; margin: 0; padding: 18px 22px; }
        .interest-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 22px 0; }
        .interest-tab { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); cursor: pointer; padding: 9px 14px; }
        .interest-tab:hover, .interest-tab.active { background: var(--teal); border-color: var(--teal); color: white; }
        .period-filter { border-bottom: 1px solid var(--line); padding: 16px 22px; }
        .period-controls { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; }
        .period-controls label { color: var(--muted); font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
        .period-controls select { border: 1px solid var(--line); border-radius: 0; color: var(--ink); padding: 8px 10px; }
        .period-controls select.period-value { display: none; }
        .period-controls.specific select.period-value { display: inline-block; }
        .period-error { color: #a95d55; display: none; font-size: .85rem; margin: 8px 0 0; }
        .officer-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 22px 0; }
        .officer-tab { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); cursor: pointer; padding: 9px 14px; }
        .officer-tab:hover, .officer-tab.active { background: var(--gold); border-color: var(--gold); color: var(--ink); }
        .region-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 22px 0; }
        .region-tab { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); cursor: pointer; padding: 9px 14px; }
        .region-tab:hover, .region-tab.active { background: var(--ink); border-color: var(--ink); color: white; }
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
        @media (max-width: 850px) { .interest-metrics { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) { .main > .header { padding: 20px; } .main > .header h1 { font-size: 1.8rem; } .table-container { padding-left: 12px; padding-right: 12px; } .interest-metrics { grid-template-columns: 1fr; } }
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

    <div class="interest-metrics" aria-label="Interest summary">
        <div class="interest-metric total"><span class="interest-metric-label">Total Interest</span><strong class="interest-metric-value" id="totalInterest">KSH <?php echo number_format($totalClearedInterest + $totalPendingInterest, 2); ?></strong></div>
        <div class="interest-metric"><span class="interest-metric-label">Cleared Interest</span><strong class="interest-metric-value" id="totalClearedInterest">KSH <?php echo number_format($totalClearedInterest, 2); ?></strong></div>
        <div class="interest-metric pending"><span class="interest-metric-label">Pending Interest</span><strong class="interest-metric-value" id="totalPendingInterest">KSH <?php echo number_format($totalPendingInterest, 2); ?></strong></div>
    </div>

    <section class="report-panel">
        <h2 id="interestTable" class="section-title">Interest Breakdown (Clients)</h2>
        <div class="period-filter">
            <div class="period-controls" id="periodControls">
                <label for="periodType">Period</label>
                <select id="periodType" aria-label="Select interest period">
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
        <div class="region-tabs" id="regionTabs" role="tablist" aria-label="Filter interest by region">
            <button type="button" class="region-tab active" data-region-filter="all" role="tab" aria-selected="true">All Regions</button>
            <?php foreach ($regions as $region): ?>
                <button type="button" class="region-tab" data-region-filter="<?php echo htmlspecialchars($region, ENT_QUOTES, 'UTF-8'); ?>" role="tab" aria-selected="false"><?php echo htmlspecialchars($region); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="interest-tabs" id="interestTabs" role="tablist" aria-label="Filter interest by payment status">
            <button type="button" class="interest-tab active" data-interest-filter="all" role="tab" aria-selected="true">All Interest</button>
            <button type="button" class="interest-tab" data-interest-filter="cleared" role="tab" aria-selected="false">Cleared Interest</button>
            <button type="button" class="interest-tab" data-interest-filter="pending" role="tab" aria-selected="false">Pending Interest</button>
        </div>
        <div class="officer-tabs" id="officerTabs" role="tablist" aria-label="Filter interest by loan officer">
            <button type="button" class="officer-tab active" data-officer-filter="all" role="tab" aria-selected="true">All Officers</button>
            <?php foreach ($loanOfficers as $loanOfficer): ?>
                <button type="button" class="officer-tab" data-officer-region="<?php echo htmlspecialchars($loanOfficerRegions[$loanOfficer] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?>" data-officer-filter="<?php echo htmlspecialchars($loanOfficer, ENT_QUOTES, 'UTF-8'); ?>" role="tab" aria-selected="false"><?php echo htmlspecialchars($loanOfficer); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="table-container">
            <input type="text" id="interestSearch" placeholder="Search by borrower or phone..." class="form-control mb-3">
            <table id="interestBreakdownTable" class="table table-bordered">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Loan ID</th>
                        <th>Principal (KSH)</th>
                        <th>Total Amount (KSH)</th>
                        <th>Total Paid (KSH)</th>
                        <th>Total Balance (KSH)</th>
                        <th>Interest (KSH)</th>
                        <th>Cleared Interest (KSH)</th>
                        <th>Pending Interest (KSH)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_interest_details && $result_interest_details->num_rows > 0): ?>
                        <?php while ($row = $result_interest_details->fetch_assoc()): ?>
                            <tr data-release-date="<?php echo htmlspecialchars(date('Y-m-d', strtotime($row['loan_release_date'])), ENT_QUOTES, 'UTF-8'); ?>" data-officer="<?php echo htmlspecialchars($row['loan_officer_name'], ENT_QUOTES, 'UTF-8'); ?>" data-region="<?php echo htmlspecialchars($row['region_name'], ENT_QUOTES, 'UTF-8'); ?>" data-cleared-interest="<?php echo (float) $row['cleared_interest'] > 0 ? 'true' : 'false'; ?>" data-pending-interest="<?php echo (float) $row['pending_interest'] > 0 ? 'true' : 'false'; ?>" data-cleared-value="<?php echo (float) $row['cleared_interest']; ?>" data-pending-value="<?php echo (float) $row['pending_interest']; ?>">
                                <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                                <td><a href="repayment_details.php?loanId=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['id']); ?></a></td>
                                <td><?php echo number_format($row['principal'], 2); ?></td>
                                <td><?php echo number_format($row['total_amount'], 2); ?></td>
                                <td><?php echo number_format($row['total_paid'], 2); ?></td>
                                <td><?php echo number_format($row['total_balance'], 2); ?></td>
                                <td><?php echo number_format($row['interest'], 2); ?></td>
                                <td><?php echo number_format($row['cleared_interest'], 2); ?></td>
                                <td><?php echo number_format($row['pending_interest'], 2); ?></td>
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
        const interestTabs = document.querySelectorAll('.interest-tab');
        const officerTabs = document.querySelectorAll('.officer-tab');
        const regionTabs = document.querySelectorAll('.region-tab');
        const periodControls = document.getElementById('periodControls');
        const periodType = document.getElementById('periodType');
        const periodYear = document.getElementById('periodYear');
        const periodMonth = document.getElementById('periodMonth');
        const periodError = document.getElementById('periodError');
        let selectedInterestFilter = 'all';
        let selectedOfficerFilter = 'all';
        let selectedRegionFilter = 'all';

        function rowMatchesPeriod(row) {
            if (periodType.value === 'general') return true;
            if (periodYear.value === '0') return false;
            const releaseDate = row.dataset.releaseDate.split('-');
            return Number(releaseDate[0]) === Number(periodYear.value) && (periodMonth.value === '0' || Number(releaseDate[1]) === Number(periodMonth.value));
        }

        function updateInterestMetrics() {
            let clearedTotal = 0;
            let pendingTotal = 0;
            document.querySelectorAll('#interestBreakdownTable tbody tr[data-officer]').forEach(row => {
                if ((selectedOfficerFilter === 'all' || row.dataset.officer === selectedOfficerFilter) && (selectedRegionFilter === 'all' || row.dataset.region === selectedRegionFilter) && rowMatchesPeriod(row)) {
                    clearedTotal += Number(row.dataset.clearedValue || 0);
                    pendingTotal += Number(row.dataset.pendingValue || 0);
                }
            });
            document.getElementById('totalClearedInterest').textContent = 'KSH ' + clearedTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('totalPendingInterest').textContent = 'KSH ' + pendingTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('totalInterest').textContent = 'KSH ' + (clearedTotal + pendingTotal).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function validatePeriod() {
            const specific = periodType.value === 'specific';
            periodControls.classList.toggle('specific', specific);
            const valid = !specific || periodYear.value !== '0';
            periodError.style.display = valid ? 'none' : 'block';
            return valid;
        }

        function filterInterestRows() {
            const filter = interestSearch.value.toLowerCase().replace(/[^a-z0-9]/g, '');
            const periodValid = validatePeriod();
            document.querySelectorAll('#interestBreakdownTable tbody tr[data-officer]').forEach(row => {
                const cells = Array.from(row.cells).map(cell => cell.textContent.toLowerCase().replace(/[^a-z0-9]/g, ''));
                const textMatches = cells.some(text => text.includes(filter));
                const interestMatches = selectedInterestFilter === 'all' || row.dataset[selectedInterestFilter + 'Interest'] === 'true';
                const officerMatches = selectedOfficerFilter === 'all' || row.dataset.officer === selectedOfficerFilter;
                const regionMatches = selectedRegionFilter === 'all' || row.dataset.region === selectedRegionFilter;
                row.style.display = periodValid && textMatches && interestMatches && officerMatches && regionMatches && rowMatchesPeriod(row) ? '' : 'none';
            });
        }

        function filterOfficerTabs() {
            officerTabs.forEach(tab => {
                const isAllOfficers = tab.dataset.officerFilter === 'all';
                const matchesRegion = isAllOfficers || selectedRegionFilter === 'all' || tab.dataset.officerRegion === selectedRegionFilter;
                tab.hidden = !matchesRegion;
                tab.disabled = !matchesRegion;
            });

            const selectedOfficerTab = Array.from(officerTabs).find(tab => tab.dataset.officerFilter === selectedOfficerFilter);
            if (selectedOfficerFilter !== 'all' && (!selectedOfficerTab || selectedOfficerTab.disabled)) {
                selectedOfficerFilter = 'all';
                officerTabs.forEach(tab => {
                    const active = tab.dataset.officerFilter === 'all';
                    tab.classList.toggle('active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });
            }
        }

        periodType.addEventListener('change', function () {
            filterInterestRows();
            updateInterestMetrics();
        });
        [periodYear, periodMonth].forEach(input => input.addEventListener('change', function () {
            filterInterestRows();
            updateInterestMetrics();
        }));

        if (interestSearch) {
            interestSearch.addEventListener('input', function () {
                filterInterestRows();
            });
        }

        interestTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                selectedInterestFilter = this.dataset.interestFilter;
                interestTabs.forEach(item => {
                    const active = item === this;
                    item.classList.toggle('active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                filterInterestRows();
            });
        });

        officerTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                selectedOfficerFilter = this.dataset.officerFilter;
                officerTabs.forEach(item => {
                    const active = item === this;
                    item.classList.toggle('active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                updateInterestMetrics();
                filterInterestRows();
            });
        });

        regionTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                selectedRegionFilter = this.dataset.regionFilter;
                regionTabs.forEach(item => {
                    const active = item === this;
                    item.classList.toggle('active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                filterOfficerTabs();
                updateInterestMetrics();
                filterInterestRows();
            });
        });

        filterOfficerTabs();
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
