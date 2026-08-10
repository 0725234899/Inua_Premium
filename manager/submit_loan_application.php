<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../includes/functions.php';
include 'db.php'; // Ensure this path is correct

$conn = db_connect();

// Get form data
$borrower = $_POST['borrower'];
$loan_product = $_POST['loan_product'];
$principal = $_POST['principal'];
$loan_release_date = $_POST['loan_release_date'];
$interest = $_POST['loan_interest_percentage'];
$interest_method = $_POST['interest_method'];
$interest_calculation = $_POST['interest_calculation'] ?? 'monthly';
$loan_interest_percentage = $_POST['loan_interest_percentage'];
$loan_duration = (float) ($_POST['loan_duration'] ?? 0);
$loan_duration_unit = $_POST['loan_duration_unit'] ?? 'months';
$repayment_cycle = $_POST['repayment_cycle'] ?? 'monthly';
$loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
$number_of_repayments = calculateLoanRepaymentCount($loan_duration, $loan_duration_unit, $repayment_cycle);
$processing_fee = (float) ($_POST['processing_fee'] ?? 0);
$registration_fee = (float) ($_POST['registration_fee'] ?? 0);
$loan_status = "pending";
$target_file = '';
// Projected maturity date (optional) - prefer submitted value, otherwise compute
$projected_maturity_date = isset($_POST['projected_maturity_date']) && $_POST['projected_maturity_date'] !== '' ? $_POST['projected_maturity_date'] : null;

// Calculate total interest
$total_interest = 0;
switch ($interest_method) {
    case 'flat_rate':
        $periodCount = getDurationInPeriods($loan_duration, $loan_duration_unit, $interest_calculation);
        $total_interest = ($principal * ($loan_interest_percentage / 100)) * $periodCount;
        break;
    case 'percentage':
        $periodCount = getDurationInPeriods($loan_duration, $loan_duration_unit, $interest_calculation);
        $total_interest = ($principal * ($loan_interest_percentage / 100)) * $periodCount;
        break;
    case 'fixed_amount':
        $total_interest = $loan_interest_percentage * $number_of_repayments;
        break;
}

$total_amount_inclusive = $principal + $total_interest + $processing_fee + $registration_fee;
$total_amount = $principal + $total_interest;
// Prepare SQL to insert loan application
$hasProjectedColumn = false;
try {
    $colStmt = $conn->prepare("SHOW COLUMNS FROM loan_applications LIKE 'projected_maturity_date'");
    if ($colStmt) {
        $colStmt->execute();
        $colRow = $colStmt->fetch(PDO::FETCH_ASSOC);
        if ($colRow) {
            $hasProjectedColumn = true;
        }
        $colStmt->closeCursor();
    }
} catch (Exception $e) {
    // If the SHOW COLUMNS query fails, assume column is absent and continue
    $hasProjectedColumn = false;
}

if (!$hasProjectedColumn) {
    try {
        $conn->exec("ALTER TABLE loan_applications ADD COLUMN projected_maturity_date DATE NULL");
        $hasProjectedColumn = true;
    } catch (Exception $e) {
        // ignore schema creation errors and continue without the column
    }
}

if ($hasProjectedColumn) {
    $sql = "INSERT INTO loan_applications 
        (borrower, loan_product, principal, loan_release_date, projected_maturity_date, interest, interest_method, loan_interest, loan_duration, loan_duration_unit, repayment_cycle, number_of_repayments, processing_fee, registration_fee, loan_status, total_amount, total_amount_inclusive, id_photo_path) 
        VALUES (:borrower, :loan_product, :principal, :loan_release_date, :projected_maturity_date, :interest, :interest_method, :loan_interest, :loan_duration, :loan_duration_unit, :repayment_cycle, :number_of_repayments, :processing_fee, :registration_fee, :loan_status, :total_amount, :total_amount_inclusive, :id_photo_path)";
} else {
    $sql = "INSERT INTO loan_applications 
        (borrower, loan_product, principal, loan_release_date, interest, interest_method, loan_interest, loan_duration, loan_duration_unit, repayment_cycle, number_of_repayments, processing_fee, registration_fee, loan_status, total_amount, total_amount_inclusive, id_photo_path) 
        VALUES (:borrower, :loan_product, :principal, :loan_release_date, :interest, :interest_method, :loan_interest, :loan_duration, :loan_duration_unit, :repayment_cycle, :number_of_repayments, :processing_fee, :registration_fee, :loan_status, :total_amount, :total_amount_inclusive, :id_photo_path)";
}

$stmt = $conn->prepare($sql);
$stmt->bindValue(':borrower', $borrower, PDO::PARAM_STR);
$stmt->bindValue(':loan_product', $loan_product, PDO::PARAM_STR);
$stmt->bindValue(':principal', $principal, PDO::PARAM_STR);
$stmt->bindValue(':loan_release_date', $loan_release_date, PDO::PARAM_STR);
$stmt->bindValue(':interest', $interest, PDO::PARAM_STR);
$stmt->bindValue(':interest_method', $interest_method, PDO::PARAM_STR);
$stmt->bindValue(':loan_interest', $loan_interest_percentage, PDO::PARAM_STR);
$stmt->bindValue(':loan_duration', $loan_duration, PDO::PARAM_INT);
$stmt->bindValue(':loan_duration_unit', $loan_duration_unit, PDO::PARAM_STR);
$stmt->bindValue(':repayment_cycle', $repayment_cycle, PDO::PARAM_STR);
$stmt->bindValue(':number_of_repayments', $number_of_repayments, PDO::PARAM_INT);
$stmt->bindValue(':processing_fee', $processing_fee, PDO::PARAM_STR);
$stmt->bindValue(':registration_fee', $registration_fee, PDO::PARAM_STR);
$stmt->bindValue(':loan_status', $loan_status, PDO::PARAM_STR);
$stmt->bindValue(':total_amount', $total_amount, PDO::PARAM_STR);
$stmt->bindValue(':total_amount_inclusive', $total_amount_inclusive, PDO::PARAM_STR);
if ($hasProjectedColumn) {
    $stmt->bindValue(':projected_maturity_date', $projected_maturity_date, $projected_maturity_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
}
$stmt->bindValue(':id_photo_path', $target_file, PDO::PARAM_STR); // Store file path

if ($stmt->execute()) {
    $loan_id = $conn->lastInsertId();
    echo "New loan application submitted successfully";

    // Generate repayment schedule
    // If projected maturity date not provided, compute it server-side
    if (empty($projected_maturity_date)) {
        $maturityDateObj = getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit);
        $projected_maturity_date = $maturityDateObj ? $maturityDateObj->format('Y-m-d') : null;
    }
    generateRepaymentSchedule($conn, $loan_id, $principal, $total_interest, $repayment_cycle, $number_of_repayments, $loan_release_date, $loan_duration, $loan_duration_unit, $projected_maturity_date);
} else {
    echo "Error: " . $stmt->errorInfo()[2];
}

// Function to convert loan duration to weeks
function normalizeDurationUnit($loan_duration_unit) {
    $unit = strtolower(trim((string) $loan_duration_unit));
    switch ($unit) {
        case 'day':
        case 'days':
            return 'days';
        case 'week':
        case 'weeks':
            return 'weeks';
        case 'month':
        case 'months':
            return 'months';
        case 'year':
        case 'years':
            return 'years';
        default:
            return 'months';
    }
}

function getDurationInWeeks($loan_duration, $loan_duration_unit) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    switch ($loan_duration_unit) {
        case 'days':
            return $loan_duration / 7;
        case 'weeks':
            return $loan_duration;
        case 'months':
            return $loan_duration * 4;
        case 'years':
            return $loan_duration * 52;
        default:
            return 0;
    }
}

function getDurationInMonths($loan_duration, $loan_duration_unit) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    switch ($loan_duration_unit) {
        case 'days':
            return $loan_duration / 30;
        case 'weeks':
            return $loan_duration / 4;
        case 'months':
            return $loan_duration;
        case 'years':
            return $loan_duration * 12;
        default:
            return 0;
    }
}

function getDurationInYears($loan_duration, $loan_duration_unit) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    switch ($loan_duration_unit) {
        case 'days':
            return $loan_duration / 365;
        case 'weeks':
            return $loan_duration / 52;
        case 'months':
            return $loan_duration / 12;
        case 'years':
            return $loan_duration;
        default:
            return 0;
    }
}

function getDurationInPeriods($loan_duration, $loan_duration_unit, $periodUnit) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    switch ($periodUnit) {
        case 'weekly':
            return getDurationInWeeks($loan_duration, $loan_duration_unit);
        case 'monthly':
            return getDurationInMonths($loan_duration, $loan_duration_unit);
        case 'yearly':
            return getDurationInYears($loan_duration, $loan_duration_unit);
        default:
            return getDurationInWeeks($loan_duration, $loan_duration_unit);
    }
}

function calculateLoanRepaymentCount($loan_duration, $loan_duration_unit, $repayment_cycle) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    if ($repayment_cycle === 'once') {
        return 1;
    }

    $durationInWeeks = getDurationInWeeks($loan_duration, $loan_duration_unit);

    switch ($repayment_cycle) {
        case 'daily':
            return (int) round($durationInWeeks * 7);
        case 'weekly':
            return (int) round($durationInWeeks);
        case 'monthly':
            return (int) round($durationInWeeks / 4);
        case 'yearly':
            return (int) round($durationInWeeks / 52);
        default:
            return 0;
    }
}

// Function to generate repayment schedule
function generateRepaymentSchedule($conn, $loan_id, $principal_amount, $interest_amount, $repayment_cycle, $number_of_repayments, $loan_release_date, $loan_duration, $loan_duration_unit, $projected_maturity_date = null) {
    $start_date = new DateTime($loan_release_date);

    if ($repayment_cycle === 'once') {
        if (!empty($projected_maturity_date)) {
            $maturity_date = new DateTime($projected_maturity_date);
        } else {
            $maturity_date = getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit);
        }
        $repayment_amount = calculateRepaymentAmount($principal_amount, $interest_amount, 1);
        $sql = "INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (:loan_id, :repayment_date, :amount)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':loan_id', $loan_id, PDO::PARAM_INT);
        $stmt->bindValue(':repayment_date', $maturity_date->format('Y-m-d'), PDO::PARAM_STR);
        $stmt->bindValue(':amount', $repayment_amount, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        // Calculate each repayment date based on the repayment cycle.
        // If a projected maturity date was provided, use it as the final repayment date.
        for ($i = 1; $i <= $number_of_repayments; $i++) {
            if ($i === $number_of_repayments && !empty($projected_maturity_date)) {
                $schedule_date = new DateTime($projected_maturity_date);
            } else {
                $schedule_date = clone $start_date;
                $schedule_date->modify('+' . getCycleInterval($repayment_cycle));
            }

            $repayment_amount = calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments);

            $sql = "INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (:loan_id, :repayment_date, :amount)";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':loan_id', $loan_id, PDO::PARAM_INT);
            $stmt->bindValue(':repayment_date', $schedule_date->format('Y-m-d'), PDO::PARAM_STR);
            $stmt->bindValue(':amount', $repayment_amount, PDO::PARAM_STR);
            $stmt->execute();

            $start_date = $schedule_date;
        }
    }
    ?>
    <script>
    alert("Loan application successful");
    location.replace('index.php');
    </script>
    <?php
}

// Function to calculate repayment amount
function calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments) {
    $total_amount = $principal_amount + $interest_amount; // Make sure both principal and interest are considered
    return $number_of_repayments > 0 ? $total_amount / $number_of_repayments : 0; // Repayments are evenly split
}

function getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    $maturity_date = new DateTime($loan_release_date);

    switch ($loan_duration_unit) {
        case 'days':
            $maturity_date->modify('+' . (int) $loan_duration . ' days');
            break;
        case 'weeks':
            $maturity_date->modify('+' . (int) $loan_duration . ' weeks');
            break;
        case 'months':
            $maturity_date->modify('+' . (int) $loan_duration . ' months');
            break;
        case 'years':
            $maturity_date->modify('+' . (int) $loan_duration . ' years');
            break;
        default:
            $maturity_date->modify('+' . (int) $loan_duration . ' months');
            break;
    }

    return $maturity_date;
}

// Function to get cycle interval for repayments
function getCycleInterval($cycle) {
    switch ($cycle) {
        case 'daily':
            return '1 day';
        case 'weekly':
            return '1 week';
        case 'monthly':
            return '1 month';
        case 'yearly':
            return '1 year';
        case 'once':
            return '0 days';
        default:
            return '1 month'; // Default to monthly if unspecified
    }
}
?>
