
<?php
function getAccessToken() {
    $consumerKey = "YOUR_CONSUMER_KEY";
    $consumerSecret = "YOUR_CONSUMER_SECRET";
    $url = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);

    $response = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($response, true);
    return $result['access_token'];
}
?>
