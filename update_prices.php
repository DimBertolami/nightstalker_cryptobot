<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

function updateCoinPrices() {
    $db = getDBConnection();
    if (!$db) return false;

    // In a real app, fetch from an API like:
    // $prices = fetchFromCoinMarketCap();
    
    // Example data - replace with real API call
    $prices = [
        'BTC' => ['price' => 60000.00, 'change' => 500.00],
        'ETH' => ['price' => 3000.00, 'change' => 30.00],
        // Add other coins
    ];

    try {
        $stmt = $db->prepare("UPDATE coins SET 
                             current_price = ?, 
                             price_change_24h = ? 
                             WHERE symbol = ?");
        
        foreach ($prices as $symbol => $data) {
            $stmt->bind_param("dds", $data['price'], $data['change'], $symbol);
            $stmt->execute();
        }
        return true;
    } catch (Exception $e) {
        error_log("Price update failed: " . $e->getMessage());
        return false;
    }
}

updateCoinPrices();
