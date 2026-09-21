<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once 'db.php';

if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

$borrowers = [];
$borrowerResult = $conn->query("SELECT b.id, b.full_name, b.mobile, COALESCE(u.name, 'Unassigned') AS loan_officer_name
                               FROM borrowers b
                               LEFT JOIN users u ON b.loan_officer = u.email
                               ORDER BY b.full_name ASC");
if ($borrowerResult) {
    while ($row = $borrowerResult->fetch_assoc()) {
        $borrowers[] = $row;
    }
}

$loanProducts = getLoanProducts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Application Form</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background: #f8f8f8; color: #282828; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main { padding: 88px 24px 30px; }
        .page-container { max-width: 1180px; margin: 0 auto; }
        .shell { border: none; border-radius: 18px; box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08); background: #fff; }
        .company-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ef4444; padding-bottom: 18px; margin-bottom: 24px; }
        .company-header img { height: 70px; width: auto; object-fit: contain; }
        .section-panel { background: #fff7f7; border: 1px solid #f4d1d1; border-radius: 12px; padding: 20px; height: 100%; }
        .summary-panel { background: #fff; border: 1px solid #e7e7e7; border-radius: 16px; padding: 28px; min-height: 100%; }
        .section-title { color: #0b2f9f; font-size: 1.05rem; font-weight: 700; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #ef4444; }
        .form-label { font-weight: 600; }
        .form-control, .form-select { border-radius: 8px; border-color: #d9d9d9; }
        .form-control:focus, .form-select:focus { border-color: #0b2f9f; box-shadow: 0 0 0 0.2rem rgba(11, 47, 159, 0.12); }
        .btn-primary { background: linear-gradient(135deg, #e84545, #ff6b6b); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #d93d3d, #eb5a5a); }
        .metric { border-left: 4px solid #0b2f9f; padding: 12px 14px; background: #f7f9ff; border-radius: 8px; margin-bottom: 12px; }
        .metric strong { display: block; color: #0b2f9f; font-size: 1.15rem; }
        .required::after { content: ' *'; color: #e84545; }
        @media (max-width: 1199px) { .main { padding: 88px 16px 24px; } }
        @media (max-width: 768px) { .main { padding: 78px 10px 16px; } .company-header img { height: 52px; } }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="main">
    <div class="page-container">
        <div class="shell p-4">
            <div class="company-header">
                <div>
                    <div class="fw-bold text-uppercase text-secondary" style="letter-spacing: 1px;">Inua Premium Services</div>
                    <div class="text-muted small">Loan Application Department</div>
                </div>
                <img src="../assets/img/logo.png" alt="Inua Premium Services Logo">
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="mb-1">Loan Application Form</h2>
                    <div class="text-muted">Capture the borrower and facility details for review and processing.</div>
                </div>
                <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <form action="submit_loan_application.php" method="POST" id="loanForm" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="section-panel">
                            <div class="section-title">Borrower and Loan Details</div>
                            <div class="mb-3">
                                <label for="borrowerSearch" class="form-label">Search Borrower</label>
                                <input type="search" class="form-control" id="borrowerSearch" placeholder="Search by name or mobile">
                                <select class="form-select mt-2" name="borrower" id="borrower" required>
                                    <?php if ($borrowers): ?>
                                        <?php foreach ($borrowers as $borrower): ?>
                                            <option value="<?= (int) $borrower['id']; ?>" data-search="<?= htmlspecialchars(strtolower($borrower['full_name'] . ' ' . $borrower['mobile']), ENT_QUOTES); ?>">
                                                <?= htmlspecialchars($borrower['full_name']); ?> (<?= htmlspecialchars($borrower['mobile'] ?? ''); ?>) | Officer: <?= htmlspecialchars($borrower['loan_officer_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No borrowers found</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="loanProduct" class="form-label required">Loan Product</label>
                                <select class="form-select" id="loanProduct" name="loan_product" required>
                                    <?php foreach ($loanProducts as $product): ?>
                                        <option value="<?= (int) $product['id']; ?>"><?= htmlspecialchars($product['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6"><label for="principal" class="form-label required">Principal Amount</label><input type="number" class="form-control" id="principal" name="principal" min="0" step="0.01" required></div>
                                <div class="col-md-6"><label for="loanReleaseDate" class="form-label required">Loan Release Date</label><input type="date" class="form-control" id="loanReleaseDate" name="loan_release_date" value="<?= date('Y-m-d'); ?>" required></div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6"><label for="loanDuration" class="form-label required">Loan Duration</label><input type="number" class="form-control" id="loanDuration" name="loan_duration" min="1" step="1" required></div>
                                <div class="col-md-6"><label for="loanDurationUnit" class="form-label required">Duration Unit</label><select class="form-select" id="loanDurationUnit" name="loan_duration_unit" required><option value="days">Days</option><option value="weeks">Weeks</option><option value="months" selected>Months</option><option value="years">Years</option></select></div>
                            </div>

                            <div class="mb-3 mt-3"><label for="projectedMaturityDate" class="form-label">Projected Maturity Date</label><input type="date" class="form-control" id="projectedMaturityDate" name="projected_maturity_date" readonly></div>

                            <div class="row g-3">
                                <div class="col-md-6"><label for="interestMethod" class="form-label required">Interest Method</label><select class="form-select" id="interestMethod" name="interest_method" required><option value="flat_rate">Flat Rate</option><option value="percentage">Percentage</option><option value="fixed_amount">Fixed Amount Per Cycle</option></select></div>
                                <div class="col-md-6"><label for="interestCalculation" class="form-label required">Interest Calculation</label><select class="form-select" id="interestCalculation" name="interest_calculation" required><option value="weekly">Weekly</option><option value="monthly" selected>Monthly</option><option value="yearly">Yearly</option></select></div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6"><label for="loanInterestPercentage" class="form-label">Interest Rate / Amount</label><input type="number" class="form-control" id="loanInterestPercentage" name="loan_interest_percentage" min="0" step="0.01" value="0"></div>
                                <div class="col-md-6"><label for="repaymentCycle" class="form-label required">Repayment Cycle</label><select class="form-select" id="repaymentCycle" name="repayment_cycle" required><option value="daily">Daily</option><option value="weekly" selected>Weekly</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option><option value="once">Once</option></select></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="summary-panel">
                            <div class="section-title">Facility Summary</div>
                            <div class="row g-3">
                                <div class="col-md-6 col-lg-12"><label for="numberOfRepayments" class="form-label">Number of Repayments</label><input type="number" class="form-control" id="numberOfRepayments" name="number_of_repayments" readonly></div>
                                <div class="col-md-6 col-lg-12"><label for="processingFee" class="form-label required">Processing Fee</label><input type="number" class="form-control" id="processingFee" name="processing_fee" min="0" step="0.01" value="0" required></div>
                                <div class="col-md-6 col-lg-12"><label for="registrationFee" class="form-label required">Registration Fee</label><input type="number" class="form-control" id="registrationFee" name="registration_fee" min="0" step="0.01" value="0" required></div>
                            </div>
                            <div class="mt-4">
                                <div class="metric"><span>Total Interest</span><strong id="interestPreview">KES 0.00</strong></div>
                                <div class="metric"><span>Total Loan Amount</span><strong id="totalPreview">KES 0.00</strong><input type="hidden" id="totalAmount" name="total_amount" value="0"></div>
                                <div class="metric"><span>Total Inclusive of Fees</span><strong id="inclusivePreview">KES 0.00</strong><input type="hidden" id="totalAmountInclusive" name="total_amount_inclusive" value="0"></div>
                                <div class="metric"><span>Repayment Per Cycle</span><strong id="repaymentPreview">KES 0.00</strong><input type="hidden" id="repaymentAmount" name="repayment_amount" value="0"></div>
                            </div>
                            <div class="alert alert-light border mt-4 mb-4">Review all amounts and the projected maturity date before submitting the application.</div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">Submit Loan Application</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
 </main>

<script>
(function () {
    const form = document.getElementById('loanForm');
    const borrowerSearch = document.getElementById('borrowerSearch');
    const borrowerSelect = document.getElementById('borrower');

    borrowerSearch.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const visible = Array.from(borrowerSelect.options).filter(function (option) {
            const matches = (option.dataset.search || '').includes(query);
            option.hidden = !matches;
            return matches;
        });
        if (visible.length) borrowerSelect.value = visible[0].value;
    });

    function durationValues(duration, unit) {
        if (unit === 'days') return { days: duration, weeks: duration / 7, months: duration / 30, years: duration / 365 };
        if (unit === 'weeks') return { days: duration * 7, weeks: duration, months: duration / 4, years: duration / 52 };
        if (unit === 'years') return { days: duration * 365, weeks: duration * 52, months: duration * 12, years: duration };
        return { days: duration * 30, weeks: duration * 4, months: duration, years: duration / 12 };
    }

    function updateMaturity(duration, unit) {
        const release = document.getElementById('loanReleaseDate').value;
        const maturity = document.getElementById('projectedMaturityDate');
        if (!release || duration <= 0) { maturity.value = ''; return; }
        const date = new Date(release + 'T00:00:00');
        if (unit === 'days') date.setDate(date.getDate() + duration);
        else if (unit === 'weeks') date.setDate(date.getDate() + duration * 7);
        else if (unit === 'years') date.setFullYear(date.getFullYear() + duration);
        else date.setMonth(date.getMonth() + duration);
        maturity.value = date.toISOString().slice(0, 10);
    }

    function calculate() {
        const principal = parseFloat(document.getElementById('principal').value) || 0;
        const duration = parseFloat(document.getElementById('loanDuration').value) || 0;
        const unit = document.getElementById('loanDurationUnit').value;
        const interestMethod = document.getElementById('interestMethod').value;
        const interestCalculation = document.getElementById('interestCalculation').value;
        const cycle = document.getElementById('repaymentCycle').value;
        const rateOrAmount = parseFloat(document.getElementById('loanInterestPercentage').value) || 0;
        const processingFee = parseFloat(document.getElementById('processingFee').value) || 0;
        const registrationFee = parseFloat(document.getElementById('registrationFee').value) || 0;
        const periods = durationValues(duration, unit);
        const cycleKey = cycle === 'daily' ? 'days' : cycle === 'weekly' ? 'weeks' : cycle === 'yearly' ? 'years' : 'months';
        const interestKey = interestCalculation === 'weekly' ? 'weeks' : interestCalculation === 'yearly' ? 'years' : 'months';
        const repayments = cycle === 'once' ? 1 : Math.max(0, Math.round(periods[cycleKey]));
        const interestPeriods = periods[interestKey];
        const interest = interestMethod === 'fixed_amount' ? rateOrAmount * repayments : principal * (rateOrAmount / 100) * interestPeriods;
        const total = principal + interest;
        const inclusive = total + processingFee + registrationFee;
        const repayment = repayments > 0 ? total / repayments : 0;

        document.getElementById('numberOfRepayments').value = repayments;
        document.getElementById('totalAmount').value = total.toFixed(2);
        document.getElementById('totalAmountInclusive').value = inclusive.toFixed(2);
        document.getElementById('repaymentAmount').value = repayment.toFixed(2);
        document.getElementById('interestPreview').textContent = 'KES ' + interest.toFixed(2);
        document.getElementById('totalPreview').textContent = 'KES ' + total.toFixed(2);
        document.getElementById('inclusivePreview').textContent = 'KES ' + inclusive.toFixed(2);
        document.getElementById('repaymentPreview').textContent = 'KES ' + repayment.toFixed(2);
        updateMaturity(duration, unit);
    }

    form.addEventListener('input', calculate);
    form.addEventListener('change', calculate);
    calculate();
})();
</script>
</body>
</html>
