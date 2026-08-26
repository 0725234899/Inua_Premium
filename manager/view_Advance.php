<?php
session_start();
include 'db.php';
include '../includes/functions.php';

$advances = [];
$advanceResult = $conn->query("SELECT * FROM advances ORDER BY recorded_at DESC");
if ($advanceResult) {
    $advances = $advanceResult->fetch_all(MYSQLI_ASSOC);
}

$repaymentsMap = [];
$repaymentResult = $conn->query("SELECT * FROM advance_repayments ORDER BY recorded_at DESC");
if ($repaymentResult) {
    while ($repayment = $repaymentResult->fetch_assoc()) {
        $repaymentsMap[$repayment['advance_id']][] = $repayment;
    }
}

$scheduleMap = [];
$scheduleResult = $conn->query("SELECT * FROM advance_repayment_schedule ORDER BY due_date ASC");
if ($scheduleResult) {
    while ($schedule = $scheduleResult->fetch_assoc()) {
        $scheduleMap[$schedule['advance_id']][] = $schedule;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Advances</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main class="main" id="mainContent">
    <div class="container mt-4">
        <h1 class="h3 mb-3">Active Advances</h1>
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Booked Advances</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($advances)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Officer</th>
                                    <th>Amount</th>
                                    <th>Balance</th>
                                    <th>Start</th>
                                    <th>Monthly</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($advances as $advance): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($advance['loan_officer_name']); ?></td>
                                        <td><?php echo number_format($advance['amount'], 2); ?> KES</td>
                                        <td><?php echo number_format($advance['balance'], 2); ?> KES</td>
                                        <td><?php echo date('d-m-Y', strtotime($advance['start_date'])); ?></td>
                                        <td><?php echo number_format($advance['monthly_deduction'], 2); ?> KES</td>
                                        <td>
                                            <form method="POST" action="add_advance.php" style="display:inline-block; margin-right:4px;">
                                                <input type="hidden" name="advance_id" value="<?php echo (int) $advance['id']; ?>">
                                                <button class="btn btn-sm btn-primary" name="apply_deduction" onclick="return confirm('Apply monthly deduction now?')">Apply Monthly</button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleRepayForm(<?php echo (int) $advance['id']; ?>)">Add Repayment</button>
                                            <a class="btn btn-sm btn-warning" href="add_advance.php?edit_advance_id=<?php echo (int) $advance['id']; ?>">Edit</a>
                                            <button type="button" class="btn btn-sm btn-info" onclick="toggleSchedule(<?php echo (int) $advance['id']; ?>)">View Schedule</button>
                                            <form method="POST" action="add_advance.php" style="display:inline-block; margin-left:4px;">
                                                <input type="hidden" name="advance_id" value="<?php echo (int) $advance['id']; ?>">
                                                <button class="btn btn-sm btn-danger" name="delete_advance" onclick="return confirm('Delete advance? This will remove repayments.')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr id="schedule-row-<?php echo (int) $advance['id']; ?>" style="display:none">
                                        <td colspan="6">
                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Amount Due</th>
                                                            <th>Amount Paid</th>
                                                            <th>Repayment Date</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($scheduleMap[$advance['id']])): ?>
                                                            <?php foreach ($scheduleMap[$advance['id']] as $schedule): ?>
                                                                <tr>
                                                                    <td><?php echo number_format($schedule['due_amount'], 2); ?> KES</td>
                                                                    <td>0.00 KES</td>
                                                                    <td><?php echo date('d-m-Y', strtotime($schedule['due_date'])); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr><td colspan="3">No schedule generated.</td></tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr id="repay-form-<?php echo (int) $advance['id']; ?>" style="display:none">
                                        <td colspan="6">
                                            <form method="POST" action="add_advance.php" class="row g-2">
                                                <input type="hidden" name="advance_id" value="<?php echo (int) $advance['id']; ?>">
                                                <div class="col-md-4"><input name="repayment_amount" class="form-control" type="number" step="0.01" placeholder="Amount" required></div>
                                                <div class="col-md-4"><input name="payment_date" class="form-control" type="date" value="<?php echo date('Y-m-d'); ?>" required></div>
                                                <div class="col-md-4"><button class="btn btn-success" name="add_repayment">Save Repayment</button></div>
                                            </form>
                                            <?php if (!empty($repaymentsMap[$advance['id']])): ?>
                                                <div class="mt-3">
                                                    <strong>Repayments:</strong>
                                                    <ul class="mb-0">
                                                        <?php foreach ($repaymentsMap[$advance['id']] as $repayment): ?>
                                                            <li><?php echo date('d-m-Y', strtotime($repayment['payment_date'])); ?> - <?php echo number_format($repayment['amount'], 2); ?> KES</li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-muted">No advances booked yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<script>
function toggleSchedule(id) {
    var row = document.getElementById('schedule-row-' + id);
    row.style.display = row.style.display === 'none' ? '' : 'none';
}

function toggleRepayForm(id) {
    var row = document.getElementById('repay-form-' + id);
    row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>
</body>
</html>
