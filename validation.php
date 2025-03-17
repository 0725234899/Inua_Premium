
<?php
header("Content-Type: application/json");

$data = file_get_contents('php://input');
$request = json_decode($data, true);

// Extract payment details
$amount = $request['TransAmount'];
$transactionID = $request['TransID'];
$accountReference = $request['BillRefNumber']; // Loan ID or customer reference

$response = [
    "ResultCode" => 0, // 0 means payment accepted
    "ResultDesc" => "Payment validated successfully"
];

echo json_encode($response);
?>
