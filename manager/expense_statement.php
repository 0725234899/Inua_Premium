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

$expenseId = (int) ($_GET['id'] ?? 0);
if ($expenseId <= 0) {
    http_response_code(400);
    die('Invalid expense ID.');
}

$stmt = $conn->prepare("SELECT e.*, COALESCE(u.email, '') AS officer_email, COALESCE(a.area_name, 'Unassigned') AS region_name
                        FROM loan_officer_expenses e
                        LEFT JOIN users u ON e.loan_officer_id = u.id
                        LEFT JOIN areas a ON u.area = a.area_id
                        WHERE e.id = ? LIMIT 1");
$stmt->bind_param('i', $expenseId);
$stmt->execute();
$expense = $stmt->get_result()->fetch_assoc();
if (!$expense) {
    http_response_code(404);
    die('Expense record not found.');
}

$historyStmt = $conn->prepare("SELECT e.id, e.template_name, e.expense_type, e.amount, e.expense_date, e.loan_officer_name, e.payment_method, e.created_at, COALESCE(a.area_name, 'Unassigned') AS region_name
                               FROM loan_officer_expenses e
                               LEFT JOIN users u ON e.loan_officer_id = u.id
                               LEFT JOIN areas a ON u.area = a.area_id
                               WHERE e.loan_officer_id = ?
                               ORDER BY e.expense_date DESC, e.id DESC");
$historyStmt->bind_param('i', $expense['loan_officer_id']);
$historyStmt->execute();
$expenseHistory = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$historyStmt->close();

$totalExpenses = count($expenseHistory);
$totalExpenseAmount = array_sum(array_map('floatval', array_column($expenseHistory, 'amount')));
$expenseCategories = count(array_unique(array_column($expenseHistory, 'expense_type')));
$latestExpenseDate = $expenseHistory[0]['expense_date'] ?? $expense['expense_date'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Statement</title>
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
        .statement-shell { margin: 0 auto 60px; max-width: 1180px; padding: 0 22px; }
        .statement-heading { background: var(--ink); border-top: 4px solid var(--gold); color: white; padding: 28px 32px; position: relative; }
        .statement-heading h1 { font-family: Georgia, serif; font-size: 2.2rem; font-weight: normal; margin: 6px 0 8px; }
        .statement-heading p { color: #c3d0d6; margin: 0; }
        .eyebrow { color: #e5c579; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; }
        .statement-actions { bottom: 28px; position: absolute; right: 32px; }
        .statement-actions a, .statement-actions button { background: transparent; border: 1px solid #82939c; color: #fff; padding: 8px 13px; text-decoration: none; }
        .statement-actions a:hover, .statement-actions button:hover { background: var(--teal); border-color: var(--teal); }
        .sidebar-toggle-btn, .report-header .btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; padding: 8px 14px; }
        .sidebar-toggle-btn { margin-right: 4px; padding: 8px 12px; }
        .sidebar-toggle-btn:hover, .report-header .btn:hover { background: var(--teal); border-color: var(--teal); }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin: 18px 0; }
        .summary-item, .statement-panel { background: var(--paper); border: 1px solid var(--line); }
        .summary-item { border-top: 3px solid var(--teal); padding: 16px 18px; }
        .summary-item:nth-child(2) { border-top-color: var(--gold); }
        .summary-item:nth-child(3) { border-top-color: #5b7180; }
        .summary-item:nth-child(4) { border-top-color: #a95d55; }
        .summary-label { color: var(--muted); display: block; font-size: .72rem; letter-spacing: .1em; text-transform: uppercase; }
        .summary-value { display: block; font-family: Georgia, serif; font-size: 1.35rem; margin-top: 7px; }
        .statement-panel { margin-bottom: 18px; }
        .statement-panel h2 { border-bottom: 1px solid var(--line); font-family: Georgia, serif; font-size: 1.2rem; margin: 0; padding: 17px 22px; }
        .statement-details { display: grid; grid-template-columns: repeat(3, 1fr); }
        .statement-detail { border-bottom: 1px solid #edf1f3; padding: 15px 22px; }
        .detail-label { color: var(--muted); font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
        .detail-value { color: var(--ink); font-size: 1.05rem; font-weight: bold; }
        .amount { color: #a95d55; font-family: Georgia, serif; font-size: 1.5rem; }
        .table-responsive { overflow-x: auto; }
        .statement-table { margin: 0; }
        .statement-table th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .7rem; letter-spacing: .08em; padding: 13px 18px; text-transform: uppercase; white-space: nowrap; }
        .statement-table td { border-color: #e6ecef; padding: 13px 18px; vertical-align: middle; }
        .view-link { color: var(--teal); font-weight: bold; text-decoration: none; white-space: nowrap; }
        .view-link:hover { text-decoration: underline; }
        .empty { color: var(--muted); padding: 25px 22px; text-align: center; }
        .statement-footer { color: var(--muted); font-size: .78rem; }
        @media (max-width: 760px) { .summary-grid, .statement-details { grid-template-columns: repeat(2, 1fr); } .statement-actions { margin-top: 20px; position: static; } }
        @media (max-width: 768px) { .main { margin-left: 0; padding: 20px 12px 40px; } .statement-shell { padding: 0 12px; } .statement-heading { padding: 24px; } .statement-panel { padding: 0; } }
        @media (max-width: 480px) { .summary-grid, .statement-details { grid-template-columns: 1fr; } .statement-heading h1 { font-size: 1.8rem; } }
        @media print { body { background: #fff; } .statement-shell { margin: 0; max-width: none; } .statement-actions, .site-letterhead, .sidebar { display: none !important; } .statement-heading { background: #fff; border: 1px solid #999; color: #000; } .statement-heading p, .eyebrow { color: #444; } }
        @media (max-width: 520px) { .site-letterhead { padding: 0 12px; } .site-letterhead-brand { font-size: 18px; } .site-letterhead-brand img { height: 32px; } .site-letterhead-logout { font-size: 15px; } }
    </style>
</head>
<body>
<header class="site-letterhead"><div class="site-letterhead-brand"><img src="../assets/img/logo.png" alt="Inua Premium Logo">Inua Premium Services</div><a class="site-letterhead-logout" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></header>
<div class="sidebar" id="sidebarWrapper"><?php include '../includes/sidebar.php'; ?></div>
<div class="main" id="mainContent">
<main class="statement-shell">
    <section class="statement-heading">
        <div class="eyebrow">Inua Premium Services | Expense statement</div>
        <h1>Expense Statement #<?php echo (int) $expense['id']; ?></h1>
        <p>Detailed expense record for <?php echo htmlspecialchars($expense['loan_officer_name']); ?>.</p>
        <div class="statement-actions"><button type="button" onclick="window.print()">Print statement</button><a href="expense_more_information.php">Back to expenses</a></div>
    </section>
    <section class="summary-grid" aria-label="Expense summary">
        <div class="summary-item"><span class="summary-label">Total expenses</span><strong class="summary-value"><?php echo $totalExpenses; ?></strong></div>
        <div class="summary-item"><span class="summary-label">Total taken</span><strong class="summary-value"><?php echo number_format($totalExpenseAmount, 2); ?> KES</strong></div>
        <div class="summary-item"><span class="summary-label">Categories</span><strong class="summary-value"><?php echo $expenseCategories; ?></strong></div>
        <div class="summary-item"><span class="summary-label">Latest expense</span><strong class="summary-value"><?php echo htmlspecialchars($latestExpenseDate); ?></strong></div>
    </section>
    <section class="statement-panel">
        <h2>Staff expense details</h2>
        <div class="statement-details">
            <div class="statement-detail"><span class="detail-label">Person</span><span class="detail-value"><?php echo htmlspecialchars($expense['loan_officer_name']); ?></span></div>
            <div class="statement-detail"><span class="detail-label">Region</span><span class="detail-value"><?php echo htmlspecialchars($expense['region_name']); ?></span></div>
            <div class="statement-detail"><span class="detail-label">Template</span><span class="detail-value"><?php echo htmlspecialchars($expense['template_name']); ?></span></div>
            <div class="statement-detail"><span class="detail-label">Recorded at</span><span class="detail-value"><?php echo htmlspecialchars($expense['created_at']); ?></span></div>
            <div class="statement-detail"><span class="detail-label">Officer email</span><span class="detail-value"><?php echo htmlspecialchars($expense['officer_email'] ?: 'N/A'); ?></span></div>
            <div class="statement-detail"><span class="detail-label">Statement status</span><span class="detail-value">Recorded</span></div>
        </div>
    </section>
    <section class="statement-panel">
        <h2>All expenses taken</h2>
        <?php if ($expenseHistory): ?>
            <div class="table-responsive"><table class="table statement-table"><thead><tr><th>Expense ID</th><th>Category</th><th>Template</th><th>Amount</th><th>Expense date</th><th>Method</th><th>Region</th><th>Statement</th></tr></thead><tbody>
                <?php foreach ($expenseHistory as $historyExpense): ?>
                    <tr>
                        <td><?php echo (int) $historyExpense['id']; ?></td>
                        <td><?php echo htmlspecialchars($historyExpense['expense_type']); ?></td>
                        <td><?php echo htmlspecialchars($historyExpense['template_name']); ?></td>
                        <td class="amount"><?php echo number_format((float) $historyExpense['amount'], 2); ?> KES</td>
                        <td><?php echo htmlspecialchars($historyExpense['expense_date']); ?></td>
                        <td><?php echo htmlspecialchars($historyExpense['payment_method']); ?></td>
                        <td><?php echo htmlspecialchars($historyExpense['region_name']); ?></td>
                        <td><a class="view-link" href="expense_statement.php?id=<?php echo (int) $historyExpense['id']; ?>">View statement</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><div class="empty">No expenses have been recorded for this person.</div><?php endif; ?>
    </section>
    <div class="statement-footer">Statement prepared for Inua Premium Services. All amounts are presented in Kenyan Shillings (KES).</div>
</main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () { const toggle = document.getElementById('sidebarToggleMain'); const sidebar = document.getElementById('sidebarWrapper'); const main = document.getElementById('mainContent'); if (toggle) toggle.addEventListener('click', function () { sidebar.classList.toggle('collapsed'); main.classList.toggle('sidebar-collapsed'); }); });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
