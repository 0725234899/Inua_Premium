<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';
include '../includes/functions.php';

if (isset($_POST['update_advance'])) {
    $advanceId = (int) ($_POST['edit_advance_id'] ?? 0);
    $loanOfficerId = (int) ($_POST['loan_officer_id'] ?? 0);
    $templateId = !empty($_POST['template_id']) ? (int) $_POST['template_id'] : null;
    $amount = max(0, (float) ($_POST['amount'] ?? 0));
    $monthlyDeduction = max(0, (float) ($_POST['monthly_deduction'] ?? 0));
    $startDate = $_POST['start_date'] ?? '';
    $duration = $amount > 0 && $monthlyDeduction > 0
        ? max(1, min(36, (int) ceil($amount / $monthlyDeduction)))
        : 1;

    $officerStmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $officerStmt->bind_param('i', $loanOfficerId);
    $officerStmt->execute();
    $officerStmt->bind_result($loanOfficerName);
    $officerStmt->fetch();
    $officerStmt->close();

    if ($advanceId > 0 && $loanOfficerName && $startDate) {
        $paidStmt = $conn->prepare("SELECT IFNULL(SUM(amount), 0) FROM advance_repayments WHERE advance_id = ?");
        $paidStmt->bind_param('i', $advanceId);
        $paidStmt->execute();
        $paidStmt->bind_result($totalPaid);
        $paidStmt->fetch();
        $paidStmt->close();

        $balance = max(0, $amount - (float) $totalPaid);
        $updateStmt = $conn->prepare("UPDATE advances SET loan_officer_id = ?, loan_officer_name = ?, template_id = ?, amount = ?, balance = ?, start_date = ?, monthly_deduction = ?, duration_months = ?, recorded_by = ? WHERE id = ?");
        $recordedBy = $_SESSION['user_id'] ?? null;
        $updateStmt->bind_param('isiddsiiii', $loanOfficerId, $loanOfficerName, $templateId, $amount, $balance, $startDate, $monthlyDeduction, $duration, $recordedBy, $advanceId);
        $updateStmt->execute();
        $updateStmt->close();

        $deleteScheduleStmt = $conn->prepare("DELETE FROM advance_repayment_schedule WHERE advance_id = ?");
        $deleteScheduleStmt->bind_param('i', $advanceId);
        $deleteScheduleStmt->execute();
        $deleteScheduleStmt->close();

        $remaining = $balance;
        if ($remaining > 0) {
            $installment = $monthlyDeduction > 0 ? min($monthlyDeduction, $remaining) : round($remaining / $duration, 2);
            $lastAmount = round($remaining - ($installment * ($duration - 1)), 2);
            $dueDate = new DateTime($startDate);
            $scheduleStmt = $conn->prepare("INSERT INTO advance_repayment_schedule (advance_id, due_amount, due_date, status) VALUES (?, ?, ?, 'pending')");
            for ($index = 0; $index < $duration; $index++) {
                if ($index > 0) {
                    $dueDate->modify('+1 month');
                }
                $dueAmount = $index === $duration - 1 ? $lastAmount : $installment;
                $dueDateValue = $dueDate->format('Y-m-d');
                $scheduleStmt->bind_param('ids', $advanceId, $dueAmount, $dueDateValue);
                $scheduleStmt->execute();
            }
            $scheduleStmt->close();
        }

        header('Location: view_Advance.php?updated=1');
        exit;
    }
}

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

$loanOfficers = [];
$officerResult = $conn->query("SELECT id, name FROM users WHERE role_id = 2 ORDER BY name");
if ($officerResult) {
    $loanOfficers = $officerResult->fetch_all(MYSQLI_ASSOC);
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
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editAdvanceModal<?php echo (int) $advance['id']; ?>">Edit</button>
                                            <form method="POST" action="add_advance.php" style="display:inline-block; margin-left:4px;">
                                                <input type="hidden" name="advance_id" value="<?php echo (int) $advance['id']; ?>">
                                                <button class="btn btn-sm btn-danger" name="delete_advance" onclick="return confirm('Delete advance? This will remove repayments.')">Delete</button>
                                            </form>
                                            <div class="modal fade" id="editAdvanceModal<?php echo (int) $advance['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <form method="POST" action="view_Advance.php">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Edit Advance</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="edit_advance_id" value="<?php echo (int) $advance['id']; ?>">
                                                                <input type="hidden" name="template_id" value="<?php echo (int) ($advance['template_id'] ?? 0); ?>">
                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <label class="form-label" for="loan-officer-<?php echo (int) $advance['id']; ?>">Loan Officer</label>
                                                                        <select class="form-select" id="loan-officer-<?php echo (int) $advance['id']; ?>" name="loan_officer_id" required>
                                                                            <?php foreach ($loanOfficers as $loanOfficer): ?>
                                                                                <option value="<?php echo (int) $loanOfficer['id']; ?>" <?php echo (int) $loanOfficer['id'] === (int) $advance['loan_officer_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($loanOfficer['name']); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label" for="amount-<?php echo (int) $advance['id']; ?>">Advance Amount</label>
                                                                        <input class="form-control edit-advance-amount" id="amount-<?php echo (int) $advance['id']; ?>" name="amount" type="number" min="0" step="0.01" value="<?php echo htmlspecialchars($advance['amount']); ?>" required>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label" for="monthly-<?php echo (int) $advance['id']; ?>">Monthly Deduction</label>
                                                                        <input class="form-control edit-advance-monthly" id="monthly-<?php echo (int) $advance['id']; ?>" name="monthly_deduction" type="number" min="0" step="0.01" value="<?php echo htmlspecialchars($advance['monthly_deduction']); ?>" required>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label" for="duration-<?php echo (int) $advance['id']; ?>">Duration (months)</label>
                                                                        <input class="form-control edit-advance-duration" id="duration-<?php echo (int) $advance['id']; ?>" name="duration_months" type="number" value="<?php echo (int) $advance['duration_months']; ?>" readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label" for="start-<?php echo (int) $advance['id']; ?>">Start Date</label>
                                                                        <input class="form-control" id="start-<?php echo (int) $advance['id']; ?>" name="start_date" type="date" value="<?php echo htmlspecialchars($advance['start_date']); ?>" required>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary" name="update_advance">Save Changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
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
function toggleRepayForm(id) {
    var row = document.getElementById('repay-form-' + id);
    row.style.display = row.style.display === 'none' ? '' : 'none';
}

document.querySelectorAll('.edit-advance-amount, .edit-advance-monthly').forEach(function (input) {
    input.addEventListener('input', function () {
        var form = this.closest('form');
        var amount = parseFloat(form.querySelector('.edit-advance-amount').value) || 0;
        var monthly = parseFloat(form.querySelector('.edit-advance-monthly').value) || 0;
        form.querySelector('.edit-advance-duration').value = amount > 0 && monthly > 0
            ? Math.min(36, Math.max(1, Math.ceil(amount / monthly)))
            : 1;
    });
});
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
