<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$staffId = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT);
if ($staffId === null) {
    $staffId = filter_var($_GET['staff_id'] ?? null, FILTER_VALIDATE_INT);
}
if (!$staffId || $staffId < 1) {
    http_response_code(400);
    exit('A valid staff ID is required.');
}

$staffStmt = $conn->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
$staffStmt->bind_param('i', $staffId);
$staffStmt->execute();
$staff = $staffStmt->get_result()->fetch_assoc();
$staffStmt->close();

$advanceStmt = $conn->prepare(
    "SELECT a.id, a.loan_officer_name, a.amount, a.balance, a.start_date, a.monthly_deduction, a.duration_months, a.recorded_at,
            COALESCE(SUM(ar.amount), 0) AS total_paid
     FROM advances a
     LEFT JOIN advance_repayments ar ON ar.advance_id = a.id
     WHERE a.loan_officer_id = ?
     GROUP BY a.id, a.loan_officer_name, a.amount, a.balance, a.start_date, a.monthly_deduction, a.duration_months, a.recorded_at
     ORDER BY a.recorded_at DESC, a.id DESC"
);
$advanceStmt->bind_param('i', $staffId);
$advanceStmt->execute();
$advances = $advanceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$advanceStmt->close();

if (!$staff && $advances) {
    $staff = ['name' => $advances[0]['loan_officer_name'], 'email' => '', 'phone' => ''];
}
if (!$staff) {
    http_response_code(404);
    exit('Staff member or advance records not found.');
}

$totalAdvances = count($advances);
$totalAmount = array_sum(array_map('floatval', array_column($advances, 'amount')));
$totalPaid = array_sum(array_map('floatval', array_column($advances, 'total_paid')));
$totalBalance = array_sum(array_map('floatval', array_column($advances, 'balance')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advance History - <?php echo e($staff['name']); ?></title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --ink: #172331; --muted: #687582; --line: #dbe3e8; --paper: #fff; --canvas: #f2f5f6; --teal: #147d78; --gold: #c7973e; }
        body { background: var(--canvas); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; }
        .history-shell { max-width: 1220px; margin: 34px auto 60px; padding: 0 22px; }
        .history-header { background: var(--ink); border-top: 4px solid var(--gold); color: white; padding: 28px 32px; }
        .history-header h1 { font-family: Georgia, serif; font-size: 2.2rem; font-weight: normal; margin: 6px 0 8px; }
        .history-header p { color: #c3d0d6; margin: 0; }
        .eyebrow { color: #e5c579; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; }
        .header-actions { margin-top: 20px; }
        .header-actions a, .header-actions button { background: transparent; border: 1px solid #82939c; color: white; padding: 8px 13px; text-decoration: none; }
        .header-actions a:hover, .header-actions button:hover { background: var(--teal); border-color: var(--teal); }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin: 18px 0; }
        .summary-item, .panel { background: var(--paper); border: 1px solid var(--line); }
        .summary-item { border-top: 3px solid var(--teal); padding: 16px 18px; }
        .summary-item:nth-child(2) { border-top-color: var(--gold); }
        .summary-item:nth-child(3) { border-top-color: #5b7180; }
        .summary-item:nth-child(4) { border-top-color: #a95d55; }
        .summary-label { color: var(--muted); display: block; font-size: .72rem; letter-spacing: .1em; text-transform: uppercase; }
        .summary-value { display: block; font-family: Georgia, serif; font-size: 1.35rem; margin-top: 7px; }
        .panel { margin-bottom: 18px; }
        .panel-title { border-bottom: 1px solid var(--line); font-family: Georgia, serif; font-size: 1.2rem; margin: 0; padding: 17px 22px; }
        .staff-details { display: grid; grid-template-columns: repeat(3, 1fr); }
        .staff-detail { border-bottom: 1px solid #edf1f3; padding: 15px 22px; }
        .detail-label { color: var(--muted); display: block; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; }
        .detail-value { display: block; font-weight: bold; margin-top: 4px; }
        .table-responsive { overflow-x: auto; }
        .history-table { margin: 0; }
        .history-table th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .7rem; letter-spacing: .08em; padding: 13px 18px; text-transform: uppercase; white-space: nowrap; }
        .history-table td { border-color: #e6ecef; padding: 13px 18px; vertical-align: middle; }
        .amount { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .status { border-radius: 20px; display: inline-block; font-size: .7rem; font-weight: bold; padding: 5px 10px; text-transform: uppercase; }
        .status-open { background: #e6f3f1; color: #146b67; }
        .status-cleared { background: #edf0f2; color: #53636d; }
        .view-link { color: var(--teal); font-weight: bold; text-decoration: none; white-space: nowrap; }
        .view-link:hover { text-decoration: underline; }
        .empty { color: var(--muted); padding: 30px 22px; text-align: center; }
        @media (max-width: 760px) { .summary-grid, .staff-details { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .history-shell { padding: 0 12px; } .summary-grid, .staff-details { grid-template-columns: 1fr; } .history-header { padding: 24px; } .history-header h1 { font-size: 1.8rem; } }
        @media print { body { background: white; } .history-shell { margin: 0; max-width: none; } .header-actions, #header { display: none !important; } .history-header { background: white; border: 1px solid #999; color: black; } .history-header p, .eyebrow { color: #444; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="history-shell">
    <section class="history-header">
        <div class="eyebrow">Inua Premium Services | Staff advance history</div>
        <h1><?php echo e($staff['name']); ?></h1>
        <p>Complete advance history and repayment position for this staff member.</p>
        <div class="header-actions">
            <button type="button" onclick="window.print()">Print history</button>
            <a href="advance_statement.php?advance_id=<?php echo (int) ($advances[0]['id'] ?? 0); ?>">Back to statement</a>
        </div>
    </section>

    <section class="summary-grid" aria-label="Staff advance summary">
        <div class="summary-item"><span class="summary-label">Total advances</span><strong class="summary-value"><?php echo $totalAdvances; ?></strong></div>
        <div class="summary-item"><span class="summary-label">Total taken</span><strong class="summary-value"><?php echo number_format($totalAmount, 2); ?> KES</strong></div>
        <div class="summary-item"><span class="summary-label">Total repaid</span><strong class="summary-value"><?php echo number_format($totalPaid, 2); ?> KES</strong></div>
        <div class="summary-item"><span class="summary-label">Outstanding</span><strong class="summary-value"><?php echo number_format($totalBalance, 2); ?> KES</strong></div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Staff details</h2>
        <div class="staff-details">
            <div class="staff-detail"><span class="detail-label">Name</span><span class="detail-value"><?php echo e($staff['name']); ?></span></div>
            <div class="staff-detail"><span class="detail-label">Email</span><span class="detail-value"><?php echo e($staff['email'] ?: 'N/A'); ?></span></div>
            <div class="staff-detail"><span class="detail-label">Phone</span><span class="detail-value"><?php echo e($staff['phone'] ?: 'N/A'); ?></span></div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">All advances taken</h2>
        <?php if ($advances): ?>
            <div class="table-responsive"><table class="table history-table"><thead><tr><th>Advance ID</th><th>Amount</th><th>Total repaid</th><th>Balance</th><th>Start date</th><th>Monthly deduction</th><th>Status</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($advances as $advance): ?>
                    <?php $isOpen = (float) $advance['balance'] > 0; ?>
                    <tr>
                        <td><?php echo (int) $advance['id']; ?></td>
                        <td class="amount"><?php echo number_format((float) $advance['amount'], 2); ?> KES</td>
                        <td class="amount"><?php echo number_format((float) $advance['total_paid'], 2); ?> KES</td>
                        <td class="amount"><?php echo number_format((float) $advance['balance'], 2); ?> KES</td>
                        <td><?php echo e($advance['start_date']); ?></td>
                        <td class="amount"><?php echo number_format((float) $advance['monthly_deduction'], 2); ?> KES</td>
                        <td><span class="status <?php echo $isOpen ? 'status-open' : 'status-cleared'; ?>"><?php echo $isOpen ? 'Outstanding' : 'Cleared'; ?></span></td>
                        <td><a class="view-link" href="advance_statement.php?advance_id=<?php echo (int) $advance['id']; ?>">View statement</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><div class="empty">No advances have been recorded for this staff member.</div><?php endif; ?>
    </section>
</main>
</body>
</html>
