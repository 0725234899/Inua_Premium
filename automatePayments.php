<?php
function fetchPayments($transactionID) {
    $url = "https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query";
    $accessToken = "YOUR_ACCESS_TOKEN";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);

    $postData = [
        "Initiator" => "testapi",
        "SecurityCredential" => "SECURITY_CREDENTIAL",
        "CommandID" => "TransactionStatusQuery",
        "TransactionID" => $transactionID,
        "PartyA" => "YOUR_SHORTCODE",
        "IdentifierType" => "4",
        "ResultURL" => "https://yourdomain.com/result",
        "QueueTimeOutURL" => "https://yourdomain.com/timeout",
        "Remarks" => "Checking loan repayment"
    ];

    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}
?>
