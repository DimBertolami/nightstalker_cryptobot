<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$apiKey = 'K5JaS3MyNCevPcBNWAgvjp139sUFkS290Cjq0D6hLukkZogNaL2HqEzekO1Zb72n';
$secret = '1k1wkTro8uGxH6ifk2MTV46YBiJJh8Ivs0tDYLTUcEcFmA10Xgdmk3HPYOlFZvyl';

$timestamp = round(microtime(true) * 1000);

$params = [
    'timestamp' => $timestamp,
    'recvWindow' => 5000
];

$query_string = http_build_query($params);

$signature = hash_hmac('sha256', $query_string, $secret);

$url = "https://testnet.binance.vision/api/v3/account?{$query_string}&signature={$signature}";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-MBX-APIKEY: {$apiKey}"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch);
} else {
    echo $response;
}

curl_close($ch);

?>