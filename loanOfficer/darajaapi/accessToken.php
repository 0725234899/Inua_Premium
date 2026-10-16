<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
$mpesaApiConfig = getMpesaApiConfig();

//YOU MPESA API KEYS
$consumerKey = "dY6KRmds1TI3zInPWX8OvOtouLHJgbgOXeqyctGEvpECBPKQ"; //Fill with your app Consumer Key

$consumerSecret = "LrDrAog2AsCPVG9ZFZfjKWIIth4x8SLG6yIRJN4s3a6XjJrPkFQPSC5sH1xeaqXP"; //Fill with your app Consumer Secret
//ACCESS TOKEN URL
$consumerKey = $mpesaApiConfig['consumer_key'] !== '' ? $mpesaApiConfig['consumer_key'] : $consumerKey;
$consumerSecret = $mpesaApiConfig['consumer_secret'] !== '' ? $mpesaApiConfig['consumer_secret'] : $consumerSecret;
$access_token_url = $mpesaApiConfig['base_url'] . '/oauth/v1/generate?grant_type=client_credentials';
$headers = ['Content-Type:application/json; charset=utf8'];
$curl = curl_init($access_token_url);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($curl, CURLOPT_HEADER, FALSE);
curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ':' . $consumerSecret);
$result = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$result = json_decode($result);
$access_token = $result->access_token;
curl_close($curl);