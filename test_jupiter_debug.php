<?php
// Debug script for Jupiter API
require_once __DIR__ . '/includes/SwapDataSourceInterface.php';
require_once __DIR__ . '/includes/JupiterDataSource.php';

use NS\DataSources\JupiterDataSource;

// Create Jupiter data source
$jupiter = new JupiterDataSource();

// Test parameters
$inputToken = 'So11111111111111111111111111111111111111112'; // SOL
$outputToken = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'; // USDC
$amount = 1;

try {
    // Get quote
    echo "Getting quote for $amount SOL to USDC...\n";
    $quote = $jupiter->getSwapQuote($inputToken, $outputToken, $amount);
    
    // Debug output
    echo "Quote Response:\n";
    echo json_encode($quote, JSON_PRETTY_PRINT);
    echo "\n\n";
    
    // Check for specific fields
    echo "Checking response structure:\n";
    echo "- outAmount exists: " . (isset($quote['outAmount']) ? "Yes" : "No") . "\n";
    if (isset($quote['outAmount'])) {
        echo "- outAmount type: " . gettype($quote['outAmount']) . "\n";
        echo "- outAmount value: " . $quote['outAmount'] . "\n";
    }
    
    echo "- priceImpactPct exists: " . (isset($quote['priceImpactPct']) ? "Yes" : "No") . "\n";
    echo "- routePlan exists: " . (isset($quote['routePlan']) ? "Yes" : "No") . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
