<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
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
$savedMessage = $_GET['saved'] ?? '';
if ($savedMessage === '1') {
    $message = 'Expense entry saved successfully.';
} elseif ($savedMessage === 'updated') {
    $message = 'Expense entry updated successfully.';
}
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
                    header('Location: add_expenses.php?saved=updated');
                    exit;
                } else {
                    $expenseStmt = $conn->prepare("INSERT INTO loan_officer_expenses (template_id, template_name, expense_type, amount, expense_date, loan_officer_id, loan_officer_name, payment_method, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $expenseStmt->bind_param('issdsissi', $templateId, $templateName, $expenseType, $amountValue, $expenseDate, $loanOfficerId, $officer['name'], $paymentMethod, $recordedBy);
                    $expenseStmt->execute();
                    header('Location: add_expenses.php?saved=1');
                    exit;
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
        :root { --ink: #172331; --muted: #687582; --line: #dbe3e8; --paper: #ffffff; --canvas: #f2f5f6; --teal: #147d78; --gold: #c7973e; }
        body { background: var(--canvas); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; }
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
        .panel { background: var(--paper); border: 1px solid var(--line); margin-top: 18px; padding: 22px; }
        .panel h2 { background: var(--ink); border-top: 4px solid var(--gold); color: white; font-family: Georgia, serif; font-size: 1.25rem; font-weight: normal; margin: -22px -22px 22px; padding: 18px 22px; }
        .form-control, .form-select { border-color: var(--line); border-radius: 0; }
        .form-control:focus, .form-select:focus { border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(20,125,120,.12); }
        .btn-primary { background: var(--teal); border: 1px solid var(--teal); border-radius: 0; color: white; }
        .btn-primary:hover { background: #0f625e; color: white; }
        .report-tabs { border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 0 0; }
        .report-tabs .nav-link { background: transparent; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; color: var(--muted); padding: 9px 14px; }
        .report-tabs .nav-link.active { background: var(--teal); border-color: var(--teal); color: white; }
        .report-tabs .nav-link:hover { background: #f7faf9; border-color: var(--teal); color: var(--teal); }
        .table { margin: 0; }
        .table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; }
        .table td { border-color: #e6ecef; vertical-align: middle; }
        .table tbody tr:hover { background: #f7faf9; }
        @media (max-width: 768px) { .main { margin-left: 0; padding: 20px 12px 40px; } .report-shell { padding: 16px 12px; } .report-header { align-items: flex-start; flex-direction: column; gap: 12px; margin: -16px -12px 16px; padding: 20px; } .panel { padding: 16px 12px; } .panel h2 { margin: -16px -12px 16px; } }
    </style>
</head>
<body>
<div class="sidebar" id="sidebarWrapper"><?php include '../includes/sidebar.php'; ?></div>
<main class="main" id="mainContent">
<div class="report-shell">
    <div class="report-header">
            <div class="d-flex align-items-center"><button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation"><i class="bi bi-list"></i></button><h1>Expense Template</h1></div>
            <div class="d-flex gap-2"><a href="expense_more_information.php" class="btn">More Info</a><a href="index.php" class="btn">Back to Dashboard</a></div>
        </div>
    <div class="panel mb-4">
        <h2>Expense Entry</h2>
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

    </div>
</div>
</div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleButton = document.getElementById('sidebarToggleMain');
    const sidebarWrapper = document.getElementById('sidebarWrapper');
    const mainContent = document.getElementById('mainContent');
    if (toggleButton) toggleButton.addEventListener('click', function () { sidebarWrapper.classList.toggle('collapsed'); mainContent.classList.toggle('sidebar-collapsed'); });

    document.querySelectorAll('[data-expense-filter]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const filter = this.dataset.expenseFilter;
            document.querySelectorAll('[data-expense-filter]').forEach(function (item) { item.classList.toggle('active', item === tab); });
            document.querySelectorAll('tbody tr[data-expense-category]').forEach(function (row) { row.style.display = filter === 'all' || row.dataset.expenseCategory === filter ? '' : 'none'; });
        });
    });

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
