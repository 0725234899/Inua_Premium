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
$loan_interest_percentage = $_POST['loan_interest_percentage'];
$loan_duration = $_POST['loan_duration'];
$loan_duration_unit = $_POST['loan_duration_unit'];
$repayment_cycle = $_POST['repayment_cycle'];
$number_of_repayments = $_POST['number_of_repayments'];
$processing_fee = $_POST['processing_fee'];
$registration_fee = $_POST['registration_fee'];
$loan_status = "pending";

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
// Prepare SQL to insert loan application
$sql = "INSERT INTO loan_applications 
    (borrower, loan_product, principal, loan_release_date, interest, interest_method, loan_interest, loan_duration, repayment_cycle, number_of_repayments, processing_fee, registration_fee, loan_status, total_amount,total_amount_inclusive, id_photo_path) 
    VALUES (:borrower, :loan_product, :principal, :loan_release_date, :interest, :interest_method, :loan_interest, :loan_duration, :repayment_cycle, :number_of_repayments, :processing_fee, :registration_fee, :loan_status, :total_amount,:total_amount_inclusive, :id_photo_path)";

$stmt = $conn->prepare($sql);
$stmt->bindValue(':borrower', $borrower, PDO::PARAM_STR);
$stmt->bindValue(':loan_product', $loan_product, PDO::PARAM_STR);
$stmt->bindValue(':principal', $principal, PDO::PARAM_STR);
$stmt->bindValue(':loan_release_date', $loan_release_date, PDO::PARAM_STR);
$stmt->bindValue(':interest', $interest, PDO::PARAM_STR);
$stmt->bindValue(':interest_method', $interest_method, PDO::PARAM_STR);
$stmt->bindValue(':loan_interest', $loan_interest_percentage, PDO::PARAM_STR);
$stmt->bindValue(':loan_duration', $loan_duration, PDO::PARAM_INT);
$stmt->bindValue(':repayment_cycle', $repayment_cycle, PDO::PARAM_STR);
$stmt->bindValue(':number_of_repayments', $number_of_repayments, PDO::PARAM_INT);
$stmt->bindValue(':processing_fee', $processing_fee, PDO::PARAM_STR);
$stmt->bindValue(':registration_fee', $registration_fee, PDO::PARAM_STR);
$stmt->bindValue(':loan_status', $loan_status, PDO::PARAM_STR);
$stmt->bindValue(':total_amount', $total_amount, PDO::PARAM_STR);
$stmt->bindValue(':total_amount_inclusive', $total_amount_inclusive, PDO::PARAM_STR);
$stmt->bindValue(':id_photo_path', $target_file, PDO::PARAM_STR); // Store file path

if ($stmt->execute()) {
    $loan_id = $conn->lastInsertId();
    echo "New loan application submitted successfully";

    // Generate repayment schedule
    generateRepaymentSchedule($conn, $loan_id, $principal, $total_interest, $repayment_cycle, $number_of_repayments, $loan_release_date);
} else {
    echo "Error: " . $stmt->errorInfo()[2];
}

// Function to convert loan duration to weeks
function getDurationInWeeks($loan_duration, $loan_duration_unit) {
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
function generateRepaymentSchedule($conn, $loan_id, $principal_amount, $interest_amount, $repayment_cycle, $number_of_repayments, $loan_release_date) {
    $start_date = new DateTime($loan_release_date);

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
    return $total_amount / $number_of_repayments; // Repayments are evenly split
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
        default:
            return '1 month'; // Default to monthly if unspecified
    }
}
?>
