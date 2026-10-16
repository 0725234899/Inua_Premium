<?php
session_start();
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
$loan_interest_percentage = $_POST['loan_interest_percentage'];
$interest_calculation = $_POST['interest_calculation'] ?? 'monthly';
$loan_duration = $_POST['loan_duration'];
$loan_duration_unit = $_POST['loan_duration_unit'];
$repayment_cycle = $_POST['repayment_cycle'];
$number_of_repayments = $_POST['number_of_repayments'];
$processing_fee = $_POST['processing_fee'];
$registration_fee = $_POST['registration_fee'];
$loan_status = "pending";

// Normalize duration unit early so calculations use a consistent value
$loan_duration_unit = normalizeDurationUnit($loan_duration_unit);

// Calculate total interest
$total_interest = 0;
switch ($interest_method) {
    case 'flat_rate':
        $total_interest = ($principal * $loan_interest_percentage * $loan_duration) / 100;
        break;
    case 'percentage':
        $duration_in_weeks = getDurationInWeeks($loan_duration, $loan_duration_unit);
        $interest_per_period = $principal * ($loan_interest_percentage / 100);
        
        switch ($_POST['interest_calculation']) {
            case 'weekly':
                $total_interest = $interest_per_period * $duration_in_weeks;
                break;
            case 'monthly':
                $total_interest = $interest_per_period * ($duration_in_weeks / 4); // assuming 4 weeks per month
                break;
            case 'yearly':
                $total_interest = $interest_per_period * ($duration_in_weeks / 52); // assuming 52 weeks per year
                break;
        }
        break;
    case 'fixed_amount':
        $total_interest = $loan_interest_percentage * $number_of_repayments;
        break;
}

$total_amount_inclusive = $principal + $total_interest + $processing_fee + $registration_fee;
$total_amount=$principal + $total_interest;
// Older databases may not yet have the interest_calculation column.
$columnStmt = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_applications' AND COLUMN_NAME = 'interest_calculation'");
if ($columnStmt === false) {
    throw new RuntimeException('Unable to check the loan_applications schema.');
}
$hasInterestCalculationColumn = (int) $columnStmt->fetchColumn() > 0;

$interestCalculationColumn = $hasInterestCalculationColumn ? ', interest_calculation' : '';
$interestCalculationValue = $hasInterestCalculationColumn ? ', :interest_calculation' : '';
$sql = "INSERT INTO loan_applications 
    (borrower, loan_product, principal, loan_release_date, interest, interest_method{$interestCalculationColumn}, loan_interest, loan_duration, loan_duration_unit, repayment_cycle, number_of_repayments, processing_fee, registration_fee, loan_status, total_amount, total_amount_inclusive) 
    VALUES (:borrower, :loan_product, :principal, :loan_release_date, :interest, :interest_method{$interestCalculationValue}, :loan_interest, :loan_duration, :loan_duration_unit, :repayment_cycle, :number_of_repayments, :processing_fee, :registration_fee, :loan_status, :total_amount, :total_amount_inclusive)";

$stmt = $conn->prepare($sql);
$stmt->bindValue(':borrower', $borrower, PDO::PARAM_STR);
$stmt->bindValue(':loan_product', $loan_product, PDO::PARAM_STR);
$stmt->bindValue(':principal', $principal, PDO::PARAM_STR);
$stmt->bindValue(':loan_release_date', $loan_release_date, PDO::PARAM_STR);
$stmt->bindValue(':interest', $interest, PDO::PARAM_STR);
$stmt->bindValue(':interest_method', $interest_method, PDO::PARAM_STR);
if ($hasInterestCalculationColumn) {
    $stmt->bindValue(':interest_calculation', $interest_calculation, PDO::PARAM_STR);
}
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
if ($stmt->execute()) {
    $loan_id = $conn->lastInsertId();
    echo "New loan application submitted successfully";

    // Generate repayment schedule
    generateRepaymentSchedule($conn, $loan_id, $principal, $total_interest, $repayment_cycle, $number_of_repayments, $loan_release_date, $loan_duration, $loan_duration_unit);
} else {
    echo "Error: " . $stmt->errorInfo()[2];
}

// Function to convert loan duration to weeks
function normalizeDurationUnit($loan_duration_unit) {
    $unit = strtolower(trim((string)$loan_duration_unit));
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
            return $loan_duration * 4; // Assuming 4 weeks per month
        case 'years':
            return $loan_duration * 52; // Assuming 52 weeks per year
        default:
            return 0;
    }
}

// Function to generate repayment schedule
function generateRepaymentSchedule($conn, $loan_id, $principal_amount, $interest_amount, $repayment_cycle, $number_of_repayments, $loan_release_date, $loan_duration, $loan_duration_unit) {
    $start_date = new DateTime($loan_release_date);

    if ($repayment_cycle === 'once') {
        $maturity_date = getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit);
        $repayment_amount = calculateRepaymentAmount($principal_amount, $interest_amount, 1);
        $sql = "INSERT INTO repayments (loan_id, repayment_date, amount) VALUES (:loan_id, :repayment_date, :amount)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':loan_id', $loan_id, PDO::PARAM_INT);
        $stmt->bindValue(':repayment_date', $maturity_date->format('Y-m-d'), PDO::PARAM_STR);
        $stmt->bindValue(':amount', $repayment_amount, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        // Calculate each repayment date based on the repayment cycle
        for ($i = 1; $i <= $number_of_repayments; $i++) {
            $schedule_date = clone $start_date;
            $schedule_date->modify('+' . getCycleInterval($repayment_cycle));

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
 <?php
// Ensure $principal_amount is defined
if (!isset($principal_amount)) {
    die("Error: Principal amount is not set.");
}

$principal = number_format($principal_amount, 2);
$officerStmt = $conn->prepare('SELECT name FROM users WHERE email = :email LIMIT 1');
$officerStmt->execute([':email' => $_SESSION['email']]);
$officerName = (string) ($officerStmt->fetchColumn() ?: 'A loan officer');

$borrowerStmt = $conn->prepare('SELECT full_name FROM borrowers WHERE id = :borrower LIMIT 1');
$borrowerStmt->execute([':borrower' => (int) $borrower]);
$borrowerName = (string) ($borrowerStmt->fetchColumn() ?: 'a borrower');

$recipientStmt = $conn->query("SELECT DISTINCT phone FROM users WHERE role_id = 4 AND phone IS NOT NULL AND TRIM(phone) <> ''");
$recipients = $recipientStmt ? array_values(array_filter(array_map('trim', $recipientStmt->fetchAll(PDO::FETCH_COLUMN)))) : [];

if ($recipients && function_exists('sendMessage')) {
    $message = "Approval requested: loan application #{$loan_id} for {$borrowerName}, KSH {$principal}, submitted by {$officerName}. Please review and approve.";
    try {
        sendMessage($recipients, $message);
        $smsNotificationFailed = false;
    } catch (Throwable $exception) {
        error_log('Loan application SMS notification failed (' . get_class($exception) . ', code ' . $exception->getCode() . '): ' . $exception->getMessage());
        if ($exception instanceof PDOException) {
            $smsNotificationError = 'The SMS settings could not be read from the database. Check the database connection and try again.';
        } elseif ($exception instanceof RuntimeException || $exception instanceof InvalidArgumentException || $exception instanceof UnexpectedValueException) {
            $smsNotificationError = $exception->getMessage();
        } else {
            $smsNotificationError = 'SMS failed with ' . get_class($exception) . ' (code ' . $exception->getCode() . '). Check the server error log.';
        }
        $smsNotificationFailed = true;
    }
} else {
    $smsNotificationFailed = true;
    if (!$recipients) {
        $smsNotificationError = 'No manager account with a phone number was found. Add a phone number to a manager account (role ID 4) and try again.';
        error_log('Loan application SMS notification failed: no manager phone found for users with role_id = 4.');
    } else {
        $smsNotificationError = 'The SMS sender is unavailable. Check the server configuration.';
        error_log('Loan application SMS notification failed: sendMessage() is not defined.');
    }
}
?>

<script>
    alert(<?php echo json_encode($smsNotificationFailed
        ? 'Loan application submitted, but SMS could not be sent. ' . ($smsNotificationError ?? 'Check Admin > SMS Settings and the server error log.')
        : 'Loan application successful'); ?>);
    location.replace('index.php');
</script>
<?php
}
// Function to send SMS

// Function to calculate repayment amount
function calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments) {
    $total_amount = $principal_amount + $interest_amount; // Make sure both principal and interest are considered
    return $total_amount / $number_of_repayments; // Repayments are evenly split
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
