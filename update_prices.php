<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php'; // Include functions.php for logging

error_log("updateCoinPrices: Script started.");

function updateCoinPrices() {
    global $db; // Use global $db if getDBConnection() returns a global instance
    $db = getDBConnection();
    if (!$db) {
        error_log("updateCoinPrices: Failed to get DB connection.");
        return false;
    }

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
                             current_price = :current_price, 
                             price_change_24h = :price_change_24h 
                             WHERE symbol = :symbol");
        
        foreach ($prices as $symbol => $data) {
            $stmt->bindParam(':current_price', $data['price']);
            $stmt->bindParam(':price_change_24h', $data['change']);
            $stmt->bindParam(':symbol', $symbol);
            $stmt->execute();
            error_log("updateCoinPrices: Updated " . $symbol);
        }
        error_log("updateCoinPrices: All prices updated successfully.");
        return true;
    } catch (PDOException $e) {
        error_log("updateCoinPrices: PDOException - " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("updateCoinPrices: General Exception - " . $e->getMessage());
        return false;
    }
}

updateCoinPrices();

error_log("updateCoinPrices: Script finished.");