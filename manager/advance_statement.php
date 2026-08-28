<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$advanceId = filter_input(INPUT_GET, 'advance_id', FILTER_VALIDATE_INT);
if ($advanceId === null) {
    $advanceId = filter_var($_GET['advance_id'] ?? null, FILTER_VALIDATE_INT);
}
if (!$advanceId && isset($_POST['advance_id'])) {
    $advanceId = filter_var($_POST['advance_id'], FILTER_VALIDATE_INT);
}
if (!$advanceId || $advanceId < 1) {
    http_response_code(400);
    exit('A valid advance ID is required.');
}

$advanceStmt = $conn->prepare("SELECT * FROM advances WHERE id = ? LIMIT 1");
$advanceStmt->bind_param('i', $advanceId);
$advanceStmt->execute();
$advance = $advanceStmt->get_result()->fetch_assoc();
$advanceStmt->close();

if (!$advance) {
    http_response_code(404);
    exit('Advance record not found.');
}

if (isset($_POST['clear_repayment'])) {
    $repaymentId = (int) ($_POST['repayment_id'] ?? 0);
    $clearStmt = $conn->prepare("SELECT amount FROM advance_repayments WHERE id = ? AND advance_id = ? LIMIT 1");
    $clearStmt->bind_param('ii', $repaymentId, $advanceId);
    $clearStmt->execute();
    $repaymentToClear = $clearStmt->get_result()->fetch_assoc();
    $clearStmt->close();

    if ($repaymentToClear) {
        $conn->begin_transaction();
        try {
            $deleteStmt = $conn->prepare("DELETE FROM advance_repayments WHERE id = ? AND advance_id = ?");
            $deleteStmt->bind_param('ii', $repaymentId, $advanceId);
            $deleteStmt->execute();
            $deleteStmt->close();

            $restoredAmount = (float) $repaymentToClear['amount'];
            $balanceStmt = $conn->prepare("UPDATE advances SET balance = LEAST(amount, balance + ?) WHERE id = ?");
            $balanceStmt->bind_param('di', $restoredAmount, $advanceId);
            $balanceStmt->execute();
            $balanceStmt->close();
            $conn->commit();
        } catch (Throwable $exception) {
            $conn->rollback();
        }
    }

    header('Location: advance_statement.php?advance_id=' . $advanceId . '&cleared=1');
    exit;
}

$repaymentStmt = $conn->prepare("SELECT id, amount, payment_date, recorded_at FROM advance_repayments WHERE advance_id = ? ORDER BY payment_date DESC, id DESC");
$repaymentStmt->bind_param('i', $advanceId);
$repaymentStmt->execute();
$repayments = $repaymentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$repaymentStmt->close();

$scheduleStmt = $conn->prepare("SELECT id, due_amount, due_date FROM advance_repayment_schedule WHERE advance_id = ? ORDER BY due_date ASC, id ASC");
$scheduleStmt->bind_param('i', $advanceId);
$scheduleStmt->execute();
$schedule = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduleStmt->close();

$totalPaid = array_sum(array_map('floatval', array_column($repayments, 'amount')));
$advanceAmount = (float) $advance['amount'];
$balance = max(0, (float) $advance['balance']);
$status = $balance > 0 ? 'Outstanding' : 'Cleared';
$paymentCount = count($repayments);
$completionRate = $advanceAmount > 0 ? min(100, ($totalPaid / $advanceAmount) * 100) : 0;
$reportDate = date('d F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advance Statement #<?php echo (int) $advance['id']; ?></title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --ink: #172331; --muted: #687582; --line: #dbe3e8; --paper: #fff; --canvas: #f2f5f6; --teal: #147d78; --gold: #c7973e; }
        body { background: var(--canvas); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; }
        .statement-shell { max-width: 1180px; margin: 34px auto 60px; padding: 0 22px; }
        .statement-heading { background: var(--ink); border-top: 4px solid var(--gold); color: #fff; padding: 28px 32px; position: relative; }
        .statement-heading h1 { font-family: Georgia, serif; font-size: 2.2rem; font-weight: normal; margin: 6px 0 8px; }
        .statement-heading p { color: #c3d0d6; margin: 0; }
        .eyebrow { color: #e5c579; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; }
        .actions { position: absolute; right: 32px; bottom: 28px; }
        .actions a, .actions button { background: transparent; border: 1px solid #82939c; color: #fff; padding: 8px 13px; text-decoration: none; }
        .actions a:hover, .actions button:hover { background: var(--teal); border-color: var(--teal); }
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
        .details { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; }
        .detail { border-bottom: 1px solid #edf1f3; padding: 15px 22px; }
        .detail-label { color: var(--muted); display: block; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; }
        .detail-value { display: block; font-weight: bold; margin-top: 4px; }
        .table-responsive { overflow-x: auto; }
        .statement-table { margin: 0; }
        .statement-table th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .7rem; letter-spacing: .08em; padding: 13px 18px; text-transform: uppercase; white-space: nowrap; }
        .statement-table td { border-color: #e6ecef; padding: 13px 18px; vertical-align: middle; }
        .amount { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .badge-status { border-radius: 20px; display: inline-block; font-size: .7rem; font-weight: bold; padding: 5px 10px; text-transform: uppercase; }
        .badge-open { background: #e6f3f1; color: #146b67; }
        .badge-cleared { background: #edf0f2; color: #53636d; }
        .empty { color: var(--muted); padding: 25px 22px; }
        .footer { color: var(--muted); font-size: .78rem; }
        @media (max-width: 760px) { .summary-grid, .details { grid-template-columns: repeat(2, 1fr); } .actions { margin-top: 20px; position: static; } }
        @media (max-width: 480px) { .statement-shell { padding: 0 12px; } .summary-grid, .details { grid-template-columns: 1fr; } .statement-heading { padding: 24px; } .statement-heading h1 { font-size: 1.8rem; } }
        @media print { body { background: #fff; } .statement-shell { margin: 0; max-width: none; } .actions, #header { display: none !important; } .statement-heading { background: #fff; border: 1px solid #999; color: #000; } .statement-heading p, .eyebrow { color: #444; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="statement-shell">
    <section class="statement-heading">
        <div class="eyebrow">Inua Premium Services | Staff advance statement</div>
        <h1>Advance Statement #<?php echo (int) $advance['id']; ?></h1>
        <p>Detailed repayment activity for <?php echo e($advance['loan_officer_name']); ?>.</p>
        <div class="actions">
            <button type="button" onclick="window.print()">Print statement</button>
            <a href="advance_more_information.php?staff_id=<?php echo (int) $advance['loan_officer_id']; ?>">More information</a>
            <a href="advance_report.php">Back to report</a>
        </div>
    </section>

    <section class="summary-grid" aria-label="Advance summary">
        <div class="summary-item"><span class="summary-label">Advance amount</span><strong class="summary-value"><?php echo number_format($advanceAmount, 2); ?> KES</strong></div>
        <div class="summary-item"><span class="summary-label">Total repaid</span><strong class="summary-value"><?php echo number_format($totalPaid, 2); ?> KES</strong></div>
        <div class="summary-item"><span class="summary-label">Current balance</span><strong class="summary-value"><?php echo number_format($balance, 2); ?> KES</strong></div>
        <div class="summary-item"><span class="summary-label">Payments recorded</span><strong class="summary-value"><?php echo $paymentCount; ?></strong></div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Advance details</h2>
        <div class="details">
            <div class="detail"><span class="detail-label">Staff member</span><span class="detail-value"><?php echo e($advance['loan_officer_name']); ?></span></div>
            <div class="detail"><span class="detail-label">release date</span><span class="detail-value"><?php echo e($advance['start_date']); ?></span></div>
            <div class="detail"><span class="detail-label">Monthly deduction</span><span class="detail-value"><?php echo number_format((float) $advance['monthly_deduction'], 2); ?> KES</span></div>
            <div class="detail"><span class="detail-label">Duration</span><span class="detail-value"><?php echo (int) $advance['duration_months']; ?> months</span></div>
            <div class="detail"><span class="detail-label">Recorded date</span><span class="detail-value"><?php echo e($advance['recorded_at']); ?></span></div>
            <div class="detail"><span class="detail-label">Status</span><span class="detail-value"><span class="badge-status <?php echo $balance > 0 ? 'badge-open' : 'badge-cleared'; ?>"><?php echo e($status); ?></span></span></div>
            <div class="detail"><span class="detail-label">Repayment progress</span><span class="detail-value"><?php echo number_format($completionRate, 1); ?>%</span></div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Repayment records</h2>
        <?php if ($repayments): ?>
            <div class="table-responsive"><table class="table statement-table"><thead><tr><th>#</th><th>Payment date</th><th>Amount paid</th><th>Recorded at</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($repayments as $repayment): ?>
                    <tr>
                        <td><?php echo (int) $repayment['id']; ?></td>
                        <td><?php echo e($repayment['payment_date']); ?></td>
                        <td class="amount"><?php echo number_format((float) $repayment['amount'], 2); ?> KES</td>
                        <td><?php echo e($repayment['recorded_at']); ?></td>
                        <td>
                            <form method="POST" action="advance_statement.php?advance_id=<?php echo (int) $advanceId; ?>" onsubmit="return confirm('Clear this repayment record and restore the amount to the advance balance?');">
                                <input type="hidden" name="advance_id" value="<?php echo (int) $advanceId; ?>">
                                <input type="hidden" name="repayment_id" value="<?php echo (int) $repayment['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" name="clear_repayment">Clear</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><div class="empty">No repayment records have been recorded for this advance.</div><?php endif; ?>
    </section>

    <section class="panel">
        <h2 class="panel-title">Repayment schedule</h2>
        <?php if ($schedule): ?>
            <div class="table-responsive"><table class="table statement-table"><thead><tr><th>#</th><th>Amount due</th><th>Repayment date</th></tr></thead><tbody>
                <?php foreach ($schedule as $installment): ?>
                    <tr>
                        <td><?php echo (int) $installment['id']; ?></td>
                        <td class="amount"><?php echo number_format((float) $installment['due_amount'], 2); ?> KES</td>
                        <td><?php echo e($installment['due_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><div class="empty">No repayment schedule has been generated for this advance.</div><?php endif; ?>
    </section>

    <div class="footer">Statement prepared <?php echo e($reportDate); ?>. All amounts are presented in Kenyan Shillings (KES).</div>
</main>
</body>
</html>
