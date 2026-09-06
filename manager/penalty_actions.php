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
$interestRateExpression = $hasInterestCalculationColumn
    ? "LOWER(COALESCE(l.interest_calculation, l.repayment_cycle, 'monthly'))"
    : "LOWER(COALESCE(l.repayment_cycle, l.loan_duration_unit, 'monthly'))";

$conn->query("CREATE TABLE IF NOT EXISTS penalty_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    borrower_id INT NOT NULL,
    officer_email VARCHAR(255) NOT NULL,
    officer_name VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    note VARCHAR(500) DEFAULT NULL,
    acted_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_penalty_actions_loan (loan_id),
    INDEX idx_penalty_actions_officer (officer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = '';
$messageType = '';
$selectedOfficer = trim($_POST['officer_email'] ?? $_GET['officer'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionType = $_POST['action'] ?? '';
    $actionId = (int) ($_POST['action_id'] ?? 0);

    if ($actionType === 'delete') {
        if ($actionId > 0) {
            $deleteStmt = $conn->prepare('DELETE FROM penalty_actions WHERE id = ?');
            $deleteStmt->bind_param('i', $actionId);
            if ($deleteStmt->execute() && $deleteStmt->affected_rows > 0) {
                $message = 'Penalty action deleted.';
                $messageType = 'success';
            } else {
                $message = 'Penalty action was not found or could not be deleted.';
                $messageType = 'error';
            }
            $deleteStmt->close();
        }
    } elseif ($actionType === 'edit') {
        $editedAmount = round((float) ($_POST['edit_amount'] ?? 0), 2);
        $editedNote = trim($_POST['edit_note'] ?? '');
        if ($actionId <= 0 || $editedAmount <= 0) {
            $message = 'Enter a valid write-off amount.';
            $messageType = 'error';
        } else {
            $editStmt = $conn->prepare('UPDATE penalty_actions SET amount = ?, note = ? WHERE id = ?');
            $editStmt->bind_param('dsi', $editedAmount, $editedNote, $actionId);
            if ($editStmt->execute() && $editStmt->affected_rows >= 0) {
                $message = 'Penalty action updated.';
                $messageType = 'success';
            } else {
                $message = 'Unable to update the penalty action.';
                $messageType = 'error';
            }
            $editStmt->close();
        }
    } else {
    $loanId = (int) ($_POST['loan_id'] ?? 0);
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $note = trim($_POST['note'] ?? '');

    if ($loanId <= 0 || $amount <= 0) {
        $message = 'Select a client loan and enter a valid write-off amount.';
        $messageType = 'error';
    } else {
        $loanSql = "SELECT l.id, l.borrower AS borrower_id, b.full_name AS borrower_name,
                           b.loan_officer AS officer_email, COALESCE(u.name, 'Unassigned') AS officer_name,
                           GREATEST(0, (
                               COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)
                               - (l.principal + (l.principal * CASE WHEN $interestRateExpression IN ('weekly', 'week', 'weeks') THEN 0.06 ELSE 0.24 END * l.loan_duration))
                           ) - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0)) AS remaining_penalty
                    FROM loan_applications l
                    INNER JOIN borrowers b ON l.borrower = b.id
                                        LEFT JOIN users u ON CONVERT(b.loan_officer USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci
                    WHERE l.id = ?
                      AND (l.loan_status IN ('approved', 'rolled_over') OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%')
                    LIMIT 1";
        $loanStmt = $conn->prepare($loanSql);
        $loanStmt->bind_param('i', $loanId);
        $loanStmt->execute();
        $loan = $loanStmt->get_result()->fetch_assoc();

        $poolSql = "SELECT GREATEST(0,
                           COALESCE((
                               SELECT SUM(GREATEST(0,
                                   COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)
                                   - (l.principal + (l.principal * CASE WHEN $interestRateExpression IN ('weekly', 'week', 'weeks') THEN 0.06 ELSE 0.24 END * l.loan_duration))
                               ))
                               FROM loan_applications l
                               INNER JOIN borrowers b ON l.borrower = b.id
                               WHERE CONVERT(b.loan_officer USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
                                 AND (l.loan_status IN ('approved', 'rolled_over') OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%')
                           ), 0)
                           - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.officer_email = ?), 0)
                       ) AS available_penalty";
        $poolStmt = $conn->prepare($poolSql);
        $poolStmt->bind_param('ss', $selectedOfficer, $selectedOfficer);
        $poolStmt->execute();
        $availablePool = (float) ($poolStmt->get_result()->fetch_assoc()['available_penalty'] ?? 0);

        if (!$loan || $loan['officer_email'] !== $selectedOfficer) {
            $message = 'The selected client is not assigned to the selected loan officer.';
            $messageType = 'error';
        } else {
            $insertSql = "INSERT INTO penalty_actions (loan_id, borrower_id, officer_email, officer_name, amount, note, acted_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param('iissdss', $loanId, $loan['borrower_id'], $selectedOfficer, $loan['officer_name'], $amount, $note, $_SESSION['email']);
            if ($insertStmt->execute()) {
                $message = 'Penalty write-off recorded for ' . $loan['borrower_name'] . '.';
                $messageType = 'success';
            } else {
                $message = 'Unable to record the penalty action.';
                $messageType = 'error';
            }
        }
    }
    }
}

$officerSql = "SELECT u.email, u.name,
                      GREATEST(0,
                          COALESCE((
                              SELECT SUM(GREATEST(0,
                                  COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)
                                  - (l.principal + (l.principal * CASE WHEN $interestRateExpression IN ('weekly', 'week', 'weeks') THEN 0.06 ELSE 0.24 END * l.loan_duration))
                              ))
                              FROM loan_applications l
                              INNER JOIN borrowers b ON l.borrower = b.id
                              WHERE CONVERT(b.loan_officer USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci
                                AND (l.loan_status IN ('approved', 'rolled_over') OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%')
                          ), 0)
                          - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE CONVERT(pa.officer_email USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci), 0)
                      ) AS available_penalty
               FROM users u
               WHERE u.role_id = '2'
               ORDER BY u.name";
$officerResult = $conn->query($officerSql);
$officers = [];
while ($officer = $officerResult->fetch_assoc()) {
    $officers[] = $officer;
}

$loanSql = "SELECT l.id, b.full_name AS borrower_name, b.loan_officer AS officer_email,
                   COALESCE(u.name, 'Unassigned') AS officer_name,
                   GREATEST(0,
                       COALESCE(SUM(CASE WHEN r.repayment_date < CURDATE() THEN COALESCE(r.amount, 0) ELSE 0 END), 0)
                       - COALESCE(SUM(COALESCE(r.paid, 0)), 0)
                       - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0)
                   ) AS arrears,
                   GREATEST(0, (
                       COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)
                       - (l.principal + (l.principal * CASE WHEN $interestRateExpression IN ('weekly', 'week', 'weeks') THEN 0.06 ELSE 0.24 END * l.loan_duration))
                   ) - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = l.id), 0)) AS remaining_penalty
            FROM loan_applications l
            INNER JOIN borrowers b ON l.borrower = b.id
            LEFT JOIN users u ON CONVERT(b.loan_officer USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci
            LEFT JOIN repayments r ON r.loan_id = l.id
            WHERE (l.loan_status IN ('approved', 'rolled_over') OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%')
            GROUP BY l.id, b.id, b.full_name, b.loan_officer, u.name, l.principal, l.loan_duration
            HAVING arrears > 0
            ORDER BY b.full_name";
$loanResult = $conn->query($loanSql);
$loans = [];
while ($loan = $loanResult->fetch_assoc()) {
    $loans[] = $loan;
}

$actionsResult = $conn->query("SELECT pa.*, b.full_name AS borrower_name
                              FROM penalty_actions pa
                              INNER JOIN borrowers b ON b.id = pa.borrower_id
                              ORDER BY pa.created_at DESC
                              LIMIT 50");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penalty Actions - Manager</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ink: #172331; --muted: #687582; --line: #dbe3e8; --paper: #ffffff; --canvas: #f2f5f6; --teal: #147d78; --gold: #c7973e; }
        body { background: var(--canvas); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; }
        .main { margin-left: 250px; padding: 34px 22px 60px; transition: margin-left .3s ease; }
        .main.sidebar-collapsed { margin-left: 0; }
        .sidebar { transition: all .3s ease; }
        .sidebar.collapsed { display: none; }
        .header { background: var(--ink); border-top: 4px solid var(--gold); color: white; margin: 0 auto; max-width: 1280px; padding: 26px 34px; }
        .header h1 { color: white; font-family: Georgia, serif; font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: normal; }
        .sidebar-toggle-btn, .header .btn { background: transparent; border: 1px solid #82939c; border-radius: 0; color: white; padding: 8px 14px; }
        .sidebar-toggle-btn { margin-right: 14px; }
        .sidebar-toggle-btn:hover, .header .btn:hover { background: var(--teal); border-color: var(--teal); color: white; }
        .panel { background: var(--paper); border: 1px solid var(--line); margin: 18px auto 0; max-width: 1280px; padding: 22px; }
        .panel h2 { border-top: 4px solid var(--gold); background: var(--ink); color: white; font-family: Georgia, serif; font-size: 1.25rem; font-weight: normal; margin: -22px -22px 22px; padding: 18px 22px; }
        .form-label { color: var(--muted); font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
        .form-control, .form-select { border-color: var(--line); border-radius: 0; }
        .form-control:focus, .form-select:focus { border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(20,125,120,.12); }
        .btn-action { background: var(--teal); border: 1px solid var(--teal); border-radius: 0; color: white; padding: 9px 16px; }
        .btn-action:hover { background: #0f625e; color: white; }
        .alert-success { background: #e6f3f1; border-color: #b9ded9; color: #146b67; border-radius: 0; }
        .alert-danger { background: #f8e8e5; border-color: #e5beb7; color: #8e4d47; border-radius: 0; }
        .table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; }
        .table td { border-color: #e6ecef; vertical-align: middle; }
        .table tbody tr:hover { background: #f7faf9; }
        .amount { color: #a95d55; font-weight: bold; white-space: nowrap; }
        @media (max-width: 768px) { .main { margin-left: 0; padding: 20px 12px 40px; } .header { padding: 20px; } .panel { padding: 16px 12px; } .panel h2 { margin: -16px -12px 16px; } }
    </style>
</head>
<body>
<div class="sidebar" id="sidebarWrapper"><?php include '../includes/sidebar.php'; ?></div>
<main class="main" id="mainContent">
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center"><button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation"><i class="bi bi-list"></i></button><h1 class="mb-0">Penalty Actions</h1></div>
        <a href="penalty_breakdown.php" class="btn">Back to Penalties</a>
    </div>

    <section class="panel">
        <h2>Write Off Client Penalty</h2>
        <?php if ($message !== ''): ?><div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <p class="text-muted">Use the selected loan officer's available penalty pool to write off a penalty for one of that officer's assigned defaulted clients.</p>
        <form method="post" id="penaltyActionForm" class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="officer_email">Loan Officer</label>
                <select class="form-select" name="officer_email" id="officer_email" required>
                    <option value="">Select loan officer</option>
                    <?php foreach ($officers as $officer): ?>
                        <option value="<?php echo htmlspecialchars($officer['email'], ENT_QUOTES, 'UTF-8'); ?>" data-available="<?php echo (float) $officer['available_penalty']; ?>" <?php echo $selectedOfficer === $officer['email'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($officer['name']); ?> - Available: KSH <?php echo number_format((float) $officer['available_penalty'], 2); ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="officerPool" class="text-muted"></small>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="loan_id">Client Loan in Arrears</label>
                <select class="form-select" name="loan_id" id="loan_id" required>
                    <option value="">Select client loan</option>
                    <?php foreach ($loans as $loan): ?>
                        <option class="loan-option" value="<?php echo (int) $loan['id']; ?>" data-officer="<?php echo htmlspecialchars($loan['officer_email'], ENT_QUOTES, 'UTF-8'); ?>" data-remaining="<?php echo (float) $loan['remaining_penalty']; ?>"><?php echo htmlspecialchars($loan['borrower_name']); ?> - Loan #<?php echo (int) $loan['id']; ?> - Arrears KSH <?php echo number_format((float) $loan['arrears'], 2); ?> - Penalty KSH <?php echo number_format((float) $loan['remaining_penalty'], 2); ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="loanPenalty" class="text-muted"></small>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="amount">Amount to Write Off</label>
                <input class="form-control" type="number" name="amount" id="amount" step="0.01" required>
                <small class="text-muted">Enter the penalty amount to write off.</small>
            </div>
            <div class="col-12">
                <label class="form-label" for="note">Action Note</label>
                <textarea class="form-control" name="note" id="note" rows="2" maxlength="500" placeholder="Reason for the penalty write-off"></textarea>
            </div>
            <div class="col-12"><button type="submit" class="btn-action"><i class="bi bi-check2-circle"></i> Record Penalty Action</button></div>
        </form>
    </section>

    <section class="panel">
        <h2>Recent Penalty Actions</h2>
        <div class="table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Date</th><th>Officer</th><th>Client</th><th>Loan ID</th><th>Amount</th><th>Note</th><th>Action</th></tr></thead><tbody>
            <?php if ($actionsResult && $actionsResult->num_rows > 0): while ($action = $actionsResult->fetch_assoc()): ?>
                <tr><td><?php echo htmlspecialchars($action['created_at']); ?></td><td><?php echo htmlspecialchars($action['officer_name']); ?></td><td><?php echo htmlspecialchars($action['borrower_name']); ?></td><td><a href="repayment_details.php?loanId=<?php echo (int) $action['loan_id']; ?>"><?php echo (int) $action['loan_id']; ?></a></td><td class="amount">KSH <?php echo number_format((float) $action['amount'], 2); ?></td><td><?php echo htmlspecialchars($action['note'] ?: '-'); ?></td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary edit-action-btn" data-id="<?php echo (int) $action['id']; ?>" data-amount="<?php echo htmlspecialchars((string) $action['amount'], ENT_QUOTES, 'UTF-8'); ?>" data-note="<?php echo htmlspecialchars($action['note'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">Edit</button> <form method="post" class="d-inline" onsubmit="return confirm('Delete this penalty action?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="action_id" value="<?php echo (int) $action['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
            <?php endwhile; else: ?><tr><td colspan="7" class="text-center">No penalty actions recorded.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main>

<div class="modal fade" id="editActionModal" tabindex="-1" aria-labelledby="editActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="editActionModalLabel">Edit Penalty Action</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="action_id" id="editActionId">
                <label class="form-label" for="editAmount">Amount</label>
                <input class="form-control mb-3" type="number" name="edit_amount" id="editAmount" step="0.01" required>
                <label class="form-label" for="editNote">Note</label>
                <textarea class="form-control" name="edit_note" id="editNote" rows="3" maxlength="500"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-action">Save Changes</button></div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('sidebarToggleMain');
        const sidebar = document.getElementById('sidebarWrapper');
        const main = document.getElementById('mainContent');
        if (toggle) toggle.addEventListener('click', function () { sidebar.classList.toggle('collapsed'); main.classList.toggle('sidebar-collapsed'); });

        const officer = document.getElementById('officer_email');
        const loan = document.getElementById('loan_id');
        const amount = document.getElementById('amount');
        const pool = document.getElementById('officerPool');
        const loanPenalty = document.getElementById('loanPenalty');
        function refreshOptions() {
            const selected = officer.value;
            const officerOption = officer.options[officer.selectedIndex];
            pool.textContent = officerOption && officerOption.dataset.available ? 'Available pool: KSH ' + Number(officerOption.dataset.available).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '';
            Array.from(loan.options).forEach(option => {
                if (!option.dataset.officer) return;
                const matchesOfficer = selected !== '' && option.dataset.officer === selected;
                option.hidden = !matchesOfficer;
                option.disabled = !matchesOfficer;
            });
            if (!loan.value || !loan.selectedOptions[0] || loan.selectedOptions[0].disabled) loan.value = '';
            loanPenalty.textContent = '';
        }
        officer.addEventListener('change', refreshOptions);
        loan.addEventListener('change', function () {
            const option = loan.selectedOptions[0];
            if (!option || !option.dataset.remaining) return;
            loanPenalty.textContent = 'Client penalty available: KSH ' + Number(option.dataset.remaining).toLocaleString(undefined, { minimumFractionDigits: 2 });
        });
        refreshOptions();

        document.querySelectorAll('.edit-action-btn').forEach(button => {
            button.addEventListener('click', function () {
                document.getElementById('editActionId').value = this.dataset.id;
                document.getElementById('editAmount').value = this.dataset.amount;
                document.getElementById('editNote').value = this.dataset.note;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editActionModal')).show();
            });
        });
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
