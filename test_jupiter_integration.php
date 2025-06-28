<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/CryptoDataSourceInterface.php';
require_once __DIR__ . '/includes/JupiterDataSource.php';
require_once __DIR__ . '/includes/CryptoDataSourceManager.php';

// Verify API key is defined
if (!defined('JUPITER_API_KEY') || empty(JUPITER_API_KEY)) {
    die("ERROR: JUPITER_API_KEY is not defined in config.php\n");
}

// Debug output
echo "Using API Key: " . JUPITER_API_KEY . "\n\n";

// Test connection directly
echo "Testing direct API call to /v4/ping:\n";
$ch = curl_init('https://terminal.jup.ag/v4/ping');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . JUPITER_API_KEY
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_VERBOSE => true
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: ";
print_r(json_decode($response, true));

echo "\nTesting JupiterDataSource:\n";
$jupiter = new \NS\DataSources\JupiterDataSource();

echo "testConnection(): ";
var_dump($jupiter->testConnection());

echo "\ngetNewCoins(): ";
var_dump($jupiter->getNewCoins(1, 1500000, 1500000));
