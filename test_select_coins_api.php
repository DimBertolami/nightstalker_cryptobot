<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test_select_coins_api.php...\n";

$url = 'http://localhost/NS/api/select-coins.php';

// Generate more dummy data for testing TA calculations
$dummy_data = [];
$start_timestamp = strtotime('2023-01-01');

for ($i = 0; $i < 10; $i++) { // Reduced periods to 10
    $timestamp = date('Y-m-d', strtotime("+$i days", $start_timestamp));
    $open = (float)rand(1000, 1100) + (rand(0, 100) / 100);
    $high = (float)rand($open, $open + 50) + (rand(0, 100) / 100);
    $low = (float)rand($open - 50, $open) + (rand(0, 100) / 100);
    $close = (float)rand($low, $high) + (rand(0, 100) / 100);
    $volume = rand(100000, 1000000);
    $market_cap = rand(100000000, 1000000000);
    $date_added = date('Y-m-d', strtotime('2022-12-15'));

    $dummy_data[] = [
        'timestamp' => $timestamp,
        'open' => $open,
        'high' => $high,
        'low' => $low,
        'close' => $close,
        'volume' => $volume,
        'market_cap' => $market_cap,
        'date_added' => $date_added,
        'symbol' => 'BTC'
    ];
}

// Add a second coin for testing
for ($i = 0; $i < 10; $i++) { // Reduced periods to 10
    $timestamp = date('Y-m-d', strtotime("+$i days", $start_timestamp));
    $open = (float)rand(50, 100) + (rand(0, 100) / 100);
    $high = (float)rand($open, $open + 10) + (rand(0, 100) / 100);
    $low = (float)rand($open - 10, $open) + (rand(0, 100) / 100);
    $close = (float)rand($low, $high) + (rand(0, 100) / 100);
    $volume = rand(50000, 500000);
    $market_cap = rand(10000000, 100000000);
    $date_added = date('Y-m-d', strtotime('2022-12-10'));

    $dummy_data[] = [
        'timestamp' => $timestamp,
        'open' => $open,
        'high' => $high,
        'low' => $low,
        'close' => $close,
        'volume' => $volume,
        'market_cap' => $market_cap,
        'date_added' => $date_added,
        'symbol' => 'ETH'
    ];
}

$payload = json_encode($dummy_data);

echo "Sending request to: " . $url . "\n";
echo "Payload size: " . strlen($payload) . " bytes\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Set a 120-second timeout

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_errno = curl_errno($ch);
$curl_error = curl_error($ch);

if ($curl_errno) {
    echo 'cURL Error (Code: ' . $curl_errno . '): ' . $curl_error . "\n";
} else {
    echo "HTTP Status Code: " . $http_code . "\n";
    echo "Response: " . $response . "\n";
}

curl_close($ch);

echo "Finished test_select_coins_api.php.\n";

?>