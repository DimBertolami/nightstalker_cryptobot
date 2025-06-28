<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php'; // Add this line

echo "<h2>Testing CoinMarketCap API Connection</h2>";

try {
    $data = fetchFromCMC();
    echo "<pre>" . print_r($data, true) . "</pre>";
    echo "API Connection Successful!";
} catch (Exception $e) {
    echo "API Error: " . $e->getMessage();
}
