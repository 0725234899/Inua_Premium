
<?php
function registerUrls() {
    $consumerKey = "YOUR_CONSUMER_KEY";
    $consumerSecret = "YOUR_CONSUMER_SECRET";
    $url = "https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl";

    $accessToken = getAccessToken(); // Ensure you have the access token

    $postData = [
        "ShortCode" => "600000", // Sandbox shortcode
        "ResponseType" => "Completed",
        "ConfirmationURL" => "https://yourdomain.com/confirmation",
        "ValidationURL" => "https://yourdomain.com/validation"
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
