
<?php
header("Content-Type: application/json");

$data = file_get_contents('php://input');
$request = json_decode($data, true);

// Extract payment details
$amount = $request['TransAmount'];
$transactionID = $request['TransID'];
$phoneNumber = $request['MSISDN'];
$accountReference = $request['BillRefNumber']; // Loan ID or customer reference

$pdo = new PDO('mysql:host=localhost;dbname=loan_system', 'root', '');
$query = $pdo->prepare("INSERT INTO repayments (amount, transaction_id, payment_date) VALUES (?, ?, NOW())");
$query->execute([$amount, $transactionID]);

$response = [
    "ResultCode" => 0,
    "ResultDesc" => "Payment processed successfully"
];

echo json_encode($response);
?>
