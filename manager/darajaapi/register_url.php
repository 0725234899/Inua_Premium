<?php
//INCLUDE ACCESS TOKEN FILE 
include 'accessToken.php';
$registerurl = $mpesaApiConfig['base_url'] . '/mpesa/c2b/v1/registerurl';
$BusinessShortCode = '174379';
$confirmationUrl = 'https://immortal-ox-completely.ngrok-free.app8080/darajaapi/confirmation_url.php';
$validationUrl = 'https://immortal-ox-completely.ngrok-free.app8080/darajaapi/validation_url.php';

$BusinessShortCode = $mpesaApiConfig['c2b_shortcode'] !== '' ? $mpesaApiConfig['c2b_shortcode'] : $BusinessShortCode;
$confirmationUrl = $mpesaApiConfig['c2b_confirmation_url'] !== '' ? $mpesaApiConfig['c2b_confirmation_url'] : $confirmationUrl;
$validationUrl = $mpesaApiConfig['c2b_validation_url'] !== '' ? $mpesaApiConfig['c2b_validation_url'] : $validationUrl;
$responseType = $mpesaApiConfig['c2b_response_type'];

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $registerurl);
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
  'Content-Type:application/json',
  'Authorization:Bearer ' . $access_token
));
$data = array(
  'ShortCode' => $BusinessShortCode,
  'ResponseType' => $responseType,
  'ConfirmationURL' => $confirmationUrl,
  'ValidationURL' => $validationUrl
);
$data_string = json_encode($data);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
echo $curl_response = curl_exec($curl);