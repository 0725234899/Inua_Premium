
<?php
function simulateC2BPayment() {
    $accessToken = getAccessToken(); // Fetch access token function
    $url = "https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate";

    $postData = [
        "ShortCode" => "600000", // Sandbox shortcode
        "CommandID" => "CustomerPayBillOnline",
        "Amount" => "100",
        "Msisdn" => "254708374149", // Test MSISDN
        "BillRefNumber" => "LOAN001"
    ];

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}
?>
