<?php
session_start();

// Enable error reporting (for development only)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables (Ensure you have a .env loader like vlucas/phpdotenv)
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
require_once __DIR__ . '/../includes/functions.php';
$mpesaApiConfig = getMpesaApiConfig();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "microfinance";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database Connection Failed: " . $conn->connect_error]));
}

// Configuration
$config = [
    "baseUrl"          => $mpesaApiConfig['base_url'],
    "BusinessShortCode"=> $mpesaApiConfig['shortcode'],
    "consumerKey"      => $mpesaApiConfig['consumer_key'],
    "consumerSecret"   => $mpesaApiConfig['consumer_secret'],
    "passkey"          => $mpesaApiConfig['passkey'],
    "CallBackURL"      => $mpesaApiConfig['callback_url'],
    "AccountReference" => $mpesaApiConfig['account_reference'],
    "TransactionDesc"  => $mpesaApiConfig['transaction_description']
];

// Function to get access token
function generateAccessToken($config) {
    if ($config['consumerKey'] === '' || $config['consumerSecret'] === '') {
        return ["error" => "Configure M-Pesa consumer credentials before requesting a payment."];
    }

    $credentials = base64_encode($config['consumerKey'] . ':' . $config['consumerSecret']);
    $url = $config['baseUrl'] . "/oauth/v1/generate?grant_type=client_credentials";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);

    $json = json_decode($response, true);
    return $json['access_token'] ?? ["error" => "Failed to get access token"];
}

// Function to format phone number
function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone); // Remove non-numeric characters
    if (preg_match('/^0[7-9][0-9]{8}$/', $phone)) {
        return '254' . substr($phone, 1);
    } elseif (preg_match('/^254[7-9][0-9]{8}$/', $phone)) {
        return $phone;
    } else {
        return ["error" => "Invalid phone number format"];
    }
}

// Function to initiate STK Push
function initiateSTKPush($config, $phoneNumber, $amount, $orderNo, $conn) {
    if ($config['BusinessShortCode'] === '' || $config['passkey'] === '' || $config['CallBackURL'] === '') {
        return ["error" => "Configure the M-Pesa Express shortcode, passkey, and callback URL before requesting a payment."];
    }

    $accessToken = generateAccessToken($config);
    if (isset($accessToken['error'])) {
        return $accessToken;
    }

    $url = $config['baseUrl'] . "/mpesa/stkpush/v1/processrequest";
    $timestamp = date('YmdHis');
    $password = base64_encode($config['BusinessShortCode'] . $config['passkey'] . $timestamp);

    $curl_post_data = [
        'BusinessShortCode' => $config['BusinessShortCode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => "CustomerPayBillOnline",
        'Amount' => $amount,
        'PartyA' => $phoneNumber,
        'PartyB' => $config['BusinessShortCode'],
        'PhoneNumber' => $phoneNumber,
        'CallBackURL' => $config['CallBackURL'],
        'AccountReference' => $config['AccountReference'],
        'TransactionDesc' => $config['TransactionDesc'],
    ];

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($response, true);
    
    if (!isset($result['ResponseCode']) || $result['ResponseCode'] !== "0") {
        return ["error" => $result['errorMessage'] ?? "Unknown error occurred"];
    }

    // Save transaction in database
    $stmt = $conn->prepare("INSERT INTO `orders` (`OrderNo`, `Amount`, `Phone`, `CheckoutRequestID`, `MerchantRequestID`) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $orderNo, $amount, $phoneNumber, $result['CheckoutRequestID'], $result['MerchantRequestID']);
    
    if ($stmt->execute()) {
        $_SESSION["MerchantRequestID"] = $result['MerchantRequestID'];
        $_SESSION["CheckoutRequestID"] = $result['CheckoutRequestID'];
        $_SESSION["phone"] = $phoneNumber;
        $_SESSION["orderNo"] = $orderNo;
        return ["success" => "STK Push request sent", "MerchantRequestID" => $result['MerchantRequestID'], "CheckoutRequestID" => $result['CheckoutRequestID']];
    } else {
        return ["error" => "Database Error: " . $stmt->error];
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phoneNumber = $_POST['phoneNumber'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $orderNo = $_POST['orderNo'] ?? null;

    if (!$phoneNumber || !$amount || !$orderNo) {
        echo json_encode(["error" => "Missing required parameters"]);
        exit;
    }

    $formattedPhoneNumber = formatPhoneNumber($phoneNumber);
    if (isset($formattedPhoneNumber['error'])) {
        echo json_encode($formattedPhoneNumber);
        exit;
    }

    $response = initiateSTKPush($config, $formattedPhoneNumber, $amount, $orderNo, $conn);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$conn->close();
?>
