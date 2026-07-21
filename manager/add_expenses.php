<?php
session_start();
require_once 'db.php';

$conn->query("CREATE TABLE IF NOT EXISTS expense_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    expense_type VARCHAR(100) NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_template_name (template_name)
)");

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

$message = '';
$error = '';
$editing = false;
$editExpenseId = 0;
$expenseType = '';
$amount = '';
$expenseDate = date('Y-m-d');
$paymentMethod = 'mpesa';
$loanOfficerId = 0;

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $deleteStmt = $conn->prepare("DELETE FROM loan_officer_expenses WHERE id = ?");
        $deleteStmt->bind_param('i', $deleteId);
        $deleteStmt->execute();
        header('Location: add_expenses.php');
        exit;
    }
}

if (isset($_GET['edit'])) {
    $editExpenseId = (int)$_GET['edit'];
    if ($editExpenseId > 0) {
        $editStmt = $conn->prepare("SELECT * FROM loan_officer_expenses WHERE id = ? LIMIT 1");
        $editStmt->bind_param('i', $editExpenseId);
        $editStmt->execute();
        $editResult = $editStmt->get_result();
        if ($editRow = $editResult->fetch_assoc()) {
            $editing = true;
            $expenseType = $editRow['expense_type'];
            $amount = $editRow['amount'];
            $expenseDate = $editRow['expense_date'];
            $paymentMethod = $editRow['payment_method'];
            $loanOfficerId = $editRow['loan_officer_id'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expenseType = trim($_POST['expense_type'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $expenseDate = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $loanOfficerId = (int)($_POST['loan_officer_id'] ?? 0);
    $paymentMethod = strtolower(trim($_POST['payment_method'] ?? 'cash'));
    $recordedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $templateName = $expenseType;
    $expenseId = (int)($_POST['expense_id'] ?? 0);

    if ($expenseType === '' || $amount === '' || $loanOfficerId <= 0) {
        $error = 'Please complete the required fields.';
    } else {
        $amountValue = (float)$amount;
        if ($amountValue <= 0) {
            $error = 'Amount must be greater than zero.';
        } else {
            $officerStmt = $conn->prepare("SELECT id, name, email, role_id FROM users WHERE id = ? LIMIT 1");
            $officerStmt->bind_param('i', $loanOfficerId);
            $officerStmt->execute();
            $officerResult = $officerStmt->get_result();
            $officer = $officerResult->fetch_assoc();

            if (!$officer) {
                $error = 'Selected person could not be found.';
            } else {
                $templateStmt = $conn->prepare("SELECT id FROM expense_templates WHERE template_name = ? LIMIT 1");
                $templateStmt->bind_param('s', $templateName);
                $templateStmt->execute();
                $templateResult = $templateStmt->get_result();
                $templateRow = $templateResult->fetch_assoc();
                $templateId = $templateRow['id'] ?? null;

                if (!$templateId) {
                    $createTemplateStmt = $conn->prepare("INSERT INTO expense_templates (template_name, expense_type, created_by, created_at) VALUES (?, ?, ?, NOW())");
                    $createTemplateStmt->bind_param('ssi', $templateName, $expenseType, $recordedBy);
                    $createTemplateStmt->execute();
                    $templateId = $createTemplateStmt->insert_id;
                }

                if ($expenseId > 0) {
                    $updateStmt = $conn->prepare("UPDATE loan_officer_expenses SET template_id = ?, template_name = ?, expense_type = ?, amount = ?, expense_date = ?, loan_officer_id = ?, loan_officer_name = ?, payment_method = ?, recorded_by = ? WHERE id = ?");
                    $updateStmt->bind_param('issdsisssi', $templateId, $templateName, $expenseType, $amountValue, $expenseDate, $loanOfficerId, $officer['name'], $paymentMethod, $recordedBy, $expenseId);
                    $updateStmt->execute();
                    $message = 'Expense entry updated successfully.';
                    $editing = false;
                    $editExpenseId = 0;
                } else {
                    $expenseStmt = $conn->prepare("INSERT INTO loan_officer_expenses (template_id, template_name, expense_type, amount, expense_date, loan_officer_id, loan_officer_name, payment_method, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $expenseStmt->bind_param('issdsissi', $templateId, $templateName, $expenseType, $amountValue, $expenseDate, $loanOfficerId, $officer['name'], $paymentMethod, $recordedBy);
                    $expenseStmt->execute();
                    $message = 'Expense template and spending entry were saved successfully.';
                }
            }
        }
    }
}

$expenseUsersResult = $conn->query("SELECT id, name, email, role_id FROM users WHERE role_id IN (1, 2) ORDER BY role_id, name");
$expenseUsers = $expenseUsersResult ? $expenseUsersResult->fetch_all(MYSQLI_ASSOC) : [];

$expensesResult = $conn->query("SELECT id, template_name, expense_type, amount, expense_date, loan_officer_name, payment_method, created_at FROM loan_officer_expenses ORDER BY expense_date DESC, id DESC LIMIT 20");
$expenses = $expensesResult ? $expensesResult->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Template</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f7f9fc; font-family: Arial, sans-serif; }
        .page-wrap { max-width: 1100px; margin: 30px auto; padding: 20px; }
        .card { border: 0; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
        .card-header { background: #0d6efd; color: #fff; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">EXPENSE TEMPLATE</h4>
            <a href="index.php" class="btn btn-light btn-sm">Back to Dashboard</a>
        </div>
        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="expense_id" value="<?php echo (int)$editExpenseId; ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Expense Category</label>
                        <select name="expense_type" class="form-select" required>
                            <option value="">Select category</option>
                            <option value="Collection"<?php echo ($expenseType === 'Collection') ? ' selected' : ''; ?>>Collection</option>
                            <option value="Marketing"<?php echo ($expenseType === 'Marketing') ? ' selected' : ''; ?>>Marketing</option>
                            <option value="Appraisal"<?php echo ($expenseType === 'Appraisal') ? ' selected' : ''; ?>>Appraisal</option>
                            <option value="Office Spendings"<?php echo ($expenseType === 'Office Spendings') ? ' selected' : ''; ?>>Office Spendings</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?php echo htmlspecialchars($amount, ENT_QUOTES); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expense Date</label>
                        <input type="date" name="expense_date" class="form-control" value="<?php echo htmlspecialchars($expenseDate, ENT_QUOTES); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="mpesa"<?php echo ($paymentMethod === 'mpesa') ? ' selected' : ''; ?>>Mpesa</option>
                            <option value="cash"<?php echo ($paymentMethod === 'cash') ? ' selected' : ''; ?>>Cash</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Loan Officer</label>
                        <select name="loan_officer_id" class="form-select" required>
                            <option value="">Select person</option>
                            <?php foreach ($expenseUsers as $user): ?>
                                <?php $roleKey = ($user['role_id'] == 1) ? 'manager' : 'officer'; ?>
                                <option value="<?php echo (int)$user['id']; ?>" data-role="<?php echo htmlspecialchars($roleKey, ENT_QUOTES); ?>"<?php echo ($loanOfficerId === (int)$user['id']) ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars((($user['role_id'] == 1) ? 'Manager' : 'Loan Officer') . ' - ' . $user['name'] . ' (' . $user['email'] . ')', ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><?php echo $editing ? 'Update Expense' : 'Save Expense'; ?></button>
                <?php if ($editing): ?>
                    <a href="add_expenses.php" class="btn btn-secondary mt-3 ms-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Recent Spendings</h5>
        </div>
        <div class="card-body">
            <?php if (empty($expenses)): ?>
                <p class="text-muted mb-0">No spending records yet.</p>
            <?php else: ?>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Person</th>
                            <th>Template</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Date Booked</th>
                            <th>Method</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($expense['loan_officer_name'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($expense['template_name'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($expense['expense_type'], ENT_QUOTES); ?></td>
                                <td><?php echo number_format((float)$expense['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars(!empty($expense['expense_date']) ? $expense['expense_date'] : '-', ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($expense['payment_method'], ENT_QUOTES); ?></td>
                                <td>
                                    <a href="add_expenses.php?edit=<?php echo (int)$expense['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <a href="add_expenses.php?delete=<?php echo (int)$expense['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this expense entry?');">Clear</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.querySelector('select[name="expense_type"]');
    const officerSelect = document.querySelector('select[name="loan_officer_id"]');
    if (!categorySelect || !officerSelect) {
        return;
    }

    const allOfficerOptions = Array.from(officerSelect.options).slice(1);

    const renderOfficerOptions = function () {
        const selectedCategory = (categorySelect.value || '').toLowerCase();
        const showManagersOnly = selectedCategory === 'office spendings';

        officerSelect.innerHTML = '<option value="">Select person</option>';

        allOfficerOptions.forEach(function (option) {
            const role = option.getAttribute('data-role');
            if (showManagersOnly ? role === 'manager' : role === 'officer') {
                officerSelect.appendChild(option.cloneNode(true));
            }
        });

        if (officerSelect.options.length === 1) {
            officerSelect.innerHTML = '<option value="">No matching person found</option>';
        }
    };

    categorySelect.addEventListener('change', renderOfficerOptions);
    renderOfficerOptions();
});
</script>
</body>
</html>
