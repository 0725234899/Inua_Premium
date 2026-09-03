<?php
include 'db.php';

function getBorrowerLoans($conn, $borrowerId, $startDate = null, $endDate = null, $processingFeeColumn = null, $registrationFeeColumn = null) {
    $processingFeeSelect = $processingFeeColumn ? "COALESCE(la.$processingFeeColumn, 0) AS processing_fee" : '0 AS processing_fee';
    $registrationFeeSelect = $registrationFeeColumn ? "COALESCE(la.$registrationFeeColumn, 0) AS registration_fee" : '0 AS registration_fee';

    $sql = "SELECT la.id, la.principal, la.total_amount, la.loan_release_date,
                   (la.total_amount - la.principal) AS interest,
                   $processingFeeSelect,
                   $registrationFeeSelect,
                   COALESCE(SUM(r.paid), 0) AS total_paid
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

    $sql .= " GROUP BY la.id";

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
        $sql .= " AND loan_applications.loan_release_date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= 'ss';
    }

    $sql .= " GROUP BY borrowers.id";

    $overdueStmt = $conn->prepare($sql);
    if (!$overdueStmt) {
        return 0.0;
    }

    $overdueStmt->bind_param($types, ...$params);
    $overdueStmt->execute();
    $overdueResult = $overdueStmt->get_result();
    $overdueRow = $overdueResult->fetch_assoc();

    return (float) ($overdueRow['total_overdue'] ?? 0.0);
}

// Detect if a processing/registration fee column exists in loan_applications
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
        if (!empty($res) && (int)$res['cnt'] > 0) {
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
$areasStmt = $conn->prepare("SELECT area_id, area_name FROM areas ORDER BY area_name");
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
    $officerSql .= " AND area = ?";
    $officerParams[] = (int) $selected_area;
    $officerTypes .= 'i';
}
if ($selected_officer !== 'all') {
    $officerSql .= " AND id = ?";
    $officerParams[] = (int) $selected_officer;
    $officerTypes .= 'i';
}
$officerSql .= " ORDER BY name";
$officerStmt = $conn->prepare($officerSql);
if (!empty($officerParams)) {
    $officerStmt->bind_param($officerTypes, ...$officerParams);
}
$officerStmt->execute();
$officerResult = $officerStmt->get_result();

$officerData = array();
$officerOptions = [];

while ($officer = $officerResult->fetch_assoc()) {
    $officerOptions[] = $officer;
}

$processingFeeColumn = findFeeColumn($conn, ['processing_fee', 'processingfee', 'processing_fee_kes']);
$registrationFeeColumn = findFeeColumn($conn, ['registration_fee', 'registration_fees', 'reg_fee', 'regfee']);

foreach ($officerOptions as $officer) {
    $officerEmail = $officer['email'];
    $borrowerStmt = $conn->prepare("SELECT id FROM borrowers WHERE loan_officer = ?");
    $borrowerStmt->bind_param("s", $officerEmail);
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

    $officerData[] = array(
        'name' => $officer['full_name'],
        'principal' => round($principal, 2),
        'loanBook' => round($loanBook, 2),
        'performingBook' => round($performingBook, 2),
        'arrearsValue' => round($arrearsValue, 2),
        'totalOverdueAmount' => round($arrearsValue, 2),
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
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Officer Performance Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .chart-container {
            position: relative;
            width: 100%;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
            height: 400px;
            max-height: 50vh;
        }
        @media (min-width: 768px) {
            .chart-container {
                height: 500px;
            }
        }
        .tier-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .tier-1 { background-color: #10b981; color: white; }
        .tier-2 { background-color: #3b82f6; color: white; }
        .tier-3 { background-color: #f97316; color: white; }
        .tier-4 { background-color: #ef4444; color: white; }
        .accordion-button {
            cursor: pointer;
            width: 100%;
            text-align: left;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding-top: 0.5rem;
            font-size: 0.875rem;
            color: #4a5568;
        }
        .accordion-icon {
            transition: transform 0.3s ease-in-out;
        }
        .rotate-180 {
            transform: rotate(180deg);
        }
        .report-table th, .report-table td {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
        }
        .report-table th {
            background-color: #f3f4f6;
            text-align: left;
            font-weight: 600;
        }
    </style>
</head>
<body class="text-gray-800">
    <div class="container mx-auto p-4 sm:p-6 md:p-8">
        <header class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
            <div class="flex-1 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Inua Premium Loan Officer Portfolio Analysis</h1>
                <p class="mt-2 text-lg text-gray-600">Interactive Performance & Risk Assessment Dashboard</p>
            </div>
            <div class="flex flex-wrap items-center justify-center md:justify-end gap-3">
                <a href="report_analytics.php<?php echo isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="inline-flex items-center gap-2 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-semibold px-5 py-3 rounded-xl shadow-lg shadow-violet-200 transition-all duration-200">
                    <span>📊</span>
                    <span>Analytical Tool</span>
                </a>
                <a href="index.php" class="inline-flex items-center gap-2 bg-gray-800 hover:bg-gray-700 text-white font-semibold px-4 py-2 rounded-lg shadow-sm">
                    ← Back to Dashboard
                </a>
            </div>
        </header>

        <div class="bg-white p-4 rounded-xl shadow-sm mb-4">
            <div class="flex flex-wrap gap-2">
                <a class="px-3 py-2 rounded text-sm font-semibold <?= $selected_area === 'all' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700' ?>" href="?report_scope=<?= urlencode($reportScope) ?>&report_year=<?= urlencode($selectedYear) ?>&report_month=<?= urlencode($selectedMonth) ?>&report_week=<?= urlencode($selectedWeek) ?>&area_id=all&officer_id=<?= urlencode((string) $selected_officer) ?>">All Areas</a>
                <?php foreach ($areas as $area): ?>
                    <a class="px-3 py-2 rounded text-sm font-semibold <?= (string) $selected_area === (string) $area['area_id'] ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700' ?>" href="?report_scope=<?= urlencode($reportScope) ?>&report_year=<?= urlencode($selectedYear) ?>&report_month=<?= urlencode($selectedMonth) ?>&report_week=<?= urlencode($selectedWeek) ?>&area_id=<?= urlencode($area['area_id']) ?>&officer_id=<?= urlencode((string) $selected_officer) ?>">
                        <?= htmlspecialchars($area['area_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="get" class="bg-white p-4 rounded-xl shadow-sm mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1" for="report_scope">Report Scope</label>
                <select id="report_scope" name="report_scope" class="border border-gray-300 rounded px-3 py-2">
                    <option value="all" <?php echo $reportScope === 'all' ? 'selected' : ''; ?>>General report</option>
                    <option value="custom" <?php echo $reportScope === 'custom' ? 'selected' : ''; ?>>Specific period</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1" for="area_id">Area</label>
                <select id="area_id" name="area_id" class="border border-gray-300 rounded px-3 py-2">
                    <option value="all" <?php echo $selected_area === 'all' ? 'selected' : ''; ?>>All areas</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?= (int) $area['area_id'] ?>" <?php echo (string) $selected_area === (string) $area['area_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($area['area_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1" for="report_year">Year</label>
                <select id="report_year" name="report_year" class="border border-gray-300 rounded px-3 py-2">
                    <option value="0" <?php echo $selectedYear == 0 ? 'selected' : ''; ?>>All years</option>
                    <?php for ($year = date('Y'); $year >= 2000; $year--) : ?>
                        <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1" for="report_month">Month</label>
                <select id="report_month" name="report_month" class="border border-gray-300 rounded px-3 py-2">
                    <option value="0" <?php echo $selectedMonth == 0 ? 'selected' : ''; ?>>All months</option>
                    <?php foreach (range(1, 12) as $month) : $monthLabel = date('F', mktime(0, 0, 0, $month, 1)); ?>
                        <option value="<?php echo $month; ?>" <?php echo $selectedMonth == $month ? 'selected' : ''; ?>><?php echo $monthLabel; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1" for="report_week">Week</label>
                <select id="report_week" name="report_week" class="border border-gray-300 rounded px-3 py-2">
                    <option value="0" <?php echo $selectedWeek == 0 ? 'selected' : ''; ?>>All weeks</option>
                    <?php foreach (range(1, 4) as $week) : ?>
                        <option value="<?php echo $week; ?>" <?php echo $selectedWeek == $week ? 'selected' : ''; ?>>Week <?php echo $week; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1" for="officer_id">Officer</label>
                <select id="officer_id" name="officer_id" class="border border-gray-300 rounded px-3 py-2">
                    <option value="all" <?php echo $selected_officer === 'all' ? 'selected' : ''; ?>>All officers</option>
                    <?php foreach ($officerOptions as $officerOption): ?>
                        <option value="<?= (int) $officerOption['id'] ?>" <?php echo (string) $selected_officer === (string) $officerOption['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($officerOption['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Apply</button>
            <div class="text-sm text-gray-600">
                Showing <span class="font-semibold text-gray-900"><?php echo $periodLabel; ?></span>
            </div>
        </form>

        <!-- Full Report Section -->
        <section id="full-report" class="bg-white p-6 rounded-xl shadow-md mb-8">
            <button class="accordion-button">
                <h2 class="text-xl font-bold text-gray-900">Full Performance Report & Analysis</h2>
                <span class="accordion-icon text-gray-500">▼</span>
            </button>
            <div class="accordion-content">
                <!-- Organizational Framework Section (Moved inside) -->
                <div id="organizational-framework" class="bg-gray-50 p-6 rounded-lg mb-8 border border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900">Organizational Framework & Supervisory Structure</h3>
                        <button id="download-framework-pdf-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow transition">
                            📥 Download as PDF
                        </button>
                    </div>
                    
                    <!-- Hierarchy Chart -->
                    <div class="mb-8 p-6 bg-gradient-to-b from-gray-50 to-gray-100 rounded-lg">
                        <div class="flex flex-col items-center mb-8">
                            <!-- CEO/Manager Level -->
                            <div class="bg-red-600 text-white px-8 py-4 rounded-lg shadow-lg font-semibold text-center mb-6" style="min-width: 280px;">
                                <div class="text-sm uppercase tracking-widest text-red-100"></div>
                                <div class="text-2xl mt-2">Relationship Manager</div>
                                <div class="text-sm text-red-200 mt-1"></div>
                            </div>
                            
                            <!-- Connecting Line -->
                            <div class="h-8 border-l-2 border-gray-400"></div>
                            
                            <!-- Supervisors Level -->
                            <div class="flex justify-center gap-12 w-full flex-wrap md:flex-nowrap">
                                <!-- Yegon's Team -->
                                <div class="flex flex-col items-center">
                                    <div class="bg-blue-600 text-white px-6 py-3 rounded-lg shadow-md font-semibold text-center mb-4" style="min-width: 240px;">
                                        <div class="text-sm uppercase tracking-widest text-blue-100">Supervisor</div>
                                        <div class="text-xl mt-1">Yegon</div>
                                    </div>
                                    <div class="h-6 border-l-2 border-gray-400"></div>
                                    <div class="bg-gray-50 border-2 border-blue-200 p-4 rounded-lg" style="min-width: 240px;">
                                        <div class="text-xs font-semibold text-gray-600 uppercase mb-3">Relationship officer:</div>
                                        <ul class="space-y-2 text-sm text-gray-700">
                                            <li class="flex items-center"><span class="text-blue-500 mr-2">•</span>Yegon Dennis</li>
                                            <li class="flex items-center"><span class="text-blue-500 mr-2">•</span>Audrey Chepkirui</li>
                                            <li class="flex items-center"><span class="text-blue-500 mr-2">•</span>Faith Chepkirui</li>
                                            <li class="flex items-center"><span class="text-blue-500 mr-2">•</span>Bethsheba Chepkorir</li>
                                            <li class="flex items-center"><span class="text-blue-500 mr-2">•</span>Emanuel Kiplangat</li>
                                            <li class="flex items-center"><span class="text-blue-500 mr-2">•</span>Vicoty Chelangat</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Antonio's Team -->
                                <div class="flex flex-col items-center">
                                    <div class="bg-green-600 text-white px-6 py-3 rounded-lg shadow-md font-semibold text-center mb-4" style="min-width: 240px;">
                                        <div class="text-sm uppercase tracking-widest text-green-100">Supervisor</div>
                                        <div class="text-xl mt-1">Antonio</div>
                                    </div>
                                    <div class="h-6 border-l-2 border-gray-400"></div>
                                    <div class="bg-gray-50 border-2 border-green-200 p-4 rounded-lg" style="min-width: 240px;">
                                        <div class="text-xs font-semibold text-gray-600 uppercase mb-3">Relationship officer:</div>
                                        <ul class="space-y-2 text-sm text-gray-700">
                                            <li class="flex items-center"><span class="text-green-500 mr-2">•</span>Antonio Cheruiyot</li>
                                            <li class="flex items-center"><span class="text-green-500 mr-2">•</span>Betty Chepngeno</li>
                                            <li class="flex items-center"><span class="text-green-500 mr-2">•</span>Brenda Chepngetich</li>
                                            <li class="flex items-center"><span class="text-green-500 mr-2">•</span>Dennis Kipngeno</li>
                                            <li class="flex items-center"><span class="text-green-500 mr-2">•</span>Penina Cheborgei</li>
                                            <li class="flex items-center"><span class="text-green-500 mr-2">•</span>Elsie Chepngetich</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Roles and Responsibilities -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                        <!-- Main Manager Card -->
                        <div class="bg-red-50 border-l-4 border-red-600 p-6 rounded-lg">
                            <h3 class="text-lg font-bold text-red-700 mb-4">Relationship Manager (Harold Koskei)</h3>
                            <ul class="space-y-2 text-sm text-gray-700">
                                <li class="flex items-start"><span class="text-red-600 mr-2 font-bold">▪</span> Super Supervisor overseeing all supervisory teams</li>
                                <li class="flex items-start"><span class="text-red-600 mr-2 font-bold">▪</span> Final authority for loan disbursement decisions</li>
                                <li class="flex items-start"><span class="text-red-600 mr-2 font-bold">▪</span> Accountable for overall portfolio performance</li>
                                <li class="flex items-start"><span class="text-red-600 mr-2 font-bold">▪</span> Strategic planning and risk management</li>
                            </ul>
                        </div>

                        <!-- Supervisors Card -->
                        <div class="bg-blue-50 border-l-4 border-blue-600 p-6 rounded-lg">
                            <h3 class="text-lg font-bold text-blue-700 mb-4">Supervisors (Yegon & Antonio)</h3>
                            <ul class="space-y-2 text-sm text-gray-700">
                                <li class="flex items-start"><span class="text-blue-600 mr-2 font-bold">▪</span> conduct regular callbacks to both client and guarantor</li>
                                <li class="flex items-start"><span class="text-blue-600 mr-2 font-bold">▪</span> Oversee Relationship officers performance</li>
                                <li class="flex items-start"><span class="text-blue-600 mr-2 font-bold">▪</span> Track arrears for each assigned staff member</li>
                                <li class="flex items-start"><span class="text-blue-600 mr-2 font-bold">▪</span> Analyze loanbook and application forms thoroughly</li>
                                <li class="flex items-start"><span class="text-blue-600 mr-2 font-bold">▪</span> Approve loans after assessment and tag manager for disbursement</li>
                            </ul>
                        </div>

                        <!-- Staff Responsibilities Card -->
                        <div class="bg-green-50 border-l-4 border-green-600 p-6 rounded-lg">
                            <h3 class="text-lg font-bold text-green-700 mb-4">Relationship officers Responsibilities</h3>
                            <ul class="space-y-2 text-sm text-gray-700">
                                <li class="flex items-start"><span class="text-green-600 mr-2 font-bold">▪</span> Originate and manage client relationships</li>
                                <li class="flex items-start"><span class="text-green-600 mr-2 font-bold">▪</span> Submit loan applications by tagging the supervisor for review</li>
                                <li class="flex items-start"><span class="text-green-600 mr-2 font-bold">▪</span> Report to supervisor if loan application is unattended for 30+ mins</li>
                                <li class="flex items-start"><span class="text-green-600 mr-2 font-bold">▪</span> Support collections and customer relationship activities</li>
                                <li class="flex items-start"><span class="text-green-600 mr-2 font-bold">▪</span> Home and business visits to be done together with the supervisor</li>
                                <li class="flex items-start"><span class="text-green-600 mr-2 font-bold">▪</span> All existing and new clients should be re-appraised regularly</li>
                                <li class="flex items-start"><span class="text-green-600 mr-2 font-bold">▪</span> Customers in arrears to be visited by officer accompanied by the Supervisor and reports compiled</li>
                                <li class="flex items-start"><span class="text-green-600 mr-2 font-bold">▪</span> the staff should upload or tag loanforms with their IDs for both Client and guarantor each and every time loan applications are submitted to assist verification by the supervisor</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Key Operational Requirements -->
                    <div class="mt-8 bg-yellow-50 border border-yellow-300 rounded-lg p-6">
                        <h3 class="text-lg font-bold text-yellow-800 mb-4">Key Operational Requirements for Supervisors</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3">Supervisory Duties</h4>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Remain online at all times to support assigned staff</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Make periodic phone calls to clients of assigned staff if arrears figures are in question to curb Fraudelent activities</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Monitor client satisfaction and loan progress</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Conduct daily performance reviews with staff</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Should ensure that Guarantor is aware of loan amount and installments by calling them anytime the loan is applied for</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Supervisor makes final decision to approve, decline or request more information</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> The supervisor fully takes responsibility if the loan goes bad</li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3">Accountability & Liability</h4>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Fully liable for delinquencies among assigned staff</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Answerable to management for staff performance</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Responsible for timely loan processing and disbursement</li>
                                    <li class="flex items-start"><span class="text-yellow-600 mr-2">✓</span> Maintain high portfolio quality standards</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Escalation Procedures -->
                    <div class="mt-8 bg-purple-50 border border-purple-300 rounded-lg p-6">
                        <h3 class="text-lg font-bold text-purple-800 mb-4">Loan Processing & Escalation Procedures</h3>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <div class="bg-purple-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-4 flex-shrink-0 font-semibold">1</div>
                                <div>
                                    <p class="font-semibold text-gray-900">RO Submit Application</p>
                                    <p class="text-sm text-gray-600">Relationship officers prepares and submits loan application with client documentation</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="bg-purple-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-4 flex-shrink-0 font-semibold">2</div>
                                <div>
                                    <p class="font-semibold text-gray-900">Supervisor Assessment</p>
                                    <p class="text-sm text-gray-600">Supervisor conducts thorough analysis of loanbook, application forms, and client creditworthiness</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="bg-purple-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-4 flex-shrink-0 font-semibold">3</div>
                                <div>
                                    <p class="font-semibold text-gray-900">Supervisor Approval</p>
                                    <p class="text-sm text-gray-600">Upon approval, supervisor tags the Manager (Harold) with recommendation for disbursement</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="bg-purple-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-4 flex-shrink-0 font-semibold">4</div>
                                <div>
                                    <p class="font-semibold text-gray-900">Manager Disbursement</p>
                                    <p class="text-sm text-gray-600">Manager approves and processes final disbursement of funds to client</p>
                                </div>
                            </div>
                            <div class="mt-4 p-4 bg-red-100 border border-red-400 rounded">
                                <p class="text-sm text-red-800"><span class="font-semibold">⚠ Time Sensitivity:</span> If a loan application is unattended for more than 30 minutes, the staff member must immediately contact their supervisor by phone to escalate the matter.</p>
                                <p class="text-sm text-red-800"><span class="font-semibold">⚠ Point To Note:</span> Contract to be terminated if Fraudulent activities or unmet KPIs are detected.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="text-lg font-bold mt-6 mb-2">Executive Summary and Strategic Tiering</h3>
                <p>This report provides a comprehensive analysis of the performance of loan officers across the portfolio, evaluating their portfolios based on key metrics of productivity, risk, and portfolio quality. The aggregated data and performance tiers enable a strategic, evidence-based approach to management and mentorship. Performance metrics are analyzed holistically through a composite scoring system that balances growth with risk management. This tiered classification allows targeted interventions specific to each officer's strengths and challenges, rather than a uniform approach. Best practices from top-tier performers are leveraged to create organization-wide training and improvement programs.</p>
                <ul class="list-disc list-inside mt-2 space-y-1">
                    <li><strong>Tier 1: Exemplary Performers (The Benchmark):</strong> Antonio Cheruiyot, Yegon Dennis, and Penina. Characterized by exceptionally low-risk profiles and meticulous portfolio management, their performance serves as the organizational benchmark for what is achievable in portfolio quality.</li>
                    <li><strong>Tier 2: Strong & Balanced Performers:</strong> Dennis Kipng'eno and Betty. These officers demonstrate a healthy balance, managing substantial loan books while maintaining well-controlled risk metrics. Their performance proves that high productivity and portfolio quality are not mutually exclusive.</li>
                    <li><strong>Tier 3: High-Volume, High-Risk Portfolios:</strong> Emmanuel and Audrey. These individuals drive significant volume and portfolio growth, but their high arrears and PAR values present a substantial risk to the overall health and stability of the loan book.</li>
                    <li><strong>Tier 4: Contradictory & High-Risk Profiles:</strong> Bethsheba and Elsie. This group is characterized by highly volatile or consistently poor performance. Their portfolios show the highest levels of risk, which necessitates immediate review and direct, targeted interventions to mitigate potential losses.</li>
                </ul>
                <p class="mt-2">Based on this analysis, the following are the most critical recommendations for senior management: (1) Establish a daily PAR monitoring procedures to track and flag sudden spikes in risk. (2) Launch an urgent, deep-dive portfolio review for all officers in Tier 4. (3) Leverage the strategies and methodologies of the Tier 1 and Tier 2 officers to create a system-wide training and mentorship program.</p>

                <h3 class="text-lg font-bold mt-6 mb-2">Overall Performance Metrics: Aggregated Analysis and Comparative Ranking</h3>
                <div class="overflow-x-auto mt-4">
                    <table class="w-full report-table table-auto text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th>Rank</th>
                                <th>Officer</th>
                                <th>Principal Amount (KES)</th>
                                <th>Loan Book (KES)</th>
                                <th>Performing Book (KES)</th>
                                <th>Total Arrears (KES)</th>
                                <th>PAR %</th>
                                <th>No. of Customers</th>
                                <th>No. of Customers in Arrears</th>
                                <th>% of Customers in Arrears</th>
                                <th>Interest (KES)</th>
                                <th>Processing Fee (KES)</th>
                                <th>Registration Fee (KES)</th>
                                <th>Composite Score</th>
                                <th>Commission Amount (KES)</th>
                            </tr>
                        </thead>
                            <tbody id="aggregated-metrics-table-body">
                        </tbody>
                    </table>
                </div>
                <!-- ...existing code... -->
            </div>
        </section>

        <!-- Commission Table Section -->
        <section id="commission-table" class="bg-white p-6 rounded-xl shadow-md mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 text-center">Commission Qualification Table</h2>
            <div class="overflow-x-auto">
                <table class="w-full report-table table-auto text-sm" id="commission-table-body">
                    <thead>
                        <tr class="bg-gray-100">
                            <th>Officer</th>
                            <th>Loan Book (KES)</th>
                            <th>PAR %</th>
                            <th>Interest (KES)</th>
                            <th>Processing Fee (KES)</th>
                            <th>Registration Fee (KES)</th>
                            <th>Composite Score</th>
                            <th>Commission Status</th>
                            <th>Commission Amount (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows will be dynamically generated -->
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Staff Performance Rankings Section -->
        <section id="staff-performance-rankings" class="bg-white p-6 rounded-xl shadow-md mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 text-center">Staff Performance Rankings (All Metrics)</h2>
            <div class="flex justify-end mb-2">
                <button id="download-pdf-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow transition">
                    Download PDF
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full report-table table-auto text-sm" id="staff-performance-table">
                    <thead>
                        <tr class="bg-gray-100">
                            <th>Position</th>
                            <th>Name</th>
                            <th>Loan Book (KES)</th>
                            <th>PAR %</th>
                            <th>Total arrears (KES)</th>
                            <th>No. of Customers</th>
                            <th>Funded Customers</th>
                            <th>Disbursed Value (KES)</th>
                            <th>Net Income (KES)</th>
                            <th>Interest (KES)</th>
                            <th>Processing Fee (KES)</th>
                            <th>Registration Fee (KES)</th>
                            <th>Composite Score</th>
                        </tr>
                    </thead>
                    <tbody id="staff-performance-table-body"></tbody>
                </table>
            </div>
        </section>

        <!-- Overall Portfolio Summary Section -->
        <section id="overall-summary" class="bg-white p-6 rounded-xl shadow-md mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 text-center">Overall Portfolio Summary</h2>
            <div id="summary-metrics-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <!-- Metrics will be inserted here by JavaScript -->
            </div>
        </section>

        <main>
            <section id="ranking-charts" class="bg-white p-6 rounded-xl shadow-md mb-8">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">Officer Performance Rankings</h2>
                    <div class="flex items-center space-x-3">
                        <label for="metric-select" class="font-semibold text-gray-700">Select Metric:</label>
                        <select id="metric-select" class="block w-full sm:w-auto bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            <option value="par" selected>Portfolio at Risk (PAR %)</option>
                            <option value="arrearsValue">Total Arrears (KES)</option>
                            <option value="customersInArrearsPercentage">% Customers in Arrears</option>
                            <option value="loanBook">Loan Book (KES)</option>
                            <option value="performingBook">Performing Book (KES)</option>
                            <option value="principal">Principal Amount (KES)</option>
                            <option value="customers">No. of Customers</option>
                            <option value="customersInArrears">No. of Customers in Arrears</option>
                            <option value="interest">Interest (KES)</option>
                            <option value="processingFee">Processing Fee (KES)</option>
                            <option value="registrationFee">Registration Fee (KES)</option>
                            <option value="income">Income (KES)</option>
                            <option value="netIncome">Net Income (KES)</option>
                            <option value="recruitedCustomers">New Customers Recruited</option>
                            <option value="fundedCustomers">Funded Customers</option>
                            <option value="disbursementValue">Disbursed Value (KES)</option>
                        </select>
                    </div>
                </div>
                <p id="chart-context" class="text-center text-gray-600 mb-4"></p>
                <div class="chart-container">
                    <canvas id="officerRankingsChart"></canvas>
                </div>
            </section>

            <section id="officer-profiles" class="mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Performance Tiers & Officer Profiles</h2>
                    <button id="download-profiles-pdf-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow transition">
                        📥 Download as PDF
                    </button>
                </div>
                <p class="max-w-3xl mx-auto text-center text-gray-600 mb-8">
                    Officers are grouped into four performance tiers based on a composite evaluation of their portfolio quality, risk profile, and productivity. This allows for a strategic, targeted approach to management and training.
                </p>
                <div id="profiles-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const officerData = <?php echo json_encode($officerData); ?>;
            // Calculate income and net income for each officer
            const interestRate = 0.1935;
            officerData.forEach(officer => {
                officer.totalOverdueAmount = Number(officer.totalOverdueAmount ?? officer.arrearsValue ?? 0);
                officer.arrearsValue = officer.totalOverdueAmount;
                // Income is calculated as 60% of the loan book multiplied by the interest rate
                officer.income = Math.round(0.60 * officer.loanBook * interestRate);
                // Net income is income minus the officer's total overdue amount
                officer.netIncome = officer.income - officer.totalOverdueAmount;
            });

            // Calculate total income and total net income
            const totalIncome = officerData.reduce((sum, o) => sum + o.income, 0);
            const totalNetIncome = officerData.reduce((sum, o) => sum + o.netIncome, 0);

            // Updated overall team metrics
            const totalPrincipal = officerData.reduce((sum, o) => sum + o.principal, 0);
            const totalLoanBook = officerData.reduce((sum, o) => sum + o.loanBook, 0);
            const totalPerformingBook = officerData.reduce((sum, o) => sum + o.performingBook, 0);
            const totalOverdueAmount = officerData.reduce((sum, o) => sum + o.totalOverdueAmount, 0);
            const totalCustomers = officerData.reduce((sum, o) => sum + o.customers, 0);
            const totalCustomersInArrears = officerData.reduce((sum, o) => sum + o.customersInArrears, 0);
            const totalRecruitedCustomers = officerData.reduce((sum, o) => sum + o.recruitedCustomers, 0);
            const totalDisbursementValue = officerData.reduce((sum, o) => sum + o.disbursementValue, 0);
            const totalInterest = officerData.reduce((sum, o) => sum + (Number(o.interest) || 0), 0);
            const totalProcessingFee = officerData.reduce((sum, o) => sum + (Number(o.processingFee) || 0), 0);
            const totalRegistrationFee = officerData.reduce((sum, o) => sum + (Number(o.registrationFee) || 0), 0);
            const totalFees = totalProcessingFee + totalRegistrationFee;
            const overallPar = ((totalOverdueAmount / totalLoanBook) * 100).toFixed(1);
            const overallCustomersInArrearsPercentage = ((totalCustomersInArrears / totalCustomers) * 100).toFixed(1);

            const overallMetrics = [
                { label: 'Total Loan Book', value: totalLoanBook, format: val => `KES ${val.toLocaleString()}` },
                { label: 'Total Arrears', value: totalOverdueAmount, format: val => `KES ${val.toLocaleString()}` },
                { label: 'Overall PAR %', value: overallPar, format: val => `${val}%` },
                { label: 'Total Customers', value: totalCustomers, format: val => val },
                { label: '% Customers in Arrears', value: overallCustomersInArrearsPercentage, format: val => `${val}%` },
                { label: 'Total Recruited Customers', value: totalRecruitedCustomers, format: val => val },
                { label: 'Total Disbursement Value', value: totalDisbursementValue, format: val => `KES ${val.toLocaleString()}` },
                { label: 'Total Interest', value: totalInterest, format: val => `KES ${val.toLocaleString()}` },
                { label: 'Total Processing Fee', value: totalProcessingFee, format: val => `KES ${val.toLocaleString()}` },
                { label: 'Total Registration Fee', value: totalRegistrationFee, format: val => `KES ${val.toLocaleString()}` },
                { label: 'Total Fees', value: totalFees, format: val => `KES ${val.toLocaleString()}` },
                { label: 'Total Income', value: totalIncome, format: val => `KES ${val.toLocaleString()}` }, // Added Total Income
                { label: 'Total Net Income', value: totalNetIncome, format: val => `KES ${val.toLocaleString()}` } // Added Total Net Income
            ];

            // Fix: Ensure all metrics are displayed in the summary grid
            const summaryGrid = document.getElementById('summary-metrics-grid');
            if (summaryGrid) {
                summaryGrid.innerHTML = ''; // Clear previous content
                overallMetrics.forEach(metric => {
                    const card = document.createElement('div');
                    card.className = 'bg-gray-50 p-4 rounded-lg shadow-inner';
                    card.innerHTML = `
                        <p class="text-sm font-medium text-gray-500">${metric.label}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">${metric.format(metric.value)}</p>
                    `;
                    summaryGrid.appendChild(card);
                });
            }

            

            const metricConfig = {
                par: { label: 'Portfolio at Risk (PAR %)', higherIsWorse: true, format: val => `${val}%` },
                arrearsValue: { label: 'Total Overdue Amount (KES)', higherIsWorse: true, format: val => `KES ${val.toLocaleString()}` },
                customersInArrearsPercentage: { label: '% Customers in Arrears', higherIsWorse: true, format: val => `${val}%` },
                loanBook: { label: 'Loan Book (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
                performingBook: { label: 'Performing Book (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
                principal: { label: 'Principal Amount (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
                customers: { label: 'No. of Customers', higherIsWorse: false, format: val => val },
                customersInArrears: { label: 'No. of Customers in Arrears', higherIsWorse: true, format: val => val },
                interest: { label: 'Interest (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
                processingFee: { label: 'Processing Fee (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
                registrationFee: { label: 'Registration Fee (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
                income: { label: 'Income (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
                netIncome: { label: 'Net Income (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
                recruitedCustomers: { label: 'New Customers Recruited', higherIsWorse: false, format: val => val },
                fundedCustomers: { label: 'Funded Customers', higherIsWorse: false, format: val => val },
                disbursementValue: { label: 'Disbursed Value (KES)', higherIsWorse: false, format: val => `KES ${val.toLocaleString()}` },
            };

            function hasPerformanceData(officer) {
                return Number(officer.principal) > 0 ||
                    Number(officer.loanBook) > 0 ||
                    Number(officer.customers) > 0 ||
                    Number(officer.disbursementValue) > 0;
            }

            function compareByRank(a, b) {
                const aHasData = hasPerformanceData(a);
                const bHasData = hasPerformanceData(b);
                if (aHasData !== bHasData) return aHasData ? -1 : 1;
                return b.compositeScore - a.compositeScore;
            }

            // Update adviceData if needed (optional, keep as is or adjust for new tiers)
            const adviceData = {
                'Antonio Cheruiyot': "Portfolio quality is strong with disciplined collections. Share best practices with the team.",
                'Dennis Kipng\'eno': "High portfolio and large arrears. Urgent portfolio review and focused collections required.",
                'Yegon Dennis': "Low PAR and stable portfolio. Continue disciplined client management and expand carefully.",
                'Bethsheba': "High arrears concentration. Pause growth and conduct a deep-dive into problem accounts.",
                'Elsie': "Manageable PAR but watch customer arrears. Strengthen collections and monitoring.",
                'Betty': "Good productivity; reduce arrears through targeted follow-ups.",
                'Audrey': "Moderate PAR; prioritize collection interventions and client monitoring.",
                'Emmanuel': "Elevated arrears and PAR. Urgent collection strategy and account-level review needed.",
                'Penina': "Strong performer with low PAR. Document processes and mentor peers.",
                'Brenda': "Small portfolio with low arrears — opportunity to grow while maintaining discipline.",
                'Faith Chepkirui': "Low PAR and minimal arrears — continue current approach and scale carefully."
            };

            // Sort officers by composite performance (sum of normalized metrics)
            function getCompositeScore(officer) {
                // Normalize metrics to [0,1] and sum (higher is better for income, lower is better for arrears/par)
                // Use percentile bounds so one unusually large or risky portfolio cannot distort every score.
                function robustBounds(metric) {
                    const values = officerData
                        .map(o => Number(o[metric]) || 0)
                        .sort((a, b) => a - b);
                    if (values.length < 3) {
                        return { min: values[0] ?? 0, max: values[values.length - 1] ?? 0 };
                    }

                    const percentile = (rank) => {
                        const index = (values.length - 1) * rank;
                        const lower = Math.floor(index);
                        const upper = Math.ceil(index);
                        const fraction = index - lower;
                        return values[lower] + ((values[upper] - values[lower]) * fraction);
                    };

                    return { min: percentile(0.10), max: percentile(0.90) };
                }

                const bounds = {};
                ['loanBook', 'income', 'netIncome', 'customers', 'performingBook', 'principal', 'arrearsValue', 'par', 'interest', 'processingFee', 'registrationFee', 'recruitedCustomers', 'disbursementValue', 'customersInArrears', 'customersInArrearsPercentage', 'fundedCustomers'].forEach(metric => {
                    bounds[metric] = robustBounds(metric);
                });

                function norm(val, range) {
                    const boundedValue = Math.min(range.max, Math.max(range.min, Number(val) || 0));
                    return range.max === range.min ? 1 : (boundedValue - range.min) / (range.max - range.min);
                }
                function normInv(val, range) {
                    return 1 - norm(val, range);
                }

                // Composite score: risk and portfolio quality lead, with productivity and growth supporting.
                return (
                    0.25 * norm(officer.loanBook, bounds.loanBook) +
                    0.00 * norm(officer.income, bounds.income) +
                    0.05 * norm(officer.netIncome, bounds.netIncome) +
                    0.00 * norm(officer.customers, bounds.customers) +
                    0.00 * norm(officer.performingBook, bounds.performingBook) +
                    0.00 * norm(officer.principal, bounds.principal) +
                    0.00 * normInv(officer.arrearsValue, bounds.arrearsValue) +
                    0.30 * normInv(officer.par, bounds.par) +
                    0.00 * norm(officer.recruitedCustomers, bounds.recruitedCustomers) +
                    0.00 * norm(officer.disbursementValue, robustBounds('disbursementValue')) +
                    0.05 * normInv(officer.customersInArrears, bounds.customersInArrears) +
                    0.10 * norm(officer.interest, bounds.interest) +
                    0.05 * norm(officer.processingFee, bounds.processingFee) +
                    0.05 * norm(officer.registrationFee, bounds.registrationFee) +
                    0.00 * normInv(officer.customersInArrearsPercentage, bounds.customersInArrearsPercentage) +
                    0.00 * norm(officer.fundedCustomers, bounds.fundedCustomers)
                ) / 0.85;
            }

            // Sort officers by composite score descending
            officerData.forEach(o => { o.compositeScore = getCompositeScore(o); });
            officerData.sort(compareByRank);

            // Dynamically assign tiers based on composite score ranking
            officerData.sort(compareByRank);
            officerData.forEach((officer, idx) => {
                if (idx < 3) officer.tier = 1;
                else if (idx < 5) officer.tier = 2;
                else if (idx < 7) officer.tier = 3;
                else officer.tier = 4;
            });

            // Staff performance rankings table
            const staffTableBody = document.getElementById('staff-performance-table-body');
            if (staffTableBody) {
                staffTableBody.innerHTML = '';
                officerData.forEach((officer, idx) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${idx + 1}</td>
                        <td class="font-semibold">${officer.name}</td>
                        <td>${officer.loanBook.toLocaleString()}</td>
                        <td>${officer.par}%</td>
                        <td>${officer.totalOverdueAmount.toLocaleString()}</td>
                        <td>${officer.customers}</td>
                        <td>${officer.fundedCustomers ?? officer.recruitedCustomers}</td>
                        <td>${officer.disbursementValue.toLocaleString()}</td>
                        <td>${officer.netIncome.toLocaleString()}</td>
                        <td>${Number(officer.interest || 0).toLocaleString()}</td>
                        <td>${Number(officer.processingFee || 0).toLocaleString()}</td>
                        <td>${Number(officer.registrationFee || 0).toLocaleString()}</td>
                        <td>${(officer.compositeScore * 100).toFixed(1)}%</td>
                    `;
                    staffTableBody.appendChild(tr);
                });
            }

            // Populate aggregated metrics table (includes Interest and Processing/Reg Fee)
            const aggregatedBody = document.getElementById('aggregated-metrics-table-body');
            if (aggregatedBody) {
                aggregatedBody.innerHTML = '';
                officerData.forEach((officer, idx) => {
                    const tr = document.createElement('tr');
                    const commissionDisplay = officer.commissionAmount ? `KES ${Number(officer.commissionAmount).toLocaleString()}` : '—';
                    tr.innerHTML = `
                        <td>${idx + 1}</td>
                        <td class="font-semibold">${officer.name}</td>
                        <td>${Number(officer.principal || 0).toLocaleString()}</td>
                        <td>${Number(officer.loanBook || 0).toLocaleString()}</td>
                        <td>${Number(officer.performingBook || 0).toLocaleString()}</td>
                        <td>${Number(officer.arrearsValue || 0).toLocaleString()}</td>
                        <td>${officer.par}%</td>
                        <td>${Number(officer.customers || 0)}</td>
                        <td>${Number(officer.customersInArrears || 0)}</td>
                        <td>${officer.customersInArrearsPercentage}%</td>
                        <td>${Number(officer.interest || 0).toLocaleString()}</td>
                        <td>${Number(officer.processingFee || 0).toLocaleString()}</td>
                        <td>${Number(officer.registrationFee || 0).toLocaleString()}</td>
                        <td>${(officer.compositeScore * 100).toFixed(1)}%</td>
                        <td>${commissionDisplay}</td>
                    `;
                    aggregatedBody.appendChild(tr);
                });
            }

            // Add commission amount calculation to the Commission Table
            // Adjust the baseline amount dynamically based on loan book, PAR, and composite score
            const commissionTableBody = document.getElementById('commission-table-body').querySelector('tbody');
            // Function to get baseline based on loan book, PAR, and composite score
            function getCommissionBaseline(loanBook, par, compositeScore) {
                // Not entitled case explicitly specified: below 600k, par < 7% and composite >= 50%
                if (loanBook < 600000 && par < 7 && compositeScore >= 0.5) {
                    return 0;
                }

                // Only officers with loanBook >= 600,000 are considered for baselines
                if (loanBook >= 600000) {
                    // Override cases that always get a 10,000 baseline:
                    // - par > 7% AND composite >= 50%
                    // - par <= 7% AND composite < 50%
                    if ((par > 7 && compositeScore >= 0.5) || (par <= 7 && compositeScore < 0.5)) {
                        return 12000;
                    }

                    // If par <= 7% AND composite >= 50% -> apply tiered baseline by loanbook range
                    if (par <= 7 && compositeScore >= 0.5) {
                        if (loanBook >= 600000 && loanBook < 1000000) {
                            return 15000;
                        } else if (loanBook >= 1000000 && loanBook < 1500000) {
                            return 20000;
                        } else if (loanBook >= 1500000 && loanBook < 2000000) {
                            return 25000;
                        } else if (loanBook >= 2000000) {
                            return 30000; // cap at 30,000 for 2M+
                        }
                    }
                }

                // All other cases are not entitled
                return 0;
            }

            commissionTableBody.innerHTML = '';
            officerData.forEach(officer => {
                // Get baseline amount based on new rules
                const baselineAmount = getCommissionBaseline(officer.loanBook, officer.par, officer.compositeScore);
                
                // Skip officers who have baseline of 0 (not entitled)
                if (baselineAmount === 0) {
                    return; // Skip officers who did not qualify for any commission
                }

                const commissionStatus = 'Commission Qualified';
                const rowClass = 'bg-green-100'; // Green for qualified commission
                const commissionAmount = Math.round(officer.compositeScore * baselineAmount);

                const row = document.createElement('tr');
                row.className = rowClass;
                row.innerHTML = `
                    <td>${officer.name}</td>
                    <td>${officer.loanBook.toLocaleString()}</td>
                    <td>${officer.par}%</td>
                    <td>${Number(officer.interest || 0).toLocaleString()}</td>
                    <td>${Number(officer.processingFee || 0).toLocaleString()}</td>
                    <td>${Number(officer.registrationFee || 0).toLocaleString()}</td>
                    <td>${(officer.compositeScore * 100).toFixed(1)}%</td>
                    <td>${commissionStatus}</td>
                    <td>KES ${commissionAmount.toLocaleString()}</td>
                `;
                commissionTableBody.appendChild(row);
            });

            // Calculate and append subtotal for commission amounts
            let totalCommission = officerData.reduce((sum, officer) => {
                const baselineAmount = getCommissionBaseline(officer.loanBook, officer.par, officer.compositeScore);
                // Only include if baseline amount is greater than 0 (qualified)
                if (baselineAmount > 0) {
                    return sum + Math.round(officer.compositeScore * baselineAmount);
                }
                return sum;
            }, 0);

            const subtotalRow = document.createElement('tr');
            subtotalRow.className = 'bg-gray-200 font-bold';
            subtotalRow.innerHTML = `
                <td colspan="8" class="text-right">Subtotal</td>
                <td>KES ${totalCommission.toLocaleString()}</td>
            `;
            commissionTableBody.appendChild(subtotalRow);

            // Performance Tiers & Officer Profiles PDF Download Logic
            document.getElementById('download-profiles-pdf-btn').addEventListener('click', function() {
                const btn = document.getElementById('download-profiles-pdf-btn');
                const originalText = btn.textContent;
                btn.textContent = '⏳ Generating PDF...';
                btn.disabled = true;

                // Helper function to load logo
                function loadLogoAsBase64() {
                    return new Promise((resolve) => {
                        const img = new Image();
                        img.crossOrigin = "Anonymous";
                        img.onload = function() {
                            const canvas = document.createElement('canvas');
                            canvas.width = img.width;
                            canvas.height = img.height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0);
                            resolve(canvas.toDataURL('image/png'));
                        };
                        img.onerror = () => resolve(null);
                        img.src = '/assets/img/logo.png';
                    });
                }

                loadLogoAsBase64().then(logoBase64 => {
                    const pdf = new window.jspdf.jsPDF({
                        orientation: 'portrait',
                        unit: 'mm',
                        format: 'a4'
                    });

                    const pageWidth = pdf.internal.pageSize.getWidth();
                    const pageHeight = pdf.internal.pageSize.getHeight();
                    const margins = { top: 15, bottom: 15, left: 15, right: 15 };
                    const contentWidth = pageWidth - margins.left - margins.right;
                    let yPos = margins.top;

                    // Helper function to add header with logo
                    function addHeader() {
                        if (logoBase64) {
                            pdf.addImage(logoBase64, 'PNG', pageWidth - 35, 8, 28, 12);
                        }
                        pdf.setFontSize(16);
                        pdf.setTextColor(44, 122, 123);
                        pdf.text('Inua Premium', margins.left, yPos);
                        yPos += 8;
                        pdf.setFontSize(12);
                        pdf.setTextColor(60, 60, 60);
                        pdf.text('Performance Tiers & Officer Profiles', margins.left, yPos);
                        yPos += 8;
                        pdf.setDrawColor(44, 122, 123);
                        pdf.line(margins.left, yPos + 1, pageWidth - margins.right, yPos + 1);
                        yPos += 8;
                    }

                    // Helper function to add footer
                    function addFooter(pageNum) {
                        pdf.setFontSize(8);
                        pdf.setTextColor(150, 150, 150);
                        pdf.text('Inua Premium - Confidential Document', margins.left, pageHeight - 10);
                        pdf.text(`Page ${pageNum}`, pageWidth - margins.right - 10, pageHeight - 10, { align: 'right' });
                    }

                    // Page 1: Title Page
                    addHeader();
                    yPos += 20;

                    pdf.setFontSize(14);
                    pdf.setTextColor(44, 122, 123);
                    pdf.text('Performance Analysis Report', pageWidth / 2, yPos, { align: 'center' });
                    yPos += 15;

                    pdf.setFontSize(10);
                    pdf.setTextColor(100, 100, 100);
                    const introText = 'Officers are grouped into four performance tiers based on a composite evaluation of portfolio quality, risk profile, and productivity levels.';
                    const wrappedIntro = pdf.splitTextToSize(introText, contentWidth);
                    pdf.text(wrappedIntro, margins.left, yPos);
                    yPos += wrappedIntro.length * 4 + 10;

                    // Add Tier Summary
                    pdf.setFontSize(11);
                    pdf.setTextColor(44, 122, 123);
                    pdf.text('Performance Tiers Overview', margins.left, yPos);
                    yPos += 8;

                    const tierDescriptions = [
                        { tier: 'Tier 1', color: [16, 185, 129], desc: 'Exemplary Performers - Benchmark for the organization' },
                        { tier: 'Tier 2', color: [59, 130, 246], desc: 'Strong & Balanced - High productivity with quality' },
                        { tier: 'Tier 3', color: [249, 115, 22], desc: 'High-Volume, High-Risk - Requires attention' },
                        { tier: 'Tier 4', color: [239, 68, 68], desc: 'Contradictory & High-Risk - Immediate intervention needed' }
                    ];

                    pdf.setFontSize(9);
                    tierDescriptions.forEach((tier, idx) => {
                        pdf.setTextColor(tier.color[0], tier.color[1], tier.color[2]);
                        pdf.text(`${tier.tier}: ${tier.desc}`, margins.left + 5, yPos);
                        yPos += 6;
                    });

                    addFooter(1);
                    let pageNum = 2;

                    // Page 2+: Individual Officer Profiles
                    pdf.addPage();
                    yPos = margins.top;
                    addHeader();
                    yPos += 5;

                    // Group officers by tier
                    const tierGroups = {
                        1: officerData.filter(o => o.tier === 1),
                        2: officerData.filter(o => o.tier === 2),
                        3: officerData.filter(o => o.tier === 3),
                        4: officerData.filter(o => o.tier === 4)
                    };

                    const tierColors = {
                        1: [16, 185, 129],
                        2: [59, 130, 246],
                        3: [249, 115, 22],
                        4: [239, 68, 68]
                    };

                    // Process each tier
                    [1, 2, 3, 4].forEach(tierNum => {
                        const officers = tierGroups[tierNum];
                        if (officers.length === 0) return;

                        // Check if we need a new page
                        if (yPos > pageHeight - 80) {
                            addFooter(pageNum);
                            pdf.addPage();
                            pageNum++;
                            yPos = margins.top;
                            addHeader();
                            yPos += 5;
                        }

                        // Tier Header
                        const tierColor = tierColors[tierNum];
                        pdf.setFillColor(tierColor[0], tierColor[1], tierColor[2]);
                        pdf.rect(margins.left, yPos, contentWidth, 8, 'F');
                        pdf.setTextColor(255, 255, 255);
                        pdf.setFontSize(11);
                        pdf.setFont(undefined, 'bold');
                        pdf.text(`TIER ${tierNum} - Performance Profile`, margins.left + 3, yPos + 6);
                        yPos += 12;

                        // Officer details for each tier
                        pdf.setTextColor(60, 60, 60);
                        pdf.setFont(undefined, 'normal');

                        officers.forEach((officer) => {
                            if (yPos > pageHeight - 50) {
                                addFooter(pageNum);
                                pdf.addPage();
                                pageNum++;
                                yPos = margins.top;
                                addHeader();
                                yPos += 5;
                            }

                            // Officer name (bold)
                            pdf.setFontSize(10);
                            pdf.setFont(undefined, 'bold');
                            pdf.setTextColor(44, 122, 123);
                            pdf.text(officer.name, margins.left, yPos);
                            yPos += 5;

                            // Officer metrics
                            pdf.setFontSize(9);
                            pdf.setFont(undefined, 'normal');
                            pdf.setTextColor(80, 80, 80);

                            const metrics = [
                                `Loan Book: KES ${officer.loanBook.toLocaleString()}`,
                                `PAR: ${officer.par}% | Total Overdue: KES ${officer.totalOverdueAmount.toLocaleString()}`,
                                `Customers: ${officer.customers} | In Arrears: ${officer.customersInArrearsPercentage}%`,
                                `Net Income: KES ${officer.netIncome.toLocaleString()} | Composite Score: ${(officer.compositeScore * 100).toFixed(1)}%`
                            ];

                            metrics.forEach(metric => {
                                pdf.text(metric, margins.left + 5, yPos);
                                yPos += 4;
                            });

                            // Commission status
                            let commissionStatus = 'Not Qualified';
                            let statusColor = [239, 68, 68];
                            if (officer.loanBook >= 600000 && officer.compositeScore >= 0.5) {
                                commissionStatus = '✓ Commission Qualified';
                                statusColor = [16, 185, 129];
                            }
                            pdf.setTextColor(statusColor[0], statusColor[1], statusColor[2]);
                            pdf.setFont(undefined, 'bold');
                            pdf.text(commissionStatus, margins.left + 5, yPos);
                            yPos += 6;

                            // Separator line
                            pdf.setDrawColor(220, 220, 220);
                            pdf.line(margins.left + 5, yPos, pageWidth - margins.right - 5, yPos);
                            yPos += 4;
                        });

                        yPos += 3;
                    });

                    // Final page: Summary
                    addFooter(pageNum);
                    pdf.addPage();
                    pageNum++;
                    yPos = margins.top;
                    addHeader();
                    yPos += 10;

                    // Summary section
                    pdf.setFontSize(12);
                    pdf.setTextColor(44, 122, 123);
                    pdf.text('Executive Summary', margins.left, yPos);
                    yPos += 8;

                    pdf.setFontSize(9);
                    pdf.setTextColor(80, 80, 80);
                    pdf.setFont(undefined, 'normal');

                    const summaryText = [
                        `Total Officers Analyzed: ${officerData.length}`,
                        `Tier 1 (Exemplary): ${tierGroups[1].length} officers`,
                        `Tier 2 (Strong & Balanced): ${tierGroups[2].length} officers`,
                        `Tier 3 (High-Volume, High-Risk): ${tierGroups[3].length} officers`,
                        `Tier 4 (High-Risk Anomaly): ${tierGroups[4].length} officers`,
                        ``,
                        `Total Portfolio:`,
                        `Overall Loan Book: KES ${officerData.reduce((sum, o) => sum + o.loanBook, 0).toLocaleString()}`,
                        `Overall Portfolio at Risk (PAR): ${((officerData.reduce((sum, o) => sum + o.arrearsValue, 0) / officerData.reduce((sum, o) => sum + o.loanBook, 0)) * 100).toFixed(1)}%`,
                        `Total Overdue Amount: KES ${officerData.reduce((sum, o) => sum + o.totalOverdueAmount, 0).toLocaleString()}`,
                        `Total Income: KES ${officerData.reduce((sum, o) => sum + o.income, 0).toLocaleString()}`
                    ];

                    summaryText.forEach(text => {
                        if (text === '') {
                            yPos += 2;
                        } else {
                            pdf.text(text, margins.left + 5, yPos);
                            yPos += 5;
                        }
                    });

                    addFooter(pageNum);
                    pdf.save('Performance_Tiers_Officer_Profiles.pdf');
                    btn.textContent = originalText;
                    btn.disabled = false;
                });
            });

            // PDF download logic
            document.getElementById('download-pdf-btn').addEventListener('click', function () {
                const table = document.getElementById('staff-performance-table');
                const commissionTable = document.getElementById('commission-table');

                table.scrollIntoView({ behavior: "instant", block: "center" });
                setTimeout(() => {
                    html2canvas(table, { backgroundColor: "#fff", scale: 2 }).then(canvas => {
                        const imgData = canvas.toDataURL('image/png');
                        const pdf = new window.jspdf.jsPDF({
                            orientation: 'landscape',
                            unit: 'pt',
                            format: 'a4'
                        });
                        const pageWidth = pdf.internal.pageSize.getWidth();
                        const imgProps = pdf.getImageProperties(imgData);
                        const pdfWidth = pageWidth - 40;
                        const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

                        function addLogoAndSave(logoBase64) {
                            pdf.setFontSize(18);
                            pdf.text("Inua Premium Loan Officer Portfolio Analysis", 40, 55);
                            if (logoBase64) {
                                pdf.addImage(logoBase64, 'PNG', pageWidth - 120, 30, 80, 40);
                            }
                            pdf.setFontSize(14);
                            pdf.text("Staff Performance Rankings (All Metrics)", 40, 90);
                            pdf.addImage(imgData, 'PNG', 40, 110, pdfWidth, pdfHeight);

                            // Add Commission Table to PDF
                            html2canvas(commissionTable, { backgroundColor: "#fff", scale: 2 }).then(commissionCanvas => {
                                const commissionImgData = commissionCanvas.toDataURL('image/png');
                                const commissionImgProps = pdf.getImageProperties(commissionImgData);
                                const commissionPdfHeight = (commissionImgProps.height * pdfWidth) / commissionImgProps.width;

                                pdf.addPage();
                                pdf.text("Commission Qualification Table", 40, 55);
                                pdf.addImage(commissionImgData, 'PNG', 40, 70, pdfWidth, commissionPdfHeight);
                                pdf.save('staff_performance_metrics.pdf');
                            });
                        }

                        // Fetch logo and save PDF
                        fetch('/assets/img/logo.png')
                            .then(response => response.blob())
                            .then(blob => {
                                const reader = new window.FileReader();
                                reader.onloadend = function () {
                                    addLogoAndSave(reader.result);
                                };
                                reader.readAsDataURL(blob);
                            })
                            .catch(() => {
                                const logoImg = document.querySelector('img[alt="Inua Premium Logo"], img[alt="inua premium logo"]');
                                if (logoImg && logoImg.src) {
                                    if (logoImg.src.startsWith('data:image')) {
                                        addLogoAndSave(logoImg.src);
                                    } else {
                                        const tempImg = new window.Image();
                                        tempImg.crossOrigin = "Anonymous";
                                        tempImg.onload = function () {
                                            const canvas = document.createElement('canvas');
                                            canvas.width = tempImg.width;
                                            canvas.height = tempImg.height;
                                            const ctx = canvas.getContext('2d');
                                            ctx.drawImage(tempImg, 0, 0);
                                            const dataURL = canvas.toDataURL('image/png');
                                            addLogoAndSave(dataURL);
                                        };
                                        tempImg.src = logoImg.src;
                                    }
                                } else {
                                    addLogoAndSave(null);
                                }
                            });
                    });
                }, 300);
            });

            // Framework PDF download logic
            document.getElementById('download-framework-pdf-btn').addEventListener('click', function () {
                const frameworkSection = document.getElementById('organizational-framework');
                const btn = document.getElementById('download-framework-pdf-btn');
                const originalText = btn.textContent;
                btn.textContent = '⏳ Generating PDF...';
                btn.disabled = true;
                
                setTimeout(() => {
                    // Clone the section to avoid affecting the original DOM
                    const cloneSection = frameworkSection.cloneNode(true);
                    cloneSection.style.position = 'absolute';
                    cloneSection.style.left = '-9999px';
                    cloneSection.style.width = '1200px';
                    document.body.appendChild(cloneSection);
                    
                    html2canvas(cloneSection, { 
                        backgroundColor: "#ffffff", 
                        scale: 2,
                        allowTaint: true,
                        useCORS: true,
                        logging: false,
                        imageTimeout: 5000,
                        windowHeight: cloneSection.scrollHeight,
                        windowWidth: 1200
                    }).then(canvas => {
                        try {
                            const imgData = canvas.toDataURL('image/png');
                            const pdf = new window.jspdf.jsPDF({
                                orientation: 'portrait',
                                unit: 'mm',
                                format: 'a4'
                            });
                            
                            const pageWidth = pdf.internal.pageSize.getWidth();
                            const pageHeight = pdf.internal.pageSize.getHeight();
                            const imgProps = pdf.getImageProperties(imgData);
                            const imgWidth = pageWidth - 20;
                            const imgHeight = (imgProps.height * imgWidth) / imgProps.width;
                            
                            let yPos = 10;
                            const maxHeight = pageHeight - 20;
                            
                            // Add title
                            pdf.setFontSize(14);
                            pdf.text("Organizational Framework & Supervisory Structure", 10, yPos);
                            yPos += 8;
                            
                            pdf.setFontSize(10);
                            pdf.text("Inua Premium - June 2026", 10, yPos);
                            yPos += 10;
                            
                            // Add image, handling multiple pages if needed without duplication
                            const sourceCanvas = document.createElement('canvas');
                            sourceCanvas.width = canvas.width;
                            sourceCanvas.height = canvas.height;
                            const sourceCtx = sourceCanvas.getContext('2d');
                            sourceCtx.drawImage(canvas, 0, 0);
                            
                            let remainingHeight = imgHeight;
                            let canvasYOffset = 0;
                            let isFirstPage = true;
                            
                            while (remainingHeight > 0) {
                                if (!isFirstPage) {
                                    pdf.addPage();
                                    yPos = 10;
                                }
                                
                                const heightToDraw = Math.min(remainingHeight, maxHeight - yPos);
                                const sourcePixelHeight = (heightToDraw * imgProps.height) / imgHeight;
                                
                                // Create a cropped canvas for this section
                                const croppedCanvas = document.createElement('canvas');
                                croppedCanvas.width = sourceCanvas.width;
                                croppedCanvas.height = Math.ceil(sourcePixelHeight);
                                const croppedCtx = croppedCanvas.getContext('2d');
                                croppedCtx.drawImage(
                                    sourceCanvas,
                                    0,
                                    Math.round(canvasYOffset),
                                    sourceCanvas.width,
                                    Math.ceil(sourcePixelHeight),
                                    0,
                                    0,
                                    sourceCanvas.width,
                                    Math.ceil(sourcePixelHeight)
                                );
                                
                                const croppedImgData = croppedCanvas.toDataURL('image/png');
                                pdf.addImage(
                                    croppedImgData,
                                    'PNG',
                                    10,
                                    yPos,
                                    imgWidth,
                                    heightToDraw,
                                    undefined,
                                    'FAST'
                                );
                                
                                canvasYOffset += sourcePixelHeight;
                                remainingHeight -= heightToDraw;
                                isFirstPage = false;
                            }
                            
                            pdf.save('organizational_framework.pdf');
                            document.body.removeChild(cloneSection);
                            btn.textContent = originalText;
                            btn.disabled = false;
                        } catch (error) {
                            console.error('PDF generation error:', error);
                            alert('Error generating PDF. Please try again.');
                            document.body.removeChild(cloneSection);
                            btn.textContent = originalText;
                            btn.disabled = false;
                        }
                    }).catch(error => {
                        console.error('Canvas error:', error);
                        alert('Error capturing framework. Please try again.');
                        document.body.removeChild(cloneSection);
                        btn.textContent = originalText;
                        btn.disabled = false;
                    });
                }, 300);
            });

            // Now render officer profiles in sorted order
            const profilesGrid = document.getElementById('profiles-grid');
            profilesGrid.innerHTML = '';
            officerData.forEach((officer, index) => {
                // Determine commission status based on loan book and composite score
                let commissionStatus = '';
                let statusColor = '';
                let commissionRate = 0;

                if (officer.loanBook >= 600000 && officer.compositeScore >= 0.5) {
                    commissionStatus = 'Qualified for Commission';
                    statusColor = 'text-green-600 font-bold';
                    commissionRate = 100;
                } else if (officer.compositeScore >= 0.5 && officer.loanBook < 600000) {
                    commissionStatus = 'Grow Loan Book to Qualify';
                    statusColor = 'text-yellow-600 font-bold';
                    commissionRate = Math.round((officer.loanBook / 600000) * 100);
                    commissionRate = Math.max(0, Math.min(commissionRate, 99));
                } else {
                    commissionStatus = 'Improve Composite Score';
                    statusColor = 'text-orange-500 font-medium';
                    commissionRate = Math.round((officer.compositeScore * 100));
                    commissionRate = Math.max(0, Math.min(commissionRate, 49));
                }

                // Generate dynamic personalized advice
                function getAdvice(officer) {
                    // Unique, context-aware advice for each officer
                    switch (officer.name) {
                        case 'Antonio Cheruiyot':
                            return 'Antonio, your portfolio quality is excellent and your arrears are well managed. To further excel, focus on increasing recruitment and disbursement to grow your loan book even more. Maintain your low PAR and continue your disciplined approach.';
                        case 'Dennis Kipng\'eno':
                            return 'Dennis, you have a strong loan book and high recruitment, but your arrears and PAR are elevated. Prioritize collections and risk management to reduce arrears and PAR. Leverage your recruitment skills to attract more creditworthy clients.';
                        case 'Yegon Dennis':
                            return 'Yegon, your risk metrics are outstanding and your arrears are very low. To reach the next level, focus on boosting your recruitment and disbursement numbers to expand your portfolio while maintaining quality.';
                        case 'Bethsheba':
                            return 'Bethsheba, your PAR and arrears are low, showing good risk management. To improve further, increase your recruitment and disbursement to grow your loan book and overall impact.';
                        case 'Elsie':
                            return 'Elsie, your arrears and PAR are high, which is impacting your overall performance. Focus on collections and customer follow-up to reduce risk. Also, work on growing your loan book and recruiting more quality clients.';
                        case 'Betty':
                            return 'Betty, you have a strong performing book and low arrears. To further improve, increase your recruitment and disbursement to maximize your portfolio growth while maintaining your excellent risk profile.';
                        case 'Audrey':
                            return 'Audrey, your risk metrics are good and arrears are low. To move to the next tier, focus on increasing your recruitment and loan book size, and continue your strong arrears management.';
                        case 'Emmanuel':
                            return 'Emmanuel, your loan book is solid but arrears and PAR are high. Prioritize collections and risk management, and aim to reduce the number of customers in arrears. Recruitment efforts can also be increased.';
                        case 'Penina':
                            return 'Penina, you have a well-managed portfolio with low PAR and arrears. To further excel, focus on increasing your recruitment and disbursement to grow your loan book and overall performance.';
                        case 'Brenda':
                            return 'Brenda, your arrears and PAR are high relative to your loan book size. Focus on collections and risk management, and work on recruiting more quality clients to grow your portfolio.';
                        case 'Faith Chepkirui':
                            return 'Faith, your loan book is growing but arrears and PAR are elevated. Prioritize reducing arrears and PAR through better collections and monitoring, and increase recruitment to boost your performance.';
                        default:
                            // Fallback: generic advice based on metrics
                            let advice = [];
                            if (officer.loanBook < 600000) advice.push('Grow your loan book by recruiting more quality clients.');
                            if (officer.par > 7) advice.push('Reduce your PAR by improving collections.');
                            if (officer.arrearsValue > 50000) advice.push('Focus on reducing arrears.');
                            if (officer.customersInArrearsPercentage > 20) advice.push('Reduce the percentage of customers in arrears.');
                            if (officer.recruitedCustomers < 3) advice.push('Increase recruitment efforts.');
                            if (officer.compositeScore < 0.5) advice.push('Overall performance needs improvement.');
                            if (advice.length === 0) advice.push('Outstanding overall performance! Continue your best practices.');
                            return advice.join(' ');
                    }
                }

                const card = document.createElement('div');
                card.className = 'bg-white p-5 rounded-lg shadow-sm border border-gray-200 transition-transform transform hover:scale-105';
                card.innerHTML = `
                    <div class="flex justify-between items-start">
                        <h3 class="text-lg font-bold text-gray-900">${officer.name}</h3>
                        <span class="tier-badge tier-${officer.tier}">Tier ${officer.tier}</span>
                    </div>
                    <div class="mt-4 space-y-2 text-sm text-gray-600">
                        <p class="flex justify-between"><span>Loan Book:</span> <span class="font-semibold text-gray-800">${officer.loanBook.toLocaleString()} KES</span></p>
                        <p class="flex justify-between"><span>PAR:</span> <span class="font-semibold text-gray-800">${officer.par}%</span></p>
                        <p class="flex justify-between"><span>Total Overdue Amount:</span> <span class="font-semibold text-gray-800">${officer.totalOverdueAmount.toLocaleString()} KES</span></p>
                        <p class="flex justify-between"><span>Income:</span> <span class="font-semibold text-gray-800">${officer.income.toLocaleString()} KES</span></p>
                        <p class="flex justify-between"><span>Net Income:</span> <span class="font-semibold text-gray-800">${officer.netIncome.toLocaleString()} KES</span></p>
                        <p class="flex justify-between"><span>Funded Customers:</span> <span class="font-semibold text-gray-800">${officer.recruitedCustomers}</span></p>
                        <p class="flex justify-between"><span>Disbursed Value:</span> <span class="font-semibold text-gray-800">${officer.disbursementValue.toLocaleString()} KES</span></p>
                        <p class="flex justify-between"><span>Commission Qualification Rate:</span> <span class="font-semibold ${commissionRate === 100 ? 'text-green-600' : commissionRate >= 50 ? 'text-yellow-600' : 'text-orange-500'}">
                            ${commissionRate}%${commissionRate === 100 ? ' (Qualified)' : commissionRate >= 50 ? ' (Partial)' : ''}
                        </span></p>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">Composite Score: ${(officer.compositeScore * 100).toFixed(1)}%</div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <p class="text-sm ${statusColor}">${commissionStatus}</p>
                    </div>
                    <!-- Personalized Advice Section -->
                    <div class="mt-4">
                        <button class="accordion-button">
                            <span class="font-bold text-gray-800 text-sm">Personalized Advice</span>
                            <span class="accordion-icon text-gray-500">▼</span>
                        </button>
                        <div class="accordion-content">
                            <p>${getAdvice(officer)}</p>
                        </div>
                    </div>
                `;
                profilesGrid.appendChild(card);
            });
            
            // Refactored to handle multiple accordion buttons
            document.querySelectorAll('.accordion-button').forEach(button => {
                button.addEventListener('click', () => {
                    const content = button.nextElementSibling;
                    const icon = button.querySelector('.accordion-icon');
                    
                    if (content.style.maxHeight) {
                        content.style.maxHeight = null;
                        icon.classList.remove('rotate-180');
                    } else {
                        // Close other open accordions
                        document.querySelectorAll('.accordion-content').forEach(otherContent => {
                            if (otherContent !== content && otherContent.style.maxHeight) {
                                otherContent.style.maxHeight = null;
                                otherContent.previousElementSibling.querySelector('.accordion-icon').classList.remove('rotate-180');
                            }
                        });
                        content.style.maxHeight = content.scrollHeight + "px";
                        icon.classList.add('rotate-180');
                    }
                });
            });

            const ctx = document.getElementById('officerRankingsChart').getContext('2d');
            Chart.register(ChartDataLabels);
            let rankingsChart = new Chart(ctx, {
                type: 'bar',
                data: { labels: [], datasets: [{ label: '', data: [], backgroundColor: [], borderColor: [], borderWidth: 1 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, grid: { color: '#e5e7eb' } },
                        y: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const metricKey = document.getElementById('metric-select').value;
                                    return metricConfig[metricKey].format(context.raw);
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'center',
                            align: 'center',
                            color: '#fff',
                            font: { weight: 'bold', size: 11 },
                            formatter: function(value, context) {
                                const metricKey = document.getElementById('metric-select').value;
                                return metricConfig[metricKey].format(value);
                            }
                        }
                    }
                }
            });

                function updateChart() {
                    const selectedMetric = document.getElementById('metric-select').value;
                    const config = metricConfig[selectedMetric];
                    const higherIsWorse = config.higherIsWorse;

                    // Sort officer data based on the selected metric
                    officerData.sort((a, b) => {
                        const aHasData = hasPerformanceData(a);
                        const bHasData = hasPerformanceData(b);
                        if (aHasData !== bHasData) return aHasData ? -1 : 1;
                        const valA = a[selectedMetric];
                        const valB = b[selectedMetric];
                        // Sort descending for better performance (higher is better), ascending otherwise
                        return higherIsWorse ? valA - valB : valB - valA;
                    });

                    const labels = officerData.map(o => o.name);
                    const data = officerData.map(o => o[selectedMetric]);

                    const goodColor = 'rgba(16, 185, 129, 0.6)';
                    const badColor = 'rgba(239, 68, 68, 0.6)';
                    const neutralColor = 'rgba(59, 130, 246, 0.6)';

                    const backgroundColors = officerData.map(o => {
                        if (o.tier === 1) return goodColor;
                        if (o.tier === 4) return badColor;
                        if (o.tier === 3 && higherIsWorse) return 'rgba(249, 115, 22, 0.6)';
                        if (o.tier === 3 && !higherIsWorse) return neutralColor;
                        return neutralColor;
                    });

                    rankingsChart.data.labels = labels;
                    rankingsChart.data.datasets[0].data = data;
                    rankingsChart.data.datasets[0].label = config.label;
                    rankingsChart.data.datasets[0].backgroundColor = backgroundColors;
                    rankingsChart.options.scales.x.title = { display: true, text: config.label };
                    rankingsChart.options.scales.x.ticks = { callback: config.format };
                    rankingsChart.update();

                    const contextEl = document.getElementById('chart-context');
                    const sortOrder = higherIsWorse ? 'lowest to highest' : 'highest to lowest';
                    contextEl.textContent = `Officers ranked from ${sortOrder} based on their ${config.label}. Bar colors correspond to performance tiers.`;
                }

                document.getElementById('metric-select').addEventListener('change', updateChart);
                updateChart();

                // --- DYNAMIC EXECUTIVE SUMMARY AND STRATEGIC TIERING ---
                function generateDynamicSummary(officers) {
                    // Group by tier
                    const tiers = { 1: [], 2: [], 3: [], 4: [] };
                    officers.forEach(o => tiers[o.tier].push(o));

                    function officerList(arr) {
                        return arr.length
                            ? arr.map(o => `<span class="font-semibold">${o.name}</span>`).join(', ')
                            : '<span class="italic text-gray-400">None</span>';
                    }

                    function tierSummary(tier, arr) {
                        if (!arr.length) return '';
                        // Find best/worst metrics for this tier
                        const avgPAR = (arr.reduce((s, o) => s + o.par, 0) / arr.length).toFixed(1);
                        const avgLoanBook = Math.round(arr.reduce((s, o) => s + o.loanBook, 0) / arr.length);
                        const avgArrears = Math.round(arr.reduce((s, o) => s + o.arrearsValue, 0) / arr.length);
                        const avgScore = (arr.reduce((s, o) => s + o.compositeScore, 0) / arr.length * 100).toFixed(1);

                        let desc = '';
                        if (tier === 1) {
                            desc = `Exemplary performers with the <span class="text-green-700 font-semibold">lowest risk (avg PAR: ${avgPAR}%)</span>, 
                                <span class="text-green-700 font-semibold">highest portfolio quality</span> and strong productivity (avg loan book: KES ${avgLoanBook.toLocaleString()}). 
                                Their composite score averages <span class="font-semibold">${avgScore}%</span>.`;
                        } else if (tier === 2) {
                            desc = `Strong and balanced performers, maintaining <span class="text-blue-700 font-semibold">good risk metrics (avg PAR: ${avgPAR}%)</span> 
                                and high productivity (avg loan book: KES ${avgLoanBook.toLocaleString()}). Composite score avg: <span class="font-semibold">${avgScore}%</span>.`;
                        } else if (tier === 3) {
                            desc = `High-volume, high-risk portfolios. These officers have <span class="text-orange-600 font-semibold">elevated arrears (avg: KES ${avgArrears.toLocaleString()})</span> 
                                and PAR (avg: ${avgPAR}%), but drive significant business. Composite score avg: <span class="font-semibold">${avgScore}%</span>.`;
                        } else if (tier === 4) {
                            desc = `High-risk or underperforming portfolios, with <span class="text-red-600 font-semibold">highest arrears (avg: KES ${avgArrears.toLocaleString()})</span> 
                                and PAR (avg: ${avgPAR}%). Immediate intervention is required. Composite score avg: <span class="font-semibold">${avgScore}%</span>.`;
                        }
                        return `<li class="mb-2"><strong>Tier ${tier}:</strong> ${officerList(arr)}<br><span class="ml-2">${desc}</span></li>`;
                    }

                    return `
                        <ul class="list-disc list-inside mt-2 space-y-1">
                            ${tierSummary(1, tiers[1])}
                            ${tierSummary(2, tiers[2])}
                            ${tierSummary(3, tiers[3])}
                            ${tierSummary(4, tiers[4])}
                        </ul>
                        <p class="mt-2">
                            <span class="font-semibold">Note:</span> Tiers and summaries above are dynamically generated based on each officer's composite score, risk, productivity, and portfolio quality.
                        </p>
                    `;
                }

                // Replace static Executive Summary and Strategic Tiering with dynamic content
                const summarySection = document.querySelector('#full-report .accordion-content');
                if (summarySection) {
                    // ...existing code up to <ul class="list-disc ...">...
                    summarySection.innerHTML = summarySection.innerHTML.replace(
                        /<ul class="list-disc[\s\S]*?<\/ul>/,
                        generateDynamicSummary(officerData)
                    );
                }

                // Function to calculate commission baseline
                function getCommissionBaseline(loanBook, par, compositeScore) {
                    // Not entitled case explicitly specified: below 600k, par < 7% and composite >= 50%
                    if (loanBook < 600000 && par < 7 && compositeScore >= 0.5) {
                        return 0;
                    }

                    // Only officers with loanBook >= 600,000 are considered for baselines
                    if (loanBook >= 600000) {
                        // Override cases that always get a 12,000 baseline:
                        // - par > 7% AND composite >= 50%
                        // - par <= 7% AND composite < 50%
                        if ((par > 7 && compositeScore >= 0.5) || (par <= 7 && compositeScore < 0.5)) {
                            return 12000;
                        }

                        // If par <= 7% AND composite >= 50% -> apply tiered baseline by loanbook range
                        if (par <= 7 && compositeScore >= 0.5) {
                            if (loanBook >= 600000 && loanBook < 1000000) {
                                return 15000;
                            } else if (loanBook >= 1000000 && loanBook < 1500000) {
                                return 20000;
                            } else if (loanBook >= 1500000 && loanBook < 2000000) {
                                return 25000;
                            } else if (loanBook >= 2000000) {
                                return 30000; // cap at 30,000 for 2M+
                            }
                        }
                    }

                    // All other cases are not entitled
                    return 0;
                }

                // Dynamically generate the "Overall Performance Metrics: Aggregated Analysis and Comparative Ranking" table
                function renderAggregatedMetricsTable(officers) {
                    // Sort by composite score descending
                    const sorted = [...officers].sort((a, b) => b.compositeScore - a.compositeScore);

                    const tableBody = document.getElementById('aggregated-metrics-table-body');
                    if (!tableBody) return;

                    tableBody.innerHTML = '';

                    sorted.forEach((o, idx) => {
                        const baselineAmount = getCommissionBaseline(o.loanBook, o.par, o.compositeScore);
                        const commissionAmount = Math.round(o.compositeScore * baselineAmount);

                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${idx + 1}</td>
                            <td class="font-semibold">${o.name}</td>
                            <td>${o.principal.toLocaleString()}</td>
                            <td>${o.loanBook.toLocaleString()}</td>
                            <td>${o.performingBook.toLocaleString()}</td>
                            <td>${o.arrearsValue.toLocaleString()}</td>
                            <td>${o.par}%</td>
                            <td>${o.customers}</td>
                            <td>${o.customersInArrears}</td>
                            <td>${o.customersInArrearsPercentage}%</td>
                            <td>${(o.compositeScore * 100).toFixed(1)}%</td>
                            <td>KES ${commissionAmount.toLocaleString()}</td>
                        `;
                        tableBody.appendChild(row);
                    });
                }

                // Call the function to populate the table
                renderAggregatedMetricsTable(officerData);

                // Dynamically render the "In-Depth Individual Performance Profiles" section
                function renderIndividualProfiles(officers) {
                    // Group officers by tier
                    const tier1 = officers.filter(o => o.tier === 1);
                    const tier2 = officers.filter(o => o.tier === 2);
                    const tier3 = officers.filter(o => o.tier === 3);
                    const tier4 = officers.filter(o => o.tier === 4);

                    function officerNames(arr) {
                        return arr.map(o => `<span class="font-semibold">${o.name}</span>`).join(', ');
                    }

                    let html = '';

                    // Tier 1
                    if (tier1.length) {
                        html += `
                        <h5 class="text-sm font-bold mt-4">Profile: ${officerNames(tier1)} (The Benchmark Performers)</h5>
                        <p>These officers' performance is exemplary and serves as the benchmark for the entire team. Their portfolio quality is unmatched, with PARs of ${tier1.map(o => o.par + '%').join(', ')}. Their loan books are meticulously managed, as evidenced by a low number of customers in arrears and excellent performing books. The consistency of their performance suggests a combination of a superior client selection process, effective relationship management, and a highly disciplined collection methodology. Their strategies should be documented and formalized into an organization-wide best-practice standard for training and process improvement. They are ideal candidates for mentorship roles and for spearheading growth initiatives.</p>
                        `;
                    }

                    // Tier 2
                    if (tier2.length) {
                        html += `
                        <h5 class="text-sm font-bold mt-4">Profile: ${officerNames(tier2)} (Strong, Consistent Performers)</h5>
                        <p>These officers have successfully navigated the balance between productivity and risk management. ${tier2.map(o => o.name + ' has a loan book of KES ' + o.loanBook.toLocaleString() + ' and maintains a PAR of ' + o.par + '%.').join(' ')} Their performance demonstrates that high-volume lending is compatible with high portfolio quality. Their success is critical proof that the risk issues observed in other portfolios are not an inevitable consequence of size. These officers possess the ability to scale while maintaining quality, making them ideal candidates for mentorship roles and for spearheading growth initiatives.</p>
                        `;
                    }

                    // Tier 3
                    if (tier3.length) {
                        html += `
                        <h5 class="text-sm font-bold mt-4">Profile: ${officerNames(tier3)} (High-Volume, High-Risk Portfolios)</h5>
                        <p>These officers represent a significant risk to the overall portfolio health due to the size of their portfolios combined with elevated risk metrics. ${tier3.map(o => o.name + "'s portfolio has a PAR of " + o.par + "% and " + o.customersInArrearsPercentage + "% of customers in arrears.").join(' ')} This high percentage of customers in arrears suggests a potential systemic problem with their client base, perhaps resulting from a trade-off between the speed of growth and the quality of client selection. These officers require urgent attention to their collection processes.</p>
                        `;
                    }

                    // Tier 4
                    if (tier4.length) {
                        html += `
                        <h5 class="text-sm font-bold mt-4">Profile: ${officerNames(tier4)} (The High-Risk Anomaly)</h5>
                        <p>These portfolios are the most volatile and highest risk in the entire dataset. ${tier4.map(o => o.name + "'s total PAR is " + o.par + "%, with " + o.customersInArrearsPercentage + "% of customers in arrears.").join(' ')} Portfolio growth should be immediately paused, and a deep-dive investigation into the root causes of high arrears is required. These officers require direct, targeted interventions to mitigate potential losses.</p>
                        `;
                    }

                    return html;
                }

                // Replace the static profiles section with dynamic content
                const fullReportContent = document.querySelector('#full-report .accordion-content');
                if (fullReportContent) {
                    fullReportContent.innerHTML = fullReportContent.innerHTML.replace(
                        /<h4 class="text-md font-bold mt-4">In-Depth Individual Performance Profiles<\/h4>[\s\S]*?(?=<h4 class="text-md font-bold mt-6")/,
                        `<h4 class="text-md font-bold mt-4">In-Depth Individual Performance Profiles</h4>
                        <p class="mt-2">This section moves beyond aggregated rankings to provide a narrative and contextual analysis of each officer's performance, identifying the underlying factors behind their metrics.</p>
                        ${renderIndividualProfiles(officerData)}`
                    );
                }
            });
        </script>
    </body>
</html>
