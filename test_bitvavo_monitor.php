<?php
/**
 * Test script for Bitvavo Exchange Monitor
 * 
 * This script tests the coin evaluation functionality of the Exchange Price Monitor
 * with Bitvavo to verify our fixes resolved the PHP warnings.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/TradingLogger.php';
require_once __DIR__ . '/includes/exchanges/BitvavoHandler.php';

// Initialize logger
$logger = new TradingLogger();
$logger->logEvent("test_monitor", "startup", ["message" => "Starting Bitvavo test monitor"]);

// Configuration
$minMarketCap = 1500000; // Minimum market cap ($1.5M)
$minVolume24h = 1500000; // Minimum 24h volume ($1.5M)
$maxCoinAge = 24; // Maximum coin age in hours
$verbose = true;

echo "Starting Bitvavo test monitor...\n";

// Debug function to print API responses
function debugResponse($title, $data) {
    echo "\n===== {$title} =====\n";
    if (is_array($data)) {
        echo "Type: Array with " . count($data) . " items\n";
        if (!empty($data)) {
            echo "First item sample:\n";
            print_r(array_slice($data, 0, 1, true));
        }
    } else {
        echo "Type: " . gettype($data) . "\n";
        echo "Value: ";
        print_r($data);
    }
    echo "\n";
}

try {
    // Initialize Bitvavo handler
    $exchangeHandler = new BitvavoHandler();
    echo "Bitvavo handler initialized.\n";
    
    // Fetch available coins
    $availableCoins = $exchangeHandler->getAvailableCoins();
    echo "Found " . count($availableCoins) . " coins on Bitvavo.\n";
    debugResponse("Available Coins Sample", $availableCoins);
    
    // Get current prices
    $currentPrices = $exchangeHandler->getCurrentPrices();
    echo "Retrieved current prices for " . count($currentPrices) . " coins.\n";
    debugResponse("Current Prices Sample", $currentPrices);
    
    // Debug direct API calls to understand the metadata issue
    echo "\nDEBUG: Testing direct API calls...\n";
    
    // Test the ticker/24h endpoint - using non-authenticated call
    echo "Testing ticker/24h endpoint (non-authenticated)...\n";
    $tickerResponse = $exchangeHandler->debugApiCall('/ticker/24h', [], 'GET', false);
    debugResponse("Ticker 24h Response", $tickerResponse);
    
    // Test the assets endpoint
    echo "Testing assets endpoint...\n";
    $assetsResponse = $exchangeHandler->debugApiCall('/assets', [], 'GET', false);
    debugResponse("Assets Response", $assetsResponse);
    
    // Get coin metadata
    echo "\nFetching coin metadata...\n";
    $coinMetadata = $exchangeHandler->getCoinMetadata();
    echo "Retrieved metadata for " . count($coinMetadata) . " coins.\n";
    debugResponse("Coin Metadata Sample", $coinMetadata);
    
    // Evaluate coins based on criteria
    echo "\nEvaluating coins based on criteria:\n";
    echo "- Age < {$maxCoinAge} hours\n";
    echo "- Market Cap > $minMarketCap\n";
    echo "- 24h Volume > $minVolume24h\n\n";
    
    $potentialBuys = [];
    
    foreach ($currentPrices as $symbol => $currentPrice) {
        echo "Evaluating {$symbol} at price {$currentPrice}...\n";
        
        // Skip if no metadata available
        if (!isset($coinMetadata[$symbol])) {
            echo "  SKIP: No metadata available for {$symbol}\n";
            continue;
        }
        
        $metadata = $coinMetadata[$symbol];
        
        // Apply criteria
        // 1. Coin age less than 24h
        if (isset($metadata['age_hours']) && $metadata['age_hours'] > $maxCoinAge) {
            echo "  SKIP: {$symbol} too old ({$metadata['age_hours']} hours)\n";
            continue;
        }
        
        // 2. Market cap over $1.5M
        if (isset($metadata['market_cap']) && $metadata['market_cap'] < $minMarketCap) {
            echo "  SKIP: {$symbol} market cap too low ({$metadata['market_cap']})\n";
            continue;
        }
        
        // 3. 24h volume over $1.5M
        if (isset($metadata['volume_24h']) && $metadata['volume_24h'] < $minVolume24h) {
            echo "  SKIP: {$symbol} volume too low ({$metadata['volume_24h']})\n";
            continue;
        }
        
        // Coin meets all criteria
        echo "  ACCEPT: {$symbol} meets all criteria!\n";
        echo "    - Age: {$metadata['age_hours']} hours\n";
        echo "    - Market Cap: {$metadata['market_cap']}\n";
        echo "    - 24h Volume: {$metadata['volume_24h']}\n";
        
        $potentialBuys[$symbol] = [
            'price' => $currentPrice,
            'metadata' => $metadata
        ];
    }
    
    echo "\nFound " . count($potentialBuys) . " potential buys that meet all criteria.\n";
    
    if (!empty($potentialBuys)) {
        echo "\nPotential buys:\n";
        foreach ($potentialBuys as $symbol => $info) {
            echo "- {$symbol} at {$info['price']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    $logger->logEvent("test_monitor", "error", ["message" => "Error: " . $e->getMessage()]);
}

echo "\nTest completed.\n";
