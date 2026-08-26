<?php
session_start();

if (empty($_SESSION['email'])) {
    header('Location: ../login.php');
    exit();
}

require_once 'db.php';

function safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$conn->query("CREATE TABLE IF NOT EXISTS payroll_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS staff_payrolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT DEFAULT NULL,
    template_name VARCHAR(150) DEFAULT NULL,
    staff_id INT NOT NULL,
    staff_name VARCHAR(150) NOT NULL,
    staff_email VARCHAR(150) DEFAULT NULL,
    pay_period VARCHAR(100) NOT NULL,
    pay_date DATE NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    earnings LONGTEXT,
    deductions LONGTEXT,
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_staff_id (staff_id),
    KEY idx_pay_period (pay_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$columnCheck = $conn->query("SHOW COLUMNS FROM staff_payrolls LIKE 'region_id'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE staff_payrolls ADD COLUMN region_id INT DEFAULT NULL, ADD COLUMN region_name VARCHAR(150) DEFAULT NULL");
}

$regions = [];
$regionsResult = $conn->query("SELECT area_id, area_name FROM areas ORDER BY area_name ASC");
if ($regionsResult) {
    while ($row = $regionsResult->fetch_assoc()) {
        $regions[] = $row;
    }
}

$message = '';
$error = '';

$salaryColumnResult = $conn->query("SHOW COLUMNS FROM users LIKE 'basic_salary'");
if ($salaryColumnResult && $salaryColumnResult->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00");
}

$staffs = [];
$staffsResult = $conn->query("SELECT id, name, email, area AS region_id, COALESCE(basic_salary, 0) AS basic_salary FROM users ORDER BY name ASC");
if ($staffsResult) {
    $staffs = $staffsResult->fetch_all(MYSQLI_ASSOC);
}

$post = $_POST;
$payDateDefault = date('Y-m-d');

$selectedStaffSalary = 0.00;
$selectedStaffId = isset($post['staff_id']) ? (int)$post['staff_id'] : 0;
if ($selectedStaffId > 0) {
    foreach ($staffs as $staff) {
        if ((int)$staff['id'] === $selectedStaffId) {
            $selectedStaffSalary = floatval($staff['basic_salary'] ?? 0);
            break;
        }
    }
}

$activeAdvanceMap = [];
$advancesResult = $conn->query("SELECT id, loan_officer_id, monthly_deduction FROM advances WHERE balance > 0 ORDER BY id DESC");
if ($advancesResult) {
    while ($row = $advancesResult->fetch_assoc()) {
        if (!isset($activeAdvanceMap[$row['loan_officer_id']])) {
            $activeAdvanceMap[$row['loan_officer_id']] = $row;
        }
    }
}

$post = $_POST;
$payDateDefault = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payPeriod = trim($post['pay_period'] ?? '');
    $payDate = trim($post['pay_date'] ?? $payDateDefault);
    $payDateObj = DateTime::createFromFormat('Y-m-d', $payDate);

    if ($payPeriod === '' || !$payDateObj || $payDateObj->format('Y-m-d') !== $payDate) {
        $error = 'Please enter a pay period and choose a valid pay date.';
    } elseif (empty($staffs)) {
        $error = 'No staff members are available for payroll.';
    } else {
        $recordedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $templateId = null;
        $templateNameToSave = null;
        $notes = null;
        $payrollIds = [];
        $insertStmt = $conn->prepare("INSERT INTO staff_payrolls (
            template_id, template_name, staff_id, staff_name, staff_email,
            region_id, region_name, pay_period, pay_date, basic_salary, gross_pay, total_deductions,
            net_pay, earnings, deductions, notes, recorded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($staffs as $staff) {
            $staffId = (int)$staff['id'];
            $regionId = (int)($staff['region_id'] ?? 0);
            $regionName = '';
            foreach ($regions as $region) {
                if ((int)$region['area_id'] === $regionId) {
                    $regionName = $region['area_name'];
                    break;
                }
            }

            $basicSalary = (float)($staff['basic_salary'] ?? 0);
            $grossPay = $basicSalary;
            $deductions = [];
            $advanceId = 0;
            $advanceAmount = 0;
            $advanceStmt = $conn->prepare("SELECT id, monthly_deduction FROM advances WHERE loan_officer_id = ? AND balance > 0 ORDER BY id DESC LIMIT 1");
            $advanceStmt->bind_param('i', $staffId);
            $advanceStmt->execute();
            $advanceResult = $advanceStmt->get_result();
            $activeAdvance = $advanceResult ? $advanceResult->fetch_assoc() : null;
            $advanceStmt->close();

            if ($activeAdvance && (float)$activeAdvance['monthly_deduction'] > 0) {
                $advanceId = (int)$activeAdvance['id'];
                $advanceAmount = (float)$activeAdvance['monthly_deduction'];
                $deductions[] = ['label' => 'Advance', 'amount' => $advanceAmount];
            }

            $totalDeductions = $advanceAmount;
            $netPay = $grossPay - $totalDeductions;
            $earningsJson = json_encode([]);
            $deductionsJson = json_encode($deductions);
            $staffIdValue = $staff['id'];
            $staffName = $staff['name'];
            $staffEmail = $staff['email'];
            $insertStmt->bind_param('isississsddddsssi', $templateId, $templateNameToSave, $staffIdValue, $staffName, $staffEmail, $regionId, $regionName, $payPeriod, $payDate, $basicSalary, $grossPay, $totalDeductions, $netPay, $earningsJson, $deductionsJson, $notes, $recordedBy);

            if (!$insertStmt->execute()) {
                $error = 'Unable to save payroll records. Please try again.';
                break;
            }

            $payrollIds[] = (int)$conn->insert_id;
            if ($advanceId > 0 && $advanceAmount > 0) {
                $paymentDate = date('Y-m-d');
                $repaymentStmt = $conn->prepare("INSERT INTO advance_repayments (advance_id, amount, payment_date, recorded_by) VALUES (?, ?, ?, ?)");
                $repaymentStmt->bind_param('idsi', $advanceId, $advanceAmount, $paymentDate, $recordedBy);
                if ($repaymentStmt->execute()) {
                    $updateAdvance = $conn->prepare("UPDATE advances SET balance = balance - ? WHERE id = ?");
                    $updateAdvance->bind_param('di', $advanceAmount, $advanceId);
                    $updateAdvance->execute();
                    $updateAdvance->close();
                }
                $repaymentStmt->close();
            }
        }
        $insertStmt->close();

        if (empty($error) && !empty($payrollIds)) {
            $_SESSION['payroll_email_queue'] = [
                'ids' => $payrollIds,
                'period' => $payPeriod,
            ];
            header('Location: view_payroll.php');
            exit();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payroll</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #eef2ff;
            color: #0f172a;
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 1140px;
            margin: 24px auto;
            padding: 0 18px 32px;
        }
        .card {
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.16);
            padding: 28px;
            margin-bottom: 24px;
        }
        .hero {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 20px;
        }
        .hero h1 {
            margin: 0;
            font-size: 2.3rem;
            letter-spacing: -0.04em;
        }
        .hero p {
            color: #475569;
            margin-top: 12px;
            max-width: 680px;
        }
        .badge-pill {
            display: inline-flex;
            align-items: center;
            padding: 11px 18px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-weight: 600;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 18px;
        }
        .form-control,
        .form-select {
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            padding: 14px;
        }
        .input-group-text {
            border-radius: 14px 0 0 14px;
        }
        .table thead th {
            border-bottom: 2px solid #e2e8f0;
        }
        .form-row-table td,
        .form-row-table th {
            border: none;
            padding: 0.8rem;
            vertical-align: middle;
        }
        .form-row-table input {
            border-radius: 12px;
        }
        .summary-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 22px;
            margin-top: 18px;
        }
        .summary-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            color: #334155;
        }
        .summary-line strong {
            font-weight: 700;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 18px;
            border-radius: 18px;
            background: #4338ca;
            color: #fff;
            margin-top: 16px;
            font-weight: 700;
        }
        .btn-primary,
        .btn-outline-primary {
            border-radius: 999px;
            padding: 12px 24px;
        }
        .badge-soft {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card hero">
        <div>
            <a href="index.php" class="btn btn-light btn-sm">Back to Dashboard</a>
            <h1>Payroll Management</h1>
            <p></p>
        </div>
        <div class="badge-pill">Add Payroll</div>
    </div>

    <div class="card">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo safe($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo safe($error); ?></div>
        <?php endif; ?>

        <div class="section-title">Payroll details</div>
        <form method="POST" action="add_payroll.php" id="payrollForm">
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label" for="pay_period">Pay period</label>
                    <input id="pay_period" name="pay_period" type="text" class="form-control" placeholder="e.g. August 2026" value="<?php echo safe($post['pay_period'] ?? ''); ?>" required>
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="pay_date">Pay date</label>
                    <input id="pay_date" name="pay_date" type="date" class="form-control" value="<?php echo safe($post['pay_date'] ?? $payDateDefault); ?>" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Payroll and Send Payslips</button>
            </div>
        </form>
    </div>
</div>

<script>
</script>
</body>
</html>
