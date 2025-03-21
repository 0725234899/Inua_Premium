<?php
include 'db.php'; // Database connection
header("Content-Type: application/json");

$response = file_get_contents('php://input');
$data = json_decode($response, true);

if ($data['Body']['stkCallback']['ResultCode'] == 0) {
    // Extract transaction details
    $amount = $data['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
    $mpesa_code = $data['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
    $phone = $data['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];

    // Get loan ID for this borrower using phone number
    $sql = "SELECT loan_applications.id AS loan_id FROM borrowers 
            INNER JOIN loan_applications ON borrowers.id = loan_applications.borrower 
            WHERE borrowers.mobile = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $loan_id = $row['loan_id'];

        // Insert payment into the payments table
        $insert_sql = "INSERT INTO payments (phone, amount, mpesa_code, loan_id) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sdsi", $phone, $amount, $mpesa_code, $loan_id);
        $insert_stmt->execute();

        // Call distribute repayment function
        distributeRepayment($loan_id, $amount, $conn);

        http_response_code(200);
        echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
    } else {
        http_response_code(400);
        echo json_encode(["ResultCode" => 1, "ResultDesc" => "No loan found for this phone number"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["ResultCode" => 1, "ResultDesc" => "Failed"]);
}

// Function to distribute payments to unpaid installments
function distributeRepayment($loan_id, $amount_paid, $conn) {
    // Fetch all unpaid installments for this loan, ordered by due date
    $sql = "SELECT id, amount,paid FROM repayments 
            WHERE loan_id = ? AND paid < amount 
            ORDER BY repayment_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Allocate payment to installments
    while ($row = $result->fetch_assoc()) {
        $installment_id = $row['id'];
        $remaining_due = $row['amount'] - $row['paid'];

        if ($amount_paid <= 0) {
            break; // Stop if all money has been distributed
        }

        if ($amount_paid >= $remaining_due) {
            // Pay off this installment completely
            $new_amount_paid = $row['amount']; // Fully paid
            $amount_paid -= $remaining_due; // Deduct from total payment
        } else {
            // Partially pay this installment
            $new_amount_paid = $row['paid'] + $amount_paid;
            $amount_paid = 0; // Fully used up
        }

        // Update installment record
        $update_sql = "UPDATE repayments SET paid = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("di", $new_amount_paid, $installment_id);
        $update_stmt->execute();
    }
}
?>
