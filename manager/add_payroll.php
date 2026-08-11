<?php
session_start();
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
    $staffId = (int)($post['staff_id'] ?? 0);
    $regionId = (int)($post['region_id'] ?? 0);
    $payPeriod = trim($post['pay_period'] ?? '');
    $payDate = trim($post['pay_date'] ?? $payDateDefault);
    $basicSalary = 0;
    $advanceId = 0;
    $advanceAmount = 0;
    $deductions = [];

    $payDateObj = DateTime::createFromFormat('Y-m-d', $payDate);
    if (!$payDateObj || $payDateObj->format('Y-m-d') !== $payDate) {
        $payDate = '';
    }

    if ($staffId <= 0 || $regionId <= 0 || $payPeriod === '' || $payDate === '') {
        $error = 'Please select a staff member, choose a region, enter a pay period, and choose a valid pay date.';
    } elseif ($basicSalary < 0) {
        $error = 'Basic salary must be zero or higher.';
    } else {
        $staffStmt = $conn->prepare("SELECT id, name, email, COALESCE(basic_salary, 0) AS basic_salary FROM users WHERE id = ? LIMIT 1");
        $staffStmt->bind_param('i', $staffId);
        $staffStmt->execute();
        $staffRes = $staffStmt->get_result();
        $staff = $staffRes ? $staffRes->fetch_assoc() : null;
        $staffStmt->close();

        if (!$staff) {
            $error = 'The selected staff member was not found.';
        } else {
            $regionName = null;
            $regionStmt = $conn->prepare("SELECT area_name FROM areas WHERE area_id = ? LIMIT 1");
            $regionStmt->bind_param('i', $regionId);
            $regionStmt->execute();
            $regionRes = $regionStmt->get_result();
            $regionRow = $regionRes ? $regionRes->fetch_assoc() : null;
            $regionStmt->close();
            if ($regionRow) {
                $regionName = $regionRow['area_name'];
            } else {
                $error = 'Selected region was not found.';
            }
        }

        if (empty($error)) {
            $basicSalary = floatval($staff['basic_salary'] ?? 0);
            $grossPay = $basicSalary;

            $advanceStmt = $conn->prepare("SELECT id, monthly_deduction FROM advances WHERE loan_officer_id = ? AND balance > 0 ORDER BY id DESC LIMIT 1");
            $advanceStmt->bind_param('i', $staffId);
            $advanceStmt->execute();
            $advanceRes = $advanceStmt->get_result();
            $activeAdvance = $advanceRes ? $advanceRes->fetch_assoc() : null;
            $advanceStmt->close();

            if ($activeAdvance && floatval($activeAdvance['monthly_deduction']) > 0) {
                $advanceId = (int)$activeAdvance['id'];
                $advanceAmount = floatval($activeAdvance['monthly_deduction']);
                $deductions[] = ['label' => 'Advance', 'amount' => $advanceAmount];
            }

            $totalDeductions = 0;
            foreach ($deductions as $deduction) {
                $totalDeductions += floatval($deduction['amount']);
            }
            $netPay = $grossPay - $totalDeductions;

            $earningsJson = json_encode([]);
            $deductionsJson = json_encode($deductions);
            $recordedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $templateId = null;
            $templateNameToSave = null;
            $notes = null;

            $insertStmt = $conn->prepare("INSERT INTO staff_payrolls (
                template_id, template_name, staff_id, staff_name, staff_email,
                region_id, region_name,
                pay_period, pay_date, basic_salary, gross_pay, total_deductions,
                net_pay, earnings, deductions, notes, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $insertStmt->bind_param(
                'isississsddddsssi',
                $templateId,
                $templateNameToSave,
                $staff['id'],
                $staff['name'],
                $staff['email'],
                $regionId,
                $regionName,
                $payPeriod,
                $payDate,
                $basicSalary,
                $grossPay,
                $totalDeductions,
                $netPay,
                $earningsJson,
                $deductionsJson,
                $notes,
                $recordedBy
            );

            if ($insertStmt->execute()) {
                if ($advanceId > 0 && $advanceAmount > 0) {
                    $paymentDate = date('Y-m-d');
                    $advanceStmt = $conn->prepare("INSERT INTO advance_repayments (advance_id, amount, payment_date, recorded_by) VALUES (?, ?, ?, ?)");
                    $advanceStmt->bind_param('idsi', $advanceId, $advanceAmount, $paymentDate, $recordedBy);
                    if ($advanceStmt->execute()) {
                        $advanceStmt->close();
                        $upd = $conn->prepare("UPDATE advances SET balance = balance - ? WHERE id = ?");
                        $upd->bind_param('di', $advanceAmount, $advanceId);
                        $upd->execute();
                        $upd->close();
                        $message = 'Payroll saved and advance deduction applied for ' . safe($staff['name']) . '.';
                    } else {
                        $advanceStmt->close();
                        $message = 'Payroll saved, but advance deduction could not be applied.';
                    }
                } else {
                    $message = 'Payroll record saved successfully for ' . safe($staff['name']) . '.';
                }

                $payPeriod = '';
                $payDate = $payDateDefault;
                $basicSalary = '';
                $advanceId = 0;
                $advanceAmount = 0;
                $deductions = [];
                $insertStmt->close();

                $recentResult = $conn->query("SELECT id, template_name, staff_name, pay_period, pay_date, gross_pay, total_deductions, net_pay, created_at FROM staff_payrolls ORDER BY created_at DESC LIMIT 20");
                if ($recentResult) {
                    $recentPayrolls = $recentResult->fetch_all(MYSQLI_ASSOC);
                }
            } else {
                $error = 'Unable to save payroll record. Please try again.';
                $insertStmt->close();
            }
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
                <div class="col-lg-4">
                    <label class="form-label" for="region_id">Region</label>
                    <select id="region_id" name="region_id" class="form-select" required>
                        <option value="">Select region</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo (int)$region['area_id']; ?>"<?php echo (isset($post['region_id']) && (int)$post['region_id'] === (int)$region['area_id']) ? ' selected' : ''; ?>>
                                <?php echo safe($region['area_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="staff_id">Staff member</label>
                    <select id="staff_id" name="staff_id" class="form-select" required>
                        <option value="">Select staff</option>
                        <?php foreach ($staffs as $staff): ?>
                            <option value="<?php echo (int)$staff['id']; ?>"<?php echo (isset($post['staff_id']) && (int)$post['staff_id'] === (int)$staff['id']) ? ' selected' : ''; ?>>
                                <?php echo safe($staff['name'] . ' (' . $staff['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-lg-4">
                    <label class="form-label" for="pay_period">Pay period</label>
                    <input id="pay_period" name="pay_period" type="text" class="form-control" placeholder="e.g. August 2026" value="<?php echo safe($post['pay_period'] ?? ''); ?>" required>
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="pay_date">Pay date</label>
                    <input id="pay_date" name="pay_date" type="date" class="form-control" value="<?php echo safe($post['pay_date'] ?? $payDateDefault); ?>" required>
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="basic_salary">Basic salary</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input id="basic_salary" name="basic_salary" type="number" step="0.01" min="0" class="form-control" value="<?php echo safe(number_format($selectedStaffSalary ?: floatval($post['basic_salary'] ?? 0), 2)); ?>" readonly>
                    </div>
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="advance_amount">Advance deduction</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input id="advance_amount" name="advance_amount" type="number" step="0.01" min="0" class="form-control" value="0.00" readonly>
                    </div>
                    <input type="hidden" id="advance_id" name="advance_id" value="0">
                    <div class="form-text" id="advance_info">Active advance deduction will be applied automatically upon save.</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-line"><strong>Basic salary</strong><span id="displayBasic">KES <?php echo safe(number_format($selectedStaffSalary ?: floatval($post['basic_salary'] ?? 0), 2)); ?></span></div>
                <div class="summary-line"><strong>Advance deduction</strong><span id="displayDeductions">KES 0.00</span></div>
                <div class="summary-total"><span>Net pay</span><strong id="displayNet">KES 0.00</strong></div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save payroll</button>
            </div>
        </form>
    </div>
</div>

<script>
    function formatCurrency(value) {
        return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const allStaffs = <?php echo json_encode($staffs, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
    const activeAdvanceMap = <?php echo json_encode($activeAdvanceMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

    function filterStaffsByRegion(regionId) {
        return allStaffs.filter(staff => parseInt(staff.region_id || 0, 10) === regionId);
    }

    function populateStaffOptions() {
        const regionId = parseInt(document.querySelector('#region_id').value || 0, 10);
        const staffSelect = document.querySelector('#staff_id');
        const selectedStaff = parseInt(staffSelect.value || 0, 10);
        const options = [];

        const filteredStaffs = regionId > 0 ? filterStaffsByRegion(regionId) : allStaffs;
        filteredStaffs.forEach(staff => {
            const option = document.createElement('option');
            option.value = staff.id;
            option.textContent = staff.name + ' (' + staff.email + ')';
            if (staff.id === selectedStaff) {
                option.selected = true;
            }
            options.push(option);
        });

        staffSelect.innerHTML = '<option value="">Select staff</option>';
        options.forEach(option => staffSelect.appendChild(option));
        if (selectedStaff && !filteredStaffs.some(staff => staff.id === selectedStaff)) {
            staffSelect.value = '';
        }
    }

    function updateTotals() {
        const staffId = parseInt(document.querySelector('#staff_id').value || 0, 10);
        const staff = allStaffs.find(item => parseInt(item.id || 0, 10) === staffId) || { basic_salary: 0 };
        const basicSalary = parseFloat(staff.basic_salary || 0);
        const advance = activeAdvanceMap[staffId] || null;
        const advanceAmount = advance ? parseFloat(advance.monthly_deduction || 0) : 0;
        const netPay = basicSalary - advanceAmount;

        document.querySelector('#basic_salary').value = basicSalary.toFixed(2);
        document.querySelector('#advance_amount').value = advanceAmount.toFixed(2);
        document.querySelector('#advance_id').value = advance ? advance.id : 0;
        document.querySelector('#advance_info').textContent = advance ? 'Active advance deduction will apply automatically when payroll is saved.' : 'No active advance for selected staff.';
        document.querySelector('#displayBasic').textContent = 'KES ' + formatCurrency(basicSalary);
        document.querySelector('#displayDeductions').textContent = 'KES ' + formatCurrency(advanceAmount);
        document.querySelector('#displayNet').textContent = 'KES ' + formatCurrency(netPay);
    }

    document.querySelector('#region_id').addEventListener('change', function () {
        populateStaffOptions();
        updateTotals();
    });
    document.querySelector('#staff_id').addEventListener('change', updateTotals);

    populateStaffOptions();
    updateTotals();
</script>
</body>
</html>
