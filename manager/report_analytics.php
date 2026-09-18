<?php
include 'db.php';

function getBorrowerLoans($conn, $borrowerId, $startDate = null, $endDate = null, $processingFeeColumn = null, $registrationFeeColumn = null) {
    $processingFeeSelect = $processingFeeColumn ? "COALESCE(la.$processingFeeColumn, 0) AS processing_fee" : '0 AS processing_fee';
    $registrationFeeSelect = $registrationFeeColumn ? "COALESCE(la.$registrationFeeColumn, 0) AS registration_fee" : '0 AS registration_fee';

    $sql = "SELECT la.id, la.principal, la.total_amount, la.loan_release_date,
                   (la.total_amount - la.principal) AS interest,
                   $processingFeeSelect,
                   $registrationFeeSelect,
                   COALESCE(SUM(r.paid), 0) + COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = la.id), 0) AS total_paid
            FROM loan_applications la
            LEFT JOIN repayments r ON r.loan_id = la.id
            WHERE la.borrower = ? AND la.loan_status = 'approved'";

    $params = [$borrowerId];
    $types = 'i';

    if (!empty($startDate) && !empty($endDate)) {
        $sql .= " AND la.loan_release_date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= 'ss';
    }

    $sql .= ' GROUP BY la.id';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getBorrowerOverdue($conn, $borrowerId, $startDate = null, $endDate = null) {
    $sql = "SELECT GREATEST(
            COALESCE(SUM(CASE
                WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0)
                ELSE 0
            END), 0)
            - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0),
            0
        ) AS total_overdue
        FROM borrowers
        LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
        LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
        WHERE borrowers.id = ? AND loan_applications.loan_status = 'approved'";

    $params = [$borrowerId];
    $types = 'i';

    if (!empty($startDate) && !empty($endDate)) {
        $sql .= ' AND loan_applications.loan_release_date BETWEEN ? AND ?';
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= 'ss';
    }

    $sql .= ' GROUP BY borrowers.id';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0.0;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (float) ($row['total_overdue'] ?? 0.0);
}

function findFeeColumn($conn, $candidates = []) {
    if (empty($candidates)) {
        $candidates = ['processing_fee', 'processingfee', 'processing_fee_kes', 'reg_fee', 'registration_fee', 'registration_fees', 'regfee'];
    }

    foreach ($candidates as $col) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_applications' AND COLUMN_NAME = ?");
        if (!$stmt) continue;
        $stmt->bind_param('s', $col);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!empty($res) && (int) $res['cnt'] > 0) {
            return $col;
        }
    }
    return null;
}

$reportScope = isset($_GET['report_scope']) ? $_GET['report_scope'] : 'all';
$selectedYear = isset($_GET['report_year']) ? (int) $_GET['report_year'] : 0;
$selectedMonth = isset($_GET['report_month']) ? (int) $_GET['report_month'] : 0;
$selectedWeek = isset($_GET['report_week']) ? (int) $_GET['report_week'] : 0;
$selected_area = isset($_GET['area_id']) ? $_GET['area_id'] : 'all';
$selected_area = ($selected_area !== 'all' && !is_numeric($selected_area)) ? 'all' : $selected_area;
$selected_officer = isset($_GET['officer_id']) ? $_GET['officer_id'] : 'all';
$selected_officer = ($selected_officer !== 'all' && !is_numeric($selected_officer)) ? 'all' : $selected_officer;

$areas = [];
$areasStmt = $conn->prepare('SELECT area_id, area_name FROM areas ORDER BY area_name');
$areasStmt->execute();
$areasResult = $areasStmt->get_result();
while ($area = $areasResult->fetch_assoc()) {
    $areas[] = $area;
}

$selectedYear = $selectedYear > 0 ? max(2000, min(2100, $selectedYear)) : 0;
$selectedMonth = $selectedMonth > 0 ? max(1, min(12, $selectedMonth)) : 0;
$selectedWeek = max(0, min(4, $selectedWeek));

$periodStart = null;
$periodEnd = null;
$periodLabel = 'General report';

if ($reportScope === 'custom' && ($selectedYear > 0 || $selectedMonth > 0 || $selectedWeek > 0)) {
    if ($selectedWeek > 0) {
        $periodStart = sprintf('%04d-%02d-%02d', $selectedYear > 0 ? $selectedYear : date('Y'), $selectedMonth > 0 ? $selectedMonth : date('m'), 1 + (($selectedWeek - 1) * 7));
        $periodEnd = date('Y-m-d', strtotime($periodStart . ' +6 days'));
        $periodLabel = 'Week ' . $selectedWeek . ' of ' . date('F Y', strtotime($periodStart));
    } elseif ($selectedYear > 0 && $selectedMonth > 0) {
        $periodStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $periodLabel = date('F Y', strtotime($periodStart));
    } elseif ($selectedYear > 0) {
        $periodStart = sprintf('%04d-01-01', $selectedYear);
        $periodEnd = sprintf('%04d-12-31', $selectedYear);
        $periodLabel = 'Year ' . $selectedYear;
    }
}

$officerSql = "SELECT id, name AS full_name, email, area FROM users WHERE role_id = '2'";
$officerParams = [];
$officerTypes = '';
if ($selected_area !== 'all') {
    $officerSql .= ' AND area = ?';
    $officerParams[] = (int) $selected_area;
    $officerTypes .= 'i';
}
if ($selected_officer !== 'all') {
    $officerSql .= ' AND id = ?';
    $officerParams[] = (int) $selected_officer;
    $officerTypes .= 'i';
}
$officerSql .= ' ORDER BY name';
$officerStmt = $conn->prepare($officerSql);
if (!empty($officerParams)) {
    $officerStmt->bind_param($officerTypes, ...$officerParams);
}
$officerStmt->execute();
$officerResult = $officerStmt->get_result();
$officerOptions = [];
while ($officer = $officerResult->fetch_assoc()) {
    $officerOptions[] = $officer;
}

$processingFeeColumn = findFeeColumn($conn, ['processing_fee', 'processingfee', 'processing_fee_kes']);
$registrationFeeColumn = findFeeColumn($conn, ['registration_fee', 'registration_fees', 'reg_fee', 'regfee']);

$officerData = [];
foreach ($officerOptions as $officer) {
    $officerEmail = $officer['email'];
    $borrowerStmt = $conn->prepare('SELECT id FROM borrowers WHERE loan_officer = ?');
    $borrowerStmt->bind_param('s', $officerEmail);
    $borrowerStmt->execute();
    $borrowerResult = $borrowerStmt->get_result();

    $principal = 0.0;
    $loanBook = 0.0;
    $arrearsValue = 0.0;
    $paidAmount = 0.0;
    $customers = 0;
    $customersInArrears = 0;
    $fundedCustomers = 0;
    $disbursementValue = 0.0;
    $interestTotal = 0.0;
    $processingFeeTotal = 0.0;
    $registrationFeeTotal = 0.0;

    while ($borrower = $borrowerResult->fetch_assoc()) {
        $borrowerId = (int) $borrower['id'];
        $borrowerLoans = getBorrowerLoans($conn, $borrowerId, $periodStart, $periodEnd, $processingFeeColumn, $registrationFeeColumn);
        if (empty($borrowerLoans)) {
            continue;
        }

        $borrowerHasActiveBalance = false;
        $borrowerFundedInPeriod = false;

        foreach ($borrowerLoans as $loan) {
            $loanPrincipal = (float) $loan['principal'];
            $loanAmount = (float) $loan['total_amount'];
            $loanPaid = (float) $loan['total_paid'];
            $loanOutstanding = max(0.0, $loanAmount - $loanPaid);

            $loanProcessingFee = (float) ($loan['processing_fee'] ?? 0.0);
            $loanRegistrationFee = (float) ($loan['registration_fee'] ?? 0.0);
            $loanInterest = (float) ($loan['interest'] ?? max(0.0, $loanAmount - $loanPrincipal));

            $principal += $loanPrincipal;
            $loanBook += $loanOutstanding;
            $paidAmount += $loanPaid;
            $disbursementValue += $loanPrincipal;
            $interestTotal += $loanInterest;
            $processingFeeTotal += $loanProcessingFee;
            $registrationFeeTotal += $loanRegistrationFee;

            if ($loanOutstanding > 0) {
                $borrowerHasActiveBalance = true;
            }

            $loanReleaseDate = $loan['loan_release_date'];
            if (empty($periodStart) || empty($periodEnd)) {
                $borrowerFundedInPeriod = true;
            } elseif (!empty($loanReleaseDate) && $loanReleaseDate >= $periodStart && $loanReleaseDate <= $periodEnd) {
                $borrowerFundedInPeriod = true;
            }
        }

        if ($borrowerHasActiveBalance) {
            $customers++;
        }
        if ($borrowerFundedInPeriod) {
            $fundedCustomers++;
        }

        $borrowerOverdue = getBorrowerOverdue($conn, $borrowerId, $periodStart, $periodEnd);
        if ($borrowerOverdue > 0 && $borrowerHasActiveBalance) {
            $customersInArrears++;
        }

        $arrearsValue += $borrowerOverdue;
    }

    $performingBook = max(0.0, $loanBook - $arrearsValue);
    $parPercentage = $loanBook > 0 ? ($arrearsValue / $loanBook) * 100 : 0;
    $customersInArrearsPercentage = $customers > 0 ? ($customersInArrears / $customers) * 100 : 0;

    $officerData[] = [
        'name' => $officer['full_name'],
        'principal' => round($principal, 2),
        'loanBook' => round($loanBook, 2),
        'performingBook' => round($performingBook, 2),
        'arrearsValue' => round($arrearsValue, 2),
        'interest' => round($interestTotal, 2),
        'processingFee' => round($processingFeeTotal, 2),
        'registrationFee' => round($registrationFeeTotal, 2),
        'par' => round($parPercentage, 2),
        'customers' => (int) $customers,
        'customersInArrears' => (int) $customersInArrears,
        'customersInArrearsPercentage' => round($customersInArrearsPercentage, 2),
        'recruitedCustomers' => (int) $fundedCustomers,
        'fundedCustomers' => (int) $fundedCustomers,
        'disbursementValue' => round($disbursementValue, 2),
        'periodLabel' => $periodLabel,
    ];
}

$monthlyActivityMap = [];

$trendSql = "SELECT DATE_FORMAT(loan_release_date, '%Y-%m') AS month_key,
                SUM(principal) AS principal_value,
                SUM((total_amount - principal)) AS interest_value
            FROM loan_applications
            WHERE loan_status = 'approved'";
if ($reportScope === 'custom' && !empty($periodStart) && !empty($periodEnd)) {
    $trendSql .= ' AND loan_release_date BETWEEN ? AND ?';
    $trendSql .= " GROUP BY DATE_FORMAT(loan_release_date, '%Y-%m') ORDER BY month_key ASC";
    $trendStmt = $conn->prepare($trendSql);
    $trendStmt->bind_param('ss', $periodStart, $periodEnd);
    $trendStmt->execute();
} else {
    $trendSql .= " GROUP BY DATE_FORMAT(loan_release_date, '%Y-%m') ORDER BY month_key ASC";
    $trendStmt = $conn->prepare($trendSql);
    $trendStmt->execute();
}
$trendRows = $trendStmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($trendRows as $row) {
    $monthKey = (string) ($row['month_key'] ?? '');
    if ($monthKey === '') {
        continue;
    }

    $monthlyActivityMap[$monthKey] = [
        'loan_book' => (float) ($row['principal_value'] ?? 0),
        'interest' => (float) ($row['interest_value'] ?? 0),
        'expense' => 0.0,
    ];
}

$expenseTrendSql = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month_key,
                    SUM(amount) AS expense_value
                FROM loan_officer_expenses
                WHERE 1=1";
if ($reportScope === 'custom' && !empty($periodStart) && !empty($periodEnd)) {
    $expenseTrendSql .= ' AND expense_date BETWEEN ? AND ?';
    $expenseTrendSql .= " GROUP BY DATE_FORMAT(expense_date, '%Y-%m') ORDER BY month_key ASC";
    $expenseTrendStmt = $conn->prepare($expenseTrendSql);
    $expenseTrendStmt->bind_param('ss', $periodStart, $periodEnd);
    $expenseTrendStmt->execute();
} else {
    $expenseTrendSql .= " GROUP BY DATE_FORMAT(expense_date, '%Y-%m') ORDER BY month_key ASC";
    $expenseTrendStmt = $conn->prepare($expenseTrendSql);
    $expenseTrendStmt->execute();
}
$expenseTrendRows = $expenseTrendStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($expenseTrendRows as $row) {
    $monthKey = (string) ($row['month_key'] ?? '');
    if ($monthKey === '') {
        continue;
    }
    if (!isset($monthlyActivityMap[$monthKey])) {
        $monthlyActivityMap[$monthKey] = ['loan_book' => 0.0, 'interest' => 0.0, 'expense' => 0.0];
    }
    $monthlyActivityMap[$monthKey]['expense'] = (float) ($row['expense_value'] ?? 0.0);
}

uksort($monthlyActivityMap, fn ($a, $b) => strcmp($a, $b));

$trendLabels = [];
$trendLoanBook = [];
$trendInterest = [];
$trendExpenses = [];
foreach ($monthlyActivityMap as $monthKey => $monthData) {
    $trendLabels[] = date('M Y', strtotime($monthKey . '-01'));
    $trendLoanBook[] = (float) ($monthData['loan_book'] ?? 0);
    $trendInterest[] = (float) ($monthData['interest'] ?? 0);
    $trendExpenses[] = (float) ($monthData['expense'] ?? 0);
}

$expenseOfficerSql = "SELECT loan_officer_name AS officer_name, SUM(amount) AS total_expense
                    FROM loan_officer_expenses
                    WHERE 1=1";
$expenseOfficerParams = [];
$expenseOfficerTypes = '';
if ($reportScope === 'custom' && !empty($periodStart) && !empty($periodEnd)) {
    $expenseOfficerSql .= ' AND expense_date BETWEEN ? AND ?';
    $expenseOfficerParams[] = $periodStart;
    $expenseOfficerParams[] = $periodEnd;
    $expenseOfficerTypes = 'ss';
}
$expenseOfficerSql .= ' GROUP BY loan_officer_name ORDER BY total_expense DESC LIMIT 6';
$expenseOfficerStmt = $conn->prepare($expenseOfficerSql);
if (!empty($expenseOfficerParams)) {
    $expenseOfficerStmt->bind_param($expenseOfficerTypes, ...$expenseOfficerParams);
}
$expenseOfficerStmt->execute();
$expenseOfficerRows = $expenseOfficerStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$expenseOfficerLabels = [];
$expenseOfficerValues = [];
foreach ($expenseOfficerRows as $row) {
    $expenseOfficerLabels[] = $row['officer_name'] ?? 'Unknown';
    $expenseOfficerValues[] = (float) ($row['total_expense'] ?? 0);
}

function pickExtremeOfficer(array $officerData, string $metric, bool $highest = true): array {
    if (empty($officerData)) {
        return ['name' => 'N/A'];
    }

    $winner = $officerData[0];
    $bestValue = (float) ($winner[$metric] ?? 0.0);

    foreach ($officerData as $officer) {
        $currentValue = (float) ($officer[$metric] ?? 0.0);
        if (($highest && $currentValue > $bestValue) || (!$highest && $currentValue < $bestValue)) {
            $winner = $officer;
            $bestValue = $currentValue;
        }
    }

    return $winner;
}

$overallTotalLoanBook = array_sum(array_column($officerData, 'loanBook'));
$overallArrears = array_sum(array_column($officerData, 'arrearsValue'));
$overallCustomers = array_sum(array_column($officerData, 'customers'));
$overallCustomersInArrears = array_sum(array_column($officerData, 'customersInArrears'));
$overallInterest = array_sum(array_column($officerData, 'interest'));
$overallExpenses = 0.0;
$expenseStatement = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM loan_officer_expenses WHERE 1=1");
if ($reportScope === 'custom' && !empty($periodStart) && !empty($periodEnd)) {
    $expenseStatement = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM loan_officer_expenses WHERE expense_date BETWEEN ? AND ?");
    $expenseStatement->bind_param('ss', $periodStart, $periodEnd);
}
$expenseStatement->execute();
$expenseStatementResult = $expenseStatement->get_result()->fetch_assoc();
$overallExpenses = (float) ($expenseStatementResult['total_expenses'] ?? 0.0);
$overallPar = $overallTotalLoanBook > 0 ? ($overallArrears / $overallTotalLoanBook) * 100 : 0;
$overallCustomerArrearsPct = $overallCustomers > 0 ? ($overallCustomersInArrears / $overallCustomers) * 100 : 0;
$netOperatingYield = $overallInterest - $overallExpenses;
$topOfficer = pickExtremeOfficer($officerData, 'loanBook', true);
$riskOfficer = pickExtremeOfficer($officerData, 'par', true);

$performanceInsights = [];
if (!empty($officerData)) {
    $avgPar = array_sum(array_column($officerData, 'par')) / count($officerData);
    $performanceInsights[] = 'Portfolio average PAR stands at ' . number_format($avgPar, 2) . '%, which indicates the current portfolio quality trend.';
    $performanceInsights[] = 'The largest book is held by ' . htmlspecialchars($topOfficer['name']) . ' with KES ' . number_format((float) $topOfficer['loanBook'], 2) . ' in outstanding portfolio.';
    $performanceInsights[] = 'Highest risk exposure is recorded with ' . htmlspecialchars($riskOfficer['name']) . ' at ' . number_format((float) $riskOfficer['par'], 2) . '% PAR, warranting closer review.';
    $performanceInsights[] = 'Customers in arrears represent ' . number_format($overallCustomerArrearsPct, 2) . '% of active customers, which signals the need for targeted collections interventions.';
    $performanceInsights[] = 'Interest income totals KES ' . number_format($overallInterest, 2) . ' while expenses stand at KES ' . number_format($overallExpenses, 2) . ', leaving a net operating yield of KES ' . number_format($netOperatingYield, 2) . '.';
    $performanceInsights[] = 'The organization should monitor whether the expense base remains well below the revenue generated from portfolio growth and interest accumulation.';
}

$comparisonTable = [];
foreach ($officerData as $officer) {
    $comparisonTable[] = [
        'name' => $officer['name'],
        'loanBook' => (float) $officer['loanBook'],
        'par' => (float) $officer['par'],
        'arrears' => (float) $officer['arrearsValue'],
        'customers' => (int) $officer['customers'],
        'activeCustomersInArrears' => (int) $officer['customersInArrears'],
        'percentageInArrears' => (float) $officer['customersInArrearsPercentage'],
    ];
}
usort($comparisonTable, fn ($a, $b) => $b['par'] <=> $a['par']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytical Reporting Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 35%, #f8fafc 100%);
            color: #0f172a;
        }
        .glass {
            background: rgba(255,255,255,0.86);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(148,163,184,0.2);
        }
        .metric-card {
            border-radius: 20px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }
        .chart-shell {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.06);
        }
        .insight-box {
            border-left: 5px solid #2563eb;
            background: linear-gradient(90deg, rgba(37,99,235,0.08), rgba(255,255,255,1));
        }
        table th {
            background: #f8fafc;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row items-center justify-between gap-4 mb-8">
            <div>
                <p class="text-sm uppercase tracking-[0.2em] text-blue-600 font-bold">Analytical Tool</p>
                <h1 class="text-4xl font-black text-slate-900">Portfolio Intelligence & Performance Analytics</h1>
            </div>
            <div class="flex gap-3">
                <a href="par.php<?php echo isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="inline-flex items-center gap-2 bg-slate-800 text-white px-5 py-3 rounded-xl font-semibold hover:bg-slate-700 shadow-lg">
                    ← Back to PAR Dashboard
                </a>
            </div>
        </div>

        <div class="glass p-5 rounded-2xl mb-8">
            <div class="flex flex-wrap gap-2">
                <span class="px-3 py-2 rounded-full bg-blue-100 text-blue-700 text-sm font-semibold">Period: <?php echo htmlspecialchars($periodLabel); ?></span>
                <span class="px-3 py-2 rounded-full bg-emerald-100 text-emerald-700 text-sm font-semibold">Total officers: <?php echo count($officerData); ?></span>
                <span class="px-3 py-2 rounded-full bg-violet-100 text-violet-700 text-sm font-semibold">Analysis depth: Detailed</span>
            </div>
        </div>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div class="metric-card p-6 bg-gradient-to-br from-blue-600 to-blue-500 text-white">
                <p class="text-sm uppercase tracking-wide text-blue-100">Total Loan Book</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($overallTotalLoanBook, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-amber-500 to-orange-500 text-white">
                <p class="text-sm uppercase tracking-wide text-orange-100">Total Arrears</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($overallArrears, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-emerald-500 to-teal-500 text-white">
                <p class="text-sm uppercase tracking-wide text-emerald-100">Interest Income</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($overallInterest, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-rose-500 to-red-500 text-white">
                <p class="text-sm uppercase tracking-wide text-rose-100">Portfolio PAR</p>
                <h3 class="text-3xl font-black mt-3"><?php echo number_format($overallPar, 2); ?>%</h3>
            </div>
        </section>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div class="metric-card p-6 bg-gradient-to-br from-violet-600 to-indigo-500 text-white">
                <p class="text-sm uppercase tracking-wide text-violet-100">Total Expenses</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($overallExpenses, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-cyan-600 to-sky-500 text-white">
                <p class="text-sm uppercase tracking-wide text-cyan-100">Net Operating Yield</p>
                <h3 class="text-3xl font-black mt-3">KES <?php echo number_format($netOperatingYield, 2); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-lime-600 to-green-500 text-white">
                <p class="text-sm uppercase tracking-wide text-lime-100">Active Customers</p>
                <h3 class="text-3xl font-black mt-3"><?php echo number_format($overallCustomers, 0); ?></h3>
            </div>
            <div class="metric-card p-6 bg-gradient-to-br from-slate-700 to-slate-600 text-white">
                <p class="text-sm uppercase tracking-wide text-slate-100">Customer Arrears</p>
                <h3 class="text-3xl font-black mt-3"><?php echo number_format($overallCustomerArrearsPct, 2); ?>%</h3>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
            <div class="chart-shell p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-900">Portfolio Trend Analysis</h2>
                    <span class="text-xs font-semibold uppercase text-slate-500">Loan book + interest + expenses</span>
                </div>
                <div class="h-[360px]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="chart-shell p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-900">PAR Mix Snapshot</h2>
                    <span class="text-xs font-semibold uppercase text-slate-500">Risk split</span>
                </div>
                <div class="h-[360px]">
                    <canvas id="parChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
            <div class="chart-shell p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-900">Monthly Performance Summary</h2>
                    <span class="text-xs font-semibold uppercase text-slate-500">Interest vs expense efficiency</span>
                </div>
                <div class="h-[360px]">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
            <div class="chart-shell p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-900">Top Expense Contributors</h2>
                    <span class="text-xs font-semibold uppercase text-slate-500">By officer</span>
                </div>
                <div class="h-[360px]">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
            <div class="chart-shell p-5 xl:col-span-2">
                <h2 class="text-xl font-bold text-slate-900 mb-5">Officer Performance Ranking</h2>
                <div class="h-[420px]">
                    <canvas id="officerChart"></canvas>
                </div>
            </div>
            <div class="chart-shell p-5">
                <h2 class="text-xl font-bold text-slate-900 mb-5">Executive Insights</h2>
                <div class="space-y-4">
                    <?php foreach ($performanceInsights as $insight): ?>
                        <div class="insight-box rounded-xl p-4 text-sm text-slate-700">
                            <?php echo htmlspecialchars($insight); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="chart-shell p-5 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
                <h2 class="text-xl font-bold text-slate-900">Detailed Officer Analytics</h2>
                <div class="text-sm text-slate-600">Risk, customer base, and operational exposure overview</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 border-b border-slate-200">Officer</th>
                            <th class="px-4 py-3 border-b border-slate-200">Loan Book</th>
                            <th class="px-4 py-3 border-b border-slate-200">Arrears</th>
                            <th class="px-4 py-3 border-b border-slate-200">PAR %</th>
                            <th class="px-4 py-3 border-b border-slate-200">Active Customers</th>
                            <th class="px-4 py-3 border-b border-slate-200">In Arrears</th>
                            <th class="px-4 py-3 border-b border-slate-200">% in Arrears</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparisonTable as $entry): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 border-b border-slate-100 font-semibold"><?php echo htmlspecialchars($entry['name']); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100">KES <?php echo number_format($entry['loanBook'], 2); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100">KES <?php echo number_format($entry['arrears'], 2); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100"><?php echo number_format($entry['par'], 2); ?>%</td>
                                <td class="px-4 py-3 border-b border-slate-100"><?php echo number_format($entry['customers'], 0); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100"><?php echo number_format($entry['activeCustomersInArrears'], 0); ?></td>
                                <td class="px-4 py-3 border-b border-slate-100"><?php echo number_format($entry['percentageInArrears'], 2); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <div class="chart-shell p-5">
                <h2 class="text-xl font-bold text-slate-900 mb-5">Customer Risk Exposure</h2>
                <div class="h-[300px]">
                    <canvas id="customerRiskChart"></canvas>
                </div>
            </div>
            <div class="chart-shell p-5">
                <h2 class="text-xl font-bold text-slate-900 mb-5">Portfolio Quality Summary</h2>
                <div class="space-y-5 text-sm text-slate-700">
                    <div class="flex justify-between items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span>Total customers in arrears</span>
                        <strong class="text-slate-900"><?php echo number_format($overallCustomersInArrears, 0); ?></strong>
                    </div>
                    <div class="flex justify-between items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span>Customer arrears percentage</span>
                        <strong class="text-slate-900"><?php echo number_format($overallCustomerArrearsPct, 2); ?>%</strong>
                    </div>
                    <div class="flex justify-between items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span>Largest portfolio owner</span>
                        <strong class="text-slate-900"><?php echo htmlspecialchars($topOfficer['name']); ?></strong>
                    </div>
                    <div class="flex justify-between items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span>Highest PAR risk</span>
                        <strong class="text-slate-900"><?php echo htmlspecialchars($riskOfficer['name']); ?> (<?php echo number_format((float) $riskOfficer['par'], 2); ?>%)</strong>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        const officerData = <?php echo json_encode($officerData); ?>;
        const sortedOfficers = [...officerData].sort((a, b) => Number(b.par) - Number(a.par));
        const officerNames = sortedOfficers.map(item => item.name);
        const officerParValues = sortedOfficers.map(item => Number(item.par || 0));
        const officerArrearsValues = sortedOfficers.map(item => Number(item.arrearsValue || 0));
        const loanBookValues = sortedOfficers.map(item => Number(item.loanBook || 0));

        const trendLabels = <?php echo json_encode($trendLabels); ?>;
        const trendLoanBook = <?php echo json_encode($trendLoanBook); ?>;
        const trendInterest = <?php echo json_encode($trendInterest); ?>;
        const trendExpenses = <?php echo json_encode($trendExpenses); ?>;
        const expenseOfficerLabels = <?php echo json_encode($expenseOfficerLabels); ?>;
        const expenseOfficerValues = <?php echo json_encode($expenseOfficerValues); ?>;

        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendLabels.length ? trendLabels : ['No data'],
                datasets: [
                    {
                        label: 'Loan Book',
                        data: trendLoanBook.length ? trendLoanBook : [0],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.10)',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Interest Income',
                        data: trendInterest.length ? trendInterest : [0],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.08)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Expenses',
                        data: trendExpenses.length ? trendExpenses : [0],
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249,115,22,0.08)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { ticks: { callback: value => 'KES ' + Number(value).toLocaleString() } }
                }
            }
        });

        new Chart(document.getElementById('performanceChart'), {
            type: 'bar',
            data: {
                labels: trendLabels.length ? trendLabels : ['No data'],
                datasets: [
                    {
                        label: 'Interest',
                        data: trendInterest.length ? trendInterest : [0],
                        backgroundColor: 'rgba(16,185,129,0.85)',
                        borderRadius: 8
                    },
                    {
                        label: 'Expenses',
                        data: trendExpenses.length ? trendExpenses : [0],
                        backgroundColor: 'rgba(249,115,22,0.85)',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { ticks: { callback: value => 'KES ' + Number(value).toLocaleString() } }
                }
            }
        });

        new Chart(document.getElementById('expenseChart'), {
            type: 'doughnut',
            data: {
                labels: expenseOfficerLabels.length ? expenseOfficerLabels : ['No expense data'],
                datasets: [{
                    data: expenseOfficerValues.length ? expenseOfficerValues : [0],
                    backgroundColor: ['#8b5cf6', '#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#14b8a6'],
                    borderWidth: 2,
                    hoverOffset: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: context => 'KES ' + Number(context.parsed).toLocaleString() } }
                }
            }
        });

        new Chart(document.getElementById('parChart'), {
            type: 'doughnut',
            data: {
                labels: ['Performing Book', 'Arrears'],
                datasets: [{
                    data: [
                        <?php echo max(0, $overallTotalLoanBook - $overallArrears); ?>,
                        <?php echo max(0, $overallArrears); ?>
                    ],
                    backgroundColor: ['#10b981', '#f97316'],
                    borderWidth: 2,
                    hoverOffset: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: context => 'KES ' + Number(context.parsed).toLocaleString() } }
                }
            }
        });

        new Chart(document.getElementById('officerChart'), {
            type: 'bar',
            data: {
                labels: officerNames,
                datasets: [
                    {
                        label: 'PAR %',
                        data: officerParValues,
                        backgroundColor: '#2563eb',
                        borderRadius: 8
                    },
                    {
                        label: 'Arrears (KES)',
                        data: officerArrearsValues,
                        backgroundColor: '#f97316',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: { callback: value => Number(value).toLocaleString() }
                    }
                }
            }
        });

        new Chart(document.getElementById('customerRiskChart'), {
            type: 'radar',
            data: {
                labels: ['Risk', 'Customer Base', 'Arrears', 'Performance', 'Book Size'],
                datasets: [{
                    label: 'Portfolio Health Index',
                    data: [
                        <?php echo min(100, max(0, $overallPar)); ?>,
                        <?php echo min(100, max(0, ($overallCustomers / (count($officerData) || 1)) * 10)); ?>,
                        <?php echo min(100, max(0, $overallCustomerArrearsPct)); ?>,
                        <?php echo min(100, max(0, 100 - $overallPar)); ?>,
                        <?php echo min(100, max(0, ($overallTotalLoanBook / (count($officerData) || 1)) / 10000)); ?>
                    ],
                    backgroundColor: 'rgba(37,99,235,0.20)',
                    borderColor: '#2563eb',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
