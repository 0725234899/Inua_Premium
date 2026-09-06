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

$conn->query("CREATE TABLE IF NOT EXISTS loan_officer_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NULL,
    template_name VARCHAR(100) NOT NULL,
    expense_type VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    loan_officer_id INT NOT NULL,
    loan_officer_name VARCHAR(100) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'cash',
    recorded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_loan_officer (loan_officer_id),
    KEY idx_expense_date (expense_date)
)");

$expensesResult = $conn->query("SELECT e.id, e.template_name, e.expense_type, e.amount, e.expense_date, e.loan_officer_id, e.loan_officer_name, e.payment_method, e.created_at, COALESCE(a.area_name, 'Unassigned') AS region_name FROM loan_officer_expenses e LEFT JOIN users u ON e.loan_officer_id = u.id LEFT JOIN areas a ON u.area = a.area_id ORDER BY e.expense_date DESC, e.id DESC LIMIT 100");
$expenses = $expensesResult ? $expensesResult->fetch_all(MYSQLI_ASSOC) : [];
$expenseUsersResult = $conn->query("SELECT id, name, email, role_id FROM users WHERE role_id IN (1, 2) ORDER BY role_id, name");
$expenseUsers = $expenseUsersResult ? $expenseUsersResult->fetch_all(MYSQLI_ASSOC) : [];
$expenseCategories = [];
$expenseRegions = [];
$expenseOfficers = [];
$totalExpenses = 0.0;
foreach ($expenses as $expense) {
    $expenseCategories[$expense['expense_type']] = true;
    $expenseRegions[$expense['region_name']] = true;
    $expenseOfficers[$expense['loan_officer_id']] = $expense['loan_officer_name'];
    $totalExpenses += (float) $expense['amount'];
}
ksort($expenseCategories, SORT_NATURAL | SORT_FLAG_CASE);
ksort($expenseRegions, SORT_NATURAL | SORT_FLAG_CASE);
asort($expenseOfficers, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Details</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ink: #172331; --muted: #687582; --line: #dbe3e8; --paper: #ffffff; --canvas: #f2f5f6; --teal: #147d78; --gold: #c7973e; }
        body { background: var(--canvas); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; padding-top: 70px; }
        .site-letterhead { align-items: center; background: linear-gradient(90deg, #00c6ff, #0072ff); color: white; display: flex; height: 70px; justify-content: space-between; padding: 0 24px; position: fixed; top: 0; width: 100%; z-index: 1100; }
        .site-letterhead-brand { align-items: center; display: flex; font-size: 24px; font-weight: bold; }
        .site-letterhead-brand img { height: 40px; margin-right: 10px; width: auto; }
        .site-letterhead-logout { color: white; font-size: 18px; text-decoration: none; }
        .site-letterhead-logout:hover { color: #e8f8ff; }
        .sidebar { transition: all .3s ease; }
        .sidebar.collapsed { display: none; }
        .main { margin-left: 250px; padding: 34px 22px 60px; transition: margin-left .3s ease; }
        .main.sidebar-collapsed { margin-left: 0; }
        .report-shell { background: var(--paper); border: 1px solid var(--line); margin: 0 auto; max-width: 1280px; padding: 22px; }
        .report-header { align-items: center; background: var(--ink); border-top: 4px solid var(--gold); color: white; display: flex; justify-content: space-between; margin: -22px -22px 22px; padding: 26px 34px; }
        .report-header h1 { font-family: Georgia, serif; font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: normal; margin: 0; }
        .sidebar-toggle-btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; margin-right: 4px; padding: 8px 12px; }
        .sidebar-toggle-btn:hover { background: var(--teal); border-color: var(--teal); }
        .report-header .btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; }
        .report-header .btn:hover { background: var(--teal); border-color: var(--teal); }
        .report-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 0 0; }
        .report-tabs .nav-link { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); padding: 9px 14px; }
        .report-tabs .nav-link.active { background: var(--teal); border-color: var(--teal); color: white; }
        .report-tabs .nav-link:hover { background: #f7faf9; border-color: var(--teal); color: var(--teal); }
        .expense-metric { background: var(--paper); border: 1px solid var(--line); border-left: 4px solid var(--teal); margin: 18px 0; padding: 18px 20px; }
        .expense-metric-label { color: var(--muted); display: block; font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
        .expense-metric-value { color: var(--ink); display: block; font-family: Georgia, serif; font-size: 1.45rem; margin-top: 8px; }
        .period-filter { border-bottom: 1px solid var(--line); padding: 16px 0; }
        .period-controls { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; }
        .period-controls label { color: var(--muted); font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
        .period-controls select { border: 1px solid var(--line); border-radius: 0; color: var(--ink); padding: 8px 10px; }
        .period-controls select.period-value { display: none; }
        .period-controls.specific select.period-value { display: inline-block; }
        .period-error { color: #a95d55; display: none; font-size: .85rem; margin: 8px 0 0; }
        .table-container { margin-top: 18px; overflow-x: auto; }
        .table { margin: 0; min-width: 900px; }
        .table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; }
        .table td { border-color: #e6ecef; vertical-align: middle; }
        .table tbody tr:hover { background: #f7faf9; }
        .amount { color: #a95d55; font-weight: bold; }
        .btn-action { background: transparent; border: 1px solid var(--teal); border-radius: 0; color: var(--teal); }
        .btn-action:hover { background: var(--teal); color: white; }
        @media (max-width: 768px) { .main { margin-left: 0; padding: 20px 12px 40px; } .report-shell { padding: 16px 12px; } .report-header { align-items: flex-start; flex-direction: column; gap: 12px; margin: -16px -12px 16px; padding: 20px; } }
        @media (max-width: 520px) { .site-letterhead { padding: 0 12px; } .site-letterhead-brand { font-size: 18px; } .site-letterhead-brand img { height: 32px; } .site-letterhead-logout { font-size: 15px; } }
    </style>
</head>
<body>
<header class="site-letterhead"><div class="site-letterhead-brand"><img src="../assets/img/logo.png" alt="Inua Premium Logo">Inua Premium Services</div><a class="site-letterhead-logout" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></header>
<div class="sidebar" id="sidebarWrapper"><?php include '../includes/sidebar.php'; ?></div>
<main class="main" id="mainContent">
<div class="report-shell">
    <div class="report-header"><div class="d-flex align-items-center"><button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation"><i class="bi bi-list"></i></button><h1>Expense Details</h1></div><a href="add_expenses.php" class="btn">Back to Expenses</a></div>
    <h2 class="section-title">Recent Spendings</h2>
    <div class="expense-metric"><span class="expense-metric-label">Total Expenses</span><strong class="expense-metric-value" id="totalExpenses">KSH <?php echo number_format($totalExpenses, 2); ?></strong></div>
    <div class="period-filter"><div class="period-controls" id="periodControls"><label for="periodType">Period</label><select id="periodType"><option value="general">General</option><option value="specific">Specific Period</option></select><select id="periodYear" class="period-value"><option value="0">Select year</option><?php for ($year = (int) date('Y'); $year >= 2000; $year--): ?><option value="<?php echo $year; ?>"><?php echo $year; ?></option><?php endfor; ?></select><select id="periodMonth" class="period-value"><option value="0">All months</option><?php foreach (range(1, 12) as $month): ?><option value="<?php echo $month; ?>"><?php echo date('F', mktime(0, 0, 0, $month, 1)); ?></option><?php endforeach; ?></select></div><p class="period-error" id="periodError">Select a year for the specific period.</p></div>
    <ul class="nav report-tabs" id="regionTabs"><li class="nav-item"><button type="button" class="nav-link active" data-region-filter="all">All Regions</button></li><?php foreach (array_keys($expenseRegions) as $region): ?><li class="nav-item"><button type="button" class="nav-link" data-region-filter="<?php echo htmlspecialchars($region, ENT_QUOTES); ?>"><?php echo htmlspecialchars($region); ?></button></li><?php endforeach; ?></ul>
    <ul class="nav report-tabs" id="officerTabs"><li class="nav-item"><button type="button" class="nav-link active" data-officer-filter="all">All staffs</button></li><?php foreach ($expenseOfficers as $officerId => $officerName): ?><li class="nav-item"><button type="button" class="nav-link" data-officer-filter="<?php echo (int) $officerId; ?>"><?php echo htmlspecialchars($officerName); ?></button></li><?php endforeach; ?></ul>
    <ul class="nav report-tabs" id="expenseTabs">
        <li class="nav-item"><button type="button" class="nav-link active" data-expense-filter="all">All Spendings</button></li>
        <?php foreach (array_keys($expenseCategories) as $category): ?><li class="nav-item"><button type="button" class="nav-link" data-expense-filter="<?php echo htmlspecialchars($category, ENT_QUOTES); ?>"><?php echo htmlspecialchars($category); ?></button></li><?php endforeach; ?>
    </ul>
    <div class="table-container">
        <?php if (empty($expenses)): ?>
            <p class="text-muted">No spending records yet.</p>
        <?php else: ?>
            <table class="table table-bordered" id="expensesTable"><thead><tr><th>Person</th><th>Template</th><th>Category</th><th>Amount</th><th>Date Booked</th><th>Method</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($expenses as $expense): ?><tr data-expense-category="<?php echo htmlspecialchars($expense['expense_type'], ENT_QUOTES); ?>" data-region="<?php echo htmlspecialchars($expense['region_name'], ENT_QUOTES); ?>" data-officer="<?php echo (int) $expense['loan_officer_id']; ?>" data-date="<?php echo htmlspecialchars($expense['expense_date'], ENT_QUOTES); ?>" data-amount="<?php echo (float) $expense['amount']; ?>"><td><?php echo htmlspecialchars($expense['loan_officer_name'], ENT_QUOTES); ?></td><td><?php echo htmlspecialchars($expense['template_name'], ENT_QUOTES); ?></td><td><?php echo htmlspecialchars($expense['expense_type'], ENT_QUOTES); ?></td><td class="amount"><?php echo number_format((float) $expense['amount'], 2); ?></td><td><?php echo htmlspecialchars($expense['expense_date'] ?: '-', ENT_QUOTES); ?></td><td><?php echo htmlspecialchars($expense['payment_method'], ENT_QUOTES); ?></td><td><a href="expense_statement.php?id=<?php echo (int) $expense['id']; ?>" class="btn btn-sm btn-action">Statement</a> <button type="button" class="btn btn-sm btn-action edit-expense-btn" data-id="<?php echo (int) $expense['id']; ?>" data-type="<?php echo htmlspecialchars($expense['expense_type'], ENT_QUOTES); ?>" data-amount="<?php echo htmlspecialchars((string) $expense['amount'], ENT_QUOTES); ?>" data-date="<?php echo htmlspecialchars($expense['expense_date'], ENT_QUOTES); ?>" data-method="<?php echo htmlspecialchars($expense['payment_method'], ENT_QUOTES); ?>" data-officer="<?php echo htmlspecialchars((string) ($expense['loan_officer_id'] ?? ''), ENT_QUOTES); ?>">Edit</button> <a href="add_expenses.php?delete=<?php echo (int) $expense['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this expense entry?');">Clear</a></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div>
</div>
</main>
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="add_expenses.php" class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="editExpenseModalLabel">Edit Expense</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="expense_id" id="editExpenseId">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="editExpenseType">Expense Category</label><select name="expense_type" id="editExpenseType" class="form-select" required><option value="Collection">Collection</option><option value="Marketing">Marketing</option><option value="Appraisal">Appraisal</option><option value="Office Spendings">Office Spendings</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="editExpenseAmount">Amount</label><input type="number" step="0.01" min="0.01" name="amount" id="editExpenseAmount" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label" for="editExpenseDate">Expense Date</label><input type="date" name="expense_date" id="editExpenseDate" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label" for="editPaymentMethod">Payment Method</label><select name="payment_method" id="editPaymentMethod" class="form-select"><option value="mpesa">Mpesa</option><option value="cash">Cash</option></select></div>
                    <div class="col-12"><label class="form-label" for="editLoanOfficer">Loan Officer</label><select name="loan_officer_id" id="editLoanOfficer" class="form-select" required><option value="">Select person</option><?php foreach ($expenseUsers as $user): ?><option value="<?php echo (int) $user['id']; ?>"><?php echo htmlspecialchars(($user['role_id'] == 1 ? 'Manager' : 'Loan Officer') . ' - ' . $user['name'] . ' (' . $user['email'] . ')', ENT_QUOTES); ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Expense</button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('sidebarToggleMain');
    const sidebar = document.getElementById('sidebarWrapper');
    const main = document.getElementById('mainContent');
    if (toggle) toggle.addEventListener('click', function () { sidebar.classList.toggle('collapsed'); main.classList.toggle('sidebar-collapsed'); });
    const periodType = document.getElementById('periodType');
    const periodYear = document.getElementById('periodYear');
    const periodMonth = document.getElementById('periodMonth');
    const periodControls = document.getElementById('periodControls');
    const periodError = document.getElementById('periodError');
    let selectedCategory = 'all';
    let selectedRegion = 'all';
    let selectedOfficer = 'all';
    function rowMatchesPeriod(row) { if (periodType.value === 'general') return true; if (periodYear.value === '0') return false; const date = row.dataset.date.split('-'); return Number(date[0]) === Number(periodYear.value) && (periodMonth.value === '0' || Number(date[1]) === Number(periodMonth.value)); }
    function applyExpenseFilters() {
        const specific = periodType.value === 'specific';
        periodControls.classList.toggle('specific', specific);
        const periodValid = !specific || periodYear.value !== '0';
        periodError.style.display = periodValid ? 'none' : 'block';
        let total = 0;
        document.querySelectorAll('#expensesTable tbody tr[data-expense-category]').forEach(row => {
            const matches = periodValid && rowMatchesPeriod(row) && (selectedCategory === 'all' || row.dataset.expenseCategory === selectedCategory) && (selectedRegion === 'all' || row.dataset.region === selectedRegion) && (selectedOfficer === 'all' || row.dataset.officer === selectedOfficer);
            row.style.display = matches ? '' : 'none';
            if (matches) total += Number(row.dataset.amount || 0);
        });
        document.getElementById('totalExpenses').textContent = 'KSH ' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function bindTabs(selector, key, setter) { document.querySelectorAll(selector).forEach(tab => tab.addEventListener('click', function () { setter(this.dataset[key]); document.querySelectorAll(selector).forEach(item => item.classList.toggle('active', item === this)); applyExpenseFilters(); })); }
    bindTabs('[data-expense-filter]', 'expenseFilter', value => selectedCategory = value);
    bindTabs('[data-region-filter]', 'regionFilter', value => selectedRegion = value);
    bindTabs('[data-officer-filter]', 'officerFilter', value => selectedOfficer = value);
    [periodType, periodYear, periodMonth].forEach(input => input.addEventListener('change', applyExpenseFilters));
    applyExpenseFilters();
    document.querySelectorAll('.edit-expense-btn').forEach(function (button) { button.addEventListener('click', function () { document.getElementById('editExpenseId').value = this.dataset.id; document.getElementById('editExpenseType').value = this.dataset.type; document.getElementById('editExpenseAmount').value = this.dataset.amount; document.getElementById('editExpenseDate').value = this.dataset.date; document.getElementById('editPaymentMethod').value = this.dataset.method; document.getElementById('editLoanOfficer').value = this.dataset.officer; bootstrap.Modal.getOrCreateInstance(document.getElementById('editExpenseModal')).show(); }); });
});
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
