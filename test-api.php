<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Test the API connection and display the structure
echo "<h1>API Test Results</h1>";
echo "<pre>";

try {
    // Fetch data from CoinMarketCap API
    $marketData = fetchFromCMC();
    
    // Display the structure
    echo "Market Data Structure:\n";
    print_r($marketData);
    
    // Check for volume key specifically
    echo "\n\nVolume Data Check:\n";
    foreach ($marketData as $symbol => $data) {
        echo "$symbol Volume: " . ($data['volume'] ?? 'NOT FOUND') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "</pre>";
?>
