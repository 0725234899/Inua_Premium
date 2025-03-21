<?php
include 'includes/db_connect.php';

$loan_product = "Personal Loan";
$business_loan = "";
$borrower = "John Doe";
$loan_number = "LN12345";
$custom_loan_number = "CLN123";
$principal_amount = 5000.00;
$disbursed_by = "Cash";
$loan_release_date = "2024-08-01";
$interest_amount = 500.00;
$interest_method = "flat_rate";
$loan_interest_percentage = 10.00;
$loan_duration = 12;
$repayment_cycle = "monthly";
$number_of_repayments = 12;
$automated_payments = 1;
$extend_loan_after_maturity = 0;
$processing_fee = 2.00;
$registration_fee = 1.00;
$loan_status = "open";
$guarantors = "John Smith, Jane Doe";
$loan_title = "John's Personal Loan";
$description = "Loan for personal expenses.";
$accounting_account = "cash";

// Insert loan details into the database
$sql = "INSERT INTO loan (loan_product, business_loan, borrower, loan_number, custom_loan_number, principal_amount, disbursed_by, loan_release_date, interest_amount, interest_method, loan_interest_percentage, loan_duration, repayment_cycle, number_of_repayments, automated_payments, extend_loan_after_maturity, processing_fee, registration_fee, loan_status, guarantors, loan_title, description, accounting_account) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssssissssssiiiddsss", $loan_product, $business_loan, $borrower, $loan_number, $custom_loan_number, $principal_amount, $disbursed_by, $loan_release_date, $interest_amount, $interest_method, $loan_interest_percentage, $loan_duration, $repayment_cycle, $number_of_repayments, $automated_payments, $extend_loan_after_maturity, $processing_fee, $registration_fee, $loan_status, $guarantors, $loan_title, $description, $accounting_account);

if ($stmt->execute()) {
    $loan_id = $stmt->insert_id;
    generateRepaymentSchedule($conn, $loan_id, $principal_amount, $interest_amount, $repayment_cycle, $number_of_repayments, $loan_release_date);
    echo "Loan application submitted successfully.";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

function generateRepaymentSchedule($conn, $loan_id, $principal_amount, $interest_amount, $repayment_cycle, $number_of_repayments, $loan_release_date) {
    $start_date = new DateTime($loan_release_date);
    $interval = $repayment_cycle === "monthly" ? 'P1M' : 'P1W';
    $period = new DateInterval($interval);
    
    for ($i = 1; $i <= $number_of_repayments; $i++) {
        $repayment_date = $start_date->format('Y-m-d');
        $start_date->add($period);
    
        $repayment_amount = calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments);
        
        $sql = "INSERT INTO repayments (loan_id, amount, repayment_date) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            die('Prepare failed: ' . $conn->error);
        }
        
        echo "Inserting repayment: Loan ID: $loan_id, Amount: $repayment_amount, Date: $repayment_date<br>";
        
        // Bind parameters
        $stmt->bind_param("ids", $loan_id, $repayment_amount, $repayment_date);
    
        if ($stmt->execute()) {
            echo "Repayment scheduled for $repayment_date<br>";
        } else {
            echo "Error inserting repayment schedule: " . $stmt->error . "<br>";
        }
    
        $stmt->close();
    }
}

function calculateRepaymentAmount($principal_amount, $interest_amount, $number_of_repayments) {
    $total_amount = $principal_amount + $interest_amount;
    return $total_amount / $number_of_repayments;
}
?>
